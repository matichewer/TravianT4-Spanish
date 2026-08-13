<?php

require_once __DIR__.'/Catapult.php';

class Units {
	const TROOP_CANCEL_WINDOW = 90;

	public $sending = array(), $recieving = array(), $return = array();

	private function normalizeCatapultTarget($value, $allowSecondRandom = false) {
		global $building;
		return catapultNormalizeTarget($value, $building->getTypeLevel(16), $allowSecondRandom);
	}

	// Posición (1-10) que ocupa el espía dentro de cada tribu. Los galos lo tienen
	// en la tercera casilla (u23), el resto en la cuarta (u4, u14, u34, u44).
	private function getTribeScoutPosition($tribe) {
		$positions = array(1 => 4, 2 => 4, 3 => 3, 4 => 4, 5 => 4);
		$tribe = (int)$tribe;
		return isset($positions[$tribe]) ? $positions[$tribe] : 0;
	}

	// Una exploración solo puede llevar espías. Sin esta comprobación se podía
	// espiar cualquier aldea gratis y sin ser detectado enviando una unidad
	// cualquiera como exploración: las bajas del combate de espionaje solo se
	// aplicaban a las casillas de espía, así que el resto volvía intacto.
	// Un oasis sólo lo puede reforzar quien lo tiene o alguien de su alianza (propia
	// o aliada por diplomacia). Antes cualquiera podía meterle tropas al oasis de un
	// enemigo. Devuelve '' si el refuerzo es válido.
	private function oasisReinforcementError($oasisConquered, $oasisOwner) {
		global $database, $session;
		$oasisConquered = (int)$oasisConquered;
		$oasisOwner = (int)$oasisOwner;
		if($oasisConquered === 0) {
			return "No puedes reforzar un oasis sin ocupar.";
		}
		if($oasisOwner === (int)$session->uid) {
			return '';
		}
		$ownAlliance = (int)$session->alliance;
		$targetAlliance = (int)$database->getUserField($oasisOwner,'alliance',0);
		if($ownAlliance > 0 && $targetAlliance > 0
			&& ($ownAlliance === $targetAlliance
				|| $database->areAlliancesAllied($ownAlliance,$targetAlliance))) {
			return '';
		}
		return "Sólo puedes reforzar un oasis tuyo o de un aliado.";
	}

	private function scoutingSendError($tribe, $troops) {
		$scoutPosition = $this->getTribeScoutPosition($tribe);
		if($scoutPosition < 1) {
			return "No puedes explorar con esta tribu.";
		}
		$scoutsSent = isset($troops[$scoutPosition]) ? (int)$troops[$scoutPosition] : 0;
		$otherSent = isset($troops[11]) ? (int)$troops[11] : 0;
		for($position = 1; $position <= 10; $position++) {
			if($position !== $scoutPosition && isset($troops[$position])) {
				$otherSent += (int)$troops[$position];
			}
		}
		if($scoutsSent < 1) {
			return "Para explorar tienes que enviar espías.";
		}
		if($otherSent > 0) {
			return "En una exploración solo puedes enviar espías.";
		}
		return '';
	}

	public function procUnits($post) {
		if(isset($post['c'])) {
			switch($post['c']) {

				case "1":
				if (isset($post['a'])&& $post['a']==533374){
				$this->sendTroops($post);
				}else{
				$post = $this->loadUnits($post);
				return $post;								
				}
				break;

				case "2":
				if (isset($post['a'])&& $post['a']==533374){
				$this->sendTroops($post);
				}else{
				$post = $this->loadUnits($post);
				return $post;								
				}
				break;	

				case "8":
				$this->sendTroopsBack($post);
				break;	

				case "3":
				if (isset($post['a'])&& $post['a']==533374){
				$this->sendTroops($post);
				}else{
				$post = $this->loadUnits($post);
				return $post;								
				}
				break;

				case "4":
					if (isset($post['a'])&& $post['a']==533374){
						$this->sendTroops($post);
					}else{
					$post = $this->loadUnits($post);
					return $post;
					}
					break;

				case "5":
					if (isset($post['a']) && $post['a'] === "new"){
						$this->Settlers($post);
				}else{
				$post = $this->loadUnits($post);
				return $post;								
				}
				break;
			}
		}
	}

