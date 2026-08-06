<?php
/*
 * Pruebas end-to-end del ciclo de vida de un oasis.
 * Uso (dentro del contenedor web, desde la raíz del repo):
 *   php tools/test_oasis_lifecycle.php --destructivo
 *
 * Escenarios:
 *   A) conquista con el héroe: odata + wdata quedan coherentes
 *   B) el bono de producción llega a la aldea que lo conquistó, y sólo a ella
 *   C) un oasis conquistado no repuebla animales; uno libre sí
 *   C2) el oasis produce recursos hasta su propio granero (maxstore/maxcrop)
 *   D) saqueo de un oasis anexado: el informe va al dueño y el botín sale de la
 *      aldea que lo tiene, con el cupo del 10% que se repone en 10 minutos
 *   E) refuerzo a un oasis: informe correcto, cereal a cargo de la aldea del oasis
 *      y sólo el dueño o un aliado pueden reforzarlo
 *   E4) espionaje: la Naturaleza no tiene espías; un oasis sólo resiste con
 *       espías estacionados dentro
 *   E5) sólo se conquista un oasis a 3 casillas o menos de la aldea atacante
 *   F) al conquistar la aldea, sus oasis cambian de dueño
 *   G) al borrar la aldea, sus oasis vuelven a quedar libres
 *   H) no se puede soltar el oasis de otro desde la Mansión del Héroe
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
        . "  php tools/test_oasis_lifecycle.php --destructivo\n");
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
include "Building.php";
include "Form.php";
include "Units.php";

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
function rows($sql) { $r = q($sql); $o = array(); while($x = mysqli_fetch_assoc($r)) { $o[] = $x; } return $o; }
function say($s = '') { echo $s . "\n"; }

$P = TB_PREFIX;
$A_UID = 5;  $A_VIL = 797;   // MercaderTest
$B_UID = 6;  $B_VIL = 3;     // AliadoTest
$failures = 0;

function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
function runPhase($method) {
    global $automation;
    $m = new ReflectionMethod('Automation', $method);
    $m->setAccessible(true);
    $m->invoke($automation);
}

/** Deja el mundo limpio de movimientos/informes y devuelve el oasis elegido. */
function resetWorld() {
    global $P, $A_UID, $B_UID, $A_VIL;
    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    q("DELETE FROM {$P}enforcement");
    q("DELETE FROM {$P}ndata");
    q("DELETE FROM {$P}heroitems WHERE uid IN ($A_UID,$B_UID)");
    q("UPDATE {$P}heroinventory SET bag = 0 WHERE uid IN ($A_UID,$B_UID)");
    q("UPDATE {$P}odata SET conqured = 0, owner = 3, name = 'Oasis sin ocupar', loyalty = 100");
    q("UPDATE {$P}wdata w JOIN {$P}odata o ON o.wref = w.id SET w.occupied = 0");
}

/** Lanza un ataque/refuerzo ya llegado y lo procesa. */
function dispatch($fromVillage, $target, $troops, $attackType, $phase = 'sendunitsComplete') {
    global $P, $conn;
    $t = array_replace(array_fill(1, 11, 0), $troops);
    q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
       VALUES ($fromVillage,{$t[1]},{$t[2]},{$t[3]},{$t[4]},{$t[5]},{$t[6]},{$t[7]},{$t[8]},{$t[9]},{$t[10]},{$t[11]},$attackType,0,0,0)");
    $att = mysqli_insert_id($conn);
    q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
       VALUES (3,$fromVillage,$target,$att,0,'0,0,0,0,0'," . (time() - 5) . ",0,1,0,0,0,0)");
    @unlink("GameEngine/Prevention/sendunits.txt");
    @unlink("GameEngine/Prevention/sendreinfunits.txt");
    runPhase($phase);
    return $att;
}

