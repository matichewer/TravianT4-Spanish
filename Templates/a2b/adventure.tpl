<?php
$targetId = isset($_GET['id']) && is_scalar($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$adventureRecord = $targetId ? $database->getAdventure($session->uid,$targetId) : false;
$adventure = $targetId ? $database->getMInfo($targetId) : false;
$herodetail = $database->getHeroData($session->uid);
$heroVillageId = is_array($herodetail) ? (int)$herodetail['wref'] : 0;
$heroVillage = $heroVillageId > 0 ? $database->getVillage($heroVillageId) : false;
$heroVillageFields = $heroVillageId > 0 ? $database->getResourceLevel($heroVillageId) : false;
$eigen = $heroVillageId > 0 ? $database->getCoor($heroVillageId) : false;
if(!is_array($eigen) || !is_array($adventureRecord) || (int)$adventureRecord['end'] !== 0 || !is_array($adventure)
	|| !is_array($herodetail) || !is_array($heroVillage) || (int)$heroVillage['owner'] !== (int)$session->uid) {
	header("Location: build.php?id=39");
	exit;
}
$from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);
$to = array('x'=>$adventure['x'], 'y'=>$adventure['y']);
$speed = $herodetail['speed'];
$time = $generator->procDistanceTime($from,$to,$speed,1,heroEquippedBootsSpeedBonus($database,$session->uid));
$founder = $heroVillage;
?>

<h1>Aventura</h1>
				<form method="POST" action="build.php">
					<input type="hidden" name="a" value="adventure" />
					<input type="hidden" name="k" value="<?php echo $session->mchecker; ?>" />
				<input type="hidden" name="c" value="5" />
				<input type="hidden" name="h" value="<?php echo $targetId; ?>" />
				<input type="hidden" name="id" value="39" />
				<input type="hidden" name="timestamp" value="<?php echo $time ?>" />
		<table class="troop_details" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td class="role"><a href="karte.php?d=<?php echo $heroVillageId; ?>&amp;c=<?php echo $generator->getMapCheck($heroVillageId); ?>"><?php echo htmlspecialchars((string)$heroVillage['name'],ENT_QUOTES,'UTF-8'); ?></a></td><td colspan="11">Aventura (<?php echo (int)$adventure['x']; ?>|<?php echo (int)$adventure['y']; ?>)</td>
		</tr>
	</thead>
	<tbody class="units">
		<tr>
			<th></th>
				<?php for($i=($session->tribe-1)*10+1;$i<=$session->tribe*10;$i++) {
					echo "<td><img src=\"img/x.gif\" class=\"unit u".$i."\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></td>";
				} 
                echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".U0."\" alt=\"".U0."\" /></td>";
                ?>
		</tr>
		<tr>
			<th>Unidades</th>
				<?php for($i=1;$i<=10;$i++) {
					echo "<td class=\"none\">0</td>";
				} ?>
				<td>1</td>
		</tr>
	</tbody>
	<tbody class="infos">
		<tr>
			<th>Llegada</th>
				<td colspan="11"><img class="clock" src="img/x.gif" alt="Duración" title="Duración" /> <?php echo $generator->getTimeFormat($time); ?></td>
		</tr>
	</tbody>
</table>
<?php
if(is_array($heroVillageFields) && (int)$heroVillageFields['f39'] >= 1){
if($herodetail['dead']==0){
	if($database->getHUnit($heroVillageId)){
?>
	<p class="button">
		<button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Comenzar aventura</div></div></button>
	</p>
<?php }else{ ?>
<button type="button" title="Tu héroe todavía no está en esta aldea." value="Comenzar aventura" class="disabled"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Comenzar aventura</div></div></button>
<?php } ?>
<?php }else{ ?>
<button type="button" title="Tu héroe está muerto." value="Comenzar aventura" class="disabled"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Comenzar aventura</div></div></button>
<?php 
}
}else{ 
?>
<button type="button" title="Necesitas construir la plaza de reuniones." value="Comenzar aventura" class="disabled"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Comenzar aventura</div></div></button>
<?php } ?>
</form>
