<?php
require_once __DIR__.'/Hero.php';

class Battle {
	private $catapultUnits = array(8, 18, 28, 48);

	public function isCatapultUnit($unitId) {
		return in_array((int)$unitId, $this->catapultUnits, true);
	}

	public function getTribeCatapultUnit($tribe) {
		$unitId = ((int)$tribe - 1) * 10 + 8;
		return $this->isCatapultUnit($unitId) ? $unitId : 0;
	}

	public function calculateSiegeFiring($unitCount, $attackerLosses, $attackPoints, $effectiveDefense) {
		$unitCount = max(0, (float)$unitCount);
		$attackerLosses = max(0, min(1, (float)$attackerLosses));
		$attackPoints = max(0, (float)$attackPoints);
		$effectiveDefense = max(0.000001, (float)$effectiveDefense);
		if($unitCount <= 0 || $attackPoints <= 0 || $attackerLosses >= 1) {
			return 0.0;
		}

		$battleRatio = pow($attackPoints / $effectiveDefense, 1.5);
		$firingFactor = $battleRatio >= 1
			? 1 - 0.5 / $battleRatio
			: 0.5 * $battleRatio;

		return $unitCount * (1 - $attackerLosses) * max(0, $firingFactor);
	}

	public function calculateSiegeOutcome($firingPower, $targetLevel, $upgradeLevel, $moralBonus = 1, $durabilityFactor = 1) {
		$firingPower = max(0, (float)$firingPower);
		$targetLevel = max(0, (int)$targetLevel);
		$upgradeLevel = max(0, min(20, (int)$upgradeLevel));
		$moralBonus = max(0.000001, (float)$moralBonus);
		$durabilityFactor = max(1, (float)$durabilityFactor);
		$upgradeFactor = round(200 * pow(1.0205, $upgradeLevel)) / 200;
		$required = $targetLevel > 0
			? (int)ceil(
				$moralBonus * (pow($targetLevel, 2) + $targetLevel + 1)
				/ (8 * $upgradeFactor / $durabilityFactor)
			)
			: 0;
		$damage = $firingPower * 8 * $upgradeFactor / ($moralBonus * $durabilityFactor);
		$remainingLevel = $targetLevel;
		if($targetLevel > 0 && $firingPower > 0) {
			$remainingLevel = $firingPower >= $required
				? 0
				: max(0, (int)floor(sqrt(max(0, pow($targetLevel + 0.5, 2) - $damage))));
		}

		return array(
			'required' => $required,
			'firing' => $firingPower,
			'damage' => $damage,
			'level_before' => $targetLevel,
			'level_after' => $remainingLevel
		);
	}

	private function calculateCombatOutcome(
		$attackInfantry,
		$attackCavalry,
		$defenderInfantry,
		$defenderCavalry,
		$residenceDefense,
		$defenderTribe,
		$wallLevel,
		$attackerPopulation,
		$defenderPopulation,
		$involved,
		$type
	) {
		$wallFactors = array(1 => 1.030, 2 => 1.020, 3 => 1.025, 4 => 1.000, 5 => 1.000);
		$wallBaseDefense = array(1 => 10, 2 => 6, 3 => 8, 4 => 0, 5 => 0);
		$wallLevel = max(0, min(20, (int)$wallLevel));
		$wallFactor = pow(
			isset($wallFactors[(int)$defenderTribe]) ? $wallFactors[(int)$defenderTribe] : 1,
			$wallLevel
		);
		$wallBase = isset($wallBaseDefense[(int)$defenderTribe])
			? $wallBaseDefense[(int)$defenderTribe]
			: 0;
		$defenderInfantry = ($defenderInfantry + $residenceDefense) * $wallFactor + $wallLevel * $wallBase;
		$defenderCavalry = ($defenderCavalry + $residenceDefense) * $wallFactor + $wallLevel * $wallBase;

		$attackPoints = $attackInfantry + $attackCavalry;
		$defensePoints = $attackPoints > 0
			? $defenderInfantry * ($attackInfantry / $attackPoints)
				+ $defenderCavalry * ($attackCavalry / $attackPoints)
				+ 10
			: $defenderInfantry + $defenderCavalry + 10;
		$moralBonus = 1.0;
		if((int)$attackerPopulation > (int)$defenderPopulation) {
			$moralExponent = $attackPoints < $defensePoints
				? 0.2 * ($attackPoints / max($defensePoints, 0.000001))
				: 0.2;
			$moralBonus = min(
				1.5,
				pow((int)$attackerPopulation / max(1, (int)$defenderPopulation), $moralExponent)
			);
		}
		$effectiveDefense = $defensePoints * $moralBonus;
		$lossExponent = $involved >= 1000
			? max(1.0, 2 * (1.8592 - pow($involved, 0.015)))
			: 1.5;
		$attackerWins = $attackPoints > $effectiveDefense;

		if($attackPoints <= 0) {
			$attackerLosses = 1.0;
			$defenderLosses = 0.0;
		} elseif((int)$type === 4) {
			$ratio = pow($effectiveDefense / $attackPoints, $lossExponent);
			$attackerLosses = $ratio / (1 + $ratio);
			$defenderLosses = 1 - $attackerLosses;
		} elseif($attackerWins) {
			$attackerLosses = min(1.0, pow($effectiveDefense / $attackPoints, $lossExponent));
			$defenderLosses = 1.0;
		} else {
			$attackerLosses = 1.0;
			$defenderLosses = min(1.0, pow($attackPoints / max($effectiveDefense, 0.000001), $lossExponent));
		}

		return array(
			'attacker_losses' => max(0, min(1, $attackerLosses)),
			'defender_losses' => max(0, min(1, $defenderLosses)),
			'attack_points' => $attackPoints,
			'defense_points' => $defensePoints,
			'effective_defense' => $effectiveDefense,
			'moral_bonus' => $moralBonus,
			'attacker_wins' => $attackerWins
		);
	}

