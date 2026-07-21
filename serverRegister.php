<?php
#################################################################################
##                                                                             ##
##              -= YOU MUST NOT REMOVE OR CHANGE THIS NOTICE =-                ##
##                                                                             ##
## --------------------------------------------------------------------------- ##
##                                                                             ##
##  Project:       ZravianX                                                    ##
##  Version:       2011.11.01                                                  ##
##  Filename:      serverRegister.php                                          ##
##  Developed by:  Advocaite                                                   ##
##  Edited by:     ZZJHONS                                                     ##
##  License:       Creative Commons BY-NC-SA 3.0                               ##
##  Copyright:     ZravianX (c) 2011 - All rights reserved                     ##
##  URLs:          http://zravianx.zzjhons.com                                 ##
##  Source code:   http://www.github.com/ZZJHONS/ZravianX                      ##
##                                                                             ##
#################################################################################
error_reporting(E_ALL);
if (!file_exists('config/config.php')) {
header("Location: install/");
}
       include ("GameEngine/Database.php");
       include ("GameEngine/Lang/".$result['lang'].".php");
	   $users = mysql_num_rows(mysql_query("SELECT * FROM " . TB_PREFIX . "users"));
	   $time = time() - 60*10;
	   $online = mysql_num_rows(mysql_query("SELECT * FROM " . TB_PREFIX . "users WHERE timestamp > $time AND tribe!=0 AND tribe!=4 AND tribe!=5"));
?>
<h3 class="pop popgreen bold">¡Bienvenido al nuevo TRAVIAN 4!</h3>
<h3>Elige un servidor.</h3>
<br />
<div class="server serverA serverbig servernormal serverbignormal ">
    <a class="link" onclick="" href="anmelden.php" title="Registrarse en el servidor 1.">
    <span class="name">Servidor 1</span>
    <span class="player" title="Jugadores en total: <?php echo $users-4; ?>"><?php echo $users-4; ?></span>
    <span class="online" title="Jugadores conectados: <?php echo $online; ?>"><?php echo $online; ?></span>
    <span class="start">El servidor comenzó hace <?php echo round((time()-COMMENCE)/86400);?> días.</span>
    <span class="mark"></span>
    <img class="hover" src="img/x.gif">
</a>
</div>
