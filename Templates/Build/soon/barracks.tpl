<?php
$_GET['bid'] = 19;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Cuartel</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(19,4);" class="build_logo">
		<img class="building big white g19" src="img/x.gif" alt="Cuartel">
	</a>
	En el cuartel se puede entrenar infantería. Cuanto mayor sea su nivel, más rápido se entrenan las tropas. Si quieres crear tropas más avanzadas que las iniciales, deberás investigarlas en la academia.</div>
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
    <a href="#" onclick="return Travian.Game.iPopup(14,4, 'gid');">Plaza de reuniones</a> <span>Nivel 1</span></span>, <span class="buildingCondition"><a href="#" onclick="return Travian.Game.iPopup(15,4, 'gid');">Edificio principal</a> <span>Nivel 3</span></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>
