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
$pendingSettlements = $database->getPendingSettlementCountByOwner($cultureOwnerId);
$cultureEligibility = travianCultureExpansionEligibility(
	$culturePoints,
	count($cultureVillageIds),
	$pendingSettlements,
	CP
);
$cultureReadyClass = $cultureEligibility['eligible'] ? ' cultureProgressReady' : '';
$cultureProtectionClass = (isset($session->userinfo['protect']) && (int)$session->userinfo['protect'] > time())
	? ' cultureProgressWithProtection'
	: '';

// Same condition Templates/quest.tpl uses to draw the quest master sign: once
// every task is done the sign is gone, so the panel docks under the village sign.
$cultureQuestArray = $database->getUserArray($session->uid, 1);
$cultureQuestVisible = QUEST == true && $cultureQuestArray['fquest'] != "1,1,1,1,1,1,1,1,1,1,1";
$cultureQuestClass = $cultureQuestVisible ? '' : ' cultureProgressWithoutQuest';

// vdata.cp holds the culture points a village yields per day; GameEngine/Automation.php
// credits the sum of all the owner's villages, plus the hero's culture helmet, once
// every 24 hours.
$cultureDailyProduction = accountCulturePointsPerDay($database, $cultureOwnerId);
$cultureHelmetProduction = heroHelmetCulturePoints($database, $cultureOwnerId);
$cultureHelmetValues = array(
	heroHelmetCulturePointsForType(7),
	heroHelmetCulturePointsForType(8),
	heroHelmetCulturePointsForType(9)
);
$cultureArtworkCap = artworkCulturePointsCap();
?>
<?php /* Tres piezas como el cartel de la lista de aldeas: el rollo de arriba, el
cuerpo con el pergamino repetido y el rollo de abajo. Tienen que ser elementos
propios y no un fondo del contenedor, porque el arte de los rollos es
transparente por fuera del cartel y dejaria ver el pergamino por detras. */ ?>
<div id="cultureProgress" class="cultureProgress<?php echo $cultureReadyClass . $cultureQuestClass . $cultureProtectionClass; ?>">
	<div class="cultureProgressSignTop"></div>
	<div class="cultureProgressSignBody">
	<div class="cultureProgressHeader">
		<span class="cultureProgressTitleWrap">
			<span class="cultureProgressTitle" aria-describedby="cultureProgressTooltip">Puntos de Cultura</span>
			<span id="cultureProgressTooltip" class="cultureProgressTooltip" role="tooltip">
				<strong>Cómo funcionan los puntos de cultura</strong>
				<span class="cultureProgressTooltipLine"><b>Campos y edificios:</b> cada nivel aporta los PC/día que indica su ficha. La suma de todas tus aldeas se acredita entera cada 24 horas.</span>
				<span class="cultureProgressTooltipLine"><b>Ayuntamiento:</b> al terminar, una celebración pequeña entrega la producción diaria de su aldea (máximo <?php echo number_format(celebrationCulturePointsCap(1),0,',','.'); ?> PC) y una grande la de toda la cuenta (máximo <?php echo number_format(celebrationCulturePointsCap(2),0,',','.'); ?> PC).</span>
				<span class="cultureProgressTooltipLine"><b>Casco del héroe:</b> en este mundo, Gladiador, Tribuno y Cónsul suman <?php echo number_format($cultureHelmetValues[0],0,',','.'); ?>, <?php echo number_format($cultureHelmetValues[1],0,',','.'); ?> y <?php echo number_format($cultureHelmetValues[2],0,',','.'); ?> PC/día mientras el héroe esté vivo.</span>
				<span class="cultureProgressTooltipLine"><b>Obras de arte:</b> conceden tu producción diaria total, con un máximo de <?php echo number_format($cultureArtworkCap,0,',','.'); ?> PC. Solo puedes usar una cada 24 horas.</span>
				<span class="cultureProgressTooltipFoot">Los PC pertenecen a toda la cuenta y permiten fundar o conquistar más aldeas. Con Plus, el desglose por aldea está en Resumen de aldeas → Puntos de cultura.</span>
			</span>
		</span>
		<span class="cultureProgressVillages">Aldeas: <strong><?php echo $cultureStatus['ownedVillages']; ?> de <?php echo $cultureStatus['cultureCapacity']; ?></strong> posibles<?php if($pendingSettlements > 0) { echo ' ('.$pendingSettlements.' en camino)'; } ?></span>
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
	<div class="cultureProgressBar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo $cultureStatus['progressRequiredPoints']; ?>" aria-valuenow="<?php echo $cultureStatus['progressPoints']; ?>" title="Faltan <?php echo number_format($cultureStatus['remainingPoints'], 0, ',', '.'); ?> puntos de cultura">
		<div class="cultureProgressBarFill" style="width:<?php echo number_format($cultureStatus['progressPercent'], 2, '.', ''); ?>%;"></div>
	</div>
<?php } ?>
	<div class="cultureProgressRate" title="Producción diaria de puntos de cultura de toda la cuenta<?php if($cultureHelmetProduction > 0) { echo ', incluidos '.number_format($cultureHelmetProduction, 0, ',', '.').' PC del casco del héroe'; } ?>">+<?php echo number_format($cultureDailyProduction, 0, ',', '.'); ?> PC/día</div>
	</div>
	<div class="cultureProgressSignFoot"></div>
</div>
