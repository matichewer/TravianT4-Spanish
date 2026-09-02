<?php
/**
 * Auditoría de la Oficina de comercio (gid 28).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_trade_office.php
 *
 * El edificio sólo hace una cosa —subir la capacidad de carga de cada mercader un 10%
 * por nivel—, así que casi todo lo que puede fallar está en cómo se calcula y en quién
 * usa ese número:
 *
 *   A. La tabla bid28 (20 niveles, 110%..300%) es la fuente del bono.
 *   B. merchantCarryCapacity() es la única fórmula: base por tribu, TRADER_CAPACITY y
 *      el bono, con la división por 100 al final. Con la división antes de multiplicar,
 *      un galo con Oficina 13 daba 1724.99999999999977 en vez de 1725 y el envío de
 *      capacidad justa se rechazaba por "mercaderes insuficientes".
 *   C. Un nivel fuera de la tabla (editado desde el panel de administración) se recorta
 *      en vez de dejar la capacidad en 0, que dejaba el Mercado entero sin poder enviar.
 *   D. Las rutas comerciales reservan mercaderes según la capacidad de hoy: subir la
 *      Oficina tiene que liberar los que sobran.
 *   E. Una sola Oficina por aldea, y con Mercado 20 + Establo 10 como requisito.
 *   F. Las vistas del Mercado y del propio edificio no repiten la fórmula.
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

require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
if(!defined('TRADER_CAPACITY')) {
	define('TRADER_CAPACITY','1');
}
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP',true);
require_once dirname(__DIR__).'/GameEngine/Automation.php';

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$marketSource = file_get_contents(dirname(__DIR__).'/GameEngine/Market.php');
$buildingSource = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');

// ---------------------------------------------------------------------------
section('A. Tabla del edificio');
// ---------------------------------------------------------------------------
check(is_array($bid28) && count($bid28) === 20,'bid28 tiene los 20 niveles del edificio');
check(isset($bid28[1]['attri']) && $bid28[1]['attri'] == 110,'el nivel 1 da 110% de capacidad');
check(isset($bid28[20]['attri']) && $bid28[20]['attri'] == 300,'el nivel 20 da 300% de capacidad');
$progresivo = true;
for($lv = 1; $lv <= 20; $lv++) {
	if(!isset($bid28[$lv]['attri']) || $bid28[$lv]['attri'] != 100 + 10 * $lv) {
		$progresivo = false;
	}
}
check($progresivo,'cada nivel suma exactamente 10 puntos porcentuales (columna oficial de galos y teutones)');

// La tabla guarda UNA sola columna, la de galos y teutones. La romana (120..500) se
// deriva en tradeOfficeBonusPercent(): que bid28 sea la columna baja es parte del trato.
check(Automation::tradeOfficeBonusPercent(3,20) == 300 && Automation::tradeOfficeBonusPercent(2,20) == 300,
	'galos y teutones leen la tabla tal cual');
check(Automation::tradeOfficeBonusPercent(1,20) == 500,'el romano dobla el bono, no la capacidad');
check(Automation::tradeOfficeBonusPercent(1,0) == 100,'sin Oficina el romano no tiene ningun bono (100%, no 200%)');

// ---------------------------------------------------------------------------
section('B. merchantCarryCapacity(): una sola fórmula y sin coma flotante');
// ---------------------------------------------------------------------------
check(Automation::merchantCarryCapacity(1,0) == 500,'romano sin Oficina: 500 por mercader');
check(Automation::merchantCarryCapacity(2,0) == 1000,'teutón sin Oficina: 1000 por mercader');
check(Automation::merchantCarryCapacity(3,0) == 750,'galo sin Oficina: 750 por mercader');

// El caso que rompía: 750 * (230/100) = 1724.99999999999977, 750 * 230 / 100 = 1725.
$capacidadesEnteras = true;
$detalle = '';
foreach(array(1,2,3,4,5,6,7) as $tribu) {
	for($lv = 0; $lv <= 20; $lv++) {
		$capacidad = Automation::merchantCarryCapacity($tribu,$lv);
		if($capacidad != floor($capacidad)) {
			$capacidadesEnteras = false;
			$detalle = ' (tribu '.$tribu.', nivel '.$lv.' => '.sprintf('%.17f',$capacidad).')';
		}
	}
}
check($capacidadesEnteras,'ninguna combinación de tribu y nivel produce una capacidad fraccionaria'.$detalle);
check(Automation::merchantCarryCapacity(3,13) === 1725.0 || Automation::merchantCarryCapacity(3,13) == 1725,
	'galo con Oficina 13: exactamente 1725, no 1724.999...');
check(Automation::merchantCarryCapacity(3,20) == 2250,'galo con Oficina 20: 2250 (750 x 300%)');
check(Automation::merchantCarryCapacity(2,20) == 3000,'teutón con Oficina 20: 3000');

// --- Bono romano: 20 puntos por nivel (oficial desde T3.5) --------------------------
// La tabla oficial del wiki, columna por columna. Este repo aplicaba 10%/nivel a las tres
// tribus, con lo que el romano llegaba a 1500 en vez de 2500 y la Oficina no le devolvia
// nada de lo que el oficial le da para compensar su comienzo debil.
$oficial = array(
	// nivel => array(romano, galo, teuton)
	1  => array(600,825,1100),
	2  => array(700,900,1200),
	3  => array(800,975,1300),
	4  => array(900,1050,1400),
	5  => array(1000,1125,1500),
	6  => array(1100,1200,1600),
	7  => array(1200,1275,1700),
	8  => array(1300,1350,1800),
	9  => array(1400,1425,1900),
	10 => array(1500,1500,2000),
	11 => array(1600,1575,2100),
	12 => array(1700,1650,2200),
	13 => array(1800,1725,2300),
	14 => array(1900,1800,2400),
	15 => array(2000,1875,2500),
	16 => array(2100,1950,2600),
	17 => array(2200,2025,2700),
	18 => array(2300,2100,2800),
	19 => array(2400,2175,2900),
	20 => array(2500,2250,3000),
);
$tablaOficial = true;
$primerFallo = '';
foreach($oficial as $lv => $esperado) {
	foreach(array(1 => $esperado[0], 3 => $esperado[1], 2 => $esperado[2]) as $tribu => $capacidad) {
		if(Automation::merchantCarryCapacity($tribu,$lv) != $capacidad) {
			$tablaOficial = false;
			if($primerFallo === '') {
				$primerFallo = ' (tribu '.$tribu.' nivel '.$lv.': '
					.Automation::merchantCarryCapacity($tribu,$lv).' en vez de '.$capacidad.')';
			}
		}
	}
}
check($tablaOficial,'los 20 niveles dan la capacidad oficial para las tres tribus'.$primerFallo);
check(Automation::merchantCarryCapacity(1,20) == 2500,
	'romano con Oficina 20: 2500 (500 x 500%), no 1500');
check(Automation::merchantCarryCapacity(1,20) > Automation::merchantCarryCapacity(3,20)
	&& Automation::merchantCarryCapacity(1,20) < Automation::merchantCarryCapacity(2,20),
	'con la Oficina al maximo el romano queda en el medio (esa es la razon de ser del bono)');
check(Automation::merchantCarryCapacity(1,0) == 500,
	'sin Oficina el romano sigue siendo el peor: el bono dobla el porcentaje, no la base');
// El bono es de la tribu del DUENO de la aldea, no del jugador que mira la pantalla.
check(Automation::merchantCarryCapacity(1,10) == 1500 && Automation::merchantCarryCapacity(3,10) == 1500,
	'con Oficina 10 el romano alcanza al galo (1500 los dos)');

// El borde que fallaba de punta a punta: la carga justa de todos los mercaderes.
$carry = Automation::merchantCarryCapacity(3,13);
check(Automation::merchantsRequired(20 * $carry,$carry) === 20,
	'la carga exacta de 20 mercaderes sigue necesitando 20, no 21');
check(20 * $carry >= 34500,'el tope que valida el formulario no queda por debajo de la carga real');
check(Automation::merchantsRequired($carry,$carry) === 1,'un mercader lleno pide un mercader');
check(Automation::merchantsRequired($carry + 1,$carry) === 2,'un recurso más pide el segundo mercader');
check(Automation::merchantsRequired(0,$carry) === 0,'sin recursos no hace falta ningún mercader');
check(Automation::merchantsRequired(-5,$carry) === 0,'un total negativo no exige mercaderes');
check(Automation::merchantsRequired(100,0) === 0,'sin capacidad no se calcula ningún mercader');

check(strpos($automationSource,'$capacity * self::tradeOfficeBonusPercent($tribe, $tradeOfficeLevel) / 100') !== false,
	'el bono se aplica multiplicando primero y dividiendo por 100 al final');
check(substr_count($marketSource,'$bid28') === 0,
	'Market ya no recalcula la capacidad por su cuenta');
$sinFormula = preg_replace('/public static function tradeOfficeBonusPercent\(.*?\n    \}/s','',$automationSource);
check(strpos($sinFormula,'$bid28') === false,
	'Automation lee bid28 sólo dentro de tradeOfficeBonusPercent()');

// ---------------------------------------------------------------------------
section('C. Niveles fuera de la tabla');
// ---------------------------------------------------------------------------
check(Automation::merchantCarryCapacity(3,25) == Automation::merchantCarryCapacity(3,20),
	'un nivel por encima de 20 (editado a mano) se recorta al máximo, no anula la capacidad');
check(Automation::merchantCarryCapacity(3,25) > 0,
	'la capacidad nunca queda en 0: con 0 el Mercado no puede enviar nada');
check(Automation::merchantCarryCapacity(3,-3) == 750,
	'un nivel negativo se trata como "sin Oficina"');

$oficinaTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/28.tpl');
check(strpos($oficinaTpl,"isset(\$bid28[\$tradeOfficeLevel]['attri'])") !== false,
	'la ficha del edificio no imprime una celda vacía cuando el nivel no está en la tabla');
check(strpos($oficinaTpl,'isset($bid28[$tradeOfficeLevel+1][\'attri\'])') !== false,
	'la fila del nivel siguiente sólo se dibuja si ese nivel existe');

// ---------------------------------------------------------------------------
section('D. Las rutas reservan según la capacidad de hoy');
// ---------------------------------------------------------------------------
check(strpos($automationSource,'public static function routeMerchantsCommitted(') !== false,
	'existe routeMerchantsCommitted(), que recalcula la reserva de cada ruta');
check(strpos($automationSource,'$database->getTradeRoutesFrom($vid, $excludeRouteIds)') !== false,
	'la reserva se calcula sobre los recursos de la ruta, no sobre la columna merchant');
check(strpos($marketSource,'$this->routeReserved = $this->routeMerchantsCommitted();') !== false,
	'el Mercado recalcula lo comprometido por rutas con la capacidad de hoy (para mostrarlo,'
	.' no para descontarlo: los mercaderes se ocupan recien cuando la ruta sale)');

// Una ruta de 7500 creada sin Oficina reservaba 10 mercaderes para siempre.
check(Automation::merchantsRequired(7500,Automation::merchantCarryCapacity(3,0)) === 10,
	'sin Oficina, una ruta de 7500 ocupa 10 mercaderes');
check(Automation::merchantsRequired(7500,Automation::merchantCarryCapacity(3,20)) === 4,
	'con la Oficina a 20, esa misma ruta pasa a ocupar 4');

// Las ofertas publicadas siguen la misma regla que las rutas (ver check_market_offer_merchants.php).
check(strpos($automationSource,'public static function offerMerchantsCommitted(') !== false,
	'existe offerMerchantsCommitted(), que recalcula lo que aparta cada oferta publicada');
check(strpos($automationSource,'$database->openOfferAmounts($vid)') !== false,
	'la reserva de una oferta se calcula sobre lo ofrecido, no sobre la columna merchant');

$rutasTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_4.tpl');
check(strpos($rutasTpl,'$market->routeMerchants($firstRoute)') !== false,
	'el listado de rutas muestra la reserva de hoy');
check(strpos($rutasTpl,"\$route['merchant']") === false && strpos($rutasTpl,"\$firstRoute['merchant']") === false,
	'el listado ya no imprime la columna congelada del día de creación');
check(strpos($rutasTpl,'$market->routeMerchantsCommitted()') !== false,
	'el formulario de creación cuenta los mercaderes libres con la reserva recalculada');
check(strpos($rutasTpl,'(int)$market->maxcarry') === false,
	'la capacidad por mercader no se trunca con (int) al mostrarla');

// El resumen de aldeas y el Mercado tienen que dar el MISMO número de mercaderes libres.
// El resumen restaba además lo comprometido por rutas, así que la misma aldea decía
// "12/20" en dorf3 y "20/20" en el Mercado; las rutas no ocupan a nadie hasta que salen.
foreach(array('Templates/dorf3/1.tpl','Templates/dorf3/2.tpl') as $resumen) {
	$source = file_get_contents(dirname(__DIR__).'/'.$resumen);
	check(strpos($source,'Automation::routeMerchantsCommitted(') === false,
		$resumen.' no descuenta las rutas: usa la misma definición de "ocupado" que el Mercado');
	check(strpos($source,'$totalmerchants - Automation::merchantsBusy($vid,Automation::merchantCarryCapacity($session->tribe,$building->getTypeLevel(28,$vid)))') !== false,
		$resumen.' usa la misma definición de "ocupado" que el Mercado, con la capacidad de esta aldea');
}

// ---------------------------------------------------------------------------
section('E. Requisitos y unicidad');
// ---------------------------------------------------------------------------
check(preg_match('/private static \$singlePerVillage = array\(\s*([0-9,\s]+)\)/',$buildingSource,$unicos) === 1
	&& in_array(28,array_map('intval',explode(',',preg_replace('/\s+/','',$unicos[1]))),true),
	'la Oficina de comercio está en la lista de edificios únicos por aldea');
check(strpos($buildingSource,'if(!$this->isSingleBuildingAllowed($id)) {') !== false,
	'meetRequirement() corta por unicidad antes de mirar los requisitos (ver check_unique_buildings.php)');
$requisitosOficina = buildingLevelRequirements(28);
check(is_array($requisitosOficina) && count($requisitosOficina) === 2
	&& isset($requisitosOficina[17]) && (int)$requisitosOficina[17] === 20
	&& isset($requisitosOficina[20]) && (int)$requisitosOficina[20] === 10,
	'meetRequirement(28) sigue exigiendo Mercado 20 y Establo 10');

$disponiblesTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');
check(preg_match('/if\(\$building->meetRequirement\(28\)[^\n]*\n\s*include\("avaliable\/tradeoffice\.tpl"\)/',$disponiblesTpl) === 1,
	'la lista de construcciones ofrece la Oficina con el mismo requisito que el servidor');

$prontoTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/soon/tradeoffice.tpl');
check(strpos($prontoTpl,'$building->requirementsHtml(28)') !== false,
	'la ficha de "próximamente" anuncia los requisitos que pide el motor, sin copiarlos');

// ---------------------------------------------------------------------------
section('F. Vistas del Mercado sin copias de la fórmula');
// ---------------------------------------------------------------------------
$mercadoTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17.tpl');
check(strpos($mercadoTpl,'$market->merchantsFor(array_sum($resource))') !== false,
	'la confirmación del envío pide los mercaderes al Mercado en vez de repetir el ceil');
check(strpos($mercadoTpl,'/$market->maxcarry') === false,
	'la vista del Mercado ya no divide por maxcarry a mano');

$ofertasTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_1.tpl');
check(strpos($ofertasTpl,"\$market->merchantsFor((int)\$market->onsale[\$i]['wamt'])") !== false,
	'la lista de ofertas usa la misma regla que el servidor al aceptar');

$nombresCoherentes = true;
foreach(array('Templates/Build/28.tpl','Templates/Manual/428.tpl','Templates/Build/avaliable/tradeoffice.tpl','Templates/Build/soon/tradeoffice.tpl') as $vista) {
	$source = file_get_contents(dirname(__DIR__).'/'.$vista);
	if(stripos($source,'centro comercial') !== false) {
		$nombresCoherentes = false;
	}
}
check($nombresCoherentes,'el edificio se llama "Oficina de comercio" en todas sus vistas');

echo "\n";
if(count($GLOBALS['fails']) > 0) {
	echo "Trade office checks FAILED (".count($GLOBALS['fails'])." de ".$GLOBALS['checks'].").\n";
	exit(1);
}
echo "Trade office checks passed (".$GLOBALS['checks']." comprobaciones).\n";
exit(0);
