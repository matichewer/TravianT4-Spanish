<?php

require_once __DIR__.'/Hero.php';

class Technology {
	
	public $unarray = array(1=>U1,U2,U3,U4,U5,U6,U7,U8,U9,U10,U11,U12,U13,U14,U15,U16,U17,U18,U19,U20,U21,U22,U23,U24,U25,U26,U27,U28,U29,U30,U31,U32,U33,U34,U35,U36,U37,U38,U39,U40,U41,U42,U43,U44,U45,U46,U47,U48,U49,U50,U99,U0);
	
	public function grabAcademyRes() {
		global $village;
		$holder = array();
		foreach($village->researching as $research) {
			if(substr($research['tech'],0,1) == "t"){
				array_push($holder,$research);
			}
		}
		return $holder;
	}
	
	public function getABUpgrades($type='a') {
		global $village;
		$holder = array();
		foreach($village->researching as $research) {
			if(substr($research['tech'],0,1) == $type){
				array_push($holder,$research);
			}
		}
		return $holder;
	}
	
	public function isResearch($tech,$type) {
		global $village;
		if(count($village->researching) == 0) {
			return false;
		}
		else {
		switch($type) {
			case 1: $string = "t"; break;
			case 2: $string = "a"; break;
			case 3: $string = "b"; break;
		}
		foreach($village->researching as $research) {
			if($research['tech'] == $string.$tech) {
				return true;
			}
		}
		return false;
		}
	}
	
	public function procTech($post) {
		if(isset($post['ft'])) {
			switch($post['ft']) {
				case "t1":
				$this->procTrain($post);
				break;
				case "t3":
				$this->procTrain($post,true);
				break;
			}
		}
	}
	
	public function procTechno($get) {
		global $village;
		if(isset($get['a'])) {
			switch($village->resarray['f'.$get['id'].'t']) {
				case 22:
				$this->researchTech($get);
				break;
				case 12:
				$this->upgradeSword($get);
				break;
			}
		}
	}
	
	public function getTrainingList($type) {
		global $database,$village;
		$trainingarray = $database->getTraining($village->wid);
		$listarray = array();
		$barracks = array(1,2,3,11,12,13,14,21,22,31,32,33,34,41,42,43,44);
		$greatbarracks = array(61,62,63,71,72,73,74,81,82,91,92,93,94,101,102,103,104);
		$stables = array(4,5,6,15,16,23,24,25,26,35,36,45,46);
		$greatstables = array(64,65,66,75,76,83,84,85,86,95,96,105,106);
		$workshop = array(7,8,17,18,27,28,37,38,47,48);
		$greatworkshop = array(67,68,77,78,87,88,97,98,107,108);
		$residence = array(9,10,19,20,29,30,39,40,49,50);
		$trapper = array(99);
		if(count($trainingarray) > 0) {
			foreach($trainingarray as $train) {
				if($type == 1 && in_array($train['unit'],$barracks)) {
				$train['name'] = $this->unarray[$train['unit']];
				array_push($listarray,$train);
				}
				if($type == 2 && in_array($train['unit'],$stables)) {
					$train['name'] = $this->unarray[$train['unit']];
					array_push($listarray,$train);
				}
				if($type == 3 && in_array($train['unit'],$workshop)) {
					$train['name'] = $this->unarray[$train['unit']];
					array_push($listarray,$train);
				}
				if($type == 4 && in_array($train['unit'],$residence)) {
					$train['name'] = $this->unarray[$train['unit']];
					array_push($listarray,$train);
				}
				if($type == 5 && in_array($train['unit'],$greatbarracks)) {
					$train['name'] = $this->unarray[$train['unit']-60];
					$train['unit'] -= 60;
					array_push($listarray,$train);
				}
				if($type == 6 && in_array($train['unit'],$greatstables)) {
					$train['name'] = $this->unarray[$train['unit']-60];
					$train['unit'] -= 60;
					array_push($listarray,$train);
				}
				if($type == 7 && in_array($train['unit'],$greatworkshop)) {
					$train['name'] = $this->unarray[$train['unit']-60];
					$train['unit'] -= 60;
					array_push($listarray,$train);
				}
				if($type == 8 && in_array($train['unit'],$trapper)) {
					$train['name'] = $this->unarray[$train['unit']];
					array_push($listarray,$train);
				}
			}
		}
		return $listarray;
	}
	
