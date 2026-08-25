<?php
/**
 * El Tesoro (gid 27): el edificio y su pantalla.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_treasury_building.php
 *
 * El nombre. En español el edificio se llama **Tesoro**, no "Tesorería": es el nombre del
 * cliente oficial y el que usa el propio soporte de Travian ("los planos de construcción
 * se almacenan en un Tesoro de nivel 10"). Acá se llamaba "Tesorería" en nueve archivos,
 * incluido `buildingDisplayName()`, que es el que ven los informes de catapulta y la lista
 * de objetivos del punto de reunión. Y como cambió el género, tuvo que salir de la lista
 * de nombres femeninos o los informes dirían "Tesoro destruida".
 *
 * La pantalla arrastraba media docena de cosas rotas que este checker no deja volver:
 * una inyección SQL en `?show=`, la palabra húngara "Kincstár" a la vista, un
 * ordenamiento por distancia geodésica terrestre (haversine) aplicado a coordenadas del
 * mapa y usado además como clave de array —así que dos artefactos equidistantes se
 * pisaban—, y una lista escrita a mano por tipo a la que ya se le había perdido uno.
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

$failures = 0;
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        $failures++;
        echo '[FALLA] '.$message.PHP_EOL;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

// =====================================================================================
section('A. El nombre');
// =====================================================================================

check(buildingDisplayName(27) === 'Tesoro',
    'el nombre canónico del gid 27 es "Tesoro" y da "'.buildingDisplayName(27).'"');
check(buildingNameIsFeminine(27) === false,
    '"Tesoro" es masculino: si sigue en la lista de femeninos el informe dice "Tesoro destruida"');
check(strpos(buildingDamageSentence(27, 5, 0), 'destruido') !== false,
    'y la frase de daño concuerda en masculino');

// Ni una "Tesorería" suelta en ningún archivo que vea el jugador.
$stale = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach($iterator as $file) {
    if(!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    if(strpos($relative, '.git/') === 0 || strpos($relative, 'gpack/') === 0) {
        continue;
    }
    $extension = strtolower($file->getExtension());
    if(!in_array($extension, array('php', 'tpl'), true)) {
        continue;
    }
    // Este archivo habla del cambio de nombre, así que se nombra a sí mismo.
    if($relative === 'tools/check_treasury_building.php') {
        continue;
    }
    // Se ignoran las líneas de comentario: varios archivos explican POR QUÉ el nombre
    // cambió y tienen que poder nombrar el nombre viejo.
    foreach(preg_split('/\r?\n/', (string)file_get_contents($path)) as $line) {
        $trimmed = ltrim($line);
        if($trimmed === '' || $trimmed[0] === '*' || strpos($trimmed, '//') === 0
            || strpos($trimmed, '/*') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        if(preg_match('/tesorer[íi]a/iu', $line)) {
            $stale[] = $relative;
            break;
        }
    }
}
check(count($stale) === 0,
    'quedó "Tesorería" en: '.implode(', ', $stale));

// =====================================================================================
section('B. El edificio');
// =====================================================================================

global $bid27;
check(count($bid27) === 20, 'el Tesoro tiene 20 niveles y tiene '.count($bid27));
check((int)$bid27[1]['wood'] === 2880 && (int)$bid27[1]['clay'] === 2740
    && (int)$bid27[1]['iron'] === 2580 && (int)$bid27[1]['crop'] === 990,
    'el costo del nivel 1 es el oficial (2880/2740/2580/990)');
check((int)$bid27[1]['pop'] === 4, 'y consume 4 de cereal en el nivel 1');
// La tabla entera contra la fórmula oficial: base x 1,26 por nivel, redondeado a
// múltiplos de 5. Los niveles 11 y 12 tenían la madera con el primer dígito comido
// (9045 en vez de 29045, y 6600 en vez de 36600), así que subir el Tesoro del 10 al 11
// costaba MENOS madera que subirlo del 9 al 10.
$base = array('wood' => 2880, 'clay' => 2740, 'iron' => 2580, 'crop' => 990);
foreach($base as $resource => $start) {
    for($level = 1; $level <= 20; $level++) {
        $expected = round($start * pow(1.26, $level - 1) / 5) * 5;
        check((int)$bid27[$level][$resource] === (int)$expected,
            'Tesoro nivel '.$level.', '.$resource.': se esperaba '.$expected
                .' y la tabla dice '.$bid27[$level][$resource]);
    }
}
for($level = 2; $level <= 20; $level++) {
    foreach(array('wood', 'clay', 'iron', 'crop', 'time') as $column) {
        check($bid27[$level][$column] > $bid27[$level - 1][$column],
            'el '.$column.' del nivel '.$level.' no puede ser menor que el del nivel anterior');
    }
}

$buildingSource = file_get_contents($root.'/GameEngine/Building.php');
check(preg_match('/case 27:\s*if\(\$this->getTypeLevel\(15\) >= 10\)/', $buildingSource) === 1,
    'el Tesoro pide edificio principal 10, como en el oficial');
check(preg_match('/\$singlePerVillage = array\((.*?)\);/s', $buildingSource, $m) === 1
    && in_array('27', array_map('trim', explode(',', preg_replace('/\s+/', '', $m[1]))), true),
    'y es único por aldea');

// El nivel que pide cada tamaño de artefacto sale de una sola función.
check(artefactTreasuryRequirement(1) === 10 && artefactTreasuryRequirement(2) === 20,
    'los umbrales 10/20 salen de artefactTreasuryRequirement()');
$helpSource = file_get_contents($root.'/Templates/Build/build_level_help.tpl');
check(preg_match("/\\\$buildingHelpType === 'treasury'.*?1 =>.*?10 =>.*?20 =>/s", $helpSource) === 1,
    'la ayuda de niveles del Tesoro nombra los tres hitos: 1, 10 y 20');

// =====================================================================================
section('C. La pantalla');
// =====================================================================================

$templates = array();
foreach(array('27', '27_1', '27_2', '27_3', '27_head', '27_list', '27_rows', '27_show', '27_menu') as $name) {
    $path = $root.'/Templates/Build/'.$name.'.tpl';
    check(is_file($path), 'existe la plantilla '.$name.'.tpl');
    $templates[$name] = is_file($path) ? file_get_contents($path) : '';
}
$screen = implode("\n", $templates);

/** El código de las plantillas sin sus comentarios: los comentarios explican qué se sacó. */
function withoutComments($source) {
    $stripped = '';
    foreach(preg_split('/\r?\n/', (string)$source) as $line) {
        $trimmed = ltrim($line);
        if($trimmed === '' || $trimmed[0] === '*' || strpos($trimmed, '//') === 0
            || strpos($trimmed, '/*') === 0) {
            continue;
        }
        $stripped .= $line."\n";
    }
    return $stripped;
}
$screenCode = withoutComments($screen);

