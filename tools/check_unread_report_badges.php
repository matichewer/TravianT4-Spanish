<?php

require_once dirname(__DIR__) . '/config/connection.php';
if(DB_TYPE !== 1) {
	fwrite(STDERR, "Unread report badge check requires the MySQLi database driver.\n");
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
$schema = "CREATE TEMPORARY TABLE $table ("
	."id INT NOT NULL PRIMARY KEY, uid INT NOT NULL, ntype INT NOT NULL, data TEXT NOT NULL, viewed TINYINT NOT NULL DEFAULT 0,"
	."archive TINYINT NOT NULL DEFAULT 0, del TINYINT NOT NULL DEFAULT 0, ally INT NOT NULL DEFAULT 0, time INT NOT NULL DEFAULT 0"
	.") ENGINE=InnoDB";
$assert(mysqli_query($database->connection, $schema), mysqli_error($database->connection));

$reports = array(
	array(1, ''), array(25, ''), array(4, ''), array(7, ''), array(0, ''), array(22, ''),
	array(10, '1,2,3'), array(13, '1,2,3'), array(10, '1,2,3,route'), array(26, '1,2,3'),
	array(8, ''), array(9, ''), array(21, '')
);
foreach($reports as $index => $report) {
	$id = $index + 1;
	$type = (int)$report[0];
	$data = mysqli_real_escape_string($database->connection, $report[1]);
	$assert(mysqli_query($database->connection, "INSERT INTO $table (id,uid,ntype,data,viewed,archive,del) VALUES ($id, 101, $type, '$data', 0, 0, 0)"), mysqli_error($database->connection));
}
$assert(mysqli_query($database->connection, "INSERT INTO $table (id,uid,ntype,data,viewed,archive,del) VALUES"
	." (20, 101, 1, '', 1, 0, 0), (21, 202, 8, '', 0, 0, 0),"
	." (22, 101, 9, '', 0, 1, 0), (23, 101, 10, '', 0, 0, 1)"), mysqli_error($database->connection));

$counts = $database->getUnreadNoticeCountsByCategory(101);
$assert($counts === array(
	'attack' => 2,
	'defense' => 2,
	'spy' => 2,
	'trade' => 2,
	'routes' => 2,
	'reinforcement' => 1,
	'adventure' => 1,
	'misc' => 1
), 'Unread reports were not grouped into the expected categories.');
$assert(array_sum($counts) === (int)$database->getUnreadNoticeCount(101), 'Category counts do not match the total unread count.');
$assert($counts['adventure'] === 1 && $counts['misc'] === 1, 'Adventure reports were not separated from other miscellaneous reports.');
$assert((int)$database->getUnreadNoticeCount(101) === count($reports), 'Archived or deleted reports leaked into the visible unread total.');
$assert(array_sum($database->getUnreadNoticeCountsByCategory(202)) === 1, 'Unread report categories leaked between players.');
$assert(array_sum($database->getUnreadNoticeCountsByCategory(303)) === 0, 'A player without reports received a badge.');

$routeNeighbors = $database->getNoticeNeighbors(101, 0, 9, 7, false);
$assert($routeNeighbors === array('previous' => 10, 'next' => 0), 'Route detail navigation included a non-route report or lost its category context.');
$assert(mysqli_query($database->connection, "UPDATE $table SET viewed = 1 WHERE id = 12"), mysqli_error($database->connection));
$unreadNeighbors = $database->getNoticeNeighbors(101, 0, 12, 8, false);
$assert($unreadNeighbors === array('previous' => 13, 'next' => 11), 'Unread detail navigation lost the current report after opening it.');

echo "Unread report badges: OK (category mapping, visibility, totals, owner scope, filtered neighbors).\n";

?>
