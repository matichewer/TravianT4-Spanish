<?php
/**
 * Fórmula única de producción de recursos de una aldea.
 *
 * Antes esta cuenta estaba copiada en cuatro lugares (Village::get*Prod, las
 * bounty*Prod de Automation que ponen al día una aldea antes de saquearla,
 * mysqli_DB::getCropProdstarv para la hambruna y Templates/dorf3/3.tpl) y las
 * cuatro daban números distintos: la panadería se aplicaba en cascada sobre el
 * molino en una, el oasis se sumaba sobre la base en otra, y dos ignoraban el
 * bono de producción comprado con oro. Como cada una escribe recursos reales en
 * la aldea, la diferencia se traducía en recursos que aparecían o desaparecían
 * según quién actualizara la aldea. Todas usan ahora estas funciones (la de la
 * hambruna se borró: usa la producción neta de Automation, que ya sale de acá).
 *
 * Orden canónico (el que ya usaba Village.php, que es lo que ve el jugador):
 *   campos -> + % de los edificios de bonus sobre la producción de los campos
 *          -> + 25% por cada oasis del recurso, sobre lo anterior
 *          -> + 25% del bono de oro, sobre lo anterior
 *          -> * SPEED
 *
 * Con el cereal hay DOS números y no son intercambiables:
 *   - el balance, Village::getProd('crop'): bruta + héroe - población - tropas.
 *     Es lo que muestra dorf1 y lo que decide la hambruna.
 *   - el cereal libre, villageFreeCrop(): bruta sin oro ni héroe - población.
 *     Es lo único que decide si se puede construir, igual que en el T4 oficial.
 * Ver el comentario de villageFreeCrop() para por qué el oficial los separa.
 */

/**
 * Nivel de un edificio dentro de la aldea, acotado al rango real de su tabla.
 *
 * Se queda con el nivel más alto si por algún motivo hubiera más de uno del
 * mismo tipo (antes ganaba el que estuviera en el campo de número más alto, así
 * que un segundo aserradero de nivel 1 bajaba el bono del 25% al 5%), y recorta
 * los niveles imposibles al máximo de la tabla en lugar de dejar el bono en cero.
 */
function productionBuildingLevel($resarray, $type) {
	$dataarray = isset($GLOBALS['bid'.(int)$type]) ? $GLOBALS['bid'.(int)$type] : array();
	if(!is_array($dataarray) || empty($dataarray)) {
		return 0;
	}
	$maxLevel = max(array_keys($dataarray));
	$level = 0;
	for($field = 1; $field <= 40; $field++) {
		if(!isset($resarray['f'.$field.'t']) || (int)$resarray['f'.$field.'t'] !== (int)$type) {
			continue;
		}
		$level = max($level,(int)$resarray['f'.$field]);
	}
	return max(0,min($level,$maxLevel));
}

/**
 * Producción por hora de los campos de un recurso (sin bonos), a velocidad 1.
 */
function productionFieldOutput($resarray, $type) {
	$dataarray = isset($GLOBALS['bid'.(int)$type]) ? $GLOBALS['bid'.(int)$type] : array();
	if(!is_array($dataarray) || empty($dataarray)) {
		return 0;
	}
	$maxLevel = max(array_keys($dataarray));
	$output = 0;
	for($field = 1; $field <= 40; $field++) {
		if(!isset($resarray['f'.$field.'t']) || (int)$resarray['f'.$field.'t'] !== (int)$type) {
			continue;
		}
		$level = max(0,min((int)$resarray['f'.$field],$maxLevel));
		$output += isset($dataarray[$level]['prod']) ? $dataarray[$level]['prod'] : 0;
	}
	return $output;
}

/**
 * Producción bruta por hora de los cuatro recursos, con el desglose que muestra
 * el tooltip de dorf1.
 *
 * $ocounter    array de 4 enteros: oasis anexados de madera, barro, hierro y cereal.
 * $bonusFlags  array de 4 booleanos: bono de producción de oro por recurso (b1..b4).
 */
