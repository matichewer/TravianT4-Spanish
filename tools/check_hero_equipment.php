<?php

$_POST = array();
chdir(dirname(__DIR__).'/GameEngine');
require 'Inventory.php';

class FakeEquipmentDatabase
{
	public $inventory;
	public $items;
	public $hero;
	public $face;

	public function __construct($items)
	{
		$this->inventory = array(
			'helmet' => 0,
			'body' => 0,
			'leftHand' => 0,
			'rightHand' => 0,
			'shoes' => 0,
			'horse' => 0,
			'bag' => 0
		);
		$this->items = $items;
		$this->hero = array('itempower' => 0, 'autoregen' => 10, 'speed' => 7);
		$this->face = array('helmet' => 0, 'leftHand' => 0, 'rightHand' => 0, 'foot' => 0, 'horse' => 0);
	}

	public function getHeroInventory($uid)
	{
		return $this->inventory;
	}

	public function getItemData($id)
	{
		return isset($this->items[$id]) ? $this->items[$id] : false;
	}

	public function getHeroData($uid)
	{
		return $this->hero;
	}

	public function editProcItem($id, $mode)
	{
		$this->items[$id]['proc'] = (int)$mode;
		return true;
	}

	public function setHeroInventory($uid, $field, $value)
	{
		$this->inventory[$field] = (int)$value;
		return true;
	}

	public function modifyHeroFace($uid, $field, $value)
	{
		$this->face[$field] = (int)$value;
		return true;
	}

	public function modifyHero2($field, $value, $uid, $mode)
	{
		if($mode===0){
			$this->hero[$field] = (int)$value;
		}elseif($mode===1){
			$this->hero[$field] += (int)$value;
		}else{
			$this->hero[$field] -= (int)$value;
		}
		return true;
	}

	public function editHeroType($id, $type, $mode)
	{
		if($mode===0){
			$this->items[$id]['type'] -= (int)$type;
		}elseif($mode===1){
			$this->items[$id]['type'] += (int)$type;
		}else{
			$this->items[$id]['type'] = (int)$type;
		}
		return true;
	}
}

function item($id, $btype, $type, $uid=7, $num=1)
{
	return array('id' => $id, 'uid' => $uid, 'btype' => $btype, 'type' => $type, 'num' => $num, 'proc' => 0);
}

