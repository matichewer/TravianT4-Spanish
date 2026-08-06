<?php
// Regresión de la mano derecha (btype 4, types 16-60): además de la fuerza de combate
// del héroe, cada arma sube el ataque y la defensa de la unidad a la que apunta.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_weapons.php

$_POST = array();
chdir(dirname(__DIR__).'/GameEngine');
require 'Inventory.php';

class FakeWeaponDatabase
{
	public $inventory = array('helmet'=>0,'body'=>0,'leftHand'=>0,'rightHand'=>0,'shoes'=>0,'horse'=>0,'bag'=>0);
	public $items = array();
	public $hero = array('itempower'=>0,'autoregen'=>10,'speed'=>7,'dead'=>0);
	public $face = array('helmet'=>0,'leftHand'=>0,'rightHand'=>0,'foot'=>0,'horse'=>0);

	public function __construct($items = array()){ $this->items = $items; }

	public function getHeroInventory($uid){ return $this->inventory; }
	public function getItemData($id){ return isset($this->items[$id]) ? $this->items[$id] : false; }
	public function getHeroData($uid){ return $this->hero; }
	public function editProcItem($id,$mode){ $this->items[$id]['proc']=(int)$mode; return true; }
	public function setHeroInventory($uid,$field,$value){ $this->inventory[$field]=(int)$value; return true; }
	public function modifyHeroFace($uid,$field,$value){ $this->face[$field]=(int)$value; return true; }
	public function modifyHero2($field,$value,$uid,$mode)
	{
		if($mode==0){ $this->hero[$field]=$value; } else { $this->hero[$field]+=$value; }
		return true;
	}
}

