<?php
// El héroe defensor tiene una sola fuerza de lucha y debe valer lo mismo contra
// infantería que contra caballería. Antes se sumaba solo a un lado según llevara
// caballo equipado o no, así que un héroe montado no aportaba nada frente a un
// ataque de infantería pura (y uno a pie, nada frente a caballería pura).

function heroDefenseSplitAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

class HeroDefenseSplitDatabase {
	public $heroes = array();
	public $units = array();
	public $tribes = array();
	public $mounted = array();

	public function getUnit($vref) {
		return isset($this->units[$vref]) ? $this->units[$vref] : null;
	}
	public function getEnforceVillage($vref, $mode) {
		return array();
	}
	public function getVillageField($vref, $field) {
		return 0;
	}
	public function getHeroData2($uid) {
		return isset($this->heroes[$uid]) ? $this->heroes[$uid] : false;
	}
	public function getHeroData3($uid) {
		return $this->getHeroData2($uid);
	}
	public function getEquippedHeroItem($uid, $btype) {
		if((int)$btype === 6 && !empty($this->mounted[(int)$uid])) {
			return array('id' => 1, 'type' => 103);
		}
		return false;
	}
	public function getUserField($uid, $field, $mode) {
		if($field === 'tribe') {
			return isset($this->tribes[$uid]) ? $this->tribes[$uid] : 1;
		}
		return $uid;
	}
	public function getABTech($vref) {
		return array();
	}
	public function modifyHero2($column, $value, $uid, $mode) {
		return true;
	}
	public function getBreweryLevel($uid) {
		return 0;
	}
	public function getBreweryCelebrationEnd($uid) {
		return 0;
	}
}

function heroDefenseSplitHero($uid, $wref, $level, $power) {
	return array(
		'uid' => $uid, 'wref' => $wref, 'level' => $level, 'power' => $power,
		'itempower' => 0, 'offBonus' => 0, 'defBonus' => 0, 'product' => 0,
		'health' => 100, 'dead' => 0, 'hide' => 0, 'home' => $wref, 'experience' => 0
	);
}

function heroDefenseSplitUnits($vref, $hero, $counts) {
	$row = array('vref' => $vref, 'hero' => $hero);
	for($unit = 1; $unit <= 50; $unit++) {
		$row['u'.$unit] = isset($counts[$unit]) ? $counts[$unit] : 0;
	}
	return $row;
}

require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Battle.php';

$ATTACKER_UID = 10;
$DEFENDER_UID = 20;
$ATTACKER_WREF = 1000;
$DEFENDER_WREF = 2000;
$UPGRADES = array_fill_keys(array('b1','b2','b3','b4','b5','b6','b7','b8'), 0);

$database = new HeroDefenseSplitDatabase;
$database->tribes = array($ATTACKER_UID => 1, $DEFENDER_UID => 2);
// El defensor teutón aguanta con 27 lanceros, 3 paladines y su héroe nivel 10.
$database->units = array($DEFENDER_WREF => heroDefenseSplitUnits($DEFENDER_WREF, 1, array(12 => 27, 15 => 3)));
$database->heroes = array(
	$ATTACKER_UID => heroDefenseSplitHero($ATTACKER_UID, $ATTACKER_WREF, 5, 16),
	$DEFENDER_UID => heroDefenseSplitHero($DEFENDER_UID, $DEFENDER_WREF, 10, 39)
);
$database->heroes[$ATTACKER_UID]['itempower'] = 1000;

$battle = new Battle;

$Attacker = heroDefenseSplitUnits($ATTACKER_WREF, 1, array());
$Attacker['id'] = $ATTACKER_UID;
$Defender = $database->units[$DEFENDER_WREF];
$Defender['id'] = $DEFENDER_UID;

