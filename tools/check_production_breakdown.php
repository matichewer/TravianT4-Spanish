<?php

function productionBreakdownAssert($condition, $message)
{
	if(!$condition) {
		throw new RuntimeException($message);
	}
}

$engine = file_get_contents(dirname(__DIR__).'/GameEngine/Village.php');
$template = file_get_contents(dirname(__DIR__).'/Templates/production.tpl');
productionBreakdownAssert($engine !== false, 'Could not read village engine');
productionBreakdownAssert($template !== false, 'Could not read production template');

foreach(array('wood','clay','iron','crop') as $resource) {
	productionBreakdownAssert(
		strpos($engine, "\$this->productionBreakdown['".$resource."']") !== false,
		'Production breakdown is missing '.$resource
	);
}

// El desglose lo arma ahora la fórmula compartida de Production.php, así que se
// comprueba sobre su salida real en vez de sobre el texto de Village.php.
if(!defined('SPEED')) { define('SPEED', 1); }
require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Production.php';
$sampleFields = array();
for($field = 1; $field <= 40; $field++) { $sampleFields['f'.$field] = 0; $sampleFields['f'.$field.'t'] = 0; }
$sampleFields['f1t'] = 1; $sampleFields['f1'] = 5;
$sampleFields['f2t'] = 4; $sampleFields['f2'] = 5;
$sampleFields['f19t'] = 5; $sampleFields['f19'] = 3;
$sampleFields['f20t'] = 8; $sampleFields['f20'] = 3;
$sampleFields['f21t'] = 9; $sampleFields['f21'] = 3;
$sampleBreakdown = villageGrossProduction($sampleFields, array(1,0,0,1), array(true,false,false,true), 1);
foreach(array('wood','clay','iron','crop') as $resource) {
	foreach(array('fields','building_bonus','oasis_bonus','plus_bonus','speed','gross') as $component) {
		productionBreakdownAssert(
			array_key_exists($component, $sampleBreakdown['breakdown'][$resource]),
			'Production component is missing: '.$resource.'.'.$component
		);
	}
}
foreach(array('grainmill_level','grainmill_bonus','bakery_level','bakery_bonus') as $component) {
	productionBreakdownAssert(
		array_key_exists($component, $sampleBreakdown['breakdown']['crop']),
		'Crop production component is missing: '.$component
	);
}

foreach(array(
	"['hero'] = \$heroProduction[\$resource]",
	"['population'] = \$this->pop",
	"['upkeep'] = \$upkeep",
	"['artefact_saving']"
) as $component) {
	productionBreakdownAssert(strpos($engine, $component) !== false, 'Final production component is missing: '.$component);
}

productionBreakdownAssert(
	strpos($template, 'class="num tooltip"') !== false
	&& strpos($template, 'productionBreakdownTooltip(') !== false,
	'Production values do not expose the breakdown tooltip'
);
productionBreakdownAssert(
	strpos($engine, '$this->allcrop = $this->getCropProd(false);') !== false,
	'Loading the village overwrites the final crop breakdown'
);

foreach(array('Campos de recursos:', 'Oasis (+', 'Bono Plus (+', 'Bono del héroe:', 'Población:', 'Consumo de tropas:', 'Artefacto (consumo ahorrado):', 'Total actual:') as $label) {
	productionBreakdownAssert(strpos($template, $label) !== false, 'Tooltip label is missing: '.$label);
}
productionBreakdownAssert(
	strpos($template, 'Velocidad del servidor') === false,
	'The server speed still occupies a separate tooltip row'
);
productionBreakdownAssert(
	strpos($template, 'Bonos de edificios:') === false
	&& strpos($template, "['grainmill_bonus'] * \$speed") !== false
	&& strpos($template, "['bakery_bonus'] * \$speed") !== false,
	'Crop building bonuses are not shown once per building'
);

echo "Production breakdown checks passed\n";
