<?php

class Building {	

	public $NewBuilding = false;
	private $maxConcurrent;
	private $allocated;
	private $basic,$inner,$plus = 0;
	public $buildArray = array();

	public function __construct() {
		global $session;
		$this->maxConcurrent = BASIC_MAX;
		if(ALLOW_ALL_TRIBE || $session->tribe == 1) {
			$this->maxConcurrent += INNER_MAX;
		}
		if($session->plus) {
			$this->maxConcurrent += PLUS_MAX;
		}
		$this->LoadBuilding();
		foreach($this->buildArray as $build) {
		if($build['master']==1){
		$this->maxConcurrent += 1;
		}
		}
	}
	
	public function procBuild($get) { 
		global $session;
		if(isset($get['a']) && $get['c'] == $session->checker && !isset($get['id'])) {
			if($get['a'] == 0) {
				$this->removeBuilding($get['d']);
			}
			else {
				$session->changeChecker();
				$this->upgradeBuilding($get['a']);
			}
		}
		if(isset($get['a']) && $get['c'] == $session->checker && isset($get['id'])) {
			$session->changeChecker();
			$this->constructBuilding($get['id'],$get['a']);
		}
		if(isset($get['cmd']) && $get['cmd'] === 'buildingFinish' && isset($get['c']) && $get['c'] === $session->checker) {
			$session->changeChecker();
			$this->finishAll();
		}
	}
	
	public function canBuild($id,$tid) {
		global $village,$session,$database;
		$id = (int)$id;
		$tid = (int)$tid;
		if(!$this->isTribeBuildingAllowed($tid)) {
			return 1;
		}
		$demolition = $database->getDemolition($village->wid);
		if(!empty($demolition) && (int)$demolition[0]['buildnumber'] === $id) { return 11; }
		if($this->isMax($tid,$id)) {
			return 1;
		} else if($this->isMax($tid,$id,1) && ($this->isLoop($id) || $this->isCurrent($id))) {
			return 10;
		} else if($this->isMax($tid,$id,2) && $this->isLoop($id) && $this->isCurrent($id)) {
			return 10;
		} else if($this->isMax($tid,$id,3) && $this->isLoop($id) && $this->isCurrent($id) && count($database->getMasterJobs($village->wid)) > 0) {
			return 10;
		}
		else {
			if($this->allocated <= $this->maxConcurrent) {
				// El nivel que se encolaría es el siguiente al último pedido para ese
				// campo, no siempre el actual + 1.
				$queuedHere = count($database->getBuildingByField($village->wid,$id));
				$resRequired = $this->resourceRequired($id,$tid,1 + $queuedHere);
				$resRequiredPop = $resRequired['pop'];
				if ($resRequiredPop === null || $resRequiredPop === "") {
					$buildarray = isset($GLOBALS["bid".$tid]) ? $GLOBALS["bid".$tid] : array();
					$resRequiredPop = isset($buildarray[1]['pop']) ? $buildarray[1]['pop'] : 0;
				}
				// Consumo que van a sumar las construcciones ya encoladas. Antes tomaba
				// siempre el nivel actual + 1, así que con dos mejoras encoladas en el
				// mismo campo contaba dos veces la primera y nunca la segunda; el nivel
				// al que apunta cada trabajo ya está guardado en la cola.
				$soonPop = 0;
				$jobs = $database->getJobs($village->wid);
				if(is_array($jobs)) {
					foreach ($jobs as $j) {
						$buildarray = isset($GLOBALS["bid".$j['type']]) ? $GLOBALS["bid".$j['type']] : array();
						$jobLevel = (int)$j['level'];
						if(isset($buildarray[$jobLevel]['pop'])) {
							$soonPop += $buildarray[$jobLevel]['pop'];
						}
					}
				}
				// No se encola nada que deje la aldea produciendo cereal en negativo:
				// con la hambruna activa eso se paga con tropas. Mejorar una plantación
				// de cereal siempre se permite, porque es justamente la salida.
				if(($village->getProd("crop") - $soonPop - $resRequiredPop) < 0 && $village->resarray['f'.$id.'t'] <> 4) {
					return 4;
				}
				else {
					switch($this->checkResource($tid,$id)) {
						case 1:
						return 5;
						break;
						case 2:
						return 6;
						break;
						case 3:
						return 7;
						break;
						case 4:
						if($id >= 19) {
							if($session->tribe == 1 || ALLOW_ALL_TRIBE) {
								if($this->inner == 0) {
									return 8;
								}
								else {
									if($session->plus) {
										if($this->plus == 0) {
											return 9;
										}
										else {
											return 3;
										}
									}
									else { 
										return 2;
									}
								}
							}
							else {
								if($this->basic == 0) {
									return 8;
								}
								else {
									if($session->plus) {
										if($this->plus == 0) {
											return 9;
										}
										else {
											return 3;
										}
									}
									else {
										return 2;
									}
								}
							}
						}
						else {
							if($this->basic == 1) {
								if($session->plus && $this->plus == 0) {
									return 9;
								}
								else {
									return 3;
								}
							}
							else {
								return 8;
							}
						}
						break;
					}
				}
			}
			else {
				return 2;
			}
		}
	}

