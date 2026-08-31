<?php

require_once dirname(__DIR__) . '/config/connection.php';
if(DB_TYPE !== 1) {
	fwrite(STDERR, "Alliance report navigation check requires the MySQLi database driver.\n");
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
// `data` hace falta desde que el filtro 0 de getNoticeNeighbors() excluye los informes
// de ruta comercial (`ntype IN (10,11,12,13) AND data LIKE '%,route'`). Sin la columna la
// consulta no compila y el doble devolvía vecinos vacíos en vez de fallar a la vista.
$schema = "CREATE TEMPORARY TABLE $table ("
	."id INT NOT NULL PRIMARY KEY, uid INT NOT NULL, ally INT NOT NULL, ntype INT NOT NULL, "
	."archive TINYINT NOT NULL DEFAULT 0, del TINYINT NOT NULL DEFAULT 0, time INT NOT NULL, "
	."data TEXT NOT NULL DEFAULT ''"
	.") ENGINE=InnoDB";
$assert(mysqli_query($database->connection, $schema), mysqli_error($database->connection));

$rows = array(
	// Three alliance events, newest first. The middle one belongs to another member.
	array(101, 10, 7, 1, 300),
	array(102, 20, 7, 4, 200),
	array(103, 10, 7, 22, 100),
	// Personal and unrelated-alliance reports must not enter alliance navigation.
	array(104, 10, 0, 10, 250),
	array(105, 10, 8, 1, 225),
	// Reinforcement events are deliberately absent from the alliance events screen.
	array(106, 20, 7, 15, 175)
);
foreach($rows as $row) {
	$sql = "INSERT INTO $table (id, uid, ally, ntype, time) VALUES ("
		.implode(',', array_map('intval', $row)).")";
	$assert(mysqli_query($database->connection, $sql), mysqli_error($database->connection));
}

$neighbors = $database->getNoticeNeighbors(10, 7, 102, 0, true);
$assert($neighbors === array('previous' => 101, 'next' => 103), 'Alliance navigation did not stay inside alliance military events.');

$cageReport = array(107, 20, 7, 25, 50);
$sql = "INSERT INTO $table (id, uid, ally, ntype, time) VALUES ("
	.implode(',', array_map('intval', $cageReport)).")";
$assert(mysqli_query($database->connection, $sql), mysqli_error($database->connection));
$assert($database->getAuthorizedNotice(10, 7, 107) !== false, 'Alliance members could not open a shared cage report.');

$personalNeighbors = $database->getNoticeNeighbors(10, 7, 104, 2, false);
$assert($personalNeighbors === array('previous' => 0, 'next' => 0), 'Personal report navigation changed unexpectedly.');

echo "Alliance report navigation: OK (alliance scope, ordering, excluded event types).\n";

?>