function villageGrossProduction($resarray, $ocounter, $bonusFlags, $speed) {
	$resarray = is_array($resarray) ? $resarray : array();
	$ocounter = is_array($ocounter) ? array_values($ocounter) : array();
	$bonusFlags = is_array($bonusFlags) ? array_values($bonusFlags) : array();
	$speed = (float)$speed;

	$resources = array(
		'wood' => array('field'=>1,'building'=>5,'name'=>'Aserradero','index'=>0),
		'clay' => array('field'=>2,'building'=>6,'name'=>'Fábrica de ladrillos','index'=>1),
		'iron' => array('field'=>3,'building'=>7,'name'=>'Fundición de hierro','index'=>2),
		'crop' => array('field'=>4,'building'=>8,'name'=>'Molino','index'=>3)
	);

	$result = array('production'=>array(),'breakdown'=>array());
	foreach($resources as $resource => $info) {
		$fields = productionFieldOutput($resarray,$info['field']);
		$total = $fields;
		$details = array('fields'=>$fields);

		if($resource === 'crop') {
			$grainmill = productionBuildingLevel($resarray,8);
			$bakery = productionBuildingLevel($resarray,9);
			$grainmillPercent = $grainmill >= 1 ? $GLOBALS['bid8'][$grainmill]['attri'] : 0;
			$bakeryPercent = $bakery >= 1 ? $GLOBALS['bid9'][$bakery]['attri'] : 0;
			// Los dos bonos se calculan sobre la producción de los campos, nunca uno
			// sobre el otro: molino 5 + panadería 5 son +50%, no +56,25%.
			$grainmillBonus = $fields / 100 * $grainmillPercent;
			$bakeryBonus = $fields / 100 * $bakeryPercent;
			$buildingBonus = $grainmillBonus + $bakeryBonus;
			$details['grainmill_level'] = $grainmill;
			$details['grainmill_percent'] = $grainmillPercent;
			$details['grainmill_bonus'] = $grainmillBonus;
			$details['bakery_level'] = $bakery;
			$details['bakery_percent'] = $bakeryPercent;
			$details['bakery_bonus'] = $bakeryBonus;
			$details['building_bonus'] = $buildingBonus;
		}
		else {
			$level = productionBuildingLevel($resarray,$info['building']);
			$buildingPercent = $level >= 1 ? $GLOBALS['bid'.$info['building']][$level]['attri'] : 0;
			$buildingBonus = $fields / 100 * $buildingPercent;
			$details['building'] = $info['name'];
			$details['building_level'] = $level;
			$details['building_percent'] = $buildingPercent;
			$details['building_bonus'] = $buildingBonus;
		}
		$total += $details['building_bonus'];

		$oasis = isset($ocounter[$info['index']]) ? (int)$ocounter[$info['index']] : 0;
		$oasisBonus = $total * 0.25 * $oasis;
		$total += $oasisBonus;
		$details['oasis_percent'] = 25 * $oasis;
		$details['oasis_bonus'] = $oasisBonus;

		$hasGoldBonus = !empty($bonusFlags[$info['index']]);
		$plusBonus = $hasGoldBonus ? $total * 0.25 : 0;
		$total += $plusBonus;
		$details['plus_percent'] = $hasGoldBonus ? 25 : 0;
		$details['plus_bonus'] = $plusBonus;

		$total *= $speed;
		$details['speed'] = $speed;
		$details['gross'] = round($total);

		$result['production'][$resource] = round($total);
		$result['breakdown'][$resource] = $details;
	}
	return $result;
}

/**
 * Producción de cereal "base" tal como la define el T4 oficial: la de las
 * plantaciones con los bonos de molino, panadería y oasis, SIN el bono de oro y
 * SIN la producción del héroe.
 *
 * No es la producción que ve el jugador en la barra de recursos —esa lleva el oro
 * y el héroe, y además le resta población y tropas—. Es el número con el que el
 * oficial decide si te deja construir, y deja los dos bonos afuera a propósito:
 * son temporales, así que dejarlos entrar permitiría construir edificios que la
 * aldea no puede sostener cuando el bono se vence.
 */
