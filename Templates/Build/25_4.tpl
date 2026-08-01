<h1 class="titleInHeader">Residencia <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
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
include("expansion_slots.tpl");
?>
</div><div class="clear">&nbsp;</div>
    <div class="clear"></div>
