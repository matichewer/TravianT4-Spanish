<?php
$_GET['bid'] = 36;
$bid = $_GET['bid'];
$uprequire = $building->resourceRequired($id,$bid);
?>
<h2>Trampero</h2>
<div class="build_desc">
	<a href="#" onclick="return Travian.Game.iPopup(36,4);" class="build_logo">
		<img class="building big white g36" src="img/x.gif" alt="Trampero">


	</a>
	El trampero protege tu aldea con trampas bien escondidas. Así, los enemigos incautos pueden quedar atrapados y ya no podrán dañar tu aldea.</div>

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
    <a href="#" onclick="return Travian.Game.iPopup(16,4, 'gid');">Plaza de reuniones</a> <span>Nivel 1</span></span>
    </div>
	<div class="clear"></div>
</div>
<div class="clear"></div><hr>