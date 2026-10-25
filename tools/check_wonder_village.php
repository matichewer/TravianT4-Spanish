<?php
/**
 * La aldea de la Maravilla: qué se puede construir ahí, qué no, y qué la protege.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_wonder_village.php
 *
 * Una auditoría contra el T4 oficial encontró que casi nada de lo que distingue a esa aldea
 * funcionaba, y que los checkers que existían daban verde porque ninguno la miraba:
 *
 *   A. El gran almacén y el gran granero se construyen SIN plano de almacenamiento
 *      ("Building a World Wonder": "without needing an artefact"). Pedían el plano igual.
 *   B. El constructor maestro no funciona ahí. Estaba anunciado como bloqueado y se
 *      ofrecía, se aceptaba y se activaba.
 *   C. No puede haber Tesoro. Las aldeas nacían con uno de nivel 10 que sobrevivía a la
 *      conquista, se podía subir y recibía el plano o cualquier artefacto.
 *   D. El arquitecto protege los demás edificios de la aldea pero no la Maravilla ("does
 *      not apply to the Wonder of the World itself"), y la Maravilla en 0 sigue siendo la
 *      Maravilla: con el tipo del campo 99 se iba la aldea entera.
 *   E. El arquitecto también vale contra arietes ("against attacks from catapults and
 *      rams"); los arietes no leían ningún artefacto, en ninguna aldea.
 *   F. Lo mismo contra la base de verdad, sobre tablas temporales: la herramienta que
 *      derriba el Tesoro de fábrica.
 *
 * Todo lo que sigue corre el motor real; los dobles sólo reemplazan a la base.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Lang/".LANG.".php";
require_once $root.'/GameEngine/Battle.php';
require_once $root.'/GameEngine/GeneratorX.php';
require_once $root.'/GameEngine/Technology.php';
require_once $root.'/GameEngine/Automation.php';
require_once $root.'/GameEngine/Building.php';
$realDatabase = $database;

$failures = 0;
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        $failures++;
        echo '[FALLA] '.$message.PHP_EOL;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

define('WV_WW', 990700);       // la aldea de la Maravilla, ya de un jugador
define('WV_NORMAL', 990701);   // otra aldea del mismo jugador
define('WV_ENEMY', 990702);    // la aldea que ataca
define('WV_OWNER', 9077);

function wvArt($type, $size, $vref, $id = 1, $age = 999999999) {
    return array('id' => $id, 'type' => $type, 'size' => $size, 'vref' => $vref,
        'owner' => WV_OWNER, 'conquered' => max(0, time() - $age));
}

/** Una aldea con edificio principal 20 y nada más; `$wonder` le pone la Maravilla. */
function wvFields($wonder, $wonderLevel = 0) {
    $fields = array();
    for($slot = 1; $slot <= 40; $slot++) {
        $fields['f'.$slot] = 0;
        $fields['f'.$slot.'t'] = 0;
    }
    $fields['f26'] = 20;
    $fields['f26t'] = 15;
    $fields['f99'] = $wonder ? $wonderLevel : 0;
    $fields['f99t'] = $wonder ? WONDER_BUILDING_TYPE : 0;
    return $fields;
}

/** La capa de datos de mentira: devuelve filas crudas y deja decidir al motor. */
class WonderVillageDatabase {
    public $artefacts = array();
    public $fields = array();
    public $fieldsByVillage = array();
    public $village = array('maxstore' => 400000, 'maxcrop' => 400000);
    public $masterRows = array();
    public $queries = array();
    public $activated = array();
    public $unitsByVillage = array();

