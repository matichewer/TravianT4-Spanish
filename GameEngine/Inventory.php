<?php
include "Data/hero_full.php";
require_once __DIR__."/Hero.php";
$hero_levels = $GLOBALS["hero_levels"];

if(!function_exists('getHeroArmorBonuses')){
	function getHeroArmorBonuses($type){
		$itemPower = array(
			88 => 500,
			89 => 1000,
			90 => 1500,
			91 => 250,
			92 => 500,
			93 => 750
		);
		$autoRegen = array(
			82 => 20,
			83 => 30,
			84 => 40,
			85 => 10,
			86 => 15,
			87 => 20
		);

		return array(
			'itempower' => isset($itemPower[$type]) ? $itemPower[$type] : 0,
			'autoregen' => isset($autoRegen[$type]) ? $autoRegen[$type] : 0
		);
	}
}

if(!function_exists('getHeroWeaponPowerBonus')){
	function getHeroWeaponPowerBonus($type){
		$type = (int)$type;
		if($type<16 || $type>60){
			return 0;
		}

		return (($type-16)%3+1)*500;
	}
}

if(!function_exists('getHeroEquipmentDefinition')){
	function getHeroEquipmentDefinition($btype){
		// El slot sale de heroEquipmentSlot() para que no haya dos mapas de btype a
		// columna de `heroinventory` que se puedan desincronizar. La tabla de caras
		// acota a los btype 1-6: los de bolsa (7-9) tienen slot pero no se equipan por
		// acá, van por equipHeroBagItem.
		$slot = heroEquipmentSlot($btype);
		$faces = array(1 => 'helmet', 2 => null, 3 => 'leftHand', 4 => 'rightHand', 5 => 'foot', 6 => 'horse');
		if($slot===false || !array_key_exists((int)$btype, $faces)){
			return false;
		}

		return array('slot' => $slot, 'face' => $faces[(int)$btype]);
	}
}

if(!function_exists('isOwnedHeroItem')){
	function isOwnedHeroItem($item, $uid, $btype=null){
		return is_array($item)
			&& isset($item['id'], $item['uid'], $item['btype'])
			&& (int)$item['id']>0
			&& (int)$item['uid']==(int)$uid
			&& ($btype===null || (int)$item['btype']==(int)$btype);
	}
}

if(!function_exists('applyHeroEquipmentBonusChange')){
	function applyHeroEquipmentBonusChange($database, $uid, $btype, $oldItem, $newItem){
		$heroData = $database->getHeroData($uid);
		if(!is_array($heroData)){
			return;
		}

		if((int)$btype===2){
			$oldBonuses = is_array($oldItem) ? getHeroArmorBonuses((int)$oldItem['type']) : array('itempower' => 0, 'autoregen' => 0);
			$newBonuses = is_array($newItem) ? getHeroArmorBonuses((int)$newItem['type']) : array('itempower' => 0, 'autoregen' => 0);
			$itemPower = max(0, (int)$heroData['itempower']-$oldBonuses['itempower'])+$newBonuses['itempower'];
			$autoRegen = max(0, (int)$heroData['autoregen']-$oldBonuses['autoregen'])+$newBonuses['autoregen'];
			$database->modifyHero2('itempower', $itemPower, $uid, 0);
			$database->modifyHero2('autoregen', $autoRegen, $uid, 0);
		}elseif((int)$btype===4){
			$oldBonus = is_array($oldItem) ? getHeroWeaponPowerBonus((int)$oldItem['type']) : 0;
			$newBonus = is_array($newItem) ? getHeroWeaponPowerBonus((int)$newItem['type']) : 0;
			$itemPower = max(0, (int)$heroData['itempower']-$oldBonus)+$newBonus;
			$database->modifyHero2('itempower', $itemPower, $uid, 0);
		}elseif((int)$btype===5){
			// El bono de las botas de mercenario no se guarda: depende de la distancia
			// del viaje, así que se lee del objeto equipado al calcular cada movimiento.
			$empty = array('autoregen' => 0, 'armyspeed' => 0, 'speed' => 0);
			$oldBonuses = is_array($oldItem) ? getHeroShoesBonuses((int)$oldItem['type']) : $empty;
			$newBonuses = is_array($newItem) ? getHeroShoesBonuses((int)$newItem['type']) : $empty;
			$autoRegen = max(0, (int)$heroData['autoregen']-$oldBonuses['autoregen'])+$newBonuses['autoregen'];
			$speed = max(7, (int)$heroData['speed']-$oldBonuses['speed'])+$newBonuses['speed'];
			$database->modifyHero2('autoregen', $autoRegen, $uid, 0);
			$database->modifyHero2('speed', $speed, $uid, 0);
		}elseif((int)$btype===6){
			$oldBonus = is_array($oldItem) ? getHeroHorseSpeedBonus((int)$oldItem['type']) : 0;
			$newBonus = is_array($newItem) ? getHeroHorseSpeedBonus((int)$newItem['type']) : 0;
			$speed = max(7, (int)$heroData['speed']-$oldBonus)+$newBonus;
			$database->modifyHero2('speed', $speed, $uid, 0);
		}
	}
}

