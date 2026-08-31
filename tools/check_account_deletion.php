<?php
/**
 * Borrar una cuenta: qué pasa con sus aldeas.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_account_deletion.php
 *
 * La regla oficial es corta y no tiene excepciones: cuando se cumple el plazo de borrado,
 * **todas** las aldeas del jugador desaparecen del mapa y dejan casillas vacías, y sus
 * tropas desaparecen con ellas. Ninguna aldea pasa a los natars, la capital tampoco.
 *
 * `Automation::clearDeleting()` tenía tres fallos que se tapaban entre sí, y por eso nadie
 * los había visto: en este mundo el camino nunca llegó a correr entero.
 *
 *   1. **El borrado de la aldea vivía FUERA del `foreach` de aldeas.** Usaba el `$village`
 *      que quedaba de la última vuelta, así que de una cuenta con seis aldeas se borraba la
 *      fila de `vdata` de una sola. Las otras cinco quedaban de fantasmas en el mapa: sin
 *      `fdata`, sin `units`, sin dueño vivo, y con su casilla ocupada para siempre.
 *   2. **La capital no se borraba nunca.** Se le intentaba pasar a los natars con
 *      `UPDATE vdata ... WHERE id`, y `vdata` no tiene columna `id`. Además de no ser lo
 *      que hace el original, el UPDATE fallaba en silencio.
 *   3. **`where from = ...` sin backticks.** `from` es palabra reservada de MySQL, así que
 *      esos dos DELETE no fallaban por la columna: fallaban por SINTAXIS. Las tropas que el
 *      jugador tenía reforzando a otros, y sus movimientos en curso, sobrevivían a la
 *      cuenta.
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real, igual que
 * check_village_conquest.php: el mundo de verdad no se toca.
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

global $database, $generator, $technology, $logging;
$generator = new GeneratorX();
$logging = new Logging();

$failures = array();
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        echo '[FALLA] '.$message.PHP_EOL;
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}
function q($sql) {
    global $database;
    $result = mysqli_query($database->connection, $sql);
    if($result === false) {
        fwrite(STDERR, 'SQL: '.mysqli_error($database->connection).PHP_EOL.$sql.PHP_EOL);
        exit(1);
    }
    return $result;
}
function scalar($sql) {
    $result = q($sql);
    $line = mysqli_fetch_row($result);
    return $line ? $line[0] : null;
}

// =====================================================================================
section('A. Lo que el código ya no puede volver a decir');
// =====================================================================================
$source = file_get_contents($root.'/GameEngine/Automation.php');
$start = strpos($source, 'private function clearDeleting()');
$end = strpos($source, 'private function ClearUser()');
$body = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';
check($body !== '', 'clearDeleting() existe');

check(strpos($body, "owner = 2") === false,
    'ninguna aldea se le pasa a los natars al borrarse la cuenta');
check(strpos($body, '$getvillage') === false,
    'ya no queda la variable de la última vuelta con la que se decidía si borrar o no');
check(strpos($body, '$database->addTech($village)') === false
    && strpos($body, '$database->addUnits($village)') === false,
    'y no se le vuelven a crear tablas a una aldea que se está borrando');

// `from` y `to` son palabras reservadas: sin backticks el SQL no compila.
foreach(array('from', 'to') as $reserved) {
    check(preg_match('/(where|and|or|set)\s+'.$reserved.'\s*=/i', $body) !== 1,
        'clearDeleting() escribe `'.$reserved.'` con backticks: es palabra reservada de MySQL');
}
// Y en todo el repo, que es donde el mismo error puede reaparecer.
$reservedHits = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach($rii as $file) {
    if(!$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if(strpos($relative, '.git/') === 0 || strpos($relative, 'gpack/') === 0) {
        continue;
    }
    if(!in_array(strtolower($file->getExtension()), array('php', 'tpl'), true)) {
        continue;
    }
    // Este archivo habla del bug, así que se nombra a sí mismo.
    if($relative === 'tools/check_account_deletion.php') {
        continue;
    }
    $text = file_get_contents($file->getPathname());
    foreach(array('from', 'to', 'desc', 'rank') as $reserved) {
        if(preg_match('/(?:where|and|or|set)\s+'.$reserved.'\s*=\s*[^=]/i', $text, $m)) {
            $reservedHits[] = $relative.' -> '.trim($m[0]);
        }
    }
}
check(count($reservedHits) === 0,
    'una columna con nombre de palabra reservada quedó sin backticks: '.implode(' | ', $reservedHits));

// =====================================================================================
section('B. Un borrado de cuenta de verdad, resuelto por el motor');
// =====================================================================================
$P = TB_PREFIX;
$tables = array();
$result = q("SHOW TABLES LIKE '".$P."%'");
while($line = mysqli_fetch_row($result)) {
    $name = substr($line[0], strlen($P));
    if($name === 'config') {
        continue;
    }
    $tables[] = $name;
}
foreach($tables as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
}

define('U_GONE', 9301);   // el que se borra
define('U_OTHER', 9302);  // un vecino, que no debe perder nada
define('V_CAP', 993001);  // la CAPITAL del que se borra
define('V_TWO', 993002);
define('V_THREE', 993003);
define('V_OTHER', 993010);

function resetDeletionWorld() {
    global $P, $tables;
    foreach($tables as $table) {
        q("DELETE FROM {$P}{$table}");
    }
    $now = time();
    q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES "
        ."(".U_GONE.",'sevá',1,0,3,0),(".U_OTHER.",'sequeda',2,0,3,0)");
    $tiles = array(V_CAP, V_TWO, V_THREE, V_OTHER);
    $values = array();
    $x = 1;
    foreach($tiles as $tile) {
        $values[] = "($tile,$x,1,3,0,1)";
        $x++;
    }
    q("INSERT INTO {$P}wdata (id,x,y,fieldtype,oasistype,occupied) VALUES ".implode(',', $values));
    q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) VALUES "
        ."(".V_CAP.",".U_GONE.",1,100,0,100,$now,$now,800,800),"
        ."(".V_TWO.",".U_GONE.",0,100,0,100,$now,$now,800,800),"
        ."(".V_THREE.",".U_GONE.",0,100,0,100,$now,$now,800,800),"
        ."(".V_OTHER.",".U_OTHER.",1,100,0,100,$now,$now,800,800)");
    foreach(array(V_CAP, V_TWO, V_THREE, V_OTHER) as $village) {
        q("INSERT INTO {$P}fdata (vref) VALUES ($village)");
        q("INSERT INTO {$P}units (vref) VALUES ($village)");
        q("INSERT INTO {$P}abdata (vref) VALUES ($village)");
        q("INSERT INTO {$P}tdata (vref) VALUES ($village)");
    }
    // Tropas del que se borra reforzando al vecino: en el oficial desaparecen con él.
    q("INSERT INTO {$P}enforcement (vref,`from`,u1) VALUES (".V_OTHER.",".V_TWO.",50)");
    // Tropas del vecino reforzando al que se borra: NO se las puede quedar el barrido.
    q("INSERT INTO {$P}enforcement (vref,`from`,u11) VALUES (".V_CAP.",".V_OTHER.",70)");
    // Un movimiento en curso del que se borra.
    q("INSERT INTO {$P}attacks (id,vref,t1,attack_type) VALUES (79001,".V_TWO.",25,3)");
    q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,ref2,`data`,endtime,proc) "
        ."VALUES (0,3,".V_TWO.",".V_OTHER.",79001,0,'0',".($now + 3600).",0)");
    // Y el pedido de borrado, ya vencido.
    q("INSERT INTO {$P}deleting (uid,timestamp) VALUES (".U_GONE.",".($now - 60).")");
}

resetDeletionWorld();
$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$clear = $reflection->getMethod('clearDeleting');
$clear->setAccessible(true);
@unlink($root.'/GameEngine/Prevention/cleardeleting.txt');
$clear->invoke($automation);

// --- Las aldeas -----------------------------------------------------------------------
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE owner = ".U_GONE) === 0,
    'no queda ninguna aldea del jugador borrado: las TRES desaparecen, no sólo la última');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE wref = ".V_CAP) === 0,
    'la capital también desaparece — en el original no pasa a los natars');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE owner = 2") === 0,
    'y no aparece ninguna aldea nueva a nombre de los natars');

// --- El mapa --------------------------------------------------------------------------
foreach(array(V_CAP => 'la capital', V_TWO => 'la segunda aldea', V_THREE => 'la tercera') as $tile => $label) {
    check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = $tile") === 0,
        'la casilla de '.$label.' queda libre para que otro se asiente');
}
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".V_OTHER) === 1,
    'y la casilla del vecino sigue ocupada');

// --- Las filas por aldea --------------------------------------------------------------
foreach(array('fdata', 'units', 'abdata', 'tdata') as $table) {
    check((int)scalar("SELECT COUNT(*) FROM {$P}{$table} WHERE vref IN ("
        .V_CAP.",".V_TWO.",".V_THREE.")") === 0,
        'no sobrevive ninguna fila de '.$table.' a las aldeas borradas');
    check((int)scalar("SELECT COUNT(*) FROM {$P}{$table} WHERE vref = ".V_OTHER) === 1,
        'y la fila de '.$table.' del vecino sigue intacta');
}

// --- Las tropas -----------------------------------------------------------------------
check((int)scalar("SELECT COUNT(*) FROM {$P}enforcement WHERE `from` IN ("
    .V_CAP.",".V_TWO.",".V_THREE.")") === 0,
    'las tropas del borrado que reforzaban a otro desaparecen con él (el DELETE era un error de sintaxis)');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 3 AND `from` IN ("
    .V_CAP.",".V_TWO.",".V_THREE.")") === 0,
    'sus ataques en curso desaparecen: eran tropas suyas');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE `to` IN ("
    .V_CAP.",".V_TWO.",".V_THREE.")") === 0,
    'y no queda ningún movimiento apuntando a una aldea que ya no existe');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE id = 79001") === 0,
    'la fila de tropas de ese movimiento no queda colgada');

// Lo que NO puede pasar es que el barrido se quede con las tropas de un tercero: el
// refuerzo del vecino sale de vuelta hacia su propia aldea, y ese movimiento tiene que
// sobrevivir aunque su origen ya no exista (es de dónde vuelven, no a dónde van).
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 4 AND `to` = ".V_OTHER) === 1,
    'el refuerzo del vecino que estaba en la aldea borrada vuelve a su casa');

// --- El vecino no puede perder nada ----------------------------------------------------
check((int)scalar("SELECT COUNT(*) FROM {$P}users WHERE id = ".U_OTHER) === 1,
    'el vecino sigue existiendo');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE wref = ".V_OTHER) === 1,
    'con su aldea');

// --- La cuenta ------------------------------------------------------------------------
check((int)scalar("SELECT COUNT(*) FROM {$P}users WHERE id = ".U_GONE) === 0,
    'la cuenta se borra');
check((int)scalar("SELECT COUNT(*) FROM {$P}deleting WHERE uid = ".U_GONE) === 0,
    'y el pedido de borrado se consume, para que el barrido no lo repita');

// --- Una cuenta de una sola aldea ------------------------------------------------------
resetDeletionWorld();
q("DELETE FROM {$P}vdata WHERE wref IN (".V_TWO.",".V_THREE.")");
@unlink($root.'/GameEngine/Prevention/cleardeleting.txt');
$clear->invoke($automation);
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE owner = ".U_GONE) === 0,
    'una cuenta de una sola aldea (su capital) también se borra entera');
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".V_CAP) === 0,
    'y libera su casilla');

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Borrado de cuenta: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
