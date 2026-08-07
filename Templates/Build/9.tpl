<h1 class="titleInHeader">Panadería <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid9">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(9,4);" class="build_logo">
	<img class="building big white g9" src="img/x.gif" alt="Panadería" title="Panadería" />
</a>
Aquí se usa la harina de tu molino para hornear pan. Junto con el molino, el aumento de la producción de cereal puede llegar al 50 por ciento.</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Aumento de producción actual:</th>
			<td><b><?php echo $village->resarray['f'.$id] >= 1 ? $bid9[$village->resarray['f'.$id]]['attri'] : 0; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Aumento de producción en el nivel  <?php echo $village->resarray['f'.$id]+1; ?></th>
			<td><b><?php echo $bid9[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>