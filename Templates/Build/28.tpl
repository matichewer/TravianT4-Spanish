<h1 class="titleInHeader">Oficina de comercio <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid28">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(28,4);" class="build_logo">
        <img class="building big white g28" src="img/x.gif" alt="Oficina de comercio" title="Oficina de comercio"></a>
        Los carros de comercio de tu mercado pueden mejorarse en la oficina de comercio. Cuanto mayor sea el nivel, más puede transportar cada mercader.</div>


	<table cellpadding="1" cellspacing="1" id="build_value">
	<?php
	// Un nivel fuera de la tabla (0 recien construido, o uno editado desde el panel de
	// administracion) dejaba la celda vacia: sin bonus la capacidad es la base, 100%.
	$tradeOfficeLevel = (int)$village->resarray['f'.$id];
	$tradeOfficeMax = count($bid28);
	$tradeOfficeBonus = isset($bid28[$tradeOfficeLevel]['attri']) ? $bid28[$tradeOfficeLevel]['attri'] : ($tradeOfficeLevel > 0 ? $bid28[$tradeOfficeMax]['attri'] : 100);
	?>
		<tr>
			<th>Capacidad de transporte por mercader:</th>
			<td><b><?php echo $tradeOfficeBonus; ?>%</b></td>
		</tr>
		<?php
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id) && isset($bid28[$tradeOfficeLevel+1]['attri'])) {
        ?>
		<tr>
			<th>Capacidad de transporte por mercader en el nivel  <?php echo $tradeOfficeLevel+1; ?> </th>
			<td><b><?php echo $bid28[$tradeOfficeLevel+1]['attri']; ?>%</b></td>
		</tr>
            <?php
            }
            ?>
	</table>
<?php 
include("upgrade.tpl");
?>
</p></div>