	public function cancelTroopMovement($post) {
		global $database, $village, $session;

		$status = 'invalid';
		$tokenIsValid = isset($post['c']) && is_string($post['c']) && hash_equals((string) $session->mchecker, $post['c']);
		$moveid = isset($post['moveid']) && ctype_digit((string) $post['moveid']) ? (int) $post['moveid'] : 0;

		if($tokenIsValid && $moveid > 0) {
			$movement = $database->getOutgoingMovement($moveid, $village->wid, $session->uid);
			if($movement) {
				$now = time();
				$sentAt = ctype_digit((string) $movement['data']) ? (int) $movement['data'] : 0;
				$elapsed = $now - $sentAt;

				if($sentAt > 0 && $elapsed >= 0 && $elapsed <= self::TROOP_CANCEL_WINDOW && (int) $movement['endtime'] > $now) {
					$returnEndtime = $now + max(1, $elapsed);
					$status = $database->cancelOutgoingMovement($moveid, $village->wid, $sentAt, $now, $returnEndtime) ? 'success' : 'failed';
				} else {
					$status = 'expired';
				}
			}
		}

		$_SESSION['movement_cancel_status'] = $status;
		header("Location: build.php?id=39");
		exit;
	}

	// Una aventura es un movimiento más: se puede abortar dentro de la misma ventana
	// de 90 segundos y el héroe tarda en volver lo que llevaba viajando.
	public function cancelAdventure($post) {
		global $database, $village, $session;

		$status = 'invalid';
		$tokenIsValid = isset($post['c']) && is_string($post['c']) && hash_equals((string) $session->mchecker, $post['c']);
		$moveid = isset($post['moveid']) && ctype_digit((string) $post['moveid']) ? (int) $post['moveid'] : 0;

		if($tokenIsValid && $moveid > 0) {
			$movement = $database->getOutgoingAdventure($moveid, $village->wid, $session->uid);
			if($movement) {
				$now = time();
				$sentAt = ctype_digit((string) $movement['data']) ? (int) $movement['data'] : 0;
				$elapsed = $now - $sentAt;

				if($sentAt > 0 && $elapsed >= 0 && $elapsed <= self::TROOP_CANCEL_WINDOW && (int) $movement['endtime'] > $now) {
					$returnEndtime = $now + max(1, $elapsed);
					// El regreso viaja como cualquier vuelta de tropas: una fila de attacks
					// con el héroe en t11, que es lo que devuelve la unidad a la aldea y le
					// reapunta hero.wref cuando llega.
					$reference = $database->addAttack($village->wid,0,0,0,0,0,0,0,0,0,0,1,3,0,0,0);
					if($reference > 0) {
						$cancelled = $database->cancelAdventureMovement($moveid, $village->wid, $sentAt, $now, $returnEndtime, $reference);
						if(!$cancelled) {
							$database->removeAttack($reference);
						}
						$status = $cancelled ? 'adventure' : 'failed';
					} else {
						$status = 'failed';
					}
				} else {
					$status = 'expired';
				}
			}
		}

		$_SESSION['movement_cancel_status'] = $status;
		header("Location: build.php?id=39");
		exit;
	}

	public function managePrisoners($post) {
		global $database, $village, $session;

		$status = 'invalid';
		$tokenIsValid = isset($post['c']) && is_scalar($post['c'])
			&& hash_equals((string)$session->mchecker,(string)$post['c']);
		$prisonerId = isset($post['prisoner_id']) && ctype_digit((string)$post['prisoner_id'])
			? (int)$post['prisoner_id']
			: 0;
		$operation = isset($post['operation']) && is_scalar($post['operation'])
			? (string)$post['operation']
			: '';

		if($tokenIsValid && $prisonerId > 0 && in_array($operation,array('release','disband'),true)) {
			$prisoner = $database->getPrisonersByID($prisonerId);
			if($prisoner) {
				$trapOwner = (int)$database->getVillageField((int)$prisoner['wref'],'owner');
				$troopOwner = (int)$database->getVillageField((int)$prisoner['from'],'owner');
				$authorized = $operation === 'release'
					? $trapOwner === (int)$session->uid && (int)$prisoner['wref'] === (int)$village->wid
					: $troopOwner === (int)$session->uid && (int)$prisoner['from'] === (int)$village->wid;

				if($authorized) {
					if($operation === 'release') {
						$status = $this->queuePrisonerReturn($prisoner,$troopOwner) ? 'released' : 'failed';
					} else {
						$status = $database->disbandPrisonersAtomic(
							$prisonerId,
							(int)$prisoner['wref'],
							(int)$prisoner['from'],
							$troopOwner
						) ? 'disbanded' : 'failed';
					}
				}
			}
		}

		$_SESSION['prisoner_status'] = $status;
		header("Location: build.php?gid=16");
		exit;
	}

