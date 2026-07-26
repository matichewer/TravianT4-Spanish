<?php
error_reporting(E_ALL);

function oasisAssert($condition, $message) {
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

function oasisVillage($overrides = array()) {
    return array_merge(
        array('wref' => 1000, 'x' => 0, 'y' => 0, 'mansion' => 10, 'oases' => 0),
        $overrides
    );
}

function oasisTarget($overrides = array()) {
    return array_merge(
        array('x' => 1, 'y' => 1, 'conqured' => 0, 'loyalty' => 100, 'holder_oases' => 0),
        $overrides
    );
}

// Distance: only oases inside the 3 field radius of the village can be annexed.
$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 3, 'y' => -3)));
oasisAssert($outcome['status'] === 'conquered', 'anexa un oasis libre en el límite de 3 casillas');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 4, 'y' => 0)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis a 4 casillas en X');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 0, 'y' => -4)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis a 4 casillas en Y');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 120, 'y' => 87)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis del otro lado del mapa');

// The map wraps around, so the borders are neighbours.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('x' => WORLD_MAX, 'y' => 0)),
    oasisTarget(array('x' => -WORLD_MAX + 1, 'y' => 0))
);
oasisAssert($outcome['status'] === 'conquered', 'cruza el borde del mapa: ambos extremos son vecinos');

// Hero's mansion: level 10, 15 and 20 for the first, second and third oasis.
foreach(array(0 => 10, 1 => 15, 2 => 20) as $held => $needed) {
    $outcome = $automation->oasisAnnexationOutcome(
        oasisVillage(array('mansion' => $needed - 1, 'oases' => $held)),
        oasisTarget()
    );
    oasisAssert(
        $outcome['status'] === 'mansion_too_low' && $outcome['needed_mansion'] === $needed,
        'exige mansión del héroe nivel '.$needed.' para el oasis número '.($held + 1)
    );

    $outcome = $automation->oasisAnnexationOutcome(
        oasisVillage(array('mansion' => $needed, 'oases' => $held)),
        oasisTarget()
    );
    oasisAssert($outcome['status'] === 'conquered', 'con mansión nivel '.$needed.' anexa el oasis número '.($held + 1));
}

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(array('mansion' => 20, 'oases' => 3)), oasisTarget());
oasisAssert($outcome['status'] === 'oasis_limit', 'no permite un cuarto oasis');

// Attacking an oasis the village already holds changes nothing.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('oases' => 3)),
    oasisTarget(array('conqured' => 1000, 'holder_oases' => 3))
);
oasisAssert($outcome['status'] === 'already_owned', 'atacar un oasis propio no lo vuelve a conquistar');

// Oasis held by another player: 1, 2 or 3 raids depending on how many the holder has.
$attacker = oasisVillage(array('mansion' => 10, 'oases' => 0));

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 3))
);
oasisAssert($outcome['status'] === 'conquered', 'un solo ataque toma el oasis de quien tiene 3');

$loyalty = 100;
$raids = 0;
$status = '';
do {
    $outcome = $automation->oasisAnnexationOutcome(
        $attacker,
        oasisTarget(array('conqured' => 2000, 'loyalty' => $loyalty, 'holder_oases' => 2))
    );
    $loyalty = $outcome['loyalty'];
    $status = $outcome['status'];
    $raids++;
} while($status === 'loyalty_reduced' && $raids < 10);
oasisAssert($status === 'conquered' && $raids === 2, 'hacen falta 2 ataques contra quien tiene 2 oasis');

$loyalty = 100;
$raids = 0;
$status = '';
do {
    $outcome = $automation->oasisAnnexationOutcome(
        $attacker,
        oasisTarget(array('conqured' => 2000, 'loyalty' => $loyalty, 'holder_oases' => 1))
    );
    $loyalty = $outcome['loyalty'];
    $status = $outcome['status'];
    $raids++;
} while($status === 'loyalty_reduced' && $raids < 10);
oasisAssert($status === 'conquered' && $raids === 3, 'hacen falta 3 ataques contra quien tiene su último oasis');

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 1))
);
oasisAssert(
    $outcome['status'] === 'loyalty_reduced' && $outcome['loyalty'] === 66,
    'el primer ataque deja la lealtad en 66%'
);

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 0))
);
oasisAssert($outcome['status'] === 'loyalty_reduced', 'un dato inconsistente de oasis del dueño no rompe el cálculo');

// Every check runs before the loyalty damage: a far away oasis is never touched.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('mansion' => 20)),
    oasisTarget(array('x' => 50, 'y' => 50, 'conqured' => 2000, 'loyalty' => 50, 'holder_oases' => 3))
);
oasisAssert(
    $outcome['status'] === 'out_of_range' && $outcome['loyalty'] === 50,
    'un oasis fuera de alcance conserva su lealtad'
);

echo "Todas las comprobaciones de conquista de oasis pasaron.\n";