function villageBaseCropProduction($resarray, $ocounter, $speed) {
	$gross = villageGrossProduction($resarray,$ocounter,array(false,false,false,false),$speed);
	return (float)$gross['production']['crop'];
}

/**
 * "Cereal libre" del T4 oficial: producción base menos los HABITANTES de la aldea.
 *
 * Las tropas no entran. Esto sorprende, y es la regla del juego original: el
 * candado existe para que no te construyas un déficit *sólo con edificios*, no
 * para limitar el ejército. Un martillo con -5.000 de cereal sigue pudiendo
 * construir; lo que le pasa es que se le mueren las tropas de hambre, que es un
 * castigo aparte (Automation::starvation).
 *
 * El balance real —el que decide la hambruna y el que muestra dorf1— es otra
 * cuenta distinta: Village::getProd('crop'), que sí resta tropas y sí suma héroe
 * y oro. Los dos números conviven a propósito; no unificarlos.
 */
function villageFreeCrop($resarray, $ocounter, $population, $speed) {
	return villageBaseCropProduction($resarray,$ocounter,$speed) - max(0,(int)$population);
}

/**
 * Umbral del escape antibloqueo del oficial: con la producción base de cereal por
 * encima de este número, el edificio principal, el almacén y el granero se pueden
 * subir hasta nivel 10 aunque el cereal libre esté en rojo.
 *
 * El oficial dice 276/h y sus mundos corren a velocidad 1, así que acá se escala
 * por SPEED: pedir 276 pelados en un mundo x3 haría que el escape se abriera con
 * plantaciones a la mitad de nivel que en el original.
 */
function freeCropUnlockThreshold($speed) {
	return 276 * (float)$speed;
}

/**
 * Bonos de producción de oro (b1..b4) de un jugador, en el orden madera, barro,
 * hierro y cereal.
 */
function villageGoldBonusFlags($database, $uid) {
	$flags = array(false,false,false,false);
	$uid = (int)$uid;
	if($uid <= 0 || !is_object($database)) {
		return $flags;
	}
	$now = time();
	foreach(array(1,2,3,4) as $slot) {
		$until = $database->getUserField($uid,'b'.$slot,0);
		$flags[$slot - 1] = ((int)$until > $now);
	}
	return $flags;
}

/**
 * Oasis anexados por una aldea, contados por recurso igual que Village::sortOasis.
 */
function villageOasisCounter($oasisowned) {
	$wood = $clay = $iron = $crop = 0;
	if(is_array($oasisowned)) {
		foreach($oasisowned as $oasis) {
			switch(isset($oasis['type']) ? (int)$oasis['type'] : 0) {
				case 1: $wood += 1; break;
				case 2: $wood += 2; break;
				case 3: $wood += 1; $crop += 1; break;
				case 4: $clay += 1; break;
				case 5: $clay += 2; break;
				case 6: $clay += 1; $crop += 1; break;
				case 7: $iron += 1; break;
				case 8: $iron += 2; break;
				case 9: $iron += 1; $crop += 1; break;
				case 10:
				case 11: $crop += 1; break;
				case 12: $crop += 2; break;
			}
		}
	}
	return array($wood,$clay,$iron,$crop);
}

/**
 * Qué recursos mejora un oasis y en cuánto, DERIVADO de villageOasisCounter(): cada
 * unidad del reparto vale 25%. Se calcula así, y no con una tabla propia, para que la
 * etiqueta que lee el jugador no pueda desfasarse del bono que la aldea cobra de
 * verdad — el mapa, la Mansión del Héroe y el perfil tenían cada uno su copia del
 * switch de los 12 tipos, y ninguna estaba obligada a coincidir con la producción.
 *
 * Devuelve array de array('res' => 1..4, 'percent' => 25|50), en orden madera, barro,
 * hierro, cereal.
 */
