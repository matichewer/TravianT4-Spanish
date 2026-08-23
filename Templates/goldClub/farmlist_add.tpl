<div id="raidListCreate">
	<h4>Crear una nueva lista</h4>
	<?php if(isset($_GET['from_map']) && $_GET['from_map'] == 1) { ?>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Primero crea una lista de granjas. Después podrás configurar las tropas para el objetivo seleccionado en el mapa.</div></div>
	<?php } ?>
	<form action="build.php?gid=16&t=99" method="post">
		<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">
        <input type="hidden" name="action" value="addList">
		<?php if(isset($_GET['from_map']) && $_GET['from_map'] == 1) { ?>
		<input type="hidden" name="from_map" value="1">
		<input type="hidden" name="map_x" value="<?php echo isset($_GET['x']) ? (int)$_GET['x'] : 0; ?>">
		<input type="hidden" name="map_y" value="<?php echo isset($_GET['y']) ? (int)$_GET['y'] : 0; ?>">
		<?php } ?>
			<table cellpadding="1" cellspacing="1" class="transparent">
				<tbody><tr>
					<th>
						Nombre:					</th>
					<td>
						<input class="text" id="name" name="name" type="text">
					</td>
				</tr>
				<tr>
					<th>
						Aldea:					</th>
					<td>
                    
						<select id="did" name="did">
<?php
	for($i=1;$i<=count($session->villages);$i++) {
    if($session->villages[$i-1] == $village->wid){
    	$select = 'selected="selected"';
    }else{
        $select = '';
    }
    
		echo "<option value=\"".$session->villages[$i-1]."\" ".$select.">".$database->getVillageField($session->villages[$i-1],'name')."</option>";
    }
?>						</select>
					</td>
				</tr>
			</tbody></table>

			</div>
				</div>

<button type="submit" value="create"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Crear</div></div></button>
</form>
</div>
