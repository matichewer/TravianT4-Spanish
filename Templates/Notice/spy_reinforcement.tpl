<?php
// Bloque de refuerzos de un informe de espionaje: un cuadro por tribu con el total de
// tropas ajenas que defienden la aldea. A propósito no dice de qué jugador ni de qué
// aldea vienen — el espía cuenta lo que hay, no averigua quién lo mandó.
$reinforcementTribe = (int)$spyReinforcement['tribe'];
$reinforcementStart = ($reinforcementTribe - 1) * 10 + 1;
$reinforcementHero = (int)$spyReinforcement['hero'];
$reinforcementIsNature = !empty($spyReinforcement['nature']);
$reinforcementLabel = $reinforcementIsNature
	? REPORT_NATURE_REINF
	: constant('TRIBE'.$reinforcementTribe);
?>
<table cellpadding="0" cellspacing="0">
	<thead>
		<tr>
			<td class="role"><div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><div class="role">Refuerzo</div></div></div></td>
			<td class="troopHeadline" colspan="<?php echo $reinforcementHero > 0 ? 11 : 10; ?>"><?php echo htmlspecialchars($reinforcementLabel, ENT_QUOTES, 'UTF-8'); ?></td>
		</tr>
	</thead>
	<tbody class="units">
		<tr>
			<th class="coords"></th>
			<?php for($unit = $reinforcementStart; $unit < $reinforcementStart + 10; $unit++) {
				$isLast = $unit === $reinforcementStart + 9 && $reinforcementHero === 0;
				$unitName = $technology->getUnitName($unit);
			?>
				<td class="uniticon<?php echo $isLast ? ' last' : ''; ?>"><img src="img/x.gif" class="unit u<?php echo $unit; ?>" title="<?php echo $unitName; ?>" alt="<?php echo $unitName; ?>" /></td>
			<?php } ?>
			<?php if($reinforcementHero > 0) { ?>
				<td class="uniticon last"><img src="img/x.gif" class="unit uhero" title="<?php echo U0; ?>" alt="<?php echo U0; ?>" /></td>
			<?php } ?>
		</tr>
	</tbody>
	<tbody class="units last">
		<tr>
			<th><?php echo REPORT_TROOPS; ?></th>
			<?php for($position = 0; $position < 10; $position++) {
				$amount = (int)$spyReinforcement['units'][$position];
				$isLast = $position === 9 && $reinforcementHero === 0;
			?>
				<td class="unit<?php echo $amount === 0 ? ' none' : ''; ?><?php echo $isLast ? ' last' : ''; ?>"><?php echo $amount; ?></td>
			<?php } ?>
			<?php if($reinforcementHero > 0) { ?>
				<td class="unit last"><?php echo $reinforcementHero; ?></td>
			<?php } ?>
		</tr>
	</tbody>
</table>
