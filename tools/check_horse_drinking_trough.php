<?php
// Regresión del Abrevadero (edificio 41, sólo romano):
//   A. Requisitos de construcción (Plaza de reuniones 10, Establo 20, sólo tribu 1, único por aldea).
//   B. Descuento de consumo de cereal (Equites Legati/Imperatoris/Caesaris en niveles 10/15/20).
//   C. El descuento de consumo usa la aldea correcta (propia, de refuerzo o la pasada explícitamente),
//      no la aldea activa de quien mira la pantalla — bug real que mostraba el consumo de cereal
//      equivocado en el informe de refuerzo enviado (Notice/8.tpl) y en la lista de "Refuerzos"
//      enviados de la Plaza de reuniones (Build/16.tpl) cuando el jugador tiene más de una aldea.
//   D. Los informes viejos de aldeas eliminadas no generan warnings al buscar el Abrevadero.
//   E. El Abrevadero acelera el entrenamiento sólo en Establo/Gran establo (cobertura mínima; la
//      cobertura completa contra trainUnit() vive en check_hero_training_helmets.php).
//   F. Sanidad de la tabla de datos (Data/buidata.php $bid41) contra el texto mostrado al jugador.
//
//   docker compose exec -T web php /var/www/html/tools/check_horse_drinking_trough.php

error_reporting(E_ALL);

for($i = 1; $i <= 50; $i++) {
	if(!defined('U'.$i)) {
		define('U'.$i,'Unit '.$i);
	}
}
define('U99','Trap');
define('U0','Hero');
define('BANNED',0);
define('SPEED',1);
define('TRAPPER_CAPACITY',1);
define('ALLOW_ALL_TRIBE',false);
define('BASIC_MAX',2);
define('INNER_MAX',2);
define('PLUS_MAX',1);
define('TB_PREFIX','trough_test_');

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Technology.php';
require dirname(__DIR__).'/GameEngine/Building.php';

