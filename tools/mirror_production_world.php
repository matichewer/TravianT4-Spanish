<?php
/**
 * Rehace el mundo LOCAL como espejo del de producción, para poder probar cosas de mapa con
 * la geografía real en vez de con un mundo de juguete.
 *
 * Por qué existe. El Docker de desarrollo tenía un mundo de ±25 mientras el código declara
 * WORLD_MAX 100, y ninguna aldea coincidía con las de travian.chewer.net. Cualquier cosa
 * que dependa de dónde está la gente —la zona gris, la aparición de aldeas natar, las
 * distancias de viaje— no se podía probar de forma representativa.
 *
 * De paso ejercita el generador de mapa del instalador, incluido el terreno de la zona
 * gris, que sólo corre al instalar y por lo tanto nunca se ve en desarrollo.
 *
 * DESTRUCTIVO: borra el mundo local entero. No correrlo nunca contra producción.
 *
 * Uso:  docker compose exec -T web php /var/www/html/tools/mirror_production_world.php --destructivo
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--destructivo', $argv, true)) {
    fwrite(STDERR,
        "Borra y regenera el mundo local completo (mapa, cuentas y aldeas).\n"
        ."Sólo contra el Docker de desarrollo:\n"
        ."  docker compose exec -T web php /var/www/html/tools/mirror_production_world.php --destructivo\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/unitdata.php";
include "GeneratorX.php";
include "GreyZone.php";
include "NatarVillage.php";

global $database;
$connection = $database->connection;

/**
 * Las aldeas reales de travian.chewer.net, tal como las lista el ranking.
 */
$productionVillages = array(
    array('chewer',     '[00] Cuarto de Libra',          5,   2, 690, 1),
    array('Fedro',      'Hefesto',                       6,  21, 606, 1),
    array('chewer',     '[02] Sed de Ketchup',          37, -20, 544, 0),
    array('MBeluS',     'Simcity',                       7,  17, 503, 1),
    array('Che_Bigote', 'Antipelados',                   5,   7, 425, 1),
    array('Fedro',      'Zeus',                          8,  26, 399, 0),
    array('chewer',     '[01] Capital del Cheddar',     38, -19, 364, 0),
    array('chewer',     '[03] Fortaleza del Pepinillo', 39, -18, 360, 0),
    array('Che_Bigote', 'Gonca',                        -4,   7, 196, 0),
    array('MBeluS',     'Piolinópolis',                  2,  62, 187, 0),
    array('chewer',     '[04] Ministerio del Paty',     41, -19, 159, 0),
    array('Fedro',      'Ananké',                        8,  28, 137, 0),
    array('MBeluS',     'Aldea de MBeluS 3',            20,  60,  36, 0)
);

// Las 13 coordenadas de Maravilla que fija el instalador.
$wonderCoords = array();
preg_match_all(
    '/case\s+\d+:\s*\$x\s*=\s*(-?\d+);\s*\$y\s*=\s*(-?\d+);/s',
    file_get_contents($root.'/install/include/multihunter.php'),
    $matches, PREG_SET_ORDER
);
foreach($matches as $match) {
    $wonderCoords[] = array((int)$match[1], (int)$match[2]);
}

$password = 'zonagris123';
$radius = (int)WORLD_MAX;

// Las casillas donde va a haber una aldea tienen que ser valles válidos: si el sorteo las
// deja como oasis, addResourceFields() no tiene caso para el tipo 0 y la aldea nace sin
// campos. Se reservan antes de generar el mapa.
$reserved = array();
foreach($productionVillages as $village) {
    $reserved[$village[2].'|'.$village[3]] = 4;      // valle normal 4-4-4-6
}
foreach($wonderCoords as $coord) {
    $reserved[$coord[0].'|'.$coord[1]] = 6;          // 15-cropper, como las Maravillas
}

echo "Regenerando el mundo local a ±$radius (".pow($radius * 2 + 1, 2)." casillas)...\n";

