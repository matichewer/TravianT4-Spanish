<?php
/**
 * Auditoría de las rutas comerciales (Mercado, pestaña 4): creación/edición,
 * reparto periódico y la vista de listado.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_trade_routes.php
 *
 * Cubre:
 *   A. requiredMerchants() usa el mismo colchón de coma flotante que sendResource2,
 *      asi el numero de mercaderes exigido al crear una ruta no puede quedar por
 *      encima del que hace falta realmente al entregarla.
 *   B. La capa de datos expone claimTradeRoute (reclamo atómico WHERE timestamp=...)
 *      y getTradeRoutesFrom (las rutas de la aldea, para recalcular los mercaderes
 *      que tienen comprometidos con la capacidad de hoy).
 *   C. Automation::TradeRoute() reclama cada fila antes de procesarla (sin editTradeRoute
 *      incondicional al final) y borra las rutas huérfanas cuando el origen o el
 *      destino ya no pertenecen al dueño de la ruta.
 *   D. Market::procTradeRoutes valida contra la capacidad libre (edificio menos lo ya
 *      comprometido por otras rutas), no contra la capacidad total del edificio.
 *   E. La vista 17_4.tpl no ofrece borrar/editar una ruta cuyo origen es otra aldea
 *      del mismo jugador (antes fallaba en silencio).
 *   F. El worker dedicado dispara rutas además de completar viajes, y el calendario
 *      salta al próximo horario futuro sin ráfagas de días atrasados.
 *   G. Los fallos transitorios se reintentan y los mercaderes de rutas quedan
 *      realmente reservados frente a envíos manuales.
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

// ---------------------------------------------------------------------------
section('A. requiredMerchants() sin desfasaje de coma flotante');
// ---------------------------------------------------------------------------
// La regla del redondeo vive en Automation (Market y las plantillas la delegan),
// así que hace falta cargarlo antes que Market.
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP',true);
require_once dirname(__DIR__).'/GameEngine/Automation.php';
require_once dirname(__DIR__).'/GameEngine/Market.php';

$reflection = new ReflectionClass('Market');
$market = $reflection->newInstanceWithoutConstructor();
$maxcarryProperty = $reflection->getProperty('maxcarry');
$maxcarryProperty->setAccessible(true);
$requiredMerchants = $reflection->getMethod('requiredMerchants');
$requiredMerchants->setAccessible(true);

// Caso real detectado: tribu gala (750) + Almacén Grande con bono 230% => maxcarry no entero.
$maxcarryProperty->setValue($market,750 * (230/100));
check($requiredMerchants->invoke($market,1725) === 1,
	'un total igual a la capacidad exacta (1725/1725) exige 1 mercader, no 2 por redondeo de flotantes');
check($requiredMerchants->invoke($market,1726) === 2,
	'un recurso más de la capacidad exacta sigue exigiendo el segundo mercader');

$maxcarryProperty->setValue($market,750);
check($requiredMerchants->invoke($market,750) === 1,'capacidad entera: sigue pidiendo 1 mercader justo en el límite');
check($requiredMerchants->invoke($market,751) === 2,'capacidad entera: un recurso de más pide el segundo mercader');
check($requiredMerchants->invoke($market,0) === 0,'sin recursos no hace falta ningún mercader');
check($requiredMerchants->invoke($market,-5) === 0,'un total negativo no exige mercaderes (lo rechaza el llamador)');

// El mismo colchón que usa Automation::sendResource2 al entregar: ahora es literalmente
// la misma función, no una copia con el mismo número.
$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
check(strpos($automationSource,'ceil(($amount - 0.1) / $carryCapacity)') !== false,
	'merchantsRequired() es la única implementación del colchón de 0.1');
check(strpos($automationSource,'$reqMerc = self::merchantsRequired(array_sum($resource), $maxcarry2)') !== false,
	'sendResource2 delega el redondeo en merchantsRequired() al entregar');
$marketSource = file_get_contents(dirname(__DIR__).'/GameEngine/Market.php');
check(strpos($marketSource,'return Automation::merchantsRequired($amount,$this->maxcarry);') !== false,
	'requiredMerchants() usa la misma función al crear/editar una ruta');

// ---------------------------------------------------------------------------
section('B. Capa de datos: reclamo atómico y capacidad comprometida');
// ---------------------------------------------------------------------------
$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
check(strpos($dbSource,'function claimTradeRoute(') !== false,'existe claimTradeRoute()');
check(strpos($dbSource,'SET timestamp = $nextTimestamp WHERE id = $id AND timestamp = $timestamp') !== false,
	'claimTradeRoute() fija el próximo horario con un WHERE sobre el valor leído (reclamo optimista)');
check(strpos($dbSource,"mysqli_affected_rows(\$this->connection) === 1") !== false
	&& strpos($dbSource,'function claimTradeRoute(') !== false,
	'claimTradeRoute() solo confirma éxito si afectó exactamente una fila');

check(strpos($dbSource,'function getTradeRoutesFrom(') !== false,'existe getTradeRoutesFrom()');
check(strpos($dbSource,"SELECT id, wood, clay, iron, crop FROM \" . TB_PREFIX . \"route WHERE `from` = \$vid") !== false,
	'getTradeRoutesFrom() devuelve los recursos de cada ruta de esa aldea de origen');
check(strpos($dbSource,'AND id <> $excludeRouteId') !== false,
	'getTradeRoutesFrom() puede excluir la propia ruta al editarla');
check(strpos($dbSource,'SELECT SUM(merchant) FROM') === false,
	'ya nadie suma la columna merchant guardada: quedaba congelada en la capacidad del día de creación');

check(strpos($dbSource,'function deleteTradeRoute(') !== false,'sigue existiendo deleteTradeRoute() (borrado sin dueño, para limpieza del sistema)');
check(strpos($dbSource,'function retryTradeRoute(') !== false,'existe retryTradeRoute() para fallos transitorios');
check(strpos($dbSource,'WHERE id = $id AND timestamp = $claimedTimestamp') !== false,
	'el reintento sólo revierte el reclamo que sigue perteneciendo a esta ejecución');

// ---------------------------------------------------------------------------
section('C. Automation::TradeRoute(): reclamo atómico y limpieza de huérfanas');
// ---------------------------------------------------------------------------
check(preg_match('/private function TradeRoute\(\)\s*\{.*?\n    \}/s',$automationSource,$m) === 1,'se encuentra el cuerpo de TradeRoute()');
$tradeRouteBody = $m[1] ?? '';
if($tradeRouteBody === '' && isset($m[0])) { $tradeRouteBody = $m[0]; }

check(strpos($tradeRouteBody,"claimTradeRoute(\$data['id'], \$data['timestamp'], \$nextTimestamp)") !== false,
	'TradeRoute() reclama cada fila antes de procesarla');
check(strpos($tradeRouteBody,'if(!$database->claimTradeRoute') !== false,
	'si el reclamo falla (otro request ya la tomó), la fila se salta con continue');
check(strpos($tradeRouteBody,'$database->editTradeRoute(') === false,
	'ya no hay un editTradeRoute incondicional al final (quedaba fuera del reclamo atómico)');

check(strpos($tradeRouteBody,"getVillageField(\$data['from'], \"owner\")") !== false
	&& strpos($tradeRouteBody,"getVillageField(\$data['wid'], \"owner\")") !== false,
	'TradeRoute() vuelve a comprobar el dueño real del origen y del destino antes de enviar');
check(strpos($tradeRouteBody,'!== (int)$data[\'uid\']') !== false,
	'la comprobación de dueño compara contra el uid guardado en la ruta');
check(strpos($tradeRouteBody,"\$database->deleteTradeRoute(\$data['id'])") !== false,
	'una ruta huérfana (aldea borrada o conquistada) se elimina en vez de reintentar para siempre');
check(strpos($tradeRouteBody,'if(!$this->sendResource2(') !== false
	&& strpos($tradeRouteBody,'retryTradeRoute(') !== false,
	'un envío fallido se reprograma para reintento en vez de perder el día');

// ---------------------------------------------------------------------------
section('D. Market::procTradeRoutes(): capacidad libre, no capacidad total');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$committedByOtherRoutes = $this->routeMerchantsCommitted($routeId ?: 0);') !== false,
	'procTradeRoutes() descuenta lo ya comprometido por otras rutas de la misma aldea');
check(strpos($marketSource,'$reqMerc > $merchantsFreeForRoutes') !== false,
	'la validación usa la capacidad libre para rutas, no $this->merchant a secas');
check(strpos($marketSource,'$reqMerc > $this->merchant)') === false,
	'ya no queda el chequeo viejo contra la capacidad total sin descontar rutas existentes');
check(strpos($marketSource,"\$routeId = \$postAction === 'editRoute'") !== false,
	'al editar, el routeId se calcula antes de validar para poder excluir la propia ruta');

// ---------------------------------------------------------------------------
section('E. Vista 17_4.tpl: no ofrecer borrar/editar la ruta de otra aldea');
// ---------------------------------------------------------------------------
$tplSource = file_get_contents(dirname(__DIR__).'/Templates/Build/17_4.tpl');
check(strpos($tplSource,"\$isOwnVillage = (int)\$route['from'] === (int)\$village->wid;") !== false,
	'la vista calcula si la ruta listada pertenece a la aldea actual');
check(preg_match('/if\(\$isOwnVillage\)\{ \?><a href="build\.php\?gid=17&amp;t=4&amp;action=delRoute/',$tplSource) === 1,
	'el enlace de eliminar solo aparece si la ruta es de esta aldea');
check(preg_match('/if\(\$isOwnVillage\)\{ \?><a href="build\.php\?id=<\?php echo \$id; \?>&t=4&action=editRoute/',$tplSource) === 1,
	'el enlace de editar solo aparece si la ruta es de esta aldea');
check(strpos($tplSource,'gestionar desde esa aldea') !== false,
	'las rutas de otra aldea muestran cómo gestionarlas en vez de un enlace que siempre falla');

// ---------------------------------------------------------------------------
section('F. Worker y calendario de ejecución');
// ---------------------------------------------------------------------------
$timezone = date_default_timezone_get();
date_default_timezone_set('America/Argentina/Buenos_Aires');
$morning = strtotime('2026-08-11 09:30:00');
$afterStart = strtotime('2026-08-11 10:30:00');
check(Automation::nextTradeRouteTimestamp(10,$morning) === strtotime('2026-08-11 10:00:00'),
	'antes del horario devuelve la ejecución del mismo día');
check(Automation::nextTradeRouteTimestamp(10,$afterStart) === strtotime('2026-08-12 10:00:00'),
	'después del horario devuelve directamente el día siguiente');
check(Automation::nextTradeRouteTimestamp(10,strtotime('2026-08-08 10:00:00')) === strtotime('2026-08-09 10:00:00'),
	'el cálculo siempre produce una única próxima ejecución futura');
date_default_timezone_set($timezone);

check(strpos($automationSource,'if($marketOnly) {') !== false
	&& preg_match('/if\(\$marketOnly\)\s*\{\s*\$this->marketComplete\(\);\s*\$this->TradeRoute\(\);/s',$automationSource) === 1,
	'el modo usado por market_worker procesa entregas y también dispara rutas');
check(strpos($tradeRouteBody,'timestamp <= $time ORDER BY timestamp ASC') !== false,
	'el segundo exacto del horario ya cuenta como vencido y se respeta el orden cronológico');

// ---------------------------------------------------------------------------
section('G. Reserva efectiva y reintentos');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$database->totalMerchantUsed($village->wid)') !== false
	&& strpos($marketSource,'+ $this->routeMerchantsCommitted()') !== false,
	'merchantAvail descuenta tanto movimientos reales como mercaderes reservados por rutas');
check(strpos($tplSource,'$market->routeMerchants($route)') !== false,
	'el listado muestra los mercaderes que la ruta ocupa hoy, no los del día en que se creó');
check(strpos($tradeRouteBody,'TRADE_ROUTE_RETRY_DELAY') !== false,
	'los fallos transitorios usan una espera acotada antes del siguiente intento');

// ---------------------------------------------------------------------------
section('H. Crear una ruta no cuesta oro ni falla en silencio');
// ---------------------------------------------------------------------------
$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$createRouteStart = strpos($dbSource,'function createTradeRoute(');
$createRouteBody = $createRouteStart === false ? '' : substr($dbSource,$createRouteStart,strpos($dbSource,'function getTradeRoute(') - $createRouteStart);
check($createRouteBody !== '','createTradeRoute() sigue existiendo en la capa de datos');
check(strpos($createRouteBody,'gold') === false,
	'createTradeRoute() no cobra oro: el requisito es el Club del Oro, igual que la pestaña');

check(strpos($tplSource,"\$session->gold > 1") === false,
	'el formulario de creación no se esconde según el saldo de oro');

check(strpos($marketSource,'private function tradeRouteFailure(') !== false
	&& strpos($marketSource,"\$_SESSION['tradeRouteError']") !== false,
	'los rechazos al guardar dejan un motivo en la sesión en vez de recargar mudos');
foreach(array('noresources','merchants','target','invalid') as $errorCode) {
	check(strpos($marketSource,"tradeRouteFailure('".$errorCode."'") !== false,
		"procTradeRoutes() reporta el motivo '".$errorCode."'");
	check(strpos($tplSource,"case '".$errorCode."':") !== false,
		"la vista tiene texto para el motivo '".$errorCode."'");
}
check(preg_match('/if\(!\$database->createTradeRoute\(/',$marketSource) === 1,
	'un INSERT fallido tambien se reporta en vez de darse por bueno');
check(strpos($marketSource,"unset(\$_SESSION['tradeRouteError'])") !== false,
	'el aviso se consume una sola vez y no persiste al recargar');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Trade route checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
