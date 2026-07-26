<h1 class="titleInHeader">Gran almacén <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid38">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(38,4);" class="build_logo">
        <img class="building big white g38" src="img/x.gif" alt="Gran almacén" title="Gran almacén"></a>
        En el almacén se guardan los recursos madera, arcilla y hierro. El gran almacén ofrece más espacio que el almacén normal para mantener tus recursos a salvo y protegidos.</div>

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
			<th><?php echo $hasQueuedCapacityLevel ? 'Capacidad en el nivel '.$capacityLevel.':' : 'Capacidad:'; ?></th>
			<td><b><?php echo $bid38[$capacityLevel]['attri']; ?></b></td>
		</tr>
		<tr>
	<?php
	        if(isset($bid38[$nextCapacityLevel])) {
	        ?>
			<th>Capacidad en el nivel <?php echo $nextCapacityLevel; ?> </th>
			<td><b><?php echo $bid38[$nextCapacityLevel]['attri']; ?></b></td>
	        <?php
	            }
            ?>
	</tr>
	</table>
 <?php 
include("upgrade.tpl");
?>
</p></div>
