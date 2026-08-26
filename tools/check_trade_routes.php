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
check(strpos($dbSource,"SELECT id, wid, wood, clay, iron, crop, start, start_minute, deliveries FROM \" . TB_PREFIX . \"route WHERE `from` = \$vid") !== false,
	'getTradeRoutesFrom() devuelve destino, recursos y horario (hora y minuto) de cada ruta de esa aldea de origen');
check(strpos($dbSource,'AND id NOT IN (') !== false,
	'getTradeRoutesFrom() puede excluir varias filas a la vez (todo el grupo que se esta editando, no solo una)');
check(strpos($dbSource,'SELECT SUM(merchant) FROM') === false,
	'ya nadie suma la columna merchant guardada: quedaba congelada en la capacidad del día de creación');

check(strpos($dbSource,'function deleteTradeRoute(') !== false,'sigue existiendo deleteTradeRoute() (borrado sin dueño, para limpieza del sistema)');
// Una salida sale o no sale: ya no se reprograma unos minutos después. La aldea produce
// todo el tiempo, así que "sin recursos" no dura ni un minuto, y lo que terminaba pasando
// es que la ruta salía tarde cargando 1 unidad de lo primero que se produjera, gastando
// los mercaderes de un viaje entero y dando la salida por cumplida igual.
check(strpos($dbSource,'function retryTradeRoute(') === false,
	'ya no existe retryTradeRoute(): una salida que no puede ejecutarse no se reprograma');

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
check(strpos($tradeRouteBody,'$status = $this->sendResource2(') !== false
	&& strpos($tradeRouteBody,'if($status !== self::SEND_OK) {') !== false
	&& strpos($tradeRouteBody,'$this->reportFailedDeparture(') !== false,
	'una salida que no se ejecuta deja un informe en vez de desaparecer en silencio');
check(strpos($tradeRouteBody,'retryTradeRoute(') === false,
	'no se reprograma la salida: si a su hora no hay mercaderes, esa salida no se ejecuta');
// La fila se reclama (timestamp adelantado a mañana) ANTES de repartir. Si el reparto
// lanza —una dependencia que el proceso que corre esto no cargó, por ejemplo—, sin este
// catch el envío del día desaparecía en silencio y además se cortaba el barrido para el
// resto de las rutas. Es exactamente lo que pasaba con el worker dedicado.
check(preg_match('/try\s*\{\s*\$status = \$this->sendResource2\(.*?\}\s*catch\(Throwable \$e\)\s*\{/s',$tradeRouteBody) === 1,
	'un error fatal repartiendo una ruta se atrapa en vez de cortar el barrido de las demás');

// ---------------------------------------------------------------------------
section('D. Market::procTradeRoutes(): pico de superposición, no la suma ciega');
// ---------------------------------------------------------------------------
check(strpos($marketSource,"Automation::routeScheduleEntries(\$village->wid,\$this->maxcarry,\$session->tribe,\$originalRouteIds)") !== false,
	'procTradeRoutes() arma el calendario con las otras rutas de la misma aldea (excluyendo TODO el grupo que se edita, no solo una fila)');
check(strpos($marketSource,'$peakDemand = Automation::peakConcurrentMerchants($peakEntries);') !== false,
	'la validación usa el pico de mercaderes simultáneos calculado por Automation, no una suma local');
check(strpos($marketSource,'if($peakDemand > $this->merchant)') !== false,
	'se valida contra la capacidad total del Mercado, ya que el pico ya tiene en cuenta las rutas existentes');
check(strpos($marketSource,'$reqMerc > $this->merchant)') === false,
	'ya no queda el chequeo viejo contra la capacidad total sin descontar rutas existentes');
check(strpos($marketSource,"\$originalRouteIds = array();") !== false
	&& strpos($marketSource,"if(\$postAction === 'editRoute' && isset(\$post['original_routeid'])") !== false,
	'al editar, los ids del grupo completo se calculan antes de validar para poder excluirlos');
