<?php

$connectionSource = file_get_contents(dirname(__DIR__).'/config/connection.php');
$readConnectionValue = function($name) use ($connectionSource) {
	if(!preg_match('/define\(["\']'.preg_quote($name,'/').'["\']\s*,\s*["\']([^"\']*)["\']\s*\)/',$connectionSource,$match)) {
		throw new RuntimeException('No se pudo leer '.$name.' desde config/connection.php');
	}
	return $match[1];
};
define('SQL_SERVER',$readConnectionValue('SQL_SERVER'));
define('SQL_USER',$readConnectionValue('SQL_USER'));
define('SQL_PASS',$readConnectionValue('SQL_PASS'));
define('SQL_DB',$readConnectionValue('SQL_DB'));
define('TB_PREFIX','training_queue_audit_');

require_once dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php';

function trainingQueueQuery($sql) {
	global $database;
	$result = mysqli_query($database->connection,$sql);
	if(!$result) {
		throw new RuntimeException(mysqli_error($database->connection).' | '.$sql);
	}
	return $result;
}

function trainingQueueAssert($condition,$message) {
	if(!$condition) {
		throw new RuntimeException($message);
	}
	echo '[OK] '.$message.PHP_EOL;
}

function trainingQueueRows() {
	$result = trainingQueueQuery('SELECT * FROM '.TB_PREFIX.'training ORDER BY id');
	$rows = array();
	while($row = mysqli_fetch_assoc($result)) {
		$rows[] = $row;
	}
	return $rows;
}

$created = false;
try {
	$created = true;
	trainingQueueQuery('DROP TABLE IF EXISTS '.TB_PREFIX.'training');
	trainingQueueQuery('DROP TABLE IF EXISTS '.TB_PREFIX.'units');
	trainingQueueQuery('CREATE TABLE '.TB_PREFIX.'training (
		id int unsigned NOT NULL AUTO_INCREMENT,
		vref int unsigned NOT NULL,
		unit tinyint unsigned NOT NULL,
		amt int unsigned NOT NULL,
		pop int unsigned NOT NULL,
		timestamp int unsigned NOT NULL,
		eachtime int unsigned NOT NULL,
		timestamp2 int unsigned NOT NULL,
		PRIMARY KEY (id)
	) ENGINE=MyISAM');
	trainingQueueQuery('CREATE TABLE '.TB_PREFIX.'units (
		vref int unsigned NOT NULL,
		u1 bigint unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY (vref)
	) ENGINE=MyISAM');
	trainingQueueQuery('INSERT INTO '.TB_PREFIX.'units (vref,u1) VALUES (700,0)');

	trainingQueueAssert($database->trainUnit(700,1,2,1,10,time()+20,0),'first batch is inserted');
	$rows = trainingQueueRows();
	trainingQueueAssert(count($rows) === 1 && (int)$rows[0]['amt'] === 2 && (int)$rows[0]['eachtime'] === 10,'first batch stores quantity and duration');

	$firstEnd = (int)$rows[0]['timestamp'];
	trainingQueueAssert($database->trainUnit(700,1,3,1,10,time()+30,0),'compatible adjacent order is accepted');
	$rows = trainingQueueRows();
	trainingQueueAssert(count($rows) === 1 && (int)$rows[0]['amt'] === 5,'compatible adjacent order merges');
	trainingQueueAssert((int)$rows[0]['timestamp'] === $firstEnd + 30,'compatible merge extends final time exactly');

	trainingQueueAssert($database->trainUnit(700,1,2,1,5,time()+10,0),'changed-duration order is accepted');
	$rows = trainingQueueRows();
	trainingQueueAssert(count($rows) === 2,'changed duration creates a separate batch');
	trainingQueueAssert((int)$rows[1]['eachtime'] === 5 && (int)$rows[1]['timestamp2'] === (int)$rows[0]['timestamp'] + 5,'new batch starts after the preceding batch');

	trainingQueueAssert($database->trainUnit(700,55,1,1,10,time()+10,0) === false,'unknown queue family is rejected');

	$batchId = (int)$rows[0]['id'];
	trainingQueueQuery('UPDATE '.TB_PREFIX.'training SET amt=3,timestamp2='.(time()-25).',eachtime=10 WHERE id='.$batchId);
	trainingQueueAssert($database->completeTrainingBatch($batchId,1,3,30),'overdue completed units and queue state update atomically');
	$unitRow = mysqli_fetch_assoc(trainingQueueQuery('SELECT u1 FROM '.TB_PREFIX.'units WHERE vref=700'));
	trainingQueueAssert((int)$unitRow['u1'] === 3,'overdue units are credited exactly once');
	trainingQueueAssert($database->completeTrainingBatch($batchId,1,1,10) === false,'exhausted batch cannot credit an extra unit');
	$unitRow = mysqli_fetch_assoc(trainingQueueQuery('SELECT u1 FROM '.TB_PREFIX.'units WHERE vref=700'));
	trainingQueueAssert((int)$unitRow['u1'] === 3,'failed repeat completion leaves unit count unchanged');
} finally {
	if($created) {
		mysqli_query($database->connection,'DROP TABLE IF EXISTS '.TB_PREFIX.'training');
		mysqli_query($database->connection,'DROP TABLE IF EXISTS '.TB_PREFIX.'units');
	}
}

echo "Training queue batch checks passed.\n";
