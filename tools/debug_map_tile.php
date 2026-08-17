<?php
/**
 * Por qué una casilla del mapa se ve mal, respondido desde el servidor.
 *
 * El sprite de una aldea en el mapa no es un archivo elegido a mano: `Templates/Map/mapview.tpl`
 * arma el nombre de la clase CSS con cuatro datos —tamaño de la población, relación con quien
 * mira, y tribu del dueño— y si cualquiera de ellos sale distinto de lo esperado el nombre
 * resultante no existe en ninguna hoja de estilos y la casilla se dibuja BLANCA. Como el
 * cálculo es una sola expresión de 1.800 caracteres con ternarios anidados, mirar el HTML no
 * alcanza para saber cuál de los cuatro se rompió.
 *
 * Esto reproduce esa misma expresión con los datos reales de la base, y ademas verifica las
 * dos cosas que el navegador no cuenta: si la clase tiene regla CSS, y si el archivo de
 * imagen al que apunta esa regla existe de verdad en el disco. Un `url(.../nap/d11-3.gif)`
 * apuntando a un directorio que en el disco se llama `Nap/` da 404 en Linux y casilla blanca,
 * y desde el juego se ve igual que un dato mal cargado.
 *
 * Uso:
 *   docker compose exec -T web php /var/www/html/tools/debug_map_tile.php <x> <y> [quien_mira]
 *
 * Ejemplos:
 *   ... debug_map_tile.php -4 7 chewer      la aldea de Che_Bigote, vista por chewer
 *   ... debug_map_tile.php 5 7              idem, sin nadie mirando (visitante)
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

$sweep = in_array('--barrer', $argv, true);
if(!$sweep && ($argc < 3 || !is_numeric($argv[1]) || !is_numeric($argv[2]))) {
    fwrite(STDERR,
        "Explica por que una casilla del mapa se ve como se ve.\n"
        ."  docker compose exec -T web php /var/www/html/tools/debug_map_tile.php <x> <y> [quien_mira]\n"
        ."  docker compose exec -T web php /var/www/html/tools/debug_map_tile.php --barrer\n"
        ."\nEl barrido revisa TODAS las aldeas del mundo y lista las que quedarian en blanco.\n");
    exit(1);
}

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "GeneratorX.php";

global $database, $generator;
if(!isset($generator) || !is_object($generator)) {
    $generator = new GeneratorX();
}

/**
 * Todas las hojas de estilo del gpack. Se miran todas y no solo la del idioma activo porque
 * una clase puede estar definida en una y pisada en otra, y desde el navegador eso se ve
 * igual que si no estuviera.
 */
function mapStylesheets($root) {
    $sheets = array();
    $stack = array($root.'/gpack');
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
    sort($sheets);
    return $sheets;
}

/**
 * Donde esta definida una clase de sprite y si su imagen existe en el disco.
 *
 * Devuelve una entrada por hoja que la define. `imagen => false` es el caso que da casilla
 * blanca sin ningun error visible: la regla existe, el archivo no.
 */
function mapClassRules($class, $sheets, $root) {
    $found = array();
    foreach($sheets as $sheet) {
        if(!preg_match('/div\.'.preg_quote($class, '/').'\s*\{([^}]*)\}/', file_get_contents($sheet), $m)) {
            continue;
        }
        $entry = array('hoja' => str_replace($root.'/', '', $sheet), 'regla' => trim($m[1]),
            'url' => null, 'imagen' => null);
        if(preg_match('/url\(([^)]*)\)/', $m[1], $u)) {
            $entry['url'] = trim($u[1], "'\" ");
            $path = realpath(dirname($sheet).'/'.$entry['url']);
            $entry['imagen'] = ($path !== false && is_file($path)) ? $path : false;
        }
        $found[] = $entry;
    }
    return $found;
}

/**
 * El mismo reparto de digitos que hace `mapview.tpl`: tamaño por poblacion, relacion con
 * quien mira, tribu del dueño.
 */
function mapSpriteClass($pop, $ownerUid, $ownerAlliance, $ownerTribe, $viewerUid, $viewerAlliance) {
    $pop = (int)$pop;
    $size = $pop >= 500 ? 3 : ($pop >= 250 ? 2 : ($pop >= 100 ? 1 : 0));
    if((int)$ownerUid === (int)$viewerUid && (int)$viewerUid !== 0) {
        $rel = 0; $why = 'aldea propia';
    } elseif((int)$ownerAlliance === 0) {
        $rel = 4; $why = 'el dueño no tiene alianza';
    } elseif((int)$ownerAlliance === (int)$viewerAlliance && (int)$viewerAlliance !== 0) {
        $rel = 3; $why = 'misma alianza que quien mira';
    } else {
        $rel = 4; $why = 'otra alianza';
    }
    return array('b'.$size.$rel.'-'.$ownerTribe, $size, $rel, $why);
}

