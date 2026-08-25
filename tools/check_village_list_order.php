<?php
// El cartel de aldeas lista por orden de fundación; $session->villages sigue
// empezando por la capital.
//
// Son dos órdenes distintos a propósito y conviene no mezclarlos:
//   - getVillagesIDByFoundation(): `created` ascendente, la capital no tiene
//     privilegio. Es lo único que usa Templates/multivillage.tpl.
//   - getVillagesID(): capital primero. Varios lugares leen $session->villages[0]
//     dando por sentado que ahí está la capital (aldea por defecto al entrar en
//     Village.php, premios de misiones en quest_core.tpl, aldea natal del héroe en
//     Hero.php, reembolso de colonos en Automation.php). Si alguna vez se reordena
//     esta lista, hay que arreglar esos cuatro primero.
//
//   docker compose exec -T web php /var/www/html/tools/check_village_list_order.php

require dirname(__DIR__).'/GameEngine/Database.php';

function villageOrderAssert($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

// --- El template no puede volver a leer $session->villages ----------------------
$tpl = file_get_contents(dirname(__DIR__).'/Templates/multivillage.tpl');
villageOrderAssert($tpl !== false, 'No se pudo leer multivillage.tpl');
villageOrderAssert(
	strpos($tpl, 'getVillagesIDByFoundation') !== false,
	'El cartel dejó de pedir el orden de fundación'
);
// Salvo el respaldo por si la consulta viene vacía, no debe quedar ningún
// $session->villages[...] indexado: ese orden empieza por la capital.
villageOrderAssert(
	preg_match('/\$session->villages\s*\[/', $tpl) !== 1,
	'El cartel volvió a indexar $session->villages, que ordena la capital primero'
);

// --- Comprobación real contra la base ------------------------------------------
//
// Tabla temporal con el mismo nombre que la real, para que las consultas de la
// clase caigan sobre ella.

global $database;
$prefix = TB_PREFIX;

$fdataParts = array();
for($field = 1; $field <= 40; $field++) {
	$fdataParts[] = "f{$field} int NOT NULL DEFAULT 0";
	$fdataParts[] = "f{$field}t int NOT NULL DEFAULT 0";
}

$tmp = array(
	"CREATE TEMPORARY TABLE {$prefix}vdata (wref int NOT NULL, owner int NOT NULL,
		capital tinyint NOT NULL DEFAULT 0, created int NOT NULL DEFAULT 0,
		loyalty int NOT NULL DEFAULT 100, loyaltyupdate int NOT NULL DEFAULT 0,
		celebration int NOT NULL DEFAULT 0, type int NOT NULL DEFAULT 0,
		exp1 int NOT NULL DEFAULT 0, exp2 int NOT NULL DEFAULT 0, exp3 int NOT NULL DEFAULT 0,
		pop int NOT NULL DEFAULT 0, PRIMARY KEY(wref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}fdata (vref int NOT NULL, ".implode(', ', $fdataParts).",
		PRIMARY KEY(vref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}attacks (id int NOT NULL, t9 int NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM",
	// `conquered` es la fecha de captura: la conquista la reinicia en la misma escritura
	// que cambia el dueño del artefacto, así que el doble tiene que traerla o el UPDATE
	// entero falla y la conquista se reporta como error de base de datos.
	"CREATE TEMPORARY TABLE {$prefix}artefacts (vref int NOT NULL, owner int NOT NULL, conquered int NOT NULL DEFAULT 0, PRIMARY KEY(vref)) ENGINE=MyISAM",
	"CREATE TEMPORARY TABLE {$prefix}users (id int NOT NULL, cp int NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM"
);
foreach($tmp as $q) {
	villageOrderAssert(mysqli_query($database->connection, $q) !== false,
		'No se pudo crear la tabla temporal: '.mysqli_error($database->connection));
}

// Cuatro aldeas del jugador 91. Los wref van al revés que las fechas a propósito:
// si el orden saliera por clave primaria (lo que hacía la lista vieja al no tener
// desempate) la prueba pasaría por casualidad.
//
// La 900002 es la capital y no es la más vieja.
mysqli_query($database->connection, "INSERT INTO {$prefix}vdata (wref,owner,capital,created) VALUES
	(900004,91,0,1000),
	(900003,91,0,2000),
	(900002,91,1,3000),
	(900001,91,0,4000)");

$byFoundation = $database->getVillagesIDByFoundation(91);
villageOrderAssert(
	array_map('intval', $byFoundation) === array(900004, 900003, 900002, 900001),
	'El orden de fundación salió mal: '.implode(',', $byFoundation)
);

// El desempate por wref mantiene el orden estable entre aldeas del mismo segundo
// (dos fundadas por el instalador, o un `created` en 0 de un mundo importado).
mysqli_query($database->connection, "UPDATE {$prefix}vdata SET created = 0 WHERE wref IN (900003,900004)");
villageOrderAssert(
	array_map('intval', $database->getVillagesIDByFoundation(91)) === array(900003, 900004, 900002, 900001),
	'Con `created` empatado el orden dejó de ser estable'
);
mysqli_query($database->connection, "UPDATE {$prefix}vdata SET created = 1000 WHERE wref = 900004");
mysqli_query($database->connection, "UPDATE {$prefix}vdata SET created = 2000 WHERE wref = 900003");

// Y la lista de la sesión sigue arrancando por la capital.
$sessionOrder = $database->getVillagesID(91);
villageOrderAssert(!empty($sessionOrder) && (int)$sessionOrder[0] === 900002,
	'getVillagesID() dejó de devolver la capital primero: '.implode(',', $sessionOrder));
villageOrderAssert(count($sessionOrder) === 4,
	'getVillagesID() no devolvió todas las aldeas: '.implode(',', $sessionOrder));

// Las dos listas tienen que traer las mismas aldeas, sólo que en otro orden.
$a = array_map('intval', $byFoundation);
$b = array_map('intval', $sessionOrder);
sort($a);
sort($b);
villageOrderAssert($a === $b, 'Las dos listas no contienen las mismas aldeas');

// Un jugador sin aldeas devuelve una lista vacía, no un aviso de PHP.
villageOrderAssert($database->getVillagesIDByFoundation(99) === array(),
	'Un jugador sin aldeas no devolvió una lista vacía');

// --- Una aldea conquistada se refunda con el nuevo dueño ------------------------
//
// El jugador 92 tiene una aldea fundada mucho antes que cualquiera de las del 91.
// Si la conquista dejara `created` como estaba, la aldea tomada aparecería primera
// en el cartel del conquistador, antes incluso que su propia capital.
$now = time();
mysqli_query($database->connection, "INSERT INTO {$prefix}vdata (wref,owner,capital,created,loyalty) VALUES
	(900005,92,0,500,10),
	(900006,92,1,500,100)");
// La aldea atacante (la capital del 91) tiene palacio nivel 20: 3 cupos de expansión.
mysqli_query($database->connection, "INSERT INTO {$prefix}fdata (vref,f19t,f19) VALUES
	(900001,0,0),(900002,26,20),(900003,0,0),(900004,0,0),(900005,0,0),(900006,0,0)");
mysqli_query($database->connection, "INSERT INTO {$prefix}attacks (id,t9) VALUES (7101,1)");
mysqli_query($database->connection, "INSERT INTO {$prefix}users (id,cp) VALUES (91,0),(92,0)");

$conquest = $database->applyConquestLoyalty(900002, 900005, 91, 92, 7101, 100);
villageOrderAssert(is_array($conquest) && isset($conquest['status']) && $conquest['status'] === 'conquered',
	'La conquista de prueba no se concretó: '.(is_array($conquest) ? $conquest['status'] : 'sin estado'));

$row = mysqli_fetch_assoc(mysqli_query($database->connection,
	"SELECT owner, created FROM {$prefix}vdata WHERE wref = 900005"));
villageOrderAssert((int)$row['owner'] === 91, 'La aldea no cambió de dueño');
villageOrderAssert((int)$row['created'] >= $now,
	'La aldea conquistada conservó la fecha del dueño anterior: created='.$row['created']);

// Y por lo tanto entra última en el cartel del conquistador, no primera.
$afterConquest = array_map('intval', $database->getVillagesIDByFoundation(91));
villageOrderAssert($afterConquest === array(900004, 900003, 900002, 900001, 900005),
	'La aldea conquistada no quedó al final de la lista: '.implode(',', $afterConquest));

// El panel de administración traspasa aldeas entre cuentas: mismo evento, misma regla.
$admin = file_get_contents(dirname(__DIR__).'/GameEngine/Admin/Mods/editVillageOwner.php');
villageOrderAssert($admin !== false, 'No se pudo leer editVillageOwner.php');
villageOrderAssert(preg_match('/created\s*=/', $admin) === 1,
	'Cambiar el dueño desde el panel de administración no refunda la aldea');

echo "OK: el cartel lista por fundación y \$session->villages sigue con la capital primero\n";
