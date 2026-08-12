<?php
/** Regression checks for the Smithy (building 12). */
if(PHP_SAPI !== 'cli') { die("CLI only\n"); }
chdir(dirname(__DIR__));
error_reporting(E_ALL);

define('SPEED',1);
define('TB_PREFIX','smithy_check_');
define('BANNED',0);
define('TRAPPER_CAPACITY',1);
for($i=0;$i<=50;$i++) { if(!defined('U'.$i)) { define('U'.$i,'Unit '.$i); } }
define('U99','Trap');
require 'GameEngine/Data/buidata.php';
require 'GameEngine/Data/resdata.php';
require 'GameEngine/Technology.php';
require 'GameEngine/Battle.php';

$errors=array();
function smithyAssert($condition,$message) {
	global $errors;
	if(!$condition) { $errors[]=$message; }
}
class SmithySession {
	public $tribe=1,$mchecker='token',$changes=0;
	public function changeChecker() { $this->changes++; $this->mchecker='changed'; }
}
class SmithyVillage {
	public $wid=77,$resarray=array('f25t'=>12,'f25'=>5),$techarray=array('t1'=>1,'t2'=>1),$researching=array();
}
class SmithyBuilding {
	public $level=5;
	public function getTypeLevel($type) { return $type===12 ? $this->level : 0; }
}
class SmithyLogging {
	public $logs=array();
	public function addTechLog($wid,$tech,$level) { $this->logs[]=array($wid,$tech,$level); }
}
class SmithyDatabase {
	public $lock=true,$locks=0,$deduct=true,$insert=true,$ab=array(),$running=array();
	public $deductions=array(),$research=array(),$refunds=array();
	public function __construct() { for($i=1;$i<=8;$i++) { $this->ab['b'.$i]=0; } }
	public function acquireResearchLock($wid,$timeout) { if(!$this->lock){return false;} $this->locks++; return true; }
	public function releaseResearchLock($wid) { $this->locks--; return true; }
	public function getABTech($wid) { return $this->ab; }
	public function getResearching($wid) { return $this->running; }
	public function deductResourcesIfAvailable($wid,$w,$c,$i,$f) { $this->deductions[]=array($wid,$w,$c,$i,$f); return $this->deduct; }
	public function addResearch($wid,$tech,$time) { $this->research[]=array($wid,$tech,$time); return $this->insert; }
	public function modifyResource($wid,$w,$c,$i,$f,$mode) { $this->refunds[]=array($wid,$w,$c,$i,$f,$mode); return true; }
}

$session=new SmithySession(); $village=new SmithyVillage(); $building=new SmithyBuilding();
$logging=new SmithyLogging(); $database=new SmithyDatabase(); $technology=new Technology();
$method=new ReflectionMethod('Technology','upgradeSword'); $method->setAccessible(true);
function smithyRun($request) {
	global $method,$technology;
	$method->invoke($technology,$request);
}

smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
$cost=$GLOBALS['ab2'][1];
smithyAssert(count($database->research)===1 && $database->research[0][1]==='b2','valid researched unit is queued');
smithyAssert($database->deductions[0]===array(77,$cost['wood'],$cost['clay'],$cost['iron'],$cost['crop']),'exact next-level cost is deducted');
smithyAssert(count($logging->logs)===1 && $logging->logs[0]===array(77,'b2',1),'successful upgrade is logged');
smithyAssert($database->locks===0 && $session->changes===1,'lock is released and request token rotated');

$invalid=array(
	array(array('id'=>'25','a'=>'2','c'=>'wrong'),'invalid token'),
	array(array('id'=>'25','a'=>'0','c'=>'token'),'position zero'),
	array(array('id'=>'25','a'=>'9','c'=>'token'),'position nine'),
	array(array('id'=>'25','a'=>array('2'),'c'=>'token'),'array position'),
	array(array('id'=>'0','a'=>'2','c'=>'token'),'invalid field'),
	array(array('id'=>'26','a'=>'2','c'=>'token'),'field without smithy')
);
foreach($invalid as $case) {
	$session->mchecker='token'; $database=new SmithyDatabase(); $logging=new SmithyLogging();
	smithyRun($case[0]);
	smithyAssert(!$database->deductions && !$database->research,$case[1].' is rejected without side effects');
}

