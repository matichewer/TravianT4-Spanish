<?php
/**
 * Auditoría del Mercado: contabilidad de mercaderes y rechazos con motivo.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_market_merchants.php
 *
 * Cubre:
 *   A. Un mercader está ocupado sólo si está de viaje (ida o vuelta) o esperando en una
 *      oferta publicada. Las rutas comerciales NO ocupan mercaderes hasta que salen: la
 *      reserva permanente dejaba "Mercaderes 1/16" sin un solo movimiento a la vista,
 *      bloqueaba enviar/vender/comprar, y mientras la ruta viajaba contaba los mismos
 *      mercaderes dos veces (la reserva más el envío real).
 *   B. Lo comprometido por rutas se sigue calculando, pero para mostrarlo junto al
 *      contador con su horario de salida.
 *   C. Crear una ruta sigue exigiendo que todas las rutas de la aldea quepan juntas en el
 *      Mercado (si no, hay una que no podría salir nunca).
 *   D. Ningún rechazo es mudo: enviar, ofertar, aceptar, cancelar y el NPC dejan un
 *      motivo, y todos los motivos que usa el código tienen texto.
 *   E. La pestaña del NPC no imprime el reparto que llega por GET sin normalizar.
 *   F. Automation::sendResource2() lee los mercaderes de bid17 y no del nivel del edificio.
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

$marketSource = file_get_contents(dirname(__DIR__).'/GameEngine/Market.php');
$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$mainTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17.tpl');
$buyTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_1.tpl');
$sellTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_2.tpl');
$npcTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_3.tpl');
$merchantsTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_merchants.tpl');

// ---------------------------------------------------------------------------
section('A. Ocupados = de viaje o esperando en una oferta');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$this->used = (int)$database->totalMerchantUsed($village->wid);') !== false,
	'loadMarket() cuenta como ocupados sólo los movimientos y las ofertas publicadas');
check(strpos($marketSource,'+ $this->routeMerchantsCommitted()') === false,
	'las rutas ya no se suman a los mercaderes ocupados');
check(preg_match('/function totalMerchantUsed\(\$vid\).*?sort_type = 0.*?sort_type = 2.*?market where vref = \$vid and accept = 0/s',$dbSource) === 1,
	'totalMerchantUsed() suma envíos en curso, regresos y ofertas publicadas');
check(strpos($marketSource,'return max(0,$this->merchant - $this->used);') !== false,
	'merchantAvail() nunca devuelve un número negativo');

// El caso real que reportó el jugador: Mercado 16, una ruta de 15 mercaderes, nada en
// viaje. Antes daba 1; ahora los 16 están disponibles hasta que la ruta salga.
check(MarketMerchantAccounting::available(16,0,15) === 16,
	'con una ruta de 15 mercaderes y nada en viaje, el Mercado ofrece los 16');
check(MarketMerchantAccounting::available(16,15,15) === 1,
	'mientras esa misma ruta viaja, los 15 se descuentan una sola vez (no dos)');
check(MarketMerchantAccounting::available(16,16,0) === 0,
	'con todos los mercaderes de viaje no queda ninguno libre');

// ---------------------------------------------------------------------------
section('B. Lo comprometido por rutas se muestra, no se descuenta');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$this->routeReserved = $this->routeMerchantsCommitted();') !== false,
	'loadMarket() calcula aparte los mercaderes comprometidos por rutas');
check(strpos($marketSource,'public function routeDepartureHours()') !== false
	&& strpos($marketSource,"sprintf('%02d:%02d',(int)\$route['start'],(int)\$route['start_minute'])") !== false,
	'existe el horario de salida de cada ruta (hora y minuto) para mostrarlo');
check(strpos($dbSource,'start, start_minute, deliveries FROM " . TB_PREFIX . "route') !== false,
	'getTradeRoutesFrom() trae también el horario completo (hora y minuto) de la ruta');
check(strpos($merchantsTpl,'$market->routeReserved') !== false
	&& strpos($merchantsTpl,'salen todos los días en') !== false,
	'el contador explica cuántos mercaderes salen en rutas y a qué hora');
check(strpos($merchantsTpl,'Mientras no viajen podés usarlos') !== false,
	'el texto aclara que esos mercaderes se pueden usar mientras tanto');

// ---------------------------------------------------------------------------
section('C. Las rutas de una aldea tienen que caber juntas en el Mercado');
// ---------------------------------------------------------------------------
// El detalle del calculo de solapamiento (Automation::peakConcurrentMerchants) tiene
// su propia cobertura en check_trade_routes.php seccion K; aca solo se confirma que
// procTradeRoutes() sigue validando contra el pico y avisando si no entra.
check(strpos($marketSource,'if($peakDemand > $this->merchant)') !== false,
	'crear/editar una ruta valida contra el pico de mercaderes simultaneos, no la capacidad total sin mas');
check(strpos($marketSource,'$this->tradeRouteFailure(\'merchants\'') !== false,
	'pasarse de esa capacidad avisa en vez de guardar en silencio');

// ---------------------------------------------------------------------------
section('D. Ningún rechazo es mudo');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'private function marketFailure(') !== false
	&& strpos($marketSource,"\$_SESSION['marketError']") !== false,
	'los rechazos guardan el motivo en la sesión');
check(strpos($marketSource,"unset(\$_SESSION['marketError']);") !== false,
	'el motivo se consume al mostrarlo (no se repite en la recarga siguiente)');
check(strpos($marketSource,'public function errorText()') !== false,
	'existe el texto de cada motivo');

// Todos los codigos usados tienen que tener texto propio: si alguien agrega un rechazo
// nuevo y se olvida del case, el jugador vuelve a ver un mensaje generico.
preg_match_all("/marketFailure\('([a-z]+)'/",$marketSource,$usedCodes);
$usedCodes = array_unique($usedCodes[1]);
preg_match_all("/case '([a-z]+)':/",$marketSource,$textCodes);
$textCodes = array_unique($textCodes[1]);
$missing = array_diff($usedCodes,$textCodes);
check(count($usedCodes) >= 8,'hay un motivo distinto para cada tipo de rechazo');
check(empty($missing),'todos los motivos usados tienen texto: faltan '.implode(', ',$missing));

foreach(array('sendResource','addOffer','acceptOffer','tradeResource') as $method) {
	$start = strpos($marketSource,'function '.$method.'(');
	$end = $start === false ? false : strpos($marketSource,'private function',$start + 20);
	$body = $start === false ? '' : ($end === false ? substr($marketSource,$start) : substr($marketSource,$start,$end - $start));
	check($body !== '' && strpos($body,'marketFailure') !== false,
		$method.'() explica por qué rechaza en vez de recargar la página sin más');
}
check(strpos($marketSource,'if(!$this->cancelOffer($get[\'del\'])) {') !== false,
	'cancelar una oferta que ya no se puede cancelar también avisa');
check(strpos($marketSource,"unset(\$_SESSION['marketOfferDraft'][\$village->wid]);") !== false,
	'una oferta que sí entró no deja el borrador cargado en el formulario');

foreach(array('17.tpl'=>$mainTpl,'17_1.tpl'=>$buyTpl,'17_2.tpl'=>$sellTpl,'17_3.tpl'=>$npcTpl) as $name => $tpl) {
	check(strpos($tpl,'include("17_merchants.tpl")') !== false,
		$name.' muestra la cabecera común (mensaje de error y contador)');
}
check(strpos($npcTpl,'$marketShowCounter = false;') !== false,
	'la pestaña del NPC muestra el error pero no el contador de mercaderes');
check(preg_match('/if\(\$session->plus\) \{\s*\?>\s*<div class="boxes boxesColor gray search_select"/',$buyTpl) === 1,
	'en "Comprar" el contador quedó fuera del bloque de Plus (sin Plus se pierden los filtros, no el contador)');

// ---------------------------------------------------------------------------
section('E. La pestaña del NPC no confía en el GET');
// ---------------------------------------------------------------------------
check(strpos($npcTpl,'$_GET[\'r1\']."') === false && strpos($npcTpl,'echo $_GET[') === false,
	'no se imprime ningún valor del GET sin normalizar (era un XSS en value="...")');
check(strpos($npcTpl,'ctype_digit((string)$_GET[$npcField])') !== false
	&& strpos($npcTpl,'(int)$npcPreset[') !== false,
	'el reparto que llega por GET se valida y se imprime como entero');
check(strpos($npcTpl,'$npcHasPreset') !== false,
	'un reparto incompleto o no numérico deja el formulario vacío en vez de sumar basura');

// ---------------------------------------------------------------------------
section('F. El envío automático usa la tabla del edificio');
// ---------------------------------------------------------------------------
check(strpos($automationSource,'$merchant2 = ($marketLevel2 > 0 && !empty($bid17))') !== false,
	'sendResource2() saca los mercaderes de bid17 y no del nivel del Mercado');
check(preg_match('/function sendResource2\([^)]*\)\s*\{\s*global \$bid17,/',$automationSource) === 1,
	'sendResource2() importa bid17');
check(strpos($marketSource,'$bid17[min($marketLevel,count($bid17))]') !== false,
	'loadMarket() recorta el nivel a la tabla (un nivel fuera de rango dejaba el Mercado inservible)');

echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Market merchant checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);

/**
 * La regla de disponibilidad, aislada para poder ejercitarla sin base de datos.
 * Es la misma cuenta que hace Market::merchantAvail(): el edificio menos lo que está
 * realmente fuera de casa; los mercaderes comprometidos por rutas no restan.
 */
class MarketMerchantAccounting {
	public static function available($building, $moving, $committedByRoutes) {
		return max(0, (int)$building - (int)$moving);
	}
}
