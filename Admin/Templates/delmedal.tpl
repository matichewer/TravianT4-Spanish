<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       gold.php                                                    ##
##  Developed by:  Dzoki                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##  Improved:      aggenkeech                                                  ##
#################################################################################

if($_SESSION['access'] < ADMIN) die("Access Denied: You are not Admin!");
include("../config/config.php");
$id = $_SESSION['id'];

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."medal");
$nummedals = mysql_num_rows($sql);
?>


<style>
	.del {width:12px; height:12px; background-image: url(img/admin/icon/del.gif);}
</style>
<table id="member">
	<thead>
		<tr>
			<th>Resumen de medallas</th>
		</tr>
	</thead> 
</table>
<table id="profile">
	<thead>
		<tr>
			<td>Semana</td>
			<td>Medallas</td>
		</tr>
	</thead>
	<tbody>
		<?php
			$sql = mysql_query("SELECT * FROM ".TB_PREFIX."medal");
			$tot = mysql_num_rows($sql);
			$sql = mysql_query("SELECT week FROM ".TB_PREFIX."medal ORDER BY week DESC LIMIT 1");
			$week = mysqli_fetch_assoc($sql)['week'];
			echo "<tr><td><center>$week</center></td><td><center>$tot</center></td></tr>";
		?>
	</tbody>
</table>
<br />
<br />



<form action="../GameEngine/Admin/Mods/deletemedalbyweek.php" method="POST">
<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
<table id="member">
	<thead>
		<tr>
			<th>Medallas semana por semana</th>
		</tr>
	</thead> 
</table>
<table id="profile">
	<thead>
		<tr>
			<td>Semana</td>
			<td>Medallas</td>
			<td></td>
		</tr>
	</thead>
	<tbody>
		<?php
			for($j = 0; $j<$week; $j++)
			{
				$newweek = $j+1;
				
				$sql = mysql_query("SELECT * FROM ".TB_PREFIX."medal WHERE week = $newweek");
				$tot = mysql_num_rows($sql);
				
				echo "<tr><td>$newweek</td><td>$tot</td><td><input type=\"image\" name=\"medalweek\" value=\"".$newweek."\" style=\"background-image: url('../gpack/travian_default/img/a/del.gif'); height: 12px; width: 12px;\" src=\"../gpack/travian_default/img/a/x.gif\"></td>";
			}
		?>
	</tbody>
</table>
</form>
<br />
<br />






<table id="member">
	<thead>
		<tr>
			<th>Todas las medallas (<?php echo $nummedals; ?>)</th>
		</tr>
	</thead>
</table>
<table id="profile">
	<thead>
		<tr>
			<td>Nº</td>
			<td>Código BB</td>
			<td>Medalla</td>
			<td>Categoría</td>
			<td>Jugador</td>
			<td>Puesto</td>
			<td>Semana</td>
			<td>Puntos</td>
		</tr>
	</thead>
	<tbody>
		<?php
			require_once(__DIR__."/../../GameEngine/MedalLabels.php");
			$query = "SELECT * FROM ".TB_PREFIX."medal ORDER BY id DESC";
			$result = mysql_query($query);
			while($row = mysql_fetch_array($result))
			{
				$i = $i + 1;
				$title = medalCategoryLabel($row['categorie'], $row['points']);
				$rank = medalIsBonus($row['categorie']) ? "" : $row['plaats'];
				$week = $row['week'];
				$points = medalIsBonus($row['categorie']) ? "" : $row['points'];
				$bb = $row['id'];
				$playerid = $row['userid'];
				
				$unq = "SELECT username FROM ".TB_PREFIX."users where id = $playerid";
				$user = mysqli_fetch_assoc(mysql_query($unq))['username'];
				$username = $user;
				
				$player = "<a href=\"admin.php?p=player&uid=".$playerid."\">$username</a>";
				echo"
				<tr>
					<td>$i</td>
					<td>[#$bb]</td>
					<td><img src=\"../../gpack/travian_default/img/t/".$row['img'].".jpg\"></td>
					<td>$title</td>
					<td>$player</td>
					<td>$rank</td>
					<td>$week</td>
					<td>$points</td>
				</tr>";
			}
		?>
	</tbody>
</table>