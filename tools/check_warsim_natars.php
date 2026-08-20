<?php
/**
 * El simulador de combate tiene que aceptar a los natares como defensor.
 *
 * Las aldeas natares (capital, Maravillas y asentamientos vivientes) son un
 * objetivo real y saqueable de este mundo, así que la tribu 5 se simula como
 * una aldea de jugador — población, cantero, residencia y catapultas — con dos
 * excepciones: no investiga en la herrería y no levanta muralla.
 */
error_reporting(E_ALL);

class WarsimNatarFormStub {
	public $valuearray = array();
	public $errors = array();

	public function addError($field, $error) {
		$this->errors[$field] = $error;
	}

	public function getValue($field) {
		return array_key_exists($field, $this->valuearray) ? $this->valuearray[$field] : "";
	}
}

class WarsimNatarDatabaseStub {
	public $hero = array();
	public $inventory = array();
	public $items = array();

	public function getHeroData($uid) {
		return $this->hero;
	}

	public function getUnit($id) {
		return false;
	}

	public function getABTech($id) {
		return array();
	}

	public function getHeroInventory($uid) {
		return $this->inventory;
	}

	public function getItemData($id) {
		return isset($this->items[$id]) ? $this->items[$id] : false;
	}
}

function warsimNatarAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

function warsimNatarRender($template, $variables) {
	extract($variables);
	ob_start();
	include dirname(__DIR__).'/Templates/Simulator/'.$template;

	return ob_get_clean();
}

$form = new WarsimNatarFormStub();
$database = new WarsimNatarDatabaseStub();
$session = (object)array('uid' => 7, 'tribe' => 2);
$village = (object)array('wid' => 100, 'pop' => 500);
$database->hero = array('dead' => 0, 'power' => 3, 'itempower' => 45, 'health' => 100, 'offBonus' => 0);

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Battle.php';

$battle = new Battle();

function warsimNatarSimulate($input) {
	global $battle, $form;
	$_POST = array();
	$form->valuearray = array();
	$form->errors = array();
	$battle->procSim($input);

	return isset($_POST['result']) ? $_POST['result'] : false;
}

// ---------------------------------------------------------------- formulario

$warsimSource = file_get_contents(dirname(__DIR__).'/warsim.php');
warsimNatarAssert(
	strpos($warsimSource, 'name="a2_v5"') !== false && strpos($warsimSource, 'TRIBE5') !== false,
	'warsim.php ofrece a los natares como defensor'
);
warsimNatarAssert(
	is_file(dirname(__DIR__).'/Templates/Simulator/def_5.tpl')
		&& is_file(dirname(__DIR__).'/Templates/Simulator/res_d5.tpl'),
	'existen las plantillas de entrada y resultado de la tribu 5'
);

// ------------------------------------------------------------------- procSim

$base = array(
	'a1_v' => 2,
	'a2_v5' => 1,
	'ktyp' => 0,
	'a1_1' => 1000,
	'ew1' => 500,
	'ew2' => 350,
	'kata' => 10,
	'stonemason' => 4,
	'palast' => 0,
	'wall5' => 20
);
for($unit = 41; $unit <= 50; $unit++) {
	$base['a2_'.$unit] = 0;
	$base['f2_'.$unit] = 20;
}
$base['a2_43'] = 1000;

warsimNatarSimulate($base);
warsimNatarAssert($_POST['target'] === array(5), 'acepta un objetivo natar puro');
warsimNatarAssert($form->valuearray['a2_43'] === 1000, 'conserva las tropas natares cargadas');
warsimNatarAssert($form->valuearray['a2_50'] === 0, 'llega hasta la última unidad natar (u50)');
warsimNatarAssert($form->valuearray['f2_43'] === 0, 'ignora la herrería del defensor natar');
warsimNatarAssert($form->valuearray['wall5'] === 0, 'no le da muralla a la aldea natar');
warsimNatarAssert($form->valuearray['ew2'] === 350, 'respeta la población de la aldea natar');
warsimNatarAssert(
	$form->valuearray['kata'] === 10 && $form->valuearray['stonemason'] === 4,
	'mantiene edificio objetivo y cantería como en cualquier aldea'
);

