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
	."id INT NOT NULL PRIMARY KEY, uid INT NOT NULL, ntype INT NOT NULL, viewed TINYINT NOT NULL DEFAULT 0"
	.") ENGINE=InnoDB";
$assert(mysqli_query($database->connection, $schema), mysqli_error($database->connection));

$types = array(1, 25, 4, 7, 0, 22, 10, 13, 8, 9, 21);
foreach($types as $index => $type) {
	$id = $index + 1;
	$assert(mysqli_query($database->connection, "INSERT INTO $table VALUES ($id, 101, $type, 0)"), mysqli_error($database->connection));
}
$assert(mysqli_query($database->connection, "INSERT INTO $table VALUES (20, 101, 1, 1), (21, 202, 8, 0)"), mysqli_error($database->connection));

$counts = $database->getUnreadNoticeCountsByCategory(101);
$assert($counts === array(
	'attack' => 2,
	'defense' => 2,
	'spy' => 2,
	'trade' => 2,
	'reinforcement' => 1,
	'misc' => 2
), 'Unread reports were not grouped into the expected categories.');
$assert(array_sum($counts) === (int)$database->getUnreadNoticeCount(101), 'Category counts do not match the total unread count.');
$assert(array_sum($database->getUnreadNoticeCountsByCategory(202)) === 1, 'Unread report categories leaked between players.');
$assert(array_sum($database->getUnreadNoticeCountsByCategory(303)) === 0, 'A player without reports received a badge.');

echo "Unread report badges: OK (category mapping, totals, read state, owner scope).\n";

?>
