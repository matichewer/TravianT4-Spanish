<?php

/**
 * Limpieza de una sola pasada para los prisioneros huérfanos que dejó el bug de arrasar
 * aldeas con catapultas: `destroyCatapultedVillage` borraba `units` y `vdata` pero no
 * `prisoners`, así que quedaban filas que cobraban cereal para siempre y ocupaban trampas
 * que ya nadie podía liberar. El código nuevo ya no las genera; esto barre las viejas.
 *
 * De paso resincroniza `u99o` con lo que realmente hay preso y recorta `u99` a lo que el
 * trampero aguanta, que es la otra mitad del mismo bug (las trampas sobrevivían a la
 * destrucción del edificio).
 *
 * Sin argumentos solo informa. Para escribir:
 *   docker compose exec -T web php tools/fix_orphan_prisoners.php --aplicar
 */

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit(1);
}

$root = dirname(__DIR__);
if(!file_exists($root.'/config/installed') || !file_exists($root.'/config/connection.php')) {
	fwrite(STDERR, "El servidor no está instalado.\n");
	exit(1);
}

chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require_once $root.'/GameEngine/Data/buidata.php';
require_once $root.'/GameEngine/Data/unitdata.php';
require_once $root.'/GameEngine/GeneratorX.php';
require_once $root.'/GameEngine/Database.php';
require_once $root.'/GameEngine/Automation.php';

$generator = new GeneratorX;
// El constructor de Automation dispara el mantenimiento entero (borrado de cuentas
// incluido), así que la instancia se arma sin llamarlo.
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();

$apply = in_array('--aplicar', $argv, true);
$prefix = TB_PREFIX;

function sweepRows($sql) {
	global $database;
	$result = mysqli_query($database->connection, $sql);
	if(!$result) {
		throw new RuntimeException(mysqli_error($database->connection).' | '.$sql);
	}
	$rows = array();
	while($row = mysqli_fetch_assoc($result)) {
		$rows[] = $row;
	}
	return $rows;
}

function sweepTotal($prisoner) {
	$total = 0;
	for($i = 1; $i <= 11; $i++) {
		$total += max(0, (int)$prisoner['t'.$i]);
	}
	return $total;
}

$discarded = 0;
$discardedTroops = 0;

// 1. Grupos cuya aldea captora ya no existe, o cuya aldea de origen desapareció: no hay
//    trampa que liberar ni aldea a la que volver, y mientras tanto siguen comiendo.
$orphans = sweepRows(
	"SELECT p.* FROM ".$prefix."prisoners p ".
	"LEFT JOIN ".$prefix."vdata captor ON captor.wref = p.wref ".
	"LEFT JOIN ".$prefix."vdata home ON home.wref = p.`from` ".
	"WHERE captor.wref IS NULL OR home.wref IS NULL"
);
foreach($orphans as $prisoner) {
	$troops = sweepTotal($prisoner);
	$reason = array();
	$captor = $database->getVillageField((int)$prisoner['wref'], 'owner');
	$home = $database->getVillageField((int)$prisoner['from'], 'owner');
	if($captor === null) { $reason[] = 'aldea captora '.(int)$prisoner['wref'].' inexistente'; }
	if($home === null) { $reason[] = 'aldea de origen '.(int)$prisoner['from'].' inexistente'; }
	echo "[huérfano] grupo ".(int)$prisoner['id'].": ".$troops." tropas · ".implode(', ', $reason)."\n";
	if($apply && $database->discardPrisonersAtomic((int)$prisoner['id'], (int)$prisoner['wref'])) {
		$discarded++;
		$discardedTroops += $troops;
	}
}

// 2. Ocupación desincronizada: `u99o` tiene que ser exactamente lo que hay preso.
$desynced = sweepRows(
	"SELECT u.vref, u.u99, u.u99o, COALESCE(SUM(p.t1+p.t2+p.t3+p.t4+p.t5+p.t6+p.t7+p.t8+p.t9+p.t10+p.t11),0) held ".
	"FROM ".$prefix."units u ".
	"LEFT JOIN ".$prefix."prisoners p ON p.wref = u.vref ".
	"WHERE u.u99 > 0 OR u.u99o > 0 ".
	"GROUP BY u.vref HAVING u.u99o <> held"
);
$resynced = 0;
foreach($desynced as $row) {
	echo "[ocupación] aldea ".(int)$row['vref'].": u99o=".(int)$row['u99o']." pero hay ".(int)$row['held']." presos\n";
	if($apply) {
		mysqli_query(
			$database->connection,
			"UPDATE ".$prefix."units SET u99o = ".(int)$row['held']." WHERE vref = ".(int)$row['vref']
		);
		$resynced++;
	}
}

// 3. Trampas que sobrevivieron a un trampero destruido o demolido.
$clamped = 0;
$overCapacity = 0;
foreach(sweepRows("SELECT vref, u99 FROM ".$prefix."units WHERE u99 > 0") as $row) {
	$villageId = (int)$row['vref'];
	$fields = $database->getResourceLevel($villageId);
	if(!is_array($fields)) {
		continue;
	}
	$capacity = 0;
	for($field = 19; $field <= 38; $field++) {
		if(!isset($fields['f'.$field.'t']) || (int)$fields['f'.$field.'t'] !== 36) {
			continue;
		}
		$level = (int)$fields['f'.$field];
		if($level > 0 && isset($bid36[$level]['attri'])) {
			$capacity += $bid36[$level]['attri'] * TRAPPER_CAPACITY;
		}
	}
	if((int)$row['u99'] <= $capacity) {
		continue;
	}
	echo "[trampas] aldea ".$villageId.": ".(int)$row['u99']." trampas para una capacidad de ".$capacity."\n";
	$overCapacity++;
	if($apply) {
		// syncTrapperCapacity suelta primero a los presos que ya no entran y después
		// recorta: es el mismo camino que usa la destrucción del trampero.
		$automation->syncTrapperCapacity($villageId);
		$clamped++;
	}
}

echo "\n";
if(!$apply) {
	echo "Solo informe. Volvé a correrlo con --aplicar para escribir los cambios.\n";
	echo "Encontrados: ".count($orphans)." grupos huérfanos, ".count($desynced)." aldeas desincronizadas, ".$overCapacity." con trampas de más.\n";
} else {
	echo "Aplicado: ".$discarded." grupos descartados (".$discardedTroops." tropas), ".
		$resynced." ocupaciones corregidas, ".$clamped." tramperos resincronizados.\n";
}
exit(0);
