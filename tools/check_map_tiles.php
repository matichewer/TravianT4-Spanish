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

// El sprite de una aldea es una clase CSS armada con población, relación y tribu. Si el
// nombre resultante no tiene regla, o la regla apunta a un archivo que no existe, la casilla
// sale BLANCA y no hay ningún error: ni PHP ni el navegador se quejan.
// Sólo el gpack que el juego sirve de verdad. En `gpack/` conviven cuatro paquetes y
// `GP_ENABLE` está en false, así que las plantillas traen la ruta fija del activo. Mirar los
// cuatro es peor que inútil: `travian_basic` y `travian_default` definen `div.b13`, que el
// paquete activo NO tiene, así que una clase rota pasaba por buena.
$activePack = 'travian_Travian_4.0_41';
$packCounts = array();
preg_match_all('#gpack/([a-zA-Z0-9_.]+)#',
    file_get_contents($root.'/Templates/Map/mapview.tpl')
    .file_get_contents($root.'/Templates/Map/mapviewlarge.tpl'), $packMatches);
foreach($packMatches[1] as $pack) {
    $packCounts[$pack] = (isset($packCounts[$pack]) ? $packCounts[$pack] : 0) + 1;
}
if($packCounts) {
    arsort($packCounts);
    $activePack = key($packCounts);
}
echo '     gpack activo: '.$activePack.PHP_EOL;

$sheets = array();
$stack = array($root.'/gpack/'.$activePack);
while($stack) {
    $dir = array_pop($stack);
    foreach((array)@scandir($dir) as $entry) {
        if($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir.'/'.$entry;
        if(is_dir($path)) {
            $stack[] = $path;
        } elseif(substr($entry, -4) === '.css') {
            $sheets[] = $path;
        }
    }
}
$sources = array();
foreach($sheets as $sheet) {
    $sources[$sheet] = file_get_contents($sheet);
}

$villages = $database->query_return(
    "SELECT w.x, w.y, v.name, v.pop, u.username, u.tribe, u.alliance "
    ."FROM ".TB_PREFIX."vdata v "
    ."INNER JOIN ".TB_PREFIX."wdata w ON w.id = v.wref "
    ."LEFT JOIN ".TB_PREFIX."users u ON u.id = v.owner"
);

// El nombre de clase se LEE del código de cada plantilla en vez de reimplementarlo acá. La
// copia era el bug: `mapviewlarge.tpl` emitía 'b13' sin el sufijo de tribu donde
// `mapview.tpl` emitía 'b13-'.$tribe, y como los sprites sin tribu sólo existen para las
// relaciones 1, 2 y 5 —nunca para la 3, la de la propia alianza— la aldea de un compañero
// de alianza salía como una casilla beige vacía, pero sólo en karte2.php. Un checker que
// repite la fórmula da por buenas las dos plantillas por igual y no ve la diferencia.
$templates = array(
    'karte.php'  => file_get_contents($root.'/Templates/Map/mapview.tpl'),
    'karte2.php' => file_get_contents($root.'/Templates/Map/mapviewlarge.tpl')
);

$blankTiles = array();
$resolved = array();
foreach($villages as $village) {
    $pop = (int)$village['pop'];
    $size = $pop >= 500 ? 3 : ($pop >= 250 ? 2 : ($pop >= 100 ? 1 : 0));
    // Las relaciones que las plantillas pueden producir hoy: la propia alianza (3) y
    // cualquier otro caso (4). Las ramas 1, 2 y 5 son inalcanzables porque
    // $friendarray/$enemyarray/$neutralarray se inicializan vacíos y nunca se llenan.
    foreach(array(3, 4) as $relation) {
        if($relation === 3 && (int)$village['alliance'] === 0) {
            continue;
        }
        foreach($templates as $page => $template) {
            $literal = 'b'.$size.$relation;
            if(strpos($template, "'".$literal."-'.\$tribe") !== false) {
                $class = $literal.'-'.$village['tribe'];
            } elseif(strpos($template, "'".$literal."'") !== false) {
                $class = $literal;
            } else {
                continue;
            }
            if(!isset($resolved[$class])) {
                $ok = false;
                foreach($sources as $sheet => $source) {
                    if(!preg_match('/div\.'.preg_quote($class, '/').'\s*\{([^}]*)\}/', $source, $m)) {
                        continue;
                    }
                    if(!preg_match('/url\(([^)]*)\)/', $m[1], $u)) {
                        $ok = true;
                        break;
                    }
                    $image = realpath(dirname($sheet).'/'.trim($u[1], "'\" "));
                    if($image !== false && is_file($image)) {
                        $ok = true;
                        break;
                    }
                }
                $resolved[$class] = $ok;
            }
            if(!$resolved[$class]) {
                $blankTiles[] = $page.' ('.$village['x'].'|'.$village['y'].') '
                    .$village['name'].' de '.$village['username'].' -> '.$class;
            }
        }
    }
}
check(empty($blankTiles),
    'las '.count($villages).' aldeas del mundo resuelven a un sprite que existe en las dos páginas de mapa');
foreach(array_slice(array_unique($blankTiles), 0, 8) as $offender) {
    echo '        '.$offender.PHP_EOL;
}

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
