<?php
// `updateHero` regenera vida con `heroRegenerationPerDay(autoregen) / 86400 * (now - lastupdate)`. Si el
// reloj solo avanzaba estando herido, un héroe que pasó días al 100% arrastraba un
// `lastupdate` viejo y la primera herida se curaba entera en la siguiente pasada.

function heroClockAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

require_once dirname(__DIR__).'/config/connection.php';
require_once dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Hero.php';

$heroTable = TB_PREFIX.'hero';
$sourceTable = SQL_DB.'.'.TB_PREFIX.'hero';
heroClockAssert(
	mysqli_query($database->connection, "CREATE TEMPORARY TABLE $heroTable AS SELECT * FROM $sourceTable WHERE 0"),
	'Se pudo crear la tabla temporal de héroes'
);

$uid = 900201;
$staleClock = time() - 5 * 86400;
heroClockAssert(
	mysqli_query(
		$database->connection,
		"INSERT INTO $heroTable (heroid,uid,level,health,autoregen,dead,lastupdate)"
		." VALUES (1,$uid,0,100,10,0,$staleClock)"
	),
	'Se pudo crear un héroe sano con el reloj de 5 días atrás'
);

// Regeneración tal cual la calcula Automation::updateHero.
$regenerated = function($hero, $speed) {
	return $hero['health'] + heroRegenerationPerDay($hero['autoregen'], $speed) / 86400 * (time() - $hero['lastupdate']);
};

$before = $database->getHeroData($uid);
heroClockAssert((int)$before['lastupdate'] === $staleClock, 'El héroe arranca con el reloj viejo');

$database->modifyHero2('health', 40, $uid, 2);
$damaged = $database->getHeroData($uid);

heroClockAssert(abs((float)$damaged['health'] - 60.0) < 0.0001, 'El daño dejó la vida en 60');
heroClockAssert(
	(int)$damaged['lastupdate'] >= $staleClock + 5 * 86400 - 5,
	'El daño puso el reloj de regeneración al día'
);
heroClockAssert(
	$regenerated($damaged, 10) < 61,
	'Tras el daño la regeneración suma minutos, no los 5 días acumulados'
);

// Con el reloj viejo la herida se habría borrado sola en la primera pasada.
$stale = $damaged;
$stale['lastupdate'] = $staleClock;
heroClockAssert(
	$regenerated($stale, 10) > 100,
	'El escenario probado es el que curaba de golpe con el reloj viejo'
);

// Curar también reinicia el conteo: si no, el tiempo previo se sumaba encima.
mysqli_query($database->connection, "UPDATE $heroTable SET health = 50, lastupdate = $staleClock WHERE uid = $uid");
$database->modifyHero2('health', 10, $uid, 1);
$healed = $database->getHeroData($uid);
heroClockAssert(abs((float)$healed['health'] - 60.0) < 0.0001, 'La venda dejó la vida en 60');
heroClockAssert(
	(int)$healed['lastupdate'] >= $staleClock + 5 * 86400 - 5,
	'La venda también puso el reloj al día'
);

// Las columnas que no son vida no tocan el reloj.
mysqli_query($database->connection, "UPDATE $heroTable SET lastupdate = $staleClock WHERE uid = $uid");
$database->modifyHero2('experience', 25, $uid, 1);
$experienced = $database->getHeroData($uid);
heroClockAssert((int)$experienced['experience'] === 25, 'La experiencia se sumó');
heroClockAssert((int)$experienced['lastupdate'] === $staleClock, 'Sumar experiencia no tocó el reloj');

echo "Hero regeneration clock regression: OK\n";