    public function getArtefactsByOwner($owner) { return (int)$owner === WV_OWNER ? $this->artefacts : array(); }
    public function getActiveArtefactsByOwner($owner) { return artefactActiveRows($this->getArtefactsByOwner($owner)); }
    public function getArtefactEffectValue($vref, $owner, $type) {
        return artefactVillageEffectValue($this->getActiveArtefactsByOwner($owner), $type, $vref);
    }
    public function hasActiveArtefactEffect($vref, $owner, $type) {
        return artefactVillageHasEffect($this->getActiveArtefactsByOwner($owner), $type, $vref);
    }
    public function getResourceLevel($vid) {
        return isset($this->fieldsByVillage[(int)$vid]) ? $this->fieldsByVillage[(int)$vid] : $this->fields;
    }
    public function setVillageLevel($vid, $field, $value) {
        if(isset($this->fieldsByVillage[(int)$vid])) {
            $this->fieldsByVillage[(int)$vid][$field] = (int)$value;
        } else {
            $this->fields[$field] = (int)$value;
        }
        return true;
    }
    public function getFieldLevel($vid, $field) {
        $fields = $this->getResourceLevel($vid);
        return isset($fields['f'.(int)$field]) ? (int)$fields['f'.(int)$field] : 0;
    }
    public function getVillage($vid) { return $this->village; }
    public function setVillageField($vid, $field, $value) { $this->village[$field] = $value; }
    public function setVillageCapacity($vid, $field, $value) { $this->village[$field] = $value; }
    public function getVillageField($vid, $field) {
        if($field === 'owner') { return WV_OWNER; }
        return in_array($field, array('wood', 'clay', 'iron', 'crop'), true) ? 1000000 : 0;
    }
    public function getUserField($uid, $field, $mode) {
        if($field === 'tribe') { return 1; }
        if($field === 'gold') { return 100; }
        return 0;
    }
    public function updateUserField($uid, $field, $value, $mode) { return true; }
    public function getVillagesID2($owner) { return array(array('wref' => WV_WW)); }
    public function query($sql) { $this->queries[] = $sql; return true; }
    public function query_return($sql) {
        if(strpos($sql, 'WHERE master = 1') !== false) {
            return $this->masterRows;
        }
        return array();
    }
    public function getHeroData($uid) { return array('dead' => 1, 'home' => 0, 'wref' => 0, 'speed' => 7); }
    public function getUnit($vref) { return isset($this->unitsByVillage[(int)$vref]) ? $this->unitsByVillage[(int)$vref] : array(); }
    public function getEnforceVillage($vref, $mode) { return array(); }
    public function getJobs($wid) { return array(); }
    public function getBuildingByField($wid, $field) { return array(); }
    public function getBuildingByType($wid, $type) { return array(); }
    public function getMasterJobs($wid) { return array(); }
    public function getMasterJobsByField($wid, $field) { return array(); }
    public function getDemolition($wid) { return array(); }
    public function getDorf1Building($wid) { return array(); }
    public function getDorf2Building($wid) { return array(); }
    public function hasPalace($uid, $exclude = 0) { return false; }
    public function activateMasterBuildingIfAffordable($id, $wid, $time, $loop, $wood, $clay, $iron, $crop) {
        $this->activated[] = (int)$id;
        return true;
    }
}

class WonderVillageStub {
    public $wid = WV_WW;
    public $capital = 0;
    public $pop = 500;
    public $resarray = array();
    public $maxstore = 400000;
    public $maxcrop = 400000;
    public $awood = 400000;
    public $aclay = 400000;
    public $airon = 400000;
    public $acrop = 400000;
    public $master = 0;
    public function getFreeCrop() { return 100000; }
    public function getBaseCropProduction() { return 100000; }
}

class WonderVillageAutomation extends Automation {
    public function recountPop($vid) { return 500; }
    protected function accrueProductionBeforeChange($villageId, $until) { return true; }
    public function syncTrapperCapacity($villageId) { return true; }
}

$stub = new WonderVillageDatabase();
$database = $stub;
$village = new WonderVillageStub();
$session = (object)array('uid' => WV_OWNER, 'tribe' => 1, 'plus' => 0, 'access' => 2, 'gold' => 100, 'goldclub' => 1);
$battle = new Battle();
$GLOBALS['database'] = $database;
$GLOBALS['village'] = $village;
$GLOBALS['session'] = $session;
$GLOBALS['battle'] = $battle;

