<?php
/*
 * Prueba el trampero de punta a punta, por el camino real de resolución de ataques:
 * captura en un asalto, captura + rescate en un ataque normal posterior, y lo que el
 * informe del atacante termina diciendo.
 * Uso: php tools/test_trapper_attack.php --destructivo
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos, ataques, informes, prisioneros y tropas del mundo local,\n"
        . "y le cambia la tribu y el trampero a la aldea 3.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_trapper_attack.php --destructivo\n");
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

global $database, $generator, $technology, $battle, $bid36;

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

$ATTACKER = 5;  $VILL_A = 797;   // MercaderTest, romano
$DEFENDER = 6;  $VILL_D = 3;     // AliadoTest, se vuelve galo para tener trampero
$TRAP_FIELD = 19;
$TRAP_LEVEL = 5;                 // 64 trampas de capacidad

$defenderTribeBefore = (int)one("SELECT tribe FROM {$P}users WHERE id = $DEFENDER")['tribe'];
$fieldBefore = one("SELECT f{$TRAP_FIELD} lvl, f{$TRAP_FIELD}t typ FROM {$P}fdata WHERE vref = $VILL_D");

register_shutdown_function(function() use ($P, $VILL_A, $VILL_D, $DEFENDER, $TRAP_FIELD, $defenderTribeBefore, $fieldBefore) {
    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    q("DELETE FROM {$P}prisoners");
    q("UPDATE {$P}users SET tribe = $defenderTribeBefore WHERE id = $DEFENDER");
    q("UPDATE {$P}fdata SET f{$TRAP_FIELD} = " . (int)$fieldBefore['lvl'] . ", f{$TRAP_FIELD}t = " . (int)$fieldBefore['typ'] . " WHERE vref = $VILL_D");
    q("UPDATE {$P}units SET " . implode(',', array_map(function($i){ return "u$i = 0"; }, range(1, 50)))
      . ", hero = 0, u99 = 0, u99o = 0 WHERE vref IN ($VILL_A,$VILL_D)");
});

q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");
q("DELETE FROM {$P}prisoners");
q("DELETE FROM {$P}enforcement");
q("DELETE FROM {$P}ndata WHERE uid IN ($ATTACKER,$DEFENDER)");
q("UPDATE {$P}hero SET dead = 0, health = 100 WHERE uid IN ($ATTACKER,$DEFENDER)");

// El trampero es galo: la aldea defensora cambia de tribu por el rato que dura la prueba.
q("UPDATE {$P}users SET tribe = 3 WHERE id = $DEFENDER");
q("UPDATE {$P}fdata SET f{$TRAP_FIELD} = $TRAP_LEVEL, f{$TRAP_FIELD}t = 36 WHERE vref = $VILL_D");
$capacity = $bid36[$TRAP_LEVEL]['attri'] * TRAPPER_CAPACITY;

// Sin defensores el atacante gana sin bajas: lo único que le saca tropas son las trampas.
q("UPDATE {$P}units SET " . implode(',', array_map(function($i){ return "u$i = 0"; }, range(1, 50)))
  . ", hero = 0 WHERE vref IN ($VILL_A,$VILL_D)");
q("UPDATE {$P}units SET u99 = 20, u99o = 0 WHERE vref = $VILL_D");

@unlink("GameEngine/Prevention/sendunits.txt");
@unlink("GameEngine/Prevention/returnunits.txt");

function runCron() {
    @unlink("GameEngine/Prevention/sendunits.txt");
    @unlink("GameEngine/Prevention/returnunits.txt");
    $automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod('Automation', 'sendUnitsComplete');
    $method->setAccessible(true);
    $method->invoke($automation);
}

say("== escenario ==");
say("  defensor: aldea $VILL_D, galo, trampero nivel $TRAP_LEVEL (capacidad $capacity), 20 trampas puestas, sin tropas");
say("  atacante: aldea $VILL_A, romano");
say();

// --- Asalto: las trampas capturan y el botín se va, pero un asalto no rescata nada.
$RAID = 60;
q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($VILL_A,$RAID,0,0,0,0,0,0,0,0,0,0,4,0,0,0)");
$raidRef = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$VILL_A,$VILL_D,$raidRef,0,'0,0,0,0,0'," . (time() - 7200) . ",0,1,0,0,0,0)");
runCron();

$afterRaid = one("SELECT u.u99,u.u99o,COALESCE(p.t1,0) held,
    (SELECT COUNT(*) FROM {$P}prisoners WHERE wref = $VILL_D) groups
    FROM {$P}units u LEFT JOIN {$P}prisoners p ON p.wref = u.vref AND p.`from` = $VILL_A
    WHERE u.vref = $VILL_D");
$raidReturn = one("SELECT a.t1 FROM {$P}movement m INNER JOIN {$P}attacks a ON a.id = m.ref
    WHERE m.sort_type = 4 AND m.`from` = $VILL_D AND m.`to` = $VILL_A AND a.id = $raidRef");

say("== asalto ==");
say("  enviados $RAID · atrapados " . (int)$afterRaid['held'] . " · vuelven " . (int)$raidReturn['t1']);
check((int)$afterRaid['held'] === 20, "las trampas libres capturan hasta llenarse (" . (int)$afterRaid['held'] . " de 20)");
check((int)$afterRaid['u99o'] === 20 && (int)$afterRaid['u99'] === 20, "la ocupación queda sincronizada y las trampas siguen en pie");
check((int)$raidReturn['t1'] === $RAID - 20, "el resto del asalto vuelve a casa");
check((int)$afterRaid['groups'] === 1, "un asalto no rescata: los prisioneros se quedan");

// --- El defensor repone trampas; ahora el atacante manda un ataque normal y rescata.
q("UPDATE {$P}units SET u99 = 40 WHERE vref = $VILL_D");
$ASSAULT = 40;
q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($VILL_A,$ASSAULT,0,0,0,0,0,0,0,0,0,0,3,0,0,0)");
$assaultRef = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$VILL_A,$VILL_D,$assaultRef,0,'0,0,0,0,0'," . (time() - 3600) . ",0,1,0,0,0,0)");
runCron();

$afterAssault = one("SELECT u99,u99o,
    (SELECT COUNT(*) FROM {$P}prisoners WHERE wref = $VILL_D) groups
    FROM {$P}units WHERE vref = $VILL_D");
$assaultReturn = one("SELECT a.t1 FROM {$P}movement m INNER JOIN {$P}attacks a ON a.id = m.ref
    WHERE m.sort_type = 4 AND m.`from` = $VILL_D AND m.`to` = $VILL_A AND a.id = $assaultRef");

say();
say("== ataque normal ==");
say("  enviados $ASSAULT · vuelven " . (int)$assaultReturn['t1'] . " · grupos presos restantes " . (int)$afterAssault['groups']);
check((int)$afterAssault['groups'] === 0, "ganar el ataque libera todos los prisioneros propios");
// 40 enviados + 20 del asalto + 20 capturados ahora: vuelve todo, la liberación no mata a nadie.
check((int)$assaultReturn['t1'] === 60,
    "vuelven las 40 enviadas más las 20 del asalto, sin bajas por liberar (" . (int)$assaultReturn['t1'] . ")");
check((int)$afterAssault['u99'] === 0 && (int)$afterAssault['u99o'] === 0,
    "el rescate rompe las 40 trampas usadas (u99 " . (int)$afterAssault['u99'] . ")");

// --- El informe del atacante no puede seguir llamando presas a las que volvieron con él.
$report = one("SELECT data FROM {$P}ndata WHERE uid = $ATTACKER ORDER BY id DESC LIMIT 1");
$fields = explode(',', $report['data']);
$trapStart = array_search('trap-data-v1', $fields, true);
$prisonerRow = $trapStart === false ? array() : array_slice($fields, $trapStart + 1, 11);
$releaseInfo = $trapStart === false ? '' : (isset($fields[$trapStart + 12]) ? $fields[$trapStart + 12] : '');

say();
say("== informe del atacante ==");
say("  fila de prisioneros: " . implode(',', $prisonerRow));
say("  liberación: " . strip_tags($releaseInfo));
check($trapStart !== false, "el informe trae el bloque de prisioneros marcado");
check(array_sum(array_map('intval', $prisonerRow)) === 0,
    "no lista como presas a las tropas que volvieron con el ejército");
check(strpos($releaseInfo, 'liberó') !== false && strpos($releaseInfo, '40') !== false,
    "el informe cuenta las 40 tropas liberadas");
check(strpos($releaseInfo, 'murieron') === false,
    "el informe no inventa bajas durante la liberación");

say();
if($failures > 0) {
    say("FALLARON $failures comprobaciones");
    exit(1);
}
say("TODO OK");
exit(0);