	public function walling() {
		$wall = array(31,32,33);
		foreach($this->buildArray as $job) {
			if(in_array((int)$job['type'],$wall,true)) {
				return (int)$job['type'];
			}
		}
		return false;
	}
	
	public function rallying() {
		foreach($this->buildArray as $job) {
			if($job['type'] == 16) {
				return true;
			}
		}
		return false;
	}
	
	public function procResType($ref) {
		global $session;
		switch($ref) {
			case 1: $build = "Leñador"; break;
			case 2: $build = "Barrera"; break;
			case 3: $build = "Mina de hierro"; break;
			case 4: $build = "Granja"; break;
			case 5: $build = "Aserradero"; break;
			case 6: $build = "Fábrica de ladrillos"; break;
			case 7: $build = "Fundición de hierro"; break;
			case 8: $build = "Molino"; break;
			case 9: $build = "Panadería"; break;
			case 10: $build = "Almacén"; break;
			case 11: $build = "Granero"; break;
			case 12: $build = "Herrería"; break;
			case 14: $build = "Plaza de torneos"; break;
			case 15: $build = "Edificio principal"; break;
			case 16: $build = "Plaza de reuniones"; break;
			case 17: $build = "Mercado"; break;
			case 18: $build = "Embajada"; break;
			case 19: $build = "Cuartel"; break;
			case 20: $build = "Establo"; break;
			case 21: $build = "Taller"; break;
			case 22: $build = "Academia"; break;
			case 23: $build = "Escondite"; break;
			case 24: $build = "Ayuntamiento"; break;
			case 25: $build = "Residencia"; break;
			case 26: $build = "Palacio"; break;
			case 27: $build = "Tesorería"; break;
			case 28: $build = "Oficina de comercio"; break;
			case 29: $build = "Gran cuartel"; break;
			case 30: $build = "Gran establo"; break;
			case 31: $build = "Muralla"; break;
			case 32: $build = "Muro de tierra"; break;
			case 33: $build = "Empalizada"; break;
			case 34: $build = "Taller de cantería"; break;
			case 35: $build = "Cervecería"; break;
			case 36: $build = "Trampero"; break;
			case 37: $build = "Mansión del héroe"; break;
			case 38: $build = "Gran almacén"; break;
			case 39: $build = "Gran granero"; break;
			case 40: $build = "Maravilla del mundo"; break;
			case 41: $build = "Abrevadero"; break;
			case 42: $build = "Gran taller"; break;
			default: $build = "Error"; break;
		}
        /*
         * Don't think we need to add slashes here?
         * addslashes line left in but commented out for easy reversion if it breaks anything
         */
		//return addslashes($build);
        return $build;
	}
	
	private function loadBuilding() {
		global $database,$village,$session;
		$this->buildArray = $database->getJobs($village->wid);
		$this->allocated = count($this->buildArray);
		if($this->allocated > 0) {
			foreach($this->buildArray as $build) {
				if($build['loopcon'] == 1) {
					$this->plus = 1;
				}
				else {
					if($build['field'] <= 18) {
						$this->basic += 1;
					}
					else {
						if($session->tribe == 1 || ALLOW_ALL_TRIBE) {
							$this->inner += 1;
						}
						else {
							$this->basic += 1;
						}
					}
				}
			}
			$this->NewBuilding = true;
		}
	}
	
	private function removeBuilding($d) {
		global $database,$village;
		foreach($this->buildArray as $jobs) {
			if($jobs['id'] == $d) {
				// Se devuelve lo que costó el nivel que se cancela, no siempre el
				// siguiente al actual: cancelar el segundo trabajo encolado de un campo
				// reembolsaba de menos.
				$plus = max(1,(int)$jobs['level'] - (int)$village->resarray['f'.$jobs['field']]);
				$uprequire = $this->resourceRequired($jobs['field'],$jobs['type'],$plus);
				if($database->removeBuilding($d)) {
					if($jobs['master'] == 0){
					$database->modifyResource($village->wid,$uprequire['wood'],$uprequire['clay'],$uprequire['iron'],$uprequire['crop'],1);
					}
					if($jobs['field'] >= 19) {
						header("Location: dorf2.php");
					}
					else {
						header("Location: dorf1.php");
					}
				}
			}
		}
	}
	
