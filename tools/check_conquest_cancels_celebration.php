<?php
// Una aldea conquistada pierde la celebración que tuviera en curso.
//
// Si la fiesta sobrevive al cambio de dueño, celebrationComplete() la cierra leyendo
// `owner` en ese momento y le acredita al conquistador los 500/2000 puntos de cultura
// que pagó el defensor. La cancelación va dentro del mismo UPDATE que cambia el dueño,
// así que no existe una ventana en la que la barrida pueda pagarle a nadie.
//
//   docker compose exec -T web php /var/www/html/tools/check_conquest_cancels_celebration.php

require dirname(__DIR__).'/GameEngine/Database.php';

function conquestAssert($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
conquestAssert($source !== false, 'No se pudo leer db_MYSQLi.php');

// El SET de applyConquestLoyalty() tiene que apagar la celebración de la aldea destino.
$start = strpos($source, 'function applyConquestLoyalty(');
$end = $start === false ? false : strpos($source, 'function cleanupFailedSettlement(', $start);
$conquest = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';
conquestAssert($conquest !== '', 'No se encontró el cuerpo de applyConquestLoyalty()');
conquestAssert(
	strpos($conquest, 'destination.celebration = 0') !== false
		&& strpos($conquest, 'destination.type = 0') !== false,
	'La conquista dejó de cancelar la celebración de la aldea tomada'
);
// Y tiene que ir en la misma escritura que el cambio de dueño, no en una aparte.
conquestAssert(
	preg_match('/destination\.owner = \$attackerOwner.*?destination\.celebration = 0/s', $conquest) === 1,
	'La cancelación de la celebración salió del UPDATE que cambia el dueño'
);

// El panel de administración mueve aldeas de cuenta: mismo evento, misma regla.
$admin = file_get_contents(dirname(__DIR__).'/GameEngine/Admin/Mods/editVillageOwner.php');
conquestAssert($admin !== false, 'No se pudo leer editVillageOwner.php');
conquestAssert(
	strpos($admin, 'celebration = 0') !== false && strpos($admin, 'type = 0') !== false,
	'Cambiar el dueño desde el panel de administración dejó de cancelar la celebración'
);

// --- Comprobación real contra la base ------------------------------------------
//
// Se arma una conquista completa sobre tablas temporales con los mismos nombres que
// las reales, para que las consultas de la clase caigan sobre ellas.

global $database;
$prefix = TB_PREFIX;
$now = time();

// fdata necesita todos los campos: la elegibilidad mira los tipos 19-38 de la aldea
// tomada y el cupo de expansión sale de la residencia/palacio de la atacante.
$fdataParts = array();
for($field = 1; $field <= 40; $field++) {
	$fdataParts[] = "f{$field} int NOT NULL DEFAULT 0";
	$fdataParts[] = "f{$field}t int NOT NULL DEFAULT 0";
}
$fdataColumns = implode(', ', $fdataParts);

$tmp = array(
	// `created` va porque la conquista refunda la aldea con el nuevo dueño (el cartel
	// de aldeas ordena por esa fecha); ver check_village_list_order.php.
	"CREATE TEMPORARY TABLE {$prefix}vdata (wref int NOT NULL, owner int NOT NULL, capital tinyint NOT NULL DEFAULT 0,
		loyalty int NOT NULL DEFAULT 100, loyaltyupdate int NOT NULL DEFAULT 0, celebration int NOT NULL DEFAULT 0,
		type int NOT NULL DEFAULT 0, exp1 int NOT NULL DEFAULT 0, exp2 int NOT NULL DEFAULT 0, exp3 int NOT NULL DEFAULT 0,
		pop int NOT NULL DEFAULT 0, created int NOT NULL DEFAULT 0, PRIMARY KEY(wref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}fdata (vref int NOT NULL, ".$fdataColumns.", PRIMARY KEY(vref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}attacks (id int NOT NULL, t9 int NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}artefacts (vref int NOT NULL, owner int NOT NULL, PRIMARY KEY(vref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}odata (wref int NOT NULL, conqured int NOT NULL DEFAULT 0, owner int NOT NULL DEFAULT 0,
		PRIMARY KEY(wref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}users (id int NOT NULL, cp int NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM"
);
foreach($tmp as $q) {
	conquestAssert(mysqli_query($database->connection, $q) !== false,
		'No se pudo crear la tabla temporal: '.mysqli_error($database->connection));
}

// Aldea 900001 del atacante (uid 91) y aldea 900002 del defensor (uid 92), con una
// gran celebración en curso que vence dentro de un rato.
// El defensor necesita más de una aldea: la última no se puede conquistar.
mysqli_query($database->connection, "INSERT INTO {$prefix}vdata (wref,owner,capital,loyalty,celebration,type)
	VALUES (900001,91,1,100,0,0), (900002,92,0,10,".($now + 3600).",2), (900003,92,1,100,0,0)");
// La aldea atacante tiene palacio nivel 20 (3 cupos de expansión libres).
mysqli_query($database->connection, "INSERT INTO {$prefix}fdata (vref,f19t,f19) VALUES (900001,26,20),(900002,0,0),(900003,0,0)");
mysqli_query($database->connection, "INSERT INTO {$prefix}attacks (id,t9) VALUES (7001,1)");
mysqli_query($database->connection, "INSERT INTO {$prefix}users (id,cp) VALUES (91,0),(92,0)");

$result = $database->applyConquestLoyalty(900001, 900002, 91, 92, 7001, 100);
conquestAssert(is_array($result) && isset($result['status']),
	'applyConquestLoyalty() no devolvió un estado');
conquestAssert($result['status'] === 'conquered',
	"La conquista de prueba no se concretó: ".$result['status']);

$row = mysqli_fetch_assoc(mysqli_query($database->connection,
	"SELECT owner, celebration, type FROM {$prefix}vdata WHERE wref = 900002"));
conquestAssert((int)$row['owner'] === 91, 'La aldea no cambió de dueño');
conquestAssert((int)$row['celebration'] === 0 && (int)$row['type'] === 0,
	'La aldea conquistada conservó la celebración: celebration='.$row['celebration'].' type='.$row['type']);

// Y la barrida ya no encuentra nada que pagarle al conquistador.
$pending = $database->getCel();
foreach($pending as $vil) {
	conquestAssert((int)$vil['wref'] !== 900002,
		'La aldea conquistada sigue en la cola de celebraciones por cerrar');
}

// Una conquista que sólo baja lealtad (sin llegar a 0) no toca la celebración.
mysqli_query($database->connection, "UPDATE {$prefix}vdata SET owner=92, loyalty=100, celebration=".($now + 3600).", type=1 WHERE wref=900002");
mysqli_query($database->connection, "UPDATE {$prefix}vdata SET exp1=0 WHERE wref=900001");
mysqli_query($database->connection, "UPDATE {$prefix}attacks SET t9=1 WHERE id=7001");
$partial = $database->applyConquestLoyalty(900001, 900002, 91, 92, 7001, 25);
conquestAssert($partial['status'] === 'loyalty_reduced',
	'El ataque parcial no bajó la lealtad: '.$partial['status']);
$row = mysqli_fetch_assoc(mysqli_query($database->connection,
	"SELECT owner, celebration, type FROM {$prefix}vdata WHERE wref = 900002"));
conquestAssert((int)$row['owner'] === 92, 'El ataque parcial cambió el dueño');
conquestAssert((int)$row['celebration'] !== 0 && (int)$row['type'] === 1,
	'Un ataque que sólo baja lealtad canceló la celebración');

echo "Conquest cancels celebration: OK\n";
