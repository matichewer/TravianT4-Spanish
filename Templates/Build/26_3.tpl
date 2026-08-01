<h1 class="titleInHeader">Palacio <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid26">
<div class="build_desc">
	<a href="#" onClick="return Travian.Game.iPopup(26,4, 'gid');" class="build_logo"> 
    <img class="building big white g26" src="img/x.gif" alt="Palacio" title="Palacio" /> </a>
	El rey de la nación vive en el palacio. Cuanto mayor sea el nivel, más difícil será para los enemigos conquistar la aldea. Solo con un palacio se puede nombrar capital a una aldea. No se pueden construir un palacio y una residencia en la misma aldea. Solo se permite un palacio por cuenta.</div>


<?php
$buildingHelpType = 'palace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("26_menu.tpl"); 
?>
<p>Atacando con senadores, jefes o caudillos se puede reducir la lealtad de una aldea. Si llega a cero, la aldea pasa al imperio del atacante. Mientras el palacio siga en pie la aldea no puede ser conquistada, y cada nivel acelera la recuperación de la lealtad.</p>
<p>La lealtad de esta aldea es <b><?php echo (int)$database->getVillageField($village->wid, 'loyalty'); ?></b>%.</p>
<?php if ($village->capital == 1) { ?>
<p><b>Esta aldea es la capital: no puede ser conquistada.</b></p>
<?php } else { ?>
<p><b>Las capitales no pueden ser conquistadas.</b></p>
<?php } ?>
</div>