	private function calculateRamOutcome(
		$ramCount,
		$attackerLosses,
		$attackPoints,
		$effectiveDefense,
		$wallLevel,
		$upgradeLevel,
		$moralBonus,
		$stonemasonFactor,
		$defenderTribe
	) {
		$upgradeFactor = round(200 * pow(1.0205, max(0, min(20, (int)$upgradeLevel)))) / 200;
		$stonemasonFactor = max(1, (float)$stonemasonFactor);
		$wallDurability = $this->battleWallDurability($defenderTribe);
		$required = (int)round(
			$moralBonus * (pow($wallLevel, 2) + $wallLevel + 1)
			/ (8 * $upgradeFactor / $stonemasonFactor)
			* $wallDurability
			+ 0.5
		);
		$firing = $this->calculateSiegeFiring(
			$ramCount,
			$attackerLosses,
			$attackPoints,
			$effectiveDefense
		);
		$damage = $firing * 8 * $upgradeFactor
			/ (max(0.000001, $moralBonus) * $stonemasonFactor * $wallDurability);
		$remainingLevel = $firing >= $required
			? 0
			: max(0, (int)floor(sqrt(max(0, pow($wallLevel + 0.5, 2) - $damage))));

		return array(
			'required' => $required,
			'firing' => $firing,
			'level_after' => $remainingLevel
		);
	}

	private function calculateCombatWithRams(
		$attackInfantry,
		$attackCavalry,
		$defenderInfantry,
		$defenderCavalry,
		$residenceDefense,
		$defenderTribe,
		$wallLevel,
		$attackerPopulation,
		$defenderPopulation,
		$involved,
		$type,
		$ramCount,
		$ramUpgrade,
		$stonemasonFactor
	) {
		$wallLevel = max(0, min(20, (int)$wallLevel));
		$combatWallLevel = $wallLevel;
		$ramOutcome = null;

		// Ram survival depends on battle losses, while those losses depend on the
		// wall level left by the rams. Resolve both against the same attack.
		for($iteration = 0; $iteration <= 20; $iteration++) {
			$combat = $this->calculateCombatOutcome(
				$attackInfantry,
				$attackCavalry,
				$defenderInfantry,
				$defenderCavalry,
				$residenceDefense,
				$defenderTribe,
				$combatWallLevel,
				$attackerPopulation,
				$defenderPopulation,
				$involved,
				$type
			);

			if((int)$type !== 3 || (int)$ramCount <= 0 || $wallLevel <= 0 || $combat['attack_points'] <= 0) {
				break;
			}

			$ramOutcome = $this->calculateRamOutcome(
				$ramCount,
				$combat['attacker_losses'],
				$combat['attack_points'],
				$combat['effective_defense'],
				$wallLevel,
				$ramUpgrade,
				$combat['moral_bonus'],
				$stonemasonFactor,
				$defenderTribe
			);
			$nextWallLevel = max(0, min($wallLevel, (int)$ramOutcome['level_after']));
			if($nextWallLevel >= $combatWallLevel) {
				break;
			}
			$combatWallLevel = $nextWallLevel;
		}

		$combat['ram'] = $ramOutcome;
		return $combat;
	}

	public function getOasisSimulationInput($oasisId) {
		global $database, $session, $village;

		$oasisId = (int)$oasisId;
		if($oasisId <= 0) {
			return false;
		}

		$oasis = $database->getOMInfo($oasisId);
		if(
			!is_array($oasis)
			|| !isset($oasis['oasistype'], $oasis['fieldtype'], $oasis['occupied'])
			|| (int)$oasis['oasistype'] <= 0
			|| (int)$oasis['fieldtype'] !== 0
			|| (int)$oasis['occupied'] !== 0
		) {
			return false;
		}

		$attackerTribe = isset($session->tribe) ? (int)$session->tribe : 0;
		$villageId = isset($village->wid) ? (int)$village->wid : 0;
		if($attackerTribe < 1 || $attackerTribe > 3 || $villageId <= 0) {
			return false;
		}

		$attackerUnits = $database->getUnit($villageId);
		$defenderUnits = $database->getUnit($oasisId);
		if(!is_array($attackerUnits) || !is_array($defenderUnits)) {
			return false;
		}

		$input = array(
			'a1_v' => $attackerTribe,
			'a2_v4' => 1,
			'ktyp' => 1
		);
		$attackerStart = ($attackerTribe - 1) * 10 + 1;
		$scoutingUnits = array(4, 14, 23);
		for($position = 1; $position <= 10; $position++) {
			$unitId = $attackerStart + $position - 1;
			$unitField = 'u'.$unitId;
			$input['a1_'.$position] = !in_array($unitId, $scoutingUnits, true) && isset($attackerUnits[$unitField])
				? max(0, (int)$attackerUnits[$unitField])
				: 0;
		}

		$input['a1_hero'] = 1;

		for($unit = 31; $unit <= 40; $unit++) {
			$input['a2_'.$unit] = isset($defenderUnits['u'.$unit])
				? max(0, (int)$defenderUnits['u'.$unit])
				: 0;
		}

		return $input;
	}
	
