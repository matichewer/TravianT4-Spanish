<?php
/*
 * Filas extra de la pestaña "Puntos de cultura" de la Residencia y del Palacio: cuánta
 * cultura pide cada aldea siguiente y cuánto falta para llegar.
 *
 * Se inyecta dentro del <table id="build_value"> que ya arman 25_2.tpl y 26_2.tpl, así
 * hereda el estilo del resto de la ficha (th a la izquierda, td a la derecha) y no
 * hace falta tocar el CSS del gpack.
 *
 * La ventana empieza una aldea antes de la que el jugador está persiguiendo para que
 * se vea de dónde viene, y muestra diez filas: la tabla completa llega a 125 aldeas y
 * no le sirve a nadie.
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
<tr>
	<th colspan="4" class="cultureExpansionHead" style="padding-top:12px;text-align:left;">Puntos de cultura por aldea</th>
</tr>
<?php
	foreach($expansionRows as $expansionCount => $expansionRequired) {
		$expansionMissing = max(0, $expansionRequired - $expansionPoints);
		// Una cuenta creada desde el panel puede tener más aldeas que cultura pagada:
		// ahí la fila no es una meta, es una aldea que ya existe.
		if($expansionCount <= $expansionOwned) {
			$expansionMissing = 0;
			$expansionWhen = 'ya fundada';
		} elseif($expansionMissing <= 0) {
			$expansionWhen = 'ya alcanzada';
		} elseif($expansionDaily <= 0) {
			$expansionWhen = '&mdash;';
		} else {
			$expansionDays = $expansionMissing / $expansionDaily;
			if($expansionDays < 1) {
				$expansionWhen = 'en '.max(1, (int)round($expansionDays * 24)).' h';
			} elseif($expansionDays < 365) {
				$expansionWhen = 'en '.number_format($expansionDays, 1, ',', '.').' días';
			} else {
				// A este ritmo no llega: en días son cinco cifras de ruido.
				$expansionWhen = 'en '.number_format($expansionDays / 365, 1, ',', '.').' años';
			}
		}
?>
<tr<?php echo $expansionCount === $expansionTarget ? ' class="cultureExpansionNext" style="color:#3a2a12;"' : ''; ?>>
	<th><?php echo $expansionCount === $expansionTarget ? '<b>Aldea '.$expansionCount.'</b>' : 'Aldea '.$expansionCount; ?></th>
	<td><?php echo number_format($expansionRequired, 0, ',', '.'); ?> PC</td>
	<td><?php echo $expansionMissing > 0 ? 'faltan '.number_format($expansionMissing, 0, ',', '.') : '&nbsp;'; ?></td>
	<td><?php echo $expansionWhen; ?></td>
</tr>
<?php
	}
}
