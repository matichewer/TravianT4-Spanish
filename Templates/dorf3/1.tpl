<?php
	include('menu.tpl');
?>
<table id="overview" cellpadding="1" cellspacing="1">
<thead>
<tr><td> Aldea </td><td> Ataques </td><td> Construcción </td><td> Tropas </td><td> Mercaderes </td></tr>
</thead>
<tbody>
<?php
	// En orden de fundación, igual que el cartel lateral. Ver GameEngine/VillageOverview.php.
	$varray = villageOverviewVillages($session->uid);
	foreach($varray as $vil){
		$vid = $vil['wref'];
		$vdata = $database->getVillage($vid);
		$jobs = $database->getJobs($vid);
		$unit = $database->getTraining($vid);
		// Misma definicion que el contador del Mercado (Market::merchantAvail): ocupados
		// son solo los que estan realmente de viaje. Restar aca ademas los mercaderes
		// comprometidos en rutas hacia que la misma aldea mostrara "12/20" en el resumen
		// y "20/20" en el Mercado; las rutas no ocupan a nadie hasta que salen.
		$totalmerchants = Automation::marketMerchants($building->getTypeLevel(17,$vid));
		$availmerchants = max(0,$totalmerchants - (int)$database->totalMerchantUsed($vid));
		$incoming_attacks = $database->getMovement(3,$vid,1);
		$bui = '<span class="none">-</span>';
		$tro = '<span class="none">-</span>';
		$att = '<span class="none">-</span>';

		if (count($incoming_attacks) > 0) {
			$inc_atts = count($incoming_attacks);
			for($i=0;$i<count($incoming_attacks);$i++){
				if($incoming_attacks[$i]['attack_type'] == 1 || $incoming_attacks[$i]['attack_type'] == 2) {
					$inc_atts -= 1;
				}
			}
			if($inc_atts > 0) {
				$att = '<a href="build.php?newdid='.$vid.'&id=39"><img class="att1" src="img/x.gif" title="'.$inc_atts.' ataque(s) a la aldea" alt="'.$inc_atts.' ataque(s) a la aldea"></a>';
			}

		}
		// Una pala por obra EN CURSO. Los dos foreach de acá abajo asignaban en vez de
		// acumular, así que sólo sobrevivía la última vuelta: una aldea con dos o tres
		// obras simultáneas —romano y Plus suman una cada uno sobre la básica, ver
		// Building::__construct()— mostraba una sola pala, y una que entrenaba en cuartel,
		// establo y taller a la vez mostraba un solo icono.
		//
		// `master = 1` es la cola del maestro de obras (Plus): está encolado pero todavía
		// no se construye, así que no cuenta como obra en curso.
		$buiIcons = array();
		foreach($jobs as $b){
			if((int)$b['master'] === 1) {
				continue;
			}
			$name = $building->procResType($b['type']).' nivel '.(int)$b['level'];
			$name = htmlspecialchars($name,ENT_QUOTES,'UTF-8');
			$buiIcons[] = '<a href="build.php?newdid='.$vid.'&id='.(int)$b['field'].'"><img class="bau" src="img/x.gif" title="'.$name.'" alt="'.$name.'"></a>';
		}
		if(!empty($buiIcons)) {
			$bui = implode('',$buiIcons);
		}

		// Un icono por TIPO de unidad, no por tanda: una cola de cinco tandas de
		// legionarios en el mismo cuartel es un solo tipo entrenándose, y cinco iconos
		// iguales no dicen nada que uno no diga. Las cantidades se suman en el tooltip.
		$troAmounts = array();
		foreach($unit as $c){
			$unitId = (int)$c['unit'];
			$troAmounts[$unitId] = (isset($troAmounts[$unitId]) ? $troAmounts[$unitId] : 0) + max(0,(int)$c['amt']);
		}
		if(!empty($troAmounts)) {
			$troIcons = array();
			foreach($troAmounts as $unitId => $amount){
				$name = htmlspecialchars($amount.'x '.$technology->getUnitName($unitId),ENT_QUOTES,'UTF-8');
				$troIcons[] = '<a href="build.php?newdid='.$vid.'&gid=19"><img class="unit u'.$unitId.'" src="img/x.gif" title="'.$name.'" alt="'.$name.'"></a>';
			}
			$tro = implode('',$troIcons);
		}

		if($vdata['capital'] == 1) { $class = 'hl'; } else {$class = 'hover'; }

echo '
<tr class="'.$class.'">
<td class="vil fc"><a href="dorf1.php?newdid='.$vid.'">'.$vdata['name'].'</a></td>
<td class="att">'.$att.'</td>
<td class="bui">'.$bui.'</td>
<td class="tro">'.$tro.'</td>
<td class="tra lc">'.($totalmerchants>0?'<a href="build.php?newdid='.$vid.'&amp;gid=17">':'').$availmerchants.'/'.$totalmerchants.'</a></td>
</tr>';

	}
?>
</tbody></table>