if(!function_exists('equipHeroItem')){
	function equipHeroItem($database, $uid, $item){
		$btype = isset($item['btype']) ? (int)$item['btype'] : 0;
		$definition = getHeroEquipmentDefinition($btype);
		if(!$definition || !isOwnedHeroItem($item, $uid, $btype) || (int)$item['proc']!==0){
			return false;
		}

		$inventory = $database->getHeroInventory($uid);
		if(!is_array($inventory)){
			return false;
		}

		$currentId = (int)$inventory[$definition['slot']];
		if($currentId===(int)$item['id']){
			return true;
		}

		$oldItem = null;
		if($currentId>0){
			$candidate = $database->getItemData($currentId);
			if(isOwnedHeroItem($candidate, $uid, $btype)){
				$oldItem = $candidate;
				$database->editProcItem($currentId, 0);
			}
		}

		applyHeroEquipmentBonusChange($database, $uid, $btype, $oldItem, $item);
		$database->editProcItem((int)$item['id'], 1);
		$database->setHeroInventory($uid, $definition['slot'], (int)$item['id']);
		if($definition['face']!==null){
			$database->modifyHeroFace($uid, $definition['face'], (int)$item['type']);
		}

		return true;
	}
}

if(!function_exists('unequipHeroItem')){
	function unequipHeroItem($database, $uid, $btype, $requestedItemId){
		$definition = getHeroEquipmentDefinition($btype);
		$inventory = $database->getHeroInventory($uid);
		if(!$definition || !is_array($inventory)){
			return false;
		}

		$currentId = (int)$inventory[$definition['slot']];
		if($currentId<=0 || $currentId!==(int)$requestedItemId){
			return false;
		}

		$item = $database->getItemData($currentId);
		if(!isOwnedHeroItem($item, $uid, $btype)){
			return false;
		}

		applyHeroEquipmentBonusChange($database, $uid, $btype, $item, null);
		$database->setHeroInventory($uid, $definition['slot'], 0);
		$database->editProcItem($currentId, 0);
		if($definition['face']!==null){
			$database->modifyHeroFace($uid, $definition['face'], 0);
		}

		return true;
	}
}

if(!function_exists('equipHeroBagItem')){
	function equipHeroBagItem($database, $uid, $item, $amount){
		$btype = isset($item['btype']) ? (int)$item['btype'] : 0;
		$amount = (int)$amount;
		if($btype<7 || $btype>9 || !isOwnedHeroItem($item, $uid, $btype) || $amount<1 || $amount>(int)$item['num']-(int)$item['type']){
			return false;
		}

		$inventory = $database->getHeroInventory($uid);
		if(!is_array($inventory)){
			return false;
		}

		$currentId = (int)$inventory['bag'];
		// Recargar el mismo objeto suma a lo que ya lleva la bolsa. Un objeto marcado
		// como equipado que no sea el de la bolsa es una fila inconsistente: no se toca.
		$isBagItem = $currentId>0 && $currentId===(int)$item['id'];
		if((int)$item['proc']!==0 && !$isBagItem){
			return false;
		}

		if($currentId>0 && !$isBagItem){
			$currentItem = $database->getItemData($currentId);
			if(isOwnedHeroItem($currentItem, $uid) && (int)$currentItem['btype']>=7 && (int)$currentItem['btype']<=9){
				$database->editHeroType($currentId, 0, 2);
				$database->editProcItem($currentId, 0);
			}
		}

		$database->editProcItem((int)$item['id'], 1);
		$database->editHeroType((int)$item['id'], $amount, 1);
		$database->setHeroInventory($uid, 'bag', (int)$item['id']);

		return true;
	}
}

if(!function_exists('unequipHeroBagItem')){
	function unequipHeroBagItem($database, $uid, $requestedItemId){
		$inventory = $database->getHeroInventory($uid);
		if(!is_array($inventory) || (int)$inventory['bag']<1 || (int)$inventory['bag']!==(int)$requestedItemId){
			return false;
		}

		$currentId = (int)$inventory['bag'];
		$item = $database->getItemData($currentId);
		if(!isOwnedHeroItem($item, $uid) || (int)$item['btype']<7 || (int)$item['btype']>9){
			return false;
		}

		$database->setHeroInventory($uid, 'bag', 0);
		$database->editProcItem($currentId, 0);
		$database->editHeroType($currentId, 0, 2);

		return true;
	}
}

