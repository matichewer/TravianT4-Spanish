<h1 class="titleInHeader">Palacio <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid26">
<div class="build_desc">
	<a href="#" onClick="return Travian.Game.iPopup(26,4, 'gid');" class="build_logo"> 
    <img class="building big white g26" src="img/x.gif" alt="Palacio" title="Palacio" /> </a>
	El rey de la nación vive en el palacio. Cuanto mayor sea el nivel, más difícil será para los enemigos conquistar la aldea. Solo con un palacio se puede nombrar capital a una aldea. No se pueden construir un palacio y una residencia en la misma aldea. Solo se permite un palacio por cuenta.</div>

<?php
$buildingHelpType = 'palace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("26_menu.tpl"); 
?>

<p>Para extender tu imperio necesitas puntos de cultura. Estos aumentan con el paso del tiempo, y más rápido cuanto mayores sean los niveles de tus edificios.</p>

<table cellpadding="1" cellspacing="1" id="build_value">
<tr>
	<th>Producción de esta aldea</th>
	<td><b><?php echo rtrim(rtrim(number_format(villageCulturePointsPerDay($database->getVillageField($village->wid,'cp')),2,',','.'),'0'),','); ?></b> puntos de cultura</td>
</tr>
<tr>
	<th>Producción de todas las aldeas:	</th>
	<td><b><?php echo number_format(accountVillageCulturePointsPerDay($database,$session->uid),0,',','.'); ?></b> puntos de cultura</td>
</tr>
</table><?php
$totalVillages = count($session->villages);
$pendingSettlements = $database->getPendingSettlementCountByOwner($session->uid);
$currentCulturePoints = (int)$database->getUserField($session->uid, 'cp', 0);
$cultureEligibility = travianCultureExpansionEligibility(
	$currentCulturePoints,
	$totalVillages,
	$pendingSettlements,
	CP
);
$requiredCulturePoints = $cultureEligibility['requiredPoints'];
?>
<?php if($requiredCulturePoints === null) { ?>
<p>No hay un umbral de cultura configurado para fundar o conquistar otra aldea.<br />Actualmente tienes <b><?php echo number_format($currentCulturePoints, 0, ',', '.'); ?></b> puntos de cultura.</p>
<?php } else { ?>
<p>Necesitas <b><?php echo number_format($requiredCulturePoints, 0, ',', '.'); ?></b> puntos de cultura para fundar o conquistar una nueva aldea.<br />Actualmente tienes <b><?php echo number_format($currentCulturePoints, 0, ',', '.'); ?></b> puntos de cultura.</p>
<?php } ?>
<?php if($pendingSettlements > 0) { ?>
<p><b><?php echo $pendingSettlements; ?></b> fundación<?php echo $pendingSettlements === 1 ? '' : 'es'; ?> en camino ya reserva<?php echo $pendingSettlements === 1 ? '' : 'n'; ?> puntos de cultura.</p>
<?php } ?>
</div>
