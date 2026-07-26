<?php

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

if(!function_exists('heroVillageResourceBonus')){
	function heroVillageResourceBonus($hero, $villageId, $speed){
		if(!is_array($hero) || (int)$hero['dead']!==0 || (int)$hero['wref']!==(int)$villageId){
			return array('wood'=>0, 'clay'=>0, 'iron'=>0, 'crop'=>0);
		}

		return heroResourceRates($hero, $speed);
	}
}
