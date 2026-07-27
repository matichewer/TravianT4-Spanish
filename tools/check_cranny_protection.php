<?php

error_reporting(E_ALL);

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require dirname(__DIR__).'/GameEngine/Automation.php';
require dirname(__DIR__).'/GameEngine/Building.php';

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
    'A normal attacker must use the nominal cranny capacity.'
);

$gaulProtection = $automation->calculateCrannyProtection($buildings, 1, 3, 1);
crannyProtectionAssert(
    $gaulProtection['nominal'] === 1300.0
        && $gaulProtection['capacity'] === 1300.0
        && $gaulProtection['protected'] === 1300.0,
    'A Gaul defender must use the same cranny capacity as every other tribe.'
);

$germanAgainstGaul = $automation->calculateCrannyProtection($buildings, 2, 3, 1);
crannyProtectionAssert(
    $germanAgainstGaul['nominal'] === 1300.0
        && $germanAgainstGaul['capacity'] === 1300.0
        && $germanAgainstGaul['protected'] === 1040.0,
    'A German attacker against a Gaul defender must see 80% of the standard capacity.'
);

foreach(array(1, 2, 3) as $defenderTribe) {
    $normalAttack = $automation->calculateCrannyProtection($buildings, 1, $defenderTribe, 1);
    $germanAttack = $automation->calculateCrannyProtection($buildings, 2, $defenderTribe, 1);
    crannyProtectionAssert(
        $normalAttack['capacity'] === 1300.0
            && $normalAttack['protected'] === 1300.0
            && $germanAttack['capacity'] === 1300.0
            && $germanAttack['protected'] === 1040.0,
        'Every defender tribe must receive identical cranny capacity and attacker modifiers.'
    );
}

$buildings['f20'] = 1;
$buildings['f20t'] = 23;
$multipleCrannies = $automation->calculateCrannyProtection($buildings, 2, 3, 1);
crannyProtectionAssert(
    $multipleCrannies['nominal'] === 2300.0
        && $multipleCrannies['capacity'] === 2300.0
        && $multipleCrannies['protected'] === 1840.0,
    'All crannies must contribute before tribe modifiers are applied.'
);

$resources = array(1961, 979, 1561, 2000);
$available = array_map(function($resource) use ($germanAgainstGaul) {
    return max(0, (int)floor($resource - $germanAgainstGaul['protected']));
}, $resources);
crannyProtectionAssert(
    $available === array(921, 0, 521, 960),
    'Loot availability must use the standard capacity reduced to 80% for a German attacker.'
);

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
crannyProtectionAssert(
    substr_count($automationSource, 'calculateCrannyProtection(') >= 2
        && strpos($automationSource, 'title=\"Escondite\">".round($cranny)') !== false
        && strpos($automationSource, '$cranny_eff = $crannyProtection[\'protected\']') !== false,
    'Loot resolution and scouting reports must share the calculation while reporting only the cranny capacity.'
);

$session = (object)array('tribe' => 1);
$buildingReflection = new ReflectionClass('Building');
$building = $buildingReflection->newInstanceWithoutConstructor();
$meetRequirement = $buildingReflection->getMethod('meetRequirement');
$meetRequirement->setAccessible(true);

$constructionBuildings = array();
for($field = 1; $field <= 40; $field++) {
    $constructionBuildings['f'.$field] = 0;
    $constructionBuildings['f'.$field.'t'] = 0;
}
$village = (object)array('capital' => 0, 'resarray' => $constructionBuildings);
crannyProtectionAssert(
    $meetRequirement->invoke($building, 23) === true,
    'A village without a cranny must be allowed to build its first one.'
);

$village->resarray['f19'] = 9;
$village->resarray['f19t'] = 23;
crannyProtectionAssert(
    $meetRequirement->invoke($building, 23) === false,
    'Additional crannies must remain locked before one reaches level 10.'
);

$village->resarray['f19'] = 10;
crannyProtectionAssert(
    $meetRequirement->invoke($building, 23) === true,
    'A level 10 cranny must unlock additional crannies.'
);

$building->buildArray = array(array('type' => 23));
crannyProtectionAssert(
    $meetRequirement->invoke($building, 23) === false,
    'Only one cranny construction or upgrade may be queued at a time.'
);

$availableSource = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');
crannyProtectionAssert(
    strpos($availableSource, '($cranny == 0 || $cranny >= 10)') !== false,
    'The available-building list must use the same level-10 unlock rule.'
);

if(!empty($errors)) {
    foreach($errors as $error) {
        echo "FAIL: ".$error."\n";
    }
    exit(1);
}

echo "Cranny protection regression: OK\n";
