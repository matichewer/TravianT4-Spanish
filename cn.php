<?php

/** --------------------------------------------------- **\
| ********* DO NOT REMOVE THIS COPYRIGHT NOTICE ********* |
+---------------------------------------------------------+
| Credits:     All the developers including the leaders:  |
|              Advocaite & Dzoki & Donnchadh              |
|                                                         |
| Copyright:   TravianX Project All rights reserved       |
\** --------------------------------------------------- **/

        include_once ("GameEngine/Session.php");
        include_once ("config/config.php");

        mysql_connect(SQL_SERVER, SQL_USER, SQL_PASS);
        mysql_select_db(SQL_DB);

/**
 * If user is not administrator, access is denied!
 */
        if($session->access < ADMIN)
		die("Acceso denegado: no eres administrador.");

/**
 * SMALL ARTEFACTS
 */
        function Artefact($uid, $type, $size, $art_name, $village_name, $desc, $effect, $img) {
        	global $database;
        	$kid = rand(1, 4);
        	$wid = $database->generateBase($kid);
        	$database->addArtefact($wid, $uid, $type, $size, $art_name, $desc, $effect, $img);
        	$database->setFieldTaken($wid);
        	$database->addVillage($wid, $uid, $village_name, '0');
        	$database->addResourceFields($wid, $database->getVillageType($wid));
        	$database->addUnits($wid);
        	$database->addTech($wid);
        	$database->addABTech($wid);
        	mysql_query("UPDATE " . TB_PREFIX . "vdata SET pop = " . rand(10, 200) . " WHERE wref = $wid");
        	mysql_query("UPDATE " . TB_PREFIX . "vdata SET name = '$village_name' WHERE wref = $wid");
        	if(SPEED > 3) {
        		$speed = 5;
        	} else {
        		$speed = SPEED;
        	}
        	if($size == 1) {
        		mysql_query("UPDATE " . TB_PREFIX . "units SET u41 = " . (rand(1000, 2000) * $speed) . ", u42 = " . (rand(1500, 2000) * $speed) . ", u43 = " . (rand(2300, 2800) * $speed) . ", u44 = " . (rand(25, 75) * $speed) . ", u45 = " . (rand(1200, 1900) * $speed) . ", u46 = " . (rand(1500, 2000) * $speed) . ", u47 = " . (rand(500, 900) * $speed) . ", u48 = " . (rand(100, 300) * $speed) . " , u49 = " . (rand(1, 5) * $speed) . ", u50 = " . (rand(1, 5) * $speed) . " WHERE vref = " . $wid . "");
        		mysql_query("UPDATE " . TB_PREFIX . "fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 10, f19t = 23, f19 = 10, f32t = 23, f32 = 10 WHERE vref = $wid");
        	} elseif($size == 2) {
        		mysql_query("UPDATE " . TB_PREFIX . "units SET u41 = " . (rand(2000, 4000) * $speed) . ", u42 = " . (rand(3000, 4000) * $speed) . ", u43 = " . (rand(4600, 5600) * $speed) . ", u44 = " . (rand(50, 150) * $speed) . ", u45 = " . (rand(2400, 3800) * $speed) . ", u46 = " . (rand(3000, 4000) * $speed) . ", u47 = " . (rand(1000, 1800) * $speed) . ", u48 = " . (rand(200, 600) * $speed) . " , u49 = " . (rand(2, 10) * $speed) . ", u50 = " . (rand(2, 10) * $speed) . " WHERE vref = " . $wid . "");
        		mysql_query("UPDATE " . TB_PREFIX . "fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 20, f19t = 23, f19 = 10, f32t = 23, f32 = 10 WHERE vref = $wid");
        	} elseif($size == 3) {
        		mysql_query("UPDATE " . TB_PREFIX . "units SET u41 = " . (rand(4000, 8000) * $speed) . ", u42 = " . (rand(6000, 8000) * $speed) . ", u43 = " . (rand(9200, 11200) * $speed) . ", u44 = " . (rand(100, 300) * $speed) . ", u45 = " . (rand(4800, 7600) * $speed) . ", u46 = " . (rand(6000, 8000) * $speed) . ", u47 = " . (rand(2000, 3600) * $speed) . ", u48 = " . (rand(400, 1200) * $speed) . " , u49 = " . (rand(4, 20) * $speed) . ", u50 = " . (rand(4, 20) * $speed) . " WHERE vref = " . $wid . "");
        		mysql_query("UPDATE " . TB_PREFIX . "fdata SET f22t = 27, f22 = 10, f28t = 25, f28 = 20, f19t = 23, f19 = 10, f32t = 23, f32 = 10 WHERE vref = $wid");
        	}
        }

