<?php
// Regresión de los cascos de entrenamiento (btype 1, types 10-15) sobre trainUnit():
// que el tiempo encolado sea el reducido, que cada familia acelere solo su edificio y
// que el bono sea de la aldea natal.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_training_helmets.php

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
define('TB_PREFIX','test_');

if(!function_exists('mysql_query')) {
	function mysql_query($query) {
		return true;
	}
}
if(!function_exists('mysql_fetch_assoc')) {
	function mysql_fetch_assoc($result) {
		return array(
			'maxstore' => 1000000000,
			'maxcrop' => 1000000000,
			'wood' => 1000000000,
			'clay' => 1000000000,
			'iron' => 1000000000,
			'crop' => 1000000000
		);
	}
}

require_once dirname(__DIR__).'/GameEngine/Artefact.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Technology.php';

$errors = array();
function trainingAssert($condition,$message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

class TrainingTechnology extends Technology {
	public function maxUnit($unit,$great=false) {
		return 100;
	}
}

class TrainingBuildingStub {
	public $levels = array(19 => 5, 20 => 5, 29 => 5, 30 => 5, 41 => 0);

	public function getTypeLevel($type) {
		return isset($this->levels[$type]) ? $this->levels[$type] : 0;
	}
}

class TrainingVillageStub {
	public $wid = 100;
	public $resarray = array();
	public $techarray = array();
	public $unitarray = array('u99' => 0);

	public function getProd($type) {
		return $type === 'crop' ? 100 : 0;
	}
}

class TrainingDatabaseStub {
	public $queues = array();
	public $hero = array('dead' => 0, 'wref' => 100, 'home' => 100);
	public $inventory = array('helmet' => 0);
	public $items = array();

	public function deductResourcesIfAvailable($wid,$wood,$clay,$iron,$crop) { return true; }
	public function modifyResource($wid,$wood,$clay,$iron,$crop,$mode) { return true; }

	public function trainUnit($wid,$unit,$amt,$pop,$each,$time,$mode) {
		$this->queues[] = array('wid' => $wid, 'unit' => $unit, 'each' => $each);
		return true;
	}

	public $artefacts = array();

	public function getArtefactsByOwner($owner) { return $this->artefacts; }

	public function getActiveArtefactsByOwner($owner) {
		return artefactActiveRows($this->getArtefactsByOwner($owner));
	}

	public function getArtefactEffectValue($vref,$owner,$type) {
		return artefactVillageEffectValue($this->getActiveArtefactsByOwner($owner), $type, $vref);
	}

	public function getHeroData($uid) { return $this->hero; }
	public function getHeroInventory($uid) { return $this->inventory; }
	public function getItemData($id) { return isset($this->items[$id]) ? $this->items[$id] : false; }

	public function wearHelmet($type) {
		if($type === 0) {
			$this->inventory['helmet'] = 0;
			$this->items = array();
			return;
		}
		$this->items = array(1 => array('id' => 1, 'uid' => 1, 'btype' => 1, 'type' => $type, 'num' => 1, 'proc' => 1));
		$this->inventory['helmet'] = 1;
	}
}

$session = (object)array('uid' => 1,'tribe' => 1,'access' => 2);
$building = new TrainingBuildingStub();
$village = new TrainingVillageStub();
$village->techarray = array('t1' => 1,'t4' => 1,'t7' => 1);
$database = new TrainingDatabaseStub();
$technology = new TrainingTechnology();
$trainMethod = new ReflectionMethod('Technology','trainUnit');
$trainMethod->setAccessible(true);

// Tiempo esperado, derivado de las tablas de datos y no del propio trainUnit().
function expectedTime($unitTime,$attri,$factor) {
	return (int)max(1,round($unitTime * ($attri / 100) / SPEED * $factor));
}

// Encola una unidad en el campo indicado y devuelve el `each` resultante.
function queuedTime($unit,$fieldId,$fieldType,$level,$great = false) {
	global $trainMethod,$technology,$database,$village;
	$village->resarray = array('f'.$fieldId.'t' => $fieldType,'f'.$fieldId => $level);
	$database->queues = array();
	$trained = $trainMethod->invoke($technology,$unit,1,$great,$fieldId);
	trainingAssert($trained === true,"no se pudo entrenar la unidad $unit en el campo $fieldId");

	return empty($database->queues) ? 0 : (int)$database->queues[0]['each'];
}

// --- Cuartel y Gran Cuartel ---------------------------------------------------

$database->wearHelmet(0);
$barracksBase = queuedTime(1,20,19,5);
trainingAssert(
	$barracksBase === expectedTime($u1['time'],$bid19[5]['attri'],1),
	"el cuartel sin casco encoló $barracksBase, se esperaba ".expectedTime($u1['time'],$bid19[5]['attri'],1)
);

$greatBarracksBase = queuedTime(1,20,29,5,true);
trainingAssert(
	$greatBarracksBase === expectedTime($u1['time'],$bid29[5]['attri'],1),
	'el gran cuartel sin casco no encoló su tiempo de tabla'
);

$expected = array(13 => 0.90, 14 => 0.85, 15 => 0.80);
foreach($expected as $type => $factor) {
	$database->wearHelmet($type);
	$each = queuedTime(1,20,19,5);
	$want = expectedTime($u1['time'],$bid19[5]['attri'],$factor);
	trainingAssert($each === $want,"el casco $type encoló $each en el cuartel, se esperaba $want");
	trainingAssert($each < $barracksBase,"el casco $type no redujo el tiempo del cuartel");

	$each = queuedTime(1,20,29,5,true);
	$want = expectedTime($u1['time'],$bid29[5]['attri'],$factor);
	trainingAssert($each === $want,"el casco $type encoló $each en el gran cuartel, se esperaba $want");
}

// --- Establo y Gran Establo ---------------------------------------------------

$database->wearHelmet(0);
$stableBase = queuedTime(4,20,20,5);
trainingAssert(
	$stableBase === expectedTime($u4['time'],$bid20[5]['attri'],1),
	"el establo sin casco encoló $stableBase, se esperaba ".expectedTime($u4['time'],$bid20[5]['attri'],1)
);

$greatStableBase = queuedTime(4,20,30,5,true);
trainingAssert(
	$greatStableBase === expectedTime($u4['time'],$bid30[5]['attri'],1),
	'el gran establo sin casco no encoló su tiempo de tabla'
);

$expected = array(10 => 0.90, 11 => 0.85, 12 => 0.80);
foreach($expected as $type => $factor) {
	$database->wearHelmet($type);
	$each = queuedTime(4,20,20,5);
	$want = expectedTime($u4['time'],$bid20[5]['attri'],$factor);
	trainingAssert($each === $want,"el casco $type encoló $each en el establo, se esperaba $want");
	trainingAssert($each < $stableBase,"el casco $type no redujo el tiempo del establo");

	$each = queuedTime(4,20,30,5,true);
	$want = expectedTime($u4['time'],$bid30[5]['attri'],$factor);
	trainingAssert($each === $want,"el casco $type encoló $each en el gran establo, se esperaba $want");
}

// --- Las familias no se cruzan ------------------------------------------------

$database->wearHelmet(15);
trainingAssert(queuedTime(4,20,20,5) === $stableBase,'un casco de cuartel aceleró el establo');
$database->wearHelmet(12);
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'un casco de establo aceleró el cuartel');

