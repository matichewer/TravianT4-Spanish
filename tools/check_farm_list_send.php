<?php
/**
 * El envío de la Lista de granjas (GameEngine/FarmList.php).
 *
 *   docker compose exec -T web php /var/www/html/tools/check_farm_list_send.php
 *
 * El envío vivía entero en Templates/a2b/startRaid.tpl. Se reescribió para que cueste
 * menos —seis consultas por saqueo en vez de veintiuna, ninguna por objetivo sin marcar— y
 * de paso dejó de hacer tres cosas mal. Este checker compara las dos versiones sobre los
 * mismos datos y fija lo que cambió a propósito:
 *
 *   A. Los mismos saqueos que mandaba la versión anterior: ataques, viajes y tropas, fila por fila.
 *   B. Cuesta menos: consultas contadas, y los objetivos sin marcar no cuestan nada.
 *   C. Ya no deja basura en `a2b`, la tabla de la confirmación del envío normal.
 *   D. Comprobar y descontar es una sola sentencia: un segundo envío no duplica tropas.
 *   E. El colono de teutones y galos se descuenta (se descontaba de una columna inexistente).
 *   F. Si el ataque no se puede crear, las tropas vuelven.
 *   G. La lista tiene que ser del jugador; un objetivo vacío no sale.
 *   H. build.php resuelve el envío antes de dibujar la lista, y la plantilla termina en exit.
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
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/unitdata.php";
include "GeneratorX.php";
require_once $root.'/GameEngine/FarmList.php';

global $database;
$generator = new GeneratorX();

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
// Sentencias que mandó $fn (lecturas y escrituras), contadas por la base.
function statements($fn) {
    $count = function() {
        $total = 0;
        $result = q("SHOW SESSION STATUS WHERE Variable_name IN ('Com_select','Com_insert','Com_update','Com_delete')");
        while($row = mysqli_fetch_row($result)) {
            $total += (int)$row[1];
        }
        return $total;
    };
    $before = $count();
    $value = $fn();
    return array('statements' => $count() - $before, 'value' => $value);
}

$P = TB_PREFIX;
foreach(array('farmlist', 'raidlist', 'units', 'vdata', 'users', 'wdata', 'attacks', 'movement', 'a2b', 'artefacts') as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
    if((int)scalar("SELECT COUNT(*) FROM {$P}{$table}") !== 0) {
        fwrite(STDERR, "La tabla {$P}{$table} no quedó tapada por su copia temporal; se aborta sin escribir nada.".PHP_EOL);
        exit(1);
    }
}

// -------------------------------------------------------------------------------------
// El mundo de prueba.
// -------------------------------------------------------------------------------------
define('U_ROMAN', 9801);    // el dueño de la lista, romano
define('U_TEUTON', 9802);   // otro jugador con su lista, teutón
define('U_VICTIM', 9803);
define('U_BANNED', 9804);   // access 0: no se lo ataca
define('U_HUNTER', 9805);   // access 9: tampoco
define('V_ROMAN', 930001);
define('V_TEUTON', 930002);
define('V_VICTIM', 930003);
define('V_VICTIM2', 930004);
define('V_BANNED', 930005);
define('V_HUNTER', 930006);
define('O_NEAR', 930010);   // un oasis: no tiene fila en vdata
define('O_FAR', 930011);
define('T_FAR', 930012);
define('L_ROMAN', 501);
define('L_TEUTON', 502);

$resetWorld = function() use ($P) {
    foreach(array('farmlist', 'raidlist', 'units', 'vdata', 'users', 'wdata', 'attacks', 'movement', 'a2b', 'artefacts') as $table) {
        q("DELETE FROM {$P}{$table}");
    }
    foreach(array(array(U_ROMAN, 'romano', 1, 2), array(U_TEUTON, 'teuton', 2, 2), array(U_VICTIM, 'victima', 3, 2),
                  array(U_BANNED, 'baneado', 1, 0), array(U_HUNTER, 'multihunter', 1, 9)) as $user) {
        q("INSERT INTO {$P}users (id, username, tribe, access) VALUES ($user[0], '$user[1]', $user[2], $user[3])");
    }
    foreach(array(V_ROMAN => U_ROMAN, V_TEUTON => U_TEUTON, V_VICTIM => U_VICTIM, V_VICTIM2 => U_VICTIM,
                  V_BANNED => U_BANNED, V_HUNTER => U_HUNTER) as $wref => $owner) {
        q("INSERT INTO {$P}vdata (wref, owner, name) VALUES ($wref, $owner, 'aldea')");
    }
    $coords = array(V_ROMAN => array(0, 0), V_TEUTON => array(40, 40), V_VICTIM => array(3, 4), V_VICTIM2 => array(-6, 8),
        V_BANNED => array(1, 1), V_HUNTER => array(2, 2), O_NEAR => array(0, 5), O_FAR => array(-30, 12), T_FAR => array(95, -98));
    foreach($coords as $wref => $xy) {
        q("INSERT INTO {$P}wdata (id, fieldtype, oasistype, x, y, occupied) VALUES ($wref, 3, 0, $xy[0], $xy[1], 1)");
    }
    // Romano: legionarios, pretorianos, caballería, arietes y colonos. Teutón: lo suyo.
    q("INSERT INTO {$P}units (vref, u1, u2, u5, u7, u10) VALUES (".V_ROMAN.", 100, 50, 10, 5, 3)");
    q("INSERT INTO {$P}units (vref, u11, u15, u20) VALUES (".V_TEUTON.", 60, 8, 6)");
    q("INSERT INTO {$P}farmlist (id, wref, owner, name) VALUES (".L_ROMAN.", ".V_ROMAN.", ".U_ROMAN.", 'lista')");
    q("INSERT INTO {$P}farmlist (id, wref, owner, name) VALUES (".L_TEUTON.", ".V_TEUTON.", ".U_TEUTON.", 'lista')");
    $slots = array(
        // id, lista, objetivo, t1..t10
        array(1, L_ROMAN, V_VICTIM,  array(1 => 40)),             // sale
        array(2, L_ROMAN, O_NEAR,    array(1 => 40, 2 => 10)),    // sale: un oasis no tiene dueño en vdata
        array(3, L_ROMAN, V_BANNED,  array(1 => 5)),              // no: cuenta baneada
        array(4, L_ROMAN, V_VICTIM2, array(1 => 40)),             // no: ya no quedan 40 legionarios
        array(5, L_ROMAN, O_FAR,     array(1 => 20, 7 => 2)),     // sale, con arietes
        array(6, L_ROMAN, V_HUNTER,  array(1 => 1)),              // no: multihunter
        array(7, L_ROMAN, T_FAR,     array(5 => 10)),             // sale: caballería, del otro lado del mapa
        array(8, L_ROMAN, V_VICTIM,  array(2 => 1)),              // SIN MARCAR
        array(9, L_ROMAN, V_VICTIM2, array()),                    // marcado pero vacío
        array(21, L_TEUTON, V_VICTIM, array(1 => 10, 10 => 2)),   // teutón con 2 colonos
        array(22, L_TEUTON, O_NEAR,   array(5 => 4)),
    );
    foreach($slots as $slot) {
        $troops = array();
        for($i = 1; $i <= 10; $i++) {
            $troops[] = isset($slot[3][$i]) ? (int)$slot[3][$i] : 0;
        }
        q("INSERT INTO {$P}raidlist (id, lid, towref, x, y, distance, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10)"
            ." VALUES ($slot[0], $slot[1], $slot[2], 0, 0, '0', ".implode(',', $troops).")");
    }
};
$romanPost = array('lid' => L_ROMAN, 'action' => 'startRaid');
foreach(array(1, 2, 3, 4, 5, 6, 7) as $marked) {
    $romanPost['slot'.$marked] = 'on';
}

// Lo que un envío deja en la base, sin las horas: de un viaje importa cuánto dura.
$snapshot = function() use ($P) {
    return array(
        'attacks'  => rowsOf("SELECT vref, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11, attack_type, ctar1, ctar2, spy, sethome FROM {$P}attacks ORDER BY id"),
        'movement' => rowsOf("SELECT sort_type, `from`, `to`, endtime - CAST(data AS UNSIGNED) AS duration, proc, send FROM {$P}movement ORDER BY moveid"),
        'refs'     => (int)scalar("SELECT COUNT(*) FROM {$P}movement m INNER JOIN {$P}attacks a ON a.id = m.ref"),
        'units'    => rowsOf("SELECT * FROM {$P}units ORDER BY vref"),
    );
};

/**
 * La versión anterior, tal cual estaba en Templates/a2b/startRaid.tpl. Dos cambios, ninguno
 * de fondo: no redirige, y `addA2b`/`getA2b` usan el mismo instante en vez de dos llamadas
 * a time() — con dos, si el reloj cambiaba de segundo en el medio el saqueo salía vacío, y
 * este checker fallaría una vez cada tanto por el defecto que se está reemplazando.
 */
