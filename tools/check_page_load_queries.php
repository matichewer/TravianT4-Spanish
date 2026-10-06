<?php
/**
 * Lo que una carga de página no puede volver a hacer: leer tablas enteras.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_page_load_queries.php
 *
 * El mundo vivo tardaba ~2 segundos por página y la causa no era ninguna pantalla en
 * particular: eran dos tablas que sólo crecen y que el motor leía enteras en CADA request.
 *
 *   - `ndata` (informes). `Message` se construye en Session.php, o sea en todas las páginas,
 *     y su constructor se traía TODOS los informes del jugador, dos veces, para terminar
 *     respondiendo un sí/no ("¿hay alguno sin leer?"). Con 27.641 informes eran ~47 MB por
 *     request. Las dos listas que armaba además no las leía nadie: berichte.php pagina con
 *     su propio SQL.
 *   - `movement` (viajes de tropas y mercaderes). Los ya procesados no se borran nunca
 *     —61.356 filas—, el único índice empezaba por `to`, y casi todo lo que se pregunta
 *     filtra por `proc = 0` y `sort_type`: unas 19 lecturas completas de la tabla por
 *     página, 33 ms cada una en la Raspberry.
 *
 * Este checker no mira cómo está escrito el código sino lo que la base HACE: cuenta las
 * filas que el motor lee (`Handler_read_*`) y las que la base manda (`Rows_sent`) mientras
 * corren las funciones de verdad, sobre tablas del tamaño que rompe.
 *
 *   A. El medidor mide: un recorrido completo se ve, y el instrumento no se cuenta solo.
 *   B. Construir Message no trae informes, ni al filtrar por pestaña.
 *   C. `nunread` responde lo mismo que la cuenta vieja, caso por caso y contra ella.
 *   D. Nadie puede volver a "cargar todos los informes": esos métodos ya no existen.
 *   E. movement: lo que las páginas piden a la capa de datos no recorre la tabla.
 *   F. movement: las consultas de los barridos de Automation, sacadas del fuente, tampoco.
 *   G. Ninguna lectura de movement en el repo queda fuera de un índice.
 *   H. Los índices están en la base viva, en migrations.sql y en el instalador, iguales.
 *   I. La Lista de granjas encuentra el "Último saqueo" de cada objetivo sin leer los demás.
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real, igual que
 * check_account_deletion.php: el mundo de verdad no se toca. Por eso mismo, si la base no
 * tiene los índices las secciones E y F fallan: es la migración sin aplicar.
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
require_once $root.'/GameEngine/Message.php';
require_once $root.'/GameEngine/MapData.php';

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

// Los índices que este checker defiende, con sus columnas EN ORDEN: un índice con las
// mismas columnas en otro orden tiene el mismo nombre y no sirve para nada de esto.
$EXPECTED_INDEXES = array(
    'movement' => array('pending_by_type' => array('proc', 'sort_type', 'endtime')),
    'ndata'    => array(
        'unread_by_player' => array('uid', 'viewed'),
        'last_report_by_target' => array('uid', 'toWref', 'time'),
    ),
);

$P = TB_PREFIX;

// La base viva se mira ANTES de taparla con las temporales (sección H).
$liveIndexes = array();
foreach(array_keys($EXPECTED_INDEXES) as $table) {
    $liveIndexes[$table] = array();
    $result = q("SHOW INDEX FROM {$P}{$table}");
    while($row = mysqli_fetch_assoc($result)) {
        $liveIndexes[$table][$row['Key_name']][(int)$row['Seq_in_index']] = $row['Column_name'];
    }
    foreach($liveIndexes[$table] as $name => $columns) {
        ksort($columns);
        $liveIndexes[$table][$name] = array_values($columns);
    }
}

// =====================================================================================
// Tablas temporales con el mismo nombre que las reales: en esta conexión las tapan.
// `odata` va vacía a propósito, para que lo que se cuente sea movement y no los oasis.
// =====================================================================================
foreach(array('ndata', 'mdata', 'movement', 'attacks', 'send', 'odata') as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
    if((int)scalar("SELECT COUNT(*) FROM {$P}{$table}") !== 0) {
        fwrite(STDERR, "La tabla {$P}{$table} no quedó tapada por su copia temporal; se aborta sin escribir nada.".PHP_EOL);
        exit(1);
    }
}

function insertRows($table, $columns, $rows) {
    global $P;
    foreach(array_chunk($rows, 1000) as $chunk) {
        $values = array();
        foreach($chunk as $row) {
            $values[] = '('.implode(',', $row).')';
        }
        q("INSERT INTO {$P}{$table} (".$columns.") VALUES ".implode(',', $values));
    }
}

// -------------------------------------------------------------------------------------
// El medidor. Handler_read_* cuenta cada fila que el motor de almacenamiento lee, por
// recorrido o por índice; Rows_sent, las que la base le manda al cliente. Son contadores
// de la sesión, así que no los ensucia el resto del servidor.
// -------------------------------------------------------------------------------------
function sessionCounter($like) {
    $total = 0;
    $result = q("SHOW SESSION STATUS LIKE '".$like."'");
    while($row = mysqli_fetch_row($result)) {
        $total += (int)$row[1];
    }
    return $total;
}
$meterOverhead = array('reads' => 0, 'sent' => 0);
function measure($fn) {
    global $meterOverhead;
    $reads = sessionCounter('Handler_read%');
    $sent = sessionCounter('Rows_sent');
    $value = $fn();
    $sentAfter = sessionCounter('Rows_sent');
    $readsAfter = sessionCounter('Handler_read%');
    return array(
        'reads' => $readsAfter - $reads - $meterOverhead['reads'],
        'sent'  => $sentAfter - $sent - $meterOverhead['sent'],
        'value' => $value,
    );
}
function thousands($n) {
    return number_format($n, 0, ',', '.');
}

// =====================================================================================
// Datos. Los ids son altos a propósito: no coinciden con ninguna aldea ni jugador real,
// así que los JOIN contra vdata (que no se tapa, sólo se lee) no encuentran nada.
// =====================================================================================
define('MOVES_DONE', 20000);   // viajes ya procesados: el "peso muerto" de la tabla
define('V_HOME', 990001);      // la aldea que se mira
define('V_ENEMY', 990002);
define('TILE_TARGET', 880001); // una casilla cualquiera a la que se ataca
define('U_OWNER', 990500);
define('REPORTS', 3000);       // informes del jugador cargado
define('FARM_TARGETS', 100);   // objetivos de granjeo entre los que se reparten
define('U_HEAVY', 990600);     // el jugador con miles de informes, todos leídos
define('U_OTHER', 990601);

$now = time();
$moveColumns = 'moveid,sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop';

// 20.000 viajes viejos repartidos entre 90 aldeas y todos los tipos, más o menos como
// en el mundo vivo: sobre todo ataques y regresos, algo de mercaderes, pocos colonos.
$moves = array();
$attacks = array();
$sends = array();
for($i = 1; $i <= MOVES_DONE; $i++) {
    $bucket = $i % 100;
    if($bucket < 45) { $type = 3; }
    elseif($bucket < 90) { $type = 4; }
    elseif($bucket < 95) { $type = 0; }
    elseif($bucket < 97) { $type = 2; }
    elseif($bucket < 98) { $type = 6; }
    elseif($bucket < 99) { $type = 5; }
    else { $type = 9; }
    $village = 990001 + ($i % 90);
    $other = 1 + (($i * 7919) % 40000);
    $inbound = in_array($type, array(2, 4, 6), true);
    $moves[] = array($i, $type, $inbound ? $other : $village, $inbound ? $village : $other,
        $i, 0, "''", $now - 86400 - $i, 1, 1, 0, 0, 0, 0);
    $attacks[] = array($i, $village, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 4, 0, 0, 0, 0);
    $sends[] = array($i, 750, 750, 750, 750, 4);
}

// Los pocos que importan: los que siguen en camino. $pending lleva la cuenta de lo que
// cada función tiene que devolver, para que "leyó pocas filas" nunca signifique "no
// encontró nada".
$nextId = MOVES_DONE;
$addMove = function($type, $from, $to, $attackType, $endtime, $data = '') use (&$moves, &$attacks, &$sends, &$nextId) {
    $nextId++;
    $moves[] = array($nextId, $type, $from, $to, $nextId, 0, "'".$data."'", $endtime, 0, 1, 0, 0, 0, 0);
    $attacks[] = array($nextId, $from, 25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, (int)$attackType, 0, 0, 0, 0);
    $sends[] = array($nextId, 500, 500, 500, 500, 3);
    return $nextId;
};
$future = $now + 3600;
$due = $now - 30;
// Desde V_HOME: 3 ataques, 1 espionaje, 1 refuerzo; 2 envíos de mercaderes; colonos; aventura.
$attackMoveId = $addMove(3, V_HOME, TILE_TARGET, 3, $future);
$addMove(3, V_HOME, TILE_TARGET + 1, 4, $future);
$addMove(3, V_HOME, TILE_TARGET + 2, 4, $future);
$addMove(3, V_HOME, TILE_TARGET + 3, 1, $future);
$addMove(3, V_HOME, V_ENEMY, 2, $future);
$addMove(0, V_HOME, V_ENEMY, 0, $future);
$addMove(0, V_HOME, V_ENEMY, 0, $future);
$addMove(5, V_HOME, TILE_TARGET + 9, 0, $future, (string)U_OWNER);
$addMove(9, V_HOME, TILE_TARGET + 8, 0, $future);
// Hacia V_HOME: 2 ataques, 2 regresos, mercaderes que vuelven, un botín.
$addMove(3, V_ENEMY, V_HOME, 3, $future);
$addMove(3, V_ENEMY, V_HOME, 4, $future);
$addMove(4, TILE_TARGET, V_HOME, 4, $future);
$addMove(4, TILE_TARGET + 1, V_HOME, 4, $future);
$addMove(2, V_ENEMY, V_HOME, 0, $future);
$addMove(6, TILE_TARGET, V_HOME, 0, $future);
// Uno VENCIDO de cada tipo, entre otras dos aldeas, para que los barridos tengan qué encontrar.
$dueByType = array();
foreach(array(array(0, 0), array(2, 0), array(3, 3), array(3, 2), array(4, 4), array(5, 0), array(6, 0), array(9, 0)) as $spec) {
    $addMove($spec[0], 990050, 990051, $spec[1], $due);
    $key = $spec[0].($spec[0] === 3 ? ($spec[1] === 2 ? 'r' : 'a') : '');
    $dueByType[$key] = 1;
}
$pendingMoves = $nextId - MOVES_DONE;
$totalMoves = count($moves);

insertRows('movement', $moveColumns, $moves);
insertRows('attacks', 'id,vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy,sethome', $attacks);
insertRows('send', 'id,wood,clay,iron,crop,merchant', $sends);
unset($moves, $attacks, $sends);

// Informes: un jugador con miles, todos leídos, y otros doce con mezclas al azar pero
// reproducibles, para comparar contra la cuenta vieja.
$reports = array();
$reportId = 0;
for($i = 1; $i <= REPORTS; $i++) {
    // Repartidos entre FARM_TARGETS objetivos: el objetivo k recibe los informes k, k+100...
    $reports[] = array(++$reportId, U_HEAVY, TILE_TARGET + 1000 + ($i % FARM_TARGETS), 0, "'informe'", 1 + ($i % 7), "'".str_repeat('d', 66)."'", $now - $i * 120, 1, 0, 0);
}
mt_srand(20261005);
$mixedPlayers = array();
for($player = 0; $player < 12; $player++) {
    $uid = 990700 + $player;
    $mixedPlayers[] = $uid;
    $amount = $player === 0 ? 0 : mt_rand(1, 60);
    for($i = 0; $i < $amount; $i++) {
        // Uno de cada cuatro jugadores no tiene ninguno sin leer; el resto, pocos.
        $viewed = ($player % 4 === 1 || mt_rand(1, 20) > 1) ? 1 : 0;
        $archive = mt_rand(1, 6) === 1 ? 1 : 0;
        $reports[] = array(++$reportId, $uid, V_HOME, 0, "'informe'", mt_rand(0, 26), "'x'", $now - mt_rand(1, 500000), $viewed, $archive, 0);
    }
}
insertRows('ndata', 'id,uid,toWref,ally,topic,ntype,data,time,viewed,archive,del', $reports);
$totalReports = count($reports);
unset($reports);

foreach(array('movement', 'attacks', 'send', 'ndata') as $table) {
    q("ANALYZE TABLE {$P}{$table}");
}

// Tope de filas leídas por llamada: el 5% de la tabla. Con el índice son decenas; sin
// él, la tabla entera. El margen es ancho a propósito para que esto no dependa de qué
// plan elija el optimizador entre dos índices buenos.
$moveBudget = (int)($totalMoves / 20);
$reportBudget = (int)($totalReports / 20);

// =====================================================================================
section('A. El medidor mide');
// =====================================================================================
$idle = measure(function() { return null; });
$meterOverhead = array('reads' => $idle['reads'], 'sent' => $idle['sent']);
$idleAgain = measure(function() { return null; });
check($idleAgain['reads'] === 0 && $idleAgain['sent'] === 0,
    'el medidor en vacío da 0 (da '.$idleAgain['reads'].' lecturas y '.$idleAgain['sent'].' filas enviadas): se estaría contando a sí mismo');

// `ref2` no tiene índice ni lo va a tener: esta consulta ES un recorrido completo.
$fullScan = measure(function() use ($P) { return scalar("SELECT COUNT(*) FROM {$P}movement WHERE ref2 = 424242"); });
check($fullScan['reads'] >= $totalMoves,
    'un recorrido completo de movement se ve en el medidor (leyó '.thousands($fullScan['reads']).' de '.thousands($totalMoves).' filas)');
$fullSend = measure(function() use ($P) {
    $rows = array();
    $result = q("SELECT id FROM {$P}ndata WHERE uid = ".U_HEAVY);
    while($row = mysqli_fetch_row($result)) { $rows[] = $row; }
    return count($rows);
});
check($fullSend['sent'] >= REPORTS && $fullSend['value'] === REPORTS,
    'traerse todos los informes de un jugador se ve en el medidor ('.thousands($fullSend['sent']).' filas enviadas)');
echo '  tabla movement: '.thousands($totalMoves).' filas, '.$pendingMoves.' en camino · tope por llamada: '.thousands($moveBudget).' lecturas'.PHP_EOL;
echo '  tabla ndata: '.thousands($totalReports).' informes · tope por llamada: '.thousands($reportBudget).' lecturas'.PHP_EOL;

// =====================================================================================
section('B. Construir Message no trae informes');
// =====================================================================================
// Session.php hace `$message = new Message` en todas las páginas: esto es lo que corre.
$session = (object)array('uid' => U_HEAVY, 'plus' => 0, 'alliance' => 0);
$built = measure(function() { return new Message(); });
check($built['sent'] <= 5,
    'new Message con '.thousands(REPORTS).' informes no se los trae: la base mandó '.thousands($built['sent']).' filas (antes: '.thousands(REPORTS * 2).')');
check($built['reads'] <= $reportBudget,
    'ni recorre la tabla para averiguarlo: '.thousands($built['reads']).' lecturas (tope '.thousands($reportBudget).')');
check($built['value']->nunread === false, 'y con todos los informes leídos, nunread es false');

// berichte.php llama a noticeType($_GET) con la pestaña elegida. Antes cada pestaña
// volvía a cargar todos los informes dos veces para armar dos listas que nadie leía.
foreach(array(1, 2, 3, 4, 5, 6, 7, 8) as $tab) {
    $message = $built['value'];
    $filtered = measure(function() use ($message, $tab) { $message->noticeType(array('t' => $tab)); return null; });
    check($filtered['sent'] === 0 && $filtered['reads'] === 0,
        'noticeType(t='.$tab.') no consulta nada ('.$filtered['sent'].' filas enviadas, '.$filtered['reads'].' lecturas)');
}

// =====================================================================================
section('C. nunread responde lo mismo que antes');
// =====================================================================================
// La cuenta vieja, tal cual: todas las filas del jugador y un bucle buscando viewed = 0.
// Ni `del` ni `archive` ni el tipo entraban en la condición.
$legacyUnread = function($uid) use ($P) {
    $result = q("SELECT viewed FROM {$P}ndata WHERE uid = ".(int)$uid." ORDER BY time DESC");
    while($row = mysqli_fetch_assoc($result)) {
        if($row['viewed'] == 0) {
            return true;
        }
    }
    return false;
};
$nunreadOf = function($uid) {
    global $session;
    $session = (object)array('uid' => $uid, 'plus' => 0, 'alliance' => 0);
    $message = new Message();
    return $message->nunread;
};

check($nunreadOf(990699) === false, 'un jugador sin ningún informe: false');
check($nunreadOf(U_HEAVY) === false, 'miles de informes, todos leídos: false');

$probeId = $reportId + 1;
q("INSERT INTO {$P}ndata (id,uid,toWref,ally,topic,ntype,data,time,viewed,archive,del) VALUES ($probeId,".U_OTHER.",0,0,'x',1,'x',$now,0,0,0)");
check($nunreadOf(U_OTHER) === true, 'un informe sin leer: true');
check($nunreadOf(U_HEAVY) === false, 'el informe sin leer de OTRO jugador no cuenta');
$database->archiveNotice($probeId, U_OTHER);
check($nunreadOf(U_OTHER) === true, 'archivado pero sin leer sigue contando, como antes');
$database->unarchiveNotice($probeId, U_OTHER);
$database->noticeViewed($probeId, U_OTHER);
check($nunreadOf(U_OTHER) === false, 'al leerlo vuelve a false');
q("UPDATE {$P}ndata SET viewed = 0 WHERE id = $probeId");
$database->removeNotice($probeId, U_OTHER);
check($nunreadOf(U_OTHER) === false, 'un informe borrado no cuenta (borrar lo marca como leído)');

// Uno solo sin leer, enterrado debajo de miles: el caso que el bucle viejo encontraba
// recorriéndolos todos.
$oldestId = (int)scalar("SELECT id FROM {$P}ndata WHERE uid = ".U_HEAVY." ORDER BY time ASC LIMIT 1");
q("UPDATE {$P}ndata SET viewed = 0 WHERE id = $oldestId");
$buried = measure(function() use ($nunreadOf) { return $nunreadOf(U_HEAVY); });
check($buried['value'] === true, 'el más viejo de '.thousands(REPORTS).' sin leer: true');
check($buried['reads'] <= $reportBudget && $buried['sent'] <= 5,
    'y se encuentra sin leerlos todos ('.thousands($buried['reads']).' lecturas, '.$buried['sent'].' filas enviadas)');
q("UPDATE {$P}ndata SET viewed = 1 WHERE id = $oldestId");

$mismatches = array();
foreach(array_merge($mixedPlayers, array(U_HEAVY, U_OTHER, 990699)) as $uid) {
    if($nunreadOf($uid) !== $legacyUnread($uid)) {
        $mismatches[] = $uid;
    }
}
check(empty($mismatches),
    'coincide con la cuenta vieja para los '.(count($mixedPlayers) + 3).' jugadores de prueba'
        .(empty($mismatches) ? '' : ' (difieren: '.implode(', ', $mismatches).')'));
$withUnread = 0;
foreach($mixedPlayers as $uid) {
    $withUnread += $legacyUnread($uid) ? 1 : 0;
}
check($withUnread > 0 && $withUnread < count($mixedPlayers),
    'y la comparación ve los dos resultados ('.$withUnread.' de '.count($mixedPlayers).' con informes sin leer)');

// Sin sesión $session->uid es null. Antes eso armaba un SQL roto en cada página pública.
foreach(array(null, 0, -5, '', 'abc') as $bad) {
    $unlogged = measure(function() use ($database, $bad) { return $database->hasUnreadNotice($bad); });
    check($unlogged['value'] === false && $unlogged['reads'] === 0,
        'hasUnreadNotice('.var_export($bad, true).') es false y ni consulta');
}
check($nunreadOf(null) === false, 'Message sin jugador logueado deja nunread en false');
check($database->hasUnreadNotice(U_OTHER.' OR 1=1') === false,
    'el uid se normaliza a entero: una inyección no devuelve los informes de otro');

// El contador del menú (navigation.tpl) corre en todas las páginas y usa el mismo índice.
$session = (object)array('uid' => U_HEAVY, 'plus' => 0, 'alliance' => 0);
$menu = measure(function() use ($database) { return $database->getUnreadNoticeCountsByCategory(U_HEAVY); });
check($menu['reads'] <= $reportBudget,
    'el contador de no leídos del menú tampoco recorre la tabla ('.thousands($menu['reads']).' lecturas)');

// =====================================================================================
section('D. Nadie puede volver a cargar todos los informes');
// =====================================================================================
check(!method_exists($database, 'getNotice') && !method_exists($database, 'getNotice3'),
    'la capa de datos ya no tiene getNotice() ni getNotice3(), que devolvían todos los informes de un jugador');
check(method_exists($database, 'hasUnreadNotice'), 'y tiene hasUnreadNotice()');
$messageReflection = new ReflectionClass('Message');
foreach(array('noticearray', 'notice', 'allNotice') as $property) {
    check(!$messageReflection->hasProperty($property),
        'Message ya no tiene $'.$property.', la lista de informes que no leía nadie');
}

// =====================================================================================
section('E. movement: lo que las páginas piden no recorre la tabla');
// =====================================================================================
$within = function($label, $fn, $expectedRows = null) use ($moveBudget) {
    $result = measure($fn);
    $ok = check($result['reads'] <= $moveBudget,
        $label.': '.thousands($result['reads']).' lecturas (tope '.thousands($moveBudget).')');
    if($expectedRows !== null) {
        $got = is_array($result['value']) ? count($result['value']) : $result['value'];
        check($got === $expectedRows, $label.' devuelve '.$expectedRows.' ('.var_export($got, true).')');
    }
    return $ok;
};

// Modo 0 = lo que sale de la aldea, modo 1 = lo que llega.
$within('getMovement(3, salida) — ataques, espionaje y refuerzos', function() use ($database) { return $database->getMovement(3, V_HOME, 0); }, 5);
$within('getMovement(3, llegada) — ataques entrantes', function() use ($database) { return $database->getMovement(3, V_HOME, 1); }, 2);
$within('getMovement(4, llegada) — regresos', function() use ($database) { return $database->getMovement(4, V_HOME, 1); }, 2);
$within('getMovement(0, salida) — mercaderes', function() use ($database) { return $database->getMovement(0, V_HOME, 0); }, 2);
$within('getMovement(2, llegada) — mercaderes que vuelven', function() use ($database) { return $database->getMovement(2, V_HOME, 1); }, 1);
$within('getMovement(5, salida) — colonos', function() use ($database) { return $database->getMovement(5, V_HOME, 0); }, 1);
$within('getMovement(9, salida) — aventura', function() use ($database) { return $database->getMovement(9, V_HOME, 0); }, 1);
foreach(array(0, 2, 3, 4, 5, 6, 9, 34) as $type) {
    foreach(array(0, 1) as $mode) {
        $within('getMovement('.$type.', modo '.$mode.')', function() use ($database, $type, $mode) { return $database->getMovement($type, V_HOME, $mode); });
    }
}
foreach(array(3, 4, 5, 7, 9) as $type) {
    foreach(array(0, 1) as $mode) {
        $within('getMovement2('.$type.', modo '.$mode.')', function() use ($database, $type, $mode) { return $database->getMovement2($type, V_HOME, $mode); });
    }
}
// 2 envíos de 3 mercaderes saliendo + los 3 que vuelven (en sort_type 2 viajan en `ref`).
$returningMerchants = (int)scalar("SELECT ref FROM {$P}movement WHERE sort_type = 2 AND proc = 0 AND `to` = ".V_HOME);
$within('travelingMerchants()', function() use ($database) { return (int)$database->travelingMerchants(V_HOME); }, 6 + $returningMerchants);
$within('getPendingSettlementCountByOwner()', function() use ($database) { return $database->getPendingSettlementCountByOwner(U_OWNER); }, 1);
$within('heroAdventureInProgress()', function() use ($database) { return $database->heroAdventureInProgress(U_OWNER); });
$within('checkAttack()', function() use ($database) { return $database->checkAttack(V_HOME, TILE_TARGET) !== false; }, true);
$within('isPendingAttackMovement()', function() use ($database, $attackMoveId) { return $database->isPendingAttackMovement($attackMoveId); }, true);
$tiles = array();
for($tile = 0; $tile < 99; $tile++) {
    $tiles[] = array('id' => TILE_TARGET + $tile - 40);
}
$tiles[] = array('id' => V_HOME);
$within('mapAttackMarkers() del mapa chico', function() use ($tiles) { return mapAttackMarkers($tiles, V_HOME, false); }, 4);
$within('mapAttackMarkers() del mapa grande', function() use ($tiles) { return mapAttackMarkers($tiles, V_HOME, true); }, 4);

// =====================================================================================
section('F. movement: los barridos de Automation tampoco');
// =====================================================================================
// Los barridos corren en cada request y no se pueden invocar acá: además de leer,
// resuelven batallas y escriben. Así que se saca la consulta del fuente y se ejecuta
// ésa: si alguien la cambia, lo que se mide es la nueva.
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
preg_match_all('/^\s*\$q1?\s*=\s*("SELECT \* FROM "\.TB_PREFIX\."movement.*");\s*$/m', $automationSource, $sweepMatches);
$sweepQueries = array();
foreach($sweepMatches[1] as $expression) {
    $sql = str_replace(array('".TB_PREFIX."', '$time'), array(TB_PREFIX, (string)$now), $expression);
    $sql = substr($sql, 1, -1);
    if(strpos($sql, '$') !== false || strpos($sql, '"') !== false) {
        check(false, 'no se pudo convertir a SQL una consulta de barrido (¿cambió de forma?): '.substr($expression, 0, 90));
        continue;
    }
    $sweepQueries[] = $sql;
}
// Mercaderes de ida y de vuelta, ataques, refuerzos, regresos, botín, colonos, aventuras.
check(count($sweepQueries) === 8,
    'se reconocen las 8 consultas de barrido sobre movement de Automation.php (se encontraron '.count($sweepQueries).'); si se agregó o reescribió una, hay que sumarla acá');
foreach($sweepQueries as $sql) {
    $label = 'barrido «'.preg_replace('/\s+/', ' ', substr(preg_replace('/^.*?\bwhere\b/i', '', $sql), 0, 78)).'…»';
    $result = measure(function() use ($sql) {
        $rows = 0;
        $query = q($sql);
        while(mysqli_fetch_row($query)) { $rows++; }
        return $rows;
    });
    check($result['reads'] <= $moveBudget, $label.': '.thousands($result['reads']).' lecturas (tope '.thousands($moveBudget).')');
    check($result['value'] === 1, $label.' encuentra el único vencido de su tipo (encontró '.$result['value'].')');
}
// La novena no cabe en una línea, pero sólo lee: se puede llamar de verdad.
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
$nextArrival = new ReflectionMethod('Automation', 'nextPendingAttackArrival');
$nextArrival->setAccessible(true);
$within('nextPendingAttackArrival()', function() use ($nextArrival, $automation) { return $nextArrival->invoke($automation); }, $due);

// =====================================================================================
section('G. Ninguna lectura de movement queda fuera de un índice');
// =====================================================================================
// Las secciones E y F cubren lo que existe hoy. Esto cubre lo que se escriba mañana: toda
// consulta que lea movement tiene que poder resolverse con un índice, o estar anotada acá
// con el motivo por el que no importa.
//
//   - `proc = 0|1` (con el tipo detrás)  -> pending_by_type
//   - `moveid = …`                        -> clave primaria
//   - `to = …` + `sort_type`              -> evasion_return_window
$knownUnindexed = array(
    'GameEngine/Database/db_MYSQLi.php' => array(
        'SELECT `to` FROM ".TB_PREFIX."movement WHERE sort_type = 5 AND `from` = $target'
            => 'conquestVillageCleanup(): una vez por aldea conquistada',
        'WHERE sort_type = 5 AND data = \'$userid\' LIMIT 1'
            => 'hasSettlementAttemptForQuest(): sólo mientras dura esa misión del tutorial',
    ),
);
$unindexed = array();
$statementsSeen = 0;
// `--listar` imprime cada lectura encontrada y por qué se la dio por buena.
$listStatements = in_array('--listar', $argv, true);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file) {
    $path = str_replace($root.'/', '', $file->getPathname());
    if(!preg_match('/\.(php|tpl)$/', $path) || preg_match('#^(tools|openspec|install|\.git)/#', $path)) {
        continue;
    }
    $lines = file($file->getPathname());
    $lastEnd = -1;
    foreach($lines as $index => $line) {
        if($index <= $lastEnd || !preg_match('/(?<![\$\w])(from|join)\b.{0,60}movement/i', $line) || preg_match('#^\s*(//|\*|/\*|\#)#', $line)) {
            continue;
        }
        // La sentencia PHP completa: hacia atrás hasta donde termina la anterior, hacia
        // adelante hasta su punto y coma.
        $start = $index;
        while($start > 0 && $index - $start < 10 && !preg_match('/[;{}]\s*$/', rtrim($lines[$start - 1]))) {
            $start--;
        }
        $end = $index;
        while($end < count($lines) - 1 && $end - $index < 14 && !preg_match('/;\s*$/', rtrim($lines[$end]))) {
            $end++;
        }
        $lastEnd = $end;
        $statement = implode('', array_slice($lines, $start, $end - $start + 1));
        // Borrados y updates no son lecturas de página: son conquista, borrado de cuenta
        // y cancelaciones, que ocurren una vez.
        if(preg_match('/["\']\s*(DELETE|UPDATE)\b/i', $statement)) {
            continue;
        }
        $statementsSeen++;
        $servedBy = null;
        if(preg_match('/proc`?\s*=\s*[\'"]?\s*[01]\b/i', $statement)) {
            $servedBy = 'pending_by_type';
        } elseif(preg_match('/moveid`?\s*=/i', $statement)) {
            $servedBy = 'clave primaria';
        } elseif(preg_match('/`to`\s*=/', $statement) && preg_match('/sort_type\s*=/', $statement)) {
            $servedBy = 'evasion_return_window';
        }
        if($listStatements) {
            echo '  '.str_pad($path.':'.($index + 1), 46).' '.($servedBy === null ? '(sin índice)' : $servedBy).PHP_EOL;
        }
        if($servedBy !== null) {
            continue;
        }
        $excused = false;
        if(isset($knownUnindexed[$path])) {
            foreach(array_keys($knownUnindexed[$path]) as $needle) {
                if(strpos($statement, $needle) !== false) {
                    $excused = true;
                    break;
                }
            }
        }
        if(!$excused) {
            $unindexed[] = $path.':'.($index + 1);
        }
    }
}
check($statementsSeen >= 30,
    'el barrido del repo encuentra las lecturas de movement (encontró '.$statementsSeen.'; con menos de 30 dejó de reconocerlas)');
check(empty($unindexed),
    'toda lectura de movement filtra por proc, por moveid o por to+sort_type'
        .(empty($unindexed) ? '' : ' — sin índice: '.implode(', ', $unindexed)
            .'. Agregar `proc = 0` a la consulta o anotarla en $knownUnindexed con el motivo'));

// =====================================================================================
section('H. Los índices existen en los tres lugares, con las mismas columnas');
// =====================================================================================
$normalizeColumns = function($list) {
    return array_values(array_filter(array_map(function($column) {
        return trim($column, "` \t\r\n");
    }, explode(',', $list)), 'strlen'));
};
$migrations = file_get_contents($root.'/tools/migrations.sql');
$installer = file_get_contents($root.'/install/data/sql.sql');
foreach($EXPECTED_INDEXES as $table => $indexes) {
    foreach($indexes as $name => $columns) {
        $want = implode(', ', $columns);
        check(isset($liveIndexes[$table][$name]) && $liveIndexes[$table][$name] === $columns,
            'la base viva tiene '.$table.'.'.$name.' ('.$want.')'
                .(isset($liveIndexes[$table][$name]) ? ' — tiene ('.implode(', ', $liveIndexes[$table][$name]).')' : ' — falta: aplicar tools/migrations.sql'));

        $inMigration = preg_match('/ALTER\s+TABLE\s+s1_'.$table.'\s+ADD\s+INDEX\s+IF\s+NOT\s+EXISTS\s+'.$name.'\s*\(([^)]*)\)/i', $migrations, $m)
            ? $normalizeColumns($m[1]) : null;
        check($inMigration === $columns,
            'tools/migrations.sql lo agrega con esas columnas'.($inMigration === null ? ' — no está' : ' ('.implode(', ', $inMigration).')'));

        $inInstaller = null;
        if(preg_match('/CREATE TABLE IF NOT EXISTS `%PREFIX%'.$table.'` \((.*?)\) ENGINE=/s', $installer, $block)
            && preg_match('/KEY `'.$name.'` \(([^)]*)\)/', $block[1], $m)) {
            $inInstaller = $normalizeColumns($m[1]);
        }
        check($inInstaller === $columns,
            'install/data/sql.sql lo crea igual en un mundo nuevo'.($inInstaller === null ? ' — no está' : ' ('.implode(', ', $inInstaller).')'));
    }
}

// =====================================================================================
section('I. Lista de granjas: el último saqueo de cada objetivo');
// =====================================================================================
// La lista hace UNA de estas consultas por objetivo, así que lo que cueste una se
// multiplica por cientos. Acá entró la única regresión de los índices de arriba: con
// `unread_by_player` MariaDB dejó de recorrer la tabla y pasó a leer por el índice todos
// los informes del jugador —mismas filas, pero de a una, 17 veces más lento— y la lista
// quedó peor que antes de tener índices. Por eso se mide la consulta de la plantilla,
// sacada del fuente, y no una copia.
$farmlistSource = file_get_contents($root.'/Templates/goldClub/farmlist.tpl');
$lastRaidSql = null;
if(preg_match('/\$limits = "([^"]*)";/', $farmlistSource, $limitsMatch)
    && strpos($farmlistSource, 'mysql_query("SELECT * FROM ".TB_PREFIX."ndata WHERE $limits AND toWref = ".$towref." AND uid = ".$session->uid." ORDER BY time DESC Limit 1")') !== false) {
    $lastRaidSql = function($target, $uid) use ($limitsMatch) {
        return "SELECT * FROM ".TB_PREFIX."ndata WHERE ".$limitsMatch[1]." AND toWref = ".$target." AND uid = ".$uid." ORDER BY time DESC Limit 1";
    };
}
check($lastRaidSql !== null,
    'se reconoce la consulta de "Último saqueo" de Templates/goldClub/farmlist.tpl; si cambió de forma hay que traerla acá de nuevo');
if($lastRaidSql !== null) {
    $lastRaid = function($target, $uid = U_HEAVY) use ($lastRaidSql) {
        return measure(function() use ($lastRaidSql, $target, $uid) {
            $row = mysqli_fetch_assoc(q($lastRaidSql($target, $uid)));
            return $row ? $row : null;
        });
    };
    // Lo que tiene que devolver, calculado sin la consulta: el más nuevo de ese objetivo
    // que no sea un informe de defensa (ntype 4 a 7).
    $expectedLast = function($target) use ($P) {
        $best = null;
        foreach(rowsOf("SELECT id, ntype, time FROM {$P}ndata WHERE uid = ".U_HEAVY." AND toWref = ".$target) as $row) {
            if(in_array((int)$row['ntype'], array(4, 5, 6, 7), true)) {
                continue;
            }
            if($best === null || (int)$row['time'] > (int)$best['time']) {
                $best = $row;
            }
        }
        return $best === null ? null : (int)$best['id'];
    };
    // Cada objetivo tiene ~30 informes; con el índice se llega al último leyendo un puñado.
    $perTarget = 20;
    $firstTarget = TILE_TARGET + 1000;
    $one = $lastRaid($firstTarget + 7);
    check($one['value'] !== null && (int)$one['value']['id'] === $expectedLast($firstTarget + 7),
        'devuelve el informe más nuevo de ese objetivo');
    check($one['reads'] <= $perTarget,
        'leyendo '.$one['reads'].' fila(s), no los '.thousands(REPORTS).' informes del jugador (tope '.$perTarget.')');
    $none = $lastRaid(TILE_TARGET + 5000);
    check($none['value'] === null && $none['reads'] <= $perTarget,
        'un objetivo recién agregado, sin informes, tampoco los recorre ('.$none['reads'].' lecturas)');

    // La lista entera: cien objetivos.
    $totalReads = 0;
    $wrong = 0;
    for($slot = 0; $slot < FARM_TARGETS; $slot++) {
        $result = $lastRaid($firstTarget + $slot);
        $totalReads += $result['reads'];
        $wrong += ($result['value'] !== null ? (int)$result['value']['id'] : null) === $expectedLast($firstTarget + $slot) ? 0 : 1;
    }
    check($wrong === 0, 'los '.FARM_TARGETS.' objetivos de una lista muestran cada uno su último saqueo');
    check($totalReads <= FARM_TARGETS * $perTarget,
        'dibujar la lista lee '.thousands($totalReads).' filas en total (antes: '.thousands(FARM_TARGETS * REPORTS).', '.thousands(REPORTS).' por objetivo)');

    // Un objetivo cuyos informes más nuevos son de defensa: se saltean hasta el primer ataque.
    $defended = $firstTarget + 3;
    $newest = rowsOf("SELECT id FROM {$P}ndata WHERE uid = ".U_HEAVY." AND toWref = $defended ORDER BY time DESC LIMIT 5");
    $ids = array();
    foreach($newest as $row) { $ids[] = (int)$row['id']; }
    q("UPDATE {$P}ndata SET ntype = 4 WHERE id IN (".implode(',', $ids).")");
    $skipping = $lastRaid($defended);
    check($skipping['value'] !== null && (int)$skipping['value']['id'] === $expectedLast($defended)
            && !in_array((int)$skipping['value']['id'], $ids, true),
        'los informes de defensa no cuentan como "último saqueo": se muestra el ataque anterior');
    check($skipping['reads'] <= $perTarget, 'y se llega a él sin recorrer el resto ('.$skipping['reads'].' lecturas)');

    // Otro jugador con el mismo objetivo no ve los informes del primero.
    $foreign = $lastRaid($firstTarget + 7, U_OTHER);
    check($foreign['value'] === null, 'el último saqueo es el del jugador que mira, no el de otro');
}

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Consultas por carga de página: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