	private function upgradeBuilding($id) {
		global $database,$village,$session,$logging;
		if($this->allocated < $this->maxConcurrent) {
			$bindicate = $this->canBuild($id,$village->resarray['f'.$id.'t']);
			if($bindicate != 8 && $bindicate != 9) {
				return;
			}
			$uprequire = $this->resourceRequired($id,$village->resarray['f'.$id.'t']);
			$time = time() + $uprequire['time'];
			$loop = ($bindicate == 9 ? 1 : 0);
			$loopsame = 0;
			if($loop == 1) {
				foreach($this->buildArray as $build) {
					if($build['field']==$id) {
						$loopsame += 1;
						$uprequire = $this->resourceRequired($id,$village->resarray['f'.$id.'t'],($loopsame>0?2:1));
					}
				}
				if($session->tribe == 1 || ALLOW_ALL_TRIBE) {
					if($id >= 19) {
						foreach($this->buildArray as $build) {
							if($build['field'] >= 19) {
								$time = $build['timestamp'] + $uprequire['time'];
							}
						}
					}
					else {
						foreach($this->buildArray as $build) {
							if($build['field'] <= 18) {
								$time = $build['timestamp'] + $uprequire['time'];
							}
						}
					}
				}
				else {
					$time = $this->buildArray[0]['timestamp'] + $uprequire['time'];
				}
			}
			if($session->access!=BANNED){
			$level = $database->getResourceLevel($village->wid);
			if($database->addBuildingWithResources($village->wid,$id,$village->resarray['f'.$id.'t'],$loop,$time+($loop==1?ceil(60/SPEED):0),0,$level['f'.$id] + 1 + count($database->getBuildingByField($village->wid,$id)),$uprequire['wood'],$uprequire['clay'],$uprequire['iron'],$uprequire['crop'])) {
				$logging->addBuildLog($village->wid,$this->procResType($village->resarray['f'.$id.'t']),($village->resarray['f'.$id]+($loopsame>0?2:1)),0);
				if($id >= 19) {
					header("Location: dorf2.php");
				}
				else {
					header("Location: dorf1.php");
				}
			}
			}else{
			header("Location: banned.php");
			}
			}
		}
	
		private function downgradeBuilding($id) {
		global $database,$village,$session,$logging;
		if($this->allocated < $this->maxConcurrent) {
			$name = "bid".$village->resarray['f'.$id.'t'];
			global $$name;
			$dataarray = $$name;
			$time = time() + round($dataarray[$village->resarray['f'.$id]-1]['time'] / 4);
			$loop = 0;
			if($this->inner == 1 || $this->basic == 1) {
				if($session->plus && $this->plus == 0) {
					$loop = 1;
				}
			}
			if($loop == 1) {
				if($session->tribe == 1 || ALLOW_ALL_TRIBE) {
					if($id >= 19) {
						foreach($this->buildArray as $build) {
							if($build['field'] >= 19) {
								$time = $build['timestamp'] + round($dataarray[$village->resarray['f'.$id]-1]['time'] / 4);
							}
						}
					}					
				}
				else {
					$time = $this->buildArray[0]['timestamp'] + round($dataarray[$village->resarray['f'.$id]-1]['time'] / 4);
				}
			}
			if($session->access!=BANNED){
			$level = $database->getResourceLevel($village->wid);
			if($database->addBuilding($village->wid,$id,$village->resarray['f'.$id.'t'],$loop,$time,0,0,$level['f'.$id] + 1 + count($database->getBuildingByField($village->wid,$id)))) {
				$logging->addBuildLog($village->wid,$this->procResType($village->resarray['f'.$id.'t']),($village->resarray['f'.$id]-1),2);
				header("Location: dorf2.php");
			}
			}else{
			header("Location: banned.php");
			}
		}	
	}
	
	
	
	private function constructBuilding($id,$tid) {
		global $database,$village,$session,$logging;
		if($this->allocated < $this->maxConcurrent) {
			$id = (int)$id;
			$tid = (int)$tid;
			if($tid == 16) {
				$id = 39;
			}
			else if($tid == 31 || $tid == 32 || $tid == 33) {
				$id = 40;
			}
			else if($id < 19 || $id > 38) {
				return;
			}
			if((int)$village->resarray['f'.$id.'t'] !== 0 || !$this->meetRequirement($tid)) {
				return;
			}
			foreach($this->buildArray as $queuedBuilding) {
				if((int)$queuedBuilding['field'] === $id) {
					return;
				}
			}
			$uprequire = $this->resourceRequired($id,$tid);
			$time = time() + $uprequire['time'];
			$bindicate = $this->canBuild($id,$tid);
			if($bindicate != 8 && $bindicate != 9) {
				return;
			}
			$loop = ($bindicate == 9 ? 1 : 0);
			if($loop == 1) {
				foreach($this->buildArray as $build) {
					if($build['field'] >= 19 || ($session->tribe <> 1 && !ALLOW_ALL_TRIBE)) {
						$time = $build['timestamp'] + 60 + $uprequire['time'];
					}
				}
			}
			if($session->access!=BANNED){
			$level = $database->getResourceLevel($village->wid);
				if($database->addBuildingWithResources($village->wid,$id,$tid,$loop,$time,0,$level['f'.$id] + 1 + count($database->getBuildingByField($village->wid,$id)),$uprequire['wood'],$uprequire['clay'],$uprequire['iron'],$uprequire['crop'])) {
					$logging->addBuildLog($village->wid,$this->procResType($tid),($village->resarray['f'.$id]+1),1);
					header("Location: dorf2.php");
				}
			}else{
			header("Location: banned.php");
			}
		}
	}
	