	public function getUnitList() {
		global $database,$village;
		$unitcheck = $database->getUnit($village->wid);
		for($i=1;$i<=50;$i++) {
			if($unitcheck['u'.$i] >= "40000000000" && LIMIT_TROOPS) {
				mysql_query("UPDATE ".TB_PREFIX."units set u".$i." = '0' where vref = $village->wid");
			}
		}
		$unitarray = func_num_args() == 1? $database->getUnit(func_get_arg(0)) : $village->unitarray;
		$listArray = array();
		if($unitarray['hero'] != 0 && $unitarray['hero'] != "") {
                $holder['id'] = "hero";
                $holder['name'] = $this->unarray[$i+1];
                $holder['amt'] = $unitarray['hero'];
                array_push($listArray,$holder);
        }
		
		for($i=1;$i<count($this->unarray);$i++) {
			$holder = array();
			if($unitarray['u'.$i] != 0 && $unitarray['u'.$i] != "") {
				$holder['id'] = $i;
				$holder['name'] = $this->unarray[$i];
				$holder['amt'] = $unitarray['u'.$i];
				array_push($listArray,$holder);
			}
		}
		return $listArray;
	}
	
	public function maxUnit($unit,$great=false) {
		$unit = "u".$unit;
		global $village,$$unit;
		$unitarray = $$unit;
		$res = array();
		$res = mysql_fetch_assoc(mysql_query("SELECT maxstore, maxcrop, wood, clay, iron, crop FROM ".TB_PREFIX."vdata WHERE wref = ".$village->wid)) or die(mysql_error());
		if ($res['wood'] > $res['maxstore']){$res['wood'] = $res['maxstore'];}
		if ($res['clay'] > $res['maxstore']){$res['clay'] = $res['maxstore'];}
		if ($res['iron'] > $res['maxstore']){$res['iron'] = $res['maxstore'];}
		if ($res['crop'] > $res['maxcrop']){$res['crop'] = $res['maxcrop'];}
		$woodcalc = floor($res['wood'] / ($unitarray['wood'] * ($great?3:1)));
		$claycalc = floor($res['clay'] / ($unitarray['clay'] * ($great?3:1)));
		$ironcalc = floor($res['iron'] / ($unitarray['iron'] * ($great?3:1)));
		if($res['crop']>0){
		$cropcalc = floor($res['crop'] / ($unitarray['crop'] * ($great?3:1)));
		}else{
		$cropcalc = 0;
		}
		if($unit != "u99"){
		$popcalc = floor($village->getProd("crop")/$unitarray['pop']);
		}else{
		$popcalc = $village->getProd("crop");
		}
		return max(0,min($woodcalc,$claycalc,$ironcalc,$cropcalc,$popcalc));
	}
	
    public function maxUnitPlus($unit,$great=false) {
        $unit = "u".$unit;
        global $village,$$unit;
        $unitarray = $$unit;
        $res = array();
        $res = mysql_fetch_assoc(mysql_query("SELECT maxstore, maxcrop, wood, clay, iron, crop FROM ".TB_PREFIX."vdata WHERE wref = ".$village->wid)) or die(mysql_error());
        $totalres = $res['wood']+$res['clay']+$res['iron']+$res['crop'];
        $totalresunit = ($unitarray['wood'] * ($great?3:1))+($unitarray['clay'] * ($great?3:1))+($unitarray['iron'] * ($great?3:1))+($unitarray['crop'] * ($great?3:1));
        $max =round($totalres/$totalresunit);
        return $max;
    }
    
	public function getUnits() {
		global $database,$village;
		if(func_num_args() == 1) {
			$base = func_get_arg(0);
		}
		$ownunit = func_num_args() == 2? func_get_arg(0) : $database->getUnit($base);
		$enforcementarray = func_num_args() == 2? func_get_arg(1) : $database->getEnforceVillage($base,0);
		if(count($enforcementarray) > 0) {
			foreach($enforcementarray as $enforce) {
				for($i=1;$i<=50;$i++) {
					$ownunit['u'.$i] += $enforce['u'.$i];
				}
			}
		}
		return $ownunit;
	}
	
