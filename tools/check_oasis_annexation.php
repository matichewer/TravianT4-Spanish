<?php
/**
 * La conquista de oasis, contra el T4 oficial.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_oasis_annexation.php
 *
 * Va aparte de check_oasis_conquest.php —que cubre la coherencia de `odata`/`wdata` y las
 * pantallas— y de check_village_conquest.php. Acá viven las reglas del oficial:
 *
 *   - Sólo el **héroe** anexa un oasis, y sólo en **ataque normal**: en un asalto entra,
 *     saquea y se va. Tiene que sobrevivir y no puede quedar ni un defensor en pie.
 *   - El oasis tiene que estar a **3 casillas o menos** de la aldea (cuadrado, no círculo)
 *     y la **Mansión del Héroe** habilita uno por escalón: 10 → 1, 15 → 2, 20 → 3.
 *   - Un oasis **libre cae de un solo ataque**. Uno ocupado depende de cuántos tenga el
 *     **defensor**: con 3 cae de uno, con 2 hacen falta dos ataques y con 1 hacen falta
 *     tres. (Es la tabla oficial, y va por el defensor, no por el atacante.)
 *   - Al tomarlo, la lealtad vuelve a **100%**.
 *   - La lealtad de un oasis sube **1 punto cada 30 minutos**, fijo: "el jugador no puede
 *     influir en ella". No depende de la residencia ni de ningún otro edificio.
 *   - Si te conquistan la aldea, **sus oasis se liberan**, no los hereda nadie.
 *
 * Como check_village_conquest.php, corre sobre tablas temporales copiadas del esquema
 * real: el motor entero cae sobre ellas y el mundo de verdad no se toca.
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
    return mysqli_fetch_assoc(q($sql));
}
function scalar($sql) {
    $line = mysqli_fetch_row(q($sql));
    return $line ? $line[0] : null;
}

$P = TB_PREFIX;

// Se tapan todas las tablas: el bloque D resuelve ataques de verdad con
// `sendunitsComplete()`, que escribe informes y rankings además de `odata`.
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

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();

// =====================================================================================
section('A. Alcance: un cuadrado de 3 casillas, no un círculo');
// =====================================================================================
//
// El cuadrado importa: en un círculo de radio 3 la esquina (3|3) quedaría afuera
// (distancia 4,24) y el oficial la deja adentro.
check(Automation::oasisWithinAnnexationRange(0, 0, 3, 0), 'entra un oasis a 3 casillas en línea recta');
check(Automation::oasisWithinAnnexationRange(0, 0, 3, 3), 'entra la esquina del cuadrado (3|3)');
check(Automation::oasisWithinAnnexationRange(0, 0, -3, 2), 'entra hacia el otro lado');
check(!Automation::oasisWithinAnnexationRange(0, 0, 4, 0), 'queda afuera un oasis a 4 casillas');
check(!Automation::oasisWithinAnnexationRange(0, 0, 0, -4), 'y a 4 casillas en el otro eje');
check(!Automation::oasisWithinAnnexationRange(0, 0, 4, 4), 'y en diagonal');
// El mapa da la vuelta: el borde este pega con el borde oeste.
$edge = (int)WORLD_MAX;
check(Automation::oasisWithinAnnexationRange($edge, 0, -$edge, 0),
    'el mapa da la vuelta: los dos bordes son vecinos');
check(count(Automation::oasisAnnexationAxisWindow(0)) === 7,
    'la ventana de coordenadas del eje son las 7 casillas del cuadrado');

// =====================================================================================
section('B. Requisitos: Mansión del Héroe y cupo');
// =====================================================================================

/** Aldea atacante en (0|0) con la mansión y los oasis que se le pidan. */
function attacker($mansion, $held) {
    return array('wref' => 1, 'x' => 0, 'y' => 0, 'mansion' => $mansion, 'oases' => $held);
}
/** Oasis objetivo, libre salvo que se diga otra cosa. */
function target($overrides = array()) {
    return array_merge(
        array('x' => 1, 'y' => 1, 'conqured' => 0, 'loyalty' => 100, 'holder_oases' => 0),
        $overrides
    );
}

