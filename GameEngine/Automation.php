<?php

require_once __DIR__.'/CombatRanking.php';
require_once __DIR__.'/Hero.php';

class Automation {

    private $bountyresarray = array();
    private $bountyinfoarray = array();
    private $bountyproduction = array();
    private $bountyocounter = array();
    private $bountyunitall = array();
    private $bountypop;
    private $bountyOresarray = array();
    private $bountyOinfoarray = array();
    private $bountyOproduction = array();
    private $bountyOpop = 1;

    public function isWinner() {
        $q = mysql_query("SELECT vref FROM ".TB_PREFIX."fdata WHERE f99 = '100' and f99t = '40'");
        $isThere = mysql_num_rows($q);
        if($isThere > 0) {
            header('Location: '.SERVER.'winner.php');
            die();
        }
    }

// @formatter:off
    public function procResType($ref) {
        global $session;
        switch($ref) {
            case 1: $build = "Leñador"; break;
            case 2: $build = "Barrera"; break;
            case 3: $build = "Mina de hierro"; break;
            case 4: $build = "Granja"; break;
            case 5: $build = "Aserradero"; break;
            case 6: $build = "Fábrica de ladrillos"; break;
            case 7: $build = "Fundición de hierro"; break;
            case 8: $build = "Molino"; break;
            case 9: $build = "Panadería"; break;
            case 10: $build = "Almacén"; break;
            case 11: $build = "Granero"; break;
            case 12: $build = "Herrería"; break;
            case 14: $build = "Plaza de torneos"; break;
            case 15: $build = "Edificio principal"; break;
            case 16: $build = "Plaza de reuniones"; break;
            case 17: $build = "Mercado"; break;
            case 18: $build = "Embajada"; break;
            case 19: $build = "Cuartel"; break;
            case 20: $build = "Establo"; break;
            case 21: $build = "Taller"; break;
            case 22: $build = "Academia"; break;
            case 23: $build = "Escondite"; break;
            case 24: $build = "Ayuntamiento"; break;
            case 25: $build = "Residencia"; break;
            case 26: $build = "Palacio"; break;
            case 27: $build = "Tesorería"; break;
            case 28: $build = "Oficina de comercio"; break;
            case 29: $build = "Gran cuartel"; break;
            case 30: $build = "Gran establo"; break;
            case 31: $build = "Muralla"; break;
            case 32: $build = "Muro de tierra"; break;
            case 33: $build = "Empalizada"; break;
            case 34: $build = "Taller de cantería"; break;
            case 35: $build = "Cervecería"; break;
            case 36: $build = "Trampero"; break;
            case 37: $build = "Mansión del héroe"; break;
            case 38: $build = "Gran almacén"; break;
            case 39: $build = "Gran granero"; break;
            case 40: $build = "Maravilla del mundo"; break;
            case 41: $build = "Abrevadero"; break;
            case 42: $build = "Gran taller"; break;
            default: $build = "Nothing had"; break;
        }
        return addslashes($build);
    }
// @formatter:on

    private function isAllowedCatapultTargetType($target, $allowRandomSentinel = false) {
        $target = (int)$target;
        if($allowRandomSentinel && $target === 99) {
            return true;
        }
        return in_array($target, array(
            0,
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
            14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
            25, 26, 27, 28, 29, 30, 34, 35, 36, 37, 38,
            39, 40, 41, 42
        ), true);
    }

    public function selectCatapultTargetSlot($fields, $requestedType) {
        if(!is_array($fields)) {
            return 0;
        }

        $requestedType = (int)$requestedType;
        $occupied = array();
        $matching = array();
        $slots = array_merge(range(1, 40), array(99));
        foreach($slots as $slot) {
            $levelKey = 'f'.$slot;
            $typeKey = $levelKey.'t';
            if(!isset($fields[$levelKey]) || (int)$fields[$levelKey] <= 0) {
                continue;
            }
            $occupied[] = $slot;
            if($requestedType > 0 && $requestedType !== 99
                && isset($fields[$typeKey]) && (int)$fields[$typeKey] === $requestedType) {
                $matching[] = $slot;
            }
        }

        $candidates = !empty($matching) ? $matching : $occupied;
        if(empty($candidates)) {
            return 0;
        }
        return (int)$candidates[array_rand($candidates)];
    }

    /**
     * Capacidad que aporta un edificio de almacenamiento en un nivel dado,
     * ya escalada por STORAGE_MULTIPLIER igual que updateStore().
     */
    private function storageBuildingAttribute($buildingType, $level) {
        $buildingType = (int)$buildingType;
        $level = max(0, (int)$level);
        $dataName = 'bid'.$buildingType;
        if(!isset($GLOBALS[$dataName]) || !is_array($GLOBALS[$dataName])
            || !isset($GLOBALS[$dataName][$level]['attri'])) {
            return 0;
        }
        return (float)$GLOBALS[$dataName][$level]['attri'] * $this->storageMultiplier();
    }

    private function storageMultiplier() {
        return defined('STORAGE_MULTIPLIER') ? (float)STORAGE_MULTIPLIER : 1;
    }

    private function storageBase() {
        return defined('STORAGE_BASE') ? (float)STORAGE_BASE : 800 * $this->storageMultiplier();
    }

    /**
     * Aplica a maxstore/maxcrop el cambio de capacidad al pasar un almacén,
     * granero, gran almacén o gran granero de un nivel a otro.
     */
    private function applyStorageCapacityDelta($villageId, $buildingType, $oldLevel, $newLevel) {
        global $database;
        $buildingType = (int)$buildingType;
        if(!in_array($buildingType, array(10, 11, 38, 39), true)) {
            return;
        }

        $village = $database->getVillage((int)$villageId);
        if(!is_array($village)) {
            return;
        }
        $column = in_array($buildingType, array(10, 38), true) ? 'maxstore' : 'maxcrop';
        $base = $this->storageBase();
        $capacity = (float)$village[$column];
        // la capacidad base sólo cuenta mientras la aldea no tenga ningún
        // edificio de esta clase de almacenamiento, así que el primero la reemplaza
        if((int)$oldLevel <= 0 && $capacity <= $base) {
            $capacity = 0;
        }
        $capacity += $this->storageBuildingAttribute($buildingType, $newLevel)
            - $this->storageBuildingAttribute($buildingType, $oldLevel);
        $database->setVillageField((int)$villageId, $column, max($base, $capacity));
    }

    private function refreshCatapultEmbassyCapacity($villageId) {
        global $database, $bid18;
        $leader = (int)$database->getVillageField((int)$villageId, 'owner');
        if($leader <= 0) {
            return;
        }

        $maximum = 0;
        foreach($database->getVillagesID2($leader) as $village) {
            $fields = $database->getResourceLevel((int)$village['wref']);
            if(!is_array($fields)) {
                continue;
            }
            for($slot = 19; $slot <= 40; $slot++) {
                if((int)$fields['f'.$slot.'t'] !== 18) {
                    continue;
                }
                $level = max(0, (int)$fields['f'.$slot]);
                $attribute = isset($bid18[$level]['attri']) ? (int)$bid18[$level]['attri'] : 0;
                $maximum = max($maximum, $attribute);
            }
        }
        $database->query("UPDATE ".TB_PREFIX."alidata SET max = ".$maximum." WHERE leader = ".$leader);
    }

    private function destroyCatapultedVillage($villageId, $owner, $capital) {
        global $database, $logging;
        $villageId = (int)$villageId;
        $owner = (int)$owner;
        if($villageId <= 0 || $owner <= 0 || (int)$capital === 1
            || count($database->getProfileVillages($owner)) === 1) {
            return false;
        }

        $database->query("DELETE FROM ".TB_PREFIX."abdata WHERE wref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."bdata WHERE wid = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."enforcement WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."fdata WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."market WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."movement WHERE `to` = ".$villageId." OR `from` = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."odata WHERE wref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."research WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."tdata WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."training WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."units WHERE vref = ".$villageId);
        $database->query("DELETE FROM ".TB_PREFIX."vdata WHERE wref = ".$villageId);
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id = ".$villageId);
        $logging->VillageDestroyCatalog($villageId);
        return true;
    }

    private function applyCatapultImpact($villageId, $requestedType, $firingPower, $upgradeLevel, $moralBonus, $stonemasonLevel, $targetVillage) {
        global $database, $battle, $bid34;
        $fields = $database->getResourceLevel((int)$villageId);
        $slot = $this->selectCatapultTargetSlot($fields, $requestedType);
        if($slot <= 0) {
            return null;
        }

        $oldLevel = (int)$fields['f'.$slot];
        $buildingType = (int)$fields['f'.$slot.'t'];
        if($oldLevel <= 0 || $buildingType <= 0) {
            return null;
        }

        $durability = 1;
        if($slot >= 19 && (int)$stonemasonLevel > 0 && isset($bid34[(int)$stonemasonLevel]['attri'])) {
            $durability = max(1, (float)$bid34[(int)$stonemasonLevel]['attri'] / 100);
        }
        $outcome = $battle->calculateSiegeOutcome(
            $firingPower,
            $oldLevel,
            $upgradeLevel,
            $moralBonus,
            $durability
        );
        $newLevel = max(0, (int)$outcome['level_after']);
        $name = $this->procResType($buildingType);
        $destroyedVillage = false;

        if($newLevel < $oldLevel) {
            $database->setVillageLevel((int)$villageId, 'f'.$slot, $newLevel);
            if($slot >= 19 && $newLevel === 0) {
                $database->setVillageLevel((int)$villageId, 'f'.$slot.'t', 0);
            }
            $this->applyStorageCapacityDelta($villageId, $buildingType, $oldLevel, $newLevel);
            if($buildingType === 18) {
                $this->refreshCatapultEmbassyCapacity($villageId);
            }
            $population = $this->recountPop((int)$villageId);
            if((int)$population === 0) {
                $destroyedVillage = $this->destroyCatapultedVillage(
                    $villageId,
                    isset($targetVillage['owner']) ? $targetVillage['owner'] : 0,
                    isset($targetVillage['capital']) ? $targetVillage['capital'] : 0
                );
            }
        }

        if($newLevel === 0 && $oldLevel > 0) {
            $message = $name.' destruido.';
        } elseif($newLevel < $oldLevel) {
            $message = $name.' dañado del nivel <b>'.$oldLevel.'</b> al nivel <b>'.$newLevel.'</b>.';
        } else {
            $message = $name.' no sufrió daños.';
        }

        return array(
            'building_type' => $buildingType,
            'message' => $message,
            'village_destroyed' => $destroyedVillage
        );
    }

    private function resolveCatapultAttacks($data, $battleResult, $stonemasonLevel, $targetVillage, $breweryLevel) {
        $firstTarget = isset($data['ctar1']) && $this->isAllowedCatapultTargetType($data['ctar1'])
            ? (int)$data['ctar1']
            : 0;
        $secondTarget = isset($data['ctar2']) && $this->isAllowedCatapultTargetType($data['ctar2'], true)
            ? (int)$data['ctar2']
            : 0;
        if((int)$breweryLevel > 0) {
            $firstTarget = 0;
            $secondTarget = 0;
        }

        $targets = array($firstTarget);
        if($secondTarget !== 0) {
            $targets[] = $secondTarget;
        }
        $firingPower = max(0, (float)$battleResult[4]) / count($targets);
        $messages = array();
        $reportBuildingType = 0;
        $villageDestroyed = false;
        foreach($targets as $target) {
            $impact = $this->applyCatapultImpact(
                (int)$data['to'],
                $target,
                $firingPower,
                isset($battleResult[6]) ? (int)$battleResult[6] : 0,
                isset($battleResult[5]) ? (float)$battleResult[5] : 1,
                $stonemasonLevel,
                $targetVillage
            );
            if($impact === null) {
                continue;
            }
            if($reportBuildingType === 0) {
                $reportBuildingType = (int)$impact['building_type'];
            }
            $messages[] = $impact['message'];
            if($impact['village_destroyed']) {
                $villageDestroyed = true;
                break;
            }
        }

        return array(
            'report' => $reportBuildingType > 0 && !empty($messages)
                ? $reportBuildingType.','.implode('<br>', $messages)
                : ',',
            'village_destroyed' => $villageDestroyed
        );
    }

    function recountPop($vid) {
        global $database;
        $fdata = $database->getResourceLevel($vid);
        $popTot = 0;

        for ($i = 1; $i <= 40; $i++) {
            $lvl = $fdata["f".$i];
            $building = $fdata["f".$i."t"];
            if($building) {
                $popTot += $this->buildingPOP($building, $lvl);
            }
        }
        $this->recountCP($vid);
        $q = "UPDATE ".TB_PREFIX."vdata set pop = $popTot where wref = $vid";
        mysql_query($q);
        $owner = $database->getVillageField($vid, "owner");
        $this->procClimbers($owner);

        return $popTot;

    }

    function recountCP($vid) {
        global $database;
        $fdata = $database->getResourceLevel($vid);
        $popTot = 0;

        for ($i = 1; $i <= 40; $i++) {
            $lvl = $fdata["f".$i];
            $building = $fdata["f".$i."t"];
            if($building) {
                $popTot += $this->buildingCP($building, $lvl);
            }
        }

        $q = "UPDATE ".TB_PREFIX."vdata set cp = $popTot where wref = $vid";
        mysql_query($q);

        return $popTot;

    }

    function buildingPOP($f, $lvl) {
        $name = "bid".$f;
        global $$name;
        $popT = 0;
        $dataarray = $$name;

        for ($i = 0; $i <= $lvl; $i++) {
            if(isset($dataarray[$i]['pop'])) {
                $popT += $dataarray[$i]['pop'];
            }
        }
        return $popT;
    }

    function buildingCP($f, $lvl) {
        $name = "bid".$f;
        global $$name;
        $popT = 0;
        $dataarray = $$name;

        for ($i = 0; $i <= $lvl; $i++) {
            if(isset($dataarray[$i]['cp'])) {
                $popT += $dataarray[$i]['cp'];
            }
        }
        return $popT;
    }

    public function hasOrdinaryTroopReturnInEvasionWindow($database, $villageId, $attackArrivalTime, $windowSeconds = 10) {
        $villageId = (int)$villageId;
        $attackArrivalTime = (int)$attackArrivalTime;
        $windowSeconds = max(0, (int)$windowSeconds);
        if($villageId <= 0 || $attackArrivalTime <= 0) {
            return false;
        }

        $returns = $database->getOrdinaryTroopReturnsInWindow(
            $villageId,
            $attackArrivalTime - $windowSeconds,
            $attackArrivalTime
        );
        return is_array($returns) && count($returns) > 0;
    }

    public function buildTroopEvasionPayload($defenderUnits, $tribe) {
        $payload = array_fill(1, 10, 0);
        $tribe = (int)$tribe;
        if(!is_array($defenderUnits) || $tribe < 1 || $tribe > 5) {
            return $payload;
        }

        $playerUnit = ($tribe - 1) * 10;
        for($position = 1; $position <= 10; $position++) {
            $unitKey = 'u'.($playerUnit + $position);
            $payload[$position] = isset($defenderUnits[$unitKey])
                ? max(0, (int)$defenderUnits[$unitKey])
                : 0;
        }
        return $payload;
    }

    public function calculateCrannyProtection($buildings, $attackerTribe, $defenderTribe, $capacityMultiplier = null) {
        global $bid23;

        $nominal = 0.0;
        if(is_array($buildings) && is_array($bid23)) {
            for($field = 19; $field <= 38; $field++) {
                $typeKey = 'f'.$field.'t';
                $levelKey = 'f'.$field;
                if(!isset($buildings[$typeKey], $buildings[$levelKey])
                    || (int)$buildings[$typeKey] !== 23) {
                    continue;
                }
                $level = (int)$buildings[$levelKey];
                if($level > 0 && isset($bid23[$level]['attri'])) {
                    $nominal += max(0, (float)$bid23[$level]['attri']);
                }
            }
        }

        if($capacityMultiplier === null) {
            $capacityMultiplier = defined('CRANNY_CAPACITY') ? CRANNY_CAPACITY : 1;
        }
        $nominal *= max(0, (float)$capacityMultiplier);

        // German attackers reduce enemy cranny protection to 80%.
        $attackerFactor = (int)$attackerTribe === 2 ? 0.8 : 1.0;
        $capacity = $nominal;

        return array(
            'nominal' => $nominal,
            'capacity' => $capacity,
            'protected' => $capacity * $attackerFactor
        );
    }

    public function __construct($marketOnly = false) {
        if($marketOnly) {
            $this->marketComplete();
            return;
        }
        if(!file_exists("GameEngine/Prevention/cleardeleting.txt") or time() - filemtime("GameEngine/Prevention/cleardeleting.txt") > 50) {
            $this->clearDeleting();
        }
        $this->procNewClimbers();
        $this->ClearUser();
        $this->ClearInactive();
        $this->oasisResourcesProduce();
        $this->pruneResource();
        $this->pruneOResource();
        $this->addAdventures();
        $this->checkWWAttacks();
        if(!file_exists("GameEngine/Prevention/loyalty.txt") or time() - filemtime("GameEngine/Prevention/loyalty.txt") > 50) {
            $this->loyaltyRegeneration();
        }
        if(!file_exists("GameEngine/Prevention/updatehero.txt") or time() - filemtime("GameEngine/Prevention/updatehero.txt") > 50) {
            $this->updateHero();
        }
        if(!file_exists("GameEngine/Prevention/celebration.txt") or time() - filemtime("GameEngine/Prevention/celebration.txt") > 50) {
            $this->celebrationComplete();
        }
        if(!file_exists("GameEngine/Prevention/culturepoints.txt") or time() - filemtime("GameEngine/Prevention/culturepoints.txt") > 50) {
            $this->culturePoints();
        }
        if(!file_exists("GameEngine/Prevention/research.txt") or time() - filemtime("GameEngine/Prevention/research.txt") > 50) {
            $this->researchComplete();
        }
        if(!file_exists("GameEngine/Prevention/starvation.txt") or time() - filemtime("GameEngine/Prevention/starvation.txt") > 50) {
            $this->starvation();
        }
        $buildSweepDue = !file_exists("GameEngine/Prevention/build.txt")
            || time() - filemtime("GameEngine/Prevention/build.txt") > 50;
        $attackSweepDue = !file_exists("GameEngine/Prevention/sendunits.txt")
            || time() - filemtime("GameEngine/Prevention/sendunits.txt") > 50;
        $pendingAttackTime = null;
        if($buildSweepDue) {
            $pendingAttackTime = $this->nextPendingAttackArrival();
            $this->buildComplete($pendingAttackTime);
        }
        $this->MasterBuilder();
        if(!file_exists("GameEngine/Prevention/auction.txt") or time() - filemtime("GameEngine/Prevention/auction.txt") > 50) {
            $this->auctionComplete();
        }
        if(!file_exists("GameEngine/Prevention/market.txt") or time() - filemtime("GameEngine/Prevention/market.txt") > 50) {
            $this->marketComplete();
        }
        if(!file_exists("GameEngine/Prevention/training.txt") or time() - filemtime("GameEngine/Prevention/training.txt") > 50) {
            $this->trainingComplete();
        }
        if(!file_exists("GameEngine/Prevention/sendreinfunits.txt") or time() - filemtime("GameEngine/Prevention/sendreinfunits.txt") > 50) {
            $this->sendreinfunitsComplete();
        }
        if(!file_exists("GameEngine/Prevention/returnunits.txt") or time() - filemtime("GameEngine/Prevention/returnunits.txt") > 50) {
            $this->returnunitsComplete();
        }
        if(!file_exists("GameEngine/Prevention/settlers.txt") or time() - filemtime("GameEngine/Prevention/settlers.txt") > 50) {
            $this->sendSettlersComplete();
        }
        if(!file_exists("GameEngine/Prevention/adventures.txt") or time() - filemtime("GameEngine/Prevention/adventures.txt") > 50) {
            $this->sendAdventuresComplete();
        }
        if(!file_exists("GameEngine/Prevention/demolition.txt") or time() - filemtime("GameEngine/Prevention/demolition.txt") > 50) {
            $this->demolitionComplete();
        }
        if($attackSweepDue || $buildSweepDue) {
            $this->sendunitsComplete();
        }
        if($buildSweepDue && $pendingAttackTime !== null) {
            $this->buildComplete();
        }
        $this->updateStore();
        $this->TradeRoute();
        $this->regenerateOasisTroops();
        $this->weeklyMedals();
    }

    private function getfieldDistance($coorx1, $coory1, $coorx2, $coory2) {
        $max = 2 * WORLD_MAX + 1;
        $x1 = intval($coorx1);
        $y1 = intval($coory1);
        $x2 = intval($coorx2);
        $y2 = intval($coory2);
        $distanceX = min(abs($x2 - $x1), abs($max - abs($x2 - $x1)));
        $distanceY = min(abs($y2 - $y1), abs($max - abs($y2 - $y1)));
        $dist = sqrt(pow($distanceX, 2) + pow($distanceY, 2));
        return round($dist, 1);
    }

    /**
     * Decides what a successful hero raid does to the attacked oasis. Pure: the
     * caller reads the state and applies the resulting database changes.
     *
     * $village: wref, x, y, mansion (hero's mansion level), oases (already held).
     * $oasis:   x, y, conqured (holding village, 0 when free), loyalty,
     *           holder_oases (oases held by the village that owns it).
     *
     * A free oasis falls with a single raid; one held by a player needs 1, 2 or 3
     * raids depending on how many oases its holder has (3 -> 1, 2 -> 2, 1 -> 3).
     */
    public function oasisAnnexationOutcome($village, $oasis) {
        $result = array(
            'status' => '',
            'loyalty' => (int)$oasis['loyalty'],
            'needed_mansion' => (int)$village['oases'] * 5 + 10
        );

        $worldSize = 2 * WORLD_MAX + 1;
        $distanceX = abs((int)$oasis['x'] - (int)$village['x']);
        $distanceY = abs((int)$oasis['y'] - (int)$village['y']);
        $distanceX = min($distanceX, $worldSize - $distanceX);
        $distanceY = min($distanceY, $worldSize - $distanceY);

        if($distanceX > 3 || $distanceY > 3) {
            $result['status'] = 'out_of_range';
            return $result;
        }
        if((int)$oasis['conqured'] !== 0 && (int)$oasis['conqured'] === (int)$village['wref']) {
            $result['status'] = 'already_owned';
            return $result;
        }
        if((int)$village['oases'] >= 3) {
            $result['status'] = 'oasis_limit';
            return $result;
        }
        if((int)$village['mansion'] < $result['needed_mansion']) {
            $result['status'] = 'mansion_too_low';
            return $result;
        }
        if((int)$oasis['conqured'] === 0) {
            $result['status'] = 'conquered';
            $result['loyalty'] = 100;
            return $result;
        }

        // ceil() so three raids are always enough (100 -> 66 -> 32 -> taken).
        $loyaltyDamage = (int)ceil(100 / min(3, max(1, 4 - (int)$oasis['holder_oases'])));
        $loyalty = (int)$oasis['loyalty'] - $loyaltyDamage;
        if($loyalty <= 0) {
            $result['status'] = 'conquered';
            $result['loyalty'] = 100;
        } else {
            $result['status'] = 'loyalty_reduced';
            $result['loyalty'] = $loyalty;
        }
        return $result;
    }

    public function getTypeLevel($tid, $vid) {
        global $village, $database;
        $keyholder = array();

        $resourcearray = $database->getResourceLevel($vid);

        foreach (array_keys($resourcearray, $tid) as $key) {
            if(strpos($key, 't')) {
                $key = preg_replace("/[^0-9]/", '', $key);
                array_push($keyholder, $key);
            }
        }
        $element = count($keyholder);
        if($element >= 2) {
            if($tid <= 4) {
                $temparray = array();
                for ($i = 0; $i <= $element - 1; $i++) {
                    array_push($temparray, $resourcearray['f'.$keyholder[$i]]);
                }
                foreach ($temparray as $key => $val) {
                    if($val == max($temparray))
                        $target = $key;
                }
            } else {
                $target = 0;
                for ($i = 1; $i <= $element - 1; $i++) {
                    if($resourcearray['f'.$keyholder[$i]] > $resourcearray['f'.$keyholder[$target]]) {
                        $target = $i;
                    }
                }
            }
        } else if($element == 1) {
            $target = 0;
        } else {
            return 0;
        }
        if($keyholder[$target] != "") {
            return $resourcearray['f'.$keyholder[$target]];
        } else {
            return 0;
        }
    }


    private function loyaltyRegeneration() {
        if(file_exists("GameEngine/Prevention/loyalty.txt")) {
            @unlink("GameEngine/Prevention/loyalty.txt");
        }
        global $database;
        $ourFileHandle = fopen("GameEngine/Prevention/loyalty.txt", 'w');
        fclose($ourFileHandle);
        $array = array();
        $q = "SELECT * FROM ".TB_PREFIX."vdata WHERE loyalty < 100";
        $array = $database->query_return($q);
        if(!empty($array)) {
            foreach ($array as $loyalty) {
                if($this->getTypeLevel(25, $loyalty['wref']) >= 1) {
                    $value = $this->getTypeLevel(25, $loyalty['wref']);
                } elseif($this->getTypeLevel(26, $loyalty['wref']) >= 1) {
                    $value = $this->getTypeLevel(26, $loyalty['wref']);
                } else {
                    $value = 0;
                }
                $newloyalty = min(100, $loyalty['loyalty'] + $value * (time() - $loyalty['lastupdate']) * SPEED / (60 * 60));
                $q = "UPDATE ".TB_PREFIX."vdata SET loyalty = $newloyalty WHERE wref = '".$loyalty['wref']."'";
                $database->query($q);
            }
        }
        $array = array();
        // Occupied oases regenerate loyalty like villages do, driven by the
        // residence/palace of the village holding them. odata has no `lastupdate`
        // column: `lastupdated2` is the loyalty clock here (on free oases the same
        // column times the animal respawn instead), and it is reset on every
        // change so the elapsed time never counts twice.
        $q = "SELECT * FROM ".TB_PREFIX."odata WHERE loyalty < 100 AND conqured <> 0";
        $array = $database->query_return($q);
        if(!empty($array)) {
            foreach ($array as $loyalty) {
                if($this->getTypeLevel(25, $loyalty['conqured']) >= 1) {
                    $value = $this->getTypeLevel(25, $loyalty['conqured']);
                } elseif($this->getTypeLevel(26, $loyalty['conqured']) >= 1) {
                    $value = $this->getTypeLevel(26, $loyalty['conqured']);
                } else {
                    $value = 0;
                }
                $newloyalty = round(min(100, $loyalty['loyalty'] + $value * (time() - $loyalty['lastupdated2']) * SPEED / (60 * 60)));
                $q = "UPDATE ".TB_PREFIX."odata SET loyalty = $newloyalty, lastupdated2 = ".time()." WHERE wref = '".$loyalty['wref']."'";
                $database->query($q);
            }
        }

        $array2 = array();
        $q2 = "SELECT * FROM ".TB_PREFIX."vdata WHERE loyalty>125";
        $array2 = $database->query_return($q2);
        if(!empty($array2)) {
            foreach ($array2 as $loyalty) {
                $q = "UPDATE ".TB_PREFIX."vdata SET loyalty = 125 WHERE wref = '".$loyalty['wref']."'";
                $database->query($q);
            }
        }
        if(file_exists("GameEngine/Prevention/loyalty.txt")) {
            @unlink("GameEngine/Prevention/loyalty.txt");
        }
    }