function check($condition, $message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

$definitions = array(
	1 => array('slot' => 'helmet', 'face' => 'helmet', 'types' => array(4, 6)),
	2 => array('slot' => 'body', 'face' => null, 'types' => array(82, 88)),
	3 => array('slot' => 'leftHand', 'face' => 'leftHand', 'types' => array(61, 63)),
	4 => array('slot' => 'rightHand', 'face' => 'rightHand', 'types' => array(16, 18)),
	5 => array('slot' => 'shoes', 'face' => 'foot', 'types' => array(94, 96)),
	6 => array('slot' => 'horse', 'face' => 'horse', 'types' => array(103, 105))
);

foreach($definitions as $btype => $definition){
	$first = item($btype*10+1, $btype, $definition['types'][0]);
	$second = item($btype*10+2, $btype, $definition['types'][1]);
	$foreign = item($btype*10+3, $btype, $definition['types'][0], 99);
	$db = new FakeEquipmentDatabase(array($first['id'] => $first, $second['id'] => $second, $foreign['id'] => $foreign));

	check(equipHeroItem($db, 7, $first), "btype $btype: first equip failed");
	check($db->inventory[$definition['slot']]===$first['id'], "btype $btype: slot did not receive first item");
	check($db->items[$first['id']]['proc']===1, "btype $btype: first item was not marked equipped");
	if($definition['face']!==null){
		check($db->face[$definition['face']]===$first['type'], "btype $btype: first appearance was not applied");
	}

	check(equipHeroItem($db, 7, $second), "btype $btype: replacement failed");
	check($db->inventory[$definition['slot']]===$second['id'], "btype $btype: slot did not receive replacement");
	check($db->items[$first['id']]['proc']===0, "btype $btype: replaced item remained equipped");
	check($db->items[$second['id']]['proc']===1, "btype $btype: replacement was not marked equipped");
	if($btype===1){
		check($db->hero['autoregen']===30, 'helmet regeneration accumulated during replacement');
	}elseif($btype===2){
		check($db->hero['itempower']===500 && $db->hero['autoregen']===10, 'armor bonuses accumulated during replacement');
	}elseif($btype===4){
		check($db->hero['itempower']===1500, 'weapon power accumulated during replacement');
	}elseif($btype===5){
		check($db->hero['autoregen']===30, 'boots regeneration accumulated during replacement');
		check($db->hero['speed']===7, 'regeneration boots changed hero speed');
	}elseif($btype===6){
		check($db->hero['speed']===20, 'horse speed accumulated during replacement');
	}

	check(!equipHeroItem($db, 7, $foreign), "btype $btype: foreign item was accepted");
	check(!unequipHeroItem($db, 7, $btype, $first['id']), "btype $btype: stale item id removed current equipment");
	check($db->inventory[$definition['slot']]===$second['id'], "btype $btype: stale removal changed slot");
	check(unequipHeroItem($db, 7, $btype, $second['id']), "btype $btype: removal failed");
	check($db->inventory[$definition['slot']]===0 && $db->items[$second['id']]['proc']===0, "btype $btype: removal did not clear slot and item");
	if($definition['face']!==null){
		check($db->face[$definition['face']]===0, "btype $btype: removal did not clear appearance");
	}
	if($btype===1){
		check($db->hero['autoregen']===10, 'helmet regeneration remained after removal');
	}elseif($btype===2){
		check($db->hero['itempower']===0 && $db->hero['autoregen']===10, 'armor bonuses remained after removal');
	}elseif($btype===4){
		check($db->hero['itempower']===0, 'weapon power remained after removal');
	}elseif($btype===5){
		check($db->hero['autoregen']===10, 'boots regeneration remained after removal');
	}elseif($btype===6){
		check($db->hero['speed']===7, 'horse speed remained after removal');
	}
}

// Espuelas y caballo escriben los dos en `speed` desde slots distintos: sacar uno no
// puede llevarse el bono del otro.
$sharpSpurs = item(301, 5, 102);
$smallSpurs = item(302, 5, 100);
$warHorse = item(303, 6, 105);
$db = new FakeEquipmentDatabase(array(301 => $sharpSpurs, 302 => $smallSpurs, 303 => $warHorse));
check(equipHeroItem($db, 7, $sharpSpurs), 'spurs: equip failed');
check($db->hero['speed']===12, 'spurs did not add hero speed');
check($db->hero['autoregen']===10, 'spurs changed health regeneration');
check(equipHeroItem($db, 7, $warHorse), 'spurs: horse equip failed');
check($db->hero['speed']===25, 'spurs and horse speed did not stack');
check(equipHeroItem($db, 7, $smallSpurs), 'spurs: replacement failed');
check($db->hero['speed']===23, 'spur replacement swapped more than the spur bonus');
check(unequipHeroItem($db, 7, 6, 303), 'spurs: horse removal failed');
check($db->hero['speed']===10, 'horse removal discarded the spur bonus');
check(unequipHeroItem($db, 7, 5, 302), 'spurs: removal failed');
check($db->hero['speed']===7, 'spur bonus remained after removal');

// Las botas de mercenario no guardan nada en el héroe: su bono depende de la
// distancia y se lee del objeto equipado al calcular cada movimiento.
$archonBoots = item(304, 5, 99);
$db = new FakeEquipmentDatabase(array(304 => $archonBoots));
check(equipHeroItem($db, 7, $archonBoots), 'mercenary boots: equip failed');
check($db->hero['speed']===7 && $db->hero['autoregen']===10, 'mercenary boots changed stored hero stats');
check(getHeroBootsArmySpeedBonus(99)===75, 'mercenary boots bonus is incorrect');

$armor = item(201, 2, 88);
$weapon = item(202, 4, 18);
$db = new FakeEquipmentDatabase(array(201 => $armor, 202 => $weapon));
check(equipHeroItem($db, 7, $armor), 'combined bonuses: armor equip failed');
check(equipHeroItem($db, 7, $weapon), 'combined bonuses: weapon equip failed');
check($db->hero['itempower']===2000, 'combined bonuses were not added across slots');
check(unequipHeroItem($db, 7, 2, 201), 'combined bonuses: armor removal failed');
check($db->hero['itempower']===1500, 'armor removal discarded weapon bonus');
check(unequipHeroItem($db, 7, 4, 202), 'combined bonuses: weapon removal failed');
check($db->hero['itempower']===0, 'combined bonuses remained after both removals');

$smallBandages = item(71, 7, 0, 7, 20);
$cages = item(72, 9, 0, 7, 10);
$foreignBag = item(73, 8, 0, 99, 10);
$db = new FakeEquipmentDatabase(array(71 => $smallBandages, 72 => $cages, 73 => $foreignBag));
check(!equipHeroBagItem($db, 7, $smallBandages, 0), 'bag accepted zero amount');
check(!equipHeroBagItem($db, 7, $smallBandages, 21), 'bag accepted excessive amount');
check(!equipHeroBagItem($db, 7, $foreignBag, 1), 'bag accepted foreign item');
check(equipHeroBagItem($db, 7, $smallBandages, 5), 'first bag equip failed');
check($db->inventory['bag']===71 && $db->items[71]['proc']===1 && $db->items[71]['type']===5, 'first bag state is inconsistent');
check(equipHeroBagItem($db, 7, $cages, 3), 'bag replacement failed');
check($db->items[71]['proc']===0 && $db->items[71]['type']===0, 'replaced bag item remained equipped');
check($db->inventory['bag']===72 && $db->items[72]['proc']===1 && $db->items[72]['type']===3, 'replacement bag state is inconsistent');
check(!unequipHeroBagItem($db, 7, 71), 'stale bag id removed current item');
check(unequipHeroBagItem($db, 7, 72), 'bag removal failed');
check($db->inventory['bag']===0 && $db->items[72]['proc']===0 && $db->items[72]['type']===0, 'bag removal did not clear all state');

// Recarga de la bolsa: se puede sumar al objeto ya cargado sin sacarlo primero.
$bandages = item(81, 8, 0, 7, 20);
$spare = item(82, 8, 0, 7, 5);
$db = new FakeEquipmentDatabase(array(81 => $bandages, 82 => $spare));
check(equipHeroBagItem($db, 7, $bandages, 5), 'reload: initial equip failed');
check($db->items[81]['type']===5, 'reload: initial amount is wrong');

check(equipHeroBagItem($db, 7, $db->getItemData(81), 7), 'reload: topping up the bag failed');
check($db->items[81]['type']===12, 'reload: amount was replaced instead of added');
check($db->inventory['bag']===81 && $db->items[81]['proc']===1, 'reload: bag state changed while topping up');

check(!equipHeroBagItem($db, 7, $db->getItemData(81), 9), 'reload: accepted more than the remaining stack');
check($db->items[81]['type']===12, 'reload: rejected top-up still changed the amount');
check(equipHeroBagItem($db, 7, $db->getItemData(81), 8), 'reload: exact remainder was rejected');
check($db->items[81]['type']===20, 'reload: exact remainder produced a wrong amount');

// Una fila marcada como cargada que no es la de la bolsa es estado inconsistente: se ignora.
$db->items[82]['proc'] = 1;
check(!equipHeroBagItem($db, 7, $db->getItemData(82), 1), 'reload: accepted an item that is not in the bag');
check($db->inventory['bag']===81 && $db->items[82]['type']===0, 'reload: inconsistent item altered the bag');

// Cambiar de objeto sigue vaciando el anterior.
$db->items[82]['proc'] = 0;
check(equipHeroBagItem($db, 7, $db->getItemData(82), 2), 'reload: replacement after top-ups failed');
check($db->items[81]['proc']===0 && $db->items[81]['type']===0, 'reload: replaced item kept its load');
check($db->inventory['bag']===82 && $db->items[82]['type']===2, 'reload: replacement state is inconsistent');

echo "Hero equipment regression: OK\n";
