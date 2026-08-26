<?php
/** Verifica que el bloque lateral agrupe contingentes del mismo tipo. */
if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
include 'GameEngine/Lang/es.php';
include 'GameEngine/Technology.php';

$technology = new Technology();
$home = array(
    array('id' => 13, 'name' => U13, 'amt' => 1),
    array('id' => 14, 'name' => U14, 'amt' => 7),
    array('id' => 'hero', 'name' => U0, 'amt' => 1),
);
$reinforcements = array(
    array('u14' => 5, 'hero' => 1),
    array('u14' => 12, 'u24' => 3, 'hero' => 0),
);
$result = $technology->aggregateHostedUnitList($home, $reinforcements);
$byId = array();
foreach($result as $row) {
    $byId[(string)$row['id']] = $row;
}

$checks = array(
    array(count($result) === 4, 'cada tipo aparece una sola vez'),
    array(isset($byId['14']) && $byId['14']['amt'] === 24, 'suma tropas locales y todos los refuerzos'),
    array(isset($byId['hero']) && $byId['hero']['amt'] === 2, 'suma heroes locales y de refuerzo'),
    array(isset($byId['24']) && $byId['24']['amt'] === 3, 'conserva tipos que solo existen como refuerzo'),
);

$failed = false;
foreach($checks as $check) {
    list($ok, $message) = $check;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    $failed = $failed || !$ok;
}
exit($failed ? 1 : 0);
