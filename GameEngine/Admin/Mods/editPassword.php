<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       editUser.php                                                ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

include_once("validateMultihunterSession.php");

$id = (int)$_POST['uid'];
$newpw = isset($_POST['newpw']) ? (string)$_POST['newpw'] : '';

// El campo del formulario abría relleno con el literal "new password", así que apretar
// Enter sin escribir nada le ponía esa contraseña al jugador. Ahora abre vacío, y una
// contraseña vacía se rechaza en vez de dejar la cuenta con md5('').
if($id <= 0 || $newpw === '') {
	header('Location: ../../../Admin/admin.php?p=player&uid='.$id.'&e=pass');
	exit;
}
$pass = md5($newpw);

mysql_query("UPDATE ".TB_PREFIX."users SET 
	password = '".$pass."'  
	WHERE id = $id") or die(mysql_error());

// header("Location: ../../../Admin/admin.php?p=player&uid=".$id."");

$url = $_SERVER['HTTP_REFERER'];
$data = parse_url($url);

header('Location: '.$data['path'].'?p=player&uid='.$id);
?>