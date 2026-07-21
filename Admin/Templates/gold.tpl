<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       gold.tpl                                                    ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

if($_SESSION['access'] < ADMIN) die("Acceso denegado: no eres administrador.");
$id = $_SESSION['id'];

$inputValue = $_GET['g'];
if ($inputValue == '' || !is_numeric($inputValue)) {
    $inputValue = 20;
}

?>

<form action="../GameEngine/Admin/Mods/gold.php" method="POST">
	<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
	<table id="member" style="width:300px;">
		<thead>
			<tr>
				<th colspan="2">Dar oro gratis a todos</th>
			</tr>
			<tr>
				<td>Cantidad</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<center>
						<b>¿Cuánto oro?</b>
					</center>
				</td>
				<td>
					<center>
						<input class="give_gold" name="gold" value="<?php echo $inputValue; ?>" maxlength="4">&nbsp;
						<img src="../img/admin/gold.gif" class="gold" alt="Oro" name="gold" title="Oro"/>
					</center>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<center>
						<input type="image" src="../img/admin/b/ok1.gif" value="submit" title="Dar oro gratis a los jugadores">
					</center>
				</td>
			</tr>
		</tbody>
	</table>
</form>

<?php
    if(isset($_GET['g']))
	{
		echo '<br /><br /><font color="Red"><b>Oro añadido</font></b>';
	}
?>
