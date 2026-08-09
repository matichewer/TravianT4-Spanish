<?php
/**
 * Verifica dónde caen las aventuras y a quién se le cierran.
 *
 * Antes el destino se sorteaba sobre el id del tile contra un tamaño de mundo
 * escrito a mano ($lastw = 641601, o sea un mapa 801x801). Dos consecuencias: en
 * un mapa más chico el id sorteado no existe y la aventura queda muerta —se
 * lista pero al entrar rebota a la plaza de reuniones—, y como el id no mide
 * distancias, sumarle 10000 corre ~50 filas en Y pero cruza el mapa entero en X,
 * así que una aldea contra el borde este recibía aventuras en el borde oeste, al
 * doble de distancia que una del centro.
 *
 * El cierre, además, iba por casilla sin filtrar por jugador: si dos tenían
 * aventura en el mismo tile, al completarla uno se le cerraba también al otro.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_adventure_placement.php
 */

require_once dirname(__DIR__).'/config/connection.php';
require_once dirname(__DIR__).'/GameEngine/Database.php';

$fails = 0;

function check($condition, $message) {
	global $fails;
	if($condition) {
		echo "OK: ".$message."\n";
	} else {
		echo "FAIL: ".$message."\n";
		$fails++;
	}
}

function one($sql) {
	global $database;
	$result = mysqli_query($database->connection, $sql);
	return $result ? mysqli_fetch_assoc($result) : false;
}

$radius = $database->getWorldRadius();
echo "Mundo: radio $radius\n\n";
check($radius > 0, 'el radio del mundo sale de wdata, no de WORLD_MAX');

// La banda histórica: ±radio en X, ±medio radio en Y. Es lo que daban los 10000
// ids desde una aldea central, y es el tope que tiene que valer para todos.
$yspan = (int) round($radius / 2);
$maxDist = sqrt(pow($radius, 2) + pow($yspan, 2));

echo "\n== Posicionamiento ==\n";

$bounds = one("SELECT MIN(x) minx, MAX(x) maxx, MIN(y) miny, MAX(y) maxy FROM ".TB_PREFIX."wdata");

// Una aldea del centro y otras pegadas a los bordes: el peor caso del recorte.
$spots = array(
	'centro'      => array(0, 0),
	'borde oeste' => array((int)$bounds['minx'], 0),
	'borde este'  => array((int)$bounds['maxx'], 0),
	'esquina sur' => array((int)$bounds['minx'], (int)$bounds['miny']),
);

foreach($spots as $label => $xy) {
	list($vx, $vy) = $xy;
	$home = one("SELECT id FROM ".TB_PREFIX."wdata WHERE x = $vx AND y = $vy LIMIT 1");
	if(!$home) {
		echo "(salteo $label: no hay tile en ($vx|$vy))\n";
		continue;
	}
	$homeId = (int) $home['id'];

	$inexistentes = $ocupadas = $fueraDeBanda = $sinLugar = 0;
	$peor = 0;
	for($i = 0; $i < 300; $i++) {
		$id = $database->pickAdventureField($homeId);
		if($id <= 0) { $sinLugar++; continue; }
		$tile = one("SELECT x, y, occupied FROM ".TB_PREFIX."wdata WHERE id = $id");
		if(!$tile) { $inexistentes++; continue; }
		if((int)$tile['occupied'] !== 0) { $ocupadas++; }
		$dx = abs((int)$tile['x'] - $vx);
		$dy = abs((int)$tile['y'] - $vy);
		if($dx > $radius || $dy > $yspan) { $fueraDeBanda++; }
		$peor = max($peor, sqrt($dx * $dx + $dy * $dy));
	}

	printf("-- %s (%d|%d): peor distancia %.1f campos\n", $label, $vx, $vy, $peor);
	check($inexistentes === 0, "$label: ninguna aventura cae fuera del mapa");
	check($ocupadas === 0, "$label: ninguna aventura cae en casilla ocupada");
	check($sinLugar === 0, "$label: siempre encuentra lugar");
	check($fueraDeBanda === 0, "$label: ninguna se sale de la banda historica");
	check($peor <= $maxDist + 1, sprintf('%s: mismo tope que el centro (%.0f campos)', $label, $maxDist));
}