$building = (new ReflectionClass('Building'))->newInstanceWithoutConstructor();
foreach(array('maxConcurrent' => 2, 'allocated' => 0, 'buildArray' => array(), 'basic' => 0, 'inner' => 0, 'plus' => 0) as $property => $value) {
    $reflected = new ReflectionProperty('Building', $property);
    $reflected->setAccessible(true);
    $reflected->setValue($building, $value);
}
$GLOBALS['building'] = $building;

function wvUseVillage($wid, $fields) {
    global $village, $stub;
    $village->wid = $wid;
    $village->resarray = $fields;
    $stub->fields = $fields;
}

// =====================================================================================
section('A. Gran almacén y gran granero sin plano en la Maravilla');
// =====================================================================================
$stub->artefacts = array();
wvUseVillage(WV_WW, wvFields(true));
check($building->storageGreatBuildingsUnlocked(), 'en la aldea de la Maravilla el gran almacenamiento está habilitado sin plano');
check($building->meetRequirement(38), 'se puede construir un gran almacén sin plano de almacenamiento');
check($building->meetRequirement(39), 'y un gran granero');
$html = $building->requirementsHtml(38);
check(strpos($html, 'a10000') === false, 'la ficha "Necesario:" no marca nada en rojo en la Maravilla');
check(stripos($html, 'Maravilla') !== false, 'y dice por qué no pide el plano');

$fields = wvFields(true);
$fields['f26'] = 9;
wvUseVillage(WV_WW, $fields);
check(!$building->meetRequirement(38), 'pero sigue pidiendo el edificio principal 10, como en cualquier aldea');

$fields = wvFields(true);
$fields['f30'] = 5;
$fields['f30t'] = 38;
wvUseVillage(WV_WW, $fields);
check(!$building->meetRequirement(38), 'y uno nuevo sólo cuando los que hay llegaron al máximo, como en cualquier aldea');

wvUseVillage(WV_NORMAL, wvFields(false));
check(!$building->meetRequirement(38) && !$building->meetRequirement(39),
    'en una aldea normal sin plano no hay gran almacén ni gran granero');
$stub->artefacts = array(wvArt(ARTEFACT_STORAGE, ARTEFACT_SIZE_SMALL, WV_NORMAL));
check($building->meetRequirement(38), 'con el plano pequeño en la aldea, sí');
$stub->artefacts = array(wvArt(ARTEFACT_STORAGE, ARTEFACT_SIZE_SMALL, WV_NORMAL, 1, 0));
check(!$building->meetRequirement(38), 'y un plano recién capturado todavía no habilita nada');
$stub->artefacts = array(wvArt(ARTEFACT_STORAGE, ARTEFACT_SIZE_LARGE, WV_WW));
check($building->meetRequirement(38), 'el plano grande de cuenta vale en cualquier aldea');
$stub->artefacts = array();

$list = file_get_contents($root.'/Templates/Build/avaliable.tpl');
check(substr_count($list, '$storageUnlocked = $building->storageGreatBuildingsUnlocked();') === 1,
    'la lista de construcciones pregunta lo mismo que el motor');
check(substr_count($list, '!($mainbuilding >= 10 && $storageUnlocked)') === 2,
    'y las dos tarjetas de "próximamente" también, así que en la Maravilla no piden el plano');
check(strpos($list, '$storageArtefact') === false,
    'ninguna reja vuelve a mirar sólo el artefacto');
check(preg_match('/capital == 0 && !\(\$mainbuilding >= 10/', $list) !== 1,
    'y la capital ve qué le falta para el gran almacén: ahí también se puede construir');

