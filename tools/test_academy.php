<?php
/**
 * Pruebas de la Academia (edificio 22).
 *
 * Uso:  docker compose exec -T web php tools/test_academy.php
 *
 * No toca la base de datos: usa dobles de prueba para $database, $session,
 * $village, $building, $generator y $logging.
 */

if(PHP_SAPI !== 'cli') {
	die("Solo CLI\n");
}
chdir(dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', '0');

/* ------------------------------------------------------------------ *
 * Mini framework de aserciones
 * ------------------------------------------------------------------ */
$TESTS = array('ok' => 0, 'fail' => 0, 'failures' => array());

function check($name, $condition, $detail = '') {
	global $TESTS;
	if($condition) {
		$TESTS['ok']++;
	} else {
		$TESTS['fail']++;
		$TESTS['failures'][] = $name . ($detail !== '' ? "  -> " . $detail : '');
		echo "  FALLA  " . $name . ($detail !== '' ? "  -> " . $detail : '') . "\n";
	}
}

function section($title) {
	echo "\n== " . $title . "\n";
}

/* ------------------------------------------------------------------ *
 * Constantes mínimas del juego
 * ------------------------------------------------------------------ */
if(!defined('SPEED')) { define('SPEED', 1); }
if(!defined('TB_PREFIX')) { define('TB_PREFIX', 's1_'); }
if(!defined('LIMIT_TROOPS')) { define('LIMIT_TROOPS', 0); }
if(!defined('TRAPPER_CAPACITY')) { define('TRAPPER_CAPACITY', 1); }
if(!defined('BANNED')) { define('BANNED', 4); }
for($u = 0; $u <= 50; $u++) {
	if(!defined('U' . $u)) { define('U' . $u, 'U' . $u); }
}
if(!defined('U99')) { define('U99', 'U99'); }

require_once 'GameEngine/Data/buidata.php';
require_once 'GameEngine/Data/resdata.php';
require_once 'GameEngine/Data/unitdata.php';

/* ------------------------------------------------------------------ *
 * Dobles de prueba
 * ------------------------------------------------------------------ */
class StubSession {
	public $tribe = 1;
	public $uid = 1;
	public $access = 1;
	public $plus = 0;
	public $mchecker = 'abcde';
	public $goldclub = 0;
	public $userinfo = array('gold' => 100);
	public $checkerChanged = 0;
	public function changeChecker() { $this->checkerChanged++; }
}

class StubVillage {
	public $wid = 1;
	public $resarray = array();
	public $techarray = array();
	public $researching = array();
	public $unitarray = array();
	public $maxstore = 800000;
	public $maxcrop = 800000;
	public $awood = 500000;
	public $aclay = 500000;
	public $airon = 500000;
	public $acrop = 500000;
	public function getProd($type) { return 1000; }
}

class StubGenerator {
	public function getTimeFormat($sec) { return gmdate('H:i:s', max(0, (int)$sec)); }
	public function procMtime($t) { return array('hoy', date('H:i:s', (int)$t)); }
	public function pageLoadTimeStart() { return microtime(true); }
}

class StubLogging {
	public $techLogs = array();
	public function addTechLog($wid, $tech, $lvl) { $this->techLogs[] = array($wid, $tech, $lvl); }
}

class StubDatabase {
	public $res = array('wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0);
	public $queries = array();
	public $research = array();

	public function modifyResource($vid, $wood, $clay, $iron, $crop, $mode) {
		$sign = $mode ? 1 : -1;
		$this->res['wood'] += $sign * (int)$wood;
		$this->res['clay'] += $sign * (int)$clay;
		$this->res['iron'] += $sign * (int)$iron;
		$this->res['crop'] += $sign * (int)$crop;
		$this->queries[] = "UPDATE " . TB_PREFIX . "vdata set wood = wood " . ($mode ? '+' : '-') . " $wood ... where wref = $vid";
		return true;
	}

	public function deductResourcesIfAvailable($vid, $wood, $clay, $iron, $crop) {
		if($wood < 0 || $clay < 0 || $iron < 0 || $crop < 0) { return false; }
		if($this->res['wood'] < $wood || $this->res['clay'] < $clay
			|| $this->res['iron'] < $iron || $this->res['crop'] < $crop) {
			return false;
		}
		$this->res['wood'] -= $wood;
		$this->res['clay'] -= $clay;
		$this->res['iron'] -= $iron;
		$this->res['crop'] -= $crop;
		return true;
	}

	public function addResearch($vid, $tech, $time) {
		// Misma construcción de query que db_MYSQLi::addResearch()
		$this->queries[] = "INSERT into " . TB_PREFIX . "research values (0,$vid,'$tech',$time)";
		$this->research[] = array('vref' => $vid, 'tech' => $tech, 'timestamp' => $time);
		return true;
	}

	public function getResearching($vid) { return $this->research; }
	public function getUnit($vid) { return array(); }
	public function getTraining($vid) { return array(); }
	public function getActiveArtefactsByType($wid, $uid, $type) { return array(); }
}

/* ------------------------------------------------------------------ *
 * Helpers de escenario
 * ------------------------------------------------------------------ */
/** Construye un fdata con los tipos de edificio pedidos (tipo => nivel). */
function makeResarray(array $types) {
	$res = array();
	for($f = 1; $f <= 18; $f++) {
		$res['f' . $f] = 10;
		$res['f' . $f . 't'] = ($f <= 4) ? $f : (($f % 4) + 1);
	}
	$field = 19;
	foreach($types as $type => $level) {
		$res['f' . $field] = (int)$level;
		$res['f' . $field . 't'] = (int)$type;
		$field++;
	}
	for(; $field <= 40; $field++) {
		$res['f' . $field] = 0;
		$res['f' . $field . 't'] = 0;
	}
	return $res;
}

/** Devuelve el campo donde quedó un tipo de edificio. */
function fieldOfType(array $res, $type) {
	for($f = 19; $f <= 40; $f++) {
		if((int)$res['f' . $f . 't'] === (int)$type) { return $f; }
	}
	return 0;
}

global $session, $village, $database, $generator, $logging, $building, $technology;
$session = new StubSession();
$village = new StubVillage();
$database = new StubDatabase();
$generator = new StubGenerator();
$logging = new StubLogging();

require_once 'GameEngine/Technology.php';   // instancia $technology
require_once 'GameEngine/Building.php';

$buildingReflection = new ReflectionClass('Building');
$building = $buildingReflection->newInstanceWithoutConstructor();

/* ------------------------------------------------------------------ *
 * A. Requisitos de investigación (meetRRequirement)
 *    Fuente de verdad: el texto que muestran las propias plantillas
 *    Templates/Build/22_*.tpl (= tabla oficial de Travian T4).
 *    Formato: unidad => array(nivel academia, tipo edificio, nivel edificio)
 * ------------------------------------------------------------------ */
section('A. Requisitos de investigacion (meetRRequirement)');

$BUILDING_NAMES = array(11 => 'Granero', 12 => 'Herreria', 15 => 'Edificio principal',
	16 => 'Plaza de reuniones', 19 => 'Cuartel', 20 => 'Establo', 21 => 'Taller');

$expected = array(
	1 => array(
		2 => array(1, 12, 1), 3 => array(5, 12, 1), 4 => array(5, 20, 1), 5 => array(5, 20, 5),
		6 => array(15, 20, 10), 7 => array(10, 21, 1), 8 => array(15, 21, 10), 9 => array(20, 16, 10),
	),
	2 => array(
		12 => array(1, 19, 3), 13 => array(3, 12, 1), 14 => array(1, 15, 5), 15 => array(5, 20, 5),
		16 => array(15, 20, 10), 17 => array(10, 21, 1), 18 => array(15, 21, 10), 19 => array(20, 16, 5),
	),
	3 => array(
		22 => array(3, 12, 1), 23 => array(5, 20, 1), 24 => array(5, 20, 3), 25 => array(5, 20, 5),
		26 => array(15, 20, 10), 27 => array(10, 21, 1), 28 => array(15, 21, 10), 29 => array(20, 16, 10),
	),
	4 => array(
		32 => array(1, 19, 3), 33 => array(3, 12, 1), 34 => array(1, 15, 5), 35 => array(5, 20, 5),
		36 => array(15, 20, 10), 37 => array(10, 21, 1), 38 => array(15, 21, 10), 39 => array(20, 16, 5),
	),
	5 => array(
		42 => array(1, 19, 3), 43 => array(3, 12, 1), 44 => array(1, 15, 5), 45 => array(5, 20, 5),
		46 => array(15, 20, 10), 47 => array(10, 21, 1), 48 => array(15, 21, 10), 49 => array(20, 16, 5),
	),
);

/** Levanta un escenario con la academia y el resto de edificios a un nivel dado. */
function scenario($academyLevel, array $overrides = array(), $othersLevel = 20) {
	global $village;
	$types = array(22 => $academyLevel);
	foreach(array(11, 12, 15, 16, 19, 20, 21) as $type) {
		$types[$type] = isset($overrides[$type]) ? $overrides[$type] : $othersLevel;
	}
	$village->resarray = makeResarray($types);
}

foreach($expected as $tribe => $techs) {
	$session->tribe = $tribe;
	foreach($techs as $tech => $req) {
		list($aca, $btype, $blevel) = $req;
		$bname = isset($BUILDING_NAMES[$btype]) ? $BUILDING_NAMES[$btype] : $btype;

		// 1. Con los requisitos exactos debe poder investigarse.
		scenario($aca, array($btype => $blevel));
		check("tribu $tribe u$tech: academia $aca + $bname $blevel => permitido",
			$technology->meetRRequirement($tech) == true);

		// 2. Un nivel menos de academia debe bloquearlo.
		scenario($aca - 1, array($btype => $blevel));
		check("tribu $tribe u$tech: academia " . ($aca - 1) . " => bloqueado",
			!$technology->meetRRequirement($tech));

		// 3. Un nivel menos del edificio secundario debe bloquearlo.
		if($blevel > 0) {
			scenario($aca, array($btype => $blevel - 1));
			check("tribu $tribe u$tech: $bname " . ($blevel - 1) . " => bloqueado",
				!$technology->meetRRequirement($tech));
		}

		// 4. Sin el edificio secundario (resto al maximo) debe bloquearlo:
		//    detecta que se este mirando el edificio equivocado.
		scenario(20, array($btype => 0));
		check("tribu $tribe u$tech: sin $bname => bloqueado (edificio correcto)",
			!$technology->meetRRequirement($tech));
	}
}

/* ------------------------------------------------------------------ *
 * B. Requisitos para construir la Academia (Building::meetRequirement)
 *    La plantilla soon/academy.tpl anuncia: Cuartel 3 + Edificio principal 3.
 * ------------------------------------------------------------------ */
section('B. Requisitos de construccion de la Academia');

$meetRequirement = $buildingReflection->getMethod('meetRequirement');
$meetRequirement->setAccessible(true);

$village->resarray = makeResarray(array(15 => 3, 19 => 3, 16 => 0));
check('academia: EP 3 + cuartel 3 => construible',
	$meetRequirement->invoke($building, 22) == true);

$village->resarray = makeResarray(array(15 => 3, 19 => 0, 16 => 20));
check('academia: sin cuartel => no construible',
	!$meetRequirement->invoke($building, 22));

$village->resarray = makeResarray(array(15 => 2, 19 => 20, 16 => 20));
check('academia: EP 2 => no construible',
	!$meetRequirement->invoke($building, 22));

/* ------------------------------------------------------------------ *
 * C. Render de las plantillas 22_1..22_5 sin errores de PHP
 * ------------------------------------------------------------------ */
section('C. Render de plantillas Templates/Build/22_*.tpl');

$RENDER_ERRORS = array();
set_error_handler(function($no, $str, $file, $line) {
	global $RENDER_ERRORS;
	$RENDER_ERRORS[] = basename($file) . ":$line  $str";
	return true;
});

function renderAcademy($tribe, $academyLevel, $gold, array $overrides = array()) {
	global $session, $village, $building, $technology, $generator, $id, $RENDER_ERRORS;
	// build.php incluye las plantillas en el ambito global: replicamos ese ambito
	// para que $r2, $r12, $bid22, etc. esten disponibles igual que en el juego.
	extract($GLOBALS, EXTR_SKIP);
	$session->tribe = $tribe;
	$session->userinfo['gold'] = $gold;
	scenario($academyLevel, $overrides);
	$id = fieldOfType($village->resarray, 22);
	$village->researching = array();
	$village->techarray = array();
	for($t = 1; $t <= 50; $t++) { $village->techarray['t' . $t] = 0; }
	$RENDER_ERRORS = array();
	ob_start();
	try {
		include 'Templates/Build/22_' . $tribe . '.tpl';
		$html = ob_get_clean();
	} catch (Throwable $e) {
		ob_end_clean();
		return array(null, array('FATAL: ' . $e->getMessage()));
	}
	return array($html, $RENDER_ERRORS);
}

foreach(array(1, 2, 3, 4, 5) as $tribe) {
	// Academia al maximo, con oro (activa la rama del intercambio NPC).
	list($html, $errors) = renderAcademy($tribe, 20, 100);
	check("22_$tribe.tpl: renderiza con academia 20 y oro sin errores",
		empty($errors) && $html !== null, implode(' | ', array_slice($errors, 0, 3)));
	check("22_$tribe.tpl: muestra el boton Investigar",
		$html !== null && strpos($html, 'Investigar') !== false);

	// Academia baja: debe mostrar la lista de requisitos futuros.
	list($html2, $errors2) = renderAcademy($tribe, 1, 0, array(12 => 0, 19 => 0, 20 => 0, 21 => 0, 15 => 0, 16 => 0));
	check("22_$tribe.tpl: renderiza con academia 1 sin errores",
		empty($errors2) && $html2 !== null, implode(' | ', array_slice($errors2, 0, 3)));

	// Sin edificios auxiliares ninguna unidad es investigable todavia:
	// las 8 deben aparecer en el desplegable de requisitos futuros.
	$missing = array();
	for($unit = ($tribe - 1) * 10 + 2; $unit <= ($tribe - 1) * 10 + 9; $unit++) {
		if($html2 === null || strpos($html2, 'unit u' . $unit . '"') === false) {
			$missing[] = 'u' . $unit;
		}
	}
	check("22_$tribe.tpl: la lista de requisitos futuros incluye las 8 unidades",
		empty($missing), 'faltan: ' . implode(',', $missing));
}

// Todas las unidades investigadas => no debe ofrecer "Mas" con requisitos.
$session->tribe = 1;
$session->userinfo['gold'] = 0;
scenario(20);
$id = fieldOfType($village->resarray, 22);
$village->researching = array();
$village->techarray = array();
for($t = 1; $t <= 50; $t++) { $village->techarray['t' . $t] = 1; }
$RENDER_ERRORS = array();
ob_start();
include 'Templates/Build/22_1.tpl';
$htmlAll = ob_get_clean();
check('22_1.tpl: con todo investigado no aparece el desplegable "Mas"',
	strpos($htmlAll, 'researchFutureLink') === false);
check('22_1.tpl: con todo investigado avisa que no hay investigaciones',
	strpos($htmlAll, 'No hay investigaciones disponibles') !== false);

restore_error_handler();

/* ------------------------------------------------------------------ *
 * D. Validaciones del servidor al investigar (Technology::procTechno)
 * ------------------------------------------------------------------ */
section('D. Validaciones de researchTech');

/** Prepara un escenario de investigacion y ejecuta procTechno(). */
function research($tribe, array $get, array $resources, array $techarray = array(), array $running = array()) {
	global $session, $village, $database, $logging;
	$session->tribe = $tribe;
	$session->mchecker = 'abcde';
	scenario(20);
	$village->techarray = array();
	for($t = 1; $t <= 50; $t++) { $village->techarray['t' . $t] = 0; }
	foreach($techarray as $k => $v) { $village->techarray[$k] = $v; }
	$village->researching = $running;
	$database->res = $resources;
	$database->queries = array();
	$database->research = $running;
	$logging->techLogs = array();
	$get['id'] = fieldOfType($village->resarray, 22);
	global $technology;
	$technology->procTechno($get);
	return $database->research;
}

$rich = array('wood' => 500000, 'clay' => 500000, 'iron' => 500000, 'crop' => 500000);
$broke = array('wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0);

// D1. Caso feliz.
$out = research(1, array('a' => 2, 'c' => 'abcde'), $rich);
check('D1 con recursos suficientes se encola la investigacion', count($out) === 1);
check('D1 se cobran los recursos',
	$database->res['iron'] === 500000 - $r2['iron'], 'hierro=' . $database->res['iron']);

// D2. Checker invalido.
$out = research(1, array('a' => 2, 'c' => 'xxxxx'), $rich);
check('D2 con checker invalido no se investiga', count($out) === 0);

// D3. Sin recursos no debe investigarse ni quedar saldo negativo.
$out = research(1, array('a' => 2, 'c' => 'abcde'), $broke);
check('D3 sin recursos no se encola la investigacion', count($out) === 0);
check('D3 sin recursos los almacenes no quedan en negativo',
	$database->res['wood'] >= 0 && $database->res['clay'] >= 0
	&& $database->res['iron'] >= 0 && $database->res['crop'] >= 0,
	json_encode($database->res));

// D4. Tecnologia ya investigada.
$out = research(1, array('a' => 2, 'c' => 'abcde'), $rich, array('t2' => 1));
check('D4 no se puede investigar dos veces la misma unidad', count($out) === 0);
check('D4 no se cobran recursos por reinvestigar', $database->res['iron'] === 500000);

// D5. Ya hay una investigacion en curso (la interfaz lo bloquea; el servidor tambien debe).
$running = array(array('vref' => 1, 'tech' => 't3', 'timestamp' => time() + 3600));
$out = research(1, array('a' => 2, 'c' => 'abcde'), $rich, array(), $running);
check('D5 no se encolan dos investigaciones a la vez', count($out) === 1);

// D6. Tecnologia de otra tribu.
$out = research(1, array('a' => 13, 'c' => 'abcde'), $rich);
check('D6 un romano no puede investigar unidades de otra tribu', count($out) === 0);

// D7. Inyeccion SQL por el parametro a.
$payload = "2') , (0,1,'t9'," . (time() + 1) . ") -- ";
$out = research(1, array('a' => $payload, 'c' => 'abcde'), $rich);
$inject = '';
foreach($database->queries as $q) {
	if(strpos($q, "-- ") !== false || strpos($q, "') ,") !== false) { $inject = $q; }
}
check('D7 el parametro a no permite inyectar SQL', $inject === '', $inject);
check('D7 un id de tecnologia invalido no encola nada', count($out) === 0);

// D8. Tecnologia fuera de rango.
$out = research(1, array('a' => 999, 'c' => 'abcde'), $rich);
check('D8 una tecnologia inexistente no encola nada', count($out) === 0);

/* ------------------------------------------------------------------ *
 * E. calculateAvaliable: no debe dividir por cero
 *    (aldea con produccion de cereal 0 o negativa)
 * ------------------------------------------------------------------ */
section('E. calculateAvaliable con produccion nula');

class StarvingVillage extends StubVillage {
	public function getProd($type) { return $type === 'crop' ? 0 : 1000; }
}

$normalVillage = $village;
$village = new StarvingVillage();
$village->awood = $village->aclay = $village->airon = $village->acrop = 0;
$calcErrors = array();
set_error_handler(function($no, $str) use (&$calcErrors) { $calcErrors[] = $str; return true; });
$out = $technology->calculateAvaliable(2);
restore_error_handler();
check('E1 sin produccion de cereal no hay division por cero', empty($calcErrors),
	implode(' | ', $calcErrors));
check('E2 devuelve una fecha valida', is_array($out) && isset($out[1]) && $out[1] !== '');
$village = $normalVillage;

/* ------------------------------------------------------------------ *
 * Resumen
 * ------------------------------------------------------------------ */
echo "\n----------------------------------------\n";
echo "OK: " . $TESTS['ok'] . "   FALLAS: " . $TESTS['fail'] . "\n";
if($TESTS['fail'] > 0) {
	echo "\nResumen de fallas:\n";
	foreach($TESTS['failures'] as $f) { echo " - $f\n"; }
	exit(1);
}
exit(0);
