<?php
$cultureOwnerId = (int)$session->uid;
$cultureVillageIds = $session->villages;

// Administrators can inspect a village owned by another account.
if(!in_array($village->wid, $session->villages)) {
	$cultureOwnerId = (int)$database->getVillageField($village->wid, 'owner');
	$cultureVillageIds = $database->getVillagesID($cultureOwnerId);
}

$culturePoints = (int)$database->getUserField($cultureOwnerId, 'cp', 0);
$cultureStatus = travianCultureStatus($culturePoints, count($cultureVillageIds), CP);
$cultureReadyClass = ($cultureStatus['availableVillageSlots'] > 0) ? ' cultureProgressReady' : '';
?>
<div id="cultureProgress" class="cultureProgress<?php echo $cultureReadyClass; ?>">
	<div class="cultureProgressHeader">
		<span class="cultureProgressTitleWrap">
			<span class="cultureProgressTitle" aria-describedby="cultureProgressTooltip">Puntos de cultura</span>
			<span id="cultureProgressTooltip" class="cultureProgressTooltip" role="tooltip">
				<strong>Cómo funcionan los puntos de cultura</strong>
				<span class="cultureProgressTooltipLine"><b>Campos y edificios:</b> cada nivel completado aumenta la producción de PC. La producción de todas tus aldeas se suma y se acredita una vez cada 24 horas.</span>
				<span class="cultureProgressTooltipLine"><b>Ayuntamiento:</b> una celebración pequeña entrega 500 PC y una grande 2000 PC cuando finaliza.</span>
				<span class="cultureProgressTooltipLine"><b>Obras de arte:</b> conceden inmediatamente tantos PC como tu producción diaria total.</span>
				<span class="cultureProgressTooltipFoot">Los PC pertenecen a toda la cuenta, permiten fundar o conquistar más aldeas y no se gastan al utilizarlos. Con Plus, el desglose por aldea está en Resumen de aldeas → Puntos de cultura.</span>
			</span>
		</span>
		<span class="cultureProgressVillages">Aldeas: <strong><?php echo $cultureStatus['ownedVillages']; ?> de <?php echo $cultureStatus['cultureCapacity']; ?></strong> posibles</span>
	</div>
<?php if(!$cultureStatus['available']) { ?>
	<div class="cultureProgressUnavailable">No hay una tabla de cultura configurada para este modo.</div>
<?php } elseif($cultureStatus['atConfiguredMaximum']) { ?>
	<div class="cultureProgressInfo">
		<span>Máximo cultural configurado alcanzado</span>
		<strong><?php echo number_format($cultureStatus['culturePoints'], 0, ',', '.'); ?> PC</strong>
	</div>
	<div class="cultureProgressBar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100">
		<div class="cultureProgressBarFill" style="width:100%;"></div>
	</div>
<?php } else { ?>
	<div class="cultureProgressInfo">
		<strong><?php echo number_format($cultureStatus['culturePoints'], 0, ',', '.'); ?> / <?php echo number_format($cultureStatus['nextRequiredPoints'], 0, ',', '.'); ?> PC</strong>
	</div>
	<div class="cultureProgressBar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo $cultureStatus['nextRequiredPoints']; ?>" aria-valuenow="<?php echo min($cultureStatus['culturePoints'], $cultureStatus['nextRequiredPoints']); ?>" title="Faltan <?php echo number_format($cultureStatus['remainingPoints'], 0, ',', '.'); ?> puntos de cultura">
		<div class="cultureProgressBarFill" style="width:<?php echo number_format($cultureStatus['progressPercent'], 2, '.', ''); ?>%;"></div>
	</div>
<?php } ?>
</div>
