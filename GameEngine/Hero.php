<?php

if(!function_exists('getHeroHorseSpeedBonus')){
	function getHeroHorseSpeedBonus($type){
		$bonuses = array(103 => 7, 104 => 10, 105 => 13);
		return isset($bonuses[(int)$type]) ? $bonuses[(int)$type] : 0;
	}
}

if(!function_exists('getHeroArmorBonuses')){
	// El torso (btype 2) mezcla cuatro familias: armaduras de regeneración (82-84), de
	// escamas (85-87), petos (88-90) y articuladas (91-93). `itempower` y `autoregen` se
	// guardan en el héroe al equipar; `vitality` no, porque se aplica sobre el daño de
	// cada batalla y se lee del objeto equipado en ese momento.
	function getHeroArmorBonuses($type){
		$itemPower = array(88 => 500, 89 => 1000, 90 => 1500, 91 => 250, 92 => 500, 93 => 750);
		$autoRegen = array(82 => 20, 83 => 30, 84 => 40, 85 => 10, 86 => 15, 87 => 20);
		$vitality = array(85 => 4, 86 => 6, 87 => 8, 91 => 3, 92 => 4, 93 => 5);
		$type = (int)$type;

		return array(
			'itempower' => isset($itemPower[$type]) ? $itemPower[$type] : 0,
			'autoregen' => isset($autoRegen[$type]) ? $autoRegen[$type] : 0,
			'vitality' => isset($vitality[$type]) ? $vitality[$type] : 0
		);
	}
}

