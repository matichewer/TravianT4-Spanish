<?php
/**
 * Auditoría del bloqueo por escasez de alimentos y del final de obra con oro
 * (Building::canBuild caso 4 y Building::finishAll).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_build_food_guard.php
 *
 * Cubre:
 *   A. No se encola nada que deje la producción de cereal en negativo.
 *   B. Las plantaciones de cereal se pueden mejorar igual: son la salida del problema.
 *   C. El consumo proyectado cuenta el nivel al que apunta cada trabajo encolado,
 *      no siempre el actual + 1.
 *   D. finishAll delega el fin de obra en Automation, así que población, cultura,
 *      capacidad de almacén y ranking salen por el mismo camino que el fin normal.
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
require dirname(__DIR__).'/GameEngine/Building.php';

/** Capa de datos mínima para canBuild(). */
class FoodGuardDatabase {
    public $jobs = array();          // trabajos de la aldea
    public $demolition = array();
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
}

/** Aldea con producción de cereal fija y los campos que se le indiquen. */
class FoodGuardVillage {
    public $wid = 1;
    public $capital = 0;
    public $resarray = array();
    public $maxstore = 1000000;
    public $maxcrop = 1000000;
    public $awood = 1000000;
    public $aclay = 1000000;
    public $airon = 1000000;
    public $acrop = 1000000;
    public $cropProduction = 0;
    public function getProd($type) { return $type === 'crop' ? $this->cropProduction : 1000; }
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
 * Prepara la aldea y devuelve el código de canBuild() para el campo indicado.
 * Códigos: 4 = escasez de alimentos, 8 = se puede construir.
 */
function foodGuard($cropProduction, $fields, $jobs = array(), $field = 20) {
    global $database, $village, $building, $buildArrayProperty, $allocatedProperty, $maxConcurrentProperty, $innerProperty;
    $resarray = array();
    for($slot = 1; $slot <= 40; $slot++) { $resarray['f'.$slot] = 0; $resarray['f'.$slot.'t'] = 0; }
    foreach($fields as $slot => $pair) {
        $resarray['f'.$slot.'t'] = $pair[0];
        $resarray['f'.$slot] = $pair[1];
    }
    $village->resarray = $resarray;
    $village->cropProduction = $cropProduction;
    $database->jobs = $jobs;
    $buildArrayProperty->setValue($building, $jobs);
    $allocatedProperty->setValue($building, 0);
    $maxConcurrentProperty->setValue($building, 5);
    $innerProperty->setValue($building, 0);
    return $building->canBuild($field, $resarray['f'.$field.'t']);
}

function foodGuardJob($id, $field, $type, $level) {
    return array('id'=>$id, 'wid'=>1, 'field'=>$field, 'type'=>$type, 'level'=>$level,
                 'loopcon'=>0, 'timestamp'=>time()+600, 'master'=>0);
}

// ---------------------------------------------------------------------------
section('A. No se encola algo que deje el cereal en negativo');
// ---------------------------------------------------------------------------
// Almacén nivel 5 -> 6 consume bid10[6]['pop'] = 1.
$storagePop = $GLOBALS['bid10'][6]['pop'];
check(foodGuard($storagePop, array(20=>array(10,5))) === 8,
    'con la producción justa para el consumo nuevo, se puede construir');
check(foodGuard($storagePop - 1, array(20=>array(10,5))) === 4,
    'si el balance quedaría en -1, se bloquea por escasez de alimentos');
check(foodGuard(0, array(20=>array(10,5))) === 4,
    'con producción 0 tampoco se puede sumar consumo');
check(foodGuard(-5, array(20=>array(10,5))) === 4,
    'una aldea ya en negativo no puede seguir construyendo');

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
check(strpos($source, '<= -68') === false, 'ya no queda el margen de -68 de cereal');

// ---------------------------------------------------------------------------
section('B. Las plantaciones de cereal son la salida');
// ---------------------------------------------------------------------------
check(foodGuard(-500, array(13=>array(4,5)), array(), 13) === 8,
    'una plantación de cereal se puede mejorar aunque la aldea esté en rojo');

// ---------------------------------------------------------------------------
section('C. Consumo proyectado de la cola');
// ---------------------------------------------------------------------------
// Dos mejoras encoladas en el mismo campo, sobre un almacén nivel 9: los niveles 10
// y 11 consumen distinto (1 y 2), así que contar dos veces el primero se nota.
$queued = array(foodGuardJob(1,20,10,10), foodGuardJob(2,20,10,11));
$projected = $GLOBALS['bid10'][10]['pop'] + $GLOBALS['bid10'][11]['pop'] + $GLOBALS['bid10'][12]['pop'];
check($GLOBALS['bid10'][10]['pop'] !== $GLOBALS['bid10'][11]['pop'],
    'el caso elegido tiene consumos distintos entre niveles (si no, no probaría nada)');
check(foodGuard($projected, array(20=>array(10,9)), $queued) === 8,
    'con producción para los tres niveles se permite encolar el siguiente');
check(foodGuard($projected - 1, array(20=>array(10,9)), $queued) === 4,
    'y con uno menos se bloquea: la cola ya no se cuenta de menos');

// Un trabajo en otro campo también suma su consumo.
$otherField = array(foodGuardJob(1,21,11,1));
check(foodGuard($GLOBALS['bid11'][1]['pop'] + $GLOBALS['bid10'][6]['pop'], array(20=>array(10,5)), $otherField) === 8,
    'el consumo de un trabajo en otro campo también entra en la cuenta');
check(foodGuard($GLOBALS['bid11'][1]['pop'] + $GLOBALS['bid10'][6]['pop'] - 1, array(20=>array(10,5)), $otherField) === 4,
    'y si no alcanza, bloquea');

// ---------------------------------------------------------------------------
section('D. finishAll delega en el fin de obra normal');
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
check(strpos($source, '$database->modifyGold($session->uid,2,0);') !== false,
    'y se siguen cobrando 2 de oro una sola vez');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Build food guard checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