	function getAllUnits($base) {
		global $database;
		$ownunit = $database->getUnit($base);
		$ownunit['u99'] -= $ownunit['u99'];
		$ownunit['u99o'] -= $ownunit['u99o'];
		$enforcementarray = $database->getEnforceVillage($base,0);
		if(count($enforcementarray) > 0) {
			foreach($enforcementarray as $enforce) {
				for($i=1;$i<=50;$i++) {
					$ownunit['u'.$i] = (int)(isset($ownunit['u'.$i]) ? $ownunit['u'.$i] : 0)
						+ (int)(isset($enforce['u'.$i]) ? $enforce['u'.$i] : 0);
				}
				$ownunit['hero'] = (int)(isset($ownunit['hero']) ? $ownunit['hero'] : 0)
					+ (int)(isset($enforce['hero']) ? $enforce['hero'] : 0);
			}
		}
		// Un oasis no produce cereal ni tiene granero: a las tropas estacionadas ahí
		// las alimenta la aldea que lo conquistó. Sin esto un ejército aparcado en el
		// oasis no le costaba cereal a nadie.
		foreach($database->getOasis($base) as $oasis) {
			foreach($database->getEnforceVillage($oasis['wref'],0) as $enforce) {
				for($i=1;$i<=50;$i++) {
					$ownunit['u'.$i] = (int)(isset($ownunit['u'.$i]) ? $ownunit['u'.$i] : 0)
						+ (int)(isset($enforce['u'.$i]) ? $enforce['u'.$i] : 0);
				}
				$ownunit['hero'] = (int)(isset($ownunit['hero']) ? $ownunit['hero'] : 0)
					+ (int)(isset($enforce['hero']) ? $enforce['hero'] : 0);
			}
		}
		$movement = $database->getVillageMovement($base);
		if(!empty($movement)) {
			for($i=1;$i<=50;$i++) {
				$ownunit['u'.$i] = (int)(isset($ownunit['u'.$i]) ? $ownunit['u'.$i] : 0)
					+ (int)(isset($movement['u'.$i]) ? $movement['u'.$i] : 0);
			}
			$ownunit['hero'] = (int)(isset($ownunit['hero']) ? $ownunit['hero'] : 0)
				+ (int)(isset($movement['hero']) ? $movement['hero'] : 0);
		}
		$owner = (int)$database->getVillageField($base,'owner');
		$tribe = (int)$database->getUserField($owner,'tribe',0);
		if($tribe >= 1 && $tribe <= 5) {
			$prisoners = $database->getPrisoners3($base);
			foreach($prisoners as $prisoner) {
				for($position = 1; $position <= 10; $position++) {
					$unit = (($tribe - 1) * 10) + $position;
					$ownunit['u'.$unit] = (int)(isset($ownunit['u'.$unit]) ? $ownunit['u'.$unit] : 0)
						+ max(0,(int)$prisoner['t'.$position]);
				}
				$ownunit['hero'] = (int)(isset($ownunit['hero']) ? $ownunit['hero'] : 0)
					+ max(0,(int)$prisoner['t11']);
			}
		}
		return $ownunit;
	}	
	