if(!function_exists('heroArmorVitalityReduction')){
	// Puntos de salud que la armadura puesta le descuenta al daño de una batalla.
	function heroArmorVitalityReduction($database, $uid){
		$armor = heroEquippedItem($database, $uid, 2);
		if(!is_array($armor)){
			return 0;
		}
		$bonuses = getHeroArmorBonuses((int)$armor['type']);

		return $bonuses['vitality'];
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

if(!function_exists('getHeroHelmetBonuses')){
	// El slot de cabeza (btype 1) mezcla cinco familias que no comparten efecto: cascos
	// de experiencia (1-3), de regeneración (4-6), de cultura (7-9), de establo (10-12)
	// y de cuartel (13-15). Solo se puede llevar uno a la vez.
	function getHeroHelmetBonuses($type){
		$experience = array(1 => 15, 2 => 20, 3 => 25);
		$autoRegen = array(4 => 10, 5 => 15, 6 => 20);
		$culture = array(7 => 100, 8 => 400, 9 => 800);
		$stable = array(10 => 10, 11 => 15, 12 => 20);
		$barracks = array(13 => 10, 14 => 15, 15 => 20);
		$type = (int)$type;

		return array(
			'experience' => isset($experience[$type]) ? $experience[$type] : 0,
			'autoregen' => isset($autoRegen[$type]) ? $autoRegen[$type] : 0,
			'culture' => isset($culture[$type]) ? $culture[$type] : 0,
			'stable' => isset($stable[$type]) ? $stable[$type] : 0,
			'barracks' => isset($barracks[$type]) ? $barracks[$type] : 0
		);
	}
}

if(!function_exists('heroTrainingHelmetSlot')){
	// Qué familia de cascos acelera cada edificio. El bono es del edificio, no del tipo
	// de tropa: el Gran Cuartel y el Gran Establo lo cobran igual que los normales.
	function heroTrainingHelmetSlot($buildingType){
		$slots = array(19 => 'barracks', 29 => 'barracks', 20 => 'stable', 30 => 'stable');
		$buildingType = (int)$buildingType;

		return isset($slots[$buildingType]) ? $slots[$buildingType] : false;
	}
}

if(!function_exists('heroTrainingTimeFactor')){
	// Factor por el que se multiplica el tiempo de entrenamiento de una aldea según el
	// casco puesto. Devuelve 1 cuando no hay bono, así que se puede multiplicar siempre.
	//
	// El bono se cobra en la aldea natal, no en la que tenga al héroe parado. `wref` se
	// mueve solo cada vez que sale de aventura o refuerza, y eso cambiaría los tiempos
	// de una cola ya empezada; `home` solo cambia si el jugador lo pide, igual que el
	// bono de recursos. (En Travian oficial es donde está el héroe.)
	function heroTrainingTimeFactor($database, $uid, $villageId, $buildingType){
		$uid = (int)$uid;
		$villageId = (int)$villageId;
		$slot = heroTrainingHelmetSlot($buildingType);
		if($slot===false || $uid<=0 || $villageId<=0 || !method_exists($database, 'getHeroData')){
			return 1;
		}
		$hero = $database->getHeroData($uid);
		if(!is_array($hero) || (int)$hero['dead']!==0 || heroHomeVillage($hero)!==$villageId){
			return 1;
		}
		$helmet = heroEquippedItem($database, $uid, 1);
		if(!is_array($helmet)){
			return 1;
		}
		$bonuses = getHeroHelmetBonuses((int)$helmet['type']);

		return (100-$bonuses[$slot])/100;
	}
}

if(!function_exists('heroHelmetCulturePoints')){
	// Puntos de cultura por día que aporta el casco puesto. A diferencia de la
	// regeneración, no se guarda en ninguna columna: se lee del objeto equipado cada
	// vez que se acredita el día, igual que el bono de experiencia.
	//
	// Un héroe muerto no aporta nada, que es como el resto del motor trata su equipo:
	// updateHero() tampoco lo regenera y heroVillageResourceBonus() le corta los
	// recursos.
	function heroHelmetCulturePoints($database, $uid){
		$uid = (int)$uid;
		if($uid<=0 || !method_exists($database, 'getHeroData')){
			return 0;
		}
		$hero = $database->getHeroData($uid);
		if(!is_array($hero) || (int)$hero['dead']!==0){
			return 0;
		}
		$helmet = heroEquippedItem($database, $uid, 1);
		if(!is_array($helmet)){
			return 0;
		}
		$bonuses = getHeroHelmetBonuses((int)$helmet['type']);

		return $bonuses['culture'];
	}
}

if(!function_exists('accountCulturePointsPerDay')){
	// Producción diaria de PC de toda la cuenta: la suma de las aldeas más el casco de
	// cultura. Vive acá porque el casco es lo que la hace distinta de un getVSumField,
	// y tiene que ser una sola definición: la usan el crédito diario, el panel de
	// cultura y las obras de arte, que conceden justamente un día de producción.
	function accountCulturePointsPerDay($database, $uid){
		$uid = (int)$uid;
		$villages = (int)$database->getVSumField($uid, 'cp');

		return $villages+heroHelmetCulturePoints($database, $uid);
	}
}

if(!function_exists('heroExperienceWithHelmet')){
	// La cuenta va en enteros a propósito: con `$experience * (1 + 15/100)` el 1,15 de
	// coma flotante vale 1,1499… y un bono exacto se cae un punto al truncar (100 de
	// experiencia daban 114 en vez de 115, que es justo el caso de los rollos, que
	// otorgan múltiplos de 10).
	function heroExperienceWithHelmet($database, $uid, $experience){
		$experience = max(0, (int)$experience);
		$helmet = heroEquippedItem($database, $uid, 1);
		$bonuses = is_array($helmet) ? getHeroHelmetBonuses((int)$helmet['type']) : array('experience' => 0);

		return intdiv($experience*(100+$bonuses['experience']), 100);
	}
}

if(!function_exists('heroAdventureTierThresholds')){
	// El mundo abre soltando solo el primer nivel de cada familia de objetos, suma el
	// segundo a la semana y el tercero a las dos semanas.
	function heroAdventureTierThresholds(){
		return array('second' => 604800, 'third' => 1209600);
	}
}

if(!function_exists('heroAdventureItemTypes')){
	// Tipos que puede soltar una aventura para cada categoría de equipo, según el
	// tiempo transcurrido desde COMMENCE.
	//
	// Las ramas van de más vieja a más nueva. Estaban al revés (primero la de 7 días),
	// y como cualquier momento posterior a los 14 días también supera los 7, la rama
	// del tercer nivel nunca se alcanzaba: el Casco de la Sabiduría, la Curación, el
	// Cónsul, la Caballería Pesada y el Arconte no salían nunca, y lo mismo pasaba con
	// el tercer nivel de armas, escudos, calzado y caballos.
	function heroAdventureItemTypes($btype, $tribe, $elapsed){
		$btype = (int)$btype;
		$tribe = (int)$tribe;
		$elapsed = (int)$elapsed;
		$thresholds = heroAdventureTierThresholds();
		$tier = 1;
		if($elapsed >= $thresholds['third']){
			$tier = 3;
		}elseif($elapsed >= $thresholds['second']){
			$tier = 2;
		}

		switch($btype){
			case 1:
				$tiers = array(
					1 => array(1, 4, 7, 10, 13),
					2 => array(1, 2, 4, 5, 7, 8, 10, 11, 13, 14),
					3 => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15)
				);
				break;
			case 2:
				// La tabla del torso estaba comentada de arrastre, y como las aventuras son
				// la única fuente de objetos (las subastas son entre jugadores), ninguna de
				// las doce armaduras podía existir en el mundo.
				$tiers = array(
					1 => array(82, 85, 88, 91),
					2 => array(82, 83, 85, 86, 88, 89, 91, 92),
					3 => array(82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93)
				);
				break;
			case 3:
				// No hay objetos 70-72: la numeración de la mano izquierda tiene ese hueco.
				$tiers = array(
					1 => array(61, 64, 67, 73, 79),
					2 => array(61, 62, 64, 65, 67, 68, 73, 74, 79, 80),
					3 => array(61, 62, 63, 64, 65, 66, 67, 68, 69, 73, 74, 75, 76, 77, 78, 79, 80, 81)
				);
				break;
			case 4:
				$weapons = array(
					1 => array(
						1 => array(16, 19, 22, 25, 28),
						2 => array(16, 17, 19, 20, 22, 23, 25, 26, 28, 29),
						3 => array(16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30)
					),
					2 => array(
						1 => array(46, 49, 52, 55, 58),
						2 => array(46, 47, 49, 50, 52, 53, 55, 56, 58, 59),
						3 => array(46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60)
					),
					3 => array(
						1 => array(31, 34, 37, 40, 43),
						2 => array(31, 32, 34, 35, 37, 38, 40, 41, 43, 44),
						3 => array(31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45)
					)
				);
				// Las armas son propias de cada tribu y solo hay tablas para las tres
				// jugables: una tribu fuera de rango no suelta arma.
				return isset($weapons[$tribe][$tier]) ? $weapons[$tribe][$tier] : array();
			case 5:
				$tiers = array(
					1 => array(94, 97, 100),
					2 => array(94, 95, 97, 98, 100, 101),
					3 => array(94, 95, 96, 97, 98, 99, 100, 101, 102)
				);
				break;
			case 6:
				$tiers = array(
					1 => array(103),
					2 => array(103, 104),
					3 => array(103, 104, 105)
				);
				break;
			default:
				// btype 0 es el "no se encontró nada valioso" y 7-15 van por consumibles.
				return array();
		}

		return $tiers[$tier];
	}
}

if(!function_exists('heroAdventureConsumableType')){
	// Los consumibles (btype 7-15) no tienen niveles: cada categoría es un único objeto.
	function heroAdventureConsumableType($btype){
		$types = array(
			7 => 112, 8 => 113, 9 => 114, 10 => 107, 11 => 106,
			12 => 108, 13 => 110, 14 => 109, 15 => 111
		);
		$btype = (int)$btype;

		return isset($types[$btype]) ? $types[$btype] : 0;
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

if(!function_exists('reassignHeroHomeVillage')){
	// Devuelve la aldea natal del héroe, mudándola si la que tenía dejó de ser del
	// jugador.
	//
	// Perder la aldea natal (que te la conquisten con jefes o que te la arrasen con
	// catapultas) dejaba `hero.home` apuntando a una aldea ajena o directamente
	// inexistente. Como los bonos se cobran comparando contra ese número, el héroe se
	// quedaba sin bono de recursos y sin bono de entrenamiento hasta que el jugador lo
	// mandara de apoyo a otra aldea propia con el check de aldea natal tildado, sin
	// ninguna pista de que hiciera falta.
	//
	// getVillagesID() ordena por capital primero, así que la natal cae en la capital y,
	// si no hay, en la primera aldea que quede.
	function reassignHeroHomeVillage($database, $uid){
		$uid = (int)$uid;
		if($uid<=0 || !method_exists($database, 'getHeroData') || !method_exists($database, 'getVillagesID')){
			return 0;
		}
		$hero = $database->getHeroData($uid);
		if(!is_array($hero)){
			return 0;
		}
		$villages = $database->getVillagesID($uid);
		if(!is_array($villages) || empty($villages)){
			return 0;
		}
		$villages = array_map('intval', $villages);
		$home = heroHomeVillage($hero);
		if(in_array($home, $villages, true)){
			return $home;
		}

		$newHome = $villages[0];
		$database->modifyHero2('home', $newHome, $uid, 0);

		return $newHome;
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
