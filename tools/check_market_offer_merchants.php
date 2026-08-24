<?php
/**
 * Auditoría de los mercaderes que ocupa una oferta publicada en el Mercado.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_market_offer_merchants.php
 *
 * La regla: una oferta aparta —y despacha— los mercaderes que hacen falta CON LA OFICINA
 * DE COMERCIO DE HOY, no con la del día en que se publicó. Antes el número se calculaba
 * una sola vez al publicar y quedaba congelado en la columna market.merchant, así que un
 * jugador que ponía una venta sin Oficina y después subía el edificio seguía con el triple
 * de mercaderes apartados, y cuando alguien le compraba la venta salía también con el
 * triple. Es la misma regla que ya tenían las rutas comerciales.
 *
 *   A. offerMerchantsCommitted(): la reserva se recalcula, y redondea por oferta.
 *   B. merchantsBusy(): de viaje + esperando en una oferta, una sola definición.
 *      Lo que ya está en el camino NO se recalcula: salió con los mercaderes que salió.
 *   C. Al concretarse la venta se recalcula con la Oficina y la tribu DEL VENDEDOR.
 *   D. El escenario del jugador, de punta a punta.
 *   E. Nadie lee market.merchant para contabilidad (queda como dato histórico).
 *   F. El contador, el resumen de aldeas y el envío automático usan el mismo helper.
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
// Bootstrap mínimo: constantes + tablas, sin tocar la base real.
// ---------------------------------------------------------------------------
define('TB_PREFIX', 's1_');
if(!defined('TRADER_CAPACITY')) {
	define('TRADER_CAPACITY', '1');
}
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Automation.php';

/**
 * Capa de base de datos de mentira, con las dos únicas funciones que toca esta cuenta.
 * Se le carga a mano lo publicado y lo que está de viaje.
 */
class OfferMerchantsFakeDB {
	public $offers = array();      // wref => array de gamt
	public $traveling = array();   // wref => mercaderes en el camino

	public function openOfferAmounts($vid) {
		return isset($this->offers[$vid]) ? $this->offers[$vid] : array();
	}

	public function travelingMerchants($vid) {
		return isset($this->traveling[$vid]) ? $this->traveling[$vid] : 0;
	}
}

$database = new OfferMerchantsFakeDB();

$marketSource = file_get_contents(dirname(__DIR__).'/GameEngine/Market.php');
$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$sellTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_2.tpl');

// Capacidades de referencia (galo: 750 de base, 10 puntos por nivel de Oficina).
$sinOficina = Automation::merchantCarryCapacity(3, 0);   // 750
$oficina10  = Automation::merchantCarryCapacity(3, 10);  // 1500
$oficina20  = Automation::merchantCarryCapacity(3, 20);  // 2250

// ---------------------------------------------------------------------------
section('A. La reserva se recalcula con la Oficina de hoy');
// ---------------------------------------------------------------------------
$database->offers = array(101 => array(3000));
check(Automation::offerMerchantsCommitted(101, $sinOficina) === 4,
	'una oferta de 3000 sin Oficina aparta 4 mercaderes');
check(Automation::offerMerchantsCommitted(101, $oficina10) === 2,
	'la MISMA oferta, con la Oficina a 10, pasa a apartar 2');
check(Automation::offerMerchantsCommitted(101, $oficina20) === 2,
	'con la Oficina a 20 aparta 2 (3000 no entra en un solo mercader de 2250)');

// El redondeo es por oferta, no sobre la suma: dos ofertas viajan por separado.
$database->offers = array(101 => array(600, 600));
check(Automation::offerMerchantsCommitted(101, 1000) === 2,
	'dos ofertas de 600 con capacidad 1000 ocupan 2 mercaderes, no 1 (el ceil es por oferta)');
$database->offers = array(101 => array(1200));
check(Automation::offerMerchantsCommitted(101, 1000) === 2,
	'una sola oferta de 1200 con capacidad 1000 ocupa 2');

// Bordes.
$database->offers = array(101 => array());
check(Automation::offerMerchantsCommitted(101, $sinOficina) === 0,
	'sin ofertas publicadas no hay nada apartado');
$database->offers = array(101 => array((int)$sinOficina));
check(Automation::offerMerchantsCommitted(101, $sinOficina) === 1,
	'una oferta de exactamente un mercader lleno ocupa 1, no 2');
$database->offers = array(101 => array(0));
check(Automation::offerMerchantsCommitted(101, $sinOficina) === 0,
	'una oferta vacía no aparta a nadie');
check(Automation::offerMerchantsCommitted(999, $sinOficina) === 0,
	'una aldea sin filas devuelve 0, no un aviso de PHP');

// ---------------------------------------------------------------------------
section('B. merchantsBusy(): de viaje + esperando, y lo de viaje no se recalcula');
// ---------------------------------------------------------------------------
$database->offers = array(101 => array(3000));
$database->traveling = array(101 => 3);
check(Automation::merchantsBusy(101, $sinOficina) === 7,
	'3 mercaderes en el camino + una oferta de 3000 sin Oficina = 7 ocupados');
