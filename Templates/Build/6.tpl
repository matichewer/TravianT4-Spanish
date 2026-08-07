<h1 class="titleInHeader">Fábrica de ladrillos <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid6">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(6,4);" class="build_logo">
	<img class="building big white g6" src="img/x.gif" alt="Fábrica de ladrillos" title="Fábrica de ladrillos" />
</a>
Aquí el barro se procesa en ladrillos. Según su nivel, tu fábrica de ladrillos puede aumentar la producción de barro hasta un 25 por ciento.</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>
Aumento de producción actual:</th>
			<td><b><?php echo $village->resarray['f'.$id] >= 1 ? $bid6[$village->resarray['f'.$id]]['attri'] : 0; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Aumento de producción en el nivel <?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo $bid6[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>