// ------------------------------------------------------------------ defensas

$empty = $base;
$empty['a2_43'] = 0;
$withoutDefenders = warsimNatarSimulate($empty);
$withDefenders = warsimNatarSimulate($base);
warsimNatarAssert(
	$withDefenders['Defend_points'] > $withoutDefenders['Defend_points'],
	'las tropas natares suman defensa en lugar de desaparecer'
);
// 1000 Defensores (u43, di 90, sin mejoras) contra un ataque puramente de infantería.
warsimNatarAssert(
	abs($withDefenders['Defend_points'] - (1000 * 90 + 10)) < 0.001,
	'la defensa natar se calcula con los valores de unitdata'
);
warsimNatarAssert($withDefenders['Winner'] === 'defender', 'una guarnición natar suficiente rechaza el ataque');

$residence = $base;
$residence['palast'] = 15;
$residenceResult = warsimNatarSimulate($residence);
warsimNatarAssert($form->valuearray['palast'] === 15, 'la aldea natar puede tener residencia');
// 2 * nivel^2 de defensa extra, igual que en una aldea de jugador.
warsimNatarAssert(
	abs($residenceResult['Defend_points'] - ($withDefenders['Defend_points'] + 2 * 15 * 15)) < 0.001,
	'la residencia de la aldea natar aporta su defensa fija'
);
warsimNatarAssert($withoutDefenders['Winner'] === 'attacker', 'una aldea natar vacía cae');

$cavalryVsNatar = $base;
$cavalryVsNatar['a1_1'] = 0;
$cavalryVsNatar['a1_6'] = 1000; // Jinetes teutones (u16)
$cavalryResult = warsimNatarSimulate($cavalryVsNatar);
warsimNatarAssert(
	abs($cavalryResult['Defend_points'] - (1000 * 75 + 10)) < 0.001,
	'la defensa natar distingue infantería de caballería'
);

$scouting = $base;
$scouting['a1_1'] = 0;
$scouting['a1_4'] = 100; // Emisarios germanos (u14)
$scouting['a2_43'] = 0;
$scouting['a2_44'] = 50; // Pájaro de Presa
$scoutResult = warsimNatarSimulate($scouting);
warsimNatarAssert(!empty($scoutResult['scouting']), 'reconoce una exploración contra los natares');
warsimNatarAssert(
	abs($scoutResult['Defend_points'] - 50 * 20) < 0.001,
	'el Pájaro de Presa defiende contra los exploradores'
);

$mixed = $base;
$mixed['a2_v1'] = 1;
$mixed['a2_1'] = 500; // Legionarios (di 35)
$mixedResult = warsimNatarSimulate($mixed);
warsimNatarAssert($_POST['target'] === array(1, 5), 'admite una aldea de jugador reforzada por natares');
warsimNatarAssert(
	abs($mixedResult['Defend_points'] - (1000 * 90 + 500 * 35 + 10)) < 0.001,
	'suma la defensa del jugador y la de los natares'
);
$mixedWall = $mixed;
$mixedWall['wall1'] = 20;
warsimNatarSimulate($mixedWall);
warsimNatarAssert(
	$form->valuearray['wall1'] === 20 && $form->valuearray['wall5'] === 0,
	'la muralla la pone la tribu que define la aldea'
);

// ---------------------------------------------------------------- catapultas

$siege = $base;
$siege['a1_1'] = 200000;
$siege['a1_8'] = 500; // Catapultas germanas (u18)
$siegeResult = warsimNatarSimulate($siege);
warsimNatarAssert(
	isset($siegeResult['target_level_after']) && $siegeResult['target_level_after'] < 10,
	'las catapultas derriban edificios de una aldea natar'
);

$oasis = $siege;
unset($oasis['a2_v5']);
$oasis['a2_v4'] = 1;
$oasis['a2_43'] = 0;
$oasisResult = warsimNatarSimulate($oasis);
warsimNatarAssert(!isset($oasisResult['target_level_after']), 'un oasis de Naturaleza sigue sin edificios que derribar');

