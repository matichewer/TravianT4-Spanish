<div class="contentNavi tabNavi">
				<div <?php if(!isset($_GET['t'])) { echo "class=\"container active\""; } else { echo "class=\"container normal\""; } ?>>
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>"><span class="tabItem">Resumen</span></a></div>
				</div>
				<div <?php if(isset($_GET['t']) && $_GET['t'] == 1) { echo "class=\"container active\""; } else { echo "class=\"container normal\""; } ?>>
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>&amp;t=1"><span class="tabItem">Comprar</span></a></div>
				</div>
				<div <?php if(isset($_GET['t']) && $_GET['t'] == 2) { echo "class=\"container active\""; } else { echo "class=\"container normal\""; } ?>>
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>&amp;t=2"><span class="tabItem">Vender</span></a></div>
				</div>
                <?php // El mercader NPC no trabaja en la aldea de la Maravilla (oficial), asi que
                      // la pestana no se dibuja ahi: el motor igual lo rechaza, pero una pestana que
                      // solo sabe decir que no es peor que no tenerla.
                if($session->userinfo['gold'] >= 3 && !wonderVillage($village->resarray)) { ?>
                <div <?php if(isset($_GET['t']) && $_GET['t'] == 3) { echo "class=\"container active\""; } else { echo "class=\"container normal\""; } ?>>
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>&amp;t=3"><span class="tabItem">Mercader NPC</span></a></div>
				</div>
                <?php } ?>
				<?php if($session->goldclub == 1 && count($database->getProfileVillages($session->uid)) > 1) {
				?>
				<div <?php if(isset($_GET['t']) && $_GET['t'] == 4) { echo "class=\"container active\""; } else { echo "class=\"container normal\""; } ?>>
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>&amp;t=4"><span class="tabItem">Rutas comerciales</span></a></div>
				</div>
				<?php
				}
				?>
</div>