	public function meetTRequirement($unit) {
		global $session;
		switch($unit) {
			case 1:
			if($session->tribe == 1) { return true; } else { return false; }
			break;
			case 2:
			case 3:
			case 4:
			case 5:
			case 6:
			case 7:
			case 8:
			if($this->getTech($unit) && $session->tribe == 1) { return true; } else { return false; }
			break;
			case 10:
			if($session->tribe == 1) { return true; } else { return false; }
			break;
			case 11:
			if($session->tribe == 2) { return true; } else { return false; }
			break;
			case 12:
			case 13:
			case 14:
			case 15:
			case 16:
			case 17:
			case 18:
			if($session->tribe == 2 && $this->getTech($unit)) { return true; } else { return false; }
			break;
			case 20:
			if($session->tribe == 2) { return true; } else { return false; }
			break;
			case 21:
			if($session->tribe == 3) { return true; } else { return false; }
			break;
			case 22: 
			case 23:
			case 24:
			case 25:
			case 26:
			case 27:
			case 28:
			if($session->tribe == 3 && $this->getTech($unit)) { return true; } else { return false; }
			break;
			case 30:
			if($session->tribe == 3) { return true; } else { return false; }
			break;
            case 31:
            if($session->tribe == 4) { return true; } else { return false; }
            break;
            case 32: 
            case 33:
            case 34:
            case 35:
            case 36:
            case 37:
            case 38:
            if($session->tribe == 4 && $this->getTech($unit)) { return true; } else { return false; }
            break;
            case 40:
            if($session->tribe == 4) { return true; } else { return false; }
            break;
            case 41:
            if($session->tribe == 5) { return true; } else { return false; }
            break;
            case 42: 
            case 43:
            case 44:
            case 45:
            case 46:
            case 47:
            case 48:
            if($session->tribe == 5 && $this->getTech($unit)) { return true; } else { return false; }
            break;
            case 50:
            if($session->tribe == 5) { return true; } else { return false; }
            break;
		}
	}
	
	public function getTech($tech) {
		global $village;
		return ($village->techarray['t'.$tech] == 1);
	}

	public function getExpansionUnitTrainingTime($unit,$fieldId) {
		global $village,$bid25,$bid26;

		$unit = (int)$unit;
		$fieldId = (int)$fieldId;
		$unitData = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
		$fieldType = isset($village->resarray['f'.$fieldId.'t']) ? (int)$village->resarray['f'.$fieldId.'t'] : 0;
		$fieldLevel = isset($village->resarray['f'.$fieldId]) ? (int)$village->resarray['f'.$fieldId] : 0;

		if(!is_array($unitData)
			|| !in_array($unit,array(9,10,19,20,29,30,39,40,49,50),true)
			|| !in_array($fieldType,array(25,26),true)
			|| $fieldLevel < 10) {
			return 0;
		}

		$buildingData = $fieldType === 25 ? $bid25 : $bid26;
		if(!isset($buildingData[$fieldLevel]['attri'])) {
			return 0;
		}

		return max(1,(int)round(
			($buildingData[$fieldLevel]['attri'] / 100) * $unitData['time'] / SPEED
		));
	}
	
	private function procTrain($post,$great=false) {
		global $session;
		$fieldId = isset($post['id']) && is_scalar($post['id']) && ctype_digit((string)$post['id'])
			? (int)$post['id']
			: 0;
		if($session->access == BANNED) {
			header("Location: banned.php");
			return;
		}
		$tokenIsValid = isset($post['k']) && is_scalar($post['k'])
			&& hash_equals((string)$session->mchecker,(string)$post['k']);
		if(!$tokenIsValid || $fieldId < 1 || $fieldId > 40) {
			header("Location: build.php?id=".($fieldId > 0 ? $fieldId : 1));
			return;
		}
		$session->changeChecker();

		$start = ($session->tribe-1)*10+1;
		$end = ($session->tribe*10);
		for($i=$start;$i<=$end;$i++) {
			$amt = isset($post['t'.$i]) ? (int)$post['t'.$i] : 0;
			if($amt > 0) {
				$this->trainUnit($i,$amt,$great,$fieldId);
			}
		}
		if($session->tribe == 3) {
			$amt = isset($post['t99']) ? (int)$post['t99'] : 0;
			if($amt > 0) {
				$this->trainUnit(99,$amt,$great,$fieldId);
			}
		}
		header("Location: build.php?id=".$fieldId);
	}
	
