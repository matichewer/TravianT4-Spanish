<?php

error_reporting(E_ALL);

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
require dirname(__DIR__).'/GameEngine/Automation.php';

function timedBuildingAssert($condition, $message) {
    if(!$condition) {
        fwrite(STDERR, "FAIL: ".$message."\n");
        exit(1);
    }
}

class TimedBuildingDatabaseStub {
    public $queries = array();
    public $responses = array();

    public function query_return($query) {
        $this->queries[] = $query;
        return empty($this->responses) ? array() : array_shift($this->responses);
    }
}

$database = new TimedBuildingDatabaseStub();
$automationReflection = new ReflectionClass('Automation');
$automation = $automationReflection->newInstanceWithoutConstructor();
$buildComplete = $automationReflection->getMethod('buildComplete');
$buildComplete->setAccessible(true);
$buildComplete->invoke($automation, 123456, false);

timedBuildingAssert(
    count($database->queries) === 1
        && strpos($database->queries[0], 'timestamp <= 123456') !== false
        && strpos($database->queries[0], 'ORDER BY timestamp ASC, id ASC') !== false,
    'Historical building completion must stop at the attack arrival and preserve queue order.'
);

$nextAttack = $automationReflection->getMethod('nextPendingAttackArrival');
$nextAttack->setAccessible(true);
$database->responses[] = array(array('endtime' => '123000'));
timedBuildingAssert(
    $nextAttack->invoke($automation, 123456) === 123000
        && strpos(end($database->queries), 'MIN(m.endtime) AS endtime') !== false
        && strpos(end($database->queries), 'm.endtime < 123456') !== false,
    'The building sweep barrier must use the first due attack arrival.'
);

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$constructorStart = strpos($source, 'public function __construct()');
$constructorEnd = strpos($source, 'private function getfieldDistance', $constructorStart);
$constructorSource = substr($source, $constructorStart, $constructorEnd - $constructorStart);
$attackPosition = strpos($constructorSource, '$this->sendunitsComplete();');
$finalBuildPosition = strrpos($constructorSource, '$this->buildComplete();');
$reinforcementPosition = strpos($constructorSource, '$this->sendreinfunitsComplete();');
$returnPosition = strpos($constructorSource, '$this->returnunitsComplete();');

timedBuildingAssert(
    $attackPosition !== false
        && $finalBuildPosition !== false
        && $attackPosition < $finalBuildPosition
        && $reinforcementPosition !== false
        && $reinforcementPosition < $attackPosition
        && $returnPosition !== false
        && $returnPosition < $attackPosition
        && strpos($constructorSource, '$pendingAttackTime = $this->nextPendingAttackArrival();') !== false
        && strpos($constructorSource, '$this->buildComplete($pendingAttackTime);') !== false
        && strpos($constructorSource, 'if($attackSweepDue || $buildSweepDue)') !== false,
    'Buildings must stop at the first pending attack until attacks and remaining completions are swept.'
);

$sendUnitsStart = strpos($source, 'private function sendunitsComplete()');
$sendUnitsEnd = strpos($source, 'private function oasisAnnexationOutcome', $sendUnitsStart);
if($sendUnitsEnd === false) {
    $sendUnitsEnd = strlen($source);
}
$sendUnitsSource = substr($source, $sendUnitsStart, $sendUnitsEnd - $sendUnitsStart);
$historicalBuildPosition = strpos(
    $sendUnitsSource,
    '$this->buildComplete((int)$data[\'endtime\'], false);'
);
$crannyPosition = strpos($sendUnitsSource, '$crannyProtection = $this->calculateCrannyProtection(');

timedBuildingAssert(
    $historicalBuildPosition !== false
        && $crannyPosition !== false
        && $historicalBuildPosition < $crannyPosition,
    'Each attack must complete only its preceding buildings before calculating cranny protection.'
);

echo "Timed attack/building regression: OK\n";
