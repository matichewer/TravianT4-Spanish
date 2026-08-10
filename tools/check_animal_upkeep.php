<?php

function animalUpkeepAssert($condition, $message)
{
	if(!$condition) {
		throw new RuntimeException($message);
	}
}

for($i = 0; $i <= 50; $i++) {
	if(!defined('U'.$i)) {
		define('U'.$i, 'U'.$i);
	}
}
if(!defined('U99')) {
	define('U99', 'U99');
}

require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Technology.php';

$session = (object)array('tribe' => 3);
$building = null;
$technology = new Technology();
$units = array_fill_keys(array_map(function($i) { return 'u'.$i; }, range(1,50)), 0);
$units['u1'] = 2;
$units['u31'] = 7;
$units['u37'] = 3;

animalUpkeepAssert(
	$technology->getUpkeep($units, 0) === 2,
	'Los animales capturados alteran el consumo total de cereal'
);
animalUpkeepAssert(
	$technology->getUpkeep($units, 4) === 0,
	'El informe de unidades de naturaleza muestra consumo de cereal'
);

$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
animalUpkeepAssert($automation !== false, 'No se pudo leer Automation.php');
animalUpkeepAssert(
	strpos($automation, 'if($i >= 31 && $i <= 40)') !== false,
	'La automatización no excluye los animales del consumo'
);

echo "Animal upkeep checks passed\n";
