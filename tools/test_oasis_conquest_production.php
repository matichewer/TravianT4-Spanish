<?php
/*
 * Prueba end-to-end: conquistar un oasis no puede pagar su bonus retroactivamente.
 * Uso (dentro del contenedor web, desde la raíz del repo):
 *   php tools/test_oasis_conquest_production.php --destructivo
 *
 * La producción de una aldea se acredita desde `lastupdate` hasta ahora a la tarifa
 * vigente en el momento de acreditar. Si la conquista del oasis cambia el conjunto
 * de oasis anexados sin cerrar antes el tramo abierto, las horas que la aldea llevaba
 * sin actualizarse se cobran con el +25% del oasis recién ganado. Medido antes del
 * arreglo sobre esta misma aldea: una hora previa pagaba 100 de barro en vez de 80.
 *
 * Es destructivo sobre el mundo local de pruebas: no correr contra producción.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Borra movimientos y ataques del mundo local, y toca la aldea 797.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_oasis_conquest_production.php --destructivo\n");
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
$ELAPSED = 3600;                // la aldea lleva una hora sin actualizarse
$failures = 0;

function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

$vil = one("SELECT v.wref, w.x, w.y FROM {$P}vdata v JOIN {$P}wdata w ON w.id = v.wref WHERE v.wref = $VILLAGE");
if(!$vil) {
    die("No existe la aldea $VILLAGE en el mundo local.\n");
}

// Un oasis dentro del radio de anexión, para que la conquista sea posible.
$oasis = null;
$r = q("SELECT o.wref, o.type, w.x, w.y FROM {$P}odata o JOIN {$P}wdata w ON w.id = o.wref");
while($row = mysqli_fetch_assoc($r)) {
    if(Automation::oasisWithinAnnexationRange($vil['x'], $vil['y'], $row['x'], $row['y'])) {
        $oasis = $row;
        break;
    }
}
if(!$oasis) {
    die("La aldea $VILLAGE no tiene ningún oasis en rango en este mundo.\n");
}
$OASIS = (int)$oasis['wref'];
$OTYPE = (int)$oasis['type'];
say("aldea $VILLAGE ({$vil['x']}|{$vil['y']}) · oasis $OASIS ({$oasis['x']}|{$oasis['y']}) tipo $OTYPE");

// Estado guardado, para dejar el mundo como estaba.
$savedField = one("SELECT f22, f22t FROM {$P}fdata WHERE vref = $VILLAGE");
$savedVillage = one("SELECT wood, clay, iron, crop, maxstore, maxcrop, lastupdate FROM {$P}vdata WHERE wref = $VILLAGE");
$savedOasis = one("SELECT conqured, owner, loyalty, name, lastupdated, lastupdated2 FROM {$P}odata WHERE wref = $OASIS");
$savedUnits = one("SELECT * FROM {$P}units WHERE vref = $OASIS");
// Los oasis que la aldea ya tenía: el nivel de mansión exigido sube 5 por cada uno,
// así que el escenario tiene que arrancar con la aldea sin ninguno.
$savedHeld = array();
$r = q("SELECT wref, owner FROM {$P}odata WHERE conqured = $VILLAGE");
while($row = mysqli_fetch_assoc($r)) { $savedHeld[] = $row; }
$savedHero = one("SELECT dead, health, wref, home FROM {$P}hero WHERE uid = $UID");
// Qué lleva el héroe en la bolsa: con jaulas equipadas el ataque toma el camino de
// captura de animales y nunca llega a la batalla, así que tampoco anexa. El mundo
// local es compartido y puede tener jaulas puestas de otras pruebas.
$savedBag = one("SELECT bag FROM {$P}heroinventory WHERE uid = $UID");

function restore() {
    global $P, $VILLAGE, $OASIS, $UID, $savedField, $savedVillage, $savedOasis, $savedUnits, $savedHero, $savedHeld, $savedBag;
    if($savedBag) {
        q("UPDATE {$P}heroinventory SET bag = ".(int)$savedBag['bag']." WHERE uid = $UID");
    }
    q("UPDATE {$P}odata SET conqured = 0, owner = 3 WHERE conqured = $VILLAGE");
    q("UPDATE {$P}wdata SET occupied = 0 WHERE id = $OASIS");
    foreach($savedHeld as $held) {
        q("UPDATE {$P}odata SET conqured = $VILLAGE, owner = ".(int)$held['owner']." WHERE wref = ".(int)$held['wref']);
        q("UPDATE {$P}wdata SET occupied = 1 WHERE id = ".(int)$held['wref']);
    }
    q("UPDATE {$P}fdata SET f22 = ".(int)$savedField['f22'].", f22t = ".(int)$savedField['f22t']." WHERE vref = $VILLAGE");
    q("UPDATE {$P}vdata SET wood = ".(float)$savedVillage['wood'].", clay = ".(float)$savedVillage['clay']
        .", iron = ".(float)$savedVillage['iron'].", crop = ".(float)$savedVillage['crop']
        .", maxstore = ".(int)$savedVillage['maxstore'].", maxcrop = ".(int)$savedVillage['maxcrop']
        .", lastupdate = ".(int)$savedVillage['lastupdate']." WHERE wref = $VILLAGE");
    q("UPDATE {$P}odata SET conqured = ".(int)$savedOasis['conqured'].", owner = ".(int)$savedOasis['owner']
        .", loyalty = ".(int)$savedOasis['loyalty'].", name = '".mysql_real_escape_string($savedOasis['name'])."'"
        ." WHERE wref = $OASIS");
    q("UPDATE {$P}wdata SET occupied = ".((int)$savedOasis['conqured'] ? 1 : 0)." WHERE id = $OASIS");
    if($savedUnits) {
        $sets = array();
        for($i = 1; $i <= 50; $i++) { $sets[] = "u$i = ".(int)$savedUnits['u'.$i]; }
        q("UPDATE {$P}units SET ".implode(',', $sets)." WHERE vref = $OASIS");
    }
    if($savedHero) {
        q("UPDATE {$P}hero SET dead = ".(int)$savedHero['dead'].", health = ".(float)$savedHero['health']
            .", wref = ".(int)$savedHero['wref'].", home = ".(int)$savedHero['home']." WHERE uid = $UID");
    }
}

// --------------------------------------------------------------- escenario
// Mansión del héroe nivel 10 (alcanza para el primer oasis), oasis libre y vacío
// de animales para que el héroe gane sin bajas, y la aldea con una hora de
// producción sin acreditar.
q("UPDATE {$P}fdata SET f22 = 10, f22t = 37 WHERE vref = $VILLAGE");
foreach($savedHeld as $held) {
    q("UPDATE {$P}odata SET conqured = 0, owner = 3 WHERE wref = ".(int)$held['wref']);
    q("UPDATE {$P}wdata SET occupied = 0 WHERE id = ".(int)$held['wref']);
}
// `lastupdated2` al día: el barrido repuebla los oasis libres cada 24 h y si no se
// frena vuelve a meter animales antes de que se procese el ataque, con lo que queda
// algún defensor vivo y la anexión no se dispara.
q("UPDATE {$P}odata SET conqured = 0, owner = 3, loyalty = 100, name = 'Oasis sin ocupar',"
    ." lastupdated = ".time().", lastupdated2 = ".time()." WHERE wref = $OASIS");
q("UPDATE {$P}wdata SET occupied = 0 WHERE id = $OASIS");
$zero = array();
for($i = 1; $i <= 50; $i++) { $zero[] = "u$i = 0"; }
q("UPDATE {$P}units SET ".implode(',', $zero)." WHERE vref = $OASIS");
q("DELETE FROM {$P}enforcement WHERE vref = $OASIS");
// `home` fijo en la aldea: el bonus de recursos del héroe se calcula sobre su aldea
// natal, no sobre dónde está parado, así que atado acá aporta lo mismo antes y
// después del ataque y no ensucia la comparación.
q("UPDATE {$P}hero SET dead = 0, health = 100, wref = $VILLAGE, home = $VILLAGE WHERE uid = $UID");
q("UPDATE {$P}heroinventory SET bag = 0 WHERE uid = $UID");

q("DELETE FROM {$P}movement");
q("DELETE FROM {$P}attacks");

// Producción esperada para la hora previa, con y sin el oasis, según la única
// fórmula del juego. Se mide sobre el barro o el hierro, que no tienen consumo:
// el cereal netea población y tropas y ensucia la comparación.
$resarray = $database->getResourceLevel($VILLAGE);
$bonusFlags = villageGoldBonusFlags($database, $UID);
$withoutOasis = villageGrossProduction($resarray, array(0, 0, 0, 0), $bonusFlags, SPEED);
$withOasis = villageGrossProduction($resarray, villageOasisCounter(array(array('type' => $OTYPE))), $bonusFlags, SPEED);

$watched = null;
foreach(array('clay', 'iron', 'wood') as $resource) {
    if($withOasis['production'][$resource] > $withoutOasis['production'][$resource]) {
        $watched = $resource;
        break;
    }
}
if($watched === null) {
    restore();
    die("El oasis $OASIS (tipo $OTYPE) no da bonus de madera, barro ni hierro: no sirve para medir.\n");
}

// El motor suma el aporte del héroe sobre la producción bruta (bountycalculateProduction),
// así que la línea base tiene que incluirlo o la comparación queda corrida.
$heroProduction = heroVillageResourceBonus($database->getHeroData($UID), $VILLAGE, SPEED);
$expectedPlain = ($withoutOasis['production'][$watched] + $heroProduction[$watched]) / 3600 * $ELAPSED;
$expectedRetro = ($withOasis['production'][$watched] + $heroProduction[$watched]) / 3600 * $ELAPSED;
say(sprintf("  recurso medido: %s · una hora sin oasis = %.1f · con oasis = %.1f", $watched, $expectedPlain, $expectedRetro));
if($expectedPlain <= 0) {
    restore();
    die("La aldea no produce $watched: el escenario no puede distinguir los dos casos.\n");
}

$startedAt = time();
q("UPDATE {$P}vdata SET wood = 0, clay = 0, iron = 0, crop = 0, maxstore = 9999999, maxcrop = 9999999,"
    ." lastupdate = ".($startedAt - $ELAPSED)." WHERE wref = $VILLAGE");

$arrival = time() - 5;
q("INSERT INTO {$P}attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy)
   VALUES ($VILLAGE,0,0,0,0,0,0,0,0,0,0,1,3,0,0,0)");
$att = mysqli_insert_id($conn);
q("INSERT INTO {$P}movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)
   VALUES (3,$VILLAGE,$OASIS,$att,0,'0,0,0,0,0',$arrival,0,1,0,0,0,0)");

@unlink("GameEngine/Prevention/sendunits.txt");
new Automation;

// --------------------------------------------------------------- veredicto
$after = one("SELECT wood, clay, iron, crop, lastupdate FROM {$P}vdata WHERE wref = $VILLAGE");
$oasisAfter = one("SELECT conqured, owner FROM {$P}odata WHERE wref = $OASIS");
$heroAfter = one("SELECT dead, health, wref FROM {$P}hero WHERE uid = $UID");
$report = one("SELECT ntype, topic FROM {$P}ndata WHERE uid = $UID ORDER BY id DESC LIMIT 1");
$got = (float)$after[$watched];

say(sprintf("  héroe: dead=%d health=%.0f · informe: ntype=%s \"%s\"",
    (int)$heroAfter['dead'], (float)$heroAfter['health'],
    $report ? $report['ntype'] : '-', $report ? $report['topic'] : '-'));

say(sprintf("  tras la conquista: %s = %.1f · lastupdate avanzó %d s", $watched, $got, (int)$after['lastupdate'] - ($startedAt - $ELAPSED)));

check((int)$oasisAfter['conqured'] === $VILLAGE, "el héroe conquistó el oasis");
check((int)$oasisAfter['owner'] === $UID, "el oasis quedó a nombre del dueño de la aldea");
check((int)$after['lastupdate'] >= $startedAt - 5,
      "la conquista cerró el tramo de producción abierto (lastupdate quedó al día)");
check(abs($got - $expectedPlain) <= 1.0,
      sprintf("la hora previa se pagó a la tarifa vieja: %.1f ≈ %.1f", $got, $expectedPlain));
check($got < $expectedRetro - 1.0,
      sprintf("y NO a la tarifa con el oasis recién ganado (%.1f)", $expectedRetro));

restore();
say("\nEstado del mundo restaurado.");

if($failures > 0) {
    say("\n$failures comprobación(es) fallaron.");
    exit(1);
}
say("\nTodo OK.");
exit(0);
