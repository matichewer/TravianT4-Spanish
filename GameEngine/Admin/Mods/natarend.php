<?php 

/** --------------------------------------------------- **\ 
| ********* DO NOT REMOVE THIS COPYRIGHT NOTICE ********* | 
+---------------------------------------------------------+ 
| Released by:   Dzoki < dzoki.travian@gmail.com >        | 
| Copyright:     TravianX Project All rights reserved     | 
\** --------------------------------------------------- **/ 


    include_once ("../../Session.php");
    include_once ("../../NatarVillage.php"); 

/** 
 * If user is not administrator, access is denied! 
 */ 
    if($session->access < ADMIN) 
        die("Access Denied: You are not Admin!"); 


/** 
 * $id      admin userid 
 * $kid     random position on map (++|--|+-|-+) 
 * $amt     number of villages to add 
 * $wid     village id (wref/vref) 
 * $type    needed to generate village fields 
 */ 

    $id = $_POST['id']; 
    $amt = $_POST['vill_amount']; 

    for($i = 1; $i <= $amt; $i++) { 

        $kid = rand(1, 4); 
        $wid = $database->generateBase($kid); 
        $type = $database->getVillageType($wid); 

        $database->setFieldTaken($wid);
        // Se crea por el mismo camino que el instalador: dueño, economia y clase NPC
        // salen de GameEngine/NatarVillage.php. Antes esto insertaba la aldea a mano con
        // `owner` = 3, que en las instalaciones actuales es la Naturaleza y no los Natars,
        // y sin nada de la economia que la aldea necesita para no morirse de hambre ni
        // para dar botin.
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


/** 
 * Insert the log into the database 
 */ 
    mysql_query("INSERT INTO ".TB_PREFIX."admin_log values (0,$id,'Generated <b>$amt</b> of WW villages',".time().")") or die(mysql_error()); 

/** 
 * Redirect 
 */ 
    header("Location: ../../../Admin/admin.php?p=natarend&g"); ?>