<?php
/**
 * Auditoría del candado de alimentos del T4 oficial (Building::passesFoodGuard) y
 * del final de obra con oro (Building::finishAll).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_build_food_guard.php
 *
 * La regla del oficial: no se encola nada que deje el CEREAL LIBRE por debajo de 1,
 * donde cereal libre = producción de las plantaciones + molino/panadería + oasis,
 * SIN el bono de oro ni el héroe, MENOS los habitantes. Las tropas no entran.
 *
 * Cubre:
 *   A. El umbral es 1, no 0, y se mide contra el cereal libre real.
 *   B. Las tropas no bloquean la construcción (la diferencia grande con la versión
 *      vieja, que restaba su consumo y dejaba aldeas enteras sin poder construir).
 *   C. Las cinco excepciones del oficial: plantación, molino/panadería que suben el
 *      libre, principal nivel 1, principal/almacén/granero hasta 10 con producción
 *      base sobre el umbral, y maravilla del mundo.
 *   D. El consumo proyectado cuenta el nivel al que apunta cada trabajo encolado.
 *   E. Ni el bono de oro ni el héroe abren el candado.
 *   F. El constructor maestro pasa por el mismo candado (era una puerta de atrás).
 *   G. Demolición: molino y panadería no se pueden tirar si dejan el libre bajo 1.
 *   H. calculateAvaliable() no divide por cero y avisa cuando no hay fecha posible.
 *   I. finishAll delega el fin de obra en Automation.
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$GLOBALS['checks'] = 0;
$GLOBALS['fails'] = array();

function check($condition, $message) {
    $GLOBALS['checks']++;
    if($condition) {
        return true;
    }
    $GLOBALS['fails'][] = $message;
    echo "  FAIL  ".$message."\n";
    return false;
}

function section($title) {
    echo "\n== ".$title." ==\n";
}

define('TB_PREFIX', 's1_');
define('SPEED', 1);
define('ALLOW_ALL_TRIBE', false);
define('BASIC_MAX', 1);
define('INNER_MAX', 1);
define('PLUS_MAX', 1);
define('BANNED', 9);
define('MODERATOR', 8);

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Production.php';
require dirname(__DIR__).'/GameEngine/Building.php';

/** Capa de datos mínima para canBuild(). */
class FoodGuardDatabase {
    public $jobs = array();          // trabajos de la aldea
    public $demolition = array();
    public $demolitions = array();   // demoliciones encoladas por el test
    public function getJobs($wid) { return $this->jobs; }
    public function getBuildingByField($wid, $field) {
        $out = array();
        foreach($this->jobs as $job) {
            if((int)$job['field'] === (int)$field && (int)$job['master'] === 0) { $out[] = $job; }
        }
        return $out;
    }
    public function getDemolition($wid) { return $this->demolition; }
    public function getFieldLevel($wid, $field) {
        global $village;
        return isset($village->resarray['f'.$field]) ? (int)$village->resarray['f'.$field] : 0;
    }
    public function getMasterJobs($wid) { return array(); }
    public function getMasterJobsByField($wid, $field) { return array(); }
    public function addDemolition($wid, $field) { $this->demolitions[] = (int)$field; }
}

/**
 * Aldea con campos y habitantes de verdad: el cereal libre sale de la fórmula del
 * juego, no de un número puesto a mano, así que el test se rompe si la fórmula
 * cambia. El balance con tropas (getProd) se controla aparte, justamente para poder
 * comprobar que el candado NO lo mira.
 */
class FoodGuardVillage {
    public $wid = 1;
    public $capital = 0;
    public $pop = 0;
    public $resarray = array();
    public $ocounter = array(0,0,0,0);
    public $maxstore = 1000000;
    public $maxcrop = 1000000;
    public $awood = 1000000;
    public $aclay = 1000000;
    public $airon = 1000000;
    public $acrop = 1000000;
    public $netCrop = 1000;          // balance con tropas, el que ve el jugador
    public function getProd($type) { return $type === 'crop' ? $this->netCrop : 1000; }
    public function getOasisCounter() { return $this->ocounter; }
    public function getBaseCropProduction() {
        return villageBaseCropProduction($this->resarray, $this->ocounter, SPEED);
    }
    public function getFreeCrop() {
        return villageFreeCrop($this->resarray, $this->ocounter, $this->pop, SPEED);
    }
}