	public function getUpkeep($array,$type) {
		global $building,$database,$session;
		$upkeep = 0;
		$nocrop = 0;
		$horseDrinkingLevel = 0;
		if((int)$session->tribe === 1) {
			if(is_object($building) && method_exists($building,'getTypeLevel')) {
				$horseDrinkingLevel = (int)$building->getTypeLevel(41);
			} elseif(isset($array['vref']) && (int)$array['vref'] > 0) {
				$fields = $database->getResourceLevel((int)$array['vref']);
				if(is_array($fields)) {
					for($field = 19; $field <= 38; $field++) {
						if((int)$fields['f'.$field.'t'] === 41) {
							$horseDrinkingLevel = max($horseDrinkingLevel,(int)$fields['f'.$field]);
						}
					}
				}
			}
		}
		switch($type) {
			case 0:
			$start = 1;
			$end = 50;
			break;
			case 1:
			$start = 1;
			$end = 10;
			break;
			case 2:
			$start = 11;
			$end = 20;
			break;
			case 3:
			$start = 21;
			$end = 30;
			break;
			case 4:
			// Los animales sí consumen cereal en la aldea (getAllUnits los suma con type 0),
			// así que el informe tiene que mostrar el mismo número que la barra de recursos.
			$start = 31;
			$end = 40;
			break;
            case 5:
            $start = 41;
            $end = 50;
            break;
		}	
		if($nocrop == 0){
		for($i=$start;$i<=$end;$i++) {
			$hdt = 0;
			if($session->tribe == 1 && $i>=4 && $i<=6) {
				if(
					($i == 4 && $horseDrinkingLevel >= 10)
					|| ($i == 5 && $horseDrinkingLevel >= 15)
					|| ($i == 6 && $horseDrinkingLevel >= 20)
				) {
					$hdt = 1;
				}
			}
			$unit = "u".$i;
			global $$unit;
			$dataarray = $$unit;
			$amount = isset($array[$unit]) ? max(0, (int)$array[$unit]) : 0;
			$upkeep += max(0, $dataarray['pop'] - $hdt) * $amount;
		}
            if(!empty($array['hero'])){
            	$upkeep += 6;
			}
		}
		return $upkeep;
	}