check(Automation::merchantsBusy(101, $oficina10) === 5,
	'al subir la Oficina sólo baja la parte de las ofertas: los 3 del camino no se tocan');

// Un envío ya despachado salió con los mercaderes que salió: la carga no se re-reparte
// a mitad de camino porque el edificio suba (ni porque se lo catapulten).
$database->offers = array(101 => array());
check(Automation::merchantsBusy(101, $sinOficina) === Automation::merchantsBusy(101, $oficina20),
	'sin ofertas, la capacidad no cambia el número de ocupados');

$database->traveling = array();
$database->offers = array(101 => array(3000));
check(Automation::merchantsBusy(101, $sinOficina) === 4,
	'sin nadie de viaje, lo ocupado es exactamente lo que apartan las ofertas');

check(strpos($automationSource,'(int)$database->travelingMerchants($vid) + self::offerMerchantsCommitted($vid, $carryCapacity)') !== false,
	'merchantsBusy() es la única suma de las dos partes');
check(strpos($dbSource,'function totalMerchantUsed(') === false,
	'la función vieja ya no existe: una llamada sin actualizar falla en vez de contar de menos');
check(preg_match('/function travelingMerchants\(\$vid\).*?\n        \t\}/s',$dbSource,$viaje) === 1
	&& strpos($viaje[0],'market') === false,
	'travelingMerchants() no mira la tabla market: las ofertas no son un movimiento');
check(preg_match('/function openOfferAmounts\(\$vid\).*?\n        \t\}/s',$dbSource,$ofertas) === 1
	&& strpos($ofertas[0],'SELECT gamt') !== false
	&& strpos($ofertas[0],'merchant') === false,
	'openOfferAmounts() lee lo ofrecido (gamt) y no la columna merchant congelada');

// ---------------------------------------------------------------------------
section('C. Al venderse se recalcula con la Oficina y la tribu del VENDEDOR');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$hisMerc = Automation::merchantsRequired(') !== false,
	'acceptOffer() calcula los mercaderes del vendedor en vez de leerlos de la fila');
check(strpos($marketSource,"Automation::merchantCarryCapacity(\$targettribe,\$building->getTypeLevel(28,(int)\$infoarray['vref']))") !== false,
	'la capacidad sale de la tribu del dueño y de la Oficina de la ALDEA VENDEDORA');
check(strpos($marketSource,"(int)\$infoarray['gamt'],") !== false,
	'se calcula sobre lo ofrecido, que es lo que va a viajar');
check(strpos($marketSource,"\$infoarray['merchant']") === false,
	'la columna congelada ya no se usa para despachar la venta');
// El comprador sigue contando con SU capacidad: son sus mercaderes los que van.
check(strpos($marketSource,'$reqMerc = $this->requiredMerchants((int)$infoarray[\'wamt\']);') !== false,
	'el comprador sigue calculando lo suyo con su propia Oficina');
// La venta no se puede bloquear por el estado del vendedor: si le catapultaron la Oficina
// después de publicar, sale igual. Rechazar convertiría catapultear una Oficina ajena en
// una forma de congelarle todas las ventas al vendedor.
check(preg_match('/\$hisMerc <= 0\) \{\s*\n\s*\$hisMerc = 1;/',$marketSource) === 1,
	'la venta nunca sale con 0 mercaderes: hay un piso de 1');
$acceptStart = strpos($marketSource,'private function acceptOffer(');
$acceptBody = substr($marketSource,$acceptStart,strpos($marketSource,'private function cancelOffer(') - $acceptStart);
check(substr_count($acceptBody,"marketFailure('merchants'") === 2,
	'los únicos rechazos por mercaderes siguen siendo los dos del comprador');
check(strpos($acceptBody,'$targettribe = $database->getUserField($sellerOwner,"tribe",0);') !== false,
	'la tribu del vendedor se resuelve una sola vez, del dueño ya validado');

// ---------------------------------------------------------------------------
section('D. El escenario del jugador, de punta a punta');
// ---------------------------------------------------------------------------
// Un galo publica 3000 de madera sin Oficina de comercio: 4 mercaderes.
$oferta = 3000;
$alPublicar = Automation::merchantsRequired($oferta,$sinOficina);
check($alPublicar === 4,'al publicar sin Oficina, la oferta necesita 4 mercaderes');

// Sube la Oficina a 10 mientras la oferta espera. Lo apartado baja YA, sin cancelar nada.
$database->offers = array(101 => array($oferta));
$database->traveling = array();
check(Automation::merchantsBusy(101,$oficina10) === 2,
	'apenas sube la Oficina, la oferta pasa a apartar 2 y los otros 2 quedan libres');

// Y cuando alguien se la compra, el envío sale con esos 2, no con los 4 del día uno.
$alVenderse = Automation::merchantsRequired($oferta,$oficina10);
check($alVenderse === 2,'al concretarse la venta el envío sale con 2 mercaderes');
check($alVenderse === Automation::merchantsBusy(101,$oficina10),
	'lo que se despacha es exactamente lo que estaba apartado (no hay salto al vender)');
