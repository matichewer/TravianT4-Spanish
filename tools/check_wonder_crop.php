<?php
/**
 * La mitad de consumo de cereal en la aldea de la Maravilla.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_wonder_crop.php
 *
 * Oficial ("World Wonder", "Reducing crop consumption"): "Once construction plans appear,
 * units that are stationed inside a World Wonder village will consume only 50% crop". El
 * repo no lo tenía en ningún camino: dorf1, lo acreditado y la hambruna cobraban el 100%.
 *
 * Lo que el oficial no publica lo decide villageTroopUpkeep() (Wonder.php) y está en
 * AGENTS.md; este checker fija esas decisiones para que cambiarlas sea a propósito:
 *
 *   A. La cuenta: sólo lo estacionado come la mitad, el abrevadero se descuenta antes por
 *      unidad, la dieta se multiplica encima, y los dos ahorros cierran exacto.
 *   B. Qué es "estacionado": la guarnición, los refuerzos alojados y el héroe que esté ahí;
 *      no lo que está de viaje, en un oasis ni atrapado afuera.
 *   C. El motor: lo que se acredita y lee la hambruna da la cuenta de A, antes y después de
 *      los planos; una Maravilla natar sigue sin pagar; dorf1 pasa por la misma función.
 *   D. El disparador: aparecen los planos cuando existe el primer tipo 9, y justo antes se
 *      acreditan las Maravillas de jugadores con la tarifa vieja, una sola vez.
 *   E. La barra de recursos, el resumen de aldeas, el detalle de producción y la ayuda
 *      muestran el número que se cobra.
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
require_once $root.'/GameEngine/Technology.php';
require_once $root.'/GameEngine/Automation.php';
$realDatabase = $database;
$technology = new Technology();

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

define('WC_WW', 990900);       // aldea de la Maravilla de un jugador
define('WC_NORMAL', 990901);   // aldea normal del mismo jugador
define('WC_ALLY_V', 990902);   // aldea de un aliado que refuerza la Maravilla
define('WC_OASIS', 990903);    // oasis anexado a la Maravilla
define('WC_OWNER', 9091);
define('WC_ALLY', 9092);

function wcArt($type, $size, $vref, $id = 1, $age = 999999999) {
    return array('id' => $id, 'type' => $type, 'size' => $size, 'vref' => $vref,
        'owner' => WC_OWNER, 'conquered' => max(0, time() - $age));
}

/** 18 campos en nivel 10 y edificio principal 20; `$wonder` agrega la Maravilla. */
function wcFields($wonder) {
    $fields = array();
    for($slot = 1; $slot <= 40; $slot++) {
        $fields['f'.$slot] = 0;
        $fields['f'.$slot.'t'] = 0;
    }
    foreach(array(1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,4,4) as $index => $type) {
        $fields['f'.($index + 1)] = 10;
        $fields['f'.($index + 1).'t'] = $type;
    }
    $fields['f26'] = 20;
    $fields['f26t'] = 15;
    $fields['f99'] = $wonder ? 5 : 0;
    $fields['f99t'] = $wonder ? WONDER_BUILDING_TYPE : 0;
    return $fields;
}

/** La capa de datos de mentira: filas crudas, el motor decide. */
class WonderCropDatabase {
    public $artefacts = array();
    public $plansAppeared = false;
    public $fields = array();
    public $units = array();
    public $enforcement = array();
    public $oases = array();
    public $movement = array();
    public $prisoners = array();
    public $village = array();
    public $owners = array(WC_WW => WC_OWNER, WC_NORMAL => WC_OWNER, WC_ALLY_V => WC_ALLY);

