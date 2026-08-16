<?php
/**
 * Auditoría contra la base del ciclo de vida de la celebración de hidromiel.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_brewery_celebration.php
 *
 * La fiesta vive en una sola columna (`users.brewery`, el instante en que termina) pero
 * la paga una aldea (la capital) y la lee la batalla. Los tres pasos —comprobar que no
 * haya otra, cobrar, agendar— tienen que ser uno solo: si se separan, dos pedidos al
 * mismo tiempo cobran dos veces y prenden una sola fiesta.
 *
 * Todo corre sobre tablas TEMPORARY con los mismos nombres que las reales, así que las
 * consultas de la clase caen sobre ellas y el mundo de verdad no se toca.
 *
 * Cubre:
 *   A. El arranque feliz: cobra exacto una vez y agenda el final.
 *   B. No hay dos fiestas: la segunda rebota y no cobra.
 *   C. Recursos justos: falta uno solo y no se descuenta ninguno.
 *   D. El cerrojo por cuenta: con la cuenta tomada, el pedido rebota sin cobrar.
 *   E. El reintegro cuando el segundo paso no prende la fiesta.
 *   F. getBreweryLevel(): sólo la capital, sólo con la fiesta viva, acotado a 0..10.
 *   G. Entradas basura.
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$GLOBALS['checks'] = 0;
$GLOBALS['fails'] = array();

function check($condition, $message) {
	$GLOBALS['checks']++;
	if($condition) {
		return true;
	}
	$GLOBALS['fails'][] = $message;
	echo "  FAIL  ".$message."\n";
	return false;
}

function section($title) {
	echo "\n== ".$title." ==\n";
}

require dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Data/cel.php';

global $database;
$prefix = TB_PREFIX;
$link = $database->connection;

$UID = 990001;
$CAPITAL = 990101;
$SECONDARY = 990102;

$fdataParts = array();
for($field = 1; $field <= 40; $field++) {
	$fdataParts[] = "f{$field} int NOT NULL DEFAULT 0";
	$fdataParts[] = "f{$field}t int NOT NULL DEFAULT 0";
}

$tmp = array(
	"CREATE TEMPORARY TABLE {$prefix}users (id int NOT NULL, brewery int NOT NULL DEFAULT 0,
		PRIMARY KEY(id)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}vdata (wref int NOT NULL, owner int NOT NULL, capital tinyint NOT NULL DEFAULT 0,
		wood int NOT NULL DEFAULT 0, clay int NOT NULL DEFAULT 0, iron int NOT NULL DEFAULT 0,
		crop int NOT NULL DEFAULT 0, PRIMARY KEY(wref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}fdata (vref int NOT NULL, ".implode(', ', $fdataParts).",
		PRIMARY KEY(vref)) ENGINE=MyISAM"
);
// Si alguna TEMPORARY no se crea, las consultas caerían sobre las tablas reales y el
// primer DELETE de breweryReset() se llevaría el mundo puesto: se corta acá.
foreach($tmp as $q) {
	if(!mysqli_query($link, $q)) {
		fwrite(STDERR, "FAIL: no se pudo crear la tabla temporal: ".mysqli_error($link)."\n");
		exit(1);
	}
	$GLOBALS['checks']++;
}

$cost = breweryCelebrationCost();
$duration = breweryCelebrationDuration();

// Estado de partida: la capital tiene el doble de lo que cuesta una fiesta, así que
// alcanza para una sola de las dos que se van a pedir en la sección B.
function breweryReset($extraWood = 0) {
	global $link, $prefix, $UID, $CAPITAL, $SECONDARY, $cost;
	mysqli_query($link, "DELETE FROM {$prefix}users");
	mysqli_query($link, "DELETE FROM {$prefix}vdata");
	mysqli_query($link, "DELETE FROM {$prefix}fdata");
	mysqli_query($link, "INSERT INTO {$prefix}users (id,brewery) VALUES ($UID,0)");
	$w = $cost['wood'] * 2 + $extraWood;
	mysqli_query($link, "INSERT INTO {$prefix}vdata (wref,owner,capital,wood,clay,iron,crop) VALUES
		($CAPITAL,$UID,1,$w,".($cost['clay'] * 2).",".($cost['iron'] * 2).",".($cost['crop'] * 2)."),
		($SECONDARY,$UID,0,$w,".($cost['clay'] * 2).",".($cost['iron'] * 2).",".($cost['crop'] * 2).")");
	// Cervecería nivel 7 en el solar 22 de la capital.
	mysqli_query($link, "INSERT INTO {$prefix}fdata (vref,f22,f22t) VALUES ($CAPITAL,7,35),($SECONDARY,0,0)");
}

function breweryResources($wref) {
	global $link, $prefix;
	$row = mysqli_fetch_assoc(mysqli_query($link,
		"SELECT wood,clay,iron,crop FROM {$prefix}vdata WHERE wref = $wref"));
	return array_map('intval', $row);
}

function breweryCharged($before, $after, $cost) {
	return $before['wood'] - $after['wood'] === $cost['wood']
		&& $before['clay'] - $after['clay'] === $cost['clay']
		&& $before['iron'] - $after['iron'] === $cost['iron']
		&& $before['crop'] - $after['crop'] === $cost['crop'];
}

function brewerySame($before, $after) {
	return $before === $after;
}

// ---------------------------------------------------------------------------
section('A. El arranque feliz');
// ---------------------------------------------------------------------------
breweryReset();
$before = breweryResources($CAPITAL);
$end = time() + $duration;
$started = $database->startBreweryCelebration($UID, $CAPITAL, $end, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
$after = breweryResources($CAPITAL);
check($started === true, 'la primera fiesta arranca');
check(breweryCharged($before, $after, $cost), 'cobra exactamente el costo de la fiesta, una sola vez');
check($database->getBreweryCelebrationEnd($UID) === $end, 'agenda el final que se le pidió');
check($database->getBreweryLevel($UID) === 7, 'con la fiesta viva el nivel es el de la Cervecería de la capital');

// Sólo se toca la aldea que paga.
$otra = breweryResources($SECONDARY);
check($otra['wood'] === $cost['wood'] * 2, 'no se le cobra nada a las demás aldeas de la cuenta');

// ---------------------------------------------------------------------------
section('B. No hay dos fiestas a la vez');
// ---------------------------------------------------------------------------
$before = breweryResources($CAPITAL);
$segunda = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
$after = breweryResources($CAPITAL);
check($segunda === false, 'la segunda fiesta rebota mientras la primera corre');
check(brewerySame($before, $after), 'la fiesta rechazada no cobró nada');
check($database->getBreweryCelebrationEnd($UID) === $end, 'la fiesta que corría no se pisó');

// Una fiesta vencida sí deja arrancar otra, y el nivel vuelve a 0 mientras tanto.
mysqli_query($link, "UPDATE {$prefix}users SET brewery = ".(time() - 1)." WHERE id = $UID");
check($database->getBreweryLevel($UID) === 0, 'sin fiesta viva el nivel es 0 aunque el edificio siga en pie');
$tercera = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
check($tercera === true, 'con la fiesta anterior vencida se puede arrancar otra');
check($database->getBreweryLevel($UID) === 7, 'y el nivel vuelve a contar');

// El instante exacto del vencimiento ya no cuenta como fiesta viva.
mysqli_query($link, "UPDATE {$prefix}users SET brewery = ".time()." WHERE id = $UID");
check($database->getBreweryLevel($UID) === 0, 'una fiesta que termina justo ahora ya no da bono');

// ---------------------------------------------------------------------------
section('C. Recursos justos');
// ---------------------------------------------------------------------------
// Falta uno solo de los cuatro: no se descuenta ninguno. El bug clásico es comprobar
// con OR y descontar los cuatro igual, dejando tres en negativo.
foreach(array('wood','clay','iron','crop') as $recurso) {
	breweryReset();
	mysqli_query($link, "UPDATE {$prefix}vdata SET {$recurso} = ".($cost[$recurso] - 1)." WHERE wref = $CAPITAL");
	$before = breweryResources($CAPITAL);
	$ok = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
	$after = breweryResources($CAPITAL);
	check($ok === false, 'sin '.$recurso.' suficiente la fiesta no arranca');
	check(brewerySame($before, $after), 'y no se descuenta ningún recurso por falta de '.$recurso);
	check($database->getBreweryCelebrationEnd($UID) === 0, 'ni queda una fiesta agendada por falta de '.$recurso);
	check($after[$recurso] >= 0, 'el recurso faltante no quedó en negativo');
}

// Con lo justo y nada más, arranca y deja la aldea en cero.
breweryReset();
mysqli_query($link, "UPDATE {$prefix}vdata SET wood = {$cost['wood']}, clay = {$cost['clay']},
	iron = {$cost['iron']}, crop = {$cost['crop']} WHERE wref = $CAPITAL");
$ok = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
$after = breweryResources($CAPITAL);
check($ok === true, 'con lo justo la fiesta arranca');
check($after === array('wood'=>0,'clay'=>0,'iron'=>0,'crop'=>0), 'y deja la aldea exactamente en cero');

// ---------------------------------------------------------------------------
section('D. El cerrojo por cuenta');
// ---------------------------------------------------------------------------
// Dos pedidos simultáneos son dos conexiones distintas. Se simula tomando el cerrojo
// desde otra conexión: el pedido tiene que rebotar sin cobrar ni agendar.
breweryReset();
$other = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASS, SQL_DB);
if($other) {
	$lockName = $prefix.'brewery_'.$UID;
	$row = mysqli_fetch_row(mysqli_query($other, "SELECT GET_LOCK('".mysqli_real_escape_string($other, $lockName)."',5)"));
	check((int)$row[0] === 1, 'la otra conexión tomó el cerrojo de la cuenta');
	$before = breweryResources($CAPITAL);
	$bloqueada = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
	$after = breweryResources($CAPITAL);
	check($bloqueada === false, 'con la cuenta tomada por otro pedido, este rebota');
	check(brewerySame($before, $after), 'el pedido bloqueado no cobró nada');
	check($database->getBreweryCelebrationEnd($UID) === 0, 'ni agendó ninguna fiesta');
	mysqli_query($other, "SELECT RELEASE_LOCK('".mysqli_real_escape_string($other, $lockName)."')");
	// Soltado el cerrojo, el mismo pedido pasa.
	$libre = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
	check($libre === true, 'soltado el cerrojo, el pedido pasa');
	mysqli_close($other);
} else {
	check(false, 'se pudo abrir una segunda conexión para probar el cerrojo');
}

// ---------------------------------------------------------------------------
section('E. El reintegro');
// ---------------------------------------------------------------------------
// Si el cobro sale pero el UPDATE de la cuenta no prende la fiesta, los recursos
// vuelven. Se fuerza borrando la fila del usuario: el UPDATE no toca ninguna fila.
breweryReset();
$before = breweryResources($CAPITAL);
mysqli_query($link, "DELETE FROM {$prefix}users WHERE id = $UID");
$fallida = $database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
$after = breweryResources($CAPITAL);
check($fallida === false, 'sin fila de usuario la fiesta no arranca');
check(brewerySame($before, $after), 'y los recursos cobrados se devuelven enteros');

// ---------------------------------------------------------------------------
section('F. De dónde sale el nivel');
// ---------------------------------------------------------------------------
breweryReset();
$database->startBreweryCelebration($UID, $CAPITAL, time() + $duration, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
check($database->getBreweryLevel($UID) === 7, 'el nivel sale de la Cervecería de la capital');

// Una Cervecería fuera de la capital no cuenta: es exactamente lo que dejaba una
// mudanza de capital antes de que la mudanza la derribara.
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = 0, f22t = 0 WHERE vref = $CAPITAL");
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = 10, f22t = 35 WHERE vref = $SECONDARY");
check($database->getBreweryLevel($UID) === 0,
	'una Cervecería en una aldea que no es la capital no da bono');

// El tope de 10 se aplica aunque la base traiga un nivel imposible.
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = 99, f22t = 35 WHERE vref = $CAPITAL");
check($database->getBreweryLevel($UID) === 10, 'un nivel imposible se acota a 10');
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = -3 WHERE vref = $CAPITAL");
check($database->getBreweryLevel($UID) === 0, 'un nivel negativo se acota a 0');

// Con dos Cervecerías en la capital se queda con la más alta, igual que getTypeLevel().
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = 3, f22t = 35, f30 = 9, f30t = 35 WHERE vref = $CAPITAL");
check($database->getBreweryLevel($UID) === 9, 'con dos Cervecerías se queda con la de nivel más alto');

// Sólo los solares de edificios (19..38) cuentan.
mysqli_query($link, "UPDATE {$prefix}fdata SET f22 = 0, f22t = 0, f30 = 0, f30t = 0, f5 = 8, f5t = 35 WHERE vref = $CAPITAL");
check($database->getBreweryLevel($UID) === 0, 'un solar de recursos marcado como Cervecería no cuenta');

// ---------------------------------------------------------------------------
section('G. Entradas basura');
// ---------------------------------------------------------------------------
breweryReset();
$basura = array(
	array(0, $CAPITAL, time() + 3600, 'sin cuenta'),
	array(-1, $CAPITAL, time() + 3600, 'con cuenta negativa'),
	array($UID, 0, time() + 3600, 'sin aldea'),
	array($UID, -1, time() + 3600, 'con aldea negativa'),
	array($UID, $CAPITAL, time(), 'con un final que es ahora mismo'),
	array($UID, $CAPITAL, time() - 3600, 'con un final en el pasado'),
	array($UID, $CAPITAL, 0, 'con un final en cero')
);
foreach($basura as $caso) {
	list($uid, $wid, $fin, $etiqueta) = $caso;
	$before = breweryResources($CAPITAL);
	$ok = $database->startBreweryCelebration($uid, $wid, $fin, $cost['wood'], $cost['clay'], $cost['iron'], $cost['crop']);
	$after = breweryResources($CAPITAL);
	check($ok === false, 'un pedido '.$etiqueta.' rebota');
	check(brewerySame($before, $after), 'un pedido '.$etiqueta.' no cobra nada');
}
check($database->getBreweryCelebrationEnd($UID) === 0, 'ninguna entrada basura dejó una fiesta agendada');

check($database->getBreweryCelebrationEnd(0) === 0, 'la cuenta 0 no tiene fiesta');
check($database->getBreweryCelebrationEnd(-1) === 0, 'una cuenta negativa no tiene fiesta');
check($database->getBreweryCelebrationEnd(87654321) === 0, 'una cuenta inexistente no tiene fiesta');
check($database->getBreweryLevel(0) === 0, 'la cuenta 0 no tiene nivel de Cervecería');
check($database->getBreweryLevel(-1) === 0, 'una cuenta negativa no tiene nivel de Cervecería');
check($database->getBreweryLevel(87654321) === 0, 'una cuenta inexistente no tiene nivel de Cervecería');

// Un `brewery` negativo en la base (columna tocada a mano) no se lee como fiesta.
mysqli_query($link, "UPDATE {$prefix}users SET brewery = -100 WHERE id = $UID");
check($database->getBreweryCelebrationEnd($UID) === 0, 'un valor negativo en users.brewery se lee como 0');

echo "\n";
if(count($GLOBALS['fails']) > 0) {
	echo "Brewery celebration checks FAILED (".count($GLOBALS['fails'])." de ".$GLOBALS['checks'].").\n";
	exit(1);
}
echo "Brewery celebration checks passed (".$GLOBALS['checks']." comprobaciones).\n";
exit(0);
