<?php

$connectionSource = file_get_contents(dirname(__DIR__).'/config/connection.php');
$readConnectionValue = function($name) use ($connectionSource) {
	if(!preg_match('/define\(["\']'.preg_quote($name,'/').'["\']\s*,\s*["\']([^"\']*)["\']\s*\)/', $connectionSource, $match)) {
		throw new RuntimeException('No se pudo leer '.$name.' desde config/connection.php');
	}
	return $match[1];
};
define('SQL_SERVER', $readConnectionValue('SQL_SERVER'));
define('SQL_USER', $readConnectionValue('SQL_USER'));
define('SQL_PASS', $readConnectionValue('SQL_PASS'));
define('SQL_DB', $readConnectionValue('SQL_DB'));
define('TB_PREFIX', 'ranking_audit_');
define('INCLUDE_ADMIN', false);

require_once dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php';

function rankingQuery($sql) {
	global $database;
	$result = mysqli_query($database->connection, $sql);
	if(!$result) {
		throw new RuntimeException(mysqli_error($database->connection).' | '.$sql);
	}
	return $result;
}

function rankingAssert($condition, $message) {
	if(!$condition) {
		throw new RuntimeException($message);
	}
	echo '[OK] '.$message.PHP_EOL;
}

function rankingRow($sql) {
	return mysqli_fetch_assoc(rankingQuery($sql));
}

$tables = array('vdata', 'users', 'alidata');
try {
	foreach($tables as $table) {
		rankingQuery('DROP TABLE IF EXISTS '.TB_PREFIX.$table);
	}
	rankingQuery('CREATE TABLE '.TB_PREFIX.'alidata (
		id int NOT NULL PRIMARY KEY, ap bigint NOT NULL DEFAULT 0, dp bigint NOT NULL DEFAULT 0,
		clp bigint NOT NULL DEFAULT 0, RR bigint NOT NULL DEFAULT 0, oldrank bigint NOT NULL DEFAULT 0
	) ENGINE=InnoDB');
	rankingQuery('CREATE TABLE '.TB_PREFIX.'users (
		id int NOT NULL PRIMARY KEY, alliance int NOT NULL DEFAULT 0, tribe int NOT NULL DEFAULT 1,
		access int NOT NULL DEFAULT 2, ap bigint NOT NULL DEFAULT 0, dp bigint NOT NULL DEFAULT 0,
		clp bigint NOT NULL DEFAULT 0, RR bigint NOT NULL DEFAULT 0, oldrank bigint NOT NULL DEFAULT 0
	) ENGINE=InnoDB');
	rankingQuery('CREATE TABLE '.TB_PREFIX.'vdata (
		wref int NOT NULL PRIMARY KEY, owner int NOT NULL, pop int NOT NULL DEFAULT 0
	) ENGINE=InnoDB');
	rankingQuery('INSERT INTO '.TB_PREFIX.'alidata (id) VALUES (1),(2)');
	rankingQuery('INSERT INTO '.TB_PREFIX.'users (id,alliance,ap,dp,clp,RR,oldrank) VALUES
		(10,1,100,40,7,500,200),(11,1,20,10,3,-80,100),(12,2,9,4,2,25,50),(13,0,0,0,0,0,0)');
	rankingQuery('INSERT INTO '.TB_PREFIX.'vdata VALUES (101,10,200),(102,11,100),(103,12,50)');

	rankingAssert($database->reconcileAllianceWeeklyRankings(), 'la reconciliación se ejecuta');
	$ally = rankingRow('SELECT * FROM '.TB_PREFIX.'alidata WHERE id=1');
	rankingAssert((int)$ally['ap'] === 120 && (int)$ally['dp'] === 50 && (int)$ally['clp'] === 10 && (int)$ally['RR'] === 420,
		'la alianza se reconstruye como suma de sus miembros, incluido saqueo neto');

	rankingAssert($database->modifyWeeklyRankingPoints(10, 'dp', 13), 'se acredita defensa semanal');
	rankingAssert($database->modifyWeeklyRankingPoints(10, 'RR', -60), 'se descuenta saqueo sufrido');
	rankingAssert($database->modifyWeeklyRankingPoints(13, 'RR', 12), 'un jugador sin alianza conserva su puntaje personal');
	$user = rankingRow('SELECT dp,RR FROM '.TB_PREFIX.'users WHERE id=10');
	$ally = rankingRow('SELECT dp,RR FROM '.TB_PREFIX.'alidata WHERE id=1');
	rankingAssert((int)$user['dp'] === 53 && (int)$ally['dp'] === 63, 'defensa aplica el mismo delta a jugador y alianza');
	rankingAssert((int)$user['RR'] === 440 && (int)$ally['RR'] === 360, 'saqueo neto aplica el mismo delta a jugador y alianza');
	rankingAssert((int)rankingRow('SELECT RR FROM '.TB_PREFIX.'users WHERE id=13')['RR'] === 12,
		'el helper no exige que el jugador tenga alianza');

	rankingAssert($database->changeUserAlliance(10, 2), 'el jugador cambia de alianza');
	$user = rankingRow('SELECT alliance,clp,oldrank FROM '.TB_PREFIX.'users WHERE id=10');
	$old = rankingRow('SELECT ap,dp,clp,RR,oldrank FROM '.TB_PREFIX.'alidata WHERE id=1');
	$new = rankingRow('SELECT ap,dp,clp,RR,oldrank FROM '.TB_PREFIX.'alidata WHERE id=2');
	rankingAssert((int)$user['alliance'] === 2 && (int)$user['clp'] === 7 && (int)$user['oldrank'] === 200,
		'el cambio no fabrica crecimiento personal');
	rankingAssert((int)$old['ap'] === 20 && (int)$old['dp'] === 10 && (int)$old['clp'] === 3 && (int)$old['RR'] === -80 && (int)$old['oldrank'] === 100,
		'la alianza anterior conserva solo la contribución del miembro restante');
	rankingAssert((int)$new['ap'] === 109 && (int)$new['dp'] === 57 && (int)$new['clp'] === 9 && (int)$new['RR'] === 465 && (int)$new['oldrank'] === 250,
		'la alianza nueva recibe la contribución semanal y la población como línea base');

	$playerTemplate = file_get_contents(dirname(__DIR__).'/Templates/Ranking/player_top10.tpl');
	$allianceTemplate = file_get_contents(dirname(__DIR__).'/Templates/Ranking/ally_top10.tpl');
	rankingAssert(substr_count($playerTemplate, 'renderCurrentPlayerOutsideTop10($result2') === 4,
		'los cuatro rankings de jugador usan una fila exterior condicional');
	rankingAssert(substr_count($allianceTemplate, 'renderCurrentAllianceOutsideTop10($result2') === 4,
		'los cuatro rankings de alianza usan una fila exterior condicional');
} finally {
	foreach($tables as $table) {
		mysqli_query($database->connection, 'DROP TABLE IF EXISTS '.TB_PREFIX.$table);
	}
}

echo "Ranking consistency regression: OK\n";