// La tabla oficial: 10 habilita el primero, 15 el segundo, 20 el tercero.
$mansionTable = array(
    array(0, 9, 'mansion_too_low'), array(0, 10, 'conquered'),
    array(1, 14, 'mansion_too_low'), array(1, 15, 'conquered'),
    array(2, 19, 'mansion_too_low'), array(2, 20, 'conquered')
);
foreach($mansionTable as $case) {
    list($held, $mansion, $expected) = $case;
    $outcome = $automation->oasisAnnexationOutcome(attacker($mansion, $held), target());
    check($outcome['status'] === $expected,
        "con $held oasis y mansión $mansion el resultado es $expected (fue ".$outcome['status'].")");
}
$outcome = $automation->oasisAnnexationOutcome(attacker(20, 0), target());
check((int)$outcome['needed_mansion'] === 10, 'el primer oasis pide mansión 10');
check((int)$automation->oasisAnnexationOutcome(attacker(9, 1), target())['needed_mansion'] === 15,
    'el segundo pide 15');
check((int)$automation->oasisAnnexationOutcome(attacker(9, 2), target())['needed_mansion'] === 20,
    'el tercero pide 20');

check($automation->oasisAnnexationOutcome(attacker(20, 3), target())['status'] === 'oasis_limit',
    'una aldea no pasa de tres oasis');
check($automation->oasisAnnexationOutcome(attacker(20, 1), target(array('conqured' => 1)))['status'] === 'already_owned',
    'atacar un oasis propio no hace nada');

// El orden importa: los rechazos se resuelven antes de tocar la lealtad.
$outcome = $automation->oasisAnnexationOutcome(
    attacker(20, 0),
    target(array('x' => 40, 'y' => 40, 'conqured' => 99, 'loyalty' => 50, 'holder_oases' => 1))
);
check($outcome['status'] === 'out_of_range' && (int)$outcome['loyalty'] === 50,
    'un oasis fuera de alcance no pierde lealtad');
$outcome = $automation->oasisAnnexationOutcome(
    attacker(9, 0),
    target(array('conqured' => 99, 'loyalty' => 50, 'holder_oases' => 1))
);
check($outcome['status'] === 'mansion_too_low' && (int)$outcome['loyalty'] === 50,
    'con la mansión corta tampoco: el ataque no persuade a nadie');

// =====================================================================================
section('C. Cuántos ataques hacen falta');
// =====================================================================================
//
// Tabla oficial, y va por los oasis que tiene el DEFENSOR: sacarle uno a quien tiene tres
// cuesta un ataque; sacarle el último a quien tiene uno cuesta tres.
check($automation->oasisAnnexationOutcome(attacker(20, 0), target())['status'] === 'conquered',
    'un oasis libre cae de un solo ataque');

$attacksNeeded = function ($defenderOases) use ($automation) {
    $loyalty = 100;
    for($attack = 1; $attack <= 10; $attack++) {
        $outcome = $automation->oasisAnnexationOutcome(
            attacker(20, 0),
            target(array('conqured' => 99, 'loyalty' => $loyalty, 'holder_oases' => $defenderOases))
        );
        if($outcome['status'] === 'conquered') {
            return $attack;
        }
        if($outcome['status'] !== 'loyalty_reduced' || (int)$outcome['loyalty'] >= $loyalty) {
            return -1;
        }
        $loyalty = (int)$outcome['loyalty'];
    }
    return -1;
};
check($attacksNeeded(3) === 1, 'al que tiene tres oasis se le saca uno de un ataque');
check($attacksNeeded(2) === 2, 'al que tiene dos, en dos ataques');
check($attacksNeeded(1) === 3, 'al que tiene uno solo, en tres');

$outcome = $automation->oasisAnnexationOutcome(
    attacker(20, 0), target(array('conqured' => 99, 'loyalty' => 100, 'holder_oases' => 1))
);
check((int)$outcome['loyalty'] === 66, 'el primer ataque contra un oasis único lo deja en 66%');
$outcome = $automation->oasisAnnexationOutcome(
    attacker(20, 0), target(array('conqured' => 99, 'loyalty' => 100, 'holder_oases' => 2))
);
check((int)$outcome['loyalty'] === 50, 'contra quien tiene dos, la mitad de una');
$outcome = $automation->oasisAnnexationOutcome(
    attacker(20, 0), target(array('conqured' => 99, 'loyalty' => 20, 'holder_oases' => 1))
);
check($outcome['status'] === 'conquered' && (int)$outcome['loyalty'] === 100,
    'al tomarlo la lealtad vuelve a 100%');

// =====================================================================================
section('D. La lealtad sube 1 punto cada 30 minutos, y nada más');
// =====================================================================================
//
// Regla oficial completa: "la lealtad del oasis aumenta un 1% cada 30 minutos y el
// jugador no puede influir en ella". Acá estaba copiada de la aldea (un punto por hora
// por nivel de la residencia de la aldea que lo tiene), así que con un palacio 20 se
// curaba veinte veces más rápido y sin residencia no se curaba nunca.
check(abs(Automation::oasisLoyaltyRegenerationRate(1) - (1 / 1800)) < 1e-12,
    'un punto cada 1800 segundos');
