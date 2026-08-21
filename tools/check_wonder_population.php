<?php
/**
 * Auditoría del campo 99: los habitantes y los puntos de cultura del Palacio de la
 * Maravilla.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_wonder_population.php
 *
 * Por qué existe. El Palacio de la Maravilla no ocupa un campo del 19 al 38 como el
 * resto: vive en `fdata.f99`. El fin de obra normal sí le suma los habitantes
 * —`Automation::buildComplete` llama a `modifyPop` con el campo que trae el trabajo,
 * 99 incluido— pero los tres recuentos autoritativos barrían de 1 a 40, así que no
 * dejaban de contarlo: se lo **borraban**. Bastaba una demolición o un catapultazo en
 * una Aldea de la Maravilla para que perdiera de golpe toda la población de la
 * Maravilla, y con ella su consumo de cereal y sus puntos de cultura.
 *
 * Cubre:
 *   A. villagePopulationSlots() es 1..40 más el 99, y es la única lista.
 *   B. recountPop() y recountCP() conservan lo que aporta la Maravilla.
 *   C. natarVillagePopulation() también la cuenta.
 *   D. Nadie vuelve a escribir un bucle de población de 1 a 40 por su cuenta.
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

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
define('SPEED', 1);
define('ALLOW_BURST', false);
define('STORAGE_BASE', 800);
define('STORAGE_MULTIPLIER', 1);
define('TRAPPER_CAPACITY', 1);
define('CRANNY_CAPACITY', 1);

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';

// Las escrituras de recountPop/recountCP van por el shim mysql_*: se capturan acá.
$GLOBALS['writes'] = array();
function mysql_query($sql) { $GLOBALS['writes'][] = $sql; return true; }
function mysql_fetch_assoc($result) { return false; }
function mysql_error() { return ''; }

require dirname(__DIR__).'/GameEngine/Automation.php';

/** Sólo lo que tocan los recuentos. */
class WonderDatabaseStub {
    public $fields = array();
    public function getResourceLevel($vid) { return $this->fields; }
    public function getVillageField($vid, $field) { return $field === 'owner' ? 7 : 0; }
    public function syncClimberPopulation($uid) { return true; }
    public function addCP($vid, $cp) { return true; }
    public function query($q) { $GLOBALS['writes'][] = $q; return true; }
}

$database = new WonderDatabaseStub();

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();

/** Aldea con los campos que se le pasen (campo => array(tipo, nivel)). */
function wonderFields($buildings) {
    $fields = array();
    foreach(villagePopulationSlots() as $slot) {
        $fields['f'.$slot] = 0;
        $fields['f'.$slot.'t'] = 0;
    }
    foreach($buildings as $slot => $pair) {
        $fields['f'.$slot.'t'] = $pair[0];
        $fields['f'.$slot] = $pair[1];
    }
    return $fields;
}

/** Corre un recuento y devuelve el número que dejó escrito en vdata. */
function recountedValue($method, $column) {
    global $automation, $database;
    $GLOBALS['writes'] = array();
    $automation->{$method}(1);
    foreach($GLOBALS['writes'] as $sql) {
        if(preg_match('/set '.$column.' = (-?\d+)/i', $sql, $match)) {
            return (int)$match[1];
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
section('A. La lista de campos que aportan población');
// ---------------------------------------------------------------------------
$slots = villagePopulationSlots();
check(in_array(99, $slots, true), 'villagePopulationSlots() incluye el campo 99 de la Maravilla');
check(count(array_diff(range(1,40), $slots)) === 0, 'y sigue incluyendo los campos 1 a 40');
check(count($slots) === 41, 'y nada más (son 41 campos, no '.count($slots).')');

// ---------------------------------------------------------------------------
section('B. Los recuentos conservan lo que aporta la Maravilla');
// ---------------------------------------------------------------------------
$wonderLevel = 10;
$wonderPop = 0;
$wonderCp = 0;
for($level = 0; $level <= $wonderLevel; $level++) {
    if(isset($GLOBALS['bid40'][$level]['pop'])) { $wonderPop += (int)$GLOBALS['bid40'][$level]['pop']; }
    if(isset($GLOBALS['bid40'][$level]['cp'])) { $wonderCp += (int)$GLOBALS['bid40'][$level]['cp']; }
}
check($wonderPop > 0, 'una Maravilla de nivel '.$wonderLevel.' aporta habitantes ('.$wonderPop.'), si no el test no probaría nada');

// Misma aldea, con y sin Maravilla: la diferencia tiene que ser exactamente la suya.
$base = array(21 => array(15, 5), 22 => array(27, 10));
$database->fields = wonderFields($base);
$without = recountedValue('recountPop', 'pop');
$database->fields = wonderFields($base + array(99 => array(40, $wonderLevel)));
$with = recountedValue('recountPop', 'pop');
check($without !== null && $with !== null, 'recountPop escribe la población');
check($with === $without + $wonderPop,
    'recountPop suma los '.$wonderPop.' habitantes de la Maravilla (dio '.$with.', esperaba '.($without + $wonderPop).')');

$database->fields = wonderFields($base);
$cpWithout = recountedValue('recountCP', 'cp');
$database->fields = wonderFields($base + array(99 => array(40, $wonderLevel)));
$cpWith = recountedValue('recountCP', 'cp');
check($cpWith === $cpWithout + $wonderCp,
    'recountCP suma los '.$wonderCp.' puntos de cultura de la Maravilla (dio '.$cpWith.')');

// El caso que rompía: una aldea que ya tenía la Maravilla contada pasa por un
// recuento (una demolición, un catapultazo) y no puede perderla.
check($with > $without, 'un recuento sobre una Aldea de la Maravilla ya no le borra la población');

// ---------------------------------------------------------------------------
section('C. natarVillagePopulation cuenta igual');
// ---------------------------------------------------------------------------
require_once dirname(__DIR__).'/GameEngine/NatarVillage.php';
$natarWithout = natarVillagePopulation(wonderFields($base));
$natarWith = natarVillagePopulation(wonderFields($base + array(99 => array(40, $wonderLevel))));
check($natarWith === $natarWithout + $wonderPop,
    'natarVillagePopulation() también cuenta la Maravilla (dio '.$natarWith.')');
check($natarWithout === $without,
    'y coincide con recountPop en la misma aldea ('.$natarWithout.' vs '.$without.'), así que la reparación de población no pelea con la provisión natar');

// ---------------------------------------------------------------------------
section('D. Nadie se escribe su propio bucle de población');
// ---------------------------------------------------------------------------
$sources = array(
    'GameEngine/Automation.php',
    'GameEngine/NatarVillage.php',
    'tools/fix_village_pop.php'
);
foreach($sources as $source) {
    $code = file_get_contents(dirname(__DIR__).'/'.$source);
    check(strpos($code, 'villagePopulationSlots()') !== false,
        $source.' usa la lista compartida de campos');
    check(preg_match('/for\s*\(\s*\$\w+\s*=\s*1;\s*\$\w+\s*<=\s*40;.*?\n.*?(buildingPOP|buildingCP|popForBuilding)/s', $code) !== 1,
        $source.' ya no recorre 1..40 para contar población');
}

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Wonder population checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
