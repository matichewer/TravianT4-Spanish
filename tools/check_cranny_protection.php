<?php

error_reporting(E_ALL);

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require dirname(__DIR__).'/GameEngine/Automation.php';

$errors = array();
function crannyProtectionAssert($condition, $message) {
    global $errors;
    if(!$condition) {
        $errors[] = $message;
    }
}

$bid23 = array(
    1 => array('attri' => 1000),
    2 => array('attri' => 1300),
    3 => array('attri' => 1700)
);

$buildings = array();
for($field = 19; $field <= 40; $field++) {
    $buildings['f'.$field] = 0;
    $buildings['f'.$field.'t'] = 0;
}
$buildings['f19'] = 2;
$buildings['f19t'] = 23;

$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();

$romanProtection = $automation->calculateCrannyProtection($buildings, 1, 1, 1);
crannyProtectionAssert(
    $romanProtection['nominal'] === 1300.0
        && $romanProtection['capacity'] === 1300.0
        && $romanProtection['protected'] === 1300.0,
    'A normal attacker against a non-Gaul defender must use the nominal cranny capacity.'
);

$gaulProtection = $automation->calculateCrannyProtection($buildings, 1, 3, 1);
crannyProtectionAssert(
    $gaulProtection['nominal'] === 1300.0
        && $gaulProtection['capacity'] === 2600.0
        && $gaulProtection['protected'] === 2600.0,
    'A Gaul defender must receive double cranny protection.'
);

$germanAgainstGaul = $automation->calculateCrannyProtection($buildings, 2, 3, 1);
crannyProtectionAssert(
    $germanAgainstGaul['nominal'] === 1300.0
        && $germanAgainstGaul['capacity'] === 2600.0
        && $germanAgainstGaul['protected'] === 2080.0,
    'A German attacker against a Gaul defender must see 80% of the doubled capacity.'
);

$buildings['f20'] = 1;
$buildings['f20t'] = 23;
$multipleCrannies = $automation->calculateCrannyProtection($buildings, 2, 3, 1);
crannyProtectionAssert(
    $multipleCrannies['nominal'] === 2300.0
        && $multipleCrannies['capacity'] === 4600.0
        && $multipleCrannies['protected'] === 3680.0,
    'All crannies must contribute before tribe modifiers are applied.'
);

$resources = array(1961, 979, 1561, 2000);
$available = array_map(function($resource) use ($germanAgainstGaul) {
    return max(0, (int)floor($resource - $germanAgainstGaul['protected']));
}, $resources);
crannyProtectionAssert(
    array_sum($available) === 0,
    'The reported 1961/979/1561/2000 resources must all remain protected against the German attacker.'
);

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
crannyProtectionAssert(
    substr_count($automationSource, 'calculateCrannyProtection(') >= 2
        && strpos($automationSource, 'title=\"Escondite\">".round($cranny)') !== false
        && strpos($automationSource, '$cranny_eff = $crannyProtection[\'protected\']') !== false,
    'Loot resolution and scouting reports must share the calculation while reporting only the cranny capacity.'
);

if(!empty($errors)) {
    foreach($errors as $error) {
        echo "FAIL: ".$error."\n";
    }
    exit(1);
}

echo "Cranny protection regression: OK\n";