	public function procSim($post) {
		global $database, $form, $session, $village;

		if(!isset($post['a1_v'])) {
			return;
		}

		$attackerTribe = (int)$post['a1_v'];
		if($attackerTribe < 1 || $attackerTribe > 3) {
			return;
		}
		$_POST['mytribe'] = $attackerTribe;

		$target = array();
		for($tribe = 1; $tribe <= 4; $tribe++) {
			if(isset($post['a2_v'.$tribe])) {
				$target[] = $tribe;
			}
		}
		$_POST['target'] = $target;
		if(empty($target)) {
			return;
		}

		$defenderTribe = $target[0];
		$configurationChanged = isset($post['displayed_attacker'])
			&& (
				(int)$post['displayed_attacker'] !== $attackerTribe
				|| !isset($post['displayed_targets'])
				|| $post['displayed_targets'] !== implode(',', $target)
			);
		$values = $post;
		$values['a1_v'] = $attackerTribe;
		$values['tribe'] = $defenderTribe;
		$values['ktyp'] = isset($post['ktyp']) && (int)$post['ktyp'] === 1 ? 1 : 0;

		$defaultHeroPower = 100;
		$defaultHeroHealth = 100;
		$defaultHeroOffBonus = 0;
		if(isset($session->uid)) {
			$userHero = $database->getHeroData((int)$session->uid);
			if(is_array($userHero)) {
				$heroTribe = isset($session->tribe) ? (int)$session->tribe : $attackerTribe;
				$defaultHeroPower = heroFightingStrength($userHero,$heroTribe);
				$defaultHeroHealth = max(1, min(100, (int)$userHero['health']));
				$defaultHeroOffBonus = heroArmyBonusPercent($userHero['offBonus']);
			}
		}

		$attackerTotal = 0;
		for($i = 1; $i <= 10; $i++) {
			$values['a1_'.$i] = $this->simulationNumber(!$configurationChanged && isset($post['a1_'.$i]) ? $post['a1_'.$i] : 0, 0, 999999, true);
			$values['f1_'.$i] = $this->simulationNumber(!$configurationChanged && $i <= 8 && isset($post['f1_'.$i]) ? $post['f1_'.$i] : 0, 0, 20, true);
			$attackerTotal += $values['a1_'.$i];
		}
		$values['a1_hero'] = $this->simulationNumber(isset($post['a1_hero']) ? $post['a1_hero'] : 0, 0, 1, true);
		$values['h_att_power'] = $this->simulationNumber(
			isset($post['h_att_power']) && $post['h_att_power'] !== '' ? $post['h_att_power'] : $defaultHeroPower,
			0,
			99999,
			true
		);
		$values['h_att_health'] = $this->simulationNumber(
			isset($post['h_att_health']) && $post['h_att_health'] !== '' ? $post['h_att_health'] : $defaultHeroHealth,
			1,
			100,
			true
		);
		$attackerTotal += $values['a1_hero'];

		for($unit = 1; $unit <= 40; $unit++) {
			$unitTribe = (int)floor(($unit - 1) / 10) + 1;
			$unitPosition = (($unit - 1) % 10) + 1;
			$isSelected = in_array($unitTribe, $target, true);
			$values['a2_'.$unit] = $isSelected && !$configurationChanged
				? $this->simulationNumber(isset($post['a2_'.$unit]) ? $post['a2_'.$unit] : 0, 0, 999999, true)
				: 0;
			$values['f2_'.$unit] = $isSelected && $unitTribe !== 4 && $unitPosition <= 8 && !$configurationChanged
				? $this->simulationNumber(isset($post['f2_'.$unit]) ? $post['f2_'.$unit] : 0, 0, 20, true)
				: 0;
		}
		for($tribe = 1; $tribe <= 3; $tribe++) {
			$isSelected = in_array($tribe, $target, true);
			$values['a2_hero_'.$tribe] = $isSelected && !$configurationChanged
				? $this->simulationNumber(isset($post['a2_hero_'.$tribe]) ? $post['a2_hero_'.$tribe] : 0, 0, 1, true)
				: 0;
			$values['h_def_power_'.$tribe] = $isSelected && !$configurationChanged
				? $this->simulationNumber(isset($post['h_def_power_'.$tribe]) && $post['h_def_power_'.$tribe] !== '' ? $post['h_def_power_'.$tribe] : 100, 0, 99999, true)
				: 100;
			$values['h_def_bonus_'.$tribe] = $isSelected && !$configurationChanged
				? $this->simulationNumber(isset($post['h_def_bonus_'.$tribe]) ? $post['h_def_bonus_'.$tribe] : 0, 0, 20, false)
				: 0;
			$values['h_def_health_'.$tribe] = $isSelected && !$configurationChanged
				? $this->simulationNumber(isset($post['h_def_health_'.$tribe]) && $post['h_def_health_'.$tribe] !== '' ? $post['h_def_health_'.$tribe] : 100, 1, 100, true)
				: 100;
		}

		$defaultAttackerPopulation = 1;
		if(isset($village->pop)) {
			$defaultAttackerPopulation = max(1, (int)$village->pop);
		}
		$values['ew1'] = $this->simulationNumber(
			isset($post['ew1']) && $post['ew1'] !== '' ? $post['ew1'] : $defaultAttackerPopulation,
			1,
			99999,
			true
		);
		$values['ew2'] = $defenderTribe === 4
			? 100
			: $this->simulationNumber(!$configurationChanged && isset($post['ew2']) ? $post['ew2'] : 1, 1, 99999, true);
		$values['h_off_bonus'] = $this->simulationNumber(
			isset($post['h_off_bonus']) ? $post['h_off_bonus'] : $defaultHeroOffBonus,
			0,
			20,
			false
		);
		$values['kata'] = $defenderTribe === 4
			? 0
			: $this->simulationNumber(!$configurationChanged && isset($post['kata']) ? $post['kata'] : 0, 0, 20, true);
		$values['stonemason'] = $defenderTribe === 4
			? 0
			: $this->simulationNumber(!$configurationChanged && isset($post['stonemason']) ? $post['stonemason'] : 0, 0, 20, true);
		$values['palast'] = $defenderTribe === 4
			? 0
			: $this->simulationNumber(!$configurationChanged && isset($post['palast']) ? $post['palast'] : 0, 0, 20, true);
		for($tribe = 1; $tribe <= 4; $tribe++) {
			$values['wall'.$tribe] = $tribe === $defenderTribe && $defenderTribe !== 4
				? $this->simulationNumber(!$configurationChanged && isset($post['wall'.$tribe]) ? $post['wall'.$tribe] : 0, 0, 20, true)
				: 0;
		}

		$form->valuearray = $values;

		if($attackerTotal > 0) {
			$_POST['result'] = $this->simulate($values);
		} else {
			$form->addError('troops', 'Debe enviar al menos una tropa.');
		}
	}

