<?php
/**
 * Auditoría del worker del Mercado (tools/market_worker.php).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_market_worker.php
 *
 * Por qué existe: el worker corre en su propio proceso, sin Session.php, así que sólo
 * tiene los globales que su propia lista de includes arma. Cuando esa lista se quedó
 * corta —el reparto de una ruta pasó a acreditar producción, que necesita $technology y
 * $u1..$u50— el fallo NO fue visible: TradeRoute() reclama la fila adelantando su
 * timestamp al horario de mañana ANTES de repartir, así que el error fatal se llevaba el
 * envío del día en silencio y el jugador sólo veía que la ruta "no hacía nada". Y como
 * el worker gana casi siempre la carrera contra la carga de página (corre cada segundo),
 * la ruta no se ejecutaba nunca.
 *
 * Los checks de rutas comerciales son todos sobre el código fuente y por eso no lo
 * vieron: esto arranca el proceso de verdad.
 *
 * Cubre:
 *   A. El worker arranca y termina limpio, sin warnings ni fatales.
 *   B. Su bootstrap deja definidos TODOS los globales que declaran las funciones del
 *      camino automático del Mercado (drift-catcher: si mañana alguien agrega un
 *      `global $loquesea` ahí, esto falla antes que producción).
 *   C. La cadena real de reparto de una ruta corre sin lanzar, en un proceso que sólo
 *      tiene el bootstrap del worker.
 *   D. Un fallo repartiendo devuelve la ruta al reintento en vez de perder el día.
 */

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit(1);
}

$root = dirname(__DIR__);
chdir($root);

