<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<head>

  <link REL="shortcut icon" HREF="favicon.ico"/>

	<title><?php if($_SESSION['access'] == ADMIN){ echo 'Panel de administración'; } else if($_SESSION['access'] == MULTIHUNTER){ echo 'Panel de Multihunter'; } ?></title>    

	<link rel=stylesheet type="text/css" href="../img/admin/admin.css">

	<link rel=stylesheet type="text/css" href="../img/admin/acp.css">

	<link rel=stylesheet type="text/css" href="../img/../img.css">

		<script src="mt-full.js?423cb"  type="text/javascript"></script>

	<script src="ajax.js" type="text/javascript"></script>



	<meta http-equiv="content-type" content="text/html; charset=UTF-8">

	<meta http-equiv="imagetoolbar" content="no">

</head>
<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       admin_log.tpl                                               ##
##  Developed by:  Dzoki                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

if($_SESSION['access'] < ADMIN) die("Acceso denegado: esta pantalla es sólo para administradores."); ?>

<?php
		
		$no = count($database->getAdminLog());
		$log = $database->getAdminLog();
		for($i=0;$i<$no;$i++) {
		$admid = $log[$i]['user']?>
		------------------------------------<br>
		<b>ID del registro:</b> <?php echo $log[$i]['id']; ?><br />
		<b>Administrador:</b> <?php $user = $database->getUserField($admid,"username",0);
		if($user == 'Multihunter') {
		echo '<b>CONTROL PANEL</b>';
		} else { echo '<a href="admin.php?p=player&uid='.$admid.'">'.$user.'</a>'; }
			?><br />
		<b>Acción:</b> <?php echo $log[$i]['log']; ?><br />
		<b>Fecha:</b> <?php echo date("d.m.Y H:i:s",$log[$i]['time']+3600*2); ?><br />
	
	
	<?php }  ?>
		 
