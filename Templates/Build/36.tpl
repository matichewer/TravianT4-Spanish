<h1 class="titleInHeader">Trampero <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid36">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(36,4);" class="build_logo">
        <img class="building big white g36" src="img/x.gif" alt="Trampero" title="Trampero"></a>
     El trampero protege tu aldea con trampas bien escondidas. Así, los enemigos incautos pueden quedar atrapados y ya no podrán dañar tu aldea.</div>

<table cellpadding="1" cellspacing="1" id="build_value">
	<tr>
		<th>Máximo de trampas actuales:</th>

		<td><b><?php echo $bid36[$village->resarray['f'.$id]]['attri']*TRAPPER_CAPACITY; ?></b> trampas</td>
	</tr>
	<tr>
	<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
		$next = $village->resarray['f'.$id]+1+$loopsame+$doublebuild+$master;
		if($next<=20){
        ?>
		<th>Máximo de trampas en el nivel <?php echo $next; ?>:</th>
		<td><b><?php echo $bid36[$next]['attri']*TRAPPER_CAPACITY; ?></b> trampas</td>
        <?php
            }else{
		?>
		<th>Máximo de trampas en el nivel 20:</th>
		<td><b><?php echo $bid36[20]['attri']*TRAPPER_CAPACITY; ?></b> trampas</td>
		<?php
			}
			}
        ?>

	</tr>
</table>
<p>Actualmente tienes <b><?php echo $village->unitarray['u99']; ?></b> trampas, <b><?php echo $village->unitarray['u99o']; ?></b> de las cuales están ocupadas.</p>
<?php 
include("upgrade.tpl");
?>
<div class="clear"></div>
<?php if ($building->getTypeLevel(36) > 0) { ?>
<form method="POST" name="snd" action="build.php">
				<input type="hidden" name="id" value="<?php echo $id; ?>" />
				<input type="hidden" name="ft" value="t1" />
			<?php
			$trainlist = $technology->getTrainingList(8);
			foreach($trainlist as $train) {
			$train_amt += $train['amt'];
			}
			
			$max = $technology->maxUnit(99,false);
			$max1 = 0;
			for($i=19;$i<41;$i++){
			if($village->resarray['f'.$i.'t'] == 36){
			$max1 += $bid36[$village->resarray['f'.$i]]['attri']*TRAPPER_CAPACITY;
			}
			}
			if($max > $max1 - ($village->unitarray['u99'] + $train_amt)){
			$max = $max1 - ($village->unitarray['u99'] + $train_amt);
			}
			if($max < 0){
			$max = 0;
			}
			?>
		<tr>
			<div class="buildActionOverview trainUnits">
			<div class="action first">
			<div class="details">
			<div class="tit"><a href="#" onclick="return Travian.Game.iPopup(36,4,'gid')"><img class="unit u99" src="img/x.gif" alt="Trampa"></a> <a href="#" onclick="return Travian.Game.iPopup(36,4,'gid')">Trampas</a>
			<span class="furtherInfo">(Disponibles: <?php echo $village->unitarray['u99']; ?>)</span>
			</div>

			<div class="showCosts">
			<span class="resources r1"><img class="r1" src="img/x.gif" alt="Madera"><?php echo $u99['wood']; ?></span>
			<span class="resources r2"><img class="r2" src="img/x.gif" alt="Barro"><?php echo $u99['clay']; ?></span>
			<span class="resources r3"><img class="r3" src="img/x.gif" alt="Hierro"><?php echo $u99['iron']; ?></span>
			<span class="resources r4"><img class="r4" src="img/x.gif" alt="Cereal"><?php echo $u99['crop']; ?></span>
			<span class="resources r5"><img class="r5" src="img/x.gif" alt="Consumo de cereal"><?php echo $u99['pop']; ?></span>
			<div class="clear"></div>
			<span class="clocks"><img class="clock" src="img/x.gif" alt="Duración"><?php $dur=$generator->getTimeFormat(round(${'u99'}['time'] * ($bid19[$village->resarray['f'.$id]]['attri']*TRAPPER_CAPACITY / 100) / SPEED)); echo ($dur=="0:00:00")? "0:00:01":$dur; ?></span><div class="clear"></div></div>
			<span class="value"></span> <input type="text" class="text" name="t99" value="0" maxlength="4"><span class="value">
			/ </span> <a href="#" onClick="document.snd.t99.value=<?php echo $max; ?>; return false;"><?php echo $max; ?></a>
			</div>
			<div class="clear"></div>
			</div>
			</div>
		</tr>
<button type="submit" value="ok" name="s1" id="s1" class="startBuild"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Construir</div></div></button></form>
	<?php
	} else {
		echo "<b>El entrenamiento puede comenzar cuando el trampero esté terminado.</b><br>\n";
	}
    if(count($trainlist) > 0) {
    	echo "
    <table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\">
		<thead><tr>
			<td>Entrenamiento</td>
			<td>Duración</td>
			<td>Finaliza</td>
		</tr></thead>
		<tbody>";
		$TrainCount = 0;
        foreach($trainlist as $train) {
			$TrainCount++;
	        echo "<tr><td class=\"desc\">";
			echo "<img class=\"unit u".$train['unit']."\" src=\"img/x.gif\" alt=\"".U99."\" title=\"".U99."\" />";
			echo $train['amt']." ".U99."</td><td class=\"dur\">";
			if ($TrainCount == 1 ) {
				$NextFinished = $generator->getTimeFormat(($train['timestamp']-time())-($train['amt']-1)*$train['eachtime']);
				echo "<span id=timer1>".$generator->getTimeFormat($train['timestamp']-time())."</span>";
			} else {
				echo $generator->getTimeFormat($train['eachtime']*$train['amt']);
			}
			echo "</td><td class=\"fin\">";
			$time = $generator->procMTime($train['timestamp']);
			if($time[0] != "hoy") {
				echo "el ".$time[0]." a las ";
            }
			echo $time[1];
		} ?>
		</tr><tr class="next"><td colspan="3">La próxima unidad estará lista en <span id="timer2"><?php echo $NextFinished; ?></span></td></tr>
		</tbody></table>
    <?php } ?>
</p></div>