check(strpos($automationSource,'public static function routeTripSeconds(') !== false,
	'existe routeTripSeconds(), que calcula cuánto están fuera los mercaderes de una salida');
check(strpos($automationSource,'$roundTrip = 2 * (int)$generator->procDistanceTime($toCoor, $fromCoor, $tribe, 0);') !== false,
	'el viaje se calcula con la misma fórmula que usa el envío manual, no una copia con otro número');
// Con "envíos x3" los mercaderes vuelven y salen de nuevo sin liberar el cupo: el cupo
// está tomado 3 viajes de ida y vuelta, no uno. Contando uno solo, el guardado aceptaba
// rutas cuyas salidas sí se pisan de verdad, y el envío se caía recién a la hora de salir.
check(strpos($automationSource,'return $roundTrip * max(1, min(3, (int)$deliveries));') !== false,
	'el tiempo ocupado tiene en cuenta los envíos encadenados (x1/x2/x3), no solo un viaje');
check(strpos($marketSource,'private function merchantRoundTripSeconds(') === false,
	'Market ya no tiene su propia copia del cálculo de viaje');

// ---------------------------------------------------------------------------
section('E. Vista 17_4.tpl: el listado solo muestra rutas de ESTA aldea');
// ---------------------------------------------------------------------------
// Antes el listado traia TODAS las rutas del jugador (de cualquiera de sus aldeas) y
// cada fila decidia si mostrar Editar/Eliminar o "gestionar desde esa aldea" segun de
// donde salia. Ahora el filtro es mas simple y mas fuerte: se trae solo lo que sale de
// esta aldea, asi que cada fila es siempre propia y editable/borrable sin condicionales.
$tplSource = file_get_contents(dirname(__DIR__).'/Templates/Build/17_4.tpl');
check(strpos($tplSource,'$routes = $database->getTradeRoute($session->uid,$village->wid);') !== false,
	'la vista pide solo las rutas que salen de la aldea actual, no todas las del jugador');
check(strpos($tplSource,'$isOwnVillage') === false && strpos($tplSource,'gestionar desde esa aldea') === false,
	'ya no hace falta distinguir "propia vs ajena" por fila: el filtro de la consulta ya lo garantiza');
check(preg_match('/<a href="build\.php\?id=<\?php echo \$id; \?>&amp;t=4&amp;action=editRoute<\?php echo \$groupIdsQuery; \?>">Editar<\/a>/',$tplSource) === 1,
	'el enlace de editar aparece siempre (sin condicional), porque toda fila listada es de esta aldea');
check(strpos($tplSource,'action=delRoute<?php echo $groupIdsQuery; ?>') !== false,
	'el enlace de eliminar borra TODOS los horarios del grupo, no solo uno');
check(strpos($tplSource,"onclick=\"return confirm('¿Eliminar esta ruta comercial") !== false,
	'borrar un grupo con varios horarios pide confirmación (antes borraba un solo horario, ahora puede borrar varios)');
check(strpos($dbSource,'function getTradeRoute($uid,$fromVid) {') !== false
	&& strpos($dbSource,'where uid = $uid AND `from` = $fromVid') !== false,
	'getTradeRoute() filtra por aldea de origen a nivel de consulta, no solo en la vista');

// ---------------------------------------------------------------------------
section('E2. Agrupar varios horarios en una sola fila del listado');
// ---------------------------------------------------------------------------
// Antes, una ruta con N horarios (N filas en la base) aparecia como N filas identicas
// en el listado. Ahora se agrupan por destino+recursos+envios (el origen ya no hace
// falta en la firma: la consulta solo trae rutas de esta aldea, asi que siempre es el
// mismo), que es lo que procTradeRoutes() usa para decidir que filas van al mismo grupo.
check(strpos($tplSource,"\$groupKey = \$route['wid'].'|'.\$route['wood'].'|'.\$route['clay'].'|'.\$route['iron'].'|'.\$route['crop'].'|'.\$route['deliveries'];") !== false,
	'el agrupado usa la misma firma (destino+recursos+envios) que define un grupo al guardar');
