<?php
/**
 * Traslada el saldo de puntos de cultura de todos los jugadores de una tabla de
 * requisitos a otra, conservando el avance exacto de cada uno.
 *
 *   docker compose exec -T web php tools/rescale_culture_points.php
 *   docker compose exec -T web php tools/rescale_culture_points.php --aplicar
 *   docker compose exec -T web php tools/rescale_culture_points.php --desde=1 --hasta=3
 *
 * Sin --aplicar sólo informa.
 *
 * Hay que correrlo **una sola vez y junto al cambio de tabla**. Este mundo x3 usaba la
 * columna oficial x1 ($cp1: 2.000 / 8.000 / 20.000 ...) y pasa a la curva intermedia
 * propia ($cp3: 1.300 / 5.300 / 13.300 ...), que apunta a unos 10 días por aldea. Los
 * PC acumulados se ganaron contra la tabla vieja: si se dejan intactos al bajar los
 * requisitos, algunos jugadores obtienen progreso o cupos que todavía no habían ganado.
 *
 * Lo que se conserva es la posición en la curva, no el saldo bruto. Ver
 * travianCultureRescale() en GameEngine/Data/cp.php. Después de
 * correrlo, cada jugador tiene exactamente las mismas aldeas habilitadas y el mismo
 * porcentaje de avance hacia la siguiente que antes.
 *
 * Se anota en admin_log y se niega a repetirse: aplicado dos veces, cada vuelta vuelve
 * a comprimir la curva y los jugadores pierden avance de verdad.
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

require_once dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Accounts.php';
require_once dirname(__DIR__).'/GameEngine/Data/cp.php';

$apply = in_array('--aplicar', $argv, true);
$force = in_array('--forzar', $argv, true);
$fromMode = 1;
$toMode = 3;
foreach($argv as $argument) {
	if(preg_match('/^--desde=(\d+)$/', $argument, $matches)) {
		$fromMode = (int)$matches[1];
	}
	if(preg_match('/^--hasta=(\d+)$/', $argument, $matches)) {
		$toMode = (int)$matches[1];
	}
}

if($fromMode === $toMode) {
	fwrite(STDERR, "La tabla de origen y la de destino son la misma ($fromMode).\n");
	exit(1);
}
if(empty(travianCultureThresholds($fromMode)) || empty(travianCultureThresholds($toMode))) {
	fwrite(STDERR, "Alguna de las dos tablas no existe (origen $fromMode, destino $toMode).\n");
	exit(1);
}

$userTable = TB_PREFIX.'users';
$villageTable = TB_PREFIX.'vdata';
$logTable = TB_PREFIX.'admin_log';
$stamp = "rescale_culture_points $fromMode->$toMode";

$previous = $database->query_return("SELECT id FROM $logTable WHERE data LIKE '%".mysql_real_escape_string($stamp)."%' LIMIT 1");
if(is_array($previous) && !empty($previous) && !$force) {
	fwrite(STDERR, "Ya hay un traslado $fromMode->$toMode anotado en admin_log. Usá --forzar sólo si sabés que aquel no llegó a escribir.\n");
	exit(1);
}

$rows = $database->query_return(
	"SELECT u.id, u.username, u.cp, COUNT(v.wref) AS villages "
	."FROM $userTable u "
	."INNER JOIN $villageTable v ON v.owner = u.id "
	."WHERE u.access = ".(int)USER." AND ".playerAccountSql('u`.`id')." "
	."GROUP BY u.id, u.username, u.cp "
	."ORDER BY u.cp DESC"
);
if(!is_array($rows)) {
	fwrite(STDERR, "No se pudieron leer las cuentas de jugadores.\n");
	exit(1);
}

if($apply && !mysqli_begin_transaction($database->connection)) {
	fwrite(STDERR, "No se pudo iniciar la transacción.\n");
	exit(1);
}

printf("%-6s %-20s %6s %11s %11s %10s %10s\n", 'uid', 'jugador', 'aldeas', 'PC antes', 'PC después', 'cupo', 'avance');
$reviewed = 0;
$changed = 0;
$slotDrift = 0;

foreach($rows as $row) {
	$reviewed++;
	$uid = (int)$row['id'];
	$owned = (int)$row['villages'];
	$rescale = travianCultureRescale($row['cp'], $fromMode, $toMode);
	// El segundo argumento de travianCultureStatus() sólo evita que la interfaz
	// muestre menos cupos que aldeas ya poseídas. Para validar la migración hace falta
	// la capacidad cultural pura; pasar $owned aquí ocultaría una deriva por debajo de
	// ese número.
	$statusBefore = travianCultureStatus($row['cp'], 0, $fromMode);
	$statusAfter = travianCultureStatus($rescale['newPoints'], 0, $toMode);

	// La invariante que justifica todo el traslado: mismo cupo de aldeas y mismo
	// porcentaje de avance. Si alguna cuenta se sale, se informa y no se escribe nada.
	if($statusBefore['cultureCapacity'] !== $statusAfter['cultureCapacity']) {
		$slotDrift++;
	}

	printf(
		"%-6d %-20s %6d %11s %11s %4d -> %-4d %4.1f%% -> %.1f%%\n",
		$uid,
		substr((string)$row['username'], 0, 20),
		$owned,
		number_format((int)$row['cp'], 0, ',', '.'),
		number_format($rescale['newPoints'], 0, ',', '.'),
		$statusBefore['cultureCapacity'],
		$statusAfter['cultureCapacity'],
		$statusBefore['progressPercent'],
		$statusAfter['progressPercent']
	);

	if(!$rescale['changed']) {
		continue;
	}
	$changed++;
	if($apply) {
		$newPoints = (int)$rescale['newPoints'];
		if(!$database->query("UPDATE $userTable SET cp = $newPoints WHERE id = $uid")) {
			mysqli_rollback($database->connection);
			fwrite(STDERR, "Falló la escritura de la cuenta $uid. No se cambió nada.\n");
			exit(2);
		}
	}
}

if($slotDrift > 0) {
	if($apply) {
		mysqli_rollback($database->connection);
	}
	fwrite(STDERR, "\n$slotDrift cuentas cambiarían de cupo de aldeas. No se escribió nada; revisá las tablas.\n");
	exit(2);
}

printf("\n%d cuentas revisadas, %d con el saldo trasladado. Ninguna cambió de cupo de aldeas.\n", $reviewed, $changed);

if($apply) {
	$note = mysql_real_escape_string("[cultura] $stamp: $changed cuentas trasladadas de la tabla $fromMode a la $toMode");
	$database->query("INSERT INTO $logTable VALUES (0, 0, '$note', ".time().")");
	if(!mysqli_commit($database->connection)) {
		fwrite(STDERR, "No se pudo confirmar la transacción.\n");
		exit(2);
	}
	echo "Cambios aplicados.\n";
} else {
	echo "Simulación: volvé a correrlo con --aplicar para escribir.\n";
}