	private function queuePrisonerReturn($prisoner,$owner) {
		global $database, $generator;

		$owner = (int)$owner;
		$tribe = (int)$database->getUserField($owner,'tribe',0);
		if($tribe < 1 || $tribe > 5) {
			return false;
		}
		$speeds = array();
		$bootsBonus = 0;
		for($position = 1; $position <= 10; $position++) {
			if((int)$prisoner['t'.$position] > 0) {
				$unit = $GLOBALS['u'.(($tribe - 1) * 10 + $position)];
				$speeds[] = max(1, (float)$unit['speed']);
			}
		}
		$travelBonus = 0;
		if((int)$prisoner['t11'] > 0) {
			$hero = $database->getHeroData($owner);
			$speeds[] = is_array($hero) && !empty($hero['speed']) ? max(1, (float)$hero['speed']) : 6;
			$bootsBonus = heroEquippedBootsSpeedBonus($database, $owner);
			$travelBonus = heroEquippedTravelSpeedBonus($database,$owner,(int)$prisoner['wref'],(int)$prisoner['from'],true);
		}
		if(empty($speeds)) {
			return false;
		}
		$trapCoordinates = $database->getCoor((int)$prisoner['wref']);
		$homeCoordinates = $database->getCoor((int)$prisoner['from']);
		$travelTime = max(1, (int)$generator->procDistanceTime($homeCoordinates,$trapCoordinates,min($speeds),1,$bootsBonus,$travelBonus));
		$troops = array();
		for($i = 1; $i <= 11; $i++) {
			$troops[$i] = max(0,(int)$prisoner['t'.$i]);
		}
		$start = time();
		return $database->returnPrisonersAtomic(
			(int)$prisoner['id'],
			(int)$prisoner['wref'],
			(int)$prisoner['from'],
			$troops,
			$start,
			$start + $travelTime,
			false,
			$troops
		);
	}

