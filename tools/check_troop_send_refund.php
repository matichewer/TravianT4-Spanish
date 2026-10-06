<?php
/**
 * El envío normal de tropas devuelve lo que descontó cuando el viaje no se puede crear.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_troop_send_refund.php
 *
 * `Units::sendTroops()` descuenta las tropas primero (`deductUnitsIfAvailable()`) y después
 * crea el ataque y el viaje. Si alguno de los dos falla, tiene que devolverlas, y la pantalla
 * lo dice: "No se pudo crear el movimiento. Las unidades fueron devueltas". No era cierto:
 * la devolución llamaba a `modifyUnit()` con el nombre de la columna (`u11`), y esa función
 * espera el NÚMERO de la unidad y le pone la `u` ella. Armaba `uu11`, que no existe; el
 * UPDATE fallaba, nadie miraba el resultado y el jugador se quedaba sin las tropas y sin el
 * ataque. Con el héroe no pasaba —`hero` es el único nombre que `modifyUnit()` reconoce—,
 * así que de un envío mixto volvía el héroe solo.
 *
 * Ahora devuelve `refundUnits()`, que recibe exactamente lo mismo que recibió
 * `deductUnitsIfAvailable()`: las dos funciones hablan de columnas, así que no hay nada que
 * traducir entre una y otra.
 *
 *   A. refundUnits() deshace lo que hizo deductUnitsIfAvailable(), con sus mismas claves.
 *   B. El envío de verdad: si falla el viaje o el ataque, las tropas vuelven (tres tribus y héroe).
 *   C. Y si no falla, salen: el arnés ejercita el envío real, no un doble.
 *   D. Nadie más le pasa a modifyUnit() un nombre de columna.
 *
 * `sendTroops()` termina siempre en `exit`, así que cada caso de B y C corre en un proceso
 * hijo de este mismo archivo (`--caso=…`) que informa cómo quedó la base al salir. Todo
 * sobre TABLAS TEMPORALES copiadas del esquema real: el mundo de verdad no se toca.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Form.php";
include "Battle.php";
include "GeneratorX.php";
include "Units.php";

// La capa de datos de verdad, con dos fallos que se pueden encender.
class TroopRefundProbeDB extends mysqli_DB {
    public $failMovement = false;
    public $failAttack = false;
    function addMovement($type, $from, $to, $ref, $data, $endtime, $send = 1, $wood = 0, $clay = 0, $iron = 0, $crop = 0, $ref2 = 0) {
        return $this->failMovement ? false : parent::addMovement($type, $from, $to, $ref, $data, $endtime, $send, $wood, $clay, $iron, $crop, $ref2);
    }
    function addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $ctar1, $ctar2, $spy, $sethome = 0) {
        return $this->failAttack ? 0 : parent::addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $ctar1, $ctar2, $spy, $sethome);
    }
}
$database = new TroopRefundProbeDB();
$generator = new GeneratorX();
$form = new Form();

function q($sql) {
    global $database;
    $result = mysqli_query($database->connection, $sql);
    if($result === false) {
        fwrite(STDERR, 'SQL: '.mysqli_error($database->connection).PHP_EOL.substr($sql, 0, 400).PHP_EOL);
        exit(1);
    }
    return $result;
}
function scalar($sql) {
    $line = mysqli_fetch_row(q($sql));
    return $line ? $line[0] : null;
}

$P = TB_PREFIX;
foreach(array('units', 'vdata', 'users', 'wdata', 'odata', 'attacks', 'movement', 'a2b', 'artefacts', 'hero', 'heroinventory') as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
    if((int)scalar("SELECT COUNT(*) FROM {$P}{$table}") !== 0) {
        fwrite(STDERR, "La tabla {$P}{$table} no quedó tapada por su copia temporal; se aborta sin escribir nada.".PHP_EOL);
        exit(1);
    }
}

define('V_HOME', 920001);
define('V_TARGET', 920002);
define('U_SENDER', 9701);
define('U_TARGET', 9702);

// Cada caso: tribu, lo que hay en casa, lo que se manda (posición 1..11) y qué falla.
$CASES = array(
    'romano-falla-viaje' => array('tribe' => 1, 'home' => array('u1' => 100, 'u5' => 10, 'u10' => 3),
        'send' => array(1 => 30, 5 => 5, 10 => 1), 'fail' => 'movement'),
    'teuton-falla-viaje' => array('tribe' => 2, 'home' => array('u11' => 80, 'u15' => 12, 'u20' => 4),
        'send' => array(1 => 25, 5 => 6, 10 => 2), 'fail' => 'movement'),
    'galo-con-heroe-falla-viaje' => array('tribe' => 3, 'home' => array('u21' => 60, 'u24' => 9, 'hero' => 1),
        'send' => array(1 => 20, 4 => 9, 11 => 1), 'fail' => 'movement'),
    'romano-falla-ataque' => array('tribe' => 1, 'home' => array('u1' => 100, 'u2' => 40),
        'send' => array(1 => 100, 2 => 40), 'fail' => 'attack'),
    'romano-sale-bien' => array('tribe' => 1, 'home' => array('u1' => 100, 'u5' => 10, 'u10' => 3),
        'send' => array(1 => 30, 5 => 5, 10 => 1), 'fail' => null),
);

// =====================================================================================
// Proceso hijo: un envío de verdad, de punta a punta, y cómo quedó todo al salir.
// =====================================================================================
$caseName = null;
foreach($argv as $argument) {
    if(strpos($argument, '--caso=') === 0) {
        $caseName = substr($argument, 7);
    }
}
if($caseName !== null) {
    if(!isset($CASES[$caseName])) {
        fwrite(STDERR, "caso desconocido: $caseName".PHP_EOL);
        exit(2);
    }
    $case = $CASES[$caseName];
    $tribe = $case['tribe'];
    q("INSERT INTO {$P}users (id, username, tribe, access) VALUES (".U_SENDER.", 'remitente', $tribe, 2), (".U_TARGET.", 'destino', 1, 2)");
    q("INSERT INTO {$P}vdata (wref, owner, name) VALUES (".V_HOME.", ".U_SENDER.", 'casa'), (".V_TARGET.", ".U_TARGET.", 'destino')");
    q("INSERT INTO {$P}wdata (id, fieldtype, oasistype, x, y, occupied) VALUES (".V_HOME.", 3, 0, 0, 0, 1), (".V_TARGET.", 3, 0, 4, 3, 1)");
    q("INSERT INTO {$P}units (vref, ".implode(', ', array_keys($case['home'])).") VALUES (".V_HOME.", ".implode(', ', $case['home']).")");
    if(!empty($case['home']['hero'])) {
        q("INSERT INTO {$P}hero (uid, wref, home, level, speed, dead, health) VALUES (".U_SENDER.", ".V_HOME.", ".V_HOME.", 1, 7, 0, 100)");
    }
    $database->failMovement = $case['fail'] === 'movement';
    $database->failAttack = $case['fail'] === 'attack';

    // Lo que el motor tiene cargado en memoria en una página de verdad.
    $session = (object)array('uid' => U_SENDER, 'tribe' => $tribe, 'username' => 'remitente');
    $village = (object)array('wid' => V_HOME, 'unitarray' => array('hero' => 0));
    for($unit = 1; $unit <= 50; $unit++) {
        $village->unitarray['u'.$unit] = 0;
    }
    foreach($case['home'] as $column => $amount) {
        $village->unitarray[$column] = $amount;
    }
    $building = new class { function getTypeLevel($type) { return 1; } };

    // La fila que deja la pantalla de confirmación, y el formulario que la confirma.
    $troops = array();
    for($position = 1; $position <= 11; $position++) {
        $troops[$position] = isset($case['send'][$position]) ? (int)$case['send'][$position] : 0;
    }
    $stamp = time();
    $database->addA2b('probe1', $stamp, V_TARGET, $troops[1], $troops[2], $troops[3], $troops[4], $troops[5],
        $troops[6], $troops[7], $troops[8], $troops[9], $troops[10], $troops[11], 4);
    $post = array('c' => '4', 'a' => 533374, 'timestamp_checksum' => 'probe1', 'timestamp' => (string)$stamp, 'del_protect' => 0);

    $before = mysqli_fetch_assoc(q("SELECT * FROM {$P}units WHERE vref = ".V_HOME));
    register_shutdown_function(function() use ($P, $before, $caseName) {
        $after = mysqli_fetch_assoc(q("SELECT * FROM {$P}units WHERE vref = ".V_HOME));
        $changed = array();
        foreach($after as $column => $amount) {
            if((int)$amount !== (int)$before[$column]) {
                $changed[$column] = (int)$amount - (int)$before[$column];
            }
        }
        $attack = mysqli_fetch_assoc(q("SELECT t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11, attack_type FROM {$P}attacks LIMIT 1"));
        echo PHP_EOL.'RESULT:'.json_encode(array(
            'case'     => $caseName,
            'changed'  => $changed,
            'attacks'  => (int)scalar("SELECT COUNT(*) FROM {$P}attacks"),
            'movement' => (int)scalar("SELECT COUNT(*) FROM {$P}movement"),
            'linked'   => (int)scalar("SELECT COUNT(*) FROM {$P}movement m INNER JOIN {$P}attacks a ON a.id = m.ref WHERE m.`from` = ".V_HOME." AND m.`to` = ".V_TARGET." AND m.sort_type = 3"),
            'a2b'      => (int)scalar("SELECT COUNT(*) FROM {$P}a2b"),
            'attack'   => $attack ? array_map('intval', $attack) : null,
            'errors'   => isset($_SESSION['errorarray']) ? array_values($_SESSION['errorarray']) : array(),
        )).PHP_EOL;
    });
    ob_start();
    $units->procUnits($post);
    exit;
}

// =====================================================================================
// Proceso principal.
// =====================================================================================
$failures = array();
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        echo '[FALLA] '.$message.PHP_EOL;
        $failures[] = $message;
    }
    return (bool)$ok;
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}
function runCase($name) {
    $output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg('--caso='.$name).' 2>&1');
    if(!is_string($output) || !preg_match('/^RESULT:(\{.*\})$/m', $output, $m)) {
        return array('raw' => is_string($output) ? trim(substr($output, -600)) : '');
    }
    return json_decode($m[1], true);
}

// =====================================================================================
section('A. refundUnits() deshace lo que hizo deductUnitsIfAvailable()');
// =====================================================================================
check(method_exists($database, 'refundUnits'), 'la capa de datos tiene refundUnits()');
if(method_exists($database, 'refundUnits')) {
    q("INSERT INTO {$P}units (vref, u1, u9, u10, u11, u20, u21, u30, u41, u50, hero) VALUES (".V_HOME.", 100, 9, 10, 110, 20, 210, 30, 41, 50, 1)");
    $unitsRow = function() use ($P) { return mysqli_fetch_assoc(q("SELECT * FROM {$P}units WHERE vref = ".V_HOME)); };
    $initial = $unitsRow();
    // Las mismas claves, para las cinco tribus: primera unidad, décima y héroe.
    $batches = array(
        'romanos'  => array('u1' => 40, 'u9' => 9, 'u10' => 3, 'hero' => 1),
        'teutones' => array('u11' => 110, 'u20' => 20),
        'galos'    => array('u21' => 1, 'u30' => 30),
        'natares'  => array('u41' => 41, 'u50' => 7),
        'con ceros'=> array('u1' => 5, 'u2' => 0, 'hero' => 0),
    );
    foreach($batches as $label => $deductions) {
        $took = $database->deductUnitsIfAvailable(V_HOME, $deductions);
        $afterTake = $unitsRow();
        $gave = $database->refundUnits(V_HOME, $deductions);
        check($took && $afterTake !== $initial && $gave && $unitsRow() === $initial,
            $label.': descontar y devolver con las mismas claves deja la aldea como estaba');
    }
    check($database->refundUnits(V_HOME, array()) === true && $database->refundUnits(V_HOME, array('u1' => 0)) === true
            && $unitsRow() === $initial,
        'sin nada que devolver no falla ni toca nada');
    foreach(array('uu11' => 5, 'u1 = 0, u2' => 5, 'u60' => 5, 'wood' => 5) as $badColumn => $amount) {
        check($database->refundUnits(V_HOME, array('u1' => 1, $badColumn => $amount)) === false && $unitsRow() === $initial,
            'una clave que no es una columna de unidades ('.$badColumn.') se rechaza entera, sin devolver a medias');
    }
    check($database->refundUnits(V_HOME, array('u1' => -50)) === true && $unitsRow() === $initial,
        'una cantidad negativa no descuenta por la puerta de atrás');
    check($database->refundUnits(999999999, array('u1' => 5)) === false,
        'devolverle a una aldea que no existe se informa como fallo');
    q("DELETE FROM {$P}units");
}

// =====================================================================================
section('B. El envío de verdad: si falla, las tropas vuelven');
// =====================================================================================
foreach($CASES as $name => $case) {
    if($case['fail'] === null) {
        continue;
    }
    $result = runCase($name);
    if(!isset($result['changed'])) {
        check(false, $name.': el proceso hijo no informó resultado — '.(isset($result['raw']) ? $result['raw'] : ''));
        continue;
    }
    $what = $case['fail'] === 'movement' ? 'el viaje' : 'el ataque';
    check($result['changed'] === array(),
        $name.': al fallar '.$what.' las tropas quedan como estaban'
            .($result['changed'] === array() ? '' : ' — faltan '.json_encode($result['changed'])));
    check($result['attacks'] === 0 && $result['movement'] === 0,
        $name.': y no queda ni ataque ni viaje a medias ('.$result['attacks'].' / '.$result['movement'].')');
    check(in_array('No se pudo crear el movimiento. Las unidades fueron devueltas.', $result['errors'], true),
        $name.': el jugador ve el aviso de que no salió');
}

// =====================================================================================
section('C. Y si no falla, salen');
// =====================================================================================
$ok = runCase('romano-sale-bien');
if(!isset($ok['changed'])) {
    check(false, 'romano-sale-bien: el proceso hijo no informó resultado — '.(isset($ok['raw']) ? $ok['raw'] : ''));
} else {
    check($ok['changed'] === array('u1' => -30, 'u5' => -5, 'u10' => -1),
        'el envío que sale descuenta 30 legionarios, 5 de caballería y 1 colono ('.json_encode($ok['changed']).')');
    check($ok['attacks'] === 1 && $ok['movement'] === 1 && $ok['linked'] === 1,
        'y crea un ataque con su viaje de casa al destino');
    check($ok['attack'] !== null && $ok['attack']['t1'] === 30 && $ok['attack']['t5'] === 5 && $ok['attack']['t10'] === 1
            && $ok['attack']['attack_type'] === 4,
        'el ataque lleva esas mismas tropas');
    check($ok['a2b'] === 0 && $ok['errors'] === array(), 'consume la fila de confirmación y no deja errores');
}

// =====================================================================================
section('D. Nadie más le pasa a modifyUnit() un nombre de columna');
// =====================================================================================
$unitsSource = file_get_contents($root.'/GameEngine/Units.php');
$farmSource = file_get_contents($root.'/GameEngine/FarmList.php');
check(preg_match('/if\(!\$movementAdded\) \{.*?\$database->refundUnits\(\$village->wid,\s*\$unitDeductions\);/s', $unitsSource) === 1,
    'sendTroops() devuelve con refundUnits() lo mismo que le pasó a deductUnitsIfAvailable()');
check(strpos($farmSource, '$database->refundUnits($origin, $deductions)') !== false && strpos($farmSource, 'modifyUnit(') === false,
    'la Lista de granjas devuelve igual, sin traducir columnas a mano');
// modifyUnit() espera un número (o 'hero'). Un argumento que ya trae la `u` arma `uu…`.
$suspects = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file) {
    $path = str_replace($root.'/', '', $file->getPathname());
    if(!preg_match('/\.(php|tpl)$/', $path) || preg_match('#^(tools|openspec|install|\.git)/#', $path)) {
        continue;
    }
    foreach(file($file->getPathname()) as $index => $line) {
        if(!preg_match('/->modifyUnit\(\s*([^,]+),\s*([^,]+),/', $line, $m)) {
            continue;
        }
        $unitArgument = trim($m[2]);
        // Una variable llamada como una columna, o un literal que empieza con u + dígito.
        if(preg_match('/^\$(column|unitColumn|col)\b/', $unitArgument) || preg_match('/^[\'"]u\d/', $unitArgument)) {
            $suspects[] = $path.':'.($index + 1);
        }
    }
}
check(empty($suspects),
    'ningún llamador de modifyUnit() le pasa una columna ya armada'
        .(empty($suspects) ? '' : ' — revisar '.implode(', ', $suspects)));

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Devolución de tropas del envío: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
