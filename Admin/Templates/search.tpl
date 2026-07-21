<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       search.tpl                                                  ##
##  Developed by:  Dzoki                                                       ##
##  Reworked:      aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################
?>
<form action="" method="post">
	<br />
	<table id="member">
		<thead>
			<tr>
				<th colspan="3">Búsqueda v1.0</th>
			</tr>
		</thead> 
		<tr class="slr3">
			<td> 
				<select name="p" size="1" class="slr3">
					<option value="player" <?php if($_POST['p']=='player'){echo "selected";}?>>Buscar jugador</option>
					<option value="alliances" <?php if($_POST['p']=='alliances'){echo "selected";}?>>Buscar alianzas</option>
					<option value="villages" <?php if($_POST['p']=='villages'){echo "selected";}?>>Buscar aldeas</option>
					<option value="email" <?php if($_POST['p']=='email'){echo "selected";}?>>Buscar correo electrónico</option>
					<option value="ip" <?php if($_POST['p']=='ip'){echo "selected";}?>>Buscar direcciones IP</option>
					<option value="deleted_players" <?php if($_POST['p']=='deleted_players'){echo "selected";}?>>Buscar jugadores eliminados</option>
				</select>
			</td>
			<td>
				<input name="s" value="<?php echo $_POST['s'];?>">
			</td>
			<td>
				<input type="submit" value="Buscar" class="slr3">
			</td>
		</tr>
	</table>
</form>

<?php
	if($_GET['msg'])
	{
		echo '<div style="margin-top: 50px;" class="b"><center>';
		if($_GET['msg'] == 'ursdel')
		{
			echo "User was deleted.";

		}
		echo '</center></div>';
	}
?>
