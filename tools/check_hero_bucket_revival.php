<?php

require_once dirname(__DIR__).'/GameEngine/Database.php';

function heroBucketAssert($condition,$message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

$heroTable = TB_PREFIX.'hero';
$itemsTable = TB_PREFIX.'heroitems';
$trainingTable = TB_PREFIX.'training';
$villagesTable = TB_PREFIX.'vdata';
$unitsTable = TB_PREFIX.'units';

heroBucketAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $heroTable (uid INT UNSIGNED NOT NULL, wref INT UNSIGNED NOT NULL, dead TINYINT NOT NULL, health FLOAT NOT NULL, PRIMARY KEY(uid)) ENGINE=MyISAM"),
	'Could not create temporary hero table');
heroBucketAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $itemsTable (id INT UNSIGNED NOT NULL, uid INT UNSIGNED NOT NULL, btype INT UNSIGNED NOT NULL, type INT UNSIGNED NOT NULL, num INT NOT NULL, proc INT UNSIGNED NOT NULL, PRIMARY KEY(id)) ENGINE=MyISAM"),
	'Could not create temporary hero item table');
heroBucketAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $trainingTable (id INT UNSIGNED NOT NULL, vref INT UNSIGNED NOT NULL, unit INT UNSIGNED NOT NULL, PRIMARY KEY(id)) ENGINE=MyISAM"),
	'Could not create temporary training table');
heroBucketAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $villagesTable (wref INT UNSIGNED NOT NULL, owner INT UNSIGNED NOT NULL, PRIMARY KEY(wref)) ENGINE=MyISAM"),
	'Could not create temporary village table');
heroBucketAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $unitsTable (vref INT UNSIGNED NOT NULL, hero INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(vref)) ENGINE=MyISAM"),
	'Could not create temporary units table');

heroBucketAssert(mysqli_query($database->connection,
	"INSERT INTO $villagesTable (wref,owner) VALUES (910101,910001),(910102,910001),(910201,910002)"),
	'Could not seed villages');
heroBucketAssert(mysqli_query($database->connection,
	"INSERT INTO $unitsTable (vref,hero) VALUES (910101,0),(910102,0),(910201,0)"),
	'Could not seed units');
heroBucketAssert(mysqli_query($database->connection,
	"INSERT INTO $heroTable (uid,wref,dead,health) VALUES (910001,910101,1,0),(910002,910201,1,0)"),
	'Could not seed heroes');
heroBucketAssert(mysqli_query($database->connection,
	"INSERT INTO $itemsTable (id,uid,btype,type,num,proc) VALUES (1,910001,12,0,1,0),(2,910001,12,0,1,0),(3,910001,12,0,1,0),(4,910002,12,0,1,0)"),
	'Could not seed buckets');
heroBucketAssert(mysqli_query($database->connection,
	"INSERT INTO $trainingTable (id,vref,unit) VALUES (1,910101,0)"),
	'Could not seed paid revival');

$result = $database->consumeHeroRevivalBucket(910001,1,910102);
heroBucketAssert($result['ok'] && $result['vref']===910101,'Paid revival village was not preferred');
$hero = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT wref,dead,health FROM $heroTable WHERE uid=910001"));
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT proc FROM $itemsTable WHERE id=1"));
$placedResult = mysqli_query($database->connection,"SELECT SUM(u.hero) AS total,MAX(CASE WHEN u.hero=1 THEN u.vref ELSE 0 END) AS hero_vref FROM $unitsTable AS u INNER JOIN $villagesTable AS v ON v.wref=u.vref WHERE v.owner=910001");
heroBucketAssert($placedResult!==false,'Could not inspect queued hero placement: '.mysqli_error($database->connection));
$placed = mysqli_fetch_assoc($placedResult);
heroBucketAssert((int)$hero['wref']===910101 && (int)$hero['dead']===0 && (float)$hero['health']===100.0,'Queued bucket revival left incorrect hero state');
heroBucketAssert((int)$placed['total']===1 && (int)$placed['hero_vref']===910101,'Queued bucket revival did not leave exactly one hero');
heroBucketAssert((int)$item['proc']===1,'Queued bucket revival did not consume the bucket');
heroBucketAssert(mysqli_num_rows(mysqli_query($database->connection,"SELECT id FROM $trainingTable WHERE unit=0"))===0,'Queued bucket revival left its training row');

heroBucketAssert(mysqli_query($database->connection,"UPDATE $heroTable SET wref=910101,dead=1,health=0 WHERE uid=910001"),'Could not reset hero');
heroBucketAssert(mysqli_query($database->connection,"UPDATE $unitsTable SET hero=0 WHERE vref IN (910101,910102)"),'Could not reset hero placement');
$result = $database->consumeHeroRevivalBucket(910001,2,910102);
heroBucketAssert($result['ok'] && $result['vref']===910102,'Ordinary bucket revival ignored selected village');
$hero = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT wref,dead,health FROM $heroTable WHERE uid=910001"));
$placedResult = mysqli_query($database->connection,"SELECT SUM(u.hero) AS total,MAX(CASE WHEN u.hero=1 THEN u.vref ELSE 0 END) AS hero_vref FROM $unitsTable AS u INNER JOIN $villagesTable AS v ON v.wref=u.vref WHERE v.owner=910001");
heroBucketAssert($placedResult!==false,'Could not inspect ordinary hero placement: '.mysqli_error($database->connection));
$placed = mysqli_fetch_assoc($placedResult);
heroBucketAssert((int)$hero['wref']===910102 && (int)$hero['dead']===0 && (float)$hero['health']===100.0,'Ordinary bucket revival left incorrect hero state');
heroBucketAssert((int)$placed['total']===1 && (int)$placed['hero_vref']===910102,'Ordinary bucket revival did not leave exactly one hero');

$before = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT wref,dead,health FROM $heroTable WHERE uid=910001"));
$result = $database->consumeHeroRevivalBucket(910001,3,910101);
$after = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT wref,dead,health FROM $heroTable WHERE uid=910001"));
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT proc FROM $itemsTable WHERE id=3"));
heroBucketAssert(!$result['ok'] && $before===$after && (int)$item['proc']===0,'Alive hero consumed a bucket or changed state');

$result = $database->consumeHeroRevivalBucket(910002,4,910101);
$hero = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT wref,dead,health FROM $heroTable WHERE uid=910002"));
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT proc FROM $itemsTable WHERE id=4"));
heroBucketAssert(!$result['ok'] && (int)$hero['dead']===1 && (int)$item['proc']===0,'Foreign destination changed revival state');

$controller = file_get_contents(dirname(__DIR__).'/GameEngine/Inventory.php');
heroBucketAssert(strpos($controller,'consumeHeroRevivalBucket($uid,$data[\'id\'],$village->wid)')!==false,'Inventory does not use centralized bucket revival');

echo "Hero bucket revival checks passed.\n";