// =====================================================================================
section('B. El constructor maestro no trabaja en la Maravilla');
// =====================================================================================
$fields = wvFields(true);
$fields['f25'] = 5;
$fields['f25t'] = 10;
wvUseVillage(WV_WW, $fields);
check($building->masterBuildingRequest(25, 10) === false, 'no acepta subir un almacén de la Maravilla');
check($building->masterBuildingRequest(30, 11) === false, 'ni construir un granero nuevo ahí');
$fields = wvFields(false);
$fields['f25'] = 5;
$fields['f25t'] = 10;
wvUseVillage(WV_NORMAL, $fields);
check(is_array($building->masterBuildingRequest(25, 10)), 'en una aldea normal sí, o la regla se llevó puesto todo');

// La activación: un pedido que quedó en la cola de una Maravilla se cancela sin cobrarse,
// y el de una aldea normal se activa como siempre.
$automation = (new ReflectionClass('WonderVillageAutomation'))->newInstanceWithoutConstructor();
$masterBuilder = new ReflectionMethod('Automation', 'MasterBuilder');
$masterBuilder->setAccessible(true);
$wonderFields = wvFields(true);
$wonderFields['f30t'] = 10;   // addBuilding ya le escribió el tipo a la casilla vacía
$stub->fieldsByVillage = array(WV_WW => $wonderFields, WV_NORMAL => wvFields(false));
$stub->masterRows = array(
    array('id' => 71, 'wid' => WV_WW, 'field' => 30, 'type' => 10, 'loopcon' => 1, 'timestamp' => 60, 'master' => 1, 'level' => 1),
    array('id' => 72, 'wid' => WV_NORMAL, 'field' => 30, 'type' => 10, 'loopcon' => 1, 'timestamp' => 60, 'master' => 1, 'level' => 1)
);
$stub->queries = array();
$stub->activated = array();
$masterBuilder->invoke($automation);
check(!in_array(71, $stub->activated, true), 'MasterBuilder() no activa (ni cobra) el pedido de la Maravilla');
$cancelled = false;
foreach($stub->queries as $sql) {
    if(strpos($sql, 'DELETE FROM') !== false && strpos($sql, 'id = 71') !== false && strpos($sql, 'master = 1') !== false) {
        $cancelled = true;
    }
}
check($cancelled, 'lo cancela, sólo si sigue siendo un pedido del maestro');
check((int)$stub->fieldsByVillage[WV_WW]['f30t'] === 0, 'y suelta la casilla vacía que el pedido había reservado');
check(in_array(72, $stub->activated, true), 'el pedido de la aldea normal se activa como siempre');
$stub->fieldsByVillage = array();
$stub->masterRows = array();

$upgrade = file_get_contents($root.'/Templates/Build/upgrade.tpl');
check(strpos($upgrade, '$goldShortcutsAvailable = !wonderVillage($village->resarray);') !== false,
    'la ficha de mejora sabe que en la Maravilla no hay atajos con oro');
check(substr_count($upgrade, 'master=$bid') === substr_count($upgrade, 'if($goldShortcutsAvailable && $session->gold >= 1'),
    'y no ofrece el enlace del maestro en ninguna de sus ramas');
check(strpos($upgrade, 'if($goldShortcutsAvailable && $session->userinfo[\'gold\'] >= 3') !== false,
    'ni el botón del mercader NPC, que el servidor ya rechaza ahí');

// =====================================================================================
section('C. En la Maravilla no puede haber Tesoro');
// =====================================================================================
$fields = wvFields(true);
$fields['f22'] = 10;
$fields['f22t'] = 27;
wvUseVillage(WV_WW, $fields);
check(!$building->meetRequirement(27), 'no se puede construir un Tesoro nuevo en la Maravilla');
check($building->canBuild(22, 27) === 13, 'ni subir el que traía de fábrica: canBuild() devuelve 13');
check(strpos($upgrade, '$bindicate == 13') !== false, 'y la ficha explica por qué');
$fields = wvFields(false);
$fields['f22'] = 10;
$fields['f22t'] = 27;
wvUseVillage(WV_NORMAL, $fields);
check($building->canBuild(22, 27) !== 13, 'en una aldea normal el Tesoro se sube como siempre');

