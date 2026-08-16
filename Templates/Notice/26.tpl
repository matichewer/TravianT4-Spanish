<?php
// Informe "la salida de la ruta comercial no se ejecutó" (Automation::reportFailedDeparture).
// Es el único aviso que recibe el jugador: la salida no se reintenta, y si el corte pasa en
// medio de una cadena de envíos x2/x3 tampoco queda nada pendiente que la retome.
//
// data: origen, destino, madera, barro, hierro, cereal, motivo, viaje, total de viajes
include __DIR__ . "/report_data.tpl";

$routeFailReason = isset($dataarray[6]) ? (string)$dataarray[6] : '';
switch($routeFailReason) {
	case 'no_merchants':
		$routeFailTitle = 'No había mercaderes libres';
		$routeFailText = 'A esta hora los mercaderes del Mercado estaban todos de viaje, así que la salida no se '
			.'ejecutó. Podés subir el Mercado, espaciar los horarios de la ruta, bajar la cantidad de recursos por '
			.'salida o reducir los envíos encadenados.';
		break;
	case 'no_resources':
		$routeFailTitle = 'No había recursos para enviar';
		$routeFailText = 'La aldea de origen no tenía nada de lo que esta ruta transporta, así que la salida no se '
			.'ejecutó.';
		break;
	default:
		$routeFailTitle = 'La salida no se pudo completar';
		$routeFailText = 'Algo impidió que los mercaderes salieran. Si se repite, revisá que la aldea de destino siga '
			.'siendo tuya.';
}
?>
<table cellpadding="1" cellspacing="1" id="report_surround">
<thead class="theader">
	<tr>
		<th colspan="2">
			<div id="subject">
				<div class="header label"><?php echo REPORT_SUBJECT; ?></div>
				<div class="header text"><?php echo htmlspecialchars((string)$topic,ENT_QUOTES,'UTF-8'); ?></div>
				<div class="clear"></div>
			</div>
			<div id="time">
				<?php $date = $generator->procMtime($time); ?>
				<div class="header label"><?php echo REPORT_SENT; ?></div>
				<div class="header text"><?php echo $date[0]."<span> ".REPORT_AT." ".$date[1]; ?></span></div>
				<div class="toolList"><div class="clear"></div></div><div class="clear"></div>
			</div>
		</th>
	</tr>
</thead>
<tbody>
	<tr><td colspan="2" class="report_content">
	<img src="img/x.gif" class="reportImage reportType3" alt="">
<?php
$routeFailFrom = $database->getVillage($dataarray[0]);
$routeFailTo = $database->getVillage($dataarray[1]);
?>
<table cellpadding="0" cellspacing="0" id="trade">
	<thead>
		<tr>
			<td colspan="2" class="troopHeadline">
				<a href="karte.php?d=<?php echo (int)$dataarray[0]."&amp;c=".$generator->getMapCheck($dataarray[0]); ?>"><?php echo htmlspecialchars((string)$routeFailFrom['name'],ENT_QUOTES,'UTF-8'); ?></a>
				<span style="font-weight: normal;">no pudo enviar recursos a</span>
				<a href="karte.php?d=<?php echo (int)$dataarray[1]."&amp;c=".$generator->getMapCheck($dataarray[1]); ?>"><?php echo htmlspecialchars((string)$routeFailTo['name'],ENT_QUOTES,'UTF-8'); ?></a>
			</td>
		</tr>
	</thead>
	<tbody>
		<tr><td class="empty" colspan="2"></td></tr>
		<tr>
			<th>Motivo</th>
			<td><b><?php echo htmlspecialchars($routeFailTitle,ENT_QUOTES,'UTF-8'); ?></b></td>
		</tr>
		<tr>
			<th><?php echo REPORT_RESOURCES; ?></th>
			<td>
				<div class="rArea"><img class="r1" src="img/x.gif" title="<?php echo WOOD; ?>"> <?php echo (int)$dataarray[2]; ?></div>
				<div class="rArea"><img class="r2" src="img/x.gif" title="<?php echo CLAY; ?>"> <?php echo (int)$dataarray[3]; ?></div>
				<div class="rArea"><img class="r3" src="img/x.gif" title="<?php echo IRON; ?>"> <?php echo (int)$dataarray[4]; ?></div>
				<div class="rArea"><img class="r4" src="img/x.gif" title="<?php echo CROP; ?>"> <?php echo (int)$dataarray[5]; ?></div>
			</td>
		</tr>
		<?php if($reportTotalDeliveries > 1) { ?>
		<tr>
			<th>Viaje</th>
			<td><?php echo $reportCurrentDelivery.' de '.$reportTotalDeliveries; ?><?php if($reportCurrentDelivery > 1) { ?> <span class="none">(los viajes restantes de esta salida quedaron sin hacer)</span><?php } ?></td>
		</tr>
		<?php } ?>
		<tr><td class="empty" colspan="2"></td></tr>
		<tr>
			<th>&nbsp;</th>
			<td class="none"><?php echo htmlspecialchars($routeFailText,ENT_QUOTES,'UTF-8'); ?></td>
		</tr>
	</tbody>
</table>
</td></tr></tbody></table>
<div class="clear">&nbsp;</div>
