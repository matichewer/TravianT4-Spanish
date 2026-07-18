<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       Mailer.php                                                  ##
##  Developed by:  Dixie                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

class Mailer {

	function sendActivate($email,$username,$pass,$act) {

	$subject = "Bienvenido a ".SERVER_NAME;
	$message = "Hola ".$username."

Gracias por registrarte.

----------------------------
Usuario: ".$username."
Contraseña: ".$pass."
Código de activación: ".$act."
----------------------------

Para activar tu cuenta, haz clic en este enlace:
".HOMEPAGE."activate.php?code=".$act."

Gracias,
".SERVER_NAME;

	$headers = "From: ".ADMIN_EMAIL."\n";

	mail($email, $subject, $message, $headers);
	}


	function sendPassword($email, $npw, $act, $username) {

	$subject = "¿Has olvidado tu contraseña?";
	$message = "Hola ".$username."

Este correo se envió porque solicitaste una contraseña nueva.
Si realizaste la solicitud, continúa con las instrucciones.

----------------------------
Usuario: ".$username."
Contraseña nueva: ".$npw."
----------------------------

Para activar la contraseña nueva, haz clic en este enlace:
".HOMEPAGE."password.php?user=".$username."&npw=".$npw."&code=".$act."

Gracias,
".SERVER_NAME;

	$headers = "From: ".ADMIN_EMAIL."\n";

	mail($email, $subject, $message, $headers);
	}

};
$mailer = new Mailer;
?>
