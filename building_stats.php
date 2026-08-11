<?php

include("GameEngine/Village.php");

$buildingStatsSpeed = max(1, (int) SPEED);
$buildingStatsAttributeLabels = array(
	1 => 'Producción/h', 2 => 'Producción/h', 3 => 'Producción/h', 4 => 'Producción/h',
	5 => 'Bono de producción', 6 => 'Bono de producción', 7 => 'Bono de producción', 8 => 'Bono de producción', 9 => 'Bono de producción',
	10 => 'Capacidad', 11 => 'Capacidad', 12 => 'Tiempo de mejora', 14 => 'Velocidad a distancia',
	15 => 'Tiempo de construcción', 17 => 'Mercaderes', 18 => 'Miembros', 19 => 'Tiempo de entrenamiento',
	20 => 'Tiempo de entrenamiento', 21 => 'Tiempo de entrenamiento', 22 => 'Tiempo de investigación',
	23 => 'Capacidad oculta', 24 => 'Duración de celebración', 25 => 'Tiempo de entrenamiento',
	28 => 'Carga de mercaderes', 29 => 'Tiempo de entrenamiento', 30 => 'Tiempo de entrenamiento',
	31 => 'Bono defensivo', 32 => 'Bono defensivo', 33 => 'Bono defensivo', 34 => 'Resistencia de edificios',
	35 => 'Bono de ataque', 36 => 'Trampas', 38 => 'Capacidad', 39 => 'Capacidad',
	41 => 'Velocidad de entrenamiento', 42 => 'Tiempo de entrenamiento'
);

function buildingStatsDuration($seconds)
{
	$seconds = max(1, (int) round($seconds));
	$days = floor($seconds / 86400);
	$hours = floor(($seconds % 86400) / 3600);
	$minutes = floor(($seconds % 3600) / 60);
	$remainingSeconds = $seconds % 60;
	$duration = $hours.':'.str_pad($minutes, 2, '0', STR_PAD_LEFT).':'.str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
	return $days > 0 ? $days.'d '.$duration : $duration;
}

function buildingStatsEffect($gid, $levelData)
{
	if (isset($levelData['prod'])) {
		return number_format($levelData['prod'] * SPEED, 0, ',', '.');
	}
	if (!isset($levelData['attri'])) {
		return '—';
	}

	$value = $levelData['attri'];
	if (in_array($gid, array(10, 11, 38, 39), true)) {
		$value *= STORAGE_MULTIPLIER;
	} elseif ($gid === 23) {
		$value *= CRANNY_CAPACITY;
	} elseif ($gid === 36) {
		$value *= TRAPPER_CAPACITY;
	}

	if (in_array($gid, array(5, 6, 7, 8, 9, 14, 28, 31, 32, 33, 34, 35), true)) {
		return str_replace('.', ',', (string) $value).'%';
	}
	if (in_array($gid, array(12, 15, 19, 20, 21, 22, 24, 25, 29, 30, 42), true)) {
		return str_replace('.', ',', (string) $value).'% del tiempo base';
	}
	if ($gid === 41) {
		return round($value * 100).'%';
	}
	return number_format($value, 0, ',', '.');
}

$buildingStatsBuildings = array();
for ($buildingStatsGid = 1; $buildingStatsGid <= 42; $buildingStatsGid++) {
	$buildingStatsVariable = 'bid'.$buildingStatsGid;
	if (!isset($GLOBALS[$buildingStatsVariable]) || !is_array($GLOBALS[$buildingStatsVariable])) {
		continue;
	}
	$buildingStatsBuildings[] = array(
		'gid' => $buildingStatsGid,
		'name' => $building->procResType($buildingStatsGid),
		'levels' => $GLOBALS[$buildingStatsVariable]
	);
}

