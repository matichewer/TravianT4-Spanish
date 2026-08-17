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
 * El gpack que el juego sirve de verdad, deducido de las propias plantillas.
 *
 * En `gpack/` conviven cuatro paquetes y sólo uno se usa: `GP_ENABLE` está en false, asi que
 * la columna `users.gpack` no se mira y las plantillas traen la ruta fija. Mirar los cuatro
 * es peor que inutil: `travian_basic` y `travian_default` definen `div.b13`, que el paquete
 * activo no tiene, asi que una clase rota parece existir.
 */
function activeGpack($root) {
    $counts = array();
    foreach(array('Templates/html.tpl', 'Templates/Map/mapview.tpl', 'Templates/Map/mapviewlarge.tpl') as $file) {
        if(!is_file($root.'/'.$file)) {
            continue;
        }
        preg_match_all('#gpack/([a-zA-Z0-9_.]+)#', file_get_contents($root.'/'.$file), $m);
        foreach($m[1] as $pack) {
            $counts[$pack] = (isset($counts[$pack]) ? $counts[$pack] : 0) + 1;
        }
    }
    if(empty($counts)) {
        return 'travian_Travian_4.0_41';
    }
    arsort($counts);
    return key($counts);
}

/**
 * Las hojas de estilo del gpack activo. Se miran todas las de ese paquete porque una clase
 * puede estar definida en una y pisada en otra, y desde el navegador eso se ve igual que si
 * no estuviera.
 */
