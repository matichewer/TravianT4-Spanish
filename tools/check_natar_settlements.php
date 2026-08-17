<?php
/**
 * Aldeas natar independientes: estado derivado, entrenamiento acotado y aparición.
 *
 * Lo que fija. Toda la aldea viva se deriva de su edad, así que lo único que puede
 * romperla es que ese cálculo deje de ser reproducible o que el ponerse al día no tenga
 * techo. Lo segundo es el bug que ya nos mordió una vez con la hambruna natar: una
 * cantidad sin cota acreditada retroactivamente. Acá se prueba con aldeas reales, no
 * leyendo el código.
 *
 * Cubre:
 *   A. Progresión de campos y guarnición a distintas edades, en el rango que este
 *      servidor puede pelear.
 *   B. Recalcular es idempotente y no depende del azar.
 *   C. El objetivo nunca supera lo que el cereal alimenta, y el ponerse al día tiene tope.
 *   D. Dos puestas al día simultáneas acreditan el intervalo una sola vez.
 *   E. Una aldea limpiada se rearma.
 *   F. Aparición: dentro de la banda, sólo en casillas libres, respeta el tope y no
 *      filtra la casilla cuando no puede colocar.
 *   G. Hambruna: la viva sí, la estática no, la del jugador sí.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_natar_settlements.php
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

$created = array();
function dropScratch() {
    global $database, $created;
    foreach($created as $wref) {
        foreach(array('vdata' => 'wref', 'fdata' => 'vref', 'units' => 'vref', 'tdata' => 'vref', 'abdata' => 'vref') as $table => $key) {
            $database->query("DELETE FROM ".TB_PREFIX.$table." WHERE $key = ".(int)$wref);
        }
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id = ".(int)$wref);
    }
    $created = array();
}
register_shutdown_function('dropScratch');

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$accrueMethod = $reflection->getMethod('accrueProductionBeforeChange');
$accrueMethod->setAccessible(true);
$accrue = function ($wref, $until) use ($accrueMethod, $automation) {
    return $accrueMethod->invoke($automation, $wref, $until);
};
$starvation = $reflection->getMethod('starvation');
$starvation->setAccessible(true);

function garrison($wref) {
    global $database;
    $units = $database->getUnit($wref);
    $total = 0;
    for($unit = 1; $unit <= 50; $unit++) {
        $total += is_array($units) && isset($units['u'.$unit]) ? (int)$units['u'.$unit] : 0;
    }
    return $total;
}

check($database->ensureNpcVillageColumns(), 'las columnas npckind/npcupdate existen o se crean solas');

$now = time();
$wref = natarSettlementSpawn($now, true);
if($wref <= 0) {
    fwrite(STDERR, "No se pudo crear la aldea de prueba: ¿hay aldeas de jugador y casillas libres?\n");
    exit(1);
}
$created[] = $wref;

$village = $database->getVillage($wref);
check(isLivingNpcVillage($village), 'la aldea nace marcada como NPC viva');
check((int)$village['owner'] === natarsAccountId(), 'pertenece a la cuenta natar');
check(!isStaticNpcVillage($village), 'y no como guarnición estática');

// --- A. Progresión por edad ---------------------------------------------------------
$ages = array('recién nacida' => 0, 'una semana' => 604800, 'un mes' => 2592000, 'un año' => 31536000);
$levels = array();
$targets = array();
foreach($ages as $label => $delta) {
    $at = $now + $delta;
    $fields = $database->getResourceLevel($wref);
    $levels[$label] = natarSettlementFieldLevel($village, $at);
    $targets[$label] = array_sum(natarSettlementGarrisonTarget($fields, $village, $at));
    printf("     %-16s campos n%-2d   guarnición sostenible %s\n", $label, $levels[$label], number_format($targets[$label]));
}
check($levels['recién nacida'] === NATAR_SETTLEMENT_START_FIELD_LEVEL,
    'nace con los campos en el nivel inicial');
check($levels['un año'] === NATAR_SETTLEMENT_MAX_FIELD_LEVEL,
    'los campos dejan de crecer en el nivel máximo');
check($levels['una semana'] > $levels['recién nacida'] && $levels['un mes'] >= $levels['una semana'],
    'la progresión es monótona');
check($targets['recién nacida'] > 0 && $targets['recién nacida'] <= 400,
    'una aldea recién nacida ('.number_format($targets['recién nacida']).' tropas) la puede tomar un ejército de este servidor');
check($targets['un año'] > $targets['recién nacida'] * 3,
    'una madura ('.number_format($targets['un año']).') es un objetivo serio');

// --- B. Reproducible ----------------------------------------------------------------
$fields = $database->getResourceLevel($wref);
$first = natarSettlementGarrisonTarget($fields, $village, $now + 604800);
$second = natarSettlementGarrisonTarget($fields, $village, $now + 604800);
check($first === $second, 'el objetivo es el mismo si se calcula dos veces');

$before = garrison($wref);
natarSettlementBringUpToDate($wref, $now, $accrue);
check(garrison($wref) === $before, 'poner al día sin que pase tiempo no cambia nada');

// --- C. Techo del cereal y tope del ponerse al día -----------------------------------
$far = $now + 31536000 * 3;
$database->query("UPDATE ".TB_PREFIX."vdata SET npcupdate = ".(int)$now." WHERE wref = $wref");
natarSettlementBringUpToDate($wref, $far, $accrue);
$afterCatchup = garrison($wref);
$fields = $database->getResourceLevel($wref);
$ceiling = array_sum(natarSettlementGarrisonTarget($fields, $village, $far));
check($afterCatchup <= $ceiling,
    'tras tres años sin que nadie la toque, la guarnición ('.number_format($afterCatchup)
    .') no supera lo que el cereal alimenta ('.number_format($ceiling).')');

$rate = natarSettlementTrainingRate($fields, $village, $far);
$maxPerCatchup = (int)ceil(array_sum($rate) * NATAR_SETTLEMENT_CATCHUP_CAP);
check($afterCatchup - $before <= $maxPerCatchup + 10,
    'una sola puesta al día no agrega más que el tope ('.number_format($maxPerCatchup).' tropas)');

$clockAfter = (int)$database->getVillageField($wref, 'npcupdate');
check($clockAfter === $far,
    'cuando se recorta, el reloj salta al presente y el atraso se descarta');

// --- D. Concurrencia -----------------------------------------------------------------
$database->query("UPDATE ".TB_PREFIX."vdata SET npcupdate = ".(int)($far - 86400)." WHERE wref = $wref");
$snapshot = $database->getVillage($wref);
$clock = (int)$snapshot['npcupdate'];
$firstWins = $database->advanceNpcVillageClock($wref, $clock, $far);
$secondWins = $database->advanceNpcVillageClock($wref, $clock, $far);
check($firstWins && !$secondWins,
    'dos puestas al día simultáneas: sólo la primera reclama el tramo');

// --- E. Se rearma --------------------------------------------------------------------
$database->query("UPDATE ".TB_PREFIX."units SET u41=0,u42=0,u43=0,u44=0,u45=0,u46=0,u47=0 WHERE vref = $wref");
$database->query("UPDATE ".TB_PREFIX."vdata SET npcupdate = ".(int)($far - 86400)." WHERE wref = $wref");
natarSettlementBringUpToDate($wref, $far, $accrue);
check(garrison($wref) > 0, 'una aldea limpiada vuelve a entrenar tropas');

// --- F. Aparición ---------------------------------------------------------------------
$anchor = natarSettlementPickAnchor();
if(is_array($anchor)) {
    $coor = $database->getCoor($wref);
    $players = $database->query_return(
        'SELECT w.x, w.y FROM '.TB_PREFIX.'vdata v INNER JOIN '.TB_PREFIX.'wdata w ON w.id = v.wref '
        .'WHERE '.playerAccountSql('v`.`owner')
    );
    $closest = null;
    foreach($players as $player) {
        $distance = natarSettlementDistance((int)$coor['x'], (int)$coor['y'], (int)$player['x'], (int)$player['y']);
        if($closest === null || $distance < $closest) {
            $closest = $distance;
        }
    }
    check($closest !== null && $closest >= NATAR_SETTLEMENT_MIN_DISTANCE && $closest <= NATAR_SETTLEMENT_MAX_DISTANCE,
        'la aldea cayó dentro de la banda de saqueo ('.round($closest, 1).' casillas del jugador más cercano)');
}

$occupied = $database->query_return("SELECT occupied FROM ".TB_PREFIX."wdata WHERE id = $wref");
check((int)$occupied[0]['occupied'] === 1, 'la casilla quedó marcada como ocupada');

$freeBefore = $database->query_return("SELECT COUNT(*) AS n FROM ".TB_PREFIX."wdata WHERE occupied = 0");
// Una banda sin ninguna casilla libre no puede dejar nada reservado.
$noField = natarSettlementFindField(99999, 99999);
$freeAfter = $database->query_return("SELECT COUNT(*) AS n FROM ".TB_PREFIX."wdata WHERE occupied = 0");
check($noField === 0 && (int)$freeBefore[0]['n'] === (int)$freeAfter[0]['n'],
    'si no hay casilla libre en la banda no se crea nada ni se reserva ninguna');

check(natarSettlementSpawn($now) === 0,
    'no nace una segunda aldea antes de que pase el intervalo');

// --- G. Hambruna ----------------------------------------------------------------------
$netCrop = $reflection->getMethod('villageNetCropProduction');
$netCrop->setAccessible(true);
$database->query("UPDATE ".TB_PREFIX."units SET u45 = 100000 WHERE vref = $wref");
check($netCrop->invoke($automation, $wref) < 0,
    'una aldea NPC viva sí paga la manutención de sus tropas');
$database->query("UPDATE ".TB_PREFIX."vdata SET crop = -1, starv = 0, starvupdate = 0 WHERE wref = $wref");
@unlink('GameEngine/Prevention/starvation.txt');
$starvation->invoke($automation);
$after = $database->getUnit($wref);
check((int)$after['u45'] < 100000, 'y sí se muere de hambre, igual que la de un jugador');

$database->setVillageNpcKind($wref, NPC_KIND_STATIC);
check($netCrop->invoke($automation, $wref) >= 0,
    'la misma aldea marcada como estática deja de pagar manutención');
$database->setVillageNpcKind($wref, NPC_KIND_LIVING);

dropScratch();

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
