<?php
/**
 * El botón "simular" de un informe (`warsim.php?bid=`) tiene que abrir el simulador con
 * el combate ya cargado: las tropas que el espionaje encontró del lado defensor y un
 * atacante utilizable del lado propio.
 *
 * El informe es un CSV de ancho fijo hasta la posición 150 (ver Automation) y este
 * checker lo arma con la misma disposición, así que también cubre el mapeo de índices.
 */
error_reporting(E_ALL);

class WarsimReportFormStub {
	public $valuearray = array();
	public $errors = array();

	public function addError($field, $error) {
		$this->errors[$field] = $error;
	}

	public function getValue($field) {
		return array_key_exists($field, $this->valuearray) ? $this->valuearray[$field] : "";
	}
}

class WarsimReportDatabaseStub {
	public $notices = array();
	public $units = array();
	public $upgrades = array();
	public $population = array();
	public $hero = array('dead' => 0, 'power' => 0, 'itempower' => 0, 'health' => 100, 'offBonus' => 0);

	public function getAuthorizedNotice($uid, $alliance, $id) {
		return isset($this->notices[$id]) ? $this->notices[$id] : false;
	}

	public function getUnit($wref) {
		return isset($this->units[$wref]) ? $this->units[$wref] : false;
	}

	public function getABTech($wref) {
		return isset($this->upgrades[$wref]) ? $this->upgrades[$wref] : array();
	}

	public function getVillageField($wref, $field) {
		return $field === 'pop' && isset($this->population[$wref]) ? $this->population[$wref] : 0;
	}

	public function getHeroData($uid) {
		return $this->hero;
	}
}

function warsimReportAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

/**
 * Arma el prefijo de ancho fijo de un informe:
 *   0..2 atacante · 3..13 enviadas · 14..24 bajas · 25..29 botín
 *   30..35 defensor · 36+(t-1)*23 bloque por tribu (bandera, 10 tropas, héroe, 10 bajas + héroe)
 */
function warsimReportData($options) {
	$fields = array_fill(0, 151, '0');
	$fields[0] = (string)$options['attacker_uid'];
	$fields[1] = (string)$options['attacker_wref'];
	$fields[2] = (string)$options['attacker_tribe'];
	foreach($options['attacker_units'] as $position => $amount) {
		$fields[2 + $position] = (string)$amount;
	}
	$fields[13] = (string)(isset($options['attacker_hero']) ? $options['attacker_hero'] : 0);
	$fields[30] = (string)$options['defender_uid'];
	$fields[31] = (string)$options['defender_wref'];
	$fields[32] = $options['defender_name'];
	$fields[33] = (string)$options['village_tribe'];
	$fields[34] = '';
	$fields[35] = '';
	foreach($options['defenders'] as $tribe => $troops) {
		$block = 36 + ($tribe - 1) * 23;
		$fields[$block] = '1';
		foreach($troops as $position => $amount) {
			$fields[$block + $position] = (string)$amount;
		}
		if(isset($options['defender_heroes'][$tribe])) {
			$fields[$block + 11] = (string)$options['defender_heroes'][$tribe];
		}
	}
	$data = implode(',', $fields);
	if(!empty($options['spy'])) {
		$data .= ',47, No hay muralla que destruir.,,,,'
			.'<th>Defensas</th><td><img class="gebIcon g'.$options['spy']['residence_type'].'Icon">Residencia <b> Nivel '
			.$options['spy']['residence'].'</b><Br><img class="gebIcon g3'.$options['village_tribe'].'Icon">Muralla <b>Nivel '
			.$options['spy']['wall'].'</b>';
	}

	return $data.',trap-data-v1,0,0,0,0,0,0,0,0,0,0,0';
}

$form = new WarsimReportFormStub();
$database = new WarsimReportDatabaseStub();
$session = (object)array('uid' => 7, 'tribe' => 3, 'alliance' => 0);
$village = (object)array('wid' => 100, 'pop' => 480);

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Battle.php';

$battle = new Battle();

