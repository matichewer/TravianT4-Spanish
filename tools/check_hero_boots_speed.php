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

// El bono sale del objeto que ocupa el slot de pies según `heroinventory`, que es la
// fuente de verdad. Un objeto suelto en la grilla no puede aportar nada, ni siquiera
// si su fila quedó marcada con proc = 1.
class FakeBootsDatabase
{
	public $inventories;
	public $items;

	public function __construct($inventories, $items)
	{
		$this->inventories = $inventories;
		$this->items = $items;
	}

	public function getHeroInventory($uid)
	{
		return isset($this->inventories[(int)$uid]) ? $this->inventories[(int)$uid] : false;
	}

	public function getItemData($id)
	{
		return isset($this->items[(int)$id]) ? $this->items[(int)$id] : false;
	}

	public function getEquippedHeroItem($uid, $btype)
	{
		foreach($this->items as $item){
			if((int)$item['uid']===(int)$uid && (int)$item['btype']===(int)$btype && (int)$item['proc']===1){
				return $item;
			}
		}

		return false;
	}
}

function bootsItem($id, $uid, $type, $proc, $btype=5)
{
	return array('id'=>$id, 'uid'=>$uid, 'btype'=>$btype, 'type'=>$type, 'proc'=>$proc);
}

$database = new FakeBootsDatabase(
	array(
		1 => array('shoes' => 11),
		2 => array('shoes' => 21),
		3 => array('shoes' => 31),
		4 => array('shoes' => 41),
		5 => array('shoes' => 0),
		6 => array('shoes' => 61),
		7 => array('shoes' => 71),
		8 => array('shoes' => 81)
	),
	array(
		11 => bootsItem(11, 1, 97, 1),
		21 => bootsItem(21, 2, 99, 1),
		31 => bootsItem(31, 3, 94, 1),
		41 => bootsItem(41, 4, 102, 1),
		// uid 5 no tiene nada puesto, pero tiene unas botas sueltas en la grilla.
		51 => bootsItem(51, 5, 99, 0),
		// uid 6 lleva botas de regeneración y dejó unas de mercenario sueltas cuya
		// fila quedó marcada como equipada: el slot manda, no el flag.
		61 => bootsItem(61, 6, 94, 1),
		62 => bootsItem(62, 6, 99, 1),
		// uid 7 apunta a un objeto de otro dueño y uid 8 a uno de otro tipo.
		71 => bootsItem(71, 99, 99, 1),
		81 => bootsItem(81, 8, 105, 1, 6)
	)
);
bootsAssert(heroEquippedBootsSpeedBonus($database, 1)===25, 'Equipped mercenary boots were not read');
bootsAssert(heroEquippedBootsSpeedBonus($database, 2)===75, 'Equipped archon boots were not read');
bootsAssert(heroEquippedBootsSpeedBonus($database, 3)===0, 'Regeneration boots were treated as mercenary boots');
bootsAssert(heroEquippedBootsSpeedBonus($database, 4)===0, 'Spurs were treated as mercenary boots');
bootsAssert(heroEquippedBootsSpeedBonus($database, 5)===0, 'Boots sitting in the inventory grid produced a bonus');
bootsAssert(heroEquippedBootsSpeedBonus($database, 6)===0, 'An unequipped item with a stale proc flag produced a bonus');
bootsAssert(heroEquippedBootsSpeedBonus($database, 7)===0, 'Another user\'s boots produced a bonus');
bootsAssert(heroEquippedBootsSpeedBonus($database, 8)===0, 'A horse in the shoes slot produced a boots bonus');
bootsAssert(heroEquippedBootsSpeedBonus($database, 0)===0, 'A missing user produced a bonus');

