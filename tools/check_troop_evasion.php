<?php

error_reporting(E_ALL);

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require dirname(__DIR__).'/GameEngine/Automation.php';

$errors = array();
function troopEvasionAssert($condition, $message) {
	global $errors;
	if(!$condition) {
		$errors[] = $message;
	}
}

class TroopEvasionReturnDatabaseStub {
	public $returns = array();
	public $calls = array();

	public function getOrdinaryTroopReturnsInWindow($village, $windowStart, $windowEnd) {
		$this->calls[] = array((int)$village, (int)$windowStart, (int)$windowEnd);
		$matching = array();
		foreach($this->returns as $return) {
			if((int)$return['to'] !== (int)$village
				|| (int)$return['sort_type'] !== 4
				|| (int)$return['from'] === 0
				|| (int)$return['endtime'] < (int)$windowStart
				|| (int)$return['endtime'] > (int)$windowEnd) {
				continue;
			}
			$matching[] = $return;
		}
		return $matching;
	}
}

$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
$database = new TroopEvasionReturnDatabaseStub();
$attackArrival = 1000;

$database->returns = array(
	array('to' => 77, 'sort_type' => 4, 'from' => 88, 'endtime' => 995, 'proc' => 1)
);
troopEvasionAssert(
	$automation->hasOrdinaryTroopReturnInEvasionWindow($database, 77, $attackArrival),
	'A processed ordinary return five seconds before the attack must block troop evasion.'
);
troopEvasionAssert(
	$database->calls === array(array(77, 990, 1000)),
	'The return lookup must use the inclusive ten seconds before the scheduled attack arrival.'
);

$database->returns = array(
	array('to' => 77, 'sort_type' => 4, 'from' => 88, 'endtime' => 990, 'proc' => 0)
);
troopEvasionAssert(
	$automation->hasOrdinaryTroopReturnInEvasionWindow($database, 77, $attackArrival),
	'A return exactly ten seconds before the attack must block troop evasion.'
);

$database->returns = array(
	array('to' => 77, 'sort_type' => 4, 'from' => 88, 'endtime' => 1005, 'proc' => 0)
);
troopEvasionAssert(
	!$automation->hasOrdinaryTroopReturnInEvasionWindow($database, 77, $attackArrival),
	'A return after the attack must not block troop evasion.'
);

$database->returns = array(
	array('to' => 77, 'sort_type' => 4, 'from' => 0, 'endtime' => 995, 'proc' => 1)
);
troopEvasionAssert(
	!$automation->hasOrdinaryTroopReturnInEvasionWindow($database, 77, $attackArrival),
	'A return created by a previous automatic evasion must not block a new evasion.'
);

for($tribe = 1; $tribe <= 5; $tribe++) {
	$offset = ($tribe - 1) * 10;
	$units = array('hero' => 1, 'u'.(($tribe % 5) * 10 + 1) => 999);
	for($position = 1; $position <= 10; $position++) {
		$units['u'.($offset + $position)] = $position * 10;
	}

	$payload = $automation->buildTroopEvasionPayload($units, $tribe);
	troopEvasionAssert(
		count($payload) === 10 && !isset($payload[11]),
		'Tribe '.$tribe.' evasion payload must contain troops only, never the hero.'
	);
	for($position = 1; $position <= 10; $position++) {
		troopEvasionAssert(
			$payload[$position] === $position * 10,
			'Tribe '.$tribe.' position '.$position.' must map from its locally owned unit row.'
		);
	}
	troopEvasionAssert(
		$payload[10] === 100,
		'Tribe '.$tribe.' position ten must evade with the rest of the local troops.'
	);
}

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$heroInventorySource = file_get_contents(dirname(__DIR__).'/hero_inventory.php');
$mysqliSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$installerSchema = file_get_contents(dirname(__DIR__).'/install/data/sql.sql');
$migrationsSource = file_get_contents(dirname(__DIR__).'/tools/migrations.sql');

troopEvasionAssert(
	strpos($automationSource, 'buildTroopEvasionPayload($DefenderUnit, $targettribe)') !== false
		&& strpos($automationSource, 'getEnforceVillage($data[\'to\'], 0)') !== false,
	'Automatic evasion must be built from the local unit row before reinforcements are aggregated.'
);
troopEvasionAssert(
	strpos($heroInventorySource, 'hash_equals((string)$session->mchecker') !== false
		&& strpos($heroInventorySource, '$_POST[\'a\'] === \'heroHiding\'') !== false,
	'Hero hiding updates must use POST and the session checker.'
);
troopEvasionAssert(
	strpos($heroInventorySource, 'showhero') === false
		&& strpos($heroInventorySource, 'hidehero') === false,
	'Legacy GET mutations for the hero preference must be removed.'
);
troopEvasionAssert(
	strpos($heroInventorySource, 'independiente de la evasión de tropas del Club de Oro') !== false,
	'The inventory must explain that hero hiding is independent from troop evasion.'
);

// Sólo queda el driver MySQLi: el de `mysql_*` se borró porque no corre en PHP 7.
foreach(array('MySQLi' => $mysqliSource) as $adapter => $source) {
	$methodStart = strpos($source, 'function getOrdinaryTroopReturnsInWindow');
	$methodEnd = $methodStart === false ? false : strpos($source, 'function getMovement(', $methodStart);
	$methodSource = $methodStart === false || $methodEnd === false
		? ''
		: substr($source, $methodStart, $methodEnd - $methodStart);
	troopEvasionAssert(
		strpos($methodSource, 'AND `from` <> 0') !== false
			&& strpos($methodSource, 'AND endtime >= $windowStart') !== false
			&& strpos($methodSource, 'AND endtime <= $windowEnd') !== false
			&& strpos($methodSource, 'proc = 0') === false,
		$adapter.' return lookup must include processed ordinary returns only inside the attack window.'
	);
}
troopEvasionAssert(
	strpos($installerSchema, 'KEY `evasion_return_window` (`to`,`sort_type`,`endtime`,`from`)') !== false
		&& strpos($migrationsSource, 'ADD INDEX IF NOT EXISTS evasion_return_window (`to`, sort_type, endtime, `from`)') !== false,
	'New and existing worlds must index the ordinary return-window lookup.'
);

if(!empty($errors)) {
	foreach($errors as $error) {
		echo "FAIL: ".$error."\n";
	}
	exit(1);
}

echo "Troop evasion regression: OK\n";
