<?php
/**
 * Resumen de aldeas -> Tropas. Dos pestañas, como en el T4 oficial:
 *
 *   ?s=5        "Tropas propias"  — matriz aldea x unidad con las tropas del jugador que
 *                                   están en cada aldea, más un total por tipo.
 *
 * Las aldeas van en orden de fundación en las dos pestañas, igual que el cartel lateral.
 *   ?s=5&su=2   "Tropas en aldeas" — lo que hay DENTRO de cada aldea propia, incluidos los
 *                                   refuerzos de otros jugadores y la guarnición de los
 *                                   oasis anexados, con el consumo de cereal.
 *
 * La segunda pestaña era un <span> muerto: no tenía enlace ni contenido. Las tropas que
 * están fuera de la aldea (de refuerzo, en camino) no se muestran en ninguna de las dos,
 * igual que en el T4 oficial: se ven en la plaza de reuniones. La agregación vive entera
 * en GameEngine/TroopOverview.php; acá sólo se imprime.
 */

include('menu.tpl');

$troopTab = (isset($_GET['su']) && (int)$_GET['su'] === 2) ? 2 : 1;
// En orden de fundación, igual que el cartel lateral. Ver GameEngine/VillageOverview.php.
$villageIds = array();
$villageRows = array();
foreach(villageOverviewVillages($session->uid) as $vil) {
	$wref = (int)$vil['wref'];
	$villageIds[] = $wref;
	$villageRows[$wref] = $vil;
}
$tribe = (int)$session->tribe;
$ownRange = troopOverviewTribeRange($tribe);
if($ownRange === null) {
	$ownRange = array(1,10);
}
list($ownStart,$ownEnd) = $ownRange;

// Una celda de tropas: el gpack pinta en gris las que están a cero (class "none").
$troopCell = function($amount) {
	$amount = (int)$amount;
	return '<td class="'.($amount != 0 ? '' : 'none').'">'.number_format($amount,0,',','.').'</td>';
};
$unitIcons = function($start, $end, $hero = true) use ($technology) {
	$html = '';
	for($id = (int)$start; $id <= (int)$end; $id++) {
		$name = htmlspecialchars((string)$technology->getUnitName($id),ENT_QUOTES,'UTF-8');
		$html .= '<td><img class="unit u'.$id.'" src="img/x.gif" title="'.$name.'" alt="'.$name.'"></td>';
	}
	if($hero) {
		$name = htmlspecialchars((string)$technology->getUnitName(52),ENT_QUOTES,'UTF-8');
		$html .= '<td><img class="unit uhero" src="img/x.gif" title="'.$name.'" alt="'.$name.'"></td>';
	}
	return $html;
};
?>
<div class="contentNavi tabNavi">
	<div class="container <?php echo $troopTab == 1 ? 'active' : 'normal'; ?>">
		<div class="background-start">&nbsp;</div>
		<div class="background-end">&nbsp;</div>
		<div class="content"><a href="dorf3.php?s=5"><span class="tabItem">Tropas propias</span></a></div>
	</div>
	<div class="container <?php echo $troopTab == 2 ? 'active' : 'normal'; ?>">
		<div class="background-start">&nbsp;</div>
		<div class="background-end">&nbsp;</div>
		<div class="content"><a href="dorf3.php?s=5&amp;su=2"><span class="tabItem">Tropas en aldeas</span></a></div>
	</div>
	<div class="clear"></div>
