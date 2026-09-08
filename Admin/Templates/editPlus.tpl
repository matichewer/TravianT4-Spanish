<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       editPlus.tpl                                                ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

$id = $_GET['uid'];
$uid = $_GET['uid'];
$user = $database->getUserArray($id,1); 
if(isset($id))
{
	?>
	<form action="../GameEngine/Admin/Mods/editPlus.php" method="POST">
		<input type="hidden" name="admid" id="admid" value="<?php echo $_SESSION['id']; ?>">
		<input type="hidden" name="uid" value="<?php echo $uid; ?>" />
		<input type="hidden" name="id" value="<?php echo $id; ?>" />
			<br /><br />
			
			<table id="profile">
				<tbody>
					<td class="details">
						<table>
							<thead>
								<tr>
									<th colspan="2">Plus y bonificaciones de recursos de: <a href="admin.php?p=player&uid=<?php echo $user['id']; ?>"><?php echo $user['username']; ?></a></th>
								</tr>
							</thead>
							<tbody>
								<?php include("playerplusbonus.tpl"); ?>
							<tbody>
						</table>
					</td>
				</tbody>
			</table>
			<br />
			<table id="profile" cellpadding="0" cellspacing="0">
				<thead>
					<tr>
						<th colspan="2">Editar Plus y bonificaciones</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Dar Plus</td>
						<td>
							<input class="fm" name="plus" value="0" style="width: 60%;"> Days
						</td>
					</tr>
					<tr>
						<td>Dar +25% de madera</td>
						<td>
							<input class="fm" name="wood" value="0" style="width: 60%;"> Days
						</td>
					</tr>
					<tr>
					<td>Dar +25% de barro</td>
						<td>
							<input class="fm" name="clay" value="0" style="width: 60%;"> Days
						</td>
					</tr>
					<tr>
						<td>Dar +25% de hierro</td>
						<td>
							<input class="fm" name="iron" value="0" style="width: 60%;"> Days
						</td>
					</tr>
					<tr>
						<td>Dar +25% de cereal</td>
						<td>
							<input class="fm" name="crop" value="0" style="width: 60%;"> Days
						</td>
					</tr>
				</tbody>
			</table>
			<br />
			<center><input type="image" value="submit" src="../img/admin/b/ok1.gif"></center>
		</form>
	<?php
}
else
{
	include("404.tpl");
}
?>