	private function meetRequirement($id) {
		global $village;
		$id = (int)$id;
		if(!$this->isTribeBuildingAllowed($id)) {
			return false;
		}
		if(!$this->isSingleBuildingAllowed($id)) {
			return false;
		}
		switch($id) {
			case 1:
			case 2:
			case 3:
			case 4:
			case 15:
			case 16:
			case 18:
			case 31:
			case 32:
			case 33:
			return true;
			break;
			case 23:
			return !$this->hasQueuedType(23)
				&& ($this->getTypeCount(23) == 0 || $this->getTypeLevel(23) >= 10);
			break;
			case 10:
			return $this->getTypeLevel(15) >= 1 && $this->canBuildAnotherOfType(10);
			break;
			case 11:
			return $this->getTypeLevel(15) >= 1 && $this->canBuildAnotherOfType(11);
			break;
			// La unicidad de todos estos la resuelve isSingleBuildingAllowed() antes
			// del switch; acá quedan sólo los requisitos propios de cada edificio.
			case 5:
			return $this->getTypeLevel(1) >= 10 && $this->getTypeLevel(15) >= 5;
			break;
			case 6:
			return $this->getTypeLevel(2) >= 10 && $this->getTypeLevel(15) >= 5;
			break;
			case 7:
			return $this->getTypeLevel(3) >= 10 && $this->getTypeLevel(15) >= 5;
			break;
			case 8:
			return $this->getTypeLevel(4) >= 5 && $this->getTypeLevel(15) >= 5;
			break;
			case 9:
			return $this->getTypeLevel(15) >= 5 && $this->getTypeLevel(4) >= 10 && $this->getTypeLevel(8) >= 5;
			break;
			case 12:
			if($this->getTypeLevel(22) >= 1 && $this->getTypeLevel(15) >= 3) { return true; } else { return false; }
			break;
			case 14:
			if($this->getTypeLevel(16) >= 15) { return true; } else { return false; }
			break;
			case 17:
			if($this->getTypeLevel(15) >= 3 && $this->getTypeLevel(10) >= 1 && $this->getTypeLevel(11) >= 1) { return true; } else { return false; }
			break;
			case 19:
			if($this->getTypeLevel(15) >= 3 && $this->getTypeLevel(16) >= 1) { return true; } else { return false; }
			break;
			case 20:
			if($this->getTypeLevel(12) >= 3 && $this->getTypeLevel(22) >= 5) { return true; } else { return false; }
			break;
			case 21:
			if($this->getTypeLevel(22) >= 10 && $this->getTypeLevel(15) >= 5) { return true; } else { return false; }
			break;
			case 22:
			if($this->getTypeLevel(15) >= 3 && $this->getTypeLevel(19) >= 3) { return true; } else { return false; }
			break;
			case 24:
			if($this->getTypeLevel(22) >= 10 && $this->getTypeLevel(15) >= 10) { return true; } else { return false; }
			break;
			case 25:
			// Residencia y palacio se excluyen entre sí, además de ser únicos: la
			// aldea no puede tener uno si ya tiene el otro, ni construido ni en cola.
			return $this->getTypeLevel(15) >= 5
				&& $this->getTypeCount(26) == 0 && !$this->hasQueuedType(26);
			break;
			case 26:
			return $this->getTypeLevel(18) >= 1 && $this->getTypeLevel(15) >= 5
				&& $this->getTypeCount(25) == 0 && !$this->hasQueuedType(25)
				&& !$this->hasPalaceInAnotherVillage();
			break;
			case 27:
			if($this->getTypeLevel(15) >= 10) { return true; } else { return false; }
			break;
			case 28:
			return $this->getTypeLevel(17) == 20 && $this->getTypeLevel(20) >= 10;
			break;
			case 29:
			if($this->getTypeLevel(19) == 20 && $village->capital == 0) { return true; } else { return false; }
			break;
			case 30:
			if($this->getTypeLevel(20) == 20 && $village->capital == 0) { return true; } else { return false; }
			break;
			case 34:
			if($village->capital == 1 && $this->getTypeLevel(26) >= 3 && $this->getTypeLevel(15) >= 5 && $this->getTypeLevel(25) == 0) { return true; } else { return false; }
			break;
			case 35:
			if($this->getTypeLevel(16) >= 10 && $this->getTypeLevel(11) == 20) { return true; } else { return false; }
			break;
			case 36:
			if(!$this->hasQueuedType(36) && $this->getTypeLevel(16) >= 1 && ($this->getTypeCount(36) == 0 || $this->getTypeLevel(36) == 20)) { return true; } else { return false; }
			break;
			case 37:
			if($this->getTypeLevel(15) >= 3 && $this->getTypeLevel(16) >= 1) { return true; } else { return false; }
			break;
            case 38:
            return $this->getTypeLevel(15) >= 10 && $village->capital == 0
                && $this->hasStorageArtefact() && $this->canBuildAnotherOfType(38);
            break;
            case 39:
            return $this->getTypeLevel(15) >= 10 && $village->capital == 0
                && $this->hasStorageArtefact() && $this->canBuildAnotherOfType(39);
            break;
			case 40:
			return false; //not implemented
			break;
			case 41:
			if($this->getTypeLevel(16) >= 10 && $this->getTypeLevel(20) == 20) { return true; } else { return false; }
			break;
			case 42:
			if($this->getTypeLevel(21) == 20 && $village->capital == 0) { return true; } else { return false; }
			break;
		}
	}