check(strpos($tplSource,"\$scheduleSummary = \$interval === 3600 ? 'Cada hora' : 'Cada '.\$routeIntervalLabel(\$interval);") !== false,
	'un calendario que cubre el dia con intervalos regulares se resume sin alargar la fila');
check(strpos($tplSource,"\$scheduleTooltip = 'Horarios: '.implode(', ',\$scheduleTimes);") !== false
	&& strpos($tplSource,'htmlspecialchars($scheduleTooltip,ENT_QUOTES') !== false,
	'el resumen conserva en un tooltip el detalle de todos los horarios del grupo');
check(strpos($tplSource,".' y '.(\$scheduleCount - 3).' más'") !== false,
	'un grupo numeroso sin patron muestra solo tres horarios y la cantidad restante');
check(strpos($tplSource,'function($a,$b){') !== false && substr_count($tplSource,'<=>') >= 2,
	'tanto los grupos como los horarios dentro de cada grupo se ordenan de forma estable, no en el orden en que llegaron de la base');

check(strpos($tplSource,"\$requestedRouteIds = array_values(array_unique(\$requestedRouteIds));") !== false,
	'la vista arma la lista de ids pedidos para editar antes de validar el grupo');
check(strpos($tplSource,'$edited_routes = $database->getTradeRoutesByIds($requestedRouteIds)') !== false || strpos($tplSource,'getTradeRoutesByIds($requestedRouteIds)') !== false,
	'la vista trae TODAS las filas pedidas de una sola vez para validar el grupo completo');
check(strpos($tplSource,'count($edited_routes) === count($requestedRouteIds)') !== false,
	'si algun id pedido no existe (o no es del jugador), el grupo entero se rechaza, no se edita a medias');
check(strpos($tplSource,"(int)\$editedRouteRow['uid'] !== (int)\$session->uid || (int)\$editedRouteRow['from'] !== (int)\$village->wid") !== false,
	'CADA fila del grupo se revisa contra el dueño y la aldea, no solo la primera');

// ---------------------------------------------------------------------------
section('F. Worker y calendario de ejecución');
// ---------------------------------------------------------------------------
$timezone = date_default_timezone_get();
date_default_timezone_set('America/Argentina/Buenos_Aires');
$morning = strtotime('2026-08-11 09:30:00');
$afterStart = strtotime('2026-08-11 10:30:00');
check(Automation::nextTradeRouteTimestamp(10,0,$morning) === strtotime('2026-08-11 10:00:00'),
	'antes del horario devuelve la ejecución del mismo día');
check(Automation::nextTradeRouteTimestamp(10,0,$afterStart) === strtotime('2026-08-12 10:00:00'),
	'después del horario devuelve directamente el día siguiente');
check(Automation::nextTradeRouteTimestamp(10,0,strtotime('2026-08-08 10:00:00')) === strtotime('2026-08-09 10:00:00'),
	'el cálculo siempre produce una única próxima ejecución futura');
// El minuto ahora es significativo: dos rutas a la misma hora pero distinto minuto no
// pueden colapsar al mismo horario de salida.
check(Automation::nextTradeRouteTimestamp(10,15,$morning) === strtotime('2026-08-11 10:15:00'),
	'el minuto declarado se respeta en el cálculo, no solo la hora');
check(Automation::nextTradeRouteTimestamp(10,15,strtotime('2026-08-11 10:15:00')) === strtotime('2026-08-12 10:15:00'),
	'el segundo exacto del minuto declarado ya cuenta como vencido');
date_default_timezone_set($timezone);

check(strpos($automationSource,'if($marketOnly) {') !== false
	&& preg_match('/if\(\$marketOnly\)\s*\{\s*\$this->marketComplete\(\);\s*\$this->TradeRoute\(\);/s',$automationSource) === 1,
	'el modo usado por market_worker procesa entregas y también dispara rutas');
