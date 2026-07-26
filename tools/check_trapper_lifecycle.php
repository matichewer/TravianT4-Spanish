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

define('SQL_SERVER','db');
define('SQL_USER',getenv('TRAPPER_DB_USER') ?: 'travian');
define('SQL_PASS',getenv('TRAPPER_DB_PASSWORD') ?: 'travian');
define('SQL_DB',getenv('TRAPPER_DB_NAME') ?: 'travian_t4');
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

$tables = array('movement','attacks','prisoners','units','hero','enforcement','vdata','users');
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
			strpos($template,'isset($dataarray[170])') !== false
			&& strpos($template,'echo $releaseInfo') !== false,
			'el reporte '.$noticeType.' lee la información de liberación correcta'
		);
	}

	echo "Trapper lifecycle regression passed.\n";
} finally {
	if($created) {
		foreach($tables as $table) {
			mysqli_query($database->connection,"DROP TABLE IF EXISTS ".TB_PREFIX.$table);
		}
	}
}