function mapStylesheets($root) {
    $sheets = array();
    $stack = array($root.'/gpack/'.activeGpack($root));
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
 * Los dos numeros que eligen el sprite: tamaño por poblacion y relacion con quien mira.
 */
function mapSpriteSlot($pop, $ownerUid, $ownerAlliance, $viewerUid, $viewerAlliance) {
    $pop = (int)$pop;
    $size = $pop >= 500 ? 3 : ($pop >= 250 ? 2 : ($pop >= 100 ? 1 : 0));
    if((int)$ownerUid === (int)$viewerUid && (int)$viewerUid !== 0) {
        return array($size, 0, 'aldea propia');
    }
    if((int)$ownerAlliance === 0) {
        return array($size, 4, 'el dueño no tiene alianza');
    }
    if((int)$ownerAlliance === (int)$viewerAlliance && (int)$viewerAlliance !== 0) {
        return array($size, 3, 'misma alianza que quien mira');
    }
    return array($size, 4, 'otra alianza');
}

/**
 * El nombre de clase que arma UNA plantilla concreta para ese hueco, leido de su codigo.
 *
 * No se reimplementa el ternario: se busca el literal en el fuente. Reimplementarlo fue
 * exactamente el error que dejo pasar el bug de `mapviewlarge.tpl`, donde la rama de "misma
 * alianza" emitia 'b13' sin el sufijo de tribu mientras `mapview.tpl` emitia 'b13-'.$tribe.
 * Leyendo el fuente, una plantilla que se desvie se delata sola.
 */
function mapTemplateClass($source, $size, $rel, $tribe) {
    $literal = 'b'.$size.$rel;
    if(strpos($source, "'".$literal."-'.\$tribe") !== false) {
        return $literal.'-'.$tribe;
    }
    if(strpos($source, "'".$literal."'") !== false) {
        return $literal;
    }
    return null;                                  // esa rama no existe en esta plantilla
}

/**
 * Las plantillas de mapa y su codigo, indexadas por el nombre de la pagina que las usa.
 */
function mapTemplates($root) {
    return array(
        'karte.php (mapa chico)'  => file_get_contents($root.'/Templates/Map/mapview.tpl'),
        'karte2.php (mapa grande)' => file_get_contents($root.'/Templates/Map/mapviewlarge.tpl')
    );
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

    // Se evalua cada aldea en las dos plantillas de mapa y desde los dos puntos de vista que
    // cambian la clase: alguien de otra alianza y un companero de la propia. El mapa chico y
    // el grande no arman el nombre igual, asi que mirar uno solo deja pasar bugs.
    $templates = mapTemplates($root);
    $cache = array();
    $broken = array();
    foreach($villages as $v) {
        $eyes = array(array(0, 0));                       // alguien de afuera
        if((int)$v['alliance'] !== 0) {
            $eyes[] = array(-1, (int)$v['alliance']);     // un companero de alianza
        }
        foreach($eyes as $eye) {
            list($size, $rel, $why) = mapSpriteSlot($v['pop'], $v['owner'], $v['alliance'],
                $eye[0], $eye[1]);
            foreach($templates as $page => $source) {
                $class = mapTemplateClass($source, $size, $rel, $v['tribe']);
                if($class === null) {
                    continue;
                }
                if(!isset($cache[$class])) {
                    $cache[$class] = mapClassRules($class, $sheets, $root);
                }
                $ok = false;
                foreach($cache[$class] as $r) {
                    if($r['imagen'] !== false) {
                        $ok = true;
                    }
                }
                if(!$ok) {
                    $broken[] = sprintf("%-26s (%s|%s) %-22s %-12s pop %-5s %-9s -> %-9s %s",
                        $page, $v['x'], $v['y'], $v['name'], (string)$v['username'], $v['pop'],
                        'rel '.$rel, $class,
                        empty($cache[$class]) ? 'SIN REGLA CSS' : 'IMAGEN AUSENTE');
                }
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
// Las ramas 1 (aliado), 2 (enemigo) y 5 (neutral) son inalcanzables hoy: las plantillas
// inicializan `$friendarray`/`$enemyarray`/`$neutralarray` vacios y nunca los llenan. No es
// que falten sprites, es que no se usan.
list($size, $rel, $why) = mapSpriteSlot($tile['pop'], $tileowner, $targetalliance,
    $viewerUid, $viewerAlliance);
printf("\nPoblacion %d -> tamaño %d · relacion %d (%s)\n", (int)$tile['pop'], $size, $rel, $why);

$sheets = mapStylesheets($root);
$allGood = true;

// Cada pagina de mapa arma el nombre de clase con su propio codigo, y no siempre igual: se
// resuelven las dos por separado, que es lo unico que distingue "la aldea esta mal cargada"
// de "esta plantilla arma mal el nombre".
foreach(mapTemplates($root) as $page => $source) {
    $class = mapTemplateClass($source, $size, $rel, $tribe);
    printf("\n--- %s\n", $page);
    if($class === null) {
        echo "   esta plantilla no tiene rama para este caso\n";
        continue;
    }
    printf("   clase CSS:   %s\n", $class);
    $rules = mapClassRules($class, $sheets, $root);
    if(empty($rules)) {
        printf("   REGLA CSS:   NO EXISTE en ninguna de las %d hojas   <- casilla BLANCA\n",
            count($sheets));
        $allGood = false;
        continue;
    }
    foreach($rules as $entry) {
        printf("   definida en: %s\n", $entry['hoja']);
        printf("   regla:       %s\n", $entry['regla']);
        if($entry['imagen'] === null) {
            echo "   (la regla no trae imagen)\n";
            continue;
        }
        if($entry['imagen'] !== false) {
            printf("   imagen:      %s (%d bytes)  OK\n",
                str_replace($root.'/', '', $entry['imagen']), filesize($entry['imagen']));
            continue;
        }
        // La regla existe pero el archivo no. En Linux esto pasa sobre todo por mayusculas
        // —`ally/nap/` en el CSS contra `ally/Nap/` en el disco— y da 404, no un error de
        // PHP, asi que desde el juego se ve igual que un dato mal cargado.
        printf("   imagen:      %s   <- NO EXISTE, casilla BLANCA\n", $entry['url']);
        $allGood = false;
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
}

echo PHP_EOL.($allGood
    ? "=> La casilla deberia verse bien en las dos paginas.\n"
    : "=> La casilla se ve BLANCA en al menos una pagina.\n");
exit($allGood ? 0 : 1);
