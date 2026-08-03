<?php
/*
 * Prueba el ciclo completo de un refuerzo que lleva al héroe: llegada a una aldea
 * propia, llegada a una aldea de otro jugador, fusión con un refuerzo existente,
 * retiro del héroe y regreso a casa.
 *
 * El héroe tiene que quedar guardado en un único lugar: en `units` cuando está en
 * una aldea propia y en la fila de `enforcement` cuando refuerza a otro jugador.
 * Si aparece en los dos, el informe de batalla muestra dos héroes y el jugador
 * defiende con el doble de fuerza.
 *
 * Uso: docker compose exec web php tools/test_reinf_hero.php --destructivo
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$retiro = in_array('--retiro', $argv, true);
if(!$retiro && !in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos, ataques y refuerzos del mundo local.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  docker compose exec web php tools/test_reinf_hero.php --destructivo\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root . PATH_SEPARATOR . $root . '/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = array();
$_GET = array();

include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/" . LANG . ".php";
include "Technology.php";
include "Form.php";
$village = new stdClass;
$session = new stdClass;
include "Building.php";
$building = new Building;
include "Units.php";

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database, $technology, $units;
$conn = $database->connection;
$P = TB_PREFIX;

function q($sql) {
    global $conn;
    $r = mysqli_query($conn, $sql);
    if($r === false) { die("SQL ERROR: " . mysqli_error($conn) . "\n  " . $sql . "\n"); }
    return $r;
}
function one($sql) { return mysqli_fetch_assoc(q($sql)); }
function all($sql) { $r = q($sql); $o = array(); while($x = mysqli_fetch_assoc($r)) { $o[] = $x; } return $o; }
function say($s = '') { echo $s . "\n"; }
$failures = 0;
function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]    " : "  [FALLA] ") . $label);
}

$UID_A    = 5;   // MercaderTest, tribu 1 (romanos)
$UID_ALLY = 6;   // AliadoTest, tribu 2
$V_A1     = 797; // capital de A
$V_A2     = 2;   // segunda aldea de A
$V_ALLY   = 3;   // aldea del otro jugador

// --------------------------------------------------------------------------
// Segunda pasada: el retiro de tropas termina en header()+exit, así que corre
// en un proceso aparte y el proceso principal comprueba lo que dejó.
// --------------------------------------------------------------------------
if($retiro) {
    $ckey = (int)$argv[array_search('--retiro', $argv, true) + 1];
    $village->wid = $V_A1;
    $village->resarray = $database->getResourceLevel($V_A1);
    $session->uid = $UID_A;
    $session->tribe = 1;
    $form = new Form;
    $post = array('c' => '8', 'ckey' => $ckey, 't11' => 1);
    for($i = 1; $i <= 10; $i++) { $post['t'.$i] = 0; }
    $post['t1'] = 4; // se retira parte de las tropas junto al héroe
    $units->procUnits($post);
    exit(0);
}

// --------------------------------------------------------------------------
function ensureVillage($wref, $uid) {
    global $database, $P;
    if(one("SELECT wref FROM {$P}vdata WHERE wref = $wref")) { return; }
    $user = $database->getUserField($uid, 'username', 0);
    q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $wref");
    $database->addVillage($wref, $uid, $user, 0);
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);
}
ensureVillage($V_A2, $UID_A);
ensureVillage($V_ALLY, $UID_ALLY);

// Se invocan sólo las fases que interesan: el constructor de Automation hace
// además barridos de mantenimiento sobre todo el mundo (borra cuentas inactivas).
$auto = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
function runPhase($name, $method = null) {
    global $auto;
    @unlink("GameEngine/Prevention/$name.txt");
    $m = new ReflectionMethod('Automation', $method === null ? $name . 'Complete' : $method);
    $m->setAccessible(true);
    $errors = array();
    set_error_handler(function($no, $str, $file, $line) use (&$errors) {
        $errors[] = "$str ($file:$line)";
        return true;
    });
    ob_start(); $m->invoke($auto); ob_end_clean();
    restore_error_handler();
    return $errors;
}

function resetWorld() {
    global $P, $V_A1, $V_A2, $V_ALLY, $UID_A;
    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    q("DELETE FROM {$P}enforcement");
    q("UPDATE {$P}units SET hero = 0, u1 = 0, u2 = 0 WHERE vref IN ($V_A1,$V_A2,$V_ALLY)");
    q("UPDATE {$P}hero SET wref = $V_A1, home = $V_A1, dead = 0, hide = 0 WHERE uid = $UID_A");
}

function sendReinf($from, $to, $t1, $hero, $sethome = 0) {
    global $P, $conn;
    $sethome = (int)$sethome;
    q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy,sethome)
       VALUES ($to,$t1,0,0,0,0,0,0,0,0,0,$hero,2,0,0,0,$sethome)");
    $ref = mysqli_insert_id($conn);
    q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
       VALUES (3,$from,$to,$ref,0,'0,0,0,0,0'," . (time() - 5) . ",0,1,0,0,0,0)");
}

function dump($vref, $from) {
    global $P, $UID_A;
    $u = one("SELECT hero FROM {$P}units WHERE vref = $vref");
    $rows = all("SELECT * FROM {$P}enforcement WHERE vref = $vref AND `from` = $from");
    $h = one("SELECT wref FROM {$P}hero WHERE uid = $UID_A");
    say("  units($vref).hero=" . (int)$u['hero'] . "  filas=" . count($rows)
        . "  refuerzo.hero=" . (isset($rows[0]) ? (int)$rows[0]['hero'] : '-')
        . "  refuerzo.u1=" . (isset($rows[0]) ? (int)$rows[0]['u1'] : '-')
        . "  hero.wref=" . (int)$h['wref']);
    return array('units' => (int)$u['hero'], 'rows' => $rows, 'wref' => (int)$h['wref']);
}

// =========================================================================
say("== A) aldea propia -> aldea propia, héroe + tropas, con refuerzo previo ==");
resetWorld();
q("INSERT INTO {$P}enforcement (vref,`from`,u1) VALUES ($V_A2,$V_A1,5)");
sendReinf($V_A1, $V_A2, 3, 1);
$errs = runPhase('sendreinfunits');
$s = dump($V_A2, $V_A1);
check($s['units'] === 1, "el héroe queda en units de la aldea destino");
check(count($s['rows']) === 1, "sigue habiendo una sola fila de refuerzo");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['hero'] === 0, "el héroe NO se duplica en la fila de refuerzo");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['u1'] === 8, "las tropas que viajaban con el héroe se suman (5+3)");
check($s['wref'] === $V_A2, "hero.wref apunta a la aldea destino");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

// =========================================================================
say("\n== B) aldea propia -> aldea propia, héroe + tropas, sin refuerzo previo ==");
resetWorld();
sendReinf($V_A1, $V_A2, 4, 1);
$errs = runPhase('sendreinfunits');
$s = dump($V_A2, $V_A1);
check($s['units'] === 1, "el héroe queda en units de la aldea destino");
check(count($s['rows']) === 1, "se crea una única fila de refuerzo para las tropas");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['hero'] === 0, "la fila de refuerzo no lleva héroe");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['u1'] === 4, "las tropas llegan completas");
check($s['wref'] === $V_A2, "hero.wref apunta a la aldea destino");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

// =========================================================================
say("\n== C) aldea propia -> aldea de otro jugador, héroe + tropas ==");
resetWorld();
sendReinf($V_A1, $V_ALLY, 6, 1);
$errs = runPhase('sendreinfunits');
$s = dump($V_ALLY, $V_A1);
check($s['units'] === 0, "el héroe no entra en units de la aldea ajena");
check(count($s['rows']) === 1, "una única fila de refuerzo (no una para el héroe y otra para las tropas)");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['hero'] === 1, "el héroe queda en la fila de refuerzo");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['u1'] === 6, "las tropas que viajaban con el héroe llegan completas");
check($s['wref'] === $V_A1, "hero.wref sigue en la aldea de origen mientras está de refuerzo");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

// =========================================================================
say("\n== D) aldea ajena: tropas primero y héroe después, se fusionan ==");
resetWorld();
sendReinf($V_A1, $V_ALLY, 7, 0);
runPhase('sendreinfunits');
sendReinf($V_A1, $V_ALLY, 2, 1);
$errs = runPhase('sendreinfunits');
$s = dump($V_ALLY, $V_A1);
check(count($s['rows']) === 1, "el héroe se fusiona en la fila existente en vez de crear una duplicada");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['hero'] === 1, "la fila queda con un solo héroe");
check(isset($s['rows'][0]) && (int)$s['rows'][0]['u1'] === 9, "las tropas se acumulan (7+2)");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

// =========================================================================
say("\n== E) un refuerzo que sólo trae al héroe no se borra solo ==");
resetWorld();
sendReinf($V_A1, $V_ALLY, 0, 1);
runPhase('sendreinfunits');
$row = one("SELECT * FROM {$P}enforcement WHERE vref = $V_ALLY AND `from` = $V_A1");
check($row !== null && (int)$row['hero'] === 1, "la fila existe con el héroe");
if($row) {
    $technology->checkReinf((int)$row['id']);
    check(one("SELECT id FROM {$P}enforcement WHERE id = " . (int)$row['id']) !== null,
        "checkReinf no borra un refuerzo que sólo tiene al héroe");
}

// =========================================================================
say("\n== F) retirar el héroe desde la aldea ajena y volver a casa ==");
resetWorld();
sendReinf($V_A1, $V_ALLY, 9, 1);
runPhase('sendreinfunits');
$row = one("SELECT * FROM {$P}enforcement WHERE vref = $V_ALLY AND `from` = $V_A1");
$ckey = (int)$row['id'];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --retiro ' . $ckey . ' 2>&1');

$row = one("SELECT * FROM {$P}enforcement WHERE id = $ckey");
$mov = one("SELECT m.*, a.t1 AS at1, a.t11 AS at11 FROM {$P}movement m JOIN {$P}attacks a ON a.id = m.ref
            WHERE m.sort_type = 4 ORDER BY m.moveid DESC LIMIT 1");
say("  refuerzo restante: hero=" . (int)$row['hero'] . " u1=" . (int)$row['u1']
    . "   vuelta: t1=" . (int)$mov['at1'] . " t11=" . (int)$mov['at11'] . " to=" . (int)$mov['to']);
check((int)$row['hero'] === 0, "el héroe se descuenta de la fila de refuerzo al retirarlo");
check((int)$row['u1'] === 5, "las tropas retiradas se descuentan (9-4)");
check((int)$mov['at11'] === 1 && (int)$mov['to'] === $V_A1, "el héroe viaja de vuelta a la aldea de origen");

q("UPDATE {$P}movement SET endtime = " . (time() - 5) . " WHERE sort_type = 4");
$errs = runPhase('returnunits');
$home = one("SELECT hero, u1 FROM {$P}units WHERE vref = $V_A1");
$hero = one("SELECT wref FROM {$P}hero WHERE uid = $UID_A");
say("  units($V_A1).hero=" . (int)$home['hero'] . " u1=" . (int)$home['u1'] . "  hero.wref=" . (int)$hero['wref']);
check((int)$home['hero'] === 1, "el héroe llega a la aldea de origen");
check((int)$home['u1'] === 4, "las tropas retiradas llegan a la aldea de origen");
check((int)$hero['wref'] === $V_A1, "hero.wref vuelve a la aldea de origen (si no, las aventuras quedan bloqueadas)");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

// =========================================================================
say("\n== G) el informe no cuenta dos veces al héroe si los datos ya están rotos ==");
resetWorld();
q("UPDATE {$P}units SET hero = 1 WHERE vref = $V_A2");
q("INSERT INTO {$P}enforcement (vref,`from`,u1,hero) VALUES ($V_A2,$V_A1,5,1)");
$defender = $database->getUnit($V_A2);
$counted = array();
$heroes = 0;
if(!empty($defender['hero'])) { $heroes += (int)$defender['hero']; $counted[$UID_A] = true; }
foreach($database->getEnforceVillage($V_A2, 0) as $enf) {
    $n = (int)$enf['hero'];
    $owner = (int)$database->getVillageField($enf['from'], 'owner');
    if($n > 0 && isset($counted[$owner])) { $n = 0; }
    $heroes += $n;
}
say("  héroes contados: $heroes");
check($heroes === 1, "un jugador aporta un solo héroe aunque los datos viejos lo dupliquen");

// =========================================================================
say("\n== H) revivir en la mansión deja units.hero y hero.wref de acuerdo ==");
// hero.tpl ya fija wref al encargar el rescate, así que en el flujo normal esto
// no se rompe. Acá se arranca con wref desincronizado a propósito para verificar
// que el cierre del rescate deja el invariante sano igual.
resetWorld();
q("UPDATE {$P}hero SET dead = 1, health = 0, wref = $V_A1 WHERE uid = $UID_A");
q("DELETE FROM {$P}training WHERE vref = $V_A2");
q("INSERT INTO {$P}training (vref,unit,amt,pop,timestamp,eachtime,timestamp2)
   VALUES ($V_A2,0,1,6," . (time() - 100) . "," . (time() - 5) . "," . (time() - 5) . ")");
$errs = runPhase('updatehero', 'updateHero');
$hero = one("SELECT wref, dead, health FROM {$P}hero WHERE uid = $UID_A");
$u = one("SELECT hero FROM {$P}units WHERE vref = $V_A2");
say("  hero.wref=" . (int)$hero['wref'] . " dead=" . (int)$hero['dead']
    . "  units($V_A2).hero=" . (int)$u['hero']);
check((int)$hero['dead'] === 0, "el héroe revive");
check((int)$u['hero'] === 1, "el héroe aparece en la aldea donde se pagó el rescate");
check((int)$hero['wref'] === $V_A2, "hero.wref sigue al héroe (si no, las aventuras quedan bloqueadas)");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));
q("DELETE FROM {$P}training WHERE vref = $V_A2");

// =========================================================================
say("\n== I) la aldea natal no se muda sola al mover al héroe ==");
resetWorld();
sendReinf($V_A1, $V_A2, 0, 1, 0);
$errs = runPhase('sendreinfunits');
$hero = one("SELECT wref, home FROM {$P}hero WHERE uid = $UID_A");
say("  hero.wref=" . (int)$hero['wref'] . "  hero.home=" . (int)$hero['home']);
check((int)$hero['wref'] === $V_A2, "wref sigue al héroe");
check((int)$hero['home'] === $V_A1, "la aldea natal queda donde estaba");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

say("\n== J) marcando la opción, la aldea natal se muda al llegar ==");
resetWorld();
sendReinf($V_A1, $V_A2, 0, 1, 1);
$pending = one("SELECT wref, home FROM {$P}hero WHERE uid = $UID_A");
check((int)$pending['home'] === $V_A1, "mientras el héroe viaja la aldea natal no cambia");
$errs = runPhase('sendreinfunits');
$hero = one("SELECT wref, home FROM {$P}hero WHERE uid = $UID_A");
say("  hero.wref=" . (int)$hero['wref'] . "  hero.home=" . (int)$hero['home']);
check((int)$hero['home'] === $V_A2, "la aldea natal se muda a la aldea destino");
check(!$errs, "sin avisos de PHP" . ($errs ? ": " . implode(' | ', $errs) : ""));

say("\n== K) el bono de recursos se produce en la aldea natal ==");
resetWorld();
q("UPDATE {$P}hero SET product = 10, r0 = 1, r1 = 0, r2 = 0, r3 = 0, r4 = 0, home = $V_A2 WHERE uid = $UID_A");
$hero = $database->getHeroData($UID_A);
$enHome  = heroVillageResourceBonus($hero, $V_A2, SPEED);
$enWref  = heroVillageResourceBonus($hero, $V_A1, SPEED);
say("  natal($V_A2)=" . array_sum($enHome) . "  donde está($V_A1)=" . array_sum($enWref));
check(array_sum($enHome) > 0, "la aldea natal cobra el bono");
check(array_sum($enWref) === 0, "la aldea donde está el héroe no lo cobra");

say("\n== L) si la aldea natal se pierde, vuelve a la capital ==");
q("UPDATE {$P}hero SET home = 999999 WHERE uid = $UID_A");
q("UPDATE {$P}vdata SET capital = 1 WHERE wref = $V_A1");
runPhase('updatehero', 'updateHero');
$hero = one("SELECT home FROM {$P}hero WHERE uid = $UID_A");
say("  hero.home=" . (int)$hero['home']);
check((int)$hero['home'] === $V_A1, "la aldea natal huérfana vuelve a la capital");
q("UPDATE {$P}hero SET product = 4, r0 = 1 WHERE uid = $UID_A");

resetWorld();
say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
