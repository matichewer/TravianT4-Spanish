<?php

require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';

$failures = array();
$endpoint = file_get_contents(dirname(__DIR__).'/troop_stats.php');
$help = file_get_contents(dirname(__DIR__).'/help.php');

for ($unitId = 1; $unitId <= 30; $unitId++) {
	$unit = ${'u'.$unitId};
	foreach (array('atk', 'di', 'dc', 'wood', 'clay', 'iron', 'crop', 'pop', 'speed', 'time', 'cap') as $stat) {
		if (!array_key_exists($stat, $unit)) {
			$failures[] = 'La unidad u'.$unitId.' no tiene la estadística '.$stat.'.';
		}
	}
}

if (strpos($help, 'href="troop_stats.php"') === false) {
	$failures[] = 'La ayuda no enlaza la página de estadísticas de tropas.';
}

if (strpos($endpoint, "\${'u'.\$troopStatsUnitId}") === false) {
	$failures[] = 'La página no obtiene las estadísticas desde unitdata.php.';
}

if (strpos($endpoint, "array('name' => 'Romanos', 'firstUnit' => 1)") === false
	|| strpos($endpoint, "array('name' => 'Germanos', 'firstUnit' => 11)") === false
	|| strpos($endpoint, "array('name' => 'Galos', 'firstUnit' => 21)") === false) {
	$failures[] = 'La página no incluye las tres tribus jugables.';
}

if ($failures) {
	fwrite(STDERR, implode("\n", $failures)."\n");
	exit(1);
}

echo "Troop stats checks passed.\n";
