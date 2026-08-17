<?php
/**
 * Los nombres de las aldeas natar y los caminos que las crean.
 *
 * Lo que fija. Cuando salieron las aldeas natar independientes, la primera se vio en el
 * mapa como "Aldea Aldea natar": el tooltip antepone la palabra a mano, así que las 13
 * Maravillas venían mostrándose igual desde siempre. La capital, además, se llamaba
 * "1's village" porque el instalador pasaba invertidos los dos últimos argumentos de
 * addVillage(). Y todas las aldeas vivas nacían con el mismo nombre, o sea que con doce
 * en el mapa no se iban a poder distinguir.
 *
 * Cubre:
 *   A. Ningún tooltip del mapa antepone la palabra al nombre.
 *   B. Los nombres generados son estables, únicos y no empiezan con "Aldea".
 *   C. El instalador nombra la capital, y la migración renombra un mundo ya instalado.
 *   D. Los dos mods del panel crean por el camino compartido y no con SQL a mano.
 *   E. El informe de la herramienta de reparación muestra lo medido, no la constante.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_natar_village_names.php
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
include "Data/unitdata.php";
include "NatarSettlement.php";

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

// --- A. El tooltip del mapa ----------------------------------------------------------
foreach(array('Templates/Map/mapview.tpl', 'Templates/Map/mapviewlarge.tpl') as $template) {
    $source = file_get_contents($root.'/'.$template);
    check(strpos($source, '<b>Aldea ".$maparray') === false,
        basename($template).' ya no antepone la palabra al nombre de la aldea');
    check(strpos($source, '<b>".$maparray[$index][\'name\']') !== false,
        basename($template).' sigue mostrando el nombre');
}

// --- B. Nombres generados -------------------------------------------------------------
$sample = $database->query_return("SELECT id, x, y FROM ".TB_PREFIX."wdata WHERE fieldtype > 0 LIMIT 400");
$names = array();
$prefixed = 0;
foreach($sample as $field) {
    $name = natarSettlementName((int)$field['id'], (int)$field['x'], (int)$field['y']);
    $names[] = $name;
    if(stripos($name, 'aldea') === 0) {
        $prefixed++;
    }
}
check(count($names) > 100, 'se generaron '.count($names).' nombres de prueba');
check(count($names) === count(array_unique($names)),
    'no hay dos aldeas con el mismo nombre ('.count(array_unique($names)).' distintos)');
check($prefixed === 0,
    'ningún nombre empieza con "Aldea", que es la palabra que anteponen los listados');

$first = natarSettlementName(12345, 10, -10);
$second = natarSettlementName(12345, 10, -10);
check($first === $second, 'el nombre es estable: la misma aldea da siempre el mismo ("'.$first.'")');
check(natarSettlementName(12345, 10, -10) !== natarSettlementName(12346, 10, -10),
    'y dos aldeas distintas en la misma coordenada no colisionan');

// --- C. La capital ---------------------------------------------------------------------
$installer = file_get_contents($root.'/install/include/multihunter.php');
check(strpos($installer, "addVillage(\$wid, \$uid, 'Natars', '1')") !== false,
    'el instalador pasa el nombre y la capital en el orden correcto');
check(strpos($installer, "addVillage(\$wid, \$uid, '1', 'Natars')") === false,
    'y ya no queda la llamada con los argumentos invertidos');
check(strpos($installer, "SET name = 'Capital natar'") !== false,
    'el instalador nombra la capital natar');

$migrations = file_get_contents($root.'/tools/migrations.sql');
check(strpos($migrations, "SET v.name = 'Capital natar'") !== false
    && strpos($migrations, "v.capital = 1") !== false,
    'la migración renombra la capital de un mundo ya instalado');

$stale = $database->query_return(
    "SELECT wref, name FROM ".TB_PREFIX."vdata WHERE owner = ".natarsAccountId()
    ." AND capital = 1 AND (name LIKE '%1''s village%' OR name LIKE 'Aldea de 1%')"
);
check(empty($stale),
    'este mundo no tiene la capital natar con el nombre viejo'
    .(empty($stale) ? '' : ' (falta correr la migración)'));

// --- D. Los mods del panel --------------------------------------------------------------
foreach(array('addWW.php', 'natarend.php') as $mod) {
    $source = file_get_contents($root.'/GameEngine/Admin/Mods/'.$mod);
    check(strpos($source, "'3','Aldea de la Maravilla'") === false,
        "$mod ya no crea la aldea con owner = 3 (la Naturaleza)");
    check(strpos($source, 'natarsAccountId()') !== false,
        "$mod resuelve la cuenta natar por el camino compartido");
    check(strpos($source, 'natarProvisionVillage($wid)') !== false
        && strpos($source, 'natarRestockGarrison($wid, natarWonderGarrison())') !== false,
        "$mod arma la aldea con los helpers de NatarVillage.php y no con SQL suelto");
}
$addww = file_get_contents($root.'/GameEngine/Admin/Mods/addWW.php');
// Anclado al principio de línea: el comentario que explica por qué se quitó menciona el
// include viejo, y un strpos() suelto se marcaría a sí mismo.
check(preg_match('/^\s*include_once\("\.\.\/\.\.\/config\.php"\)/m', $addww) === 0,
    'addWW.php ya no incluye GameEngine/config.php, que no existe');

// --- D bis. La herramienta no confunde una aldea viva con una Maravilla -----------------
// natarVillages() separaba por la marca de capital, así que una aldea NPC viva caía en el
// grupo de las Maravillas: la reparación la habría rellenado con 31.000 tropas y
// convertido en guarnición estática.
$grouped = natarVillages();
check(isset($grouped['living']), 'natarVillages() distingue las aldeas independientes');
$misfiled = 0;
foreach(array('capital', 'wonder') as $group) {
    foreach($grouped[$group] as $row) {
        if(villageKindFromRow($row) === NPC_KIND_LIVING) {
            $misfiled++;
        }
    }
}
check($misfiled === 0, 'ninguna aldea viva quedó clasificada como Maravilla o capital');
$tool = file_get_contents($root.'/tools/fix_natar_villages.php');
check(strpos($tool, "\$villages['living']") !== false
    && strpos($tool, 'se dejan como están') !== false,
    'la herramienta de reparación deja en paz a las independientes');

// --- E. El informe de la herramienta ------------------------------------------------------
$fields = $database->getResourceLevel((int)$database->query_return(
    "SELECT wref FROM ".TB_PREFIX."vdata LIMIT 1"
)[0]['wref']);
$plan = natarVillagePlan($fields, 0);
check(isset($plan['measured_min_level'], $plan['measured_max_level'], $plan['above_max']),
    'el plan informa los niveles medidos y cuántos superan el máximo oficial');

$tool = file_get_contents($root.'/tools/fix_natar_villages.php');
check(strpos($tool, "\$plan['measured_min_level']") !== false,
    'la herramienta de reparación imprime el nivel actual además del planeado');
check(strpos($tool, 'AVISO:') !== false,
    'y avisa cuando encuentra un campo por encima del nivel oficial');

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
