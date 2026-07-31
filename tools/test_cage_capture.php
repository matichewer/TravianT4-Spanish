<?php
/*
 * Pruebas end-to-end de la captura con jaulas.
 * Uso (dentro del contenedor web, desde la raíz del repo):
 *   php tools/test_cage_capture.php
 *
 * Escenarios:
 *   A) oasis libre + héroe + jaulas -> captura, informe ntype 25, sin batalla
 *   B) oasis ocupado por un jugador -> jaulas ignoradas, batalla normal con informe
 *   C) espionaje a oasis libre       -> jaulas ignoradas, informe de espionaje
 *
 * Es destructivo sobre el mundo local de pruebas: no correr contra producción.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos, ataques, refuerzos e informes del mundo local.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_cage_capture.php --destructivo\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root . PATH_SEPARATOR . $root . '/GameEngine');
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
    if($r === false) {
        die("SQL ERROR: " . mysqli_error($conn) . "\n  " . $sql . "\n");
    }
    return $r;
}
function one($sql) { return mysqli_fetch_assoc(q($sql)); }
function say($s = '') { echo $s . "\n"; }

$P = TB_PREFIX;
$UID = 5;                       // MercaderTest
$VILLAGE = 797;
$failures = 0;

function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

$vil = one("SELECT v.wref, w.x, w.y, v.owner FROM {$P}vdata v JOIN {$P}wdata w ON w.id = v.wref WHERE v.wref = $VILLAGE");

/**
 * Deja el mundo en un estado conocido y lanza un ataque que ya llegó.
 * Devuelve array(oasis, cageId, animalesAntes, array por unidad).
 */
function setupAttack($oasisWref, $attackType, $conqured) {
    global $P, $UID, $VILLAGE, $conn;

    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    q("DELETE FROM {$P}enforcement");
    q("DELETE FROM {$P}ndata WHERE uid = $UID");
    q("DELETE FROM {$P}heroitems WHERE uid = $UID");

    q("UPDATE {$P}odata SET conqured = $conqured, owner = " . ($conqured ? 2 : 3) . " WHERE wref = $oasisWref");
    q("UPDATE {$P}hero SET dead = 0, health = 100, wref = $VILLAGE WHERE uid = $UID");

    q("INSERT INTO {$P}heroitems (uid, btype, type, num, proc) VALUES ($UID, 9, 5, 5, 1)");
    $cage = mysqli_insert_id($conn);
    q("UPDATE {$P}heroinventory SET bag = $cage WHERE uid = $UID");

    $arrival = time() - 5;
    q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
       VALUES ($VILLAGE,0,0,0,0,0,0,0,0,0,0,1,$attackType,0,0,0)");
    $att = mysqli_insert_id($conn);
    q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
       VALUES (3,$VILLAGE,$oasisWref,$att,0,'0,0,0,0,0',$arrival,0,1,0,0,0,0)");

    $u = one("SELECT * FROM {$P}units WHERE vref = $oasisWref");
    $before = array();
    $total = 0;
    for($i = 31; $i <= 40; $i++) { $before[$i] = (int)$u['u'.$i]; $total += $before[$i]; }

    @unlink("GameEngine/Prevention/sendunits.txt");
    return array($cage, $total, $before);
}

function animalsNow($oasisWref) {
    global $P;
    $u = one("SELECT * FROM {$P}units WHERE vref = $oasisWref");
    $total = 0;
    for($i = 31; $i <= 40; $i++) { $total += (int)$u['u'.$i]; }
    return $total;
}

function notices() {
    global $P, $UID;
    $out = array();
    $r = q("SELECT ntype, topic, data FROM {$P}ndata WHERE uid = $UID ORDER BY id");
    while($n = mysqli_fetch_assoc($r)) { $out[] = $n; }
    return $out;
}

function showNotices($list) {
    if(!$list) { say("  (sin informes)"); return; }
    foreach($list as $n) { say("  ntype={$n['ntype']} \"{$n['topic']}\""); }
}

