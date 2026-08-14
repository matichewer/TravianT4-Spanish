<h1 class="titleInHeader">Ayuntamiento <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

<div id="build" class="gid24">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(24,4);" class="build_logo">
	<img class="building big white g24" src="img/x.gif" alt="Ayuntamiento" title="Ayuntamiento" />
</a>

En el Ayuntamiento puedes organizar grandes celebraciones. Cada celebración aumenta tus puntos de cultura.
</div>

<?php
	$buildingHelpType = 'town-hall';
	$buildingHelpLevel = $village->resarray['f'.$id];
	include('build_level_help.tpl');

	include("upgrade.tpl");
	if ($building->getTypeLevel(24) > 0) {
		// Nombres no numéricos a propósito: build.php sólo acepta ?t=/?s= numéricos,
		// así que estas dos partes no se pueden pedir sueltas por URL (antes
		// build.php?id=<ayuntamiento>&t=1 devolvía la lista de celebraciones sin el
		// encabezado ni el bloque de mejora).
		include("Templates/Build/24_celebrations.tpl");
		include("Templates/Build/24_progress.tpl");
	}
?>
</div>