check(strpos($tradeRouteBody,'timestamp <= $time ORDER BY timestamp ASC') !== false,
	'el segundo exacto del horario ya cuenta como vencido y se respeta el orden cronológico');

// ---------------------------------------------------------------------------
section('G. Los mercaderes de una ruta no se ocupan hasta que sale');
// ---------------------------------------------------------------------------
// Una ruta reservaba sus mercaderes las 24 horas: el Mercado mostraba "1/16" sin un solo
// movimiento a la vista, no se podia enviar, vender ni comprar, y mientras la ruta
// viajaba los mismos mercaderes se contaban dos veces (la reserva + el envio real).
check(strpos($marketSource,'$this->used = Automation::merchantsBusy($village->wid,$this->maxcarry);') !== false,
	'los mercaderes ocupados son solo los que estan de viaje o esperando en una oferta');
check(strpos($marketSource,'+ $this->routeMerchantsCommitted()') === false,
	'ya no queda la reserva permanente de rutas sumada a los mercaderes ocupados');
check(strpos($marketSource,'$this->routeReserved = $this->routeMerchantsCommitted();') !== false,
	'lo comprometido por rutas se sigue calculando, pero aparte, para poder mostrarlo');
check(strpos($marketSource,'if($peakDemand > $this->merchant)') !== false,
	'crear una ruta sigue exigiendo que las salidas de la aldea entren en el Mercado en su momento de mayor superposición');
$merchantsTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_merchants.tpl');
check(strpos($merchantsTpl,'$market->routeReserved') !== false
	&& strpos($merchantsTpl,'a la vez en rutas comerciales') !== false
	&& strpos($merchantsTpl,'$market->routeDepartureHours()') !== false,
	'el contador del Mercado explica cuantos mercaderes viajan a la vez en rutas y a que hora salen');
check(strpos($marketSource,'public function routeDepartureHours()') !== false,
	'existe el horario de salida de las rutas para poder mostrarlo junto al contador');
check(strpos($tplSource,'$market->routeMerchants($firstRoute)') !== false,
	'el listado muestra los mercaderes que la ruta ocupa hoy, no los del día en que se creó');
check(strpos($automationSource,'TRADE_ROUTE_RETRY_DELAY') === false,
	'no queda rastro de la espera de reintento');

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
section('I. Minutos y varios horarios por guardado');
// ---------------------------------------------------------------------------
// Antes solo se guardaba la hora (0-23); ahora tambien el minuto, y un mismo guardado
// puede declarar varios horarios (schedule_hour[]/schedule_minute[]), cada uno su propia
// fila en la tabla, todos con el mismo destino/recursos/envios.
check(strpos($dbSource,'function createTradeRoute($uid,$wid,$from,$r1,$r2,$r3,$r4,$start,$startMinute,$deliveries,$merchant,$time)') !== false,
	'createTradeRoute() recibe el minuto ademas de la hora');
check(strpos($dbSource,'$startMinute < 0 || $startMinute > 59') !== false,
	'createTradeRoute() valida el minuto contra el rango 0-59');
check(strpos($dbSource,'function updateTradeRouteOwned($id,$uid,$from,$wid,$r1,$r2,$r3,$r4,$start,$startMinute,$deliveries,$merchant,$time)') !== false,
	'updateTradeRouteOwned() recibe destino y minuto, para tener paridad completa con crear');
check(strpos($dbSource,'SET wid = $wid, wood = $r1') !== false,
	'editar una ruta ahora puede cambiar tambien la aldea de destino');

check(strpos($marketSource,'const MAX_ROUTE_SCHEDULES') !== false,
	'existe un tope duro de horarios por guardado');
check(strpos($marketSource,'private function parseRouteSchedules(') !== false,
	'existe parseRouteSchedules(), que valida los pares hora/minuto declarados');
