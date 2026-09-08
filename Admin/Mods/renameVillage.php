<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       renameVillage.php                                           ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
#################################################################################

include_once("../../Account.php");

mysql_connect(SQL_SERVER, SQL_USER, SQL_PASS);
mysql_select_db(SQL_DB);

if ($session->access < ADMIN) die("Acceso denegado: esta pantalla es sólo para administradores.");

$did = $_POST['did'];
$name = $_POST['villagename'];
$sql = "UPDATE ".TB_PREFIX."vdata SET name = '$name' WHERE wref = $did";

mysql_query($sql);

header("Location: ../../../Admin/admin.php?p=village&did=".$did."&name=".$name."");
?>