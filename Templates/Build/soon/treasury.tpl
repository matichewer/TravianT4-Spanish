<?php
$_GET['bid'] = 27;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Tesorería</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(27,4);" class="build_logo">
		<img class="building big white g27" src="img/x.gif" alt="Tesorería">


	</a>
	Las riquezas de tu imperio se guardan en la tesorería. Una tesorería solo puede almacenar un artefacto. Para capturar un tesoro debes destruir la tesorería que lo contiene y luego lanzar un ataque normal desde la aldea donde está tu tesorería a nivel 10. El ataque debe tener éxito (al menos una unidad atacante debe sobrevivir). Tu héroe debe participar en el ataque y sobrevivir para reclamar planos de construcción y artefactos.</div>
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
    <span class="buildingCondition"><a href="#" onclick="return Travian.Game.iPopup(15,4, 'gid');">Edificio principal</a> <span>Nivel 10</span></span>,<span class="buildingCondition"><a href="#" onClick="return Popup(40,4);"><strike>Wonder of the World</strike></a></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>