check(Automation::OASIS_LOYALTY_REGEN_SECONDS === 1800, 'la constante son 30 minutos');

$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 1000 + 1799, 1);
check((int)$regen['loyalty'] === 50 && (int)$regen['clock'] === 1000,
    'a los 29 minutos y 59 segundos todavía no suma, y el reloj no se pierde');
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 1000 + 1800, 1);
check((int)$regen['loyalty'] === 51, 'a los 30 minutos exactos suma un punto');
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 1000 + 3600, 1);
check((int)$regen['loyalty'] === 52, 'en una hora, dos puntos');
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 1000 + 1800, 3);
check((int)$regen['loyalty'] === 53, 'la velocidad del mundo multiplica el ritmo');
$regen = Automation::oasisLoyaltyRegenerationOutcome(99, 1000, 1000 + 999999, 1);
check((int)$regen['loyalty'] === 100 && (int)$regen['clock'] === 1000 + 999999,
    'se corta en 100 sin dejar tiempo retroactivo acumulado');
// Y el reloj avanza sólo lo que se acreditó: la regeneración no puede depender de cada
// cuánto corra la automatización.
$partial = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 1000 + 2700, 1);
check((int)$partial['loyalty'] === 51 && (int)$partial['clock'] === 1000 + 1800,
    'los 15 minutos sobrantes quedan a cuenta del punto siguiente');

// La barrida ya no le pregunta el nivel de la residencia a nadie.
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
$sweepStart = strpos($automationSource, 'WHERE loyalty < 100 AND conqured <> 0');
$sweepEnd = strpos($automationSource, 'WHERE loyalty>125', $sweepStart);
$sweep = substr($automationSource, $sweepStart, $sweepEnd - $sweepStart);
check(strpos($sweep, 'getTypeLevel(25') === false && strpos($sweep, 'getTypeLevel(26') === false,
    'la regeneración del oasis dejó de mirar la residencia o el palacio de la aldea');

// =====================================================================================
section('E. El motor: hace falta el héroe y un ataque normal');
// =====================================================================================

define('U_ATT', 9201);
define('U_DEF', 9202);
define('V_ATT', 992001);
define('V_DEF', 992002);
define('O_FREE', 992010);
define('O_HELD', 992011);

/** Mundo mínimo: dos jugadores, dos aldeas y dos oasis a una casilla de la aldea A. */
function resetOasisWorld() {
    global $P, $tables;
    foreach($tables as $table) {
        q("DELETE FROM {$P}{$table}");
    }
    $now = time();
    q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES "
        ."(".U_ATT.",'atacante',1,0,3,0),(".U_DEF.",'defensor',2,0,3,0)");
    // La aldea A en (0|0); los dos oasis a una casilla, bien dentro del cuadrado.
    q("INSERT INTO {$P}wdata (id,x,y,fieldtype,oasistype,occupied) VALUES "
        ."(".V_ATT.",0,0,3,0,1),(".V_DEF.",5,5,3,0,1),"
        ."(".O_FREE.",1,0,1,1,0),(".O_HELD.",0,1,1,1,0)");
    q("INSERT INTO {$P}vdata (wref,owner,capital,pop,loyalty,created,lastupdate,maxstore,maxcrop,wood,clay,iron,crop) VALUES "
        ."(".V_ATT.",".U_ATT.",1,100,100,$now,$now,800,800,500,500,500,500),"
        ."(".V_DEF.",".U_DEF.",1,100,100,$now,$now,800,800,500,500,500,500)");
    foreach(array(V_ATT, V_DEF, O_FREE, O_HELD) as $place) {
        q("INSERT INTO {$P}fdata (vref) VALUES ($place)");
        q("INSERT INTO {$P}units (vref) VALUES ($place)");
    }
    // Mansión del héroe nivel 20 en la aldea que ataca.
    q("UPDATE {$P}fdata SET f19t = 37, f19 = 20, f20t = 15, f20 = 5 WHERE vref = ".V_ATT);
    // Los dos oasis, sin animales adentro: la batalla se gana sin bajas.
    q("INSERT INTO {$P}odata (wref,type,conqured,owner,loyalty,lastupdated,lastupdated2,maxstore,maxcrop,name) VALUES "
        ."(".O_FREE.",1,0,3,100,$now,$now,800,800,'Oasis sin ocupar'),"
        ."(".O_HELD.",1,".V_DEF.",".U_DEF.",100,$now,$now,800,800,'Oasis conquistado')");
    // El héroe del atacante, vivo y en casa.
    q("INSERT INTO {$P}hero (heroid,uid,wref,home,level,speed,dead,health,power,offBonus,defBonus,product) "
        ."VALUES (0,".U_ATT.",".V_ATT.",".V_ATT.",10,7,0,100,100,0,0,0)");
    q("UPDATE {$P}units SET hero = 1 WHERE vref = ".V_ATT);
}

