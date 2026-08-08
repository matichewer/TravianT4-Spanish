<?php
/**
 * Auditoría de la cancelación de trabajos en la cola de construcción
 * (mysqli_DB::removeBuilding y Building::removeBuilding).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_build_queue_cancel.php
 *
 * Cubre:
 *   A. Un solo trabajo: se borra y el solar se libera si no había edificio.
 *   B. Dos trabajos en el mismo campo: el que queda baja de nivel si iba más arriba.
 *   C. El trabajo en obra no reinicia su reloj porque se cancele otro de la cola.
 *   D. El que estaba esperando arranca y los siguientes se encadenan detrás.
 *   E. Las dos colas de los romanos (campos de recursos y centro de la aldea) no se
 *      pisan entre sí.
 *   F. El reembolso corresponde al nivel cancelado, no siempre al siguiente al actual.
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

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Building.php';

/**
 * Doble de la base: guarda bdata y fdata en memoria y responde a las consultas
 * concretas que hace removeBuilding, interpretándolas de verdad (no las da por buenas).
 */
class QueueDatabaseStub {
    public $bdata = array();     // id => trabajo
    public $fields = array();    // wref => fdata
    public $connection = null;
    public $resources = array(0,0,0,0);

    public function getFieldLevel($wid, $field) {
        return isset($this->fields[$wid]['f'.$field]) ? (int)$this->fields[$wid]['f'.$field] : 0;
    }
    public function modifyResource($wid, $wood, $clay, $iron, $crop, $mode) {
        $this->resources = array($wood, $clay, $iron, $crop);
    }
    public function mysqli_fetch_all($result) { return $result; }
}

/**
 * Las consultas que emite la capa de datos, resueltas sobre el almacén en memoria.
 * No se pueden redefinir las funciones mysqli_* (la extensión está cargada), así que
 * el código real se carga redirigiendo `mysqli_query()` y compañía a estos métodos:
 * la lógica que se audita es la del motor, sin tocar.
 */
trait QueueSqlTrait {
    public function q($query) {
        global $stub;
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if(preg_match('/^DELETE FROM '.TB_PREFIX.'bdata where id = (\d+)$/i', $query, $m)) {
            unset($stub->bdata[(int)$m[1]]);
            return true;
        }
        if(preg_match('/^UPDATE '.TB_PREFIX.'bdata SET level = level - 1 WHERE wid = (\d+) AND field = (\d+) AND level > (\d+)$/i', $query, $m)) {
            foreach($stub->bdata as $id => $job) {
                if((int)$job['wid'] === (int)$m[1] && (int)$job['field'] === (int)$m[2] && (int)$job['level'] > (int)$m[3]) {
                    $stub->bdata[$id]['level'] = (int)$job['level'] - 1;
                }
            }
            return true;
        }
        if(preg_match('/^SELECT id FROM '.TB_PREFIX.'bdata WHERE wid = (\d+) AND field = (\d+) LIMIT 1$/i', $query, $m)) {
            $rows = array();
            foreach($stub->bdata as $job) {
                if((int)$job['wid'] === (int)$m[1] && (int)$job['field'] === (int)$m[2]) { $rows[] = $job; }
            }
            return $rows;
        }
        if(preg_match('/^SELECT f(\d+) FROM '.TB_PREFIX.'fdata WHERE vref = (\d+)$/i', $query, $m)) {
            return array(array($stub->getFieldLevel((int)$m[2], (int)$m[1])));
        }
        if(preg_match('/^UPDATE '.TB_PREFIX.'fdata SET f(\d+)t = 0 WHERE vref = (\d+)$/i', $query, $m)) {
            $stub->fields[(int)$m[2]]['f'.$m[1].'t'] = 0;
            return true;
        }
        if(preg_match('/^SELECT \* FROM '.TB_PREFIX.'bdata WHERE wid = (\d+) AND master = 0( AND field <= 18| AND field >= 19)? ORDER BY timestamp ASC, id ASC$/i', $query, $m)) {
            $zone = isset($m[2]) ? trim($m[2]) : '';
            $rows = array();
            foreach($stub->bdata as $job) {
                if((int)$job['wid'] !== (int)$m[1] || (int)$job['master'] !== 0) { continue; }
                if($zone === 'AND field <= 18' && (int)$job['field'] > 18) { continue; }
                if($zone === 'AND field >= 19' && (int)$job['field'] < 19) { continue; }
                $rows[] = $job;
            }
            usort($rows, function($a, $b) {
                if((int)$a['timestamp'] === (int)$b['timestamp']) { return (int)$a['id'] - (int)$b['id']; }
                return (int)$a['timestamp'] - (int)$b['timestamp'];
            });
            return $rows;
        }
        if(preg_match('/^UPDATE '.TB_PREFIX.'bdata SET loopcon = (\d+), timestamp = (\d+) WHERE id = (\d+)$/i', $query, $m)) {
            $stub->bdata[(int)$m[3]]['loopcon'] = (int)$m[1];
            $stub->bdata[(int)$m[3]]['timestamp'] = (int)$m[2];
            return true;
        }
        $GLOBALS['unexpectedQueries'][] = $query;
        return true;
    }
    public function numRows($result) { return is_array($result) ? count($result) : 0; }
    public function fetchRow($result) { return is_array($result) && isset($result[0]) ? $result[0] : false; }
}

