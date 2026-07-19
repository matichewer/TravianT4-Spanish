<h1 class="titleInHeader">Cuartel <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid19">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(19,4);" class="build_logo">
	<img class="building big white g19" src="img/x.gif" alt="Cuartel" title="Cuartel" />
</a>
Toda la infantería se entrena en el cuartel. Cuanto mayor sea el nivel del cuartel, más rápido se entrenan las tropas.</div>
<?php
include("upgrade.tpl");
?>
<div class="clear"></div>
<?php if ($building->getTypeLevel(19) > 0) { ?>
<form method="POST" name="snd" action="build.php">
				<input type="hidden" name="id" value="<?php echo $id; ?>" />
				<input type="hidden" name="ft" value="t1" />
			<div class="buildActionOverview trainUnits">
                <?php
	                include("19_train.tpl");
                ?>
                <div class="clear"></div></div>
    <button type="submit" value="ok" name="s1" id="btn_train" value="ok" class="startTraining"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Entrenar</div></div></button>
</form>
						
    <?php
	} else {
		echo "<b>El entrenamiento puede comenzar cuando el cuartel esté terminado.</b><br>\n";
	}
    $trainlist = $technology->getTrainingList(1);
    if(count($trainlist) > 0) {
    //$timer = 2*count($trainlist);
    	echo "
        <h4 class=\"round spacer\">Entrenamiento</h4>
    <table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\">
		<thead><tr>
			<td>Unidad</td>
			<td>Duración</td>
			<td>Finaliza</td>
		</tr></thead>
		<tbody>";
		$TrainCount = 0;
        foreach($trainlist as $train) {
			$TrainCount++;
	        echo "<tr><td class=\"desc\">";
			echo "<img class=\"unit u".$train['unit']."\" src=\"img/x.gif\" alt=\"".$train['name']."\" title=\"".$train['name']."\" />";
			echo $train['amt']." ".$train['name']."</td><td class=\"dur\">";
			if ($TrainCount == 1 ) {
				$NextFinished = $generator->getTimeFormat($train['timestamp2']-time());
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
		</tr><tr class="next"><td colspan="3">La próxima unidad estará lista en <span id="timer2"><?php echo $NextFinished; ?></span> </td></tr>
		</tbody></table>

<?php } ?>
</p></div>
    <div class="clear">&nbsp;</div>
    <div class="clear"></div>