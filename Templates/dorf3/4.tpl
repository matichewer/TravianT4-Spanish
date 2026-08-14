<?php
include('menu.tpl');
?>
<table id="culture_points" cellpadding="1" cellspacing="1">
<thead>
<tr><td> Aldea </td><td> PC/Día </td><td> Celebraciones </td><td> Unidades </td><td> Espacios </td></tr>
</thead>
<tbody>
<?php

$tribe = $session->tribe;
$style = 'style="padding: 0px 2px;"';

$siedlerID = $tribe*10;
$senatorID = $tribe*10-1;

$gesSiedler = 0;
$gesSenator = 0;

$timer = 0;
$varray = $database->getProfileVillages($session->uid); 
foreach($varray as $vil){
	$vid = $vil['wref'];
	$rawCp = (int)$database->getVillageField($vid,'cp');
	$cp = villageCulturePointsPerDay($rawCp);
	$exp = 0;
	for($i=1;$i<=3;$i++) {
		${'slot'.$i} = $database->getVillageField($vid, 'exp'.$i);
		if(${'slot'.$i} != 0) { $exp++;	}
	}
	$lvlTH = $building->getTypeLevel(24,$vid);
	$lvlRes = $building->getTypeLevel(25,$vid);
	$lvlPal = $building->getTypeLevel(26,$vid);
	$maxslots = ($lvlRes>=10?floor($lvlRes/10):0)+($lvlPal>=10?floor(($lvlPal-5)/5):0);
	// Una celebración ya vencida que la barrida todavía no cerró no es una en curso:
	// mostraba una cuenta atrás en 0:00:00 y consumía un id de timer.
	$celEnd = (int)$database->getVillageField($vid,'celebration');
	$hasCel = $celEnd > time();
	if ($hasCel) { $timer++; }

	if($vil['capital'] == 1) { $class = 'hl'; } else {$class = 'hover'; }              

	echo '<tr class="'.$class.'"><td class="vil fc"><a href="dorf1.php?newdid='.$vid.'">'.$vil['name'].'</a></td>';
	echo '<td class="cps">'.rtrim(rtrim(number_format($cp,2,',','.'),'0'),',').'</td>';
	echo '<td class="cel">'.($lvlTH>0?'<a href="build.php?newdid='.$vid.'&amp;gid=24">'.($hasCel?'<span id="timer'.$timer.'">'.$generator->getTimeFormat($celEnd-time()).'</span>':'<span class="dot">●</span>').'</a>':'<span class="none">-</span>').'</td>';
	echo '<td class="tro"><span class="">';
	$unit = $database->getUnit($vid);
	$siedler = $unit['u'.$siedlerID];
	$senator = $unit['u'.$senatorID];
	if ($siedler > 0)
	{
		echo '<img '.$style.' src="img/un/u/'.$siedlerID.'.gif" title="'.constant('U'.$siedlerID).' ('.$siedler.')" />';
	}
	if ($senator > 0)
	{
		echo '<img '.$style.' src="img/un/u/'.$senatorID.'.gif" title="'.constant('U'.$senatorID).' ('.$senator.')" />';
	}

	echo '</span></td>';
	echo '<td class="slo lc">'.$exp.'/'.$maxslots.'</td>';
	$gesexp = $gesexp + $exp;
	$gesdorf = $gesdorf + $maxslots;
	$gescp = $gescp + $rawCp;
	$gesSiedler = $gesSiedler + $siedler;
	$gesSenator = $gesSenator + $senator;
	echo '</tr>';    
}
?>

<tr><td colspan="5" class="empty"></td></tr>

<tr class="sum">
	<th class="vil">Total</th>
	<td class="cps"><?php echo number_format((int)round(villageCulturePointsPerDay($gescp)),0,',','.');?></td>
	<td class="cel none">-</td>

	<td class="tro">
	<?php
	if ($gesSiedler > 0)
	{
		echo '<img '.$style.' src="img/un/u/'.$siedlerID.'.gif" title="'.constant('U'.$siedlerID).' ('.$gesSiedler.')" />';
	}
	if ($gesSenator > 0)
	{
		echo '<img '.$style.' src="img/un/u/'.$senatorID.'.gif" title="'.constant('U'.$senatorID).' ('.$gesSenator.')" />';
	}
	?></td>
	<td class="slo"><?php echo $gesexp;echo '/';echo $gesdorf;?></td>
</tr></tbody></table>
