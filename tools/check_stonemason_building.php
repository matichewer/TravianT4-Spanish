<?php

require_once dirname(__DIR__).'/GameEngine/Building.php';

function stonemasonAssert($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAILED: ".$message."\n");
		exit(1);
	}
}

function stonemasonVillage($capital, $palaceLevel, $mainBuildingLevel, $residenceLevel = 0) {
	$resarray = array();
	for($field = 1; $field <= 40; $field++) {
		$resarray['f'.$field] = 0;
		$resarray['f'.$field.'t'] = 0;
	}
	$resarray['f19'] = $palaceLevel;
	$resarray['f19t'] = 26;
	$resarray['f20'] = $mainBuildingLevel;
	$resarray['f20t'] = 15;
	$resarray['f21'] = $residenceLevel;
	$resarray['f21t'] = $residenceLevel > 0 ? 25 : 0;

	return (object)array(
		'capital' => $capital,
		'resarray' => $resarray
	);
}

$reflection = new ReflectionClass('Building');
$building = $reflection->newInstanceWithoutConstructor();
$meetRequirement = $reflection->getMethod('meetRequirement');
$meetRequirement->setAccessible(true);

$village = stonemasonVillage(1, 3, 5);
stonemasonAssert($meetRequirement->invoke($building, 34) === true, 'permite el Taller de cantería en la capital con los requisitos');

$village = stonemasonVillage(0, 3, 5);
stonemasonAssert($meetRequirement->invoke($building, 34) === false, 'rechaza el Taller de cantería fuera de la capital');

$village = stonemasonVillage(1, 2, 5);
stonemasonAssert($meetRequirement->invoke($building, 34) === false, 'exige Palacio nivel 3');

$village = stonemasonVillage(1, 3, 4);
stonemasonAssert($meetRequirement->invoke($building, 34) === false, 'exige Edificio principal nivel 5');

$village = stonemasonVillage(1, 3, 5, 1);
stonemasonAssert($meetRequirement->invoke($building, 34) === false, 'rechaza una aldea con Residencia');

$availableSource = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');
stonemasonAssert(
	substr_count($availableSource, '$stonemasonslodge == 0 && $village->capital == 1') === 2
		&& strpos($availableSource, '$database->getBuildList(34) && $village->capital == 1') !== false,
	'la lista de edificios oculta el Taller de cantería fuera de la capital'
);

$nameFiles = array(
	dirname(__DIR__).'/GameEngine/Building.php',
	dirname(__DIR__).'/GameEngine/Automation.php',
	dirname(__DIR__).'/GameEngine/Lang/es.php',
	dirname(__DIR__).'/Templates/Build/34.tpl',
	dirname(__DIR__).'/Templates/Simulator/def_end.tpl'
);
foreach($nameFiles as $nameFile) {
	$source = file_get_contents($nameFile);
	stonemasonAssert(strpos($source, 'Taller de cantería') !== false, basename($nameFile).' usa el nombre unificado');
}

echo "Stonemason building checks passed.\n";