$theft = artefactTheftOutcome(array('type' => 3, 'hero_sent' => 1, 'hero_dead' => 0),
    array('artefact' => true, 'size' => ARTEFACT_SIZE_SMALL, 'type' => ARTEFACT_PLAN, 'treasury' => 0),
    array('treasury' => 10, 'artefact' => false, 'wonder_village' => true));
check($theft['status'] === 'attacker_wonder_village', 'un héroe que sale de la Maravilla no se trae el plano');
check(artefactTheftMessage($theft) !== '', 'y el informe dice por qué');
$theft = artefactTheftOutcome(array('type' => 3, 'hero_sent' => 1, 'hero_dead' => 0),
    array('artefact' => true, 'size' => ARTEFACT_SIZE_SMALL, 'type' => ARTEFACT_PLAN, 'treasury' => 0),
    array('treasury' => 10, 'artefact' => false));
check($theft['status'] === 'claimed', 'desde una aldea normal con Tesoro 10, sí');

$natarVillage = file_get_contents($root.'/GameEngine/NatarVillage.php');
check(preg_match('/function natarWonderBuildings\(.*?\n\}/s', $natarVillage, $natarWonder) === 1
    && strpos($natarWonder[0], '`f22t` = 27') === false && strpos($natarWonder[0], '27') === false,
    'las Aldeas de la Maravilla nuevas ya no nacen con Tesoro');

// =====================================================================================
section('D. El arquitecto y la casilla de la Maravilla');
// =====================================================================================
$impact = new ReflectionMethod('Automation', 'applyCatapultImpact');
$impact->setAccessible(true);
$target = array('owner' => WV_OWNER, 'capital' => 0);

function wvHitWonder($level, $power, $artefacts) {
    global $impact, $automation, $stub, $target;
    $fields = wvFields(true, $level);
    $stub->fields = $fields;
    $stub->artefacts = $artefacts;
    $impact->invoke($automation, WV_WW, WONDER_BUILDING_TYPE, $power, 0, 1, 0, $target);
    return $stub->fields;
}

check($automation->selectCatapultTargetSlot(wvFields(true, 50), WONDER_BUILDING_TYPE) === WONDER_FIELD,
    'apuntar al tipo 40 (la oleada natar lleva ctar1 = 40) da en el campo 99');
$power = $battle->calculateSiegeOutcome(0, 50, 0, 1, 1)['required'];
$plain = wvHitWonder(50, $power, array());
check((int)$plain['f99'] === 0, 'sin artefacto, la potencia justa tira la Maravilla 50');
$candidates = array(
    'el arquitecto grande' => array(wvArt(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_LARGE, WV_NORMAL)),
    'el arquitecto único' => array(wvArt(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_UNIQUE, WV_NORMAL)),
    'un arquitecto pequeño guardado en la misma aldea' => array(wvArt(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_SMALL, WV_WW))
);
// Y el necio cuando imita al arquitecto: se busca una fila cuya tirada de hoy lo sea.
for($id = 1; $id < 5000; $id++) {
    $fool = wvArt(ARTEFACT_FOOL, ARTEFACT_SIZE_UNIQUE, WV_NORMAL, $id);
    if(artefactEffectiveType($fool) === ARTEFACT_ARCHITECT) {
        $candidates['el necio imitando al arquitecto'] = array($fool);
        break;
    }
}
check(count($candidates) === 4, 'se encontró un necio que hoy imita al arquitecto');
foreach($candidates as $label => $artefacts) {
    $after = wvHitWonder(50, $power, $artefacts);
    check((int)$after['f99'] === (int)$plain['f99'], 'con '.$label.' la Maravilla cae igual (quedó en '.(int)$after['f99'].')');
}

