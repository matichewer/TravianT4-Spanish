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

	/**
	 * Agrupa por tipo todas las tropas alojadas en una aldea para el bloque lateral.
	 * `units` y cada fila de `enforcement` son contingentes distintos, pero al jugador
	 * le interesa la guarnicion total: un mismo uN no debe ocupar varios renglones.
	 */
	public function aggregateHostedUnitList($units, $reinforcements) {
		$totals = array();
		$names = array();
		$order = array();

		foreach((array)$units as $unit) {
			$id = isset($unit['id']) ? (string)$unit['id'] : '';
			$amount = isset($unit['amt']) ? (int)$unit['amt'] : 0;
			if(($id === 'hero' || ctype_digit($id)) && $amount > 0) {
				if(!isset($totals[$id])) {
					$totals[$id] = 0;
					$order[] = $id;
				}
				$totals[$id] += $amount;
				$names[$id] = isset($unit['name']) ? $unit['name'] : ($id === 'hero' ? U0 : $this->unarray[(int)$id]);
			}
		}

		foreach((array)$reinforcements as $reinforcement) {
			$heroAmount = isset($reinforcement['hero']) ? (int)$reinforcement['hero'] : 0;
			if($heroAmount > 0) {
				if(!isset($totals['hero'])) {
					$totals['hero'] = 0;
					$names['hero'] = U0;
					$order[] = 'hero';
				}
				$totals['hero'] += $heroAmount;
			}
			for($i = 1; $i <= 50; $i++) {
				$amount = isset($reinforcement['u'.$i]) ? (int)$reinforcement['u'.$i] : 0;
				if($amount <= 0) {
					continue;
				}
				$id = (string)$i;
				if(!isset($totals[$id])) {
					$totals[$id] = 0;
					$names[$id] = $this->unarray[$i];
					$order[] = $id;
				}
				$totals[$id] += $amount;
			}
		}

		$list = array();
		foreach($order as $id) {
			$list[] = array('id' => $id, 'name' => $names[$id], 'amt' => $totals[$id]);
		}
		return $list;
	}
	
	/**
	 * Cuántas unidades de este tipo se pueden pedir de una: sólo lo que alcanzan los
	 * recursos del almacén.
	 *
	 * El balance de cereal NO entra. En el T4 oficial se entrena todo lo que se
	 * pueda pagar y el castigo por pasarse es la hambruna, no un tope: así es como
	 * funciona un martillo, que vive en rojo y se alimenta con saqueo y transportes.
	 * Acá el min() incluía floor(produccion_cereal / consumo_unidad), así que con el
	 * balance en cero o negativo el máximo era 0 y no entraba ninguna orden — y
	 * encima el tope era por orden, no acumulado (las tropas en cola no comen hasta
	 * que salen), así que se saltaba repitiendo el pedido. Era una regla propia que
	 * no frenaba nada y bloqueaba a quien la respetaba.
	 */
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
		$multiplier = $great ? 3 : 1;
		$maximum = null;
		foreach(array('wood','clay','iron','crop') as $resource) {
			$cost = (int)$unitarray[$resource] * $multiplier;
			if($cost <= 0) {
				continue;
			}
			$available = (float)$res[$resource];
			$affordable = $available > 0 ? floor($available / $cost) : 0;
			$maximum = $maximum === null ? $affordable : min($maximum,$affordable);
		}
		return max(0,(int)($maximum === null ? 0 : $maximum));
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
		return isset($village->techarray['t'.$tech]) && (int)$village->techarray['t'.$tech] === 1;
	}

	// Tiempo de entrenamiento de una unidad en segundos, para cuartel (19), gran cuartel
	// (29), establo (20), gran establo (30), taller (21) y gran taller (42).
	//
	// Es la única definición: la usan trainUnit() y las plantillas de esos edificios,
	// para que el tiempo que se muestra sea exactamente el que se encola. Antes cada
	// plantilla repetía la fórmula por su cuenta y se olvidaba de algún factor: las del
	// establo no descontaban el Bebedero, y ni las del cuartel ni las del establo
	// descontaban el artefacto de entrenamiento, así que con cualquiera de los dos
	// activos la interfaz mentía.
	//
	// `$level` es el del edificio; si no se pasa, sale del propio edificio de la aldea.
	public function getUnitTrainingTime($unit,$buildingType,$level=null) {
		global $database,$session,$village,$building,$bid19,$bid20,$bid21,$bid29,$bid30,$bid41,$bid42;

		$unit = (int)$unit;
		$buildingType = (int)$buildingType;
		$unitData = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
		$tables = array(19=>$bid19, 20=>$bid20, 21=>$bid21, 29=>$bid29, 30=>$bid30, 42=>$bid42);
		if(!is_array($unitData) || !isset($tables[$buildingType])) {
			return 0;
		}

		if($level === null) {
			$level = is_object($building) ? $building->getTypeLevel($buildingType) : 0;
		}
		$level = (int)$level;
		if(!isset($tables[$buildingType][$level]['attri'])) {
			return 0;
		}

		$time = $unitData['time'] * ($tables[$buildingType][$level]['attri'] / 100) / SPEED;

		// El Bebedero solo acelera la caballería.
		if($buildingType === 20 || $buildingType === 30) {
			$troughLevel = is_object($building) ? (int)$building->getTypeLevel(41) : 0;
			if($troughLevel >= 1 && isset($bid41[$troughLevel]['attri'])) {
				$time /= $bid41[$troughLevel]['attri'];
			}
		}

		$time *= heroTrainingTimeFactor($database,$session->uid,$village->wid,$buildingType);
		$time *= $this->getTrainingArtefactFactor();

		return max(1,(int)round($time));
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
			$value = isset($post['t'.$i]) ? $post['t'.$i] : null;
			$amt = is_scalar($value) && ctype_digit((string)$value) ? (int)$value : 0;
			if($amt > 0) {
				$this->trainUnit($i,$amt,$great,$fieldId);
			}
		}
		if($session->tribe == 3) {
			$value = isset($post['t99']) ? $post['t99'] : null;
			$amt = is_scalar($value) && ctype_digit((string)$value) ? (int)$value : 0;
			if($amt > 0) {
				$this->trainUnit(99,$amt,$great,$fieldId);
			}
		}
		header("Location: build.php?id=".$fieldId);
	}
	
	// El Bebedero reduce el consumo de las tropas que están físicamente en la
	// aldea con el edificio (propias o de refuerzo), no en la aldea activa de
	// quien mira la pantalla. `$vid` (o `$array['vref']` si no se pasa) elige
	// esa aldea; antes se miraba siempre la aldea activa de `$building`, así
	// que un informe de refuerzo enviado o la lista de "Refuerzos" en la Plaza
	// de reuniones mostraban el descuento de la aldea equivocada.
	public function getUpkeep($array,$type,$vid=0) {
		global $building,$database,$session,$village;
		$upkeep = 0;
		$nocrop = 0;
		$horseDrinkingLevel = 0;

		$targetVid = 0;
		if((int)$vid > 0) {
			$targetVid = (int)$vid;
		} elseif(isset($array['vref']) && (int)$array['vref'] > 0) {
			$targetVid = (int)$array['vref'];
		} elseif(is_object($village) && isset($village->wid)) {
			$targetVid = (int)$village->wid;
		}

		if($targetVid > 0 && is_object($building) && method_exists($building,'getTypeLevel')) {
			$currentVid = (is_object($village) && isset($village->wid)) ? (int)$village->wid : 0;
			$horseDrinkingLevel = ($targetVid === $currentVid)
				? (int)$building->getTypeLevel(41)
				: (int)$building->getTypeLevel(41,$targetVid);
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
			// Los animales de un oasis capturados con jaulas defienden la aldea,
			// pero no consumen cereal.
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
			if($i >= 31 && $i <= 40) {
				continue;
			}
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
		$tribeStart = ((int)$session->tribe - 1) * 10 + 1;
		$tribeEnd = (int)$session->tribe * 10;
		if($amt <= 0 || $fieldId < 1 || $fieldId > 40
			|| ($unit !== 99 && ($unit < $tribeStart || $unit > $tribeEnd))
			|| !($unit == 99 || $unit%10 <= 1 || $this->getTech($unit))) {
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

		$settlementLockAcquired = false;
		$trainingLockAcquired = false;
		if($isExpansionUnit) {
			$settlementLockAcquired = $database->acquireSettlementLock($session->uid,5);
			if(!$settlementLockAcquired) {
				return false;
			}
		}

		try {
			if(method_exists($database,'acquireTrainingLock')) {
				$trainingLockAcquired = $database->acquireTrainingLock($village->wid,5);
				if(!$trainingLockAcquired) {
					return false;
				}
			}

			$each = 0;
			if(in_array($unit,$footies,true)) {
				$each = $this->getUnitTrainingTime($unit,$great ? 29 : 19);
			}
			if(in_array($unit,$calvary,true)) {
				$each = $this->getUnitTrainingTime($unit,$great ? 30 : 20);
			}
			if(in_array($unit,$workshop,true)) {
				$each = $this->getUnitTrainingTime($unit,$great ? 42 : 21);
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
			if(!$database->trainUnit($village->wid,$unit+($great?60:0),$amt,${'u'.$unit}['pop'],$each,time()+$time,0,$trainingLockAcquired)) {
				$database->modifyResource($village->wid,$wood,$clay,$iron,$crop,1);
				return false;
			}
			return true;
		} finally {
			if($trainingLockAcquired) {
				$database->releaseTrainingLock($village->wid);
			}
			if($settlementLockAcquired) {
				$database->releaseSettlementLock($session->uid);
			}
		}
	}

	/**
	 * El factor que el artefacto del entrenador le aplica al tiempo de entrenamiento.
	 *
	 * Oficial: el pequeño y el único lo parten a la mitad, el grande lo deja en 3/4. Acá
	 * el grande valía 0,25 —o sea, era el más fuerte de los tres y cuadruplicaba la
	 * velocidad— porque la tabla estaba escrita a mano y al revés. Ahora sale de
	 * artefactValueTable(), que es la tabla oficial completa.
	 */
	public function getTrainingArtefactFactor() {
		global $database, $session, $village;
		if(!is_object($database) || !method_exists($database,'getArtefactEffectValue')) {
			return 1;
		}
		$factor = (float)$database->getArtefactEffectValue((int)$village->wid,(int)$session->uid,ARTEFACT_TRAINER);
		return $factor > 0 ? $factor : 1;
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
		$fieldId = isset($get['id']) && is_scalar($get['id']) && ctype_digit((string)$get['id'])
			? (int)$get['id'] : 0;
		$redirect = "build.php?id=".($fieldId >= 1 && $fieldId <= 40 ? $fieldId : 1);
		$tokenIsValid = isset($get['c']) && is_scalar($get['c'])
			&& hash_equals((string)$session->mchecker,(string)$get['c']);
		if(!$tokenIsValid) {
			header("Location: ".$redirect);
			return;
		}
		$session->changeChecker();

		$position = isset($get['a']) && is_scalar($get['a']) && ctype_digit((string)$get['a'])
			? (int)$get['a'] : 0;
		$smithyLevel = (int)$building->getTypeLevel(12);
		$unit = ((int)$session->tribe-1)*10+$position;
		$dataName = 'ab'.$unit;
		if($fieldId < 1 || $fieldId > 40
			|| !isset($village->resarray['f'.$fieldId.'t']) || (int)$village->resarray['f'.$fieldId.'t'] !== 12
			|| !isset($village->resarray['f'.$fieldId]) || (int)$village->resarray['f'.$fieldId] < 1
			|| $position < 1 || $position > 8 || $smithyLevel < 1 || $smithyLevel > 20
			|| !isset($bid12[$smithyLevel]['attri']) || !isset($GLOBALS[$dataName])
			|| (!$this->getTech($unit) && $position !== 1)) {
			header("Location: ".$redirect);
			return;
		}

		if(!$database->acquireResearchLock($village->wid,5)) {
			header("Location: ".$redirect);
			return;
		}
		try {
			do {
			$ABTech = $database->getABTech($village->wid);
			$currentTech = is_array($ABTech) && isset($ABTech['b'.$position]) ? (int)$ABTech['b'.$position] : 20;
			$running = $database->getResearching($village->wid);
			$hasSmithyOrder = false;
			foreach(is_array($running) ? $running : array() as $research) {
				if(isset($research['tech']) && substr((string)$research['tech'],0,1) === 'b') {
					$hasSmithyOrder = true;
					break;
				}
			}
			$nextLevel = $currentTech+1;
			$data = $GLOBALS[$dataName];
			if($hasSmithyOrder || $currentTech < 0 || $currentTech >= 20
				|| $currentTech >= $smithyLevel || !isset($data[$nextLevel])) {
				break;
			}
			$cost = $data[$nextLevel];
			$time = time()+max(1,(int)round(($cost['time']*($bid12[$smithyLevel]['attri']/100))/SPEED));
			if(!$database->deductResourcesIfAvailable($village->wid,$cost['wood'],$cost['clay'],$cost['iron'],$cost['crop'])) {
				break;
			}
			if(!$database->addResearch($village->wid,'b'.$position,$time)) {
				$database->modifyResource($village->wid,$cost['wood'],$cost['clay'],$cost['iron'],$cost['crop'],1);
				break;
			}
			$logging->addTechLog($village->wid,'b'.$position,$nextLevel);
			} while(false);
		} finally {
			$database->releaseResearchLock($village->wid);
		}
		header("Location: ".$redirect);
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
