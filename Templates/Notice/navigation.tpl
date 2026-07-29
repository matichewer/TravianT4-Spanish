<style type="text/css">
	button.reportNavigationButton:not(.disabled):hover .btl,
	button.reportNavigationButton:not(.disabled):hover .bbl,
	button.reportNavigationButton:not(.disabled):hover .btr,
	button.reportNavigationButton:not(.disabled):hover .bbr,
	button.reportNavigationButton:not(.disabled):hover .btc {
		background-image: url("gpack/travian_Travian_4.0_41/img/round/button/green/sprite.png");
	}
</style>
<div class="footer">
	<div style="float: left;">
		<button type="button" class="reportNavigationButton<?php if(!$noticeNeighbors['previous']) { ?> disabled<?php } ?>" value="Anterior"<?php if($noticeNeighbors['previous']) { ?> onclick="window.location.href = 'berichte.php?id=<?php echo $noticeNeighbors['previous']; ?>';"<?php } else { ?> disabled="disabled"<?php } ?>>
			<div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">&laquo; Anterior</div></div>
		</button>
	</div>
	<div style="float: right;">
		<button type="button" class="reportNavigationButton<?php if(!$noticeNeighbors['next']) { ?> disabled<?php } ?>" value="Siguiente"<?php if($noticeNeighbors['next']) { ?> onclick="window.location.href = 'berichte.php?id=<?php echo $noticeNeighbors['next']; ?>';"<?php } else { ?> disabled="disabled"<?php } ?>>
			<div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Siguiente &raquo;</div></div>
		</button>
	</div>
	<div class="clear"></div>
</div>