// El slot manda también para leer el objeto en sí.
bootsAssert(heroEquippedItem($database, 6, 5)['id']===61, 'The shoes slot did not resolve to the item it points at');
bootsAssert(heroEquippedItem($database, 5, 5)===false, 'An empty shoes slot resolved to an item');
bootsAssert(heroEquipmentSlot(5)==='shoes' && heroEquipmentSlot(6)==='horse', 'Equipment slots are mislabelled');
// Los tres tipos de bolsa comparten `bag`: el héroe puede llevar uno solo, así que
// preguntar por vendas cuando lo cargado son jaulas tiene que dar vacío.
bootsAssert(
	heroEquipmentSlot(7)==='bag' && heroEquipmentSlot(8)==='bag' && heroEquipmentSlot(9)==='bag',
	'Bag items do not share the bag slot'
);
bootsAssert(heroEquipmentSlot(13)===false, 'A non-equipable item was given a slot');
$bagDatabase = new FakeBootsDatabase(
	array(1 => array('bag' => 91)),
	array(91 => bootsItem(91, 1, 5, 1, 9))
);
bootsAssert(heroEquippedItem($bagDatabase, 1, 9)['id']===91, 'The loaded cages were not read from the bag');
bootsAssert(heroEquippedItem($bagDatabase, 1, 7)===false, 'Cages in the bag were read as small bandages');
bootsAssert(heroEquippedItem($bagDatabase, 1, 8)===false, 'Cages in the bag were read as bandages');

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

// Cada camino en el que el héroe viaja tiene que pasar los dos bonos: el de las botas
// de mercenario y el de la mano izquierda (mapa, estandarte, bandera). Se revisa en el
// código para que un movimiento nuevo no se olvide de alguno en silencio.
$callSites = array(
	'GameEngine/Units.php' => array(
		'procDistanceTime($homeCoordinates,$trapCoordinates,min($speeds),1,$bootsBonus,$travelBonus)',
		'procDistanceTime($from,$to,empty($speeds) ? 1 : min($speeds),1,$bootsBonus,$travelBonus)',
		'procDistanceTime($fromCor,$toCor,min($speeds),1,$bootsBonus,$travelBonus)',
		'heroEquippedBootsSpeedBonus($database, $session->uid)'
	),
	'GameEngine/Automation.php' => array(
		'procDistanceTime($from, $to, empty($speeds) ? 1 : min($speeds), 1, $bootsBonus, $travelBonus)',
		'procDistanceTime($from, $to, empty($returnSpeeds) ? 1 : min($returnSpeeds), 1, $returnBootsBonus, $returnTravelBonus)',
		'heroEquippedBootsSpeedBonus($database, $ownerID)'
	),
	'Templates/a2b/attack.tpl' => array('procDistanceTime($from,$to,min($speeds),1,$bootsBonus,isset($travelBonus) ? $travelBonus : 0)'),
	'Templates/a2b/sendback.tpl' => array('procDistanceTime($fromCor,$toCor,min($speeds),1,$bootsBonus,isset($travelBonus) ? $travelBonus : 0)'),
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

// La consulta vieja por `proc` ya no existe. Si alguien la reintroduce, vuelve el bug
// de fondo: un objeto suelto contando como equipado.
foreach(array('GameEngine/Database/db_MYSQLi.php', 'GameEngine/Database/db_MYSQL.php') as $driver){
	$path = dirname(__DIR__).'/'.$driver;
	if(!file_exists($path)){
		continue;
	}
	$source = file_get_contents($path);
	bootsAssert($source!==false, "Could not read $driver");
	bootsAssert(
		strpos($source, 'function getEquippedHeroItem')===false,
		"$driver brought back the proc-based equipment lookup"
	);
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
// Caballo y espuelas comparten renglón conceptual con las botas: listar los tres a la
// vez hace creer que cuenta algo que no está puesto (botas y espuelas ni siquiera
// pueden convivir, van al mismo slot). Cada línea aparece solo si aporta.
foreach(array('$horseSpeedBonus', '$spurSpeedBonus', '$bootsArmySpeedBonus') as $bonusVariable){
	bootsAssert(
		strpos($heroTemplate, '<?php if('.$bonusVariable.'>0){ ?>')!==false,
		"The hero tooltip shows the $bonusVariable line even when it contributes nothing"
	);
}
bootsAssert(
	strpos($heroTemplate, 'heroEquippedItem($database,(int)$session->uid,5)')!==false
	&& strpos($heroTemplate, 'heroEquippedItem($database,(int)$session->uid,6)')!==false,
	'The hero tooltip does not resolve equipment through the inventory slot'
);

echo "Hero boots and spurs regression: OK\n";
