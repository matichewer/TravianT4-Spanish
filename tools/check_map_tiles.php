<?php
/**
 * El mapa tiene que resolver cada casilla contra el mundo que existe de verdad.
 *
 * El fallo que fija: `getBaseID()` calculaba el id de una casilla a partir de WORLD_MAX.
 * La fórmula sólo vale si WORLD_MAX coincide con el radio con el que el instalador generó
 * `wdata`, y cuando no coinciden no falla ruidosamente: devuelve el id de otra casilla, o
 * de ninguna. En el Docker de desarrollo —mundo de ±25 con WORLD_MAX en 100— eso hacía
 * que `getMInfo()` no encontrara nada y el mapa entero saliera en blanco: 399 casillas
 * sin imagen, sin nombre de terreno y con el tooltip en "(|)".
 *
 * Ya había una pista de que el problema era conocido: GeneratorX::procDistanceTime()
 * traía un comentario explicando que no podía usar getBaseID() por este motivo, y
 * resolvía la aldea por coordenada. Se esquivaba en un sitio en vez de arreglarse.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_map_tiles.php
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
include "GeneratorX.php";

global $database, $generator;
if(!isset($generator) || !is_object($generator)) {
    $generator = new GeneratorX();
}

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

$radius = (int)$database->getWorldRadius();
$declared = (int)WORLD_MAX;
printf("     radio real del mundo: %d · WORLD_MAX declarado: %d%s\n",
    $radius, $declared, $radius === $declared ? '' : '   <- no coinciden');

check($radius > 0, 'el mundo tiene casillas generadas');

// El corazón: la casilla que getBaseID() calcula tiene que ser la que está en (x|y).
$sample = $database->query_return(
    "SELECT id, x, y FROM ".TB_PREFIX."wdata ORDER BY RAND() LIMIT 200"
);
$wrong = array();
foreach($sample as $tile) {
    $computed = $generator->getBaseID((int)$tile['x'], (int)$tile['y']);
    if($computed !== (int)$tile['id']) {
        $wrong[] = '('.$tile['x'].'|'.$tile['y'].') -> '.$computed.' en vez de '.$tile['id'];
    }
}
check(empty($wrong),
    'getBaseID() acierta el id de las '.count($sample).' casillas de la muestra');
foreach(array_slice($wrong, 0, 5) as $offender) {
    echo '        '.$offender.PHP_EOL;
}

// Y lo que el mapa hace de verdad: pedir la casilla y encontrarla completa.
$blank = 0;
foreach(array_slice($sample, 0, 50) as $tile) {
    $info = $database->getMInfo($generator->getBaseID((int)$tile['x'], (int)$tile['y']));
    if(!is_array($info) || !isset($info['x']) || $info['x'] === null || (string)$info['x'] === '') {
        $blank++;
    }
}
check($blank === 0,
    'ninguna casilla del mapa vuelve vacía ('.$blank.' de 50): un tooltip "(|)" es este bug');

// Las esquinas y el centro, que son donde la aritmética se rompe primero.
foreach(array(array(0, 0), array(-$radius, $radius), array($radius, -$radius), array($radius, $radius)) as $corner) {
    $row = $database->query_return(
        "SELECT id FROM ".TB_PREFIX."wdata WHERE x = ".(int)$corner[0]." AND y = ".(int)$corner[1]
    );
    if(!is_array($row) || !isset($row[0]['id'])) {
        continue;
    }
    check($generator->getBaseID($corner[0], $corner[1]) === (int)$row[0]['id'],
        'acierta en ('.$corner[0].'|'.$corner[1].')');
}

check(strpos(file_get_contents($root.'/GameEngine/GeneratorX.php'), 'getWorldRadius') !== false,
    'getBaseID() saca el radio del mundo real y no de WORLD_MAX');

// El mundo da la vuelta por los bordes. Sin esto, una vista de mapa cerca de un borde
// pedía casillas inexistentes y salía media pantalla en blanco.
$span = $radius * 2 + 1;
$wrapped = 0;
foreach(array(
    array(0, $radius + 1, 0, -$radius),
    array($radius + 1, 0, -$radius, 0),
    array(0, -$radius - 1, 0, $radius),
    array(-$radius - 1, 0, $radius, 0)
) as $case) {
    if($generator->getBaseID($case[0], $case[1]) === $generator->getBaseID($case[2], $case[3])) {
        $wrapped++;
    }
}
check($wrapped === 4, "pasarse de un borde lleva al borde opuesto ($wrapped de 4 direcciones)");

$offWorld = $database->getMInfo($generator->getBaseID(0, $radius + 3));
check(is_array($offWorld) && isset($offWorld['x']) && (string)$offWorld['x'] !== '',
    'una coordenada fuera de rango devuelve una casilla real en vez de nada');

// Una casilla marcada como ocupada sin aldea ni oasis detrás se dibujaba blanca, porque
// el sprite se arma con la tribu del dueño y no hay dueño.
$orphans = (int)$database->query_return(
    "SELECT COUNT(*) AS n FROM ".TB_PREFIX."wdata w "
    ."LEFT JOIN ".TB_PREFIX."vdata v ON v.wref = w.id "
    ."LEFT JOIN ".TB_PREFIX."odata o ON o.wref = w.id "
    ."WHERE w.occupied = 1 AND v.wref IS NULL AND o.wref IS NULL"
)[0]['n'];
echo '[--] casillas marcadas como ocupadas sin nada detrás en ESTE mundo: '.$orphans.PHP_EOL;

foreach(array('Templates/Map/mapview.tpl', 'Templates/Map/mapviewlarge.tpl') as $template) {
    check(strpos(file_get_contents($root.'/'.$template), '$hasVillage') !== false,
        basename($template).' dibuja el terreno cuando la casilla no tiene aldea detrás');
}

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
