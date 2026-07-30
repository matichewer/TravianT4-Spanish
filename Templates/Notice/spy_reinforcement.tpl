<?php
$reinforcementTribe = (int)$spyReinforcement['tribe'];
$reinforcementStart = ($reinforcementTribe - 1) * 10 + 1;
$reinforcementHero = (int)$spyReinforcement['hero'];
$reinforcementFrom = (int)$spyReinforcement['from'];
$reinforcementOwner = (int)$spyReinforcement['owner'];
$reinforcementIsNature = !empty($spyReinforcement['nature']);
?>
<table cellpadding="0" cellspacing="0">
	<thead>
		<tr>
			<td class="role"><div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><div class="role">Refuerzo</div></div></div></td>
			<td class="troopHeadline" colspan="<?php echo $reinforcementHero > 0 ? 11 : 10; ?>">
			<?php if($reinforcementIsNature) { ?>
				Naturaleza (animales capturados)
			<?php } else {
				$reinforcementPlayerName = htmlspecialchars($database->getUserField($reinforcementOwner, 'username', 0), ENT_QUOTES, 'UTF-8');
				$reinforcementVillageName = htmlspecialchars($database->getVillageField($reinforcementFrom, 'name'), ENT_QUOTES, 'UTF-8');
			?>
				<a href="spieler.php?uid=<?php echo $reinforcementOwner; ?>"><?php echo $reinforcementPlayerName; ?></a>
				<?php echo REPORT_FROM_VIL; ?>
				<a href="karte.php?d=<?php echo $reinforcementFrom; ?>&amp;c=<?php echo $generator->getMapCheck($reinforcementFrom); ?>"><?php echo $reinforcementVillageName; ?></a>
			<?php } ?>
			</td>
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
