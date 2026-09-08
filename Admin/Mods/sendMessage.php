<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       sendMessage.php                                             ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
#################################################################################

include_once("../../GameEngine/Account.php");
mysql_connect(SQL_SERVER, SQL_USER, SQL_PASS);
mysql_select_db(SQL_DB);
if ($session->access < ADMIN) die("Acceso denegado: esta pantalla es sólo para administradores.");

$uid = $_POST['uid'];
$topic = $_POST['topic'];
$message = $_POST['message'];
$time = time();

$query = "INSERT INTO ".TB_PREFIX."mdata (target, owner, topic, message, viewed, time) VALUES ('$uid', 1, '$topic', '$message', 0, '$time')";

mysql_query($query);

header("Location: ../../../Admin/admin.php?p=Newmessage&uid=".$uid."&msg=ok");
?>