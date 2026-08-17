<?php
/**
 * La zona gris: fundar en el centro despierta a los natars.
 *
 * Lo que fija. La zona es un ANILLO y no el disco del T4 oficial, y eso no es un descuido:
 * este mundo ya estaba empezado, y sus cuatro jugadores tenían la aldea principal más
 * cerca del centro que la Maravilla más cercana. Cualquier disco que contenga una
 * Maravilla los contiene a ellos. El anillo es la única geometría que da el reparto
 * oficial de Maravillas sin tocarle una aldea a nadie, así que el checker verifica esas
 * dos cosas a la vez: que las aldeas que ya existen queden afuera, y que el reparto de
 * Maravillas sea el del juego original.
 *
 * Cubre:
 *   A. Geometría: los bordes del anillo, el reparto 5/8 de Maravillas, y que ninguna
 *      aldea existente quede adentro.
 *   B. Fundar adentro programa las 14 oleadas; fundar afuera no programa ninguna.
 *   C. Las oleadas llegan y DESTRUYEN construcciones de verdad.
 *   D. Salen de una aldea natar y no le suman tropas.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_grey_zone.php
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
        // Las filas de `attacks` a las que apuntaban los movimientos se borran con ellos:
        // si no, cada corrida del checker deja catorce huérfanas en el mundo de pruebas.
        $database->query("DELETE a FROM ".TB_PREFIX."attacks a "
            ."INNER JOIN ".TB_PREFIX."movement m ON m.ref = a.id "
            ."WHERE m.`to` = ".(int)$wref." OR m.`from` = ".(int)$wref);
        $database->query("DELETE FROM ".TB_PREFIX."movement WHERE `to` = ".(int)$wref." OR `from` = ".(int)$wref);
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id = ".(int)$wref);
    }
    $created = array();
}
register_shutdown_function('dropScratch');

printf("     %s de radio %d%s · %d oleadas · aviso %.0f h\n",
    GREY_ZONE_INNER_RADIUS > 0 ? 'anillo' : 'disco', GREY_ZONE_OUTER_RADIUS,
    GREY_ZONE_INNER_RADIUS > 0 ? ' (hueco hasta '.GREY_ZONE_INNER_RADIUS.')' : '',
    GREY_ZONE_WAVES, GREY_ZONE_WAVE_DELAY / max(1, (int)INCREASE_SPEED) / 3600);

// --- A. Geometría -----------------------------------------------------------------------
check(greyZoneEnabled(), 'la zona gris está activa');
// La geometría se comprueba contra la forma configurada, sea disco o anillo: así el
// checker sigue valiendo si mañana el mundo nuevo usa el disco oficial de radio 22.
check(greyZoneContainsCoordinates(0, GREY_ZONE_OUTER_RADIUS), 'el borde exterior está dentro');
check(!greyZoneContainsCoordinates(0, GREY_ZONE_OUTER_RADIUS + 1), 'una casilla más allá ya está fuera');
if(GREY_ZONE_INNER_RADIUS > 0) {
    check(!greyZoneContainsCoordinates(0, 0), 'con hueco, el centro exacto queda afuera');
    check(greyZoneContainsCoordinates(0, GREY_ZONE_INNER_RADIUS), 'y el borde interior adentro');
    check(!greyZoneContainsCoordinates(0, GREY_ZONE_INNER_RADIUS - 1), 'y una más acá, afuera');
} else {
    check(greyZoneContainsCoordinates(0, 0), 'siendo un disco lleno, el centro está adentro');
    check(greyZoneContainsCoordinates(1, 1), 'y las casillas pegadas al centro también');
}

// El requisito que define el radio de este mundo: ninguna aldea ya fundada puede quedar
// adentro. Es una comprobación dura porque el mundo local ahora refleja el de producción
// (ver tools/mirror_production_world.php), así que aquí falla lo mismo que allá fallaría.
$inside = array();
$rows = $database->query_return(
    'SELECT v.wref, v.name, w.x, w.y FROM '.TB_PREFIX.'vdata v '
    .'INNER JOIN '.TB_PREFIX.'wdata w ON w.id = v.wref WHERE '.playerAccountSql('v`.`owner')
);
foreach(is_array($rows) ? $rows : array() as $row) {
    if(greyZoneContainsCoordinates((int)$row['x'], (int)$row['y'])) {
        $inside[] = $row['name'].' ('.$row['x'].'|'.$row['y'].')';
    }
}
// Con el radio actual puede haber aldeas anteriores adentro; lo que no puede pasar es que
// se pinten de ceniza, porque su dueño se instaló antes de que la regla existiera.
$painted = array();
foreach(is_array($rows) ? $rows : array() as $row) {
    $village = $database->getVillage((int)$row['wref']);
    if(greyZoneContainsCoordinates((int)$row['x'], (int)$row['y']) && greyZoneAffectsVillage($village)) {
        $painted[] = $row['name'].' ('.$row['x'].'|'.$row['y'].')';
    }
}
echo '[--] aldeas de jugador dentro del disco: '.count($inside)
    .($inside ? ' -> '.implode(', ', $inside) : '').PHP_EOL;
check(empty($painted),
    'ninguna aldea anterior a la zona se pinta de ceniza ('.count($painted).')');
foreach($painted as $offender) {
    echo '        '.$offender.PHP_EOL;
}

// El volcán tiene que entrar entero: es el que da sentido a la zona.
$volcanoOutside = array();
foreach(array_keys(greyZoneVolcanoSprites()) as $key) {
    preg_match('/^(-?\d)(-?\d)$/', $key, $parts);
    $mapX = (int)$parts[1] + GREY_ZONE_VOLCANO_OFFSET_X;
    $mapY = (int)$parts[2] + GREY_ZONE_VOLCANO_OFFSET_Y;
    if(!greyZoneContainsCoordinates($mapX, $mapY)) {
        $volcanoOutside[] = '('.$mapX.'|'.$mapY.')';
    }
}
check(empty($volcanoOutside),
    'el volcán entra completo en la zona ('.count($volcanoOutside).' piezas afuera)');

// Cuántas Maravillas quedan adentro. En el T4 oficial son 5 de 13; con un disco chico son 0,
// y eso es correcto para un mundo ya jugado, donde el premio de terreno no existe porque el
// mapa se generó una sola vez. Informativo, no una falla.
$installer = file_get_contents($root.'/install/include/multihunter.php');
preg_match_all('/case\s+\d+:\s*\$x\s*=\s*(-?\d+);\s*\$y\s*=\s*(-?\d+);/s', $installer, $matches, PREG_SET_ORDER);
$wonderCoords = array();
foreach($matches as $match) {
    $wonderCoords[] = array((int)$match[1], (int)$match[2]);
}
check(count($wonderCoords) === 13,
    'se leyeron las 13 coordenadas de Maravilla del instalador ('.count($wonderCoords).')');
$inRing = 0;
foreach($wonderCoords as $coord) {
    if(greyZoneContainsCoordinates($coord[0], $coord[1])) {
        $inRing++;
    }
}
echo '[--] Maravillas dentro de la zona: '.$inRing.' de 13'
    .($inRing === 5 ? ' (el reparto oficial)' : ' (zona de escenario: el reparto oficial 5/8 pide radio 14 o más)').PHP_EOL;

// --- B. Fundar dentro y fuera -------------------------------------------------------------
// Hace falta una aldea natar de la que salgan las oleadas.
$capital = $database->query_return(
    "SELECT wref FROM ".TB_PREFIX."vdata WHERE owner = ".natarsAccountId()." LIMIT 1"
);
if(!is_array($capital) || !isset($capital[0]['wref'])) {
    $free = $database->query_return("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND fieldtype = 3 LIMIT 1");
    $scratch = (int)$free[0]['id'];
    $created[] = $scratch;
    $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 1 WHERE id = $scratch");
    $database->addVillage($scratch, natarsAccountId(), 'Natars', '1');
    $database->addResourceFields($scratch, $database->getVillageType($scratch));
    $database->addUnits($scratch);
    $database->addTech($scratch);
    $database->addABTech($scratch);
    $database->query("UPDATE ".TB_PREFIX."vdata SET capital = 1 WHERE wref = $scratch");
    $database->setVillageNpcKind($scratch, NPC_KIND_STATIC);
    $capital = array(array('wref' => $scratch));
}
$sourceWref = (int)$capital[0]['wref'];

function freeFieldIn($wantInside) {
    global $database;
    foreach($database->query_return(
        "SELECT id, x, y FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND oasistype = 0 AND fieldtype > 0"
    ) as $field) {
        if(greyZoneContainsCoordinates((int)$field['x'], (int)$field['y']) === $wantInside) {
            return (int)$field['id'];
        }
    }
    return 0;
}

$insideField = freeFieldIn(true);
$outsideField = freeFieldIn(false);
check($insideField > 0 && $outsideField > 0, 'hay casillas libres dentro y fuera para probar');

check(greyZoneScheduleAssault($outsideField) === 0,
    'fundar fuera de la zona no despierta a nadie');
$database->query("DELETE FROM ".TB_PREFIX."movement WHERE `to` = $outsideField");

$attackIdBefore = (int)$database->query_return("SELECT COALESCE(MAX(id),0) AS m FROM ".TB_PREFIX."attacks")[0]['m'];
$launched = greyZoneScheduleAssault($insideField);
check($launched === GREY_ZONE_WAVES, "fundar dentro programa las ".GREY_ZONE_WAVES." oleadas (salieron $launched)");

$waves = $database->query_return(
    "SELECT m.endtime, m.from FROM ".TB_PREFIX."movement m WHERE m.`to` = $insideField AND m.sort_type = 3 ORDER BY m.endtime ASC"
);
check(count($waves) === GREY_ZONE_WAVES, 'quedaron '.count($waves).' movimientos en camino');
if($waves) {
    $lead = ((int)$waves[0]['endtime'] - time()) / 3600;
    check($lead > 1, sprintf('la primera avisa con %.1f h de anticipación', $lead));
    check((int)$waves[0]['from'] === $sourceWref, 'salen de una aldea natar');
}

// --- C. Llegan y destruyen ----------------------------------------------------------------
// Se arma la aldea recién fundada de verdad, para que las catapultas tengan qué romper.
$created[] = $insideField;
$victimOwner = (int)$database->query_return(
    "SELECT owner FROM ".TB_PREFIX."vdata WHERE ".playerAccountSql('owner')." LIMIT 1"
)[0]['owner'];
$database->addVillage($insideField, $victimOwner, 'Aldea de prueba', 0);
$database->addResourceFields($insideField, $database->getVillageType($insideField));
$database->addUnits($insideField);
$database->addTech($insideField);
$database->addABTech($insideField);
$database->query("UPDATE ".TB_PREFIX."fdata SET f1 = 5, f2 = 5, f3 = 5, f4 = 5, f26 = 5, f26t = 15 WHERE vref = $insideField");

$levelsBefore = 0;
$fieldsBefore = $database->getResourceLevel($insideField);
for($slot = 1; $slot <= 40; $slot++) {
    $levelsBefore += isset($fieldsBefore['f'.$slot]) ? (int)$fieldsBefore['f'.$slot] : 0;
}
$garrisonBefore = 0;
$sourceUnits = $database->getUnit($sourceWref);
for($unit = 1; $unit <= 50; $unit++) {
    $garrisonBefore += is_array($sourceUnits) ? (int)$sourceUnits['u'.$unit] : 0;
}

$database->query("UPDATE ".TB_PREFIX."movement SET endtime = ".(time() - 60)." WHERE `to` = $insideField AND proc = 0");
$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$resolve = $reflection->getMethod('sendunitsComplete');
$resolve->setAccessible(true);
@unlink('GameEngine/Prevention/sendunits.txt');
$resolve->invoke($automation);

$fieldsAfter = $database->getResourceLevel($insideField);
$levelsAfter = 0;
for($slot = 1; $slot <= 40; $slot++) {
    $levelsAfter += isset($fieldsAfter['f'.$slot]) ? (int)$fieldsAfter['f'.$slot] : 0;
}
check($levelsAfter < $levelsBefore,
    "las oleadas destruyen construcciones de verdad (niveles totales $levelsBefore -> $levelsAfter)");

$reports = (int)$database->query_return(
    "SELECT COUNT(*) AS n FROM ".TB_PREFIX."ndata WHERE uid = $victimOwner AND toWref = $insideField"
)[0]['n'];
check($reports > 0, "el jugador recibe informes del asalto ($reports)");

// --- D. No engordan a los natars -----------------------------------------------------------
$garrisonAfter = 0;
$sourceUnits = $database->getUnit($sourceWref);
for($unit = 1; $unit <= 50; $unit++) {
    $garrisonAfter += is_array($sourceUnits) ? (int)$sourceUnits['u'.$unit] : 0;
}
check($garrisonAfter === $garrisonBefore,
    'y no le suman tropas a la aldea natar de la que salieron');

// El tinte del mapa: un tooltip obliga a pasar el mouse por la casilla exacta, así que en
// la práctica la zona era invisible. Tiene que verse de un vistazo.
foreach(array('Templates/Map/mapview.tpl', 'Templates/Map/mapviewlarge.tpl') as $template) {
    $source = file_get_contents($root.'/'.$template);
    check(strpos($source, "\$image = 'ashland'") !== false,
        basename($template).' pinta el suelo de la zona con la ceniza del T4 oficial');
    check(strpos($source, 'travian_Travian_4.0_41/img/map/lowRes/tiles.png') !== false,
        basename($template).' usa la spritesheet oficial que ya vive en el repo');
    check(strpos($source, '.tile.greyzone:after') !== false
        && strpos($source, 'pointer-events:none') !== false,
        basename($template).' tiñe los oasis y aldeas de adentro sin robarles el clic');
}


// El volcán del centro, que es de donde sale la ceniza. El arte estaba en el repo sin
// conectar: 18 casillas (5 de ancho por 4 de alto) alrededor de (0|0).
$sprites = greyZoneVolcanoSprites();
check(count($sprites) === 18, 'el volcán tiene sus 18 piezas ('.count($sprites).')');
$cx = GREY_ZONE_VOLCANO_OFFSET_X;
$cy = GREY_ZONE_VOLCANO_OFFSET_Y;
check(greyZoneVolcanoClass($cx, $cy) === 'ash-vulcano00',
    "la pieza central del volcán cae en ($cx|$cy)");
check(greyZoneVolcanoClass($cx + 3, $cy) === '' && greyZoneVolcanoClass($cx, $cy + 2) === '',
    'y no se derrama fuera de su recuadro');
check(!greyZoneIsVolcano(0, 0) && !greyZoneIsVolcano(1, 0),
    'el volcán no tapa la capital natar (0|0) ni a Multihunter (1|0)');
$volcanoPieces = 0;
for($vx = -8; $vx <= 8; $vx++) {
    for($vy = -8; $vy <= 8; $vy++) {
        if(greyZoneIsVolcano($vx, $vy)) {
            $volcanoPieces++;
        }
    }
}
check($volcanoPieces === 18, "el volcán entero cabe en el mapa ($volcanoPieces piezas)");
$positions = array();
foreach($sprites as $key => $pos) {
    $positions[] = $pos[0].','.$pos[1];
}
check(count($positions) === count(array_unique($positions)),
    'ninguna pieza del volcán repite posición en la spritesheet');
$css = greyZoneVolcanoCss();
check(substr_count($css, 'div.ash-vulcano') === 18,
    'se generan las 18 reglas CSS desde el mismo mapa de coordenadas');

// --- E. El terreno que genera el instalador ------------------------------------------------
// El T4 oficial llena la zona gris de 15-croppers y oasis del 50%: es el premio que
// compensa las catorce oleadas. Sin eso la zona es todo castigo y nadie va nunca. Esto no
// afecta a un mundo ya instalado —el terreno se genera una sola vez— pero tiene que estar
// bien para el próximo.
$generator = file_get_contents($root.'/install/include/wdata.php');
check(strpos($generator, 'GameEngine/GreyZone.php') !== false,
    'el generador del mapa comparte la definición de zona gris con el motor');
check(strpos($generator, 'function isgrayfield') === false,
    'y ya no lleva su propia copia del radio, que además nunca se llamaba');
check(strpos($generator, 'greyZoneContainsCoordinates($x, $y)') !== false,
    'el generador consulta la zona al elegir el terreno de cada casilla');
check(strpos($generator, 'function greyZoneTerrain') === false
    && strpos($generator, 'greyZoneTerrain($x, $y)') !== false,
    'y llama al reparto de terreno compartido, con la coordenada, en vez de tener su copia');

// El recuadro del volcán no puede salir sorteado como oasis: dejaría agujeros en el dibujo.
$volcanoOasis = 0;
foreach(array_keys(greyZoneVolcanoSprites()) as $key) {
    preg_match('/^(-?\d)(-?\d)$/', $key, $parts);
    // Las claves son el desplazamiento respecto del CENTRO DEL VOLCÁN; para consultar el
    // terreno hay que llevarlas a coordenadas del mapa.
    $mapX = (int)$parts[1] + GREY_ZONE_VOLCANO_OFFSET_X;
    $mapY = (int)$parts[2] + GREY_ZONE_VOLCANO_OFFSET_Y;
    for($draw = 0; $draw < 30; $draw++) {
        $terrain = greyZoneTerrain($mapX, $mapY);
        if($terrain[1] != 0) {
            $volcanoOasis++;
        }
    }
}
check($volcanoOasis === 0,
    'ninguna casilla del volcán se genera como oasis ('.$volcanoOasis.' de 540 sorteos)');

$sample = array('cropper15' => 0, 'cropper9' => 0, 'valle' => 0, 'oasis50' => 0, 'otro' => 0);
for($draw = 0; $draw < 4000; $draw++) {
    list($fieldType, $oasisType) = greyZoneTerrain();
    if($oasisType > 0) {
        $sample[in_array($oasisType, array(2, 5, 8, 12), true) ? 'oasis50' : 'otro']++;
    } elseif((int)$fieldType === 6) {
        $sample['cropper15']++;
    } elseif((int)$fieldType === 1) {
        $sample['cropper9']++;
    } else {
        $sample['valle']++;
    }
}
printf("     de 4.000 casillas: %d 15-croppers · %d 9-croppers · %d valles · %d oasis del 50%%\n",
    $sample['cropper15'], $sample['cropper9'], $sample['valle'], $sample['oasis50']);
check($sample['otro'] === 0, 'la zona gris no genera oasis que no sean del 50%');
check($sample['cropper15'] > 400, 'genera 15-croppers en cantidad ('.$sample['cropper15'].' de 4.000)');
check($sample['oasis50'] > 900, 'y oasis del 50% en cantidad ('.$sample['oasis50'].' de 4.000)');

// Y las filas de `attacks` que creó esta prueba, hayan quedado referenciadas o no.
$database->query("DELETE FROM ".TB_PREFIX."attacks WHERE id > $attackIdBefore");
$database->query("DELETE FROM ".TB_PREFIX."ndata WHERE uid = $victimOwner AND toWref = $insideField");
dropScratch();

// El motor tiene que haberse llevado las filas de `attacks` de las oleadas que no llegaron
// a resolverse porque la aldea fue arrasada antes.
$orphans = (int)$database->query_return(
    "SELECT COUNT(*) AS n FROM ".TB_PREFIX."attacks a "
    ."LEFT JOIN ".TB_PREFIX."movement m ON m.ref = a.id WHERE m.moveid IS NULL"
)[0]['n'];
check($orphans === 0, "no quedaron filas de `attacks` huérfanas ($orphans)");

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