$database = new FoodGuardDatabase();
$village = new FoodGuardVillage();
$session = (object)array('tribe'=>1, 'plus'=>0, 'access'=>1);

$reflection = new ReflectionClass('Building');
$building = $reflection->newInstanceWithoutConstructor();
$buildArrayProperty = $reflection->getProperty('buildArray');
$buildArrayProperty->setAccessible(true);
$allocatedProperty = $reflection->getProperty('allocated');
$allocatedProperty->setAccessible(true);
$maxConcurrentProperty = $reflection->getProperty('maxConcurrent');
$maxConcurrentProperty->setAccessible(true);
$innerProperty = $reflection->getProperty('inner');
$innerProperty->setAccessible(true);

/**
 * Arma la aldea: `$cropFields` plantaciones a nivel `$cropLevel`, `$pop` habitantes,
 * y los edificios que se pasen en `$buildings` (campo => array(tipo, nivel)).
 */
function foodGuardVillage($cropFields, $cropLevel, $pop, $buildings = array(), $oasis = array(0,0,0,0)) {
    global $village, $database;
    $resarray = array();
    for($slot = 1; $slot <= 40; $slot++) { $resarray['f'.$slot] = 0; $resarray['f'.$slot.'t'] = 0; }
    for($slot = 1; $slot <= $cropFields; $slot++) {
        $resarray['f'.$slot.'t'] = 4;
        $resarray['f'.$slot] = $cropLevel;
    }
    foreach($buildings as $slot => $pair) {
        $resarray['f'.$slot.'t'] = $pair[0];
        $resarray['f'.$slot] = $pair[1];
    }
    $village->resarray = $resarray;
    $village->pop = $pop;
    $village->ocounter = $oasis;
    $database->jobs = array();
    $database->demolition = array();
    return $village;
}

/** Prepara el estado del constructor y devuelve el código de canBuild(). */
function foodGuardCode($field, $type, $jobs = array()) {
    global $database, $building, $buildArrayProperty, $allocatedProperty, $maxConcurrentProperty, $innerProperty;
    $database->jobs = $jobs;
    $buildArrayProperty->setValue($building, $jobs);
    $allocatedProperty->setValue($building, 0);
    $maxConcurrentProperty->setValue($building, 5);
    $innerProperty->setValue($building, 0);
    return $building->canBuild($field, $type);
}

function foodGuardJob($id, $field, $type, $level, $master = 0) {
    return array('id'=>$id, 'wid'=>1, 'field'=>$field, 'type'=>$type, 'level'=>$level,
                 'loopcon'=>0, 'timestamp'=>time()+600, 'master'=>$master);
}

/** Cuántos habitantes hay que ponerle a la aldea para que el libre quede en $target. */
function popForFreeCrop($target) {
    global $village;
    return (int)round($village->getBaseCropProduction() - $target);
}

const CODE_MAX = 1;
const CODE_FOOD = 4;
const CODE_OK = 8;

// ---------------------------------------------------------------------------
section('A. El umbral del oficial es 1, no 0');
// ---------------------------------------------------------------------------
// Almacén nivel 5 -> 6 cuesta 1 habitante.
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$village->pop = popForFreeCrop(2);
check(foodGuardCode(20, 10) === CODE_OK,
    'con el libre en 2, una mejora de 1 habitante lo deja en 1 y se permite');
$village->pop = popForFreeCrop(1);
check(foodGuardCode(20, 10) === CODE_FOOD,
    'con el libre en 1, esa misma mejora lo dejaría en 0 y se bloquea');
$village->pop = popForFreeCrop(0);
check(foodGuardCode(20, 10) === CODE_FOOD, 'con el libre en 0 no se construye');
$village->pop = popForFreeCrop(-50);
check(foodGuardCode(20, 10) === CODE_FOOD, 'una aldea en rojo tampoco');

