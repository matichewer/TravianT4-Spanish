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

villageOrderAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE {$prefix}vdata (wref int NOT NULL, owner int NOT NULL,
		capital tinyint NOT NULL DEFAULT 0, created int NOT NULL DEFAULT 0,
		PRIMARY KEY(wref)) ENGINE=MyISAM") !== false,
	'No se pudo crear la tabla temporal: '.mysqli_error($database->connection));

// Cuatro aldeas del jugador 91. Los wref van al revés que las fechas a propósito:
// si el orden saliera por clave primaria (lo que hacía la lista vieja al no tener
// desempate) la prueba pasaría por casualidad.
//
// La 900002 es la capital y no es la más vieja; la 900004 es una aldea conquistada,
// que conserva el `created` del jugador que la fundó y por eso encabeza la lista.
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

echo "OK: el cartel lista por fundación y \$session->villages sigue con la capital primero\n";