	private function simulationNumber($value, $minimum, $maximum, $integer) {
		$value = str_replace(',', '.', trim((string)$value));
		if($value === '' || !is_numeric($value)) {
			$value = $minimum;
		}
		$value = $integer ? (int)$value : (float)$value;
		return max($minimum, min($maximum, $value));
	}
	
	private function simulate($post) {
		global $bid34;

		$cavalry = array(4, 5, 6, 15, 16, 23, 24, 25, 26);
		$scouts = array(4, 14, 23, 34);
		$attackerTribe = (int)$post['a1_v'];
		$defenderTribe = (int)$post['tribe'];
		$start = ($attackerTribe - 1) * 10 + 1;
		$attackInfantry = 0.0;
		$attackCavalry = 0.0;
		$attackScout = 0.0;
		$defenseInfantry = 0.0;
		$defenseCavalry = 0.0;
		$defenseScout = 0.0;
		$defenseByTribe = array();
		for($tribe = 1; $tribe <= 4; $tribe++) {
			$defenseByTribe[$tribe] = array('infantry' => 0.0, 'cavalry' => 0.0);
		}
		$involved = 0;
		$onlyScouts = true;

		for($local = 1; $local <= 10; $local++) {
			$unit = $start + $local - 1;
			$amount = (int)$post['a1_'.$local];
			$upgrade = $local <= 8 ? (int)$post['f1_'.$local] : 0;
			$unitData = $GLOBALS['u'.$unit];
			$involved += $amount;
			if($amount <= 0) {
				continue;
			}
			if(!in_array($unit, $scouts, true)) {
				$onlyScouts = false;
			}
			if(in_array($unit, $scouts, true)) {
				$attackScout += $amount * 35 * pow(1.021, $upgrade);
			}
			$strength = $unitData['atk'] + ($unitData['atk'] + 300 * $unitData['pop'] / 7) * (pow(1.007, $upgrade) - 1);
			if(in_array($unit, $cavalry, true)) {
				$attackCavalry += $amount * $strength;
			} else {
				$attackInfantry += $amount * $strength;
			}
		}
		$attackerHero = (int)$post['a1_hero'] === 1;
		if($attackerHero) {
			$onlyScouts = false;
			$involved++;
			$attackInfantry += (int)$post['h_att_power'];
		}

		for($unit = 1; $unit <= 40; $unit++) {
			$amount = (int)$post['a2_'.$unit];
			$upgrade = (int)$post['f2_'.$unit];
			$unitData = $GLOBALS['u'.$unit];
			$unitTribe = (int)floor(($unit - 1) / 10) + 1;
			$involved += $amount;
			if($amount <= 0) {
				continue;
			}
			$defenseByTribe[$unitTribe]['infantry'] += $amount * ($unitData['di'] + ($unitData['di'] + 300 * $unitData['pop'] / 7) * (pow(1.007, $upgrade) - 1));
			$defenseByTribe[$unitTribe]['cavalry'] += $amount * ($unitData['dc'] + ($unitData['dc'] + 300 * $unitData['pop'] / 7) * (pow(1.007, $upgrade) - 1));
			if(in_array($unit, $scouts, true)) {
				$defenseScout += $amount * 20 * pow(1.03, $upgrade);
			}
		}
		for($tribe = 1; $tribe <= 4; $tribe++) {
			if($tribe <= 3 && (int)$post['a2_hero_'.$tribe] === 1) {
				$heroPower = (int)$post['h_def_power_'.$tribe];
				$defenseByTribe[$tribe]['infantry'] += $heroPower;
				$defenseByTribe[$tribe]['cavalry'] += $heroPower;
				$heroDefenseBonus = 1 + (float)$post['h_def_bonus_'.$tribe] / 100;
				$defenseByTribe[$tribe]['infantry'] *= $heroDefenseBonus;
				$defenseByTribe[$tribe]['cavalry'] *= $heroDefenseBonus;
				$involved++;
			}
			$defenseInfantry += $defenseByTribe[$tribe]['infantry'];
			$defenseCavalry += $defenseByTribe[$tribe]['cavalry'];
		}

		if($onlyScouts) {
			if($attackScout <= 0) {
				return array(1 => 1.0, 2 => 0.0, 'Attack_points' => 0, 'Defend_points' => $defenseScout, 'Winner' => 'defender', 'scouting' => true);
			}
			$attackerLosses = $defenseScout >= $attackScout ? 1.0 : pow($defenseScout / $attackScout, 1.5);
			return array(
				1 => min(1.0, $attackerLosses),
				2 => 0.0,
				'Attack_points' => $attackScout,
				'Defend_points' => $defenseScout,
				'Winner' => $attackScout > $defenseScout ? 'attacker' : 'defender',
				'scouting' => true
			);
		}

		$heroBonus = $attackerHero ? 1 + (float)$post['h_off_bonus'] / 100 : 1.0;
		$attackInfantry *= $heroBonus;
		$attackCavalry *= $heroBonus;

		$residenceDefense = 2 * pow((int)$post['palast'], 2);
		$wallLevel = isset($post['wall'.$defenderTribe]) ? (int)$post['wall'.$defenderTribe] : 0;
		$ramCount = (int)$post['a1_7'];
		$ramUpgrade = (int)$post['f1_7'];
		$stonemasonLevel = (int)$post['stonemason'];
		$stonemasonFactor = $stonemasonLevel > 0 && isset($bid34[$stonemasonLevel])
			? max(1, $bid34[$stonemasonLevel]['attri'] / 100)
			: 1.0;
		$combat = $this->calculateCombatWithRams(
			$attackInfantry,
			$attackCavalry,
			$defenseInfantry,
			$defenseCavalry,
			$residenceDefense,
			$defenderTribe,
			$wallLevel,
			(int)$post['ew1'],
			(int)$post['ew2'],
			$involved,
			(int)$post['ktyp'] === 1 ? 4 : 3,
			$ramCount,
			$ramUpgrade,
			$stonemasonFactor
		);
		$attackPoints = $combat['attack_points'];
		$defensePoints = $combat['defense_points'];
		$effectiveDefense = $combat['effective_defense'];
		$moralBonus = $combat['moral_bonus'];
		$attackerLosses = $combat['attacker_losses'];
		$defenderLosses = $combat['defender_losses'];
		$attackerWins = $combat['attacker_wins'];

		$result = array(
			1 => $attackerLosses,
			2 => $defenderLosses,
			'Attack_points' => $attackPoints,
			'Defend_points' => $defensePoints,
			'Winner' => $attackerWins ? 'attacker' : 'defender',
			'moral_bonus' => $moralBonus
		);
		if($attackerHero) {
			$heroDamage = min(100, (int)round($attackerLosses * 100));
			$heroHealth = (int)$post['h_att_health'];
			$heroDead = $heroDamage >= $heroHealth || $heroDamage > 90;
			$result['attacker_hero'] = array(
				'damage' => $heroDamage,
				'health' => $heroDead ? 0 : max(0, $heroHealth - $heroDamage),
				'dead' => $heroDead
			);
		}
		$result['defender_heroes'] = array();
		for($tribe = 1; $tribe <= 3; $tribe++) {
			if((int)$post['a2_hero_'.$tribe] === 1) {
				$heroDamage = min(100, (int)round($defenderLosses * 100));
				$heroHealth = (int)$post['h_def_health_'.$tribe];
				$heroDead = $heroDamage >= $heroHealth || $heroDamage > 90;
				$result['defender_heroes'][$tribe] = array(
					'damage' => $heroDamage,
					'health' => $heroDead ? 0 : max(0, $heroHealth - $heroDamage),
					'dead' => $heroDead
				);
			}
		}

		$targetLevel = (int)$post['kata'];
		$catapultCount = (int)$post['a1_8'];
		$catapultUnit = $this->getTribeCatapultUnit($attackerTribe);
		if((int)$post['ktyp'] === 0 && $defenderTribe !== 4 && $catapultUnit > 0 && $targetLevel > 0 && $catapultCount > 0 && $attackPoints > 0) {
			$catapultUpgrade = (int)$post['f1_8'];
			$catapultsFiring = $this->calculateSiegeFiring(
				$catapultCount,
				$attackerLosses,
				$attackPoints,
				$effectiveDefense
			);
			$outcome = $this->calculateSiegeOutcome(
				$catapultsFiring,
				$targetLevel,
				$catapultUpgrade,
				$moralBonus,
				$stonemasonFactor
			);
			$result[3] = $outcome['required'];
			$result[4] = $catapultsFiring;
			$result['target_level_after'] = $outcome['level_after'];
		}

		if($combat['ram'] !== null) {
			$result[7] = $combat['ram']['required'];
			$result[8] = $combat['ram']['firing'];
			$result['wall_level_after'] = $combat['ram']['level_after'];
		}

		return $result;
	}


