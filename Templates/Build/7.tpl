<h1 class="titleInHeader">Fundición de hierro <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid7">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(7,4);" class="build_logo">
	<img class="building big white g7" src="img/x.gif" alt="Fundición de hierro" title="Fundición de hierro" />
</a>
El hierro se funde en la fundición de hierro. Según su nivel, puede aumentar tu producción de hierro hasta un 25 por ciento.</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Aumento de producción actual:</th>
			<td><b><?php echo $village->resarray['f'.$id] >= 1 ? $bid7[$village->resarray['f'.$id]]['attri'] : 0; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Aumento de producción en el nivel<?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo $bid7[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>