// El taller no lo acelera ningún casco.
$building->levels[21] = 5;
$database->wearHelmet(0);
$workshopBase = queuedTime(7,20,21,5);
foreach(array(10,12,13,15) as $type) {
	$database->wearHelmet($type);
	trainingAssert(queuedTime(7,20,21,5) === $workshopBase,"el casco $type aceleró el taller");
}

// --- El Bebedero y el artefacto, que las plantillas se olvidaban ---------------

// El Bebedero (41) acelera solo la caballería, y solo en establo y gran establo.
$database->wearHelmet(0);
$building->levels[41] = 10;
$troughFactor = 1 / $bid41[10]['attri'];
trainingAssert(
	queuedTime(4,20,20,5) === expectedTime($u4['time'],$bid20[5]['attri'],$troughFactor),
	'el establo no descontó el Bebedero'
);
trainingAssert(
	queuedTime(4,20,30,5,true) === expectedTime($u4['time'],$bid30[5]['attri'],$troughFactor),
	'el gran establo no descontó el Bebedero'
);
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'el Bebedero aceleró la infantería');
trainingAssert(queuedTime(7,20,21,5) === $workshopBase,'el Bebedero aceleró el taller');

// El Bebedero y el casco se acumulan.
$database->wearHelmet(12);
trainingAssert(
	queuedTime(4,20,20,5) === expectedTime($u4['time'],$bid20[5]['attri'],$troughFactor * 0.80),
	'el Bebedero y el casco de establo no se acumularon'
);
$database->wearHelmet(0);
$building->levels[41] = 0;