// --- 1. Vaciar -------------------------------------------------------------------------
foreach(array('wdata', 'vdata', 'fdata', 'units', 'tdata', 'abdata', 'odata', 'movement',
    'attacks', 'ndata', 'enforcement', 'training', 'research', 'market', 'send', 'mdata',
    'hero', 'heroinventory', 'prisoners', 'a2b', 'farmlist', 'raidlist', 'ww_attacks',
    'adventure', 'bdata', 'demolition', 'route') as $table) {
    mysqli_query($connection, "TRUNCATE TABLE ".TB_PREFIX.$table);
}
mysqli_query($connection, "DELETE FROM ".TB_PREFIX."users WHERE ".playerAccountSql('id'));
mysqli_query($connection, "ALTER TABLE ".TB_PREFIX."users AUTO_INCREMENT = 5");

// --- 2. El mapa ------------------------------------------------------------------------
// Mismo orden que el generador del instalador (y sobre el que se apoya getBaseID): la fila
// de arriba primero, de izquierda a derecha, con el id autoincremental desde 1.
$greyTiles = 0;
$values = array();
$inserted = 0;
for($y = $radius; $y >= -$radius; $y--) {
    for($x = -$radius; $x <= $radius; $x++) {
        if($x === 1 && $y === 0) {
            $type = 3; $oasis = 0;
        } elseif($x === 0 && $y === 0) {
            $type = 1; $oasis = 0;
        } elseif(isset($reserved[$x.'|'.$y])) {
            $type = $reserved[$x.'|'.$y]; $oasis = 0;
        } elseif(greyZoneContainsCoordinates($x, $y)) {
            list($type, $oasis) = greyZoneTerrain($x, $y);
            $greyTiles++;
        } else {
            $roll = rand(1, 1000);
            $ladder = array(10=>array(1,0), 90=>array(2,0), 400=>array(3,0), 480=>array(4,0),
                560=>array(5,0), 570=>array(6,0), 600=>array(7,0), 630=>array(8,0),
                660=>array(9,0), 740=>array(10,0), 820=>array(11,0), 900=>array(12,0),
                908=>array(0,1), 916=>array(0,2), 924=>array(0,3), 932=>array(0,4),
                940=>array(0,5), 948=>array(0,6), 956=>array(0,7), 964=>array(0,8),
                972=>array(0,9), 980=>array(0,10), 988=>array(0,11));
            $type = 0; $oasis = 12;
            foreach($ladder as $threshold => $pair) {
                if($roll <= $threshold) { $type = $pair[0]; $oasis = $pair[1]; break; }
            }
        }
        $image = $oasis == 0 ? 't'.rand(0, 9) : 'o'.$oasis;
        $values[] = "(0,$type,$oasis,$x,$y,0,'$image')";
        if(count($values) >= 2000) {
            mysqli_query($connection, "INSERT INTO ".TB_PREFIX."wdata VALUES ".implode(',', $values));
            $inserted += count($values);
            $values = array();
        }
    }
}
if($values) {
    mysqli_query($connection, "INSERT INTO ".TB_PREFIX."wdata VALUES ".implode(',', $values));
    $inserted += count($values);
}
printf("  mapa: %s casillas, %s dentro de la zona gris\n", number_format($inserted), number_format($greyTiles));

$database->worldRadiusCache = null;
$database->populateOasisdata();
$database->populateOasis();
$database->populateOasisUnitsLow();
echo "  oasis poblados\n";

// --- 3. Las cuentas y sus aldeas -------------------------------------------------------
$tribes = array('chewer' => 1, 'Fedro' => 1, 'MBeluS' => 2, 'Che_Bigote' => 3);
$userIds = array();
foreach($tribes as $name => $tribe) {
    mysqli_query($connection,
        "INSERT INTO ".TB_PREFIX."users (username,password,access,email,timestamp,tribe,location,act,protect,quest,fquest,cp) "
        ."VALUES ('".mysqli_real_escape_string($connection, $name)."','".md5($password)."',2,'"
        .strtolower($name)."@local.invalid',".time().",$tribe,'','',0,25,35,'0,0,0,0,0,0,0,0,0,0,0')");
    $userIds[$name] = (int)mysqli_insert_id($connection);
}

