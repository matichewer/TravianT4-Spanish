<?php
/**
 * Lo que el mantenimiento del mundo no puede volver a hacer en cada request.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_world_sweep_cost.php
 *
 * `Automation` corre entero al cargar cualquier página, incluso el login. Tres cosas ahí
 * costaban lo mismo hubiera o no algo que hacer, y escalan con el mundo, no con el jugador:
 *
 *   - `updateStore()` mandaba un UPDATE por CADA aldea del mundo, natars incluidos, para
 *     reescribir un almacén que casi nunca cambió. Además de la ida y vuelta, cada uno
 *     toma el candado de escritura de `vdata`, la tabla que más se lee del juego.
 *   - Los tres barridos de oasis (producir, podar, regenerar animales) recorrían los 4.092
 *     oasis del mapa. El UPDATE de producción solo tardaba diez veces lo que tarda leerlos
 *     aunque no cambiara ninguna fila.
 *   - "Qué oasis tiene esta aldea" (`conqured = N`) no tenía índice y se pregunta varias
 *     veces por página.
 *   - La reposición diaria de animales entraba entera en una sola pasada: como cada oasis
 *     queda con la hora en que se lo repuso, los ~4.000 vencían juntos para siempre y una
 *     vez por día un request cualquiera pagaba todos.
 *
 * Ninguno de los tres cambios puede alterar un solo número del juego, y eso es lo que se
 * comprueba acá además del costo:
 *
 *   A. updateStore() deja EXACTAMENTE las mismas filas que la versión que escribía todo.
 *   B. ...y escribe sólo las aldeas que había que corregir; con el mundo en orden, ninguna.
 *   C. sweepDue() deja pasar una vez por intervalo, y los tres barridos de oasis cuelgan de él.
 *   D. Limitar el barrido no cambia la producción de un oasis ni lo que se saquea.
 *   E. Los oasis de una aldea salen por índice.
 *   F. El índice está en la base viva, en migrations.sql y en el instalador, igual.
 *   G. Los animales de los oasis se reponen de a tandas, no los 4.000 en un request.
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real: el mundo de verdad no se toca.
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

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Hero.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
include "Ranking.php";
include "Logging.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database;

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
function rowsOf($sql) {
    $rows = array();
    $result = q($sql);
    while($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}
function sessionCounter($like) {
    $total = 0;
    $result = q("SHOW SESSION STATUS LIKE '".$like."'");
    while($row = mysqli_fetch_row($result)) {
        $total += (int)$row[1];
    }
    return $total;
}
// Cuántos UPDATE mandó $fn y cuántas filas leyó el motor de almacenamiento mientras tanto.
function measure($fn) {
    $updates = sessionCounter('Com_update');
    $reads = sessionCounter('Handler_read%');
    $value = $fn();
    return array(
        'reads'   => sessionCounter('Handler_read%') - $reads,
        'updates' => sessionCounter('Com_update') - $updates,
        'value'   => $value,
    );
}

$EXPECTED_INDEX = array('table' => 'odata', 'name' => 'annexed_by_village', 'columns' => array('conqured'));

$P = TB_PREFIX;

// La base viva se mira ANTES de taparla con las temporales (sección F).
$liveIndex = array();
$result = q("SHOW INDEX FROM {$P}odata");
while($row = mysqli_fetch_assoc($result)) {
    if($row['Key_name'] === $EXPECTED_INDEX['name']) {
        $liveIndex[(int)$row['Seq_in_index']] = $row['Column_name'];
    }
}
ksort($liveIndex);
$liveIndex = array_values($liveIndex);

foreach(array('vdata', 'fdata', 'odata', 'units', 'wdata') as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
    if((int)scalar("SELECT COUNT(*) FROM {$P}{$table}") !== 0) {
        fwrite(STDERR, "La tabla {$P}{$table} no quedó tapada por su copia temporal; se aborta sin escribir nada.".PHP_EOL);
        exit(1);
    }
}

$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
$call = function($method) use ($automation) {
    $reflection = new ReflectionMethod('Automation', $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($automation, array_slice(func_get_args(), 1));
};
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');

// =====================================================================================
// Un mundo de 60 aldeas con almacenes al azar (reproducible) y de todo un poco en vdata.
// =====================================================================================
define('VILLAGES', 60);
define('V_BASE', 970000);
mt_srand(20261006);

// La capacidad que le corresponde a una aldea según sus edificios. Es la cuenta del
// barrido, escrita de nuevo acá para tener contra qué comparar.
$capacityOf = function($slots) {
    $store = $crop = 0;
    foreach($slots as $slot) {
        list($type, $level) = $slot;
        if($type === 10 || $type === 38) { $store += $GLOBALS['bid'.$type][$level]['attri'] * STORAGE_MULTIPLIER; }
        if($type === 11 || $type === 39) { $crop += $GLOBALS['bid'.$type][$level]['attri'] * STORAGE_MULTIPLIER; }
    }
    return array($store == 0 ? 800 * STORAGE_MULTIPLIER : $store, $crop == 0 ? 800 * STORAGE_MULTIPLIER : $crop);
};

$needsWrite = array();   // wref => por qué
$kinds = array();
for($i = 0; $i < VILLAGES; $i++) {
    $wref = V_BASE + $i;
    // Edificios: de ninguno a varios almacenes y graneros, algún "gran" cada tanto.
    $slots = array();
    $free = range(19, 39);
    shuffle($free);
    $buildings = $i % 7 === 0 ? 0 : mt_rand(1, 5);
    for($b = 0; $b < $buildings; $b++) {
        $roll = mt_rand(1, 20);
        $type = $roll <= 9 ? 10 : ($roll <= 18 ? 11 : ($roll === 19 ? 38 : 39));
        $slots[array_pop($free)] = array($type, mt_rand(1, 20));
    }
    // Y relleno que no es almacenamiento, para que el barrido tenga qué ignorar.
    $slots[array_pop($free)] = array(15, mt_rand(1, 20));
    $slots[array_pop($free)] = array(17, mt_rand(1, 20));
    list($store, $crop) = $capacityOf($slots);

    $columns = array('vref');
    $values = array($wref);
    foreach($slots as $slot => $building) {
        $columns[] = 'f'.$slot; $values[] = $building[1];
        $columns[] = 'f'.$slot.'t'; $values[] = $building[0];
    }
    q("INSERT INTO {$P}fdata (".implode(',', $columns).") VALUES (".implode(',', $values).")");

    // vdata: la mayoría en orden; el resto, cada forma de estar mal.
    $kind = $i % 10;
    $maxstore = $store; $maxcrop = $crop;
    $wood = round($store * mt_rand(0, 100) / 100, 2);
    $clay = round($store * mt_rand(0, 100) / 100, 2);
    $iron = round($store * mt_rand(0, 100) / 100, 2);
    $grain = round($crop * mt_rand(0, 100) / 100, 2);
    $why = null;
    if($kind === 3) { $maxstore = $store + 400; $why = 'maxstore de más'; }
    if($kind === 4) { $maxcrop = max(0, $crop - 300); $grain = min($grain, $maxcrop); $why = 'maxcrop de menos'; }
    if($kind === 5) { $wood = $store + 123.45; $why = 'madera por encima del tope'; }
    if($kind === 6) { $grain = $crop + 0.01; $why = 'cereal un centavo por encima del tope'; }
    if($kind === 7) { $grain = -250.5; }                       // deuda de cereal: NO se toca
    if($kind === 8) { $wood = $store; $grain = $crop; }        // justo en el tope: NO se toca
    if($kind === 9 && $i % 20 === 9) { $maxstore = 0; $maxcrop = 0; $why = 'capacidades en cero'; }
    $kinds[$wref] = $kind;
    if($why !== null) {
        $needsWrite[$wref] = $why;
    }
    q("INSERT INTO {$P}vdata (wref, owner, name, maxstore, maxcrop, wood, clay, iron, crop)"
        ." VALUES ($wref, 990900, 'aldea $i', $maxstore, $maxcrop, $wood, $clay, $iron, $grain)");
}
// Una fila de fdata sin aldea y una aldea sin fdata: ninguna de las dos puede romper nada.
q("INSERT INTO {$P}fdata (vref, f19, f19t) VALUES (".(V_BASE + 5000).", 10, 10)");
q("INSERT INTO {$P}vdata (wref, owner, name, maxstore, maxcrop, wood, clay, iron, crop) VALUES (".(V_BASE + 6000).", 990900, 'sin fdata', 4000, 4000, 9000, 1, 1, 1)");

$snapshot = function($table) {
    return rowsOf("SELECT wref, maxstore, maxcrop, wood, clay, iron, crop FROM $table ORDER BY wref");
};

// La versión anterior, tal cual estaba: un UPDATE por aldea, haga falta o no. Corre sobre
// una copia aparte para dejar el resultado que la nueva tiene que igualar.
q("CREATE TEMPORARY TABLE zz_legacy_vdata AS SELECT * FROM {$P}vdata");
$legacyUpdateStore = function() use ($P) {
    global $bid10, $bid38, $bid11, $bid39;
    $result = q('SELECT * FROM `'.$P.'fdata`');
    while($row = mysqli_fetch_assoc($result)) {
        $ress = $crop = 0;
        for($i = 19; $i < 40; ++$i) {
            if($row['f'.$i.'t'] == 10) { $ress += $bid10[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER; }
            if($row['f'.$i.'t'] == 38) { $ress += $bid38[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER; }
            if($row['f'.$i.'t'] == 11) { $crop += $bid11[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER; }
            if($row['f'.$i.'t'] == 39) { $crop += $bid39[$row['f'.$i]]['attri'] * STORAGE_MULTIPLIER; }
        }
        if($ress == 0) { $ress = 800 * STORAGE_MULTIPLIER; }
        if($crop == 0) { $crop = 800 * STORAGE_MULTIPLIER; }
        q('UPDATE `zz_legacy_vdata` SET `maxstore` = '.$ress.', `maxcrop` = '.$crop
            .', `wood` = LEAST(`wood`,'.$ress.'), `clay` = LEAST(`clay`,'.$ress.')'
            .', `iron` = LEAST(`iron`,'.$ress.'), `crop` = LEAST(`crop`,'.$crop.')'
            .' WHERE `wref` = '.$row['vref']);
    }
};

// =====================================================================================
section('A. updateStore() deja las mismas filas que la versión que escribía todo');
// =====================================================================================
$before = $snapshot("{$P}vdata");
$legacy = measure($legacyUpdateStore);
$wanted = $snapshot('zz_legacy_vdata');
$changedByLegacy = 0;
foreach($wanted as $index => $row) {
    $changedByLegacy += $row === $before[$index] ? 0 : 1;
}
check($changedByLegacy === count($needsWrite),
    'la versión vieja cambia de verdad '.count($needsWrite).' aldeas de '.VILLAGES.' (cambió '.$changedByLegacy.'): los datos de prueba tienen qué corregir');

$first = measure(function() use ($call) { $call('updateStore'); });
$got = $snapshot("{$P}vdata");
$different = array();
foreach($wanted as $index => $row) {
    if($row !== $got[$index]) {
        $different[] = $row['wref'].' ('.(isset($needsWrite[$row['wref']]) ? $needsWrite[$row['wref']] : 'no debía cambiar').')';
    }
}
check(count($got) === count($wanted) && empty($different),
    'las '.count($wanted).' filas de vdata quedan idénticas a las de la versión vieja'
        .(empty($different) ? '' : ' — difieren: '.implode(', ', array_slice($different, 0, 6))));

// Los casos que no son error y por eso no se pueden "corregir".
$debt = rowsOf("SELECT COUNT(*) n, MAX(crop) worst FROM {$P}vdata WHERE wref BETWEEN ".V_BASE." AND ".(V_BASE + VILLAGES)." AND crop < 0");
check((int)$debt[0]['n'] === VILLAGES / 10 && (float)$debt[0]['worst'] === -250.5,
    'la deuda de cereal (granero en negativo) sigue intacta en las '.(VILLAGES / 10).' aldeas que la tenían');
check((float)scalar("SELECT wood FROM {$P}vdata WHERE wref = ".(V_BASE + 6000)) === 9000.0,
    'una aldea sin fila de fdata no se toca, igual que antes');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE wref = ".(V_BASE + 5000)) === 0,
    'y una fila de fdata sin aldea no inventa ninguna');

// =====================================================================================
section('B. ...y escribe sólo donde había algo que corregir');
// =====================================================================================
check($legacy['updates'] === VILLAGES + 1,
    'la versión vieja mandaba un UPDATE por fila de fdata: '.$legacy['updates'].' para '.VILLAGES.' aldeas');
check($first['updates'] === count($needsWrite),
    'la nueva manda '.count($needsWrite).', uno por aldea a corregir (mandó '.$first['updates'].')');
$again = measure(function() use ($call) { $call('updateStore'); });
check($again['updates'] === 0,
    'con el mundo ya en orden, una segunda pasada no escribe nada (mandó '.$again['updates'].' UPDATE)');
check($snapshot("{$P}vdata") === $wanted, 'ni cambia nada');

// Cada forma de estar mal, por separado: corregir una no puede depender de las otras.
// Una capacidad mal anotada vuelve a su valor; un exceso se recorta AL TOPE, que no es
// lo que la aldea tenía antes de recibir de más.
$target = V_BASE + 1;
$targetIndex = null;
foreach($wanted as $index => $row) {
    if((int)$row['wref'] === $target) {
        $targetIndex = $index;
    }
}
$oneWrong = array(
    'un almacén demolido deja maxstore de más' => array("maxstore = maxstore + 1", null),
    'una mejora sin registrar deja maxcrop de menos' => array("maxcrop = maxcrop - 1", null),
    'una entrega que pasa el tope del almacén' => array("iron = maxstore + 1", array('iron', 'maxstore')),
    'una entrega que pasa el tope del granero' => array("crop = maxcrop + 1", array('crop', 'maxcrop')),
);
foreach($oneWrong as $label => $case) {
    list($assignment, $clamped) = $case;
    q("UPDATE {$P}vdata SET $assignment WHERE wref = $target");
    $fix = measure(function() use ($call) { $call('updateStore'); });
    $expected = $wanted;
    if($clamped !== null) {
        $expected[$targetIndex][$clamped[0]] = number_format((float)$wanted[$targetIndex][$clamped[1]], 2, '.', '');
    }
    check($fix['updates'] === 1 && $snapshot("{$P}vdata") === $expected,
        $label.': se corrige con un solo UPDATE ('.$fix['updates'].') y no toca a nadie más');
    if($clamped !== null) {
        q("UPDATE {$P}vdata SET ".$clamped[0]." = ".$wanted[$targetIndex][$clamped[0]]." WHERE wref = $target");
    }
}
check($snapshot("{$P}vdata") === $wanted && $targetIndex !== null, 'y el mundo de prueba queda como estaba');

// =====================================================================================
section('C. sweepDue() deja pasar una vez por intervalo');
// =====================================================================================
// Las marcas son rutas relativas al directorio de trabajo, así que se prueba en uno
// descartable para no pisar las del servidor que está corriendo.
$sandbox = sys_get_temp_dir().'/sweepdue_'.getmypid();
mkdir($sandbox.'/GameEngine/Prevention', 0777, true);
chdir($sandbox);
$marker = $sandbox.'/GameEngine/Prevention/prueba.txt';
check($call('sweepDue', 'prueba') === true, 'la primera vez le toca');
check(file_exists($marker), 'y deja la marca puesta');
check($call('sweepDue', 'prueba') === false, 'enseguida después, no');
check($call('sweepDue', 'prueba') === false, 'ni a la tercera');
touch($marker, time() - 40);
clearstatcache();
check($call('sweepDue', 'prueba') === false, 'a los 40 segundos todavía no');
touch($marker, time() - 51);
clearstatcache();
check($call('sweepDue', 'prueba') === true, 'pasados los 50 segundos, sí');
clearstatcache();
check(time() - filemtime($marker) <= 2, 'y la marca vuelve a quedar con la hora de ahora');
check($call('sweepDue', 'prueba') === false, 'así que el request siguiente ya no entra');
touch($marker, time() - 20);
clearstatcache();
check($call('sweepDue', 'prueba', 10) === true && $call('sweepDue', 'otro') === true,
    'el intervalo es por parámetro y cada barrido tiene su marca');
chdir($root);
array_map('unlink', glob($sandbox.'/GameEngine/Prevention/*'));
rmdir($sandbox.'/GameEngine/Prevention');
rmdir($sandbox.'/GameEngine');
rmdir($sandbox);

// Y los tres barridos de oasis cuelgan de él en el constructor, sin ninguna llamada suelta.
check(preg_match('/public function __construct\(\$marketOnly = false\) \{(.*?)\n    \}\n/s', $automationSource, $constructor) === 1,
    'se encuentra el constructor de Automation');
$constructor = isset($constructor[1]) ? $constructor[1] : '';
check(substr_count($constructor, '$oasisSweepDue = $this->sweepDue(\'oasis\');') === 1,
    'el constructor pregunta una sola vez si toca el barrido de oasis');
foreach(array('oasisResourcesProduce', 'pruneOResource', 'regenerateOasisTroops') as $sweep) {
    $callsInConstructor = substr_count($constructor, '$this->'.$sweep.'();');
    $gated = preg_match_all('/if\(\$oasisSweepDue\) \{\s*\$this->'.$sweep.'\(\);\s*\}/', $constructor);
    check($callsInConstructor === 1 && $gated === 1,
        $sweep.'() corre sólo cuando toca ('.$callsInConstructor.' llamada(s), '.$gated.' dentro del if)');
}

// =====================================================================================
section('D. Limitar el barrido no cambia la producción ni el saqueo');
// =====================================================================================
// 8 por hora y por recurso, por la velocidad del mundo: la misma cuenta del motor.
$perSecond = 8 * (float)SPEED / 3600;
$now = time();
$oasisColumns = "wref, type, conqured, wood, clay, iron, crop, maxstore, maxcrop, lastupdated, lastupdated2, loyalty, owner, name";
$addOasis = function($wref, $stock, $max, $updated, $annexedTo = 0) use ($P, $oasisColumns, $now) {
    q("INSERT INTO {$P}odata ($oasisColumns) VALUES ($wref, 1, $annexedTo, $stock, $stock, $stock, $stock, $max, $max, $updated, $now, 100, 3, 'oasis')");
};
// A y B llevan dos horas vaciados. Sobre A "ya pasó" un barrido hace una hora; sobre B no
// pasó ninguno. Si la producción dependiera de cada cuánto se barre, terminarían distinto.
$hour = round($perSecond * 3600, 2);
$addOasis(960001, $hour, 2000, $now - 3600);
$addOasis(960002, 0, 2000, $now - 7200);
$addOasis(960003, 0, 1000, $now - 30 * 86400);   // un mes sin tocar: se llena y para ahí
$addOasis(960004, 1000, 1000, $now - 9000);      // lleno: no hay nada que producir
$addOasis(960005, 0, 2000, $now - 3600);         // el que van a saquear
$addOasis(960006, 0, 2000, $now - 3600);         // su vecino, que nadie mira

// Antes de saquear, el motor pone al día ESE oasis (updateORes) sin esperar al barrido.
$call('updateORes', 960005);
$looted = (float)scalar("SELECT wood FROM {$P}odata WHERE wref = 960005");
check(abs($looted - $hour) <= $perSecond * 5 + 0.02,
    'el oasis que se va a saquear tiene su hora de producción ('.$looted.' de '.$hour.') aunque el barrido no haya corrido');
check((float)scalar("SELECT wood FROM {$P}odata WHERE wref = 960006") === 0.0,
    'y updateORes() no toca a los demás');
$lootSettles = strpos($automationSource, '$this->updateORes($data[\'to\']);');
$lootReads = strpos($automationSource, '$database->getOasisField($data[\'to\'], \'clay\');');
check($lootSettles !== false && $lootReads !== false && $lootSettles < $lootReads,
    'el saqueo pone al día el oasis antes de leer lo que hay');
// Si alguien más empezara a leer el stock de un oasis, vería hasta un minuto de atraso.
$stockReaders = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file) {
    $path = str_replace($root.'/', '', $file->getPathname());
    if(!preg_match('/\.(php|tpl)$/', $path) || preg_match('#^(tools|openspec|install|\.git)/#', $path)) {
        continue;
    }
    if(preg_match('/getOasisField\([^)]*[\'"](wood|clay|iron|crop)[\'"]\s*\)/', file_get_contents($file->getPathname()))) {
        $stockReaders[] = $path;
    }
}
check($stockReaders === array('GameEngine/Automation.php'),
    'nadie más lee el stock de un oasis ('.implode(', ', $stockReaders).'): si aparece otro lector, tiene que llamar a updateORes() antes');

// El barrido completo.
$call('oasisResourcesProduce');
$stockA = (float)scalar("SELECT wood FROM {$P}odata WHERE wref = 960001");
$stockB = (float)scalar("SELECT wood FROM {$P}odata WHERE wref = 960002");
check(abs($stockA - $stockB) <= 0.02,
    'un oasis barrido hace una hora y otro que nadie barrió en dos terminan con lo mismo ('.$stockA.' y '.$stockB.')');
check(abs($stockB - 2 * $hour) <= $perSecond * 5 + 0.02, 'que son las dos horas de producción ('.(2 * $hour).')');
check((float)scalar("SELECT crop FROM {$P}odata WHERE wref = 960003") === 1000.0,
    'un oasis olvidado un mes se llena hasta su tope y no más');
check((int)scalar("SELECT lastupdated FROM {$P}odata WHERE wref = 960004") === $now - 9000,
    'un oasis lleno ni se toca');
$settled = rowsOf("SELECT wref, wood, clay, iron, crop FROM {$P}odata ORDER BY wref");
$call('oasisResourcesProduce');
$resettled = rowsOf("SELECT wref, wood, clay, iron, crop FROM {$P}odata ORDER BY wref");
$drift = 0.0;
foreach($settled as $index => $row) {
    $drift = max($drift, abs((float)$row['wood'] - (float)$resettled[$index]['wood']));
}
check($drift <= $perSecond * 5 + 0.02, 'barrer dos veces seguidas no regala nada (diferencia máxima '.$drift.')');

// =====================================================================================
section('E. Los oasis de una aldea salen por índice');
// =====================================================================================
define('OASES', 4000);
define('V_HOLDER', 970001);
q("DELETE FROM {$P}odata");
$rows = array();
for($i = 1; $i <= OASES; $i++) {
    $annexedTo = $i <= 3 ? V_HOLDER : ($i <= 12 ? V_BASE + 100 + $i : 0);
    $loyalty = $i === 2 ? 80 : 100;
    $rows[] = "(".(950000 + $i).", ".(1 + $i % 12).", $annexedTo, 1000, 1000, 1000, 1000, 1000, 1000, $now, $now, $loyalty, 3, 'oasis')";
}
foreach(array_chunk($rows, 1000) as $chunk) {
    q("INSERT INTO {$P}odata ($oasisColumns) VALUES ".implode(',', $chunk));
}
q("ANALYZE TABLE {$P}odata");
$budget = (int)(OASES / 20);

$oases = measure(function() use ($database) { return $database->getOasis(V_HOLDER); });
check(count($oases['value']) === 3, 'getOasis() devuelve los 3 oasis de la aldea');
check($oases['reads'] <= $budget,
    'getOasis() no recorre los '.number_format(OASES, 0, ',', '.').' oasis del mapa: '.$oases['reads'].' lecturas (tope '.$budget.')');
$none = measure(function() use ($database) { return $database->getOasis(V_BASE + 59); });
check(count($none['value']) === 0 && $none['reads'] <= $budget,
    'ni para una aldea que no tiene ninguno ('.$none['reads'].' lecturas)');

// La regeneración de lealtad corre en cada request y sólo le importan los anexados.
check(preg_match('/\$q = ("SELECT \* FROM "\.TB_PREFIX\."odata WHERE loyalty < 100 AND conqured <> 0");/', $automationSource, $loyaltyQuery) === 1,
    'se reconoce la consulta de lealtad de oasis de loyaltyRegeneration()');
if(isset($loyaltyQuery[1])) {
    $sql = substr(str_replace('".TB_PREFIX."', TB_PREFIX, $loyaltyQuery[1]), 1, -1);
    $loyalty = measure(function() use ($sql) { return rowsOf($sql); });
    check(count($loyalty['value']) === 1, 'y encuentra el único oasis anexado con la lealtad baja');
    check($loyalty['reads'] <= $budget, 'sin recorrer el mapa: '.$loyalty['reads'].' lecturas (tope '.$budget.')');
}

// La reposición de animales es el caso inverso: casi todos los oasis están libres, así
// que el índice no filtra nada y por él MariaDB los lee igual a todos, pero de a uno —
// quince veces más lento en MyISAM que recorrer la tabla. La consulta lo esquiva con
// `conqured + 0 = 0`; acá se comprueba sobre la que está escrita en el fuente.
check(preg_match('/\$q = "(SELECT wref FROM )"\.TB_PREFIX\."(odata WHERE [^"]*lastupdated2 < )\$due"\s*\."( ORDER BY lastupdated2 ASC, wref ASC LIMIT )"\.self::OASIS_REGEN_BATCH;/', $automationSource, $regenQuery) === 1,
    'se reconoce la consulta de regenerateOasisTroops()');
if(!empty($regenQuery)) {
    $regenSql = $regenQuery[1].TB_PREFIX.$regenQuery[2].($now - 86400).$regenQuery[3].Automation::OASIS_REGEN_BATCH;
    $regenPlan = rowsOf("EXPLAIN ".$regenSql);
    check($regenPlan[0]['key'] === null && $regenPlan[0]['type'] === 'ALL',
        'la tanda de animales se busca recorriendo la tabla, no por el índice de anexados (plan: '
            .$regenPlan[0]['type'].', índice '.var_export($regenPlan[0]['key'], true).')');
}

// =====================================================================================
section('F. El índice existe en los tres lugares, con las mismas columnas');
// =====================================================================================
$name = $EXPECTED_INDEX['name'];
$columns = $EXPECTED_INDEX['columns'];
$normalize = function($list) {
    return array_values(array_filter(array_map(function($column) {
        return trim($column, "` \t\r\n");
    }, explode(',', $list)), 'strlen'));
};
check($liveIndex === $columns,
    'la base viva tiene odata.'.$name.' ('.implode(', ', $columns).')'.(empty($liveIndex) ? ' — falta: aplicar tools/migrations.sql' : ''));
$migrations = file_get_contents($root.'/tools/migrations.sql');
$inMigration = preg_match('/ALTER\s+TABLE\s+s1_odata\s+ADD\s+INDEX\s+IF\s+NOT\s+EXISTS\s+'.$name.'\s*\(([^)]*)\)/i', $migrations, $m) ? $normalize($m[1]) : null;
check($inMigration === $columns, 'tools/migrations.sql lo agrega con esas columnas'.($inMigration === null ? ' — no está' : ''));
$installer = file_get_contents($root.'/install/data/sql.sql');
$inInstaller = null;
if(preg_match('/CREATE TABLE IF NOT EXISTS `%PREFIX%odata` \((.*?)\) ENGINE=/s', $installer, $block)
    && preg_match('/KEY `'.$name.'` \(([^)]*)\)/', $block[1], $m)) {
    $inInstaller = $normalize($m[1]);
}
check($inInstaller === $columns, 'install/data/sql.sql lo crea igual en un mundo nuevo'.($inInstaller === null ? ' — no está' : ''));

// =====================================================================================
section('G. Los animales de los oasis se reponen de a tandas');
// =====================================================================================
$batch = Automation::OASIS_REGEN_BATCH;
define('DUE_OASES', 150);
q("DELETE FROM {$P}odata");
$now = time();
$regenRows = array();
$mapRows = array();
$unitRows = array();
$addRegenOasis = function($wref, $annexedTo, $lastRegen) use (&$regenRows, &$mapRows, &$unitRows, $now) {
    // Tipos 4 a 9: su cadena empieza por una especie que siempre repone algo.
    $regenRows[] = "($wref, 4, $annexedTo, 1000, 1000, 1000, 1000, 1000, 1000, $now, $lastRegen, 100, 3, 'oasis')";
    $mapRows[] = "($wref, 0, ".(4 + $wref % 6).", ".($wref % 200 - 100).", ".(intdiv($wref, 200) % 200 - 100).", 0)";
    $unitRows[] = "($wref)";
};
// 150 vencidos, cada uno un segundo más atrasado que el anterior: el orden es conocido.
for($i = 1; $i <= DUE_OASES; $i++) {
    $addRegenOasis(940000 + $i, 0, $now - 90000 - (DUE_OASES - $i));
}
$oldest = range(940001, 940000 + $batch);
for($i = 1; $i <= 10; $i++) { $addRegenOasis(941000 + $i, 0, $now - 3600); }               // al día
for($i = 1; $i <= 5; $i++)  { $addRegenOasis(942000 + $i, V_HOLDER, $now - 200000); }      // anexados
$addRegenOasis(943001, 0, $now + 86400);                                                   // reloj adelantado
q("INSERT INTO {$P}odata ($oasisColumns) VALUES ".implode(',', $regenRows));
q("INSERT INTO {$P}wdata (id, fieldtype, oasistype, x, y, occupied) VALUES ".implode(',', $mapRows));
q("INSERT INTO {$P}units (vref) VALUES ".implode(',', $unitRows));

$animals = "u31+u32+u33+u34+u35+u36+u37+u38+u39+u40";
$regenerated = function() use ($P, $now) {
    $ids = array();
    foreach(rowsOf("SELECT wref FROM {$P}odata WHERE wref BETWEEN 940001 AND 940999 AND lastupdated2 >= ".($now - 5)." ORDER BY wref") as $row) {
        $ids[] = (int)$row['wref'];
    }
    return $ids;
};
$herd = function($where) use ($P, $animals) {
    $out = array();
    foreach(rowsOf("SELECT vref, $animals AS total FROM {$P}units WHERE $where ORDER BY vref") as $row) {
        $out[(int)$row['vref']] = (int)$row['total'];
    }
    return $out;
};

// La forma vieja de escribir el plazo, con un solo oasis fechado en el futuro.
$legacyDue = @mysqli_query($database->connection,
    "SELECT * FROM {$P}odata where conqured = 0 and $now - lastupdated2 > 86400");
check($legacyDue === false,
    'restar sobre la columna UNSIGNED fallaba entera con un oasis de reloj adelantado: por eso el plazo se compara del otro lado');

$firstPass = measure(function() use ($call) { $call('regenerateOasisTroops'); });
check($regenerated() === $oldest,
    'una pasada repone '.$batch.' oasis, y son los '.$batch.' más atrasados (repuso '.count($regenerated()).')');
$afterFirst = $herd("vref BETWEEN 940001 AND 940999");
$fed = 0;
$untouched = 0;
foreach($afterFirst as $wref => $total) {
    if(in_array($wref, $oldest, true)) { $fed += $total > 0 ? 1 : 0; } else { $untouched += $total === 0 ? 1 : 0; }
}
check($fed === $batch, 'a los '.$batch.' les llegaron animales ('.$fed.')');
check($untouched === DUE_OASES - $batch, 'y a los otros '.(DUE_OASES - $batch).' vencidos, todavía no ('.$untouched.')');
check($firstPass['updates'] <= 2 * $batch,
    'la pasada manda a lo sumo dos UPDATE por oasis de la tanda ('.$firstPass['updates'].' de '.(2 * $batch).'), no dos por oasis vencido ('.(2 * DUE_OASES).')');

// Las pasadas siguientes terminan el resto, y nadie se repone dos veces.
$call('regenerateOasisTroops');
$afterSecond = $herd("vref BETWEEN 940001 AND 940999");
$again = 0;
foreach($oldest as $wref) {
    $again += $afterSecond[$wref] === $afterFirst[$wref] ? 0 : 1;
}
check($again === 0, 'los de la primera tanda no vuelven a reponerse en la segunda');
check(count($regenerated()) === 2 * $batch, 'la segunda pasada repone otros '.$batch.' ('.count($regenerated()).' en total)');
$call('regenerateOasisTroops');
check(count($regenerated()) === DUE_OASES, 'y a la tercera están los '.DUE_OASES.' ('.count($regenerated()).')');
check(min($herd("vref BETWEEN 940001 AND 940999")) > 0, 'todos con animales');
$idle = measure(function() use ($call) { $call('regenerateOasisTroops'); });
check($idle['updates'] === 0, 'sin nada vencido, la pasada no escribe ('.$idle['updates'].' UPDATE)');

// Lo que no vence: al día, anexados y el del reloj adelantado.
check(array_sum($herd("vref BETWEEN 941001 AND 941999")) === 0
        && (int)scalar("SELECT COUNT(*) FROM {$P}odata WHERE wref BETWEEN 941001 AND 941999 AND lastupdated2 = ".($now - 3600)) === 10,
    'un oasis repuesto hace una hora no se toca');
check(array_sum($herd("vref BETWEEN 942001 AND 942999")) === 0
        && (int)scalar("SELECT COUNT(*) FROM {$P}odata WHERE wref BETWEEN 942001 AND 942999 AND lastupdated2 = ".($now - 200000)) === 5,
    'un oasis anexado no repone animales por más atrasado que esté');
check(array_sum($herd("vref = 943001")) === 0
        && (int)scalar("SELECT lastupdated2 FROM {$P}odata WHERE wref = 943001") === $now + 86400,
    'y el del reloj adelantado ni se toca ni rompe la pasada de los demás');

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Costo del mantenimiento del mundo: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
