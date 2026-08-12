<?php
// Recorta el excedente de puntos de cultura acumulado antes de activar la curva lenta.
// Cada jugador conserva como máximo el requisito para una aldea más de las que posee;
// quien todavía no llegó a ese requisito conserva su saldo sin cambios.
//
//   docker compose exec -T web php /var/www/html/tools/normalize_culture_points.php
//   docker compose exec -T web php /var/www/html/tools/normalize_culture_points.php --apply
//
// Sin --apply solo muestra lo que cambiaría. Es idempotente y excluye las cuentas de
// sistema/administración al seleccionar únicamente cuentas con acceso USER y aldeas.

require_once dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Data/cp.php';

$apply = in_array('--apply', $argv, true);
$userTable = TB_PREFIX.'users';
$villageTable = TB_PREFIX.'vdata';
$slowCultureMode = 1;

$rows = $database->query_return(
	"SELECT u.id, u.username, u.cp, COUNT(v.wref) AS villages "
	."FROM $userTable u "
	."INNER JOIN $villageTable v ON v.owner = u.id "
	."WHERE u.access = ".(int)USER." "
	."GROUP BY u.id, u.username, u.cp "
	."ORDER BY u.id"
);
if(!is_array($rows)){
	fwrite(STDERR, "No se pudieron leer las cuentas de jugadores\n");
	exit(1);
}

$reviewed = 0;
$changed = 0;
$removed = 0;
$withoutThreshold = 0;

if($apply && !mysqli_begin_transaction($database->connection)){
	fwrite(STDERR, "No se pudo iniciar la transacción\n");
	exit(1);
}

foreach($rows as $row){
	$reviewed++;
	$uid = (int)$row['id'];
	$normalization = travianCultureNormalization($row['cp'], $row['villages'], $slowCultureMode);
	if($normalization['cap'] === null){
		$withoutThreshold++;
		printf("uid %-7d %-24s %3d aldeas: sin umbral configurado\n", $uid, $row['username'], $normalization['ownedVillages']);
		continue;
	}
	if(!$normalization['changed']){
		continue;
	}

	$difference = $normalization['currentPoints'] - $normalization['newPoints'];
	$changed++;
	$removed += $difference;
	printf(
		"uid %-7d %-24s %3d aldeas: %10d -> %-10d (-%d)%s\n",
		$uid,
		$row['username'],
		$normalization['ownedVillages'],
		$normalization['currentPoints'],
		$normalization['newPoints'],
		$difference,
		$apply ? '' : '   (simulación)'
	);

	if($apply){
		$sql = "UPDATE $userTable SET cp = ".(int)$normalization['newPoints']
			." WHERE id = $uid AND cp > ".(int)$normalization['cap'];
		if(!$database->query($sql)){
			mysqli_rollback($database->connection);
			fwrite(STDERR, "No se pudo normalizar uid $uid; se revirtió toda la operación\n");
			exit(1);
		}
	}
}

if($apply && !mysqli_commit($database->connection)){
	mysqli_rollback($database->connection);
	fwrite(STDERR, "No se pudo confirmar la normalización\n");
	exit(1);
}

printf(
	"\n%d jugadores revisados, %d saldos %s, %d PC excedentes%s\n",
	$reviewed,
	$changed,
	$apply ? 'normalizados' : 'por normalizar',
	$removed,
	($changed > 0 && !$apply) ? ' (volver a correr con --apply para quitarlos)' : ''
);
if($withoutThreshold > 0){
	printf("%d jugadores exceden el máximo de la tabla y se dejaron sin cambios\n", $withoutThreshold);
}