// Plus para la cuenta del administrador del servidor, que es con la que se prueba: la
// columna `plus` es el instante hasta el que dura, así que un año alcanza. Sobrevive a
// regenerar el mundo porque vive acá y no en un UPDATE suelto.
mysqli_query($connection,
    "UPDATE ".TB_PREFIX."users SET plus = ".(time() + 31536000)." WHERE username = 'chewer'");

$generator = new GeneratorX();
foreach($productionVillages as $village) {
    list($owner, $name, $x, $y, $pop, $capital) = $village;
    $wref = $generator->getBaseID($x, $y);
    $database->setFieldTaken($wref);
    $database->addVillage($wref, $userIds[$owner], $owner, $capital);
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);
    // Fundadas hace tres meses, como las de producción: así quedan del lado correcto de la
    // fecha de corte de la zona gris y el espejo refleja de verdad lo que se ve allá.
    mysqli_query($connection, "UPDATE ".TB_PREFIX."vdata SET name='"
        .mysqli_real_escape_string($connection, $name)."', pop=$pop, capital=$capital, "
        ."created=".(time() - 7776000).", "
        ."wood=1200, clay=1200, iron=1200, crop=1200 WHERE wref = $wref");
}
printf("  %d cuentas y %d aldeas de jugador\n", count($userIds), count($productionVillages));

// --- 4. El mundo natar -----------------------------------------------------------------
$natars = natarsAccountId();
$capitalWref = $generator->getBaseID(0, 0);
$database->setFieldTaken($capitalWref);
$database->addVillage($capitalWref, $natars, 'Natars', '1');
$database->addResourceFields($capitalWref, $database->getVillageType($capitalWref));
$database->addUnits($capitalWref);
$database->addTech($capitalWref);
$database->addABTech($capitalWref);
mysqli_query($connection, "UPDATE ".TB_PREFIX."vdata SET name='Capital natar', capital=1 WHERE wref = $capitalWref");
natarRestockGarrison($capitalWref, natarCapitalGarrison());
natarProvisionVillage($capitalWref);

// Multihunter vive en (1|0), pegado a la capital natar y por lo tanto dentro de la zona
// gris. Faltaba en el espejo, que es justamente donde hay que ver cómo queda esa esquina.
$mhWref = $generator->getBaseID(1, 0);
if(!$database->getVillageState($mhWref)) {
    $database->setFieldTaken($mhWref);
    $database->addVillage($mhWref, UID_MULTIHUNTER, 'Multihunter', '1');
    $database->addResourceFields($mhWref, $database->getVillageType($mhWref));
    $database->addUnits($mhWref);
    $database->addTech($mhWref);
    $database->addABTech($mhWref);
    mysqli_query($connection, "UPDATE ".TB_PREFIX."vdata SET name='Aldea del Multihunter' WHERE wref = $mhWref");
}

foreach($wonderCoords as $coord) {
    $wref = $generator->getBaseID($coord[0], $coord[1]);
    if($database->getVillageState($wref)) {
        continue;
    }
    $database->setFieldTaken($wref);
    $database->addVillage($wref, $natars, 'Natars', '0');
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);
    mysqli_query($connection, "UPDATE ".TB_PREFIX."vdata SET name='Aldea de la Maravilla', capital=0, natar=1 WHERE wref = $wref");
    mysqli_query($connection, "UPDATE ".TB_PREFIX."fdata SET f22t=27,f22=10,f28t=25,f28=10,f19t=23,f19=10,f99t=40,f26=0,f26t=0,f21=1,f21t=15,f39=1,f39t=16 WHERE vref = $wref");
    natarRestockGarrison($wref, natarWonderGarrison());
    natarProvisionVillage($wref);
}
printf("  mundo natar: capital + %d Maravillas\n", count($wonderCoords));

echo "\nListo. Entrá con cualquiera de estas cuentas, contraseña '$password':\n";
foreach($userIds as $name => $id) {
    echo "   $name\n";
}
echo "\nLa zona gris está en el anillo/disco ".GREY_ZONE_INNER_RADIUS."-".GREY_ZONE_OUTER_RADIUS
    .". El volcán, en (0|0).\n";