/**
 * THE ARCHITECTS
 */

        $desc = 'El plano de construcción permite levantar una Maravilla del Mundo en una aldea natar. Un plano basta para mejorarla hasta el nivel 50; a partir de ese nivel, tu alianza necesita un segundo plano.';


        $vname = 'Plano de la Maravilla del Mundo';
        $effect = '';
        for($i > 1; $i < 10; $i++) {
		Artefact($uid, 1, 1, 'Plano de construcción de la Maravilla del Mundo', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type1.gif');
        }


/**
 * MILITARY HASTE
 */

        $desc = "Este artefacto aumenta la resistencia de tu aldea frente a catapultas y arietes.";

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Secreto del arquitecto';
        $effect = '4x';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 2, 1, 'Pequeño secreto del arquitecto', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type2.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Secreto del arquitecto';
        $effect = '3x';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 2, 2, 'Gran secreto del arquitecto', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type2.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Secreto del arquitecto';
        $effect = '5x';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 2, 3, 'Secreto único del arquitecto', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type2.gif');
        }


/**
 * THE DIET
 */


        $desc = "Este artefacto aumenta la velocidad de movimiento de tus tropas.";

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Botas de los titanes';
        $effect = '2x';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 4, 1, 'Pequeñas botas de los titanes', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type4.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Botas de los titanes';
        $effect = '1.5x';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 4, 2, 'Grandes botas de los titanes', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type4.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Botas de los titanes';
        $effect = '3x';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 4, 3, 'Botas únicas de los titanes', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type4.gif');
        }


/**
 * ACADEMIC ADVANCEMENT
 */


        $desc = "Este artefacto fortalece a tus exploradores, incluidos los que defienden la aldea. Además, permite reconocer en el punto de reunión el tipo de tropas atacantes, aunque no su cantidad.";

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Ojos del águila';
        $effect = '5x';
        for($i > 1; $i < 5; $i++) {
		Artefact($uid, 5, 1, 'Pequeños ojos del águila', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type5.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Ojos del águila';
        $effect = '3x';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 5, 2, 'Grandes ojos del águila', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type5.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Ojos del águila';
        $effect = '10x';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 5, 3, 'Ojos únicos del águila', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type5.gif');
        }


/**
 * STORAGE MASTER PLAN
 */


        $desc = "Este artefacto reduce el consumo de cereal de las tropas propias y de refuerzo estacionadas en la aldea.";

        unset($i);
        unset($vname);
        unset($effect);;
        $vname = 'Control de dieta';
        $effect = '1/2';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 6, 1, 'Pequeño control de dieta', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type6.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Control de dieta';
        $effect = '3/4';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 6, 2, 'Gran control de dieta', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type6.gif');
        }


/**
 * ARTEFACT OF THE FOOL
 */


        $desc = 'Este artefacto acelera el entrenamiento de tropas en el cuartel, establo y taller.
También afecta al cuartel grande y al establo grande.';

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Talento del entrenador';
		$effect = '1/2';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 8, 1, 'Pequeño talento del entrenador', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type8.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Talento del entrenador';
		$effect = '3/4';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 8, 2, 'Gran talento del entrenador', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type8.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Talento del entrenador';
		$effect = '1/2';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 8, 3, 'Talento único del entrenador', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type8.gif');
        }


/**
 * RIVAL'S CONFUSION
 */


        $desc = 'Este artefacto aumenta la capacidad de tus escondites.

Las catapultas enemigas solo podrán elegir objetivos al azar.

La tesorería y la Maravilla del Mundo siguen pudiendo seleccionarse; con el artefacto único tampoco se puede seleccionar la tesorería.';

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Confusión del rival';
        $effect = '200x';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 7, 1, 'Pequeña confusión del rival', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type7.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Confusión del rival';
        $effect = '100x';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 7, 2, 'Gran confusión del rival', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type7.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Confusión del rival';
        $effect = '500x';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 7, 3, 'Confusión única del rival', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type7.gif');
        }


/**
 * WAREHOUSE CONFUSION
 */


        $desc = 'Este plano permite construir el gran almacén y el gran granero.
También es necesario para mejorar ambos edificios.';

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Plano de almacenamiento';
        $effect = '';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 9, 1, 'Pequeño plano de almacenamiento', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type9.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Plano de almacenamiento';
        $effect = '';
        for($i > 1; $i < 4; $i++) {
		Artefact($uid, 9, 2, 'Gran plano de almacenamiento', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type9.gif');
        }


/**
 * HAWK'S EYESIGHT
 */


        $desc = "Este artefacto reduce la eficacia defensiva de las murallas de la aldea.";

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Artefacto del necio';
        $effect = '2x';
        for($i > 1; $i < 6; $i++) {
		Artefact($uid, 3, 1, 'Pequeño artefacto del necio', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type3.gif');
        }

        unset($i);
        unset($vname);
        unset($effect);
        $vname = 'Artefacto del necio';
        $effect = '5x';
        for($i > 1; $i < 1; $i++) {
		Artefact($uid, 3, 3, 'Artefacto único del necio', '' . $vname . '', '' . $desc . '', '' . $effect . '', 'type3.gif');
        }
header("Location: dorf1.php");
