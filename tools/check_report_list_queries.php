<?php
/**
 * Las listas de Informes y de Mensajes: contar sin traer filas, leer sólo la página.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_report_list_queries.php
 *
 * check_page_load_queries.php cubre lo que corre en TODAS las páginas. Esto cubre lo que
 * quedaba del mismo problema en una sola pantalla, berichte.php, cuyas plantillas de lista
 * (Templates/Notice/all.tpl y t_1..t_7) hacían tres cosas mal:
 *
 *   - Contaban los informes de la pestaña trayéndoselos TODOS, con su `data` y ordenados,
 *     para llamar a mysql_num_rows(). Con 27.641 informes de un jugador eran ~22.000 filas
 *     por visita para saber cuántas páginas dibujar.
 *   - La página en sí leía y ordenaba todos los informes del jugador para mostrar diez.
 *   - En una pestaña vacía la página quedaba en 0 y la consulta salía con `LIMIT -10,10`,
 *     que es un error de sintaxis ([SQL FALLIDO] en el log). Lo mismo en las tres listas
 *     de Templates/Message/.
 *
 * Como el otro checker, no mira cómo está escrito el código sino lo que la base HACE: se
 * dibujan las plantillas de verdad sobre tablas temporales y se cuentan las filas leídas
 * (`Handler_read_*`) y enviadas (`Rows_sent`). Y lo que el jugador ve se compara, pestaña
 * por pestaña y página por página, contra las consultas viejas tal cual estaban.
 *
 *   A. El medidor y la trampa de consultas fallidas funcionan.
 *   B. Cada pestaña muestra las mismas filas, en las mismas páginas, que la consulta vieja.
 *   C. El paginador cuenta lo que la lista muestra (la pestaña de ataques contaba borrados).
 *   D. Informes del mismo segundo: orden fijo, sin repetidos, igual al de las flechas.
 *   E. Con miles de informes: contar manda una fila y la primera página lee las suyas.
 *   F. Una pestaña vacía no manda una consulta rota, ni en Informes ni en Mensajes.
 *   G. Mensajes: mismas filas que antes, contando sin traerlas.
 *   H. Ninguna plantilla de lista vuelve a contar o a paginar por su cuenta.
 *   I. El índice está en la base viva, en migrations.sql y en el instalador, igual.
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real: el mundo de verdad no se toca.
 * Por eso mismo, si la base no tiene el índice la sección E falla: es la migración sin
 * aplicar.
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
function column($sql) {
    $values = array();
    $result = q($sql);
    while($row = mysqli_fetch_row($result)) {
        $values[] = (int)$row[0];
    }
    return $values;
}
function thousands($n) {
    return number_format($n, 0, ',', '.');
}

// El índice que este checker defiende, con sus columnas EN ORDEN: las tres primeras son
// las igualdades del filtro y las dos últimas el orden de la lista.
$INDEX_TABLE = 'ndata';
$INDEX_NAME = 'list_by_player';
$INDEX_COLUMNS = array('uid', 'archive', 'del', 'time', 'id');

$P = TB_PREFIX;

// La base viva se mira ANTES de taparla con las temporales (sección I).
$liveIndex = array();
$result = q("SHOW INDEX FROM {$P}{$INDEX_TABLE} WHERE Key_name = '{$INDEX_NAME}'");
while($row = mysqli_fetch_assoc($result)) {
    $liveIndex[(int)$row['Seq_in_index']] = $row['Column_name'];
}
ksort($liveIndex);
$liveIndex = array_values($liveIndex);

// =====================================================================================
// Tablas temporales con el mismo nombre que las reales: en esta conexión las tapan.
// =====================================================================================
foreach(array('ndata', 'mdata') as $table) {
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
// El medidor, igual que en check_page_load_queries.php: contadores de la sesión.
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

// -------------------------------------------------------------------------------------
// La trampa de consultas fallidas. La capa de datos deja cada consulta que falla en el
// log de errores como `[SQL FALLIDO] …`; acá el log va a un archivo que se puede leer.
// -------------------------------------------------------------------------------------
$sqlLog = tempnam(sys_get_temp_dir(), 'sqllog');
ini_set('log_errors', '1');
ini_set('error_log', $sqlLog);
register_shutdown_function(function() use ($sqlLog) { @unlink($sqlLog); });
function failedQueries() {
    global $sqlLog;
    clearstatcache();
    $failed = array();
    foreach(file($sqlLog) as $line) {
        if(strpos($line, '[SQL FALLIDO]') !== false) {
            $failed[] = trim($line);
        }
    }
    return $failed;
}
function forgetFailedQueries() {
    global $sqlLog;
    file_put_contents($sqlLog, '');
}

// -------------------------------------------------------------------------------------
// Dibujar una lista de verdad. El reparto por pestaña es el de berichte.php; la sección H
// comprueba contra su fuente que siga siendo ése.
// -------------------------------------------------------------------------------------
function renderReports($uid, $tab, $page = null, $perPage = 10) {
    global $database, $generator, $technology, $root;
    $session = (object)array('uid' => $uid, 'plus' => 1, 'tribe' => 1, 'alliance' => 0);
    $reportsPerPage = $perPage;
    $reportPageSizes = array(10, 20, 50, 100);
    $reportFilter = (int)$tab;
    $_GET = array();
    if($tab > 0) {
        $_GET['t'] = (string)$tab;
    }
    if($page !== null) {
        $_GET['page'] = (string)$page;
    }
    $_SERVER['PHP_SELF'] = '/berichte.php';
    ob_start();
    if(isset($_GET['t'])) {
        if($reportFilter === 8) {
            $noticeSqlFilter = "and viewed = 0";
            include $root."/Templates/Notice/t_2.tpl";
        } else {
            include $root."/Templates/Notice/t_".$reportFilter.".tpl";
        }
    } else {
        include $root."/Templates/Notice/all.tpl";
    }
    return parseList(ob_get_clean());
}
function renderMessages($uid, $box, $page = null) {
    global $database, $generator, $root;
    $session = (object)array('uid' => $uid, 'plus' => 1, 'tribe' => 1, 'alliance' => 0);
    $_GET = array();
    if($page !== null) {
        $_GET['page'] = (string)$page;
    }
    $_SERVER['PHP_SELF'] = '/nachrichten.php';
    ob_start();
    include $root."/Templates/Message/".$box.".tpl";
    return parseList(ob_get_clean());
}
// Lo que el jugador ve de una lista: qué filas, en qué orden, y qué dice el paginador.
function parseList($html) {
    preg_match_all('/<input class="check" type="checkbox" name="n\d+" value="(\d+)"/', $html, $rows);
    $paginator = '';
    if(preg_match('/<div class="paginator">(.*?)<\/div>/s', $html, $m)) {
        $paginator = $m[1];
    }
    preg_match_all('/class="number[^"]*"[^>]*>(\d+)</', $paginator, $numbers);
    $current = preg_match('/class="number currentPage">(\d+)</', $paginator, $m) ? (int)$m[1] : null;
    return array(
        'ids'       => array_map('intval', $rows[1]),
        'current'   => $current,
        'lastShown' => empty($numbers[1]) ? null : max(array_map('intval', $numbers[1])),
        'empty'     => strpos($html, 'class="none"') !== false,
        'paginator' => trim(preg_replace('/\s+/', ' ', $paginator)),
    );
}

// =====================================================================================
// Las consultas VIEJAS, tal cual estaban en cada plantilla antes del cambio: `count` es
// la que se traía todas las filas para contarlas y `list` la de la página. Son la
// referencia contra la que se compara, no una segunda definición de las pestañas.
// =====================================================================================
$route = "(ntype = 26 or (ntype IN (10,11,12,13) and data LIKE '%,route'))";
$LEGACY = array(
    0 => array('name' => 'Todos',
        'count' => "uid = %d and archive = 0 and del = 0 and not $route",
        'list'  => "uid = %d and archive = 0 and del = 0 and not $route"),
    8 => array('name' => 'No leídos',
        'count' => "uid = %d and archive = 0 and viewed = 0 and del = 0",
        'list'  => "uid = %d and archive=0 and viewed = 0 and del = 0"),
    1 => array('name' => 'Ataque',
        // La única que contaba otra cosa que lo que listaba: le faltaba `del = 0`.
        'count' => "uid = %d and archive=0 and (ntype=1 or ntype=2 or ntype=3 or ntype=4 or ntype=5 or ntype=6 or ntype=7 or ntype=25)",
        'list'  => "uid = %d and archive=0 and (ntype=1 or ntype=2 or ntype=3 or ntype=4 or ntype=5 or ntype=6 or ntype=7 or ntype=25) and del = 0"),
    6 => array('name' => 'Espías',
        'count' => "uid = %d and archive = 0 and (ntype IN (0,22,23,24)) and del = 0",
        'list'  => "uid = %d and archive=0 and (ntype IN (0,22,23,24)) and del = 0"),
    5 => array('name' => 'Refuerzo',
        'count' => "uid = %d and archive = 0 and (ntype IN (8)) and del = 0",
        'list'  => "uid = %d and archive=0 and (ntype IN (8)) and del = 0"),
    3 => array('name' => 'Varios',
        'count' => "uid = %d and archive = 0 and (ntype = 9 or ntype IN (15,16,17,18,19,20,21)) and del = 0",
        'list'  => "uid = %d and archive=0 and (ntype = 9 or ntype IN (15,16,17,18,19,20,21)) and del = 0"),
    2 => array('name' => 'Comercio',
        'count' => "uid = %d and archive = 0 and ntype IN (10,11,12,13) and data NOT LIKE '%%,route' and del = 0",
        'list'  => "uid = %d and archive=0 and ntype IN (10,11,12,13) and data NOT LIKE '%%,route' and del = 0"),
    7 => array('name' => 'Rutas',
        'count' => "uid = %d and archive = 0 and $route and del = 0",
        'list'  => "uid = %d and archive=0 and $route and del = 0"),
    4 => array('name' => 'Archivo',
        'count' => "uid = %d and archive = 1 and del = 0",
        'list'  => "uid = %d and archive=1 and del = 0"),
);
// `$route` lleva un `%` literal: en las entradas armadas con sprintf hay que escaparlo.
foreach($LEGACY as $tab => $spec) {
    foreach(array('count', 'list') as $key) {
        $LEGACY[$tab][$key] = str_replace(array("'%,route'", "'%%,route'"), "'%%,route'", $spec[$key]);
    }
}
function legacyCount($tab, $uid) {
    global $LEGACY, $P;
    // mysql_num_rows() sobre todas las filas: lo que hacía la plantilla.
    return mysqli_num_rows(q("SELECT * FROM {$P}ndata WHERE ".sprintf($LEGACY[$tab]['count'], $uid)." ORDER BY time DESC"));
}
function legacyVisible($tab, $uid) {
    global $LEGACY, $P;
    return (int)scalar("SELECT COUNT(*) FROM {$P}ndata WHERE ".sprintf($LEGACY[$tab]['list'], $uid));
}
function legacyPage($tab, $uid, $page, $perPage) {
    global $LEGACY, $P;
    return column("SELECT id FROM {$P}ndata WHERE ".sprintf($LEGACY[$tab]['list'], $uid)
        ." ORDER BY time DESC LIMIT ".(($page - 1) * $perPage).",".$perPage);
}

// =====================================================================================
// Datos. Los uid son altos a propósito: no coinciden con ningún jugador real.
// =====================================================================================
define('U_MIXED', 990800);   // de todo: cada tipo, archivados, borrados, sin leer, rutas
define('U_EMPTY', 990801);   // ningún informe y ningún mensaje
define('U_TEN', 990802);     // exactamente una página
define('U_ELEVEN', 990803);  // una página y una fila
define('U_TIES', 990804);    // informes que comparten segundo
define('U_HEAVY', 990805);   // el jugador con miles
define('U_CROWD', 990900);   // el resto del mundo, para que la tabla no sea de uno solo
define('HEAVY_REPORTS', 3000);
define('U_MAIL', 990810);    // mensajes
define('MAIL_INBOX', 137);

$now = time();
$battleData = "'".implode(',', array_fill(0, 40, 0))."'";
$reportData = function($ntype, $routeMark) use ($battleData) {
    if($ntype >= 10 && $ntype <= 13) {
        return $routeMark ? "'1,2,3,4,route'" : "'1,2,3,4'";
    }
    if($ntype === 9) {
        return "'0,dead'";
    }
    return $battleData;
};
$reports = array();
$reportId = 0;
$addReport = function($uid, $ntype, $time, $viewed = 1, $archive = 0, $del = 0, $routeMark = false) use (&$reports, &$reportId, $reportData) {
    $reports[] = array(++$reportId, $uid, 1, 0, "'informe ".$reportId."'", $ntype, $reportData($ntype, $routeMark), $time, $viewed, $archive, $del);
    return $reportId;
};

// U_MIXED: 26 vueltas por los 27 tipos de informe, cada una con su segundo propio para
// que el orden viejo (`ORDER BY time DESC`, sin desempate) tenga una sola respuesta.
// Los id van al revés que las fechas a propósito: si la lista se ordenara por id, o si
// el desempate pisara a la fecha, la comparación lo vería.
mt_srand(20261005);
$mixedTimes = range(1, 26 * 27);
shuffle($mixedTimes);
$k = 0;
for($round = 0; $round < 26; $round++) {
    for($ntype = 0; $ntype <= 26; $ntype++) {
        $addReport(U_MIXED, $ntype, $now - 86400 * 3 + $mixedTimes[$k++] * 37,
            mt_rand(1, 4) === 1 ? 0 : 1,          // uno de cada cuatro sin leer
            mt_rand(1, 6) === 1 ? 1 : 0,          // uno de cada seis archivado
            mt_rand(1, 5) === 1 ? 1 : 0,          // uno de cada cinco borrado
            mt_rand(1, 2) === 1);                 // la mitad del comercio viene de una ruta
    }
}
for($i = 1; $i <= 10; $i++) { $addReport(U_TEN, 1, $now - $i * 60); }
for($i = 1; $i <= 11; $i++) { $addReport(U_ELEVEN, 1, $now - $i * 60); }
// U_TIES: 11 grupos de 3 informes en el mismo segundo. Con 10 por página, casi todos los
// cortes de página caen en medio de un grupo.
$tiesExpected = array();
for($group = 0; $group < 11; $group++) {
    $ids = array();
    for($i = 0; $i < 3; $i++) {
        $ids[] = $addReport(U_TIES, 1 + $i, $now - 5000 + $group * 100);
    }
    $tiesExpected[$group] = array_reverse($ids);
}
$tiesExpected = call_user_func_array('array_merge', array_reverse($tiesExpected));
// U_HEAVY: todos de ataque, todos leídos, uno cada dos minutos; cada séptimo, borrado.
for($i = 1; $i <= HEAVY_REPORTS; $i++) {
    $addReport(U_HEAVY, 1 + ($i % 7), $now - $i * 120, 1, 0, $i % 7 === 0 ? 1 : 0);
}
// El resto del mundo: 40 jugadores con una docena cada uno. El pesado queda dueño de
// ~80% de la tabla, que es la proporción con la que el optimizador puede preferir
// ignorar un índice.
for($i = 0; $i < 480; $i++) {
    $addReport(U_CROWD + ($i % 40), $i % 27, $now - mt_rand(1, 400000), mt_rand(0, 1));
}
insertRows('ndata', 'id,uid,toWref,ally,topic,ntype,data,time,viewed,archive,del', $reports);
$totalReports = count($reports);
unset($reports);

// Mensajes. El remitente/destinatario es la cuenta de soporte, que existe en todo mundo:
// la lista pide su nombre a `users`, que no se tapa.
$messages = array();
$messageId = 0;
for($i = 1; $i <= MAIL_INBOX; $i++) {
    // recibidos: uno de cada nueve archivado, uno de cada once borrado por el destinatario
    $messages[] = array(++$messageId, U_MAIL, UID_SUPPORT, "'asunto ".$messageId."'", "'".str_repeat('m', 300)."'",
        $i % 3 === 0 ? 0 : 1, $i % 9 === 0 ? 1 : 0, 0, $now - 86400 + $i * 53, $i % 11 === 0 ? 1 : 0, 0, 0, 0, 0, 0);
}
for($i = 1; $i <= 34; $i++) {
    // enviados: uno de cada cinco borrado por el remitente
    $messages[] = array(++$messageId, UID_SUPPORT, U_MAIL, "'asunto ".$messageId."'", "'".str_repeat('m', 300)."'",
        1, 0, 1, $now - 80000 + $i * 71, 0, $i % 5 === 0 ? 1 : 0, 0, 0, 0, 0);
}
insertRows('mdata', 'id,target,owner,topic,message,viewed,archived,send,time,deltarget,delowner,alliance,player,coor,report', $messages);
unset($messages);

foreach(array('ndata', 'mdata') as $table) {
    q("ANALYZE TABLE {$P}{$table}");
}

// Tope de filas leídas para "una página": el 5% de la tabla. Con el índice son las que
// se muestran; sin él, todas las del jugador.
$readBudget = (int)($totalReports / 20);

// =====================================================================================
section('A. El medidor y la trampa funcionan');
// =====================================================================================
$idle = measure(function() { return null; });
$meterOverhead = array('reads' => $idle['reads'], 'sent' => $idle['sent']);
$idleAgain = measure(function() { return null; });
check($idleAgain['reads'] === 0 && $idleAgain['sent'] === 0,
    'el medidor en vacío da 0 (da '.$idleAgain['reads'].' lecturas y '.$idleAgain['sent'].' filas enviadas)');

// El conteo viejo, medido: es el número contra el que se compara todo lo demás.
$oldCount = measure(function() { return legacyCount(1, U_HEAVY); });
check($oldCount['sent'] >= HEAVY_REPORTS,
    'el conteo viejo se ve en el medidor: para contar '.thousands($oldCount['value']).' informes la base mandó '.thousands($oldCount['sent']).' filas');

// La consulta rota, tal cual salía: la trampa tiene que verla.
forgetFailedQueries();
$broken = mysql_query("SELECT * FROM {$P}ndata WHERE uid = ".U_EMPTY." and archive=0 and (ntype IN (8)) and del = 0 ORDER BY time DESC LIMIT -10,10");
$trapped = failedQueries();
check($broken === false && count($trapped) === 1 && strpos($trapped[0], "near '-10,10'") !== false,
    'un `LIMIT -10,10` falla y queda en el log como [SQL FALLIDO] ('.count($trapped).' línea/s atrapada/s)');
forgetFailedQueries();
check(failedQueries() === array(), 'y la trampa se puede vaciar');
echo '  tabla ndata: '.thousands($totalReports).' informes, '.thousands(HEAVY_REPORTS).' de un solo jugador · tope para una página: '.thousands($readBudget).' lecturas'.PHP_EOL;

// =====================================================================================
section('B. Cada pestaña muestra las mismas filas que la consulta vieja');
// =====================================================================================
check((int)scalar("SELECT COUNT(*) - COUNT(DISTINCT time) FROM {$P}ndata WHERE uid = ".U_MIXED) === 0,
    'los informes del jugador de prueba no comparten segundo, así que el orden viejo tiene una sola respuesta');

$pagesCompared = 0;
foreach($LEGACY as $tab => $spec) {
    $visible = legacyVisible($tab, U_MIXED);
    check($visible > 10, 'la pestaña '.$spec['name'].' del jugador de prueba tiene más de una página ('.$visible.' informes)');
    foreach(array(10, 20, 50, 100) as $perPage) {
        $pages = (int)ceil($visible / $perPage);
        $wrong = array();
        $seen = array();
        for($page = 1; $page <= $pages; $page++) {
            $got = renderReports(U_MIXED, $tab, $page, $perPage);
            $pagesCompared++;
            if($got['ids'] !== legacyPage($tab, U_MIXED, $page, $perPage) || $got['current'] !== $page) {
                $wrong[] = $page;
            }
            $seen = array_merge($seen, $got['ids']);
        }
        check(empty($wrong),
            $spec['name'].', '.$perPage.' por página: las '.$pages.' páginas muestran las filas de la consulta vieja, en su orden'
                .(empty($wrong) ? '' : ' — difieren: '.implode(', ', $wrong)));
        check(count($seen) === $visible && count(array_unique($seen)) === $visible,
            $spec['name'].', '.$perPage.' por página: entre todas las páginas salen los '.$visible.' informes, una vez cada uno');
    }
    // Sin `page`, con basura y pasándose del final: lo mismo que hacía antes.
    $first = legacyPage($tab, U_MIXED, 1, 10);
    $lastPage = (int)ceil($visible / 10);
    $last = legacyPage($tab, U_MIXED, $lastPage, 10);
    foreach(array(null, 0, '', 'abc', '1x') as $requested) {
        $got = renderReports(U_MIXED, $tab, $requested, 10);
        check($got['ids'] === $first && $got['current'] === 1,
            $spec['name'].': page='.var_export($requested, true).' muestra la primera página');
    }
    foreach(array($lastPage + 1, 99999, '99999999999999999999') as $requested) {
        $got = renderReports(U_MIXED, $tab, $requested, 10);
        check($got['ids'] === $last && $got['current'] === $lastPage,
            $spec['name'].': page='.$requested.' (más allá del final) muestra la última, la '.$lastPage);
    }
}
echo '  '.thousands($pagesCompared).' páginas comparadas fila por fila'.PHP_EOL;

// Una página justa y una página y una fila: los dos bordes de `ceil()`.
$ten = renderReports(U_TEN, 0, null, 10);
check(count($ten['ids']) === 10 && $ten['lastShown'] === 1 && strpos($ten['paginator'], '<a ') === false,
    '10 informes a 10 por página: una sola página y ningún enlace en el paginador');
$eleven = renderReports(U_ELEVEN, 0, null, 10);
$elevenSecond = renderReports(U_ELEVEN, 0, 2, 10);
check(count($eleven['ids']) === 10 && $eleven['lastShown'] === 2 && count($elevenSecond['ids']) === 1,
    '11 informes a 10 por página: dos páginas, y la segunda trae el que falta');

// =====================================================================================
section('C. El paginador cuenta lo que la lista muestra');
// =====================================================================================
foreach($LEGACY as $tab => $spec) {
    $visible = legacyVisible($tab, U_MIXED);
    $counted = legacyCount($tab, U_MIXED);
    $expectedLast = max(1, (int)ceil($visible / 10));
    $got = renderReports(U_MIXED, $tab, 99999, 10);
    check($got['lastShown'] === $expectedLast && !empty($got['ids']),
        $spec['name'].': la última página del paginador es la '.$expectedLast.' y tiene informes (muestra '.var_export($got['lastShown'], true).')');
    if($tab === 1) {
        // El único cambio visible buscado: la pestaña de ataques contaba los borrados.
        check($counted > $visible,
            'Ataque: la cuenta vieja incluía los borrados ('.$counted.' contados contra '.$visible.' listados), o sea '
                .((int)ceil($counted / 10) - $expectedLast).' página/s vacía/s al final');
        check(legacyPage(1, U_MIXED, (int)ceil($counted / 10), 10) === array(),
            'Ataque: y esa última página vieja no tenía ninguna fila');
    } else {
        check($counted === $visible,
            $spec['name'].': la cuenta vieja ya coincidía con la lista ('.$counted.'), así que su paginador no cambia');
    }
}

// =====================================================================================
section('D. Informes del mismo segundo');
// =====================================================================================
// Sin desempate el orden entre ellos lo decidía el ordenamiento de turno. Ahora es el de
// getNoticeNeighbors(): fecha y, a igual fecha, el id más nuevo primero.
$tieIds = array();
for($page = 1; $page <= 4; $page++) {
    $got = renderReports(U_TIES, 0, $page, 10);
    $tieIds = array_merge($tieIds, $got['ids']);
}
check($tieIds === $tiesExpected,
    'la lista sale por fecha y, dentro del mismo segundo, por id descendente');
check(count($tieIds) === 33 && count(array_unique($tieIds)) === 33,
    'cortando grupos por la mitad no se repite ni se pierde ningún informe entre páginas ('.count(array_unique($tieIds)).' de 33)');
// Las flechas de anterior/siguiente al abrir un informe recorren la misma secuencia.
$walk = array($tieIds[0]);
while(count($walk) <= 40) {
    $neighbors = $database->getNoticeNeighbors(U_TIES, 0, end($walk), 0);
    if(!$neighbors['next']) {
        break;
    }
    $walk[] = $neighbors['next'];
}
check($walk === $tieIds, 'y es el mismo orden que siguen las flechas de anterior/siguiente al abrir un informe');

// =====================================================================================
section('E. Con miles de informes');
// =====================================================================================
$heavyVisible = legacyVisible(1, U_HEAVY);
foreach($LEGACY as $tab => $spec) {
    $counted = measure(function() use ($tab) { return renderReports(U_HEAVY, $tab, null, 10); });
    $rows = count($counted['value']['ids']);
    check($counted['sent'] <= $rows + 1,
        $spec['name'].': dibujar la primera página manda '.$counted['sent'].' filas (las '.$rows.' que muestra y la cuenta)');
}
$count = measure(function() use ($database) {
    return $database->countNoticeList(U_HEAVY, 0, "and (ntype=1 or ntype=2 or ntype=3 or ntype=4 or ntype=5 or ntype=6 or ntype=7 or ntype=25)");
});
check($count['value'] === $heavyVisible && $count['sent'] === 1,
    'contar los '.thousands($heavyVisible).' informes de ataque manda 1 fila (antes: '.thousands($oldCount['sent']).')');
check($count['reads'] <= $totalReports + 10,
    'y los lee a lo sumo una vez, sin ordenarlos ('.thousands($count['reads']).' lecturas sobre '.thousands($totalReports).' filas)');

$firstPage = measure(function() use ($database) {
    $ids = array();
    $result = $database->getNoticeListPage(U_HEAVY, 0, '', 1, 10);
    while($row = mysqli_fetch_assoc($result)) { $ids[] = (int)$row['id']; }
    return $ids;
});
check(count($firstPage['value']) === 10 && $firstPage['sent'] === 10,
    'la primera página manda sus 10 filas');
check($firstPage['reads'] <= $readBudget,
    'y lee '.thousands($firstPage['reads']).' para mostrarlas, no las '.thousands(HEAVY_REPORTS)
        .' del jugador (tope '.thousands($readBudget).'); si falla, falta el índice: aplicar tools/migrations.sql');
check($firstPage['value'] === legacyPage(0, U_HEAVY, 1, 10), 'y son las mismas 10 de la consulta vieja');

foreach(array(20, 50, 100) as $perPage) {
    $page = measure(function() use ($database, $perPage) {
        $rows = 0;
        $result = $database->getNoticeListPage(U_HEAVY, 0, '', 1, $perPage);
        while(mysqli_fetch_row($result)) { $rows++; }
        return $rows;
    });
    check($page['value'] === $perPage && $page['reads'] <= max($readBudget, $perPage * 3),
        'a '.$perPage.' por página: '.thousands($page['reads']).' lecturas');
}
// La pestaña "No leídos" de quien tiene todo leído: la cuenta da 0 por su índice y la
// lista ni se pide.
$unread = measure(function() { return renderReports(U_HEAVY, 8, null, 10); });
check($unread['value']['empty'] && $unread['sent'] === 1 && $unread['reads'] <= $readBudget,
    '"No leídos" con todo leído: 1 fila enviada y '.thousands($unread['reads']).' lecturas');
// Una pestaña sin ningún informe entre miles: se cuenta, y la lista no se pide.
$none = measure(function() { return renderReports(U_HEAVY, 5, null, 10); });
check($none['value']['empty'] && $none['sent'] === 1 && $none['reads'] <= $totalReports + 10,
    'una pestaña vacía entre '.thousands(HEAVY_REPORTS).' informes: se cuenta ('.thousands($none['reads']).' lecturas) y la lista no se consulta');

// =====================================================================================
section('F. Una pestaña vacía no manda una consulta rota');
// =====================================================================================
forgetFailedQueries();
foreach($LEGACY as $tab => $spec) {
    foreach(array(null, 1, 0, 7) as $requested) {
        $got = renderReports(U_EMPTY, $tab, $requested, 10);
        check($got['empty'] && $got['ids'] === array() && $got['current'] === 1 && strpos($got['paginator'], '<a ') === false,
            $spec['name'].' sin informes (page='.var_export($requested, true).'): "No hay informes disponibles", página 1 y paginador sin enlaces');
    }
}
foreach(array('inbox' => 'Recibidos', 'sent' => 'Enviados', 'archive' => 'Archivo') as $box => $label) {
    foreach(array(null, 1, 0, 7) as $requested) {
        $got = renderMessages(U_EMPTY, $box, $requested);
        check($got['empty'] && $got['ids'] === array() && $got['current'] === 1,
            'Mensajes · '.$label.' sin mensajes (page='.var_export($requested, true).'): lista vacía y página 1');
    }
}
$failed = failedQueries();
check($failed === array(),
    'ninguna de esas '.((count($LEGACY) + 3) * 4).' listas vacías dejó un [SQL FALLIDO] en el log'
        .(empty($failed) ? '' : ' — '.count($failed).': '.substr($failed[0], 0, 220)));
// La capa de datos tampoco arma un LIMIT negativo si le llega una página imposible.
foreach(array(0, -3, '', 'abc', null) as $bad) {
    $result = $database->getNoticeListPage(U_TEN, 0, '', $bad, 10);
    check($result !== false && mysqli_num_rows($result) === 10,
        'getNoticeListPage(página '.var_export($bad, true).') devuelve la primera página');
}
check(failedQueries() === array(), 'y sin consultas fallidas');

// =====================================================================================
section('G. Mensajes');
// =====================================================================================
$BOXES = array(
    'inbox'   => array('Recibidos', "target = ".U_MAIL." AND archived = 0 AND deltarget = 0"),
    'sent'    => array('Enviados', "owner = ".U_MAIL." AND delowner = 0"),
    'archive' => array('Archivo', "target = ".U_MAIL." AND archived = 1"),
);
forgetFailedQueries();
foreach($BOXES as $box => $spec) {
    list($label, $where) = $spec;
    $total = (int)scalar("SELECT COUNT(*) FROM {$P}mdata WHERE $where");
    $pages = (int)ceil($total / 10);
    $wrong = array();
    for($page = 1; $page <= $pages; $page++) {
        $got = renderMessages(U_MAIL, $box, $page);
        $expected = column("SELECT id FROM {$P}mdata WHERE $where ORDER BY time DESC LIMIT ".(($page - 1) * 10).",10");
        if($got['ids'] !== $expected || $got['current'] !== $page) {
            $wrong[] = $page;
        }
    }
    check($total > 10 && empty($wrong),
        $label.': las '.$pages.' páginas ('.$total.' mensajes) muestran las filas de la consulta vieja'
            .(empty($wrong) ? '' : ' — difieren: '.implode(', ', $wrong)));
    $beyond = renderMessages(U_MAIL, $box, 9999);
    check($beyond['current'] === $pages && $beyond['lastShown'] === $pages && !empty($beyond['ids']),
        $label.': pasarse del final muestra la última página, la '.$pages);
    // 1 de la cuenta + 10 mensajes + el nombre del otro jugador por cada fila.
    $first = measure(function() use ($box) { return renderMessages(U_MAIL, $box, 1); });
    check($first['sent'] <= 1 + 2 * count($first['value']['ids']),
        $label.': la primera página manda '.$first['sent'].' filas, no las '.$total.' de la bandeja para contarlas');
}
check(failedQueries() === array(), 'y sin consultas fallidas');

// =====================================================================================
section('H. Ninguna plantilla vuelve a contar o a paginar por su cuenta');
// =====================================================================================
$noticeTemplates = array('all.tpl', 't_1.tpl', 't_2.tpl', 't_3.tpl', 't_4.tpl', 't_5.tpl');
foreach($noticeTemplates as $name) {
    $source = file_get_contents($root.'/Templates/Notice/'.$name);
    check(strpos($source, 'mysql_num_rows') === false && !preg_match('/mysql_query\s*\(|ndata/', $source),
        'Templates/Notice/'.$name.' no consulta `ndata` a mano ni cuenta con mysql_num_rows()');
    $counts = preg_match_all('/\$database->countNoticeList\(\$session->uid,\s*([01]),\s*([^)]*?)\)/', $source, $countCalls);
    $lists = preg_match_all('/\$database->getNoticeListPage\(\$session->uid,\s*([01]),\s*(.*?),\s*\$page,\s*\$itemsPerPage\)/', $source, $listCalls);
    check($counts === 1 && $lists === 1
            && $countCalls[1][0] === $listCalls[1][0] && trim($countCalls[2][0]) === trim($listCalls[2][0]),
        'Templates/Notice/'.$name.' cuenta y lista con el mismo filtro'
            .($counts === 1 && $lists === 1 ? ' ('.$countCalls[1][0].', '.trim($countCalls[2][0]).')' : ' — no se reconocen las dos llamadas'));
}
// t_6 y t_7 no consultan: fijan el filtro y delegan.
foreach(array('t_6.tpl' => 't_5.tpl', 't_7.tpl' => 't_2.tpl') as $name => $delegate) {
    $source = file_get_contents($root.'/Templates/Notice/'.$name);
    check(!preg_match('/mysql_query|mysqli_query|\$database->/', $source) && strpos($source, $delegate) !== false,
        'Templates/Notice/'.$name.' sólo fija el filtro e incluye a '.$delegate);
}
foreach(array('inbox.tpl', 'sent.tpl', 'archive.tpl') as $name) {
    $source = file_get_contents($root.'/Templates/Message/'.$name);
    check(strpos($source, 'mysql_num_rows') === false
            && substr_count($source, 'mysql_query(') === 2
            && strpos($source, 'SELECT COUNT(*) FROM $prefix WHERE $messageListWhere') !== false
            && strpos($source, 'SELECT * FROM $prefix WHERE $messageListWhere ORDER BY time DESC $limit') !== false,
        'Templates/Message/'.$name.' cuenta con COUNT(*) y lista con la misma condición');
}
// renderReports() reparte las pestañas como berichte.php; que siga siendo cierto.
$berichte = file_get_contents($root.'/berichte.php');
check(preg_match('/if\(\$reportFilter === 8\) \{\s*\$noticeSqlFilter = "and viewed = 0";\s*include\("Templates\/Notice\/t_2\.tpl"\);\s*\} else \{\s*include\("Templates\/Notice\/t_"\.\$reportFilter\."\.tpl"\);\s*\}\s*\} else \{\s*include\("Templates\/Notice\/all\.tpl"\);/', $berichte) === 1,
    'berichte.php reparte las pestañas como las dibuja este checker ("No leídos" es t_2 con `viewed = 0`)');

// =====================================================================================
section('I. El índice existe en los tres lugares, con las mismas columnas');
// =====================================================================================
$normalizeColumns = function($list) {
    return array_values(array_filter(array_map(function($column) {
        return trim($column, "` \t\r\n");
    }, explode(',', $list)), 'strlen'));
};
$want = implode(', ', $INDEX_COLUMNS);
check($liveIndex === $INDEX_COLUMNS,
    'la base viva tiene '.$INDEX_TABLE.'.'.$INDEX_NAME.' ('.$want.')'
        .(empty($liveIndex) ? ' — falta: aplicar tools/migrations.sql' : ' — tiene ('.implode(', ', $liveIndex).')'));
$migrations = file_get_contents($root.'/tools/migrations.sql');
$inMigration = preg_match('/ALTER\s+TABLE\s+s1_'.$INDEX_TABLE.'\s+ADD\s+INDEX\s+IF\s+NOT\s+EXISTS\s+'.$INDEX_NAME.'\s*\(([^)]*)\)/i', $migrations, $m)
    ? $normalizeColumns($m[1]) : null;
check($inMigration === $INDEX_COLUMNS,
    'tools/migrations.sql lo agrega con esas columnas'.($inMigration === null ? ' — no está' : ' ('.implode(', ', $inMigration).')'));
$installer = file_get_contents($root.'/install/data/sql.sql');
$inInstaller = null;
if(preg_match('/CREATE TABLE IF NOT EXISTS `%PREFIX%'.$INDEX_TABLE.'` \((.*?)\) ENGINE=/s', $installer, $block)
    && preg_match('/KEY `'.$INDEX_NAME.'` \(([^)]*)\)/', $block[1], $m)) {
    $inInstaller = $normalizeColumns($m[1]);
}
check($inInstaller === $INDEX_COLUMNS,
    'install/data/sql.sql lo crea igual en un mundo nuevo'.($inInstaller === null ? ' — no está' : ' ('.implode(', ', $inInstaller).')'));
// El orden de la lista tiene que ser el que el índice trae ya ordenado.
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(preg_match('/function getNoticeListPage\(.*?ORDER BY time DESC, id DESC LIMIT/s', $dbSource) === 1,
    'getNoticeListPage() ordena por las dos últimas columnas del índice (time DESC, id DESC)');

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Listas de informes y mensajes: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
