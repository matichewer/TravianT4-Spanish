<?php

include("GameEngine/Village.php");

$troopStatsTribes = array(
	array('name' => 'Galos', 'firstUnit' => 21),
	array('name' => 'Romanos', 'firstUnit' => 1),
	array('name' => 'Germanos', 'firstUnit' => 11)
);
$troopStatsTrainingSpeed = max(1, (int) SPEED);
$troopStatsMovementSpeed = max(1, (int) INCREASE_SPEED);

function troopStatsDuration($seconds)
{
	$hours = floor($seconds / 3600);
	$minutes = floor(($seconds % 3600) / 60);
	$remainingSeconds = $seconds % 60;

	return $hours.':'.str_pad($minutes, 2, '0', STR_PAD_LEFT).':'.str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
}

include "Templates/html.tpl";
?>
<body class="v35 webkit chrome troopStatsClean">
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
						<div id="content" class="universal troopStatsPage">
							<h1 class="titleInHeader">Estadísticas de tropas</h1>
							<p class="troopStatsIntro">Estadísticas de las unidades de las tres tribus. Los tiempos corresponden al nivel 1 del edificio de entrenamiento y disminuyen al mejorar ese edificio.</p>
							<?php foreach ($troopStatsTribes as $troopStatsTribe) { ?>
							<h2><?php echo $troopStatsTribe['name']; ?></h2>
							<div class="troopStatsTableWrapper">
								<table class="troopStatsTable" cellpadding="1" cellspacing="1">
									<thead>
										<tr>
											<th class="troopStatsUnit">Unidad</th>
											<th><img class="att_all" src="img/x.gif" alt="Ataque" title="Ataque" /></th>
											<th><img class="def_i" src="img/x.gif" alt="Defensa contra infantería" title="Defensa contra infantería" /></th>
											<th><img class="def_c" src="img/x.gif" alt="Defensa contra caballería" title="Defensa contra caballería" /></th>
											<th><img class="r1" src="img/x.gif" alt="Madera" title="Madera" /></th>
											<th><img class="r2" src="img/x.gif" alt="Barro" title="Barro" /></th>
											<th><img class="r3" src="img/x.gif" alt="Hierro" title="Hierro" /></th>
											<th><img class="r4" src="img/x.gif" alt="Cereal" title="Cereal" /></th>
											<th><img class="r5" src="img/x.gif" alt="Consumo de cereal por hora" title="Consumo de cereal por hora" /></th>
											<th title="Casillas por hora">Vel.</th>
											<th title="Capacidad de carga">Carga</th>
											<th><img class="clock" src="img/x.gif" alt="Tiempo de entrenamiento" title="Tiempo de entrenamiento a nivel 1" /></th>
										</tr>
									</thead>
									<tbody>
									<?php for ($troopStatsUnitId = $troopStatsTribe['firstUnit']; $troopStatsUnitId < $troopStatsTribe['firstUnit'] + 10; $troopStatsUnitId++) {
										$troopStatsUnit = ${'u'.$troopStatsUnitId};
										$troopStatsUnitName = constant('U'.$troopStatsUnitId);
									?>
										<tr>
											<td class="troopStatsUnit"><img class="unit u<?php echo $troopStatsUnitId; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars(html_entity_decode($troopStatsUnitName, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>" /> <?php echo $troopStatsUnitName; ?></td>
											<td><?php echo $troopStatsUnit['atk']; ?></td>
											<td><?php echo $troopStatsUnit['di']; ?></td>
											<td><?php echo $troopStatsUnit['dc']; ?></td>
											<td><?php echo $troopStatsUnit['wood']; ?></td>
											<td><?php echo $troopStatsUnit['clay']; ?></td>
											<td><?php echo $troopStatsUnit['iron']; ?></td>
											<td><?php echo $troopStatsUnit['crop']; ?></td>
											<td><?php echo $troopStatsUnit['pop']; ?></td>
											<td><?php echo $troopStatsUnit['speed'] * $troopStatsMovementSpeed; ?></td>
											<td><?php echo $troopStatsUnit['cap']; ?></td>
											<td><?php echo troopStatsDuration(max(1, (int) round($troopStatsUnit['time'] / $troopStatsTrainingSpeed))); ?></td>
										</tr>
									<?php } ?>
									</tbody>
								</table>
							</div>
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
