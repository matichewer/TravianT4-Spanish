<?php
/**
 * Robar un artefacto: las cinco condiciones oficiales, y el ataque de verdad.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_artefact_theft.php
 *
 * Oficial (support.travian.com, "Getting ready for the Artefact release"): para llevarse
 * un artefacto hay que **derribar el Tesoro de la aldea que lo guarda** y después ganar un
 * **ataque normal —no un asalto—** con el **héroe**, que tiene que **sobrevivir**; y la
 * aldea que se lo lleva necesita su propio Tesoro **vacío** y al nivel que pide el tamaño
 * (10 el pequeño, 20 el grande y el único).
 *
 * De esas cinco, este repo comprobaba dos. Faltaban:
 *
 *   - **el Tesoro del defensor**, que es el agujero grande: el héroe entraba a una aldea
 *     con el Tesoro intacto y se llevaba el artefacto sin necesidad de una sola catapulta;
 *   - **que el héroe sobreviva**: un héroe muerto en la batalla se lo llevaba igual (la
 *     anexión de oasis, dos líneas más arriba, sí lo comprobaba con `$dead11`).
 *
 * Y la captura no reiniciaba `artefacts.conquered`, que es a la vez el reloj del retardo
 * de activación y el orden de prioridad del podio de tres.
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real, igual que
 * check_village_conquest.php: el mundo de verdad no se toca.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
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
include "Hero.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
include "Ranking.php";
include "Logging.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database, $generator, $technology;
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
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}
function q($sql) {
    global $database;
    $result = mysqli_query($database->connection, $sql);
    if($result === false) {
        fwrite(STDERR, 'SQL: '.mysqli_error($database->connection).PHP_EOL.$sql.PHP_EOL);
        exit(1);
    }
    return $result;
}
function row($sql) {
    $result = q($sql);
    return mysqli_fetch_assoc($result);
}
function scalar($sql) {
    $result = q($sql);
    $line = mysqli_fetch_row($result);
    return $line ? $line[0] : null;
}

// =====================================================================================
section('A. La regla, sin base de datos de por medio');
// =====================================================================================
//
// artefactTheftOutcome() es una función pura: recibe el ataque, la aldea atacada y la
// atacante, y decide. El orden de los rechazos también importa, porque es el que decide
// qué le dice el informe al jugador.

function theft($overrides = array()) {
    $attack = array_merge(array('type' => 3, 'hero_sent' => 1, 'hero_dead' => 0),
        isset($overrides['attack']) ? $overrides['attack'] : array());
    $target = array_merge(array('artefact' => true, 'size' => ARTEFACT_SIZE_SMALL, 'treasury' => 0),
        isset($overrides['target']) ? $overrides['target'] : array());
    $attacker = array_merge(array('treasury' => 10, 'artefact' => false),
        isset($overrides['attacker']) ? $overrides['attacker'] : array());
    return artefactTheftOutcome($attack, $target, $attacker);
}

check(theft()['status'] === 'claimed',
    'con las cinco condiciones cumplidas el artefacto se lo lleva');

check(theft(array('target' => array('artefact' => false)))['status'] === 'no_artefact',
    'una aldea sin artefacto no produce ningún aviso');

check(theft(array('attack' => array('type' => 4)))['status'] === 'raid',
    'un ASALTO no se lleva el artefacto: el héroe saquea y se va');
check(theft(array('attack' => array('type' => 1)))['status'] === 'raid',
    'una exploración tampoco');

check(theft(array('attack' => array('hero_sent' => 0)))['status'] === 'no_hero',
    'sin héroe no hay robo, por más catapultas que hayan ido');

check(theft(array('attack' => array('hero_dead' => 1)))['status'] === 'hero_dead',
    'un héroe MUERTO no se lleva nada: esto no se comprobaba');

check(theft(array('target' => array('treasury' => 1)))['status'] === 'defender_treasury_standing',
    'con el Tesoro enemigo en pie no hay robo, ni siquiera a nivel 1');
check(theft(array('target' => array('treasury' => 20)))['status'] === 'defender_treasury_standing',
    'ni con el Tesoro enemigo intacto a nivel 20');
check(theft(array('target' => array('treasury' => 0)))['status'] === 'claimed',
    'y con el Tesoro enemigo derribado a 0, sí');

// El Tesoro del atacante, por tamaño.
check(theft(array('attacker' => array('treasury' => 9)))['status'] === 'attacker_treasury_low',
    'un artefacto pequeño pide Tesoro 10 y con 9 no alcanza');
check(theft(array('attacker' => array('treasury' => 10)))['status'] === 'claimed',
    'con Tesoro 10 el pequeño sí');
check(theft(array('target' => array('size' => ARTEFACT_SIZE_LARGE),
                  'attacker' => array('treasury' => 19)))['status'] === 'attacker_treasury_low',
    'el grande pide Tesoro 20 y con 19 no alcanza');
check(theft(array('target' => array('size' => ARTEFACT_SIZE_LARGE),
                  'attacker' => array('treasury' => 20)))['status'] === 'claimed',
    'con Tesoro 20 el grande sí');
check(theft(array('target' => array('size' => ARTEFACT_SIZE_UNIQUE),
                  'attacker' => array('treasury' => 19)))['status'] === 'attacker_treasury_low',
    'y el único pide 20 igual que el grande');
check(theft(array('attacker' => array('treasury' => 0)))['status'] === 'attacker_treasury_low',
    'sin Tesoro no se lleva nada');

check(theft(array('attacker' => array('artefact' => true)))['status'] === 'attacker_treasury_occupied',
    'un Tesoro que ya guarda un artefacto no puede recibir otro: uno por aldea');

// El mensaje del rechazo dice el nivel que hace falta, no un número escrito a mano.
$low = theft(array('target' => array('size' => ARTEFACT_SIZE_LARGE), 'attacker' => array('treasury' => 5)));
check(strpos(artefactTheftMessage($low), '20') !== false,
    'el aviso de "te falta Tesoro" nombra el nivel que pide ESE artefacto');
foreach(array('raid','no_hero','hero_dead','defender_treasury_standing','attacker_treasury_occupied','claimed','database_error') as $code) {
    check(trim(artefactTheftMessage(array('status' => $code))) !== '',
        'el resultado "'.$code.'" tiene un texto para el informe');
}
check(artefactTheftMessage(array('status' => 'no_artefact')) === '',
    'y "no hay artefacto" no escribe nada: no es un rechazo, no pasó nada');

// El orden de los rechazos: primero lo que el jugador decidió al armar el ataque.
check(theft(array('attack' => array('type' => 4), 'attacker' => array('treasury' => 0)))['status'] === 'raid',
    'con varios problemas a la vez se reporta primero el del tipo de ataque');
check(theft(array('attack' => array('hero_dead' => 1), 'target' => array('treasury' => 5)))['status'] === 'hero_dead',
    'y el héroe muerto antes que el Tesoro enemigo');

// =====================================================================================
section('B. canClaimArtifact(): la mitad "en casa", contra la base real');
// =====================================================================================
//
// Se conserva como función propia y ahora delega en artefactTheftOutcome(), así que las
// dos no pueden divergir. Las Maravillas tienen Tesoro 10, que es justo el umbral del
// artefacto de aldea.
$wonder = $database->query_return(
    "SELECT f.vref FROM ".TB_PREFIX."fdata f "
    ."INNER JOIN ".TB_PREFIX."vdata v ON v.wref = f.vref "
    ."WHERE f.f22t = 27 AND f.f22 >= 10 LIMIT 1"
);
if(is_array($wonder) && count($wonder)) {
    $vref = (int)$wonder[0]['vref'];
    check($database->getVillageTreasuryLevel($vref) >= 10,
        'la lectura del nivel de Tesoro encuentra el edificio en su ranura');
    check($database->canClaimArtifact($vref, 1) === true,
        'una aldea con Tesoro 10 puede quedarse un artefacto de aldea');
    check($database->canClaimArtifact($vref, 2) === false,
        'pero no uno de cuenta, que pide Tesoro 20');
    check($database->canClaimArtifact($vref, 3) === false, 'ni uno único');
    check($database->canClaimArtifact($vref, 0) === false,
        'un tamaño desconocido no habilita nada');
    check($database->canClaimArtifact($vref, 4) === false, 'y uno inventado tampoco');
} else {
    echo '[--] no hay ninguna aldea con Tesoro para probar contra datos reales'.PHP_EOL;
}
check($database->canClaimArtifact(0, 1) === false, 'una aldea inexistente no puede reclamar');
check($database->villageHoldsArtefact(0) === false, 'ni guarda un artefacto');

// El código no puede volver a preguntar por la aldea equivocada. El fallo original:
// `canClaimArtifact()` leía una variable antes de asignarla y terminaba midiendo el
// Tesoro de la VÍCTIMA, y `claimArtefact()` recibía el destino repetido, así que el
// artefacto se quedaba donde estaba y sólo cambiaba de dueño.
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
check(strpos($automationSource, "\$database->getVillageTreasuryLevel(\$attackerVillage)") !== false,
    'el Tesoro que se mide "en casa" es el de la aldea atacante');
check(strpos($automationSource, "\$database->getVillageTreasuryLevel((int)\$data['to'])") !== false,
    'y el que tiene que estar derribado es el de la aldea atacada');
check(strpos($automationSource, "claimArtefact(\$attackerVillage, (int)\$data['to']") !== false,
    'el artefacto se muda a la aldea del atacante');
check(strpos($automationSource, "'hero_dead' => (int)\$dead11") !== false,
    'y el resultado del héroe entra en la decisión');

// =====================================================================================
section('C. La captura reinicia el reloj');
// =====================================================================================
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(preg_match('/function claimArtefact.*?conquered = \$time/s', $dbSource) === 1,
    'robar un artefacto reinicia su fecha de captura');
check(strpos($dbSource, 'artefact.conquered = ') !== false,
    'y conquistar la aldea que lo guarda, también');

// =====================================================================================
section('D. Un robo de verdad, resuelto por el motor');
// =====================================================================================
$P = TB_PREFIX;
$tables = array();
$result = q("SHOW TABLES LIKE '".$P."%'");
while($line = mysqli_fetch_row($result)) {
    $name = substr($line[0], strlen($P));
    if($name === 'config') {
        continue;
    }
    $tables[] = $name;
}
foreach($tables as $table) {
    $create = row("SHOW CREATE TABLE {$P}{$table}");
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
}

define('U_ATT', 9201);
define('U_DEF', 9202);
define('V_ATT', 992001);
define('V_TARGET', 992002);
define('ATTACK_ID', 78001);

/**
 * Deja el banco listo para un ataque con el héroe sobre una aldea con artefacto.
 * $targetTreasury: el nivel al que quedó el Tesoro del defensor DESPUÉS de las catapultas.
 */