	private function battleUnitStrength($base, $population, $level) {
		$base = max(0, (float)$base);
		$population = max(0, (float)$population);
		$level = max(0, min(20, (int)$level));
		return $base + ($base + 300 * $population / 7) * (pow(1.007, $level) - 1);
	}

	private function battleHeroStrength($hero, $tribe) {
		return heroFightingStrength($hero, $tribe);
	}

	private function battleHeroBonus($points) {
		return heroArmyBonusFactor($points);
	}

	private function battleHeroIsMounted($uid) {
		global $database;
		if((int)$uid <= 0 || !method_exists($database, 'getEquippedHeroItem')) {
			return false;
		}
		$horse = $database->getEquippedHeroItem((int)$uid, 6);
		return is_array($horse) && !empty($horse['id']);
	}

	private function battleBreweryLevel($attackerId,$attackerTribe) {
		global $database;
		if((int)$attackerTribe !== 2 || (int)$attackerId <= 0 || !method_exists($database, 'getBreweryLevel')) {
			return 0;
		}
		return max(0, min(10, (int)$database->getBreweryLevel((int)$attackerId)));
	}

	private function battleBreweryActive($attackerId,$attackerTribe) {
		global $database;
		if((int)$attackerTribe !== 2 || (int)$attackerId <= 0 || !method_exists($database, 'getBreweryCelebrationEnd')) {
			return false;
		}
		return (int)$database->getBreweryCelebrationEnd((int)$attackerId) > time();
	}