$session->mchecker='token'; $village->techarray=array('t1'=>1,'t2'=>0); $database=new SmithyDatabase();
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->deductions,'unresearched non-default unit is rejected');

$session->mchecker='token'; $village->techarray=array('t1'=>1,'t2'=>1); $database=new SmithyDatabase();
$database->running=array(array('tech'=>'t3'),array('tech'=>'b1'));
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->deductions,'a second Smithy order is rejected even with an Academy order present');

$session->mchecker='token'; $database=new SmithyDatabase(); $database->ab['b2']=5;
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->deductions,'upgrade cannot exceed Smithy level');
$session->mchecker='token'; $building->level=20; $database=new SmithyDatabase(); $database->ab['b2']=20;
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->deductions,'level 20 is a hard cap');

$session->mchecker='token'; $building->level=5; $database=new SmithyDatabase(); $database->lock=false;
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->deductions,'lock failure leaves resources untouched');
$session->mchecker='token'; $database=new SmithyDatabase(); $database->deduct=false;
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(!$database->research && $database->locks===0,'insufficient resources do not queue and release the lock');
$session->mchecker='token'; $database=new SmithyDatabase(); $database->insert=false;
smithyRun(array('id'=>'25','a'=>'2','c'=>'token'));
smithyAssert(count($database->refunds)===1 && $database->refunds[0][5]===1,'queue failure refunds the reservation');
smithyAssert($database->locks===0 && !$logging->logs,'queue failure releases the lock and is not logged');

foreach(range(1,5) as $tribe) {
	foreach(range(1,8) as $position) {
		$table=$GLOBALS['ab'.(($tribe-1)*10+$position)];
		smithyAssert(count($table)===20,"tribe $tribe position $position has all 20 levels");
		foreach(range(1,20) as $level) {
			$row=$table[$level];
			smithyAssert(isset($row['wood'],$row['clay'],$row['iron'],$row['crop'],$row['time'])
				&& min($row['wood'],$row['clay'],$row['iron'],$row['crop'],$row['time'])>0,
				"tribe $tribe position $position level $level has complete positive costs and duration");
		}
	}
}

$battle=new Battle();
$upgradeMethod=new ReflectionMethod('Battle','battleUpgradeLevel'); $upgradeMethod->setAccessible(true);
$strengthMethod=new ReflectionMethod('Battle','battleUnitStrength'); $strengthMethod->setAccessible(true);
smithyAssert($upgradeMethod->invoke($battle,array('b2'=>7),2)===7,'combat maps Smithy position b2 to unit position 2');
smithyAssert($upgradeMethod->invoke($battle,array('b2'=>99),2)===20,'combat caps corrupt upgrade values at level 20');
smithyAssert($upgradeMethod->invoke($battle,array('a2'=>7),2)===0,'combat ignores obsolete armoury columns');
smithyAssert($strengthMethod->invoke($battle,40,1,20)>$strengthMethod->invoke($battle,40,1,0),'a completed Smithy level increases combat strength');

$template=file_get_contents('Templates/Build/12_upgrades.tpl');
smithyAssert(strpos($template,"if((int)\$abdata['b'.\$j] >= 20)")!==false,'level-20 rendering avoids reading nonexistent level 21 data');
$automation=file_get_contents('GameEngine/Automation.php');
smithyAssert(strpos($automation,"preg_match('/^[ab][1-8]$/D',\$tech)")!==false,'completion only accepts valid Smithy columns');
smithyAssert(strpos($automation,'LEAST(20,')!==false,'completion cannot increment beyond level 20');

if($errors) { foreach($errors as $error){fwrite(STDERR,"FAIL: $error\n");} exit(1); }
echo "Smithy checks passed\n";
