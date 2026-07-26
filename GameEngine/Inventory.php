<?php
include "Data/hero_full.php";
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

if(!function_exists('getHeroHorseSpeedBonus')){
	function getHeroHorseSpeedBonus($type){
		$bonuses = array(103 => 7, 104 => 10, 105 => 13);
		return isset($bonuses[(int)$type]) ? $bonuses[(int)$type] : 0;
	}
}

if(!function_exists('getHeroEquipmentDefinition')){
	function getHeroEquipmentDefinition($btype){
		$definitions = array(
			1 => array('slot' => 'helmet', 'face' => 'helmet'),
			2 => array('slot' => 'body', 'face' => null),
			3 => array('slot' => 'leftHand', 'face' => 'leftHand'),
			4 => array('slot' => 'rightHand', 'face' => 'rightHand'),
			5 => array('slot' => 'shoes', 'face' => 'foot'),
			6 => array('slot' => 'horse', 'face' => 'horse')
		);

		return isset($definitions[(int)$btype]) ? $definitions[(int)$btype] : false;
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
		if($btype<7 || $btype>9 || !isOwnedHeroItem($item, $uid, $btype) || (int)$item['proc']!==0 || $amount<1 || $amount>(int)$item['num']-(int)$item['type']){
			return false;
		}

		$inventory = $database->getHeroInventory($uid);
		if(!is_array($inventory)){
			return false;
		}

		$currentId = (int)$inventory['bag'];
		if($currentId>0 && $currentId!==(int)$item['id']){
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
	$uid = (int)$session->uid;
	$heroData = $database->getHeroData($uid);
	$itemId = isset($data['id']) ? (int)$data['id'] : 0;
	$itemData = $itemId>0 ? $database->getItemData($itemId) : false;

	if(!isOwnedHeroItem($itemData, $uid) || (int)$itemData['proc']!==0){
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
					$database->modifyHero2('experience', $value, $uid, 1);
					$database->editHeroNum($data['id'], $data['amount'], 0);
				}else{
					$database->editProcItem($data['id'], 1);
					$database->modifyHero2('experience', $value, $uid, 1);
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
			if($database->resetHeroAttributes($uid)){
				$database->editProcItem($data['id'], 1);
			}
			header("Location: hero_inventory.php");
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
