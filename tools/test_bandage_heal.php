<?php
/*
 * Prueba las vendas del héroe: cuánto revive, qué descuenta de la bolsa, desde dónde y
 * a qué velocidad vuelven las tropas revividas, y que la curación de un ataque no se
 * filtre al siguiente ataque de la misma tanda.
 * Uso: php tools/test_bandage_heal.php --destructivo
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos, ataques, informes y tropas del mundo local.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_bandage_heal.php --destructivo\n");
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

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database, $generator, $technology, $battle;

$conn = $database->connection;
function q($sql) {
    global $conn;
    $r = mysqli_query($conn, $sql);
    if($r === false) { die("SQL ERROR: " . mysqli_error($conn) . "\n  " . $sql . "\n"); }
    return $r;
}
function one($sql) { return mysqli_fetch_assoc(q($sql)); }
function say($s = '') { echo $s . "\n"; }

$P = TB_PREFIX;
$failures = 0;
function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

// El escenario se limpia pase lo que pase, también si la prueba corta antes de tiempo.
register_shutdown_function(function() use ($P) {
    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    q("DELETE FROM {$P}heroitems WHERE uid IN (5,6)");
    q("UPDATE {$P}heroinventory SET bag = 0 WHERE uid IN (5,6)");
    q("UPDATE {$P}units SET " . implode(',', array_map(function($i){ return "u$i = 0"; }, range(1, 50)))
      . ", hero = 0 WHERE vref IN (797,3,2)");
});

// Atacante A: romano, aldea 797, con vendas. Atacante B: teutón, aldea 3, sin vendas.
$UID_A = 5;  $VILL_A = 797; $TARGET_A = 3;
$UID_B = 6;  $VILL_B = 3;   $TARGET_B = 2;
$BANDAGES = 60;
$SENT = 100;

q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");
q("DELETE FROM {$P}enforcement");
q("DELETE FROM {$P}ndata WHERE uid IN ($UID_A,$UID_B)");
q("DELETE FROM {$P}heroitems WHERE uid IN ($UID_A,$UID_B)");
q("UPDATE {$P}heroinventory SET bag = 0 WHERE uid IN ($UID_A,$UID_B)");
q("UPDATE {$P}hero SET dead = 0, health = 100 WHERE uid IN ($UID_A,$UID_B)");

// Defensas que garantizan bajas al atacante sin matarle el héroe.
q("UPDATE {$P}units SET " . implode(',', array_map(function($i){ return "u$i = 0"; }, range(1, 50)))
  . ", hero = 0 WHERE vref IN ($VILL_A,$VILL_B,$TARGET_B)");
// B también tiene que ganar perdiendo tropas y conservando el héroe: si su héroe muere,
// el bloque de curación ni se evalúa y la fuga entre ataques no se llega a probar.
q("UPDATE {$P}units SET u12 = 70 WHERE vref = $TARGET_A");
q("UPDATE {$P}units SET u2  = 20 WHERE vref = $TARGET_B");

// Vendas cargadas en la bolsa del atacante A.
q("INSERT INTO {$P}heroitems (uid,btype,type,num,proc) VALUES ($UID_A,8,$BANDAGES,$BANDAGES,1)");
$bandageId = mysqli_insert_id($conn);
q("UPDATE {$P}heroinventory SET bag = $bandageId WHERE uid = $UID_A");

$battleA = time() - 7200;
$battleB = $battleA + 100;

q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($VILL_A,$SENT,0,0,0,0,0,0,0,0,0,1,3,0,0,0)");
$refA = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$VILL_A,$TARGET_A,$refA,0,'0,0,0,0,0',$battleA,0,1,0,0,0,0)");

q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($VILL_B,$SENT,0,0,0,0,0,0,0,0,0,1,3,0,0,0)");
$refB = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$VILL_B,$TARGET_B,$refB,0,'0,0,0,0,0',$battleB,0,1,0,0,0,0)");

say("== escenario ==");
say("  A: aldea $VILL_A (uid $UID_A, romano) -> $TARGET_A, $SENT legionarios + héroe, $BANDAGES vendas");
say("  B: aldea $VILL_B (uid $UID_B, teutón) -> $TARGET_B, $SENT maceros + héroe, sin vendas");
say("  tope por 33%: " . floor(($SENT + 1) / 100 * 33));

@unlink("GameEngine/Prevention/sendunits.txt");
@unlink("GameEngine/Prevention/returnunits.txt");

$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) {
    $errors[] = "$str  ($file:$line)";
    return true;
});
new Automation;
restore_error_handler();

// Automation.php arrastra avisos previos en varios sitios (procDistanceTime, producción
// del botín, informes). Sólo hacen fallar los que caen dentro del bloque de vendas, que
// se delimita por sus propias líneas para que no dependa de la numeración.
$engine = realpath("GameEngine/Automation.php");
$src = file($engine);
$blockStart = $blockEnd = 0;
foreach($src as $n => $line) {
    if(strpos($line, 'getEquippedHeroItem($AttackerID, 7)') !== false) { $blockStart = $n + 1; }
    if($blockStart && strpos($line, 'addMovement(4,') !== false && !$blockEnd) { $blockEnd = $n + 1; }
}
if(!$blockStart || !$blockEnd) { die("No se pudo delimitar el bloque de vendas en Automation.php\n"); }

$known = array();
$phpErrors = array();
foreach(array_unique($errors) as $e) {
    if((stripos($e, 'undefined') === false && stripos($e, 'null') === false)) { continue; }
    $inBlock = preg_match('/\(([^:]+):(\d+)\)$/', $e, $m)
        && realpath($m[1]) === $engine
        && $m[2] >= $blockStart && $m[2] <= $blockEnd;
    if($inBlock) { $phpErrors[] = $e; } else { $known[] = $e; }
}
say("\n== resultado ==");
say("  bloque de vendas: líneas $blockStart-$blockEnd de Automation.php");
if($known) { say("  [nota]  " . count($known) . " aviso(s) de PHP fuera del bloque, ajenos a esta tanda"); }
check(!$phpErrors, "el bloque de vendas no usa variables sin definir"
    . ($phpErrors ? ": " . implode(' | ', array_slice($phpErrors, 0, 3)) : ""));

// Los movimientos de curación son los de tipo 4 cuyo ref no es el ataque original.
$heals = array();
$r = q("SELECT m.*, a.vref, a.t1, a.t11 FROM {$P}movement m JOIN {$P}attacks a ON a.id = m.ref
        WHERE m.sort_type = 4 AND m.ref NOT IN ($refA,$refB)");
while($row = mysqli_fetch_assoc($r)) { $heals[] = $row; }

check(count($heals) === 1, "se creó exactamente un regreso de curación (encontrados: " . count($heals) . ")");
if(count($heals) !== 1) {
    foreach($heals as $h) { say("    ref={$h['ref']} from={$h['from']} to={$h['to']} t1={$h['t1']}"); }
    say("\n$failures COMPROBACIONES FALLARON");
    exit(1);
}
$heal = $heals[0];
$healed = (int)$heal['t1'];

$survivors = one("SELECT t1 FROM {$P}attacks WHERE id = $refA");
$dead = $SENT - (int)$survivors['t1'];
$healmax = floor(($SENT + 1) / 100 * 33);
say("  bajas del atacante A: $dead · revividas: $healed");

check($dead >= $healmax, "el escenario mata más tropas que el tope (bajas $dead >= tope $healmax)");
check($healed === (int)$healmax, "revive exactamente el 33% de lo enviado, no las $BANDAGES vendas ($healed)");
check((int)$heal['t11'] === 0, "no intenta revivir al héroe");

$item = one("SELECT type,num,proc FROM {$P}heroitems WHERE id = $bandageId");
$bag = one("SELECT bag FROM {$P}heroinventory WHERE uid = $UID_A");
say("  bolsa: type={$item['type']} num={$item['num']} proc={$item['proc']} · bag={$bag['bag']}");
check((int)$item['type'] === $BANDAGES - $healed, "descuenta de la bolsa una venda por tropa revivida");
check((int)$item['num'] === $BANDAGES - $healed, "descuenta lo mismo del stock total");
check((int)$item['proc'] === 1 && (int)$bag['bag'] === $bandageId, "las vendas sobrantes siguen cargadas");

check((int)$heal['to'] === $VILL_A, "las tropas vuelven a la aldea del atacante");
check((int)$heal['from'] === $TARGET_A, "el regreso sale de la aldea atacada, no de la propia");
check((int)$heal['vref'] === $VILL_A, "el ataque de regreso queda a nombre del dueño de las tropas");

// Velocidad: debe usar la unidad del atacante (legionario, 6), no la del defensor (macero, 7).
$from = $database->getMInfo($VILL_A);
$to = $database->getMInfo($TARGET_A);
$dx = min(abs($to['x'] - $from['x']), (2 * WORLD_MAX + 1) - abs($to['x'] - $from['x']));
$dy = min(abs($to['y'] - $from['y']), (2 * WORLD_MAX + 1) - abs($to['y'] - $from['y']));
$distance = sqrt($dx * $dx + $dy * $dy);
$travel = function($speed) use ($distance) { return round(($distance / $speed) * 3600 / INCREASE_SPEED); };
$floor = 86400 / HEAL_SPEED;
$expectOwn = max($floor, $travel($GLOBALS['u1']['speed']));
$expectEnemy = max($floor, $travel($GLOBALS['u11']['speed']));
$actual = (int)$heal['endtime'] - $battleA;
say("  regreso: {$actual}s · esperado tribu propia {$expectOwn}s · tribu del defensor {$expectEnemy}s · piso {$floor}s");

check(abs($actual - $expectOwn) <= 1, "el regreso usa la velocidad de la tribu del atacante");
if($expectOwn != $expectEnemy) {
    check(abs($actual - $expectEnemy) > 1, "no usa la velocidad de la tribu del defensor");
} else {
    say("  [nota]  el piso de HEAL_SPEED tapa la diferencia de velocidad; subí config.heal para distinguirlas");
}
check($actual > 0 && abs(((int)$heal['endtime'] - $battleA) - $actual) <= 1,
    "el regreso cuenta desde la hora de la batalla y no desde la del cron");

// El atacante B no tenía vendas: no puede heredar la curación de A.
$survivorsB = one("SELECT t1 FROM {$P}attacks WHERE id = $refB");
$deadB = $SENT - (int)$survivorsB['t1'];
$heroB = one("SELECT dead FROM {$P}hero WHERE uid = $UID_B");
say("  bajas del atacante B: $deadB · su héroe murió: " . ((int)$heroB['dead'] ? 'sí' : 'no'));
check($deadB > 0 && (int)$heroB['dead'] === 0,
    "el ataque B llega al bloque de curación (pierde tropas y conserva el héroe)");

$healB = one("SELECT COUNT(*) c FROM {$P}movement m JOIN {$P}attacks a ON a.id = m.ref
              WHERE m.sort_type = 4 AND m.ref NOT IN ($refA,$refB) AND m.to = $VILL_B");
check((int)$healB['c'] === 0, "el ataque sin vendas no revivió tropas heredadas del anterior");

say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
