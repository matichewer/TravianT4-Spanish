<?php
// Las botas de mercenario (types 97-99) prometen "+25/50/75% de velocidad del ejército
// en distancias > 20 casillas". El bono no se guarda en ninguna columna del héroe: se
// lee del objeto equipado y se aplica al calcular el tiempo de viaje, acortando solo
// el tramo que pasa el umbral. Este checker cubre la tabla de bonos, la fórmula y que
// los movimientos donde viaja el héroe efectivamente pasen el bono.

require_once dirname(__DIR__).'/GameEngine/Hero.php';

function bootsAssert($condition, $message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

// Tabla de bonos del slot de pies: tres familias que no se pisan entre sí.
foreach(array(94 => 10, 95 => 15, 96 => 20) as $type => $expected){
	$bonuses = getHeroShoesBonuses($type);
	bootsAssert($bonuses['autoregen']===$expected, "Regeneration boots $type give the wrong health bonus");
	bootsAssert($bonuses['speed']===0 && $bonuses['armyspeed']===0, "Regeneration boots $type leaked a speed bonus");
}
foreach(array(97 => 25, 98 => 50, 99 => 75) as $type => $expected){
	$bonuses = getHeroShoesBonuses($type);
	bootsAssert($bonuses['armyspeed']===$expected, "Mercenary boots $type give the wrong army speed bonus");
	bootsAssert($bonuses['speed']===0 && $bonuses['autoregen']===0, "Mercenary boots $type leaked another bonus");
}
foreach(array(100 => 3, 101 => 4, 102 => 5) as $type => $expected){
	$bonuses = getHeroShoesBonuses($type);
	bootsAssert($bonuses['speed']===$expected, "Spurs $type give the wrong hero speed bonus");
	bootsAssert($bonuses['armyspeed']===0 && $bonuses['autoregen']===0, "Spurs $type leaked another bonus");
}
$unknown = getHeroShoesBonuses(93);
bootsAssert(
	$unknown===array('autoregen'=>0,'armyspeed'=>0,'speed'=>0),
	'An item that is not shoes produced a bonus'
);

// Fórmula: el primer tramo (hasta el umbral) tarda lo mismo que sin botas y solo el
// resto se acelera, así que la distancia efectiva es T + (D-T)/(1+bono/100).
$threshold = heroBootsDistanceThreshold();
bootsAssert($threshold===20, 'The boots threshold no longer matches the item description');
bootsAssert(heroBootsTravelDistance(15, 75)===15.0, 'Boots shortened a trip below the threshold');
bootsAssert(heroBootsTravelDistance(20, 75)===20.0, 'Boots shortened a trip exactly at the threshold');
bootsAssert(heroBootsTravelDistance(30, 0)===30.0, 'A hero without boots got a shortened trip');
bootsAssert(abs(heroBootsTravelDistance(30, 25)-28.0)<0.000001, 'The 25 percent boots bonus is incorrect');
bootsAssert(abs(heroBootsTravelDistance(30, 50)-26.666666666667)<0.000001, 'The 50 percent boots bonus is incorrect');
bootsAssert(abs(heroBootsTravelDistance(120, 75)-77.142857142857)<0.000001, 'The 75 percent boots bonus is incorrect');

// El bono se lee del objeto que está en el slot de pies, que también puede tener
// botas de regeneración o espuelas: esas no aceleran a nadie.
class FakeBootsDatabase
{
	public $shoes;

	public function __construct($shoes)
	{
		$this->shoes = $shoes;
	}

	public function getEquippedHeroItem($uid, $btype)
	{
		return ((int)$btype===5 && isset($this->shoes[(int)$uid])) ? $this->shoes[(int)$uid] : false;
	}
}

$database = new FakeBootsDatabase(array(
	1 => array('type' => 97),
	2 => array('type' => 99),
	3 => array('type' => 94),
	4 => array('type' => 102)
));
bootsAssert(heroEquippedBootsSpeedBonus($database, 1)===25, 'Equipped mercenary boots were not read');
bootsAssert(heroEquippedBootsSpeedBonus($database, 2)===75, 'Equipped archon boots were not read');
bootsAssert(heroEquippedBootsSpeedBonus($database, 3)===0, 'Regeneration boots were treated as mercenary boots');
bootsAssert(heroEquippedBootsSpeedBonus($database, 4)===0, 'Spurs were treated as mercenary boots');
bootsAssert(heroEquippedBootsSpeedBonus($database, 5)===0, 'An empty shoes slot produced a bonus');
bootsAssert(heroEquippedBootsSpeedBonus($database, 0)===0, 'A missing user produced a bonus');

// procDistanceTime completo. Se carga GeneratorX con lo mínimo que usa el modo 1.
if(!defined('WORLD_MAX')){ define('WORLD_MAX', 200); }
if(!defined('INCREASE_SPEED')){ define('INCREASE_SPEED', 1); }

class FakeBootsBuilding
{
	public function getTypeLevel($type)
	{
		return 0;
	}
}

$building = new FakeBootsBuilding();
$bid14 = array();
$bid28 = array();
require_once dirname(__DIR__).'/GameEngine/GeneratorX.php';

$origin = array('x' => 0, 'y' => 0);
$far = array('x' => 30, 'y' => 0);
$near = array('x' => 15, 'y' => 0);

bootsAssert($generator->procDistanceTime($origin, $far, 10, 1)===10800.0, 'Base travel time changed');
bootsAssert($generator->procDistanceTime($origin, $far, 10, 1, 25)===10080.0, 'Boots did not shorten a long trip');
bootsAssert($generator->procDistanceTime($origin, $far, 10, 1, 50)===9600.0, 'The 50 percent boots trip is incorrect');
bootsAssert($generator->procDistanceTime($origin, $far, 10, 1, 75)===9257.0, 'The 75 percent boots trip is incorrect');
bootsAssert($generator->procDistanceTime($origin, $near, 10, 1, 75)===5400.0, 'Boots shortened a trip below the threshold');
bootsAssert(
	$generator->procDistanceTime($origin, $far, 300, 0, 75)===$generator->procDistanceTime($origin, $far, 300, 0),
	'Boots leaked into a movement that does not carry troops'
);

// Cada camino en el que el héroe viaja tiene que pasar el bono. Se revisa en el código
// para que un movimiento nuevo no se olvide de las botas en silencio.
$callSites = array(
	'GameEngine/Units.php' => array(
		'$travelTime = max(1, (int)$generator->procDistanceTime($homeCoordinates,$trapCoordinates,min($speeds),1,$bootsBonus));',
		'$time = $generator->procDistanceTime($from,$to,empty($speeds) ? 1 : min($speeds),1,$bootsBonus);',
		'$time = $generator->procDistanceTime($fromCor,$toCor,min($speeds),1,$bootsBonus);',
		'heroEquippedBootsSpeedBonus($database, $session->uid)'
	),
	'GameEngine/Automation.php' => array(
		'$endtime = $this->procDistanceTime($from, $to, empty($speeds) ? 1 : min($speeds), 1, $bootsBonus) + $AttackArrivalTime;',
		'$endtime = $this->procDistanceTime($from, $to, empty($returnSpeeds) ? 1 : min($returnSpeeds), 1, $returnBootsBonus) + $AttackArrivalTime;',
		'heroEquippedBootsSpeedBonus($database, $ownerID)'
	),
	'Templates/a2b/attack.tpl' => array('$time = $generator->procDistanceTime($from,$to,min($speeds),1,$bootsBonus);'),
	'Templates/a2b/sendback.tpl' => array('$time = $generator->procDistanceTime($fromCor,$toCor,min($speeds),1,$bootsBonus);'),
	'Templates/a2b/adventure.tpl' => array('heroEquippedBootsSpeedBonus($database,$session->uid)'),
	'hero_adventure.php' => array('heroEquippedBootsSpeedBonus($database,$session->uid)')
);
foreach($callSites as $file => $fragments){
	$source = file_get_contents(dirname(__DIR__).'/'.$file);
	bootsAssert($source!==false, "Could not read $file");
	foreach($fragments as $fragment){
		bootsAssert(strpos($source, $fragment)!==false, "$file no longer applies the mercenary boots bonus");
	}
}

// El tooltip del héroe tiene que desglosar las espuelas aparte de la velocidad base.
$heroTemplate = file_get_contents(dirname(__DIR__).'/Templates/hero.tpl');
bootsAssert($heroTemplate!==false, 'Could not read hero template');
bootsAssert(
	strpos($heroTemplate, '$heroBaseSpeed = max(7,(int)$hero[\'speed\']-$horseSpeedBonus-$spurSpeedBonus);')!==false,
	'The hero tooltip folds the spur bonus into the base speed'
);
bootsAssert(
	strpos($heroTemplate, 'Espuelas: +<?php echo $spurSpeedBonus; ?> casillas por hora')!==false,
	'The hero tooltip does not list the spur bonus'
);
bootsAssert(
	strpos($heroTemplate, 'Botas: +<?php echo $bootsArmySpeedBonus; ?>%')!==false,
	'The hero tooltip does not mention the mercenary boots bonus'
);

echo "Hero boots and spurs regression: OK\n";
