<div class="clear"></div><br />
<?php
	// Celebración en curso. Esta plantilla ya no cierra <div> que abre 24_celebrations.tpl:
	// antes emitía tres cierres sueltos que sólo aparecían cuando había una fiesta
	// corriendo, así que el resto del tiempo la página quedaba con la estructura
	// abierta (o cerrada de más cuando además estaba la celebración grande).
	if(!isset($timer)) {
		$timer = 1;
	}
	$timeleft = (int)$database->getVillageField($village->wid, 'celebration');
	if($timeleft > time()) {
		$runningType = (int)$database->getVillageField($village->wid, 'type');
		$runningName = $runningType === 2 ? 'Gran celebración' : 'Pequeña celebración';
		$finish = $generator->procMtime($timeleft);
?>
<table cellpadding="1" cellspacing="1" class="under_progress">
	<thead><tr><td>Celebración</td><td>Duración</td><td>Finaliza</td></tr></thead>
	<tbody>
		<tr>
			<td class="desc"><?php echo $runningName; ?></td>
			<td class="dur"><span id="timer<?php echo $timer; ?>"><?php echo $generator->getTimeFormat($timeleft-time()); ?></span></td>
			<td class="fin"><?php if($finish[0] !== 'hoy') { echo 'el '.htmlspecialchars((string)$finish[0],ENT_QUOTES,'UTF-8').' a las '; } echo htmlspecialchars((string)$finish[1],ENT_QUOTES,'UTF-8'); ?></td>
		</tr>
	</tbody>
</table>
<?php
		$timer++;
	}
?>
