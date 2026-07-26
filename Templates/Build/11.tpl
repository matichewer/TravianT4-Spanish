<h1 class="titleInHeader">Granero <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid11">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(11,4);" class="build_logo">
	<img class="building big white g11" src="img/x.gif" alt="Granero" title="Granero" />
</a>
El cereal de las granjas se almacena en el granero. Cuanto mayor sea el nivel, mayor será la capacidad de almacenamiento. </div>

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
		<th><?php echo $hasQueuedCapacityLevel ? 'Capacidad en el nivel '.$capacityLevel.':' : 'Capacidad de almacenamiento actual:'; ?></th>
		<td><b><?php echo $bid11[$capacityLevel]['attri']*STORAGE_MULTIPLIER; ?></b></td>
	</tr>
    
	<tr>
<?php 
        if(isset($bid11[$nextCapacityLevel])) {
        ?>
		<th>Capacidad en el nivel <?php echo $nextCapacityLevel; ?> </th>
		<td><b><?php echo $bid11[$nextCapacityLevel]['attri']*STORAGE_MULTIPLIER; ?></b></td>
        <?php
            }
            ?>
	</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>
