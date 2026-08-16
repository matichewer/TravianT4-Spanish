<?php
$template = file_get_contents(dirname(__DIR__).'/Templates/Build/16.tpl');

function oasisReinforcementDisplayAssert($condition, $message) {
    if(!$condition) {
        fwrite(STDERR, "FALLA: $message\n");
        exit(1);
    }
}

oasisReinforcementDisplayAssert(
    strpos($template, '$reinforcedCoords = $reinforcedIsOasis') !== false,
    'la plaza de reuniones no distingue los oasis para mostrar sus coordenadas'
);
oasisReinforcementDisplayAssert(
    strpos($template, 'Refuerzo a ".htmlspecialchars($reinforcedName') !== false
        && strpos($template, '.$reinforcedCoords."</a>"') !== false,
    'el encabezado del refuerzo no incluye las coordenadas calculadas'
);
oasisReinforcementDisplayAssert(
    strpos($template, '<span class=\"coordinateY\">(".$coor[\'x\']."</span>') !== false
        && strpos($template, '<span class=\"coordinateX\">".$coor[\'y\'].")</span>') !== false,
    'la celda de coordenadas no respeta el orden x|y'
);

echo "Oasis reinforcement display checks passed.\n";
