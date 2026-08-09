<?php
// El modo 2 de `modifyHero`/`modifyHero2` resta salud, y el camino de muerte por aventura
// resta un daño mayor o igual a la vida que quedaba. El resultado tiene que ser 0.
//
// Ya lo era antes de que la resta llevara `GREATEST(0, …)`, pero por dos accidentes que
// nadie declaró: `s1_hero.health` es `float(12,9) unsigned` y el server corre con
// `sql_mode` vacío, así que MariaDB pisaba el negativo en silencio. Bastaba con volver la
// columna `signed` para guardar vida negativa, o con activar el modo estricto para que el
// UPDATE fallara y la resta se perdiera entera. Este checker fija la invariante del lado
// de la aplicación, que es donde se puede leer.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_health_floor.php

function floorAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

require_once dirname(__DIR__).'/config/connection.php';
require_once dirname(__DIR__).'/GameEngine/Database.php';

$heroTable = TB_PREFIX.'hero';
$sourceTable = SQL_DB.'.'.TB_PREFIX.'hero';
floorAssert(
	mysqli_query($database->connection, "CREATE TEMPORARY TABLE $heroTable AS SELECT * FROM $sourceTable WHERE 0"),
	'Se pudo crear la tabla temporal de héroes'
);

$uid = 900301;
$heroid = 1;
$reset = function($health) use ($database, $heroTable, $uid, $heroid) {
	mysqli_query($database->connection, "DELETE FROM $heroTable WHERE uid = $uid");
	mysqli_query(
		$database->connection,
		"INSERT INTO $heroTable (heroid,uid,level,health,autoregen,dead,lastupdate)"
		." VALUES ($heroid,$uid,0,$health,10,0,".(time() - 3600).")"
	);
};
$health = function() use ($database, $uid) {
	$hero = $database->getHeroData($uid);
	return (float)$hero['health'];
};

// El caso que dejaba la columna en negativo: el daño se lleva más vida de la que hay.
$reset(20);
$database->modifyHero2('health', 50, $uid, 2);
floorAssert($health() === 0.0, 'restar 50 sobre 20 de vida deja 0 y no '.$health());

// Restar exactamente la vida que queda también da 0, no un -0 raro.
$reset(35);
$database->modifyHero2('health', 35, $uid, 2);
floorAssert($health() === 0.0, 'restar la vida exacta deja 0 (dejó '.$health().')');

// Un daño normal sigue restando lo que tiene que restar.
$reset(80);
$database->modifyHero2('health', 30, $uid, 2);
floorAssert($health() === 50.0, 'un daño que no llega a cero sigue restando (dejó '.$health().')');

// El piso no puede haberse comido el reinicio del reloj de regeneración.
$reset(80);
$before = $database->getHeroData($uid);
$database->modifyHero2('health', 30, $uid, 2);
$after = $database->getHeroData($uid);
floorAssert(
	(int)$after['lastupdate'] > (int)$before['lastupdate'],
	'restar salud sigue poniendo al día el reloj de regeneración'
);

// Los otros modos quedan como estaban.
$reset(40);
$database->modifyHero2('health', 25, $uid, 1);
floorAssert($health() === 65.0, 'el modo 1 sigue sumando (dejó '.$health().')');
$reset(40);
$database->modifyHero2('health', 90, $uid, 0);
floorAssert($health() === 90.0, 'el modo 0 sigue asignando (dejó '.$health().')');

// Y la experiencia, que también usa el modo 1, no se ve afectada por el piso.
$reset(50);
$database->modifyHero2('experience', 120, $uid, 1);
$hero = $database->getHeroData($uid);
floorAssert((int)$hero['experience'] === 120, 'la experiencia sigue sumando normal');

// modifyHero() apunta por heroid en vez de uid y tiene el mismo modo 2.
$reset(15);
$database->modifyHero('health', 60, $heroid, 2);
floorAssert($health() === 0.0, 'modifyHero() por heroid también tiene piso (dejó '.$health().')');
$reset(70);
$database->modifyHero('health', 20, $heroid, 2);
floorAssert($health() === 50.0, 'modifyHero() por heroid sigue restando normal (dejó '.$health().')');

echo "Hero health floor regression: OK\n";