// --- Barrido: todas las aldeas del mundo de una pasada ----------------------------------
if($sweep) {
    $sheets = mapStylesheets($root);
    printf("Revisando todas las aldeas contra %d hojas de estilo del gpack.\n\n", count($sheets));

    $villages = $database->query_return(
        "SELECT w.x, w.y, v.name, v.pop, v.owner, u.username, u.tribe, u.alliance "
        ."FROM ".TB_PREFIX."vdata v "
        ."INNER JOIN ".TB_PREFIX."wdata w ON w.id = v.wref "
        ."LEFT JOIN ".TB_PREFIX."users u ON u.id = v.owner "
        ."ORDER BY w.y DESC, w.x"
    );

    // Se evalua desde dos puntos de vista, porque la relacion cambia la clase: un visitante
    // sin alianza y un aliado. Si alguna de las dos no existe, alguien ve la casilla rota.
    $cache = array();
    $broken = array();
    foreach($villages as $v) {
        foreach(array(array(0, 0, 'visitante'), array(-1, (int)$v['alliance'], 'un aliado')) as $eye) {
            if($eye[1] === 0 && $eye[0] === -1) {
                continue;                       // sin alianza, ya lo cubre el visitante
            }
            list($class, , , $why) = mapSpriteClass($v['pop'], $v['owner'], $v['alliance'],
                $v['tribe'], $eye[0], $eye[1]);
            if(!isset($cache[$class])) {
                $cache[$class] = mapClassRules($class, $sheets, $root);
            }
            $rules = $cache[$class];
            $ok = false;
            foreach($rules as $r) {
                if($r['imagen'] !== false) {
                    $ok = true;
                }
            }
            if(!$ok) {
                $broken[] = sprintf("(%s|%s) %-28s %-12s pop %-5s -> %-8s %s",
                    $v['x'], $v['y'], $v['name'], (string)$v['username'], $v['pop'], $class,
                    empty($rules) ? 'SIN REGLA CSS' : 'IMAGEN AUSENTE ('.$rules[0]['url'].')');
            }
        }
    }

    printf("%d aldeas revisadas.\n", count($villages));
    if(empty($broken)) {
        echo "Ninguna se veria en blanco: todas las clases tienen regla e imagen.\n";
        exit(0);
    }
    echo "\nSe verian en blanco:\n";
    foreach(array_unique($broken) as $line) {
        echo '  '.$line.PHP_EOL;
    }
    exit(1);
}

$x = (int)$argv[1];
$y = (int)$argv[2];
$viewer = isset($argv[3]) ? $argv[3] : null;

// --- Quien mira -------------------------------------------------------------------------
// La relación con el que mira decide el segundo digito de la clase, asi que sin esto no se
// puede reproducir lo que ve un jugador concreto.
$viewerUid = 0;
$viewerAlliance = 0;
if($viewer !== null) {
    $rows = $database->query_return(
        "SELECT id, username, alliance, tribe FROM ".TB_PREFIX."users WHERE username = '"
        .mysql_real_escape_string($viewer)."' LIMIT 1"
    );
    if(!is_array($rows) || !count($rows)) {
        fwrite(STDERR, "No existe el usuario '$viewer'.\n");
        exit(1);
    }
    $viewerUid = (int)$rows[0]['id'];
    $viewerAlliance = (int)$rows[0]['alliance'];
    printf("Mira: %s (uid %d, alianza %d)\n\n", $rows[0]['username'], $viewerUid, $viewerAlliance);
} else {
    echo "Mira: nadie (visitante sin sesion)\n\n";
}

// --- La casilla -------------------------------------------------------------------------
$tile = $database->getMInfo($generator->getBaseID($x, $y));
if(!is_array($tile) || !isset($tile['id'])) {
    fwrite(STDERR, "No hay ninguna casilla en ($x|$y).\n");
    exit(1);
}

printf("Casilla (%d|%d)  wref=%s\n", $x, $y, $tile['id']);
foreach(array('occupied', 'fieldtype', 'oasistype', 'image', 'wref', 'owner', 'pop', 'name') as $field) {
    printf("   %-10s %s\n", $field, isset($tile[$field]) && $tile[$field] !== null
        ? var_export($tile[$field], true) : '(null)');
}

