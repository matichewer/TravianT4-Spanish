<h1 class="titleInHeader">Almacén <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid10">
<div class="build_desc">
<a href="#" onclick="return Travian.Game.iPopup(10,4, 'gid');" class="build_logo">
<img class="building big white g10" src="img/x.gif" alt="Almacén">
</a>
La madera, el barro y el hierro se almacenan en el almacén. Cuanto mayor sea el nivel, mayor será la capacidad de almacenamiento
</div>

<?php
$capacityLevel = $building->constructionTargetLevel($id);
$hasQueuedCapacityLevel = ($capacityLevel !== false);
if(!$hasQueuedCapacityLevel) {
	$capacityLevel = (int)$village->resarray['f'.$id];
}
$nextCapacityLevel = $capacityLevel + 1;
?>
	<table cellpadding="1" cellspacing="1" id="build_value">
    <tr>
			<th>
<?php echo $hasQueuedCapacityLevel ? 'Capacidad en el nivel '.$capacityLevel.':' : 'Capacidad de almacenamiento actual:'; ?></th>
			<td><b><?php echo $bid10[$capacityLevel]['attri']*STORAGE_MULTIPLIER; ?></b></td>
		</tr>
    	<tr>
        <?php 
        if(isset($bid10[$nextCapacityLevel])) {
        ?>
			<th>Capacidad en el nivel <?php echo $nextCapacityLevel; ?> </th>
			<td><b><?php echo $bid10[$nextCapacityLevel]['attri']*STORAGE_MULTIPLIER; ?></b></td>
        <?php
        }
        ?>
	</tr>
	</table>
 <?php 
include("upgrade.tpl");
?>
</p></div>
