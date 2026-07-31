<?php
/*
 * Prueba la llegada de los animales capturados con jaulas: el aviso de refuerzo,
 * el refuerzo creado en la aldea y cómo se renderiza en el punto de reunión.
 * Requiere haber corrido antes tools/test_cage_capture.php (deja el escenario C).
 * Uso: php tools/test_cage_arrival.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos, ataques, refuerzos e informes del mundo local.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_cage_arrival.php --destructivo\n");
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
$UID = 5;
$VILLAGE = 797;
$failures = 0;
function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

// ------------------------------------------------ montar animales en vuelo
q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");
q("DELETE FROM {$P}enforcement");
q("DELETE FROM {$P}ndata WHERE uid = $UID");

$oasis = one("SELECT o.wref FROM {$P}odata o JOIN {$P}units u ON u.vref = o.wref
              WHERE (u.u31+u.u32+u.u33+u.u34+u.u35+u.u36+u.u37+u.u38+u.u39+u.u40) >= 6 LIMIT 1");
$OASIS = (int)$oasis['wref'];

q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($OASIS,2,1,0,0,1,0,0,1,0,0,0,2,0,0,0)");
$ref = mysqli_insert_id($conn);

// -------------------------------------------- 1) vista de tropas entrantes
say("== punto de reunión, tropas entrantes (animales aún en camino) ==");
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,0,$VILLAGE,$ref,0,'0,0,0,0,0'," . (time() + 300) . ",0,1,0,0,0,0)");

$village = new stdClass;
$village->wid = $VILLAGE;
$village->vname = $database->getVillageField($VILLAGE, 'name');
$village->resarray = $database->getResourceLevel($VILLAGE);
$session = new stdClass;
$session->tribe = 1;
$timer = 0;

$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) {
    $errors[] = "$str  ($file:$line)";
    return true;
});
ob_start();
include "Templates/Build/16_incomming.tpl";
$html = ob_get_clean();
restore_error_handler();

$text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
say("  render: $text");
check(!$errors, "renderiza sin avisos de PHP" . ($errors ? ": " . implode(' | ', $errors) : ""));
check(strpos($html, 'class="unit u31"') !== false, "usa los iconos de unidades de naturaleza (u31..u40)");
check(strpos($html, 'class="unit u-9"') === false, "no genera clases de unidad inválidas");
check(strpos($text, '?') === false, "muestra las cantidades reales en vez de signos de pregunta");
check(strpos($html, 'karte.php?d=0') === false, "no enlaza a la aldea inexistente 0");

// --------------------------------------------------- 2) procesar la llegada
say("\n== llegada de los animales ==");
q("UPDATE {$P}movement SET endtime = " . (time() - 5) . " WHERE `from` = 0");
@unlink("GameEngine/Prevention/sendreinfunits.txt");

$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) {
    $errors[] = "$str  ($file:$line)";
    return true;
});
new Automation;
restore_error_handler();

$notice = one("SELECT * FROM {$P}ndata WHERE uid = $UID ORDER BY id DESC LIMIT 1");
$enforce = one("SELECT * FROM {$P}enforcement WHERE vref = $VILLAGE AND `from` = 0");

if($notice) { say("  informe: ntype={$notice['ntype']} \"{$notice['topic']}\""); }
if($enforce) {
    $line = array();
    for($i = 31; $i <= 40; $i++) {
        if((int)$enforce['u'.$i] > 0) { $line[] = "u$i={$enforce['u'.$i]}"; }
    }
    say("  refuerzo: " . implode(', ', $line));
}

$phpErrors = array_filter($errors, function($e) { return stripos($e, 'undefined') !== false; });
check(!$phpErrors, "el procesamiento no usa variables sin definir" . ($phpErrors ? ": " . implode(' | ', $phpErrors) : ""));
check($notice && (int)$notice['ntype'] === 8, "se avisó la llegada del refuerzo");
check($enforce && (int)$enforce['u31'] === 2 && (int)$enforce['u32'] === 1
      && (int)$enforce['u35'] === 1 && (int)$enforce['u38'] === 1,
      "los animales entraron como refuerzo de naturaleza en las unidades correctas");
check((int)one("SELECT COUNT(*) c FROM {$P}movement WHERE `from` = 0 AND proc = 0")['c'] === 0,
      "el movimiento quedó procesado");

// manutención: la barra de recursos y el informe tienen que coincidir
$upkeepAll = $technology->getUpkeep($technology->getAllUnits($VILLAGE), 0);
$upkeepNature = $technology->getUpkeep($enforce, 4);
say("  manutención: total aldea $upkeepAll · sólo animales $upkeepNature");
check($upkeepNature === 8, "el informe cobra el cereal real de los animales (2+1+2+3)");

q("DELETE FROM {$P}enforcement WHERE vref = $VILLAGE AND `from` = 0");
q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");

say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
