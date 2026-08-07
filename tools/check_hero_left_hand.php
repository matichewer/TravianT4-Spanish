<?php
// Regresión de la mano izquierda (btype 3): mapas (61-63), estandartes (64-66),
// banderas (67-69), bolsas del ladrón (73-75), escudos (76-78) y cuernos (79-81).
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_left_hand.php

$_POST = array();
chdir(dirname(__DIR__).'/GameEngine');
require 'Inventory.php';

class FakeLeftHandDatabase
{
	public $inventory = array('helmet'=>0,'body'=>0,'leftHand'=>0,'rightHand'=>0,'shoes'=>0,'horse'=>0,'bag'=>0);
	public $items = array();
	public $hero = array('itempower'=>0,'autoregen'=>10,'speed'=>7,'dead'=>0,'wref'=>500,'home'=>500);
	public $face = array('helmet'=>0,'leftHand'=>0,'rightHand'=>0,'foot'=>0,'horse'=>0);

	// Aldea => dueño, y dueño => alianza.
	public $villageOwners = array(10 => 1, 11 => 1, 20 => 2, 30 => 3, 40 => 0);
	public $alliances = array(1 => 7, 2 => 7, 3 => 9);

	public function __construct($items = array())
	{
		$this->items = $items;
	}

	public function getHeroInventory($uid){ return $this->inventory; }
	public function getItemData($id){ return isset($this->items[$id]) ? $this->items[$id] : false; }
	public function getHeroData($uid){ return $this->hero; }
	public function editProcItem($id, $mode){ $this->items[$id]['proc'] = (int)$mode; return true; }
	public function setHeroInventory($uid, $field, $value){ $this->inventory[$field] = (int)$value; return true; }
	public function modifyHeroFace($uid, $field, $value){ $this->face[$field] = (int)$value; return true; }
	public function modifyHero2($field, $value, $uid, $mode)
	{
		if($mode==0){ $this->hero[$field] = $value; } else { $this->hero[$field] += $value; }
		return true;
	}

	public function getVillageField($wref, $field)
	{
		return isset($this->villageOwners[$wref]) ? $this->villageOwners[$wref] : 0;
	}

	public function getUserField($uid, $field, $mode)
	{
		return isset($this->alliances[$uid]) ? $this->alliances[$uid] : 0;
	}
}

function check($condition, $message)
{
	if(!$condition){
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

function withItem($type)
{
	$db = new FakeLeftHandDatabase(array(
		1 => array('id' => 1, 'uid' => 7, 'btype' => 3, 'type' => $type, 'num' => 1, 'proc' => 0)
	));
	check(equipHeroItem($db, 7, $db->getItemData(1)), "no se pudo equipar el objeto $type");

	return $db;
}

// --- Escudos (76-78): fuerza de combate, y se guarda al equipar -----------------

$expected = array(76 => 500, 77 => 1000, 78 => 1500);
foreach($expected as $type => $power){
	$db = withItem($type);
	check((int)$db->hero['itempower']===$power,
		"el escudo $type dejó itempower en ".$db->hero['itempower']." en vez de $power");
	check((int)$db->face['leftHand']===$type, "el escudo $type no se dibujó en la mano");

	check(unequipHeroItem($db, 7, 3, 1), "no se pudo sacar el escudo $type");
	check((int)$db->hero['itempower']===0, "sacar el escudo $type dejó fuerza fantasma");
	check((int)$db->face['leftHand']===0, "sacar el escudo $type no limpió la mano");
}

// Cambiar un escudo por otro no acumula.
$db = new FakeLeftHandDatabase(array(
	1 => array('id'=>1,'uid'=>7,'btype'=>3,'type'=>76,'num'=>1,'proc'=>0),
	2 => array('id'=>2,'uid'=>7,'btype'=>3,'type'=>78,'num'=>1,'proc'=>0)
));
check(equipHeroItem($db, 7, $db->getItemData(1)), 'primer escudo rechazado');
check(equipHeroItem($db, 7, $db->getItemData(2)), 'reemplazo de escudo rechazado');
check((int)$db->hero['itempower']===1500, 'el reemplazo acumuló fuerza: '.$db->hero['itempower']);
check(unequipHeroItem($db, 7, 3, 2), 'no se pudo sacar el escudo de reemplazo');
check((int)$db->hero['itempower']===0, 'quedó fuerza fantasma tras el reemplazo');

// Los que no son escudos no escriben nada en el héroe.
foreach(array(61, 64, 67, 73, 79) as $type){
	$db = withItem($type);
	check((int)$db->hero['itempower']===0, "el objeto $type escribió fuerza de combate");
	check((int)$db->hero['speed']===7, "el objeto $type tocó la velocidad del héroe");
}

// --- Velocidad de viaje --------------------------------------------------------

// Mapas (61-63): solo al volver, sin importar de quién sean las aldeas.
$expected = array(61 => 30, 62 => 40, 63 => 50);
foreach($expected as $type => $bonus){
	$db = withItem($type);
	check(heroEquippedTravelSpeedBonus($db, 7, 20, 10, true)===$bonus,
		"el mapa $type no aceleró el regreso de una aldea ajena");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 11, true)===$bonus,
		"el mapa $type no aceleró el regreso entre aldeas propias");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 20, false)===0,
		"el mapa $type aceleró la ida");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 11, false)===0,
		"el mapa $type aceleró la ida entre aldeas propias");
}