// El artefacto del entrenador aplica a los tres edificios, no solo al taller, y su
// tabla es la oficial: 1/2 el pequeño y el único, 3/4 el grande. Acá el grande valía
// 0,25 —cuatro veces más rápido, el más fuerte de los tres— y el checker lo pinaba.
$trainerFactors = array(
	1 => array(0.5,  'pequeño (aldea)'),
	2 => array(0.75, 'grande (cuenta)'),
	3 => array(0.5,  'único (cuenta)')
);
foreach($trainerFactors as $size => $expected) {
	list($factor,$label) = $expected;
	$database->artefacts = array(array(
		'id' => 1, 'vref' => 100, 'owner' => 3,
		'type' => ARTEFACT_TRAINER, 'size' => $size, 'conquered' => 0
	));
	trainingAssert(
		queuedTime(1,20,19,5) === expectedTime($u1['time'],$bid19[5]['attri'],$factor),
		'el cuartel no descontó el artefacto de entrenamiento '.$label
	);
	trainingAssert(
		queuedTime(4,20,20,5) === expectedTime($u4['time'],$bid20[5]['attri'],$factor),
		'el establo no descontó el artefacto de entrenamiento '.$label
	);
	trainingAssert(
		queuedTime(7,20,21,5) === expectedTime($u7['time'],$bid21[5]['attri'],$factor),
		'el taller no descontó el artefacto de entrenamiento '.$label
	);
}

// Un artefacto recién capturado todavía no hace nada: el retardo de activación primero.
$database->artefacts = array(array(
	'id' => 1, 'vref' => 100, 'owner' => 3,
	'type' => ARTEFACT_TRAINER, 'size' => 1, 'conquered' => time()
));
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,
	'un artefacto sin activar ya aceleraba el entrenamiento');

// Artefacto y casco se acumulan.
$database->artefacts = array(array(
	'id' => 1, 'vref' => 100, 'owner' => 3,
	'type' => ARTEFACT_TRAINER, 'size' => 1, 'conquered' => 0
));
$database->wearHelmet(15);
trainingAssert(
	queuedTime(1,20,19,5) === expectedTime($u1['time'],$bid19[5]['attri'],0.5 * 0.80),
	'el artefacto y el casco de cuartel no se acumularon'
);
$database->wearHelmet(0);
$database->artefacts = array();
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'el tiempo no volvió al base sin artefacto');

// --- Aldea natal y héroe muerto -----------------------------------------------

$database->wearHelmet(15);
$database->hero['home'] = 101;
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'el bono se cobró fuera de la aldea natal');

$discounted = expectedTime($u1['time'],$bid19[5]['attri'],0.80);
$database->hero['home'] = 100;
trainingAssert(queuedTime(1,20,19,5) === $discounted,'el bono no se cobró en la aldea natal');

// Mover al héroe no mueve el bono: manda `home`, no `wref`.
$database->hero['wref'] = 101;
trainingAssert(queuedTime(1,20,19,5) === $discounted,'el bono siguió al héroe fuera de su aldea natal');
$database->hero['wref'] = 100;

// Un héroe sin `home` (creado antes de que existiera la columna) cae en `wref`.
$database->hero['home'] = 0;
trainingAssert(queuedTime(1,20,19,5) === $discounted,'un héroe sin `home` no cobró el bono en su `wref`');
$database->hero['home'] = 100;

$database->hero['dead'] = 1;
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'un héroe muerto siguió acelerando el cuartel');
$database->hero['dead'] = 0;

// Sin casco vuelve al tiempo base.
$database->wearHelmet(0);
trainingAssert(queuedTime(1,20,19,5) === $barracksBase,'sin casco el tiempo no volvió al base');

// El tiempo nunca puede quedar en cero.
$database->wearHelmet(15);
trainingAssert(queuedTime(1,20,19,20) >= 1,'el tiempo encolado quedó por debajo de 1 segundo');

if($errors) {
	fwrite(STDERR,"Hero training helmets regression: FAILED\n");
	foreach($errors as $error) {
		fwrite(STDERR,"  - $error\n");
	}
	exit(1);
}

echo "Hero training helmets regression: OK\n";