$database->units[100] = array();
for($unit = 21; $unit <= 30; $unit++) {
	$database->units[100]['u'.$unit] = 0;
}
$database->units[100]['u21'] = 900;  // Falanges
$database->units[100]['u23'] = 40;   // Batidores (exploradores)
$database->units[100]['u24'] = 250;  // Rayos de Teutates
$database->upgrades[100] = array('b1' => 5, 'b4' => 12, 'b8' => 3);
$database->population[8100] = 1750;

$scoutReport = array(
	'attacker_uid' => 7,
	'attacker_wref' => 100,
	'attacker_tribe' => 3,
	'attacker_units' => array(3 => 5), // solo Batidores
	'defender_uid' => 2,
	'defender_wref' => 8100,
	'defender_name' => 'Capital natar',
	'village_tribe' => 5,
	'defenders' => array(5 => array(1 => 940, 3 => 1200, 4 => 60, 6 => 380, 7 => 95))
);
$database->notices[31] = array('data' => warsimReportData($scoutReport));

$input = $battle->getReportSimulationInput(31);
warsimReportAssert(is_array($input), 'carga un informe autorizado');
warsimReportAssert(!empty($input['a2_v5']), 'marca a los natares como defensor');
warsimReportAssert(
	$input['a2_41'] === 940 && $input['a2_43'] === 1200 && $input['a2_44'] === 60
		&& $input['a2_46'] === 380 && $input['a2_47'] === 95,
	'precarga las tropas natares que vio el espionaje'
);
warsimReportAssert($input['a2_42'] === 0 && $input['a2_50'] === 0, 'deja en cero las unidades natares que no aparecieron');
warsimReportAssert($input['a2_village'] === 5, 'la aldea es la del informe, no la primera tribu marcada');
warsimReportAssert(empty($input['a2_v1']) && empty($input['a2_v4']), 'no marca tribus sin tropas');
warsimReportAssert($input['ew2'] === 1750, 'usa la población de la aldea atacada');
warsimReportAssert($input['ktyp'] === 0, 'arranca como ataque normal');

warsimReportAssert($input['a1_v'] === 3, 'el atacante es la tribu del informe');
warsimReportAssert(
	$input['a1_1'] === 900 && $input['a1_4'] === 250 && $input['a1_3'] === 0,
	'con un informe de espionaje precarga el ejército de la aldea actual, sin exploradores'
);
warsimReportAssert($input['f1_1'] === 5 && $input['f1_4'] === 12 && $input['f1_8'] === 3, 'precarga la herrería propia');
warsimReportAssert($input['a1_hero'] === 1, 'incluye al héroe');

// --------------------------------------------------- informe de ataque de verdad

$attackReport = $scoutReport;
$attackReport['attacker_units'] = array(1 => 700, 4 => 120, 8 => 40);
$attackReport['attacker_hero'] = 1;
$database->notices[32] = array('data' => warsimReportData($attackReport));
$attackInput = $battle->getReportSimulationInput(32);
warsimReportAssert(
	$attackInput['a1_1'] === 700 && $attackInput['a1_4'] === 120 && $attackInput['a1_8'] === 40,
	'un informe de ataque precarga las tropas que se enviaron'
);
warsimReportAssert($attackInput['a1_hero'] === 1, 'respeta el héroe del informe');

$heroLessReport = $attackReport;
$heroLessReport['attacker_hero'] = 0;
$database->notices[33] = array('data' => warsimReportData($heroLessReport));
warsimReportAssert($battle->getReportSimulationInput(33)['a1_hero'] === 0, 'no inventa un héroe que no fue');

// ------------------------------------------------- el jugador es el defensor

$natarWave = array(
	'attacker_uid' => 2,
	'attacker_wref' => 8100,
	'attacker_tribe' => 5,
	'attacker_units' => array(2 => 350, 5 => 77),
	'defender_uid' => 7,
	'defender_wref' => 100,
	'defender_name' => 'Mi aldea',
	'village_tribe' => 3,
	'defenders' => array(3 => array(1 => 500, 2 => 120)),
	'defender_heroes' => array(3 => 1)
);
$database->notices[34] = array('data' => warsimReportData($natarWave));
$waveInput = $battle->getReportSimulationInput(34);
warsimReportAssert($waveInput['a1_v'] === 3, 'una oleada natar no se puede simular como atacante: cae en la tribu propia');
warsimReportAssert($waveInput['a1_1'] === 900 && $waveInput['a1_3'] === 0, 'y precarga el ejército propio');
warsimReportAssert($waveInput['a2_21'] === 500 && $waveInput['a2_22'] === 120, 'carga a los defensores del informe');
warsimReportAssert($waveInput['a2_hero_3'] === 1, 'marca al héroe defensor');