function resetTheftWorld($attackerTreasury = 10, $targetTreasury = 0, $size = 1, $attackType = 3) {
    global $P, $tables, $database;
    foreach($tables as $table) {
        q("DELETE FROM {$P}{$table}");
    }
    $now = time();

    q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES "
        ."(".U_ATT.",'ladron',1,0,3,0),(".U_DEF.",'guardian',2,0,3,0)");
    q("INSERT INTO {$P}wdata (id,x,y,fieldtype,oasistype,occupied) VALUES "
        ."(".V_ATT.",1,1,3,0,1),(".V_TARGET.",2,1,3,0,1)");
    q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,loyaltyupdate,created,lastupdate,maxstore,maxcrop,wood,clay,iron,crop) VALUES "
        ."(".V_ATT.",".U_ATT.",1,100,10,100,$now,$now,$now,800,800,100,100,100,100),"
        ."(".V_TARGET.",".U_DEF.",1,100,10,100,$now,$now,$now,800,800,100,100,100,100)");
    foreach(array(V_ATT, V_TARGET) as $village) {
        q("INSERT INTO {$P}fdata (vref) VALUES ($village)");
        q("INSERT INTO {$P}units (vref) VALUES ($village)");
    }
    // El Tesoro del atacante, y el edificio principal para que la aldea sea normal.
    q("UPDATE {$P}fdata SET f20t = 15, f20 = 10, f22t = 27, f22 = ".(int)$attackerTreasury
        ." WHERE vref = ".V_ATT);
    // El Tesoro del defensor: 0 significa derribado, y entonces no se escribe el edificio.
    if((int)$targetTreasury > 0) {
        q("UPDATE {$P}fdata SET f20t = 15, f20 = 10, f22t = 27, f22 = ".(int)$targetTreasury
            ." WHERE vref = ".V_TARGET);
    } else {
        q("UPDATE {$P}fdata SET f20t = 15, f20 = 10 WHERE vref = ".V_TARGET);
    }
    // El artefacto, capturado hace mucho para que ya esté maduro.
    q("INSERT INTO {$P}artefacts (id,vref,owner,type,size,conquered,name,`desc`,effect,img) "
        ."VALUES (0,".V_TARGET.",".U_DEF.",".ARTEFACT_TRAINER.",".(int)$size.",1,'x','x','x','x')");
    // Un héroe vivo para el atacante, en su aldea.
    q("INSERT INTO {$P}hero (uid,wref,level,speed,points,experience,dead,health,power,itempower,"
        ."offBonus,defBonus,product,r0,r1,r2,r3,r4,autoregen,lastupdate,lastadv,hash,hide,home) "
        ."VALUES (".U_ATT.",".V_ATT.",1,7,0,0,0,100,100,0,0,0,0,1,0,0,0,0,0,$now,0,'',1,".V_ATT.")");
    // Un ataque normal con tropas de sobra y el héroe (t11), ya llegado.
    q("INSERT INTO {$P}attacks (id,vref,t1,t11,attack_type) VALUES "
        ."(".ATTACK_ID.",".V_ATT.",500,1,".(int)$attackType.")");
    q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,ref2,`data`,endtime,proc) "
        ."VALUES (0,3,".V_ATT.",".V_TARGET.",".ATTACK_ID.",0,'0',".($now - 30).",0)");
}

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$resolve = $reflection->getMethod('sendunitsComplete');
$resolve->setAccessible(true);

