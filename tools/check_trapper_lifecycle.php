<?php

/**
 * Regression aislada del ciclo de vida del trampero.
 *
 * Ejecutar dentro del contenedor web:
 *   php tools/check_trapper_lifecycle.php
 *
 * Crea tablas temporales con prefijo trapper_audit_ dentro de la base local,
 * prueba las operaciones y las elimina incluso si una aserción falla.
 */

$connectionSource = file_get_contents(dirname(__DIR__).'/config/connection.php');
$readConnectionValue = function($name) use ($connectionSource) {
	if(!preg_match('/define\(["\']'.preg_quote($name,'/').'["\']\s*,\s*["\']([^"\']*)["\']\s*\)/',$connectionSource,$match)) {
		throw new RuntimeException('No se pudo leer '.$name.' desde config/connection.php');
	}
	return $match[1];
};
define('SQL_SERVER',getenv('TRAPPER_DB_HOST') ?: $readConnectionValue('SQL_SERVER'));
define('SQL_USER',getenv('TRAPPER_DB_USER') ?: $readConnectionValue('SQL_USER'));
define('SQL_PASS',getenv('TRAPPER_DB_PASSWORD') ?: $readConnectionValue('SQL_PASS'));
define('SQL_DB',getenv('TRAPPER_DB_NAME') ?: $readConnectionValue('SQL_DB'));
define('TB_PREFIX','trapper_audit_');

require_once dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php';

function trapperQuery($sql) {
	global $database;
	$result = mysqli_query($database->connection,$sql);
	if(!$result) {
		throw new RuntimeException(mysqli_error($database->connection).' | '.$sql);
	}
	return $result;
}

function trapperAssert($condition,$message) {
	if(!$condition) {
		throw new RuntimeException($message);
	}
	echo "[OK] ".$message."\n";
}

function trapperRow($sql) {
	$result = trapperQuery($sql);
	return mysqli_fetch_assoc($result);
}

function trapperTroops($values) {
	$troops = array_fill(1,11,0);
	foreach($values as $position => $amount) {
		$troops[(int)$position] = (int)$amount;
	}
	return $troops;
}

if(isset($argv[1]) && $argv[1] === 'capture-worker') {
	$workerVillage = isset($argv[2]) ? (int)$argv[2] : 0;
	$workerOrigin = isset($argv[3]) ? (int)$argv[3] : 0;
	$result = $database->capturePrisonersAtomic(
		$workerVillage,
		$workerOrigin,
		trapperTroops(array(1 => 80,2 => 20)),
		10
	);
	echo array_sum($result);
	exit(0);
}

$tables = array('movement','attacks','prisoners','units','hero','enforcement','vdata','users','fdata','wdata');

// Clona una aldea a otro wref. `INSERT ... SELECT *` choca con la clave primaria, así que
// hay que enumerar las columnas para poder pisar wref en el camino.
function trapperCloneVillage($sourceWref,$newWref) {
	global $database;
	$columns = array();
	$result = trapperQuery("SHOW COLUMNS FROM ".TB_PREFIX."vdata");
	while($column = mysqli_fetch_assoc($result)) {
		$columns[] = $column['Field'];
	}
	$select = array();
	foreach($columns as $column) {
		$select[] = $column === 'wref' ? (int)$newWref : '`'.$column.'`';
	}
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."vdata (`".implode('`,`',$columns)."`) ".
		"SELECT ".implode(',',$select)." FROM ".TB_PREFIX."vdata WHERE wref = ".(int)$sourceWref
	);
}

// Las coordenadas viven en wdata: sin una fila ahí, el regreso de los liberados no puede
// calcular el viaje.
function trapperPlaceVillage($wref,$x,$y) {
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."wdata (id,fieldtype,oasistype,x,y,occupied,image) ".
		"VALUES (".(int)$wref.",3,0,".(int)$x.",".(int)$y.",1,'')"
	);
}
$sourcePrefix = 's1_';
$created = false;

