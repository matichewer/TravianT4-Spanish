<?php

		include ("../../config/connection.php");
        include ("../../config/config.php");
        include ("../../GameEngine/Admin/database.php");
        include ("../../GameEngine/Database.php");
        include ("../../GameEngine/Lang/".LANG.".php");
        include ("../../GameEngine/NatarVillage.php");
		
		$StartNatars = true;
/**
 * Functions
 */
        
        if(isset($_POST['mhpw'])) {
        	$password = $_POST['mhpw'];
        	mysql_query("UPDATE " . TB_PREFIX . "users SET password = '" . md5($password) . "' WHERE username = 'Multihunter'");
			mysql_query("UPDATE " . TB_PREFIX . "users SET password = '" . md5($password) . "' WHERE username = 'Support'");
        	$wid = $admin->getWref(1, 0);
        	$uid = 4;
        	$status = $database->getVillageState($wid);
        	if($status == 0) {
        		$database->setFieldTaken($wid);
        		$database->addVillage($wid, $uid, 'Multihunter', '1');
        		$database->addResourceFields($wid, $database->getVillageType($wid));
        		$database->addUnits($wid);
        		$database->addTech($wid);
        		$database->addABTech($wid);
        		// El nombre se fija a mano y no se deja armar a addVillage(): esa funcion
        		// devuelve "Aldea de Multihunter", sin el articulo, y las instalaciones
        		// viejas dejaban directamente el "Multihunter's village" en ingles. Se ve
        		// en el mapa, al lado de la capital natar.
        		mysql_query("UPDATE " . TB_PREFIX . "vdata SET name = 'Aldea del Multihunter' WHERE wref = " . (int)$wid);
        	}
        }


if($StartNatars){
		$username = "Natars";
        $password = $_POST['mhpw'];
        $email = "natars@travianx.com";
        $desc = "[#natars]";
		$uid = 2;

        mysql_query("INSERT INTO " . TB_PREFIX . "users (id,username,password,access,email,timestamp,desc2,tribe,location,act,protect,quest,fquest) VALUES ('$uid', 'Natars', '" . md5($password) . "', 2, '$email', ".time().", '$desc', 5, '', '', 0, 25, 35)");
        
        $wid = $admin->getWref(0, 0);
        $status = $database->getVillageState($wid);
        if($status == 0) {
        	$database->setFieldTaken($wid);
            // addVillage($wid, $uid, $username, $capital): los dos últimos argumentos
            // estaban invertidos, así que la capital natar terminaba nombrada con el '1'
            // ("Aldea de 1"). El `capital` mal pasado no hacía daño porque la línea de
            // más abajo lo corrige, pero el nombre quedaba a la vista en el mapa.
            $database->addVillage($wid, $uid, 'Natars', '1');
            mysql_query("UPDATE " . TB_PREFIX . "vdata SET name = 'Capital natar' WHERE wref = " . (int)$wid);
        	$database->addResourceFields($wid, $database->getVillageType($wid));
        	$database->addUnits($wid);
        	$database->addTech($wid);
        	$database->addABTech($wid);
        }
        // Esta es la aldea desde la que parten las oleadas contra las Maravillas.
        // Debe ser la capital natar; las 13 aldeas de Maravilla no lo son.
        mysql_query("UPDATE " . TB_PREFIX . "vdata SET capital = IF(wref = " . (int)$wid . ", 1, 0) WHERE owner = $uid") or die(mysql_error());
        natarRestockGarrison($wid, natarCapitalGarrison());
        // Le arma campos, almacenamiento y población coherentes con su guarnición. La
        // capital consume más cereal del que cualquier aldea puede producir, así que
        // igual queda en rojo: quien no la deja morir de hambre es starvation(), que no
        // toca aldeas NPC. Ver GameEngine/NatarVillage.php.
        natarProvisionVillage($wid);

	for($i=1;$i<=14;$i++){
		switch ($i) {
			case 1:
				$x=0;
				$y=-21;
				break;
			case 2:
				$x=15;
				$y=15;
				break;
			case 3:
				$x=21;
				$y=0;
				break;
			case 4:
				$x=-2;
				$y=12;
				break;
			case 5:
				$x=-12;
				$y=2;
				break;
			case 6:
				$x=-5;
				$y=-11;
				break;
			case 7:
				$x=15;
				$y=-15;
				break;
			case 8:
				$x=11;
				$y=6;
				break;
			case 9:
				$x=0;
				$y=21;
				break;
			case 10:
				$x=-15;
				$y=15;
				break;
			case 11:
				$x=-21;
				$y=0;
				break;
			case 12:
				$x=-15;
				$y=-15;
				break;
			case 13:
				$x=9;
				$y=-8;
				break;
		}
		
		$wid = $admin->getWref($x, $y);
        $status = $database->getVillageState($wid);
        if($status == 0) {
        	$database->setFieldTaken($wid);
        	$database->addVillage($wid, $uid, 'Natars', '1');
        	$database->addResourceFields($wid, $database->getVillageType($wid));
        	$database->addUnits($wid);
        	$database->addTech($wid);
        	$database->addABTech($wid);
			mysql_query("UPDATE " . TB_PREFIX . "vdata SET name = 'Aldea de la Maravilla' WHERE wref = '$wid'");
			mysql_query("UPDATE " . TB_PREFIX . "vdata SET capital = 0 WHERE wref = '$wid'");
			mysql_query("UPDATE " . TB_PREFIX . "vdata SET natar = 1 WHERE wref = '$wid'");
			mysql_query("UPDATE " . TB_PREFIX . "fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 10, f19t = 23, f19 = 10, f99t = 40, f26 = 0, f26t = 0, f21 = 1, f21t = 15, f39 = 1, f39t = 16 WHERE vref = " . $wid . "");
			natarRestockGarrison($wid, natarWonderGarrison());
			// Campos de cereal al nivel que sostiene la guarnición, más el resto de los
			// campos, almacén y granero: sin esto la aldea nacía con el balance en unos
			// -45.000/h y se vaciaba sola, y con tope 800 nunca daba botín.
			natarProvisionVillage($wid);
        }
	}
}

        header("Location: ../index.php?s=5");

?>