$errors = array();
function troughAssert($condition,$message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

// ------------------------------------------------------------------------------
// Helpers comunes
// ------------------------------------------------------------------------------

function emptyResarray() {
	$resarray = array();
	for($field = 1; $field <= 40; $field++) {
		$resarray['f'.$field.'t'] = 0;
		$resarray['f'.$field] = 0;
	}
	return $resarray;
}

class TroughDatabaseStub {
	public $jobs = array();
	public function getJobs($wid) { return $this->jobs; }
	public function getResourceLevel($wid) { return null; }
}

class TroughSessionStub {
	public $tribe = 1;
	public $plus = 0;
}

class TroughVillageStub {
	public $wid = 1;
	public $resarray = array();
}

$database = new TroughDatabaseStub();
$session = new TroughSessionStub();
$village = new TroughVillageStub();
$village->resarray = emptyResarray();

$meetRequirement = new ReflectionMethod('Building','meetRequirement');
$meetRequirement->setAccessible(true);
$isTribeAllowed = new ReflectionMethod('Building','isTribeBuildingAllowed');
$isTribeAllowed->setAccessible(true);

function makeBuilding($queuedJobs = array()) {
	global $database;
	$database->jobs = $queuedJobs;
	return new Building();
}

// ------------------------------------------------------------------------------
// A. Requisitos de construcción
// ------------------------------------------------------------------------------

// Plaza de reuniones (16) en el campo 39, Establo (20) en el campo 20: la convención real del juego.
function setPrereqs($rallyLevel,$stableLevel,$troughField = 0,$troughLevel = 0) {
	global $village;
	$village->resarray = emptyResarray();
	$village->resarray['f39t'] = 16;
	$village->resarray['f39'] = $rallyLevel;
	$village->resarray['f20t'] = 20;
	$village->resarray['f20'] = $stableLevel;
	if($troughField > 0) {
		$village->resarray['f'.$troughField.'t'] = 41;
		$village->resarray['f'.$troughField] = $troughLevel;
	}
}

$session->tribe = 1;

setPrereqs(9,20);
$building = makeBuilding();
troughAssert(
	$meetRequirement->invoke($building,41) === false,
	'Plaza de reuniones 9 (falta 1) no debería habilitar el Abrevadero'
);

setPrereqs(10,19);
$building = makeBuilding();
troughAssert(
	$meetRequirement->invoke($building,41) === false,
	'Establo 19 (falta 1) no debería habilitar el Abrevadero'
);

setPrereqs(10,20);
$building = makeBuilding();
troughAssert(
	$meetRequirement->invoke($building,41) === true,
	'Plaza de reuniones 10 + Establo 20 debería habilitar el Abrevadero'
);

setPrereqs(20,20);
$building = makeBuilding();
troughAssert(
	$meetRequirement->invoke($building,41) === true,
	'Niveles por encima del mínimo también deberían habilitar el Abrevadero'
);

// Tribu: sólo romano (1), sin importar que el resto de los requisitos se cumplan.
setPrereqs(10,20);
foreach(array(1,2,3,4,5) as $tribe) {
	$session->tribe = $tribe;
	$building = makeBuilding();
	$expected = ($tribe === 1);
	troughAssert(
		$isTribeAllowed->invoke($building,41) === $expected,
		"isTribeBuildingAllowed(41) para tribu $tribe debería ser ".var_export($expected,true)
	);
	troughAssert(
		$meetRequirement->invoke($building,41) === $expected,
		"meetRequirement(41) para tribu $tribe debería ser ".var_export($expected,true)
	);
}
$session->tribe = 1;

// Único por aldea: ya construido en cualquier campo, o encolado, bloquea uno nuevo.
setPrereqs(10,20,30,5);
$building = makeBuilding();
troughAssert(
	$meetRequirement->invoke($building,41) === false,
	'Un Abrevadero ya construido no debería permitir levantar un segundo'
);

setPrereqs(10,20);
$building = makeBuilding(array(
	array('field' => 25,'type' => 41,'level' => 1,'loopcon' => 0,'master' => 0,'timestamp' => time())
));
troughAssert(
	$meetRequirement->invoke($building,41) === false,
	'Un Abrevadero ya encolado no debería permitir encolar un segundo'
);

// ------------------------------------------------------------------------------
// B. Descuento de consumo de cereal por nivel (Equites Legati/Imperatoris/Caesaris)
// ------------------------------------------------------------------------------

class TroughBuildingStub {
	public $currentLevel = 0;
	public $byVid = array();
	public function getTypeLevel($tid,$vid = 0) {
		if((int)$vid === 0) {
			return $this->currentLevel;
		}
		return isset($this->byVid[$vid][$tid]) ? $this->byVid[$vid][$tid] : 0;
	}
}

$technology = new Technology();
$village = (object)array('wid' => 100);
$building = new TroughBuildingStub();

function baseUnits() {
	return array('u4' => 1,'u5' => 1,'u6' => 1,'u1' => 1,'u2' => 1,'u3' => 1,'u7' => 1,'u8' => 1,'u9' => 1,'u10' => 1);
}

$p4 = $GLOBALS['u4']['pop'];
$p5 = $GLOBALS['u5']['pop'];
$p6 = $GLOBALS['u6']['pop'];
$others = $GLOBALS['u1']['pop']+$GLOBALS['u2']['pop']+$GLOBALS['u3']['pop']
	+$GLOBALS['u7']['pop']+$GLOBALS['u8']['pop']+$GLOBALS['u9']['pop']+$GLOBALS['u10']['pop'];
$fullUpkeep = $p4+$p5+$p6+$others;

$thresholds = array(
	0  => array(0,0,0),
	9  => array(0,0,0),
	10 => array(1,0,0),
	14 => array(1,0,0),
	15 => array(1,1,0),
	19 => array(1,1,0),
	20 => array(1,1,1),
	25 => array(1,1,1), // por si algún día se agrega un nivel 21+, no debería descontar de más
);
foreach($thresholds as $level => $discounts) {
	$building->currentLevel = $level;
	list($d4,$d5,$d6) = $discounts;
	$expected = ($p4-$d4)+($p5-$d5)+($p6-$d6)+$others;
	$got = $technology->getUpkeep(baseUnits(),1,100);
	troughAssert(
		$got === $expected,
		"Abrevadero nivel $level: consumo esperado $expected, obtenido $got"
	);
}
$building->currentLevel = 0;

// El descuento nunca deja el consumo de una unidad en negativo.
$originalU4Pop = $GLOBALS['u4']['pop'];
$GLOBALS['u4']['pop'] = 1;
$building->currentLevel = 20;
$got = $technology->getUpkeep(array('u4' => 1),1,100);
troughAssert($got === 0,"con población 1 y Abrevadero 20 el consumo de u4 no debería bajar de 0 (obtenido $got)");
$GLOBALS['u4']['pop'] = $originalU4Pop;
$building->currentLevel = 0;

// El Abrevadero no toca otras unidades ni otras tribus.
$building->currentLevel = 20;
troughAssert(
	$technology->getUpkeep(array('u11' => 5),2,100) === $GLOBALS['u11']['pop']*5,
	'El Abrevadero no debería afectar unidades de otra tribu (u11)'
);
troughAssert(
	$technology->getUpkeep(array('u1' => 5),1,100) === $GLOBALS['u1']['pop']*5,
	'El Abrevadero no debería afectar infantería romana (u1)'
);
$building->currentLevel = 0;

// ------------------------------------------------------------------------------
// C. La aldea correcta: propia, de refuerzo (array['vref']) o pasada por parámetro
// ------------------------------------------------------------------------------

$building->currentLevel = 0;          // la aldea activa (100) no tiene Abrevadero
$building->byVid = array(
	100 => array(41 => 15),           // si se consultara por vid explícito, level 15 (para detectar mezclas)
	200 => array(41 => 20),           // aldea de refuerzo/objetivo: Abrevadero nivel 20
	300 => array(41 => 10)            // otra aldea: Abrevadero nivel 10
);

$expectedNoTrough = $p4+$p5+$p6+$others;                 // sin descuento
$expectedLevel20  = ($p4-1)+($p5-1)+($p6-1)+$others;      // -1 en las tres
$expectedLevel10  = ($p4-1)+$p5+$p6+$others;              // sólo Equites Legati

// C1. Sin vid ni vref: usa la aldea activa (100), que en este escenario no tiene Abrevadero.
troughAssert(
	$technology->getUpkeep(baseUnits(),1) === $expectedNoTrough,
	'Sin vid/vref debería usar la aldea activa sin Abrevadero'
);

// C2. array['vref'] apunta a la aldea de destino: éste era el bug (el `elseif` nunca se
// alcanzaba porque $building siempre es un objeto). Debe reflejar la aldea de destino.
$withVref = baseUnits();
$withVref['vref'] = 200;
troughAssert(
	$technology->getUpkeep($withVref,1) === $expectedLevel20,
	'array[vref]=200 debería descontar el Abrevadero de la aldea 200, no el de la aldea activa'
);

// C3. El tercer parámetro explícito ($vid) también debe funcionar sin necesitar 'vref' en el array.
troughAssert(
	$technology->getUpkeep(baseUnits(),1,300) === $expectedLevel10,
	'El parámetro $vid explícito debería resolver el Abrevadero de esa aldea'
);

// C4. Si se pasan los dos, el $vid explícito gana sobre array[vref].
$conflicting = baseUnits();
$conflicting['vref'] = 300; // nivel 10 si ganara vref
troughAssert(
	$technology->getUpkeep($conflicting,1,200) === $expectedLevel20,
	'El $vid explícito debería tener prioridad sobre array[vref]'
);

// C5. Cuando la aldea objetivo coincide con la aldea activa, se usa la ruta rápida
// (getTypeLevel(41) sin vid), no la ruta por vid — que en este escenario tiene un valor
// distinto a propósito para detectar si se toma la ruta equivocada.
$building->currentLevel = 7; // distinto de byVid[100][41]=15, a propósito
$sameVillage = baseUnits();
$sameVillage['vref'] = 100;
$expectedFastPath = $p4+$p5+$p6+$others; // nivel 7: no llega a ningún umbral (10/15/20)
troughAssert(
	$technology->getUpkeep($sameVillage,1) === $expectedFastPath,
	'Cuando la aldea objetivo es la activa debería usar getTypeLevel(41) sin vid (ruta rápida)'
);
$building->currentLevel = 0;

// C6. Sin $building (informe u otro contexto sin edificio cargado) no debería romper ni
// otorgar descuentos fantasma.
$savedBuilding = $building;
$building = null;
troughAssert(
	$technology->getUpkeep(baseUnits(),1,200) === $expectedNoTrough,
	'Sin objeto $building no debería poder resolver el Abrevadero de ninguna aldea'
);
$building = $savedBuilding;

// ------------------------------------------------------------------------------
// D. Una aldea eliminada en un informe viejo equivale a no tener Abrevadero
// ------------------------------------------------------------------------------

$database = new TroughDatabaseStub();
$building = new Building();
$warning = null;
set_error_handler(function($severity,$message) use (&$warning) {
	$warning = $message;
	return true;
});
$deletedVillageLevel = $building->getTypeLevel(41,999);
restore_error_handler();
troughAssert(
	$deletedVillageLevel === 0 && $warning === null,
	'Una aldea eliminada debería devolver nivel 0 sin warnings al renderizar un informe viejo'
);

// ------------------------------------------------------------------------------
// E. El Abrevadero sólo acelera Establo (20) y Gran establo (30)
// ------------------------------------------------------------------------------

class TroughTrainingBuildingStub {
	public $levels = array(20 => 5,30 => 5,19 => 5,41 => 20);
	public function getTypeLevel($tid,$vid = 0) {
		return isset($this->levels[$tid]) ? $this->levels[$tid] : 0;
	}
}

$trainingSession = (object)array('uid' => 1,'tribe' => 1,'access' => 2);
$trainingDatabase = new class {
	public function getArtefactsByOwner($owner) { return array(); }
	public function getActiveArtefactsByOwner($owner) { return array(); }
	public function getArtefactEffectValue($vref,$owner,$type) { return 1; }
	public function getHeroData($uid) { return array('dead' => 1,'wref' => 0,'home' => 0); }
	public function getHeroInventory($uid) { return array(); }
	public function getItemData($id) { return false; }
};
$trainingVillage = (object)array('wid' => 100);
$trainingBuilding = new TroughTrainingBuildingStub();

$GLOBALS['session'] = $trainingSession;
$GLOBALS['database'] = $trainingDatabase;
$GLOBALS['village'] = $trainingVillage;
$GLOBALS['building'] = $trainingBuilding;

$stableWithTrough = $technology->getUnitTrainingTime(4,20,5);
$stableWithoutTrough = $stableWithTrough;
$trainingBuilding->levels[41] = 0;
$stableNoTrough = $technology->getUnitTrainingTime(4,20,5);
$trainingBuilding->levels[41] = 20;

troughAssert(
	$stableWithTrough < $stableNoTrough,
	"el Abrevadero nivel 20 debería reducir el tiempo de entrenamiento en el Establo ($stableWithTrough >= $stableNoTrough)"
);

$expectedStableTrough = (int)max(1,round($GLOBALS['u4']['time']*($bid20[5]['attri']/100)/SPEED/$bid41[20]['attri']));
troughAssert(
	$stableWithTrough === $expectedStableTrough,
	"tiempo de Establo con Abrevadero 20: esperado $expectedStableTrough, obtenido $stableWithTrough"
);

$barracksWithTrough = $technology->getUnitTrainingTime(1,19,5);
$trainingBuilding->levels[41] = 0;
$barracksNoTrough = $technology->getUnitTrainingTime(1,19,5);
$trainingBuilding->levels[41] = 20;
troughAssert(
	$barracksWithTrough === $barracksNoTrough,
	'el Abrevadero no debería afectar el tiempo de entrenamiento del Cuartel (infantería)'
);

unset($GLOBALS['session'],$GLOBALS['database'],$GLOBALS['village'],$GLOBALS['building']);

// ------------------------------------------------------------------------------
// F. Sanidad de la tabla de datos contra el texto mostrado al jugador
// ------------------------------------------------------------------------------

troughAssert(count($bid41) === 20,'bid41 debería tener exactamente 20 niveles');
troughAssert(
	round($bid41[1]['attri'],2) === 1.01,
	'Nivel 1 del Abrevadero debería configurar 101% (el texto dice "1% por nivel")'
);
troughAssert(
	round($bid41[20]['attri'],2) === 1.25,
	'Nivel 20 del Abrevadero debería configurar 125% (el texto de ayuda promete ese máximo)'
);
for($level = 2; $level <= 20; $level++) {
	troughAssert(
		$bid41[$level]['attri'] >= $bid41[$level-1]['attri'],
		"el bono del Abrevadero no debería bajar del nivel ".($level-1)." al $level"
	);
}

// ------------------------------------------------------------------------------

if($errors) {
	fwrite(STDERR,"Horse drinking trough (Abrevadero) regression: FAILED\n");
	foreach($errors as $error) {
		fwrite(STDERR,"  - $error\n");
	}
	exit(1);
}

echo "Horse drinking trough (Abrevadero) regression: OK\n";