check(strpos($marketSource,"count(\$hours) !== count(\$minutes)") !== false,
	'parseRouteSchedules() rechaza arrays de hora y minuto desparejados');
check(strpos($marketSource,'isset($originalRouteIds[$scheduleIndex])') !== false,
	'al editar, cada horario declarado actualiza la fila original en su misma posicion (o crea una si no hay); no hardcodea "solo el primero"');
check(strpos($marketSource,'for($i = count($schedules); $i < count($originalRouteIds); $i++)') !== false,
	'los ids originales que sobran (horarios que el jugador quito) se borran, no quedan huerfanos');
check(strpos($marketSource,'Automation::nextTradeRouteTimestamp($schedule[\'hour\'],$schedule[\'minute\'])') !== false,
	'el horario de cada ruta se calcula con la unica implementacion de Automation, no una copia local');
check(strpos($marketSource,"strtotime('today '.sprintf") === false,
	'ya no queda el calculo de horario duplicado que existia antes en Market');

$routeFormTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_route_form.tpl');
check(strpos($routeFormTpl,'name="schedule_hour[]"') !== false && strpos($routeFormTpl,'name="schedule_minute[]"') !== false,
	'el formulario compartido declara hora y minuto por cada horario');
check(strpos($routeFormTpl,'id="routeFormAddSchedule"') !== false,
	'el formulario permite agregar mas de un horario antes de guardar');
check(strpos($routeFormTpl,'name="tvillage"') !== false,
	'el formulario de edicion tambien puede cambiar la aldea de destino, igual que al crear');
$createTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_create.tpl');
$editTpl = file_get_contents(dirname(__DIR__).'/Templates/Build/17_edit.tpl');
check(strpos($createTpl,"include('17_route_form.tpl')") !== false && strpos($editTpl,"include('17_route_form.tpl')") !== false,
	'crear y editar comparten el mismo formulario, para no duplicar campos entre los dos');
check(strpos($editTpl,"\$firstRoute['wid']") !== false,
	'editar precarga el destino actual de la ruta, no solo recursos/horario/envios');
check(strpos($editTpl,'foreach($edited_routes as $editedRoute)') !== false,
	'editar arma los horarios a partir de TODAS las filas del grupo, no de una sola');

// ---------------------------------------------------------------------------
section('J. Un guardado rechazado no borra lo que el jugador ya completo');
// ---------------------------------------------------------------------------
// Antes, cualquier motivo de rechazo (recursos vacios, mercaderes insuficientes, etc.)
// redirigia a un formulario en blanco: el destino, los recursos y los horarios que el
// jugador ya habia cargado (incluidos los que agrego a mano) se perdian.
check(strpos($marketSource,"\$_SESSION['tradeRouteDraft'][\$village->wid][\$routeDraftKey] = array(") !== false,
	'el borrador se guarda ANTES de cualquier validacion, asi que sobrevive a cualquier motivo de rechazo');
check(preg_match('/\$routeDraftKey\s*=\s*\$postAction === \'editRoute\' \? \'edit\'\.implode\(\'-\',\$originalRouteIds\) : \'create\';/',$marketSource) === 1,
	'el borrador distingue crear de editar (y de que GRUPO de rutas) para no mezclar formularios distintos');
check(strpos($marketSource,'private function draftScalar(') !== false
	&& strpos($marketSource,'private function draftScalarArray(') !== false,
	'los valores del borrador se guardan como escalares/arrays de escalares, sin confiar en el payload');
check(strpos($marketSource,'public function routeDraftFor(') !== false,
	'existe routeDraftFor(), que normaliza el borrador a los mismos tipos que usan los defaults del formulario');
check(strpos($marketSource,"unset(\$_SESSION['tradeRouteDraft'][\$village->wid][\$routeDraftKey]);") !== false,
	'el borrador se borra recien cuando el guardado termina bien, no en cada intento');
