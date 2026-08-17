<?php
/**
 * Lo que un jugador puede hacerle a una aldea natar viva: saquearla, arrasarla, y no
 * reforzarla.
 *
 * Va aparte de check_natar_settlements.php porque eso prueba el modelo (edad, crecimiento,
 * entrenamiento) y esto prueba los caminos del motor que la tocan desde afuera.
 *
 * Cubre:
 *   A. Una aldea viva acumula por encima del escondite, así que un saqueo trae botín.
 *   B. El camino que arrasa una aldea con catapultas funciona con una aldea NPC.
 *   C. La capital natar sobrevive a ese mismo camino, porque una capital no se arrasa.
 *   D. No se pueden mandar refuerzos a una aldea natar.
 *   E. Ni las aldeas vivas ni las estáticas ensucian las clasificaciones.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_natar_settlement_combat.php
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
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
include "Ranking.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";
// destroyCatapultedVillage() escribe en el catálogo de aldeas arrasadas: sin $logging el
// camino se corta justo al final, que es lo que pasa en producción si alguien lo llama
// desde un proceso sin Session.php.
include "Logging.php";

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
$updateRes = $reflection->getMethod('updateRes');
$updateRes->setAccessible(true);
$destroy = $reflection->getMethod('destroyCatapultedVillage');
$destroy->setAccessible(true);

$now = time();
$wref = natarSettlementSpawn($now, true);
if($wref <= 0) {
    fwrite(STDERR, "No se pudo crear la aldea de prueba.\n");
    exit(1);
}
$created[] = $wref;

// Se la envejece para que tenga campos y almacén de verdad.
$mature = $now - (NATAR_SETTLEMENT_GROWTH_INTERVAL * NATAR_SETTLEMENT_MAX_FIELD_LEVEL);
$database->query("UPDATE ".TB_PREFIX."vdata SET created = ".(int)$mature.", npcupdate = ".(int)$mature." WHERE wref = $wref");
natarSettlementBringUpToDate($wref, $now, $accrue);

// --- A. Da botín ----------------------------------------------------------------------
$fields = $database->getResourceLevel($wref);
$cranny = $automation->calculateCrannyProtection($fields, 1, 5);
$database->query("UPDATE ".TB_PREFIX."vdata SET wood = 0, clay = 0, iron = 0, crop = 0, lastupdate = ".(int)($now - 86400)." WHERE wref = $wref");
$updateRes->invoke($automation, $wref, natarsAccountId());

$village = $database->getVillage($wref);
$lootable = array();
foreach(array('wood', 'clay', 'iron') as $resource) {
    $lootable[$resource] = floor((float)$village[$resource] - $cranny['protected']);
}
printf("     escondite %s por recurso · tras 24 h: madera %s, barro %s, hierro %s\n",
    number_format($cranny['protected']), number_format($village['wood']),
    number_format($village['clay']), number_format($village['iron']));
check(min($lootable) > 0,
    'una aldea viva madura junta por encima del escondite: hay botín (madera saqueable '
    .number_format($lootable['wood']).')');
check((float)$village['wood'] <= (float)$village['maxstore'],
    'y no se pasa de su almacén');

// --- B. Se puede arrasar ---------------------------------------------------------------
$razed = $destroy->invoke($automation, $wref, natarsAccountId(), 0);
$stillThere = $database->query_return("SELECT wref FROM ".TB_PREFIX."vdata WHERE wref = $wref");
$fieldFreed = $database->query_return("SELECT occupied FROM ".TB_PREFIX."wdata WHERE id = $wref");
check($razed === true && empty($stillThere),
    'las catapultas arrasan una aldea natar viva igual que la de un jugador');
check((int)$fieldFreed[0]['occupied'] === 0,
    'y la casilla vuelve a quedar libre');
$created = array_values(array_diff($created, array($wref)));

// --- C. La capital natar no ------------------------------------------------------------
$capital = $database->query_return(
    "SELECT wref FROM ".TB_PREFIX."vdata WHERE owner = ".natarsAccountId()." AND capital = 1 LIMIT 1"
);
if(!is_array($capital) || !isset($capital[0]['wref'])) {
    // El mundo local no tiene capital natar: se arma una descartable para poder probar
    // que sobrevive. Es la comprobación que más importa de este archivo.
    $free = $database->query_return("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND fieldtype = 3 LIMIT 1");
    if(is_array($free) && isset($free[0]['id'])) {
        $scratchCapital = (int)$free[0]['id'];
        $created[] = $scratchCapital;
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 1 WHERE id = $scratchCapital");
        $database->addVillage($scratchCapital, natarsAccountId(), 'Natars', '1');
        $database->addResourceFields($scratchCapital, $database->getVillageType($scratchCapital));
        $database->addUnits($scratchCapital);
        $database->addTech($scratchCapital);
        $database->addABTech($scratchCapital);
        $database->query("UPDATE ".TB_PREFIX."vdata SET capital = 1 WHERE wref = $scratchCapital");
        $database->setVillageNpcKind($scratchCapital, NPC_KIND_STATIC);
        $capital = array(array('wref' => $scratchCapital));
    }
}
if(is_array($capital) && isset($capital[0]['wref'])) {
    $capitalWref = (int)$capital[0]['wref'];
    $survived = $destroy->invoke($automation, $capitalWref, natarsAccountId(), 1);
    $exists = $database->query_return("SELECT wref FROM ".TB_PREFIX."vdata WHERE wref = $capitalWref");
    check($survived === false && !empty($exists),
        'la capital natar sobrevive: una capital nunca se arrasa');
} else {
    echo "[--] este mundo no tiene capital natar: no se pudo comprobar".PHP_EOL;
}

// --- D. No acepta refuerzos ------------------------------------------------------------
$unitsSource = file_get_contents($root.'/GameEngine/Units.php');
check(strpos($unitsSource, 'No puedes enviar refuerzos a una aldea natar.') !== false
    && strpos($unitsSource, "isSystemAccount(\$database->getVillageField(\$data['to_vid'],'owner'))") !== false,
    'el envío revalida que el destino no sea una aldea natar antes de aceptar un refuerzo');

// --- E. Fuera de las clasificaciones ----------------------------------------------------
$second = natarSettlementSpawn($now + NATAR_SETTLEMENT_SPAWN_INTERVAL + 1, true);
if($second > 0) {
    $created[] = $second;
    $database->query("UPDATE ".TB_PREFIX."vdata SET pop = 9999 WHERE wref = $second");
}
$ranking = new Ranking();
$result = $ranking->procVillagesRanking("LIMIT 20");
$rankedNatar = false;
while($row = mysql_fetch_assoc($result)) {
    if(isSystemAccount($row['owner'])) {
        $rankedNatar = true;
    }
}
check(!$rankedNatar,
    'una aldea natar con 9.999 de población no aparece en la clasificación de aldeas');

$result = $ranking->procUsersRanking("LIMIT 20");
$rankedAccount = false;
while($row = mysql_fetch_assoc($result)) {
    if(isSystemAccount($row['userid'])) {
        $rankedAccount = true;
    }
}
check(!$rankedAccount, 'ni la cuenta natar en la clasificación de jugadores');

dropScratch();

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
