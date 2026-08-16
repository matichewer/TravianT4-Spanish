<?php
// Cabecera comun de las pestañas del Mercado: el ultimo rechazo (antes los envios y las
// ofertas se caian con un redirect mudo) y el contador de mercaderes.
//
// Los mercaderes comprometidos por rutas comerciales se muestran aparte a proposito: no
// estan ocupados —salen recien a su horario— pero son la unica explicacion de por que un
// envio grande puede no entrar. Sin esta linea, la ruta era invisible desde esta pantalla.
//
// $marketShowCounter = false lo deja solo con el mensaje de error (pestaña del NPC).
$marketErrorText = $market->errorText();
if($marketErrorText !== '') {
	echo '<p class="error"><b>'.htmlspecialchars($marketErrorText,ENT_QUOTES,'UTF-8').'</b></p>';
}
if(!isset($marketShowCounter) || $marketShowCounter) {
?>
<div class="boxes boxesColor gray traderCount"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Mercaderes <?php echo $market->merchantAvail(); ?> / <?php echo (int)$market->merchant; ?></div>
			</div><div class="clear"></div>
<?php
	if((int)$market->routeReserved > 0) {
		$marketRouteHours = $market->routeDepartureHours();
		// El numero es el PICO de mercaderes de viaje a la vez, no la suma de las salidas
		// del dia: una misma ruta declarada en tres horarios que no se pisan usa los
		// mismos mercaderes las tres veces. Sumarlos daba cifras imposibles ("24" en un
		// Mercado de 20) y ademas no coincidian con lo que valida el guardado.
		$marketRouteReserved = (int)$market->routeReserved;
		echo '<p class="none">Hasta '.$marketRouteReserved.' de esos mercaderes '
			.($marketRouteReserved === 1 ? 'está de viaje' : 'están de viaje')
			.' a la vez en rutas comerciales ('
			.(count($marketRouteHours) > 1 ? 'salidas: ' : 'salida: ')
			.htmlspecialchars(implode(', ',$marketRouteHours),ENT_QUOTES,'UTF-8').'). '
			.'Mientras no viajen podés usarlos para cualquier otro envío; si a esa hora están ocupados, '
			.'la ruta se reintenta sola.';
		if($session->goldclub == 1) {
			echo ' <a href="build.php?id='.(int)$id.'&amp;t=4">Ver rutas comerciales</a>';
		}
		echo '</p>';
	}
}
unset($marketShowCounter);
?>
