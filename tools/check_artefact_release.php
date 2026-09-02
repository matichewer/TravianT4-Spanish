<?php
/**
 * La liberación de artefactos: el plan, su validación y el sembrado de verdad.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_artefact_release.php
 *
 * Lo que fija, y por qué.
 *
 * **La defensa se deriva del mundo, no de una constante.** Oficial: "Defence values are
 * based on the top 100 offensive armies of the game world". Antes los números estaban
 * escritos dentro del mod del panel (6/4/1 aldeas con guarnición de Maravilla x1/x2/x4), y
 * eso no puede servir a la vez para un mundo de cuatro jugadores sin un solo soldado —donde
 * son 87 aldeas de 31.000 tropas, o sea decoración inalcanzable— y para uno grande, donde
 * sería un regalo. El bloque E prueba exactamente ese par de escenarios.
 *
 * **La proporción entre tamaños es oficial y fija**: el grande vale 1,5384 veces el pequeño
 * y el único 1,5 veces el grande. No es 1/2/4.
 *
 * **Los anillos también son oficiales**: únicos en el centro, grandes en la corona
 * intermedia, pequeños en la periferia; guardados como fracción de WORLD_MAX para que el
 * reparto sea el mismo en un mapa de cualquier tamaño.
 *
 * **Y todo lo que llega del formulario es hostil.** El panel avisa, pero un POST repetido
 * con curl no pasa por el formulario: el bloque B revisa que nada salga de rango, que un
 * anillo invertido se arregle y que un modo inventado caiga al de siempre.
 *
 * El bloque F crea las aldeas de verdad, con la misma función que usa el panel, sobre
 * TABLAS TEMPORALES: el mundo real no se toca.
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
include "Data/resdata.php";
include "Data/unitdata.php";
require_once $root.'/GameEngine/NatarVillage.php';
require_once $root.'/GameEngine/NatarSettlement.php';
require_once $root.'/GameEngine/ArtefactRelease.php';

global $database;
$failures = array();
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        echo '[FALLA] '.$message.PHP_EOL;
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}
function q($sql) {
    global $database;
    $result = mysqli_query($database->connection, $sql);
    if($result === false) {
        fwrite(STDERR, 'SQL: '.mysqli_error($database->connection).PHP_EOL.$sql.PHP_EOL);
        exit(1);
    }
    return $result;
}
function scalar($sql) {
    $line = mysqli_fetch_row(q($sql));
    return $line ? $line[0] : null;
}

// =====================================================================================
section('A. Los valores por defecto son los oficiales');
// =====================================================================================
$defaults = artefactReleaseDefaults();
check(abs($defaults['tier_large'] - 1.5384) < 0.0001,
    'el grande vale 1,5384 veces el pequeño');
check(abs($defaults['tier_unique'] - 1.5) < 0.0001,
    'y el único 1,5 veces el grande');
check((int)$defaults['defence_sample'] === 100,
    'la referencia son los 100 mejores ejércitos ofensivos');
check($defaults['defence_mode'] === 'world',
    'por defecto la defensa se deriva del mundo, no de un número fijo');
check((int)$defaults['treasury'] === 20,
    'las aldeas de artefacto llevan Tesoro 20, como en el oficial');
check((int)$defaults['wall'] === 0,
    'y nacen sin muralla: el ariete no tiene nada que hacer ahí');
// Los anillos oficiales sobre el mapa de ±200 son 0-25, 20-60 y 40-110, o sea 13%, 30% y 55%.
check($defaults['ring_unique_max'] < $defaults['ring_large_max']
    && $defaults['ring_large_max'] < $defaults['ring_small_max'],
    'los únicos quedan más al centro que los grandes, y los grandes que los pequeños');
check((int)$defaults['ring_unique_min'] === 0,
    'el anillo de los únicos arranca en el centro del mapa');

// =====================================================================================
section('B. Nada de lo que llega del formulario puede romper el plan');
// =====================================================================================
$limits = artefactReleaseLimits();
foreach($limits as $key => $limit) {
    list($min, $max, $decimal) = $limit;
    $over = artefactReleaseNormalizeConfig(array($key => $max + 1000));
    check($over['config'][$key] <= $max, $key.': un valor por encima del máximo se recorta');
    check(count($over['warnings']) > 0, $key.': y el recorte se avisa');
    $under = artefactReleaseNormalizeConfig(array($key => $min - 1000));
    check($under['config'][$key] >= $min, $key.': un valor por debajo del mínimo se recorta');
    $garbage = artefactReleaseNormalizeConfig(array($key => 'no soy un número'));
    check($garbage['config'][$key] === $defaults[$key],
        $key.': un valor que no es número cae al de por defecto');
    $empty = artefactReleaseNormalizeConfig(array($key => ''));
    check($empty['config'][$key] === $defaults[$key],
        $key.': un campo vacío cae al de por defecto');
}
$array = artefactReleaseNormalizeConfig(array('count_small' => array(1, 2, 3)));
check($array['config']['count_small'] === $defaults['count_small'],
    'un array donde se esperaba un número no revienta ni entra');

$mode = artefactReleaseNormalizeConfig(array('defence_mode' => 'lo que sea'));
check($mode['config']['defence_mode'] === 'world', 'un modo inventado cae a "world"');
check(artefactReleaseNormalizeConfig(array('defence_mode' => 'manual'))['config']['defence_mode'] === 'manual',
    'y "manual" se acepta');

$flipped = artefactReleaseNormalizeConfig(array('ring_small_min' => 80, 'ring_small_max' => 10));
check((int)$flipped['config']['ring_small_min'] === 10 && (int)$flipped['config']['ring_small_max'] === 80,
    'un anillo invertido se da vuelta en vez de quedarse sin casillas donde colocar');

$empty = artefactReleaseNormalizeConfig(array('count_small' => 0, 'count_large' => 0, 'count_unique' => 0));
check(count($empty['warnings']) > 0, 'sembrar cero de todo avisa antes de no hacer nada');

check(artefactReleaseNormalizeConfig(null)['config'] === $defaults,
    'una entrada que no es array devuelve los valores por defecto');
check(artefactReleaseNormalizeConfig(array())['config'] === $defaults,
    'y una vacía también');
// Una clave que no está en la tabla de límites no puede colarse en la configuración.
$extra = artefactReleaseNormalizeConfig(array('malicioso' => 'x', 'count_small' => 3));
check(!isset($extra['config']['malicioso']), 'una clave desconocida se ignora');
check((int)$extra['config']['count_small'] === 3, 'y las conocidas sí entran');

// =====================================================================================
section('C. La defensa: proporciones, piso y modo manual');
// =====================================================================================
$config = artefactReleaseNormalizeConfig(array('defence_floor' => 0, 'defence_factor' => 100))['config'];
$small = artefactReleaseDefenceTarget($config, 100000, ARTEFACT_SIZE_SMALL);
$large = artefactReleaseDefenceTarget($config, 100000, ARTEFACT_SIZE_LARGE);
$unique = artefactReleaseDefenceTarget($config, 100000, ARTEFACT_SIZE_UNIQUE);
check(abs($small - 100000) < 1, 'con el factor al 100% el pequeño vale la referencia entera');
check(abs($large / $small - 1.5384) < 0.001, 'el grande sale 1,5384 veces el pequeño');
check(abs($unique / $large - 1.5) < 0.001, 'y el único 1,5 veces el grande');
check($unique > $large && $large > $small, 'los tres quedan ordenados');

$half = artefactReleaseNormalizeConfig(array('defence_floor' => 0, 'defence_factor' => 50))['config'];
check(abs(artefactReleaseDefenceTarget($half, 100000, ARTEFACT_SIZE_SMALL) - 50000) < 1,
    'el factor al 50% parte la referencia a la mitad');

$floored = artefactReleaseNormalizeConfig(array('defence_floor' => 80000, 'defence_factor' => 100))['config'];
check(abs(artefactReleaseDefenceTarget($floored, 1000, ARTEFACT_SIZE_SMALL) - 80000) < 1,
    'un mundo sin ejércitos cae al piso');
check(artefactReleaseDefenceTarget($floored, 500000, ARTEFACT_SIZE_SMALL) > 80000,
    'pero un mundo con ejércitos grandes lo supera');

$manual = artefactReleaseNormalizeConfig(array('defence_mode' => 'manual',
    'defence_manual' => 123456, 'defence_floor' => 0))['config'];
check(abs(artefactReleaseDefenceTarget($manual, 999999999, ARTEFACT_SIZE_SMALL) - 123456) < 1,
    'en modo manual la referencia del mundo se ignora por completo');

// =====================================================================================
section('D. La guarnición: composición natar, escala correcta');
// =====================================================================================
$composition = artefactReleaseComposition();
check(count($composition) === 10, 'la composición usa las diez unidades natar');
check(isset($composition[44]) && $composition[44] > 0,
    'y lleva exploradores (u44), a diferencia de las aldeas natar independientes');

// El error admisible no es un porcentaje: es el del redondeo a entero de cada una de las
// diez unidades. Como mucho media unidad de cada tipo, o sea la mitad de la suma de sus
// defensas. En un objetivo grande eso es ruido; en uno de mil puntos es un 3%, y sigue
// siendo redondeo y no un error de fórmula.
$roundingBudget = 0.0;
foreach(array_keys(artefactReleaseComposition()) as $unit) {
    $roundingBudget += 0.5 * (float)$GLOBALS['u'.$unit]['di'];
}
check($roundingBudget > 0, 'el margen de redondeo se puede calcular');
foreach(array(1000, 50000, 2500000, 50000000) as $target) {
    $garrison = artefactReleaseGarrison($target);
    $stats = artefactReleaseGarrisonStats($garrison);
    $error = abs($stats['infantry'] - $target);
    check($error <= $roundingBudget,
        'una guarnición para '.number_format($target).' puntos da '
            .number_format($stats['infantry']).' (se desvía '.number_format($error)
            .', el redondeo admite '.number_format($roundingBudget).')');
    check($stats['troops'] > 0, 'y tiene tropas de verdad');
    foreach($garrison as $unit => $amount) {
        check($amount >= 0, 'ninguna unidad puede quedar en negativo (u'.$unit.')');
    }
}
// La mezcla se conserva: la proporción entre dos unidades no cambia con la escala.
$smallGarrison = artefactReleaseGarrison(200000);
$bigGarrison = artefactReleaseGarrison(2000000);
$ratioSmall = $smallGarrison[43] / max(1, $smallGarrison[41]);
$ratioBig = $bigGarrison[43] / max(1, $bigGarrison[41]);
check(abs($ratioSmall - $ratioBig) < 0.05,
    'la mezcla de unidades es la misma en una guarnición chica y en una grande');

check(artefactReleaseGarrison(0) !== null, 'un objetivo de cero no revienta');
check(artefactReleaseGarrisonStats(array())['troops'] === 0, 'ni una guarnición vacía');
check(artefactReleaseGarrisonStats(null)['troops'] === 0, 'ni una nula');

// =====================================================================================
section('E. Sirve para un mundo de cuatro jugadores y para uno grande');
// =====================================================================================
//
// Es la razón de ser de todo esto: el MISMO plan, con la misma configuración, tiene que dar
// una guarnición peleable en los dos extremos.
$default = artefactReleaseNormalizeConfig(array())['config'];

// Mundo chico: nadie tiene tropas. Manda el piso.
$tinyPlan = artefactReleasePlan($default, 0);
$tinySmall = $tinyPlan['summary'][ARTEFACT_SIZE_SMALL]['stats'];
check($tinySmall['troops'] > 0 && $tinySmall['troops'] < 5000,
    'en un mundo sin ejércitos la aldea pequeña queda en '.number_format($tinySmall['troops'])
        .' tropas: peleable por un jugador solo');

// Mundo grande: los mejores ejércitos suman millones de puntos de ataque.
$hugePlan = artefactReleasePlan($default, 8000000);
$hugeSmall = $hugePlan['summary'][ARTEFACT_SIZE_SMALL]['stats'];
check($hugeSmall['troops'] > $tinySmall['troops'] * 20,
    'y en un mundo grande sube sola a '.number_format($hugeSmall['troops']).' tropas');
check($hugeSmall['infantry'] >= 8000000 * 0.99,
    'la defensa acompaña a la ofensiva del mundo, que es la regla oficial');

// La proporción entre tamaños no depende del tamaño del mundo.
foreach(array($tinyPlan, $hugePlan) as $label => $plan) {
    $s = $plan['summary'][ARTEFACT_SIZE_SMALL]['stats']['infantry'];
    $l = $plan['summary'][ARTEFACT_SIZE_LARGE]['stats']['infantry'];
    $u = $plan['summary'][ARTEFACT_SIZE_UNIQUE]['stats']['infantry'];
    check(abs($l / $s - 1.5384) < 0.01 && abs($u / $l - 1.5) < 0.01,
        'la proporción oficial entre tamaños se mantiene en los dos mundos');
}

// El conteo de aldeas es independiente de la defensa.
check($tinyPlan['total_villages'] === $hugePlan['total_villages'],
    'la cantidad de aldeas no cambia con la fuerza del mundo, sólo su guarnición');

// Y los conteos hacen lo que dicen.
$counted = artefactReleasePlan(
    artefactReleaseNormalizeConfig(array('count_small' => 2, 'count_large' => 1, 'count_unique' => 1))['config'],
    0
);
check($counted['summary'][ARTEFACT_SIZE_SMALL]['villages'] === 16, '2 pequeños por cada uno de los 8 tipos = 16');
check($counted['summary'][ARTEFACT_SIZE_LARGE]['villages'] === 8, '1 grande por tipo = 8');
check($counted['summary'][ARTEFACT_SIZE_UNIQUE]['villages'] === 7,
    'y 7 únicos: el plano de almacenamiento no tiene versión única');
check($counted['total_villages'] === 31, 'total 31 aldeas');

$none = artefactReleasePlan(
    artefactReleaseNormalizeConfig(array('count_small' => 0, 'count_large' => 0, 'count_unique' => 0))['config'], 0);
check($none['total_villages'] === 0 && $none['villages'] === array(),
    'con los tres conteos en cero el plan queda vacío y no crea nada');

// Ningún plan puede incluir un único del plano de almacenamiento.
foreach($counted['villages'] as $village) {
    check(!($village['type'] === ARTEFACT_STORAGE && $village['size'] === ARTEFACT_SIZE_UNIQUE),
        'el plan nunca incluye un plano de almacenamiento único');
}
// Y todos los tipos del catálogo tienen que aparecer.
$seenTypes = array();
foreach($counted['villages'] as $village) {
    $seenTypes[$village['type']] = true;
}
check(count($seenTypes) === count(artefactTypeCatalog()),
    'los ocho tipos de artefacto entran en el plan');

// =====================================================================================
section('F. Los anillos del mapa');
// =====================================================================================
$rings = artefactReleaseNormalizeConfig(array())['config'];
list($uniqueMin, $uniqueMax) = artefactReleaseRing($rings, ARTEFACT_SIZE_UNIQUE);
list($largeMin, $largeMax) = artefactReleaseRing($rings, ARTEFACT_SIZE_LARGE);
list($smallMin, $smallMax) = artefactReleaseRing($rings, ARTEFACT_SIZE_SMALL);
check($uniqueMax <= WORLD_MAX && $smallMax <= WORLD_MAX, 'ningún anillo se sale del mapa');
check($uniqueMax < $smallMin || $uniqueMax < $smallMax,
    'el anillo de los únicos termina antes que el de los pequeños');
check($smallMax > $largeMax && $largeMax > $uniqueMax,
    'los tres anillos van de adentro hacia afuera');
check(abs($smallMax - WORLD_MAX * 0.55) < 1,
    'el anillo exterior llega al 55% del borde, como en el oficial');
// El reparto es proporcional: en un mapa el doble de grande, las bandas se duplican.
check($smallMax / WORLD_MAX > 0.5 && $smallMax / WORLD_MAX < 0.6,
    'las bandas se guardan como fracción del mapa, así que escalan solas');

// =====================================================================================
section('G. Un sembrado de verdad, sobre tablas temporales');
// =====================================================================================
$P = TB_PREFIX;
$tables = array();
$result = q("SHOW TABLES LIKE '".$P."%'");
while($line = mysqli_fetch_row($result)) {
    $name = substr($line[0], strlen($P));
    if($name !== 'config') {
        $tables[] = $name;
    }
}
foreach($tables as $table) {
    $create = mysqli_fetch_assoc(q("SHOW CREATE TABLE {$P}{$table}"));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
}
foreach($tables as $table) {
    q("DELETE FROM {$P}{$table}");
}

// Un tablero cuadrado alrededor del centro y un jugador con un ejército conocido.
q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES "
    ."(".UID_NATARS.",'Natars',5,0,2,0),(9601,'ofensivo',1,0,3,0)");
$span = 40;
$side = $span * 2 + 1;
$rows = array();
for($x = -$span; $x <= $span; $x++) {
    for($y = -$span; $y <= $span; $y++) {
        $id = 600000 + ($x + $span) * $side + ($y + $span);
        $rows[] = "($id,3,0,$x,$y,0,0)";
    }
}
foreach(array_chunk($rows, 500) as $chunk) {
    q("INSERT INTO {$P}wdata (id,fieldtype,oasistype,x,y,occupied,image) VALUES ".implode(',', $chunk));
}
$playerTile = 600000 + ($span) * $side + ($span);   // (0|0)
$now = time();
q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $playerTile");
q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) "
    ."VALUES ($playerTile,9601,1,100,0,100,$now,$now,800,800)");
q("INSERT INTO {$P}fdata (vref) VALUES ($playerTile)");
// 1.000 imperanos (u3, atk 70) = 70.000 puntos de ataque.
q("INSERT INTO {$P}units (vref,u3) VALUES ($playerTile,1000)");

$database->flushArtefactCache();
$reference = artefactReleaseReferenceOffence($database, 100);
check($reference === 70000,
    'la referencia lee las tropas de casa: 1.000 imperanos = 70.000 puntos y da '.number_format($reference));

// Las tropas que el jugador tiene reforzando también cuentan.
q("INSERT INTO {$P}enforcement (vref,`from`,u3) VALUES ($playerTile,$playerTile,500)");
check(artefactReleaseReferenceOffence($database, 100) === 105000,
    'y también las que tiene reforzando a otro (1.500 imperanos = 105.000)');
q("DELETE FROM {$P}enforcement");

// Una aldea natar no puede contar como ejército del mundo.
q("INSERT INTO {$P}wdata (id,fieldtype,oasistype,x,y,occupied,image) VALUES (699999,3,0,99,99,1,0)");
q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) "
    ."VALUES (699999,".UID_NATARS.",0,0,0,100,$now,$now,800,800)");
q("INSERT INTO {$P}units (vref,u43) VALUES (699999,99999)");
check(artefactReleaseReferenceOffence($database, 100) === 70000,
    'las tropas de los natars no inflan la referencia del mundo');
q("DELETE FROM {$P}vdata WHERE wref = 699999");
q("DELETE FROM {$P}units WHERE vref = 699999");
q("DELETE FROM {$P}wdata WHERE id = 699999");

// Ahora el sembrado, con un plan chico para que entre en el tablero.
$seedConfig = artefactReleaseNormalizeConfig(array(
    'count_small' => 1, 'count_large' => 1, 'count_unique' => 1,
    'defence_factor' => 100, 'defence_floor' => 0,
    'treasury' => 20, 'fields' => 10, 'cranny' => 10, 'wall' => 0
))['config'];
$seedPlan = artefactReleasePlan($seedConfig, $reference);
$outcome = artefactReleaseExecute($database, $seedPlan, UID_NATARS);

check($outcome['failed'] === 0, 'todas las aldeas encontraron casilla ('.$outcome['failed'].' sin sitio)');
check(count($outcome['created']) === $seedPlan['total_villages'],
    'se crearon las '.$seedPlan['total_villages'].' aldeas del plan');
check((int)scalar("SELECT COUNT(*) FROM {$P}artefacts") === $seedPlan['total_villages'],
    'y hay un artefacto por aldea');

// Un artefacto de cada tipo y tamaño, sin faltantes ni duplicados.
$expected = array();
foreach($seedPlan['villages'] as $village) {
    $expected[$village['type'].'-'.$village['size']] = true;
}
$actual = array();
foreach($database->getAllArtefacts() as $artefact) {
    $actual[(int)$artefact['type'].'-'.(int)$artefact['size']] = true;
}
ksort($expected);
ksort($actual);
check($expected === $actual, 'están exactamente los tipos y tamaños que pedía el plan');
check(count($actual) === 23, 'o sea 8 pequeños + 8 grandes + 7 únicos = 23');

// Cada aldea creada tiene que ser una aldea natar de verdad.
foreach($outcome['created'] as $wref) {
    $village = $database->getVillage($wref);
    $fields = $database->getResourceLevel($wref);
    $artefact = $database->getOwnArtefactInfo($wref);
    check(is_array($artefact) && !empty($artefact['id']), $wref.': guarda un artefacto');
    check((int)$village['owner'] === UID_NATARS, $wref.': es de la cuenta natar');
    check(isStaticNpcVillage($village), $wref.': es escenario estático (no come, no pasa hambre, no repone)');
    check(!isLivingNpcVillage($village), $wref.': y no una aldea natar viva, que sí crecería');
    check((int)$village['capital'] === 0, $wref.': no es capital, así que se puede arrasar');
    check((int)$village['pop'] > 0, $wref.': tiene habitantes contados desde fdata');
    check((int)$village['maxstore'] > 800, $wref.': tiene almacén de verdad, no los 800 de fábrica');

    $treasury = 0;
    $residence = 0;
    for($slot = 19; $slot <= 40; $slot++) {
        if((int)$fields['f'.$slot.'t'] === 27) { $treasury = max($treasury, (int)$fields['f'.$slot]); }
        if(in_array((int)$fields['f'.$slot.'t'], array(25, 26), true)) { $residence++; }
    }
    check($treasury === 20, $wref.': el Tesoro quedó en 20');
    check($residence === 0, $wref.': sin residencia ni palacio, así que se toma con catapultas o jefes');
    check((int)$fields['f99t'] === 0, $wref.': no lleva Maravilla');
    check((int)$fields['f40'] === 0, $wref.': sin muralla, como pidió la configuración');

    $units = $database->getUnit($wref);
    $troops = 0;
    for($unit = 41; $unit <= 50; $unit++) {
        $troops += (int)$units['u'.$unit];
    }
    check($troops > 0, $wref.': tiene guarnición ('.number_format($troops).' tropas)');
    for($unit = 1; $unit <= 40; $unit++) {
        check((int)$units['u'.$unit] === 0, $wref.': no tiene tropas de tribus de jugador');
    }

    $coordinates = $database->getCoor($wref);
    $distance = greyZoneDistanceToCentre((int)$coordinates['x'], (int)$coordinates['y']);
    $size = (int)$artefact['size'];
    list($ringMin, $ringMax) = artefactReleaseRing($seedConfig, $size);
    check($distance >= $ringMin - 0.001 && $distance <= $ringMax + 0.001,
        $wref.': cayó dentro de su anillo ('.round($distance, 1).' contra '
            .round($ringMin).'-'.round($ringMax).')');

    $name = (string)$database->getVillageField($wref, 'name');
    check($name !== '' && strpos($name, 'Aldea') !== 0,
        $wref.': el nombre no empieza con "Aldea" ("'.$name.'")');
}

// Ninguna aldea puede haber caído sobre otra.
check(count(array_unique($outcome['created'])) === count($outcome['created']),
    'no hay dos artefactos en la misma casilla');
check((int)scalar("SELECT COUNT(*) FROM {$P}wdata WHERE occupied = 1")
    === count($outcome['created']) + 1,
    'todas las casillas usadas quedaron marcadas como ocupadas, y sólo ésas');

// Un anillo sin casillas libres no puede reservar nada ni inventar aldeas.
$blocked = artefactReleaseFindTile($database, WORLD_MAX - 1, WORLD_MAX,
    array());
check($blocked === 0, 'un anillo fuera del tablero no devuelve ninguna casilla');
$freeBefore = (int)scalar("SELECT COUNT(*) FROM {$P}wdata WHERE occupied = 0");
artefactReleaseFindTile($database, WORLD_MAX - 1, WORLD_MAX, array());
check((int)scalar("SELECT COUNT(*) FROM {$P}wdata WHERE occupied = 0") === $freeBefore,
    'y buscar sin encontrar no reserva ni marca nada');

// La lista de casillas ya tomadas se respeta: dos aldeas no se pelean la misma.
$taken = array();
$first = artefactReleaseFindTile($database, 0, WORLD_MAX, $taken);
$taken[$first] = true;
$second = artefactReleaseFindTile($database, 0, WORLD_MAX, $taken);
check($first > 0 && $second > 0 && $first !== $second,
    'la segunda búsqueda no devuelve la casilla que la primera ya apartó');

// =====================================================================================
section('H. El panel y el mod usan el mismo plan');
// =====================================================================================
$mod = file_get_contents($root.'/GameEngine/Admin/Mods/addArtefacts.php');
$form = file_get_contents($root.'/Admin/Templates/addArtefacts.tpl');
check(strpos($mod, 'artefactReleaseNormalizeConfig($_POST)') !== false,
    'el mod normaliza el POST antes de tocar nada: es la frontera de confianza');
check(strpos($mod, 'artefactReleasePlan(') !== false && strpos($form, 'artefactReleasePlan(') !== false,
    'la vista previa y el sembrado salen del MISMO plan');
check(strpos($mod, 'artefactReleaseExecute(') !== false,
    'y el sembrado ejecuta ese plan, sin números propios');
check(preg_match('/\$alreadySeeded > 0 && \(!isset\(\$_POST\[.confirmar.\]\)/', $mod) === 1,
    'sembrar sobre un mundo que ya tiene artefactos exige confirmación explícita');
$release = file_get_contents($root.'/GameEngine/ArtefactRelease.php');
check(strpos($release, 'NPC_KIND_STATIC') !== false,
    'las aldeas nacen marcadas como escenario');
check(strpos($release, 'natarProvisionVillage(') !== false
    && strpos($release, 'natarRestockGarrison(') !== false,
    'y pasan por el aprovisionamiento compartido, no por SQL escrito a mano');
check(strpos($mod, 'mysql_query(') === false,
    'el mod ya no escribe SQL propio: sólo ejecuta el plan');
check(strpos($form, 'artefactReleaseLimits()') !== false,
    'el formulario dibuja los rangos desde la misma tabla de límites que valida el servidor');

echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Liberación de artefactos: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
