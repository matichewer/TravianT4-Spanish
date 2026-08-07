<h1 class="titleInHeader">Molino <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid8">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(8,4);" class="build_logo">
	<img class="building big white g8" src="img/x.gif" alt="Molino" title="Molino" />
</a>
El molino aumenta tu producción de cereal un 5% por nivel, hasta un máximo del 25%. Úsalo junto con la panadería para un aumento total de hasta el 50%
</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Aumento de producción actual:</th>
			<td><b><?php echo $village->resarray['f'.$id] >= 1 ? $bid8[$village->resarray['f'.$id]]['attri'] : 0; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Aumento de producción en el nivel  <?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo $bid8[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>