$GLOBALS['unexpectedQueries'] = array();

// La clase de datos completa depende de constantes del servidor; para esta auditoría
// alcanza con extraer los métodos que participan de la cancelación.
$source = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$start = strpos($source, '        	function removeBuilding($d) {');
$end = strpos($source, 'function addDemolition(', $start);
if($start === false || $end === false) {
    fwrite(STDERR, "No se pudo aislar removeBuilding() en db_MYSQLi.php\n");
    exit(1);
}
$methods = substr($source, $start, $end - $start);
$methods = substr($methods, 0, strrpos($methods, '}') + 1);
// Mismo código del motor, con el transporte redirigido al doble.
$methods = preg_replace('/mysqli_query\(\s*\$this->connection\s*,/', '$this->q(', $methods);
$methods = str_replace(
    array('mysqli_num_rows(', 'mysqli_fetch_row('),
    array('$this->numRows(', '$this->fetchRow('),
    $methods
);
eval('class QueueDatabase extends QueueDatabaseStub { use QueueSqlTrait; '.$methods.' }');

$stub = new QueueDatabase();
$database = $stub;   // Building::removeBuilding() lo busca con este nombre
$session = (object)array('tribe'=>1, 'plus'=>0);
$village = (object)array('wid'=>1, 'capital'=>0, 'resarray'=>array());

$buildingReflection = new ReflectionClass('Building');
$building = $buildingReflection->newInstanceWithoutConstructor();
$buildArrayProperty = $buildingReflection->getProperty('buildArray');
$buildArrayProperty->setAccessible(true);

/** Prepara aldea, campos y cola. $jobs: id => array(field, type, level, loopcon, timestamp, master) */
function queueScenario($fieldLevels, $jobs) {
    global $stub, $village, $building, $buildArrayProperty;
    $fields = array();
    for($field = 1; $field <= 40; $field++) { $fields['f'.$field] = 0; $fields['f'.$field.'t'] = 0; }
    foreach($fieldLevels as $field => $pair) {
        $fields['f'.$field.'t'] = $pair[0];
        $fields['f'.$field] = $pair[1];
    }
    $stub->fields[1] = $fields;
    $village->resarray = $fields;
    $stub->bdata = array();
    foreach($jobs as $id => $job) {
        $stub->bdata[$id] = array(
            'id'=>$id, 'wid'=>1, 'field'=>$job[0], 'type'=>$job[1], 'level'=>$job[2],
            'loopcon'=>$job[3], 'timestamp'=>$job[4], 'master'=>isset($job[5]) ? $job[5] : 0
        );
    }
    $buildArrayProperty->setValue($building, array_values($stub->bdata));
}

$now = time();

// ---------------------------------------------------------------------------
section('A. Un único trabajo');
// ---------------------------------------------------------------------------
// Construcción nueva de un almacén en el campo 20: el solar quedó reservado con
// nivel 0, así que al cancelar tiene que liberarse.
queueScenario(array(20=>array(10,0)), array(1=>array(20,10,1,0,$now+600)));
$stub->removeBuilding(1);
check(empty($stub->bdata), 'el trabajo cancelado desaparece de la cola');
check((int)$stub->fields[1]['f20t'] === 0, 'el solar vuelve a quedar libre');

// Una mejora sobre un edificio existente no libera el solar.
queueScenario(array(20=>array(10,5)), array(1=>array(20,10,6,0,$now+600)));
$stub->removeBuilding(1);
check((int)$stub->fields[1]['f20t'] === 10, 'cancelar una mejora no borra el edificio');

// ---------------------------------------------------------------------------
section('B. Dos trabajos en el mismo campo');
// ---------------------------------------------------------------------------
queueScenario(array(20=>array(10,5)), array(
    1=>array(20,10,6,0,$now+600),
    2=>array(20,10,7,1,$now+1200)
));
$stub->removeBuilding(1);
check(isset($stub->bdata[2]) && (int)$stub->bdata[2]['level'] === 6,
    'al cancelar el nivel 6 el que iba al 7 pasa a ser el 6');
check((int)$stub->bdata[2]['loopcon'] === 0, 'y deja de estar en espera');
check((int)$stub->bdata[2]['timestamp'] > $now, 'con su reloj arrancando ahora');

