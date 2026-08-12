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
<input type="hidden" name="k" value="<?php echo $session->mchecker; ?>" />
			<div class="buildActionOverview trainUnits">
                <?php
	                include("19_train.tpl");
                ?>
                <div class="clear"></div></div>
	    <button type="submit" value="ok" name="s1" id="btn_train" class="startTraining"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Entrenar</div></div></button>
</form>
						
    <?php
	} else {
		echo "<b>El entrenamiento puede comenzar cuando el cuartel esté terminado.</b><br>\n";
	}
	$trainingQueueType = 1;
	include("training_queue.tpl");
?>
</div>
    <div class="clear">&nbsp;</div>
    <div class="clear"></div>
