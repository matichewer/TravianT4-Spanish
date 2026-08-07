<h1 class="titleInHeader">Aserradero <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid5">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(5,4);" class="build_logo">
	<img class="building big white g5" src="img/x.gif" alt="Aserradero" title="Aserradero" />
</a>
La madera producida por el leñador se convierte en tablones en el aserradero. Según el nivel, puede aumentar la producción de madera hasta un 25 por ciento.</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Aumento de producción en el nivel actual: </th>
			<td><b><?php echo $village->resarray['f'.$id] >= 1 ? $bid5[$village->resarray['f'.$id]]['attri'] : 0; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Aumento de producción en el nivel<?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo $bid5[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>