include "Templates/html.tpl";
?>
<body class="v35 webkit chrome troopStatsClean buildingStatsClean">
	<div id="wrapper">
		<img id="staticElements" src="img/x.gif" alt="" />
		<div class="troopStatsHeader">
			<a id="logo" href="<?php echo HOMEPAGE; ?>" target="_blank" title="<?php echo SERVER_NAME; ?>"></a>
			<p class="troopStatsBack"><a href="help.php">&laquo; Volver a la ayuda</a></p>
		</div>
		<div class="bodyWrapper">
			<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" />
			<div id="mid">
				<div id="contentOuterContainer">
					<div class="contentTitle">&nbsp;</div>
					<div class="contentContainer">
						<div id="content" class="universal troopStatsPage buildingStatsPage">
							<h1 class="titleInHeader">Edificios</h1>
							<p class="troopStatsIntro">Costos, tiempos y efectos de todos los edificios en cada nivel. Los valores incluyen la velocidad y los multiplicadores configurados en este servidor.</p>
							<nav class="buildingStatsIndex" aria-label="Índice de edificios">
							<?php foreach ($buildingStatsBuildings as $buildingStatsBuilding) { ?>
								<a href="#edificio-<?php echo $buildingStatsBuilding['gid']; ?>"><?php echo htmlspecialchars(html_entity_decode($buildingStatsBuilding['name'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></a>
							<?php } ?>
							</nav>
							<?php foreach ($buildingStatsBuildings as $buildingStatsBuilding) {
								$buildingStatsGid = $buildingStatsBuilding['gid'];
								$buildingStatsEffectLabel = isset($buildingStatsAttributeLabels[$buildingStatsGid]) ? $buildingStatsAttributeLabels[$buildingStatsGid] : 'Efecto';
							?>
							<section class="buildingStatsSection" id="edificio-<?php echo $buildingStatsGid; ?>">
								<h2><span class="building g<?php echo $buildingStatsGid; ?>" aria-hidden="true"></span><?php echo $buildingStatsBuilding['name']; ?></h2>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable buildingStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr>
											<th>Nivel</th>
											<th><img class="r1" src="img/x.gif" alt="Madera" title="Madera" /></th>
											<th><img class="r2" src="img/x.gif" alt="Barro" title="Barro" /></th>
											<th><img class="r3" src="img/x.gif" alt="Hierro" title="Hierro" /></th>
											<th><img class="r4" src="img/x.gif" alt="Cereal" title="Cereal" /></th>
											<th><img class="r5" src="img/x.gif" alt="Población" title="Población añadida" /></th>
											<th title="Puntos de cultura por día">PC/día</th>
											<th><?php echo $buildingStatsEffectLabel; ?></th>
											<th><img class="clock" src="img/x.gif" alt="Duración" title="Tiempo de construcción" /></th>
										</tr></thead>
										<tbody>
										<?php foreach ($buildingStatsBuilding['levels'] as $buildingStatsLevel => $buildingStatsLevelData) {
											if ((int) $buildingStatsLevel < 1 || !isset($buildingStatsLevelData['wood'])) { continue; }
										?>
										<tr>
											<td><?php echo (int) $buildingStatsLevel; ?></td>
											<td><?php echo number_format($buildingStatsLevelData['wood'], 0, ',', '.'); ?></td>
											<td><?php echo number_format($buildingStatsLevelData['clay'], 0, ',', '.'); ?></td>
											<td><?php echo number_format($buildingStatsLevelData['iron'], 0, ',', '.'); ?></td>
											<td><?php echo number_format($buildingStatsLevelData['crop'], 0, ',', '.'); ?></td>
											<td><?php echo isset($buildingStatsLevelData['pop']) ? $buildingStatsLevelData['pop'] : '—'; ?></td>
											<td><?php echo isset($buildingStatsLevelData['cp']) ? $buildingStatsLevelData['cp'] : '—'; ?></td>
											<td><?php echo buildingStatsEffect($buildingStatsGid, $buildingStatsLevelData); ?></td>
											<td><?php echo buildingStatsDuration($buildingStatsLevelData['time'] / $buildingStatsSpeed); ?></td>
										</tr>
										<?php } ?>
										</tbody>
									</table>
								</div>
							</section>
							<?php } ?>
							<div class="clear"></div>
						</div>
					</div>
					<div class="contentFooter">&nbsp;</div>
				</div>

<?php include("Templates/footer.tpl"); ?>

				<div id="ce"></div>
			</div>
		</div>
	</div>
</body>
</html>