    public function wonderPlansHaveAppeared() { return $this->plansAppeared; }
    public function getArtefactsByOwner($owner) { return (int)$owner === WC_OWNER ? $this->artefacts : array(); }
    public function getActiveArtefactsByOwner($owner) { return artefactActiveRows($this->getArtefactsByOwner($owner)); }
    public function getArtefactEffectValue($vref, $owner, $type) {
        return artefactVillageEffectValue($this->getActiveArtefactsByOwner($owner), $type, $vref);
    }
    public function getResourceLevel($vid) { return $this->fields; }
    public function getVillage($vid) {
        return $this->village + array('wref' => $vid, 'owner' => WC_OWNER, 'pop' => 500, 'lastupdate' => time(),
            'natar' => 1, 'npckind' => 0, 'capital' => 0);
    }
    public function getVillageField($vid, $field) {
        if($field === 'owner') { return isset($this->owners[(int)$vid]) ? $this->owners[(int)$vid] : 0; }
        return isset($this->village[$field]) ? $this->village[$field] : 0;
    }
    public function getUserField($uid, $field, $mode) { return $field === 'tribe' ? 1 : 0; }
    public function getUserArray($uid, $mode) { return array('b1' => 0, 'b2' => 0, 'b3' => 0, 'b4' => 0, 'plus' => 0); }
    public function getHeroData($uid) { return array('dead' => 1, 'home' => 0, 'wref' => 0, 'speed' => 7); }
    public function getUnit($vid) {
        $row = isset($this->units[(int)$vid]) ? $this->units[(int)$vid] : array();
        return $row + array('vref' => $vid, 'u99' => 0, 'u99o' => 0, 'hero' => 0);
    }
    public function getEnforceVillage($vid, $mode) {
        $rows = array();
        foreach($this->enforcement as $row) {
            if((int)($mode ? $row['from'] : $row['vref']) === (int)$vid) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    public function getOasis($vid) { return (int)$vid === WC_WW ? $this->oases : array(); }
    public function getVillageMovement($vid) { return (int)$vid === WC_WW ? $this->movement : array(); }
    public function getPrisoners3($vid) { return (int)$vid === WC_WW ? $this->prisoners : array(); }
    public function query_return($sql) { return array(); }
    public function query($sql) { return true; }
}

class WonderCropAutomation extends Automation {
    public $accrued = array();
    protected function accrueProductionBeforeChange($villageId, $until) {
        $this->accrued[] = (int)$villageId;
        return true;
    }
}

$stub = new WonderCropDatabase();
$database = $stub;
$GLOBALS['database'] = $stub;
$session = (object)array('uid' => WC_OWNER, 'tribe' => 1);
$GLOBALS['session'] = $session;
$village = null;

// =====================================================================================
section('A. La cuenta de villageTroopUpkeep()');
// =====================================================================================
$ww = wcFields(true);
$normal = wcFields(false);

$stub->plansAppeared = false;
$detail = villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 1000, 800);
check($detail['charged'] === 1000, 'antes de que aparezcan los planos, la Maravilla paga el 100% (1.000)');

$stub->plansAppeared = true;
$detail = villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 1000, 800);
check($detail['charged'] === 600, 'con los planos, lo estacionado come la mitad: 200 + 800/2 = 600 (dio '.$detail['charged'].')');
check(abs($detail['wonder_saving'] - 400) < 0.001 && abs($detail['artefact_saving']) < 0.001,
    'y el ahorro se atribuye a la Maravilla, no al artefacto');
check(villageTroopUpkeep($stub, WC_OWNER, WC_NORMAL, $normal, 1000, 800)['charged'] === 1000,
    'una aldea normal no come la mitad aunque haya planos');
check(villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 1000, 5000)['charged'] === 500,
    'lo estacionado nunca puede ser más que el total');

// La dieta se multiplica encima: es la inferencia documentada en AGENTS.md.
foreach(array(ARTEFACT_SIZE_SMALL => 300, ARTEFACT_SIZE_LARGE => 450, ARTEFACT_SIZE_UNIQUE => 300) as $size => $expected) {
    $stub->artefacts = array(wcArt(ARTEFACT_DIET, $size, $size === ARTEFACT_SIZE_SMALL ? WC_WW : WC_NORMAL));
    $detail = villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 1000, 800);
    check($detail['charged'] === $expected,
        'con la dieta de tamaño '.$size.' paga '.$detail['charged'].' (esperado '.$expected.': la mitad y encima la dieta)');
    check(abs($detail['wonder_saving'] + $detail['artefact_saving'] - $detail['saving']) < 0.001,
        'los dos ahorros suman exacto lo que se deja de pagar');
}
$stub->artefacts = array(wcArt(ARTEFACT_DIET, ARTEFACT_SIZE_SMALL, WC_NORMAL));
check(villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 1000, 800)['charged'] === 600,
    'la dieta pequeña de OTRA aldea no llega a la Maravilla');
$stub->artefacts = array();
$detail = villageTroopUpkeep($stub, WC_OWNER, WC_WW, $ww, 3, 3);
check($detail['charged'] === 2 && abs($detail['wonder_saving'] + $detail['artefact_saving'] - 1) < 0.001,
    'con números impares se redondea una sola vez, al final, y el detalle sigue cerrando');