	private function isTribeBuildingAllowed($tid) {
		global $session,$village;
		$tribe = isset($session->tribe) ? (int)$session->tribe : 0;
		switch((int)$tid) {
			case 31:
				return in_array($tribe, array(1, 5), true);
			case 32:
				return in_array($tribe, array(2, 4), true);
			case 33:
				return $tribe === 3;
			case 35:
				return $tribe === 2 && (int)$village->capital === 1;
			case 36:
				return $tribe === 3;
			case 41:
				return $tribe === 1;
			default:
				return true;
		}
	}

	/**
	 * El gran almacén y el gran granero exigen un artefacto de almacenamiento
	 * (tipo 6): pequeño en esta misma aldea, o grande/único en la cuenta.
	 */
	public function hasStorageArtefact() {
		global $database,$session,$village;
		$inVillage = $database->getOwnArtefactInfoByType($village->wid,6);
		if(is_array($inVillage) && !empty($inVillage['vref'])
			&& (int)$inVillage['vref'] === (int)$village->wid) {
			return true;
		}
		foreach(array(2,3) as $size) {
			$account = $database->getOwnUniqueArtefactInfo($session->uid,6,$size);
			if(is_array($account) && !empty($account['owner'])
				&& (int)$account['owner'] === (int)$session->uid) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Los edificios que pueden repetirse (almacén, granero, gran almacén,
	 * gran granero) sólo admiten uno nuevo cuando todos los que ya existen
	 * llegaron al nivel máximo y no hay otro del mismo tipo en la cola.
	 */
	public function canBuildAnotherOfType($tid) {
		global $village;
		if($this->hasQueuedType($tid)) {
			return false;
		}
		$dataarray = isset($GLOBALS['bid'.$tid]) ? $GLOBALS['bid'.$tid] : array();
		$maxLevel = count($dataarray);
		for($field = 1; $field <= 40; $field++) {
			if(!isset($village->resarray['f'.$field.'t'])
				|| (int)$village->resarray['f'.$field.'t'] !== (int)$tid) {
				continue;
			}
			if((int)$village->resarray['f'.$field] < $maxLevel) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Solo se permite un palacio por cuenta.
	 */
	private function hasPalaceInAnotherVillage() {
		global $database,$session,$village;
		if(!method_exists($database,'hasPalace')) {
			return false;
		}
		return $database->hasPalace((int)$session->uid,(int)$village->wid);
	}

	/**
	 * Edificios que sólo admiten uno por aldea: ni construido ni en cola puede haber
	 * otro del mismo tipo. Es la misma regla que aplica la lista de construcciones
	 * (`avaliable.tpl` los ofrece sólo con nivel 0 y sin trabajo encolado); sin ella
	 * del lado del servidor, una petición a mano levantaba un segundo edificio que no
	 * suma nada —getTypeLevel() se queda con el nivel más alto— y sólo gasta recursos,
	 * población y un solar.
	 *
	 * Quedan fuera a propósito los que sí se repiten, cada uno con su propia regla:
	 * almacén (10), granero (11), escondite (23), trampero (36), gran almacén (38) y
	 * gran granero (39); y los que van a un solar fijo, plaza de reuniones (16) y
	 * muralla (31/32/33), donde constructBuilding ya rechaza el solar ocupado.
	 */
	private static $singlePerVillage = array(
		5,6,7,8,9,12,14,15,17,18,19,20,21,22,24,25,26,27,28,29,30,34,35,37,41,42
	);

	private function isSingleBuildingAllowed($tid) {
		$tid = (int)$tid;
		if(!in_array($tid,self::$singlePerVillage,true)) {
			return true;
		}
		return $this->getTypeCount($tid) == 0 && !$this->hasQueuedType($tid);
	}

	private function hasQueuedType($tid) {
		foreach($this->buildArray as $queuedBuilding) {
			if((int)$queuedBuilding['type'] === (int)$tid) {
				return true;
			}
		}
		return false;
	}
	
	private function checkResource($tid,$id,$plus=null) {
		$name = "bid".$tid;
		global $village,$$name,$database;
		if($plus === null) {
			$plus = 1;
			foreach($this->buildArray as $job) {
				if($job['type'] == $tid && $job['field'] == $id) {
					$plus = 2;
				}
			}
		}
		$dataarray = $$name;
		$target = $village->resarray['f'.$id]+$plus;
		// Un nivel que no existe en la tabla (el siguiente al máximo, con la mejora
		// anterior todavía en obra) no tiene costo: sin esto el edificio se anunciaba
		// como mejorable y con los costos en blanco.
		if(!isset($dataarray[$target])) {
			return 0;
		}
		$wood = $dataarray[$target]['wood'];
		$clay = $dataarray[$target]['clay'];
		$iron = $dataarray[$target]['iron'];
		$crop = $dataarray[$target]['crop'];
		if($wood > $village->maxstore || $clay > $village->maxstore || $iron > $village->maxstore) {
			return 1;
		}
		else {
			if($crop > $village->maxcrop) {
				return 2;
			}
			else {
				if($wood > $village->awood || $clay > $village->aclay || $iron > $village->airon || $crop > $village->acrop) {
					return 3;
				}
				else {
					if($village->awood-$wood >= 0 && $village->aclay-$clay >= 0 && $village->airon-$iron >= 0 && $village->acrop-$crop >= 0){
						return 4;
					}
					else {
						return 3;
					}
				}
			}
		}
	}
	
	/**
	 * Cuántos niveles por encima del actual ya están pagos y en la cola de este campo.
	 * El badge y el tooltip del mapa hablan de la próxima mejora que todavía se puede
	 * pedir, así que arrancan a contar desde ahí y no desde el nivel construido.
	 */
	private function queuedLevelOffset($field) {
		global $village;
		$current = (int)$village->resarray['f'.$field];
		$queued = $this->constructionTargetLevel($field);
		return ($queued !== false && $queued > $current) ? $queued - $current : 0;
	}

	public function badgeUpgradeState($id,$tid) {
		if(!$tid) {
			return "cannotUpgrade";
		}
		$offset = $this->queuedLevelOffset($id);
		if($this->isMax($tid,$id,$offset)) {
			return "maxLevel";
		}
		return ($this->checkResource($tid,$id,$offset+1) == 4) ? "canUpgrade" : "cannotUpgrade";
	}

	/**
	 * Builds the HTML tooltip used by the village maps for an existing building.
	 */
	public function upgradeTooltip($field,$tid) {
		global $village;
		$field = (int)$field;
		$tid = (int)$tid;
		$name = $this->procResType($tid);
		$title = "<div style=color:#FFF><b>".$name."</b></div>";

		$offset = $this->queuedLevelOffset($field);
		$nextLevel = (int)$village->resarray['f'.$field] + $offset + 1;
		if($this->isMax($tid,$field,$offset) || !isset($GLOBALS['bid'.$tid][$nextLevel])) {
			return $title."Nivel máximo";
		}

		$required = $this->resourceRequired($field,$tid,$offset+1);
		return $title."Costo para nivel ".$nextLevel."<br>"
			."<img class='r1' src='img/x.gif' alt='Madera'> ".number_format($required['wood'],0,',','.')
			." &nbsp;<img class='r2' src='img/x.gif' alt='Barro'> ".number_format($required['clay'],0,',','.')
			." &nbsp;<img class='r3' src='img/x.gif' alt='Hierro'> ".number_format($required['iron'],0,',','.')
			." &nbsp;<img class='r4' src='img/x.gif' alt='Cereal'> ".number_format($required['crop'],0,',','.');
	}

	/**
	 * Returns the final target level among active and queued jobs for a field.
	 */
	public function constructionTargetLevel($field) {
		$targetLevel = false;
		foreach($this->buildArray as $job) {
			if((int)$job['field'] !== (int)$field) {
				continue;
			}
			$jobLevel = (int)$job['level'];
			if($targetLevel === false || $jobLevel > $targetLevel) {
				$targetLevel = $jobLevel;
			}
		}
		return $targetLevel;
	}

	public function isMax($id,$field,$loop=0) {
		$name = "bid".$id;
		global $$name,$village;
		$dataarray = $$name;
		if(!is_array($dataarray)) {
			return false;
		}	
		else if($id <= 4) {
			if($village->capital == 1) {
				return ($village->resarray['f'.$field] == (count($dataarray) - 1 - $loop));
			}
			else {
				return ($village->resarray['f'.$field] == (count($dataarray) - 11 - $loop));
			}
		}
		else {
			return ($village->resarray['f'.$field] == count($dataarray) - $loop);
		}
	}
	
	public function getTypeLevel($tid,$vid=0) {
		global $village,$database;
		$keyholder = array();
		if($vid == 0) {
			$resourcearray = $village->resarray;
		} else {
			$resourcearray = $database->getResourceLevel($vid);
		}
		// Los informes se conservan aunque una aldea haya sido borrada. En ese
		// caso getResourceLevel() no devuelve una fila: no hay edificios que
		// consultar y, en particular, tampoco un Abrevadero que aplicar.
		if(!is_array($resourcearray)) {
			return 0;
		}
		foreach(array_keys($resourcearray,$tid) as $key) {
			if(strpos($key,'t')) {
				$key = preg_replace("/[^0-9]/", '', $key);
				array_push($keyholder, $key);
			}	 
		}
		$element = count($keyholder);
		if($element >= 2) {
			if($tid <= 4) {
				$temparray = array();
				for($i=0;$i<=$element-1;$i++) {
					array_push($temparray,$resourcearray['f'.$keyholder[$i]]);
				}
				foreach ($temparray as $key => $val) {
					if ($val == max($temparray)) 
					$target = $key; 
				}
			}
			else {
				$target = 0;
				for($i=1;$i<=$element-1;$i++) {
					if($resourcearray['f'.$keyholder[$i]] > $resourcearray['f'.$keyholder[$target]]) {
						$target = $i;
					}
				}
			}
		}
		else if($element == 1) {
			$target = 0;
		}
		else {
			return 0;
		}
		if($keyholder[$target] != "") {
			return $resourcearray['f'.$keyholder[$target]];
		}
		else {
			return 0;
		}
	}

	public function getTypeCount($tid,$vid=0) {
		global $village,$database;
		$resourcearray = $vid == 0
			? $village->resarray
			: $database->getResourceLevel($vid);
		if(!is_array($resourcearray)) {
			return 0;
		}
		$count = 0;
		for($field = 1; $field <= 40; $field++) {
			if(isset($resourcearray['f'.$field.'t']) && (int)$resourcearray['f'.$field.'t'] === (int)$tid) {
				$count++;
			}
		}
		return $count;
	}
	
	
	public function isCurrent($id) {
		foreach($this->buildArray as $build) {
			if($build['field'] == $id && $build['loopcon'] <> 1) {
				return true;
			}
		}
	}
	
	public function isLoop($id=0) {
		foreach($this->buildArray as $build) {
			if(($build['field'] == $id && $build['loopcon']) || ($build['loopcon'] == 1 && $id == 0)) {
				return true;
			}
		}
		return false;
	}

	public function canFinishAll() {
		global $database,$village;

		$resourceLevel = $database->getResourceLevel($village->wid);
		if($resourceLevel['f99t'] == 40) {
			return false;
		}

		foreach($this->buildArray as $job) {
			if($job['wid'] == $village->wid && $job['master'] == 0 && !in_array((int)$job['type'], array(25,26,40), true)) {
				return true;
			}
		}

		$demolition = $database->getDemolition($village->wid);
		return !empty($demolition);
	}
	
	/**
	 * Termina todas las construcciones en curso a cambio de 2 de oro.
	 *
	 * El fin de obra en sí lo resuelve Automation, el mismo camino que usan las
	 * construcciones que terminan solas: acá sólo se elige qué trabajos entran
	 * (residencia, palacio y maravilla del mundo nunca), se cobra el oro y se
	 * reordena lo que haya quedado en la cola.
	 */
	public function finishAll() {
		global $database,$session,$logging,$village,$automation;
		if($session->access==BANNED) {
			header("Location: banned.php");
			return;
		}
		if($database->getUserField($session->uid, 'gold', 0) < 2 || !$this->canFinishAll()) {
			header("Location: ".$session->referrer);
			return;
		}
		$jobIds = array();
		foreach($this->buildArray as $jobs) {
			if((int)$jobs['wid'] !== (int)$village->wid || (int)$jobs['master'] !== 0) {
				continue;
			}
			if(in_array((int)$jobs['type'], array(25,26,40), true)) {
				continue;
			}
			$jobIds[] = (int)$jobs['id'];
		}
		$finished = false;
		if(!empty($jobIds) && is_object($automation) && method_exists($automation,'finishBuildingsNow')) {
			$finished = $automation->finishBuildingsNow($village->wid, $jobIds) > 0;
		}
		$demolition = $database->getDemolition($village->wid);
		if(!empty($demolition)) {
			$database->finishDemolition($village->wid);
			$finished = true;
		}
		if($finished) {
			$logging->goldFinLog($village->wid);
			$database->modifyGold($session->uid,2,0);
			// Lo que no se puede terminar (una residencia, un palacio) queda en la cola:
			// se reordena para que arranque ya y no herede el reloj de lo que terminó.
			if(method_exists($database,'resequenceBuildingQueue')) {
				$database->resequenceBuildingQueue($village->wid,1);
				$database->resequenceBuildingQueue($village->wid,19);
			}
		}
		if(isset($_GET['id'])) {
			header("Location: ".$session->referrer . "?id=" . $_GET['id']);
		}
		else {
			header("Location: ".$session->referrer);
		}
	}
	
	public function resourceRequired($id,$tid,$plus=1) {
		$name = "bid".$tid;
		global $$name,$village,$bid15;
		$dataarray = $$name;
		$wood = $dataarray[$village->resarray['f'.$id]+$plus]['wood'];
		$clay = $dataarray[$village->resarray['f'.$id]+$plus]['clay'];
		$iron = $dataarray[$village->resarray['f'.$id]+$plus]['iron'];
		$crop = $dataarray[$village->resarray['f'.$id]+$plus]['crop'];
		$pop = $dataarray[$village->resarray['f'.$id]+$plus]['pop'];
		if ($tid == 15) {
			if($this->getTypeLevel(15) == 0) {
				$time = round($dataarray[$village->resarray['f'.$id]+$plus]['time']/ SPEED *5);
			}
			else {
				$time = round($dataarray[$village->resarray['f'.$id]+$plus]['time'] / SPEED);
			}
		}
		else {
			if($this->getTypeLevel(15) != 0) {
				$time = round($dataarray[$village->resarray['f'.$id]+$plus]['time'] * ($bid15[$this->getTypeLevel(15)]['attri']/100)  / SPEED);
			}
			else {
				$time = round($dataarray[$village->resarray['f'.$id]+$plus]['time']*5 / SPEED);
			}
		}
		$cp = $dataarray[$village->resarray['f'.$id]+$plus]['cp'];
		return array("wood"=>$wood,"clay"=>$clay,"iron"=>$iron,"crop"=>$crop,"pop"=>$pop,"time"=>$time,"cp"=>$cp);
	}
	
	/**
	 * Valida un pedido al constructor maestro y devuelve el nivel y la duración
	 * que corresponden, o false si el pedido no es legal.
	 *
	 * dorf1.php/dorf2.php encolaban tal cual el tipo, el campo, el nivel y hasta la
	 * duración que venían en la URL. Con eso se podía encolar un segundo aserradero,
	 * un aserradero de nivel 6 (que no existe: sin costo, sin tiempo y con bono 0) o
	 * cualquier edificio en cualquier campo. El nivel y el tiempo se recalculan acá.
	 */
	public function masterBuildingRequest($field,$type) {
		global $database,$village;
		$field = (int)$field;
		$type = (int)$type;
		if($field < 1 || $field > 40) {
			return false;
		}
		$dataarray = isset($GLOBALS['bid'.$type]) ? $GLOBALS['bid'.$type] : null;
		if(!is_array($dataarray) || empty($dataarray)) {
			return false;
		}
		$demolition = $database->getDemolition($village->wid);
		if(!empty($demolition) && (int)$demolition[0]['buildnumber'] === $field) {
			return false;
		}
		foreach($this->buildArray as $job) {
			if((int)$job['field'] === $field && (int)$job['master'] === 1) {
				return false;
			}
		}
		$currentType = (int)$village->resarray['f'.$field.'t'];
		$currentLevel = (int)$village->resarray['f'.$field];
		if($currentType !== 0) {
			// Mejora: el tipo tiene que ser el que ya está en el campo.
			if($currentType !== $type) {
				return false;
			}
		}
		else {
			// Construcción nueva: campo válido para el tipo y requisitos cumplidos.
			if($type == 16) {
				if($field != 39) { return false; }
			}
			else if($type == 31 || $type == 32 || $type == 33) {
				if($field != 40) { return false; }
			}
			else if($field < 19 || $field > 38) {
				return false;
			}
			if(!$this->meetRequirement($type)) {
				return false;
			}
			foreach($this->buildArray as $job) {
				if((int)$job['field'] === $field) {
					return false;
				}
			}
		}
		$queued = count($database->getBuildingByField($village->wid,$field));
		$level = $currentLevel + 1 + $queued;
		$maxLevel = $this->masterMaxLevel($type,$dataarray);
		if($level < 1 || $level > $maxLevel || !isset($dataarray[$level])) {
			return false;
		}
		$uprequire = $this->resourceRequired($field,$type,1 + $queued);
		return array('level'=>$level,'time'=>max(1,(int)$uprequire['time']));
	}

	/**
	 * Nivel máximo de un tipo de edificio en esta aldea, con la misma regla que
	 * isMax(): los campos de recursos llegan a 20 sólo en la capital.
	 */
	private function masterMaxLevel($type,$dataarray) {
		global $village;
		if($type <= 4) {
			return (int)$village->capital === 1 ? count($dataarray) - 1 : count($dataarray) - 11;
		}
		return count($dataarray);
	}

	public function getTypeField($type) {
		global $village;
		for($i=19;$i<=40;$i++) {
			if($village->resarray['f'.$i.'t'] == $type) {
				return $i;
			}
		}
	}
	
	public function calculateAvaliable($id,$tid,$plus=1) {
		global $village,$generator;
		$uprequire = $this->resourceRequired($id,$tid,$plus);
		$rwood = $uprequire['wood']-$village->awood;
		$rclay = $uprequire['clay']-$village->aclay;
		$rcrop = $uprequire['crop']-$village->acrop;
		$riron = $uprequire['iron']-$village->airon;
		$rwtime = $rwood / $village->getProd("wood") * 3600;
		$rcltime = $rclay / $village->getProd("clay")* 3600;
		$rctime = $rcrop / $village->getProd("crop")* 3600;
		$ritime = $riron / $village->getProd("iron")* 3600;
		$reqtime = max($rwtime,$rctime,$rcltime,$ritime);
		$reqtime += time();
		return $generator->procMtime($reqtime);
	}
};

?>
