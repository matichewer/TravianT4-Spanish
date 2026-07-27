<?php

require_once dirname(__DIR__) . '/config/connection.php';
if(DB_TYPE !== 1) {
	fwrite(STDERR, "Report privacy integration check requires the MySQLi database driver.\n");
	exit(1);
}

require_once dirname(__DIR__) . '/GameEngine/Database/db_MYSQLi.php';

$assert = function($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, $message . "\n");
		exit(1);
	}
};

$table = TB_PREFIX . 'ndata';
$schema = "CREATE TEMPORARY TABLE $table (
	id INT NOT NULL PRIMARY KEY,
	uid INT NOT NULL,
	toWref INT NOT NULL DEFAULT 0,
	ally INT NOT NULL DEFAULT 0,
	topic VARCHAR(255) NOT NULL DEFAULT '',
	ntype INT NOT NULL DEFAULT 0,
	data TEXT NOT NULL,
	time INT NOT NULL DEFAULT 0,
	viewed TINYINT NOT NULL DEFAULT 0,
	archive TINYINT NOT NULL DEFAULT 0,
	del TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB";
$assert(mysqli_query($database->connection, $schema), mysqli_error($database->connection));

$fixtures = array(
	array(1, 101, 7, 'military', 1),
	array(2, 101, 7, 'spy', 20),
	array(3, 101, 7, 'reinforcement attacked', 15),
	array(4, 101, 7, 'reinforcement arrived', 8),
	array(5, 101, 7, 'adventure', 9),
	array(6, 101, 7, 'trade', 10),
	array(7, 102, 0, 'private military', 3),
);
foreach($fixtures as $fixture) {
	list($id, $uid, $ally, $topic, $ntype) = $fixture;
	$topic = mysqli_real_escape_string($database->connection, $topic);
	$query = "INSERT INTO $table (id, uid, ally, topic, ntype, data)"
		." VALUES ($id, $uid, $ally, '$topic', $ntype, 'fixture')";
	$assert(mysqli_query($database->connection, $query), mysqli_error($database->connection));
}

$assert($database->getAuthorizedNotice(101, 0, 6)['topic'] === 'trade', 'Owner cannot read a private report.');
$assert($database->getAuthorizedNotice(201, 7, 1)['topic'] === 'military', 'Alliance member cannot read a combat report.');
$assert($database->getAuthorizedNotice(201, 7, 2)['topic'] === 'spy', 'Alliance member cannot read an espionage report.');
$assert($database->getAuthorizedNotice(201, 7, 3)['topic'] === 'reinforcement attacked', 'Alliance member cannot read an attacked-reinforcement report.');
$assert($database->getAuthorizedNotice(201, 7, 4) === false, 'Alliance member can read another player\'s reinforcement-arrival report.');
$assert($database->getAuthorizedNotice(201, 7, 5) === false, 'Alliance member can read another player\'s adventure report.');
$assert($database->getAuthorizedNotice(201, 7, 6) === false, 'Alliance member can read another player\'s trade report.');
$assert($database->getAuthorizedNotice(201, 0, 1) === false, 'Player without an alliance can read an alliance report.');
$assert($database->getAuthorizedNotice(999, 99, 1) === false, 'Unrelated player can read a guessed report identifier.');
$assert((int)$database->getAuthorizedNotice(101, 0, '1 OR 1=1')['id'] === 1, 'Report identifier was not normalized to an integer.');
$assert($database->getNotice2(1, 'topic') === 'military', 'Allowed report field lookup failed.');
$assert($database->getNotice2(1, 'topic, data') === false, 'Arbitrary report field expression was accepted.');

$getState = function($id) use ($database, $table) {
	$result = mysqli_query($database->connection, "SELECT viewed, archive, del FROM $table WHERE id = ".(int)$id);
	return mysqli_fetch_assoc($result);
};

$database->archiveNotice(1, 999);
$database->removeNotice(1, 999);
$database->noticeViewed(1, 999);
$state = $getState(1);
$assert((int)$state['archive'] === 0, 'Forged archive changed another player\'s report.');
$assert((int)$state['del'] === 0, 'Forged delete changed another player\'s report.');
$assert((int)$state['viewed'] === 0, 'Forged read action changed another player\'s report.');

$database->archiveNotice(1, 101);
$database->removeNotice(1, 101);
$state = $getState(1);
$assert((int)$state['archive'] === 1 && (int)$state['del'] === 1, 'Owner report actions did not apply.');
$database->unarchiveNotice(1, 999);
$assert((int)$getState(1)['archive'] === 1, 'Forged unarchive changed another player\'s report.');
$database->unarchiveNotice(1, 101);
$assert((int)$getState(1)['archive'] === 0, 'Owner could not unarchive their report.');

require_once dirname(__DIR__) . '/GameEngine/Message.php';
$GLOBALS['session'] = (object)array('uid' => 201, 'alliance' => 7, 'plus' => 0);
$allianceMessage = new Message();
$allianceMessage->noticeType(array('id' => 1));
$assert((int)$allianceMessage->readingNotice['id'] === 1, 'Direct report view rejected an authorized alliance report.');
$allianceMessage->noticeType(array('id' => 6));
$assert(empty($allianceMessage->readingNotice), 'Direct report view exposed a private alliance report.');

$GLOBALS['session'] = (object)array('uid' => 999, 'alliance' => 99, 'plus' => 0);
$outsiderMessage = new Message();
$outsiderMessage->noticeType(array('id' => 1));
$assert(empty($outsiderMessage->readingNotice), 'Direct report view exposed a report to an unrelated player.');

