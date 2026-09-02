<?php
$_GET['bid'] = 35;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Cervecería</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(35,4);" class="build_logo">
		<img class="building big white g35" src="img/x.gif" alt="Cervecería">

	</a>
	La cervecería permite celebrar festivales de hidromiel. Mientras uno está activo, aumenta un 1% por nivel el ataque de todas las tropas germanas de la cuenta; a cambio, el poder de persuasión de los jefes se reduce un 50% y las catapultas solo pueden disparar al azar. Solo puede construirse en la capital.</div>
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
    <?php echo $building->requirementsHtml(35); ?>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>
