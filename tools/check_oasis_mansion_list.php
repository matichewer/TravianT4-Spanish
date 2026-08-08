<?php
/**
 * La mansión del héroe lista en "Otros oasis" los que la aldea podría anexar. Ese
 * listado tiene que usar exactamente el mismo radio que la conquista
 * (Automation::oasisAnnexationOutcome), y no perder ninguno por el camino.
 *
 * El bug original: el template guardaba los oasis en $rows[$dist] con $dist float.
 * PHP trunca las claves float a entero, así que de todos los oasis a distancia
 * 3.0-3.9 sobrevivía uno solo, y como después mostraba "los 10 primeros" la tabla
 * terminaba ofreciendo oasis a 8, 9 y 10 casillas mientras escondía vecinos reales.
 */
error_reporting(E_ALL);

function mansionAssert($condition, $message) {
    if(!$condition) {
        echo "FAIL: ".$message."\n";
        exit(1);
    }
    echo "OK: ".$message."\n";
}

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
define('WORLD_MAX', 200);
require dirname(__DIR__).'/GameEngine/Automation.php';

$automationClass = new ReflectionClass('Automation');
$automation = $automationClass->newInstanceWithoutConstructor();

// El radio es un cuadrado de 3, no un círculo: (3|3) entra aunque diste 4.24.
mansionAssert(
    Automation::oasisWithinAnnexationRange(0, 0, 3, 3),
    'la esquina del cuadrado (3|3) está en rango pese a distar 4.24'
);
mansionAssert(
    !Automation::oasisWithinAnnexationRange(0, 0, 4, 0),
    'un oasis a 4 casillas en X queda fuera de rango'
);
mansionAssert(
    !Automation::oasisWithinAnnexationRange(0, 0, 0, -4),
    'un oasis a 4 casillas en Y queda fuera de rango'
);
mansionAssert(
    Automation::oasisWithinAnnexationRange(WORLD_MAX, 0, -WORLD_MAX + 1, 0),
    'el rango cruza el borde del mapa'
);

// La lista y la conquista no pueden discrepar: mismo veredicto para cada casilla.
$mismatch = 0;
for($x = -8; $x <= 8; $x++) {
    for($y = -8; $y <= 8; $y++) {
        if($x === 0 && $y === 0) { continue; }
        $listed = Automation::oasisWithinAnnexationRange(0, 0, $x, $y);
        $outcome = $automation->oasisAnnexationOutcome(
            array('wref' => 1000, 'x' => 0, 'y' => 0, 'mansion' => 10, 'oases' => 0),
            array('x' => $x, 'y' => $y, 'conqured' => 0, 'loyalty' => 100, 'holder_oases' => 0)
        );
        if($listed !== ($outcome['status'] !== 'out_of_range')) {
            $mismatch++;
        }
    }
}
mansionAssert($mismatch === 0, 'la lista y la conquista coinciden en las 288 casillas vecinas');

// La ventana por eje acota el SELECT a las 49 casillas del cuadrado.
$window = Automation::oasisAnnexationAxisWindow(10);
mansionAssert($window === array(7, 8, 9, 10, 11, 12, 13), 'la ventana del eje son las 7 coordenadas del centro');

$window = Automation::oasisAnnexationAxisWindow(WORLD_MAX);
mansionAssert(
    $window === array(WORLD_MAX - 3, WORLD_MAX - 2, WORLD_MAX - 1, WORLD_MAX, -WORLD_MAX, -WORLD_MAX + 1, -WORLD_MAX + 2),
    'la ventana da la vuelta al mapa en el borde positivo'
);

$window = Automation::oasisAnnexationAxisWindow(-WORLD_MAX);
mansionAssert(
    $window === array(WORLD_MAX - 2, WORLD_MAX - 1, WORLD_MAX, -WORLD_MAX, -WORLD_MAX + 1, -WORLD_MAX + 2, -WORLD_MAX + 3),
    'la ventana da la vuelta al mapa en el borde negativo'
);

// La ventana nunca puede dejar afuera una casilla que sí está en rango.
$missing = 0;
for($center = -WORLD_MAX; $center <= WORLD_MAX; $center += 37) {
    $window = Automation::oasisAnnexationAxisWindow($center);
    mansionAssert(count(array_unique($window)) === 7, 'la ventana de '.$center.' tiene 7 coordenadas distintas');
    for($c = -WORLD_MAX; $c <= WORLD_MAX; $c++) {
        $inRange = Automation::oasisWithinAnnexationRange($center, 0, $c, 0);
        if($inRange && !in_array($c, $window, true)) {
            $missing++;
        }
        if(!$inRange && in_array($c, $window, true)) {
            $missing++;
        }
    }
}
mansionAssert($missing === 0, 'la ventana del SELECT cubre exactamente el rango en todo el mapa');

// El fallo concreto que traía el template: agrupar por distancia float pierde oasis.
$oases = array(
    array('id' => 1, 'x' => 2, 'y' => 3),   // dist 3.6
    array('id' => 2, 'x' => 3, 'y' => 2),   // dist 3.6 -> misma clave truncada
    array('id' => 3, 'x' => 1, 'y' => 0),
);
$brokenKeying = array();
foreach($oases as $oasis) {
    $brokenKeying[round(sqrt($oasis['x'] * $oasis['x'] + $oasis['y'] * $oasis['y']), 1)] = $oasis;
}
mansionAssert(
    count($brokenKeying) === 2 && count($oases) === 3,
    'reproducido: indexar por distancia float pierde uno de los dos oasis a 3.6'
);

// Y el orden que usa ahora el template los conserva a todos.
$worldSize = 2 * WORLD_MAX + 1;
$squaredDistance = function($row) use ($worldSize) {
    $dx = abs((int)$row['x']);
    $dy = abs((int)$row['y']);
    $dx = min($dx, $worldSize - $dx);
    $dy = min($dy, $worldSize - $dy);
    return $dx * $dx + $dy * $dy;
};
$sorted = $oases;
usort($sorted, function($a, $b) use ($squaredDistance) {
    $byDistance = $squaredDistance($a) - $squaredDistance($b);
    return $byDistance !== 0 ? $byDistance : ((int)$a['id'] - (int)$b['id']);
});
mansionAssert(count($sorted) === 3, 'el orden nuevo conserva los tres oasis');
mansionAssert(
    array_column($sorted, 'id') === array(3, 1, 2),
    'ordena por cercanía y desempata por id para que el orden sea estable'
);

echo "\nTodo OK: la lista de oasis de la mansión coincide con el radio de conquista.\n";