for($unitId = 0; $unitId <= 50; $unitId++) {
	if(!defined('U'.$unitId)) {
		define('U'.$unitId, 'U'.$unitId);
	}
}
foreach(array('WOOD', 'CLAY', 'IRON', 'CROP') as $resourceName) {
	if(!defined($resourceName)) {
		define($resourceName, $resourceName);
	}
}

$renderReportReference = function($uid, $alliance, $reportId) {
	$GLOBALS['session'] = (object)array('uid' => $uid, 'alliance' => $alliance);
	$input = '[message][report0]'.(int)$reportId.'[/report0][/message]';
	$alliance = -1;
	$player = -1;
	$report = 0;
	$coor = -1;
	$rep1 = 89;
	$rep2 = 89;
	$rep3 = 89;
	$xx = 0;
	$yy = 0;
	$cx = 0;
	$cy = 0;
	include dirname(__DIR__) . '/GameEngine/BBCode.php';
	return $bbcoded;
};

$ownerReference = $renderReportReference(101, 0, 6);
$allianceReference = $renderReportReference(201, 7, 1);
$privateAllianceReference = $renderReportReference(201, 7, 6);
$outsiderReference = $renderReportReference(999, 99, 1);
$assert(strpos($ownerReference, 'trade') !== false && strpos($ownerReference, 'berichte.php?id=6') !== false, 'Owner report BBCode was not rendered.');
$assert(strpos($allianceReference, 'military') !== false && strpos($allianceReference, 'berichte.php?id=1') !== false, 'Authorized alliance report BBCode was not rendered.');
$assert(strpos($privateAllianceReference, 'trade') === false && strpos($privateAllianceReference, 'berichte.php') === false, 'Private report metadata leaked through alliance BBCode.');
$assert(strpos($outsiderReference, 'military') === false && strpos($outsiderReference, 'berichte.php') === false, 'Report metadata leaked to an unrelated BBCode reader.');

$messageSource = file_get_contents(dirname(__DIR__) . '/GameEngine/Message.php');
$bbcodeSource = file_get_contents(dirname(__DIR__) . '/GameEngine/BBCode.php');
$attackSource = file_get_contents(dirname(__DIR__) . '/a2b.php');
$automationSource = file_get_contents(dirname(__DIR__) . '/GameEngine/Automation.php');
$spyTemplate = file_get_contents(dirname(__DIR__) . '/Templates/Notice/0.tpl');
$unknownDefenderTemplate = file_get_contents(dirname(__DIR__) . '/Templates/Notice/unknown_defender.tpl');
$defeatTemplate = file_get_contents(dirname(__DIR__) . '/Templates/Notice/3.tpl');

$assert(strpos($messageSource, 'hasSharedReportMessage') === false, 'Messages still grant report access.');
$assert(strpos($messageSource, 'getAuthorizedNotice') !== false, 'Direct report reads bypass centralized authorization.');
$assert(strpos($bbcodeSource, 'getAuthorizedNotice') !== false, 'Report BBCode bypasses reader authorization.');
$assert(strpos($attackSource, 'getAuthorizedNotice') !== false, 'Repeat-attack loading bypasses reader authorization.');
$assert(strpos($attackSource, 'getNoticeData($bid)') === false, 'Repeat-attack loading still reads raw report data.');
foreach(glob(dirname(__DIR__) . '/Templates/Notice/[0-9]*.tpl') as $noticeTemplate) {
	$templateSource = file_get_contents($noticeTemplate);
	$assert(strpos($templateSource, 'getNotice2(') === false, basename($noticeTemplate).' re-queries report data outside the authorized row.');
}

preg_match('/\$data_fail\s*=\s*(.*);/', $automationSource, $failedPayloadMatch);
$assert(!empty($failedPayloadMatch[1]), 'Failed battle payload was not found.');
$failedPayloadSource = $failedPayloadMatch[1];
$assert(strpos($failedPayloadSource, 'no-defense-info-v1') !== false, 'Failed battle payload has no privacy marker.');
foreach(array('$targettribe', '$rom', '$ger', '$gal', '$nat', '$natar') as $defenseSignal) {
	$assert(strpos($failedPayloadSource, $defenseSignal) === false, 'Failed battle payload still stores defensive signal '.$defenseSignal.'.');
}
$assert(strpos($spyTemplate, '$targettribe = $faild ? 0') !== false, 'Failed espionage reports can still render stored defender tribes.');
$assert(strpos($spyTemplate, 'if(!$faild)') !== false, 'Failed espionage reports can still render defensive sections.');
$assert(strpos($spyTemplate, 'unknown_defender.tpl') !== false, 'Failed espionage reports omit the public defender reference.');
$assert(strpos($unknownDefenderTemplate, 'spieler.php?uid=') !== false, 'Failed espionage reports omit the defender player link.');
$assert(strpos($unknownDefenderTemplate, 'karte.php?d=') !== false, 'Failed espionage reports omit the defender village link.');
preg_match_all('/\$dataarray\[(\d+)\]/', $unknownDefenderTemplate, $unknownDefenderFields);
foreach($unknownDefenderFields[1] as $unknownDefenderField) {
	$assert((int)$unknownDefenderField <= 32, 'Unknown defender placeholders read hidden defensive report data.');
}
$assert(strpos($defeatTemplate, '$targettribe = 0') !== false, 'Total-loss attack reports can still render defender tribes.');

echo "Report privacy: OK (authorization, secondary routes, owner-scoped mutations, total-loss secrecy).\n";

?>
