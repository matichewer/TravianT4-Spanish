<h1 class="titleInHeader">Academia <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

<div id="build" class="gid22">
<div class="build_desc">
<a href="#" onClick="return Popup(22,4);" class="build_logo">

	<img class="building big white g22" src="img/x.gif" alt="Academia" title="Academia" />
</a>
Los nuevos tipos de tropas deben investigarse primero en la academia antes de poder entrenarlos. Cuanto mayor sea el nivel, más tipos de tropas estarán disponibles para investigar.</div>
<?php
$buildingHelpType = 'academy';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
?>
<div class="clear"></div>

<?php
	if ($building->getTypeLevel(22) > 0 && (int)$session->tribe >= 1 && (int)$session->tribe <= 5) {
		include("22_".(int)$session->tribe.".tpl");
	} else {
		echo "<p><b>No hay nuevos tipos de tropas disponibles para investigar</b><br></p>\n";
	}
?>

         </div>
<div class="clear">&nbsp;</div>
<div class="clear"></div>
