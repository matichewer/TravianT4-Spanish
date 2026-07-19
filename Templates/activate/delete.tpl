<h4>¿No recibiste el correo?</h4>
<div class="activation">
			Para poder jugar a Travian necesitas una dirección de correo electrónico válida, a la que se enviará un código de activación. En circunstancias muy especiales, es posible que no recibas el correo de registro.
			<br>
			<br>
			El motivo puede ser alguno de los siguientes:

			<ul>
				<li>Error tipográfico en la dirección.</li>
				<li>La bandeja de entrada está llena.</li>
				<li>Confusión con la dirección: por ejemplo, escribiste .com en lugar de .ir por error.</li>
				<li>Tu dirección de correo está enviando este correo por error a la carpeta de spam.</li>
			</ul>
			Puedes cancelar tu registro y volver a registrarte con otra dirección de correo. Te enviaremos un nuevo código de activación.
		</div>
        <hr>
        <h4>Eliminar esta cuenta</h4>
        <form action="activate.php" method="post">
        <input type="hidden" name="id" value="<?php echo $_GET['id']; ?>" />
		<input type="hidden" name="ft" value="a3" />
			<div class="boxes activation boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
				<table cellpadding="1" cellspacing="1" class="transparent">
					<tbody><tr class="top">
						<th>Nombre:</th>
						<td class="name"><?php echo $database->getActivateField($_GET['id'],"username",0); ?></td>
					</tr>
					<tr class="btm">
						<th>Contraseña:</th>
						<td><input class="text" type="password" name="pw" maxlength="20"></td>
					</tr>
				</tbody></table>
				</div>
				</div>
			<div class="clear"></div><button type="submit" value="Törlés" name="delreports" id="btn_delete" onclick="document.snd.w.value=screen.width+':'+screen.height;"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Eliminar</div></div></button>
		</form>