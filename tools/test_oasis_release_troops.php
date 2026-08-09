<?php
/*
 * Prueba end-to-end: soltar un oasis manda los refuerzos de vuelta a su aldea.
 * Uso (dentro del contenedor web, desde la raíz del repo):
 *   php tools/test_oasis_release_troops.php --destructivo
 *
 * Antes, `removeOases()` dejaba las filas de `enforcement` apuntando a un oasis que ya
 * no era de nadie: las tropas seguían comiendo cereal de su aldea, peleaban contra
 * quien intentara conquistarlo y sólo volvían si el dueño se acordaba de traerlas a
 * mano desde el punto de reunión.
 *
 * Es destructivo sobre el mundo local de pruebas: no correr contra producción.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR, "Toca refuerzos, movimientos y ataques del mundo local.\n"
        . "Ejecutalo sólo contra el Docker de desarrollo:\n"
        . "  php tools/test_oasis_release_troops.php --destructivo\n");
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

global $database, $generator, $technology;

// El constructor de Automation corre el barrido completo del mundo (y borra cuentas
// inactivas). Acá sólo se necesita un método suelto, así que se instancia sin él.
$automationClass = new ReflectionClass('Automation');
$automation = $automationClass->newInstanceWithoutConstructor();

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
$UID = 5;                       // MercaderTest, romano (tribu 1 -> u1..u10)
$VILLAGE = 797;
$failures = 0;

function check($ok, $label) {
    global $failures;
    if(!$ok) { $failures++; }
    say(($ok ? "  [ok]   " : "  [FALLA] ") . $label);
}

$vil = one("SELECT v.wref, w.x, w.y FROM {$P}vdata v JOIN {$P}wdata w ON w.id = v.wref WHERE v.wref = $VILLAGE");
if(!$vil) { die("No existe la aldea $VILLAGE en el mundo local.\n"); }
$tribe = (int)$database->getUserField($UID, 'tribe', 0);

// Un oasis en rango, para que el escenario sea el real.
$oasis = null;
$r = q("SELECT o.wref, w.x, w.y FROM {$P}odata o JOIN {$P}wdata w ON w.id = o.wref");
while($row = mysqli_fetch_assoc($r)) {
    if(Automation::oasisWithinAnnexationRange($vil['x'], $vil['y'], $row['x'], $row['y'])) { $oasis = $row; break; }
}
if(!$oasis) { die("La aldea $VILLAGE no tiene ningún oasis en rango.\n"); }
$OASIS = (int)$oasis['wref'];
say("aldea $VILLAGE ({$vil['x']}|{$vil['y']}) · oasis $OASIS ({$oasis['x']}|{$oasis['y']}) · tribu $tribe");

$savedOasis = one("SELECT conqured, owner, loyalty, name FROM {$P}odata WHERE wref = $OASIS");
$savedHero = one("SELECT wref, home FROM {$P}hero WHERE uid = $UID");

function cleanup() {
    global $P, $OASIS, $VILLAGE, $UID, $savedOasis, $savedHero;
    q("DELETE FROM {$P}enforcement WHERE vref = $OASIS");
    q("DELETE FROM {$P}movement WHERE `from` = $OASIS");
    q("UPDATE {$P}odata SET conqured = ".(int)$savedOasis['conqured'].", owner = ".(int)$savedOasis['owner']
        .", loyalty = ".(int)$savedOasis['loyalty']." WHERE wref = $OASIS");
    q("UPDATE {$P}wdata SET occupied = ".((int)$savedOasis['conqured'] ? 1 : 0)." WHERE id = $OASIS");
    if($savedHero) {
        q("UPDATE {$P}hero SET wref = ".(int)$savedHero['wref'].", home = ".(int)$savedHero['home']." WHERE uid = $UID");
    }
}

/** Deja el oasis en manos de la aldea, con un refuerzo adentro. */
function setup($units, $hero) {
    global $P, $OASIS, $VILLAGE, $UID, $conn, $tribe;
    q("DELETE FROM {$P}enforcement WHERE vref = $OASIS");
    q("DELETE FROM {$P}movement WHERE `from` = $OASIS");
    q("UPDATE {$P}odata SET conqured = $VILLAGE, owner = $UID, loyalty = 100 WHERE wref = $OASIS");
    q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $OASIS");
    q("UPDATE {$P}hero SET wref = ".($hero ? $OASIS : $VILLAGE).", home = $VILLAGE, dead = 0 WHERE uid = $UID");

    $cols = array('vref', '`from`', 'hero');
    $vals = array($OASIS, $VILLAGE, (int)$hero);
    foreach($units as $slot => $amount) {
        $cols[] = 'u'.(($tribe - 1) * 10 + $slot);
        $vals[] = (int)$amount;
    }
    q("INSERT INTO {$P}enforcement (".implode(',', $cols).") VALUES (".implode(',', $vals).")");
    return mysqli_insert_id($conn);
}