// ------------------------------------------------------ aldea vs refuerzo

$reinforced = array(
	'attacker_uid' => 7,
	'attacker_wref' => 100,
	'attacker_tribe' => 3,
	'attacker_units' => array(1 => 800),
	'defender_uid' => 12,
	'defender_wref' => 8200,
	'defender_name' => 'Aldea gala',
	'village_tribe' => 3,
	'defenders' => array(
		1 => array(1 => 300),
		3 => array(1 => 450)
	),
	'spy' => array('residence' => 12, 'residence_type' => 26, 'wall' => 18)
);
$database->population[8200] = 640;
$database->notices[35] = array('data' => warsimReportData($reinforced));
$reinforcedInput = $battle->getReportSimulationInput(35);
warsimReportAssert(
	!empty($reinforcedInput['a2_v1']) && !empty($reinforcedInput['a2_v3']),
	'marca a la aldea y a su refuerzo'
);
warsimReportAssert($reinforcedInput['a2_village'] === 3, 'la aldea sigue siendo la del informe aunque el refuerzo tenga tribu menor');
warsimReportAssert($reinforcedInput['palast'] === 12 && $reinforcedInput['wall3'] === 18, 'lee residencia y muralla del bloque de espionaje');

$_POST = array();
$form->valuearray = array();
$battle->procSim($reinforcedInput);
warsimReportAssert($_POST['village_tribe'] === 3, 'procSim respeta la pista de la aldea');
warsimReportAssert($form->valuearray['wall3'] === 18 && $form->valuearray['wall1'] === 0, 'la muralla es la de la aldea, no la del refuerzo');
warsimReportAssert(
	abs($_POST['result']['Defend_points'] - ((300 * 35 + 450 * 40 + 2 * 12 * 12) * pow(1.025, 18) + 18 * 8 + 10)) < 0.5,
	'la defensa suma aldea, refuerzo, residencia y muralla'
);

$_POST = array();
$form->valuearray = array();
$battle->procSim($input);
warsimReportAssert($_POST['target'] === array(5), 'el escenario natar se simula tal cual llega');
warsimReportAssert($_POST['result']['Defend_points'] > 100000, 'la guarnición natar del informe pesa en el resultado');

// ----------------------------------------------------------------- rechazos

warsimReportAssert($battle->getReportSimulationInput(999) === false, 'rechaza un informe inexistente');
warsimReportAssert($battle->getReportSimulationInput(0) === false, 'rechaza un id vacío');
$database->notices[36] = array('data' => '7,100,3,0,0');
warsimReportAssert($battle->getReportSimulationInput(36) === false, 'rechaza un informe truncado');
$empty = $scoutReport;
$empty['defenders'] = array();
$empty['village_tribe'] = 0;
$database->notices[37] = array('data' => warsimReportData($empty));
warsimReportAssert($battle->getReportSimulationInput(37) === false, 'rechaza un informe sin ningún defensor');

$warsimSource = file_get_contents(dirname(__DIR__).'/warsim.php');
warsimReportAssert(
	strpos($warsimSource, "getReportSimulationInput(\$_GET['bid'])") !== false,
	'warsim.php atiende el parámetro bid del botón del informe'
);
warsimReportAssert(
	strpos($warsimSource, 'name="a2_village"') !== false,
	'warsim.php conserva la aldea defensora al reenviar el formulario'
);
$reportButtons = 0;
foreach(glob(dirname(__DIR__).'/Templates/Notice/*.tpl') as $template) {
	if(strpos(file_get_contents($template), 'warsim.php?bid=') !== false) {
		$reportButtons++;
	}
}
warsimReportAssert($reportButtons > 0, 'los informes siguen enlazando al simulador con bid');

echo "Warsim report checks passed.\n";
