<?php
$_GET['bid'] = 8;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Molino</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(8,4);" class="build_logo">
		<img class="building big white g8" src="img/x.gif" alt="Molino">


	</a>
	Aquí se muele tu grano para producir harina. Según su nivel, tu molino puede aumentar la producción de cereal hasta un 25 por ciento.</div>
<div id="contract" class="contract contractNew contractWrapper">
	<div class="contractText">Costo:</div>
	<div class="contractCosts">
    <div class="showCosts">
    <span class="resources r1 little_res"><img class="r1" src="img/x.gif" alt="Madera"><?php echo $uprequire['wood']; ?></span>
    <span class="resources r2 little_res"><img class="r2" src="img/x.gif" alt="Barro"><?php echo $uprequire['clay']; ?></span>
    <span class="resources r3 little_res"><img class="r3" src="img/x.gif" alt="Hierro"><?php echo $uprequire['iron']; ?></span>
    <span class="resources r4"><img class="r4" src="img/x.gif" alt="Cereal"><?php echo $uprequire['crop']; ?></span>
    <span class="resources r5"><img class="r5" src="img/x.gif" alt="Consumo de cereal"><?php echo $uprequire['pop']; ?></span>
    <div class="clear"></div>
    <span class="clocks"><img class="clock" src="img/x.gif" alt="Duración">

    <?php echo $generator->getTimeFormat($uprequire['time']); ?>
	</span>
    <div class="clear"></div>
    </div></div>
	<div class="contractLink">
    <div class="contractText">Necesario:</div>
    <span class="buildingCondition">
    <a href="#" onclick="return Travian.Game.iPopup(4,4, 'gid');">Granja</a> <span>Nivel 5</span></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>
