<h1 class="titleInHeader">Plaza de reuniones <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid16">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(16,4);" class="build_logo">
<img class="g16 big white" src="img/x.gif" alt="Plaza de reuniones" title="Plaza de reuniones" />
</a>
Las tropas de tu aldea se reúnen aquí. Desde aquí puedes enviarlas a conquistar, saquear o reforzar otras aldeas.</div>
<?php include("upgrade.tpl"); ?>
<div class="contentNavi tabNavi">
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=<?php echo $id; ?>"><span class="tabItem">Resumen</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="a2b.php"><span class="tabItem">Enviar tropas</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="warsim.php"><span class="tabItem">Simulador de combate</span></a></div>
				</div>
                <div class="container active">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=39&amp;t=99"><span class="tabItem">Lista de granjas</span></a></div>
				</div>
				<?php if($session->goldclub==1){ ?>
                <div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="build.php?id=39&amp;t=100"><span class="tabItem">Club de Oro</span></a></div>
				</div>
				<?php } ?>
</div>

		<div id="raidList">
	<?php if(!$session->goldclub) { ?>
	<div class="options">
					<div id="spaceUsed">
				<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">				La lista de granjas es gratuita, pero solo está disponible con el Club de Oro.					</div>
				</div>
                <div class="clear"></div>
			</div>

			<a class="arrow" href="plus.php?id=3#goldclub">Club de Oro</a>
			</div>
            <?php
            }else{ include "Templates/goldClub/farmlist.tpl"; }
            
            ?>
</div>

</div>
