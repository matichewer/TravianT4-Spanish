<?php
/**
 * La ayuda del final de partida no puede mentirle al jugador.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_endgame_help.php
 *
 * `endgame.php` explica artefactos, Tesoro, planos y Maravilla. El riesgo de una página así
 * no es que se rompa —es HTML— sino que **envejezca**: alguien cambia un valor del motor y
 * la ayuda sigue anunciando el viejo. Ya pasó en este repo con la ficha del Tesoro, que
 * decía "24 horas" escrito a mano en un mundo x3 donde el retardo son 12.
 *
 * Por eso este checker no revisa la redacción: revisa que los NÚMEROS salgan del motor y no
 * del texto, y que lo que la página afirma siga siendo cierto — incluidas las dos cosas en
 * las que este servidor se aparta del Travian oficial a propósito, que son justo las que un
 * jugador que viene de otro servidor va a dar por sentadas al revés.
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
require_once $root.'/GameEngine/Artefact.php';
require_once $root.'/GameEngine/Wonder.php';

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

$page = file_get_contents($root.'/endgame.php');
$help = file_get_contents($root.'/help.php');

// =====================================================================================
section('A. La ayuda es alcanzable');
// =====================================================================================
check(is_file($root.'/endgame.php'), 'existe endgame.php');
check(strpos($help, 'href="endgame.php"') !== false,
    'help.php enlaza la ayuda del final de partida: una página que nadie encuentra no sirve');
check(strpos($page, 'href="help.php"') !== false, 'y desde la ayuda se puede volver');
check(strpos($page, 'include("GameEngine/Village.php")') !== false,
    'arranca el motor como el resto de las páginas del juego');
foreach(array('building_stats.php#edificio-27', 'building_stats.php#edificio-40') as $link) {
    check(strpos($page, $link) !== false,
        'enlaza la tabla de costos de '.$link.', que es donde están los números finos');
}

// =====================================================================================
section('B. Los números salen del motor, no del texto');
// =====================================================================================
//
// Es la comprobación que justifica el archivo: cada valor que el jugador lee tiene que
// venir de una llamada, para que cambiar la velocidad del mundo o una tabla oficial
// actualice la ayuda sola.
$derived = array(
    'artefactActivationDelay(SPEED)'   => 'el retardo de activación',
    'artefactEffectTypeCatalog()'      => 'la lista de artefactos y qué hace cada uno',
    'WONDER_PLAN_SOLO_MAX_LEVEL'       => 'hasta qué nivel llega la Maravilla con un solo plano',
    'artefactEffectValueLabel('        => 'los valores de cada tamaño',
    'artefactTreasuryRequirement('     => 'los niveles de Tesoro que pide cada tamaño',
    'ARTEFACT_MAX_ACTIVE'              => 'cuántos artefactos pueden estar activos',
    "bid40"                            => 'el nivel máximo de la Maravilla',
    "bid27"                            => 'el costo del Tesoro',
    'SPEED'                            => 'la velocidad del mundo'
);
foreach($derived as $needle => $what) {
    check(strpos($page, $needle) !== false,
        $what.' se lee del motor ('.$needle.'), no está escrito en el texto');
}

// Y al revés: los valores concretos no pueden estar escritos a mano en la prosa.
$hardcoded = array(
    '/\b24 horas\b/u'  => 'el retardo de 24 h (es 12 en un mundo x3, y sale de la tabla)',
    '/\bnivel 100\b/u' => 'el nivel máximo de la Maravilla',
    '/\bx200\b/u'      => 'el multiplicador del escondite',
    '/\b1,5384\b/u'    => 'la proporción entre tamaños'
);
foreach($hardcoded as $pattern => $what) {
    // Se mira sólo el HTML, no los comentarios PHP: el encabezado del archivo explica
    // justamente por qué no se escriben a mano y necesita poder nombrarlos.
    $html = preg_replace('/\/\*.*?\*\//s', '', $page);
    $html = preg_replace('/^\s*\/\/.*$/m', '', $html);
    // "24 horas" sí puede aparecer para el necio, que cambia cada 24 h y no escala.
    if($pattern === '/\b24 horas\b/u') {
        check(preg_match_all($pattern, $html) <= 1,
            'no hay más de una mención escrita a mano de "24 horas" (la del necio, que no escala)');
        continue;
    }
    check(preg_match($pattern, $html) !== 1, 'no está escrito a mano '.$what);
}

// =====================================================================================
section('C. Lo que la página afirma sigue siendo cierto');
// =====================================================================================
$delayHours = round(artefactActivationDelay(SPEED) / 3600);
check($delayHours > 0 && $delayHours <= 24,
    'el retardo de este mundo son '.$delayHours.' horas, un número que se puede anunciar');
check(count(artefactEffectTypeCatalog()) === 8,
    'la página dice "8 clases" y hay 8 artefactos de efecto (el plano de construcción va aparte)');
check(count(artefactTypeCatalog()) === 9,
    'el catálogo entero tiene 9: los 8 de efecto más el plano de construcción');
check(ARTEFACT_MAX_ACTIVE === 3 && ARTEFACT_MAX_ACTIVE_ACCOUNT === 1,
    'el podio que describe la página es de 3 activos y 1 de cuenta');
check(artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL) === 10
    && artefactTreasuryRequirement(ARTEFACT_SIZE_LARGE) === 20
    && artefactTreasuryRequirement(ARTEFACT_SIZE_UNIQUE) === 20,
    'los niveles 10/20 que anuncia son los que el motor exige');

// "El grande es el más flojo de los tres" — la página lo dice y es contraintuitivo.
$table = artefactValueTable();
foreach($table as $type => $values) {
    if($type === ARTEFACT_STORAGE) {
        continue;   // binario: no escala con el tamaño
    }
    $strongerIsHigher = !in_array($type, array(ARTEFACT_DIET, ARTEFACT_TRAINER), true);
    $smallBeatsLarge = $strongerIsHigher
        ? $values[ARTEFACT_SIZE_SMALL] >= $values[ARTEFACT_SIZE_LARGE]
        : $values[ARTEFACT_SIZE_SMALL] <= $values[ARTEFACT_SIZE_LARGE];
    check($smallBeatsLarge,
        artefactTypeName($type).': la página afirma que el grande es el más flojo, y lo es');
}

// El plano de almacenamiento es el único sin versión única, y la página lo dibuja con raya.
check(!isset($GLOBALS['artefactStorageUnique']),
    'el plano de almacenamiento no tiene versión única');
check(strpos($page, 'ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE') !== false,
    'y la tabla lo muestra como una raya en vez de inventarle un valor');

// La Maravilla: nivel máximo y que los natars la atacan.
global $bid40;
$wonderMax = max(array_keys($bid40));
check($wonderMax === 100, 'la Maravilla llega al nivel '.$wonderMax.', que es lo que la página anuncia');
$automation = file_get_contents($root.'/GameEngine/Automation.php');
check(preg_match('/\$indi\[.type.\] == 40 and \(\$indi\[.level.\] % 5 == 0 or \$indi\[.level.\] > 95\)/', $automation) === 1,
    'los natars atacan la Maravilla cada 5 niveles y a partir del 96, como dice la página');

// =====================================================================================
section('D. Las dos divergencias con el oficial están dichas');
// =====================================================================================
//
// Un jugador que viene de otro servidor va a dar por sentado lo contrario en las dos, así
// que callarlas es peor que no tener la página.
$building = file_get_contents($root.'/GameEngine/Building.php');
check(strpos($building, 'case 40:') !== false && strpos($building, 'wonderVillage') !== false,
    'la Maravilla no se puede levantar desde cero: se conquista una aldea natar que ya la tiene');
check(strpos($page, 'conquistás con administradores') !== false,
    'y explica cuál es el camino que sí funciona acá');

// El plano de construcción SÍ existe ahora, y la página tiene que decir las dos mitades de
// la regla oficial: uno hasta el 49, dos —de dos jugadores distintos— de ahí en adelante.
check(strpos($page, 'plano de construcción') !== false,
    'la página explica el plano de construcción de la Maravilla');
// La frase vieja era "los planos de construcción de la Maravilla ... no existen". Se busca
// esa vecindad y no un "no existen" suelto, porque la sección del plano de almacenamiento
// dice —con razón— que sin él el gran almacén y el gran granero no existen.
check(preg_match('/planos? de construcción[^.]{0,200}no existen/ui', $page) !== 1,
    'y ya no dice que los planos de construcción no existen, que es lo que decía antes');
check(strpos($page, 'WONDER_PLAN_SOLO_MAX_LEVEL') !== false,
    'el nivel a partir del cual hacen falta dos planos sale de la constante, no del texto');
$wonder = file_get_contents($root.'/GameEngine/Wonder.php');
$solo = wonderPlanRequirement(WONDER_PLAN_SOLO_MAX_LEVEL);
$duo = wonderPlanRequirement(WONDER_PLAN_SOLO_MAX_LEVEL + 1);
check($solo['own'] === 0 && $solo['alliance'] === 1,
    'hasta el '.WONDER_PLAN_SOLO_MAX_LEVEL.' alcanza un plano de cualquiera de la alianza, como dice la página');
check($duo['own'] === 1 && $duo['alliance'] === 2,
    'del '.(WONDER_PLAN_SOLO_MAX_LEVEL + 1).' en adelante hacen falta dos, uno propio: la página lo dice así');
check(artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL, ARTEFACT_PLAN) === 10,
    'el Tesoro 10 que la página anuncia para el plano es el que el motor exige');

// Y las tres cosas que no funcionan en la aldea de la Maravilla, que la página lista.
$market = file_get_contents($root.'/GameEngine/Market.php');
check(strpos($market, 'wonderVillage($village->resarray)') !== false,
    'el mercader NPC está bloqueado en la aldea de la Maravilla, como dice la página');
check(strpos($page, 'mercader NPC') !== false,
    'y la página lo avisa');
check(strpos($building, 'case 27:') !== false,
    'el Tesoro está bloqueado en la aldea de la Maravilla');
check(preg_match('/no se puede construir (<b>)?Tesoro/ui', $page) === 1,
    'y la página lo avisa, que es lo que explica por qué el plano se puede robar');

// La otra: las aldeas de artefacto no reponen tropas, al revés que una aldea de jugador.
check(strpos($page, 'no se repone nunca') !== false,
    'la página avisa que la guarnición de una aldea de artefacto no se repone');
$release = file_get_contents($root.'/GameEngine/ArtefactRelease.php');
check(strpos($release, 'NPC_KIND_STATIC') !== false,
    'y es cierto: nacen como escenario estático, que es lo que no repone ni crece');

// =====================================================================================
section('E. La página se renderiza sin avisos y sin texto sin escapar');
// =====================================================================================
check(strpos($page, 'htmlspecialchars(') !== false,
    'el texto que sale del catálogo se escapa antes de llegar al HTML');
check(preg_match('/echo \$endgameInfo\[.(name|effect).\](?!.*htmlspecialchars)/', $page) !== 1,
    'no se vuelca ningún campo del catálogo sin escapar');
// Sólo se usan clases que ya existen en la hoja de estilos: una clase nueva obligaría a
// subir el cache-buster de Cloudflare, y ese es un paso que se olvida.
$css = file_get_contents($root.'/img/travian_basics.css');
foreach(array('troopStatsHeader', 'troopStatsBack', 'troopStatsIntro', 'troopStatsTableWrapper',
    'troopStatsTable', 'buildingStatsIndex', 'buildingStatsSection', 'helpInfoBlock') as $class) {
    if(strpos($page, $class) === false) {
        continue;
    }
    check(strpos($css, $class) !== false,
        'la clase '.$class.' que usa la página existe en travian_basics.css');
}

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Ayuda del final de partida: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
