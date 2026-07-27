<h1 class="titleInHeader">Escondite <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

<div id="build" class="gid23"><div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(23,4);" class="build_logo">
	<img class="building big white g23" src="img/x.gif" alt="Escondite" title="Escondite" />
</a>
El escondite sirve para ocultar parte de tus recursos cuando la aldea es atacada. Esos recursos no pueden ser robados.</div>

	<table cellpadding="1" cellspacing="1" id="build_value">
	<tr>
		<th>Capacidad del escondite</th>
		<td><b>
        <?php echo $bid23[$village->resarray['f'.$id]]['attri']*CRANNY_CAPACITY; ?>
        </b> unidades</td>
	</tr>
	<tr>
<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
		<th>Capacidad del escondite en el nivel <?php echo $village->resarray['f'.$id]+1; ?></th>
		<td><b>
        <?php echo $bid23[$village->resarray['f'.$id]+1]['attri']*CRANNY_CAPACITY; ?>
        
        </b> unidades</td>
        <?php
            }
            ?>
	</tr>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>