// Un edificio sin costo de habitantes también necesita libre >= 1: el oficial pide
// que quede cereal libre, no sólo que la mejora no consuma.
foodGuardVillage(6, 5, 0, array(21=>array(23,0)));
$village->pop = popForFreeCrop(0);
check($GLOBALS['bid23'][1]['pop'] === 0, 'el escondite nivel 1 no cuesta habitantes (si no, no probaría nada)');
check(foodGuardCode(21, 23) === CODE_FOOD,
    'con el libre en 0 ni siquiera se construye algo que no consume');
$village->pop = popForFreeCrop(1);
check(foodGuardCode(21, 23) === CODE_OK, 'con el libre en 1 sí');

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
check(strpos($source, '<= -68') === false, 'ya no queda el margen de -68 de cereal');
check(strpos($source, '$village->getProd("crop") - $soonPop') === false,
    'el candado ya no usa el balance con tropas');

// ---------------------------------------------------------------------------
section('B. Las tropas no bloquean la construcción');
// ---------------------------------------------------------------------------
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$village->pop = popForFreeCrop(5);
$village->netCrop = 1000;
$withoutArmy = foodGuardCode(20, 10);
$village->netCrop = -5000;   // un martillo comiéndose la aldea
$withArmy = foodGuardCode(20, 10);
check($withoutArmy === CODE_OK && $withArmy === CODE_OK,
    'un ejército que deja el balance en -5000 no impide construir (así es el oficial)');
$village->netCrop = 1000;

// ---------------------------------------------------------------------------
section('C. Las cinco excepciones del oficial');
// ---------------------------------------------------------------------------
// C1. Plantación de cereal: siempre.
foodGuardVillage(6, 5, 0);
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(3, 4) === CODE_OK, 'una plantación se mejora aunque la aldea esté en rojo');