// La inyección SQL. `$_GET['show']` entraba crudo en `WHERE id = ...`.
check(strpos($templates['27_show'], "ctype_digit((string)\$_GET['show'])") !== false,
    'la ficha valida que ?show= sea un número antes de consultar');
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(preg_match('/function getArtefactDetails\(\$id\) \{\s*\$id = \(int\)\$id;/', $dbSource) === 1,
    'y getArtefactDetails() castea a entero, que es la otra mitad del arreglo');

// Restos de la versión anterior.
check(stripos($screenCode, 'Kincstár') === false,
    'no puede quedar la palabra húngara "Kincstár" a la vista del jugador');
check(stripos($screenCode, 'haversine') === false,
    'la distancia entre aldeas no se calcula con una fórmula geodésica terrestre');
check(strpos($screenCode, 'natarSettlementDistance(') !== false,
    'sino con la distancia del mapa, que respeta el borde que da la vuelta');
check(stripos($screenCode, 'Aldea con inscripciones') === false,
    'y no queda la traducción sin sentido del alcance del artefacto');
check(strpos($screenCode, '"Inactive"') === false && strpos($screenCode, '"Active"') === false,
    'el estado del artefacto está en español');
check(strpos($screenCode, 'artefact.image-') === false,
    'la clase de la ilustración no puede llevar un punto literal: era una clase inexistente');

// Los nombres y efectos salen del catálogo, no de las columnas del INSERT.
check(strpos($templates['27_rows'], 'artefactDisplayName(') !== false,
    'el nombre del artefacto sale del catálogo');
check(strpos($templates['27_rows'], "\$row['name']") === false,
    'y no de la columna `name`, que guarda lo que escribió quien lo sembró');

// La lista recorre el catálogo: un tipo nuevo aparece solo.
check(strpos($templates['27_list'], 'artefactTypeCatalog()') !== false,
    'la lista de artefactos recorre el catálogo en vez de tener un bloque por tipo');
check(substr_count($templates['27_2'], 'mysql_query') === 0
    && substr_count($templates['27_3'], 'mysql_query') === 0,
    'las pestañas ya no arman sus propias consultas');

// El encabezado es uno solo: build.php entra directo a 27_2/27_3 cuando llega ?t=.
foreach(array('27', '27_2', '27_3') as $name) {
    check(strpos($templates[$name], '27_head.tpl') !== false,
        $name.'.tpl usa el encabezado compartido');
}
check(substr_count($screenCode, 'titleInHeader') === 1,
    'y el título del edificio está escrito una sola vez en todo el Tesoro');

// El retardo que anuncia la pantalla sale de la constante, no de un literal.
check(strpos($templates['27_head'], 'artefactActivationDelay(SPEED)') !== false,
    'el retardo anunciado sale de la velocidad del mundo');
check(strpos(withoutComments($templates['27_head']), '24 horas') === false,
    'y no está escrito a mano: este mundo es x'.SPEED.', o sea '
        .round(artefactActivationDelay(SPEED) / 3600).' horas');

// Todas las salidas al HTML están escapadas.
check(preg_match('/echo \$artefact\[/', $templates['27_show']) !== 1,
    'la ficha no vuelca campos del artefacto sin escapar');
check(substr_count($templates['27_rows'], 'htmlspecialchars(') >= 4,
    'las celdas escapan el texto que vuelcan');

// =====================================================================================
echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Tesoro: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