function runTheft() {
    global $root, $resolve, $automation, $database;
    $database->flushArtefactCache();
    @unlink($root.'/GameEngine/Prevention/sendunits.txt');
    $resolve->invoke($automation);
}

function artefactVillage() {
    return (int)scalar("SELECT vref FROM ".TB_PREFIX."artefacts LIMIT 1");
}
function artefactOwner() {
    return (int)scalar("SELECT owner FROM ".TB_PREFIX."artefacts LIMIT 1");
}
function lastAttackerReport() {
    return (string)scalar("SELECT `data` FROM ".TB_PREFIX."ndata WHERE uid = ".U_ATT
        ." ORDER BY id DESC LIMIT 1");
}

// --- El caso que funciona ------------------------------------------------------------
resetTheftWorld(10, 0, 1, 3);
runTheft();
check(artefactVillage() === V_ATT,
    'con el Tesoro enemigo derribado y el héroe vivo, el artefacto se muda a la aldea atacante');
check(artefactOwner() === U_ATT, 'y cambia de dueño');
check((int)scalar("SELECT conquered FROM ".TB_PREFIX."artefacts LIMIT 1") > 1,
    'la captura reinicia el reloj de activación');
check(strpos(lastAttackerReport(), 'artefacto') !== false,
    'y el informe del atacante lo cuenta');