// De acá en adelante se escribe en la tabla de aventuras. La TEMPORARY tapa a la
// real dentro de esta conexión, así que la partida no se toca.
//
// Si la creación falla hay que cortar acá y no seguir: sin la temporal, todo lo
// que viene abajo le escribe a la partida de verdad. `LIKE` no sirve —MariaDB
// contesta "Not unique table/alias" cuando el molde tiene el mismo nombre—, y
// `AS SELECT` no se trae el AUTO_INCREMENT, así que se repone a mano.
$creada = mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE ".TB_PREFIX."adventure AS SELECT * FROM ".SQL_DB.".".TB_PREFIX."adventure WHERE 0");
if(!$creada) {
	echo "FAIL: no se pudo crear la tabla temporal de aventuras: ".mysqli_error($database->connection)."\n";
	echo "      corto acá para no escribir sobre la partida.\n";
	exit(1);
}
$conId = mysqli_query($database->connection,
	"ALTER TABLE ".TB_PREFIX."adventure MODIFY id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
if(!$conId) {
	echo "FAIL: la tabla temporal quedó sin AUTO_INCREMENT: ".mysqli_error($database->connection)."\n";
	exit(1);
}
check(true, 'tabla temporal de aventuras creada (no se toca la partida)');

$libre = one("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 LIMIT 1");
$tile = (int) $libre['id'];
$uidA = 900001;
$uidB = 900002;
$vence = time() + 3600;

echo "\n== Cierre de aventuras ==\n";

mysqli_query($database->connection, "INSERT INTO ".TB_PREFIX."adventure (wref, uid, dif, time, `end`) VALUES ($tile, $uidA, 0, $vence, 0)");
mysqli_query($database->connection, "INSERT INTO ".TB_PREFIX."adventure (wref, uid, dif, time, `end`) VALUES ($tile, $uidB, 0, $vence, 0)");

$database->closeAdventure($uidA, $tile);

$abiertaA = one("SELECT COUNT(1) n FROM ".TB_PREFIX."adventure WHERE uid = $uidA AND `end` = 0");
$abiertaB = one("SELECT COUNT(1) n FROM ".TB_PREFIX."adventure WHERE uid = $uidB AND `end` = 0");
check((int)$abiertaA['n'] === 0, 'cierra la aventura del jugador que llego');
check((int)$abiertaB['n'] === 1, 'no cierra la del otro jugador en la misma casilla');

// getAdventure tiene que devolver la abierta, no una vieja ya cerrada: de ahí
// sale la dificultad con la que se resuelve la llegada.
mysqli_query($database->connection, "INSERT INTO ".TB_PREFIX."adventure (wref, uid, dif, time, `end`) VALUES ($tile, $uidA, 1, $vence, 0)");
$vigente = $database->getAdventure($uidA, $tile);
check(is_array($vigente) && (int)$vigente['end'] === 0 && (int)$vigente['dif'] === 1,
	'getAdventure devuelve la aventura abierta y no la cerrada anterior');

echo "\n== Contador del costado ==\n";

// Siempre acotado por uid: un DELETE sin WHERE acá, si la temporal no existiera,
// se lleva puesta la tabla de la partida.
mysqli_query($database->connection, "DELETE FROM ".TB_PREFIX."adventure WHERE uid IN ($uidA, $uidB)");
$inexistente = one("SELECT MAX(id) + 1000 AS id FROM ".TB_PREFIX."wdata");
mysqli_query($database->connection, "INSERT INTO ".TB_PREFIX."adventure (wref, uid, dif, time, `end`) VALUES ($tile, $uidA, 0, $vence, 0)");
mysqli_query($database->connection, "INSERT INTO ".TB_PREFIX."adventure (wref, uid, dif, time, `end`) VALUES (".(int)$inexistente['id'].", $uidA, 0, $vence, 0)");
check($database->getAdventureCount($uidA) === 1, 'cuenta solo las aventuras que la lista puede mostrar');

echo "\n";
if($fails) {
	echo "$fails comprobacion(es) fallaron\n";
	exit(1);
}
echo "todo ok\n";
exit(0);