	private function battleWallDurability($tribe) {
		$durability = array(1 => 1.0, 2 => 5.0, 3 => 2.0);
		return isset($durability[(int)$tribe]) ? $durability[(int)$tribe] : 1.0;
	}

	private function battleUpgradeLevel($upgrades, $position) {
		$key = 'b'.(int)$position;
		return is_array($upgrades) && isset($upgrades[$key])
			? max(0, min(20, (int)$upgrades[$key]))
			: 0;
	}

	// Que un héroe caiga depende solo del porcentaje de bajas de su bando, así que se
	// puede saber antes de tocar la base de datos. Eso permite contar los héroes
	// muertos de los dos lados antes de repartir la experiencia.
	private function battleHeroDies($hero, $losses) {
		if(!is_array($hero) || empty($hero['uid'])) {
			return false;
		}
		$damage = max(0, min(100, (int)round(100 * $losses)));
		$health = max(0, min(100, (float)$hero['health']));
		return $damage > 90 || $damage >= $health;
	}

	private function battleHeroOutcome($hero, $losses, $experience) {
		global $database;
		$outcome = array('dead' => 0, 'damage' => 0);
		if(!is_array($hero) || empty($hero['uid'])) {
			return $outcome;
		}

		$damage = max(0, min(100, (int)round(100 * $losses)));
		$dead = $this->battleHeroDies($hero, $losses);
		$outcome['dead'] = $dead ? 1 : 0;
		$outcome['damage'] = $damage;

		if($dead) {
			$database->modifyHero2('dead', 1, (int)$hero['uid'], 0);
			$database->modifyHero2('health', 0, (int)$hero['uid'], 0);
		} elseif($damage > 0) {
			$database->modifyHero2('health', $damage, (int)$hero['uid'], 2);
		}
		if($experience > 0) {
			$experience = heroExperienceWithHelmet($database, (int)$hero['uid'], $experience);
			$database->modifyHero2('experience', $experience, (int)$hero['uid'], 1);
		}

		return $outcome;
	}

	// 1 = scouting, 3 = normal attack, 4 = raid
	function calculateBattle($Attacker, $Defender, $def_wall, $att_tribe, $def_tribe, $residence, $attpop, $defpop, $type, $def_ab, $att_ab, $tblevel, $stonemason, $walllevel = 0, $AttackerID = 0, $DefenderID = 0, $AttackerWref = 0, $DefenderWref = 0) {
		global $bid34, $database;

		$cavalry = array(4, 5, 6, 15, 16, 23, 24, 25, 26, 35, 36, 45, 46);
		$scouts = array(4, 14, 23, 34, 44);
		$result = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 1,
			6 => $this->battleUpgradeLevel($att_ab, 8),
			7 => 0,
			8 => 0,
			'Attack_points' => 0,
			'Defend_points' => 0,
			'Winner' => 'defender',
			'bounty' => 0,
			'brewery_active' => 0,
			'brewery_level' => 0,
			'deadherodef' => 0,
			'deadheroref' => array(),
			'casualties_attacker' => array()
		);
		for($i = 1; $i <= 11; $i++) {
			$result['casualties_attacker'][$i] = 0;
		}

		$attackerInfantry = 0.0;
		$attackerCavalry = 0.0;
		$attackerScout = 0.0;
		$attackerAmounts = array();
		$attackerPopulationLost = 0;
		$involved = 0;
		$attackerStart = ((int)$att_tribe - 1) * 10 + 1;

		for($position = 1; $position <= 10; $position++) {
			$unit = $attackerStart + $position - 1;
			$amount = max(0, (int)(isset($Attacker['u'.$unit]) ? $Attacker['u'.$unit] : 0));
			$attackerAmounts[$position] = $amount;
			$involved += $amount;
			if($amount === 0) {
				continue;
			}
			$unitData = $GLOBALS['u'.$unit];
			$upgrade = $this->battleUpgradeLevel($att_ab, $position);
			if(in_array($unit, $scouts, true)) {
				$attackerScout += $amount * 35 * pow(1.021, $upgrade);
			}
			if((int)$type === 1) {
				continue;
			}
			$strength = $this->battleUnitStrength($unitData['atk'], $unitData['pop'], $upgrade) * $amount;
			if(in_array($unit, $cavalry, true)) {
				$attackerCavalry += $strength;
			} else {
				$attackerInfantry += $strength;
			}
		}

		$attackerHero = null;
		if((int)$type !== 1 && !empty($Attacker['hero'])) {
			$attackerHero = $database->getHeroData2((int)$Attacker['id']);
			if(is_array($attackerHero)) {
				$heroStrength = $this->battleHeroStrength($attackerHero, $att_tribe);
				if($this->battleHeroIsMounted($attackerHero['uid'])) {
					$attackerCavalry += $heroStrength;
				} else {
					$attackerInfantry += $heroStrength;
				}
				$heroBonus = $this->battleHeroBonus($attackerHero['offBonus']);
				$attackerInfantry *= $heroBonus;
				$attackerCavalry *= $heroBonus;
				$involved++;
			}
		}
		$breweryActive = $this->battleBreweryActive($AttackerID, $att_tribe);
		$result['brewery_active'] = $breweryActive ? 1 : 0;
		$breweryLevel = $breweryActive ? $this->battleBreweryLevel($AttackerID, $att_tribe) : 0;
		if((int)$type !== 1 && $breweryLevel > 0) {
			$breweryFactor = 1 + $breweryLevel / 100;
			$attackerInfantry *= $breweryFactor;
			$attackerCavalry *= $breweryFactor;
			$result['brewery_level'] = $breweryLevel;
		}