check(artefactTroopUpkeep($stub, WC_OWNER, WC_WW, 1000)['charged'] === 1000,
    'artefactTroopUpkeep() sigue siendo sólo la dieta: la Maravilla vive en villageTroopUpkeep()');

// =====================================================================================
section('B. Qué está estacionado en la aldea');
// =====================================================================================
$stub->units = array(WC_WW => array('u1' => 100, 'u6' => 10, 'hero' => 1));
$stub->enforcement = array(
    array('id' => 1, 'from' => WC_ALLY_V, 'vref' => WC_WW, 'u1' => 50, 'hero' => 1),   // refuerzo de un aliado, con su héroe
    array('id' => 2, 'from' => WC_WW, 'vref' => WC_OASIS, 'u1' => 30, 'hero' => 0),    // tropas propias en el oasis anexado
    array('id' => 3, 'from' => WC_WW, 'vref' => WC_ALLY_V, 'u1' => 40, 'hero' => 0)     // tropas propias reforzando a otro
);
$stub->oases = array(array('wref' => WC_OASIS));
$stub->movement = array('u1' => 20, 'hero' => 0);
$stub->prisoners = array(array('t1' => 10, 't11' => 0));
$stationed = $technology->getStationedUnits(WC_WW);
$all = $technology->getAllUnits(WC_WW);
check((int)$stationed['u1'] === 150 && (int)$stationed['hero'] === 2,
    'estacionado: la guarnición y el refuerzo alojado, con los dos héroes (150 legionarios, 2 héroes)');
check((int)$stationed['u6'] === 10, 'y la caballería de la guarnición');
check((int)$all['u1'] === 150 + 30 + 20 + 10,
    'lo que la aldea alimenta suma además el oasis, el viaje y los atrapados (dio '.(int)$all['u1'].')');
check((int)$stationed['u99'] === 0 && (int)$all['u99'] === 0, 'las trampas no son tropas en ninguna de las dos cuentas');

// =====================================================================================
section('C. El motor: lo que se acredita y lee la hambruna');
// =====================================================================================
$automation = (new ReflectionClass('WonderCropAutomation'))->newInstanceWithoutConstructor();
$netMethod = new ReflectionMethod('Automation', 'villageNetCropProduction');
$netMethod->setAccessible(true);
$stub->fields = $ww;
$stub->village = array('pop' => 500, 'npckind' => 0);
$allUpkeep = $automation->getUpkeep($technology->getAllUnits(WC_WW), 0, WC_WW);
$stationedUpkeep = $automation->getUpkeep($technology->getStationedUnits(WC_WW), 0, WC_WW);
$gross = villageGrossProduction($ww, array(0, 0, 0, 0), array(false, false, false, false), SPEED);
$grossCrop = $gross['production']['crop'];
// 210 legionarios (1), 10 Equites Caesaris (4) y dos héroes (6); estacionados, 150 legionarios.
check($allUpkeep === 210 + 10 * 4 + 2 * 6 && $stationedUpkeep === 150 + 10 * 4 + 2 * 6,
    'consumo de prueba: '.$allUpkeep.' en total y '.$stationedUpkeep.' estacionado');

$stub->plansAppeared = false;
$before = $netMethod->invoke($automation, WC_WW);
check(abs($before - ($grossCrop - 500 - $allUpkeep)) < 0.001,
    'antes de los planos el neto es bruto − población − todo el consumo ('.round($before).')');
$stub->plansAppeared = true;
$after = $netMethod->invoke($automation, WC_WW);
$expected = $grossCrop - 500 - (int)round(($allUpkeep - $stationedUpkeep) + $stationedUpkeep * WONDER_CROP_UPKEEP_FACTOR);
check(abs($after - $expected) < 0.001,
    'después de los planos, lo estacionado paga la mitad y lo demás entero ('.round($after).', esperado '.round($expected).')');
$stub->artefacts = array(wcArt(ARTEFACT_DIET, ARTEFACT_SIZE_LARGE, WC_NORMAL));
$withDiet = $netMethod->invoke($automation, WC_WW);
$expected = $grossCrop - 500 - (int)round((($allUpkeep - $stationedUpkeep) + $stationedUpkeep * WONDER_CROP_UPKEEP_FACTOR) * 0.75);
check(abs($withDiet - $expected) < 0.001, 'con la dieta grande encima, la misma cuenta que villageTroopUpkeep()');
$stub->artefacts = array();

