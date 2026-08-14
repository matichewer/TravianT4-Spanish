<div class="clear"></div>
<?php
// Lista de celebraciones del Ayuntamiento. Las dos fiestas comparten toda la
// estructura, así que se arma una sola vez y se recorre: el bloque duplicado que
// había antes dejaba los <div> sin cerrar en cuanto no se mostraba el botón
// (celebración en curso o recursos insuficientes), y la fiesta grande abría su
// propio "build_details" dentro del "research" de la pequeña.
//
// Qué celebraciones existen lo decide celebrationDuration(): devuelve 0 para las
// que el nivel actual no habilita. Es la misma función que usa celebration.php,
// así que la plantilla no puede ofrecer una fiesta que el servidor vaya a rechazar.
$level = (int)$village->resarray['f'.$id];
$celebrationBusy = (int)$database->getVillageField($village->wid, 'celebration') > 0;
$celebrations = array(
	1 => array('name' => 'Pequeña celebración', 'points' => 500),
	2 => array('name' => 'Gran celebración', 'points' => 2000)
);

foreach($celebrations as $i => $celebration) {
	$duration = celebrationDuration($i, $level);
	if($duration <= 0 || !isset($cel[$i])) {
		continue;
	}
	$cost = $cel[$i];

	// El aviso tiene que explicar por qué no se puede, no sólo que falta. Un costo
	// mayor que el almacén o el granero no se junta nunca por más que se espere.
	$overStorage = $cost['wood'] > $village->maxstore || $cost['clay'] > $village->maxstore
		|| $cost['iron'] > $village->maxstore;
	$overGranary = $cost['crop'] > $village->maxcrop;
	$missing = false;
	$missingWithoutProduction = false;
	foreach(array('wood','clay','iron','crop') as $res) {
		if($cost[$res] > $village->{'a'.$res}) {
			$missing = true;
			if($village->getProd($res) <= 0) {
				$missingWithoutProduction = true;
			}
		}
	}
?>
<div class="build_details researches">
	<div class="research">
		<div class="information">
			<div class="title">
				<a href="#" onclick="return Travian.Game.iPopup(24,4);">
				<img class="celebration celebrationSmall" src="img/x.gif" alt="<?php echo $celebration['name']; ?>">
				</a>
				<a href="#" onclick="return Travian.Game.iPopup(24,4);"><?php echo $celebration['name']; ?></a>
				<span class="points">(<?php echo $celebration['points']; ?> puntos de cultura)</span>
			</div>
			<div class="costs">
				<div class="showCosts">
					<span class="resources r1 little_res"><img class="r1" src="img/x.gif" alt="Madera"><?php echo $cost['wood']; ?></span>
					<span class="resources r2 little_res"><img class="r2" src="img/x.gif" alt="Barro"><?php echo $cost['clay']; ?></span>
					<span class="resources r3 little_res"><img class="r3" src="img/x.gif" alt="Hierro"><?php echo $cost['iron']; ?></span>
					<span class="resources r4 little_res"><img class="r4" src="img/x.gif" alt="Cereal"><?php echo $cost['crop']; ?></span>
					<div class="clear"></div>
					<span class="clocks"><img class="clock" src="img/x.gif" alt="Duración"><?php echo $generator->getTimeFormat($duration); ?></span>
<?php
	if($session->userinfo['gold'] >= 3 && $building->getTypeLevel(17) >= 1) {
?>
					<button type="button" value="npc" class="icon" title="Mercader NPC: cambia tus recursos sobrantes por los que faltan para esta celebración, a cambio de oro" onclick="window.location.href = 'build.php?gid=17&amp;t=3&amp;r1=<?php echo $cost['wood']; ?>&amp;r2=<?php echo $cost['clay']; ?>&amp;r3=<?php echo $cost['iron']; ?>&amp;r4=<?php echo $cost['crop']; ?>'; return false;"><img src="img/x.gif" class="npc" alt="npc"></button>
<?php
	}
?>
					<div class="clear"></div>
				</div>
			</div>
<?php
	if($celebrationBusy) {
		echo '<div class="contractLink"><span class="none">Ya hay una celebración en curso en esta aldea</span></div>';
	}
	else if($overStorage) {
		echo '<div class="contractLink"><span class="none">Mejora el almacén.</span></div>';
	}
	else if($overGranary) {
		echo '<div class="contractLink"><span class="none">Mejora el granero.</span></div>';
	}
	else if($missing) {
		if($missingWithoutProduction) {
			echo '<div class="contractLink"><span class="none">Sin producción de alguno de los recursos que faltan: nunca habrá suficientes.</span></div>';
		}
		else {
			// procMtime() devuelve array(día, hora). Mostrar sólo la hora escondía que
			// los recursos recién alcanzan al día siguiente.
			$available = $technology->calculateAvaliable(24,$cost);
			echo '<div class="contractLink"><span class="none">Recursos suficientes: '.$available[0].' a las '.$available[1].'</span></div>';
		}
	}
	else {
?>
			<button type="button" value="Celebrar" class="build" onclick="window.location.href = 'celebration.php?id=<?php echo $id; ?>&amp;type=<?php echo $i; ?>&amp;c=<?php echo urlencode((string)$session->mchecker); ?>'; return false;"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div>
<div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div>
</div><div class="button-contents">Celebrar</div></div></button>
<?php
	}
?>
		</div>
		<div class="clear"></div>
	</div>
</div>
<?php
}
?>
