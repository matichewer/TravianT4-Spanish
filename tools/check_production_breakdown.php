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

foreach(array(
	"'fields'=>",
	"'building_bonus'=>",
	"'oasis_bonus'=>",
	"'plus_bonus'=>",
	"'speed'=>",
	"'gross'=>"
) as $component) {
	productionBreakdownAssert(strpos($engine, $component) !== false, 'Production component is missing: '.$component);
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