// El abrevadero va antes, por unidad: 10 Equites Caesaris comen 4 cada uno, 3 con el
// abrevadero en 20, y la mitad de eso en la Maravilla.
$withTrough = $ww;
$withTrough['f30'] = 20;
$withTrough['f30t'] = 41;
$stub->fields = $withTrough;
$troughStationed = $automation->getUpkeep($technology->getStationedUnits(WC_WW), 0, WC_WW);
check($troughStationed === $stationedUpkeep - 10, 'el abrevadero 20 le saca 1 de cereal a cada Equites Caesaris antes de la mitad');
$stub->fields = $ww;

// Una Maravilla natar sigue sin pagar nada, con o sin planos.
$stub->village = array('pop' => 500, 'npckind' => NPC_KIND_STATIC);
$stub->owners[WC_WW] = natarsAccountId();
$natar = $netMethod->invoke($automation, WC_WW);
check(abs($natar - ($grossCrop - 500)) < 0.001, 'una Maravilla natar estática sigue sin pagar manutención');
$stub->owners[WC_WW] = WC_OWNER;
$stub->village = array('pop' => 500, 'npckind' => 0);

$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
check(strpos($automationSource, '$net = $this->villageNetCropProduction($wref);') !== false,
    'la hambruna lee este mismo neto');
check(preg_match('/function bountycalculateProduction\(.*?villageTroopUpkeep\(\$database, \$uid, \$bountywid, \$this->bountyresarray, \$upkeep, \$stationedUpkeep\)/s', $automationSource) === 1,
    'lo acreditado pasa por villageTroopUpkeep()');

// dorf1 no se puede ejecutar sin una sesión, así que se fija que pase por la misma función
// con las mismas dos entradas.
$villageEngine = preg_replace('/\s+/', '', file_get_contents($root.'/GameEngine/Village.php'));
check(strpos($villageEngine, '$stationedUpkeep=wonderVillage($this->resarray)?$technology->getUpkeep($technology->getStationedUnits($this->wid),0):0;') !== false,
    'dorf1 calcula lo estacionado con getStationedUnits(), igual que lo acreditado');
check(strpos($villageEngine, 'villageTroopUpkeep($database,$session->uid,$this->wid,$this->resarray,$upkeep,$stationedUpkeep)') !== false,
    'y cobra con villageTroopUpkeep()');
check(strpos($villageEngine, "['upkeep_charged']=\$upkeepDetail['charged'];") !== false
    && strpos($villageEngine, "['wonder_saving']=\$upkeepDetail['wonder_saving'];") !== false,
    'y deja el consumo cobrado y el ahorro de la Maravilla en el detalle de producción');

// =====================================================================================
section('D. El disparador: aparecen los planos');
// =====================================================================================
check(wonderPlansHaveAppeared(new stdClass()) === false, 'un doble sin el método contesta que no');

// artefactReleaseExecute() con una base que no tiene casillas libres: no crea nada, pero
// tiene que avisar UNA vez antes del primer plano y nunca si ya había planos.
class NoTileDatabase {
    public $plansAppeared = false;
    public function wonderPlansHaveAppeared() { return $this->plansAppeared; }
    public function query_return($sql) { return array(); }
}
$calls = 0;
$counter = function($until) use (&$calls) { $calls++; };
$plan = array('villages' => array(
    array('type' => ARTEFACT_TRAINER, 'size' => 1, 'ring' => array(0, 10)),
    array('type' => ARTEFACT_PLAN, 'size' => 1, 'ring' => array(0, 10)),
    array('type' => ARTEFACT_PLAN, 'size' => 1, 'ring' => array(0, 10))
));
$noTiles = new NoTileDatabase();
artefactReleaseExecute($noTiles, $plan, 2, $counter);
check($calls === 1, 'el primer plano dispara la acreditación de las Maravillas una sola vez (fueron '.$calls.')');
$calls = 0;
$noTiles->plansAppeared = true;
artefactReleaseExecute($noTiles, $plan, 2, $counter);
check($calls === 0, 'si el mundo ya tenía planos, no se vuelve a acreditar');
$calls = 0;
$noTiles->plansAppeared = false;
artefactReleaseExecute($noTiles, array('villages' => array($plan['villages'][0])), 2, $counter);
check($calls === 0, 'una liberación sin planos no toca a las Maravillas');

$release = file_get_contents($root.'/GameEngine/ArtefactRelease.php');
$panel = file_get_contents($root.'/GameEngine/Admin/Mods/addArtefacts.php');
check(strpos($automationSource, "artefactReleaseRunScheduled(\$database, null, array(\$this, 'accrueWonderVillagesBeforePlans'))") !== false,
    'la liberación programada le pasa la acreditación');