function heroDefenseSplitBattle($attackerUnits, $defenderMounted) {
	global $battle, $database, $Attacker, $Defender, $UPGRADES;
	global $ATTACKER_UID, $DEFENDER_UID, $ATTACKER_WREF, $DEFENDER_WREF;

	$database->mounted = array($DEFENDER_UID => $defenderMounted ? 1 : 0);
	$attacker = $Attacker;
	foreach($attackerUnits as $unit => $amount) {
		$attacker['u'.$unit] = $amount;
	}

	return $battle->calculateBattle(
		$attacker, $Defender, 8, 1, 2, 0, 100, 100, 3,
		$UPGRADES, $UPGRADES, 0, 0, 8, $ATTACKER_UID, $DEFENDER_UID, $ATTACKER_WREF, $DEFENDER_WREF
	);
}

// Fuerza de lucha del héroe defensor: 100 + 80 * 39 = 3220 puntos.
$heroDefense = 100 + 80 * 39;

$onFoot = heroDefenseSplitBattle(array(), false);
$mounted = heroDefenseSplitBattle(array(), true);
heroDefenseSplitAssert(
	abs($onFoot['Defend_points'] - $mounted['Defend_points']) < 0.001,
	'el caballo del héroe defensor no cambia la defensa contra un ataque de infantería'
);
heroDefenseSplitAssert(
	$mounted['Winner'] === 'defender',
	'un héroe montado defiende contra infantería en vez de dejar pasar el ataque'
);

// Mismo control del lado opuesto: ataque de pura caballería contra un héroe a pie.
$cavalryOnFoot = heroDefenseSplitBattle(array(6 => 5), false);
$cavalryMounted = heroDefenseSplitBattle(array(6 => 5), true);
heroDefenseSplitAssert(
	abs($cavalryOnFoot['Defend_points'] - $cavalryMounted['Defend_points']) < 0.001,
	'el caballo del héroe defensor tampoco cambia la defensa contra caballería'
);

// La fuerza del héroe tiene que estar realmente sumada, no solo ser igual en los
// dos casos: sin héroe en la aldea la defensa cae en sus puntos (por el muro).
$wallFactor = pow(1.02, 8);
$database->units[$DEFENDER_WREF]['hero'] = 0;
$defenderWithoutHero = $Defender;
$defenderWithoutHero['hero'] = 0;
$previousDefender = $Defender;
$Defender = $defenderWithoutHero;
$withoutHero = heroDefenseSplitBattle(array(), false);
$Defender = $previousDefender;
$database->units[$DEFENDER_WREF]['hero'] = 1;

heroDefenseSplitAssert(
	abs(($onFoot['Defend_points'] - $withoutHero['Defend_points']) - $heroDefense * $wallFactor) < 0.5,
	'el héroe defensor aporta sus '.$heroDefense.' puntos de fuerza de lucha a la defensa'
);

// El caballo sigue decidiendo si el héroe ATACA como caballería: contra este
// defensor (60 de defensa contra caballería en los lanceros) el resultado cambia.
$database->mounted = array($ATTACKER_UID => 1);
$attackerMounted = $battle->calculateBattle(
	$Attacker, $Defender, 8, 1, 2, 0, 100, 100, 3,
	$UPGRADES, $UPGRADES, 0, 0, 8, $ATTACKER_UID, $DEFENDER_UID, $ATTACKER_WREF, $DEFENDER_WREF
);
$database->mounted = array();
$attackerOnFoot = $battle->calculateBattle(
	$Attacker, $Defender, 8, 1, 2, 0, 100, 100, 3,
	$UPGRADES, $UPGRADES, 0, 0, 8, $ATTACKER_UID, $DEFENDER_UID, $ATTACKER_WREF, $DEFENDER_WREF
);
heroDefenseSplitAssert(
	$attackerMounted['Defend_points'] !== $attackerOnFoot['Defend_points'],
	'el caballo del héroe atacante sigue decidiendo si pega como caballería'
);

echo "check_hero_defense_split: todo OK\n";
