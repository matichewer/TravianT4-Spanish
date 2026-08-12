<h1 class="titleInHeader">Establo <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid20">
<p class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(20,4);" class="build_logo">
<img class="building big white g20" src="img/x.gif" alt="Establo" title="Establo" /> </a>
En el establo se entrena la caballería. Cuanto mayor sea su nivel, más rápido se entrenan las tropas.<br /></p>
<?php 
include("upgrade.tpl");
?>
<?php if ($building->getTypeLevel(20) > 0) { ?>
<div class="clear"></div>
		<form method="POST" name="snd" action="build.php">
			<input type="hidden" name="id" value="<?php echo $id; ?>" />
<input type="hidden" name="ft" value="t1" />
<input type="hidden" name="k" value="<?php echo $session->mchecker; ?>" />
                <div class="buildActionOverview trainUnits">
                <?php 
                	include("20_".$session->tribe.".tpl");
                ?>
                </div>
			<div class="clear"></div>
				<?php if ($success > 0) { ?>
				    <button type="submit" value="ok" name="s1" id="btn_train" class="startTraining">
                    <div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Entrenar</div></div>
                    </button>
				<?php } ?>

		</form>
<?php
	} else {
		echo "<b>El entrenamiento puede comenzar cuando el establo esté terminado.</b><br>\n";
	}
	$trainingQueueType = 2;
	include("training_queue.tpl");
	?>
</div>
<div class="clear">&nbsp;</div>
    <div class="clear"></div>
