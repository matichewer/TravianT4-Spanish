<?php
error_reporting(E_ALL);

class OasisSimulationFormStub {
	public $valuearray = array();
	private $errors = array();

	public function addError($field, $error) {
		$this->errors[$field] = $error;
	}
}

class OasisSimulationDatabaseStub {
	public $tiles = array();
	public $units = array();
	public $hero = array();
	public $unitReads = array();

	public function getOMInfo($id) {
		return isset($this->tiles[$id]) ? $this->tiles[$id] : false;
	}

	public function getUnit($id) {
		$this->unitReads[] = (int)$id;
		return isset($this->units[$id]) ? $this->units[$id] : false;
	}

	public function getHeroData($uid) {
		return $this->hero;
	}
}

function oasisSimulationAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

$form = new OasisSimulationFormStub();
$database = new OasisSimulationDatabaseStub();
$session = (object)array('uid' => 7, 'tribe' => 2);
$village = (object)array('wid' => 100, 'pop' => 321);

$database->tiles = array(
	900 => array('oasistype' => 1, 'fieldtype' => 0, 'occupied' => 0),
	901 => array('oasistype' => 1, 'fieldtype' => 0, 'occupied' => 1),
	902 => array('oasistype' => 0, 'fieldtype' => 1, 'occupied' => 1)
);
$database->units[100] = array(
	'u11' => 12,
	'u12' => 7,
	'u13' => 0,
	'u14' => 0,
	'u15' => 2,
	'u16' => 0,
	'u17' => 0,
	'u18' => 0,
	'u19' => 1,
	'u20' => 3,
	'hero' => 1
);
$database->units[900] = array(
	'u31' => 3,
	'u32' => 0,
	'u33' => 4,
	'u34' => 0,
	'u35' => 0,
	'u36' => 1,
	'u37' => 0,
	'u38' => 0,
	'u39' => 0,
	'u40' => 2
);
$database->hero = array(
	'dead' => 0,
	'power' => 3,
	'itempower' => 45,
	'health' => 77,
	'offBonus' => 25
);

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Battle.php';

$warsimSource = file_get_contents(dirname(__DIR__).'/warsim.php');
oasisSimulationAssert(
	strpos($warsimSource, 'getOasisSimulationInput') !== false
		&& strpos($warsimSource, 'procSim($simulationInput)') !== false,
	'warsim.php procesa el parámetro oasis mediante el escenario precargado'
);

$battle = new Battle();
$input = $battle->getOasisSimulationInput(900);

oasisSimulationAssert(is_array($input), 'acepta un oasis desocupado');
oasisSimulationAssert($input['a1_v'] === 2 && $input['a2_v4'] === 1 && $input['ktyp'] === 1, 'configura Germanos contra Naturaleza como saqueo');
oasisSimulationAssert($input['a1_1'] === 12 && $input['a1_2'] === 7 && $input['a1_10'] === 3, 'mapea todas las tropas de la aldea seleccionada');
oasisSimulationAssert($input['a1_hero'] === 1, 'incluye al héroe vivo presente en la aldea');
oasisSimulationAssert($input['a2_31'] === 3 && $input['a2_33'] === 4 && $input['a2_40'] === 2, 'mapea los animales actuales del oasis');

$database->unitReads = array();
oasisSimulationAssert($battle->getOasisSimulationInput(901) === false, 'rechaza un oasis ocupado');
oasisSimulationAssert($battle->getOasisSimulationInput(902) === false, 'rechaza una casilla que no es oasis');
oasisSimulationAssert($battle->getOasisSimulationInput(999) === false, 'rechaza un objetivo inexistente');
oasisSimulationAssert(count($database->unitReads) === 0, 'no lee unidades de objetivos inválidos');

$database->units[100]['hero'] = 0;
$withoutHero = $battle->getOasisSimulationInput(900);
oasisSimulationAssert($withoutHero['a1_hero'] === 0, 'excluye al héroe que no está en la aldea');
$database->units[100]['hero'] = 1;
$database->hero['dead'] = 1;
$deadHero = $battle->getOasisSimulationInput(900);
oasisSimulationAssert($deadHero['a1_hero'] === 0, 'excluye al héroe muerto');
$database->hero['dead'] = 0;

$_POST = array();
$form->valuearray = array();
$battle->procSim($input);
oasisSimulationAssert(isset($_POST['result']), 'calcula automáticamente el escenario precargado');
oasisSimulationAssert($form->valuearray['a1_1'] === 12 && $form->valuearray['a2_31'] === 3, 'conserva las cantidades precargadas');

$editedInput = $input;
$editedInput['displayed_attacker'] = 2;
$editedInput['displayed_targets'] = '4';
$editedInput['a1_1'] = 25;
$editedInput['a2_31'] = 9;
$_POST = array();
$form->valuearray = array();
$battle->procSim($editedInput);
oasisSimulationAssert($form->valuearray['a1_1'] === 25 && $form->valuearray['a2_31'] === 9, 'conserva las cantidades editadas al volver a simular');

echo "Oasis simulation checks passed.\n";