	private function loadUnits($post) {
		global $database,$village,$session,$generator,$logging,$form;
				// Busqueda por nombre de pueblo
				// Confirmamos y buscamos las coordenadas por nombre de pueblo
				if(	!$post['t1'] && !$post['t2'] && !$post['t3'] && !$post['t4'] && !$post['t5'] && 
					!$post['t6'] && !$post['t7'] && !$post['t8'] && !$post['t9'] && !$post['t10'] && !$post['t11']){
				$form->addError("error","Debes seleccionar al menos una tropa");				
				}				

				$validX = is_numeric($post['x']) && floor($post['x']) == $post['x'];
				$validY = is_numeric($post['y']) && floor($post['y']) == $post['y'];

				if(!$post['dname'] && !$validX && !$validY){
				$form->addError("error","Ingresa un nombre o coordenadas");			
				}

				if(isset($post['dname']) && $post['dname'] != "") {
					$id = $database->getVillageByName(stripslashes($post['dname']));
					if (!isset($id)){				
					$form->addError("error","La aldea no existe");
					}else{
					$coor = $database->getCoor($id);
					}
				}
				// Busqueda por coordenadas de pueblo
				// Confirmamos y buscamos las coordenadas por coordenadas de pueblo				
				if($validX && $validY) {
					$coor = array('x'=>$post['x'], 'y'=>$post['y']);
					$id = $generator->getBaseID($coor['x'],$coor['y']);
					if (!$database->getVillageState($id)){
						$form->addError("error","Las coordenadas no existen");
					}
				}
					$offset =($session->tribe - 1) * 10;
					for($i=1; $i<=10; $i++)
					{
						if(isset($post['t'.$i]))
						{
                            
							if ($post['t'.$i] > $village->unitarray['u'.($offset+$i)])
							{
								$form->addError("error","No puedes enviar más unidades de las que tienes");
								break;
							}

							if($post['t'.$i]<0)
							{
								$form->addError("error","No puedes enviar una cantidad negativa de unidades.");
								break;
							}

						}												
					}
					if(isset($post['t11']))
					{
							if ($post['t11'] > $village->unitarray['hero'])
                            {
                                $form->addError("error","No puedes enviar más unidades de las que tienes");
                                //break;
                            }
                            
                            if($post['t11']<0)
                            {
                                $form->addError("error","No puedes enviar una cantidad negativa de unidades.");
                                //break;
                            }
					}

					if((isset($post['c']) ? (int)$post['c'] : 0) === 1) {
						$scoutTroops = array();
						for($position = 1; $position <= 11; $position++) {
							$scoutTroops[$position] = isset($post['t'.$position]) ? $post['t'.$position] : 0;
						}
						$scoutError = $this->scoutingSendError($session->tribe, $scoutTroops);
						if($scoutError !== '') {
							$form->addError("error",$scoutError);
						}
					}

                if ($database->isVillageOases($id) == 0) {
				// Solo los envíos hostiles (explorar/atacar/saquear) chocan con la
				// protección de principiante; el refuerzo (c = 2) siempre se permite.
				if((isset($post['c']) ? (int)$post['c'] : 0) != 2 && $database->hasBeginnerProtection($id)==1) {
	                $form->addError("error","El jugador está bajo protección de principiante. No puedes atacarlo");
                }
                
				//check if banned:
				$villageOwner = $database->getVillageField($id,'owner');
				$userAccess = $database->getUserField($villageOwner,'access',0);
					if($userAccess == '0'){
								$form->addError("error","El jugador está baneado. No puedes atacarlo");
								//break;
					}

				//check if attacking same village that units are in
					if($id == $village->wid){
								$form->addError("error","No puedes atacar la misma aldea desde la que envías.");
								//break;
					}
				// Procesamos el array con los errores dados en el formulario
				if($form->returnErrors() > 0) {
					$_SESSION['errorarray'] = $form->getErrors();
					$_SESSION['valuearray'] = $_POST;
					header("Location: a2b.php");		
				}else{				
				// Debemos devolver un array con $post, que contiene todos los datos mas 
				// otra variable que definira que el flag esta levantado y se va a enviar y el tipo de envio
				$villageName = $database->getVillageField($id,'name');
				$speed= 300;
				$timetaken = $generator->procDistanceTime($coor,$village->coor,INCREASE_SPEED,1);								
				array_push($post, "$id", "$villageName", "$villageOwner","$timetaken");
				return $post;

			}
                  }else{

                // Oases accept normal attacks and raids. Only an oasis already
                // held by a player can be reinforced, and only by its owner or an ally.
                $oasisConquered = (int)$database->getOasisField($id,"conqured");
                $oasisOwner = (int)$database->getOasisField($id,"owner");
                $attackType = isset($post['c']) ? (int)$post['c'] : 0;
                if($attackType === 2) {
                    $reinforceError = $this->oasisReinforcementError($oasisConquered,$oasisOwner);
                    if($reinforceError !== '') {
                        $form->addError("error",$reinforceError);
                    }
                }

                      if($form->returnErrors() > 0) {
                    $_SESSION['errorarray'] = $form->getErrors();
                    $_SESSION['valuearray'] = $_POST;
                    header("Location: a2b.php");
                }else{

                $villageName = $oasisConquered !== 0 ? $database->getOasisField($id,"name") : "Oasis sin ocupar";
                $speed= 300;
                $timetaken = $generator->procDistanceTime($coor,$village->coor,INCREASE_SPEED,1);
                array_push($post, "$id", "$villageName", ($oasisConquered !== 0 ? (string)$oasisOwner : "3"), "$timetaken");
                return $post;

            }
                  }	

	}
	private function sendTroops($post) {
		global $form, $database, $village, $generator, $session, $building, $battle;

		$confirmationKey = isset($post['timestamp_checksum']) && is_scalar($post['timestamp_checksum'])
			? (string)$post['timestamp_checksum']
			: '';
		if(!preg_match('/^[A-Za-z0-9]{6}$/', $confirmationKey)) {
			$confirmationKey = '';
		}
		$confirmationTimestamp = isset($post['timestamp']) && ctype_digit((string)$post['timestamp'])
			? (int)$post['timestamp']
			: 0;
		$data = $confirmationKey !== '' && $confirmationTimestamp > 0
			? $database->getA2b($confirmationKey, $confirmationTimestamp)
			: null;

		if(!$data) {
			header("Location: build.php?id=39");
			exit;
		}



		 $Gtribe = "";
		if ($session->tribe == '2'){ $Gtribe = "1"; } else if ($session->tribe == '3'){ $Gtribe = "2"; }else if ($session->tribe == '4'){ $Gtribe = "3"; }else if ($session->tribe == '5'){ $Gtribe = "4"; }
				for($i=1; $i<=10; $i++){
						if(isset($data['u'.$i])){
							// La unidad de posicion 10 (colono/administrador) usa
							// 'u{tribu}0' (u10, u20, u30...): la concatenacion
							// 'u'.$Gtribe.$i solo da la columna correcta para i<10.
							$unitColumn = $i === 10 ? 'u'.((int)$session->tribe).'0' : 'u'.$Gtribe.$i;

                            if ($data['u'.$i] > $village->unitarray[$unitColumn])
							{
								$form->addError("error","No puedes enviar más unidades de las que tienes");
								break;
							}

							if($data['u'.$i]<0)
							{
								$form->addError("error","No puedes enviar una cantidad negativa de unidades.");
								break;
							}

						}
					}
                    if ($data['u11'] > $village->unitarray['hero'])
                            {
                                $form->addError("error","No puedes enviar más unidades de las que tienes");
                                //break;
                            }
                            
                            if($data['u11']<0)
                            {
                                $form->addError("error","No puedes enviar una cantidad negativa de unidades.");
                                //break;
                            }
				// Recheck the reinforcement rule on confirmation so a hand-crafted
				// request cannot reinforce an unoccupied or enemy oasis.
				if($database->isVillageOases($data['to_vid']) != 0 && (int)$data['type'] === 2) {
					$reinforceError = $this->oasisReinforcementError(
						(int)$database->getOasisField($data['to_vid'],"conqured"),
						(int)$database->getOasisField($data['to_vid'],"owner")
					);
					if($reinforceError !== '') {
						$form->addError("error",$reinforceError);
					}
				}
				// Idem con la exploración: se revalida en la confirmación para que una
				// petición armada a mano no pueda espiar sin enviar espías.
				if((int)$data['type'] === 1) {
					$scoutTroops = array();
					for($position = 1; $position <= 11; $position++) {
						$scoutTroops[$position] = isset($data['u'.$position]) ? $data['u'.$position] : 0;
					}
					$scoutError = $this->scoutingSendError($session->tribe, $scoutTroops);
					if($scoutError !== '') {
						$form->addError("error",$scoutError);
					}
				}
				if($form->returnErrors() > 0) {
					$_SESSION['errorarray'] = $form->getErrors();
					$_SESSION['valuearray'] = $_POST;
					header("Location: a2b.php");		
				} else {


		if(!$database->claimA2b($confirmationKey, $confirmationTimestamp)) {
			header("Location: build.php?id=39");
			exit;
		}

			 if($session->tribe == 1){ $u = ""; } elseif($session->tribe == 2){ $u = "1"; } elseif($session->tribe == 3){ $u = "2"; }elseif($session->tribe == 4){ $u = "3"; }else {$u = "4"; }


		$unitDeductions = array('hero' => (int)$data['u11']);
		for($position = 1; $position <= 10; $position++) {
			$column = $position === 10 ? 'u'.((int)$session->tribe).'0' : 'u'.$u.$position;
			$unitDeductions[$column] = (int)$data['u'.$position];
		}
		if(!$database->deductUnitsIfAvailable($village->wid,$unitDeductions)) {
			$form->addError("error","Las unidades ya no están disponibles o el envío está vacío.");
			$_SESSION['errorarray'] = $form->getErrors();
			header("Location: a2b.php");
			exit;
		}
	if($database->checkVilExist($data['to_vid'])){
		$query1 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'vdata` WHERE `wref` = ' . $data['to_vid']);
	}else{
		$query1 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'odata` WHERE `wref` = ' . $data['to_vid']);
	}
    $data1 = mysql_fetch_assoc($query1);
    $query2 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'users` WHERE `id` = ' . $data1['owner']);
    $data2 = mysql_fetch_assoc($query2);
    $query21 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'users` WHERE `id` = ' . $session->uid);
    $data21 = mysql_fetch_assoc($query21);


    
		$eigen = $database->getCoor($village->wid);
		$from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);
		$ander = $database->getCoor($data['to_vid']);
		$to = array('x'=>$ander['x'], 'y'=>$ander['y']);
        $start = ($data21['tribe']-1)*10+1;
        $end = ($data21['tribe']*10);
        
		$speeds = array();
		$scout = 1;

		//find slowest unit.			
		for($i=1;$i<=10;$i++){
			if (isset($data['u'.$i])){
				if($data['u'.$i] != '' && $data['u'.$i] > 0){
					if($unitarray) { reset($unitarray); }
					$unitarray = $GLOBALS["u".(($session->tribe-1)*10+$i)];
					$speeds[] = max(1, (int)$unitarray['speed']);
                }
			}
		}
		$bootsBonus = 0;
		$travelBonus = 0;
		if (isset($data['u11'])) {
			if($data['u11'] != '' && $data['u11'] > 0){
				$heroarray = $database->getHeroData($session->uid);
				$speeds[] = max(1, (int)$heroarray['speed']);
				$bootsBonus = heroEquippedBootsSpeedBonus($database, $session->uid);
				// Ida: el estandarte vale si las dos aldeas son propias y la bandera si
				// son de la misma alianza. El mapa no, que es solo para volver.
				$travelBonus = heroEquippedTravelSpeedBonus($database,$session->uid,$village->wid,$data['to_vid'],false);
			}
		}

		$time = $generator->procDistanceTime($from,$to,empty($speeds) ? 1 : min($speeds),1,$bootsBonus,$travelBonus);
		$sentAt = time();
		$catapultUnit = $battle->getTribeCatapultUnit((int)$session->tribe);
		$hasCatapults = $catapultUnit > 0
			&& isset($data['u8']) && (int)$data['u8'] > 0
			&& isset($data['type']) && (int)$data['type'] === 3;
		$post['ctar1'] = $hasCatapults
			? $this->normalizeCatapultTarget(isset($post['ctar1']) ? $post['ctar1'] : 0)
			: 0;
		$post['ctar2'] = $hasCatapults && $building->getTypeLevel(16) >= 20
			? $this->normalizeCatapultTarget(isset($post['ctar2']) ? $post['ctar2'] : 0, true)
			: 0;
        // El valor llega crudo desde $_POST y termina interpolado en el INSERT de
        // addAttack: solo se aceptan las dos opciones reales (1 recursos, 2 defensas).
        $post['spy'] = ((int)$data['type'] === 1 && isset($post['spy']) && (int)$post['spy'] === 2) ? 2 : (((int)$data['type'] === 1) ? 1 : 0);
		// La aldea natal solo se puede mudar mandando al héroe como refuerzo a otra
		// aldea propia, y el cambio se aplica recién cuando el héroe llega.
		$sethome = isset($post['sethome']) && (int)$post['sethome'] === 1
			&& (int)$data['u11'] > 0
			&& (int)$data['type'] === 2
			&& (int)$database->getVillageField($data['to_vid'],'owner') === (int)$session->uid
			&& (int)$data['to_vid'] !== (int)$village->wid ? 1 : 0;
		$reference = $database->addAttack(($village->wid),$data['u1'],$data['u2'],$data['u3'],$data['u4'],$data['u5'],$data['u6'],$data['u7'],$data['u8'],$data['u9'],$data['u10'],$data['u11'],$data['type'],$post['ctar1'],$post['ctar2'],$post['spy'],$sethome);

		$movementAdded = $reference > 0
			&& $database->addMovement(3,$village->wid,$data['to_vid'],$reference,$sentAt,($time+$sentAt));
		if(!$movementAdded) {
			if($reference > 0) $database->removeAttack($reference);
			foreach($unitDeductions as $column => $amount) {
				if($amount > 0) $database->modifyUnit($village->wid,$column,$amount,1);
			}
			$form->addError("error","No se pudo crear el movimiento. Las unidades fueron devueltas.");
			$_SESSION['errorarray'] = $form->getErrors();
			header("Location: a2b.php");
			exit;
		}
   
		$to_owner = $database->getVillageField($data['to_vid'],'owner');
		if($post['del_protect'] == 1) {
		$database->updateUserField($session->uid, "protect", (time()-1), 1);
		}

		if($form->returnErrors() > 0) {
			$_SESSION['errorarray'] = $form->getErrors();
			$_SESSION['valuearray'] = $_POST;
				header("Location: a2b.php");
				exit;
			}
			header("Location: build.php?id=39");
			exit;

	}}

	private function sendTroopsBack($post) {
		global $form, $database, $village, $generator, $session, $technology;

		$enforce=$database->getEnforceArray($post['ckey'],0);
		if(($enforce['from']==$village->wid) || ($enforce['vref']==$village->wid)){
			$to = $database->getVillage($enforce['from']);
			$Gtribe = "";
			if ($database->getUserField($to['owner'],'tribe',0) == '2'){ $Gtribe = "1"; } else if ($database->getUserField($to['owner'],'tribe',0) == '3'){ $Gtribe = "2"; } else if ($database->getUserField($to['owner'],'tribe',0) == '4'){ $Gtribe = "3"; }else if ($database->getUserField($to['owner'],'tribe',0) == '5'){ $Gtribe = "4"; }  

					$backTribe = (int)$database->getUserField($to['owner'],'tribe',0);
					for($i=1; $i<=10; $i++){
						if(isset($post['t'.$i])){
							// 'u'.$Gtribe.$i solo da la columna correcta para i<10.
							$unitColumn = $i === 10 ? 'u'.$backTribe.'0' : 'u'.$Gtribe.$i;
							if ($post['t'.$i] > $enforce[$unitColumn])
							{
								$form->addError("error","No puedes enviar más unidades de las que tienes");
								break;
							}

							if($post['t'.$i]<0)
							{
								$form->addError("error","No puedes enviar una cantidad negativa de unidades.");
								break;
							}
						} else {
						$post['t'.$i.'']='0';
						}
					}
					// El héroe también se retira desde esta pantalla. Sin acotarlo aquí y sin
					// descontarlo más abajo volvía a casa pero seguía contando como refuerzo.
					$post['t11'] = isset($post['t11']) ? (int)$post['t11'] : 0;
					if($post['t11'] < 0) {
						$post['t11'] = 0;
					}
					if($post['t11'] > (int)$enforce['hero']) {
						$post['t11'] = (int)$enforce['hero'];
					}

				if($form->returnErrors() > 0) {
					$_SESSION['errorarray'] = $form->getErrors();
					$_SESSION['valuearray'] = $_POST;
					header("Location: a2b.php");		
				} else {

					//change units
                    $start = ($database->getUserField($to['owner'],'tribe',0)-1)*10+1;
                    $end = ($database->getUserField($to['owner'],'tribe',0)*10);

                    $j='1';
					for($i=$start;$i<=$end;$i++){
						$database->modifyEnforce($post['ckey'],$i,$post['t'.$j.''],0); $j++;
					}
					if($post['t11'] > 0){
						$database->modifyEnforce($post['ckey'],'hero',$post['t11'],0);
					}

						//get cord 
						$fromcoor = $database->getCoor($enforce['from']);
						$tocoor = $database->getCoor($enforce['vref']);
						$fromCor = array('x'=>$tocoor['x'], 'y'=>$tocoor['y']);
						$toCor = array('x'=>$fromcoor['x'], 'y'=>$fromcoor['y']);

				$speeds = array();

				//find slowest unit.
				for($i=1;$i<=11;$i++){
					if (isset($post['t'.$i])){
						if( $post['t'.$i] != '' && $post['t'.$i] > 0){
                        if($unitarray) { reset($unitarray); }
                        $unitarray = $GLOBALS["u".(($session->tribe-1)*10+$i)];
							// El refuerzo puede ser de otro jugador: el héroe que vuelve es el
							// del dueño de las tropas, no el de quien está mirando la aldea.
							if($post['t11'] != '' && $post['t11'] > 0){
								$heroarray = $database->getHeroData($to['owner']);
								$speeds[] = $heroarray['speed'];
							}else{
								$speeds[] = $unitarray['speed'];
							}
                    	} else {
						$post['t'.$i.'']='0';
						}
					} else {
						$post['t'.$i.'']='0';
					}
				}
				$heroReturns = $post['t11'] != '' && $post['t11'] > 0;
				$bootsBonus = $heroReturns
					? heroEquippedBootsSpeedBonus($database, $to['owner'])
					: 0;
				$travelBonus = $heroReturns
					? heroEquippedTravelSpeedBonus($database,$to['owner'],$village->wid,$enforce['from'],true)
					: 0;
				$time = $generator->procDistanceTime($fromCor,$toCor,min($speeds),1,$bootsBonus,$travelBonus);
				$reference = $database->addAttack($enforce['from'],$post['t1'],$post['t2'],$post['t3'],$post['t4'],$post['t5'],$post['t6'],$post['t7'],$post['t8'],$post['t9'],$post['t10'],$post['t11'],2,0,0,0,0);
				$database->addMovement(4,$village->wid,$enforce['from'],$reference,0,($time+time()));
				$technology->checkReinf($post['ckey']);

							header("Location: build.php?id=39");
							exit;

				}
		} else {
			$form->addError("error","No puedes cambiar las tropas de otro jugador.");
				if($form->returnErrors() > 0) {
					$_SESSION['errorarray'] = $form->getErrors();
					$_SESSION['valuearray'] = $_POST;
					header("Location: a2b.php");		
				}
		}
	}

		public function Settlers($post) {
			global $building, $database, $generator, $village, $session;

			$tokenIsValid = isset($post['k']) && is_scalar($post['k']) && hash_equals((string)$session->mchecker,(string)$post['k']);
			$target = isset($post['s']) && is_scalar($post['s']) && ctype_digit((string)$post['s']) ? (int)$post['s'] : 0;
			if(!$tokenIsValid || !isset($post['a'],$post['id']) || $post['a'] !== 'new' || (string)$post['id'] !== '39' || $target <= 0) {
				$this->redirectToRallyPoint();
			}
			$session->changeChecker();
			if(!$database->acquireSettlementLock($session->uid,5)) {
				$this->redirectToRallyPoint();
			}
			try {
				$this->queueSettlement($target);
			} finally {
				$database->releaseSettlementLock($session->uid);
			}
			$this->redirectToRallyPoint();
		}

		private function queueSettlement($target) {
			global $building, $database, $generator, $village, $session;

			$targetInfo = $database->getMInfo($target);
			if(!is_array($targetInfo) || (int)$targetInfo['occupied'] !== 0 || (int)$targetInfo['oasistype'] !== 0 || (int)$targetInfo['fieldtype'] <= 0) {
				return false;
			}

			$outgoingSettlers = $database->getMovement(5,$village->wid,0);
			if($database->getPendingSettlementCountByOwner($session->uid,0,$target) > 0) {
				return false;
			}
			$unlockedSlots = $database->getExpansionSlotLimit($village->wid);
			$usedSlots = 0;
			foreach(array('exp1','exp2','exp3') as $field) {
				if((int)$database->getVillageField($village->wid,$field) > 0) {
					$usedSlots++;
				}
			}
			if($unlockedSlots <= $usedSlots + count($outgoingSettlers)) {
				return false;
			}

			$cps = (int)$database->getUserField($session->uid,'cp',0);
			$ownedVillages = count($database->getVillagesID($session->uid));
			$pendingSettlements = $database->getPendingSettlementCountByOwner($session->uid);
			$cultureEligibility = travianCultureExpansionEligibility($cps,$ownedVillages,$pendingSettlements,CP);
			$unit = (int)$session->tribe * 10;
			$units = $database->getUnit($village->wid);
			if(!$cultureEligibility['eligible'] || !is_array($units) || (int)$units['u'.$unit] < 3) {
				return false;
			}

			$targetCoor = array('x'=>$targetInfo['x'],'y'=>$targetInfo['y']);
			$travelTime = $generator->procDistanceTime($village->coor,$targetCoor,300,0);
			if($travelTime <= 0 || !$database->deductResourcesIfAvailable($village->wid,750,750,750,750)) {
				return false;
			}
			if(!$database->deductUnitIfAvailable($village->wid,$unit,3)) {
				$database->modifyResource($village->wid,750,750,750,750,1);
				return false;
			}
			if(!$database->addMovement(5,$village->wid,$target,0,$session->uid,time()+$travelTime)) {
				$database->refundFoundingAssets($village->wid,$session->uid,$unit);
				return false;
			}
			$database->markFollowupQuestAchieved($session->uid,9);
			return true;
		}

		public function Adventures($post) {
			global $database, $generator, $village, $session;

			$tokenIsValid = isset($post['k']) && is_scalar($post['k']) && hash_equals((string)$session->mchecker,(string)$post['k']);
			$target = isset($post['h']) && is_scalar($post['h']) && ctype_digit((string)$post['h']) ? (int)$post['h'] : 0;
			if(!$tokenIsValid || !isset($post['a'],$post['id']) || $post['a'] !== 'adventure' || (string)$post['id'] !== '39' || $target <= 0) {
				$this->redirectToRallyPoint();
			}
			$session->changeChecker();

			$adventure = $database->getAdventure($session->uid,$target);
			$hero = $database->getHeroData($session->uid);
			$targetCoor = $database->getCoor($target);
			$heroVillageId = is_array($hero) ? (int)$hero['wref'] : 0;
			$heroVillage = $heroVillageId > 0 ? $database->getVillage($heroVillageId) : false;
			$heroVillageFields = $heroVillageId > 0 ? $database->getResourceLevel($heroVillageId) : false;
			$heroVillageCoor = $heroVillageId > 0 ? $database->getCoor($heroVillageId) : false;
			if(!is_array($adventure) || (int)$adventure['end'] !== 0 || !is_array($hero) || (int)$hero['dead'] !== 0
				|| (int)$hero['speed'] <= 0 || !is_array($heroVillage) || (int)$heroVillage['owner'] !== (int)$session->uid
				|| !is_array($heroVillageFields) || (int)$heroVillageFields['f39'] < 1
				|| !$database->getHUnit($heroVillageId) || !is_array($heroVillageCoor) || !is_array($targetCoor)) {
				$this->redirectToRallyPoint();
			}
			// Un jugador tiene un solo héroe, así que solo puede haber una aventura en
			// curso. Mirar únicamente los movimientos de esta aldea hacia este destino
			// dejaba lanzar una segunda aventura si el héroe figuraba a la vez en dos
			// aldeas, y llegaban dos informes por una sola salida.
			if($database->heroAdventureInProgress((int)$session->uid)) {
				$this->redirectToRallyPoint();
			}

			$travelTime = $generator->procDistanceTime(
				$heroVillageCoor,
				$targetCoor,
				(int)$hero['speed'],
				1,
				heroEquippedBootsSpeedBonus($database, $session->uid)
			);
			if($travelTime <= 0 || !$database->deductUnitIfAvailable($heroVillageId,'hero',1)) {
				$this->redirectToRallyPoint();
			}
			// El momento de salida va en `data`, igual que en los envíos de tropas: es
			// lo que después permite cancelar la aventura dentro de la misma ventana.
			$sentAt = time();
			if(!$database->addMovement(9,$heroVillageId,$target,0,$sentAt,$sentAt+$travelTime)) {
				$database->modifyUnit($heroVillageId,'hero',1,1);
			}
			$this->redirectToRallyPoint();
		}

		private function redirectToRallyPoint() {
			header("Location: build.php?id=39");
			exit;
		}

};

$units = new Units;

?>
