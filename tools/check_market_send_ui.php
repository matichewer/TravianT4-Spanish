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

checkMarketUi(strpos($template,'function selectedResources(excludeResNr)') !== false,
	'el formulario suma los otros tres recursos seleccionados');
checkMarketUi(strpos($template,'haendler * carry - selectedResources(resNr)') !== false,
	'cada enlace rápido descuenta de la capacidad común lo ya cargado en otros recursos');
checkMarketUi(strpos($template,"parseInt(\$('r' + resNr).value)") !== false,
	'el incremento parte del valor visible y no de un contador interno desactualizado');
checkMarketUi(strpos($template,'$canSend = $market->maxcarry * $market->merchantAvail();') !== false,
	'la confirmación del formulario conserva el límite total del lado del servidor');
checkMarketUi(strpos(file_get_contents(dirname(__DIR__).'/GameEngine/Market.php'),'$reqMerc > $this->merchantAvail()') !== false,
	'el envío definitivo también rechaza pedidos sin mercaderes suficientes');

if(empty($fails)) {
	echo "Market send UI checks passed ($checks comprobaciones).\n";
	exit(0);
}

echo count($fails)." de $checks comprobaciones fallaron.\n";
exit(1);
