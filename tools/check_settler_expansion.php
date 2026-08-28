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
define('CP',3);

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/cp.php';
require dirname(__DIR__).'/GameEngine/Technology.php';
require dirname(__DIR__).'/GameEngine/Units.php';

$errors = array();
function settlerAssert($condition, $message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

settlerAssert(
	array($u10['wood'],$u10['clay'],$u10['iron'],$u10['crop']) === array(5800,5300,7200,5500),
	'Roman settler costs must remain tribe-specific.'
);
settlerAssert(
	array($u20['wood'],$u20['clay'],$u20['iron'],$u20['crop']) === array(7200,5500,5800,6500),
	'German settler costs must remain tribe-specific.'
);
settlerAssert(
	array($u30['wood'],$u30['clay'],$u30['iron'],$u30['crop']) === array(5500,7000,5300,4900),
	'Gaul settler costs must remain tribe-specific.'
);

class SettlerBuildingStub {
	public $levels = array(25 => 10, 26 => 0);

	public function getTypeLevel($type) {
		return isset($this->levels[$type]) ? $this->levels[$type] : 0;
	}
}

class SettlerTrainingDatabaseStub {
	public $slots = array('settlers' => 6, 'chiefs' => 2);
	public $deductAllowed = true;
	public $queueAllowed = true;
	public $deductions = array();
	public $queues = array();
	public $refunds = array();
	public $locks = 0;

	public function acquireSettlementLock($uid, $timeout) {
		$this->locks++;
		return true;
	}

	public function releaseSettlementLock($uid) {
		$this->locks--;
		return true;
	}

	public function getAvailableExpansionTraining() {
		return $this->slots;
	}

	public function deductResourcesIfAvailable($wid, $wood, $clay, $iron, $crop) {
		$this->deductions[] = array($wid,$wood,$clay,$iron,$crop);
		return $this->deductAllowed;
	}

	public function trainUnit($wid, $unit, $amt, $pop, $each, $time, $mode) {
		$this->queues[] = array($wid,$unit,$amt,$pop,$each,$time,$mode);
		return $this->queueAllowed;
	}

	public function modifyResource($wid, $wood, $clay, $iron, $crop, $mode) {
		$this->refunds[] = array($wid,$wood,$clay,$iron,$crop,$mode);
		return true;
	}
}

$session = (object)array('uid' => 7, 'tribe' => 2, 'access' => 2, 'mchecker' => 'token');
$building = new SettlerBuildingStub();
$village = (object)array(
	'wid' => 100,
	'resarray' => array('f25t' => 25, 'f25' => 10),
	'techarray' => array('t20' => 0),
	'unitarray' => array('u99' => 0)
);
$database = new SettlerTrainingDatabaseStub();
$technology = new Technology();
$trainMethod = new ReflectionMethod('Technology','trainUnit');
$trainMethod->setAccessible(true);

$expectedTrainingTimes = array(1 => array(10,10421),2 => array(20,12009),3 => array(30,8794));
foreach($expectedTrainingTimes as $tribe => $trainingData) {
	$session->tribe = $tribe;
	$unit = $trainingData[0];
	$expectedTime = $trainingData[1];
	$village->resarray = array('f25t' => 25, 'f25' => 10);
	$village->techarray = array('t'.$unit => 0);
	$database = new SettlerTrainingDatabaseStub();
	$trained = $trainMethod->invoke($technology,$unit,1,false,25);
	settlerAssert($trained === true, 'The tribe '.$tribe.' settler should train with valid requirements.');
	settlerAssert(
		count($database->queues) === 1 && $database->queues[0][4] === $expectedTime,
		'The tribe '.$tribe.' settler queue must use the displayed Residence level 10 training time.'
	);

	$village->resarray = array('f25t' => 26, 'f25' => 10);
	$database = new SettlerTrainingDatabaseStub();
	$trained = $trainMethod->invoke($technology,$unit,1,false,25);
	settlerAssert($trained === true, 'The tribe '.$tribe.' settler should also train in a Palace.');
	settlerAssert(
		count($database->queues) === 1 && $database->queues[0][4] === $expectedTime,
		'The tribe '.$tribe.' settler queue must use the displayed Palace level 10 training time.'
	);
}

$session->tribe = 2;
$village->resarray = array('f25t' => 25, 'f25' => 10);
$village->techarray = array('t20' => 0);
$database = new SettlerTrainingDatabaseStub();
$trained = $trainMethod->invoke($technology,20,3,false,25);
settlerAssert($trained === true, 'Three German settlers should train with valid requirements.');
settlerAssert(
	$database->deductions === array(array(100,21600,16500,17400,19500)),
	'German settler training must deduct exactly three configured u20 costs.'
);
settlerAssert(count($database->queues) === 1 && $database->queues[0][2] === 3, 'Exactly three settlers must be queued.');
settlerAssert($database->locks === 0, 'The training account lock must always be released.');

$database = new SettlerTrainingDatabaseStub();
$village->resarray = array('f25t' => 0, 'f25' => 0);
settlerAssert(
	$trainMethod->invoke($technology,20,3,false,25) === false
		&& empty($database->deductions)
		&& empty($database->queues),
	'Training without a Residence or Palace must be rejected before charging.'
);

$database = new SettlerTrainingDatabaseStub();
$village->resarray = array('f25t' => 25, 'f25' => 10);
$database->deductAllowed = false;
settlerAssert(
	$trainMethod->invoke($technology,20,3,false,25) === false
		&& count($database->deductions) === 1
		&& empty($database->queues),
	'Insufficient resources must create no settler queue.'
);

$database = new SettlerTrainingDatabaseStub();
$database->slots['settlers'] = 0;
settlerAssert(
	$trainMethod->invoke($technology,20,1,false,25) === false
		&& empty($database->deductions)
		&& empty($database->queues),
	'Zero expansion capacity must not become a negative training quantity or a resource credit.'
);

$database = new SettlerTrainingDatabaseStub();
settlerAssert(
	$trainMethod->invoke($technology,20,-3,false,25) === false
		&& empty($database->deductions),
	'Negative settler quantities must be rejected.'
);

$eligibility = travianCultureExpansionEligibility(5300,2,0,CP);
settlerAssert($eligibility['eligible'] && $eligibility['nextVillageCount'] === 3, '5,300 PC must allow a third village on the intermediate curve.');
$eligibility = travianCultureExpansionEligibility(5300,2,1,CP);
settlerAssert(!$eligibility['eligible'] && $eligibility['requiredPoints'] === 13300, 'A pending third village must reserve intermediate-curve capacity account-wide.');
$eligibility = travianCultureExpansionEligibility(13300,2,1,CP);
settlerAssert($eligibility['eligible'], '13,300 PC must allow owned and pending villages plus a fourth village.');

$cultureStatus = travianCultureStatus(1316,1,CP);
settlerAssert(
	$cultureStatus['cultureCapacity'] === 2
		&& $cultureStatus['currentRequiredPoints'] === 1300
		&& $cultureStatus['nextRequiredPoints'] === 5300,
	'Culture progress must use the thresholds for the current and next village capacity.'
);
settlerAssert(
	$cultureStatus['progressPoints'] === 16
		&& $cultureStatus['progressRequiredPoints'] === 4000
		&& abs($cultureStatus['progressPercent'] - (16 / 4000 * 100)) < 0.0001,
	'Culture progress must measure only the current threshold segment.'
);
$cultureStatus = travianCultureStatus(5300,2,CP);
settlerAssert(
	$cultureStatus['cultureCapacity'] === 3
		&& $cultureStatus['progressPoints'] === 0
		&& $cultureStatus['progressRequiredPoints'] === 8000
		&& (float)$cultureStatus['progressPercent'] === 0.0,
	'Culture progress must restart when a new village capacity is unlocked.'
);

class SettlerFoundingDatabaseStub {
	public $pending = 0;
	public $pendingTarget = 0;
	public $settlers = 3;
	public $resourceDeductions = array();
	public $unitDeductions = array();
	public $movements = array();
	public $questAchievements = array();
	public $refunds = array();
	public $movementAllowed = true;
	public $expansionSlots = 3;

	public function getExpansionSlotLimit($wid) {
		return $this->expansionSlots;
	}

	public function getMInfo($target) {
		return array('occupied' => 0, 'oasistype' => 0, 'fieldtype' => 3, 'x' => 10, 'y' => 11);
	}

	public function getMovement($type, $village, $mode) {
		return array();
	}

	public function getPendingSettlementCountByOwner($uid, $excludeMoveId = 0, $target = 0) {
		return $target > 0 ? $this->pendingTarget : $this->pending;
	}

	public function getVillageField($wid, $field) {
		return 0;
	}

	public function getUserField($uid, $field, $mode) {
		return $field === 'cp' ? 8000 : 0;
	}

	public function getVillagesID($uid) {
		return array(101,102);
	}

	public function getUnit($wid) {
		return array('u20' => $this->settlers);
	}

	public function deductResourcesIfAvailable($wid, $wood, $clay, $iron, $crop) {
		$this->resourceDeductions[] = array($wid,$wood,$clay,$iron,$crop);
		return true;
	}

	public function deductUnitIfAvailable($wid, $unit, $amt) {
		$this->unitDeductions[] = array($wid,$unit,$amt);
		return $this->settlers >= $amt;
	}

	public function modifyResource($wid, $wood, $clay, $iron, $crop, $mode) {
		return true;
	}

	public function modifyUnit($wid, $unit, $amt, $mode) {
		return true;
	}

	public function addMovement($type, $from, $to, $ref, $data, $endtime) {
		$this->movements[] = array($type,$from,$to,$ref,$data,$endtime);
		return $this->movementAllowed;
	}

	public function markFollowupQuestAchieved($uid, $questIndex) {
		$this->questAchievements[] = array((int)$uid,(int)$questIndex);
		return true;
	}

	public function refundFoundingAssets($wid, $owner, $unit) {
		$this->refunds[] = array($wid,$owner,$unit);
		return true;
	}
}

class SettlerGeneratorStub {
	public function procDistanceTime($from, $to, $speed, $mode) {
		return 120;
	}
}

$database = new SettlerFoundingDatabaseStub();
$generator = new SettlerGeneratorStub();
$building = new SettlerBuildingStub();
$village = (object)array('wid' => 100, 'coor' => array('x' => 1, 'y' => 2));
$foundingMethod = new ReflectionMethod('Units','queueSettlement');
$foundingMethod->setAccessible(true);
$units = new Units();

settlerAssert($foundingMethod->invoke($units,900) === true, 'A valid settlement must be queued.');
settlerAssert($database->resourceDeductions === array(array(100,750,750,750,750)), 'Founding must deduct 750 of each resource.');
settlerAssert($database->unitDeductions === array(array(100,20,3)), 'Founding must deduct exactly three German settlers.');
settlerAssert(
	count($database->movements) === 1 && $database->movements[0][0] === 5 && $database->movements[0][4] === 7,
	'Settlement movement must store the founding account id.'
);
settlerAssert(
	$database->questAchievements === array(array(7,9)),
	'Queuing a settlement must preserve completion of the three-settler quest.'
);

$database = new SettlerFoundingDatabaseStub();
$database->settlers = 2;
settlerAssert(
	$foundingMethod->invoke($units,900) === false
		&& empty($database->resourceDeductions)
		&& empty($database->movements),
	'Fewer than three settlers must create no movement and charge no resources.'
);

$database = new SettlerFoundingDatabaseStub();
$database->pending = 1;
settlerAssert(
	$foundingMethod->invoke($units,900) === false
		&& empty($database->resourceDeductions)
		&& empty($database->movements),
	'A pending settlement from another village must consume the only available culture slot.'
);

$database = new SettlerFoundingDatabaseStub();
$database->expansionSlots = 0;
settlerAssert(
	$foundingMethod->invoke($units,900) === false
		&& empty($database->resourceDeductions)
		&& empty($database->movements),
	'Sin plazas de expansión desbloqueadas no se puede fundar ni se cobran recursos.'
);

$database = new SettlerFoundingDatabaseStub();
$database->movementAllowed = false;
settlerAssert(
	$foundingMethod->invoke($units,900) === false
		&& $database->refunds === array(array(100,7,20)),
	'If movement creation fails, settlers and founding resources must be refunded together.'
);

$trainingForms = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/Templates/Build'));
foreach($iterator as $file) {
	if(!$file->isFile() || substr($file->getFilename(),-4) !== '.tpl') {
		continue;
	}
	$source = file_get_contents($file->getPathname());
	if(strpos($source,'name="ft" value="t1"') !== false || strpos($source,'name="ft" value="t3"') !== false) {
		$trainingForms[] = $file->getPathname();
		settlerAssert(strpos($source,'name="k"') !== false, 'Training form lacks a request token: '.$file->getPathname());
	}
}
settlerAssert(count($trainingForms) === 11, 'Expected all eleven troop-training forms to be covered.');

$expansionTrainingTemplates = array('25_train.tpl','26_train.tpl');
foreach($expansionTrainingTemplates as $templateName) {
	$templateSource = file_get_contents(dirname(__DIR__).'/Templates/Build/'.$templateName);
	settlerAssert(
		strpos($templateSource,'getExpansionUnitTrainingTime($i,$id)') !== false,
		$templateName.' must use the same expansion-unit time calculation as the queue.'
	);
	settlerAssert(
		strpos($templateSource,'$popupTrainingTime') !== false,
		$templateName.' must pass the displayed settler time to the unit popup.'
	);
}

$manualSource = file_get_contents(dirname(__DIR__).'/manual.php');
settlerAssert(
	strpos($manualSource,"\$bid25[10]['attri']") !== false
		&& strpos($manualSource,'/ SPEED') !== false
		&& strpos($manualSource,"array(9,10,19,20,29,30)") !== false,
	'The standalone settler manual must apply server speed and the minimum valid expansion-building level to settlers and chiefs alike.'
);

$spanishFiles = array(dirname(__DIR__).'/GameEngine/Lang/es.php',dirname(__DIR__).'/Templates');
foreach($spanishFiles as $path) {
	$files = is_dir($path)
		? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path))
		: array(new SplFileInfo($path));
	foreach($files as $file) {
		if(!$file->isFile() || !preg_match('/\.(?:php|tpl)$/',$file->getFilename())) {
			continue;
		}
		$source = file_get_contents($file->getPathname());
		settlerAssert(
			stripos($source,'Jinete germano') === false,
			'Spanish player-facing text uses Jinete germano instead of Jinete Teutón: '.$file->getPathname()
		);
	}
}
$spanishLanguage = file_get_contents(dirname(__DIR__).'/GameEngine/Lang/es.php');
settlerAssert(
	strpos($spanishLanguage,'define("U16","Jinete Teutón");') !== false,
	'The Spanish unit 16 label must be Jinete Teutón.'
);
settlerAssert(
	strpos($spanishLanguage,'define("TRIBE2","Germanos");') !== false,
	'The Spanish tribe 2 label must be Germanos.'
);

if(!empty($errors)) {
	foreach($errors as $error) {
		fwrite(STDERR,"FAIL: ".$error."\n");
	}
	exit(1);
}

echo "Settler expansion regression: OK\n";
