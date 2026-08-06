<?php

if(!function_exists('getHeroHorseSpeedBonus')){
	function getHeroHorseSpeedBonus($type){
		$bonuses = array(103 => 7, 104 => 10, 105 => 13);
		return isset($bonuses[(int)$type]) ? $bonuses[(int)$type] : 0;
	}
}

if(!function_exists('getHeroShoesBonuses')){
	// El slot de pies (btype 5) mezcla tres familias de objetos que no comparten
	// efecto: botas de regeneración (94-96) suman salud por día, botas de mercenario
	// (97-99) aceleran al ejército en los trayectos largos y espuelas (100-102)
	// suman casillas por hora al héroe. Solo se puede llevar uno a la vez.
	function getHeroShoesBonuses($type){
		$autoRegen = array(94 => 10, 95 => 15, 96 => 20);
		$armySpeed = array(97 => 25, 98 => 50, 99 => 75);
		$heroSpeed = array(100 => 3, 101 => 4, 102 => 5);
		$type = (int)$type;

		return array(
			'autoregen' => isset($autoRegen[$type]) ? $autoRegen[$type] : 0,
			'armyspeed' => isset($armySpeed[$type]) ? $armySpeed[$type] : 0,
			'speed' => isset($heroSpeed[$type]) ? $heroSpeed[$type] : 0
		);
	}
}

if(!function_exists('getHeroSpurSpeedBonus')){
	function getHeroSpurSpeedBonus($type){
		$bonuses = getHeroShoesBonuses($type);

		return $bonuses['speed'];
	}
}

if(!function_exists('getHeroBootsArmySpeedBonus')){
	function getHeroBootsArmySpeedBonus($type){
		$bonuses = getHeroShoesBonuses($type);

		return $bonuses['armyspeed'];
	}
}

if(!function_exists('heroBootsDistanceThreshold')){
	// Las botas prometen su bono "en distancias > 20 casillas". Es un umbral propio
	// del objeto: no se toca con TS_THRESHOLD, que ajusta la Plaza de Torneos.
	function heroBootsDistanceThreshold(){
		return 20;
	}
}

if(!function_exists('heroEquipmentSlot')){
	// Columna de `heroinventory` en la que vive cada tipo de objeto. Los tres tipos de
	// bolsa (vendas chicas, vendas, jaulas) comparten `bag`: el héroe lleva uno solo.
	function heroEquipmentSlot($btype){
		$slots = array(
			1=>'helmet', 2=>'body', 3=>'leftHand', 4=>'rightHand', 5=>'shoes', 6=>'horse',
			7=>'bag', 8=>'bag', 9=>'bag'
		);
		$btype = (int)$btype;

		return isset($slots[$btype]) ? $slots[$btype] : false;
	}
}

if(!function_exists('heroEquippedItem')){
	// Qué objeto lleva puesto el héroe en un slot. La fuente de verdad es
	// `heroinventory`, no el flag `proc` de la fila del objeto: los dos se escriben
	// juntos al equipar pero sin transacción, y una fila con `proc = 1` que quedara
	// huérfana daría un bono fantasma imposible de ver, porque la grilla del
	// inventario lista `proc = 0`. Resolver por el slot y validar dueño y tipo hace
	// que solo lo realmente equipado pueda tener efecto.
	function heroEquippedItem($database, $uid, $btype){
		$uid = (int)$uid;
		$btype = (int)$btype;
		$slot = heroEquipmentSlot($btype);
		if($uid<=0 || $slot===false){
			return false;
		}
		if(!method_exists($database, 'getHeroInventory') || !method_exists($database, 'getItemData')){
			return false;
		}
		$inventory = $database->getHeroInventory($uid);
		if(!is_array($inventory) || empty($inventory[$slot])){
			return false;
		}
		$item = $database->getItemData((int)$inventory[$slot]);

		return (is_array($item) && (int)$item['uid']===$uid && (int)$item['btype']===$btype) ? $item : false;
	}
}

if(!function_exists('heroEquippedBootsSpeedBonus')){
	// Bono de las botas de mercenario que lleva puestas el héroe de $uid. Devuelve 0
	// si el slot de pies está vacío o si lo que hay ahí es otra cosa (regeneración,
	// espuelas), que no aceleran al ejército.
	function heroEquippedBootsSpeedBonus($database, $uid){
		$shoes = heroEquippedItem($database, $uid, 5);

		return is_array($shoes) ? getHeroBootsArmySpeedBonus((int)$shoes['type']) : 0;
	}
}