    private function clearDeleting() {
        if(file_exists("GameEngine/Prevention/cleardeleting.txt")) {
            @unlink("GameEngine/Prevention/cleardeleting.txt");
        }
        global $database;
        $ourFileHandle = fopen("GameEngine/Prevention/cleardeleting.txt", 'w');
        fclose($ourFileHandle);
        $needDelete = $database->getNeedDelete();
        if(count($needDelete) > 0) {
            foreach ($needDelete as $need) {
                $needVillage = $database->getVillagesID($need['uid']);
                foreach ($needVillage as $village) {
                    $getvillage = $database->getVillage($village);
                    $q = "DELETE FROM ".TB_PREFIX."abdata where wref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."bdata where wid = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."enforcement where from = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."fdata where vref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."market where vref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."odata where wref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."research where vref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."tdata where vref = ".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."training where vref =".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."farmlist where wref =".$village;
                    $database->query($q);
                    $q = "DELETE FROM ".TB_PREFIX."raidlist where towref = ".$village;
                    $database->query($q);
                    $getmovement = $database->getMovement(3, $village, 1);
                    foreach ($getmovement as $movedata) {
                        $time = time();
                        $time2 = $time - $movedata['starttime'];
                        $database->addMovement(4, $movedata['to'], $movedata['from'], $movedata['ref'], $time, $time + $time2);
                        $database->setMovementProc($movedata['moveid']);
                    }
                    $q = "DELETE FROM ".TB_PREFIX."movement where from = ".$village;
                    $database->query($q);
                    $getprisoners = $database->getPrisoners($village);
                    foreach ($getprisoners as $pris) {
                        $owner = (int)$database->getVillageField($pris['from'], "owner");
                        $tribe = (int)$database->getUserField($owner, "tribe", 0);
                        $survivors = array();
                        for ($i = 1; $i <= 11; $i++) {
                            $survivors[$i] = max(0, (int)$pris['t'.$i]);
                        }
                        $this->queueFreedPrisonerReturn($pris, $owner, $tribe, $survivors);
                    }
                    $getprisoners = $database->getPrisoners3($village);
                    foreach ($getprisoners as $pris) {
                        $database->disbandPrisonersAtomic(
                            (int)$pris['id'],
                            (int)$pris['wref'],
                            (int)$pris['from'],
                            (int)$need['uid']
                        );
                    }
                    $q = "DELETE FROM ".TB_PREFIX."units where vref =".$village;
                    $database->query($q);
                    $enforcement = $database->getEnforceVillage($village, 0);
                    foreach ($enforcement as $enforce) {
                        $time = time();
                        $fromcoor = $database->getCoor($enforce['vref']);
                        $tocoor = $database->getCoor($enforce['from']);
                        $targettribe = $database->getUserField($database->getVillageField($enforce['from'], "owner"), "tribe", 0);
                        $time2 = $this->procDistanceTime($tocoor, $fromcoor, $targettribe, 0);
                        $start = 10 * ($targettribe - 1);
                        for ($i = 1; $i < 11; $i++) {
                            $unit = $start + $i;
                            $post['t'.$i] = $enforce['u'.$unit];
                        }
                        $post['t11'] = $enforce['hero'];
                        $reference = $database->addAttack($enforce['from'], $post['t1'], $post['t2'], $post['t3'], $post['t4'], $post['t5'], $post['t6'], $post['t7'], $post['t8'], $post['t9'], $post['t10'], $post['t11'], 2, 0, 0, 0, 0);
                        $database->addMovement(4, $enforce['vref'], $enforce['from'], $reference, $time, $time + $time2);
                        $q = "DELETE FROM ".TB_PREFIX."enforcement where id = ".$enforce['id'];
                        $database->query($q);
                    }
                }
                for ($i = 0; $i < 20; $i++) {
                    $q = "SELECT * FROM ".TB_PREFIX."users where friend".$i." = ".$need['uid']." or friend".$i."wait = ".$need['uid']."";
                    $array = $database->query_return($q);
                    foreach ($array as $friend) {
                        $database->deleteFriend($friend['id'], "friend".$i);
                        $database->deleteFriend($friend['id'], "friend".$i."wait");
                    }
                }
                $database->updateUserField($need['uid'], 'alliance', 0, 1);
                if($database->isAllianceOwner($need['uid'])) {
                    $alliance = $database->getUserAllianceID($need['uid']);
                    $newowner = $database->getAllMember2($alliance);
                    $newleader = $newowner['id'];
                    $q = "UPDATE ".TB_PREFIX."alidata set leader = ".$newleader." where id = ".$alliance."";
                    $database->query($q);
                    $database->updateAlliPermissions($newleader, $alliance, "Leader", 1, 1, 1, 1, 1, 1, 1);
                    $villages = $database->getVillagesID2($newleader);
                    $max = 0;
                    foreach ($villages as $village) {
                        $field = $database->getResourceLevel($village['wref']);
                        for ($i = 19; $i <= 40; $i++) {
                            if($field['f'.$i.'t'] == 18) {
                                $level = $field['f'.$i];
                                $attri = $bid18[$level]['attri'];
                            }
                        }
                        if($attri > $max) {
                            $max = $attri;
                        }
                    }
                    $q = "UPDATE ".TB_PREFIX."alidata set max = $max where leader = $newleader";
                    $database->query($q);
                }
                $database->deleteAlliance($alliance);
                $q = "DELETE FROM ".TB_PREFIX."hero where uid = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."mdata where target = ".$need['uid']." or owner = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."ndata where uid = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."users where id = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."deleting where uid = ".$need['uid'];
                $database->query($q);
                if($getvillage['capital'] == 0) {
                    $q = "DELETE FROM ".TB_PREFIX."vdata where wref = ".$village;
                    $database->query($q);
                    $q = "UPDATE ".TB_PREFIX."wdata set occupied = 0 where id = ".$village;
                    $database->query($q);
                } else {
                    $q = "UPDATE ".TB_PREFIX."vdata set capital = 0, owner = 2 where id = ".$village;
                    $database->query($q);
                    $database->addTech($village);
                    $database->addABTech($village);
                    $database->addUnits($village);
                }
                $q = "DELETE FROM ".TB_PREFIX."mdata where target = ".$need['uid']." or owner = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."ndata where uid = ".$need['uid'];
                $database->query($q);
                $q = "DELETE FROM ".TB_PREFIX."users where id = ".$need['uid'];
                $database->query($q);
            }
        }
        if(file_exists("GameEngine/Prevention/cleardeleting.txt")) {
            @unlink("GameEngine/Prevention/cleardeleting.txt");
        }
    }

    private function ClearUser() {
        global $database;
        if(AUTO_DEL_INACTIVE) {
            $time = time() + UN_ACT_TIME;
            $q = "DELETE from ".TB_PREFIX."users where timestamp >= $time and act != ''";
            $database->query($q);
        }
    }

    private function ClearInactive() {
        global $database;
        if(TRACK_USR) {
            $timeout = time() - USER_TIMEOUT * 60;
            $q = "DELETE FROM ".TB_PREFIX."active WHERE timestamp < $timeout";
            $database->query($q);
        }
    }

    private function pruneOResource() {
        global $database;
        if(!ALLOW_BURST) {
            $q = "SELECT * FROM ".TB_PREFIX."odata WHERE maxstore < 800 OR maxcrop < 800";
            $array = $database->query_return($q);
            foreach ($array as $getoasis) {
                if($getoasis['maxstore'] < 800) {
                    $maxstore = 800;
                } else {
                    $maxstore = $getoasis['maxstore'];
                }
                if($getoasis['maxcrop'] < 800) {
                    $maxcrop = 800;
                } else {
                    $maxcrop = $getoasis['maxcrop'];
                }
                $q = "UPDATE ".TB_PREFIX."odata set maxstore = $maxstore, maxcrop = $maxcrop where wref = ".$getoasis['wref']."";
                $database->query($q);
            }
            $q = "SELECT * FROM ".TB_PREFIX."odata WHERE wood < 0 OR clay < 0 OR iron < 0 OR crop < 0";
            $array = $database->query_return($q);
            foreach ($array as $getoasis) {
                if($getoasis['wood'] < 0) {
                    $wood = 0;
                } else {
                    $wood = $getoasis['wood'];
                }
                if($getoasis['clay'] < 0) {
                    $clay = 0;
                } else {
                    $clay = $getoasis['clay'];
                }
                if($getoasis['iron'] < 0) {
                    $iron = 0;
                } else {
                    $iron = $getoasis['iron'];
                }
                if($getoasis['crop'] < 0) {
                    $crop = 0;
                } else {
                    $crop = $getoasis['crop'];
                }
                $q = "UPDATE ".TB_PREFIX."odata set wood = $wood, clay = $clay, iron = $iron, crop = $crop where wref = ".$getoasis['wref']."";
                $database->query($q);
            }
        }
    }

    private function pruneResource() {
        global $database;
        if(!ALLOW_BURST) {
            $q = "SELECT * FROM ".TB_PREFIX."vdata WHERE maxstore < 800 OR maxcrop < 800";
            $array = $database->query_return($q);
            foreach ($array as $getvillage) {
                if($getvillage['maxstore'] < 800) {
                    $maxstore = 800;
                } else {
                    $maxstore = $getvillage['maxstore'];
                }
                if($getvillage['maxcrop'] < 800) {
                    $maxcrop = 800;
                } else {
                    $maxcrop = $getvillage['maxcrop'];
                }
                $q = "UPDATE ".TB_PREFIX."vdata set maxstore = $maxstore, maxcrop = $maxcrop where wref = ".$getvillage['wref']."";
                $database->query($q);
            }
            $q = "SELECT * FROM ".TB_PREFIX."vdata WHERE wood > maxstore OR clay > maxstore OR iron > maxstore OR crop > maxcrop";
            $array = $database->query_return($q);
            foreach ($array as $getvillage) {
                if($getvillage['wood'] > $getvillage['maxstore']) {
                    $wood = $getvillage['maxstore'];
                } else {
                    $wood = $getvillage['wood'];
                }
                if($getvillage['clay'] > $getvillage['maxstore']) {
                    $clay = $getvillage['maxstore'];
                } else {
                    $clay = $getvillage['clay'];
                }
                if($getvillage['iron'] > $getvillage['maxstore']) {
                    $iron = $getvillage['maxstore'];
                } else {
                    $iron = $getvillage['iron'];
                }
                if($getvillage['crop'] > $getvillage['maxcrop']) {
                    $crop = $getvillage['maxcrop'];
                } else {
                    $crop = $getvillage['crop'];
                }
                $q = "UPDATE ".TB_PREFIX."vdata set wood = $wood, clay = $clay, iron = $iron, crop = $crop where wref = ".$getvillage['wref']."";
                $database->query($q);
            }
            $q = "SELECT * FROM ".TB_PREFIX."vdata WHERE wood < 0 OR clay < 0 OR iron < 0 OR crop < 0";
            $array = $database->query_return($q);
            foreach ($array as $getvillage) {
                if($getvillage['wood'] < 0) {
                    $wood = 0;
                } else {
                    $wood = $getvillage['wood'];
                }
                if($getvillage['clay'] < 0) {
                    $clay = 0;
                } else {
                    $clay = $getvillage['clay'];
                }
                if($getvillage['iron'] < 0) {
                    $iron = 0;
                } else {
                    $iron = $getvillage['iron'];
                }
                // el cereal negativo se conserva: la hambruna lo resuelve starvation()
                $crop = $getvillage['crop'];
                $q = "UPDATE ".TB_PREFIX."vdata set wood = $wood, clay = $clay, iron = $iron, crop = $crop where wref = ".$getvillage['wref']."";
                $database->query($q);
            }
        }
    }

    private function culturePoints() {
        if(file_exists("GameEngine/Prevention/culturepoints.txt")) {
            @unlink("GameEngine/Prevention/culturepoints.txt");
        }
        global $database;
        $ourFileHandle = @fopen("GameEngine/Prevention/culturepoints.txt", 'w');
        @fclose($ourFileHandle);
        // The village value is labelled and calculated as culture points per day,
        // so it must be credited once every 24 hours.
        $time = time() - 86400;
        $array = array();
        $q = "SELECT id, lastupdate FROM ".TB_PREFIX."users where lastupdate < $time";
        $array = $database->query_return($q);

        foreach ($array as $indi) {
            if($indi['lastupdate'] < $time) {
                $cp = $database->getVSumField($indi['id'], 'cp');
                $newupdate = time();
                $q = "UPDATE ".TB_PREFIX."users set cp = cp + $cp, lastupdate = $newupdate where id = '".$indi['id']."'";
                $database->query($q);
            }
        }
        if(file_exists("GameEngine/Prevention/culturepoints.txt")) {
            @unlink("GameEngine/Prevention/culturepoints.txt");
        }
    }

    private function nextPendingAttackArrival($beforeTime = null) {
        global $database;

        $beforeTime = $beforeTime === null ? time() : max(0, (int)$beforeTime);
        $movementTable = TB_PREFIX."movement";
        $attackTable = TB_PREFIX."attacks";
        $q = "SELECT MIN(m.endtime) AS endtime"
            . " FROM ".$movementTable." AS m"
            . " INNER JOIN ".$attackTable." AS a ON a.id = m.ref"
            . " WHERE m.proc = 0"
            . " AND m.sort_type = 3"
            . " AND a.attack_type != 2"
            . " AND m.endtime < ".$beforeTime;
        $rows = $database->query_return($q);
        if(!is_array($rows) || empty($rows[0]['endtime'])) {
            return null;
        }
        return (int)$rows[0]['endtime'];
    }

    private function buildComplete($throughTime = null, $managePreventionFile = true) {
        if($managePreventionFile && file_exists("GameEngine/Prevention/build.txt")) {
            @unlink("GameEngine/Prevention/build.txt");
        }
        global $database, $bid18;
        if($managePreventionFile) {
            $ourFileHandle = @fopen("GameEngine/Prevention/build.txt", 'w');
            @fclose($ourFileHandle);
        }
        $timeCondition = $throughTime === null
            ? "timestamp < ".time()
            : "timestamp <= ".max(0, (int)$throughTime);
        $array = array();
        $q = "SELECT * FROM ".TB_PREFIX."bdata where ".$timeCondition." and master = 0 ORDER BY timestamp ASC, id ASC";
        $array = $database->query_return($q);
        foreach ($array as $indi) {
            $q = "UPDATE ".TB_PREFIX."fdata set f".$indi['field']." = ".$indi['level'].", f".$indi['field']."t = ".$indi['type']." where vref = ".$indi['wid'];
            if($database->query($q)) {
                $level = $database->getFieldLevel($indi['wid'], $indi['field']);
                $pop = $this->getPop($indi['type'], ($level - 1));
                $database->modifyPop($indi['wid'], $pop[0], 0);
                $this->procClimbers($database->getVillageField($indi['wid'], 'owner'));
                $database->addCP($indi['wid'], $pop[1]);
                if($indi['type'] == 18) {
                    $allyleader = $database->getVillageField($indi['wid'], "owner");
                    $allyvillages = $database->getVillagesID2($allyleader);
                    $allymax = 0;
                    foreach ($allyvillages as $allyvillage) {
                        $allyfield = $database->getResourceLevel($allyvillage['wref']);
                        for ($i = 19; $i <= 40; $i++) {
                            if($allyfield['f'.$i.'t'] == 18) {
                                $allylevel = $allyfield['f'.$i];
                                $allyattri = $bid18[$allylevel]['attri'];
                            }
                        }
                        if($allyattri > $allymax) {
                            $allymax = $allyattri;
                        }
                    }
                    $q = "UPDATE ".TB_PREFIX."alidata set max = $allymax where leader = $allyleader";
                    $database->query($q);
                }

                $this->applyStorageCapacityDelta($indi['wid'], $indi['type'], $level - 1, $level);

                $q4 = "UPDATE ".TB_PREFIX."bdata set loopcon = 0 where loopcon = 1 and wid = ".$indi['wid'];
                $database->query($q4);
                $q = "DELETE FROM ".TB_PREFIX."bdata where id = ".$indi['id'];
                $database->query($q);
            }
            // by SlimShady95 aka Manuel Mannhardt < manuel_mannhardt@web.de >
            if($indi['type'] == 40 and ($indi['level'] % 5 == 0 or $indi['level'] > 95) and $indi['level'] != 100) {
                $this->startNatarAttack($indi['level'], $indi['wid']);
            }
            if($indi['type'] == 40 && $indi['level'] == 100) { //now can't be more than one winners if ww to level 100 is build by 2 users or more on same time
                mysql_query("TRUNCATE ".TB_PREFIX."bdata");
            }
            if($database->getUserField($database->getVillageField($indi['wid'], "owner"), "tribe", 0) != 1) {
                $q4 = "UPDATE ".TB_PREFIX."bdata set loopcon = 0 where loopcon = 1 and master = 0 and wid = ".$indi['wid'];
                $database->query($q4);
            } else {
                if($indi['field'] > 18) {
                    $q4 = "UPDATE ".TB_PREFIX."bdata set loopcon = 0 where loopcon = 1 and master = 0 and wid = ".$indi['wid']." and field > 18";
                    $database->query($q4);
                } else {
                    $q4 = "UPDATE ".TB_PREFIX."bdata set loopcon = 0 where loopcon = 1 and master = 0 and wid = ".$indi['wid']." and field < 19";
                    $database->query($q4);
                }
            }
            $q = "DELETE FROM ".TB_PREFIX."bdata where id = ".$indi['id'];
            $database->query($q);
            /* $crop = $database->getCropProdstarv($indi['wid']);
				$unitarrays = $this->getAllUnits($indi['wid']);
				$village = $database->getVillage($indi['wid']);
				$upkeep = $village['pop'] + $this->getUpkeep($unitarrays, 0, $indi['wid']);
				if ($crop < $upkeep){
					// add starv data
					$database->setVillageField($indi['wid'], 'starv', $upkeep);
					$database->setVillageField($indi['wid'], 'starvupdate', $time);
				} */
        }
        if($managePreventionFile && file_exists("GameEngine/Prevention/build.txt")) {
            @unlink("GameEngine/Prevention/build.txt");
        }
    }

    // by SlimShady95 aka Manuel Mannhardt < manuel_mannhardt@web.de >
    private function startNatarAttack($level, $vid) {
        global $database;

        // bad, but should work :D
        // I took the data from my first ww (first .org world)
        // todo: get the algo from the real travian with the 100 biggest
        // offs and so on
        $troops = array(
            5 => array(
                array(3412, 2814, 4156, 3553, 9, 0),
                array(35, 0, 77, 33, 17, 10)
            ),

            10 => array(
                array(4314, 3688, 5265, 4621, 13, 0),
                array(65, 0, 175, 77, 28, 17)
            ),

            15 => array(
                array(4645, 4267, 5659, 5272, 15, 0),
                array(99, 0, 305, 134, 40, 25)
            ),

            20 => array(
                array(6207, 5881, 7625, 7225, 22, 0),
                array(144, 0, 456, 201, 56, 36)
            ),

            25 => array(
                array(6004, 5977, 7400, 7277, 23, 0),
                array(152, 0, 499, 220, 58, 37)
            ),

            30 => array(
                array(7073, 7181, 8730, 8713, 27, 0),
                array(183, 0, 607, 268, 69, 45)
            ),

            35 => array(
                array(7090, 7320, 8762, 8856, 28, 0),
                array(186, 0, 620, 278, 70, 45)
            ),

            40 => array(
                array(7852, 6967, 9606, 8667, 25, 0),
                array(146, 0, 431, 190, 60, 37)
            ),

            45 => array(
                array(8480, 8883, 10490, 10719, 35, 0),
                array(223, 0, 750, 331, 83, 54)
            ),

            50 => array(
                array(8522, 9038, 10551, 10883, 35, 0),
                array(224, 0, 757, 335, 83, 54)
            ),

            55 => array(
                array(8931, 8690, 10992, 10624, 32, 0),
                array(219, 0, 707, 312, 84, 54)
            ),

            60 => array(
                array(12138, 13013, 15040, 15642, 51, 0),
                array(318, 0, 1079, 477, 118, 76)
            ),

            65 => array(
                array(13397, 14619, 16622, 17521, 58, 0),
                array(345, 0, 1182, 522, 127, 83)
            ),

            70 => array(
                array(16323, 17665, 20240, 21201, 70, 0),
                array(424, 0, 1447, 640, 157, 102)
            ),

            75 => array(
                array(20739, 22796, 25746, 27288, 91, 0),
                array(529, 0, 1816, 803, 194, 127)
            ),

            80 => array(
                array(21857, 24180, 27147, 28914, 97, 0),
                array(551, 0, 1898, 839, 202, 132)
            ),

            85 => array(
                array(22476, 25007, 27928, 29876, 100, 0),
                array(560, 0, 1933, 855, 205, 134)
            ),

            90 => array(
                array(31345, 35053, 38963, 41843, 141, 0),
                array(771, 0, 2668, 1180, 281, 184)
            ),

            95 => array(
                array(31720, 35635, 39443, 42506, 144, 0),
                array(771, 0, 2671, 1181, 281, 184)
            ),

            96 => array(
                array(32885, 37007, 40897, 44130, 150, 0),
                array(795, 0, 2757, 1219, 289, 190)
            ),

            97 => array(
                array(32940, 37099, 40968, 44235, 150, 0),
                array(794, 0, 2755, 1219, 289, 190)
            ),

            98 => array(
                array(33521, 37691, 41686, 44953, 152, 0),
                array(812, 0, 2816, 1246, 296, 194)
            ),

            99 => array(
                array(36251, 40861, 45089, 48714, 165, 0),
                array(872, 0, 3025, 1338, 317, 208)
            )
        );

        // select the troops^^
        if(isset($troops[$level])) {
            $units = $troops[$level];
        } else {
            return false;
        }

        // get the capital village from the natars
        $query = mysql_query('SELECT `wref` FROM `'.TB_PREFIX.'vdata` WHERE `owner` = 3 and `capital` = 1 LIMIT 1') or die(mysql_error());
        $row = mysql_fetch_assoc($query);

        // start the attacks
        $endtime = time() + round((60 * 60 * 24) / INCREASE_SPEED);

        // -.-
        mysql_query('INSERT INTO `'.TB_PREFIX.'ww_attacks` (`vid`, `attack_time`) VALUES ('.$vid.', '.$endtime.')');
        mysql_query('INSERT INTO `'.TB_PREFIX.'ww_attacks` (`vid`, `attack_time`) VALUES ('.$vid.', '.($endtime + 1).')');

        // wave 1
        $ref = $database->addAttack($row['wref'], 0, $units[0][0], $units[0][1], 0, $units[0][2], $units[0][3], $units[0][4], $units[0][5], 0, 0, 0, 3, 0, 0, 0, 0, 20, 20, 0, 20, 20, 20, 20);
        $database->addMovement(3, $row['wref'], $vid, $ref, 0, $endtime);

        // wave 2
        $ref2 = $database->addAttack($row['wref'], 0, $units[1][0], $units[1][1], 0, $units[1][2], $units[1][3], $units[1][4], $units[1][5], 0, 0, 0, 3, 40, 0, 0, 0, 20, 20, 0, 20, 20, 20, 20, array('vid' => $vid, 'endtime' => ($endtime + 1)));
        $database->addMovement(3, $row['wref'], $vid, $ref2, 0, $endtime + 1);
    }

    private function checkWWAttacks() {
        $query = mysql_query('SELECT * FROM `'.TB_PREFIX.'ww_attacks` WHERE `attack_time` <= '.time());
        while($row = mysql_fetch_assoc($query)) {
            // fix for destroyed wws
            $query2 = mysql_query('UPDATE `'.TB_PREFIX.'fdata` SET `f99t` = 40 WHERE `vref` = '.$row['vid']);

            // delete the attack
            $query3 = mysql_query('DELETE FROM `'.TB_PREFIX.'ww_attacks` WHERE `vid` = '.$row['vid'].' AND `attack_time` = '.$row['attack_time']);
        }
    }

    private function getPop($tid, $level) {
        $name = "bid".$tid;
        global $$name, $village;
        $dataarray = $$name;
        $pop = $dataarray[($level + 1)]['pop'];
        $cp = $dataarray[($level + 1)]['cp'];
        return array($pop, $cp);
    }

