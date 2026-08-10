<?php
	$varmedal = $database->getProfileMedalAlly($_GET['aid'])
?>
<form action="../GameEngine/Admin/Mods/delallymedal.php" method="POST">
	<input type="hidden" name="aid" value="<?php echo $_GET['aid']; ?>">
	<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
	<table id="profile"> 
		<thead>
			<tr>
				<th colspan="6">Medallas de la alianza (<?php echo sizeof($varmedal); ?>)</th>
			</tr>
			<tr>
				<td>Categoría</td>
				<td>Puesto</td>
				<td>Semana</td>
				<td>Puntos</td>
				<td>Medalla</td>
				<td></td>
			</tr>
		</thead>
			<?php
				if(sizeof($varmedal) ==0)
				{ ?>
					<td colspan="5"><center>Esta alianza todavía no tiene medallas</center></td><?php 
				}
				elseif(sizeof($varmedal) >0)
				{
					require_once(__DIR__."/../../GameEngine/MedalLabels.php");
					foreach($varmedal as $medal)
					{
						$title = medalCategoryLabel($medal['categorie'], $medal['points'], true);
						$rank = $medal['plaats'];
						if($rank == '0') { $rank = "<p>Bonus</p>"; } else { $rank = $rank; }
						$week = $medal['week'];
						$points = $medal['points'];
						if($points == '') { $points = "<p>Bonus</p>"; } else { $points = $points; }
						
						echo"
							<tr>
								<td>$title</td>
								<td>$rank</td>
								<td>$week</td>
								<td>$points</td>
								<td><img src=\"../gpack/travian_default/img/t/".$medal['img'].".jpg\"></td>
								<td>
									<input type=\"image\" name=\"medalid\" value=\"".$medal['id']."\" style=\"background-image: url('../gpack/travian_default/img/a/del.gif'); height: 12px; width: 12px;\" src=\"../gpack/travian_default/img/a/x.gif\">
								</td>
							</tr>";
						}
						
						$averagerank = 0;
						foreach($varmedal as $medal)
						{
							$rank = $medal['plaats'];
							if($rank > 0)
							{
								if(is_numeric($rank))
								{
									$i = $i + 1;
									$averagerank = $averagerank + $medal['plaats'];
								}
								else
								{
									$averagerank = $averagerank + 0;
								}
							}
						}
						$average = $averagerank / $i;
						echo "</form><tr><td><b>Puesto promedio</b></td><td>$average</td><td></td><td></td><td>Eliminar todas</td>"; 
					}
				?>
				<td>
					<form action="../GameEngine/Admin/Mods/delallymedalbyaid.php" method="POST">
						<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
						<input type="hidden" name="aid" value="<?php echo $_GET['aid']; ?>">
						<input type="image" name="allyid" value="<?php echo $id; ?>" style="background-image: url('../gpack/travian_default/img/a/del.gif'); height: 12px; width: 12px;" src="../gpack/travian_default/img/a/x.gif">
					</form>
				</td>
			</tbody>
		</table>