// Estandartes (64-66): entre aldeas propias, en las dos direcciones.
$expected = array(64 => 30, 65 => 40, 66 => 50);
foreach($expected as $type => $bonus){
	$db = withItem($type);
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 11, false)===$bonus,
		"el estandarte $type no aceleró la ida entre aldeas propias");
	check(heroEquippedTravelSpeedBonus($db, 7, 11, 10, true)===$bonus,
		"el estandarte $type no aceleró la vuelta entre aldeas propias");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 20, false)===0,
		"el estandarte $type aceleró un viaje a una aldea ajena");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 20, true)===0,
		"el estandarte $type aceleró el regreso de una aldea ajena");
}

// Banderas (67-69): entre aldeas de la misma alianza, pero no de la misma cuenta.
$expected = array(67 => 15, 68 => 20, 69 => 25);
foreach($expected as $type => $bonus){
	$db = withItem($type);
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 20, false)===$bonus,
		"la bandera $type no aceleró el viaje a un aliado");
	check(heroEquippedTravelSpeedBonus($db, 7, 20, 10, true)===$bonus,
		"la bandera $type no aceleró el regreso de un aliado");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 30, false)===0,
		"la bandera $type aceleró un viaje a otra alianza");
	check(heroEquippedTravelSpeedBonus($db, 7, 10, 40, false)===0,
		"la bandera $type aceleró un viaje a un jugador sin alianza");
}

// Sin objeto no hay bono, y los objetos de otras familias tampoco lo dan.
$db = new FakeLeftHandDatabase();
check(heroEquippedTravelSpeedBonus($db, 7, 10, 11, true)===0, 'sin objeto apareció un bono de viaje');
foreach(array(73, 76, 79) as $type){
	$db = withItem($type);
	foreach(array(array(10,11),array(10,20),array(20,10)) as $trip){
		check(heroEquippedTravelSpeedBonus($db, 7, $trip[0], $trip[1], true)===0,
			"el objeto $type aceleró un viaje");
		check(heroEquippedTravelSpeedBonus($db, 7, $trip[0], $trip[1], false)===0,
			"el objeto $type aceleró un viaje");
	}
}