if($_POST && isset($_POST['a']) && $_POST['a']=='inventory'){
	$data = $_POST;
	$tokenIsValid = isset($data['c']) && is_scalar($data['c'])
		&& hash_equals((string)$session->mchecker,(string)$data['c']);
	if(!$tokenIsValid){
		header("Location: hero_inventory.php");
		exit;
	}

	$uid = (int)$session->uid;
	$heroData = $database->getHeroData($uid);
	$itemId = isset($data['id']) ? (int)$data['id'] : 0;
	$itemData = $itemId>0 ? $database->getItemData($itemId) : false;

	// proc=1 en un consumible normal significa "ya gastado" y no se puede volver a usar.
	// En los objetos de bolsa (7-9) significa "cargado", y recargarlos es legítimo:
	// equipHeroBagItem valida que sea el que está realmente en la bolsa.
	$isBagConsumable = isOwnedHeroItem($itemData, $uid)
		&& (int)$itemData['btype']>=7 && (int)$itemData['btype']<=9;

	if(!isOwnedHeroItem($itemData, $uid) || ((int)$itemData['proc']!==0 && !$isBagConsumable)){
		$data['btype'] = 0;
	}else{
		$data['id'] = $itemId;
		$data['btype'] = (int)$itemData['btype'];
		$data['type'] = (int)$itemData['type'];
		$data['amount'] = isset($data['amount']) ? (int)$data['amount'] : 0;
	}

	if($data['btype']>=1 && $data['btype']<=6){
		if((int)$heroData['dead']===0){
			equipHeroItem($database, $uid, $itemData);
		}
	}

	elseif($data['btype']>=7 && $data['btype']<=9){
		if((int)$heroData['dead']===0){
			equipHeroBagItem($database, $uid, $itemData, $data['amount']);
		}
	}

	elseif($data['btype']==10){
		if($data['amount'] <= $itemData['num']){
			$value = ($data['amount']*10);
				if($data['amount'] < $itemData['num']){
					$database->modifyHero2('experience', heroExperienceWithHelmet($database, $uid, $value), $uid, 1);
					$database->editHeroNum($data['id'], $data['amount'], 0);
				}else{
					$database->editProcItem($data['id'], 1);
					$database->modifyHero2('experience', heroExperienceWithHelmet($database, $uid, $value), $uid, 1);
				}
		}
		header("Location: hero_inventory.php");
	}

	elseif($data['btype']==11){
		if($heroData['health']<100){
			if($data['amount'] <= $itemData['num']){
				$health = round($heroData['health']);
				if(($health+$data['amount'])>100){
					$database->modifyHero2('health', 100, $uid, 0);
					$newAmount = intval(100-$health);
					$database->editHeroNum($data['id'], $newAmount, 0);
				}	
				else{
					if($data['amount'] < $itemData['num']){
						$database->modifyHero2('health', $data['amount'], $uid, 1);
						$database->editHeroNum($data['id'], $data['amount'], 0);
					}else{
						$database->editProcItem($data['id'], 1);
						$database->modifyHero2('health', $data['amount'], $uid, 1);
					}
				}
			}
		}
	}

	elseif($data['btype']==12){
		if($heroData['dead']!=0){
			$database->modifyHero2('dead', 0, $uid, 0);
			$database->modifyHero2('health', 100, $uid, 0);
			$database->modifyHero2('wref', $village->wid, $uid, 0);
			$database->editTableField('units', 'hero', 1, 'vref', $village->wid);
			$database->editProcItem($data['id'], 1);
		}
	}

		elseif($data['btype']==13){
			$database->consumeBookOfWisdom($uid,$data['id']);
			header("Location: hero_inventory.php");
			exit;
		}

	elseif($data['btype']==14){
		if($village->loyalty<=125){
			if($data['amount'] <= $itemData['num']){
				if(($village->loyalty+$data['amount'])>125){
					$database->setVillageField($village->wid, 'loyalty', 125);
					$newAmount = intval(125-$village->loyalty);
					$database->editHeroNum($data['id'], $newAmount, 0);
				}	
				else{
					if($data['amount'] < $itemData['num']){
						$database->setVillageField($village->wid, 'loyalty', ($village->loyalty+$data['amount']));
						$database->editHeroNum($data['id'], $data['amount'], 0);
					}else{
						$database->editProcItem($data['id'], 1);
						$database->setVillageField($village->wid, 'loyalty', ($village->loyalty+$data['amount']));
					}
				}
			}
		}
		header("Location: hero_inventory.php");
	}

	elseif($data['btype']==15){
		if($data['amount'] <= $itemData['num']){
			$value = ($data['amount']*$database->getVSumField($uid, 'cp'));
			if($data['amount'] < $itemData['num']){
				$database->updateUserField($uid, 'cp', $value, 2);
				$database->editHeroNum($data['id'], $data['amount'], 0);
			}else{
				$database->editProcItem($data['id'], 1);
				$database->updateUserField($uid, 'cp', $value, 2);
			}
		}
	}

}
?>