// Los demás edificios de la aldea de la Maravilla sí quedan protegidos.
$power10 = $battle->calculateSiegeOutcome(0, 10, 0, 1, 1)['required'];
foreach(array('sin artefacto' => array(), 'con el arquitecto grande' => array(wvArt(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_LARGE, WV_NORMAL))) as $label => $artefacts) {
    $fields = wvFields(true, 50);
    $fields['f30'] = 10;
    $fields['f30t'] = 38;
    $stub->fields = $fields;
    $stub->artefacts = $artefacts;
    $impact->invoke($automation, WV_WW, 38, $power10, 0, 1, 0, $target);
    $greatWarehouse[$label] = (int)$stub->fields['f30'];
}
check($greatWarehouse['sin artefacto'] === 0, 'sin artefacto el gran almacén 10 de la Maravilla cae');
check($greatWarehouse['con el arquitecto grande'] > 0,
    'con el arquitecto grande aguanta (quedó en '.$greatWarehouse['con el arquitecto grande'].'): los otros edificios sí se protegen');

// La Maravilla en 0 sigue siendo la Maravilla.
$power1 = $battle->calculateSiegeOutcome(0, 1, 0, 1, 1)['required'];
$after = wvHitWonder(1, $power1 * 2, array());
check((int)$after['f99'] === 0, 'las catapultas pueden dejar la Maravilla en 0');
check((int)$after['f99t'] === WONDER_BUILDING_TYPE && wonderVillage($after),
    'pero el campo 99 conserva el tipo y la aldea sigue siendo la de la Maravilla');
$fields = wvFields(true, 10);
$fields['f30'] = 1;
$fields['f30t'] = 38;
$stub->fields = $fields;
$stub->artefacts = array();
$impact->invoke($automation, WV_WW, 38, $power1 * 2, 0, 1, 0, $target);
check((int)$stub->fields['f30t'] === 0, 'un edificio común en 0 sigue dejando la casilla libre');

// =====================================================================================
section('E. El arquitecto contra los arietes');
// =====================================================================================
function wvRamBattle($artefacts, $rams = 10) {
    global $battle, $stub;
    $stub->artefacts = $artefacts;
    $attacker = array();
    $defender = array();
    for($unit = 1; $unit <= 50; $unit++) {
        $attacker['u'.$unit] = 0;
        $defender['u'.$unit] = 0;
    }
    $attacker['u1'] = 3000;   // legionarios
    $attacker['u7'] = $rams;  // arietes
    $defender['u1'] = 1;
    $stub->unitsByVillage = array(WV_NORMAL => $defender);
    $result = $battle->calculateBattle(
        $attacker, array('id' => 1), 10, 1, 1, 0, 100, 100, 3, array(), array(), 0, 0, 10,
        999, WV_OWNER, WV_ENEMY, WV_NORMAL
    );
    return isset($result['wall_level_after']) ? (int)$result['wall_level_after'] : -1;
}
$plainWall = wvRamBattle(array());
check($plainWall > 0 && $plainWall < 10, 'sin artefacto diez arietes bajan la muralla 10 (quedó en '.$plainWall.')');
foreach(array(ARTEFACT_SIZE_SMALL => 'pequeño', ARTEFACT_SIZE_LARGE => 'grande', ARTEFACT_SIZE_UNIQUE => 'único') as $size => $label) {
    $wall = wvRamBattle(array(wvArt(ARTEFACT_ARCHITECT, $size, WV_NORMAL)));
    check($wall > $plainWall, 'con el arquitecto '.$label.' del defensor la muralla aguanta más (quedó en '.$wall.')');
}
$wall = wvRamBattle(array(wvArt(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_SMALL, WV_WW)));
check($wall === $plainWall, 'un arquitecto pequeño de OTRA aldea no protege esta muralla');
$stub->artefacts = array();