check($alVenderse < $alPublicar,'mejorar la Oficina abarata una oferta ya publicada');

// El caso opuesto: le catapultan la Oficina y la venta necesita MÁS de lo que apartó.
$conOficinaDestruida = Automation::merchantsRequired($oferta,$sinOficina);
check($conOficinaDestruida === 4,'si le destruyen la Oficina, esa misma oferta vuelve a pedir 4');
check(Automation::merchantsBusy(101,$sinOficina) === 4,
	'y el contador lo refleja al instante, sin esperar a que se venda');

// Con el bono romano oficial, un romano con Oficina 20 lleva 2500 por mercader.
check(Automation::merchantsRequired(5000,Automation::merchantCarryCapacity(1,20)) === 2,
	'un romano con Oficina 20 despacha 5000 con 2 mercaderes (2500 cada uno)');
check(Automation::merchantsRequired(5000,Automation::merchantCarryCapacity(1,0)) === 10,
	'ese mismo romano, sin Oficina, necesitaba 10');

// ---------------------------------------------------------------------------
section('E. market.merchant queda como dato histórico y nadie lo lee');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$offerId = $database->addMarket($village->wid,$gtype,$gamt,$wtype,$wamt,$time,$alliance,$reqMerc,0);') !== false,
	'la columna se sigue escribiendo al publicar (no hace falta migración)');
check(strpos($marketSource,"&& (int)\$offer['merchant'] > 0") === false,
	'validOffer() ya no exige la columna: una oferta vale por lo que ofrece');
check(strpos($marketSource,"isset(\$offer['vref'],\$offer['gtype'],\$offer['gamt'],\$offer['wtype'],\$offer['wamt'],\$offer['accept'],\$offer['alliance'],\$offer['maxtime'])") !== false,
	'validOffer() sigue exigiendo el resto de las columnas');
check(strpos($sellTpl,"\$offer['merchant']") === false,
	'la pestaña "Mis ofertas" ya no imprime el número congelado');
check(strpos($sellTpl,'$market->offerMerchants($offer)') !== false,
	'la pestaña muestra la reserva de hoy, que es la que de verdad se ocupa');
check(strpos($marketSource,'public function offerMerchants($offer)') !== false
	&& strpos($marketSource,"return \$this->merchantsFor((int)\$offer['gamt']);") !== false,
	'offerMerchants() delega en la misma regla de redondeo que el servidor');

// Lo que muestra la pestaña y lo que aparta la reserva tienen que ser el mismo número
// para cualquier oferta: son dos caminos distintos hasta merchantsRequired().
$coinciden = true;
foreach(array(1,750,751,1500,3000,12345) as $monto) {
	$database->offers = array(101 => array($monto));
	foreach(array($sinOficina,$oficina10,$oficina20) as $capacidad) {
		if(Automation::offerMerchantsCommitted(101,$capacidad) !== Automation::merchantsRequired($monto,$capacidad)) {
			$coinciden = false;
		}
	}
}
check($coinciden,'la reserva de una oferta es exactamente merchantsRequired() de lo ofrecido');

// ---------------------------------------------------------------------------
section('F. Un solo helper para "ocupado" en todas las pantallas');
// ---------------------------------------------------------------------------
check(strpos($marketSource,'$this->used = Automation::merchantsBusy($village->wid,$this->maxcarry);') !== false,
	'el contador del Mercado usa merchantsBusy()');
check(strpos($marketSource,'$currentMerchantAvail = max(0,$this->merchant - Automation::merchantsBusy($village->wid,$this->maxcarry));') !== false,
	'la relectura al aceptar una oferta usa el mismo helper');
check(strpos($automationSource,'self::marketMerchants($this->getTypeLevel(17, $from)) - self::merchantsBusy($from, $maxcarry2)') !== false,
	'el envío automático (rutas y envíos encadenados) usa el mismo helper');
foreach(array('Templates/dorf3/1.tpl','Templates/dorf3/2.tpl') as $resumen) {
	$source = file_get_contents(dirname(__DIR__).'/'.$resumen);
	check(strpos($source,'Automation::merchantsBusy($vid,Automation::merchantCarryCapacity($session->tribe,$building->getTypeLevel(28,$vid)))') !== false,
		$resumen.' descuenta lo mismo que el Mercado, con la Oficina de esa aldea');
}
// La capacidad tiene que estar calculada antes de contar: es un parámetro obligatorio.
check(strpos($marketSource,'$this->maxcarry = Automation::merchantCarryCapacity($session->tribe,$building->getTypeLevel(28));') !== false,
	'loadMarket() calcula la capacidad antes de contar los ocupados');
check(strpos($marketSource,'$this->maxcarry = ') < strpos($marketSource,'$this->used = '),
	'y lo hace en ese orden, no al revés');

echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Market offer merchant checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