// C2. Molino y panadería, sólo si la mejora sube el cereal libre.
// Molino 1: +5% sobre los campos y cuesta 3 habitantes; con 6 campos a nivel 5 son
// 198/h, así que +10 contra -3 conviene. Con los campos a nivel 2 son 54/h: +3 -3 = 0.
foodGuardVillage(6, 5, 0, array(22=>array(0,0)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(22, 8) === CODE_OK, 'el molino nivel 1 se permite si sube el libre');
foodGuardVillage(6, 2, 0, array(22=>array(0,0)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(22, 8) === CODE_FOOD,
    'con los campos bajos el mismo molino no compensa sus habitantes y se bloquea');
foodGuardVillage(6, 2, 0, array(22=>array(0,0)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(22, 9) === CODE_FOOD,
    'la panadería nivel 1 cuesta 4 habitantes y con 54/h sólo devuelve 3: se bloquea');
foodGuardVillage(6, 5, 0, array(22=>array(0,0)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(22, 9) === CODE_OK, 'con 198/h la panadería sí sube el libre y pasa');

// C3. Edificio principal nivel 1: siempre. Nivel 2 ya no.
foodGuardVillage(6, 5, 0, array(23=>array(0,0)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(23, 15) === CODE_OK, 'el edificio principal nivel 1 se construye siempre');
foodGuardVillage(6, 5, 0, array(23=>array(15,1)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(23, 15) === CODE_FOOD,
    'el nivel 2 del principal ya entra al candado (producción base baja)');

// C4. Con producción base sobre el umbral, principal/almacén/granero hasta nivel 10.
check((int)freeCropUnlockThreshold(1) === 276, 'el umbral del oficial es 276/h a velocidad 1');
check((int)freeCropUnlockThreshold(3) === 828, 'y escala con SPEED');
foodGuardVillage(6, 8, 0, array(20=>array(10,5), 21=>array(11,9), 23=>array(15,5), 24=>array(19,1)));
$village->pop = popForFreeCrop(-500);
check($village->getBaseCropProduction() >= 276, 'la aldea del caso pasa el umbral (base '.$village->getBaseCropProduction().')');
check(foodGuardCode(20, 10) === CODE_OK, 'el almacén sube al 6 pese al rojo');
check(foodGuardCode(21, 11) === CODE_OK, 'el granero sube al 10 pese al rojo');
check(foodGuardCode(23, 15) === CODE_OK, 'el principal sube al 6 pese al rojo');
check(foodGuardCode(24, 19) === CODE_FOOD, 'pero el cuartel no: no está en la lista');
foodGuardVillage(6, 8, 0, array(21=>array(11,10)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(21, 11) === CODE_FOOD, 'y el granero se frena en el nivel 11');
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$village->pop = popForFreeCrop(-500);
check($village->getBaseCropProduction() < 276, 'con 198/h la aldea no llega al umbral');
check(foodGuardCode(20, 10) === CODE_FOOD, 'así que el almacén sí queda bloqueado');

// C5. Maravilla del mundo: exenta.
foodGuardVillage(6, 5, 0, array(40=>array(40,1)));
$village->pop = popForFreeCrop(-500);
check(foodGuardCode(40, 40) === CODE_OK, 'la maravilla del mundo está exenta del candado');

// ---------------------------------------------------------------------------
section('D. Consumo proyectado de la cola');
// ---------------------------------------------------------------------------
// Dos mejoras encoladas en el mismo campo, sobre un almacén nivel 9: los niveles 10
// y 11 consumen distinto (1 y 2), así que contar dos veces el primero se nota.
foodGuardVillage(6, 5, 0, array(20=>array(10,9)));
$queued = array(foodGuardJob(1,20,10,10), foodGuardJob(2,20,10,11));
$projected = $GLOBALS['bid10'][10]['pop'] + $GLOBALS['bid10'][11]['pop'] + $GLOBALS['bid10'][12]['pop'];
check($GLOBALS['bid10'][10]['pop'] !== $GLOBALS['bid10'][11]['pop'],
    'el caso elegido tiene consumos distintos entre niveles (si no, no probaría nada)');
$village->pop = popForFreeCrop($projected + 1);
check(foodGuardCode(20, 10, $queued) === CODE_OK,
    'con libre para los tres niveles se permite encolar el siguiente');
$village->pop = popForFreeCrop($projected);
check(foodGuardCode(20, 10, $queued) === CODE_FOOD,
    'y con uno menos se bloquea: la cola no se cuenta de menos');

// Un trabajo en otro campo también suma su consumo. Los campos van a nivel 5 a
// propósito: con 198/h la aldea no llega al umbral de 276, así que el almacén no se
// escapa por la excepción y el candado es el que decide.
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$otherField = array(foodGuardJob(1,21,11,1));
$needed = $GLOBALS['bid11'][1]['pop'] + $GLOBALS['bid10'][6]['pop'];
$village->pop = popForFreeCrop($needed + 1);
check(foodGuardCode(20, 10, $otherField) === CODE_OK,
    'el consumo de un trabajo en otro campo también entra en la cuenta');
$village->pop = popForFreeCrop($needed);
check(foodGuardCode(20, 10, $otherField) === CODE_FOOD, 'y si no alcanza, bloquea');

// ---------------------------------------------------------------------------
section('E. Ni el oro ni el héroe abren el candado');
// ---------------------------------------------------------------------------
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$base = $village->getBaseCropProduction();
$withGold = villageGrossProduction($village->resarray, $village->ocounter, array(false,false,false,true), SPEED);
check((int)$withGold['production']['crop'] > (int)$base,
    'el bono de oro sí sube la producción real ('.$base.' -> '.(int)$withGold['production']['crop'].')');
check((int)villageFreeCrop($village->resarray, $village->ocounter, 0, SPEED) === (int)$base,
    'pero el cereal libre lo ignora, como en el oficial');
$oasisFree = villageFreeCrop($village->resarray, array(0,0,0,1), 0, SPEED);
check($oasisFree > $base, 'el oasis, en cambio, sí cuenta ('.$base.' -> '.$oasisFree.')');

// ---------------------------------------------------------------------------
section('F. El constructor maestro pasa por el mismo candado');
// ---------------------------------------------------------------------------
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$village->pop = popForFreeCrop(5);
$buildArrayProperty->setValue($building, array());
check(is_array($building->masterBuildingRequest(20, 10)),
    'con cereal libre el pedido al maestro constructor se acepta');
$village->pop = popForFreeCrop(0);
check($building->masterBuildingRequest(20, 10) === false,
    'sin cereal libre el maestro constructor tampoco puede: era la puerta de atrás');
$village->pop = popForFreeCrop(-500);
check(is_array($building->masterBuildingRequest(3, 4)),
    'pero una plantación se le puede pedir igual');

$dorf1 = file_get_contents(dirname(__DIR__).'/dorf1.php');
$dorf2 = file_get_contents(dirname(__DIR__).'/dorf2.php');
check(strpos($dorf1, 'masterBuildingRequest') !== false && strpos($dorf2, 'masterBuildingRequest') !== false,
    'dorf1 y dorf2 siguen entrando por masterBuildingRequest y no por addBuilding directo');

// ---------------------------------------------------------------------------
section('G. Demolición del molino y la panadería');
// ---------------------------------------------------------------------------
foodGuardVillage(6, 5, 0, array(22=>array(8,1), 23=>array(15,10)));
$village->pop = popForFreeCrop(1);
check($building->demolitionAllowed(23) === true, 'el edificio principal se demuele siempre');
check($building->demolitionAllowed(22) === false,
    'el molino no, si tirarlo deja el cereal libre por debajo de 1');
$village->pop = popForFreeCrop(50);
check($building->demolitionAllowed(22) === true,
    'con libre de sobra el molino sí se puede demoler');

$demolitionTemplate = file_get_contents(dirname(__DIR__).'/Templates/Build/15_1.tpl');
check(strpos($demolitionTemplate, 'demolitionAllowed') !== false,
    'la pantalla de demolición consulta el candado antes de encolar');

// ---------------------------------------------------------------------------
section('H. calculateAvaliable no divide por cero');
// ---------------------------------------------------------------------------
foodGuardVillage(6, 5, 0, array(20=>array(10,5)));
$village->acrop = 0;
$village->awood = $village->aclay = $village->airon = 0;
$errors = array();
set_error_handler(function($no, $str) use (&$errors) { $errors[] = $str; return true; });
$village->netCrop = 0;
$zero = $building->calculateAvaliable(20, 10);
$village->netCrop = -100;
$negative = $building->calculateAvaliable(20, 10);
restore_error_handler();
check(empty($errors), 'sin producción de cereal no hay warning de división por cero ('.implode(' | ', $errors).')');
check($zero === false, 'con producción 0 devuelve false en vez de una fecha inventada');
check($negative === false, 'con producción negativa también');
$village->netCrop = 1000;
$village->awood = $village->aclay = $village->airon = $village->acrop = 1000000;

$upgradeTemplate = file_get_contents(dirname(__DIR__).'/Templates/Build/upgrade.tpl');
check(strpos($upgradeTemplate, '$neededtime === false') !== false,
    'upgrade.tpl contempla que no haya fecha posible');

// ---------------------------------------------------------------------------
section('I. finishAll delega en el fin de obra normal');
// ---------------------------------------------------------------------------
check(strpos($source, '$automation->finishBuildingsNow($village->wid, $jobIds)') !== false,
    'finishAll usa el camino compartido de Automation');
check(strpos($source, 'UPDATE ".TB_PREFIX."fdata set f".$jobs[\'field\']') === false,
    'y ya no escribe fdata por su cuenta');
check(preg_match('/finishAll\(\).*?modifyPop/s', $source) !== 1,
    'ni contabiliza población a mano');

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
check(strpos($automationSource, 'public function finishBuildingsNow($villageId, $jobIds)') !== false,
    'Automation expone el punto de entrada para terminar con oro');
check(preg_match('/finishBuildingsNow.*?\$this->buildComplete\(\$now, false\)/s', $automationSource) === 1,
    'que adelanta el reloj y llama al fin de obra de siempre');
check(strpos($source, "in_array((int)\$jobs['type'], array(25,26,40), true)") !== false,
    'residencia, palacio y maravilla del mundo siguen sin poder terminarse con oro');
check(strpos($source, '$database->modifyGold($session->uid,self::FINISH_ALL_GOLD,0);') !== false
    && Building::FINISH_ALL_GOLD === 2,
    'y se siguen cobrando 2 de oro una sola vez');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Build food guard checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