    private function TradeRoute() {
        global $database;
        $time = time();
        $q = "SELECT * FROM ".TB_PREFIX."route where timestamp < $time";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $targettribe = $database->getUserField($database->getVillageField($data['from'], "owner"), "tribe", 0);
            $this->sendResource2($data['wood'], $data['clay'], $data['iron'], $data['crop'], $data['from'], $data['wid'], $targettribe, $data['deliveries']);
            $database->editTradeRoute($data['id'], "timestamp", 86400, 1);
        }
    }

    private function marketComplete() {
        if(file_exists("GameEngine/Prevention/market.txt")) {
            @unlink("GameEngine/Prevention/market.txt");
        }
        global $database, $generator;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/market.txt", 'w');
        @fclose($ourFileHandle);
        do {
            $processed = false;
            $q = "SELECT * FROM ".TB_PREFIX."movement, ".TB_PREFIX."send where ".TB_PREFIX."movement.ref = ".TB_PREFIX."send.id and ".TB_PREFIX."movement.proc = 0 and sort_type = 0 and endtime <= $time";
            $dataarray = $database->query_return($q);
            foreach ($dataarray as $data) {
                if(!$database->claimMovementProc($data['moveid'])) {
                    continue;
                }
                $processed = true;

                if($data['wood'] >= $data['clay'] && $data['wood'] >= $data['iron'] && $data['wood'] >= $data['crop']) {
                    $sort_type = "10";
                } elseif($data['clay'] >= $data['wood'] && $data['clay'] >= $data['iron'] && $data['clay'] >= $data['crop']) {
                    $sort_type = "11";
                } elseif($data['iron'] >= $data['wood'] && $data['iron'] >= $data['clay'] && $data['iron'] >= $data['crop']) {
                    $sort_type = "12";
                } else {
                    $sort_type = "13";
                }

                $to = $database->getMInfo($data['to']);
                $from = $database->getMInfo($data['from']);
                $toAlly = $database->getUserField($to['owner'], 'alliance', 0);
                $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                $fromcoor = $database->getCoor($data['from']);
                $tocoor = $database->getCoor($data['to']);
                $senderTribe = $database->getUserField($from['owner'], "tribe", 0);
                $travelTime = $this->procDistanceTime($fromcoor, $tocoor, $senderTribe, 0);
                $totalDeliveries = (int)$data['ref2'] > 0 ? (int)$data['ref2'] : max(1, (int)$data['send']);
                $currentDelivery = max(1, $totalDeliveries - (int)$data['send'] + 1);
                $noticeData = ''.$from['wref'].','.$to['wref'].','.$data['wood'].','.$data['clay'].','.$data['iron'].','.$data['crop'].','.$travelTime.','.$currentDelivery.','.$totalDeliveries.'';
                $database->addNotice($to['owner'], $to['wref'], $toAlly, $sort_type, ''.addslashes($from['name']).' envió recursos a '.addslashes($to['name']).'', $noticeData, $data['endtime']);
                if($from['owner'] != $to['owner']) {
                    $database->addNotice($from['owner'], $to['wref'], $fromAlly, $sort_type, ''.addslashes($from['name']).' envió recursos a '.addslashes($to['name']).'', $noticeData, $data['endtime']);
                }
                $database->modifyResource($data['to'], $data['wood'], $data['clay'], $data['iron'], $data['crop'], 1);
                $endtime = $travelTime + $data['endtime'];
                $database->addMovement(2, $data['to'], $data['from'], $data['merchant'], '0,0,0,0,0', $endtime, $data['send'], $data['wood'], $data['clay'], $data['iron'], $data['crop'], $totalDeliveries);
            }

            $q1 = "SELECT * FROM ".TB_PREFIX."movement where proc = 0 and sort_type = 2 and endtime <= $time";
            $dataarray1 = $database->query_return($q1);
            foreach ($dataarray1 as $data1) {
                if(!$database->claimMovementProc($data1['moveid'])) {
                    continue;
                }
                $processed = true;
                if($data1['send'] > 1) {
                    $targettribe1 = $database->getUserField($database->getVillageField($data1['to'], "owner"), "tribe", 0);
                    $send = $data1['send'] - 1;
                    $totalDeliveries = (int)$data1['ref2'] > 0 ? (int)$data1['ref2'] : max(1, (int)$data1['send']);
                    $this->sendResource2($data1['wood'], $data1['clay'], $data1['iron'], $data1['crop'], $data1['to'], $data1['from'], $targettribe1, $send, $data1['endtime'], $totalDeliveries);
                }
            }
        } while($processed);
        if(file_exists("GameEngine/Prevention/market.txt")) {
            @unlink("GameEngine/Prevention/market.txt");
        }
    }

    private function sendResource2($wtrans, $ctrans, $itrans, $crtrans, $from, $to, $tribe, $send, $departureTime = null, $totalDeliveries = null) {
        global $bid17, $bid28, $database, $generator, $logging;
        $availableWood = $database->getWoodAvailable($from);
        $availableClay = $database->getClayAvailable($from);
        $availableIron = $database->getIronAvailable($from);
        $availableCrop = $database->getCropAvailable($from);
        if($availableWood >= $wtrans AND $availableClay >= $ctrans AND $availableIron >= $itrans AND $availableCrop >= $crtrans) {
            $merchant2 = ($this->getTypeLevel(17, $from) > 0) ? $this->getTypeLevel(17, $from) : 0;
            $used2 = $database->totalMerchantUsed($from);
            $merchantAvail2 = $merchant2 - $used2;
            $maxcarry2 = ($tribe == 1) ? 500 : (($tribe == 2) ? 1000 : 750);
            $maxcarry2 *= TRADER_CAPACITY;
            if($this->getTypeLevel(28, $from) != 0) {
                $maxcarry2 *= $bid28[$this->getTypeLevel(28, $from)]['attri'] / 100;
            }
            $resource = array($wtrans, $ctrans, $itrans, $crtrans);
            $reqMerc = ceil((array_sum($resource) - 0.1) / $maxcarry2);
            if($merchantAvail2 != 0 && $reqMerc <= $merchantAvail2) {
                $coor = $database->getCoor($to);
                $coor2 = $database->getCoor($from);
                if($database->getVillageState($to)) {
                    $timetaken = $generator->procDistanceTime($coor, $coor2, $tribe, 0);
                    $res = $resource[0] + $resource[1] + $resource[2] + $resource[3];
                    if($res != 0) {
                        $resdata = "".$resource[0].",".$resource[1].",".$resource[2].",".$resource[3]."";
                        if(!$database->deductResourcesIfAvailable($from, $resource[0], $resource[1], $resource[2], $resource[3])) {
                            return false;
                        }
                        $reference = $database->sendResource($resource[0], $resource[1], $resource[2], $resource[3], $reqMerc, 0);
                        if(!$reference) {
                            $database->modifyResource($from, $resource[0], $resource[1], $resource[2], $resource[3], 1);
                            return false;
                        }
                        $departureTime = $departureTime === null ? time() : (int)$departureTime;
                        $totalDeliveries = $totalDeliveries === null ? max(1, (int)$send) : max(1, (int)$totalDeliveries);
                        if(!$database->addMovement(0, $from, $to, $reference, $resdata, $departureTime + $timetaken, $send, 0, 0, 0, 0, $totalDeliveries)) {
                            $database->sendResource($reference, 0, 0, 0, 0, 1);
                            $database->modifyResource($from, $resource[0], $resource[1], $resource[2], $resource[3], 1);
                            return false;
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function sendunitsComplete() {
        if(file_exists("GameEngine/Prevention/sendunits.txt")) {
            @unlink("GameEngine/Prevention/sendunits.txt");
        }
        global $bid23, $bid36, $database, $battle, $village, $technology, $logging, $session;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/sendunits.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."movement, ".TB_PREFIX."attacks where ".TB_PREFIX."movement.ref = ".TB_PREFIX."attacks.id and ".TB_PREFIX."movement.proc = '0' and ".TB_PREFIX."movement.sort_type = '3' and ".TB_PREFIX."attacks.attack_type != '2' and endtime < $time ORDER BY endtime ASC";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            // Bring the world up to the attack's arrival time, but never apply
            // a building level that completed after this attack.
            $this->buildComplete((int)$data['endtime'], false);
            $totalattackdead = 0;
            $totaldead_att = $totaldead_def = $totalsend_def = 0;
            $totaltraped_att = $DefenderHeroRef = 0;
            $totaldead_alldef = array_fill(1, 5, 0);
            $rams = $catp = $chiefs = 0;
            $scout = $type = 0;
            $rom = $ger = $gal = $nat = $natar = 0;
            $info_ram = $info_cat = $info_chief = $info_spy = $info_trap = '';
            $spyReinforcements = array();
            $eee = 0;
            $walllevel = $stonemason = $tblevel = 0;
            $breweryActive = false;
            $breweryLevel = 0;
            $herosend_att = (int)$data['t11'];
            $cage = array('id' => 0, 'type' => 0);
            $dead = array_fill(1, 50, 0);
            $dead['hero'] = 0;
            $DefenderHeroByTribe = array_fill(1, 5, 0);
            $DeadHeroByTribe = array_fill(1, 5, 0);
            unset($empty);
            for($i = 1; $i <= 11; $i++) {
                ${'dead'.$i} = 0;
                ${'traped'.$i} = 0;
            }
            //set base things
            //$battle->resolveConflict($data);
            $tocoor = $database->getCoor($data['from']);
            $fromcoor = $database->getCoor($data['to']);
            $isoasis = $database->isVillageOases($data['to']);
            $AttackArrivalTime = $data['endtime'];
            if($isoasis == 0) {
                $AttackerID = $database->getUserField($database->getVillageField($data['from'], "owner"), "id", 0);
                $DefenderID = $database->getUserField($database->getVillageField($data['to'], "owner"), "id", 0);
                $owntribe = $database->getUserField($database->getVillageField($data['from'], "owner"), "tribe", 0);
                $targettribe = $database->getUserField($database->getVillageField($data['to'], "owner"), "tribe", 0);
                $ownally = $database->getUserField($database->getVillageField($data['from'], "owner"), "alliance", 0);
                $targetally = $database->getUserField($database->getVillageField($data['to'], "owner"), "alliance", 0);
                $to = $database->getMInfo($data['to']);
                $from = $database->getMInfo($data['from']);
                $toF = $database->getVillage($data['to']);
                $fromF = $database->getVillage($data['from']);

                $DefenderUnit = array();
                $DefenderUnit = $database->getUnit($data['to']);
                $evasion = $database->getUserField($DefenderID, "evasion", 0);
                $capital = $database->getVillageField($data['to'], "capital");
                $playerunit = ($targettribe - 1) * 10;
                $cannotsend = $this->hasOrdinaryTroopReturnInEvasionWindow(
                    $database,
                    $data['to'],
                    $AttackArrivalTime
                );
                if($evasion == 1 && $capital == 1 && $cannotsend == 0 && $data['attack_type'] > 2 && $targettribe >= 1 && $targettribe <= 5) {
                    $evade = $this->buildTroopEvasionPayload($DefenderUnit, $targettribe);
                    $totaltroops = array_sum($evade);
                    if($totaltroops > 0) {
                        for ($i = 1; $i <= 10; $i++) {
                            if($evade[$i] > 0) {
                                $database->modifyUnit($data['to'], $playerunit + $i, $evade[$i], 0);
                            }
                        }
                        $evasionSpeed = (int)EVASION_SPEED;
                        if($evasionSpeed < 1) {
                            $evasionSpeed = 1;
                        }
                        $attackid = $database->addAttack($data['to'], $evade[1], $evade[2], $evade[3], $evade[4], $evade[5], $evade[6], $evade[7], $evade[8], $evade[9], $evade[10], 0, 2, 0, 0, 0);
                        $database->addMovement(4, 0, $data['to'], $attackid, '0,0,0,0,0', time() + (int)round(180 / $evasionSpeed));
                    }
                }

                //get defence units
                $Defender = array();
                $rom = $ger = $gal = $nat = $natar = 0;
                $Defender = $database->getUnit($data['to']);
                if(!empty($Defender['hero'])) {
                    $DefenderHeroByTribe[$targettribe] += (int)$Defender['hero'];
                }
                $enforcementarray = $database->getEnforceVillage($data['to'], 0);
                if(count($enforcementarray) > 0) {
                    foreach ($enforcementarray as $enforce) {
                        if((int)$data['attack_type'] === 1) {
                            $spyReinforcement = $this->buildSpyReinforcementSnapshot($enforce);
                            if($spyReinforcement !== null) {
                                $spyReinforcements[] = $spyReinforcement;
                            }
                        }
                        for ($i = 1; $i <= 50; $i++) {
                            $Defender['u'.$i] += $enforce['u'.$i];
                        }
                        $reinforcementHeroes = (int)$enforce['hero'];
                        $DefenderHeroRef += $reinforcementHeroes;
                        if($reinforcementHeroes > 0) {
                            $reinforcementOwner = $database->getVillageField($enforce['from'], "owner");
                            $reinforcementTribe = (int)$database->getUserField($reinforcementOwner, "tribe", 0);
                            if($reinforcementTribe >= 1 && $reinforcementTribe <= 5) {
                                $DefenderHeroByTribe[$reinforcementTribe] += $reinforcementHeroes;
                            }
                        }
                    }
                }
                for ($i = 1; $i <= 50; $i++) {
                    if(!isset($Defender['u'.$i])) {
                        $Defender['u'.$i] = '0';
                    } else {
                        if($Defender['u'.$i] == '' or $Defender['u'.$i] <= '0') {
                            $Defender['u'.$i] = '0';
                        } else {
                            if($i <= 10) {
                                $rom = '1';
                            } else if($i <= 20) {
                                $ger = '1';
                            } else if($i <= 30) {
                                $gal = '1';
                            } else if($i <= 40) {
                                $nat = '1';
                            } else if($i <= 50) {
                                $natar = '1';
                            }
                        }
                    }
                }
                if(!isset($Defender['hero'])) {
                    $Defender['hero'] = '0';
                } else {
                    if($Defender['hero'] == '' or $Defender['hero'] <= '0') {
                        $Defender['hero'] = '0';
                    }
                }
                //get attack units
                $Attacker = array();
                $start = ($owntribe - 1) * 10 + 1;
                $end = ($owntribe * 10);
                $u = (($owntribe - 1) * 10);
                $catp = 0;
                $catapult = array(8, 18, 28, 48);
                $ram = array(7, 17, 27, 37, 47);
                $chief = array(9, 19, 29, 39, 49);
                $spys = array(4, 14, 23, 34, 44);
                for ($i = $start; $i <= $end; $i++) {
                    $y = $i - $u;
                    $Attacker['u'.$i] = $data['t'.$y];
                    //there are catas
                    if(in_array($i, $catapult)) {
                        $catp += $Attacker['u'.$i];
                        $catp_pic = $i;
                    }
                    if(in_array($i, $ram)) {
                        $rams += $Attacker['u'.$i];
                        $ram_pic = $i;
                    }
                    if(in_array($i, $chief)) {
                        $chiefs += $Attacker['u'.$i];
                        $chief_pic = $i;
                    }
                    if(in_array($i, $spys)) {
                        $chiefs += $Attacker['u'.$i];
                        $spy_pic = $i;
                    }
                }
                $Attacker['hero'] = $data['t11'];
                $hero_pic = "hero";
                $Attacker['id'] = $database->getVillageField($data['from'], "owner");
                $Defender['id'] = $database->getVillageField($data['to'], "owner");
                $AttackerID = $database->getVillageField($data['from'], "owner");
                $DefenderID = $database->getVillageField($data['to'], "owner");
                //need to set these variables.
                $def_wall = $database->getFieldLevel($data['to'], 40);
                $att_tribe = $owntribe;
                $def_tribe = $targettribe;
                $residence = "0";
                $attpop = $fromF['pop'];
                $defpop = $toF['pop'];
                for ($i = 19; $i < 40; $i++) {
                    if($database->getFieldLevel($data['to'], "".$i."t") == '25' OR $database->getFieldLevel($data['to'], "".$i."t") == '26') {
                        $residence = $database->getFieldLevel($data['to'], $i);
                        $i = 40;
                    }
                }

                //type of attack
                if($data['attack_type'] == 1) {
                    $type = 1;
                    $scout = 1;
                }
                if($data['attack_type'] == 2) {
                    $type = 2;
                }
                if($data['attack_type'] == 3) {
                    $type = 3;
                }
                if($data['attack_type'] == 4) {
                    $type = 4;
                }

                $def_ab = Array(
                    "b1" => 0, // Blacksmith level
                    "b2" => 0, // Blacksmith level
                    "b3" => 0, // Blacksmith level
                    "b4" => 0, // Blacksmith level
                    "b5" => 0, // Blacksmith level
                    "b6" => 0, // Blacksmith level
                    "b7" => 0, // Blacksmith level
                    "b8" => 0); // Blacksmith level

                $att_ab = Array(
                    "a1" => 0, // legacy defensive upgrade level
                    "a2" => 0, // legacy defensive upgrade level
                    "a3" => 0, // legacy defensive upgrade level
                    "a4" => 0, // legacy defensive upgrade level
                    "a5" => 0, // legacy defensive upgrade level
                    "a6" => 0, // legacy defensive upgrade level
                    "a7" => 0, // legacy defensive upgrade level
                    "a8" => 0); // legacy defensive upgrade level

                //rams attack
                if($rams > 0 and $type == '3') {
                    $basearraywall = $database->getMInfo($data['to']);
                    if($database->getFieldLevel($basearraywall['wref'], 40) > '0') {
                        for ($w = 1; $w < 2; $w++) {
                            if($database->getFieldLevel($basearraywall['wref'], 40) != '0') {

                                $walllevel = $database->getFieldLevel($basearraywall['wref'], 40);
                                $wallgid = $database->getFieldLevel($basearraywall['wref'], "40t");
                                $wallid = 40;
                                $w = '4';
                            } else {
                                $w = $w--;
                            }
                        }
                    } else {
                        $empty = 1;
                    }
                }

                $tblevel = '1';
                $stonemason = 0;


                /*--------------------------------
            // End Battle part
            --------------------------------*/
            } else {
                $Attacker['id'] = $database->getUserField($database->getVillageField($data['from'], "owner"), "id", 0);
                $Defender['id'] = 3;
                $AttackerID = $database->getVillageField($data['from'], "owner");
                $DefenderID = $database->getOasisField($data['to'], "owner");
                $owntribe = $database->getUserField($database->getVillageField($data['from'], "owner"), "tribe", 0);
                $targettribe = 4;
                $ownally = $database->getUserField($database->getVillageField($data['from'], "owner"), "alliance", 0);
                $targetally = 0;
                $to = $database->getOMInfo($data['to']);
                $from = $database->getMInfo($data['from']);
                $toF = $database->getOasisV($data['to']);
                $fromF = $database->getVillage($data['from']);

                $cage = $database->getEquippedHeroItem($AttackerID, 9);
                if(!is_array($cage)) {
                    $cage = array('id' => 0, 'type' => 0, 'num' => 0);
                }
                $cageID = (int)$cage['id'];
                $cage['type'] = max(0, (int)$cage['type']);
                $cage['num'] = max(0, (int)$cage['num']);

                // Las jaulas solo capturan en oasis sin ocupar y en ataques reales:
                // sobre un oasis conquistado o en un espionaje se resuelve la batalla normal.
                if($cageID == 0 || (int)$toF['conqured'] != 0 || (int)$data['attack_type'] == 1) {
                    $cage['type'] = 0;
                }

                //get defence units
                $Defender = array();
                $rom = $ger = $gal = $nat = $natar = 0;
                $Defender = $database->getUnit($data['to']);
                if(!empty($Defender['hero']) && $targettribe >= 1 && $targettribe <= 5) {
                    $DefenderHeroByTribe[$targettribe] += (int)$Defender['hero'];
                }
                $enforcementarray = $database->getEnforceVillage($data['to'], 0);
                if(count($enforcementarray) > 0) {
                    foreach ($enforcementarray as $enforce) {
                        if((int)$data['attack_type'] === 1) {
                            $spyReinforcement = $this->buildSpyReinforcementSnapshot($enforce);
                            if($spyReinforcement !== null) {
                                $spyReinforcements[] = $spyReinforcement;
                            }
                        }
                        for ($i = 1; $i <= 50; $i++) {
                            $Defender['u'.$i] += $enforce['u'.$i];
                        }
                        $reinforcementHeroes = (int)$enforce['hero'];
                        $DefenderHeroRef += $reinforcementHeroes;
                        if($reinforcementHeroes > 0) {
                            $reinforcementOwner = $database->getVillageField($enforce['from'], "owner");
                            $reinforcementTribe = (int)$database->getUserField($reinforcementOwner, "tribe", 0);
                            if($reinforcementTribe >= 1 && $reinforcementTribe <= 5) {
                                $DefenderHeroByTribe[$reinforcementTribe] += $reinforcementHeroes;
                            }
                        }
                    }
                }
                for ($i = 1; $i <= 50; $i++) {
                    if(!isset($Defender['u'.$i])) {
                        $Defender['u'.$i] = 0;
                    } else {
                        if(!is_numeric($Defender['u'.$i]) or $Defender['u'.$i] <= 0) {
                            $Defender['u'.$i] = 0;
                        } else {
                            if($i <= 10) {
                                $rom = '1';
                            } else if($i <= 20) {
                                $ger = '1';
                            } else if($i <= 30) {
                                $gal = '1';
                            } else if($i <= 40) {
                                $nat = '1';
                            } else if($i <= 50) {
                                $natar = '1';
                            }
                        }
                    }
                }
                if(!isset($Defender['hero'])) {
                    $Defender['hero'] = 0;
                } else {
                    if(!is_numeric($Defender['hero']) or $Defender['hero'] <= 0) {
                        $Defender['hero'] = 0;
                    }
                }
                //get attack units
                $Attacker = array();
                $start = ($owntribe - 1) * 10 + 1;
                $end = ($owntribe * 10);
                $u = (($owntribe - 1) * 10);
                $catp = 0;
                $catapult = array(8, 18, 28, 48);
                $ram = array(7, 17, 27, 37, 47);
                $chief = array(9, 19, 29, 39, 49);
                $spys = array(4, 14, 23, 34, 44);
                for ($i = $start; $i <= $end; $i++) {
                    $y = $i - $u;
                    $Attacker['u'.$i] = $data['t'.$y];
                    //there are catas
                    if(in_array($i, $catapult)) {
                        $catp += $Attacker['u'.$i];
                        $catp_pic = $i;
                    }
                    if(in_array($i, $ram)) {
                        $rams += $Attacker['u'.$i];
                        $ram_pic = $i;
                    }
                    if(in_array($i, $chief)) {
                        $chiefs += $Attacker['u'.$i];
                        $chief_pic = $i;
                    }
                    if(in_array($i, $spys)) {
                        $chiefs += $Attacker['u'.$i];
                        $spy_pic = $i;
                    }
                }
                $Attacker['hero'] = $data['t11'];
                $hero_pic = "hero";
                $Attacker['id'] = $database->getUserField($database->getVillageField($data['from'], "owner"), "id", 0);
                $Defender['id'] = $database->getUserField($database->getVillageField($data['to'], "owner"), "id", 0);
                //need to set these variables.
                $def_wall = 1;
                $att_tribe = $owntribe;
                $def_tribe = $targettribe;
                $residence = "0";
                $attpop = $fromF['pop'];
                $defpop = 100;


                //type of attack
                if($data['attack_type'] == 1) {
                    $type = 1;
                    $scout = 1;
                }
                if($data['attack_type'] == 2) {
                    $type = 2;
                }
                if($data['attack_type'] == 3) {
                    $type = 3;
                }
                if($data['attack_type'] == 4) {
                    $type = 4;
                }

                $def_ab = Array(
                    "b1" => 0, // Blacksmith level
                    "b2" => 0, // Blacksmith level
                    "b3" => 0, // Blacksmith level
                    "b4" => 0, // Blacksmith level
                    "b5" => 0, // Blacksmith level
                    "b6" => 0, // Blacksmith level
                    "b7" => 0, // Blacksmith level
                    "b8" => 0); // Blacksmith level

                $att_ab = Array(
                    "a1" => 0, // legacy defensive upgrade level
                    "a2" => 0, // legacy defensive upgrade level
                    "a3" => 0, // legacy defensive upgrade level
                    "a4" => 0, // legacy defensive upgrade level
                    "a5" => 0, // legacy defensive upgrade level
                    "a6" => 0, // legacy defensive upgrade level
                    "a7" => 0, // legacy defensive upgrade level
                    "a8" => 0); // legacy defensive upgrade level

                $empty = '1';
                $tblevel = '0';
                $stonemason = 0;

            }
            $hidehero = $database->getHeroData($DefenderID);
            if($hidehero['hide'] == 1) {
                if($targettribe >= 1 && $targettribe <= 5) {
                    $DefenderHeroByTribe[$targettribe] = max(
                        0,
                        $DefenderHeroByTribe[$targettribe] - (int)$Defender['hero']
                    );
                }
                $Defender['hero'] = 0;
            }
            if($cage['type'] == 0 || $Attacker['hero'] == 0 || $isoasis == 0) {
                if($targettribe == 1) {
                    $def_spy = $Defender['u4'];
                } elseif($targettribe == 2) {
                    $def_spy = $Defender['u14'];
                } elseif($targettribe == 3) {
                    $def_spy = $Defender['u23'];
                } elseif($targettribe == 5) {
                    $def_spy = $Defender['u54'];
                }
                if(!$scout or $def_spy > 0) {
                    $capturedTroops = array_fill(1,11,0);
                    if($isoasis == 0 && (int)$targettribe === 3) {
                        $trapCapacity = 0;
                        $trapFields = $database->getResourceLevel($data['to']);
                        for($field = 19; $field <= 38; $field++) {
                            if((int)$trapFields['f'.$field.'t'] === 36) {
                                $trapLevel = (int)$trapFields['f'.$field];
                                if($trapLevel > 0 && isset($bid36[$trapLevel]['attri'])) {
                                    $trapCapacity += $bid36[$trapLevel]['attri'] * TRAPPER_CAPACITY;
                                }
                            }
                        }
                        $attackingTroops = array();
                        for($i = 1; $i <= 11; $i++) {
                            $attackingTroops[$i] = max(0,(int)$data['t'.$i]);
                        }
                        $capturedTroops = $database->capturePrisonersAtomic(
                            (int)$data['to'],
                            (int)$data['from'],
                            $attackingTroops,
                            $trapCapacity
                        );
                    }
                    for ($i = 1; $i <= 11; $i++) {
                        ${'traped'.$i} = max(0,(int)$capturedTroops[$i]);
                    }
                    for ($i = $start; $i <= $end; $i++) {
                        $j = $i - $start + 1;
                        $Attacker['u'.$i] -= ${'traped'.$j};
                    }
                    $Attacker['hero'] -= $traped11;
                    $totaltraped_att = $traped1 + $traped2 + $traped3 + $traped4 + $traped5 + $traped6 + $traped7 + $traped8 + $traped9 + $traped10 + $traped11;
                }
                $storedAttackerUpgrades = $database->getABTech($data['from']);
                if(is_array($storedAttackerUpgrades)) {
                    $att_ab = $storedAttackerUpgrades;
                }
                if($isoasis == 0) {
                    $defenderFields = $database->getResourceLevel($data['to']);
                    for($field = 19; $field <= 40; $field++) {
                        if(isset($defenderFields['f'.$field.'t']) && (int)$defenderFields['f'.$field.'t'] === 34) {
                            $stonemason = (int)$defenderFields['f'.$field];
                            break;
                        }
                    }
                }
                $battlepart = $battle->calculateBattle(
                    $Attacker,
                    $Defender,
                    $def_wall,
                    $att_tribe,
                    $def_tribe,
                    $residence,
                    $attpop,
                    $defpop,
                    $type,
                    $def_ab,
                    $att_ab,
                    $tblevel,
                    $stonemason,
                    $walllevel,
                    $AttackerID,
                    $DefenderID,
                    $data['from'],
                    $data['to']
                );
                $breweryActive = !empty($battlepart['brewery_active']);
                $breweryLevel = isset($battlepart['brewery_level']) ? (int)$battlepart['brewery_level'] : 0;
                if($DefenderHeroByTribe[1] > 0) {
                    $rom = '1';
                }
                if($DefenderHeroByTribe[2] > 0) {
                    $ger = '1';
                }
                if($DefenderHeroByTribe[3] > 0) {
                    $gal = '1';
                }
                if($DefenderHeroByTribe[4] > 0) {
                    $nat = '1';
                }
                if($DefenderHeroByTribe[5] > 0) {
                    $natar = '1';
                }
                //units attack string for battleraport
                $unitssend_att = ''.$data['t1'].','.$data['t2'].','.$data['t3'].','.$data['t4'].','.$data['t5'].','.$data['t6'].','.$data['t7'].','.$data['t8'].','.$data['t9'].','.$data['t10'].','.$data['t11'].'';
                $totalsend_att = $data['t1'] + $data['t2'] + $data['t3'] + $data['t4'] + $data['t5'] + $data['t6'] + $data['t7'] + $data['t8'] + $data['t9'] + $data['t10'] + $data['t11'];

                //units defence string for battleraport
                $unitssend_def[1] = ''.$Defender['u1'].','.$Defender['u2'].','.$Defender['u3'].','.$Defender['u4'].','.$Defender['u5'].','.$Defender['u6'].','.$Defender['u7'].','.$Defender['u8'].','.$Defender['u9'].','.$Defender['u10'].','.$DefenderHeroByTribe[1].'';

                $unitssend_def[2] = ''.$Defender['u11'].','.$Defender['u12'].','.$Defender['u13'].','.$Defender['u14'].','.$Defender['u15'].','.$Defender['u16'].','.$Defender['u17'].','.$Defender['u18'].','.$Defender['u19'].','.$Defender['u20'].','.$DefenderHeroByTribe[2].'';

                $unitssend_def[3] = ''.$Defender['u21'].','.$Defender['u22'].','.$Defender['u23'].','.$Defender['u24'].','.$Defender['u25'].','.$Defender['u26'].','.$Defender['u27'].','.$Defender['u28'].','.$Defender['u29'].','.$Defender['u30'].','.$DefenderHeroByTribe[3].'';

                $unitssend_def[4] = ''.$Defender['u31'].','.$Defender['u32'].','.$Defender['u33'].','.$Defender['u34'].','.$Defender['u35'].','.$Defender['u36'].','.$Defender['u37'].','.$Defender['u38'].','.$Defender['u39'].','.$Defender['u40'].','.$DefenderHeroByTribe[4].'';

                $unitssend_def[5] = ''.$Defender['u41'].','.$Defender['u42'].','.$Defender['u43'].','.$Defender['u44'].','.$Defender['u45'].','.$Defender['u46'].','.$Defender['u47'].','.$Defender['u48'].','.$Defender['u49'].','.$Defender['u50'].','.$DefenderHeroByTribe[5].'';
                $unitssend_deff[1] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitssend_deff[2] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitssend_deff[3] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitssend_deff[4] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitssend_deff[5] = '?,?,?,?,?,?,?,?,?,?,?,';
                //how many troops died? for battleraport
                if($battlepart['casualties_attacker'][1] == 0) {
                    $dead1 = 0;
                } else {
                    $dead1 = $battlepart['casualties_attacker'][1];
                }
                if($battlepart['casualties_attacker'][2] == 0) {
                    $dead2 = 0;
                } else {
                    $dead2 = $battlepart['casualties_attacker'][2];
                }
                if($battlepart['casualties_attacker'][3] == 0) {
                    $dead3 = 0;
                } else {
                    $dead3 = $battlepart['casualties_attacker'][3];
                }
                if($battlepart['casualties_attacker'][4] == 0) {
                    $dead4 = 0;
                } else {
                    $dead4 = $battlepart['casualties_attacker'][4];
                }
                if($battlepart['casualties_attacker'][5] == 0) {
                    $dead5 = 0;
                } else {
                    $dead5 = $battlepart['casualties_attacker'][5];
                }
                if($battlepart['casualties_attacker'][6] == 0) {
                    $dead6 = 0;
                } else {
                    $dead6 = $battlepart['casualties_attacker'][6];
                }
                if($battlepart['casualties_attacker'][7] == 0) {
                    $dead7 = 0;
                } else {
                    $dead7 = $battlepart['casualties_attacker'][7];
                }
                if($battlepart['casualties_attacker'][8] == 0) {
                    $dead8 = 0;
                } else {
                    $dead8 = $battlepart['casualties_attacker'][8];
                }
                if($battlepart['casualties_attacker'][9] == 0) {
                    $dead9 = 0;
                } else {
                    $dead9 = $battlepart['casualties_attacker'][9];
                }
                if($battlepart['casualties_attacker'][10] == 0) {
                    $dead10 = 0;
                } else {
                    $dead10 = $battlepart['casualties_attacker'][10];
                }
                if($battlepart['casualties_attacker'][11] == 0) {
                    $dead11 = 0;
                } else {
                    $dead11 = $battlepart['casualties_attacker'][11];
                }


                //kill own defence
                $q = "SELECT * FROM ".TB_PREFIX."units WHERE vref='".$data['to']."'";
                $unitlist = $database->query_return($q);
                $start = ($targettribe - 1) * 10 + 1;
                $end = ($targettribe * 10);
                //FIX
                for ($i = $start; $i <= $end; $i++) {
                    if($unitlist) {
                        $localLoss = (int)round($battlepart[2] * $unitlist[0]['u'.$i]);
                        $dead[$i] += $localLoss;
                        if($localLoss > 0) {
                            $database->modifyUnit($data['to'], $i, $localLoss, 0);
                        }
                    }
                }
                if($unitlist) {
                    $localHeroLoss = (int)$battlepart['deadherodef'];
                    $dead['hero'] += $localHeroLoss;
                    $DeadHeroByTribe[$targettribe] += $localHeroLoss;
                    if($localHeroLoss > 0) {
                        $database->modifyUnit($data['to'], 'hero', $localHeroLoss, 0);
                    }
                }


                //kill other defence in village
                if(count($database->getEnforceVillage($data['to'], 0)) > 0) {
                    foreach ($database->getEnforceVillage($data['to'], 0) as $enforce) {
                        $wrong = '0';
                        if((int)$enforce['from'] === 0) {
                            $reinforcementOwner = 0;
                            $reinforcementAlly = 0;
                            $tribe = 4;
                        } else {
                            $reinforcementOwner = $database->getVillageField($enforce['from'], "owner");
                            $reinforcementAlly = $database->getUserField($reinforcementOwner, "alliance", 0);
                            $tribe = $database->getUserField($reinforcementOwner, "tribe", 0);
                        }
                        $start = ($tribe - 1) * 10 + 1;
                        $reinforcementDead = array_fill($start, 10, 0);

                        if($tribe == 1) {
                            $rom = '1';
                        } else if($tribe == 2) {
                            $ger = '1';
                        } else if($tribe == 3) {
                            $gal = '1';
                        } else if($tribe == 4) {
                            $nat = '1';
                        } else {
                            $natar = '1';
                        }
                        for ($i = $start; $i <= ($start + 9); $i++) {
                            if($enforce['u'.$i] > '0') {
                                $reinforcementDead[$i] = (int)round($battlepart[2] * $enforce['u'.$i]);
                                if($reinforcementDead[$i] > 0) {
                                    $database->modifyEnforce($enforce['id'], $i, $reinforcementDead[$i], 0);
                                }
                                $dead[$i] += $reinforcementDead[$i];
                                if($reinforcementDead[$i] != $enforce['u'.$i]) {
                                    $wrong = '1';
                                }
                            }
                        }
                        $reinforcementHeroLoss = !empty($enforce['hero']) && isset($battlepart['deadheroref'][$enforce['id']])
                            ? (int)$battlepart['deadheroref'][$enforce['id']]
                            : 0;
                        if($reinforcementHeroLoss > 0) {
                            $database->modifyEnforce($enforce['id'], "hero", $reinforcementHeroLoss, 0);
                        }
                        $dead['hero'] += $reinforcementHeroLoss;
                        $DeadHeroByTribe[$tribe] += $reinforcementHeroLoss;
                        if((int)$enforce['hero'] !== $reinforcementHeroLoss) {
                            $wrong = '1';
                        }

                        $notlife = ''.$reinforcementDead[$start].','.$reinforcementDead[$start + 1].','.$reinforcementDead[$start + 2].','.$reinforcementDead[$start + 3].','.$reinforcementDead[$start + 4].','.$reinforcementDead[$start + 5].','.$reinforcementDead[$start + 6].','.$reinforcementDead[$start + 7].','.$reinforcementDead[$start + 8].','.$reinforcementDead[$start + 9].'';
                        $notlife1 = array_sum($reinforcementDead);
                        $life = ''.$enforce['u'.$start.''].','.$enforce['u'.($start + 1).''].','.$enforce['u'.($start + 2).''].','.$enforce['u'.($start + 3).''].','.$enforce['u'.($start + 4).''].','.$enforce['u'.($start + 5).''].','.$enforce['u'.($start + 6).''].','.$enforce['u'.($start + 7).''].','.$enforce['u'.($start + 8).''].','.$enforce['u'.($start + 9).''].'';
                        $life1 = $enforce['u'.$start.''] + $enforce['u'.($start + 1).''] + $enforce['u'.($start + 2).''] + $enforce['u'.($start + 3).''] + $enforce['u'.($start + 4).''] + $enforce['u'.($start + 5).''] + $enforce['u'.($start + 6).''] + $enforce['u'.($start + 7).''] + $enforce['u'.($start + 8).''] + $enforce['u'.($start + 9).''];
                        $lifehero = $enforce['hero'];
                        $notlifehero = $reinforcementHeroLoss;
                        $totallife = $enforce['hero'] + $life1;
                        $totalnotlife = $reinforcementHeroLoss + $notlife1;
                        $totalsend_att = $data['t1'] + $data['t2'] + $data['t3'] + $data['t4'] + $data['t5'] + $data['t6'] + $data['t7'] + $data['t8'] + $data['t9'] + $data['t10'] + $data['t11'];
                        $totaldead_att = $dead1 + $dead2 + $dead3 + $dead4 + $dead5 + $dead6 + $dead7 + $dead8 + $dead9 + $dead10 + $dead11;
                        //NEED TO SEND A RAPPORTAGE!!!
                        $data2 = ''.$reinforcementOwner.','.$enforce['from'].','.addslashes($to['name']).','.$tribe.','.$life.','.$notlife.','.$lifehero.','.$notlifehero.',reinforcement-origin-v1';
                        if($scout && $reinforcementOwner > 0) {
                            if($totaldead_att > 0) {
                                if($totaldead_att == $totalsend_att) {
                                    $database->addNotice($reinforcementOwner, $from['wref'], $reinforcementAlly, 15, 'Refuerzo en '.addslashes($to['name']).' atacado', $data2, $AttackArrivalTime);
                                } else {
                                    $database->addNotice($reinforcementOwner, $from['wref'], $reinforcementAlly, 16, 'Refuerzo en '.addslashes($to['name']).' atacado', $data2, $AttackArrivalTime);
                                }
                            }
                        } else if(!$scout) {
                            if($reinforcementOwner > 0) {
                                if($totalnotlife == 0) {
                                    $database->addNotice($reinforcementOwner, $from['wref'], $reinforcementAlly, 15, 'Refuerzo en '.addslashes($to['name']).' atacado', $data2, $AttackArrivalTime);
                                } else if($totallife > $totalnotlife) {
                                    $database->addNotice($reinforcementOwner, $from['wref'], $reinforcementAlly, 16, 'Refuerzo en '.addslashes($to['name']).' atacado', $data2, $AttackArrivalTime);
                                } else {
                                    $database->addNotice($reinforcementOwner, $from['wref'], $reinforcementAlly, 17, 'Refuerzo en '.addslashes($to['name']).' atacado', $data2, $AttackArrivalTime);
                                }
                            }
                            //delete reinf sting when its killed all.
                            if($wrong == '0') {
                                $database->deleteReinf($enforce['id']);
                            }
                        }
                    }
                }

                $unitsdead_def[1] = ''.$dead[1].','.$dead[2].','.$dead[3].','.$dead[4].','.$dead[5].','.$dead[6].','.$dead[7].','.$dead[8].','.$dead[9].','.$dead[10].','.$DeadHeroByTribe[1].'';
                $unitsdead_def[2] = ''.$dead['11'].','.$dead['12'].','.$dead['13'].','.$dead['14'].','.$dead['15'].','.$dead['16'].','.$dead['17'].','.$dead['18'].','.$dead['19'].','.$dead['20'].','.$DeadHeroByTribe[2].'';
                $unitsdead_def[3] = ''.$dead['21'].','.$dead['22'].','.$dead['23'].','.$dead['24'].','.$dead['25'].','.$dead['26'].','.$dead['27'].','.$dead['28'].','.$dead['29'].','.$dead['30'].','.$DeadHeroByTribe[3].'';
                $unitsdead_def[4] = ''.$dead['31'].','.$dead['32'].','.$dead['33'].','.$dead['34'].','.$dead['35'].','.$dead['36'].','.$dead['37'].','.$dead['38'].','.$dead['39'].','.$dead['40'].','.$DeadHeroByTribe[4].'';
                $unitsdead_def[5] = ''.$dead['41'].','.$dead['42'].','.$dead['43'].','.$dead['44'].','.$dead['45'].','.$dead['46'].','.$dead['47'].','.$dead['48'].','.$dead['49'].','.$dead['50'].','.$DeadHeroByTribe[5].'';

                $unitsdead_deff[1] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitsdead_deff[2] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitsdead_deff[3] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitsdead_deff[4] = '?,?,?,?,?,?,?,?,?,?,?,';
                $unitsdead_deff[5] = '?,?,?,?,?,?,?,?,?,?,?,';

                $deadhero = $dead['hero'];

                $totaldead_alldef[1] = $dead['1'] + $dead['2'] + $dead['3'] + $dead['4'] + $dead['5'] + $dead['6'] + $dead['7'] + $dead['8'] + $dead['9'] + $dead['10'];
                $totaldead_alldef[2] = $dead['11'] + $dead['12'] + $dead['13'] + $dead['14'] + $dead['15'] + $dead['16'] + $dead['17'] + $dead['18'] + $dead['19'] + $dead['20'];
                $totaldead_alldef[3] = $dead['21'] + $dead['22'] + $dead['23'] + $dead['24'] + $dead['25'] + $dead['26'] + $dead['27'] + $dead['28'] + $dead['29'] + $dead['30'];
                $totaldead_alldef[4] = $dead['31'] + $dead['32'] + $dead['33'] + $dead['34'] + $dead['35'] + $dead['36'] + $dead['37'] + $dead['38'] + $dead['39'] + $dead['40'];
                $totaldead_alldef[5] = $dead['41'] + $dead['42'] + $dead['43'] + $dead['44'] + $dead['45'] + $dead['46'] + $dead['47'] + $dead['48'] + $dead['49'] + $dead['50'];

                $totalDefenderDeaths = $totaldead_alldef[1] + $totaldead_alldef[2] + $totaldead_alldef[3] + $totaldead_alldef[4] + $totaldead_alldef[5] + $deadhero;
                $totalattackdead += $totalDefenderDeaths;

                // Set units returning from attack
                $database->modifyAttack($data['ref'], 1, $dead1);
                $database->modifyAttack($data['ref'], 2, $dead2);
                $database->modifyAttack($data['ref'], 3, $dead3);
                $database->modifyAttack($data['ref'], 4, $dead4);
                $database->modifyAttack($data['ref'], 5, $dead5);
                $database->modifyAttack($data['ref'], 6, $dead6);
                $database->modifyAttack($data['ref'], 7, $dead7);
                $database->modifyAttack($data['ref'], 8, $dead8);
                $database->modifyAttack($data['ref'], 9, $dead9);
                $database->modifyAttack($data['ref'], 10, $dead10);
                $database->modifyAttack($data['ref'], 11, $dead11);
                $unitsdead_att = ''.$dead1.','.$dead2.','.$dead3.','.$dead4.','.$dead5.','.$dead6.','.$dead7.','.$dead8.','.$dead9.','.$dead10.','.$dead11.'';

                $database->modifyAttack($data['ref'], 1, $traped1);
                $database->modifyAttack($data['ref'], 2, $traped2);
                $database->modifyAttack($data['ref'], 3, $traped3);
                $database->modifyAttack($data['ref'], 4, $traped4);
                $database->modifyAttack($data['ref'], 5, $traped5);
                $database->modifyAttack($data['ref'], 6, $traped6);
                $database->modifyAttack($data['ref'], 7, $traped7);
                $database->modifyAttack($data['ref'], 8, $traped8);
                $database->modifyAttack($data['ref'], 9, $traped9);
                $database->modifyAttack($data['ref'], 10, $traped10);
                $database->modifyAttack($data['ref'], 11, $traped11);
                $unitstraped_att = ''.$traped1.','.$traped2.','.$traped3.','.$traped4.','.$traped5.','.$traped6.','.$traped7.','.$traped8.','.$traped9.','.$traped10.','.$traped11.'';
                if($herosend_att > 0) {
                    $unitsdead_att_check = $unitsdead_att.','.$dead11;
                } else {
                    $unitsdead_att_check = $unitsdead_att;
                }


                //top 10 attack and defence update
                $totaldead_att = $dead1 + $dead2 + $dead3 + $dead4 + $dead5 + $dead6 + $dead7 + $dead8 + $dead9 + $dead10 + $dead11;
                $totalattackdead += $totaldead_att;
                if($totaldead_att > 0 && $dead11 == 0 && $Attacker['hero'] > 0) {

                    $smallbandage = $database->getEquippedHeroItem($AttackerID, 7);
					$smallbandageID = $smallbandage['id'];

                    if($smallbandageID != 0) {
                        $healmax = floor($totalsend_att / 4);
                        $totalheal = 0;
                        for ($i = 1; $i <= 10; $i++) {
                            ${'heal'.$i} = 0;
                        }
                        while($smallbandage['type'] > 0 && $totalheal < $healmax && $totalheal < $totaldead_att) {
                            for ($i = 1; $i <= 10; $i++) {
								if($smallbandage['type'] == 0) {
									break;
								}
                                if(${'heal'.$i} < ${'dead'.$i} && $totalheal < $healmax && $totalheal < $totaldead_att) {
                                    ${'heal'.$i}++;
                                    $smallbandage['type']--;
                                    $smallbandage['num']--;
                                    $totalheal++;
                                }
                            }
                        }
                        if($smallbandage['type'] <= 0) {
                            $database->setHeroInventory($AttackerID, "bag", 0);
                            $database->editProcItem($smallbandageID, 0);
                        }
						// remove or update item depending on quantity
                        if($smallbandage['num'] <= 0) {
                            $q = "DELETE FROM ".TB_PREFIX."heroitems where id = ".$smallbandageID;
                            $database->query($q);
                        }
						else {
							$database->editHeroType($smallbandageID, $smallbandage['type'], 2);
							$database->editHeroNum2($smallbandageID, $smallbandage['num'], 2);
						}
                    }

                    $bandage = $database->getEquippedHeroItem($AttackerID, 8);
                    $bandageID = $bandage['id'];

                    if($bandageID != 0) {
                        $healmax = floor($totalsend_att / 100 * 33);
                        $totalheal = 0;
                        for ($i = 1; $i <= 10; $i++) {
                            ${'heal'.$i} = 0;
                        }
                        while($bandage['type'] > 0 && $totalheal < $healmax && $totalheal < $totaldead_att) {
                            for ($i = 1; $i <= 10; $i++) {
								if($bandage['type'] == 0) {
									break;
								}
                                if(${'heal'.$i} < ${'dead'.$i} && $totalheal < $healmax && $totalheal < $totaldead_att) {
                                    ${'heal'.$i}++;
                                    $bandage['type']--;
                                    $bandage['num']--;
                                    $totalheal++;
                                }
                            }
                        }
                        if($bandage['type'] <= 0) {
                            $database->setHeroInventory($AttackerID, "bag", 0);
                            $database->editProcItem($bandageID, 0);
                        }
						// remove or update item depending on quantity
                        if($bandage['num'] <= 0) {
                            $q = "DELETE FROM ".TB_PREFIX."heroitems where id = ".$bandageID;
                            $database->query($q);
                        }
						else {
							$database->editHeroType($bandageID, $bandage['type'], 2);
							$database->editHeroNum2($bandageID, $bandage['num'], 2);
						}
                    }
                    if($totalheal > 0) {

                        $speeds = array();

                        //find slowest unit.
                        $tribeunit = ($targettribe - 1) * 10;
                        for ($i = 1; $i <= 10; $i++) {
                            if(${'heal'.$i} > 0) {
                                if($unitarray) {
                                    reset($unitarray);
                                }
                                $unitarray = $GLOBALS["u".($tribeunit + $i)];
                                $speeds[] = $unitarray['speed'];
                            }
                        }
                        $time = $this->procDistanceTime($from, $to, min($speeds), 1);
                        if($time < (86400 / HEAL_SPEED)) {
                            $time = 86400 / HEAL_SPEED;
                        }
                        $reference = $database->addAttack($to['wref'], $heal1, $heal2, $heal3, $heal4, $heal5, $heal6, $heal7, $heal8, $heal9, $heal10, 0, 3, 0, 0, 0, 0);
                        $datar = "0,0,0,0,0";
                        $database->addMovement(4, $from['wref'], $from['wref'], $reference, $datar, ($time + time()));
                    }
                }
                for ($i = 1; $i <= 50; $i++) {
                    $totalsend_def += $Defender['u'.$i];
                }
                $totalsend_def += $Defender['hero'] + $DefenderHeroRef;

                for ($i = 1; $i <= 50; $i++) {
                    $totaldead_def += $dead[''.$i.''];
                }
                $totaldead_def += $dead['hero'];

                $attackerCasualtiesByUnit = array();
                $attackerUnitStart = (($owntribe - 1) * 10);
                for ($i = 1; $i <= 10; $i++) {
                    $attackerCasualtiesByUnit[$attackerUnitStart + $i] = ${'dead'.$i};
                }
                $defensivePoints = calculateCombatRankingPoints($attackerCasualtiesByUnit, $dead11);
                $offensivePoints = calculateCombatRankingPoints($dead, $dead['hero']);

                $database->modifyPoints($toF['owner'], 'dpall', $defensivePoints);
                $database->modifyPoints($from['owner'], 'apall', $offensivePoints);
                $database->modifyPoints($toF['owner'], 'dp', $defensivePoints);
                $database->modifyPoints($from['owner'], 'ap', $offensivePoints);
                $database->modifyPointsAlly($targetally, 'Adp', $defensivePoints);
                $database->modifyPointsAlly($ownally, 'Aap', $offensivePoints);
                $database->modifyPointsAlly($targetally, 'dp', $defensivePoints);
                $database->modifyPointsAlly($ownally, 'ap', $offensivePoints);


                if(!$isoasis) {
                    $buildarray = $database->getResourceLevel($data['to']);
                    $crannyProtection = $this->calculateCrannyProtection(
                        $buildarray,
                        $owntribe,
                        $targettribe
                    );
                    $cranny = $crannyProtection['capacity'];
                    $cranny_eff = $crannyProtection['protected'];

                    // work out available resources.
                    $this->updateRes($data['to'], $to['owner']);
                    $this->pruneResource();

                    $totclay = $database->getVillageField($data['to'], 'clay');
                    $totiron = $database->getVillageField($data['to'], 'iron');
                    $totwood = $database->getVillageField($data['to'], 'wood');
                    $totcrop = $database->getVillageField($data['to'], 'crop');
                } else {
                    $cranny = 0;
                    $cranny_eff = 0;

                    // work out available resources.
                    $this->updateORes($data['to']);
                    $this->pruneOResource();

                    $totclay = $database->getOasisField($data['to'], 'clay');
                    $totiron = $database->getOasisField($data['to'], 'iron');
                    $totwood = $database->getOasisField($data['to'], 'wood');
                    $totcrop = $database->getOasisField($data['to'], 'crop');
                }
                $avclay = floor($totclay - $cranny_eff);
                $aviron = floor($totiron - $cranny_eff);
                $avwood = floor($totwood - $cranny_eff);
                $avcrop = floor($totcrop - $cranny_eff);

                $avclay = ($avclay < 0) ? 0 : $avclay;
                $aviron = ($aviron < 0) ? 0 : $aviron;
                $avwood = ($avwood < 0) ? 0 : $avwood;
                $avcrop = ($avcrop < 0) ? 0 : $avcrop;


                $avtotal = array($avwood, $avclay, $aviron, $avcrop);

                $av = $avtotal;

                // resources (wood,clay,iron,crop)
                $steal = array(0, 0, 0, 0);

                //bounty variables
                $btotal = $battlepart['bounty'];
                $bmod = 0;


                for ($i = 0; $i < 5; $i++) {
                    for ($j = 0; $j < 4; $j++) {
                        if(isset($avtotal[$j])) {
                            if($avtotal[$j] < 1)
                                unset($avtotal[$j]);
                        }
                    }
                    if(!$avtotal) {
                        // echo 'array empty'; *no resources left to take.
                        break;
                    }
                    if($btotal < 1 && $bmod < 1)
                        break;
                    if($btotal < 1) {
                        while($bmod) {
                            //random select
                            $rs = array_rand($avtotal);
                            if(isset($avtotal[$rs])) {
                                $avtotal[$rs] -= 1;
                                $steal[$rs] += 1;
                                $bmod -= 1;
                            }
                        }
                    }

                    // handle unballanced amounts.
                    $btotal += $bmod;
                    $bmod = $btotal % count($avtotal);
                    $btotal -= $bmod;
                    $bsplit = $btotal / count($avtotal);

                    $max_steal = (min($avtotal) < $bsplit) ? min($avtotal) : $bsplit;

                    for ($j = 0; $j < 4; $j++) {
                        if(isset($avtotal[$j])) {
                            $avtotal[$j] -= $max_steal;
                            $steal[$j] += $max_steal;
                            $btotal -= $max_steal;
                        }
                    }
                }


                //work out time of return
                $start = ($owntribe - 1) * 10 + 1;
                $end = ($owntribe * 10);

                $unitspeeds = array(6, 5, 7, 16, 14, 10, 4, 3, 4, 5,
                    7, 7, 6, 9, 10, 9, 4, 3, 4, 5,
                    7, 6, 17, 19, 16, 13, 4, 3, 4, 5,
                    7, 7, 6, 9, 10, 9, 4, 3, 4, 5,
                    7, 7, 6, 9, 10, 9, 4, 3, 4, 5);

                $speeds = array();

                //find slowest unit.
                for ($i = 1; $i <= 11; $i++) {
                    if($data['t'.$i] > $battlepart['casualties_attacker'][$i]) {
                        if($i == 11) {
                            if($heroarray) {
                                reset($heroarray);
                            }
                            $getVillage = $database->getVillage($data['vref']);
                            $heroarray = $database->getHeroData($getVillage['owner']);
                            $speeds[] = max(1, (int)$heroarray['speed']);
                        } else {
                            if($unitarray) {
                                reset($unitarray);
                            }
                            $unitarray = $GLOBALS["u".(($owntribe - 1) * 10 + $i)];
                            $speeds[] = max(1, (int)$unitarray['speed']);
                        }
                    }
                }


// Data for when troops return.

                //catapulten kijken :D
                $info_cat = $info_chief = $info_ram = ",";
                $catapultDestroyedVillage = false;

                // Oases have neither wall nor buildings: rams and catapults never
                // do anything there (and $wallid only exists on the village path).
                if($type == '3' && !$isoasis) {
                    if($rams != '0') {
                        if(isset($empty)) {
                            $info_ram = "".$ram_pic.", There is no wall to destroy.";
                        } else

                            if(isset($battlepart['wall_level_after']) && (int)$battlepart['wall_level_after'] === 0) {
                                $info_ram = "".$ram_pic.", Wall destroyed.";
                                $database->setVillageLevel($data['to'], "f".$wallid."", '0');
                                $database->setVillageLevel($data['to'], "f".$wallid."t", '0');
                                $pop = $this->recountPop($data['to']);

                            } elseif($battlepart[8] == 0) {

                                $info_ram = "".$ram_pic.", Wall was not damaged.";
                            } else {
                                $totallvl = isset($battlepart['wall_level_after'])
                                    ? max(0, (int)$battlepart['wall_level_after'])
                                    : $walllevel;
                                $info_ram = "".$ram_pic.",Wall damaged from level <b>".$walllevel."</b> to level <b>".$totallvl."</b>.";
                                $database->setVillageLevel($data['to'], "f".$wallid."", $totallvl);

                            }
                    }
                }
                if($type == '3' && !$isoasis && $catp != '0' && $toF['pop'] > 0) {
                    $catapultResolution = $this->resolveCatapultAttacks(
                        $data,
                        $battlepart,
                        $stonemason,
                        $to,
                        $breweryLevel
                    );
                    $info_cat = $catapultResolution['report'];
                    $catapultDestroyedVillage = $catapultResolution['village_destroyed'];
                }
                // A village can only be conquered by surviving, non-trapped chiefs
                // sent in a normal attack. Raids must never lower loyalty.
                $survivingChiefs = max(0, (int)$data['t9'] - (int)$dead9 - (int)$traped9);
                if(!$catapultDestroyedVillage && (int)$type === 3 && $survivingChiefs > 0) {
                    $attackerOwner = (int)$database->getVillageField($data['from'], 'owner');
                    $defenderOwner = (int)$database->getVillageField($data['to'], 'owner');
                    $settlementLockAcquired = $attackerOwner > 0
                        && $database->acquireSettlementLock($attackerOwner, 5);

                    if(!$settlementLockAcquired) {
                        $info_chief = "".$chief_pic.", No se pudo reservar la capacidad de expansión.";
                    } else {
                        try {
                            $conquestEligibility = $database->getConquestEligibility(
                                $data['from'],
                                $data['to'],
                                $attackerOwner,
                                $defenderOwner
                            );
                            $conquestStatus = $conquestEligibility['status'];

                            if($conquestStatus === 'eligible') {
                                $attackerCulturePoints = (int)$database->getUserField($attackerOwner, 'cp', 0);
                                $attackerVillageCount = count($database->getVillagesID($attackerOwner));
                                $pendingSettlements = $database->getPendingSettlementCountByOwner($attackerOwner);
                                $cultureEligibility = travianCultureExpansionEligibility(
                                    $attackerCulturePoints,
                                    $attackerVillageCount,
                                    $pendingSettlements,
                                    CP
                                );
                                if(!$cultureEligibility['eligible']) {
                                    $conquestStatus = 'culture';
                                }
                            }

                            if($conquestStatus === 'eligible') {
                                $loyaltyDamage = 0;
                                for($i = 0; $i < $survivingChiefs; $i++) {
                                    $loyaltyDamage += rand(15, 25);
                                }
                                if($breweryActive) {
                                    $loyaltyDamage = max(1, (int)floor($loyaltyDamage / 2));
                                }
                                $conquestResult = $database->applyConquestLoyalty(
                                    $data['from'],
                                    $data['to'],
                                    $attackerOwner,
                                    $defenderOwner,
                                    $data['ref'],
                                    $loyaltyDamage
                                );
                                $conquestStatus = $conquestResult['status'];
                            } else {
                                $conquestResult = $conquestEligibility;
                            }

                            if($conquestStatus === 'loyalty_reduced') {
                                $info_chief = "".$chief_pic.", Lealtad reducida de <b>".$conquestResult['old_loyalty']."</b> a <b>".$conquestResult['new_loyalty']."</b>.";
                            } elseif($conquestStatus === 'conquered') {
                                $info_chief = "".$chief_pic.", ¡Conquistaste la aldea!";
                            } else {
                                $conquestMessages = array(
                                    'same_owner' => 'No puedes conquistar una aldea propia.',
                                    'capital' => 'No puedes conquistar una capital.',
                                    'last_village' => 'No puedes conquistar la última aldea de un jugador.',
                                    'residence' => 'La Residencia o el Palacio todavía existe.',
                                    'no_slot' => 'No hay un cupo de expansión libre en la aldea atacante.',
                                    'culture' => 'No tienes suficientes puntos de cultura para conquistar otra aldea.',
                                    'source_changed' => 'La aldea atacante cambió de dueño.',
                                    'target_changed' => 'La aldea objetivo ya cambió de dueño.',
                                    'no_chief' => 'No quedó ningún jefe disponible para completar la conquista.',
                                    'busy' => 'Otra conquista sobre esta aldea se está procesando.',
                                    'database_error' => 'No se pudo completar la conquista por un error de base de datos.',
                                    'invalid' => 'La conquista no cumple los requisitos.'
                                );
                                $message = isset($conquestMessages[$conquestStatus])
                                    ? $conquestMessages[$conquestStatus]
                                    : $conquestMessages['database_error'];
                                $info_chief = "".$chief_pic.", ".$message;
                            }
                        } finally {
                            $database->releaseSettlementLock($attackerOwner);
                        }
                    }
                }

                if($data['t11'] > 0) {
                    if($isoasis != 0) {
                        //count oasis troops: $troops_o
                        $troops_o = 0;
                        $o_unit2 = mysql_query("select * from ".TB_PREFIX."units where `vref`='".$data['to']."'");
                        $o_unit = mysql_fetch_array($o_unit2);
                        for ($i = 1; $i <= 50; $i++) {
                            $troops_o += $o_unit[$i];
                        }

                        $o_unit2 = mysql_query("select * from ".TB_PREFIX."enforcement where `vref`='".$data['to']."'");
                        while($o_unit = @mysql_fetch_array($o_unit2)) {
                            for ($i = 1; $i <= 50; $i++) {
                                $troops_o += $o_unit[$i];
                            }
                            $troops_o += $o_unit['hero'];
                        }

                        // The oasis is annexed only if every defender died and the
                        // hero came back alive.
                        if($troops_o <= 0 && $dead11 == 0) {
                            //check hero mansion level
                            $hero_mansion_level = 0;
                            $dbo2 = mysql_query("select * from ".TB_PREFIX."fdata where `vref`='".$data['from']."'");
                            $dbo = mysql_fetch_array($dbo2);
                            for ($i = 19; $i <= 40; $i++) {
                                if($dbo['f'.$i.'t'] == 37) {
                                    $hero_mansion_level = $dbo['f'.$i];
                                }
                            }

                            //check number of occupied oasis
                            $dbo2 = mysql_query("select * from ".TB_PREFIX."odata where `conqured`='".$data['from']."'");
                            $number_o = mysql_num_rows($dbo2);

                            $dbo2 = mysql_query("select * from ".TB_PREFIX."odata where `wref`='".$data['to']."'");
                            $dbo = mysql_fetch_array($dbo2);
                            $o_conqured = $dbo['conqured'];
                            $o_loyalty = $dbo['loyalty'];
                            $holder_o = 0;
                            if($o_conqured != 0) {
                                $dbo3 = mysql_query("select * from ".TB_PREFIX."odata where `conqured`='".$o_conqured."'");
                                $holder_o = mysql_num_rows($dbo3);
                            }
                            $o_coor = $database->getCoor($data['to']);
                            $v_coor = $database->getCoor($data['from']);

                            $annexation = $this->oasisAnnexationOutcome(
                                array(
                                    'wref' => $data['from'],
                                    'x' => $v_coor['x'],
                                    'y' => $v_coor['y'],
                                    'mansion' => $hero_mansion_level,
                                    'oases' => $number_o
                                ),
                                array(
                                    'x' => $o_coor['x'],
                                    'y' => $o_coor['y'],
                                    'conqured' => $o_conqured,
                                    'loyalty' => $o_loyalty,
                                    'holder_oases' => $holder_o
                                )
                            );

                            switch($annexation['status']) {
                                case 'conquered':
                                    $a_uid = $database->getVillageField($data['from'], "owner");
                                    mysql_query("UPDATE ".TB_PREFIX."odata SET `conqured`='".$data['from']."', `owner`='".$a_uid."', `name`='Oasis conquistado', `loyalty`='100', `lastupdated`='".time()."', `lastupdated2`='".time()."' WHERE `wref`='".$data['to']."' ");
                                    mysql_query("UPDATE ".TB_PREFIX."wdata SET `occupied`='1' WHERE `id`='".$data['to']."' ");
                                    $info_chief = "".$hero_pic.", tu héroe conquistó este oasis.";
                                    break;
                                case 'loyalty_reduced':
                                    mysql_query("UPDATE ".TB_PREFIX."odata SET `loyalty`='".$annexation['loyalty']."', `lastupdated2`='".time()."' WHERE `wref`='".$data['to']."' ");
                                    $info_chief = "".$hero_pic.", la lealtad del oasis bajó de ".round($o_loyalty)."% a ".$annexation['loyalty']."%.";
                                    break;
                                case 'already_owned':
                                    $info_chief = "".$hero_pic.", tu héroe ya había conquistado este oasis.";
                                    break;
                                case 'oasis_limit':
                                    $info_chief = "".$hero_pic.", tu héroe ya conquistó 3 oasis.";
                                    break;
                                case 'mansion_too_low':
                                    $info_chief = "".$hero_pic.", necesitas una Mansión del Héroe de nivel ".$annexation['needed_mansion']." para conquistar este oasis.";
                                    break;
                                case 'out_of_range':
                                    $info_chief = "".$hero_pic.", este oasis está demasiado lejos: solo puedes conquistar oasis a 3 casillas o menos de la aldea.";
                                    break;
                            }
                        }
                    } else {
                        $artifact = $database->getOwnArtefactInfo($data['to']);
                        if($artifact['vref'] == $data['to']) {
                            if($database->canClaimArtifact($artifact['vref'], $artifact['size'])) {
                                $database->claimArtefact($data['to'], $data['to'], $database->getVillageField($data['from'], "owner"));
                                $info_chief = "".$hero_pic.", tu héroe lleva un artefacto de regreso a la aldea.";
                            } else {
                                $info_chief = "";
                            }
                        }
                    }
                }
                if($scout) {
                    if($data['spy'] == 1) {
                        $info_spy = "".$spy_pic.",
<tbody><tr><td class=\"empty\" colspan=\"12\"></td></tr></tbody>
<tbody class=\"goods\">
	<tr><th>".REPORT_RESOURCES."</th><td colspan=\"11\"><div class=\"res\"><div class=\"rArea\"><img class=\"r1\" src=\"img/x.gif\" title=\"".LUMBER."\">".round($totwood)."</div><div class=\"rArea\"><img class=\"r2\" src=\"img/x.gif\" title=\"".CLAY."\">".round($totclay)."</div><div class=\"rArea\"><img class=\"r3\" src=\"img/x.gif\" title=\"".IRON."\">".round($totiron)."</div><div class=\"rArea\"><img class=\"r4\" src=\"img/x.gif\" title=\"".CROP."\">".round($totcrop)."</div></div></td></tr></tbody>

<tbody class=\"goods\"><tr><th></th><td colspan=\"11\"><div class=\"res\"><div class=\"rArea\"><img class=\"gebIcon g23Icon\" src=\"img/x.gif\" title=\"Escondite\">".round($cranny)."</div></div></td></tr></tbody>";

                    } else if($data['spy'] == 2) {
                        $rptitle = 'Palacio/Residencia';
                        $rpid = 25; //Use icon for Residence
                        if($isoasis == 0) {
                            $basearray = $database->getMInfo($data['to']);
                            $resarray = $database->getResourceLevel($basearray['wref']);
                            $crannylevel = 0;
                            $rplevel = 0;
                            $walllevel = 0;
                            $tribe = $database->getUserField($basearray['owner'], 'tribe', 0);

                            for ($j = 19; $j <= 40; $j++) {
                                if($resarray['f'.$j.'t'] == 25) {
                                    $rplevel = (int)$resarray['f'.$j];
                                    $rptitle = 'Residencia';
                                    $rpid = 25;
                                    break;
                                } elseif($resarray['f'.$j.'t'] == 26) {
                                    $rplevel = (int)$resarray['f'.$j];
                                    $rptitle = 'Palacio';
                                    $rpid = 26;
                                    break;
                                }
                            }
                            // The wall always sits on field 40, its gid depends on the tribe
                            // (31 city wall, 32 earth wall, 33 palisade).
                            if(in_array((int)$resarray['f40t'], array(31, 32, 33), true)) {
                                $walllevel = (int)$resarray['f40'];
                            }
                            for ($j = 19; $j <= 40; $j++) {
                                if($resarray['f'.$j.'t'] == 23) {
                                    $crannylevel = (int)$resarray['f'.$j];
                                    break;
                                }
                            }
                        } else {
                            $crannylevel = 0;
                            $walllevel = 0;
                            $rplevel = 0;
                            $tribe = 0;
                        }
                        if($tribe == 1) {
                            $walltitle = 'Muralla';
                            $iconClass = 'gebIcon g3'.$tribe.'Icon';
                        } elseif($tribe == 2) {
                            $walltitle = 'Muro de tierra';
                            $iconClass = 'gebIcon g3'.$tribe.'Icon';
                        } elseif($tribe == 3) {
                            $walltitle = 'Empalizada';
                            $iconClass = 'gebIcon g3'.$tribe.'Icon';
                        } else {
                            /**
                             * @todo Not sure what Natar Wall should be called, also using City Wall for the icon for now
                             */
                            $walltitle = "Muralla natar";
                            $iconClass = 'gebIcon g31Icon';
                        }

                        $info_spy = "".$spy_pic.",
<tbody><tr><td class=\"empty\" colspan=\"12\"></td></tr></tbody>
<tbody class=\"goods\">
	<tr><th>Defensas</th><td colspan=\"11\"><div class=\"res\">
<div class=\"rArea\">
<img class=\"gebIcon g".$rpid."Icon\" src=\"img/x.gif\" title=\"".$rptitle."\">".$rptitle." <b> Nivel ".$rplevel."</b><Br>
<img class=\"".$iconClass."\" src=\"img/x.gif\" title=\"".$walltitle."\">".$walltitle." <b>Nivel ".$walllevel."</b>
</div>
</div></td></tr></tbody>";

                    }

                    $data2 = ''.$from['owner'].','.$from['wref'].','.$owntribe.','.$unitssend_att.','.$unitsdead_att.',0,0,0,0,0,'.$to['owner'].','.$to['wref'].','.addslashes($to['name']).','.$targettribe.',,,'.$rom.','.$unitssend_def[1].','.$unitsdead_def[1].','.$ger.','.$unitssend_def[2].','.$unitsdead_def[2].','.$gal.','.$unitssend_def[3].','.$unitsdead_def[3].','.$nat.','.$unitssend_def[4].','.$unitsdead_def[4].','.$natar.','.$unitssend_def[5].','.$unitsdead_def[5].','.$info_ram.','.$info_cat.','.$info_chief.','.$info_spy.','.$unitstraped_att;
                    if(!empty($spyReinforcements)) {
                        $spyReinforcementJson = json_encode($spyReinforcements);
                        if($spyReinforcementJson !== false) {
                            $data2 .= ',spyref:'.base64_encode($spyReinforcementJson);
                        }
                    }
                } else {
                    $data2 = ''.$from['owner'].','.$from['wref'].','.$owntribe.','.$unitssend_att.','.$unitsdead_att.','.$steal[0].','.$steal[1].','.$steal[2].','.$steal[3].','.$battlepart['bounty'].','.$to['owner'].','.$to['wref'].','.addslashes($to['name']).','.$targettribe.',,,'.$rom.','.$unitssend_def[1].','.$unitsdead_def[1].','.$ger.','.$unitssend_def[2].','.$unitsdead_def[2].','.$gal.','.$unitssend_def[3].','.$unitsdead_def[3].','.$nat.','.$unitssend_def[4].','.$unitsdead_def[4].','.$natar.','.$unitssend_def[5].','.$unitsdead_def[5].','.$info_ram.','.$info_cat.','.$info_chief.','.$info_spy.','.$unitstraped_att;
                }

                // When all troops die, sends no info.
                $data_fail = ''.$from['owner'].','.$from['wref'].','.$owntribe.','.$unitssend_att.','.$unitsdead_att.','.$steal[0].','.$steal[1].','.$steal[2].','.$steal[3].','.$battlepart['bounty'].','.$to['owner'].','.$to['wref'].','.addslashes($to['name']).',0,,,0,'.$unitssend_deff[1].','.$unitsdead_deff[1].',0,'.$unitssend_deff[2].','.$unitsdead_deff[2].',0,'.$unitssend_deff[3].','.$unitsdead_deff[3].',0,'.$unitssend_deff[4].','.$unitsdead_deff[4].',0,'.$unitssend_deff[5].','.$unitsdead_deff[5].',,,'.$unitstraped_att.',no-defense-info-v1';

                //Undetected and detected in here.
                if($scout) {
                    for ($i = 1; $i <= 11; $i++) {
                        if($battlepart['casualties_attacker'][$i]) {
                            $toAlly = $database->getUserField($to['owner'], 'alliance', 0);
                            $database->addNotice($to['owner'], $to['wref'], $toAlly, 0, ''.addslashes($from['name']).' espía a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                            break;
                        }
                    }
                } else {
                    if($type == 3 && $totalsend_att - ($totaldead_att + $totaltraped_att) > 0) {
                        $prisoners = $database->getPrisoners($to['wref']);
                        if(count($prisoners) > 0) {
                            $mytroops = 0;
                            $anothertroops = 0;
                            $releasedDeaths = 0;
                            foreach ($prisoners as $prisoner) {
                                $p_owner = (int)$database->getVillageField($prisoner['from'], "owner");
                                $p_alliance = (int)$database->getUserField($p_owner, "alliance", 0);
                                $isOwnPrisoner = $p_owner === (int)$from['owner'];
                                if(!$isOwnPrisoner && !$this->alliancesAreFriendly($p_alliance, $ownally)) {
                                    continue;
                                }
                                $released = $this->trappedTroopSurvivors($prisoner);
                                $capturedSnapshot = array();
                                for($i = 1; $i <= 11; $i++) {
                                    $capturedSnapshot[$i] = max(0,(int)$prisoner['t'.$i]);
                                }
                                $releaseCompleted = false;
                                if($isOwnPrisoner) {
                                    $releaseCompleted = $database->mergePrisonersIntoAttackAtomic(
                                        (int)$prisoner['id'],
                                        (int)$prisoner['wref'],
                                        (int)$prisoner['from'],
                                        (int)$data['ref'],
                                        $released['units'],
                                        $capturedSnapshot
                                    );
                                    if($releaseCompleted) {
                                        $mytroops += $released['survived'];
                                    }
                                } else {
                                    $p_tribe = (int)$database->getUserField($p_owner, "tribe", 0);
                                    $releaseCompleted = $this->queueFreedPrisonerReturn(
                                        $prisoner,
                                        $p_owner,
                                        $p_tribe,
                                        $released['units']
                                    );
                                    if($releaseCompleted) {
                                        $anothertroops += $released['survived'];
                                    }
                                }
                                if($releaseCompleted) {
                                    $releasedDeaths += $released['dead'];
                                }
                            }
                            $trapper_pic = "<img src=\"".GP_LOCATE."img/u/98.gif\" alt=\"Trampa\" title=\"Trampa\" />";
                            $p_username = $database->getUserField($from['owner'], "username", 0);
                            if($mytroops > 0 && $anothertroops > 0) {
                                $info_trap = "".$trapper_pic." ".$p_username." liberó <b>".$mytroops."</b> tropas propias y <b>".$anothertroops."</b> tropas aliadas.";
                            } elseif($mytroops > 0) {
                                $info_trap = "".$trapper_pic." ".$p_username." liberó <b>".$mytroops."</b> tropas propias.";
                            } elseif($anothertroops > 0) {
                                $info_trap = "".$trapper_pic." ".$p_username." liberó <b>".$anothertroops."</b> tropas aliadas.";
                            }
                            if($releasedDeaths > 0) {
                                $info_trap .= " <b>".$releasedDeaths."</b> tropas murieron durante la liberación.";
                            }
                        }
                    }
                    $data2 = $data2.','.addslashes($info_trap).',,';
                    if($totaldead_def == 0) {
                        $toAlly = $database->getUserField($to['owner'], 'alliance', 0);
                        if($totalsend_def == 0) {
                            $database->addNotice($to['owner'], $to['wref'], $toAlly, 7, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        } else {
                            $database->addNotice($to['owner'], $to['wref'], $toAlly, 4, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        }

                    } else {
                        $toAlly = $database->getUserField($to['owner'], 'alliance', 0);
                        if($totalsend_def > $totaldead_def) {
                            $database->addNotice($to['owner'], $to['wref'], $toAlly, 5, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        } else {
                            $database->addNotice($to['owner'], $to['wref'], $toAlly, 6, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        }
                    }
                }
                //to here

                // If the dead units not equal the ammount sent they will return and report
                if($totalsend_att - ($totaldead_att + $totaltraped_att) > 0) {
                    $endtime = $this->procDistanceTime($from, $to, empty($speeds) ? 1 : min($speeds), 1) + $AttackArrivalTime;
                    //$endtime = $this->procDistanceTime($from,$to,min($speeds),1) + time();
                    if($type == 1) {
                        $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                        $spyReportType = ($totaldead_att == 0 && $totaltraped_att == 0) ? 22 : 23;
                        $database->addNotice($from['owner'], $to['wref'], $fromAlly, $spyReportType, ''.addslashes($from['name']).' espía a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                    } else {
                        if($totaldead_att == 0 && $totaltraped_att == 0) {
                            $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                            $database->addNotice($from['owner'], $to['wref'], $fromAlly, 1, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        } else {
                            $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                            $database->addNotice($from['owner'], $to['wref'], $fromAlly, 2, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data2, $AttackArrivalTime);
                        }
                    }


                    $database->setMovementProc($data['moveid']);
                    $datar = "".$steal[0].",".$steal[1].",".$steal[2].",".$steal[3].",".$battlepart['bounty']."";
                    $database->addMovement(4, $to['wref'], $from['wref'], $data['ref'], $datar, $endtime);

                    // send the bounty on type 6.
                    if($type !== 1) {
                        $reference = $database->sendResource($steal[0], $steal[1], $steal[2], $steal[3], 0, 0);
                        if($isoasis == 0) {
                            $database->modifyResource($to['wref'], $steal[0], $steal[1], $steal[2], $steal[3], 0);
                        } else {
                            $database->modifyOasisResource($to['wref'], $steal[0], $steal[1], $steal[2], $steal[3], 0);
                        }
                        $database->addMovement(6, $to['wref'], $from['wref'], $reference, $datar, $endtime);
                        //$database->updateVillage($to['wref']);
                        $totalStolen = $steal[0] + $steal[1] + $steal[2] + $steal[3];
                        $database->modifyPoints($from['owner'], 'RR', $totalStolen);
                        $database->modifyPoints($to['owner'], 'RR', -$totalStolen);
                        $database->modifyPointsAlly($ownally, 'RR', $totalStolen);
                        $database->modifyPointsAlly($targetally, 'RR', -$totalStolen);
                    }
                } else //else they die and don't return or report.
                {
                    $database->setMovementProc($data['moveid']);
                    if($type == 1) {
                        $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                        $database->addNotice($from['owner'], $to['wref'], $fromAlly, 24, ''.addslashes($from['name']).' espía a '.addslashes($to['name']).'', $data_fail, $AttackArrivalTime);
                    } else {
                        $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                        $database->addNotice($from['owner'], $to['wref'], $fromAlly, 3, ''.addslashes($from['name']).' ataca a '.addslashes($to['name']).'', $data_fail, $AttackArrivalTime);
                    }
                }

                if($type == 3 or $type == 4) {
                    $database->addGeneralAttack($totalattackdead);
                }

            } else {
                $database->setMovementProc($data['moveid']);
                $datar = "0,0,0,0,0";
                $AttackArrivalTime = $data['endtime'];

                // El regreso viaja a la velocidad de la unidad más lenta enviada, no a la del héroe.
                $returnSpeeds = array();
                for ($i = 1; $i <= 11; $i++) {
                    if($data['t'.$i] <= 0) {
                        continue;
                    }
                    if($i == 11) {
                        $getHero = $database->getHeroData($AttackerID);
                        $returnSpeeds[] = max(1, (int)$getHero['speed']);
                    } else {
                        $unitarray = $GLOBALS["u".(($owntribe - 1) * 10 + $i)];
                        $returnSpeeds[] = max(1, (int)$unitarray['speed']);
                    }
                }
                $endtime = $this->procDistanceTime($from, $to, empty($returnSpeeds) ? 1 : min($returnSpeeds), 1) + $AttackArrivalTime;
                $database->addMovement(4, $to['wref'], $from['wref'], $data['ref'], $datar, $endtime);

                $cagesBefore = $cage['type'];
                $animals = 0;
                for ($i = 31; $i <= 40; $i++) {
                    $animals += $Defender['u'.$i];
                    $j = $i - 30;
                    ${'captured'.$j} = 0;
                }
                while($cage['type'] > 0 && $animals > 0) {
                    for ($i = 31; $i <= 40; $i++) {
                        if($Defender['u'.$i] > 0 && $cage['type'] > 0) {
                            $Defender['u'.$i]--;
                            $animals--;
                            $cage['type']--;
                            $cage['num']--;
                            $j = $i - 30;
                            ${'captured'.$j}++;
                        }
                    }
                }
                if($cage['type'] <= 0) {
                    $database->setHeroInventory($AttackerID, "bag", 0);
                    $database->editProcItem($cageID, 0);
                }
				// remove or update item depending on quantity
                if($cage['num'] <= 0) {
                    $q = "DELETE FROM ".TB_PREFIX."heroitems where id = ".$cageID;
                    $database->query($q);
                }
				else {
					$database->editHeroType($cageID, $cage['type'], 2);
					$database->editHeroNum2($cageID, $cage['num'], 2);
				}
                $total_captured = 0;
                $capturedList = '';
                for ($i = 1; $i <= 10; $i++) {
                    $total_captured += ${'captured'.$i};
                    $capturedList .= ','.${'captured'.$i};
                }

                // Informe de captura para el atacante (el oasis está libre, no hay defensor al que avisar).
                $oasiscoor = $database->getCoor($to['wref']);
                $oasisLabel = '('.$oasiscoor['x'].'|'.$oasiscoor['y'].')';
                if($total_captured > 0) {
                    $cageTopic = 'Tu héroe capturó '.$total_captured.' animales en el oasis '.$oasisLabel;
                } else {
                    $cageTopic = 'Tu héroe no capturó animales en el oasis '.$oasisLabel;
                }
                $data_cage = $from['wref'].','.$from['owner'].','.$to['wref'].$capturedList
                    .','.($cagesBefore - $cage['type']).','.$cage['type'].','.$animals;
                $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                $database->addNotice($from['owner'], $to['wref'], $fromAlly, 25, addslashes($cageTopic), $data_cage, $AttackArrivalTime);

                if($total_captured > 0) {

                    $speeds = array();

                    //find slowest captured animal.
                    for ($i = 31; $i <= 40; $i++) {
                        $j = $i - 30;
                        if(${'captured'.$j} <= 0) {
                            continue;
                        }
                        $database->modifyUnit($to['wref'], $i, ${'captured'.$j}, 0);
                        $unitarray = $GLOBALS["u".$i];
                        $speeds[] = max(1, (int)$unitarray['speed']);
                    }
                    $time = $this->procDistanceTime($from, $to, empty($speeds) ? 1 : min($speeds), 1);
                    $reference = $database->addAttack($to['wref'], $captured1, $captured2, $captured3, $captured4, $captured5, $captured6, $captured7, $captured8, $captured9, $captured10, 0, 2, 0, 0, 0, 0);
                    $database->addMovement(3, 0, $from['wref'], $reference, $datar, ($time + $AttackArrivalTime));

                }
            }
            /* $crop = $database->getCropProdstarv($to['wref']);
				$unitarrays = $this->getAllUnits($to['wref']);
				$getvillage = $database->getVillage($to['wref']);
				$village_upkeep = $getvillage['pop'] + $this->getUpkeep($unitarrays, 0, $to['wref']);
				if ($crop < $village_upkeep){
					// add starv data
					$database->setVillageField($to['wref'], 'starv', $village_upkeep);
					$database->setVillageField($to['wref'], 'starvupdate', time());
				} */
            unset($crop, $unitarrays, $getvillage, $village_upkeep);

        }
        if(file_exists("GameEngine/Prevention/sendunits.txt")) {
            @unlink("GameEngine/Prevention/sendunits.txt");
        }
    }

    private function trappedTroopSurvivors($prisoner) {
        $survivors = array();
        $remainders = array();
        $capturedTotal = 0;
        $deathsAssigned = 0;
        for($i = 1; $i <= 11; $i++) {
            $captured = max(0, (int)$prisoner['t'.$i]);
            $capturedTotal += $captured;
            $deaths = intdiv($captured, 4);
            $deathsAssigned += $deaths;
            $survivors[$i] = $captured - $deaths;
            $remainders[$i] = $captured % 4;
        }
        $deathsRemaining = (int)floor($capturedTotal / 4) - $deathsAssigned;
        arsort($remainders);
        foreach($remainders as $position => $remainder) {
            if($deathsRemaining <= 0) {
                break;
            }
            if($remainder > 0 && $survivors[$position] > 0) {
                $survivors[$position]--;
                $deathsRemaining--;
            }
        }
        return array(
            'units' => $survivors,
            'captured' => $capturedTotal,
            'survived' => array_sum($survivors),
            'dead' => $capturedTotal - array_sum($survivors)
        );
    }

    private function alliancesAreFriendly($firstAlliance,$secondAlliance) {
        global $database;
        $firstAlliance = (int)$firstAlliance;
        $secondAlliance = (int)$secondAlliance;
        if($firstAlliance <= 0 || $secondAlliance <= 0) {
            return false;
        }
        if($firstAlliance === $secondAlliance) {
            return true;
        }
        return $database->areAlliancesAllied($firstAlliance, $secondAlliance);
    }

    private function queueFreedPrisonerReturn($prisoner,$owner,$tribe,$survivors) {
        global $database;
        if(array_sum($survivors) <= 0) {
            return false;
        }
        $trapCoordinates = $database->getCoor($prisoner['wref']);
        $homeCoordinates = $database->getCoor($prisoner['from']);
        $speeds = array();
        for($i = 1; $i <= 10; $i++) {
            if($survivors[$i] > 0) {
                $unit = $GLOBALS['u'.(((int)$tribe - 1) * 10 + $i)];
                $speeds[] = $unit['speed'];
            }
        }
        if($survivors[11] > 0) {
            $hero = $database->getHeroData($owner);
            if(is_array($hero) && !empty($hero['speed'])) {
                $speeds[] = $hero['speed'];
            }
        }
        if(empty($speeds)) {
            return false;
        }
        $travelTime = $this->procDistanceTime(
            array('x' => $homeCoordinates['x'], 'y' => $homeCoordinates['y']),
            array('x' => $trapCoordinates['x'], 'y' => $trapCoordinates['y']),
            min($speeds),
            1
        );
        $start = time();
        return $database->returnPrisonersAtomic(
            (int)$prisoner['id'],
            (int)$prisoner['wref'],
            (int)$prisoner['from'],
            $survivors,
            $start,
            $start + max(1,(int)$travelTime),
            true,
            array(
                1 => max(0,(int)$prisoner['t1']),
                2 => max(0,(int)$prisoner['t2']),
                3 => max(0,(int)$prisoner['t3']),
                4 => max(0,(int)$prisoner['t4']),
                5 => max(0,(int)$prisoner['t5']),
                6 => max(0,(int)$prisoner['t6']),
                7 => max(0,(int)$prisoner['t7']),
                8 => max(0,(int)$prisoner['t8']),
                9 => max(0,(int)$prisoner['t9']),
                10 => max(0,(int)$prisoner['t10']),
                11 => max(0,(int)$prisoner['t11'])
            )
        );
    }

    private function buildSpyReinforcementSnapshot($enforcement) {
        global $database;

        $from = (int)$enforcement['from'];
        $owner = 0;
        $tribe = 4;
        if($from > 0) {
            $owner = (int)$database->getVillageField($from, 'owner');
            $tribe = (int)$database->getUserField($owner, 'tribe', 0);
        }

        if($tribe < 1 || $tribe > 5) {
            for($candidateTribe = 1; $candidateTribe <= 5; $candidateTribe++) {
                $candidateStart = ($candidateTribe - 1) * 10 + 1;
                for($unit = $candidateStart; $unit < $candidateStart + 10; $unit++) {
                    if(!empty($enforcement['u'.$unit])) {
                        $tribe = $candidateTribe;
                        break 2;
                    }
                }
            }
        }
        if($tribe < 1 || $tribe > 5) {
            return null;
        }

        $units = array();
        $hasTroops = !empty($enforcement['hero']);
        $start = ($tribe - 1) * 10 + 1;
        for($unit = $start; $unit < $start + 10; $unit++) {
            $amount = max(0, (int)$enforcement['u'.$unit]);
            $units[] = $amount;
            if($amount > 0) {
                $hasTroops = true;
            }
        }
        if(!$hasTroops) {
            return null;
        }

        return array(
            'from' => $from,
            'owner' => $owner,
            'tribe' => $tribe,
            'units' => $units,
            'hero' => max(0, (int)$enforcement['hero']),
            'nature' => $from === 0
        );
    }

    private function sendreinfunitsComplete() {
        if(file_exists("GameEngine/Prevention/sendreinfunits.txt")) {
            @unlink("GameEngine/Prevention/sendreinfunits.txt");
        }
        global $bid23, $database, $battle;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/sendreinfunits.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."movement, ".TB_PREFIX."attacks where ".TB_PREFIX."movement.ref = ".TB_PREFIX."attacks.id and ".TB_PREFIX."movement.proc = '0' and ".TB_PREFIX."movement.sort_type = '3' and ".TB_PREFIX."attacks.attack_type = '2' and endtime < $time";
        $dataarray = $database->query_return($q);

        foreach ($dataarray as $data) {
            $AttackArrivalTime = $data['endtime'];
            if($data['from'] == 0) {
                $to = $database->getMInfo($data['to']);
                //check if there is defence from town in to town
                $check = $database->getEnforce($data['to'], $data['from']);
                if(!isset($check['id'])) {
                    //no:
                    $database->addEnforce($data);
                } else {
                    //yes
                    $start = 31;
                    $end = 40;
                    //add unit.
                    $j = '1';
                    for ($i = $start; $i <= $end; $i++) {
                        $database->modifyEnforce($check['id'], $i, $data['t'.$j.''], 1);
                        $j++;
                    }
                }
                $to = $database->getMInfo($data['to']);
                $targetally = $database->getUserField($to['owner'], 'alliance', 0);
                $unitssend_att = ''.$data['t1'].','.$data['t2'].','.$data['t3'].','.$data['t4'].','.$data['t5'].','.$data['t6'].','.$data['t7'].','.$data['t8'].','.$data['t9'].','.$data['t10'].','.$data['t11'].'';
                $data_fail = '0,0,4,'.$unitssend_att.','.$to['wref'].','.$to['owner'];
                $database->addNotice($to['owner'], $to['wref'], $targetally, 8, 'la naturaleza reforzó '.addslashes($to['name']).'', $data_fail, $AttackArrivalTime);
                $database->setMovementProc($data['moveid']);
            } else {
                //set base things
                $owntribe = $database->getUserField($database->getVillageField($data['from'], "owner"), "tribe", 0);
                $targettribe = $database->getUserField($database->getVillageField($data['to'], "owner"), "tribe", 0);
                $to = $database->getMInfo($data['to']);
                $from = $database->getMInfo($data['from']);
                $toF = $database->getVillage($data['to']);
                $fromF = $database->getVillage($data['from']);
                $HeroTransfer = 0;

                //check to see if we're only sending a hero between own villages and there's a Mansion at target village
                if($data['t11'] != 0) {
                    if($database->getVillageField($data['from'], "owner") == $database->getVillageField($data['to'], "owner")) {
                        $check = $database->getEnforce($data['to'], $data['from']);
                        //don't reinforce, addunit instead
                        $database->modifyUnit($data['to'], 'hero', 1, 1);
                        $database->modifyEnforce($check['id'], 'hero', 1, 1);
                        $database->modifyHero2('wref', $data['to'], $database->getVillageField($data['from'], "owner"), 0);
                        $HeroTransfer = 1;
                    } else {
                        $check = $database->getEnforce($data['to'], $data['from']);
                        if($database->checkEnforce($data['to'], $data['from']) != 0) {
                            $database->modifyEnforce($check['id'], 'hero', 1, 1);
                        } else {
                            $database->addHeroEnforce($data);
                        }
                        $HeroTransfer = 1;
                    }
                }
                if(!$HeroTransfer) {
                    //check if there is defence from town in to town
                    $check = $database->getEnforce($data['to'], $data['from']);
                    if(!isset($check['id'])) {
                        //no:
                        $database->addEnforce($data);
                    } else {
                        //yes
                        $start = ($owntribe - 1) * 10 + 1;
                        $end = ($owntribe * 10);
                        //add unit.
                        $j = '1';
                        for ($i = $start; $i <= $end; $i++) {
                            $database->modifyEnforce($check['id'], $i, $data['t'.$j.''], 1);
                            $j++;
                        }
                    }
                }
                //send rapport
                $unitssend_att = ''.$data['t1'].','.$data['t2'].','.$data['t3'].','.$data['t4'].','.$data['t5'].','.$data['t6'].','.$data['t7'].','.$data['t8'].','.$data['t9'].','.$data['t10'].','.$data['t11'].'';
                $data_fail = ''.$from['wref'].','.$from['owner'].','.$owntribe.','.$unitssend_att.','.$to['wref'].','.$to['owner'].'';
                $fromAlly = $database->getUserField($from['owner'], 'alliance', 0);
                $database->addNotice($from['owner'], $to['wref'], $fromAlly, 8, ''.addslashes($from['name']).' reforzó '.addslashes($to['name']).'', $data_fail, $AttackArrivalTime);
                if($from['owner'] != $to['owner']) {
                    $toAlly = $database->getUserField($from['owner'], 'alliance', 0);
                    $database->addNotice($to['owner'], $to['wref'], $toAlly, 8, ''.addslashes($from['name']).' reforzó '.addslashes($to['name']).'', $data_fail, $AttackArrivalTime);
                }
                //update status
                $database->setMovementProc($data['moveid']);
            }
        }
        if(file_exists("GameEngine/Prevention/sendreinfunits.txt")) {
            @unlink("GameEngine/Prevention/sendreinfunits.txt");
        }
    }

    private function returnunitsComplete() {
        if(file_exists("GameEngine/Prevention/returnunits.txt")) {
            @unlink("GameEngine/Prevention/returnunits.txt");
        }
        global $database;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/returnunits.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."movement, ".TB_PREFIX."attacks where ".TB_PREFIX."movement.ref = ".TB_PREFIX."attacks.id and ".TB_PREFIX."movement.proc = '0' and ".TB_PREFIX."movement.sort_type = '4' and endtime < $time";
        $dataarray = $database->query_return($q);

        foreach ($dataarray as $data) {

            $tribe = $database->getUserField($database->getVillageField($data['to'], "owner"), "tribe", 0);

            if($tribe == 1) {
                $u = "";
            } elseif($tribe == 2) {
                $u = "1";
            } elseif($tribe == 3) {
                $u = "2";
            } elseif($tribe == 4) {
                $u = "3";
            } else {
                $u = "4";
            }
            $database->modifyUnit($data['to'], $u."1", $data['t1'], 1);
            $database->modifyUnit($data['to'], $u."2", $data['t2'], 1);
            $database->modifyUnit($data['to'], $u."3", $data['t3'], 1);
            $database->modifyUnit($data['to'], $u."4", $data['t4'], 1);
            $database->modifyUnit($data['to'], $u."5", $data['t5'], 1);
            $database->modifyUnit($data['to'], $u."6", $data['t6'], 1);
            $database->modifyUnit($data['to'], $u."7", $data['t7'], 1);
            $database->modifyUnit($data['to'], $u."8", $data['t8'], 1);
            $database->modifyUnit($data['to'], $u."9", $data['t9'], 1);
            $database->modifyUnit($data['to'], $tribe."0", $data['t10'], 1);
            $database->modifyUnit($data['to'], "hero", $data['t11'], 1);
            $database->setMovementProc($data['moveid']);
        }

        // Recieve the bounty on type 6.

        $q = "SELECT * FROM ".TB_PREFIX."movement, ".TB_PREFIX."send where ".TB_PREFIX."movement.ref = ".TB_PREFIX."send.id and ".TB_PREFIX."movement.proc = 0 and sort_type = 6 and endtime < $time";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {

            if($data['wood'] >= $data['clay'] && $data['wood'] >= $data['iron'] && $data['wood'] >= $data['crop']) {
                $sort_type = "10";
            } elseif($data['clay'] >= $data['wood'] && $data['clay'] >= $data['iron'] && $data['clay'] >= $data['crop']) {
                $sort_type = "11";
            } elseif($data['iron'] >= $data['wood'] && $data['iron'] >= $data['clay'] && $data['iron'] >= $data['crop']) {
                $sort_type = "12";
            } elseif($data['crop'] >= $data['wood'] && $data['crop'] >= $data['clay'] && $data['crop'] >= $data['iron']) {
                $sort_type = "13";
            }

            $to = $database->getMInfo($data['to']);
            $from = $database->getMInfo($data['from']);
            $database->modifyResource($data['to'], $data['wood'], $data['clay'], $data['iron'], $data['crop'], 1);
            //$database->updateVillage($data['to']);
            $database->setMovementProc($data['moveid']);
        }
        $this->pruneResource();
        if(file_exists("GameEngine/Prevention/returnunits.txt")) {
            @unlink("GameEngine/Prevention/returnunits.txt");
        }
    }

    private function sendSettlersComplete() {
        if(file_exists("GameEngine/Prevention/settlers.txt")) {
            @unlink("GameEngine/Prevention/settlers.txt");
        }
        global $database, $building;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/settlers.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."movement where proc = 0 and sort_type = 5 and endtime < $time ORDER BY endtime ASC, moveid ASC";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $source = $database->getMInfo($data['from']);
            $owner = (int)$data['data'];
            if($owner <= 0 && is_array($source)) {
                $owner = (int)$source['owner'];
            }
            if($owner <= 0 || !$database->acquireSettlementLock($owner,5)) {
                continue;
            }
            try {
                $cultureEligibility = travianCultureExpansionEligibility(
                    (int)$database->getUserField($owner,'cp',0),
                    count($database->getVillagesID($owner)),
                    0,
                    CP
                );
                if(!$cultureEligibility['eligible']) {
                    if($this->refundSettlementAssets($data,$owner)) {
                        $database->setMovementProc($data['moveid']);
                    }
                    continue;
                }
                if(!$database->claimFieldForSettlement($data['to'])) {
                    if(!$database->checkVilExist($data['to'])) {
                        continue;
                    }
                    if($this->refundSettlementAssets($data,$owner)) {
                        $database->setMovementProc($data['moveid']);
                    }
                    continue;
                }

                $user = $database->getUserField($owner,'username',0);
                $villageAdded = $database->addVillage($data['to'],$owner,$user,0)
                    && (int)$database->getVillageField($data['to'],'owner') === $owner;
                if(!$villageAdded) {
                    $database->releaseUninitializedSettlementClaim($data['to']);
                    continue;
                }
                $created = $database->addResourceFields($data['to'],$database->getVillageType($data['to']))
                    && $database->addUnits($data['to'])
                    && $database->addTech($data['to'])
                    && $database->addABTech($data['to']);
                if(!$created) {
                    $database->cleanupFailedSettlement($data['to'],$owner);
                    continue;
                }

                $sourceOwner = (int)$database->getVillageField($data['from'],'owner');
                if($sourceOwner === $owner && !$database->assignExpansionSlot($data['from'],$data['to'],$owner)) {
                    if($database->cleanupFailedSettlement($data['to'],$owner)
                        && $this->refundSettlementAssets($data,$owner)) {
                        $database->setMovementProc($data['moveid']);
                    }
                    continue;
                }
                if($database->setMovementProc($data['moveid'])) {
                    $database->markFollowupQuestAchieved($owner,9);
                    $database->markFollowupQuestAchieved($owner,10);
                }
            } finally {
                $database->releaseSettlementLock($owner);
            }
        }
        if(file_exists("GameEngine/Prevention/settlers.txt")) {
            @unlink("GameEngine/Prevention/settlers.txt");
        }
    }

    private function refundSettlementAssets($movement, $owner) {
        global $database;
        $owner = (int)$owner;
        $destination = (int)$movement['from'];
        if((int)$database->getVillageField($destination,'owner') !== $owner) {
            $villages = $database->getVillagesID($owner);
            $destination = !empty($villages) ? (int)$villages[0] : 0;
        }
        if($destination <= 0) {
            return true;
        }
        if(!is_array($database->getUnit($destination))) {
            return false;
        }
        $tribe = (int)$database->getUserField($owner,'tribe',0);
        $settlerUnit = $tribe * 10;
        if($settlerUnit < 10 || $settlerUnit > 50) {
            return false;
        }
        return $database->refundFoundingAssets($destination,$owner,$settlerUnit);
    }

    private function sendAdventuresComplete() {
        if(file_exists("GameEngine/Prevention/adventures.txt")) {
            @unlink("GameEngine/Prevention/adventures.txt");
        }
        global $database, $building, $session;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/adventures.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."movement where proc = 0 and sort_type = 9 and endtime <= $time";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $from = $database->getMInfo($data['from']);
            $to = $database->getAInfo($data['to']);
            $owner = $database->getUserField($from['owner'], 'username', 0);
            $ally = $database->getUserField($from['owner'], 'alliance', 0);
            $tribe = $database->getUserField($from['owner'], 'tribe', 0);
            $ownerID = $from['owner'];
            $coor = $database->getCoor($data['to']);
            $getHero = $database->getHeroData($ownerID);
            $getAdv = $database->getAdventure($ownerID, $data['to']);
            $heroface = $database->HeroFace($ownerID);
            $helmetID = $database->getHeroItemID($ownerID, 1);
            if($helmetID != 0) {
                $helmet = $database->getItemData($helmetID);
            }
            $notroops = rand(0, 3);
            if($notroops > 0) {
                $nosilver = rand(0, 3);
                if($nosilver > 0) {
                    $btype = rand(0, 15);

                    if($btype == 1) {
                        if($time >= (COMMENCE + 604800)) {
                            $ntype = array(1 => 1, 2, 4, 5, 7, 8, 10, 11, 13, 14);
                        } elseif($time >= (COMMENCE + 1209600)) {
                            $ntype = array(1 => 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15);
                        } else {
                            $ntype = array(1 => 1, 4, 7, 10, 13);
                        }
                    } /*elseif($btype==2){
					if($time >= (COMMENCE+604800)){
						$ntype = array(1=>82,83,85,86,88,89,91,92);
					}
					elseif($time >= (COMMENCE+1209600)){
						$ntype = array(1=>82,83,84,85,86,87,88,89,90,91,92,93);
					}
					else{
						$ntype = array(1=>82,85,88,91);
					}
				}*/
                    elseif($btype == 3) {
                        if($time >= (COMMENCE + 604800)) {
                            $ntype = array(1 => 61, 62, 64, 65, 67, 68, 73, 74, 79, 80);
                        } elseif($time >= (COMMENCE + 1209600)) {

                            $ntype = array(1 => 61, 62, 63, 64, 65, 66, 67, 68, 69, 73, 74, 75, 76, 77, 78, 79, 80, 81);
                        } else {
                            $ntype = array(1 => 61, 64, 67, 73, 79);
                        }
                    } elseif($btype == 4) {
                        if($time >= (COMMENCE + 604800)) {
                            if($tribe == 1) {
                                $ntype = array(1 => 16, 17, 19, 20, 22, 23, 25, 26, 28, 29);
                            } elseif($tribe == 2) {
                                $ntype = array(1 => 46, 47, 49, 50, 52, 53, 55, 56, 58, 59);
                            } elseif($tribe == 3) {
                                $ntype = array(1 => 31, 32, 34, 35, 37, 38, 40, 41, 43, 44);
                            }
                        } elseif($time >= (COMMENCE + 1209600)) {
                            if($tribe == 1) {
                                $ntype = array(1 => 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30);
                            } elseif($tribe == 2) {
                                $ntype = array(1 => 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60);
                            } elseif($tribe == 3) {
                                $ntype = array(1 => 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45);
                            }
                        } else {
                            if($tribe == 1) {
                                $ntype = array(1 => 16, 19, 22, 25, 28);
                            } elseif($tribe == 2) {
                                $ntype = array(1 => 46, 49, 52, 55, 58);
                            } elseif($tribe == 3) {
                                $ntype = array(1 => 31, 34, 37, 40, 43);
                            }
                        }
                    } elseif($btype == 5) {
                        if($time >= (COMMENCE + 604800)) {
                            $ntype = array(1 => 94, 95, 97, 98, 100, 101);
                        } elseif($time >= (COMMENCE + 1209600)) {
                            $ntype = array(1 => 94, 95, 96, 98, 99, 100, 101, 102);
                        } else {
                            $ntype = array(1 => 94, 97, 100);
                        }
                    } elseif($btype == 6) {
                        if($time >= (COMMENCE + 604800)) {
                            $ntype = array(1 => 103, 104);
                        } elseif($time >= (COMMENCE + 1209600)) {
                            $ntype = array(1 => 103, 104, 105);
                        } else {
                            $ntype = array(1 => 103);
                        }
                    } elseif($btype >= 7) {
                        $ntype = array(7 => 112, 113, 114, 107, 106, 108, 110, 109, 111);
                    }
                    if($getAdv['dif'] == 0) {
                        $exp = rand(0, 40);
                        $sgh = 1000;
                    } else {
                        $exp = rand(10, 80);
                        $sgh = 2000;
                    }
                    $health = round((3.007 / max(1,heroFightingStrength($getHero,$tribe))) * $sgh);

                    $exp = heroExperienceWithHelmet($database, $ownerID, $exp);
                    $database->modifyHero2('experience', $exp, $ownerID, 1);
                    $database->setMovementProc($data['moveid']);
                    $database->editTableField('adventure', 'end', 1, 'wref', $data['to']);

                    if(($getHero['health'] - $health) <= 0) {
                        $database->modifyHero2('dead', 1, $ownerID, 0);
                        $database->modifyHero2('health', $health, $ownerID, 2);
                        $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',dead,tu héroe no sobrevivió a la aventura.,,'.$health.','.$exp.'', $data['endtime']);
                    } else {
                        if($btype >= 7) {
                            $nntype = $ntype[$btype];
                            if($btype == 9) {
                                $num = rand(6, 20);
                            } elseif($btype == 12 or $btype == 13 or $btype == 15) {
                                $num = 1;
                            } else {
                                $num = rand(20, 50);
                            }
							if($btype == 7 || $btype == 8 || $btype == 9) {
								$id = $database->getHeroItemID($ownerID, $btype);
								if($id != 0) {
                                    $database->editHeroNum2($id, $num, 1);
                                } else {
                                    $database->addHeroItem($ownerID, $btype, 0, $num);
                                }
							}
							else if($btype == 10 || $btype == 11 || $btype == 14 || $btype == 15) {
                                if($database->checkHeroItem($ownerID, $btype)) {
                                    $id = $database->getHeroItemID($ownerID, $btype);
                                    $database->editHeroNum($id, $num, 1);
                                } else {
                                    $database->addHeroItem($ownerID, $btype, $nntype, $num);
                                }
                            }
							else {
                                $database->addHeroItem($ownerID, $btype, $nntype, $num);
                            }
                        } else {
                            if($btype == 1 or $btype > 2) {
                                $num = 1;
                                $s2 = rand(1, count($ntype));
                                $nntype = $ntype[$s2];
                                $database->addHeroItem($ownerID, $btype, $nntype, $num);
                            }
                        }
                        if($btype == 0 or $btype == 2) {
                            $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',,No se encontró nada valioso,,'.$health.','.$exp.'', $data['endtime']);
                        } else {
                            $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].','.$btype.','.$nntype.','.$num.','.$health.','.$exp.'', $data['endtime']);
                        }
                        $database->modifyHero2('health', $health, $ownerID, 2);
                        $ref = $database->addAttack($from['wref'], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 3, 0, 0, 0);
                        $AttackArrivalTime = $data['endtime'];
                        $speeds = array();
                        $speeds[] = $getHero['speed'];
                        $endtime = $this->procDistanceTime($from, $to, min($speeds), 1) + $AttackArrivalTime;
                        $database->addMovement(4, $data['to'], $data['from'], $ref, '0,0,0,0,0', $endtime);
                    }
                } else {
                    if($getAdv['dif'] == 0) {
                        $exp = rand(0, 40);
                        $sgh = 1000;
                    } else {
                        $exp = rand(10, 80);
                        $sgh = 2000;
                    }
                    $health = round((3.007 / max(1,heroFightingStrength($getHero,$tribe))) * $sgh);

                    $exp = heroExperienceWithHelmet($database, $ownerID, $exp);
                    $database->modifyHero2('experience', $exp, $ownerID, 1);
                    $database->setMovementProc($data['moveid']);
                    $database->editTableField('adventure', 'end', 1, 'wref', $data['to']);

                    if(($getHero['health'] - $health) <= 0) {
                        $database->modifyHero2('dead', 1, $ownerID, 0);
                        $database->modifyHero2('health', $health, $ownerID, 2);
                        $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',dead,tu héroe no sobrevivió a la aventura.,,'.$health.','.$exp.'', $data['endtime']);
                    } else {
                        $amt = rand(300, 1000);
                        $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',17,0,'.$amt.','.$health.','.$exp.'', $data['endtime']);

                        $database->modifyHero2('health', $health, $ownerID, 2);
                        $database->updateUserField($ownerID, 'silver', $amt, 2);
                        $ref = $database->addAttack($from['wref'], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 3, 0, 0, 0);
                        $AttackArrivalTime = $data['endtime'];
                        $speeds = array();
                        $speeds[] = $getHero['speed'];
                        $endtime = $this->procDistanceTime($from, $to, min($speeds), 1) + $AttackArrivalTime;
                        $database->addMovement(4, $data['to'], $data['from'], $ref, '0,0,0,0,0', $endtime);
                    }
                }
            } else {
                if($getAdv['dif'] == 0) {
                    $exp = rand(0, 40);
                    $sgh = 1000;
                } else {
                    $exp = rand(10, 80);
                    $sgh = 2000;
                }
                $health = round((3.007 / max(1,heroFightingStrength($getHero,$tribe))) * $sgh);

                $exp = heroExperienceWithHelmet($database, $ownerID, $exp);
                $database->modifyHero2('experience', $exp, $ownerID, 1);
                $database->setMovementProc($data['moveid']);
                $database->editTableField('adventure', 'end', 1, 'wref', $data['to']);

                if(($getHero['health'] - $health) <= 0) {
                    $database->modifyHero2('dead', 1, $ownerID, 0);
                    $database->modifyHero2('health', $health, $ownerID, 2);
                    $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',dead,tu héroe no sobrevivió a la aventura.,,'.$health.','.$exp.'', $data['endtime']);
                } else {
                    $unit = rand(1, 6);
                    if(($tribe != 3 && $unit < 4) or ($tribe == 3 && $unit < 3)) {
                        $amt = rand(20, 40);
                    } else if(($tribe != 3 && $unit == 4) or ($tribe == 3 && $unit == 3)) {
                        $amt = rand(10, 20);
                    } else {
                        $amt = rand(5, 10);
                    }
                    $database->addNotice($ownerID, $data['to'], $ally, 9, ''.addslashes($from['name']).' explora ('.addslashes($coor['x']).'|'.addslashes($coor['y']).')', ''.$from['wref'].',16,'.$unit.','.$amt.','.$health.','.$exp.'', $data['endtime']);

                    $database->modifyHero2('health', $health, $ownerID, 2);
                    $ref = $database->addAttack($from['wref'], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 3, 0, 0, 0);
                    $AttackArrivalTime = $data['endtime'];
                    $speeds = array();
                    $speeds[] = $getHero['speed'];
                    $endtime = $this->procDistanceTime($from, $to, min($speeds), 1) + $AttackArrivalTime;
                    $database->addMovement(4, $data['to'], $data['from'], $ref, '0,0,0,0,0', $endtime);

                    $ref = $database->addAttack($from['wref'], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3, 0, 0, 0);
                    $database->modifyAttack2($ref, $unit, $amt);
                    $speeds1 = array();
                    $unitarray = $GLOBALS["u".(($tribe - 1) * 10 + $unit)];
                    $speeds1[] = $unitarray['speed'];
                    $endtime = $this->procDistanceTime($from, $to, min($speeds1), 1) + $AttackArrivalTime;
                    $database->addMovement(4, $data['to'], $data['from'], $ref, '0,0,0,0,0', $endtime);
                }
            }
        }
        $q2 = "SELECT * FROM ".TB_PREFIX."adventure where time <= $time";
        $dataarray2 = $database->query_return($q2);
        foreach ($dataarray2 as $data2) {
            $database->editTableField('adventure', 'end', 1, 'id', $data2['id']);
        }

        if(file_exists("GameEngine/Prevention/adventures.txt")) {
            @unlink("GameEngine/Prevention/adventures.txt");
        }
    }

    private function researchComplete() {
        if(file_exists("GameEngine/Prevention/research.txt")) {
            @unlink("GameEngine/Prevention/research.txt");
        }
        global $database;
        $time = time();
        $ourFileHandle = @fopen("GameEngine/Prevention/research.txt", 'w');
        @fclose($ourFileHandle);
        $q = "SELECT * FROM ".TB_PREFIX."research where timestamp < $time";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $sort_type = substr($data['tech'], 0, 1);
            switch($sort_type) {
                case "t":
                    $q = "UPDATE ".TB_PREFIX."tdata set ".$data['tech']." = 1 where vref = ".$data['vref'];
                    break;
                case "a":
                case "b":
                    $q = "UPDATE ".TB_PREFIX."abdata set ".$data['tech']." = ".$data['tech']." + 1 where vref = ".$data['vref'];
                    break;
            }
            $database->query($q);
            $q = "DELETE FROM ".TB_PREFIX."research where id = ".$data['id'];
            $database->query($q);
        }
        if(file_exists("GameEngine/Prevention/research.txt")) {
            @unlink("GameEngine/Prevention/research.txt");
        }
    }

    private function updateRes($bountywid, $uid) {
        global $session;
        $this->bountyLoadTown($bountywid);
        $this->bountycalculateProduction($bountywid, $uid);
        $this->bountyprocessProduction($bountywid);
    }

    private function updateORes($bountywid) {
        global $session;
        $this->bountyLoadOTown($bountywid);
        $this->bountycalculateOProduction($bountywid);
        $this->bountyprocessOProduction($bountywid);
    }

    private function bountyLoadOTown($bountywid) {
        global $database, $session, $logging, $technology;
        $this->bountyOinfoarray = $database->getOasisV($bountywid);
        $this->bountyOresarray = $database->getResourceLevel($bountywid);
        $this->bountyOpop = 2;

    }

    private function bountyLoadTown($bountywid) {
        global $database, $session, $logging, $technology;
        $this->bountyinfoarray = $database->getVillage($bountywid);
        $this->bountyresarray = $database->getResourceLevel($bountywid);
        $this->bountyoasisowned = $database->getOasis($bountywid);
        $this->bountyocounter = $this->bountysortOasis();
        $this->bountypop = $this->bountyinfoarray['pop'];

        //$unitarray = $database->getUnit($bountywid);
        //if(count($unitarray) > 0) {
        //    for($i=1;$i<=50;$i++) {
        //        $this->bountyunitall['u'.$i] = $unitarray['u'.$i];
        //    }
        //}
        //$enforcearray = $database->getEnforceVillage($bountywid,0);
        //if(count($enforcearray) > 0) {
        //    foreach($enforcearray as $enforce) {
        //        for($i=1;$i<=50;$i++) {
        //            $this->bountyunitall['u'.$i] += $enforce['u'.$i];
        //        }
        //    }
        //}
    }

    private function bountysortOasis() {
        $crop = $clay = $wood = $iron = 0;
        foreach ($this->bountyoasisowned as $oasis) {
            switch($oasis['type']) {
                case 1:
                    $wood += 1;
                    break;
                case 2:
                    $wood += 2;
                    break;
                case 3:
                    $wood += 1;
                    $crop += 1;
                    break;
                case 4:
                    $clay += 1;
                    break;
                case 5:
                    $clay += 2;
                    break;
                case 6:
                    $clay += 1;
                    $crop += 1;
                    break;
                case 7:
                    $iron += 1;
                    break;
                case 8:
                    $iron += 2;
                    break;
                case 9:
                    $iron += 1;
                    $crop += 1;
                    break;
                case 10:
                case 11:
                    $crop += 1;
                    break;
                case 12:
                    $crop += 2;
                    break;
            }
        }
        return array($wood, $clay, $iron, $crop);
    }

    function getAllUnits($base) {
        global $technology;
        return $technology->getAllUnits($base);
    }

    public function getUpkeep($array, $type, $vid = 0, $prisoners = 0) {
        global $database, $session, $village;
        if($vid == 0) {
            $vid = $village->wid;
        }
        $buildarray = array();
        if($vid != 0) {
            $buildarray = $database->getResourceLevel($vid);
        }
        $horseDrinkingLevel = 0;
        $villageOwner = $vid > 0 ? (int)$database->getVillageField($vid, 'owner') : 0;
        $villageTribe = $villageOwner > 0 ? (int)$database->getUserField($villageOwner, 'tribe', 0) : 0;
        if($villageTribe === 1 && is_array($buildarray)) {
            for($j = 19; $j <= 38; $j++) {
                if((int)$buildarray['f'.$j.'t'] === 41) {
                    $horseDrinkingLevel = (int)$buildarray['f'.$j];
                    break;
                }
            }
        }
        $upkeep = 0;
        $nocrop = 0;
        switch($type) {
            case 0:
                $start = 1;
                $end = 50;
                break;
            case 1:
                $start = 1;
                $end = 10;
                break;
            case 2:
                $start = 11;
                $end = 20;
                break;
            case 3:
                $start = 21;
                $end = 30;
                break;
            case 4:
                // Ver Technology::getUpkeep: los animales sí consumen cereal en la aldea.
                $start = 31;
                $end = 40;
                break;
            case 5:
                $start = 41;
                $end = 50;
                break;
        }
        if($nocrop == 0) {
            for ($i = $start; $i <= $end; $i++) {
                $k = $i - $start + 1;
                $unit = "u".$i;
                $unit2 = "t".$k;
                global $$unit;
                $dataarray = $$unit;
                $hasReducedUpkeep = ($i == 4 && $horseDrinkingLevel >= 10)
                    || ($i == 5 && $horseDrinkingLevel >= 15)
                    || ($i == 6 && $horseDrinkingLevel >= 20);
                if($prisoners == 0) {
                    $amount = isset($array[$unit]) ? max(0, (int)$array[$unit]) : 0;
                    if($hasReducedUpkeep) {
                        $upkeep += max(0, $dataarray['pop'] - 1) * $amount;
                    } else {
                        $upkeep += $dataarray['pop'] * $amount;
                    }
                } else {
                    $amount = isset($array[$unit2]) ? max(0, (int)$array[$unit2]) : 0;
                    if($hasReducedUpkeep) {
                        $upkeep += max(0, $dataarray['pop'] - 1) * $amount;
                    } else {
                        $upkeep += $dataarray['pop'] * $amount;
                    }
                }
            }
            //   $unit = "hero";
            //   global $$unit;
            //   $dataarray = $$unit;
            if($prisoners == 0) {
                $upkeep += (isset($array['hero']) ? max(0, (int)$array['hero']) : 0) * 6;
            } else {
                $upkeep += (isset($array['t11']) ? max(0, (int)$array['t11']) : 0) * 6;
            }
        }
        return $upkeep;
    }

    private function bountycalculateOProduction($bountywid) {
        global $technology, $database;
        $this->bountyOproduction['wood'] = $this->bountyGetOWoodProd();
        $this->bountyOproduction['clay'] = $this->bountyGetOClayProd();
        $this->bountyOproduction['iron'] = $this->bountyGetOIronProd();
        $this->bountyOproduction['crop'] = $this->bountyGetOCropProd();
    }

	    private function bountycalculateProduction($bountywid, $uid) {
	        global $technology, $database;
	        $normalA = $database->getOwnArtefactInfoByType($bountywid, 4);
	        $largeA = $database->getOwnUniqueArtefactInfo($uid, 4, 2);
	        $uniqueA = $database->getOwnUniqueArtefactInfo($uid, 4, 3);
	        $upkeep = $this->getUpkeep($this->getAllUnits($bountywid), 0, $bountywid);
	        $heroData = $database->getHeroData($uid);
	        $heroProduction = heroVillageResourceBonus($heroData, $bountywid, SPEED);

	        $this->bountyproduction['wood'] = $this->bountyGetWoodProd()+$heroProduction['wood'];
	        $this->bountyproduction['clay'] = $this->bountyGetClayProd()+$heroProduction['clay'];
	        $this->bountyproduction['iron'] = $this->bountyGetIronProd()+$heroProduction['iron'];

	        if($uniqueA['size'] == 3 && $uniqueA['owner'] == $uid) {
	            $this->bountyproduction['crop'] = $this->bountyGetCropProd()-$this->bountypop-(($upkeep)-round($upkeep*0.50))+$heroProduction['crop'];

	        } else if($normalA['type'] == 4 && $normalA['size'] == 1 && $normalA['owner'] == $uid) {
	            $this->bountyproduction['crop'] = $this->bountyGetCropProd()-$this->bountypop-(($upkeep)-round($upkeep*0.25))+$heroProduction['crop'];

	        } else if($largeA['size'] == 2 && $largeA['owner'] == $uid) {
	            $this->bountyproduction['crop'] = $this->bountyGetCropProd()-$this->bountypop-(($upkeep)-round($upkeep*0.25))+$heroProduction['crop'];

	        } else {
	            $this->bountyproduction['crop'] = $this->bountyGetCropProd()-$this->bountypop-$upkeep+$heroProduction['crop'];
	        }
	    }

    private function bountyprocessProduction($bountywid) {
        global $database;
        $timepast = time() - $this->bountyinfoarray['lastupdate'];
        $nwood = ($this->bountyproduction['wood'] / 3600) * $timepast;
        $nclay = ($this->bountyproduction['clay'] / 3600) * $timepast;
        $niron = ($this->bountyproduction['iron'] / 3600) * $timepast;
        $ncrop = ($this->bountyproduction['crop'] / 3600) * $timepast;
        $database->modifyResource($bountywid, $nwood, $nclay, $niron, $ncrop, 1);
        $database->updateVillage($bountywid);
    }

    private function bountyprocessOProduction($bountywid) {
        global $database;
        $timepast = time() - $this->bountyOinfoarray['lastupdated'];
        $nwood = ($this->bountyOproduction['wood'] / 3600) * $timepast;
        $nclay = ($this->bountyOproduction['clay'] / 3600) * $timepast;
        $niron = ($this->bountyOproduction['iron'] / 3600) * $timepast;
        $ncrop = ($this->bountyOproduction['crop'] / 3600) * $timepast;
        $database->modifyOasisResource($bountywid, $nwood, $nclay, $niron, $ncrop, 1);
        $database->updateOasis($bountywid);
    }

    private function bountyGetWoodProd() {
        global $bid1, $bid5, $session;
        $wood = $sawmill = 0;
        $woodholder = array();
        for ($i = 1; $i <= 38; $i++) {
            if($this->bountyresarray['f'.$i.'t'] == 1) {
                array_push($woodholder, 'f'.$i);
            }
            if($this->bountyresarray['f'.$i.'t'] == 5) {
                $sawmill = $this->bountyresarray['f'.$i];
            }
        }
        for ($i = 0; $i <= count($woodholder) - 1; $i++) {
            $wood += $bid1[$this->bountyresarray[$woodholder[$i]]]['prod'];
        }
        if($sawmill >= 1) {
            $wood += $wood / 100 * $bid5[$sawmill]['attri'];
        }
        if($this->bountyocounter[0] != 0) {
            $wood += $wood * 0.25 * $this->bountyocounter[0];
        }
//        $wood += $wood*$this->bountyocounter[0]*0.25;
        $wood *= SPEED;
        return round($wood);
    }

    private function bountyGetOWoodProd() {
        global $session;
        $wood = 0;
        $wood += 40;
        $wood *= SPEED;
        return round($wood);
    }

    private function bountyGetOClayProd() {
        global $session;
        $clay = 0;
        $clay += 40;
        $clay *= SPEED;
        return round($clay);
    }

    private function bountyGetOIronProd() {
        global $session;
        $iron = 0;
        $iron += 40;
        $iron *= SPEED;
        return round($iron);
    }

    private function bountyGetOCropProd() {
        global $session;
        $crop = 0;
        $crop += 40;
        $crop *= SPEED;
        return round($crop);
    }

    private function bountyGetClayProd() {
        global $bid2, $bid6, $session;
        $clay = $brick = 0;
        $clayholder = array();
        for ($i = 1; $i <= 38; $i++) {
            if($this->bountyresarray['f'.$i.'t'] == 2) {
                array_push($clayholder, 'f'.$i);
            }
            if($this->bountyresarray['f'.$i.'t'] == 6) {
                $brick = $this->bountyresarray['f'.$i];
            }
        }
        for ($i = 0; $i <= count($clayholder) - 1; $i++) {
            $clay += $bid2[$this->bountyresarray[$clayholder[$i]]]['prod'];
        }
        if($brick >= 1) {
            $clay += $clay / 100 * $bid6[$brick]['attri'];
        }
        if($this->bountyocounter[1] != 0) {
            $clay += $clay * 0.25 * $this->bountyocounter[1];
        }
//        $clay += $clay*$this->bountyocounter[1]*0.25;
        $clay *= SPEED;
        return round($clay);
    }

    private function bountyGetIronProd() {
        global $bid3, $bid7, $session;
        $iron = $foundry = 0;
        $ironholder = array();
        for ($i = 1; $i <= 38; $i++) {
            if($this->bountyresarray['f'.$i.'t'] == 3) {
                array_push($ironholder, 'f'.$i);
            }
            if($this->bountyresarray['f'.$i.'t'] == 7) {
                $foundry = $this->bountyresarray['f'.$i];
            }
        }
        for ($i = 0; $i <= count($ironholder) - 1; $i++) {
            $iron += $bid3[$this->bountyresarray[$ironholder[$i]]]['prod'];
        }
        if($foundry >= 1) {
            $iron += $iron / 100 * $bid7[$foundry]['attri'];
        }
        if($this->bountyocounter[2] != 0) {
            $iron += $iron * 0.25 * $this->bountyocounter[2];
        }
//        $iron += $iron*$this->bountyocounter[2]*0.25;
        $iron *= SPEED;
        return round($iron);
    }

    private function bountyGetCropProd() {
        global $bid4, $bid8, $bid9, $session;
        $crop = $grainmill = $bakery = 0;
        $cropholder = array();
        for ($i = 1; $i <= 38; $i++) {
            if($this->bountyresarray['f'.$i.'t'] == 4) {
                array_push($cropholder, 'f'.$i);
            }
            if($this->bountyresarray['f'.$i.'t'] == 8) {
                $grainmill = $this->bountyresarray['f'.$i];
            }
            if($this->bountyresarray['f'.$i.'t'] == 9) {
                $bakery = $this->bountyresarray['f'.$i];
            }
        }
        for ($i = 0; $i <= count($cropholder) - 1; $i++) {
            $crop += $bid4[$this->bountyresarray[$cropholder[$i]]]['prod'];
        }
        if($grainmill >= 1) {
            $crop += $crop / 100 * $bid8[$grainmill]['attri'];
        }
        if($bakery >= 1) {
            $crop += $crop / 100 * $bid9[$bakery]['attri'];
        }
        if($this->bountyocounter[3] != 0) {
            $crop += $crop * 0.25 * $this->bountyocounter[3];
        }

//        $crop += $crop*$this->bountyocounter[3]*0.25;
        $crop *= SPEED;
        return round($crop);
    }

    private function trainingComplete() {
        global $database;
        if(!$database->acquireTrainingCompletionLock(0)) {
            return;
        }
        try {
            $time = time();
            $trainlist = $database->getTrainingList();
            if(count($trainlist) > 0) {
                foreach ($trainlist as $train) {
                $timepast = $time - $train['timestamp2'];
                $pop = $train['pop'];
                $trained = 0;
                if($timepast >= 0 && $train['amt'] > 0) {
                    $trained = floor($timepast / $train['eachtime']) + 1;
                    if($trained > $train['amt']) {
                        $trained = $train['amt'];
                    }
                    $completedUnit = (int)$train['unit'];
                    if($train['unit'] > 60 && $train['unit'] != 99) {
                        $completedUnit -= 60;
                    }
                    $completed = $database->completeTrainingBatch(
                        $train['id'],
                        $completedUnit,
                        $trained,
                        $trained * $train['eachtime']
                    );
                    if(!$completed) {
                        continue;
                    }
                    if($trained > 0 && $completedUnit >= 10 && $completedUnit <= 50 && $completedUnit % 10 === 0) {
                        $owner = (int)$database->getVillageField($train['vref'],'owner');
                        $tribe = (int)$database->getUserField($owner,'tribe',0);
                        if($completedUnit === $tribe * 10 && $database->hasOwnSettlersForUser($owner,$tribe,3)) {
                            $database->markFollowupQuestAchieved($owner,9);
                        }
                    }
                }
                $new_amt = $train['amt'] - $trained;
                if($new_amt == 0) {
                    $database->trainUnit($train['id'], 0, 0, 0, 0, 1, 1);
                }
                /* $crop = $database->getCropProdstarv($train['vref']);
				$unitarrays = $this->getAllUnits($train['vref']);
				$village = $database->getVillage($train['vref']);
				$upkeep = $village['pop'] + $this->getUpkeep($unitarrays, 0, $train['vref']);
				if ($crop < $upkeep){
					// add starv data
					$database->setVillageField($train['vref'], 'starv', $upkeep);
					$database->setVillageField($train['vref'], 'starvupdate', $time);
				} */
                }
            }
        } finally {
            $database->releaseTrainingCompletionLock();
        }
    }

    private function procDistanceTime($coor, $thiscoor, $ref, $mode) {
        global $bid14, $database, $generator;
        $resarray = $database->getResourceLevel($generator->getBaseID($coor['x'], $coor['y']));
        $ts_attri = 0;
        $xdistance = ABS($thiscoor['x'] - $coor['x']);
        if($xdistance > WORLD_MAX) {
            $xdistance = (2 * WORLD_MAX + 1) - $xdistance;
        }
        $ydistance = ABS($thiscoor['y'] - $coor['y']);
        if($ydistance > WORLD_MAX) {
            $ydistance = (2 * WORLD_MAX + 1) - $ydistance;
        }
        $distance = SQRT(POW($xdistance, 2) + POW($ydistance, 2));
        if(!$mode) {
            if($ref == 1) {
                $speed = 16;
            } else if($ref == 2) {
                $speed = 12;
            } else if($ref == 3) {
                $speed = 24;
            } else if($ref == 300) {
                $speed = 5;
            } else {
                $speed = 1;
            }
        } else {
            $speed = max(1, (float)$ref);
            for ($i = 19; $i <= 40; $i++) {
                if($resarray['f'.$i.'t'] == 14) {
                    $ts_level = $resarray['f'.$i];
                    $ts_attri = $bid14[$ts_level]['attri'];
                }
            }
            if($ts_attri > 0) {
                $speed = $distance <= TS_THRESHOLD ? $speed : $speed * ((TS_THRESHOLD + ($distance - TS_THRESHOLD) * $ts_attri / 100) / $distance);
            }
        }

        return round(($distance / $speed) * 3600 / INCREASE_SPEED);

    }

    private function getsort_typeLevel($tid, $resarray) {
        global $village;
        $keyholder = array();
        foreach (array_keys($resarray, $tid) as $key) {
            if(strpos($key, 't')) {
                $key = preg_replace("/[^0-9]/", '', $key);
                array_push($keyholder, $key);
            }
        }
        $element = count($keyholder);
        if($element >= 2) {
            if($tid <= 4) {
                $temparray = array();
                for ($i = 0; $i <= $element - 1; $i++) {
                    array_push($temparray, $resarray['f'.$keyholder[$i]]);
                }
                foreach ($temparray as $key => $val) {
                    if($val == max($temparray))
                        $target = $key;
                }
            } else {
                for ($i = 0; $i <= $element - 1; $i++) {
                    //if($resarray['f'.$keyholder[$i]] != $this->getsort_typeMaxLevel($tid)) {
                    //    $target = $i;
                    //}
                }
            }
        } else if($element == 1) {
            $target = 0;
        } else {
            return 0;
        }
        if($keyholder[$target] != "") {
            return $resarray['f'.$keyholder[$target]];
        } else {
            return 0;
        }
    }

    private function celebrationComplete() {
        if(file_exists("GameEngine/Prevention/celebration.txt")) {
            @unlink("GameEngine/Prevention/celebration.txt");
        }
        global $database;
        $ourFileHandle = fopen("GameEngine/Prevention/celebration.txt", 'w');
        fclose($ourFileHandle);
        $varray = $database->getCel();
        foreach ($varray as $vil) {
            $id = $vil['wref'];
            $type = $vil['type'];
            $user = $vil['owner'];
            if($type == 1) {
                $cp = 500;
            } else if($type == 2) {
                $cp = 2000;
            }
            $database->clearCel($id);
            $database->setCelCp($user, $cp);
        }
        if(file_exists("GameEngine/Prevention/celebration.txt")) {
            @unlink("GameEngine/Prevention/celebration.txt");
        }
    }

    private function demolitionComplete() {
        if(file_exists("GameEngine/Prevention/demolition.txt")) {
            @unlink("GameEngine/Prevention/demolition.txt");
        }
        global $building, $database;
        $ourFileHandle = fopen("GameEngine/Prevention/demolition.txt", 'w');
        fclose($ourFileHandle);
        $varray = $database->getDemolition();
        foreach ($varray as $vil) {
            if($vil['timetofinish'] <= time()) {
                $type = $database->getFieldType($vil['vref'], $vil['buildnumber']);
                $level = $database->getFieldLevel($vil['vref'], $vil['buildnumber']);
                $this->applyStorageCapacityDelta($vil['vref'], $type, $level, $level - 1);
                if($type == 18) {
                    $allyleader = $database->getVillageField($data['to'], "owner");
                    $allyvillages = $database->getVillagesID2($allyleader);
                    $allymax = 0;
                    foreach ($allyvillages as $allyvillage) {
                        $allyfield = $database->getResourceLevel($allyvillage['wref']);
                        for ($i = 19; $i <= 40; $i++) {
                            if($allyfield['f'.$i.'t'] == 18) {
                                $allylevel = $allyfield['f'.$i];
                                $allyattri = $bid18[$allylevel]['attri'];
                            }
                        }
                        if($allyattri > $allymax) {
                            $allymax = $allyattri;
                        }
                    }
                    $q = "UPDATE ".TB_PREFIX."alidata set max = $allymax where leader = $allyleader";
                    $database->query($q);
                }
                if($level == 1) {
                    $clear = ",f".$vil['buildnumber']."t=0";
                } else {
                    $clear = "";
                }
                $q = "UPDATE ".TB_PREFIX."fdata SET f".$vil['buildnumber']."=".($level - 1).$clear." WHERE vref=".$vil['vref'];
                $database->query($q);
                $pop = $this->getPop($type, $level - 1);
                $database->modifyPop($vil['vref'], $pop[0], 1);
                $this->procClimbers($database->getVillageField($vil['vref'], 'owner'));
                $database->delDemolition($vil['vref']);
            }
        }
        if(file_exists("GameEngine/Prevention/demolition.txt")) {
            @unlink("GameEngine/Prevention/demolition.txt");
        }
    }

    private function updateHero() {
        if(file_exists("GameEngine/Prevention/updatehero.txt")) {
            @unlink("GameEngine/Prevention/updatehero.txt");
        }
        global $database, $session;
        $time = time();
        $ourFileHandle = fopen("GameEngine/Prevention/updatehero.txt", 'w');
        fclose($ourFileHandle);
	        $hero_levels = $GLOBALS["hero_levels"];
	        $q = "SELECT * FROM ".TB_PREFIX."hero";
	        $harray = $database->query_return($q);
	        if(!empty($harray)) {
	            foreach ($harray as $hdata) {
	                if((int)$hdata['dead']===0 && $hdata['health'] < 100) {
	                    $health = $hdata['health'] + $hdata['autoregen'] * SPEED / 86400 * (time() - $hdata['lastupdate']);
	                    if($health > 100) {
	                        $health = 100;
                    }
	                    $database->modifyHero("health", $health, $hdata['heroid'], 0);
	                    $database->modifyHero("lastupdate", time(), $hdata['heroid'], 0);
	                }
	                $currentLevel = max(0,(int)$hdata['level']);
	                $targetLevel = heroLevelForExperience($hdata['experience'],$currentLevel,$hero_levels);
	                if($targetLevel>$currentLevel) {
	                    $database->advanceHeroLevel($hdata['heroid'],$currentLevel,$targetLevel);
	                }
	            }
	        }
        $q2 = "SELECT * FROM ".TB_PREFIX."training where unit = 0";
        $dataarray2 = $database->query_return($q2);
        foreach ($dataarray2 as $data3) {
            if($data3['eachtime'] <= time()) {
                $database->trainHero($data3['id'], 0, 1);
                $getVil = $database->getMInfo($data3['vref']);
                $database->modifyHero2('dead', 0, $getVil['owner'], 0);
                $database->modifyHero2('health', 100, $getVil['owner'], 0);
                $database->editTableField('units', 'hero', 1, 'vref', $data3['vref']);
            }
        }
        $q2 = "SELECT * FROM ".TB_PREFIX."units where hero > 1";
        $dataarray2 = $database->query_return($q2);
        foreach ($dataarray2 as $data3) {
            $database->editTableField('units', 'hero', 1, 'vref', $data3['vref']);
        }
        if(file_exists("GameEngine/Prevention/updatehero.txt")) {
            @unlink("GameEngine/Prevention/updatehero.txt");
        }

    }

    private function auctionComplete() {
        global $database;
        if(!$database->acquireAuctionLock(0)) {
            return;
        }

        $preventionFile = "GameEngine/Prevention/auction.txt";
        touch($preventionFile);
        $time = time();
        try {
            $q = "SELECT * FROM ".TB_PREFIX."auction where finish = 0 and time <= $time";
            $dataarray = $database->query_return($q);
            foreach ($dataarray as $data) {
                $ownerID = (int) $data['owner'];
                $biderID = (int) $data['uid'];
                $silver = (int) $data['silver'];
                $newsilver = (int) $data['newsilver'];
                $btype = (int) $data['btype'];

                // uid arranca en 0 y solo cambia cuando alguien puja. Sin ofertas
                // el item vuelve al vendedor; con ofertas, uid siempre es quien
                // tiene la oferta maxima vigente al momento del cierre.
                $noBids = ($biderID === 0);
                $receiverID = $noBids ? $ownerID : $biderID;
				if($btype == 7 || $btype == 8 || $btype == 9) {
					$id = $database->getHeroItemID($receiverID, $btype);
					if($id != 0) {
						$database->editHeroNum2($id, $data['num'], 1);
                    } else {
						$database->addHeroItem($receiverID, $btype, 0, $data['num']);
					}
				}
                else if($btype == 10 || $btype == 11 || $btype == 14 || $btype == 15) {
                    if($database->checkHeroItem($receiverID, $btype)) {
						$id = $database->getHeroItemID($receiverID, $btype);
                        $database->editHeroNum($id, $data['num'], 1);
                    } else {
                        $database->addHeroItem($receiverID, $data['btype'], $data['type'], $data['num']);
                    }
                } else {
                    $database->addHeroItem($receiverID, $data['btype'], $data['type'], $data['num']);
                }
                if(!$noBids) {
                    $silver2 = max(0, $newsilver - $silver);
                    $database->setSilver($ownerID, $silver, 1);
                    $database->setSilver($biderID, $silver2, 1);
                }
                $q = "UPDATE ".TB_PREFIX."auction set finish=1 where id = ".(int) $data['id']." and finish=0";
                $database->query($q);
                if(!$noBids) {
                    $this->sendAuctionResultMessages($data);
                }
            }
        } finally {
            $database->releaseAuctionLock();
        }
    }

    private function sendAuctionResultMessages($auction) {
        global $database;

        $auctionID = (int) $auction['id'];
        $ownerID = (int) $auction['owner'];
        $winnerID = (int) $auction['uid'];
        $participantRows = $database->query_return(
            "SELECT ab.uid, u.username
             FROM ".TB_PREFIX."auction_bids ab
             INNER JOIN ".TB_PREFIX."users u ON u.id = ab.uid
             WHERE ab.auction_id = $auctionID
             GROUP BY ab.uid, u.username
             ORDER BY MIN(ab.id)"
        );

        $bidderNames = array();
        $recipientIDs = array($ownerID => true);
        foreach($participantRows as $participant) {
            $participantID = (int) $participant['uid'];
            $bidderNames[$participantID] = (string) $participant['username'];
            $recipientIDs[$participantID] = true;
        }

        // Una subasta que ya estaba activa al aplicar la migracion no tiene
        // historial previo, pero al menos debe incluir al ganador vigente.
        if(!isset($bidderNames[$winnerID])) {
            $bidderNames[$winnerID] = (string) $database->getUserField($winnerID, 'username', 0);
            $recipientIDs[$winnerID] = true;
        }

        $startingPrice = $this->getAuctionStartingPrice($auction);
        $sellerName = (string) $database->getUserField($ownerID, 'username', 0);
        $winnerName = (string) $database->getUserField($winnerID, 'username', 0);
        $itemName = $this->getAuctionItemName($auction);
        $quantity = max(1, (int) $auction['num']);

        $bidderLinks = array();
        foreach($bidderNames as $bidderID => $bidderName) {
            $bidderLinks[] = $this->getAuctionPlayerLink($bidderID, $bidderName);
        }

        $message = "[message][b]La subasta ha finalizado[/b]\n\n"
            . "[b]Vendedor:[/b] ".$this->getAuctionPlayerLink($ownerID, $sellerName)."\n"
            . "[b]Objeto:[/b] ".$quantity." × ".htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8')."\n"
            . "[b]Postores:[/b] ".implode(', ', $bidderLinks)."\n"
            . "[b]Ganador:[/b] ".$this->getAuctionPlayerLink($winnerID, $winnerName)."\n"
            . "[b]Precio inicial:[/b] ".$startingPrice." de plata\n"
            . "[b]Precio final:[/b] ".(int) $auction['silver']." de plata[/message]";

        foreach(array_keys($recipientIDs) as $recipientID) {
            $database->sendMessage(
                (int) $recipientID,
                4,
                'Resultado de la subasta',
                addslashes($message),
                0,
                0,
                0,
                0,
                0
            );
        }
    }

    private function getAuctionPlayerLink($playerID, $playerName) {
        return '<a href="spieler.php?uid='.(int) $playerID.'">'
            .htmlspecialchars((string) $playerName, ENT_QUOTES, 'UTF-8')
            .'</a>';
    }

    private function getAuctionStartingPrice($auction) {
        global $database;

        $auctionID = (int) $auction['id'];
        $rows = $database->query_return(
            "SELECT price_before
             FROM ".TB_PREFIX."auction_bids
             WHERE auction_id = $auctionID
             ORDER BY id ASC
             LIMIT 1"
        );
        if(isset($rows[0])) {
            return (int) $rows[0]['price_before'];
        }

        $btype = (int) $auction['btype'];
        if(in_array($btype, array(7, 8, 9, 10, 11, 13, 14), true)) {
            return (int) $auction['num'];
        }
        return 100;
    }

    private function getAuctionItemName($auction) {
        $btype = (int) $auction['btype'];
        $type = (int) $auction['type'];
        $name = "Objeto del héroe (tipo ".$btype.")";
        $title = "";
        include dirname(__DIR__)."/Templates/Auction/alt.tpl";
        return trim($name);
    }

    private function addAdventures() {
        global $database;
        $time = time();
        $adv_time = 86400 / ADVENTURE_SPEED;
        $q = "SELECT * FROM ".TB_PREFIX."hero where $time - lastadv > $adv_time";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $database->addAdventure($database->getVFH($data['uid']), $data['uid']);
            $database->modifyHero('lastadv', $time, $data['heroid']);
        }
    }

    private function MasterBuilder() {
        global $database;
        $q = "SELECT * FROM ".TB_PREFIX."bdata WHERE master = 1";
        $array = $database->query_return($q);
        foreach ($array as $master) {
            $owner = $database->getVillageField($master['wid'], 'owner');
            $tribe = $database->getUserField($owner, 'tribe', 0);
            $villwood = $database->getVillageField($master['wid'], 'wood');
            $villclay = $database->getVillageField($master['wid'], 'clay');
            $villiron = $database->getVillageField($master['wid'], 'iron');
            $villcrop = $database->getVillageField($master['wid'], 'crop');
            $type = $master['type'];
            $level = $master['level'];
            $buildarray = $GLOBALS["bid".$type];
            $buildwood = $buildarray[$level]['wood'];
            $buildclay = $buildarray[$level]['clay'];
            $buildiron = $buildarray[$level]['iron'];
            $buildcrop = $buildarray[$level]['crop'];
            $ww = count($database->getBuildingByType($master['wid'], 40));
            if($tribe == 1) {
                if($master['field'] < 19) {
                    $bdata = count($database->getDorf1Building($master['wid']));
                    $bbdata = count($database->getDorf2Building($master['wid']));
                    $bdata1 = $database->getDorf1Building($master['wid']);
                } else {
                    $bdata = count($database->getDorf2Building($master['wid']));
                    $bbdata = count($database->getDorf1Building($master['wid']));
                    $bdata1 = $database->getDorf2Building($master['wid']);
                }
            } else {
                $bdata = $bbdata = $bdata1 = count($database->getDorf1Building($master['wid'])) + count($database->getDorf2Building($master['wid']));
            }
            if($database->getUserField($owner, 'plus', 0) > time() or $ww > 0) {
                if($bbdata < 2) {
                    $inbuild = 2;
                } else {
                    $inbuild = 1;
                }
            } else {
                $inbuild = 1;
            }
            $usergold = $database->getUserField($owner, 'gold', 0);
            if($bdata < $inbuild && $buildwood <= $villwood && $buildclay <= $villclay && $buildiron <= $villiron && $buildcrop <= $villcrop && $usergold > 0) {
                $time = $master['timestamp'] + time();
                if(!empty($bdata1)) {
                    foreach ($bdata1 as $master1) {
                        $time += ($master1['timestamp'] - time());
                    }
                }
                if($bdata == 0) {
                    $database->updateBuildingWithMaster($master['id'], $time, 0);
                } else {
                    $database->updateBuildingWithMaster($master['id'], $time, 1);
                }
                $gold = $usergold - 1;
                $database->updateUserField($owner, 'gold', $gold, 1);
                $database->modifyResource($master['wid'], $buildwood, $buildclay, $buildiron, $buildcrop, 0);
            }
        }
    }

    private function starvation() {
        if(file_exists("GameEngine/Prevention/starvation.txt")) {
            @unlink("GameEngine/Prevention/starvation.txt");
        }
        global $database;
        $ourFileHandle = @fopen("GameEngine/Prevention/starvation.txt", 'w');
        @fclose($ourFileHandle);
        $starvcost = array(

            '1' => 30,
            '2' => 70,
            '3' => 80,
            '4' => 40,
            '5' => 100,
            '6' => 180,
            '7' => 70,
            '8' => 90,
            '9' => 37500,
            '10' => 5500,
            '11' => 40,
            '12' => 40,
            '13' => 70,
            '14' => 50,
            '15' => 75,
            '16' => 80,
            '17' => 70,
            '18' => 60,
            '19' => 27200,
            '20' => 6500,
            '21' => 30,
            '22' => 60,
            '23' => 40,
            '24' => 60,
            '25' => 120,
            '26' => 170,
            '27' => 75,
            '28' => 90,
            '29' => 37500,
            '30' => 4900,
            '31' => 100,
            '32' => 100,
            '33' => 100,
            '34' => 100,
            '35' => 100,
            '36' => 100,
            '37' => 100,
            '38' => 100,
            '39' => 100,
            '40' => 100,
            '41' => 100,
            '42' => 100,
            '43' => 100,
            '44' => 100,
            '45' => 100,
            '46' => 100,
            '47' => 100,
            '48' => 100,
            '49' => 100,
            '50' => 100
        );
        $starvupkeep = array(

            '1' => 1,
            '2' => 1,
            '3' => 1,
            '4' => 2,
            '5' => 3,
            '6' => 4,
            '7' => 3,
            '8' => 6,
            '9' => 5,
            '10' => 1,
            '11' => 1,
            '12' => 1,
            '13' => 1,
            '14' => 1,
            '15' => 2,
            '16' => 3,
            '17' => 6,
            '18' => 4,
            '19' => 1,
            '20' => 1,
            '21' => 1,
            '22' => 1,
            '23' => 2,
            '24' => 2,
            '25' => 2,
            '26' => 3,
            '27' => 3,
            '28' => 6,
            '29' => 4,
            '30' => 1,
            '31' => 1,
            '32' => 1,
            '33' => 1,
            '34' => 2,
            '35' => 2,
            '36' => 3,
            '37' => 3,
            '38' => 3,
            '39' => 3,
            '40' => 5,
            '41' => 1,
            '42' => 1,
            '43' => 1,
            '44' => 1,
            '45' => 2,
            '46' => 3,
            '47' => 6,
            '48' => 5,
            '49' => 1,
            '50' => 1
        );

        $time = time();

        // load villages with minus prod
        $starvarray = array();
        $starvarray = $database->getStarvation();
        foreach ($starvarray as $starv) {
            if(($starv['starvupdate'] + 60) < $time) {
                // get enforce
                $enforcearray = $database->getEnforceVillage($starv['wref'], 0);
                $maxcount = 0;
                if(count($enforcearray) == 0) {
                    // get units
                    $unitarray = $database->getUnit($starv['wref']);
                    for ($i = 0; $i <= 50; $i++) {
                        $units = $unitarray['u'.$i];
                        if($unitarray['u'.$i] > $maxcount) {
                            $maxcount = $unitarray['u'.$i];
                            $maxtype = $i;
                        }
                    }
                } else {
                    foreach ($enforcearray as $enforce) {
                        for ($i = 0; $i <= 50; $i++) {
                            $units = $enforce['u'.$i];
                            if($enforce['u'.$i] > $maxcount) {
                                $maxcount = $enforce['u'.$i];
                                $maxtype = $i;
                                $enf = $enforce['id'];
                            }
                        }
                    }
                }

                // counting

                $timedif = $time - $starv['starvupdate'];

                $starvsec = ($starv['starv'] / 3600);

                $difcrop = ($timedif * $starvsec);
                $newcrop = 0;
                $oldcrop = $database->getVillageField($starv['wref'], 'crop');
                if($oldcrop > 100) {
                    $difcrop = $difcrop - $oldcrop;
                    if($difcrop < 0) {
                        $difcrop = 0;
                        $newcrop = $oldcrop - $difcrop;
                    } else {
                        $newcrop = $starvcost[$maxtype];
                    }
                }
                if($difcrop > 0) {
                    $killunits = round(($difcrop / $starvcost[$maxtype]));
                    if(isset($enf)) {
                        if($killunits < $maxcount) {
                            $database->modifyEnforce($enf, $maxtype, $killunits, 0);
                        } else {
                            $database->deleteReinf($enf);
                        }
                    } else {
                        if($killunits < $maxcount) {
                            $database->modifyUnit($starv['wref'], $maxtype, $killunits, 0);
                        } elseif($killunits > $maxcount) {
                            $killunits = $maxcount;
                            $database->modifyUnit($starv['wref'], $maxtype, $killunits, 0);
                        }
                    }
                }

                $upkeep = $starv['starv'] - ($killunits * $starvupkeep[$maxtype]);

                $time = time();

                $crop = $database->getCropProdstarv($starv['wref']);
                $unitarrays = $this->getAllUnits($starv['wref']);
                $upkeep = $this->getUpkeep($unitarrays, 0, $starv['wref']);
                if($crop < $upkeep) {
                    // add starv data
                    $database->setVillageField($starv['wref'], 'starv', $upkeep);
                    $database->setVillageField($starv['wref'], 'starvupdate', $time);
                    $database->setVillageField($starv['wref'], 'crop', $newcrop);
                } else {
                    $database->setVillageField($starv['wref'], 'starv', 0);
                    $database->setVillageField($starv['wref'], 'starvupdate', 0);
                    $database->setVillageField($starv['wref'], 'crop', $newcrop);
                }
            }
            unset ($starv);
            unset ($unitarray);
            unset ($enforcearray);
            unset ($enforce);
            unset ($starvarray);
        }

        if(file_exists("GameEngine/Prevention/starvation.txt")) {
            @unlink("GameEngine/Prevention/starvation.txt");
        }
    }

    // by SlimShady95, aka Manuel Mannhardt < manuel_mannhardt@web.de > UPDATED FROM songeriux < haroldas.snei@gmail.com >
    private function updateStore() {
        global $bid10, $bid38, $bid11, $bid39;

        $result = mysql_query('SELECT * FROM `'.TB_PREFIX.'fdata`');
        while($row = mysql_fetch_assoc($result)) {
            $ress = $crop = 0;
            for ($i = 19; $i < 40; ++$i) {
                if($row['f'.$i.'t'] == 10) {
                    $ress += $bid10[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER;
                }

                if($row['f'.$i.'t'] == 38) {
                    $ress += $bid38[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER;
                }


                if($row['f'.$i.'t'] == 11) {
                    $crop += $bid11[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER;
                }

                if($row['f'.$i.'t'] == 39) {
                    $crop += $bid39[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER;
                }
            }

            if($ress == 0) {
                $ress = 800 * STORAGE_MULTIPLIER;
            }

            if($crop == 0) {
                $crop = 800 * STORAGE_MULTIPLIER;
            }

            mysql_query('UPDATE `'.TB_PREFIX.'vdata` SET `maxstore` = '.$ress.', `maxcrop` = '.$crop.' WHERE `wref` = '.$row['vref']) or die(mysql_error());
        }
    }

    private function oasisResourcesProduce() {
        global $database;
        $time = time();
        $q = "SELECT * FROM ".TB_PREFIX."odata WHERE wood < 800 OR clay < 800 OR iron < 800 OR crop < 800";
        $array = $database->query_return($q);
        foreach ($array as $getoasis) {
            $oasiswood = $getoasis['wood'] + (8 * SPEED / 3600) * (time() - $getoasis['lastupdated']);
            $oasisclay = $getoasis['clay'] + (8 * SPEED / 3600) * (time() - $getoasis['lastupdated']);
            $oasisiron = $getoasis['iron'] + (8 * SPEED / 3600) * (time() - $getoasis['lastupdated']);
            $oasiscrop = $getoasis['crop'] + (8 * SPEED / 3600) * (time() - $getoasis['lastupdated']);
            if($oasiswood > $getoasis['maxstore']) {
                $oasiswood = $getoasis['maxstore'];
            }
            if($oasisclay > $getoasis['maxstore']) {
                $oasisclay = $getoasis['maxstore'];
            }
            if($oasisiron > $getoasis['maxstore']) {
                $oasisiron = $getoasis['maxstore'];
            }
            if($oasiscrop > $getoasis['maxcrop']) {
                $oasiscrop = $getoasis['maxcrop'];
            }
            $q = "UPDATE ".TB_PREFIX."odata set wood = $oasiswood, clay = $oasisclay, iron = $oasisiron, crop = $oasiscrop where wref = ".$getoasis['wref']."";
            $database->query($q);
            $database->updateOasis($getoasis['wref']);
        }
    }

    private function procNewClimbers() {
        global $database;
        $accessLimit = INCLUDE_ADMIN ? 10 : 8;
        $users = $database->query_return(
            "SELECT id FROM ".TB_PREFIX."users
             WHERE oldrank = 0 AND id > 3 AND tribe <= 3 AND access < ".$accessLimit
        );
        foreach ($users as $user) {
            $database->syncClimberPopulation((int)$user['id']);
        }
    }

    private function procClimbers($uid) {
        global $database;
        $database->syncClimberPopulation((int)$uid);
    }

    private function regenerateOasisTroops() {
        global $database;
        $time = time();
        $q = "SELECT * FROM ".TB_PREFIX."odata where conqured = 0 and $time - lastupdated2 > 86400";
        $array = $database->query_return($q);
        foreach ($array as $oasis) {
            $database->populateOasisUnitsLow2($oasis['wref']);
            $database->updateOasis2($oasis['wref']);
        }
    }

    private function weeklyMedalSchedule($now) {
        $configuredStart = strtotime(START_DATE . " " . START_TIME);
        $worldStart = max((int)COMMENCE, $configuredStart ? $configuredStart : 0);
        $firstBoundary = strtotime("next monday 00:00:00", $worldStart);
        if(!$firstBoundary || $now < $firstBoundary) {
            return false;
        }

        $boundary = strtotime("monday this week 00:00:00", $now);
        if($boundary < $firstBoundary) {
            return false;
        }

        return array(
            "boundary" => date("Y-m-d", $boundary),
            "week" => 1 + (int)floor(($boundary - $firstBoundary) / 604800)
        );
    }

    private function weeklyMedalBoundary($now) {
        $schedule = $this->weeklyMedalSchedule($now);
        return $schedule === false ? false : $schedule["boundary"];
    }

    private function repairPrematureMedalWeeks($expectedWeek) {
        $expectedWeek = max(1, (int)$expectedWeek);
        foreach(array("medal", "allimedal") as $table) {
            $result = mysql_query(
                "SELECT COALESCE(MAX(week),0) AS maxweek FROM " . TB_PREFIX . $table
            );
            $row = $result ? mysql_fetch_assoc($result) : false;
            if(!$row) {
                error_log("Unable to inspect weekly medal numbering for " . $table);
                return false;
            }

            $maximumWeek = (int)$row["maxweek"];
            if($maximumWeek <= $expectedWeek) {
                continue;
            }

            // Old worlds started on a Monday and immediately awarded an empty
            // week. Remove those premature rows and align every real award with
            // the number of weekly boundaries elapsed since the world started.
            $offset = $maximumWeek - $expectedWeek;
            if(!mysql_query(
                "DELETE FROM " . TB_PREFIX . $table . " WHERE week <= " . $offset
            )) {
                error_log("Unable to remove premature weekly medals from " . $table);
                return false;
            }
            if(!mysql_query(
                "UPDATE " . TB_PREFIX . $table . " SET week = week - " . $offset
            )) {
                error_log("Unable to realign weekly medals in " . $table);
                return false;
            }
        }
        return true;
    }

    private function weeklyMedals() {
        $schedule = $this->weeklyMedalSchedule(time());
        if($schedule === false) {
            return;
        }
        $boundary = $schedule["boundary"];
        $scheduledWeek = (int)$schedule["week"];

        // The marker itself is locked so simultaneous page requests cannot hand
        // out the same medals twice. Keeping the completed Monday in the file
        // also lets the first request after a Monday outage catch up later.
        $marker = __DIR__ . "/Prevention/medals.txt";
        $handle = @fopen($marker, "c+");
        if(!$handle) {
            error_log("Unable to open weekly medal marker: " . $marker);
            return;
        }
        if(!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return;
        }

        $repairMarker = __DIR__ . "/Prevention/medal_weeks_v2.txt";
        $repairedWeek = file_exists($repairMarker)
            ? (int)trim(file_get_contents($repairMarker))
            : 0;
        if($repairedWeek < $scheduledWeek) {
            if(!$this->repairPrematureMedalWeeks($scheduledWeek)) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return;
            }
            file_put_contents($repairMarker, (string)$scheduledWeek, LOCK_EX);
        }

        rewind($handle);
        $lastRun = trim(stream_get_contents($handle));
        if($lastRun !== $boundary) {
            $this->giveOutMedals($scheduledWeek);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $boundary);
            fflush($handle);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Categories: 1 attackers top10, 2 defenders top10, 3 population growth top10 (ally), 4 robbers top10,
     * 5 bonus att+def top10, 6/12 att top3/top10 streak, 7/13 def top3/top10 streak,
     * 8/14 pop growth top3/top10 streak, 9/15 robbers top3/top10 streak, 10 population growth top10,
     * 11/16 population growth top3/top10 streak.
     */
    public function giveOutMedals($scheduledWeek = null) {
        global $database;
        if($scheduledWeek === null) {
            $schedule = $this->weeklyMedalSchedule(time());
            if($schedule === false) {
                return false;
            }
            $scheduledWeek = (int)$schedule["week"];
        }
        $week = max(1, (int)$scheduledWeek);
        $allyweek = $week;

        // Reconcile any population change that came from an uncommon path before
        // the scores are frozen into medals and reset for the next week.
        $database->syncAllClimberPopulations();

        // Only the first $top places of every weekly ranking get a medal (MEDAL_TOP,
        // MEDAL_ALLY_TOP). On a small server this can be 1 so only the winner is awarded.
        $top = MEDAL_TOP;
        $allyTop = MEDAL_ALLY_TOP;
        // The "3rd/5th/10th time in the top 3" and "...in the top 10" streak medals collapse
        // into the same thing once $top <= 3, so the second series is skipped there.
        $streakA = min(3, $top);
        $streakB = min(10, $top);
        $streakSplit = ($streakB > $streakA);

        //Aanvallers v/d Week
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY ap DESC Limit " . $top);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "t2_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '1', '" . ($i) . "', '" . $week . "', '" . $row['ap'] . "', '" . $img . "')");
        }

        //Verdediger v/d Week
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY dp DESC Limit " . $top);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "t3_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '2', '" . ($i) . "', '" . $week . "', '" . $row['dp'] . "', '" . $img . "')");
        }

        //Population growth of the week
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY clp DESC Limit " . $top);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "t1_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '10', '" . ($i) . "', '" . $week . "', '" . $row['clp'] . "', '" . $img . "')");
        }

        //Overvallers v/d Week
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY RR DESC Limit " . $top);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "t4_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '4', '" . ($i) . "', '" . $week . "', '" . $row['RR'] . "', '" . $img . "')");
        }

        //deel de bonus voor aanval+defence top 10 uit
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY ap DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result2 = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY dp DESC Limit " . $top);
            while($row2 = mysql_fetch_array($result2)) {
                if($row['id'] == $row2['id']) {
                    $result3 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 5");
                    $row3 = mysql_fetch_array($result3);
                    if($row3[0] <= '2') {
                        $img = "t22" . $row3[0] . "_1";
                        switch ($row3[0]) {
                            case "0":
                                $tekst = "";
                                break;
                            case "1":
                                $tekst = "twice ";
                                break;
                            case "2":
                                $tekst = "three times ";
                                break;
                        }
                        mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '5', '0', '" . $week . "', '" . $tekst . "', '" . $img . "')");
                    }
                }
            }
        }

        //je staat voor 3e / 5e / 10e keer in de top 3 aanvallers
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY ap DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 1 AND plaats<=" . $streakA . "");
            $row1 = mysql_fetch_array($result1);
            if($row1[0] == '3') {
                $img = "t120_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '6', '0', '" . $week . "', 'Three', '" . $img . "')");
            }
            if($row1[0] == '5') {
                $img = "t121_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '6', '0', '" . $week . "', 'Five', '" . $img . "')");
            }
            if($row1[0] == '10') {
                $img = "t122_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '6', '0', '" . $week . "', 'Ten', '" . $img . "')");
            }
        }

        if($streakSplit) {
            //je staat voor 3e / 5e / 10e keer in de top 10 aanvallers
            $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY ap DESC Limit " . $top);
            while($row = mysql_fetch_array($result)) {
                $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 1 AND plaats<=" . $streakB . "");
                $row1 = mysql_fetch_array($result1);
                if($row1[0] == '3') {
                    $img = "t130_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '12', '0', '" . $week . "', 'Three', '" . $img . "')");
                }
                if($row1[0] == '5') {
                    $img = "t131_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '12', '0', '" . $week . "', 'Five', '" . $img . "')");
                }
                if($row1[0] == '10') {
                    $img = "t132_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '12', '0', '" . $week . "', 'Ten', '" . $img . "')");
                }
            }
        }

        //je staat voor 3e / 5e / 10e keer in de top 3 verdedigers
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY dp DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 2 AND plaats<=" . $streakA . "");
            $row1 = mysql_fetch_array($result1);
            if($row1[0] == '3') {
                $img = "t140_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '7', '0', '" . $week . "', 'Three', '" . $img . "')");
            }
            if($row1[0] == '5') {
                $img = "t141_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '7', '0', '" . $week . "', 'Five', '" . $img . "')");
            }
            if($row1[0] == '10') {
                $img = "t142_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '7', '0', '" . $week . "', 'Ten', '" . $img . "')");
            }
        }

        if($streakSplit) {
            //je staat voor 3e / 5e / 10e keer in de top 10 verdedigers
            $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY dp DESC Limit " . $top);
            while($row = mysql_fetch_array($result)) {
                $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 2 AND plaats<=" . $streakB . "");
                $row1 = mysql_fetch_array($result1);
                if($row1[0] == '3') {
                    $img = "t150_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '13', '0', '" . $week . "', 'Three', '" . $img . "')");
                }
                if($row1[0] == '5') {
                    $img = "t151_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '13', '0', '" . $week . "', 'Five', '" . $img . "')");
                }
                if($row1[0] == '10') {
                    $img = "t152_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '13', '0', '" . $week . "', 'Ten', '" . $img . "')");
                }
            }
        }

        //je staat voor 3e / 5e / 10e keer in de top 3 klimmers (pop)
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY Rc DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 3 AND plaats<=" . $streakA . "");
            $row1 = mysql_fetch_array($result1);
            if($row1[0] == '3') {
                $img = "t100_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '8', '0', '" . $week . "', 'Three', '" . $img . "')");
            }
            if($row1[0] == '5') {
                $img = "t101_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '8', '0', '" . $week . "', 'Five', '" . $img . "')");
            }
            if($row1[0] == '10') {
                $img = "t102_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '8', '0', '" . $week . "', 'Ten', '" . $img . "')");
            }
        }

        if($streakSplit) {
            //je staat voor 3e / 5e / 10e keer in de top 10 klimmers (pop)
            $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY Rc DESC Limit " . $top);
            while($row = mysql_fetch_array($result)) {
                $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 3 AND plaats<=" . $streakB . "");
                $row1 = mysql_fetch_array($result1);
                if($row1[0] == '3') {
                    $img = "t110_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '14', '0', '" . $week . "', 'Three', '" . $img . "')");
                }
                if($row1[0] == '5') {
                    $img = "t111_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '14', '0', '" . $week . "', 'Five', '" . $img . "')");
                }
                if($row1[0] == '10') {
                    $img = "t112_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '14', '0', '" . $week . "', 'Ten', '" . $img . "')");
                }
            }
        }

        //Population growth top-three streaks
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY clp DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 10 AND plaats<=" . $streakA . "");
            $row1 = mysql_fetch_array($result1);
            if($row1[0] == '3') {
                $img = "t200_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '11', '0', '" . $week . "', 'Three', '" . $img . "')");
            }
            if($row1[0] == '5') {
                $img = "t201_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '11', '0', '" . $week . "', 'Five', '" . $img . "')");
            }
            if($row1[0] == '10') {
                $img = "t202_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '11', '0', '" . $week . "', 'Ten', '" . $img . "')");
            }
        }

        if($streakSplit) {
            //Population growth top-ten streaks
            $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY clp DESC Limit " . $top);
            while($row = mysql_fetch_array($result)) {
                $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 10 AND plaats<=" . $streakB . "");
                $row1 = mysql_fetch_array($result1);
                if($row1[0] == '3') {
                    $img = "t210_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '16', '0', '" . $week . "', 'Three', '" . $img . "')");
                }
                if($row1[0] == '5') {
                    $img = "t211_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '16', '0', '" . $week . "', 'Five', '" . $img . "')");
                }
                if($row1[0] == '10') {
                    $img = "t212_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '16', '0', '" . $week . "', 'Ten', '" . $img . "')");
                }
            }
        }

        //je staat voor 3e / 5e / 10e keer in de top 3 overvallers
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY RR DESC Limit " . $top);
        while($row = mysql_fetch_array($result)) {
            $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 4 AND plaats<=" . $streakA . "");
            $row1 = mysql_fetch_array($result1);
            if($row1[0] == '3') {
                $img = "t160_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '9', '0', '" . $week . "', 'Three', '" . $img . "')");
            }
            if($row1[0] == '5') {
                $img = "t161_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '9', '0', '" . $week . "', 'Five', '" . $img . "')");
            }
            if($row1[0] == '10') {
                $img = "t162_1";
                mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '9', '0', '" . $week . "', 'Ten', '" . $img . "')");
            }
        }

        if($streakSplit) {
            //je staat voor 3e / 5e / 10e keer in de top 10 overvallers
            $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY RR DESC Limit " . $top);
            while($row = mysql_fetch_array($result)) {
                $result1 = mysql_query("SELECT count(*) FROM ".TB_PREFIX."medal WHERE userid='" . $row['id'] . "' AND categorie = 4 AND plaats<=" . $streakB . "");
                $row1 = mysql_fetch_array($result1);
                if($row1[0] == '3') {
                    $img = "t170_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '15', '0', '" . $week . "', 'Three', '" . $img . "')");
                }
                if($row1[0] == '5') {
                    $img = "t171_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '15', '0', '" . $week . "', 'Five', '" . $img . "')");
                }
                if($row1[0] == '10') {
                    $img = "t172_1";
                    mysql_query("insert into " . TB_PREFIX . "medal(userid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '15', '0', '" . $week . "', 'Ten', '" . $img . "')");
                }
            }
        }

        //Zet alle waardens weer op 0
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id > 3 ORDER BY id+0 DESC");
        while($row = mysql_fetch_array($result)) {
            mysql_query("UPDATE " . TB_PREFIX . "users SET ap=0, dp=0, Rc=0, clp=0, RR=0 WHERE id = " . $row['id']);
        }

        //Start alliance Medals
        $result = mysql_query("SELECT * FROM ".TB_PREFIX."alidata ORDER BY ap DESC Limit " . $allyTop);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "a2_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "allimedal(allyid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '1', '" . ($i) . "', '" . $allyweek . "', '" . $row['ap'] . "', '" . $img . "')");
        }

        $result = mysql_query("SELECT * FROM ".TB_PREFIX."alidata ORDER BY dp DESC Limit " . $allyTop);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "a3_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "allimedal(allyid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '2', '" . ($i) . "', '" . $allyweek . "', '" . $row['dp'] . "', '" . $img . "')");
        }

        $result = mysql_query("SELECT * FROM ".TB_PREFIX."alidata ORDER BY RR DESC Limit " . $allyTop);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "a4_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "allimedal(allyid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '4', '" . ($i) . "', '" . $allyweek . "', '" . $row['RR'] . "', '" . $img . "')");
        }

        $result = mysql_query("SELECT * FROM ".TB_PREFIX."alidata ORDER BY clp DESC Limit " . $allyTop);
        $i = 0;
        while($row = mysql_fetch_array($result)) {
            $i++;
            $img = "a1_" . ($i) . "";
            mysql_query("insert into " . TB_PREFIX . "allimedal(allyid, categorie, plaats, week, points, img) values('" . $row['id'] . "', '3', '" . ($i) . "', '" . $allyweek . "', '" . $row['clp'] . "', '" . $img . "')");
        }

        $result = mysql_query("SELECT * FROM ".TB_PREFIX."alidata ORDER BY id+0 DESC");
        while($row = mysql_fetch_array($result)) {
            mysql_query("UPDATE " . TB_PREFIX . "alidata SET ap=0, dp=0, RR=0, clp=0 WHERE id = " . $row['id']);
        }
    }

}

if(!defined('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP')) {
    $automation = new Automation;
}

?>
