<?php
$id = $_GET['uid'];
if(isset($id)){
$user = $database->getUserArray($id,1);
$varray = $database->getProfileVillages($id);
$varmedal = $database->getProfileMedal($id);
foreach($varray as $vil) {
	$totalpop += $vil['pop'];
}
?>
<div align="center">
	<ul class="tabs">
    <?php if(isset($_GET['Edit'])){ ?>
		<li><a id="a_title_1" href="?p=Users&uid=<?php echo $user['id'];?>">Perfil del jugador</a></li>
		<li><a id="a_title_2" class="current" href="#">Editar perfil</a></li>
    <?php }else{ ?>
		<li><a id="a_title_1" onclick="SetCurrent(1);" class="current" href="#">Perfil del jugador</a></li>
		<li>Editar jugador</li>
	<?php } ?>
	</ul>
</div>
<br>
<div id="div_1">
<?php if(isset($_GET['Edit'])){ ?>


<?php }else{ ?>
<table width="600" border="1" align="center" cellpadding="3" id="profile">
      <thead>
	    	<tr bgcolor="#E2E2E2" height="25">
				<th colspan="2">Jugador <a href="?p=Users&uid=<?php echo $user['id'];?>"><?php echo $user['username'];?></a></th>
		</tr>
    		<tr align="center" bgcolor="#E2E2E2" height="25">
                <td><B>Datos</B></td>
                <td><B>Descripción</B></td>
       	</tr>
   	</thead>
		<tbody>
		    <tr>
                <td class="details">
              <table cellpadding="3" cellspacing="0" border="1" width="100%">
                        <tr align="right">
                            <th>Posición:</th>
                            <td><?php echo $ranking->getUserRank($user['username']); ?></td>
                        </tr>
						<tr align="right">
                            <th>Tribu</th>
                            <td><?php if($user['tribe'] == 1) {echo "Romanos";}else if($user['tribe'] == 2) {echo "Germanos";}else if($user['tribe'] == 3) {echo "Galos";} ?></td>
			            </tr>
			            <tr align="right">
                            <th>Alianza:</th>
                            <td><?php
				if($user['alliance'] == 0) {
					echo "-";
				}
                else {
					echo "<a href=\"?p=alliance&aid=".$user['alliance']."\">".$database->getAllianceName($user['alliance'])."</a>";
                }
				?>
                			</td>
            			</tr>
            			<tr align="right">
                            <th>Aldeas:</th>
                            <td><?php echo count($varray);?></td>
                        </tr>
			            <tr align="right">
			                <th>Población:</th>
				                <td><?php echo $totalpop;?></td>
			            </tr>
            <?php
            if(isset($user['birthday']) && $user['birthday'] != 0) {
				$age = date("Y")-substr($user['birthday'],0,4);
				echo "<tr align='right'><th>Edad: </th><td>$age</td></tr>";
            }
            if(isset($user['gender']) && $user['gender'] != 0) {
				$gender = ($user['gender']== 1)? "Hombre" : "Mujer";
				echo "<tr align='right'><th>Sexo: </th><td>".$gender."</td></tr>";
            }

            echo "<tr align='right'><th>Correo electrónico: </th><td>".$user['email']."</td></tr>";
			?>
                <tr><td colspan="2" class="empty"></td></tr>
          <tr>
            	<td colspan="2" class="desc2">
                	<div class="desc2div" style="height:200"><center><?php echo nl2br($user['desc1']); ?></center></div>
                </td>
            </tr>
        </table>
        </td>
        <td class="desc1" width="285">
        <table cellpadding="3" cellspacing="0" border="1" width="100%">

	<?php
		if($_SESSION['access'] == ADMIN){
			echo '<tr align="right"><td colspan="2"><a href="?p=Users&uid='.$user['id'].'&Edit">&raquo;<b> Editar perfil</b></a></td></tr>';
		}
	?>
		<tr align="right"><td colspan="2"><a href="?p=Messages&uid=<?php echo $user['id']; ?>&Send">&raquo;<b> Escribir mensaje</b></a></td></tr>
	<?php if($_SESSION['access'] == ADMIN){ ?>
	<tr><td colspan="2"><a onclick="return (function(){
				('<b>Eliminar jugador <?php echo $user['username'];?></b><br><br>¿Quieres eliminar a este jugador?').dialog(
				{
					onOkay: function(dialog, contentElement)
					{
						window.location.href = '?p=Users&uid=<?php echo $user['id']; ?>&Edit'}
				});
				return false;
			})()" href="#">&raquo;<b> Eliminar jugador</b></a></td></tr>
	<?php } ?>
		<tr align="right"><td colspan="2"><a href="?p=Users&uid=<?php echo $user['id']; ?>&Ban">&raquo;<b> Bloqueado</b></a></td></tr>
		<tr><td colspan="2" class="empty"></td></tr>
            <tr>
            	<td colspan="2" class="desc2">
               	  <div class="desc2div" style="height:240"><center><?php echo nl2br($user['desc2']); ?></center></div>
                </td>
            </tr>
        </table>

        </td>
    </tr>

    </tbody>

</table>
<?php } } ?>
</div>
