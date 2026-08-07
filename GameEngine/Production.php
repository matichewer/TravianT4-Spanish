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
			switch((int)$oasis['type']) {
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
