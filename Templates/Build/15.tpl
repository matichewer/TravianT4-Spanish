<h1 class="titleInHeader">Edificio principal <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid15">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(15,4);" class="build_logo">
<img class="building big white g15" src="img/x.gif" alt="Edificio principal" title="Edificio principal" />
</a>Los arquitectos de la aldea viven en el edificio principal. Cuanto mayor sea el nivel, más rápido se construyen o mejoran los demás edificios.</div>

<?php
$buildingHelpType = 'main-building';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');
?>

	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Tiempo de construcción actual:</th>
			<td><b><?php echo round($bid15[$village->resarray['f'.$id]]['attri']); ?></b> por ciento</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Tiempo de construcción en el nivel <?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo round($bid15[$village->resarray['f'.$id]+1]['attri']); ?></b> por ciento</td>
            <?php
            }
            ?>
		</tr>
	</table>
	
<?php 
include("upgrade.tpl");
if($village->resarray['f'.$id] >= 10){
	include("Templates/Build/15_1.tpl");
}
?>
</p></div>
