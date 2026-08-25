<?php
/**
 * Los ocho artefactos: qué hace cada uno y cuánto vale.
 *
 * La tabla es la oficial y cada casilla es un número publicado, no una interpolación.
 * Tres de ellas estaban mal en este repo y las tres en la misma dirección —el artefacto
 * grande, que es el flojo, salía más fuerte que el pequeño—:
 *
 *   - dieta pequeña: ahorraba 1/4 del consumo, el oficial ahorra 1/2;
 *   - entrenamiento grande: dejaba el tiempo en 1/4, el oficial lo deja en 3/4;
 *   - botas únicas: en la lista de granjeo valían x3, el oficial da x2.
 *
 * Y tres efectos no existían en ningún lado: la capacidad del escondite, la velocidad de
 * tropas fuera de la lista de granjeo, y el espionaje.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_artefact_effects.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/GameEngine/Artefact.php';

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
function art($id, $type, $size, $conquered = 0, $vref = 100, $owner = 7) {
    return array('id' => $id, 'type' => $type, 'size' => $size,
        'conquered' => $conquered, 'vref' => $vref, 'owner' => $owner);
}

// -------------------------------------------------------------------------------------
section('A. La tabla oficial de valores, casilla por casilla');

// tipo => array(pequeño, grande, único). Publicados por Travian.
$officialValues = array(
    ARTEFACT_ARCHITECT => array(4.0, 3.0, 5.0),      // durabilidad de edificios
    ARTEFACT_BOOTS     => array(2.0, 1.5, 2.0),      // velocidad de tropas
    ARTEFACT_EAGLE     => array(5.0, 3.0, 10.0),     // eficacia de exploradores
    ARTEFACT_DIET      => array(0.5, 0.75, 0.5),     // cereal que SIGUEN comiendo
    ARTEFACT_TRAINER   => array(0.5, 0.75, 0.5),     // tiempo de entrenamiento que queda
    ARTEFACT_CONFUSION => array(200.0, 100.0, 500.0) // capacidad del escondite
);
$names = array(1 => 'pequeño', 2 => 'grande', 3 => 'único');
foreach($officialValues as $type => $values) {
    foreach($values as $index => $expected) {
        $size = $index + 1;
        $got = artefactEffectValue(art(1, $type, $size), $type);
        check(abs($got - $expected) < 0.0001,
            artefactTypeName($type).' '.$names[$size].': se esperaba '.$expected.' y da '.$got);
    }
}

// Las tres trampas que este repo ya pisó una vez, escritas como afirmaciones sueltas.
check(artefactEffectValue(art(1, ARTEFACT_DIET, 1), ARTEFACT_DIET) === 0.5,
    'la dieta PEQUEÑA parte el consumo a la mitad (ahorraba sólo 1/4)');
check(artefactEffectValue(art(1, ARTEFACT_DIET, 2), ARTEFACT_DIET) === 0.75,
    'la dieta GRANDE lo deja en 3/4, que es la floja');
check(artefactEffectValue(art(1, ARTEFACT_TRAINER, 2), ARTEFACT_TRAINER) === 0.75,
    'el entrenamiento GRANDE deja el tiempo en 3/4 (dejaba 1/4, o sea el más fuerte de los tres)');
check(artefactEffectValue(art(1, ARTEFACT_BOOTS, 3), ARTEFACT_BOOTS) === 2.0,
    'las botas ÚNICAS valen x2, no x3');

// El pequeño nunca puede ser peor que el grande: es la forma de la tabla oficial.
foreach($officialValues as $type => $values) {
    $small = $values[0];
    $large = $values[1];
    $strongerIsHigher = !in_array($type, array(ARTEFACT_DIET, ARTEFACT_TRAINER), true);
    check($strongerIsHigher ? $small >= $large : $small <= $large,
        artefactTypeName($type).': el pequeño no puede ser más flojo que el grande');
}

// El plano es binario y no escala.
check(artefactEffectValue(art(1, ARTEFACT_STORAGE, 1), ARTEFACT_STORAGE) === 1.0
    && artefactEffectValue(art(1, ARTEFACT_STORAGE, 2), ARTEFACT_STORAGE) === 1.0,
    'el plano de almacenamiento no escala con el tamaño');

// Preguntar por un tipo que el artefacto no tiene devuelve el neutro, nunca cero.
check(artefactEffectValue(art(1, ARTEFACT_DIET, 1), ARTEFACT_BOOTS) === 1.0,
    'un artefacto de dieta no puede devolver 0 al preguntarle por la velocidad');

// -------------------------------------------------------------------------------------
section('B. Los ocho tipos tienen nombre, alcance y requisito');

foreach(artefactTypeCatalog() as $type => $info) {
    check(trim($info['name']) !== '', 'el tipo '.$type.' tiene nombre');
    check(trim($info['effect']) !== '', 'el tipo '.$type.' explica su efecto');
    foreach(array(1, 2, 3) as $size) {
        $display = artefactDisplayName($type, $size);
        check(trim($display) !== '' && $display !== 'Artefacto desconocido',
            'el tipo '.$type.' tamaño '.$size.' tiene nombre propio');
    }
    // El nombre del pequeño y el del grande no pueden ser el mismo: el panel de
    // administración llamaba "Artefact of the slight fool" al pequeño y al grande.
    check(artefactDisplayName($type, 1) !== artefactDisplayName($type, 2),
        'el tipo '.$type.': el pequeño y el grande no pueden llamarse igual');
}
check(artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL) === 10, 'el pequeño pide Tesoro 10');
check(artefactTreasuryRequirement(ARTEFACT_SIZE_LARGE) === 20, 'el grande pide Tesoro 20');
check(artefactTreasuryRequirement(ARTEFACT_SIZE_UNIQUE) === 20, 'el único pide Tesoro 20');
check(artefactSizeScope(ARTEFACT_SIZE_SMALL) === 'village', 'el pequeño es de aldea');
check(artefactSizeScope(ARTEFACT_SIZE_LARGE) === 'account', 'el grande es de cuenta');
check(artefactSizeScope(ARTEFACT_SIZE_UNIQUE) === 'account', 'el único es de cuenta');

// -------------------------------------------------------------------------------------
section('C. El artefacto del necio');

// Imita a otro artefacto y cambia solo cada 24 h, sin columna y sin cron: la tirada es
// una función de (id, ventana), así que dos procesos distintos ven lo mismo.
$fool = art(42, ARTEFACT_FOOL, 1, 0);
$rollA = artefactFoolRoll($fool, 3600);
$rollB = artefactFoolRoll($fool, 7200);
check($rollA === $rollB, 'dentro de la misma ventana de 24 h la tirada no puede cambiar');

$changed = false;
for($window = 1; $window <= 12; $window++) {
    $later = artefactFoolRoll($fool, $window * ARTEFACT_FOOL_WINDOW + 60);
    if($later !== $rollA) {
        $changed = true;
        break;
    }
}
check($changed, 'y tiene que cambiar en alguna de las doce ventanas siguientes');

// Nunca imita a un plano ni a sí mismo: es la excepción oficial.
$seen = array();
for($id = 1; $id <= 400; $id++) {
    for($window = 0; $window < 6; $window++) {
        $roll = artefactFoolRoll(art($id, ARTEFACT_FOOL, 1, 0), $window * ARTEFACT_FOOL_WINDOW);
        $seen[$roll['type']] = true;
    }
}
check(!isset($seen[ARTEFACT_STORAGE]), 'el necio nunca imita al plano de almacenamiento');
check(!isset($seen[ARTEFACT_FOOL]), 'ni a sí mismo');
foreach(artefactFoolCandidateTypes() as $candidate) {
    check(isset($seen[$candidate]),
        'en 2400 tiradas tiene que haber salido alguna vez '.artefactTypeName($candidate));
}

// El único siempre sale para bien; los otros dos pueden salir en contra.
$uniquePenalty = false;
$smallPenalty = false;
for($id = 1; $id <= 400; $id++) {
    if(artefactFoolRoll(art($id, ARTEFACT_FOOL, ARTEFACT_SIZE_UNIQUE, 0), 0)['penalty']) {
        $uniquePenalty = true;
    }
    if(artefactFoolRoll(art($id, ARTEFACT_FOOL, ARTEFACT_SIZE_SMALL, 0), 0)['penalty']) {
        $smallPenalty = true;
    }
}
check($uniquePenalty === false, 'el necio ÚNICO nunca puede salir en contra');
check($smallPenalty === true, 'pero el pequeño sí, alguna vez');

// Un necio en contra invierte el bono, no lo anula.
$badFool = null;
for($id = 1; $id <= 400 && $badFool === null; $id++) {
    $row = art($id, ARTEFACT_FOOL, ARTEFACT_SIZE_SMALL, 0);
    $roll = artefactFoolRoll($row, 0);
    if($roll['penalty'] && $roll['type'] === ARTEFACT_ARCHITECT) {
        $badFool = $row;
    }
}
if($badFool !== null) {
    $value = artefactEffectValue($badFool, ARTEFACT_ARCHITECT, 0);
    check(abs($value - 0.25) < 0.0001,
        'un necio en contra imitando al arquitecto debe dar 1/4, el recíproco del x4');
} else {
    echo '[--] no salió ningún necio en contra imitando al arquitecto en 400 tiradas'.PHP_EOL;
}

// La tirada no puede dejar sembrada la PRNG global: si lo hiciera, todo el rand() del
// motor —animales de oasis, objetos de aventura— pasaría a ser determinista.
mt_srand(12345);
$before = mt_rand();
mt_srand(12345);
artefactFoolRoll(art(7, ARTEFACT_FOOL, 1, 0), 0);
check(mt_rand() === $before, 'la tirada del necio no puede dejar sembrada la PRNG global');

// -------------------------------------------------------------------------------------
section('D. Los efectos están enchufados de verdad al motor');

// Cada efecto tiene que tener un consumidor. Es la comprobación que faltaba: la tabla de
// valores puede estar perfecta y el efecto no aplicarse en ningún lado, que es
// exactamente lo que pasaba con el escondite, la velocidad y el espionaje.
$sources = array(
    'Automation'  => file_get_contents($root.'/GameEngine/Automation.php'),
    'Technology'  => file_get_contents($root.'/GameEngine/Technology.php'),
    'Battle'      => file_get_contents($root.'/GameEngine/Battle.php'),
    'Building'    => file_get_contents($root.'/GameEngine/Building.php'),
    'Village'     => file_get_contents($root.'/GameEngine/Village.php'),
    'Units'       => file_get_contents($root.'/GameEngine/Units.php'),
    'GeneratorX'  => file_get_contents($root.'/GameEngine/GeneratorX.php'),
    'startRaid'   => file_get_contents($root.'/Templates/a2b/startRaid.tpl')
);
$all = implode("\n", $sources);

check(strpos($sources['Automation'], 'ARTEFACT_ARCHITECT') !== false,
    'la durabilidad del arquitecto se aplica en el impacto de catapulta');
check(strpos($sources['Technology'], 'ARTEFACT_TRAINER') !== false,
    'el artefacto del entrenador se aplica al tiempo de entrenamiento');
check(strpos($sources['Battle'], 'ARTEFACT_EAGLE') !== false,
    'los ojos del águila se aplican en la resolución del espionaje');
check(strpos($sources['Building'], 'ARTEFACT_STORAGE') !== false,
    'el plano de almacenamiento gatea el gran almacén y el gran granero');
check(strpos($all, 'artefactTroopUpkeep(') !== false,
    'el consumo de cereal pasa por la función compartida de dieta');
check(substr_count($all, 'artefactTroopUpkeep(') >= 2,
    'y la usan los DOS caminos de producción (dorf1 y el que acredita recursos)');
check(strpos($sources['Automation'], 'ARTEFACT_CONFUSION') !== false,
    'la confusión del rival se aplica a las catapultas');
check(preg_match('/calculateCrannyProtection\(.*?ARTEFACT_CONFUSION/s', $sources['Automation']) === 1,
    'y también multiplica la capacidad del escondite, que era la mitad que faltaba');
check(strpos($sources['GeneratorX'], '$artefactFactor') !== false,
    'el cálculo de tiempo de viaje acepta el factor de las botas');
check(strpos($sources['Units'], 'artefactTroopSpeedFactor(') !== false,
    'las salidas desde el punto de reunión aplican las botas');
check(strpos($sources['startRaid'], 'artefactTroopSpeedFactor(') !== false,
    'y la lista de granjeo usa la MISMA función, no una copia con otros números');
check(substr_count($sources['Automation'], 'artefactTroopSpeedFactor(') >= 4,
    'las vueltas de los movimientos también aplican las botas: la ida y la vuelta tienen que ser simétricas');

// Ninguna consulta puede volver a pedir las columnas que no existen.
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(preg_match('/artefacts[^;]*\bactive\s*=/i', $dbSource) !== 1,
    'ninguna consulta de artefactos puede volver a pedir la columna `active`, que no existe');
check(preg_match('/artefacts[^;]*\bkind\s*=/i', $dbSource) !== 1,
    'ni la columna `kind`');
check(strpos($all, 'bad_effect') === false && strpos($all, 'effect2') === false,
    'ni las columnas `bad_effect`/`effect2` que leía el artefacto del necio');

// -------------------------------------------------------------------------------------
echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Efectos de artefactos: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