// Cancelar el segundo no toca el nivel del primero.
queueScenario(array(20=>array(10,5)), array(
    1=>array(20,10,6,0,$now+600),
    2=>array(20,10,7,1,$now+1200)
));
$stub->removeBuilding(2);
check((int)$stub->bdata[1]['level'] === 6, 'cancelar el segundo deja intacto el nivel del primero');
check((int)$stub->bdata[1]['timestamp'] === $now+600, 'y no le reinicia el reloj');
check((int)$stub->bdata[1]['loopcon'] === 0, 'el primero sigue en obra');

// ---------------------------------------------------------------------------
section('C/D. Cola de tres trabajos');
// ---------------------------------------------------------------------------
queueScenario(array(1=>array(1,5), 2=>array(1,5), 3=>array(1,5)), array(
    1=>array(1,1,6,0,$now+100),
    2=>array(2,1,6,1,$now+300),
    3=>array(3,1,6,1,$now+700)
));
$stub->removeBuilding(2);
check((int)$stub->bdata[1]['timestamp'] === $now+100, 'el trabajo en obra conserva su reloj');
check((int)$stub->bdata[1]['loopcon'] === 0, 'y sigue en obra');
check((int)$stub->bdata[3]['loopcon'] === 1, 'el tercero sigue esperando');
// El fin se recalcula desde las tablas (bdata sólo guarda el instante de fin, no la
// duración), así que tiene que quedar exactamente detrás del trabajo en obra.
$expectedDuration = $building->resourceRequired(3, 1, 6 - 5)['time'];
check((int)$stub->bdata[3]['timestamp'] === (int)$stub->bdata[1]['timestamp'] + (int)$expectedDuration,
    'y su fin queda justo detrás del que está en obra, con la duración de las tablas');

// Cancelar el que está en obra hace arrancar al siguiente desde ahora.
queueScenario(array(1=>array(1,5), 2=>array(1,5)), array(
    1=>array(1,1,6,0,$now+100),
    2=>array(2,1,6,1,$now+300)
));
$stub->removeBuilding(1);
check((int)$stub->bdata[2]['loopcon'] === 0, 'el que esperaba pasa a estar en obra');
check((int)$stub->bdata[2]['timestamp'] >= $now, 'con su duración contada desde ahora');

// ---------------------------------------------------------------------------
section('E. Las dos colas de los romanos no se pisan');
// ---------------------------------------------------------------------------
queueScenario(array(1=>array(1,5), 20=>array(10,5), 21=>array(11,5)), array(
    1=>array(1,1,6,0,$now+100),
    2=>array(20,10,6,0,$now+200),
    3=>array(21,11,6,1,$now+900)
));
$stub->removeBuilding(2);
check((int)$stub->bdata[1]['timestamp'] === $now+100,
    'cancelar en el centro de la aldea no toca la cola de los campos de recursos');
check((int)$stub->bdata[3]['loopcon'] === 0, 'y el que esperaba en el centro arranca');

// ---------------------------------------------------------------------------
section('F. Reembolso del nivel cancelado');
// ---------------------------------------------------------------------------
$buildingRemove = $buildingReflection->getMethod('removeBuilding');
$buildingRemove->setAccessible(true);
// Building::removeBuilding() redirige al terminar; en CLI eso sólo emite un aviso.
$previousReporting = error_reporting(E_ALL & ~E_WARNING);
$logging = new class { public function addBuildLog() {} };
queueScenario(array(20=>array(10,5)), array(
    1=>array(20,10,6,0,$now+600),
    2=>array(20,10,7,1,$now+1200)
));
$stub->resources = array(0,0,0,0);
$buildingRemove->invoke($building, 2);   // cancela el nivel 7
check($stub->resources[0] == $GLOBALS['bid10'][7]['wood'],
    'cancelar el nivel 7 devuelve lo que cuesta el nivel 7 ('.$GLOBALS['bid10'][7]['wood'].' de madera)');

queueScenario(array(20=>array(10,5)), array(1=>array(20,10,6,0,$now+600)));
$stub->resources = array(0,0,0,0);
$buildingRemove->invoke($building, 1);
check($stub->resources[0] == $GLOBALS['bid10'][6]['wood'],
    'y cancelar el nivel 6 devuelve lo del nivel 6 ('.$GLOBALS['bid10'][6]['wood'].' de madera)');

error_reporting($previousReporting);

// ---------------------------------------------------------------------------
section('G. Sin consultas sorpresa');
// ---------------------------------------------------------------------------
check(empty($GLOBALS['unexpectedQueries']),
    'la cancelación no emite consultas fuera de las auditadas: '.implode(' | ', array_slice($GLOBALS['unexpectedQueries'],0,2)));

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
check(strpos($source, 'SameBuildCount') === false,
    'no vuelve la cadena de condiciones que comparaba un campo contra un booleano');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Build queue cancel checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