check(strpos($marketSource,"\$backToForm = 'action=editRoute';") !== false
	&& strpos($marketSource,"\$backToForm .= '&routeid%5B%5D='.\$rid;") !== false,
	'un rechazo al editar vuelve al mismo formulario de edicion con TODO el grupo (con su borrador), no a la lista de rutas');

check(strpos($createTpl,"\$market->routeDraftFor('create')") !== false,
	'el formulario de creacion recupera su propio borrador');
check(strpos($editTpl,"\$market->routeDraftFor('edit'.implode('-',\$routeFormOriginalRouteIds))") !== false,
	'el formulario de edicion recupera el borrador de ese GRUPO especifico, no el de otro');

// ---------------------------------------------------------------------------
section('K. Automation::peakConcurrentMerchants(): solapamiento real, no la suma');
// ---------------------------------------------------------------------------
// Caso reportado: dos aldeas vecinas (viaje corto) con horarios espaciados de sobra
// (cada 12 horas) no deberian exigir el doble de mercaderes solo por existir dos
// horarios; el pico real es 1, no 2, porque nunca coinciden en el tiempo.
check(Automation::peakConcurrentMerchants(array(
		array('start'=>0,'duration'=>3600,'merchants'=>1),
		array('start'=>12*3600,'duration'=>3600,'merchants'=>1),
	)) === 1,
	'dos salidas de 1 mercader cada 12hs con 1h de viaje nunca se pisan: el pico es 1, no 2');

// Si el viaje dura mas que el espacio entre horarios, ahi si se pisan y hace falta
// el doble.
check(Automation::peakConcurrentMerchants(array(
		array('start'=>0,'duration'=>13*3600,'merchants'=>1),
		array('start'=>12*3600,'duration'=>3600,'merchants'=>1),
	)) === 2,
	'si el viaje de la primera salida todavia no termino cuando arranca la segunda, el pico es 2');

// Una salida que cruza la medianoche (sale a las 23 y vuelve a las 2) tiene que
// pisarse igual con una que sale a las 01: el reloj de 24hs da la vuelta.
check(Automation::peakConcurrentMerchants(array(
		array('start'=>23*3600,'duration'=>3*3600,'merchants'=>1),
		array('start'=>1*3600,'duration'=>1800,'merchants'=>1),
	)) === 2,
	'el solapamiento se calcula con vuelta de medianoche, no solo dentro del mismo dia');

// Un viaje de mas de 24 horas (aldea rarisimamente lejos) nunca libera al mercader:
// ocupa su cupo todo el dia, sin importar el horario de otras salidas.
check(Automation::peakConcurrentMerchants(array(
		array('start'=>0,'duration'=>90000,'merchants'=>2),
		array('start'=>43200,'duration'=>60,'merchants'=>1),
	)) === 3,
	'un viaje de ida y vuelta de mas de 24hs ocupa su cupo todo el dia, se suma siempre');

// Cierres antes que aperturas en el mismo instante: el mercader que vuelve justo
// cuando otro sale ya esta libre para el segundo.
check(Automation::peakConcurrentMerchants(array(
		array('start'=>0,'duration'=>3600,'merchants'=>1),
		array('start'=>3600,'duration'=>3600,'merchants'=>1),
	)) === 1,
	'un cierre exacto no se cuenta como solapado con la apertura que sucede en el mismo instante');

check(Automation::peakConcurrentMerchants(array()) === 0,
	'sin salidas no hace falta ningun mercader');

check(strpos($automationSource,"foreach(\$database->getTradeRoutesFrom(\$vid, \$excludeRouteIds) as \$route) {") !== false,
	'el calendario de la aldea se arma con las rutas reales de esa aldea, no un supuesto');