/** Manda un ataque ya vencido desde la aldea A y lo resuelve. */
function resolveAttack($target, $troops, $attackType) {
    global $P, $database, $reflection, $automation, $root;
    $t = array_replace(array_fill(1, 11, 0), $troops);
    q("INSERT INTO {$P}attacks (id,vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy) VALUES "
        ."(0,".V_ATT.",{$t[1]},{$t[2]},{$t[3]},{$t[4]},{$t[5]},{$t[6]},{$t[7]},{$t[8]},{$t[9]},{$t[10]},{$t[11]},$attackType,0,0,0)");
    $ref = mysqli_insert_id($database->connection);
    q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,ref2,`data`,endtime,proc) VALUES "
        ."(0,3,".V_ATT.",$target,$ref,0,'0,0,0,0,0',".(time() - 20).",0)");
    @unlink($root.'/GameEngine/Prevention/sendunits.txt');
    $method = $reflection->getMethod('sendunitsComplete');
    $method->setAccessible(true);
    $method->invoke($automation);
    return $ref;
}

resetOasisWorld();
$village = $database->getVillage(V_ATT);
$session = new stdClass();
$session->uid = U_ATT;
$session->tribe = 1;

// E.1 Un asalto con el héroe no anexa nada.
resolveAttack(O_FREE, array(11 => 1), 4);
check((int)scalar("SELECT conqured FROM {$P}odata WHERE wref = ".O_FREE) === 0,
    'un asalto con el héroe no anexa el oasis: hace falta ataque normal');
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".O_FREE) === 0,
    'y el mapa no lo marca como ocupado');

// E.2 Un ataque normal sin el héroe tampoco.
resetOasisWorld();
resolveAttack(O_FREE, array(1 => 50), 3);
check((int)scalar("SELECT conqured FROM {$P}odata WHERE wref = ".O_FREE) === 0,
    'un ataque normal sin el héroe no anexa: sólo el héroe toma oasis');

// E.3 Con el héroe y ataque normal, sí.
resetOasisWorld();
resolveAttack(O_FREE, array(11 => 1), 3);
$oasis = row("SELECT * FROM {$P}odata WHERE wref = ".O_FREE);
check((int)$oasis['conqured'] === V_ATT, 'con el héroe y ataque normal el oasis queda anexado a la aldea');
check((int)$oasis['owner'] === U_ATT, 'y a nombre del jugador');
check((int)$oasis['loyalty'] === 100, 'con la lealtad en 100%');
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".O_FREE) === 1,
    'el mapa lo marca ocupado');
$report = scalar("SELECT `data` FROM {$P}ndata WHERE uid = ".U_ATT." ORDER BY id DESC LIMIT 1");
check(is_string($report) && strpos($report, 'conquistó este oasis') !== false,
    'y el informe se lo cuenta al jugador');

// E.4 Si el héroe no sobrevive, no se anexa nada: el oasis hay que limpiarlo primero.
resetOasisWorld();
q("UPDATE {$P}units SET u37 = 400, u38 = 400 WHERE vref = ".O_FREE); // elefantes y tigres
resolveAttack(O_FREE, array(11 => 1), 3);
check((int)scalar("SELECT conqured FROM {$P}odata WHERE wref = ".O_FREE) === 0,
    'un héroe que no sobrevive a los animales no anexa el oasis');
check((int)scalar("SELECT dead FROM {$P}hero WHERE uid = ".U_ATT) === 1,
    '(y efectivamente murió: el escenario prueba lo que dice probar)');

// E.5 Fuera de alcance: el oasis no pierde lealtad y el informe lo explica.
resetOasisWorld();
q("UPDATE {$P}wdata SET x = 9, y = 9 WHERE id = ".O_FREE);
resolveAttack(O_FREE, array(11 => 1), 3);
check((int)scalar("SELECT conqured FROM {$P}odata WHERE wref = ".O_FREE) === 0,
    'un oasis a más de 3 casillas no se anexa');
