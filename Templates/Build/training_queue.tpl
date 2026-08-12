<?php
$trainlist = $technology->getTrainingList((int)$trainingQueueType);
if(count($trainlist) > 0) {
	$now = time();
	$nextFinished = $generator->getTimeFormat(max(0,(int)$trainlist[0]['timestamp2'] - $now));
?>
<h4 class="round spacer">Entrenamiento</h4>
<table cellpadding="1" cellspacing="1" class="under_progress">
	<thead>
		<tr>
			<td>Unidad</td>
			<td>Duración</td>
			<td>Finaliza</td>
		</tr>
	</thead>
	<tbody>
	<?php foreach($trainlist as $index => $train) {
		$name = htmlspecialchars((string)$train['name'],ENT_QUOTES,'UTF-8');
		$duration = $index === 0
			? max(0,(int)$train['timestamp'] - $now)
			: max(0,(int)$train['eachtime'] * (int)$train['amt']);
		$finish = $generator->procMTime((int)$train['timestamp']);
	?>
		<tr>
			<td class="desc"><img class="unit u<?php echo (int)$train['unit']; ?>" src="img/x.gif" alt="<?php echo $name; ?>" title="<?php echo $name; ?>" /><?php echo (int)$train['amt'].' '.$name; ?></td>
			<td class="dur"><?php if($index === 0) { ?><span id="timer1"><?php } echo $generator->getTimeFormat($duration); if($index === 0) { ?></span><?php } ?></td>
			<td class="fin"><?php if($finish[0] !== 'hoy') { echo 'el '.htmlspecialchars((string)$finish[0],ENT_QUOTES,'UTF-8').' a las '; } echo htmlspecialchars((string)$finish[1],ENT_QUOTES,'UTF-8'); ?></td>
		</tr>
	<?php } ?>
		<tr class="next"><td colspan="3">La próxima unidad estará lista en <span id="timer2"><?php echo $nextFinished; ?></span></td></tr>
	</tbody>
</table>
<?php } ?>