// =====================================================================================
section('F. Contra la base de verdad: el Tesoro de fábrica');
// =====================================================================================
//
// TEMPORARY TABLES: tapan a las reales para esta conexión y desaparecen al cerrarla.
$database = $realDatabase;
$GLOBALS['database'] = $realDatabase;
$P = TB_PREFIX;
$ready = true;
foreach(array('fdata', 'vdata', 'artefacts', 'bdata', 'demolition') as $table) {
    $create = $database->query_return("SHOW CREATE TABLE `{$P}{$table}`");
    if(!is_array($create) || !count($create)) {
        $ready = false;
        continue;
    }
    $database->query("DROP TEMPORARY TABLE IF EXISTS `{$P}{$table}`");
    $ready = (bool)$database->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create[0]['Create Table'], 1)) && $ready;
}
check($ready, 'se pudo clonar el mundo de prueba en tablas temporales');
if($ready) {
    $now = time();
    // Tres Maravillas con el Tesoro de fábrica: una limpia, una que guarda un artefacto y una
    // con una obra del Tesoro en la cola. Y una aldea normal con Tesoro, que no se toca.
    foreach(array(990801, 990802, 990803, 990804) as $wref) {
        $database->query("INSERT INTO `{$P}vdata` (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) "
            ."VALUES ($wref,".WV_OWNER.",0,100,0,100,$now,$now,800,800)");
        $database->query("INSERT INTO `{$P}fdata` (vref,f22t,f22,f26t,f26,f99t,f99) VALUES ($wref,27,10,15,20,"
            .($wref === 990804 ? '0,0' : WONDER_BUILDING_TYPE.',5').")");
    }
    $database->query("INSERT INTO `{$P}artefacts` (vref,owner,type,size,conquered,name,`desc`,effect,img) "
        ."VALUES (990802,".WV_OWNER.",".ARTEFACT_TRAINER.",1,1,'x','x','x','x')");
    $database->query("INSERT INTO `{$P}bdata` (wid,field,type,loopcon,timestamp,master,level) "
        ."VALUES (990803,22,27,0,".($now + 3600).",0,11)");

    $repairs = wonderVillageTreasuryRepairs($database);
    $byVillage = array();
    foreach($repairs as $repair) {
        $byVillage[$repair['wref']] = $repair;
    }
    check(count($repairs) === 3 && !isset($byVillage[990804]), 'encuentra los tres Tesoros de Maravilla y no el de la aldea normal');
    check(isset($byVillage[990801]) && $byVillage[990801]['status'] === 'ok' && $byVillage[990801]['level'] === 10,
        'el limpio se derriba');
    check(isset($byVillage[990802]) && $byVillage[990802]['status'] === 'holds_artefact', 'el que guarda un artefacto no se toca');
    check(isset($byVillage[990803]) && $byVillage[990803]['status'] === 'queued', 'ni el que tiene una obra del Tesoro en la cola');

    $realAutomation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
    foreach($repairs as $repair) {
        wonderVillageRemoveTreasury($realAutomation, $repair);
    }
    $after = $database->getResourceLevel(990801);
    check((int)$after['f22'] === 0 && (int)$after['f22t'] === 0, 'el Tesoro de la Maravilla quedó derribado');
    check((int)$after['f99t'] === WONDER_BUILDING_TYPE && (int)$after['f99'] === 5, 'y la Maravilla, intacta');
    $popAfter = (int)$database->getVillageField(990801, 'pop');
    $expectedPop = 0;
    foreach(villagePopulationSlots() as $slot) {
        $type = (int)$after['f'.$slot.'t'];
        $level = (int)$after['f'.$slot];
        for($l = 1; $l <= $level; $l++) {
            $expectedPop += isset($GLOBALS['bid'.$type][$l]['pop']) ? (int)$GLOBALS['bid'.$type][$l]['pop'] : 0;
        }
    }
    check($popAfter === $expectedPop, 'la población se recontó sin el Tesoro ('.$popAfter.')');
    foreach(array(990802, 990803, 990804) as $wref) {
        $untouched = $database->getResourceLevel($wref);
        check((int)$untouched['f22t'] === 27 && (int)$untouched['f22'] === 10, 'la aldea '.$wref.' conserva su Tesoro');
    }

    foreach(array('fdata', 'vdata', 'artefacts', 'bdata', 'demolition') as $table) {
        $database->query("DROP TEMPORARY TABLE IF EXISTS `{$P}{$table}`");
    }
}

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Aldea de la Maravilla: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