	private function trainUnit($unit,$amt,$great=false,$fieldId=0) {
		global $session,$database,${'u'.$unit},$building,$village,$bid19,$bid20,$bid21,$bid25,$bid26,$bid29,$bid30,$bid36,$bid41,$bid42;

		$unit = (int)$unit;
		$amt = (int)$amt;
		$fieldId = (int)$fieldId;
		if($amt <= 0 || $fieldId < 1 || $fieldId > 40
			|| !($this->getTech($unit) || $unit%10 <= 1 || $unit == 99)) {
			return false;
		}

		$footies = array(1,2,3,11,12,13,14,21,22,31,32,33,34,41,42,43,44);
		$calvary = array(4,5,6,15,16,23,24,25,26,35,36,45,46);
		$workshop = array(7,8,17,18,27,28,37,38,47,48);
		$special = array(9,10,19,20,29,30,39,40,49,50);
		$trapper = array(99);
		$isExpansionUnit = ($unit%10 == 0 || ($unit%10 == 9 && $unit != 99));
		$fieldType = isset($village->resarray['f'.$fieldId.'t']) ? (int)$village->resarray['f'.$fieldId.'t'] : 0;
		$fieldLevel = isset($village->resarray['f'.$fieldId]) ? (int)$village->resarray['f'.$fieldId] : 0;
		$expectedFieldType = 0;
		if(in_array($unit,$footies,true)) {
			$expectedFieldType = $great ? 29 : 19;
		} elseif(in_array($unit,$calvary,true)) {
			$expectedFieldType = $great ? 30 : 20;
		} elseif(in_array($unit,$workshop,true)) {
			$expectedFieldType = $great ? 42 : 21;
		} elseif($isExpansionUnit) {
			$expectedFieldType = $fieldType;
		} elseif($unit === 99) {
			$expectedFieldType = 36;
		}
		if($expectedFieldType === 0 || $fieldType !== $expectedFieldType || $fieldLevel < 1) {
			return false;
		}
		if($isExpansionUnit && ($great || !in_array($fieldType,array(25,26),true) || $fieldLevel < 10)) {
			return false;
		}
		if($unit === 99 && ($great || (int)$session->tribe !== 3 || $fieldType !== 36 || $fieldLevel < 1)) {
			return false;
		}

		$lockAcquired = false;
		if($isExpansionUnit) {
			$lockAcquired = $database->acquireSettlementLock($session->uid,5);
			if(!$lockAcquired) {
				return false;
			}
		}

		try {
			$each = 0;
			if(in_array($unit,$footies,true)) {
				// El casco entra dentro del round() y no después, para que el tiempo
				// encolado sea exactamente el que muestran 19_train.tpl / 29_train.tpl.
				$helmet = heroTrainingTimeFactor($database,$session->uid,$village->wid,$great ? 29 : 19);
				$each = $great
					? round(($bid29[$building->getTypeLevel(29)]['attri'] / 100) * ${'u'.$unit}['time'] / SPEED * $helmet)
					: round(($bid19[$building->getTypeLevel(19)]['attri'] / 100) * ${'u'.$unit}['time'] / SPEED * $helmet);
			}
			if(in_array($unit,$calvary,true)) {
				$horseDrinking = $building->getTypeLevel(41)>=1 ? (1/$bid41[$building->getTypeLevel(41)]['attri']) : 1;
				$helmet = heroTrainingTimeFactor($database,$session->uid,$village->wid,$great ? 30 : 20);
				$each = $great
					? round(($bid30[$building->getTypeLevel(30)]['attri'] * $horseDrinking / 100) * ${'u'.$unit}['time'] / SPEED * $helmet)
					: round(($bid20[$building->getTypeLevel(20)]['attri'] * $horseDrinking / 100) * ${'u'.$unit}['time'] / SPEED * $helmet);
			}
			if(in_array($unit,$workshop,true)) {
				$each = $great
					? round(($bid42[$building->getTypeLevel(42)]['attri'] / 100) * ${'u'.$unit}['time'] / SPEED)
					: round(($bid21[$building->getTypeLevel(21)]['attri'] / 100) * ${'u'.$unit}['time'] / SPEED);
			}
			if(in_array($unit,array_merge($footies,$calvary,$workshop),true)) {
				$each = round($each * $this->getTrainingArtefactFactor());
			}
			if(in_array($unit,$special,true)) {
				$each = $this->getExpansionUnitTrainingTime($unit,$fieldId);
			}
			if(in_array($unit,$trapper,true)) {
				$each = round(($bid19[$fieldLevel]['attri'] / 100) * ${'u'.$unit}['time'] / SPEED);
			}

			if($isExpansionUnit) {
				$slots = $database->getAvailableExpansionTraining();
				$available = $unit%10 == 0 ? (int)$slots['settlers'] : (int)$slots['chiefs'];
				if($available < $amt) {
					return false;
				}
			} elseif($unit != 99) {
				if($this->maxUnit($unit,$great) < $amt) {
					return false;
				}
			} else {
				$trainlist = $this->getTrainingList(8);
				$train_amt = 0;
				foreach($trainlist as $train) {
					$train_amt += max(0,(int)$train['amt']);
				}
				$max = 0;
				for($i=19;$i<41;$i++) {
					$level = (int)$village->resarray['f'.$i];
					if((int)$village->resarray['f'.$i.'t'] === 36 && $level > 0 && isset($bid36[$level]['attri'])) {
						$max += $bid36[$level]['attri'] * TRAPPER_CAPACITY;
					}
				}
				if($max - ($village->unitarray['u99'] + $train_amt) < $amt) {
					return false;
				}
			}

			$multiplier = $great ? 3 : 1;
			$wood = ${'u'.$unit}['wood'] * $amt * $multiplier;
			$clay = ${'u'.$unit}['clay'] * $amt * $multiplier;
			$iron = ${'u'.$unit}['iron'] * $amt * $multiplier;
			$crop = ${'u'.$unit}['crop'] * $amt * $multiplier;
			$each = max(1,(int)$each);
			$time = $each*$amt;
			if(!$database->deductResourcesIfAvailable($village->wid,$wood,$clay,$iron,$crop)) {
				return false;
			}
			if(!$database->trainUnit($village->wid,$unit+($great?60:0),$amt,${'u'.$unit}['pop'],$each,time()+$time,0)) {
				$database->modifyResource($village->wid,$wood,$clay,$iron,$crop,1);
				return false;
			}
			return true;
		} finally {
			if($lockAcquired) {
				$database->releaseSettlementLock($session->uid);
			}
		}
	}