		$defenderSources = array();
		if((int)$DefenderWref > 0 && method_exists($database, 'getUnit')) {
			$localUnits = $database->getUnit((int)$DefenderWref);
			if(is_array($localUnits)) {
				$defenderSources[] = array(
					'units' => $localUnits,
					'from' => (int)$DefenderWref,
					'owner' => (int)$Defender['id'],
					'reinforcement' => 0,
					'local' => true
				);
			}
			$reinforcements = $database->getEnforceVillage((int)$DefenderWref, 0);
			if(is_array($reinforcements)) {
				foreach($reinforcements as $reinforcement) {
					$owner = (int)$database->getVillageField((int)$reinforcement['from'], 'owner');
					$defenderSources[] = array(
						'units' => $reinforcement,
						'from' => (int)$reinforcement['from'],
						'owner' => $owner,
						'reinforcement' => (int)$reinforcement['id'],
						'local' => false
					);
				}
			}
		}
		if(empty($defenderSources)) {
			$defenderSources[] = array(
				'units' => $Defender,
				'from' => (int)$DefenderWref,
				'owner' => (int)$Defender['id'],
				'reinforcement' => 0,
				'local' => true
			);
		}

		$defenderOwners = array();
		$defenderScout = 0.0;
		$defenderHeroes = array();
		$countedHeroOwners = array();
		$defenderPopulationLost = 0;
		foreach($defenderSources as $source) {
			$sourceUpgrades = $def_ab;
			if($source['from'] > 0 && method_exists($database, 'getABTech')) {
				$storedUpgrades = $database->getABTech($source['from']);
				if(is_array($storedUpgrades)) {
					$sourceUpgrades = $storedUpgrades;
				}
			}
			$ownerKey = $source['owner'] > 0 ? $source['owner'] : 'source-'.$source['from'].'-'.$source['reinforcement'];
			if(!isset($defenderOwners[$ownerKey])) {
				$defenderOwners[$ownerKey] = array(
					'infantry' => 0.0,
					'cavalry' => 0.0,
					'bonus' => 1.0
				);
			}

			for($unit = 1; $unit <= 50; $unit++) {
				$amount = max(0, (int)(isset($source['units']['u'.$unit]) ? $source['units']['u'.$unit] : 0));
				if($amount === 0) {
					continue;
				}
				$involved += $amount;
				$position = (($unit - 1) % 10) + 1;
				$upgrade = $this->battleUpgradeLevel($sourceUpgrades, $position);
				$unitData = $GLOBALS['u'.$unit];
				if(in_array($unit, $scouts, true)) {
					$defenderScout += $amount * 20 * pow(1.03, $upgrade);
				}
				if((int)$type === 1) {
					continue;
				}
				$defenderOwners[$ownerKey]['infantry'] += $amount * $this->battleUnitStrength($unitData['di'], $unitData['pop'], $upgrade);
				$defenderOwners[$ownerKey]['cavalry'] += $amount * $this->battleUnitStrength($unitData['dc'], $unitData['pop'], $upgrade);
			}

			if(!$source['local'] && !empty($source['units']['hero'])) {
				$result['deadheroref'][$source['reinforcement']] = 0;
			}
			if((int)$type === 1 || empty($source['units']['hero'])) {
				continue;
			}
			// Cada jugador tiene un solo héroe: si aparece a la vez en la aldea y en un
			// refuerzo (datos viejos), solo cuenta una vez.
			$heroOwner = (int)$source['owner'];
			if($heroOwner > 0) {
				if(isset($countedHeroOwners[$heroOwner])) {
					continue;
				}
				$countedHeroOwners[$heroOwner] = true;
			}
			$hero = $source['local']
				? $database->getHeroData3((int)$source['owner'])
				: $database->getHeroData2((int)$source['owner']);
			if(!is_array($hero)) {
				continue;
			}
			$heroTribe = (int)$database->getUserField((int)$source['owner'], 'tribe', 0);
			if($heroTribe < 1 || $heroTribe > 3) {
				$heroTribe = (int)$def_tribe;
			}
			// El héroe tiene una sola fuerza de lucha: al defender vale igual contra
			// infantería que contra caballería. Antes se sumaba a un solo lado según
			// llevara o no caballo, así que un héroe montado no defendía nada frente
			// a un ataque de infantería (y uno a pie, nada frente a caballería). El
			// caballo solo decide si el héroe ataca como caballería, no cómo defiende.
			$heroStrength = $this->battleHeroStrength($hero, $heroTribe);
			$defenderOwners[$ownerKey]['infantry'] += $heroStrength;
			$defenderOwners[$ownerKey]['cavalry'] += $heroStrength;
			$defenderOwners[$ownerKey]['bonus'] = max(
				$defenderOwners[$ownerKey]['bonus'],
				$this->battleHeroBonus($hero['defBonus'])
			);
			$defenderHeroes[] = array(
				'data' => $hero,
				'local' => $source['local'],
				'reinforcement' => $source['reinforcement']
			);
			$involved++;
		}

		if((int)$type === 1) {
			$result['Attack_points'] = $attackerScout;
			$result['Defend_points'] = $defenderScout;
			$result['Winner'] = $attackerScout > $defenderScout ? 'attacker' : 'defender';
			$scoutLosses = $attackerScout > 0
				? ($defenderScout >= $attackerScout ? 1.0 : pow($defenderScout / $attackerScout, 1.5))
				: 1.0;
			$result[1] = max(0, min(1, $scoutLosses));
			// Las bajas alcanzan a todas las unidades enviadas, no solo a los espías:
			// una exploración que lleva tropas normales (o que no lleva ningún espía)
			// no puede volver intacta y traer el informe gratis.
			foreach($attackerAmounts as $position => $amount) {
				$result['casualties_attacker'][$position] = (int)round($amount * $result[1]);
			}
			return $result;
		}