// Misma condicion que la plantilla: sin fila en vdata detras no hay aldea que dibujar.
$hasVillage = isset($tile['wref']) && $tile['wref'] !== null;
$isVillage = ((int)$tile['occupied'] === 1 && (int)$tile['fieldtype'] > 0 && $hasVillage);

if(!$isVillage) {
    echo "\n=> No es una aldea. Se dibuja el terreno: '".$tile['image']."'\n";
    if((int)$tile['occupied'] === 1 && !$hasVillage) {
        echo "   OJO: marcada como ocupada sin fila en vdata (campo no liberado al borrar la aldea).\n";
    }
    exit(0);
}

// --- El dueño ---------------------------------------------------------------------------
$tileowner = (int)$tile['owner'];
$targetalliance = $database->getUserField($tileowner, "alliance", 0);
$tribe = $database->getUserField($tileowner, "tribe", 0);
$username = $database->getUserField($tileowner, "username", 0);

echo "\nDueño (tal como lo lee la plantilla, con getUserField):\n";
printf("   uid        %d\n", $tileowner);
printf("   username   %s\n", var_export($username, true));
printf("   tribe      %s%s\n", var_export($tribe, true),
    ((string)$tribe === '' || (int)$tribe <= 0) ? '   <- VACIA: la clase queda sin sufijo y no existe' : '');
printf("   alliance   %s\n", var_export($targetalliance, true));

$raw = $database->query_return(
    "SELECT id, username, tribe, alliance FROM ".TB_PREFIX."users WHERE id = ".$tileowner." LIMIT 1"
);
if(!is_array($raw) || !count($raw)) {
    echo "   OJO: no hay fila en users con id $tileowner. La aldea quedo huerfana.\n";
} else {
    printf("   (fila cruda: tribe=%s alliance=%s)\n", $raw[0]['tribe'], $raw[0]['alliance']);
}

// --- La clase ---------------------------------------------------------------------------
// Las ramas 1 (aliado), 2 (enemigo) y 5 (neutral) son inalcanzables hoy: `mapview.tpl`
// inicializa `$friendarray`/`$enemyarray`/`$neutralarray` vacios y nunca los llena. No es
// que falten sprites, es que no se usan.
list($class, $size, $rel, $why) = mapSpriteClass($tile['pop'], $tileowner, $targetalliance,
    $tribe, $viewerUid, $viewerAlliance);
printf("\nPoblacion %d -> tamaño %d · relacion %d (%s)\n", (int)$tile['pop'], $size, $rel, $why);
printf("=> clase CSS:  %s\n", $class);

// --- Y si esa clase existe --------------------------------------------------------------
$sheets = mapStylesheets($root);
$rules = mapClassRules($class, $sheets, $root);

if(empty($rules)) {
    printf("   REGLA CSS:   NO EXISTE en ninguna de las %d hojas   <- casilla BLANCA\n", count($sheets));
    exit(1);
}

$usable = false;
foreach($rules as $entry) {
    printf("\n   definida en: %s\n", $entry['hoja']);
    printf("   regla:       %s\n", $entry['regla']);
    if($entry['imagen'] === null) {
        echo "   (la regla no trae imagen)\n";
        $usable = true;
        continue;
    }
    if($entry['imagen'] !== false) {
        printf("   imagen:      %s (%d bytes)  OK\n",
            str_replace($root.'/', '', $entry['imagen']), filesize($entry['imagen']));
        $usable = true;
        continue;
    }
    // El caso que importa: la regla existe pero el archivo no. En Linux esto pasa sobre todo
    // por mayusculas —`ally/nap/` en el CSS contra `ally/Nap/` en el disco— y da 404, no un
    // error de PHP, asi que desde el juego se ve igual que un dato mal cargado.
    $wanted = $root.'/gpack/'.$entry['url'];
    printf("   imagen:      %s   <- NO EXISTE, casilla BLANCA\n", $entry['url']);
    $dir = dirname(dirname($root.'/'.$entry['hoja']).'/'.$entry['url']);
    $parent = dirname($dir);
    if(is_dir($parent)) {
        foreach((array)scandir($parent) as $sibling) {
            if($sibling !== '.' && $sibling !== '..' && $sibling !== basename($dir)
                && strcasecmp($sibling, basename($dir)) === 0) {
                printf("   pero SI existe '%s/' (difiere en mayusculas del '%s/' que pide el CSS)\n",
                    $sibling, basename($dir));
            }
        }
    }
}

echo PHP_EOL.($usable
    ? "=> La casilla deberia verse bien.\n"
    : "=> La casilla se ve BLANCA: ninguna regla resuelve a una imagen existente.\n");
exit($usable ? 0 : 1);
