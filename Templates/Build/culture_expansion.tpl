<?php
/*
 * Pestaña "Puntos de cultura" de la Residencia y del Palacio: cuánta cultura pide cada
 * aldea siguiente y cuánto falta para llegar.
 *
 * Los estilos van en un <style> acá adentro y no en el CSS del gpack a propósito. El
 * gpack está detrás de Cloudflare con TTL de 4 horas y cada cambio obliga a versionar
 * el asset y a esperar el deploy antes de tocar la URL nueva (ver AGENTS.md); esto es
 * una tabla de una pantalla y no vale esa ceremonia. Todo cuelga de #cultureExpansion
 * para no pisar nada del resto de la ficha.
 *
 * La ventana empieza una aldea antes de la que el jugador está persiguiendo, para que
 * se vea de dónde viene, y muestra diez filas: la tabla llega a 125 aldeas.
 */
$expansionOwned = count($session->villages);
$expansionPending = (int)$database->getPendingSettlementCountByOwner($session->uid);
$expansionPoints = (int)$database->getUserField($session->uid, 'cp', 0);
$expansionDaily = accountCulturePointsPerDay($database, $session->uid);
$expansionTarget = $expansionOwned + $expansionPending + 1;
$expansionFrom = max(2, $expansionTarget - 1);
$expansionRows = array();
for($expansionCount = $expansionFrom; count($expansionRows) < 10; $expansionCount++) {
	$expansionRequired = travianCultureRequiredForVillageCount($expansionCount, CP);
	if($expansionRequired === null) {
		break;
	}
	$expansionRows[$expansionCount] = $expansionRequired;
}
if(!empty($expansionRows)) {
?>
<style>
#cultureExpansion{margin:18px 0 4px;border-collapse:collapse;width:auto;min-width:340px;line-height:19px;background:transparent;}
#cultureExpansion caption{caption-side:top;text-align:left;font-weight:bold;padding:0 0 6px;color:#5c4426;}
#cultureExpansion th,#cultureExpansion td{padding:2px 0;white-space:nowrap;border:0;background:transparent;font-weight:normal;}
#cultureExpansion thead th{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#8a7a5e;border-bottom:1px solid rgba(92,68,38,.28);padding-bottom:3px;}
#cultureExpansion .ceName{text-align:left;padding-right:26px;}
#cultureExpansion .ceNum{text-align:right;padding-left:22px;font-variant-numeric:tabular-nums;}
#cultureExpansion .ceWhen{text-align:right;padding-left:22px;color:#6b6152;}
#cultureExpansion tbody tr.ceDone .ceName,#cultureExpansion tbody tr.ceDone .ceNum{color:#9a9184;}
#cultureExpansion tbody tr.ceDone .ceWhen{color:#9a9184;font-style:italic;}
#cultureExpansion tbody tr.ceNext{background:rgba(180,140,60,.16);}
#cultureExpansion tbody tr.ceNext th,#cultureExpansion tbody tr.ceNext td{font-weight:bold;color:#4a3517;padding-top:3px;padding-bottom:3px;}
#cultureExpansion tbody tr.ceNext .ceName{padding-left:6px;}
#cultureExpansion tbody tr.ceNext .ceWhen{padding-right:6px;color:#4a3517;}
#cultureExpansion tbody tr.ceNext .ceNum:last-of-type{padding-right:0;}
</style>
<table id="cultureExpansion" cellpadding="0" cellspacing="0">
	<caption>Puntos de cultura por aldea</caption>
	<thead>
		<tr>
			<th class="ceName" scope="col">Aldea</th>
			<th class="ceNum" scope="col">Necesarios</th>
			<th class="ceNum" scope="col">Te faltan</th>
			<th class="ceWhen" scope="col">A tu ritmo</th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach($expansionRows as $expansionCount => $expansionRequired) {
		$expansionMissing = max(0, $expansionRequired - $expansionPoints);
		// Una cuenta creada desde el panel puede tener más aldeas que cultura pagada:
		// ahí la fila no es una meta, es una aldea que ya existe.
		if($expansionCount <= $expansionOwned) {
			$expansionMissing = 0;
			$expansionWhen = 'ya fundada';
		} elseif($expansionMissing <= 0) {
			// La fila resaltada es la que el jugador está persiguiendo: si ya le
			// alcanza la cultura, lo que quiere leer es que puede fundarla.
			$expansionWhen = $expansionCount === $expansionTarget ? '¡puedes fundarla!' : 'ya alcanzada';
		} elseif($expansionDaily <= 0) {
			$expansionWhen = '&mdash;';
		} else {
			$expansionDays = $expansionMissing / $expansionDaily;
			if($expansionDays < 1) {
				$expansionWhen = max(1, (int)round($expansionDays * 24)).' h';
			} elseif($expansionDays < 365) {
				$expansionWhen = number_format($expansionDays, 1, ',', '.').' días';
			} else {
				// A este ritmo no llega: en días son cinco cifras de ruido.
				$expansionWhen = number_format($expansionDays / 365, 1, ',', '.').' años';
			}
		}
		$expansionDone = $expansionCount <= $expansionOwned || $expansionMissing <= 0;
		$expansionClass = $expansionCount === $expansionTarget ? 'ceNext' : ($expansionDone ? 'ceDone' : '');
?>
		<tr<?php echo $expansionClass !== '' ? ' class="'.$expansionClass.'"' : ''; ?>>
			<th class="ceName" scope="row">Aldea <?php echo $expansionCount; ?></th>
			<td class="ceNum"><?php echo number_format($expansionRequired, 0, ',', '.'); ?></td>
			<td class="ceNum"><?php echo $expansionMissing > 0 ? number_format($expansionMissing, 0, ',', '.') : '&ndash;'; ?></td>
			<td class="ceWhen"><?php echo $expansionWhen; ?></td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
