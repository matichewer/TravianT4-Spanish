<?php
$unknownDefenderUid = isset($dataarray[30]) ? (int)$dataarray[30] : 0;
$unknownDefenderWref = isset($dataarray[31]) ? (int)$dataarray[31] : 0;
$unknownDefenderVillage = isset($dataarray[32]) ? stripslashes($dataarray[32]) : '';
$unknownDefenderMap = $unknownDefenderWref > 0 ? $database->getMInfo($unknownDefenderWref) : false;
$unknownDefenderTribe = is_array($unknownDefenderMap) && (int)$unknownDefenderMap['fieldtype'] === 0
	? 4
	: ($unknownDefenderUid > 0 ? (int)$database->getUserField($unknownDefenderUid, 'tribe', 0) : 0);

if($unknownDefenderTribe < 1 || $unknownDefenderTribe > 5) {
	$unknownDefenderTribe = 1;
}

$unknownDefenderStart = array(1 => 1, 2 => 11, 3 => 21, 4 => 31, 5 => 41);
$unknownDefenderHasHero = $unknownDefenderTribe !== 4;
$unknownDefenderColumns = $unknownDefenderHasHero ? 11 : 10;
?>
<table cellpadding="0" cellspacing="0">
	<thead>
		<tr>
			<td class="role">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><div class="role"><?php echo REPORT_DEFENDER; ?></div></div></div>
			</td>
			<td class="troopHeadline" colspan="<?php echo $unknownDefenderColumns; ?>">
				<?php if($unknownDefenderTribe === 4) { ?>
					<span class="none2">Naturaleza</span>
				<?php } else { ?>
					<a href="spieler.php?uid=<?php echo $unknownDefenderUid; ?>"><?php echo htmlspecialchars($database->getUserField($unknownDefenderUid, 'username', 0), ENT_QUOTES, 'UTF-8'); ?></a>
				<?php } ?>
				<?php echo REPORT_FROM_VIL; ?>
				<a href="karte.php?d=<?php echo $unknownDefenderWref; ?>&amp;c=<?php echo $generator->getMapCheck($unknownDefenderWref); ?>"><?php echo htmlspecialchars($unknownDefenderVillage, ENT_QUOTES, 'UTF-8'); ?></a>
			</td>
		</tr>
	</thead>
	<tbody class="units">
		<tr>
			<th class="coords"></th>
			<?php
			$unknownDefenderLastUnit = $unknownDefenderStart[$unknownDefenderTribe] + 9;
			for($unitId = $unknownDefenderStart[$unknownDefenderTribe]; $unitId <= $unknownDefenderLastUnit; $unitId++) {
				$lastClass = (!$unknownDefenderHasHero && $unitId === $unknownDefenderLastUnit) ? ' last' : '';
				echo '<td class="uniticon'.$lastClass.'"><img src="img/x.gif" class="unit u'.$unitId.'" title="'.$technology->getUnitName($unitId).'" alt="'.$technology->getUnitName($unitId).'" /></td>';
			}
			if($unknownDefenderHasHero) {
				echo '<td class="uniticon last"><img src="img/x.gif" class="unit uhero" title="'.$technology->getUnitName(51).'" alt="'.$technology->getUnitName(51).'" /></td>';
			}
			?>
		</tr>
	</tbody>
	<tbody class="units">
		<tr>
			<th><?php echo REPORT_TROOPS; ?></th>
			<?php for($column = 1; $column <= $unknownDefenderColumns; $column++) { ?>
				<td class="unit none<?php echo $column === $unknownDefenderColumns ? ' last' : ''; ?>">?</td>
			<?php } ?>
		</tr>
	</tbody>
	<tbody class="units last">
		<tr>
			<th><?php echo REPORT_CASUALTIES; ?></th>
			<?php for($column = 1; $column <= $unknownDefenderColumns; $column++) { ?>
				<td class="unit none<?php echo $column === $unknownDefenderColumns ? ' last' : ''; ?>">?</td>
			<?php } ?>
		</tr>
	</tbody>
</table>
