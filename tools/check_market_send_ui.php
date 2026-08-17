<?php
/**
 * Regression checker for the marketplace quick-add resource links.
 *
 * Run with:
 *   docker compose exec -T web php /var/www/html/tools/check_market_send_ui.php
 */

$checks = 0;
$fails = array();

function checkMarketUi($condition,$message) {
	global $checks,$fails;
	$checks++;
	if(!$condition) {
		$fails[] = $message;
		echo "FAIL: $message\n";
		return;
	}
	echo "OK: $message\n";
}

$template = file_get_contents(dirname(__DIR__).'/Templates/Build/17.tpl');
$offerTemplate = file_get_contents(dirname(__DIR__).'/Templates/Build/17_2.tpl');
$marketEngine = file_get_contents(dirname(__DIR__).'/GameEngine/Market.php');

checkMarketUi(strpos($template,'function selectedResources(excludeResNr)') !== false,
	'el formulario suma los otros tres recursos seleccionados');
checkMarketUi(strpos($template,'haendler * carry - selectedResources(resNr)') !== false,
	'cada enlace rápido descuenta de la capacidad común lo ya cargado en otros recursos');
checkMarketUi(strpos($template,"parseInt(\$('r' + resNr).value)") !== false,
	'el incremento parte del valor visible y no de un contador interno desactualizado');
checkMarketUi(strpos($template,"link.style.color = capacityUsed ? '#999999' : ''") !== false,
	'los enlaces rápidos se vuelven grises al ocupar todos los mercaderes');
checkMarketUi(substr_count($template,'updateQuickAddState();') >= 3,
	'el color se actualiza al cargar la página, sumar o editar recursos');
checkMarketUi(strpos($template,'$canSend = $market->maxcarry * $market->merchantAvail();') !== false,
	'la confirmación del formulario conserva el límite total del lado del servidor');
checkMarketUi(strpos($template,'name="cancel"') !== false && strpos($template,'>Editar</div>') !== false,
	'la confirmación ofrece volver al formulario mediante un botón Editar');
checkMarketUi(strpos($template,'name="x" value="<?php echo htmlspecialchars($marketX') !== false
	&& strpos($template,'name="y" value="<?php echo htmlspecialchars($marketY') !== false
	&& strpos($template,'name="dname" value="<?php echo htmlspecialchars($marketVillageName') !== false,
	'Cancelar conserva destino y nombre seleccionados');
checkMarketUi(strpos($template,"&& !isset(\$_POST['cancel'])") !== false,
	'Editar evita volver a mostrar la pantalla de confirmación');
checkMarketUi(strpos($marketEngine,"if(isset(\$post['cancel']))") !== false,
	'Editar no procesa el envío ni consume el token de seguridad');
checkMarketUi(strpos($template,"\$marketSendCount === 2 ? ' selected=\"selected\"'") !== false
	&& strpos($template,"\$marketSendCount === 3 ? ' selected=\"selected\"'") !== false,
	'Editar conserva la cantidad de envíos seleccionada');
checkMarketUi(strpos($template,"\$quickTargetSelected = \$marketCoordinatesValid ? ((int)\$marketX).'|'.((int)\$marketY) : '';") !== false
	&& strpos($template,'$marketTargetCoor = $database->getCoor($getwref);') !== false,
	'Editar conserva las coordenadas para preseleccionar el destino rápido');
checkMarketUi(strpos(file_get_contents(dirname(__DIR__).'/Templates/quick_target_select.tpl'),'selected="selected"') !== false,
	'el selector de destino rápido marca la aldea conservada');
checkMarketUi(strpos($marketEngine,'$reqMerc > $this->merchantAvail()') !== false,
	'el envío definitivo también rechaza pedidos sin mercaderes suficientes');
checkMarketUi(strpos($marketEngine,"\$_SESSION['marketOfferDraft'][\$village->wid]") !== false,
	'la última oferta se conserva por aldea después de la redirección');
checkMarketUi(strpos($offerTemplate,"(int)\$offerDraft['gamt']") !== false
	&& strpos($offerTemplate,"(int)\$offerDraft['wamt']") !== false,
	'el formulario vuelve a cargar las dos cantidades de la oferta');
checkMarketUi(substr_count($offerTemplate,"\$offerDraft['gtype']") === 4
	&& substr_count($offerTemplate,"\$offerDraft['wtype']") === 4,
	'el formulario vuelve a seleccionar los recursos ofrecido y buscado');
checkMarketUi(strpos($offerTemplate,"\$offerDraft['limited']") !== false
	&& strpos($offerTemplate,"\$offerDraft['alliance']") !== false,
	'el formulario conserva los límites de tiempo y alianza');
checkMarketUi(strpos($offerTemplate,'name="a" value="<?php echo $session->mchecker; ?>"') !== false,
	'cada oferta repetida sigue usando el token de seguridad actual');

if(empty($fails)) {
	echo "Market send UI checks passed ($checks comprobaciones).\n";
	exit(0);
}

echo count($fails)." de $checks comprobaciones fallaron.\n";
exit(1);