// La función pura no mezcla familias aunque le mientan las condiciones.
check(heroTravelSpeedBonus(63, true, true, true)===50, 'el mapa perdió su bono');
check(heroTravelSpeedBonus(66, true, true, true)===50, 'el estandarte no aplicó en un regreso entre aldeas propias');
check(heroTravelSpeedBonus(69, true, false, true)===25, 'la bandera no aplicó en un regreso entre aliados');
check(heroTravelSpeedBonus(69, false, true, true)===25, 'la bandera no aplicó entre aliados');
check(heroTravelSpeedBonus(0, true, true, true)===0, 'un tipo inexistente dio bono');

// --- Bolsas del ladrón (73-75): botín ------------------------------------------

$expected = array(73 => 10, 74 => 15, 75 => 20);
foreach($expected as $type => $bonus){
	$db = withItem($type);
	check(heroEquippedBountyBonus($db, 7)===$bonus,
		"la bolsa $type dio ".heroEquippedBountyBonus($db, 7)."% en vez de $bonus%");
}
foreach(array(61, 64, 67, 76, 79) as $type){
	$db = withItem($type);
	check(heroEquippedBountyBonus($db, 7)===0, "el objeto $type subió el botín");
}
$db = new FakeLeftHandDatabase();
check(heroEquippedBountyBonus($db, 7)===0, 'sin objeto apareció bono de botín');

// --- Cuernos del natariano (79-81): fuerza contra los natares -------------------

$expected = array(79 => 1.20, 80 => 1.25, 81 => 1.30);
foreach($expected as $type => $factor){
	$db = withItem($type);
	check(abs(heroNatarStrengthFactor($db, 7, 5)-$factor)<0.0001,
		"el cuerno $type dio factor ".heroNatarStrengthFactor($db, 7, 5)." contra natares");
	// Contra cualquier otra tribu no hace nada.
	foreach(array(1, 2, 3, 4, 0) as $tribe){
		check(heroNatarStrengthFactor($db, 7, $tribe)===1,
			"el cuerno $type aplicó contra la tribu $tribe");
	}
}
foreach(array(61, 64, 67, 73, 76) as $type){
	$db = withItem($type);
	check(heroNatarStrengthFactor($db, 7, 5)===1, "el objeto $type subió la fuerza contra natares");
}
$db = new FakeLeftHandDatabase();
check(heroNatarStrengthFactor($db, 7, 5)===1, 'sin objeto apareció bono contra natares');

// --- Los 18 objetos existen y ninguno se solapa --------------------------------

$families = array(
	'homecoming' => array(61,62,63), 'ownvillages' => array(64,65,66),
	'alliance' => array(67,68,69), 'bounty' => array(73,74,75),
	'itempower' => array(76,77,78), 'natar' => array(79,80,81)
);
foreach($families as $field => $types){
	foreach($types as $type){
		$bonuses = getHeroLeftHandBonuses($type);
		check($bonuses[$field] > 0, "el objeto $type no tiene bono de $field");
		foreach($families as $other => $unused){
			if($other !== $field){
				check($bonuses[$other] === 0, "el objeto $type tiene bono de $other además del suyo");
			}
		}
	}
}
// El hueco 70-72 de la numeración no existe.
foreach(array(70, 71, 72) as $type){
	$bonuses = getHeroLeftHandBonuses($type);
	check(array_sum($bonuses) === 0, "el objeto inexistente $type tiene bonos");
}

// El script de reconciliación tiene que contemplar el escudo: un héroe que ya lo tenía
// puesto antes del deploy no tiene su fuerza guardada, y desequiparlo se la restaría al
// arma. Se revisa en el código para que un cambio futuro no se lo lleve puesto.
$migration = file_get_contents(dirname(__DIR__).'/tools/fix_hero_equipment_bonuses.php');
check($migration !== false, 'No se pudo leer fix_hero_equipment_bonuses.php');
foreach(array('getHeroLeftHandBonuses', 'getHeroWeaponPowerBonus', 'itempower') as $fragment){
	check(strpos($migration, $fragment) !== false,
		"fix_hero_equipment_bonuses.php ya no reconcilia $fragment");
}

echo "Hero left hand regression: OK\n";