function oasisTypeBonuses($type) {
	list($wood, $clay, $iron, $crop) = villageOasisCounter(array(array('type' => (int)$type)));
	$bonuses = array();
	foreach(array(1 => $wood, 2 => $clay, 3 => $iron, 4 => $crop) as $res => $units) {
		if($units > 0) {
			$bonuses[] = array('res' => (int)$res, 'percent' => (int)$units * 25);
		}
	}
	return $bonuses;
}

/**
 * Nombre del recurso 1..4. Usa las constantes del idioma activo cuando ya están
 * cargadas (Lang se incluye después que esta capa, así que sólo existen en tiempo de
 * llamada, no de include).
 */
function oasisResourceLabel($res) {
	switch((int)$res) {
		case 1: return defined('WOOD') ? WOOD : "Madera";
		case 2: return defined('CLAY') ? CLAY : "Barro";
		case 3: return defined('IRON') ? IRON : "Hierro";
		case 4: return defined('CROP') ? CROP : "Cereal";
	}
	return "";
}

/**
 * Recurso principal de un oasis, para las listas que muestran un solo nombre.
 * Un oasis mixto (madera+cereal) se llama por el recurso que no es el cereal.
 */
function oasisResourceName($type) {
	$bonuses = oasisTypeBonuses($type);
	if(empty($bonuses)) {
		return "Desconocido";
	}
	return oasisResourceLabel($bonuses[0]['res']);
}

/**
 * Los iconos del oasis con el bono en el title, para las tablas que sólo tienen lugar
 * para el icono: el perfil mostraba "Madera" a secas y no dejaba distinguir un oasis
 * del 25% de uno del 50%, que es el dato por el que se elige cuál conquistar.
 */
function oasisBonusIcons($type) {
	$icons = array();
	foreach(oasisTypeBonuses($type) as $bonus) {
		$icons[] = "<img class='r".$bonus['res']."' src='img/x.gif' title='"
			.oasisResourceLabel($bonus['res'])." +".$bonus['percent']."%'>";
	}
	return implode(" ", $icons);
}

/**
 * El bono como tooltip del mapa: un renglón por recurso, icono y porcentaje.
 * Lo usan karte.php y el mapa grande, que antes tenían cada uno su copia del switch.
 */
function oasisBonusTooltip($type) {
	$lines = array();
	foreach(oasisTypeBonuses($type) as $bonus) {
		$lines[] = "<img class='r".$bonus['res']."' src='img/x.gif' /> "
			.oasisResourceLabel($bonus['res'])." ".$bonus['percent']."%";
	}
	return implode("<br>", $lines);
}

/**
 * El bono como filas de la tabla "Distribución de terreno" del detalle de casilla.
 * Un oasis de un solo recurso devuelve las celdas sueltas y el `<tr>` lo pone la tabla;
 * uno mixto trae sus dos filas completas. Se conserva tal cual estaba, incluidos los
 * saltos de línea, para no tocar el HTML que ya renderiza el navegador.
 */
function oasisBonusDistributionRows($type) {
	$cells = array();
	foreach(oasisTypeBonuses($type) as $bonus) {
		$label = oasisResourceLabel($bonus['res']);
		$cells[] = "<td class=\"ico\"><img class=\"r".$bonus['res']."\" src=\"img/x.gif\" title=\"".$label."\"></td>\n"
			."<td class=\"val\">".$bonus['percent']."%</td><td class=\"desc\">".$label."</td>";
	}
	if(empty($cells)) {
		return "";
	}
	if(count($cells) === 1) {
		return "\n".$cells[0];
	}
	foreach($cells as $index => $cell) {
		$cells[$index] = "<tr>".$cell."</tr>";
	}
	return "\n".implode("\n", $cells);
}

/**
 * El bono en texto, con icono y porcentaje visibles. Lo usa la Mansión del Héroe, que
 * sí tiene una columna entera para mostrarlo.
 */
function oasisResourceBonus($type) {
	$parts = array();
	foreach(oasisTypeBonuses($type) as $bonus) {
		$parts[] = "<span><img class='r".$bonus['res']."' src='img/x.gif' title='"
			.oasisResourceLabel($bonus['res'])."'> ".$bonus['percent']."%</span>";
	}
	return implode("", $parts);
}
