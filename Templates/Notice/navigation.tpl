<div class="footer">
	<div class="paginator">
		<?php if($noticeNeighbors['previous']) { ?>
			<a class="previous" href="berichte.php?id=<?php echo $noticeNeighbors['previous']; ?>" title="Informe anterior">
				<img src="img/x.gif" alt="Informe anterior">
			</a>
		<?php } else { ?>
			<img src="img/x.gif" class="previous disabled" alt="No hay un informe anterior" title="No hay un informe anterior">
		<?php } ?>
		<span>Anterior</span>
		<span>&nbsp;|&nbsp;</span>
		<span>Siguiente</span>
		<?php if($noticeNeighbors['next']) { ?>
			<a class="next" href="berichte.php?id=<?php echo $noticeNeighbors['next']; ?>" title="Informe siguiente">
				<img src="img/x.gif" alt="Informe siguiente">
			</a>
		<?php } else { ?>
			<img src="img/x.gif" class="next disabled" alt="No hay un informe siguiente" title="No hay un informe siguiente">
		<?php } ?>
	</div>
	<div class="clear"></div>
</div>
