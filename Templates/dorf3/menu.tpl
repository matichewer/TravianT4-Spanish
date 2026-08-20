<?php
// `?s=` puede no venir (la pestaña Resumen) y compararlo crudo emitía un aviso por
// pestaña. La pestaña Tropas conserva el subtab en `?su=`, que no cambia cuál se marca.
$menuSection = isset($_GET['s']) ? (int)$_GET['s'] : 0;
?>
<div class="contentNavi subNavi">
				<div class="container <?php echo $menuSection == 0 ? 'active' : 'normal'; ?>">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="dorf3.php"><span class="tabItem">Resumen</span></a></div>
				</div>
				<div class="container <?php echo $menuSection == 2 ? 'active' : 'normal'; ?>">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="dorf3.php?s=2"><span class="tabItem">Recursos</span></a></div>
				</div>
				<div class="container <?php echo $menuSection == 3 ? 'active' : 'normal'; ?>">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="dorf3.php?s=3"><span class="tabItem">Almacén</span></a></div>
				</div>
				<div class="container <?php echo $menuSection == 4 ? 'active' : 'normal'; ?>">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="dorf3.php?s=4"><span class="tabItem">Puntos de cultura</span></a></div>
				</div>
				<div class="container <?php echo $menuSection == 5 ? 'active' : 'normal'; ?>">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="dorf3.php?s=5"><span class="tabItem">Tropas</span></a></div>
				</div><div class="clear"></div>
</div>