try {
	$created = true;
	foreach($tables as $table) {
		trapperQuery("DROP TABLE IF EXISTS ".TB_PREFIX.$table);
		trapperQuery("CREATE TABLE ".TB_PREFIX.$table." LIKE ".$sourcePrefix.$table);
	}

	$fixture = trapperRow(
		"SELECT v.wref,v.owner,u.tribe FROM ".$sourcePrefix."vdata v ".
		"INNER JOIN ".$sourcePrefix."users u ON u.id = v.owner ".
		"INNER JOIN ".$sourcePrefix."units x ON x.vref = v.wref ".
		"WHERE u.tribe BETWEEN 1 AND 5 LIMIT 1"
	);
	trapperAssert((bool)$fixture,'existe una aldea local utilizable como fixture');
	$base = (int)$fixture['wref'];
	$owner = (int)$fixture['owner'];
	$tribe = (int)$fixture['tribe'];
	trapperQuery("INSERT INTO ".TB_PREFIX."users SELECT * FROM ".$sourcePrefix."users WHERE id = $owner");
	trapperQuery("INSERT INTO ".TB_PREFIX."vdata SELECT * FROM ".$sourcePrefix."vdata WHERE wref = $base");
	trapperQuery("INSERT INTO ".TB_PREFIX."units SELECT * FROM ".$sourcePrefix."units WHERE vref = $base");
	$zeroUnits = array('hero = 0','u99 = 0','u99o = 0');
	for($i = 1; $i <= 50; $i++) {
		$zeroUnits[] = "u$i = 0";
	}
	trapperQuery("UPDATE ".TB_PREFIX."units SET ".implode(',',$zeroUnits)." WHERE vref = $base");

	$allocation = $database->allocateTrapsProportionally(trapperTroops(array(1 => 80,2 => 20)),8);
	trapperAssert(array_sum($allocation) === 8,'la asignación proporcional conserva el total');
	trapperAssert($allocation[1] === 6 && $allocation[2] === 2,'la captura parcial no usa blindaje por orden de unidad');

	$trapVillage = $base + 700000;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($trapVillage,10,0)");
	$firstCapture = $database->capturePrisonersAtomic(
		$trapVillage,
		$base,
		trapperTroops(array(1 => 80,2 => 20)),
		10
	);
	$secondCapture = $database->capturePrisonersAtomic(
		$trapVillage,
		$base,
		trapperTroops(array(1 => 80,2 => 20)),
		10
	);
	$captureState = trapperRow(
		"SELECT u.u99,u.u99o,p.* FROM ".TB_PREFIX."units u ".
		"INNER JOIN ".TB_PREFIX."prisoners p ON p.wref = u.vref ".
		"WHERE u.vref = $trapVillage AND p.`from` = $base"
	);
	$capturedTotal = 0;
	for($i = 1; $i <= 11; $i++) {
		$capturedTotal += (int)$captureState['t'.$i];
	}
	trapperAssert(array_sum($firstCapture) === 10 && array_sum($secondCapture) === 0,'una segunda llegada no sobreocupa trampas');
	trapperAssert((int)$captureState['u99o'] === 10 && $capturedTotal === 10,'ocupación y prisioneros quedan sincronizados');

	$captureFailureVillage = $base + 700006;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($captureFailureVillage,5,0)");
	trapperQuery(
		"ALTER TABLE ".TB_PREFIX."prisoners ADD CONSTRAINT trapper_no_capture ".
		"CHECK (wref <> $captureFailureVillage)"
	);
	$failedCapture = $database->capturePrisonersAtomic(
		$captureFailureVillage,
		$base,
		trapperTroops(array(1 => 5)),
		5
	);
	$captureFailureState = trapperRow(
		"SELECT u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE wref = $captureFailureVillage) prisoners ".
		"FROM ".TB_PREFIX."units WHERE vref = $captureFailureVillage"
	);
	trapperAssert(
		array_sum($failedCapture) === 0
		&& (int)$captureFailureState['u99o'] === 0
		&& (int)$captureFailureState['prisoners'] === 0,
		'un fallo al persistir la captura revierte la reserva de trampas'
	);
	trapperQuery("ALTER TABLE ".TB_PREFIX."prisoners DROP CONSTRAINT trapper_no_capture");

	$concurrentVillage = $base + 700005;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($concurrentVillage,10,0)");
	$workers = array();
	for($worker = 0; $worker < 2; $worker++) {
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).
			' capture-worker '.$concurrentVillage.' '.$base;
		$pipes = array();
		$process = proc_open(
			$command,
			array(0 => array('pipe','r'),1 => array('pipe','w'),2 => array('pipe','w')),
			$pipes
		);
		trapperAssert(is_resource($process),'se inicia el trabajador concurrente '.($worker + 1));
		fclose($pipes[0]);
		$workers[] = array($process,$pipes);
	}
	$concurrentCaptured = 0;
	foreach($workers as $worker) {
		$output = stream_get_contents($worker[1][1]);
		$error = stream_get_contents($worker[1][2]);
		fclose($worker[1][1]);
		fclose($worker[1][2]);
		$status = proc_close($worker[0]);
		trapperAssert($status === 0,'el trabajador concurrente termina sin error'.($error === '' ? '' : ': '.$error));
		$concurrentCaptured += (int)$output;
	}
	$concurrentState = trapperRow(
		"SELECT u.u99o,COALESCE(SUM(p.t1+p.t2+p.t3+p.t4+p.t5+p.t6+p.t7+p.t8+p.t9+p.t10+p.t11),0) captured ".
		"FROM ".TB_PREFIX."units u LEFT JOIN ".TB_PREFIX."prisoners p ON p.wref = u.vref ".
		"WHERE u.vref = $concurrentVillage GROUP BY u.vref"
	);
	trapperAssert(
		$concurrentCaptured === 10
		&& (int)$concurrentState['u99o'] === 10
		&& (int)$concurrentState['captured'] === 10,
		'dos procesos simultáneos no sobreocupan ni desincronizan las trampas'
	);

	$failureVillage = $base + 700001;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($failureVillage,20,10)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($failureVillage,$base,6,4,0,0,0,0,0,0,0,0,0)"
	);
	$failurePrisoner = trapperRow("SELECT * FROM ".TB_PREFIX."prisoners WHERE wref = $failureVillage");
	trapperQuery("ALTER TABLE ".TB_PREFIX."movement ADD CONSTRAINT trapper_no_return CHECK (sort_type <> 4)");
	$failedReturn = $database->returnPrisonersAtomic(
		(int)$failurePrisoner['id'],
		$failureVillage,
		$base,
		trapperTroops(array(1 => 6,2 => 4)),
		time(),
		time() + 60,
		false,
		trapperTroops(array(1 => 6,2 => 4))
	);
	$failureState = trapperRow(
		"SELECT u.u99,u.u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$failurePrisoner['id'].") prisoners,".
		"(SELECT COUNT(*) FROM ".TB_PREFIX."attacks) attacks FROM ".TB_PREFIX."units u WHERE u.vref = $failureVillage"
	);
	trapperAssert($failedReturn === false,'el fallo al crear el retorno se propaga');
	trapperAssert(
		(int)$failureState['prisoners'] === 1
		&& (int)$failureState['u99o'] === 10
		&& (int)$failureState['attacks'] === 0,
		'el fallo conserva prisioneros y trampas y compensa el ataque'
	);
	trapperQuery("ALTER TABLE ".TB_PREFIX."movement DROP CONSTRAINT trapper_no_return");

	trapperQuery("UPDATE ".TB_PREFIX."prisoners SET t1 = t1 + 1 WHERE id = ".(int)$failurePrisoner['id']);
	trapperQuery("UPDATE ".TB_PREFIX."units SET u99o = u99o + 1 WHERE vref = $failureVillage");
	$staleReturn = $database->returnPrisonersAtomic(
		(int)$failurePrisoner['id'],
		$failureVillage,
		$base,
		trapperTroops(array(1 => 6,2 => 4)),
		time(),
		time() + 60,
		false,
		trapperTroops(array(1 => 6,2 => 4))
	);
	$staleState = trapperRow(
		"SELECT u.u99o,p.t1,p.t2 FROM ".TB_PREFIX."units u ".
		"INNER JOIN ".TB_PREFIX."prisoners p ON p.wref = u.vref WHERE p.id = ".(int)$failurePrisoner['id']
	);
	trapperAssert(
		$staleReturn === false
		&& (int)$staleState['u99o'] === 11
		&& (int)$staleState['t1'] === 7
		&& (int)$staleState['t2'] === 4,
		'una liberación con una lectura vieja no borra capturas recién agregadas'
	);

	$manualReturn = $database->returnPrisonersAtomic(
		(int)$failurePrisoner['id'],
		$failureVillage,
		$base,
		trapperTroops(array(1 => 7,2 => 4)),
		time(),
		time() + 60,
		false,
		trapperTroops(array(1 => 7,2 => 4))
	);
	$manualReplay = $database->returnPrisonersAtomic(
		(int)$failurePrisoner['id'],
		$failureVillage,
		$base,
		trapperTroops(array(1 => 7,2 => 4)),
		time(),
		time() + 60,
		false,
		trapperTroops(array(1 => 7,2 => 4))
	);
	$manualState = trapperRow(
		"SELECT u.u99,u.u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$failurePrisoner['id'].") prisoners,".
		"(SELECT COUNT(*) FROM ".TB_PREFIX."movement WHERE `from` = $failureVillage AND `to` = $base) returns ".
		"FROM ".TB_PREFIX."units u WHERE u.vref = $failureVillage"
	);
	trapperAssert($manualReturn === true && $manualReplay === false,'la liberación manual es idempotente');
	trapperAssert(
		(int)$manualState['u99'] === 20
		&& (int)$manualState['u99o'] === 0
		&& (int)$manualState['prisoners'] === 0
		&& (int)$manualState['returns'] === 1,
		'la liberación devuelve todas las tropas y reutiliza las trampas'
	);

	$ownVillage = $base + 700002;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($ownVillage,12,8)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($ownVillage,$base,8,0,0,0,0,0,0,0,0,0,0)"
	);
	$ownPrisoner = trapperRow("SELECT * FROM ".TB_PREFIX."prisoners WHERE wref = $ownVillage");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy) ".
		"VALUES ($base,1,0,0,0,0,0,0,0,0,0,0,3,0,0,0)"
	);
	$attackId = (int)mysqli_insert_id($database->connection);
	$merged = $database->mergePrisonersIntoAttackAtomic(
		(int)$ownPrisoner['id'],
		$ownVillage,
		$base,
		$attackId,
		trapperTroops(array(1 => 6)),
		trapperTroops(array(1 => 8))
	);
	$ownState = trapperRow(
		"SELECT a.t1,u.u99,u.u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$ownPrisoner['id'].") prisoners ".
		"FROM ".TB_PREFIX."attacks a INNER JOIN ".TB_PREFIX."units u ON u.vref = $ownVillage WHERE a.id = $attackId"
	);
	trapperAssert($merged && (int)$ownState['t1'] === 7,'las tropas propias liberadas se suman al retorno atacante');
	trapperAssert(
		(int)$ownState['u99'] === 4 && (int)$ownState['u99o'] === 0 && (int)$ownState['prisoners'] === 0,
		'la liberación en batalla destruye todas las trampas usadas'
	);

	$allyVillage = $base + 700003;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($allyVillage,12,8)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($allyVillage,$base,8,0,0,0,0,0,0,0,0,0,0)"
	);
	$allyPrisoner = trapperRow("SELECT * FROM ".TB_PREFIX."prisoners WHERE wref = $allyVillage");
	$allyReturned = $database->returnPrisonersAtomic(
		(int)$allyPrisoner['id'],
		$allyVillage,
		$base,
		trapperTroops(array(1 => 6)),
		time(),
		time() + 60,
		true,
		trapperTroops(array(1 => 8))
	);
	$allyState = trapperRow(
		"SELECT a.t1,u.u99,u.u99o FROM ".TB_PREFIX."movement m ".
		"INNER JOIN ".TB_PREFIX."attacks a ON a.id = m.ref ".
		"INNER JOIN ".TB_PREFIX."units u ON u.vref = $allyVillage ".
		"WHERE m.`from` = $allyVillage AND m.`to` = $base LIMIT 1"
	);
	trapperAssert($allyReturned && (int)$allyState['t1'] === 6,'la liberación aliada crea un retorno separado con sus supervivientes');
	trapperAssert((int)$allyState['u99'] === 4 && (int)$allyState['u99o'] === 0,'el retorno aliado destruye las trampas usadas');

	$heroSource = trapperRow("SELECT * FROM ".$sourcePrefix."hero WHERE uid = $owner LIMIT 1");
	if($heroSource) {
		trapperQuery("INSERT INTO ".TB_PREFIX."hero SELECT * FROM ".$sourcePrefix."hero WHERE heroid = ".(int)$heroSource['heroid']);
	} else {
		trapperQuery(
			"INSERT INTO ".TB_PREFIX."hero ".
			"(uid,wref,level,speed,points,experience,dead,health,power,itempower,offBonus,defBonus,product,".
			"r0,r1,r2,r3,r4,autoregen,lastupdate,lastadv,hash,hide) VALUES ".
			"($owner,$base,0,6,0,0,0,100,0,0,0,0,0,0,0,0,0,0,0,0,0,'',1)"
		);
	}
	trapperQuery("UPDATE ".TB_PREFIX."hero SET dead = 0,health = 100 WHERE uid = $owner");
	$disbandVillage = $base + 700004;
	$trappedHero = 1;
	$disbandTotal = 2 + $trappedHero;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($disbandVillage,9,$disbandTotal)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($disbandVillage,$base,2,0,0,0,0,0,0,0,0,0,$trappedHero)"
	);
	$disbandPrisoner = trapperRow("SELECT * FROM ".TB_PREFIX."prisoners WHERE wref = $disbandVillage");
	$unauthorizedDisband = $database->disbandPrisonersAtomic(
		(int)$disbandPrisoner['id'],
		$disbandVillage,
		$base,
		$owner + 999999
	);
	$unauthorizedState = trapperRow(
		"SELECT u.u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$disbandPrisoner['id'].") prisoners ".
		"FROM ".TB_PREFIX."units u WHERE u.vref = $disbandVillage"
	);
	trapperAssert(
		$unauthorizedDisband === false
		&& (int)$unauthorizedState['u99o'] === $disbandTotal
		&& (int)$unauthorizedState['prisoners'] === 1,
		'un dueño ajeno no puede desbandar el grupo prisionero'
	);
	$disbanded = $database->disbandPrisonersAtomic(
		(int)$disbandPrisoner['id'],
		$disbandVillage,
		$base,
		$owner
	);
	$disbandState = trapperRow(
		"SELECT u.u99,u.u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$disbandPrisoner['id'].") prisoners ".
		"FROM ".TB_PREFIX."units u WHERE u.vref = $disbandVillage"
	);
	trapperAssert($disbanded && (int)$disbandState['u99'] === 9 && (int)$disbandState['u99o'] === 0,'el dueño de tropas puede desbandar y libera las trampas');
	$heroState = trapperRow("SELECT dead,health FROM ".TB_PREFIX."hero WHERE uid = $owner LIMIT 1");
	trapperAssert((int)$heroState['dead'] === 1 && (float)$heroState['health'] === 0.0,'un héroe desbandado queda muerto');

	for($i = 0; $i <= 50; $i++) {
		if(!defined('U'.$i)) {
			define('U'.$i,'U'.$i);
		}
	}
	if(!defined('U99')) {
		define('U99','U99');
	}
	if(!defined('SPEED')) {
		define('SPEED',1);
	}
	if(!defined('TRAPPER_CAPACITY')) {
		define('TRAPPER_CAPACITY',1);
	}
	require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
	require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
	require_once dirname(__DIR__).'/GameEngine/Technology.php';

	trapperQuery("DELETE FROM ".TB_PREFIX."prisoners");
	$before = $technology->getAllUnits($base);
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($trapVillage,$base,3,2,0,0,0,0,0,0,0,0,1)"
	);
	$upkeepPrisoners = $database->getPrisoners3($base);
	$directPrisonerCount = trapperRow(
		"SELECT COUNT(*) amount FROM ".TB_PREFIX."prisoners WHERE `from` = ".(int)$base
	);
	trapperAssert(
		count($upkeepPrisoners) >= 1,
		'la consulta de consumo encuentra el grupo prisionero '.
		'(base '.(int)$base.', directo '.(int)$directPrisonerCount['amount'].')'
	);
	$after = $technology->getAllUnits($base);
	$offset = ($tribe - 1) * 10;
	$firstDelta = (int)$after['u'.($offset + 1)] - (int)$before['u'.($offset + 1)];
	$secondDelta = (int)$after['u'.($offset + 2)] - (int)$before['u'.($offset + 2)];
	$heroDelta = (int)$after['hero'] - (int)$before['hero'];
	trapperAssert(
		$firstDelta === 3 && $secondDelta === 2 && $heroDelta === 1,
		'las tropas capturadas siguen incluidas en el consumo de su aldea '.
		"(deltas $firstDelta/$secondDelta/$heroDelta)"
	);
	if(!defined('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP')) {
		define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP',true);
	}
	require_once dirname(__DIR__).'/GameEngine/Automation.php';
	$automationReflection = new ReflectionClass('Automation');
	$automationForUpkeep = $automationReflection->newInstanceWithoutConstructor();
	$automatedUnits = $automationForUpkeep->getAllUnits($base);
	trapperAssert(
		(int)$automatedUnits['u'.($offset + 1)] === (int)$after['u'.($offset + 1)]
		&& (int)$automatedUnits['u'.($offset + 2)] === (int)$after['u'.($offset + 2)]
		&& (int)$automatedUnits['hero'] === (int)$after['hero'],
		'la automatización de consumo usa el mismo total con prisioneros'
	);

	if(!defined('GP_LOCATE')) {
		define('GP_LOCATE','gpack/');
	}
	$releaseMethod = new ReflectionMethod('Automation','releaseTrappedTroops');
	$releaseMethod->setAccessible(true);

	// Rescate: un ataque ganado devuelve el 100% de lo atrapado. La liberación forzada no
	// cuesta bajas, así que lo que entra a la trampa es exactamente lo que vuelve.
	trapperQuery("DELETE FROM ".TB_PREFIX."prisoners");
	$rescueVillage = $base + 700007;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($rescueVillage,30,20)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($rescueVillage,$base,13,7,0,0,0,0,0,0,0,0,0)"
	);
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy) ".
		"VALUES ($base,5,0,0,0,0,0,0,0,0,0,0,3,0,0,0)"
	);
	$rescueAttackId = (int)mysqli_insert_id($database->connection);
	$rescueResult = $releaseMethod->invoke(
		$automationForUpkeep,
		array('from' => $base,'ref' => $rescueAttackId),
		array('owner' => $owner,'wref' => $base),
		array('wref' => $rescueVillage),
		0
	);
	$rescueState = trapperRow(
		"SELECT a.t1,a.t2,u.u99,u.u99o,".
		"(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE wref = $rescueVillage) prisoners ".
		"FROM ".TB_PREFIX."attacks a INNER JOIN ".TB_PREFIX."units u ON u.vref = $rescueVillage ".
		"WHERE a.id = $rescueAttackId"
	);
	trapperAssert(
		(int)$rescueState['t1'] === 18 && (int)$rescueState['t2'] === 7,
		'la liberación devuelve el 100% de las tropas atrapadas, sin bajas '.
		'(t1 '.(int)$rescueState['t1'].', t2 '.(int)$rescueState['t2'].')'
	);
	trapperAssert(
		(int)$rescueState['u99'] === 10 && (int)$rescueState['u99o'] === 0
		&& (int)$rescueState['prisoners'] === 0,
		'el rescate rompe las trampas que retenían al grupo'
	);
	trapperAssert(
		(int)$rescueResult['freed'][1] === 13 && (int)$rescueResult['freed'][2] === 7,
		'el rescate informa qué tropas volvieron con el ejército para calcular el estado final'
	);
	trapperAssert(
		strpos($rescueResult['info'],'liberó <b>20</b> tropas propias') !== false
		&& strpos($rescueResult['info'],'murieron') === false,
		'el informe del rescate ya no habla de bajas durante la liberación'
	);

	// Tropas propias pero de OTRA aldea: no pueden colarse en el ejército atacante, o
	// rescatarlas sería un traslado gratis de tropas entre aldeas propias.
	$otherHome = $base + 700008;
	$foreignTrapVillage = $base + 700012;
	trapperCloneVillage($base,$otherHome);
	trapperQuery("INSERT INTO ".TB_PREFIX."wdata SELECT * FROM ".$sourcePrefix."wdata WHERE id = $base");
	trapperPlaceVillage($otherHome,12,34);
	trapperPlaceVillage($foreignTrapVillage,15,38);
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($foreignTrapVillage,10,6)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($foreignTrapVillage,$otherHome,6,0,0,0,0,0,0,0,0,0,0)"
	);
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy) ".
		"VALUES ($base,4,0,0,0,0,0,0,0,0,0,0,3,0,0,0)"
	);
	$foreignAttackId = (int)mysqli_insert_id($database->connection);
	$foreignRescue = $releaseMethod->invoke(
		$automationForUpkeep,
		array('from' => $base,'ref' => $foreignAttackId),
		array('owner' => $owner,'wref' => $base),
		array('wref' => $foreignTrapVillage),
		0
	);
	$foreignState = trapperRow("SELECT t1 FROM ".TB_PREFIX."attacks WHERE id = $foreignAttackId");
	$foreignReturn = trapperRow(
		"SELECT COUNT(*) amount FROM ".TB_PREFIX."movement WHERE `from` = $foreignTrapVillage AND `to` = $otherHome"
	);
	trapperAssert(
		(int)$foreignState['t1'] === 4 && (int)$foreignRescue['freed'][1] === 0,
		'las tropas propias de otra aldea no se suman al ejército que las rescató'
	);
	trapperAssert(
		(int)$foreignReturn['amount'] === 1,
		'las tropas propias de otra aldea regresan a su propia aldea'
	);

	// El trampero destruido se lleva las trampas y suelta a los presos que ya no entran.
	$shrinkVillage = $base + 700009;
	trapperPlaceVillage($shrinkVillage,20,44);
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($shrinkVillage,20,9)");
	trapperQuery("INSERT INTO ".TB_PREFIX."fdata (vref,f19,f19t) VALUES ($shrinkVillage,0,0)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($shrinkVillage,$base,9,0,0,0,0,0,0,0,0,0,0)"
	);
	$automationForUpkeep->syncTrapperCapacity($shrinkVillage);
	$shrinkState = trapperRow(
		"SELECT u.u99,u.u99o,".
		"(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE wref = $shrinkVillage) prisoners,".
		"(SELECT COUNT(*) FROM ".TB_PREFIX."movement WHERE `from` = $shrinkVillage AND `to` = $base) returns ".
		"FROM ".TB_PREFIX."units u WHERE u.vref = $shrinkVillage"
	);
	trapperAssert(
		(int)$shrinkState['prisoners'] === 0 && (int)$shrinkState['returns'] === 1,
		'destruir el trampero suelta a los prisioneros hacia su aldea'
	);
	trapperAssert(
		(int)$shrinkState['u99'] === 0 && (int)$shrinkState['u99o'] === 0,
		'las trampas no sobreviven al trampero destruido '.
		'(u99 '.(int)$shrinkState['u99'].', u99o '.(int)$shrinkState['u99o'].')'
	);

	// Un trampero que solo baja de nivel conserva lo que sigue entrando.
	$partialVillage = $base + 700010;
	$partialLevel = 5;
	$partialCapacity = $bid36[$partialLevel]['attri'] * TRAPPER_CAPACITY;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($partialVillage,".($partialCapacity + 40).",0)");
	trapperQuery("INSERT INTO ".TB_PREFIX."fdata (vref,f19,f19t) VALUES ($partialVillage,$partialLevel,36)");
	$automationForUpkeep->syncTrapperCapacity($partialVillage);
	$partialState = trapperRow("SELECT u99,u99o FROM ".TB_PREFIX."units WHERE vref = $partialVillage");
	trapperAssert(
		(int)$partialState['u99'] === (int)$partialCapacity,
		'bajar el trampero recorta las trampas hasta la capacidad que queda '.
		'(u99 '.(int)$partialState['u99'].', capacidad '.(int)$partialCapacity.')'
	);

	// Grupo sin aldea de origen viva: no hay a dónde volver, pero la trampa se libera.
	$orphanVillage = $base + 700011;
	trapperQuery("INSERT INTO ".TB_PREFIX."units (vref,u99,u99o) VALUES ($orphanVillage,15,7)");
	trapperQuery(
		"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
		"VALUES ($orphanVillage,".($base + 999999).",7,0,0,0,0,0,0,0,0,0,0)"
	);
	$orphanPrisoner = trapperRow("SELECT * FROM ".TB_PREFIX."prisoners WHERE wref = $orphanVillage");
	$discarded = $database->discardPrisonersAtomic((int)$orphanPrisoner['id'],$orphanVillage);
	$orphanState = trapperRow(
		"SELECT u99,u99o,(SELECT COUNT(*) FROM ".TB_PREFIX."prisoners WHERE wref = $orphanVillage) prisoners ".
		"FROM ".TB_PREFIX."units WHERE vref = $orphanVillage"
	);
	trapperAssert(
		$discarded === true && (int)$orphanState['prisoners'] === 0
		&& (int)$orphanState['u99o'] === 0 && (int)$orphanState['u99'] === 15,
		'un grupo sin aldea de origen se descarta y libera la trampa sin romperla'
	);

	$session = (object)array('tribe' => 3,'uid' => $owner);
	$village = (object)array(
		'techarray' => array('t99' => 0),
		'resarray' => array('f19t' => 19,'f19' => 10)
	);
	$method = new ReflectionMethod('Technology','trainUnit');
	$method->setAccessible(true);
	trapperAssert(
		$method->invoke($technology,99,1,false,19) === false,
		'una petición forjada desde un campo que no es trampero se rechaza'
	);

	foreach(array(1,2,4,5,6,7) as $noticeType) {
		$template = file_get_contents(dirname(__DIR__).'/Templates/Notice/'.$noticeType.'.tpl');
		trapperAssert(
			strpos($template,'$reportTrapStart') !== false
			&& strpos($template,'$trapstart + 10') !== false
			&& strpos($template,'$releaseInfoIndex = $trapstart + 11') !== false
			&& strpos($template,'echo $releaseInfo') !== false,
			'el reporte '.$noticeType.' lee prisioneros, héroe y liberación desde el bloque marcado'
		);
	}
	$spyTemplate = file_get_contents(dirname(__DIR__).'/Templates/Notice/0.tpl');
	trapperAssert(
		strpos($spyTemplate,'$reportTrapStart') !== false
		&& strpos($spyTemplate,'$trapstart+10') !== false,
		'el reporte de espionaje lee las once posiciones del bloque de prisioneros'
	);
	$failedTemplate = file_get_contents(dirname(__DIR__).'/Templates/Notice/3.tpl');
	trapperAssert(
		strpos($failedTemplate,'$reportTrapStart') !== false
		&& strpos($failedTemplate,'$trapstart+10') !== false,
		'el reporte de derrota total conserva la posición de cada prisionero'
	);
	$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
	trapperAssert(
		substr_count($automationSource,'trap-data-v1') === 3,
		'los informes nuevos delimitan explícitamente el bloque de prisioneros'
	);
	trapperAssert(
		strpos($automationSource,"$"."unitstraped_att = implode(',', $"."stilltraped)") === false,
		'el informe conserva las tropas atrapadas en la batalla aunque sean liberadas al ganar'
	);
	// La detección pasó a decidirse en spyAttemptDetected(), pero los espías capturados
	// en trampas la siguen disparando aunque no haya ninguna baja. El comportamiento en
	// sí lo cubre tools/check_spy_detection.php.
	trapperAssert(
		strpos($automationSource,'$spyDetected = $this->spyAttemptDetected($def_spy, $totaltraped_att, $totaldead_att)') !== false,
		'capturar espías genera un informe para el defensor aunque no haya bajas'
	);
	trapperAssert(
		strpos($automationSource,'trappedTroopSurvivors') === false,
		'no quedó rastro del 25% de bajas al liberar'
	);
	$databaseSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
	trapperAssert(
		strpos($databaseSource,'function destroyUsedTraps') === false,
		'la destrucción de trampas vive solo en los métodos atómicos'
	);
	// El regreso de las tropas curadas llega horas después del ejército: si el informe no
	// lo cuenta, el atacante ve aparecer unidades de la nada.
	trapperAssert(
		strpos($automationSource,"',heal-v1,'.(int)\$totalheal") !== false,
		'el informe del atacante lleva las tropas que revivió la venda'
	);
	trapperAssert(
		strpos(file_get_contents(dirname(__DIR__).'/Templates/Notice/report_data.tpl'),'heal-v1') !== false,
		'el lector de informes entiende el bloque de curación'
	);
	foreach(array(1,2) as $noticeType) {
		trapperAssert(
			strpos(file_get_contents(dirname(__DIR__).'/Templates/Notice/'.$noticeType.'.tpl'),'$reportHealedTroops') !== false,
			'el reporte '.$noticeType.' muestra las tropas curadas por la venda'
		);
	}
	// El nombre viaja dentro de un CSV y se imprime como HTML crudo, y los nombres admiten
	// cualquier carácter: sin escapar, una coma parte el informe y el HTML se ejecuta.
	$safeMethod = new ReflectionMethod('Automation','reportSafeText');
	$safeMethod->setAccessible(true);
	$unsafeName = $safeMethod->invoke($automationForUpkeep,'a,b<script>x</script>');
	trapperAssert(
		strpos($unsafeName,',') === false && strpos($unsafeName,'<script>') === false,
		'el nombre del rescatador no puede partir el informe ni inyectar HTML ('.$unsafeName.')'
	);
	// El nombre de aldea ya viene escapado de la base: acá solo hay que sacarle la coma,
	// y sobre todo no volver a escaparlo o cada `&amp;` se convertiría en `&amp;amp;`.
	$fieldMethod = new ReflectionMethod('Automation','reportSafeField');
	$fieldMethod->setAccessible(true);
	$unsafeVillage = $fieldMethod->invoke($automationForUpkeep,'Pueblo, del &amp; Sur');
	trapperAssert(
		strpos($unsafeVillage,',') === false && strpos($unsafeVillage,'&amp;amp;') === false,
		'el nombre de aldea no parte el CSV del informe ni se re-escapa ('.$unsafeVillage.')'
	);
	trapperAssert(
		substr_count($automationSource,'addslashes($this->reportSafeField($to[\'name\']))') === 3,
		'los tres nombres de aldea que viajan dentro del CSV están protegidos'
	);

	echo "Trapper lifecycle regression passed.\n";
} finally {
	if($created) {
		foreach($tables as $table) {
			mysqli_query($database->connection,"DROP TABLE IF EXISTS ".TB_PREFIX.$table);
		}
	}
}
