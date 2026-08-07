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
$varray = $database->getProfileVillages($session->uid);
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

	$timerwood = floor(($maxs-$wood)/$prod_wood*3600);
	$timerclay = floor(($maxs-$clay)/$prod_clay*3600);
	$timeriron = floor(($maxs-$iron)/$prod_iron*3600);
	$timer1 = $generator->getTimeFormat(min($timerwood,$timerclay,$timeriron));
	$timer2 = $generator->getTimeFormat(floor(($maxc-$crop)/$prod_crop*3600));

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