	public function getTrainingArtefactFactor() {
		global $database, $session, $village;
		if(!method_exists($database,'getActiveArtefactsByType')) {
			return 1;
		}
		$factor = 1;
		$artefacts = $database->getActiveArtefactsByType((int)$village->wid,(int)$session->uid,5);
		foreach($artefacts as $artefact) {
			$size = (int)$artefact['size'];
			$candidate = $size === 2 ? 0.25 : 0.5;
			$factor = min($factor,$candidate);
		}
		return $factor;
	}
	
	public function meetRRequirement($tech) {
		global $session,$building;
		switch($tech) {
			case 2:
			if($building->getTypeLevel(22) >= 1 && $building->getTypeLevel(12) >= 1) { return true; } else { return false; }
			break;
			case 3:
			if($building->getTypeLevel(22) >= 5 && $building->getTypeLevel(12) >= 1) { return true; } else { return false; }
			break;
			case 4:
			case 23:
			if($building->getTypeLevel(22) >= 5 && $building->getTypeLevel(20) >= 1) { return true; } else { return false; }
			break;
			case 5:
			case 25:
			if($building->getTypeLevel(22) >= 5 && $building->getTypeLevel(20) >= 5) { return true; } else { return false; }
			break;
			case 6:
			if($building->getTypeLevel(22) >= 15 && $building->getTypeLevel(20) >= 10) { return true; } else { return false; }
			break;
			case 9:
			case 29:
			if($building->getTypeLevel(22) >= 20 && $building->getTypeLevel(16) >= 10) { return true; } else { return false; }
			break;
			case 12:
            case 32:
            case 42:
			if($building->getTypeLevel(22) >= 1 && $building->getTypeLevel(19) >= 3) { return true; } else { return false; }
			break;
			case 13:
            case 33:
            case 43:
			if($building->getTypeLevel(22) >= 3 && $building->getTypeLevel(12) >= 1) { return true; } else { return false; }
			break;
			case 14:
            case 34:
            case 44:
			if($building->getTypeLevel(22) >= 1 && $building->getTypeLevel(15) >= 5) { return true; } else { return false; }
			break;
			case 15:
            case 35:
            case 45:
			if($building->getTypeLevel(22) >= 5 && $building->getTypeLevel(20) >= 5) { return true; } else { return false; }
			break;
			case 16:
			case 26:
            case 36:
            case 46:
			if($building->getTypeLevel(22) >= 15 && $building->getTypeLevel(20) >= 10) { return true; } else { return false; }
			break;
			case 7:
			case 17:
			case 27:
            case 37:
            case 47:
			if($building->getTypeLevel(22) >= 10 && $building->getTypeLevel(21) >= 1) { return true; } else { return false; }
			break;
			case 8:
			case 18:
			case 28:
            case 38:
            case 48:
			if($building->getTypeLevel(22) >= 15 && $building->getTypeLevel(21) >= 10) { return true; } else { return false; }
			break;
			case 19:
            case 39:
            case 49:
			if($building->getTypeLevel(22) >= 20 && $building->getTypeLevel(16) >= 5) { return true; } else { return false; }
			break;
			case 22:
			if($building->getTypeLevel(22) >= 3 && $building->getTypeLevel(12) >= 1) { return true; } else { return false; }
			break;
			case 24:
			if($building->getTypeLevel(22) >= 5 && $building->getTypeLevel(20) >= 3) { return true; } else { return false; }
			break;
		}
	}
	
