<h1 class="titleInHeader">Residencia <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid25">
<div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(25,4, 'gid');" class="build_logo">
        <img class="building big white g25" src="img/x.gif" alt="Residencia" title="Residencia" /> </a>
        La residencia es un pequeño palacio donde vive el rey o la reina cuando visita la aldea. La residencia protege la aldea de los enemigos que quieren conquistarla.</div>

<?php
$buildingHelpType = 'residence';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("25_menu.tpl");
?>
<p>Atacando con senadores, jefes o caudillos se puede reducir la lealtad de una aldea. Si llega a cero, la aldea pasa al imperio del atacante. Mientras la residencia siga en pie la aldea no puede ser conquistada, y cada nivel acelera la recuperación de la lealtad.</p>
<p>La lealtad de esta aldea es <b><?php echo (int)$database->getVillageField($village->wid, 'loyalty'); ?></b>%.</p>
<?php if ($village->capital == 1) { ?>
<p><b>Esta aldea es la capital: no puede ser conquistada.</b></p>
<?php } else { ?>
<p><b>Las capitales no pueden ser conquistadas.</b></p>
<?php } ?>
</div><div class="clear">&nbsp;</div>
    <div class="clear"></div>
