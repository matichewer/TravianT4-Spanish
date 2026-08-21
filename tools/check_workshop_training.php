<?php

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
// Lo que hay en el almacen de la aldea, para poder mover el techo de maxUnit().
$GLOBALS['workshopStock'] = array('wood'=>1000000000,'clay'=>1000000000,'iron'=>1000000000,'crop'=>1000000000);
if(!function_exists('mysql_fetch_assoc')) {
	function mysql_fetch_assoc($result) {
		return array_merge(
			array('maxstore' => 1000000000, 'maxcrop' => 1000000000),
			$GLOBALS['workshopStock']
		);
	}
}

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Technology.php';

$errors = array();
function workshopAssert($condition,$message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

class WorkshopTechnology extends Technology {
	public function maxUnit($unit,$great=false) {
		return 100;
	}
}

class WorkshopBuildingStub {
	public $levels = array(21 => 5, 42 => 10);

	public function getTypeLevel($type) {
		return isset($this->levels[$type]) ? $this->levels[$type] : 0;
	}
}

class WorkshopVillageStub {
	public $wid = 100;
	public $resarray = array();
	public $techarray = array();
	public $unitarray = array('u99' => 0);
	public $cropProduction = 100;

	public function getProd($type) {
		return $type === 'crop' ? $this->cropProduction : 0;
	}
}

class WorkshopDatabaseStub {
	public $deductions = array();
	public $queues = array();

	public function deductResourcesIfAvailable($wid,$wood,$clay,$iron,$crop) {
		$this->deductions[] = array($wid,$wood,$clay,$iron,$crop);
		return true;
	}

	public function trainUnit($wid,$unit,$amt,$pop,$each,$time,$mode) {
		$this->queues[] = array($wid,$unit,$amt,$pop,$each,$time,$mode);
		return true;
	}

	public function modifyResource($wid,$wood,$clay,$iron,$crop,$mode) {
		return true;
	}
}

$session = (object)array('uid' => 1,'tribe' => 1,'access' => 2);
$building = new WorkshopBuildingStub();
$village = new WorkshopVillageStub();
$village->resarray = array('f30t' => 21,'f30' => 5);
$village->techarray = array('t7' => 1,'t8' => 1);
$database = new WorkshopDatabaseStub();
$technology = new WorkshopTechnology();
$trainMethod = new ReflectionMethod('Technology','trainUnit');
$trainMethod->setAccessible(true);

$trained = $trainMethod->invoke($technology,7,2,false,30);
workshopAssert($trained === true,'A researched siege unit must train in a completed Workshop.');
workshopAssert(count($database->queues) === 1 && $database->queues[0][1] === 7,'The normal Workshop must queue the normal unit id.');
workshopAssert((int)$database->queues[0][4] === (int)round($u7['time'] * $bid21[5]['attri'] / 100),'The normal Workshop must use its displayed level time.');

$village->resarray = array('f30t' => 19,'f30' => 20);
$database = new WorkshopDatabaseStub();
$trained = $trainMethod->invoke($technology,7,1,false,30);
workshopAssert($trained === false && count($database->deductions) === 0,'A forged request from another building must not train siege.');

$village->resarray = array('f30t' => 21,'f30' => 0);
$database = new WorkshopDatabaseStub();
$trained = $trainMethod->invoke($technology,7,1,false,30);
workshopAssert($trained === false && count($database->queues) === 0,'An unfinished Workshop must not train siege.');

$village->resarray = array('f30t' => 42,'f30' => 10);
$database = new WorkshopDatabaseStub();
$trained = $trainMethod->invoke($technology,8,1,true,30);
workshopAssert($trained === true,'A researched siege unit must train in a completed Great Workshop.');
workshopAssert(
	count($database->queues) === 1
		&& $database->queues[0][1] === 68
		&& $database->deductions[0][1] === $u8['wood'] * 3,
	'The Great Workshop must use the great unit id and triple costs.'
);

$village->resarray = array('f30t' => 21,'f30' => 20);
$database = new WorkshopDatabaseStub();
$trained = $trainMethod->invoke($technology,8,1,true,30);
workshopAssert($trained === false && count($database->deductions) === 0,'Great Workshop training must not be forged from a normal Workshop.');

// El techo de entrenamiento sale SOLO de lo que hay en el almacen. En el T4 oficial
// no hay tope por balance de cereal: se entrena lo que se pueda pagar y el castigo
// por pasarse es la hambruna. Aca el min() incluia floor(produccion/consumo), asi que
// con el balance en cero o en rojo el maximo era 0 y no entraba ninguna orden.
$baseTechnology = new Technology();
$GLOBALS['workshopStock'] = array(
	'wood' => $u7['wood'] * 5,
	'clay' => $u7['clay'] * 9,
	'iron' => $u7['iron'] * 9,
	'crop' => $u7['crop'] * 9
);
$village->cropProduction = 1000;
$plenty = $baseTechnology->maxUnit(7);
workshopAssert($plenty === 5,'Training maximum must come from the scarcest stored resource (got '.$plenty.').');

$village->cropProduction = -100000;
workshopAssert($baseTechnology->maxUnit(7) === $plenty,
	'A negative crop balance must not lower the training maximum any more.');
$village->cropProduction = 0;
workshopAssert($baseTechnology->maxUnit(7) === $plenty,
	'A zero crop balance must not lower the training maximum either.');

$GLOBALS['workshopStock']['crop'] = 0;
workshopAssert($baseTechnology->maxUnit(7) === 0,'Without stored crop the unit cannot be paid for.');
$GLOBALS['workshopStock']['crop'] = $u7['crop'] * 9;

// La gran fabrica cuesta el triple, asi que entra un tercio.
$GLOBALS['workshopStock'] = array(
	'wood' => $u7['wood'] * 9,
	'clay' => $u7['clay'] * 9,
	'iron' => $u7['iron'] * 9,
	'crop' => $u7['crop'] * 9
);
$great = $baseTechnology->maxUnit(7,true);
workshopAssert($great === 3,'Great buildings triple the cost, so the maximum is a third (got '.$great.').');

$technologySource = file_get_contents(dirname(__DIR__).'/GameEngine/Technology.php');
workshopAssert(strpos($technologySource,'$popcalc') === false,
	'maxUnit must not look at the crop balance any more.');
workshopAssert(strpos($technologySource,'getProd("crop")') === false
	&& strpos($technologySource,"getProd('crop')") === false,
	'Nothing in Technology may gate training on the crop balance.');

if($errors) {
	foreach($errors as $error) {
		fwrite(STDERR,"FAIL: ".$error.PHP_EOL);
	}
	exit(1);
}

echo "Workshop training checks passed.".PHP_EOL;