// ------------------------------------------------- A) refuerzo sólo de tropas
say("\n== A) refuerzo de 10 legionarios y 5 pretorianos ==");
$id = setup(array(1 => 10, 2 => 5), 0);
$before = time();
$sent = $automation->returnOasisReinforcements($OASIS);

$row = one("SELECT * FROM {$P}enforcement WHERE id = $id");
$move = one("SELECT * FROM {$P}movement WHERE `from` = $OASIS AND `to` = $VILLAGE AND sort_type = 4 ORDER BY moveid DESC LIMIT 1");
$att = $move ? one("SELECT * FROM {$P}attacks WHERE id = ".(int)$move['ref']) : null;

check($sent === 1, "informa 1 refuerzo devuelto");
check(!$row, "la fila del refuerzo se borró del oasis");
check($move !== null, "se creó el movimiento de regreso (sort_type 4) hacia la aldea");
check($att && (int)$att['vref'] === $VILLAGE, "el ataque de vuelta apunta a la aldea de origen");
check($att && (int)$att['attack_type'] === 2, "va como refuerzo (attack_type 2), no como ataque");
check($att && (int)$att['t1'] === 10 && (int)$att['t2'] === 5, "vuelven las 10 y 5 unidades exactas");
check($att && (int)$att['t11'] === 0, "sin héroe en este caso");
// Tienen que viajar, no teletransportarse.
check($move && (int)$move['endtime'] > $before, "llegan con tiempo de viaje, no al instante");
if($move) { say("    viaje: ".((int)$move['endtime'] - $before)."s"); }

// ------------------------------------------------------ B) el héroe también
say("\n== B) refuerzo con el héroe adentro ==");
$id = setup(array(1 => 3), 1);
$sent = $automation->returnOasisReinforcements($OASIS);

$row = one("SELECT * FROM {$P}enforcement WHERE id = $id");
$move = one("SELECT * FROM {$P}movement WHERE `from` = $OASIS AND `to` = $VILLAGE AND sort_type = 4 ORDER BY moveid DESC LIMIT 1");
$att = $move ? one("SELECT * FROM {$P}attacks WHERE id = ".(int)$move['ref']) : null;

check($sent === 1, "informa 1 refuerzo devuelto");
check(!$row, "la fila del refuerzo se borró");
check($att && (int)$att['t11'] === 1, "el héroe vuelve en el mismo movimiento");
check($att && (int)$att['t1'] === 3, "y las 3 unidades que lo acompañaban");

// --------------------------------------------- C) oasis sin nadie adentro
say("\n== C) oasis vacío ==");
q("DELETE FROM {$P}enforcement WHERE vref = $OASIS");
q("DELETE FROM {$P}movement WHERE `from` = $OASIS");
$sent = $automation->returnOasisReinforcements($OASIS);
$move = one("SELECT * FROM {$P}movement WHERE `from` = $OASIS AND sort_type = 4");
check($sent === 0, "no devuelve nada");
check(!$move, "y no inventa movimientos");

// ------------------------------- D) la mansión lo llama antes de soltar el oasis
say("\n== D) cableado en la Mansión del Héroe ==");
$tpl = file_get_contents(dirname(__DIR__).'/Templates/Build/37_heromansion.tpl');
$callPos = strpos($tpl, 'returnOasisReinforcements($oasisToRelease)');
$removePos = strpos($tpl, 'removeOases($oasisToRelease)');
check($callPos !== false, "la mansión devuelve las tropas al soltar el oasis");
check($removePos !== false && $callPos !== false && $callPos < $removePos,
      "y lo hace ANTES de soltarlo, cuando el oasis todavía tiene coordenadas y dueño");

cleanup();
say("\nEstado del mundo restaurado.");

if($failures > 0) {
    say("\n$failures comprobación(es) fallaron.");
    exit(1);
}
say("\nTodo OK.");
exit(0);
