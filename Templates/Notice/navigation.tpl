<div class="footer">
	<div style="float: left;">
		<button type="button" value="Anterior"<?php if($noticeNeighbors['previous']) { ?> onclick="window.location.href = 'berichte.php?id=<?php echo $noticeNeighbors['previous']; ?>';"<?php } else { ?> disabled="disabled"<?php } ?>>
			<div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">&laquo; Anterior</div></div>
		</button>
	</div>
	<div style="float: right;">
		<button type="button" value="Siguiente"<?php if($noticeNeighbors['next']) { ?> onclick="window.location.href = 'berichte.php?id=<?php echo $noticeNeighbors['next']; ?>';"<?php } else { ?> disabled="disabled"<?php } ?>>
			<div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Siguiente &raquo;</div></div>
		</button>
	</div>
	<div class="clear"></div>
</div>
