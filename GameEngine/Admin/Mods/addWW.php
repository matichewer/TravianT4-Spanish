<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       addArtefacts.php                                            ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

ini_set('max_execution_time', 1000);
error_reporting(E_ALL);

// Database.php ya arrastra config/connection.php y config/config.php. El
// include_once("../../config.php") que habia aca apuntaba a GameEngine/config.php, que
// no existe: este mod estaba roto desde antes de tocarlo.
include_once("../../Database.php");
include_once("../../NatarVillage.php"); 

$session = $_POST['admid'];

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id = ".$session."");
$access = mysql_fetch_array($sql);
$sessionaccess = $access['access'];

if($sessionaccess != 9) die("<h1><font color=\"red\">Access Denied: You are not Admin!</font></h1>");

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE username LIKE 'Natars'");
$result = mysql_fetch_assoc($sql);

if(count($result) > 1) ## Natar Account Exists
{
	if(SPEED > 3){ $speed = 5; }
	else { $speed = SPEED; }
    $amt = $_POST['amount']; 

    for($i = 1; $i <= $amt; $i++)
	{
		$kid = rand(1, 4); 
		$wid = $database->generateBase($kid); 
		$type = $database->getVillageType($wid); 
		$database->setFieldTaken($wid); 
        // Mismo camino que el instalador: dueno, economia y clase NPC salen de
        // GameEngine/NatarVillage.php. Antes esto insertaba la aldea a mano con
        // `owner` = 3, que en las instalaciones actuales es la Naturaleza y no los Natars.
        $database->addVillage($wid, natarsAccountId(), 'Natars', '0');
        $database->addResourceFields($wid, $type);
        $database->addUnits($wid);
        $database->addTech($wid);
        $database->addABTech($wid);
        mysql_query("UPDATE `".TB_PREFIX."vdata` SET `name` = '".mysql_real_escape_string(natarWonderVillageName($wid))."', `capital` = 0, `natar` = 1 WHERE `wref` = ".(int)$wid) or die(mysql_error());
        // Los edificios salen de GameEngine/NatarVillage.php. Esta linea traia todavia la
        // residencia (f28t = 25) que el instalador ya no pone, asi que una Maravilla creada
        // desde el panel nacia inconquistable sin catapultas.
        natarWonderBuildings($wid);
        natarRestockGarrison($wid, natarWonderGarrison());
        natarProvisionVillage($wid);
    }
    header("Location: ../../../Admin/admin.php?p=addWW&g&amt=".$_POST['amount'].""); 
}
else
{
	header("Location: ../../../Admin/admin.php?p=npctribecreatenatar");
}?>