<?php
$_GET['bid'] = 28;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Oficina de comercio</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(28,4);" class="build_logo">
		<img class="building big white g28" src="img/x.gif" alt="Oficina de comercio">


	</a>
	En la oficina de comercio se mejoran los carros de los mercaderes y se equipan con caballos poderosos. Cuanto mayor sea su nivel, más pueden transportar tus mercaderes. Con cada nivel de este edificio, los mercaderes transportan un 10% más de recursos.</div>
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
    <a href="#" onclick="return Travian.Game.iPopup(17,4, 'gid');">Mercado</a> <span>Nivel 20</span></span>, <span class="buildingCondition"><a href="#" onclick="return Travian.Game.iPopup(20,4, 'gid');">Establo</a> <span>Nivel 10</span></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>