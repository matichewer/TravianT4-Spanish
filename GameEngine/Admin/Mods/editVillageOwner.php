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

$id = $_POST['did'];

// Igual que en una conquista: la aldea cambia de dueño, así que la celebración se
// cancela. Si sobrevive, los puntos de cultura que pagó el dueño anterior se los
// lleva el nuevo cuando la barrida cierra la fiesta.
// Y `created` se reescribe con la hora del traspaso: para el dueño nuevo la aldea
// se funda ahora, que es la fecha con la que se ordena su cartel de aldeas.
mysql_query("UPDATE ".TB_PREFIX."vdata SET
	owner = '".$_POST['newowner']."',
	celebration = 0,
	type = 0,
	created = ".time()."
	WHERE wref = $id AND capital = 0") or die(mysql_error());

// header("Location: ../../../Admin/admin.php?p=player&uid=".$_POST['newowner']."");

$url = $_SERVER['HTTP_REFERER'];
$data = parse_url($url);

header('Location: '.$data['path'].'?p=player&uid='.$_POST['newowner']);
?>