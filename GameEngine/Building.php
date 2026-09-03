<?php

require_once __DIR__.'/Catapult.php';

class Building {	

	/**
	 * El oficial cobra el fin de obra y el derribo completo por separado: 2 de oro
	 * terminan todo lo que la aldea tenga en curso (incluida la demolición que esté
	 * corriendo) y 5 de oro tiran un edificio entero al instante.
	 */
	const FINISH_ALL_GOLD = 2;
	const DEMOLISH_ALL_GOLD = 5;

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
				// El candado de alimentos del T4 oficial, con sus excepciones.
				if(!$this->passesFoodGuard($id,$tid,$queuedHere,$resRequiredPop)) {
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

	/**
	 * Candado de alimentos del T4 oficial.
	 *
	 * La regla: no se encola nada que deje el CEREAL LIBRE por debajo de 1. Cereal
	 * libre = producción base (campos + molino/panadería + oasis, sin oro ni héroe)
	 * menos habitantes; las tropas NO cuentan (ver villageFreeCrop). Antes acá se
	 * usaba el balance con tropas, así que un ejército grande bloqueaba toda la
	 * construcción de la aldea; el original no hace eso, allí el castigo por tener
	 * más tropas de las que se pueden alimentar es la hambruna y nada más.
	 *
	 * Las excepciones no son adorno: sin ellas una aldea puede quedar sin ninguna
	 * jugada legal. Son las cinco del oficial:
	 *   - plantación de cereal: siempre, es la salida del problema;
	 *   - molino y panadería: si la mejora SUBE el cereal libre;
	 *   - edificio principal nivel 1: siempre (sin él no se puede ni demoler);
	 *   - edificio principal, almacén y granero hasta nivel 10, si la producción
	 *     base llega a freeCropUnlockThreshold();
	 *   - maravilla del mundo: siempre.
	 */
	public function passesFoodGuard($field,$type,$queuedHere = 0,$newPop = 0) {
		global $village;
		$field = (int)$field;
		$type = (int)$type;
		$queuedHere = max(0,(int)$queuedHere);
		$currentLevel = isset($village->resarray['f'.$field]) ? (int)$village->resarray['f'.$field] : 0;
		$targetLevel = $currentLevel + 1 + $queuedHere;

		if($type === 4 || $type === 40) {
			return true;
		}
		if($type === 15 && $targetLevel === 1) {
			return true;
		}
		if(($type === 8 || $type === 9)
			&& $this->raisesFreeCrop($field,$type,$currentLevel + $queuedHere,$targetLevel)) {
			return true;
		}
		if(in_array($type,array(10,11,15),true) && $targetLevel <= 10
			&& $village->getBaseCropProduction() >= freeCropUnlockThreshold(SPEED)) {
			return true;
		}
		return ($village->getFreeCrop() - $this->queuedPop() - (int)$newPop) >= 1;
	}

	/**
	 * El oficial tampoco deja demoler un molino o una panadería si eso dejaría el
	 * cereal libre por debajo de 1: sería saltarse el candado por el otro lado y
	 * quedar sin salida. Cualquier otro edificio se puede demoler siempre, porque
	 * bajar habitantes es justamente cómo se sale de un bloqueo.
	 *
	 * $targetLevel es el nivel con el que queda la casilla: uno menos en la demolición
	 * común y 0 en el derribo completo con oro, que se lleva todos los niveles juntos.
	 * El candado se mide contra ese nivel, no contra el actual.
	 */
	public function demolitionAllowed($field, $targetLevel = null) {
		global $village;
		$field = (int)$field;
		$type = isset($village->resarray['f'.$field.'t']) ? (int)$village->resarray['f'.$field.'t'] : 0;
		if($type !== 8 && $type !== 9) {
			return true;
		}
		$level = isset($village->resarray['f'.$field]) ? (int)$village->resarray['f'.$field] : 0;
		$targetLevel = $targetLevel === null ? $level - 1 : max(0,(int)$targetLevel);
		$dataarray = isset($GLOBALS['bid'.$type]) ? $GLOBALS['bid'.$type] : array();
		$popBack = 0;
		for($lost = $level; $lost > $targetLevel; $lost--) {
			$popBack += isset($dataarray[$lost]['pop']) ? (int)$dataarray[$lost]['pop'] : 0;
		}
		$population = max(0,(int)$village->pop - $popBack);
		return ($this->baseCropWithField($field,$type,$targetLevel) - $population) >= 1;
	}

	/**
	 * Consumo que van a sumar los trabajos ya encolados. Cuenta el nivel al que
	 * apunta cada trabajo: con dos mejoras encoladas en el mismo campo, tomar
	 * siempre "nivel actual + 1" contaba dos veces la primera y nunca la segunda.
	 */
	private function queuedPop() {
		global $database,$village;
		$soonPop = 0;
		$jobs = $database->getJobs($village->wid);
		if(is_array($jobs)) {
			foreach($jobs as $job) {
				$dataarray = isset($GLOBALS['bid'.$job['type']]) ? $GLOBALS['bid'.$job['type']] : array();
				$jobLevel = (int)$job['level'];
				if(isset($dataarray[$jobLevel]['pop'])) {
					$soonPop += $dataarray[$jobLevel]['pop'];
				}
			}
		}
		return $soonPop;
	}

	/**
	 * Devuelve true si subir el molino o la panadería deja MÁS cereal libre del que
	 * hay. Suben la producción y cuestan habitantes, así que hay niveles en los que
	 * la cuenta da negativa; el oficial sólo deja pasar los que dan positiva.
	 */
	private function raisesFreeCrop($field,$type,$fromLevel,$toLevel) {
		$dataarray = isset($GLOBALS['bid'.(int)$type]) ? $GLOBALS['bid'.(int)$type] : array();
		if(!isset($dataarray[(int)$toLevel])) {
			return false;
		}
		$popCost = isset($dataarray[(int)$toLevel]['pop']) ? (int)$dataarray[(int)$toLevel]['pop'] : 0;
		$before = $this->baseCropWithField($field,$type,$fromLevel);
		$after = $this->baseCropWithField($field,$type,$toLevel);
		return ($after - $before - $popCost) > 0;
	}

	/**
	 * Producción base de cereal de la aldea suponiendo que un campo tuviera otro
	 * nivel. Se copia el mapa de campos y se pasa por la fórmula única del juego,
	 * para no tener acá una segunda cuenta del bono del molino que se desfase.
	 */
	private function baseCropWithField($field,$type,$level) {
		global $village;
		$level = (int)$level;
		$resarray = $village->resarray;
		$resarray['f'.(int)$field] = max(0,$level);
		$resarray['f'.(int)$field.'t'] = $level > 0 ? (int)$type : 0;
		return villageBaseCropProduction($resarray,$village->getOasisCounter(),SPEED);
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
	
	/** Nombre de un edificio. Lista única en Catapult.php (ver buildingDisplayName). */
	public function procResType($ref) {
		return buildingDisplayName($ref);
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
	
	/**
	 * Los requisitos de nivel salen de `buildingLevelRequirements()` (Catapult.php), que es
	 * la única tabla: acá quedan sólo las condiciones que no son "edificio X en nivel N".
	 *
	 * Es público porque la lista de construcciones disponibles es la misma pregunta hecha
	 * desde la plantilla, y cuando cada lado la contestaba por su cuenta se desincronizaban.
	 */
	public function meetRequirement($id) {
		global $village;
		$id = (int)$id;
		$requirements = buildingLevelRequirements($id);
		// Un gid que no existe no se construye (el 13 no es ningún edificio).
		if($requirements === null) {
			return false;
		}
		if(!$this->isTribeBuildingAllowed($id)) {
			return false;
		}
		if(!$this->isSingleBuildingAllowed($id)) {
			return false;
		}
		if(!$this->meetsLevelRequirements($id)) {
			return false;
		}
		switch($id) {
			// Almacén, granero, gran almacén y gran granero admiten otro sólo cuando los
			// que ya están llegaron al máximo.
			case 10:
			case 11:
			return $this->canBuildAnotherOfType($id);
			break;
			case 23:
			return !$this->hasQueuedType(23)
				&& ($this->getTypeCount(23) == 0 || $this->getTypeLevel(23) >= 10);
			break;
			// Residencia y palacio se excluyen entre sí, además de ser únicos: la
			// aldea no puede tener uno si ya tiene el otro, ni construido ni en cola.
			case 25:
			return $this->getTypeCount(26) == 0 && !$this->hasQueuedType(26);
			break;
			case 26:
			return $this->getTypeCount(25) == 0 && !$this->hasQueuedType(25)
				&& !$this->hasPalaceInAnotherVillage();
			break;
			case 29:
			case 30:
			case 42:
			return (int)$village->capital === 0;
			break;
			case 34:
			return (int)$village->capital === 1 && $this->getTypeLevel(25) == 0;
			break;
			case 36:
			return !$this->hasQueuedType(36)
				&& ($this->getTypeCount(36) == 0 || $this->getTypeLevel(36) == 20);
			break;
			case 38:
			case 39:
			return (int)$village->capital === 0
				&& $this->hasStorageArtefact() && $this->canBuildAnotherOfType($id);
			break;
			case 40:
			return false; //not implemented
			break;
		}
		return true;
	}

	/** ¿Están todos los edificios que este pide, en el nivel que pide? */
	public function meetsLevelRequirements($tid) {
		$requirements = buildingLevelRequirements($tid);
		if($requirements === null) {
			return false;
		}
		foreach($requirements as $required => $level) {
			if($this->getTypeLevel($required) < $level) {
				return false;
			}
		}
		return true;
	}

	/**
	 * El bloque "Necesario:" de la lista de construcciones, armado con la misma tabla que
	 * decide si se puede construir. Lo que no se cumple sale en rojo, como en el oficial:
	 * la ficha no decía cuál de los requisitos era el que faltaba.
	 */
	public function requirementsHtml($tid) {
		$tid = (int)$tid;
		$requirements = buildingLevelRequirements($tid);
		if($requirements === null) {
			return '';
		}
		$parts = array();
		foreach($requirements as $required => $level) {
			$met = $this->getTypeLevel($required) >= $level;
			$style = $met ? '' : ' style="color:#a10000"';
			$parts[] = '<span class="buildingCondition"'.$style.'>'
				.'<a href="#" onclick="return Travian.Game.iPopup('.(int)$required.',4, \'gid\');"'.$style.'>'
				.buildingDisplayName($required).'</a> <span>Nivel '.(int)$level.'</span></span>';
		}
		foreach($this->requirementExtras($tid) as $extra) {
			$style = $extra[1] ? '' : ' style="color:#a10000"';
			$parts[] = '<span class="buildingCondition"'.$style.'>'.$extra[0].'</span>';
		}
		return implode(', ', $parts);
	}

	/**
	 * Los requisitos que no son un nivel de edificio, en el orden en que los muestra la
	 * ficha: cada uno es array(texto, cumplido).
	 */
	private function requirementExtras($tid) {
		global $village;
		$extras = array();
		switch((int)$tid) {
			case 25:
			$extras[] = array('<strike>'.buildingDisplayName(26).'</strike>',$this->getTypeCount(26) == 0);
			break;
			case 26:
			$extras[] = array('<strike>'.buildingDisplayName(25).'</strike>',$this->getTypeCount(25) == 0);
			break;
			case 29:
			case 30:
			case 42:
			$extras[] = array('Aldea que no sea la capital',(int)$village->capital === 0);
			break;
			case 34:
			$extras[] = array('Capital',(int)$village->capital === 1);
			$extras[] = array('<strike>'.buildingDisplayName(25).'</strike>',$this->getTypeLevel(25) == 0);
			break;
			case 35:
			$extras[] = array('Capital',(int)$village->capital === 1);
			break;
			case 38:
			case 39:
			$extras[] = array('Aldea que no sea la capital',(int)$village->capital === 0);
			$extras[] = array('Plano de almacenamiento',$this->hasStorageArtefact());
			break;
		}
		return $extras;
	}

	private function isTribeBuildingAllowed($tid) {
		global $session,$village;
		$tribe = isset($session->tribe) ? (int)$session->tribe : 0;
		// Qué edificio es de qué tribu vive en Catapult.php (`buildingTribeLock`), porque
		// la conquista necesita la misma lista para decidir cuáles se caen al cambiar de
		// tribu la aldea. Acá sólo queda lo que además depende de la aldea.
		if(!tribeCanBuild($tid, $tribe)) {
			return false;
		}
		// La Cervecería, además de germana, es de la capital.
		if((int)$tid === 35) {
			return (int)$village->capital === 1;
		}
		return true;
	}

	/**
	 * El gran almacén y el gran granero exigen el plano de almacenamiento: pequeño en esta
	 * misma aldea, o grande en la cuenta.
	 *
	 * Pasa por el conjunto activo como cualquier otro artefacto, así que un plano recién
	 * capturado no habilita nada hasta que pasa el retardo, y un plano desplazado del
	 * podio de tres activos deja de habilitar. Es lo oficial ("mientras poseas ese
	 * artefacto puedes construir y ampliar esos edificios") y el motivo por el que el
	 * plano no puede tener su propia consulta: era el único artefacto que hacía efecto
	 * y lo hacía sin mirar ninguna de las dos reglas.
	 */
	public function hasStorageArtefact() {
		global $database,$session,$village;
		if(!is_object($database) || !method_exists($database,'hasActiveArtefactEffect')) {
			return false;
		}
		return $database->hasActiveArtefactEffect((int)$village->wid,(int)$session->uid,ARTEFACT_STORAGE);
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

	/**
	 * Nivel que contrataría ahora el enlace de mejora de un campo/edificio.
	 * Si ya hay niveles en obra o pedidos al maestro constructor, la ficha debe
	 * anunciar tanto el costo como el rendimiento de ese mismo nivel futuro.
	 */
	public function nextUpgradeLevel($id) {
		global $database,$village;
		$currentQueued = $this->isCurrent($id) ? 1 : 0;
		$loopQueued = $currentQueued && $this->isLoop($id) ? 1 : 0;
		$masterQueued = count($database->getMasterJobsByField($village->wid,$id));

		return (int)$village->resarray['f'.$id] + 1 + $currentQueued + $loopQueued + $masterQueued;
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

		return $this->demolitionInProgress() !== null;
	}

	/** La demolición que la aldea tiene en curso, o null si no hay ninguna. */
	public function demolitionInProgress() {
		global $database,$village;
		$demolition = $database->getDemolition($village->wid);
		return empty($demolition) ? null : $demolition[0];
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
		global $session;
		if($session->access==BANNED) {
			header("Location: banned.php");
			return;
		}
		$this->finishAllNow();
		if(isset($_GET['id'])) {
			header("Location: ".$session->referrer . "?id=" . $_GET['id']);
		}
		else {
			header("Location: ".$session->referrer);
		}
	}

	/**
	 * El fin de obra con oro sin redirecciones: devuelve true si se cobró.
	 *
	 * Lo usa el enlace "Finalizar todo" de dorf1/dorf2 y también el Edificio
	 * Principal, donde una demolición puede ser lo único en curso. Es el mismo
	 * cobro y el mismo camino: una demolición apurada con oro sigue bajando un solo
	 * nivel, como en el oficial.
	 */
	public function finishAllNow() {
		global $database,$session,$logging,$village,$automation;
		if($session->access==BANNED) {
			return false;
		}
		if($database->getUserField($session->uid, 'gold', 0) < self::FINISH_ALL_GOLD || !$this->canFinishAll()) {
			return false;
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
		if($this->demolitionInProgress() !== null) {
			$database->finishDemolition($village->wid);
			$finished = true;
		}
		if($finished) {
			$logging->goldFinLog($village->wid);
			$database->modifyGold($session->uid,self::FINISH_ALL_GOLD,0);
			// Lo que no se puede terminar (una residencia, un palacio) queda en la cola:
			// se reordena para que arranque ya y no herede el reloj de lo que terminó.
			if(method_exists($database,'resequenceBuildingQueue')) {
				$database->resequenceBuildingQueue($village->wid,1);
				$database->resequenceBuildingQueue($village->wid,19);
			}
		}
		return $finished;
	}

	/**
	 * Requisitos del derribo completo con oro. Son los mismos que los de la demolición
	 * común (Edificio Principal en DEMOLISH_LEVEL_REQ, casilla interior ocupada y sin
	 * obras encoladas encima) con dos diferencias: el candado de alimentos del molino y
	 * la panadería se mide contra el nivel 0, porque cae el edificio entero, y una
	 * Aldea de la Maravilla no compra atajos con oro, igual que en el fin de obra.
	 */
	public function canDemolishInstantly($field) {
		global $database,$village;
		$field = (int)$field;
		if($field < 19 || $field > 40) {
			return false;
		}
		$type = isset($village->resarray['f'.$field.'t']) ? (int)$village->resarray['f'.$field.'t'] : 0;
		$level = isset($village->resarray['f'.$field]) ? (int)$village->resarray['f'.$field] : 0;
		if($type <= 0 || $level <= 0) {
			return false;
		}
		if($this->getTypeLevel(15) < DEMOLISH_LEVEL_REQ) {
			return false;
		}
		if(isset($village->resarray['f99t']) && (int)$village->resarray['f99t'] === 40) {
			return false;
		}
		if(!empty($database->getBuildingByField($village->wid,$field))
			|| !empty($database->getMasterJobsByField($village->wid,$field))) {
			return false;
		}
		return $this->demolitionAllowed($field,0);
	}

	/**
	 * Derriba un edificio entero al instante por 5 de oro (la tercera forma de demoler
	 * del oficial). El derribo lo hace Automation nivel por nivel, el mismo paso que
	 * usa el reloj del Edificio Principal, así que la población, los puntos de cultura,
	 * la capacidad de los almacenes y la producción acreditada quedan como corresponde.
	 *
	 * Devuelve 'ok', o el motivo del rechazo. El oro se cobra sólo si cayó algún nivel:
	 * dos clics seguidos dejan el segundo sin nada que demoler y sin cobro.
	 */
	public function demolishInstantly($field) {
		global $database,$session,$village,$automation,$logging;
		$field = (int)$field;
		if($session->access==BANNED) {
			return 'banned';
		}
		if(!$this->canDemolishInstantly($field)) {
			return 'denied';
		}
		if($database->getUserField($session->uid, 'gold', 0) < self::DEMOLISH_ALL_GOLD) {
			return 'gold';
		}
		$levels = is_object($automation) ? (int)$automation->demolishBuildingNow($village->wid,$field) : 0;
		if($levels <= 0) {
			return 'denied';
		}
		$database->modifyGold($session->uid,self::DEMOLISH_ALL_GOLD,0);
		$logging->goldDemolitionLog($village->wid);
		return 'ok';
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
		// El constructor maestro no era una puerta de atrás al candado de alimentos:
		// dorf1/dorf2 encolaban el pedido sin pasar por canBuild(), así que con oro se
		// podía encolar cualquier edificio en una aldea bloqueada y MasterBuilder() lo
		// activaba después mirando sólo los recursos.
		if(!$this->passesFoodGuard($field,$type,$queued,isset($uprequire['pop']) ? (int)$uprequire['pop'] : 0)) {
			return false;
		}
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
	
	/**
	 * Cuándo va a haber recursos para esta mejora, o false si no va a haberlos nunca.
	 *
	 * Un recurso que falta y no se produce (o se produce en negativo, que es lo
	 * normal con el cereal) no tiene fecha. Antes esto dividía por la producción sin
	 * mirar: con producción de cereal exactamente 0 la división daba INF y la página
	 * mostraba una fecha inventada, y con producción negativa el tiempo salía
	 * negativo y lo tapaba el max(), así que anunciaba una fecha que no iba a llegar.
	 */
	public function calculateAvaliable($id,$tid,$plus=1) {
		global $village,$generator;
		$uprequire = $this->resourceRequired($id,$tid,$plus);
		$missing = array(
			'wood' => $uprequire['wood']-$village->awood,
			'clay' => $uprequire['clay']-$village->aclay,
			'iron' => $uprequire['iron']-$village->airon,
			'crop' => $uprequire['crop']-$village->acrop
		);
		$reqtime = 0;
		foreach($missing as $resource => $amount) {
			if($amount <= 0) {
				continue;
			}
			$production = $village->getProd($resource);
			if($production <= 0) {
				return false;
			}
			$reqtime = max($reqtime,$amount / $production * 3600);
		}
		return $generator->procMtime($reqtime + time());
	}
};

?>
