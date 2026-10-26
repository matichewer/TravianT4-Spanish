<form action="spieler.php" method="POST">
<input type="hidden" name="ft" value="p3">
<input type="hidden" name="a" value="<?php echo htmlspecialchars($session->checker, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="uid" value="<?php echo $session->uid; ?>" />
	
<h4 class="round">Cambiar contraseña</h4>

<table cellpadding="1" cellspacing="1" id="change_pass" class="account transparent">
	<tbody>
		<tr>
			<th class="col1">Contraseña actual</th>
			<td><input class="text" type="password" name="pw1" maxlength="20"></td>
		</tr>
		<tr>
			<th>Nueva contraseña</th>
			<td><input class="text" type="password" name="pw2" maxlength="20"></td>
		</tr>
		<tr>
			<th>Repetir nueva contraseña</th>
			<td><input class="text" type="password" name="pw3" maxlength="20"></td>
		</tr>

	</tbody>
</table>
<?php
if($form->getError("pw") != "") {
echo "<span class=\"error\">".$form->getError('pw')."</span>";
}
?>
	<h4 class="round spacer">Cambiar dirección de correo</h4>
	<table id="change_mail" cellpadding="1" cellspacing="1" class="transparent">
		<tbody>
			<tr>
				<td colspan="2">
Ingresa el correo actual y el nuevo. Cada correo te dará un código que deberás ingresar aquí		</td>
			</tr>
			<tr>
				<th>Correo actual:</th>
				<td><input class="text" type="text" name="email_alt" maxlength="50" size="40"></td>
			</tr>
			<tr>
				<th>Correo nuevo:</th>
				<td><input class="text" type="text" name="email_neu" maxlength="50" size="40"></td>
			</tr>
		</tbody>
	</table>
    <?php
if($form->getError("email") != "") {
echo "<span class=\"error\">".$form->getError('email')."</span>";
}
?>
<h4 class="round spacer">Representantes</h4>
<input type="hidden" name="sitter_flag_posted" value="1">

<?php if($form->getError('sit') !== '') { echo '<p class="error">'.htmlspecialchars($form->getError('sit'), ENT_QUOTES, 'UTF-8').'</p>'; } ?>
<div class="text">Agrega un representante para que pueda administrar tu cuenta</div>

<script type="text/javascript">
function cloneName(obj, id)
{
	$(id).innerHTML = obj.value.stripTags();
}

</script>

<table class="sitters transparent">
				<tbody><tr>
		<th>
				<div class="boxes boxesColor lightGreen"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><span>Representante 1				</span>	</div>
				</div>
		</th><td>
<?php

if($session->userinfo['sit1'] == 0) {
echo "<input class=\"text\" type=\"text\" name=\"v1\" maxlength=\"15\">";
}
if($session->userinfo['sit1'] != 0) {
	
    echo "<button type=\"button\" value=\"del\" class=\"icon\" onclick=\"window.location.href = 'spieler.php?s=3&e=3&id=".$session->userinfo['sit1']."&a=".$session->checker."&type=1'; return false;\"><img src=\"img/x.gif\" class=\"del\" alt=\"helyettes\"></button>";
    echo "&nbsp;".$database->getUserField($session->userinfo['sit1'],"username",0)."";

}
?></td>
	</tr>
				<tr>
		<th>
				<div class="boxes boxesColor orange"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><span>Representante 2</span>	</div>
				</div>
		</th><td>
<?php
if($session->userinfo['sit2'] == 0) {
echo "<input class=\"text\" type=\"text\" name=\"v2\" maxlength=\"15\">";
}
if($session->userinfo['sit2'] != 0) {
	
    echo "<button type=\"button\" value=\"del\" class=\"icon\" onclick=\"window.location.href = 'spieler.php?s=3&e=3&id=".$session->userinfo['sit2']."&a=".$session->checker."&type=2'; return false;\"><img src=\"img/x.gif\" class=\"del\" alt=\"helyettes\"></button>";
    echo "&nbsp;".$database->getUserField($session->userinfo['sit2'],"username",0)."";

}
?></td>
	</tr>
			</tbody></table>
            
<h4 class="round spacer">Cuentas que representas</h4>
<input type="hidden" name="sitter_flag_posted" value="1">

<div class="text">Estas son las cuentas que representas</div>

<table class="sitters transparent"><tbody>
<?php
$represented = $database->getSitee($session->uid);
if(empty($represented)) { echo '<tr><td>No representas ninguna cuenta</td></tr>'; }
foreach($represented as $representedAccount) {
    $ownerId = (int)$representedAccount['id'];
    $ownerName = htmlspecialchars($database->getUserField($ownerId, 'username', 0), ENT_QUOTES, 'UTF-8');
    $removeUrl = 'spieler.php?s=3&e=3&id='.(int)$session->uid.'&owner='.$ownerId.'&a='.urlencode($session->checker).'&type=3';
    echo '<tr><td><a class="delete" href="'.htmlspecialchars($removeUrl, ENT_QUOTES, 'UTF-8').'">Dejar de representar</a> &nbsp;'.$ownerName.'</td></tr>';
}
?>
</tbody></table>

<h4 class="round spacer">Eliminar cuenta</h4>
<table cellpadding="1" cellspacing="1" id="del_acc" class="account transparent">
	<tbody>
		<tr>
			<td colspan="2">Aquí puedes eliminar tu cuenta. Una vez iniciada la cancelación, tomará tres días completar la eliminación de tu cuenta.</td>
		</tr>
        
        
<?php
$timestamp = $database->isDeleting($session->uid);
if($timestamp) {
echo "<tr><td colspan=\"2\" class=\"count\">";
if($timestamp > time()+48*3600) {
echo "<button type=\"button\" value=\"del\" class=\"icon\" onclick=\"window.location.href = 'spieler.php?s=3&id=".$session->uid."&a=1&e=4'; return false;\">
                    <img src=\"img/x.gif\" class=\"del\" alt=\"del\"></button>";
        }
		$time=$generator->getTimeFormat(($timestamp-time()));
        echo " La cuenta será eliminada en <span id=\"timer1\">".$time."</span> .</td></tr>";
}
else {
?>
			<tr>
				<th>¿Eliminar cuenta?</th>
				<td class="del_selection">
					<label><input class="radio" type="radio" name="del" value="1"> Sí</label>
					<label><input class="radio" type="radio" name="del" value="0" checked="checked"> No</label>
				</td>
			</tr>
            <tr>
				<th>Confirmar contraseña</th>
				<td><input class="text" type="password" name="del_pw" maxlength="20"></td>
			</tr>
            <?php 
        }
        ?>
						
                </tbody>
</table>
    <?php
if($form->getError("del") != "") {
echo "<span class=\"error\">".$form->getError("del")."</span>";
}
?>
<h4 class="round spacer">Oro</h4>
<table cellpadding="1" cellspacing="1" class="newsletter transparent">
		<tbody>
<tr>
					<td colspan="2">Actualmente tienes <img src="img/x.gif" class="gold" alt="Oro"> <b><?php echo $session->gold; ?></b> piezas de oro.</td>
				</tr>
					</tbody>
	</table>
<br />
<div class="save_button">
<button type="submit" value="Guardar"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Guardar</div></div></button></div>

</form>