// --------------------------------------------------------------------- héroe

$heroInput = $base;
$heroInput['a1_hero'] = 1;
unset($heroInput['h_att_power']);
warsimNatarSimulate($heroInput);
$plainPower = $form->valuearray['h_att_power'];
warsimNatarAssert($plainPower === 100 + 80 * 3 + 45, 'la fuerza del héroe sale de sus atributos');
$database->inventory = array('leftHand' => 55);
$database->items = array(55 => array('uid' => 7, 'btype' => 3, 'type' => 81));
warsimNatarSimulate($heroInput);
warsimNatarAssert(
	$form->valuearray['h_att_power'] === (int)($plainPower * 1.30),
	'el cuerno del natariano refuerza al héroe también en el simulador'
);
$hornAgainstOasis = $heroInput;
unset($hornAgainstOasis['a2_v5']);
$hornAgainstOasis['a2_v4'] = 1;
warsimNatarSimulate($hornAgainstOasis);
warsimNatarAssert($form->valuearray['h_att_power'] === $plainPower, 'el cuerno no vale contra otros defensores');
$database->inventory = array();
$database->items = array();

// ---------------------------------------------------------------- plantillas

if(!defined('TRIBE5')) { define('TRIBE5', 'Natares'); }
if(!defined('WARSIM_POP')) { define('WARSIM_POP', 'Población'); }
if(!defined('WARSIM_STONEMASON')) { define('WARSIM_STONEMASON', 'Cantería'); }
if(!defined('WARSIM_PALACE')) { define('WARSIM_PALACE', 'Residencia'); }
if(!defined('WARSIM_ETC')) { define('WARSIM_ETC', 'Otros'); }
if(!defined('WARSIM_WALL1')) { define('WARSIM_WALL1', 'Muralla'); }
if(!defined('WARSIM_WALL2')) { define('WARSIM_WALL2', 'Terraplén'); }
if(!defined('WARSIM_WALL3')) { define('WARSIM_WALL3', 'Empalizada'); }

$technology = new stdClass();
$technology->unarray = array();
for($unit = 1; $unit <= 50; $unit++) {
	$technology->unarray[$unit] = 'u'.$unit;
}
warsimNatarSimulate($base);

$defenderForm = warsimNatarRender('def_5.tpl', array('technology' => $technology, 'form' => $form));
for($unit = 41; $unit <= 50; $unit++) {
	warsimNatarAssert(strpos($defenderForm, 'name="a2_'.$unit.'"') !== false, 'def_5.tpl pide la unidad u'.$unit);
}
warsimNatarAssert(strpos($defenderForm, 'name="f2_4') === false, 'def_5.tpl no ofrece niveles de herrería');
warsimNatarAssert(strpos($defenderForm, 'class="unit u43"') !== false, 'def_5.tpl usa los iconos natares');

$_POST['result'] = array(1 => 0.1, 2 => 0.25);
$defenderResult = warsimNatarRender('res_d5.tpl', array('technology' => $technology, 'form' => $form));
warsimNatarAssert(strpos($defenderResult, '<td>1000</td>') !== false, 'res_d5.tpl muestra las tropas natares');
warsimNatarAssert(strpos($defenderResult, '<td>250</td>') !== false, 'res_d5.tpl reparte las bajas natares');

$others = warsimNatarRender('def_end.tpl', array('target' => array(5), 'form' => $form));
warsimNatarAssert(strpos($others, 'name="wall5"') === false, 'def_end.tpl no pide muralla para los natares');
warsimNatarAssert(strpos($others, 'name="stonemason"') !== false && strpos($others, 'name="palast"') !== false, 'def_end.tpl mantiene cantería y residencia');
warsimNatarAssert(strpos($others, 'readonly') === false, 'def_end.tpl deja editar la población natar');

$playerOthers = warsimNatarRender('def_end.tpl', array('target' => array(1), 'form' => $form));
warsimNatarAssert(strpos($playerOthers, 'name="wall1"') !== false, 'def_end.tpl sigue pidiendo la muralla de un jugador');

echo "Warsim Natar checks passed.\n";