// oasis libre con animales, el más cercano a la aldea
$oasis = one(
    "SELECT o.wref, w.x, w.y FROM {$P}odata o
     JOIN {$P}wdata w ON w.id = o.wref
     JOIN {$P}units u ON u.vref = o.wref
     WHERE (u.u31+u.u32+u.u33+u.u34+u.u35+u.u36+u.u37+u.u38+u.u39+u.u40) >= 6
     ORDER BY (POW(w.x - {$vil['x']},2) + POW(w.y - {$vil['y']},2)) ASC LIMIT 1"
);
$OASIS = (int)$oasis['wref'];
say("aldea $VILLAGE ({$vil['x']}|{$vil['y']}) · oasis $OASIS ({$oasis['x']}|{$oasis['y']})");

// ------------------------------------------------------- A: oasis libre
say("\n== A) oasis libre + héroe + 5 jaulas ==");
list($cageId, $animalsBefore, $before) = setupAttack($OASIS, 3, 0);
new Automation;

$after = animalsNow($OASIS);
$cage = one("SELECT * FROM {$P}heroitems WHERE id = $cageId");
$inv = one("SELECT bag FROM {$P}heroinventory WHERE uid = $UID");
$list = notices();
$move = one("SELECT m.*, a.attack_type, a.t1 FROM {$P}movement m JOIN {$P}attacks a ON a.id = m.ref
             WHERE m.sort_type = 3 AND m.`from` = 0");

say("  oasis: $animalsBefore -> $after animales");
showNotices($list);
check($animalsBefore - $after === 5, "se capturaron 5 animales");
check(count($list) === 1 && (int)$list[0]['ntype'] === 25, "se generó un único informe, de tipo 25");
check(!$cage, "el ítem de jaulas se borró al agotarse");
check((int)$inv['bag'] === 0, "la jaula quedó desequipada");
check($move && (int)$move['to'] === $VILLAGE, "los animales viajan hacia la aldea");
check((int)one("SELECT COUNT(*) c FROM {$P}movement WHERE sort_type = 4")['c'] === 1, "el héroe vuelve a casa");

// -------------------------------------------------- B: oasis ocupado
say("\n== B) oasis ocupado por un jugador ==");
list($cageId, $animalsBefore, $before) = setupAttack($OASIS, 3, 1301);
new Automation;

$after = animalsNow($OASIS);
$cage = one("SELECT * FROM {$P}heroitems WHERE id = $cageId");
$list = notices();

say("  oasis: $animalsBefore -> $after animales");
showNotices($list);
check($cage && (int)$cage['type'] === 5 && (int)$cage['num'] === 5, "las jaulas quedaron intactas");
check(count($list) >= 1, "se generó informe de batalla");
check((int)one("SELECT COUNT(*) c FROM {$P}movement WHERE sort_type = 3 AND `from` = 0")['c'] === 0,
      "no se enviaron animales capturados");

// ------------------------------------------------------ C: espionaje
say("\n== C) espionaje a oasis libre ==");
list($cageId, $animalsBefore, $before) = setupAttack($OASIS, 1, 0);
new Automation;

$after = animalsNow($OASIS);
$cage = one("SELECT * FROM {$P}heroitems WHERE id = $cageId");
$list = notices();

say("  oasis: $animalsBefore -> $after animales");
showNotices($list);
check($cage && (int)$cage['type'] === 5, "las jaulas quedaron intactas");
check($animalsBefore === $after, "no se capturaron animales");
check((int)one("SELECT COUNT(*) c FROM {$P}movement WHERE sort_type = 3 AND `from` = 0")['c'] === 0,
      "no se enviaron animales capturados");

// -------------------------------------------------------------- limpieza
q("UPDATE {$P}odata SET conqured = 0, owner = 3 WHERE wref = $OASIS");

say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
