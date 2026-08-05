<?php
// Bloques de defensores del informe de batalla.
//
// Si el informe trae el desglose por jugador (marcador `defenders-v1`) y lo está mirando
// uno de los defensores, se arma un bloque por bando: el dueño de la aldea con sus tropas
// propias y cada refuerzo con su nombre y su aldea. Antes todo se agrupaba por tribu, así
// que dos aliados de la misma tribu caían en un solo bloque sumado y ninguno decía de
// quién era. Para los informes viejos, y para el atacante, se usa el desglose por tribu de
// siempre.
if(!empty($reportDefenderParties) && !empty($reportViewerIsDefender)) {
	$attackedVillage = isset($dataarray[31]) ? (int)$dataarray[31] : 0;
	foreach($reportDefenderParties as $defenderParty) {
		$partyStart = ($defenderParty['tribe'] - 1) * 10 + 1;
		$partyHasHero = $defenderParty['sent'][11] > 0;
		$partyIsVillageOwner = $defenderParty['wref'] === $attackedVillage;
		$partyName = $defenderParty['uid'] > 0
			? $database->getUserField($defenderParty['uid'], 'username', 0)
			: '';
		$partyVillage = $defenderParty['wref'] > 0
			? $database->getVillageField($defenderParty['wref'], 'name')
			: '';
		?>
		<table cellpadding="0" cellspacing="0">
			<thead>
				<tr>
					<td class="role"><div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><div class="role"><?php echo REPORT_DEFENDER; ?></div></div></div></td>
					<td class="troopHeadline" colspan="<?php echo $partyHasHero ? 11 : 10; ?>">
					<?php
					// Los animales enjaulados defienden como un refuerzo con `from = 0`, así
					// que no tienen jugador ni aldea a los que enlazar. Sin esto el bloque
					// salía como "Refuerzo:" y nada más.
					$partyIsNature = $defenderParty['uid'] <= 0 && $defenderParty['wref'] <= 0;
					$partyPlainLabel = '';
					if($partyIsNature) {
						$partyPlainLabel = REPORT_NATURE_REINF;
					} elseif($partyName === '') {
						$partyPlainLabel = $partyIsVillageOwner ? REPORT_DEFENDER : REPORT_REINF;
					}
					// El prefijo sólo va si después viene algo que lo acompañe.
					if(!$partyIsVillageOwner && $partyPlainLabel !== REPORT_REINF) {
						echo REPORT_REINF.': ';
					}
					if($partyPlainLabel !== '') {
						echo htmlspecialchars($partyPlainLabel, ENT_QUOTES, 'UTF-8');
					} else {
						echo '<a href="spieler.php?uid='.$defenderParty['uid'].'">'
							.htmlspecialchars(stripslashes($partyName), ENT_QUOTES, 'UTF-8').'</a>';
						if($partyVillage !== '') {
							echo ' '.REPORT_FROM_VIL.' <a href="karte.php?d='.$defenderParty['wref']
								.'&amp;c='.$generator->getMapCheck($defenderParty['wref']).'">'
								.htmlspecialchars(stripslashes($partyVillage), ENT_QUOTES, 'UTF-8').'</a>';
						}
					}
					?>
					</td>
				</tr>
			</thead>

			<tbody class="units"><tr>
			<th class="coords"></th>
<?php
		for($position = 1; $position <= 10; $position++) {
			$unit = $partyStart + $position - 1;
			$last = ($position === 10 && !$partyHasHero) ? ' last' : '';
			echo "<td class=\"uniticon".$last."\"><img src=\"img/x.gif\" class=\"unit u$unit\" title=\"".$technology->getUnitName($unit)."\" alt=\"".$technology->getUnitName($unit)."\" /></td>";
		}
		if($partyHasHero) {
			echo "<td class=\"uniticon last\"><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".U0."\" alt=\"".U0."\" /></td>";
		}
		echo "</tr></tbody><tbody class=\"units\"><tr><th>".REPORT_TROOPS."</th>";
		for($position = 1; $position <= 10; $position++) {
			$last = ($position === 10 && !$partyHasHero) ? ' last' : '';
			$amount = $defenderParty['sent'][$position];
			$none = $amount == 0 ? ' none' : '';
			echo "<td class=\"unit".$none.$last."\">".$amount."</td>";
		}
		if($partyHasHero) {
			echo "<td class=\"unit last\">".$defenderParty['sent'][11]."</td>";
		}
		echo "</tr></tbody>";
		echo "<tbody class=\"units last\"><tr><th>".REPORT_CASUALTIES."</th>";
		for($position = 1; $position <= 10; $position++) {
			$last = ($position === 10 && !$partyHasHero) ? ' last' : '';
			$amount = $defenderParty['dead'][$position];
			$none = $amount == 0 ? ' none' : '';
			echo "<td class=\"unit".$none.$last."\">".$amount."</td>";
		}
		if($partyHasHero) {
			$amount = $defenderParty['dead'][11];
			$none = $amount == 0 ? ' none' : '';
			echo "<td class=\"unit".$none." last\">".$amount."</td>";
		}
		echo "</tr></tbody>";
		?>
		</table>
		<?php
	}
} else {
	$faild = isset($faild) ? $faild : false;
	$targettribe = $dataarray['33'];
	$ddd = '36';
	include "Templates/Notice/tribe_".$targettribe.".tpl";
	for($s = 1; $s <= 5; $s++) {
		if($s != $targettribe) {
			if($dataarray[$ddd] == 1) {
				include "Templates/Notice/tribe_".$s.".tpl";
			}
		}
		$ddd += '23';
	}
}
?>