// -----------------------------------------------------------------------------
// Modo sonda: el propio archivo se vuelve a ejecutar en un proceso aparte, con
// EXACTAMENTE el bootstrap de market_worker.php y nada más, y contesta en JSON. Así
// las comprobaciones de abajo miden el entorno real del worker y no el de este proceso.
// -----------------------------------------------------------------------------
if(in_array('--probe', $argv, true)) {
	error_reporting(E_ALL);
	ini_set('display_errors', '0');
	$probeWarnings = array();
	set_error_handler(function($no,$str,$file,$line) use (&$probeWarnings) {
		$probeWarnings[] = $str.' ('.basename($file).':'.$line.')';
		return true;
	});

	// Misma lista de includes que tools/market_worker.php, leída del archivo para que no
	// pueda desincronizarse de lo que el worker carga de verdad. Se conservan el orden y
	// las expresiones tal cual: el orden importa (Lang define las constantes U1..U50 que
	// Technology necesita al declararse, y LANG sale de config.php, que entra con el
	// primer include).
	$workerSource = file_get_contents($root.'/tools/market_worker.php');
	preg_match_all('/^require_once (\$root\..+?);$/m', $workerSource, $workerIncludes);
	date_default_timezone_set('America/Argentina/Buenos_Aires');
	define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
	foreach($workerIncludes[1] as $includeExpression) {
		require_once eval('return '.$includeExpression.';');
	}

	// Se fotografían los globales ACÁ, apenas termina el bootstrap y antes de correr
	// nada: un `global $x;` dentro de una función crea la clave en $GLOBALS aunque el
	// valor sea null, así que medir después daría por definido justo lo que falta (es lo
	// que pasaba con $technology: la clave existía, el objeto no). Por lo mismo se
	// descartan los null: lo que importa es que el objeto/la tabla estén ahí de verdad.
	$defined = array();
	foreach($GLOBALS as $name => $value) {
		if($value !== null) {
			$defined[] = $name;
		}
	}

	$probeVillage = 0;
	$row = mysqli_fetch_row(mysqli_query($database->connection,
		"SELECT wref FROM ".TB_PREFIX."vdata WHERE owner > 0 ORDER BY wref ASC LIMIT 1"));
	if($row) {
		$probeVillage = (int)$row[0];
	}

	// La cadena exacta que corría el reparto de una ruta cuando explotaba:
	// sendResource2 -> accrueProductionBeforeChange -> updateRes -> bountycalculateProduction
	// -> getAllUnits($technology) / getUpkeep($u1..$u50).
	$chainError = '';
	if($probeVillage > 0) {
		try {
			$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
			$accrue = new ReflectionMethod('Automation', 'accrueProductionBeforeChange');
			$accrue->setAccessible(true);
			$accrue->invoke($automation, $probeVillage, null);
		} catch(Throwable $e) {
			$chainError = get_class($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';
		}
	}

	restore_error_handler();
	echo json_encode(array(
		'globals' => $defined,
		'village' => $probeVillage,
		'chainError' => $chainError,
		'warnings' => $probeWarnings,
	));
	exit(0);
}

error_reporting(E_ALL);

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

$php = PHP_BINARY;

// ---------------------------------------------------------------------------
section('A. El worker arranca y termina limpio');
// ---------------------------------------------------------------------------
$workerOutput = array();
$workerStatus = 1;
exec(escapeshellarg($php).' '.escapeshellarg($root.'/tools/market_worker.php').' 2>&1', $workerOutput, $workerStatus);
$workerText = implode("\n", $workerOutput);
check($workerStatus === 0, 'market_worker.php termina con código 0 (salió '.$workerStatus.')');
check(stripos($workerText,'Fatal error') === false,
	'market_worker.php no lanza ningún error fatal'.($workerText === '' ? '' : ":\n        ".str_replace("\n","\n        ",$workerText)));
check(stripos($workerText,'Warning') === false && stripos($workerText,'Notice') === false,
	'market_worker.php no emite warnings ni notices'.($workerText === '' ? '' : ":\n        ".str_replace("\n","\n        ",$workerText)));

// ---------------------------------------------------------------------------
section('B. El bootstrap del worker define todo lo que el Mercado automático usa');
// ---------------------------------------------------------------------------
$probeRaw = shell_exec(escapeshellarg($php).' '.escapeshellarg(__FILE__).' --probe 2>&1');
$probe = json_decode((string)$probeRaw, true);
if(!check(is_array($probe) && isset($probe['globals']),
	'la sonda con el bootstrap del worker corre y contesta'.(is_array($probe) ? '' : ":\n        ".trim((string)$probeRaw)))) {
	echo "\n".count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
	exit(1);
}
$workerGlobals = array_flip($probe['globals']);
check(empty($probe['warnings']),
	'el bootstrap del worker no emite warnings'.(empty($probe['warnings']) ? '' : ': '.implode(' | ',$probe['warnings'])));

// Los objetos que el camino automático usa sí o sí.
foreach(array('database','generator','technology','logging') as $needed) {
	check(isset($workerGlobals[$needed]), 'el worker define $'.$needed);
}
// Tablas de datos: mercaderes/capacidad y consumo de cereal de cada unidad.
foreach(array('bid17','bid28','hero_levels') as $needed) {
	check(isset($workerGlobals[$needed]), 'el worker carga $'.$needed);
}
$missingUnits = array();
for($i = 1; $i <= 50; $i++) {
	if(!isset($workerGlobals['u'.$i])) {
		$missingUnits[] = 'u'.$i;
	}
}
check(empty($missingUnits),
	'el worker carga $u1..$u50, que getUpkeep() usa para el consumo de cereal'
	.(empty($missingUnits) ? '' : ' (faltan: '.implode(', ',$missingUnits).')'));

// Drift-catcher: cada `global $x` declarado en las funciones del camino automático tiene
// que existir tras el bootstrap del worker. Agregar una dependencia nueva ahí sin
// cargarla acá vuelve a romper las rutas comerciales en silencio.
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
// $session y $village son objetos por petición: estas funciones los declaran, pero en el
// camino automático nunca los tocan porque siempre reciben un vid explícito (se verifica
// abajo). El resto tiene que estar.
$perRequest = array('session' => true, 'village' => true);
$marketPathMethods = array(
	'TradeRoute', 'marketComplete', 'sendResource2',
	'bountyLoadTown', 'bountycalculateProduction', 'getUpkeep', 'getAllUnits',
);
foreach($marketPathMethods as $method) {
	// El `global` no siempre es la primera línea del cuerpo (marketComplete borra su
	// archivo de cerrojo antes), así que se busca dentro del arranque de la función.
	if(preg_match('/function '.$method.'\s*\([^)]*\)\s*\{(?:(?!function ).){0,400}?global ([^;]+);/s', $automationSource, $m) !== 1) {
		check(false, 'se puede leer la lista de globales de '.$method.'()');
		continue;
	}
	foreach(explode(',', $m[1]) as $global) {
		$global = ltrim(trim($global), '$');
		if($global === '' || isset($perRequest[$global])) {
			continue;
		}
		check(isset($workerGlobals[$global]),
			$method.'() declara $'.$global.' y el worker lo define');
	}
}
check(strpos($automationSource,'$this->getUpkeep($this->getAllUnits($bountywid), 0, $bountywid)') !== false,
	'getUpkeep() recibe siempre un vid explícito en el camino automático, así que no necesita $village');

// ---------------------------------------------------------------------------
section('C. La cadena real de reparto corre bajo ese bootstrap');
// ---------------------------------------------------------------------------
if((int)$probe['village'] > 0) {
	check($probe['chainError'] === '',
		'acreditar la producción de una aldea (lo primero que hace sendResource2) no lanza'
		.($probe['chainError'] === '' ? '' : ":\n        ".$probe['chainError']));
} else {
	echo "  (mundo sin aldeas: no se pudo probar la cadena real)\n";
}

// ---------------------------------------------------------------------------
section('D. Un fallo repartiendo no se lleva el envío del día');
// ---------------------------------------------------------------------------
preg_match('/private function TradeRoute\(\).*?\n    \}/s', $automationSource, $tradeRouteBody);
$tradeRouteBody = isset($tradeRouteBody[0]) ? $tradeRouteBody[0] : '';
check($tradeRouteBody !== '', 'se puede leer el cuerpo de TradeRoute()');
// El orden importa: la fila se reclama primero (para que dos workers no la dupliquen),
// así que a partir de ahí cualquier salida sin envío tiene que reprogramarla.
check(strpos($tradeRouteBody,'claimTradeRoute(') < strpos($tradeRouteBody,'sendResource2('),
	'la fila se reclama antes de repartir (evita que dos procesos manden la misma ruta)');
check(preg_match('/catch\(Throwable \$e\)/', $tradeRouteBody) === 1,
	'un error fatal repartiendo se atrapa en vez de cortar el barrido y perder el día');
check(strpos($tradeRouteBody,'if(!$sent) {') !== false
	&& strpos($tradeRouteBody,'retryTradeRoute(') !== false,
	'cualquier salida sin envío devuelve la ruta al reintento corto');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Market worker checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