</div>
<?php
if($troopTab == 1) {
	// Las tropas del jugador que están en cada aldea: las de la aldea más las que mandó
	// de refuerzo desde otra aldea suya y las de sus oasis. Lo que está fuera de sus
	// aldeas (refuerzo a un aliado, en camino) se ve en la plaza de reuniones, como en el
	// T4 oficial. Ver la cabecera de GameEngine/TroopOverview.php.
	$own = troopOverviewOwnTroops($villageIds,$tribe,$session->uid);
	$totals = troopOverviewEmptyUnits($ownStart,$ownEnd);

	echo '<table cellpadding="1" cellspacing="1" id="troops"><thead><tr><th>Aldea</th>'
		.$unitIcons($ownStart,$ownEnd).'</tr></thead><tbody>';

	foreach($villageIds as $vid) {
		$vil = $villageRows[$vid];
		$units = isset($own[$vid]) ? $own[$vid] : troopOverviewEmptyUnits($ownStart,$ownEnd);
		$class = $vil['capital'] == 1 ? 'hl' : 'hover';

		echo '<tr class="'.$class.'"><th class="vil fc"><a href="dorf1.php?newdid='.$vid.'">'.$vil['name'].'</a></th>';
		for($id = $ownStart; $id <= $ownEnd; $id++) {
			echo $troopCell($units['u'.$id]);
		}
		echo $troopCell($units['hero']);
		echo '</tr>';

		$totals = troopOverviewSumUnits($totals,$units);
	}

	echo '<tr><td colspan="12" class="empty"></td></tr>';
	echo '<tr class="sum"><th class="vil fc">Total</th>';
	for($id = $ownStart; $id <= $ownEnd; $id++) {
		echo $troopCell($totals['u'.$id]);
	}
	echo $troopCell($totals['hero']);
	echo '</tr>';
	echo '</tbody></table>';
} else {
	$garrisons = troopOverviewVillageGarrisons($villageIds,$tribe,$session->uid);

	$labelRefs = array();
	$ownerIds = array();
	foreach($garrisons as $groups) {
		foreach($groups as $group) {
			$labelRefs[] = $group['where'];
			$labelRefs[] = $group['from'];
			if(!empty($group['owner'])) {
				$ownerIds[] = $group['owner'];
			}
		}
	}
	$places = troopOverviewResolvePlaces($labelRefs);
	$coords = troopOverviewResolveCoords($labelRefs);
	$users = troopOverviewResolveUsers($ownerIds);

	foreach($villageIds as $vid) {
		$vil = $villageRows[$vid];
		$groups = isset($garrisons[$vid]) ? $garrisons[$vid] : array();
		$class = $vil['capital'] == 1 ? 'hl' : 'hover';
		$upkeep = 0;

		echo '<table cellpadding="1" cellspacing="1" class="vil_troops"><thead><tr class="'.$class.'">'
			.'<th class="vil fc" colspan="12"><a href="dorf1.php?newdid='.$vid.'">'.$vil['name'].'</a></th>'
			.'</tr></thead><tbody>';

		if(empty($groups)) {
			echo '<tr><th>Tropas</th><td colspan="11" class="none">Sin tropas en la aldea</td></tr>';
		}

		$printedTribe = null;
		foreach($groups as $group) {
			// Cada tribu trae sus propias diez columnas: un refuerzo galo en una aldea
			// romana no se puede imprimir bajo los iconos romanos. La fila de iconos se
			// repite sólo cuando cambia la tribu.
			if($printedTribe !== $group['tribe']) {
				echo '<tr><th>&nbsp;</th>'.$unitIcons($group['start'],$group['end']).'</tr>';
				$printedTribe = $group['tribe'];
			}

			if($group['kind'] === 'own') {
				$label = 'Tropas propias';
			} elseif($group['kind'] === 'caged') {
				$label = 'Animales enjaulados';
			} elseif($group['kind'] === 'oasis') {
				$label = 'En el oasis '.troopOverviewPlaceLabel($group['where'],$places,$coords);
			} elseif((int)$group['from'] === 0) {
				$label = 'Tropas de la Naturaleza';
			} else {
				$owner = !empty($group['owner']) && isset($users[$group['owner']])
					? htmlspecialchars($users[$group['owner']]['username'],ENT_QUOTES,'UTF-8')
					: 'jugador desconocido';
				$label = 'Refuerzo de '.troopOverviewPlaceLabel($group['from'],$places,$coords).' ('.$owner.')';
			}

			echo '<tr><th>'.$label.'</th>';
			for($id = $group['start']; $id <= $group['end']; $id++) {
				echo $troopCell($group['units']['u'.$id]);
			}
			echo $troopCell($group['units']['hero']);
			echo '</tr>';

			// El consumo se pide con la tribu y la aldea del grupo, no con las del jugador
			// que mira: si no, getUpkeep() cobra por el rango equivocado y busca el
			// abrevadero en la aldea actual en lugar de en ésta.
			$upkeep += (int)$technology->getUpkeep($group['units'],$group['tribe'],$vid);
		}

		echo '</tbody><tbody class="upkeep"><tr><th>Consumo de cereal</th><td colspan="11">'
			.number_format($upkeep,0,',','.').' <img class="r4" src="img/x.gif" title="Cereal" alt="Cereal"> por hora</td>'
			.'</tr></tbody></table>';
	}
}
?>