if(!function_exists('heroBootsTravelDistance')){
	// Las botas solo aceleran el tramo que excede el umbral, así que el bono se
	// aplica acortando la distancia efectiva en vez de subir la velocidad: el
	// resultado equivale a T/v + (D-T)/(v*(1+bono/100)), o sea el primer tramo tarda
	// lo mismo que sin botas. (La Plaza de Torneos usa una aproximación distinta y
	// más vieja en procDistanceTime; no se la toca desde acá.)
	function heroBootsTravelDistance($distance, $bonus){
		$distance = max(0, (float)$distance);
		$bonus = max(0, (float)$bonus);
		$threshold = heroBootsDistanceThreshold();
		if($bonus<=0 || $distance<=$threshold){
			return $distance;
		}

		return $threshold+($distance-$threshold)/(1+$bonus/100);
	}
}

if(!function_exists('heroExperienceWithHelmet')){
	function heroExperienceWithHelmet($database, $uid, $experience){
		$experience = max(0, (float)$experience);
		$helmet = heroEquippedItem($database, $uid, 1);
		if(is_array($helmet) && isset($helmet['type'])){
			$bonuses = array(1 => 15, 2 => 20, 3 => 25);
			$type = (int)$helmet['type'];
			if(isset($bonuses[$type])){
				$experience *= 1 + $bonuses[$type] / 100;
			}
		}

		return (int)floor($experience);
	}
}

if(!function_exists('heroAttributeLimit')){
	function heroAttributeLimit(){
		return 100;
	}
}
if(!function_exists('heroFightingStrength')){
	function heroFightingStrength($hero, $tribe){
		if(!is_array($hero)){
			return 0;
		}

		$pointsPerAttribute = (int)$tribe === 1 ? 100 : 80;
		$powerPoints = max(0, min(heroAttributeLimit(), (int)$hero['power']));
		$itemPower = max(0, (int)$hero['itempower']);

		return 100+$pointsPerAttribute*$powerPoints+$itemPower;
	}
}

if(!function_exists('heroArmyBonusFactor')){
	function heroArmyBonusFactor($points){
		return 1+max(0, min(heroAttributeLimit(), (float)$points))/500;
	}
}

if(!function_exists('heroArmyBonusPercent')){
	function heroArmyBonusPercent($points){
		return max(0, min(heroAttributeLimit(), (float)$points))/5;
	}
}

if(!function_exists('heroLevelForExperience')){
	function heroLevelForExperience($experience, $currentLevel, $levels){
		if(!is_array($levels) || empty($levels)){
			return max(0, (int)$currentLevel);
		}

		$maximumLevel = count($levels)-1;
		$level = max(0, min($maximumLevel, (int)$currentLevel));
		$experience = max(0, (int)$experience);
		while($level<$maximumLevel && isset($levels[$level+1]) && $experience>=(int)$levels[$level+1]){
			$level++;
		}

		return $level;
	}
}

if(!function_exists('heroResourceRates')){
	function heroResourceRates($hero, $speed){
		$rates = array('wood'=>0, 'clay'=>0, 'iron'=>0, 'crop'=>0);
		if(!is_array($hero)){
			return $rates;
		}

		$points = max(0, min(heroAttributeLimit(), (int)$hero['product']));
		$speed = max(0, (float)$speed);
		if($points===0 || $speed==0){
			return $rates;
		}

		if(!empty($hero['r0'])){
			$amount = 3*$speed*$points;
			foreach($rates as $resource=>$unused){
				$rates[$resource] = $amount;
			}
			return $rates;
		}

		$resourceFields = array('r1'=>'wood', 'r2'=>'clay', 'r3'=>'iron', 'r4'=>'crop');
		foreach($resourceFields as $field=>$resource){
			if(!empty($hero[$field])){
				$rates[$resource] = 10*$speed*$points;
				break;
			}
		}

		return $rates;
	}
}

if(!function_exists('heroHomeVillage')){
	// La aldea natal es la que cobra el bono de recursos del héroe. Es un valor
	// propio: solo cambia si el jugador lo pide al mandar al héroe a otra aldea
	// suya, así que no se mueve sola cuando el héroe sale de aventura o refuerza.
	// `wref` es el respaldo para los héroes creados antes de que existiera `home`.
	function heroHomeVillage($hero){
		if(!is_array($hero)){
			return 0;
		}
		$home = isset($hero['home']) ? (int)$hero['home'] : 0;

		return $home > 0 ? $home : (int)$hero['wref'];
	}
}

if(!function_exists('heroVillageResourceBonus')){
	function heroVillageResourceBonus($hero, $villageId, $speed){
		if(!is_array($hero) || (int)$hero['dead']!==0 || heroHomeVillage($hero)!==(int)$villageId){
			return array('wood'=>0, 'clay'=>0, 'iron'=>0, 'crop'=>0);
		}

		return heroResourceRates($hero, $speed);
	}
}
