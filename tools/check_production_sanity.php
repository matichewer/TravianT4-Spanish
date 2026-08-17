<?php
/**
 * Ninguna aldea puede tener un balance de cereal que sus propios campos no podrían cubrir
 * ni al máximo.
 *
 * La lección de fondo del bug natar. Aquella hambruna no fue letal porque el reloj de
 * producción sea perezoso —eso está bien resuelto: starvation() mata en función de la tasa
 * y descarta la deuda, así que un jugador ausente no recibe un castigo retroactivo—. Fue
 * letal porque la TASA era absurda (-45.000 de cereal/h en una Maravilla, -5.200.000/h en
 * la capital) y nada en el motor lo notó. Se aplicó en silencio hasta vaciar las aldeas.
 *
 * La distinción que hace este checker:
 *   - Un JUGADOR puede tener el balance en rojo cuanto quiera: entrenar de más y pasar
 *     hambre es parte del juego, y es su decisión. Sólo se informa.
 *   - Una aldea NPC no: nadie la administra, así que un rojo que sus campos no podrían
 *     cubrir ni construidos al tope es siempre un bug del motor, no una decisión.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_production_sanity.php
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
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$netCrop = $reflection->getMethod('villageNetCropProduction');
$netCrop->setAccessible(true);

/**
 * El techo absoluto de cereal de una aldea: sus campos de cereal al nivel máximo de la
 * tabla, con molino y panadería al tope, por SPEED. Nada puede producir más que esto, así
 * que un déficit mayor es irrecuperable por definición.
 */
function absoluteCropCeiling($fields) {
    $ceiling = is_array($fields) ? $fields : array();
    $maxLevel = max(array_keys($GLOBALS['bid4']));
    for($slot = 1; $slot <= 18; $slot++) {
        if(isset($ceiling['f'.$slot.'t']) && (int)$ceiling['f'.$slot.'t'] === 4) {
            $ceiling['f'.$slot] = $maxLevel;
        }
    }
    foreach(array(8 => 5, 9 => 5) as $type => $level) {
        $slot = natarFindBuildingSlot($ceiling, $type);
        if($slot > 0) {
            $ceiling['f'.$slot.'t'] = $type;
            $ceiling['f'.$slot] = $level;
        }
    }
    return natarVillageGrossCrop($ceiling);
}

$villages = $database->query_return("SELECT * FROM ".TB_PREFIX."vdata ORDER BY wref ASC");
check(is_array($villages) && count($villages) > 0, 'el mundo tiene aldeas que revisar ('.count($villages).')');

$impossibleNpc = array();
$negativePlayers = 0;
$staticNegative = array();

foreach($villages as $village) {
    $wref = (int)$village['wref'];
    $fields = $database->getResourceLevel($wref);
    if(!is_array($fields)) {
        continue;
    }
    $net = $netCrop->invoke($automation, $wref);
    $kind = villageKindFromRow($village);

    if($kind === NPC_KIND_PLAYER) {
        if($net < 0) {
            $negativePlayers++;
        }
        continue;
    }

    // Una guarnición estática no paga manutención, así que su balance NUNCA puede ser
    // negativo. Si lo es, la exención se rompió y las Maravillas están otra vez en
    // cuenta regresiva.
    if($kind === NPC_KIND_STATIC && $net < 0) {
        $staticNegative[] = $village['name'].' (wref '.$wref.'): '.round($net).'/h';
        continue;
    }

    // Y ninguna aldea NPC puede tener un déficit que sus campos no cubrirían ni al tope.
    if($net < 0 && abs($net) > absoluteCropCeiling($fields)) {
        $impossibleNpc[] = $village['name'].' (wref '.$wref.'): '.round($net).'/h contra un techo de '
            .round(absoluteCropCeiling($fields)).'/h';
    }
}

check(empty($staticNegative),
    'ninguna guarnición estática tiene el balance en rojo (no pagan manutención)');
foreach($staticNegative as $offender) {
    echo '        '.$offender.PHP_EOL;
}

check(empty($impossibleNpc),
    'ninguna aldea NPC tiene un déficit que sus campos no podrían cubrir ni al máximo');
foreach($impossibleNpc as $offender) {
    echo '        '.$offender.PHP_EOL;
}

echo '[--] aldeas de jugador con el balance en rojo: '.$negativePlayers
    .' (informativo: entrenar de más es decisión del jugador)'.PHP_EOL;

// Integridad de la clase: una aldea de una cuenta del sistema nunca puede quedar marcada
// como aldea de jugador. Si pasa, algo la creó por fuera de los helpers de
// NatarVillage.php —que es justo lo que hacían los dos mods del panel— y esa aldea va a
// pagar manutención y morirse de hambre sin que nadie la administre.
$misclassified = array();
foreach($villages as $village) {
    if(isSystemAccount($village['owner']) && villageKindFromRow($village) === NPC_KIND_PLAYER) {
        $misclassified[] = $village['name'].' (wref '.$village['wref'].')';
    }
}
check(empty($misclassified),
    'ninguna aldea de una cuenta del sistema quedó marcada como aldea de jugador');
foreach($misclassified as $offender) {
    echo '        '.$offender.PHP_EOL;
}

// El checker tiene que morder de verdad: se le fabrica el caso que nos mordió a nosotros.
// Se busca por dueño y no por clase, para que la prueba corra aunque la clase esté mal.
$scratch = $database->query_return(
    "SELECT wref FROM ".TB_PREFIX."vdata WHERE ".systemAccountSql('owner')." LIMIT 1"
);
if(is_array($scratch) && isset($scratch[0]['wref'])) {
    $wref = (int)$scratch[0]['wref'];
    $units = $database->getUnit($wref);
    $saved = is_array($units) ? (int)$units['u41'] : 0;
    $savedKind = $database->getVillageNpcKind($wref);

    // Marcada como aldea de jugador, la misma guarnición sí paga manutención: es el
    // escenario que el checker tiene que detectar si alguna vez la exención se invierte.
    $originalNet = $netCrop->invoke($automation, $wref);
    $database->query("UPDATE ".TB_PREFIX."units SET u41 = 5000000 WHERE vref = $wref");
    $database->setVillageNpcKind($wref, NPC_KIND_LIVING);
    $fields = $database->getResourceLevel($wref);
    $net = $netCrop->invoke($automation, $wref);
    check($net < 0 && abs($net) > absoluteCropCeiling($fields),
        'el checker reconoce un déficit imposible cuando se le fabrica uno ('.round($net).'/h)');

    $database->setVillageNpcKind($wref, $savedKind === null ? NPC_KIND_STATIC : $savedKind);
    $database->query("UPDATE ".TB_PREFIX."units SET u41 = $saved WHERE vref = $wref");
    check(abs($netCrop->invoke($automation, $wref) - $originalNet) < 1,
        'y el mundo queda exactamente como estaba ('.round($originalNet).'/h)');
} else {
    echo '[--] este mundo no tiene guarniciones estáticas: no se pudo probar el caso negativo'.PHP_EOL;
}

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
