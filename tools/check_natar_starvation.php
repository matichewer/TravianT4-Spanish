<?php
/**
 * Las aldeas natar no se pueden morir de hambre.
 *
 * El fallo que fija: las Aldeas de la Maravilla nacían con miles de tropas natar y los
 * 18 campos en nivel 0, o sea un balance de unos -45.000 de cereal/h. Como `lastupdate`
 * de una aldea NPC sólo avanza cuando alguien la ataca, el primer ataque —incluso un
 * espionaje que fracasa— acreditaba de golpe semanas de producción negativa, el granero
 * quedaba en rojo y starvation() se comía la guarnición entera a un contingente por
 * minuto. En unos diez minutos la Maravilla quedaba indefensa sin que nadie la tocara,
 * y el atacante siguiente entraba sin una sola baja.
 *
 * Esto no mira el código: arma una aldea igual que el instalador y le pasa el
 * starvation() real por encima.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_natar_starvation.php
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
include "NatarVillage.php";
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
$starvation = $reflection->getMethod('starvation');
$starvation->setAccessible(true);

$created = array();
function scratchNatarVillage($garrison) {
    global $database, $created;
    $row = $database->query_return("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND fieldtype = 3 LIMIT 1");
    if(!is_array($row) || !isset($row[0]['id'])) {
        fwrite(STDERR, "No queda ningún campo de aldea libre en el mundo local.\n");
        exit(1);
    }
    $wref = (int)$row[0]['id'];
    $created[] = $wref;
    $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 1 WHERE id = $wref");
    $database->addVillage($wref, 2, 'Natars', '1');
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);
    $database->query("UPDATE ".TB_PREFIX."vdata SET name = 'Aldea de la Maravilla', capital = 0, natar = 1 WHERE wref = $wref");
    $database->query("UPDATE ".TB_PREFIX."fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 10, f19t = 23, f19 = 10, f99t = 40, f26 = 0, f26t = 0, f21 = 1, f21t = 15, f39 = 1, f39t = 16 WHERE vref = $wref");
    natarRestockGarrison($wref, $garrison);
    return $wref;
}

function dropScratchVillages() {
    global $database, $created;
    foreach($created as $wref) {
        foreach(array('vdata' => 'wref', 'fdata' => 'vref', 'units' => 'vref', 'tdata' => 'vref', 'abdata' => 'vref') as $table => $key) {
            $database->query("DELETE FROM ".TB_PREFIX.$table." WHERE $key = $wref");
        }
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id = $wref");
    }
    $created = array();
}

function garrisonSize($wref) {
    global $database;
    $units = $database->getUnit($wref);
    $total = 0;
    for($unit = 1; $unit <= 50; $unit++) {
        $total += is_array($units) && isset($units['u'.$unit]) ? (int)$units['u'.$unit] : 0;
    }
    return $total;
}

register_shutdown_function('dropScratchVillages');

// --- A. Una Aldea de la Maravilla aprovisionada se alimenta sola -------------------
$wonder = scratchNatarVillage(natarWonderGarrison(false));
$before = garrisonSize($wonder);
$plan = natarProvisionVillage($wonder);

check($before > 0, "la aldea de prueba arranca con guarnición ($before tropas)");
check($plan !== null && $plan['crop_level'] === 10,
    "los 18 campos quedan en nivel 10, como en el T4 oficial (nivel ".($plan ? $plan['crop_level'] : '?').")");
check($plan['net_crop'] >= 0,
    "produce cereal de sobra mientras es natar: ".round($plan['net_crop'])."/h");
check($plan['net_crop_as_player'] < 0,
    "y con manutención esa misma guarnición daría ".round($plan['net_crop_as_player'])."/h, "
    ."que es lo que la vaciaba");

$measured = $netCrop->invoke($automation, $wonder);
check($measured >= 0,
    "Automation no le cobra manutención a una aldea NPC (".round($measured)."/h)");

// --- B. starvation() no le toca una tropa ------------------------------------------
// Se la fuerza a entrar en el barrido: cereal negativo es la condición de getStarvation().
for($pass = 1; $pass <= 3; $pass++) {
    $database->query("UPDATE ".TB_PREFIX."vdata SET crop = -1, starvupdate = 0 WHERE wref = $wonder");
    @unlink('GameEngine/Prevention/starvation.txt');
    $starvation->invoke($automation);
}
check(garrisonSize($wonder) === $before,
    "tres pasadas de starvation() dejan la guarnición intacta (".garrisonSize($wonder)." de $before)");
check((float)$database->getVillageField($wonder, 'crop') >= 0,
    "starvation() le corta el rojo de cereal en vez de arrastrarlo");

// --- C. La capital natar tampoco, aunque su balance no se pueda cubrir -------------
$capital = scratchNatarVillage(natarCapitalGarrison());
$database->query("UPDATE ".TB_PREFIX."vdata SET capital = 1, natar = 0 WHERE wref = $capital");
$capitalBefore = garrisonSize($capital);
$capitalPlan = natarProvisionVillage($capital);
check($capitalPlan['net_crop'] >= 0,
    "la capital natar también produce en positivo (".round($capitalPlan['net_crop'])."/h) pese a sus "
    .number_format($capitalPlan['upkeep'])." de cereal/h de tropas, que ningún campo podría cubrir");
for($pass = 1; $pass <= 3; $pass++) {
    $database->query("UPDATE ".TB_PREFIX."vdata SET crop = -1, starvupdate = 0 WHERE wref = $capital");
    @unlink('GameEngine/Prevention/starvation.txt');
    $starvation->invoke($automation);
}
check(garrisonSize($capital) === $capitalBefore,
    "la capital natar conserva sus ".number_format($capitalBefore)." tropas tras starvation()");

// --- D. Una aldea de jugador sí pasa hambre ----------------------------------------
// El arreglo no puede haber apagado la hambruna para todos.
$player = $database->query_return(
    "SELECT `wref` FROM ".TB_PREFIX."vdata WHERE ".playerAccountSql('owner')." LIMIT 1"
);
if(is_array($player) && isset($player[0]['wref'])) {
    $wref = (int)$player[0]['wref'];
    $saved = $database->getVillage($wref);
    $units = $database->getUnit($wref);
    $tribe = (int)$database->getUserField($database->getVillageField($wref, 'owner'), 'tribe', 0);
    $slot = ($tribe >= 1 && $tribe <= 5 ? ($tribe - 1) * 10 : 0) + 1;
    $savedUnit = is_array($units) ? (int)$units['u'.$slot] : 0;
    $database->query("UPDATE ".TB_PREFIX."units SET u$slot = 100000 WHERE vref = $wref");
    check($netCrop->invoke($automation, $wref) < 0,
        "una aldea de jugador sí paga la manutención de sus tropas en la producción");
    $database->query("UPDATE ".TB_PREFIX."vdata SET crop = -1, starv = 0, starvupdate = 0 WHERE wref = $wref");
    @unlink('GameEngine/Prevention/starvation.txt');
    $starvation->invoke($automation);
    $after = $database->getUnit($wref);
    check((int)$after['u'.$slot] < 100000,
        "una aldea de jugador con el granero vacío sigue perdiendo tropas por hambre");
    $database->query("UPDATE ".TB_PREFIX."units SET u$slot = $savedUnit WHERE vref = $wref");
    $database->query("UPDATE ".TB_PREFIX."vdata SET crop = ".(float)$saved['crop']
        .", starv = ".(float)$saved['starv'].", starvupdate = ".(int)$saved['starvupdate']
        ." WHERE wref = $wref");
} else {
    echo "[--] sin aldeas de jugador en el mundo local: no se pudo comprobar el caso de control".PHP_EOL;
}

dropScratchVillages();

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
