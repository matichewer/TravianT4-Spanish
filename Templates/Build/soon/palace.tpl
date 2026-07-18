<?php
$_GET['bid'] = 26;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Palacio</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(26,4);" class="build_logo">
		<img class="building big white g26" src="img/x.gif" alt="Palacio">


	</a>
	El rey o la reina del reino vive en el palacio. Solo puede existir un palacio en tu imperio a la vez. Además, el palacio es el único edificio donde puedes proclamar una aldea como tu capital.</div>
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
    <a href="#" onclick="return Travian.Game.iPopup(18,4, 'gid');">Embajada</a> <span>Nivel 1</span></span>, <span class="buildingCondition"><a href="#" onclick="return Travian.Game.iPopup(15,4, 'gid');">Edificio principal</a> <span>Nivel 5</span></span>, <span class="buildingCondition"><a href="#" onclick="return Travian.Game.iPopup(25,4, 'gid');"><strike>Residence</strike></a></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>