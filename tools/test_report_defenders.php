<?php
/*
 * Prueba end-to-end del desglose de defensores por jugador en el informe de batalla.
 * Uso (dentro del contenedor web, desde la raíz del repo):
 *   php tools/test_report_defenders.php --destructivo
 *
 * El caso que se comprueba es justo el que el formato viejo no podía representar: el
 * dueño de la aldea y un refuerzo de la MISMA tribu. Agregado por tribu los dos caían
 * en un solo bloque sumado, así que era imposible saber de quién era cada tropa.
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
        . "  php tools/test_report_defenders.php --destructivo\n");
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

$UID_DEF  = 5;   // MercaderTest, tribu 1 (romanos): dueño de la aldea atacada
$UID_ATT  = 6;   // AliadoTest, tribu 2 (teutones): atacante
$V_DEF    = 797; // aldea atacada
$V_REINF  = 2;   // segunda aldea del defensor, de donde sale el refuerzo
$V_ATT    = 3;   // aldea del atacante

$DEF_TROOPS    = 10; // legionarios propios en la aldea atacada
$REINF_TROOPS  = 6;  // legionarios que llegan como refuerzo
$NATURE_TROOPS = 4;  // ratas enjauladas defendiendo, sin jugador ni aldea de origen
$ATT_TROOPS    = 30; // hacheros del atacante

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

// ------------------------------------------------------------------ escenario
q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");
q("DELETE FROM {$P}enforcement");
q("DELETE FROM {$P}ndata");
q("UPDATE {$P}units SET hero = 0, u1 = 0, u2 = 0, u3 = 0, u11 = 0, u12 = 0, u13 = 0
   WHERE vref IN ($V_DEF,$V_REINF,$V_ATT)");
q("UPDATE {$P}units SET u1 = $DEF_TROOPS WHERE vref = $V_DEF");
q("UPDATE {$P}units SET u13 = $ATT_TROOPS WHERE vref = $V_ATT");
q("UPDATE {$P}hero SET dead = 1, hide = 1 WHERE uid IN ($UID_DEF,$UID_ATT)");
// Refuerzo romano del propio defensor, desde su segunda aldea: misma tribu que el dueño.
q("INSERT INTO {$P}enforcement (vref,`from`,u1) VALUES ($V_DEF,$V_REINF,$REINF_TROOPS)");
// Animales enjaulados: defienden como un refuerzo con `from = 0`, sin jugador ni aldea.
q("INSERT INTO {$P}enforcement (vref,`from`,u31) VALUES ($V_DEF,0,$NATURE_TROOPS)");

$arrival = time() - 5;
q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($V_ATT,0,0,$ATT_TROOPS,0,0,0,0,0,0,0,0,4,0,0,0)");
$att = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$V_ATT,$V_DEF,$att,0,'0,0,0,0,0',$arrival,0,1,0,0,0,0)");

say("== asalto de $ATT_TROOPS hacheros contra $DEF_TROOPS legionarios propios + $REINF_TROOPS de refuerzo ==");
$errs = runPhase('sendunits');
// El mundo de pruebas arrastra avisos viejos (artefactos nulos, $unitarray, $resarray)
// que no tienen que ver con el informe, así que sólo se exige que no aparezcan los del
// desglose de defensores.
$ownErrs = array_values(array_filter($errs, function($e) {
    return strpos($e, 'defenderParties') !== false
        || strpos($e, 'ownerParty') !== false
        || strpos($e, 'reinforcementParty') !== false
        || strpos($e, 'data2def') !== false;
}));
check($ownErrs === array(), "el desglose de defensores no produce avisos de PHP: " . implode(' | ', $ownErrs));
say("  (" . count($errs) . " avisos preexistentes del mundo de pruebas, ajenos al informe)");

// ------------------------------------------------------------------- informes
$defReport = one("SELECT * FROM {$P}ndata WHERE uid = $UID_DEF AND ntype IN (4,5,6,7) ORDER BY id DESC LIMIT 1");
$attReport = one("SELECT * FROM {$P}ndata WHERE uid = $UID_ATT AND ntype IN (1,2,3) ORDER BY id DESC LIMIT 1");
check($defReport !== null, "el defensor recibe su informe de batalla");
check($attReport !== null, "el atacante recibe su informe de batalla");
if($defReport === null || $attReport === null) {
    say("\n$failures COMPROBACIONES FALLARON");
    exit(1);
}

function decodeParties($data) {
    $fields = explode(',', $data);
    $marker = array_search('defenders-v1', $fields, true);
    if($marker === false || !isset($fields[$marker + 1])) { return array(); }
    $parties = array();
    foreach(explode('|', $fields[$marker + 1]) as $group) {
        $values = explode(';', $group);
        if(count($values) < 25) { continue; }
        $party = array('uid' => (int)$values[0], 'wref' => (int)$values[1], 'tribe' => (int)$values[2],
                       'sent' => array(), 'dead' => array());
        for($i = 0; $i < 11; $i++) {
            $party['sent'][$i + 1] = (int)$values[3 + $i];
            $party['dead'][$i + 1] = (int)$values[14 + $i];
        }
        $parties[] = $party;
    }
    return $parties;
}

$parties = decodeParties($defReport['data']);
check(count($parties) === 3, "el informe del defensor trae los 3 bandos por separado (trae " . count($parties) . ")");
if(count($parties) !== 3) {
    say("  data: " . $defReport['data']);
    say("\n$failures COMPROBACIONES FALLARON");
    exit(1);
}

// Se buscan por aldea de origen, no por posición, para que el orden no ate la prueba.
function partyByVillage($parties, $wref) {
    foreach($parties as $party) {
        if($party['wref'] === $wref) { return $party; }
    }
    return null;
}
$owner  = partyByVillage($parties, $V_DEF);
$reinf  = partyByVillage($parties, $V_REINF);
$nature = partyByVillage($parties, 0);
check($owner !== null && $reinf !== null && $nature !== null,
    "cada bando aparece con su aldea de origen (o sin ninguna, para la naturaleza)");
if($owner === null || $reinf === null || $nature === null) {
    say("  data: " . $defReport['data']);
    say("\n$failures COMPROBACIONES FALLARON");
    exit(1);
}
say("  dueño   : uid={$owner['uid']} aldea={$owner['wref']} tribu={$owner['tribe']} envió={$owner['sent'][1]} bajas={$owner['dead'][1]}");
say("  refuerzo: uid={$reinf['uid']} aldea={$reinf['wref']} tribu={$reinf['tribe']} envió={$reinf['sent'][1]} bajas={$reinf['dead'][1]}");

check($owner['uid'] === $UID_DEF && $owner['wref'] === $V_DEF && $owner['tribe'] === 1,
    "el primer bando es el dueño de la aldea atacada");
check($reinf['uid'] === $UID_DEF && $reinf['wref'] === $V_REINF && $reinf['tribe'] === 1,
    "el segundo bando es el refuerzo, con su aldea de origen propia");
check($owner['wref'] !== $reinf['wref'],
    "dos bandos de la misma tribu quedan separados, que es lo que el formato viejo no podía");
check($owner['sent'][1] === $DEF_TROOPS, "las tropas propias del dueño son las suyas, sin el refuerzo sumado");
check($reinf['sent'][1] === $REINF_TROOPS, "el refuerzo conserva su propio total");
check($owner['dead'][1] > 0 && $owner['dead'][1] < $DEF_TROOPS,
    "el asalto deja bajas parciales en el dueño ({$owner['dead'][1]}/{$DEF_TROOPS})");
check($reinf['dead'][1] > 0 && $reinf['dead'][1] < $REINF_TROOPS,
    "el asalto deja bajas parciales en el refuerzo ({$reinf['dead'][1]}/{$REINF_TROOPS})");

// El bloque agregado por tribu que ya existía tiene que seguir cuadrando con la suma.
$fields = explode(',', $defReport['data']);
$aggregatedSent = (int)$fields[37];
$aggregatedDead = (int)$fields[48];
check($aggregatedSent === $owner['sent'][1] + $reinf['sent'][1],
    "la suma de los bandos coincide con el bloque agregado por tribu ($aggregatedSent)");
check($aggregatedDead === $owner['dead'][1] + $reinf['dead'][1],
    "las bajas de los bandos coinciden con las del bloque agregado ($aggregatedDead)");

check($nature['uid'] === 0 && $nature['wref'] === 0 && $nature['tribe'] === 4,
    "los animales enjaulados quedan como un bando propio, sin jugador ni aldea");
check($nature['sent'][1] === $NATURE_TROOPS && $nature['dead'][1] > 0,
    "los animales enjaulados aportan sus tropas y sus bajas ({$nature['dead'][1]}/{$NATURE_TROOPS})");

check(strpos($attReport['data'], 'defenders-v1') === false,
    "el informe del atacante no lleva el desglose por jugador");

say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