/** Pone la Mansión del Héroe de una aldea en el nivel pedido. */
function setMansion($village, $level) {
    global $P;
    $fields = one("SELECT * FROM {$P}fdata WHERE vref = $village");
    for($i = 19; $i <= 40; $i++) {
        if((int)$fields['f'.$i.'t'] === 37) {
            q("UPDATE {$P}fdata SET f{$i} = $level WHERE vref = $village");
            return $i;
        }
    }
    for($i = 19; $i <= 38; $i++) {
        if((int)$fields['f'.$i.'t'] === 0) {
            q("UPDATE {$P}fdata SET f{$i}t = 37, f{$i} = $level WHERE vref = $village");
            return $i;
        }
    }
    return 0;
}

// Un oasis dentro del radio de conquista (3 casillas) de la aldea de A.
$coorA = one("SELECT x, y FROM {$P}wdata WHERE id = $A_VIL");
$oasis = one("SELECT o.wref, o.type, w.x, w.y FROM {$P}odata o
              JOIN {$P}wdata w ON w.id = o.wref
              WHERE ABS(w.x - {$coorA['x']}) <= 3 AND ABS(w.y - {$coorA['y']}) <= 3
              LIMIT 1");
if(!$oasis) { die("No hay ningún oasis a 3 casillas de la aldea $A_VIL; no se puede probar la conquista.\n"); }
$O = (int)$oasis['wref'];
$OTYPE = (int)$oasis['type'];
say("aldea A: $A_VIL ({$coorA['x']}|{$coorA['y']}) · oasis: $O ({$oasis['x']}|{$oasis['y']}) tipo $OTYPE");

// ---------------------------------------------------------------- A) conquista
say("\n== A) el héroe conquista un oasis libre ==");
resetWorld();
setMansion($A_VIL, 20);
q("UPDATE {$P}hero SET dead = 0, health = 100, wref = $A_VIL WHERE uid = $A_UID");
q("UPDATE {$P}units SET hero = 1 WHERE vref = $A_VIL");
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $O");
dispatch($A_VIL, $O, array(11 => 1), 4);

$od = one("SELECT * FROM {$P}odata WHERE wref = $O");
$wd = one("SELECT occupied FROM {$P}wdata WHERE id = $O");
check((int)$od['conqured'] === $A_VIL, "el oasis queda atado a la aldea que lo conquistó");
check((int)$od['owner'] === $A_UID, "el dueño del oasis es el jugador que lo conquistó");
check((int)$od['loyalty'] === 100, "la lealtad arranca en 100%");
check((int)$wd['occupied'] === 1, "el mapa marca el oasis como ocupado");

// ------------------------------------------------ B) bono de producción
say("\n== B) el bono de recursos va a la aldea que lo conquistó ==");
$owned = $database->getOasis($A_VIL);
check(count($owned) === 1 && (int)$owned[0]['wref'] === $O,
      "getOasis() de la aldea conquistadora devuelve el oasis");
$otherVillage = one("SELECT wref FROM {$P}vdata WHERE owner = $A_UID AND wref <> $A_VIL LIMIT 1");
if($otherVillage) {
    check(count($database->getOasis((int)$otherVillage['wref'])) === 0,
          "otra aldea del mismo jugador no cobra el bono");
}
// El bono se calcula sobre `type`, igual que Village::sortOasis().
$expected = array(
    1 => array('wood', 1), 2 => array('wood', 2), 3 => array('wood', 1),
    4 => array('clay', 1), 5 => array('clay', 2), 6 => array('clay', 1),
    7 => array('iron', 1), 8 => array('iron', 2), 9 => array('iron', 1),
    10 => array('crop', 1), 11 => array('crop', 1), 12 => array('crop', 2)
);
check(isset($expected[$OTYPE]), "el tipo de oasis $OTYPE es uno de los 12 conocidos");
if(isset($expected[$OTYPE])) {
    say("     tipo $OTYPE => +" . (25 * $expected[$OTYPE][1]) . "% de " . $expected[$OTYPE][0]);
}

// ------------------------------------- C) repoblación de animales
say("\n== C) sólo los oasis libres repueblan animales ==");
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $O");
q("UPDATE {$P}odata SET lastupdated2 = " . (time() - 200000) . " WHERE wref = $O");
runPhase('regenerateOasisTroops');
$u = one("SELECT u31,u32,u33,u34,u35,u36,u37,u38,u39,u40 FROM {$P}units WHERE vref = $O");
check(array_sum(array_map('intval', $u)) === 0,
      "un oasis conquistado no repuebla animales aunque pasen días");

$free = one("SELECT wref FROM {$P}odata WHERE conqured = 0 AND type IN (1,2,3) LIMIT 1");
$F = (int)$free['wref'];
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $F");
q("UPDATE {$P}odata SET lastupdated2 = " . (time() - 200000) . " WHERE wref = $F");
runPhase('regenerateOasisTroops');
$u = one("SELECT u31,u32,u33,u34,u35,u36,u37,u38,u39,u40 FROM {$P}units WHERE vref = $F");
check(array_sum(array_map('intval', $u)) > 0, "un oasis libre sí repuebla animales pasadas 24 h");
check((int)one("SELECT lastupdated2 FROM {$P}odata WHERE wref = $F")['lastupdated2'] >= time() - 60,
      "la repoblación reinicia el reloj para que no dispare dos veces seguidas");

// ------------------------- C2) el oasis produce hasta su propio granero
say("\n== C2) producción de recursos de un oasis ==");
// Un oasis con granero de 2000: antes el barrido filtraba por un 800 fijo y la
// producción se estancaba ahí, muy por debajo del tope real.
$big = one("SELECT wref, maxstore, maxcrop FROM {$P}odata WHERE maxstore >= 2000 AND maxcrop >= 2000 LIMIT 1");
$BIG = (int)$big['wref'];
$hourly = 8 * (float)SPEED;
q("UPDATE {$P}odata SET wood = 850, clay = 850, iron = 850, crop = 850,
     lastupdated = " . (time() - 3600) . " WHERE wref = $BIG");
runPhase('oasisResourcesProduce');
$grown = one("SELECT wood, clay, iron, crop, lastupdated FROM {$P}odata WHERE wref = $BIG");
say("     tras 1 h: madera {$grown['wood']} (850 + $hourly esperado, tope {$big['maxstore']})");
check((int)$grown['wood'] > 850, "un oasis por encima de 800 sigue produciendo");
check(abs((int)$grown['wood'] - (850 + $hourly)) <= 1, "produce 8 por hora y por recurso, escalado por la velocidad");
check((int)$grown['crop'] === (int)$grown['wood'], "los cuatro recursos crecen igual");
check((int)$grown['lastupdated'] >= time() - 60, "la producción adelanta el reloj del oasis");

// El tope es el granero propio, y no se pasa por mucho tiempo que haya corrido.
q("UPDATE {$P}odata SET wood = 0, clay = 0, iron = 0, crop = 0,
     lastupdated = " . (time() - 864000) . " WHERE wref = $BIG");
runPhase('oasisResourcesProduce');
$full = one("SELECT wood, clay, iron, crop FROM {$P}odata WHERE wref = $BIG");
check((int)$full['wood'] === (int)$big['maxstore'], "llena hasta maxstore y no lo pasa");
check((int)$full['crop'] === (int)$big['maxcrop'], "el cereal llena hasta maxcrop");

// Un oasis lleno ya no se toca.
q("UPDATE {$P}odata SET lastupdated = " . (time() - 3600) . " WHERE wref = $BIG");
runPhase('oasisResourcesProduce');
check((int)one("SELECT lastupdated FROM {$P}odata WHERE wref = $BIG")['lastupdated'] === time() - 3600,
      "un oasis lleno no entra en el barrido");

// Un reloj adelantado no puede restar recursos.
q("UPDATE {$P}odata SET wood = 500, lastupdated = " . (time() + 3600) . " WHERE wref = $BIG");
runPhase('oasisResourcesProduce');
check((int)one("SELECT wood FROM {$P}odata WHERE wref = $BIG")['wood'] === 500,
      "un reloj adelantado no descuenta recursos");

// Saquear un oasis libre lo pone al día primero. Con un reloj muy viejo eso llegaba
// a sumar decenas de miles de recursos: producía a 40 por hora en vez de 8 y sin tope.
q("DELETE FROM {$P}ndata"); q("DELETE FROM {$P}movement"); q("DELETE FROM {$P}attacks");
q("UPDATE {$P}odata SET conqured = 0, owner = 3, wood = 0, clay = 0, iron = 0, crop = 0,
     lastupdated = " . (time() - 30 * 86400) . " WHERE wref = $BIG");
q("UPDATE {$P}wdata SET occupied = 0 WHERE id = $BIG");
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $BIG");
q("UPDATE {$P}units SET u1 = 5000 WHERE vref = $B_VIL");
dispatch($B_VIL, $BIG, array(1 => 2), 4);
$raided = one("SELECT wood, clay, iron, crop FROM {$P}odata WHERE wref = $BIG");
foreach(array('wood','clay','iron') as $res) {
    check((int)$raided[$res] <= (int)$big['maxstore'],
          "tras 30 días sin tocarse, el saqueo no infla $res por encima del granero");
}
check((int)$raided['crop'] <= (int)$big['maxcrop'],
      "tras 30 días sin tocarse, el saqueo no infla el cereal por encima del granero");
q("UPDATE {$P}units SET u1 = 0 WHERE vref = $B_VIL");

// ------------------------------- D) ataque de un tercero al oasis ocupado
say("\n== D) un tercero saquea el oasis ocupado ==");
q("DELETE FROM {$P}ndata"); q("DELETE FROM {$P}movement"); q("DELETE FROM {$P}attacks");
q("UPDATE {$P}odata SET wood = 700, clay = 700, iron = 700, crop = 700, lastraid = 0,
     lastupdated = " . time() . " WHERE wref = $O");
// La aldea llena y con la producción al día, para que el 10% sea predecible.
q("UPDATE {$P}vdata SET wood = 4000, clay = 4000, iron = 4000, crop = 4000,
     maxstore = 8000, maxcrop = 8000, lastupdate = " . time() . " WHERE wref = $A_VIL");
q("UPDATE {$P}units SET u1 = 5000, u3 = 2000 WHERE vref = $B_VIL");
$villageBefore = one("SELECT wood,clay,iron,crop FROM {$P}vdata WHERE wref = $A_VIL");
$oasisBefore = one("SELECT wood,clay,iron,crop FROM {$P}odata WHERE wref = $O");
dispatch($B_VIL, $O, array(1 => 2000, 3 => 1000), 4);

$defenderReport = one("SELECT * FROM {$P}ndata WHERE uid = $A_UID AND ntype IN (4,5,6,7) ORDER BY id DESC LIMIT 1");
$attackerReport = one("SELECT * FROM {$P}ndata WHERE uid = $B_UID AND ntype IN (1,2,3) ORDER BY id DESC LIMIT 1");
check((bool)$defenderReport, "el dueño del oasis recibe el informe de defensa");
check($defenderReport && (int)$defenderReport['toWref'] === $O, "el informe apunta al oasis atacado");
check((bool)$attackerReport, "el atacante recibe su informe");
check(count(rows("SELECT id FROM {$P}ndata WHERE uid = 0")) === 0, "no se generan informes huérfanos");

$villageAfter = one("SELECT wood,clay,iron,crop FROM {$P}vdata WHERE wref = $A_VIL");
$oasisAfter = one("SELECT wood,clay,iron,crop FROM {$P}odata WHERE wref = $O");
$fromVillage = array();
foreach(array('wood','clay','iron','crop') as $res) {
    $fromVillage[$res] = (int)round($villageBefore[$res] - $villageAfter[$res]);
}
say("     robado a la aldea: " . json_encode($fromVillage) . " (10% de 4000 = 400 por recurso)");
check(array_sum($fromVillage) > 0, "el saqueo de un oasis anexado sale de la aldea que lo tiene");
foreach($fromVillage as $res => $taken) {
    check($taken <= 400, "no se lleva más del 10% de $res");
}
check(array_sum(array_map('intval', $oasisAfter)) === array_sum(array_map('intval', $oasisBefore)),
      "el stock propio del oasis anexado ya no se toca");
$clock = (int)one("SELECT lastraid FROM {$P}odata WHERE wref = $O")['lastraid'];
check($clock > 0, "el saqueo arranca el reloj del cupo del 10%");

// Un segundo saqueo inmediato no encuentra nada: el cupo no se repuso.
q("DELETE FROM {$P}ndata"); q("DELETE FROM {$P}movement"); q("DELETE FROM {$P}attacks");
$beforeSecond = one("SELECT wood,clay,iron,crop FROM {$P}vdata WHERE wref = $A_VIL");
dispatch($B_VIL, $O, array(1 => 2000, 3 => 1000), 4);
$afterSecond = one("SELECT wood,clay,iron,crop FROM {$P}vdata WHERE wref = $A_VIL");
$secondTotal = 0;
foreach(array('wood','clay','iron','crop') as $res) {
    $secondTotal += (int)round($beforeSecond[$res] - $afterSecond[$res]);
}
say("     robado en el segundo saqueo inmediato: $secondTotal");
check($secondTotal === 0, "saquear de nuevo enseguida no rinde nada: el cupo no se repuso");

// A mitad de la ventana el cupo está a la mitad.
q("DELETE FROM {$P}ndata"); q("DELETE FROM {$P}movement"); q("DELETE FROM {$P}attacks");
q("UPDATE {$P}odata SET lastraid = " . (time() - 305) . " WHERE wref = $O");
q("UPDATE {$P}vdata SET wood = 4000, clay = 4000, iron = 4000, crop = 4000,
     lastupdate = " . time() . " WHERE wref = $A_VIL");
$beforeThird = one("SELECT wood FROM {$P}vdata WHERE wref = $A_VIL");
dispatch($B_VIL, $O, array(1 => 2000, 3 => 1000), 4);
$afterThird = one("SELECT wood FROM {$P}vdata WHERE wref = $A_VIL");
$thirdWood = (int)round($beforeThird['wood'] - $afterThird['wood']);
say("     madera robada a los 5 minutos: $thirdWood (medio cupo de 400 = 200)");
check($thirdWood > 150 && $thirdWood <= 210,
      "a mitad de la ventana el cupo está a la mitad");

// El reparto del cupo en el tiempo es lineal.
$now = time();
check(abs($automation->oasisRaidShare(0, $now) - 0.10) < 1e-9, "sin saqueos previos el cupo está entero");
check(abs($automation->oasisRaidShare($now - 600, $now) - 0.10) < 1e-9, "a los 10 minutos el cupo está entero");
check(abs($automation->oasisRaidShare($now - 300, $now) - 0.05) < 1e-9, "a los 5 minutos hay medio cupo");
check($automation->oasisRaidShare($now, $now) === 0.0, "recién saqueado no queda cupo");
check($automation->oasisRaidShare($now + 60, $now) === 0.0, "un reloj adelantado no da cupo negativo");
check($automation->oasisRaidClock(100, 100, $now) === $now, "llevarse todo el cupo reinicia el reloj");
check($automation->oasisRaidClock(0, 100, $now) === $now - 600, "no llevarse nada deja el cupo intacto");
check($automation->oasisRaidClock(50, 100, $now) === $now - 300, "llevarse la mitad consume la mitad del cupo");
check($automation->oasisRaidClock(10, 0, $now) === $now, "un cupo en cero no divide por cero");

// ---------------------------------------- E) refuerzo a un oasis propio
say("\n== E) refuerzo a un oasis ocupado ==");
q("DELETE FROM {$P}ndata"); q("DELETE FROM {$P}movement"); q("DELETE FROM {$P}attacks"); q("DELETE FROM {$P}enforcement");
q("UPDATE {$P}units SET u1 = 500 WHERE vref = $A_VIL");
dispatch($A_VIL, $O, array(1 => 120), 2, 'sendreinfunitsComplete');

$enforce = one("SELECT * FROM {$P}enforcement WHERE vref = $O AND `from` = $A_VIL");
check($enforce && (int)$enforce['u1'] === 120, "las tropas quedan estacionadas en el oasis");
$reinfReport = one("SELECT * FROM {$P}ndata WHERE uid = $A_UID AND ntype = 8 ORDER BY id DESC LIMIT 1");
check((bool)$reinfReport, "quien refuerza recibe su informe");
check($reinfReport && (int)$reinfReport['toWref'] === $O,
      "el informe de refuerzo apunta al oasis y no a la aldea 0");
check($reinfReport && strpos($reinfReport['topic'], 'reforzó Oasis') !== false,
      "el informe nombra al oasis: \"" . ($reinfReport ? $reinfReport['topic'] : '') . "\"");
check(count(rows("SELECT id FROM {$P}ndata WHERE uid = 0")) === 0,
      "reforzar un oasis no crea el informe fantasma con uid 0");

// -------------------- E2) el cereal de esas tropas lo paga la aldea del oasis
say("\n== E2) la consumo de cereal de las tropas del oasis la paga la aldea que lo tiene ==");
$withOasisTroops = $technology->getUpkeep($technology->getAllUnits($A_VIL), 0, $A_VIL);
q("DELETE FROM {$P}enforcement WHERE vref = $O");
$withoutOasisTroops = $technology->getUpkeep($technology->getAllUnits($A_VIL), 0, $A_VIL);
say("     consumo de cereal con las 120 tropas en el oasis: $withOasisTroops · sin ellas: $withoutOasisTroops");
check($withOasisTroops - $withoutOasisTroops === 120,
      "las 120 tropas del oasis suman su cereal a la aldea que lo conquistó");
// Y no se las cobra a nadie más: el oasis no es una aldea.
check(count($database->getOasis($B_VIL)) === 0,
      "la aldea de otro jugador no paga por las tropas de este oasis");

// ----------------------- E3) sólo el dueño y sus aliados pueden reforzar
say("\n== E3) quién puede reforzar el oasis ==");
$reinforceError = new ReflectionMethod('Units', 'oasisReinforcementError');
$reinforceError->setAccessible(true);
$units = (new ReflectionClass('Units'))->newInstanceWithoutConstructor();
$sessionBackup = isset($session) ? $session : null;
$session = new stdClass;

$session->uid = $A_UID; $session->alliance = 0;
check($reinforceError->invoke($units, 0, 3) !== '', "no se puede reforzar un oasis sin ocupar");
check($reinforceError->invoke($units, $A_VIL, $A_UID) === '', "el dueño puede reforzar su oasis");
check($reinforceError->invoke($units, $B_VIL, $B_UID) !== '',
      "sin alianza no se puede reforzar el oasis de otro");

q("UPDATE {$P}users SET alliance = 99 WHERE id IN ($A_UID,$B_UID)");
$session->alliance = 99;
check($reinforceError->invoke($units, $B_VIL, $B_UID) === '',
      "un compañero de alianza sí puede reforzarlo");
q("UPDATE {$P}users SET alliance = 99 WHERE id = $A_UID");
q("UPDATE {$P}users SET alliance = 98 WHERE id = $B_UID");
check($reinforceError->invoke($units, $B_VIL, $B_UID) !== '',
      "una alianza distinta y sin pacto no puede reforzarlo");
q("UPDATE {$P}users SET alliance = 0 WHERE id IN ($A_UID,$B_UID)");
$session = $sessionBackup;

// --------------------------------------------- E4) espionaje sobre el oasis
say("\n== E4) espiar oasis ==");
q("DELETE FROM {$P}enforcement"); q("DELETE FROM {$P}ndata");
q("UPDATE {$P}units SET u4 = 20 WHERE vref = $A_VIL");

/**
 * Espía el objetivo desde la aldea de A y devuelve cuántos espías vuelven.
 * Limpia los movimientos antes: si mueren todos no se crea el regreso, y sin
 * limpiar se leería el de la corrida anterior.
 */
function spyRun($target) {
    global $P, $A_VIL;
    q("DELETE FROM {$P}ndata");
    q("DELETE FROM {$P}movement");
    q("DELETE FROM {$P}attacks");
    dispatch($A_VIL, $target, array(4 => 20), 1);
    $back = one("SELECT a.t4 FROM {$P}movement m JOIN {$P}attacks a ON a.id = m.ref
                 WHERE m.sort_type = 4 AND m.`to` = $A_VIL ORDER BY m.moveid DESC LIMIT 1");
    return $back ? (int)$back['t4'] : 0;
}

// Un oasis libre lleno de murciélagos: la Naturaleza no tiene espías, así que la
// exploración vuelve entera y sin ser detectada.
q("UPDATE {$P}odata SET conqured = 0, owner = 3, name = 'Oasis sin ocupar' WHERE wref = $O");
q("UPDATE {$P}wdata SET occupied = 0 WHERE id = $O");
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=50,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $O");
check(spyRun($O) === 20, "un oasis libre con 50 murciélagos no mata espías: la Naturaleza no explora");
check((bool)one("SELECT id FROM {$P}ndata WHERE uid = $A_UID AND ntype = 22"),
      "el espionaje de un oasis libre no se detecta");
check(count(rows("SELECT id FROM {$P}ndata WHERE uid = 3")) === 0,
      "la Naturaleza no recibe informes de espionaje");

// Un oasis anexado sin tropas dentro tampoco resiste el espionaje.
q("UPDATE {$P}units SET u34 = 0 WHERE vref = $O");
q("UPDATE {$P}odata SET conqured = $A_VIL, owner = $A_UID, name = 'Oasis conquistado' WHERE wref = $O");
q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $O");
check(spyRun($O) === 20, "un oasis anexado sin tropas dentro se espía sin bajas");

// Con espías estacionados en el oasis sí resiste: la defensa la dan las tropas que
// están DENTRO del oasis, no las de la aldea que lo tiene.
q("INSERT INTO {$P}enforcement (vref,`from`,u4) VALUES ($O,$A_VIL,100)");
check(spyRun($O) === 0, "100 espías estacionados en el oasis matan a los 20 exploradores");
q("DELETE FROM {$P}enforcement");
q("UPDATE {$P}units SET u4 = 5000 WHERE vref = $A_VIL");
check(spyRun($O) === 20,
      "los espías de la aldea que tiene el oasis no lo defienden: tienen que estar dentro");
q("UPDATE {$P}units SET u4 = 0 WHERE vref = $A_VIL");
q("DELETE FROM {$P}ndata");

// --------------------------- E5) sólo se conquista un oasis cercano
say("\n== E5) alcance de la conquista ==");
$farOasis = one("SELECT o.wref, w.x, w.y FROM {$P}odata o JOIN {$P}wdata w ON w.id = o.wref
                 WHERE o.conqured = 0 AND (ABS(w.x - {$coorA['x']}) > 3 OR ABS(w.y - {$coorA['y']}) > 3)
                 LIMIT 1");
$FAR = (int)$farOasis['wref'];
q("UPDATE {$P}units SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $FAR");
q("UPDATE {$P}odata SET conqured = 0, owner = 3, loyalty = 100 WHERE wref = $FAR");
q("UPDATE {$P}hero SET dead = 0, health = 100, wref = $A_VIL WHERE uid = $A_UID");
q("UPDATE {$P}units SET hero = 1 WHERE vref = $A_VIL");
q("DELETE FROM {$P}ndata");
dispatch($A_VIL, $FAR, array(11 => 1), 4);
$farData = one("SELECT conqured, owner, loyalty FROM {$P}odata WHERE wref = $FAR");
check((int)$farData['conqured'] === 0 && (int)$farData['owner'] === 3,
      "un oasis a más de 3 casillas no se conquista");
check((int)$farData['loyalty'] === 100,
      "un oasis fuera de alcance no pierde lealtad: sólo se lo puede atacar");
$farReport = one("SELECT data FROM {$P}ndata WHERE uid = $A_UID ORDER BY id DESC LIMIT 1");
check($farReport && strpos($farReport['data'], 'demasiado lejos') !== false,
      "el informe explica por qué no se conquistó");

// ------------------------- F) la aldea cambia de dueño: los oasis la siguen
say("\n== F) al conquistar la aldea, los oasis cambian de dueño ==");
$transfer = $database->transferVillageOases($A_VIL, $B_UID);
$od = one("SELECT owner, conqured FROM {$P}odata WHERE wref = $O");
check((int)$od['owner'] === $B_UID, "el oasis pasa al nuevo dueño de la aldea");
check((int)$od['conqured'] === $A_VIL, "el oasis sigue atado a la misma aldea");
$database->transferVillageOases($A_VIL, $A_UID);

// ----------------------------- G) la aldea desaparece: los oasis se liberan
say("\n== G) al borrar la aldea, los oasis vuelven a quedar libres ==");
$released = $database->releaseVillageOases($A_VIL);
$od = one("SELECT * FROM {$P}odata WHERE wref = $O");
$wd = one("SELECT occupied FROM {$P}wdata WHERE id = $O");
check($released === 1, "se liberó el oasis de la aldea borrada");
check((int)$od['conqured'] === 0 && (int)$od['owner'] === 3, "el oasis queda sin aldea y sin dueño");
check((int)$od['loyalty'] === 100, "la lealtad vuelve al 100%");
check((int)$wd['occupied'] === 0, "el mapa deja de marcarlo como ocupado");
check((int)$od['lastupdated2'] >= time() - 60,
      "el reloj de repoblación arranca al liberarlo, no repuebla de golpe");
check(count($database->getOasis($A_VIL)) === 0, "la aldea deja de cobrar el bono");

// --------------------- H) no se puede soltar el oasis de otro jugador
say("\n== H) soltar un oasis ajeno desde la Mansión del Héroe ==");
q("UPDATE {$P}odata SET conqured = $B_VIL, owner = $B_UID, name = 'Oasis conquistado' WHERE wref = $O");
q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $O");
$session = new stdClass; $session->uid = $A_UID;
$village = new stdClass; $village->wid = $A_VIL; $village->vname = 'Aldea de MercaderTest';
$_GET = array('gid' => '37', 'del' => (string)$O);
$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) { $errors[] = "$str ($file:$line)"; return true; });
ob_start();
include "Templates/Build/37_heromansion.tpl";
$html = ob_get_clean();
restore_error_handler();
$od = one("SELECT conqured, owner FROM {$P}odata WHERE wref = $O");
check((int)$od['conqured'] === $B_VIL && (int)$od['owner'] === $B_UID,
      "el oasis de otro jugador no se puede soltar desde la aldea propia");
check(!$errors, "la Mansión del Héroe renderiza sin avisos de PHP" . ($errors ? ": " . implode(' | ', $errors) : ""));

$_GET = array('gid' => '37', 'del' => "$O; DROP TABLE {$P}odata");
ob_start();
include "Templates/Build/37_heromansion.tpl";
ob_end_clean();
check((bool)one("SELECT wref FROM {$P}odata WHERE wref = $O"), "un `del` no numérico no llega al SQL");

// ------------------------------------------------------------- limpieza
resetWorld();
q("UPDATE {$P}units SET u1 = 0, u3 = 0 WHERE vref = $B_VIL");
say("\n" . ($failures === 0 ? "TODO OK" : "$failures COMPROBACIONES FALLARON"));
exit($failures === 0 ? 0 : 1);