		$defenderInfantry = 0.0;
		$defenderCavalry = 0.0;
		foreach($defenderOwners as $ownerDefense) {
			$defenderInfantry += $ownerDefense['infantry'] * $ownerDefense['bonus'];
			$defenderCavalry += $ownerDefense['cavalry'] * $ownerDefense['bonus'];
		}

		$wallLevel = max(0, min(20, (int)$def_wall));
		$residenceDefense = 2 * pow(max(0, (int)$residence), 2);
		$stonemasonFactor = isset($bid34[(int)$stonemason]['attri'])
			? max(1, $bid34[(int)$stonemason]['attri'] / 100)
			: 1;
		$ramCount = isset($attackerAmounts[7]) ? $attackerAmounts[7] : 0;
		$ramUpgrade = $this->battleUpgradeLevel($att_ab, 7);
		$combat = $this->calculateCombatWithRams(
			$attackerInfantry,
			$attackerCavalry,
			$defenderInfantry,
			$defenderCavalry,
			$residenceDefense,
			$def_tribe,
			$wallLevel,
			$attpop,
			$defpop,
			$involved,
			$type,
			$ramCount,
			$ramUpgrade,
			$stonemasonFactor
		);
		$attackPoints = $combat['attack_points'];
		$defensePoints = $combat['defense_points'];
		$effectiveDefense = $combat['effective_defense'];
		$moralBonus = $combat['moral_bonus'];
		$attackerWins = $combat['attacker_wins'];

		$result[1] = $combat['attacker_losses'];
		$result[2] = $combat['defender_losses'];
		$result[5] = $moralBonus;
		$result['Attack_points'] = $attackPoints;
		$result['Defend_points'] = $defensePoints;
		$result['Winner'] = $attackerWins ? 'attacker' : 'defender';

		foreach($attackerAmounts as $position => $amount) {
			$loss = (int)round($amount * $result[1]);
			$result['casualties_attacker'][$position] = $loss;
			if($loss > 0) {
				$unitData = $GLOBALS['u'.($attackerStart + $position - 1)];
				$attackerPopulationLost += $loss * (int)$unitData['pop'];
			}
		}

		foreach($defenderSources as $source) {
			for($unit = 1; $unit <= 50; $unit++) {
				$amount = max(0, (int)(isset($source['units']['u'.$unit]) ? $source['units']['u'.$unit] : 0));
				if($amount > 0) {
					$loss = (int)round($amount * $result[2]);
					$defenderPopulationLost += $loss * (int)$GLOBALS['u'.$unit]['pop'];
				}
			}
		}

		// Un héroe caído vale 6 de población para el rival. Las dos muertes se resuelven
		// antes de repartir experiencia: si no, el atacante nunca cobraba los 6 del héroe
		// defensor, porque esas bajas se calculaban después de darle su experiencia.
		if($this->battleHeroDies($attackerHero, $result[1])) {
			$attackerPopulationLost += 6;
		}
		foreach($defenderHeroes as $defenderHero) {
			if($this->battleHeroDies($defenderHero['data'], $result[2])) {
				$defenderPopulationLost += 6;
			}
		}

		if(is_array($attackerHero)) {
			$attackerOutcome = $this->battleHeroOutcome($attackerHero, $result[1], $defenderPopulationLost);
			$result['casualties_attacker'][11] = $attackerOutcome['dead'];
		}

		$heroExperience = count($defenderHeroes) > 0
			? (int)floor($attackerPopulationLost / count($defenderHeroes))
			: 0;
		foreach($defenderHeroes as $defenderHero) {
			$outcome = $this->battleHeroOutcome($defenderHero['data'], $result[2], $heroExperience);
			if($defenderHero['local']) {
				$result['deadherodef'] = $outcome['dead'];
			} else {
				$result['deadheroref'][$defenderHero['reinforcement']] = $outcome['dead'];
			}
		}

		$maxBounty = 0;
		foreach($attackerAmounts as $position => $amount) {
			$unitData = $GLOBALS['u'.($attackerStart + $position - 1)];
			$maxBounty += max(0, $amount - $result['casualties_attacker'][$position]) * (int)$unitData['cap'];
		}
		$result['bounty'] = $maxBounty;

		if((int)$type === 3 && $attackPoints > 0) {
			$catapultCount = isset($attackerAmounts[8]) ? $attackerAmounts[8] : 0;
			$catapultUnit = $this->getTribeCatapultUnit($att_tribe);
			if($catapultUnit > 0 && $catapultCount > 0 && (int)$tblevel > 0) {
				$result[4] = $this->calculateSiegeFiring(
					$catapultCount,
					$result[1],
					$attackPoints,
					$effectiveDefense
				);
				$outcome = $this->calculateSiegeOutcome(
					$result[4],
					(int)$tblevel,
					$result[6],
					$moralBonus,
					$stonemasonFactor
				);
				$result[3] = $outcome['required'];
				$result['target_level_after'] = $outcome['level_after'];
			}

			if($combat['ram'] !== null) {
				$result[7] = $combat['ram']['required'];
				$result[8] = $combat['ram']['firing'];
				$result['wall_level_after'] = $combat['ram']['level_after'];
			}
		}

		return $result;
	}

};

$battle = new Battle;
?>