check(strpos($release, 'artefactReleaseExecute($database, $plan, $natarId, $beforePlansAppear)') !== false,
    'y la programada la propaga hasta el sembrado');
check(strpos($panel, "array(\$automation, 'accrueWonderVillagesBeforePlans')") !== false,
    'el botón del panel también');

// Contra la base de verdad, sobre tablas temporales.
$database = $realDatabase;
$GLOBALS['database'] = $realDatabase;
$P = TB_PREFIX;
$ready = true;
foreach(array('artefacts', 'fdata', 'vdata') as $table) {
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
    $database->flushArtefactCache();
    check($database->wonderPlansHaveAppeared() === false, 'sin ningún plano en el mundo, no aparecieron');
    $database->query("INSERT INTO `{$P}artefacts` (vref,owner,type,size,conquered,name,`desc`,effect,img) "
        ."VALUES (1,".WC_OWNER.",".ARTEFACT_TRAINER.",1,1,'x','x','x','x')");
    $database->flushArtefactCache();
    check($database->wonderPlansHaveAppeared() === false, 'otros artefactos no cuentan');
    $database->query("INSERT INTO `{$P}artefacts` (vref,owner,type,size,conquered,name,`desc`,effect,img) "
        ."VALUES (2,".natarsAccountId().",".ARTEFACT_PLAN.",1,".time().",'x','x','x','x')");
    check($database->wonderPlansHaveAppeared() === false, 'la respuesta se guarda por request hasta que se vacía la caché');
    $database->flushArtefactCache();
    check($database->wonderPlansHaveAppeared() === true, 'con un plano en el mundo —aunque sea de los natars— aparecieron');

    $now = time();
    $natarId = natarsAccountId();
    foreach(array(
        array(990950, WC_OWNER, true),     // Maravilla de jugador: se acredita
        array(990951, $natarId, true),  // Maravilla natar: no paga manutención, no se toca
        array(990952, WC_OWNER, false)     // aldea normal: no cambia nada
    ) as $row) {
        list($wref, $owner, $wonder) = $row;
        $database->query("INSERT INTO `{$P}vdata` (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) "
            ."VALUES ($wref,$owner,0,100,0,100,$now,$now,800,800)");
        $database->query("INSERT INTO `{$P}fdata` (vref,f99t,f99) VALUES ($wref,".($wonder ? WONDER_BUILDING_TYPE : 0).",0)");
    }
    $recorder = (new ReflectionClass('WonderCropAutomation'))->newInstanceWithoutConstructor();
    $recorder->accrueWonderVillagesBeforePlans($now);
    check($recorder->accrued === array(990950),
        'antes del primer plano se acreditan sólo las Maravillas de jugadores ('.implode(',', $recorder->accrued).')');

    foreach(array('artefacts', 'fdata', 'vdata') as $table) {
        $database->query("DROP TEMPORARY TABLE IF EXISTS `{$P}{$table}`");
    }
    $database->flushArtefactCache();
}

// =====================================================================================
section('E. Lo que ve el jugador es lo que se cobra');
// =====================================================================================
$res = file_get_contents($root.'/Templates/res.tpl');
check(strpos($res, "\$cropBreakdown['upkeep_charged']") !== false && strpos($res, 'getUpkeep(') === false,
    'la barra de recursos muestra el consumo cobrado, no el mantenimiento crudo');
$overview = file_get_contents($root.'/Templates/dorf3/3.tpl');
check(strpos($overview, 'villageTroopUpkeep(') !== false && strpos($overview, 'getStationedUnits(') !== false,
    'el resumen de aldeas cobra con la misma función y la misma parte estacionada');
check(strpos($overview, 'heroVillageResourceBonus(') !== false,
    'y suma el héroe, como dorf1');
$tooltip = file_get_contents($root.'/Templates/production.tpl');
check(strpos($tooltip, "\$details['wonder_saving']") !== false && strpos($tooltip, 'Aldea de la Maravilla (consumo ahorrado)') !== false,
    'el detalle de producción muestra el ahorro de la Maravilla');
$help = file_get_contents($root.'/endgame.php');
check(strpos($help, 'WONDER_CROP_UPKEEP_FACTOR') !== false,
    'la ayuda saca el porcentaje del motor, no del texto');

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Cereal en la Maravilla: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
