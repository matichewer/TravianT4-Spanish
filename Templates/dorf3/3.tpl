<?php
include('menu.tpl');
?>
<table id="warehouse" cellpadding="1" cellspacing="1">
<thead>
<tr><td> Village </td>
<td><img class="r1" src="img/x.gif" title="Madera" alt="Madera"></td>
<td><img class="r2" src="img/x.gif" title="Barro" alt="Barro"></td>
<td><img class="r3" src="img/x.gif" title="Hierro" alt="Hierro"></td>
<td><img class="clock" src="img/x.gif" title="Tiempo" alt="Tiempo"></td>
<td><img class="r4" src="img/x.gif" title="Cereal" alt="Cereal"></td>
<td><img class="clock" src="img/x.gif" title="Tiempo" alt="Tiempo"></td>
</tr></thead><tbody>
<?php
// En orden de fundación, igual que el cartel lateral. Ver GameEngine/VillageOverview.php.
$varray = villageOverviewVillages($session->uid);
$timer = 1;
foreach($varray as $vil){
	$vid = $vil['wref'];
	$vdata = $database->getVillage($vid);
	$pop = $vdata['pop'];
	$wood = floor($vdata['wood']);
	$clay = floor($vdata['clay']);
	$iron = floor($vdata['iron']);
	$crop = floor($vdata['crop']);
	$maxs = $vdata['maxstore'];
	$maxc = $vdata['maxcrop'];

	// Misma producción que muestra dorf1 para cada aldea: la copia que había acá
	// se olvidaba de los oasis anexados y aplicaba el bono de oro de la madera a
	// los cuatro recursos, así que los tiempos de llenado no coincidían.
	$vresarray = $database->getResourceLevel($vid);
	$vgross = villageGrossProduction(
		$vresarray,
		villageOasisCounter($database->getOasis($vid)),
		array($session->bonus1 == 1,$session->bonus2 == 1,$session->bonus3 == 1,$session->bonus4 == 1),
		SPEED
	);
	$prod_wood = $vgross['production']['wood'];
	$prod_clay = $vgross['production']['clay'];
	$prod_iron = $vgross['production']['iron'];
	$prod_crop = $vgross['production']['crop'];

	$prod_crop -= $pop;
    $prod_crop -= $technology->getUpkeep($technology->getAllUnits($vid),0);

	$percentW = floor($wood/($maxs/100));
	$percentC = floor($clay/($maxs/100));
	$percentI = floor($iron/($maxs/100));
	$percentCr = floor($crop/($maxc/100));

	if($vdata['capital'] == 1) {$class = 'hl';} else {$class = 'hover';}
	$cr = 95;   //warning percentage
	if($percentW >= $cr) {$critW = 'crit';} else {$critW = '';}
	if($percentC >= $cr) {$critC = 'crit';} else {$critC = '';}
	if($percentI >= $cr) {$critI = 'crit';} else {$critI = '';}
	if($percentCr >= $cr) {$critCR = 'crit';} else {$critCR = '';}

	// Un recurso que no produce nunca llena el almacén: dividir por su producción
	// daba INF y colgaba la página al formatear el tiempo. Sólo cuentan los que suben.
	$fillTimes = array();
	foreach(array(array($prod_wood,$maxs,$wood),array($prod_clay,$maxs,$clay),array($prod_iron,$maxs,$iron)) as $fill) {
		if($fill[0] > 0) {
			$fillTimes[] = floor(max(0,$fill[1]-$fill[2])/$fill[0]*3600);
		}
	}
	$timer1 = $generator->getTimeFormat(empty($fillTimes) ? 0 : min($fillTimes));
	$timer2 = $generator->getTimeFormat($prod_crop > 0 ? floor(max(0,$maxc-$crop)/$prod_crop*3600) : 0);

	echo '<tr class="'.$class.'">
		<td class="vil fc"><a href="dorf1.php?newdid='.$vid.'">'.$vdata['name'].'</a></td>
		<td class="lum '.$critW.'" title="'.$wood.'/'.$maxs.'">'.$percentW.'%</td>
		<td class="clay '.$critC.'" title="'.$clay.'/'.$maxs.'">'.$percentC.'%</td>
		<td class="iron '.$critI.'" title="'.$iron.'/'.$maxs.'">'.$percentI.'%</td>
		<td class="max123"><span '.($timer1!="0:00:00"?'id="timer'.$timer.'"':'').'>'.$timer1.'</span></td>';
	if($timer1 != "0:00:00") { $timer++; }
	echo '
		<td class="crop '.$critCR.'" title="'.$crop.'/'.$maxc.'">'.$percentCr.'%</td>
		<td class="max4 lc"><span '.($timer2!="0:00:00"?'id="timer'.$timer.'"':'').'>'.$timer2.'</span></td></tr>';
	if($timer2 != "0:00:00") { $timer++; }
}
?>
</tbody></table>