function check($condition, $message)
{
	if(!$condition){
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

function withWeapon($type)
{
	$db = new FakeWeaponDatabase(array(
		1 => array('id'=>1,'uid'=>7,'btype'=>4,'type'=>$type,'num'=>1,'proc'=>0)
	));
	check(equipHeroItem($db, 7, $db->getItemData(1)), "no se pudo equipar el arma $type");

	return $db;
}

// --- Fuerza de combate: la mitad que ya funcionaba -----------------------------

foreach(range(16, 60) as $type){
	$expected = (($type-16)%3+1)*500;
	$db = withWeapon($type);
	check((int)$db->hero['itempower']===$expected,
		"el arma $type dejó itempower en ".$db->hero['itempower']." en vez de $expected");
	check((int)$db->face['rightHand']===$type, "el arma $type no se dibujó en la mano");

	check(unequipHeroItem($db, 7, 4, 1), "no se pudo sacar el arma $type");
	check((int)$db->hero['itempower']===0, "sacar el arma $type dejó fuerza fantasma");
}

// --- Unidad y bono por unidad ---------------------------------------------------

// Las 45 armas apuntan a una unidad, y las tres de cada familia suben de a escalones.
$families = array(
	1  => array(3, 4, 5),   2  => array(3, 4, 5),   3  => array(3, 4, 5),
	5  => array(9, 12, 15), 6  => array(12, 16, 20),
	21 => array(3, 4, 5),   22 => array(3, 4, 5),
	24 => array(6, 8, 10),  25 => array(6, 8, 10),  26 => array(9, 12, 15),
	11 => array(3, 4, 5),   12 => array(3, 4, 5),   13 => array(3, 4, 5),
	15 => array(6, 8, 10),  16 => array(9, 12, 15)
);
$seen = array();
foreach(range(16, 60) as $type){
	$bonuses = getHeroWeaponBonuses($type);
	check($bonuses['unit'] > 0, "el arma $type no apunta a ninguna unidad");
	check(isset($families[$bonuses['unit']]), "el arma $type apunta a la unidad ".$bonuses['unit'].", que no tiene armas");
	$tier = ($type-16)%3;
	check($bonuses['strength'] === $families[$bonuses['unit']][$tier],
		"el arma $type da ".$bonuses['strength']." en vez de ".$families[$bonuses['unit']][$tier]);
	$seen[$bonuses['unit']][] = $type;
}
check(count($seen) === 15, 'no hay exactamente 15 unidades con arma');
foreach($seen as $unit => $types){
	check(count($types) === 3, "la unidad $unit no tiene exactamente tres armas");
}

// Las armas de cada tribu apuntan solo a unidades de esa tribu.
$ranges = array(array(16, 30, 1, 10), array(31, 45, 21, 30), array(46, 60, 11, 20));
foreach($ranges as $range){
	for($type = $range[0]; $type <= $range[1]; $type++){
		$unit = getHeroWeaponBonuses($type)['unit'];
		check($unit >= $range[2] && $unit <= $range[3],
			"el arma $type apunta a la unidad $unit, de otra tribu");
	}
}

// Lo que no es arma no tiene nada.
foreach(array(0, 15, 61, 76, 100) as $type){
	$bonuses = getHeroWeaponBonuses($type);
	check($bonuses['unit'] === 0 && $bonuses['strength'] === 0 && $bonuses['itempower'] === 0,
		"el tipo $type devolvió bonos de arma");
}

// --- El bono aplicado a un ejército ---------------------------------------------

// Espada larga del legionario (18): +5 por legionario.
$db = withWeapon(18);
$weapon = heroEquippedWeaponBonuses($db, 7);
check($weapon['unit'] === 1 && $weapon['strength'] === 5, 'la espada larga no apunta al legionario con +5');
check(heroWeaponArmyBonus($weapon, array('u1' => 5000)) === 25000, '5000 legionarios no dieron 25000 puntos');
check(heroWeaponArmyBonus($weapon, array('u1' => 1)) === 5, 'un legionario no dio 5 puntos');
check(heroWeaponArmyBonus($weapon, array('u1' => 0)) === 0, 'sin legionarios apareció bono');
check(heroWeaponArmyBonus($weapon, array('u2' => 5000)) === 0, 'los pretorianos cobraron el bono del legionario');
check(heroWeaponArmyBonus($weapon, array()) === 0, 'un ejército vacío dio bono');
check(heroWeaponArmyBonus($weapon, array('u1' => -10)) === 0, 'una cantidad negativa dio bono');

// Un arma de otra tribu no suma: el romano nunca lleva falanges.
$db = withWeapon(33);
$weapon = heroEquippedWeaponBonuses($db, 7);
check($weapon['unit'] === 21, 'la lanza de la falange no apunta a la falange');
check(heroWeaponArmyBonus($weapon, array('u1' => 5000, 'u2' => 5000, 'u3' => 5000)) === 0,
	'un arma gala sumó sobre un ejército romano');

// Sin arma equipada no hay bono.
$db = new FakeWeaponDatabase();
$weapon = heroEquippedWeaponBonuses($db, 7);
check($weapon['unit'] === 0 && $weapon['strength'] === 0, 'sin arma apareció un bono de unidad');
check(heroWeaponArmyBonus($weapon, array('u1' => 5000)) === 0, 'sin arma un ejército cobró bono');

// Un objeto de otro slot en la mano derecha no cuenta como arma.
$db = new FakeWeaponDatabase(array(
	1 => array('id'=>1,'uid'=>7,'btype'=>3,'type'=>78,'num'=>1,'proc'=>1)
));
$db->inventory['rightHand'] = 1;
$weapon = heroEquippedWeaponBonuses($db, 7);
check($weapon['unit'] === 0, 'un escudo en la mano derecha se contó como arma');

// --- Cambiar de arma no acumula --------------------------------------------------

$db = new FakeWeaponDatabase(array(
	1 => array('id'=>1,'uid'=>7,'btype'=>4,'type'=>16,'num'=>1,'proc'=>0),
	2 => array('id'=>2,'uid'=>7,'btype'=>4,'type'=>30,'num'=>1,'proc'=>0)
));
check(equipHeroItem($db, 7, $db->getItemData(1)), 'primera arma rechazada');
check(equipHeroItem($db, 7, $db->getItemData(2)), 'reemplazo de arma rechazado');
check((int)$db->hero['itempower']===1500, 'el reemplazo acumuló fuerza: '.$db->hero['itempower']);
$weapon = heroEquippedWeaponBonuses($db, 7);
check($weapon['unit'] === 6 && $weapon['strength'] === 20, 'el reemplazo no cambió la unidad del bono');
check(unequipHeroItem($db, 7, 4, 2), 'no se pudo sacar el arma de reemplazo');
check((int)$db->hero['itempower']===0, 'quedó fuerza fantasma tras el reemplazo');
check(heroEquippedWeaponBonuses($db, 7)['unit'] === 0, 'quedó bono de unidad tras sacar el arma');

echo "Hero weapons regression: OK\n";