	private function researchTech($get) {
		global $database,$session,$bid22,$building,$village,$logging;

		$fieldId = isset($get['id']) && is_scalar($get['id']) && ctype_digit((string)$get['id'])
			? (int)$get['id']
			: 0;
		$redirect = "build.php?id=".($fieldId >= 1 && $fieldId <= 40 ? $fieldId : 1);
		$tokenIsValid = isset($get['c']) && is_scalar($get['c'])
			&& hash_equals((string)$session->mchecker,(string)$get['c']);
		if(!$tokenIsValid) {
			header("Location: ".$redirect);
			return;
		}
		$session->changeChecker();

		$tech = isset($get['a']) && is_scalar($get['a']) && ctype_digit((string)$get['a'])
			? (int)$get['a']
			: 0;
		$start = ($session->tribe-1)*10+1;
		$end = $session->tribe*10;
		$data = isset($GLOBALS['r'.$tech]) ? $GLOBALS['r'.$tech] : null;
		$academyLevel = (int)$building->getTypeLevel(22);
		if($tech < $start || $tech > $end || !is_array($data)
			|| $academyLevel < 1 || !isset($bid22[$academyLevel]['attri'])
			|| !$this->meetRRequirement($tech)
			|| $this->getTech($tech)
			|| $this->isResearch($tech,1)
			|| count($this->grabAcademyRes()) > 0) {
			header("Location: ".$redirect);
			return;
		}

		$time = time() + max(1,(int)round(($data['time'] * ($bid22[$academyLevel]['attri'] / 100))/SPEED));
		if($database->deductResourcesIfAvailable($village->wid,$data['wood'],$data['clay'],$data['iron'],$data['crop'])) {
			if($database->addResearch($village->wid,"t".$tech,$time)) {
				$logging->addTechLog($village->wid,"t".$tech,1);
			} else {
				$database->modifyResource($village->wid,$data['wood'],$data['clay'],$data['iron'],$data['crop'],1);
			}
		}
		header("Location: ".$redirect);
	}
	
	private function upgradeSword($get) {
		global $database,$session,$bid12,$building,$village,$logging;
		$ABTech = $database->getABTech($village->wid);
		$CurrentTech = $ABTech["b".$get['a']];
		$unit = ($session->tribe-1)*10+intval($get['a']);
		if(($this->getTech($unit) || ($unit % 10) == 1) && ($CurrentTech < $building->getTypeLevel(12)) && $get['c'] == $session->mchecker) {
			global ${'ab'.strval($unit)};
			$data = ${'ab'.strval($unit)};
			$time = time() + round(($data[$CurrentTech+1]['time'] * ($bid12[$building->getTypeLevel(12)]['attri'] / 100))/SPEED);
			if ($database->modifyResource($village->wid,$data[$CurrentTech+1]['wood'],$data[$CurrentTech+1]['clay'],$data[$CurrentTech+1]['iron'],$data[$CurrentTech+1]['crop'],0)) {
				$database->addResearch($village->wid,"b".$get['a'],$time);
				$logging->addTechLog($village->wid,"b".$get['a'],$CurrentTech+1);
			}
		}
		$session->changeChecker();
		header("Location: build.php?id=".$get['id']);
	}
	
	public function getUnitName($i) {
		return $this->unarray[$i];
	}
	
    public function finishTech() {
		global $database,$village;
		$q = "UPDATE ".TB_PREFIX."research SET timestamp=".(time()-1)." WHERE vref = ".$village->wid;
		$database->query($q);
    }
	
	public function calculateAvaliable($id,$resarray=array()) {
		global $village,$generator,${'r'.$id};
		if(count($resarray)==0) {
			$resarray['wood'] = ${'r'.$id}['wood'];
			$resarray['clay'] = ${'r'.$id}['clay'];
			$resarray['iron'] = ${'r'.$id}['iron'];
			$resarray['crop'] = ${'r'.$id}['crop'];
		}
		$times = array();
		foreach(array('wood','clay','iron','crop') as $res) {
			$missing = $resarray[$res] - $village->{'a'.$res};
			if($missing <= 0) {
				$times[] = 0;
				continue;
			}
			$prod = $village->getProd($res);
			// Sin produccion (cereal negativo o cero) nunca se alcanza: se muestra a un mes vista.
			$times[] = $prod > 0 ? $missing / $prod * 3600 : 30*24*3600;
		}
		$reqtime = max($times);
		$reqtime += time();
		return $generator->procMtime($reqtime);
	}

	public function checkReinf($id) {
		global $database;
		$enforce=$database->getEnforceArray($id,0);
		$fail='0';
					for($i=1; $i<=50; $i++){
						if($enforce['u'.$i.'']>0){
						$fail='1';
						}
					}
			// Un refuerzo que solo trae al héroe también es un refuerzo: borrar la fila
			// aquí hacía desaparecer al héroe de la aldea defensora.
			if($enforce['hero']>0){
			$fail='1';
			}
			if($fail==0){
			$database->deleteReinf($id);
			}

	}
	
}
$technology = new Technology;
?>