// El contador que se muestra ("N de esos mercaderes salen todos los días...") y la
// validación al guardar tienen que salir del MISMO calendario. Cuando el contador sumaba
// las salidas, una ruta de 8 mercaderes declarada en tres horarios que no se pisan decía
// "24" en un Mercado que como mucho tiene 20 — un número imposible, y encima distinto del
// que el guardado acababa de aceptar.
check(strpos($automationSource,'return self::peakConcurrentMerchants(self::routeScheduleEntries($vid, $carryCapacity, $tribe, $excludeRouteIds));') !== false,
	'routeMerchantsCommitted() devuelve el PICO de mercaderes de viaje a la vez, no la suma de todas las salidas del día');
check(preg_match('/routeMerchantsCommitted\([^)]*\)\s*\{\s*\$total = 0;/',$automationSource) !== 1,
	'ya no queda la suma ciega de las salidas del día');
check(strpos($dbSource,'SELECT id, wid, wood, clay, iron, crop, start, start_minute, deliveries FROM') !== false,
	'getTradeRoutesFrom() trae tambien el destino de cada ruta, necesario para calcular su viaje');

// ---------------------------------------------------------------------------
section('L. Una ruta con varios horarios es una sola fila en el listado y en la edicion');
// ---------------------------------------------------------------------------
// Antes, crear una ruta con 3 horarios dejaba 3 filas identicas (misma descripcion,
// mismos recursos) en el listado, y editarla solo dejaba tocar el primer horario:
// agregar o quitar horarios en la edicion no reconciliaba las filas de mas/de menos.
check(strpos($dbSource,'function getTradeRoutesByIds(') !== false,
	'existe getTradeRoutesByIds(), para traer el grupo completo de una sola consulta');
check(strpos($dbSource,"WHERE id IN (\" . implode(',',\$ids) . \")") !== false,
	'getTradeRoutesByIds() trae todas las filas pedidas en una sola consulta, no una por una');

check(strpos($marketSource,"\$ownedRoutes = \$database->getTradeRoutesByIds(\$originalRouteIds);") !== false,
	'antes de reconciliar, se revalida contra la base que CADA id del grupo sigue siendo del jugador y de esta aldea');
check(preg_match('/foreach\(\$originalRouteIds as \$rid\)\s*\{\s*if\(!isset\(\$ownedRoutes\[\$rid\]\)/',$marketSource) === 1,
	'un id colado en el formulario que no pertenece al jugador/aldea rechaza el guardado entero, no se ignora en silencio');

check(strpos($tplSource,"\$requestedRouteIds[] = (int)\$rawId;") !== false,
	'el enlace de editar puede pedir varios ids del grupo a la vez (routeid[])');
check(strpos($tplSource,'usort($edited_routes,function($a,$b){') !== false,
	'el grupo se ordena por horario antes de mostrarlo, para que la posicion de cada horario en el formulario sea predecible');

// ---------------------------------------------------------------------------
section('M. sendResource2() pone al dia la produccion antes de revisar disponibilidad');
// ---------------------------------------------------------------------------
// Caso real: una ruta con "envios x3" mandaba el primer envio completo, pero el
// segundo (recien cuando el mercader volvia del primero) llegaba con menos de lo
// configurado aunque la aldea tuviera produccion de sobra. La columna de recursos en
// la base solo se pone al dia cuando alguien carga una pagina de esa aldea
// (Village.php::processProduction); un envio automatico corre solo, sin que nadie la
// este mirando, asi que leia el remanente congelado desde la ultima visita.
check(preg_match('/private function sendResource2\(.*?\)\s*\{\s*global[^;]*;\s*(?:\/\/[^\n]*\n\s*)*\$this->accrueProductionBeforeChange\(\$from, ?null\);/s',$automationSource) === 1,
	'sendResource2() acredita la produccion real de la aldea de origen ANTES de leer cuanto hay disponible, no despues');
check(strpos($automationSource,'protected function accrueProductionBeforeChange(') !== false,
	'reutiliza la misma funcion de acreditar produccion que ya usan los cambios de nivel/oasis, no una copia nueva');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Trade route checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
