<?php

$targetId = isset($_GET['id']) && is_scalar($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$founder = $database->getVillage($village->wid);
$newvillage = $targetId ? $database->getMInfo($targetId) : false;
if(!is_array($newvillage) || (int)$newvillage['occupied'] !== 0 || (int)$newvillage['oasistype'] !== 0 || (int)$newvillage['fieldtype'] <= 0) {
	header("Location: build.php?id=39");
	exit;
}
$eigen = $database->getCoor($village->wid);
$from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);
$to = array('x'=>$newvillage['x'], 'y'=>$newvillage['y']);
$time = $generator->procDistanceTime($from,$to,300,0);

echo '<pre>';
//print_r($founder);
echo '</pre>';
?>

<h1>Fundar nueva aldea</h1>
<!--<p>De kolonisten kunnen nog niet vertrekken.<br> Voor het stichten van een nieuw dorp is er nog 750 grondstoffen hout, klei, ijzer en graan nodig.</p>-->
				<form method="POST" action="build.php">
					<input type="hidden" name="a" value="new" />
					<input type="hidden" name="k" value="<?php echo $session->mchecker; ?>" />
				<input type="hidden" name="c" value="5" />
				<input type="hidden" name="s" value="<?php echo $targetId; ?>" />
				<input type="hidden" name="id" value="39" />
				<input type="hidden" name="timestamp" value="<?php echo $time ?>" />
		<table class="troop_details" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td class="role"><a href="karte.php?d=<?php echo (int)$founder['0']; ?>&amp;c=<?php echo $generator->getMapCheck($founder['0']); ?>"><?php echo htmlspecialchars((string)$database->getUserField($session->uid,'username',0),ENT_QUOTES,'UTF-8'); ?></a></td><td colspan="10"><a href="karte.php?d=<?php echo $targetId; ?>&amp;c=<?php echo $generator->getMapCheck($targetId); ?>">Nueva aldea (<?php echo (int)$newvillage['x']; ?>|<?php echo (int)$newvillage['y']; ?>)</a></td>
		</tr>
	</thead>
	<tbody class="units">
		<tr>
			<th>&nbsp;</th>
				<?php for($i=($session->tribe-1)*10+1;$i<=$session->tribe*10;$i++) {
					echo "<td><img src=\"img/x.gif\" class=\"unit u".$i."\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></td>";
				} ?>
		</tr>
		<tr>
			<th>Tropas</th>
				<?php for($i=1;$i<=9;$i++) {
					echo "<td class=\"none\">0</td>";
				} ?>
				<td>3</td>
		</tr>
	</tbody>
	<tbody class="infos">
		<tr>
			<th>Duración</th>
				<td colspan="10"><img class="clock" src="img/x.gif" alt="duración" title="duración" /> <?php echo $generator->getTimeFormat($time); ?></td>
		</tr>
	</tbody>
	<tbody class="infos">
		<tr>
			<th>Recursos</th>
				<td colspan="10">
				<img class="r1" src="img/x.gif" alt="Madera" title="Madera" /> 750 | 
				<img class="r2" src="img/x.gif" alt="Barro" title="Barro" /> 750 | 
				<img class="r3" src="img/x.gif" alt="Hierro" title="Hierro" /> 750 | 
				<img class="r4" src="img/x.gif" alt="Cereal" title="Cereal" /> 750 </td>
		</tr>
	</tbody>
</table>

<p class="button">
<?php
$cps = (int)$database->getUserField($session->uid, 'cp', 0);
$pendingSettlements = $database->getPendingSettlementCountByOwner($session->uid);
$cultureEligibility = travianCultureExpansionEligibility($cps,count($session->villages),$pendingSettlements,CP);
$need_cps = $cultureEligibility['requiredPoints'];
$wood = round($village->awood);
$clay = round($village->aclay);
$iron = round($village->airon);
$crop = round($village->acrop);
if($cultureEligibility['eligible']) {
	if($wood>=750 && $clay>=750 && $iron>=750 && $crop>=750){
?>

<button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Fundar nueva aldea</div></div></button>
<?php
	}else {
		echo "<span class=\"none\">No hay suficientes recursos para fundar una nueva aldea.</span>";
	}
} else {
  $cultureRequirement = ($need_cps === null) ? "No hay un umbral de cultura configurado para otra aldea." : number_format($cps, 0, ',', '.')."/".number_format($need_cps, 0, ',', '.')." puntos de cultura";
  print "<span class=\"none\">$cultureRequirement</span>";
}
?>
</form>
</p>