// --- El agujero grande: el Tesoro del defensor en pie --------------------------------
resetTheftWorld(10, 10, 1, 3);
runTheft();
check(artefactVillage() === V_TARGET,
    'con el Tesoro enemigo EN PIE el artefacto no se mueve, aunque el ataque se gane');
check(artefactOwner() === U_DEF, 'ni cambia de dueño');
check(strpos(lastAttackerReport(), 'Tesoro') !== false,
    'y el informe explica que hay que derribarlo');

resetTheftWorld(10, 1, 1, 3);
runTheft();
check(artefactVillage() === V_TARGET,
    'un Tesoro enemigo de nivel 1 sigue protegiendo el artefacto: hay que llevarlo a 0');

// --- Un asalto no roba ---------------------------------------------------------------
resetTheftWorld(10, 0, 1, 4);
runTheft();
check(artefactVillage() === V_TARGET, 'un asalto con el héroe no se lleva el artefacto');

// --- Sin Tesoro suficiente en casa ---------------------------------------------------
resetTheftWorld(9, 0, 1, 3);
runTheft();
check(artefactVillage() === V_TARGET,
    'con Tesoro 9 en casa el artefacto pequeño no se puede recibir');

resetTheftWorld(10, 0, 2, 3);
runTheft();
check(artefactVillage() === V_TARGET,
    'y con Tesoro 10 tampoco se puede recibir uno de cuenta');

resetTheftWorld(20, 0, 2, 3);
runTheft();
check(artefactVillage() === V_ATT, 'con Tesoro 20, sí');

// --- El Tesoro de casa ya ocupado ----------------------------------------------------
resetTheftWorld(10, 0, 1, 3);
q("INSERT INTO ".TB_PREFIX."artefacts (id,vref,owner,type,size,conquered,name,`desc`,effect,img) "
    ."VALUES (0,".V_ATT.",".U_ATT.",".ARTEFACT_DIET.",1,1,'x','x','x','x')");
runTheft();
check((int)scalar("SELECT COUNT(*) FROM ".TB_PREFIX."artefacts WHERE vref = ".V_ATT) === 1,
    'una aldea que ya guarda un artefacto no puede recibir un segundo');
check((int)scalar("SELECT COUNT(*) FROM ".TB_PREFIX."artefacts WHERE vref = ".V_TARGET) === 1,
    'y el otro se queda donde estaba');

// --- Una aldea con artefacto no desaparece del mapa ----------------------------------
//
// Oficial: una aldea que guarda un artefacto sigue en el mapa aunque la dejen en cero
// habitantes. Borrarla dejaba la fila de `artefacts` apuntando a una aldea inexistente:
// el artefacto seguía haciendo efecto y ya no había forma de robarlo.
resetTheftWorld(10, 0, 1, 3);
$destroy = $reflection->getMethod('destroyCatapultedVillage');
$destroy->setAccessible(true);
q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) "
    ."VALUES (992003,".U_DEF.",0,0,0,100,".time().",".time().",800,800)");
$database->flushArtefactCache();
check($destroy->invoke($automation, V_TARGET, U_DEF, 0) === false,
    'una aldea que guarda un artefacto no se puede arrasar del mapa');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata WHERE wref = ".V_TARGET) === 1,
    'y sigue existiendo');
check($destroy->invoke($automation, 992003, U_DEF, 0) === true,
    'pero una aldea sin artefacto sí');

// =====================================================================================
echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Robo de artefactos: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