function legacyStartRaid($database, $generator, $session, $post) {
    $unitarray = null;
    $lid = (int)$post['lid'];
    $tribe = (int)$session->tribe;
    $getFLData = $database->getFLData($lid);
    if(!is_array($getFLData) || (int)$getFLData['owner'] !== (int)$session->uid) {
        return false;
    }
    $sql = "SELECT * FROM ".TB_PREFIX."raidlist WHERE lid = ".$lid." order by id asc";
    $array = $database->query_return($sql);
    foreach($array as $row){
        $sql1 = mysql_fetch_array(mysql_query("SELECT * FROM ".TB_PREFIX."units WHERE vref = ".$getFLData['wref']));
        $sid = $row['id'];
        $wref = $row['towref'];
        $t1 = $row['t1'];$t2 = $row['t2'];$t3 = $row['t3'];$t4 = $row['t4'];$t5 = $row['t5'];
        $t6 = $row['t6'];$t7 = $row['t7'];$t8 = $row['t8'];$t9 = $row['t9'];$t10 = $row['t10'];
        $t11 = 0;
        $villageOwner = $database->getVillageField($wref,'owner');
        $userAccess = $database->getUserField($villageOwner,'access',0);
        if($userAccess != '0' && $userAccess != '8' && $userAccess != '9'){
        if($tribe == 1){ $uname = "u"; } elseif($tribe == 2){ $uname = "u1"; } elseif($tribe == 3){ $uname = "u2"; }
        if($tribe == 1){ $uname1 = "u1"; } elseif($tribe == 2){ $uname1 = "u2"; } elseif($tribe == 3){ $uname1 = "u3"; }
        if($tribe == 1){ $uname2 = ""; } elseif($tribe == 2){ $uname2 = "1"; } elseif($tribe == 3){ $uname2 = "2"; }
        if($sql1[$uname.'1']>=$t1 && $sql1[$uname.'2']>=$t2 && $sql1[$uname.'3']>=$t3 && $sql1[$uname.'4']>=$t4 && $sql1[$uname.'5']>=$t5 && $sql1[$uname.'6']>=$t6 && $sql1[$uname.'7']>=$t7 && $sql1[$uname.'8']>=$t8 && $sql1[$uname.'9']>=$t9 && $sql1[$uname1.'0']>=$t10 && $sql1['hero']>=$t11){
        if(isset($post['slot'.$sid]) && $post['slot'.$sid]=='on'){
            $ckey = $generator->generateRandStr(6);
            $stamp = time();
            $id = $database->addA2b($ckey,$stamp,$wref,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11,4);
            $data = $database->getA2b($ckey, $stamp);
            $eigen = $database->getCoor($getFLData['wref']);
            $from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);
            $ander = $database->getCoor($data['to_vid']);
            $to = array('x'=>$ander['x'], 'y'=>$ander['y']);
            $speeds = array();
            for($i=1;$i<=10;$i++){
                if ($data['u'.$i]){
                    if($data['u'.$i] != '' && $data['u'.$i] > 0){
                        if($unitarray) { reset($unitarray); }
                        $unitarray = $GLOBALS["u".(($tribe-1)*10+$i)];
                        $speeds[] = $unitarray['speed'];
                    }
                }
            }
            $time = $generator->procDistanceTime($from, $to, min($speeds), 1, 0, 0,
                artefactTroopSpeedFactor($database,$getFLData['owner'],$getFLData['wref']));
            if($data['u7'] > 0){ $ctar1 = 99; }else{ $ctar1 = 0; }
            $ctar2 = 0;
            $reference = $database->addAttack(($getFLData['wref']),$data['u1'],$data['u2'],$data['u3'],$data['u4'],$data['u5'],$data['u6'],$data['u7'],$data['u8'],$data['u9'],$data['u10'],$data['u11'],$data['type'],$ctar1,$ctar2,0);
            $database->modifyUnit($getFLData['wref'], $uname2.'1', $data['u1'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'2', $data['u2'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'3', $data['u3'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'4', $data['u4'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'5', $data['u5'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'6', $data['u6'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'7', $data['u7'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'8', $data['u8'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'9', $data['u9'], 0);
            $database->modifyUnit($getFLData['wref'], $uname2.'10', $data['u10'], 0);
            $database->modifyUnit($getFLData['wref'], 'hero', $data['u11'], 0);
            $sentAt = time();
            $database->addMovement(3,$getFLData['wref'],$data['to_vid'],$reference,$sentAt,($time+$sentAt));
        }
        }
        }
    }
    return true;
}
$roman = (object)array('uid' => U_ROMAN, 'tribe' => 1);
$teuton = (object)array('uid' => U_TEUTON, 'tribe' => 2);
$send = function($session, $post, $db = null) use ($database, $generator) {
    return farmListSendRaids($db === null ? $database : $db, $generator, $session->uid, $session->tribe, $post['lid'], $post);
};

// =====================================================================================
section('A. Los mismos saqueos que mandaba la versión anterior');
// =====================================================================================
$resetWorld();
$legacyRun = statements(function() use ($database, $generator, $roman, $romanPost) {
    return legacyStartRaid($database, $generator, $roman, $romanPost);
});
$legacy = $snapshot();
$legacyA2b = (int)scalar("SELECT COUNT(*) FROM {$P}a2b");

$resetWorld();
$newRun = statements(function() use ($send, $roman, $romanPost) { return $send($roman, $romanPost); });
$new = $snapshot();

check(count($legacy['attacks']) === 4,
    'la versión anterior manda 4 de los 7 objetivos marcados (mandó '.count($legacy['attacks']).'): los datos de prueba ejercitan los descartes');
check($new['attacks'] === $legacy['attacks'], 'los ataques creados son los mismos, tropa por tropa');
check($new['movement'] === $legacy['movement'], 'los viajes son los mismos: origen, destino y duración');
check($new['units'] === $legacy['units'], 'y las tropas que quedan en la aldea, también');
check($new['refs'] === 4 && $legacy['refs'] === 4, 'cada viaje apunta a su ataque');
check($newRun['value'] === array('sent' => 4, 'skipped' => 3),
    'la función informa 4 enviados y 3 salteados ('.json_encode($newRun['value']).')');

// Lo que tiene que haber pasado, dicho en claro además de comparado.
$byTarget = array();
foreach(rowsOf("SELECT m.`to`, a.t1, a.t2, a.t5, a.t7, a.attack_type, a.ctar1 FROM {$P}movement m INNER JOIN {$P}attacks a ON a.id = m.ref") as $row) {
    $byTarget[(int)$row['to']] = $row;
}
check(isset($byTarget[V_VICTIM], $byTarget[O_NEAR], $byTarget[O_FAR], $byTarget[T_FAR]) && count($byTarget) === 4,
    'salen la aldea, los dos oasis y la casilla lejana');
check(!isset($byTarget[V_BANNED]) && !isset($byTarget[V_HUNTER]), 'no se ataca a la cuenta baneada ni al multihunter');
check(!isset($byTarget[V_VICTIM2]), 'ni sale el objetivo para el que ya no alcanzaban los legionarios');
check((int)$byTarget[O_FAR]['attack_type'] === 4 && (int)$byTarget[O_FAR]['ctar1'] === 99 && (int)$byTarget[V_VICTIM]['ctar1'] === 0,
    'son asaltos (tipo 4), y el que lleva arietes conserva su marca');
$home = rowsOf("SELECT u1, u2, u5, u7, u10 FROM {$P}units WHERE vref = ".V_ROMAN);
check($home[0] === array('u1' => '0', 'u2' => '40', 'u5' => '0', 'u7' => '3', 'u10' => '3'),
    'en casa quedan 0 legionarios, 40 pretorianos, 0 de caballería, 3 arietes y los 3 colonos');

// =====================================================================================
section('B. Cuesta menos');
// =====================================================================================
check($newRun['statements'] <= 2 + 2 + 6 * 7,
    'el envío nuevo manda '.$newRun['statements'].' sentencias para 7 marcados y 4 enviados (la versión anterior: '.$legacyRun['statements'].')');
check($newRun['statements'] * 2 <= $legacyRun['statements'],
    'menos de la mitad que antes');
echo '  9 objetivos, 7 marcados, 4 enviados: '.$legacyRun['statements'].' sentencias antes, '.$newRun['statements'].' ahora'.PHP_EOL;
$resetWorld();
$nothing = statements(function() use ($send, $roman) { return $send($roman, array('lid' => L_ROMAN)); });
check($nothing['statements'] <= 2 && $nothing['value'] === array('sent' => 0, 'skipped' => 0),
    'sin nada marcado no se toca ningún objetivo: '.$nothing['statements'].' sentencias (antes, tres por objetivo de la lista)');
$one = statements(function() use ($send, $roman) { return $send($roman, array('lid' => L_ROMAN, 'slot1' => 'on')); });
check($one['statements'] <= 10 && $one['value']['sent'] === 1,
    'un solo saqueo marcado en una lista de 9: '.$one['statements'].' sentencias');

// =====================================================================================
section('C. Ya no deja basura en a2b');
// =====================================================================================
check($legacyA2b === 4, 'la versión anterior dejaba una fila en a2b por saqueo, para siempre ('.$legacyA2b.')');
$resetWorld();
$send($roman, $romanPost);
check((int)scalar("SELECT COUNT(*) FROM {$P}a2b") === 0, 'la nueva no escribe en a2b');

// =====================================================================================
section('D. Un segundo envío no duplica tropas');
// =====================================================================================
// El formulario enviado dos veces (doble clic, F5 sobre el POST). Quedaron 40 pretorianos
// y 3 arietes: nada de lo marcado se puede volver a mandar.
$afterFirst = $snapshot();
$second = $send($roman, $romanPost);
$afterSecond = $snapshot();
check($second === array('sent' => 0, 'skipped' => 7), 'el segundo envío no manda nada ('.json_encode($second).')');
check($afterSecond === $afterFirst, 'ni toca ataques, viajes o tropas');
// Tropas justas para uno solo de dos objetivos iguales.
$resetWorld();
q("UPDATE {$P}units SET u1 = 40 WHERE vref = ".V_ROMAN);
$tight = $send($roman, array('lid' => L_ROMAN, 'slot1' => 'on', 'slot4' => 'on'));
check($tight === array('sent' => 1, 'skipped' => 1) && (int)scalar("SELECT u1 FROM {$P}units WHERE vref = ".V_ROMAN) === 0,
    'con tropas para un solo objetivo sale uno, y la columna queda en 0, no desbordada');
check((int)scalar("SELECT SUM(t1) FROM {$P}attacks") === 40, 'nunca viajan más legionarios de los que había');

// =====================================================================================
section('E. El colono de teutones y galos se descuenta');
// =====================================================================================
check(farmListUnitColumn(1, 1) === 'u1' && farmListUnitColumn(1, 10) === 'u10'
        && farmListUnitColumn(2, 1) === 'u11' && farmListUnitColumn(2, 10) === 'u20'
        && farmListUnitColumn(3, 9) === 'u29' && farmListUnitColumn(3, 10) === 'u30',
    'la décima unidad de cada tribu es u10, u20 y u30');
$teutonPost = array('lid' => L_TEUTON, 'slot21' => 'on', 'slot22' => 'on');
$resetWorld();
legacyStartRaid($database, $generator, $teuton, $teutonPost);
$legacySettlersHome = (int)scalar("SELECT u20 FROM {$P}units WHERE vref = ".V_TEUTON);
$legacySettlersOut = (int)scalar("SELECT SUM(t10) FROM {$P}attacks");
check($legacySettlersOut === 2 && $legacySettlersHome === 6,
    'la versión anterior mandaba 2 colonos teutones y los dejaba también en casa ('.$legacySettlersHome.' de 6): los descontaba de `u110`');
$resetWorld();
$teutonRun = $send($teuton, $teutonPost);
check($teutonRun === array('sent' => 2, 'skipped' => 0), 'la nueva manda los dos objetivos del teutón');
$teutonHome = rowsOf("SELECT u11, u15, u20 FROM {$P}units WHERE vref = ".V_TEUTON);
check($teutonHome[0] === array('u11' => '50', 'u15' => '4', 'u20' => '4'),
    'y descuenta todo lo que salió, colonos incluidos (quedan '.json_encode($teutonHome[0]).')');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE vref = ".V_TEUTON." AND t1 = 10 AND t10 = 2") === 1,
    'el ataque lleva los 10 de infantería y los 2 colonos en sus posiciones');

// =====================================================================================
section('F. Si el ataque no se puede crear, las tropas vuelven');
// =====================================================================================
// Misma conexión que $database (las tablas temporales son de la conexión), pero el viaje falla.
class FarmListFailingMovementDB extends mysqli_DB {
    function __construct() {}
    function addMovement($type, $from, $to, $ref, $data, $endtime, $send = 1, $wood = 0, $clay = 0, $iron = 0, $crop = 0, $ref2 = 0) {
        return false;
    }
}
$failing = new FarmListFailingMovementDB();
$failing->connection = $database->connection;
$resetWorld();
$unitsBefore = rowsOf("SELECT * FROM {$P}units ORDER BY vref");
$failed = $send($roman, $romanPost, $failing);
check($failed['sent'] === 0, 'ningún saqueo se da por enviado ('.json_encode($failed).')');
check(rowsOf("SELECT * FROM {$P}units ORDER BY vref") === $unitsBefore, 'las tropas vuelven a la aldea, todas');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks") === 0 && (int)scalar("SELECT COUNT(*) FROM {$P}movement") === 0,
    'y no queda un ataque sin su viaje');

// =====================================================================================
section('G. La lista es del jugador, y un objetivo vacío no sale');
// =====================================================================================
$resetWorld();
$before = $snapshot();
$foreign = $send($roman, array('lid' => L_TEUTON, 'slot21' => 'on', 'slot22' => 'on'));
check($foreign === false && $snapshot() === $before,
    'la lista de otro jugador no se puede disparar: no sale nada con sus tropas ni con las propias');
check($send($roman, array('lid' => 999999, 'slot1' => 'on')) === false && $send($roman, array('lid' => 0)) === false,
    'ni una lista que no existe');
$empty = $send($roman, array('lid' => L_ROMAN, 'slot9' => 'on'));
check($empty === array('sent' => 0, 'skipped' => 1) && (int)scalar("SELECT COUNT(*) FROM {$P}attacks") === 0,
    'un objetivo sin tropas asignadas no crea un ataque vacío');
check($send($roman, array('lid' => L_ROMAN, 'slot8' => 'off', 'slot1' => '1')) === array('sent' => 0, 'skipped' => 0),
    'sólo cuenta como marcado el valor que manda el formulario (`on`)');

// =====================================================================================
section('H. build.php resuelve el envío antes de dibujar la lista');
// =====================================================================================
$template = file_get_contents($root.'/Templates/a2b/startRaid.tpl');
$build = file_get_contents($root.'/build.php');
$engine = file_get_contents($root.'/GameEngine/FarmList.php');
check(strpos($template, 'farmListSendRaids(') !== false && strpos($template, '$session->tribe') !== false,
    'la plantilla delega en farmListSendRaids() con la tribu de la sesión, no la del formulario');
check(preg_match('/header\("Location: build\.php\?id=39&t=99"\);\s*exit;\s*$/', $template) === 1,
    'y termina en exit: sin él build.php seguía dibujando la página para una respuesta que se descarta');
check(substr_count($build, 'Templates/a2b/startRaid.tpl') === 1, 'build.php la incluye en un solo lugar');
$dispatch = strpos($build, 'Templates/a2b/startRaid.tpl');
$render = strpos($build, 'include($tabTemplate);');
check($dispatch !== false && $render !== false && $dispatch < $render,
    'y antes de incluir la plantilla de la pestaña, que es la que dibuja la lista objetivo por objetivo');
check(preg_match('/\$_GET\[\'t\'\] == 99 && \$session->goldclub == 1\s*&& isset\(\$_POST\[\'action\'\]\) && \$_POST\[\'action\'\] == \'startRaid\'\) \{\s*if\(\$session->access != BANNED\)\{/', $build) === 1,
    'con las mismas condiciones de antes: pestaña 99, Club de Oro y cuenta no baneada');
check(strpos($engine, 'deductUnitsIfAvailable(') !== false && strpos($engine, 'addA2b') === false && substr_count($engine, 'modifyUnit(') === 1,
    'el motor descuenta con deductUnitsIfAvailable(), no pasa por a2b y sólo usa modifyUnit() para devolver');
check(strpos($engine, 'artefactTroopSpeedFactor(') !== false,
    'y la velocidad sigue saliendo de artefactTroopSpeedFactor(), como en el punto de reunión');

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Envío de la Lista de granjas: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
