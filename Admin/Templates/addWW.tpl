<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       natarnature.tpl                                             ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################
?>
<form action="../GameEngine/Admin/Mods/addWW.php" method="POST">
	<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
	
	<br />
	<h3>Guarnición de las Aldeas de la Maravilla</h3>
	<p>La cantidad de tropas sale de un número al azar: la primera casilla de cada columna es el mínimo y la segunda el máximo.<br />
	Después el número al azar se multiplica por la velocidad del servidor.<br /><br />
	Con velocidad 1x, 2x o 3x se multiplica por la velocidad; con cualquier otra, por 5.</p>
	
	<table id="member" cellpadding="1" cellspacing="1" >
		<thead>
			<tr>
				<th colspan="3">Crear las Aldeas de la Maravilla</th>
			</tr>
			<tr>
				<td class="on">Desde</td>
				<td class="on">Hasta</td>
			</tr>
		</thead>
		<tbody>
			<?php
				$wwrnd1 = array(1000,1000,1000,1000,1000,1000,1000,1000,1000,1000,);
				$wwrnd2 = array(10000,10000,10000,10000,10000,10000,10000,10000,10000,10000,);
				for($i=41; $i<51; $i++)
				{
					$p = $i - 41;
					echo '
					<tr>
						<td>
							<img src="../gpack/travian_default/img/u/'.$i.'.gif" ><input class="fm" name="ww1u'.$i.'" value="'.$wwrnd1[$p].'" maxlength="6" style="width: 50%;">
						</td>
						<td>
							<input class="fm" name="ww2u'.$i.'" value="'.$wwrnd2[$p].'" maxlength="6" style="width: 50%;"> <img src="../gpack/travian_default/img/u/'.$i.'.gif">
						</td>
					</tr>';
				}
				echo '<td colspan="2">
					<center>
						WW Villages: <input class="fm" name="amount" value="13" maxlength="6"><br /><br />
						<input type="image" value="submit" src="../img/admin/b/ok1.gif">
					</center>
				</td>';
			?>
		</tbody>
	</table>
</form>
<?php
if(isset($_GET['g']))
{
	if(isset($_GET['amt']))
	{
		echo ''.$_GET['amt'].' Aldeas de la Maravilla creadas';
	}
}
?>