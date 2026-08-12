<?php
error_reporting(E_ALL);

function wallAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
}

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Battle.php';
require dirname(__DIR__).'/GameEngine/Building.php';

$wallData = array(1 => $bid31, 2 => $bid32, 3 => $bid33);
$expectedPercent = array(1 => 3.0, 2 => 2.0, 3 => 2.5);
$expectedBase = array(1 => 10, 2 => 6, 3 => 8);
$expectedDurability = array(1 => 1.0, 2 => 5.0, 3 => 2.0);

foreach($wallData as $tribe => $levels) {
	wallAssert(count($levels) === 20, 'la muralla de la tribu '.$tribe.' tiene exactamente 20 niveles');
	$previous = array('wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'attri' => 0, 'time' => 0);
	for($level = 1; $level <= 20; $level++) {
		wallAssert(isset($levels[$level]), 'existe la muralla de la tribu '.$tribe.' nivel '.$level);
		foreach(array('wood', 'clay', 'iron', 'crop', 'pop', 'cp', 'attri', 'time') as $key) {
			wallAssert(isset($levels[$level][$key]) && is_numeric($levels[$level][$key]), 'dato '.$key.' inválido para tribu '.$tribe.' nivel '.$level);
		}
		foreach(array('wood', 'clay', 'iron', 'crop', 'attri', 'time') as $key) {
			wallAssert($levels[$level][$key] >= $previous[$key], $key.' retrocede en tribu '.$tribe.' nivel '.$level);
		}
		$expectedAttribute = (int)round((pow(1 + $expectedPercent[$tribe] / 100, $level) - 1) * 100);
		wallAssert((int)$levels[$level]['attri'] === $expectedAttribute, 'bonificación canónica en tribu '.$tribe.' nivel '.$level);
		$previous = $levels[$level];
	}
}

$battle = new Battle();
$combatMethod = new ReflectionMethod('Battle', 'calculateCombatOutcome');
$combatMethod->setAccessible(true);
$ramMethod = new ReflectionMethod('Battle', 'calculateRamOutcome');
$ramMethod->setAccessible(true);

foreach($wallData as $tribe => $levels) {
	for($level = 0; $level <= 20; $level++) {
		$result = $combatMethod->invoke($battle, 1000, 0, 100, 100, 0, $tribe, $level, 100, 100, 1, 3);
		$factor = pow(1 + $expectedPercent[$tribe] / 100, $level);
		$expectedDefense = 100 * $factor + $level * $expectedBase[$tribe] + 10;
		wallAssert(abs($result['defense_points'] - $expectedDefense) < 0.000001, 'defensa exacta de tribu '.$tribe.' nivel '.$level);

		$none = $ramMethod->invoke($battle, 0, 0, 1000, 100, $level, 0, 1, 1, $tribe);
		wallAssert($none['level_after'] === $level, 'cero arietes conserva tribu '.$tribe.' nivel '.$level);
		if($level > 0) {
			$overwhelming = $ramMethod->invoke($battle, $none['required'] * 3, 0, 1000, 100, $level, 0, 1, 1, $tribe);
			wallAssert($overwhelming['level_after'] === 0, 'arietes suficientes destruyen tribu '.$tribe.' nivel '.$level);
		}
	}

	$reference = $ramMethod->invoke($battle, 0, 0, 1000, 100, 20, 0, 1, 1, $tribe);
	$baseRequired = ($reference['required'] - 1) / $expectedDurability[$tribe];
	wallAssert(abs($baseRequired - round($baseRequired)) <= 1, 'resistencia de arietes aplicada a tribu '.$tribe);
}

for($level = -10; $level <= 30; $level++) {
	$result = $combatMethod->invoke($battle, 1000, 0, 100, 100, 0, 1, $level, 100, 100, 1, 3);
	$clamped = max(0, min(20, $level));
	$expectedDefense = 100 * pow(1.03, $clamped) + $clamped * 10 + 10;
	wallAssert(abs($result['defense_points'] - $expectedDefense) < 0.000001, 'nivel fuera de rango '.$level.' se limita de forma segura');
}

$buildingClass = new ReflectionClass('Building');
$building = $buildingClass->newInstanceWithoutConstructor();
foreach(array(31, 32, 33) as $wallType) {
	$building->buildArray = array(array('type' => $wallType));
	wallAssert($building->walling() === $wallType, 'la cola conserva el tipo real de muralla '.$wallType);
}
$building->buildArray = array(array('type' => 15));
wallAssert($building->walling() === false, 'un trabajo que no es muralla no activa su representación');

$dorf2Template = file_get_contents(dirname(__DIR__).'/Templates/dorf2.tpl');
wallAssert(strpos($dorf2Template, 'bBottom') === false, 'la mitad inferior usa una clase CSS existente');
wallAssert(strpos($dorf2Template, 'procResType($wallType)') !== false, 'la muralla terminada muestra su nombre real');

echo "Todas las comprobaciones de murallas pasaron.\n";