$report = scalar("SELECT `data` FROM {$P}ndata WHERE uid = ".U_ATT." ORDER BY id DESC LIMIT 1");
check(is_string($report) && strpos($report, 'demasiado lejos') !== false,
    'y el informe explica por qué');

// E.6 Un oasis ajeno pierde lealtad ataque a ataque, y cae al tercero.
resetOasisWorld();
resolveAttack(O_HELD, array(11 => 1), 3);
check((int)scalar("SELECT loyalty FROM {$P}odata WHERE wref = ".O_HELD) === 66,
    'el primer ataque contra el único oasis del defensor lo deja en 66%');
check((int)scalar("SELECT conqured FROM {$P}odata WHERE wref = ".O_HELD) === V_DEF,
    'y todavía es del defensor');
q("UPDATE {$P}hero SET dead = 0, health = 100, wref = ".V_ATT." WHERE uid = ".U_ATT);
q("UPDATE {$P}units SET hero = 1 WHERE vref = ".V_ATT);
resolveAttack(O_HELD, array(11 => 1), 3);
q("UPDATE {$P}hero SET dead = 0, health = 100, wref = ".V_ATT." WHERE uid = ".U_ATT);
q("UPDATE {$P}units SET hero = 1 WHERE vref = ".V_ATT);
resolveAttack(O_HELD, array(11 => 1), 3);
$oasis = row("SELECT * FROM {$P}odata WHERE wref = ".O_HELD);
check((int)$oasis['conqured'] === V_ATT && (int)$oasis['owner'] === U_ATT,
    'al tercer ataque cambia de manos');
check((int)$oasis['loyalty'] === 100, 'y la lealtad vuelve a 100%');
check((int)scalar("SELECT COUNT(*) FROM {$P}ndata WHERE uid = ".U_DEF) >= 1,
    'al que lo pierde le llegan sus informes de defensa');

// E.7 Un asalto contra un oasis ajeno tampoco le baja la lealtad.
resetOasisWorld();
resolveAttack(O_HELD, array(11 => 1), 4);
check((int)scalar("SELECT loyalty FROM {$P}odata WHERE wref = ".O_HELD) === 100,
    'un asalto no le baja la lealtad a un oasis ajeno');

// =====================================================================================
section('F. Los oasis siguen a la aldea sólo hasta que la aldea cambia de dueño');
// =====================================================================================
resetOasisWorld();
q("UPDATE {$P}odata SET conqured = ".V_ATT.", owner = ".U_ATT." WHERE wref = ".O_FREE);
q("UPDATE {$P}wdata SET occupied = 1 WHERE id = ".O_FREE);
$release = $reflection->getMethod('releaseVillageOasesSafely');
$release->setAccessible(true);
$release->invoke($automation, V_ATT);
$oasis = row("SELECT * FROM {$P}odata WHERE wref = ".O_FREE);
check((int)$oasis['conqured'] === 0, 'perder la aldea suelta sus oasis (regla oficial)');
check((int)$oasis['loyalty'] === 100 && (int)$oasis['owner'] !== U_ATT,
    'y quedan libres, al 100%, listos para que los tome cualquiera');
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".O_FREE) === 0,
    'el mapa deja de marcarlos ocupados');

// El camino de la conquista de aldea usa exactamente ésta función, no un traspaso.
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
$conquestStart = strpos($dbSource, 'function applyConquestLoyalty(');
$conquestEnd = strpos($dbSource, 'function conquestVillageCleanup(', $conquestStart);
$conquestBody = substr($dbSource, $conquestStart, $conquestEnd - $conquestStart);
check(strpos($conquestBody, 'transferVillageOases') === false,
    'la conquista de aldea ya no traspasa los oasis al conquistador');
check(preg_match('/completeVillageConquest.*?releaseVillageOasesSafely\(\$target\)/s', $automationSource) === 1,
    'los suelta con releaseVillageOasesSafely()');

// Y un oasis ocupado no repuebla animales; uno libre sí.
check(preg_match('/FROM ".TB_PREFIX."odata where conqured = 0 and \$time - lastupdated2 > 86400/', $automationSource) === 1,
    'sólo repueblan animales los oasis libres');

echo PHP_EOL;
if($failures) {
    fwrite(STDERR, 'Fallaron '.count($failures).' de '.$checks." comprobaciones de anexión de oasis.\n");
    exit(1);
}
echo 'Anexión de oasis: OK ('.$checks." comprobaciones)\n";
