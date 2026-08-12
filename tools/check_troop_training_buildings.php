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
define('TB_PREFIX','training_contract_');

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Technology.php';

$errors = array();
function troopTrainingAssert($condition,$message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

class TroopTrainingTechnology extends Technology {
	public $maximum = 100;
	public function maxUnit($unit,$great=false) {
		return $this->maximum;
	}
}

class TroopTrainingBuildingStub {
	public $levels = array(19 => 5,20 => 5,21 => 5,41 => 0);
	public function getTypeLevel($type) {
		return isset($this->levels[$type]) ? $this->levels[$type] : 0;
	}
}

class TroopTrainingVillageStub {
	public $wid = 900;
	public $resarray = array();
	public $techarray = array();
	public $unitarray = array('u99' => 0);
	public function getProd($type) { return 100000; }
}

class TroopTrainingSessionStub {
	public $uid = 1;
	public $tribe = 1;
	public $access = 2;
	public $mchecker = 'valid-token';
	public function changeChecker() { $this->mchecker = 'changed-token'; }
}

class TroopTrainingDatabaseStub {
	public $lockAllowed = true;
	public $deductAllowed = true;
	public $queueAllowed = true;
	public $locks = 0;
	public $deductions = array();
	public $queues = array();
	public $refunds = array();
	public function acquireTrainingLock($wid,$timeout) {
		if(!$this->lockAllowed) { return false; }
		$this->locks++;
		return true;
	}
	public function releaseTrainingLock($wid) { $this->locks--; return true; }
	public function deductResourcesIfAvailable($wid,$wood,$clay,$iron,$crop) {
		$this->deductions[] = array($wid,$wood,$clay,$iron,$crop);
		return $this->deductAllowed;
	}
	public function trainUnit($wid,$unit,$amt,$pop,$each,$time,$mode,$alreadyLocked=false) {
		$this->queues[] = array($wid,$unit,$amt,$pop,$each,$time,$mode,$alreadyLocked);
		return $this->queueAllowed;
	}
	public function modifyResource($wid,$wood,$clay,$iron,$crop,$mode) {
		$this->refunds[] = array($wid,$wood,$clay,$iron,$crop,$mode);
		return true;
	}
	public function getActiveArtefactsByType($wid,$uid,$type) { return array(); }
}

$session = new TroopTrainingSessionStub();
$building = new TroopTrainingBuildingStub();
$village = new TroopTrainingVillageStub();
$database = new TroopTrainingDatabaseStub();
$technology = new TroopTrainingTechnology();
$trainMethod = new ReflectionMethod('Technology','trainUnit');
$trainMethod->setAccessible(true);

$families = array(
	1 => array(19 => 1,20 => 4,21 => 7),
	2 => array(19 => 11,20 => 15,21 => 17),
	3 => array(19 => 21,20 => 23,21 => 27),
	4 => array(19 => 31,20 => 35,21 => 37),
	5 => array(19 => 41,20 => 45,21 => 47)
);

foreach($families as $tribe => $buildings) {
	$session->tribe = $tribe;
	foreach($buildings as $fieldType => $unit) {
		$village->resarray = array('f30t' => $fieldType,'f30' => 5);
		$village->techarray = array('t'.$unit => 1);
		$database = new TroopTrainingDatabaseStub();
		$trained = $trainMethod->invoke($technology,$unit,2,false,30);
		troopTrainingAssert($trained === true,"tribe $tribe unit $unit trains in building $fieldType");
		troopTrainingAssert(count($database->queues) === 1 && $database->queues[0][7] === true,"unit $unit queues while holding the village lock");
		troopTrainingAssert($database->locks === 0,"unit $unit releases its village lock");
		$cost = $GLOBALS['u'.$unit];
		troopTrainingAssert($database->deductions[0] === array(900,$cost['wood']*2,$cost['clay']*2,$cost['iron']*2,$cost['crop']*2),"unit $unit deducts its exact cost");
	}
}

$session->tribe = 1;
$village->techarray = array('t1' => 1,'t4' => 1,'t7' => 1,'t11' => 1);

$invalidCases = array(
	array(1,19,0,30,'unfinished building'),
	array(1,20,5,30,'wrong unit family'),
	array(11,19,5,30,'foreign tribe'),
	array(1,19,5,0,'invalid field'),
	array(1,19,5,41,'invalid field')
);
foreach($invalidCases as $case) {
	list($unit,$type,$level,$field,$label) = $case;
	$village->resarray = array('f'.$field.'t' => $type,'f'.$field => $level);
	$database = new TroopTrainingDatabaseStub();
	$trained = $trainMethod->invoke($technology,$unit,1,false,$field);
	$changed = count($database->deductions) + count($database->queues) + count($database->refunds);
	troopTrainingAssert($trained === false && $changed === 0,$label.' is rejected without side effects');
}

$village->resarray = array('f30t' => 19,'f30' => 5);
$village->techarray = array('t2' => 0);
$database = new TroopTrainingDatabaseStub();
troopTrainingAssert($trainMethod->invoke($technology,2,1,false,30) === false && empty($database->deductions),'unresearched unit is rejected');

foreach(array(0,-1) as $quantity) {
	$village->techarray = array('t1' => 1);
	$database = new TroopTrainingDatabaseStub();
	troopTrainingAssert($trainMethod->invoke($technology,1,$quantity,false,30) === false && empty($database->deductions),"quantity $quantity is rejected");
}

foreach(array('1soldier',' 1',array('1')) as $malformed) {
	$session->mchecker = 'valid-token';
	$village->resarray = array('f30t' => 19,'f30' => 5);
	$village->techarray = array();
	$database = new TroopTrainingDatabaseStub();
	$technology->procTech(array('ft' => 't1','id' => '30','k' => 'valid-token','t1' => $malformed));
	troopTrainingAssert(empty($database->deductions) && empty($database->queues),'malformed posted quantity is ignored');
}

foreach(array(
	array('ft' => 't1','id' => '30','k' => 'wrong-token','t1' => '1'),
	array('ft' => 't1','id' => '0','k' => 'valid-token','t1' => '1'),
	array('ft' => 't1','id' => array('30'),'k' => 'valid-token','t1' => '1')
) as $invalidRequest) {
	$session->mchecker = 'valid-token';
	$database = new TroopTrainingDatabaseStub();
	$technology->procTech($invalidRequest);
	troopTrainingAssert(empty($database->deductions) && empty($database->queues),'invalid token or field request is rejected');
}

$technology->maximum = 1;
$database = new TroopTrainingDatabaseStub();
troopTrainingAssert($trainMethod->invoke($technology,1,2,false,30) === false && empty($database->deductions),'quantity over current capacity is rejected');
$technology->maximum = 100;

$database = new TroopTrainingDatabaseStub();
$database->lockAllowed = false;
troopTrainingAssert($trainMethod->invoke($technology,1,1,false,30) === false && empty($database->deductions),'lock timeout rejects without deduction');

$database = new TroopTrainingDatabaseStub();
$database->deductAllowed = false;
troopTrainingAssert($trainMethod->invoke($technology,1,1,false,30) === false && empty($database->queues) && $database->locks === 0,'insufficient resources leave no queue and release the lock');

$database = new TroopTrainingDatabaseStub();
$database->queueAllowed = false;
troopTrainingAssert($trainMethod->invoke($technology,1,2,false,30) === false,'queue failure rejects the order');
troopTrainingAssert(count($database->refunds) === 1 && $database->refunds[0][5] === 1,'queue failure refunds the exact reservation');
troopTrainingAssert($database->locks === 0,'queue failure releases the lock');

if($errors) {
	foreach($errors as $error) {
		fwrite(STDERR,'FAIL: '.$error.PHP_EOL);
	}
	exit(1);
}

echo "Troop training building checks passed.\n";
