<?php
/**
 * Una aldea natar tiene que dar botín cuando se la saquea.
 *
 * El fallo que fija: las aldeas natar se creaban sin almacén ni granero, así que su tope
 * quedaba en el de una aldea recién fundada (800 por recurso), mientras que el escondite
 * nivel 10 que el instalador les pone esconde 10.000 de cada uno en este servidor. La
 * cuenta del saqueo es `disponible = guardado - escondite`, o sea que daba cero siempre:
 * la Aldea de la Maravilla era imposible de saquear aunque no le quedara una sola tropa.
 * En Travian las aldeas natar se pueden atacar y saquear como cualquier otra.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_natar_lootable.php
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
$updateRes = $reflection->getMethod('updateRes');
$updateRes->setAccessible(true);

$wref = 0;
function dropScratchVillage() {
    global $database, $wref;
    if($wref <= 0) {
        return;
    }
    foreach(array('vdata' => 'wref', 'fdata' => 'vref', 'units' => 'vref', 'tdata' => 'vref', 'abdata' => 'vref') as $table => $key) {
        $database->query("DELETE FROM ".TB_PREFIX.$table." WHERE $key = $wref");
    }
    $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id = $wref");
    $wref = 0;
}
register_shutdown_function('dropScratchVillage');

$row = $database->query_return("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND fieldtype = 3 LIMIT 1");
if(!is_array($row) || !isset($row[0]['id'])) {
    fwrite(STDERR, "No queda ningún campo de aldea libre en el mundo local.\n");
    exit(1);
}
$wref = (int)$row[0]['id'];
$database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 1 WHERE id = $wref");
$database->addVillage($wref, 2, 'Natars', '1');
$database->addResourceFields($wref, $database->getVillageType($wref));
$database->addUnits($wref);
$database->addTech($wref);
$database->addABTech($wref);
$database->query("UPDATE ".TB_PREFIX."vdata SET name = 'Aldea de la Maravilla', capital = 0, natar = 1 WHERE wref = $wref");
$database->query("UPDATE ".TB_PREFIX."fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 10, f19t = 23, f19 = 10, f99t = 40, f26 = 0, f26t = 0, f21 = 1, f21t = 15, f39 = 1, f39t = 16 WHERE vref = $wref");
natarRestockGarrison($wref, natarWonderGarrison(false));

$buildings = $database->getResourceLevel($wref);
$cranny = $automation->calculateCrannyProtection($buildings, 1, 5);
check($cranny['capacity'] > 0, "la aldea conserva su escondite (".number_format($cranny['capacity'])." por recurso)");

$plan = natarProvisionVillage($wref);
check($plan['maxstore'] > $cranny['capacity'],
    "el almacén (".number_format($plan['maxstore']).") supera al escondite (".number_format($cranny['capacity']).")");

// Un día entero de producción sin que nadie la toque, que es como llega cualquier
// atacante a una aldea natar.
$database->query("UPDATE ".TB_PREFIX."vdata SET wood = 0, clay = 0, iron = 0, crop = 0, lastupdate = ".(time() - 86400)." WHERE wref = $wref");
$updateRes->invoke($automation, $wref, 2);

$village = $database->getVillage($wref);
$lootable = array();
foreach(array('wood', 'clay', 'iron', 'crop') as $resource) {
    $lootable[$resource] = floor((float)$village[$resource] - $cranny['protected']);
}
printf("   tras 24 h: madera %s, barro %s, hierro %s, cereal %s\n",
    number_format($village['wood']), number_format($village['clay']),
    number_format($village['iron']), number_format($village['crop']));
printf("   saqueable: madera %s, barro %s, hierro %s, cereal %s\n",
    number_format($lootable['wood']), number_format($lootable['clay']),
    number_format($lootable['iron']), number_format($lootable['crop']));

foreach($lootable as $resource => $amount) {
    check($amount > 0, "queda $resource por encima del escondite para llevarse (".number_format($amount).")");
}

// El escondite tiene que seguir cumpliendo su función: un atacante no puede vaciarla.
check(min($lootable) < (float)$village['wood'] + (float)$village['clay'],
    "el escondite sigue protegiendo una parte de lo guardado");

dropScratchVillage();

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
