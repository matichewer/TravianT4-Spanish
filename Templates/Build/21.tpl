<h1 class="titleInHeader">Taller <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid21"><div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(21,4, 'gid');" class="build_logo"> 
<img class="building big white g21" src="img/x.gif" alt="Taller" title="Taller" /> </a>
En el taller se construyen armas de asedio como catapultas y arietes. Cuanto mayor sea su nivel, más rápido se producen las unidades.</div>
<?php
include("upgrade.tpl");
if ($building->getTypeLevel(21) > 0) { ?>
<div class="clear"></div>
		<form method="POST" name="snd" action="build.php">
			<input type="hidden" name="id" value="<?php echo $id; ?>" />
<input type="hidden" name="ft" value="t1" />
<input type="hidden" name="k" value="<?php echo $session->mchecker; ?>" />
<div class="buildActionOverview trainUnits">
             <?php 
            $success = 0;
            $start = ($session->tribe == 1)? 7 : (($session->tribe == 2)? 17 : 27);
            if ($session->tribe == 1){
            $start = 7;
            }else if ($session->tribe == 2){
            $start = 17;
            }else if ($session->tribe == 3){
            $start = 27;
            }else if ($session->tribe == 4){
            $start = 37;
            }else if ($session->tribe == 5){
            $start = 47;
            }
            for($i=$start;$i<=($start+1);$i++) {
                if($technology->getTech($i)) {
                echo "<div class=\"action first\">
                	<div class=\"bigUnitSection\">
						<a href=\"#\" onclick=\"return Travian.Game.iPopup($i,1);\">
							<img class=\"unitSection u".$i."Section\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\">
						</a>
						<a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom($i);\">
							<img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\">
						</a>
					</div>
					<div class=\"details\">
						<div class=\"tit\">
							<a href=\"#\" onclick=\"return Travian.Game.iPopup($i,1);\"><img class=\"unit u$i\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\"></a>
							<a href=\"#\" onclick=\"return Travian.Game.iPopup($i,1);\">".$technology->getUnitName($i)."</a>
							<span class=\"furtherInfo\">(Disponibles: ".$village->unitarray['u'.$i].")</span>
						</div>
                        <div class=\"showCosts\">
                        <span class=\"resources r1\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'u'.$i}['wood']."</span>
                        <span class=\"resources r2\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'u'.$i}['clay']."</span>
                        <span class=\"resources r3\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'u'.$i}['iron']."</span>
                        <span class=\"resources r4\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'u'.$i}['crop']."</span>
                        <span class=\"resources r5\"><img class=\"r5\" src=\"img/x.gif\" alt=\"Consumo de cereal\">".${'u'.$i}['pop']."</span>
                        <div class=\"clear\"></div>
                        <span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
                        echo $generator->getTimeFormat($technology->getUnitTrainingTime($i,21,$village->resarray['f'.$id]));
						echo "</span><div class=\"clear\"></div></div><span class=\"value\">Total</span>
						<input type=\"text\" class=\"text\" name=\"t$i\" value=\"0\" maxlength=\"4\">
                        <span class=\"value\"> / </span>
						<a href=\"#\" onClick=\"document.snd.t$i.value=".$technology->maxUnit($i)."; return false;\">".$technology->maxUnit($i)."</a>
					</div></div>
					<div class=\"clear\"></div><br />";
                      $success += 1;
                }
	            }
	            if($success == 0) {
	echo "<div class=\"none\">No hay unidades disponibles. Investiga en la academia.</div>";
	            }
            ?>
</div><div class="clear"></div>
			<?php if($success > 0) { ?><button type="submit" value="ok" name="s1" id="btn_train" class="startTraining"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Entrenar</div></div></button><?php } ?>
		</form>
<?php
	    } else {
			echo "<b>El entrenamiento puede comenzar cuando el Taller esté terminado.</b><br>\n";
		}

	$trainingQueueType = 3;
	include("training_queue.tpl");
?>
</div>

<div class="clear">&nbsp;</div>
    <div class="clear"></div>
