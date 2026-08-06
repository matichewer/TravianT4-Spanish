<?php
// Un espionaje se detecta si el defensor tenía aunque sea un espía en la aldea, propio
// o llegado como refuerzo, y entonces el informe va al dueño de la aldea y a todos los
// que mandaron refuerzo. Antes la detección dependía de que muriera algún espía
// atacante y, como las bajas se redondean, con pocos espías defendiendo el redondeo
// daba cero: un solo espía nunca alcanzaba para avisarle a nadie.

function spyDetectionAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

class SpyDetectionDatabase {
	public $units = array();
	public $reinforcements = array();
	public $reinforcementOwners = array();

	public function getUnit($vref) {
		return isset($this->units[$vref]) ? $this->units[$vref] : null;
	}
	public function getEnforceVillage($vref, $mode) {
		return isset($this->reinforcements[$vref]) ? $this->reinforcements[$vref] : array();
	}
	public function getVillageField($vref, $field) {
		return isset($this->reinforcementOwners[$vref]) ? $this->reinforcementOwners[$vref] : 0;
	}
	public function getABTech($vref) {
		return array();
	}
	public function getUserField($uid, $field, $mode) {
		return $field === 'tribe' ? 1 : $uid;
	}
	public function getBreweryCelebrationEnd($uid) {
		return 0;
	}
}

function spyDetectionUnits($vref, $counts) {
	$row = array('vref' => $vref, 'hero' => 0);
	for($unit = 1; $unit <= 50; $unit++) {
		$row['u'.$unit] = isset($counts[$unit]) ? $counts[$unit] : 0;
	}
	return $row;
}

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Battle.php';
require_once dirname(__DIR__).'/GameEngine/Automation.php';

$ATTACKER_WREF = 1000;
$DEFENDER_WREF = 2000;
$REINFORCEMENT_WREF = 3000;
$REINFORCEMENT_OWNER = 30;

$database = new SpyDetectionDatabase;
$database->reinforcementOwners = array($REINFORCEMENT_WREF => $REINFORCEMENT_OWNER);
$battle = new Battle;
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();

// Espionaje romano: tantos Equites Legati atacando contra los espías que defiendan la
// aldea, sean propios (u4) o de un refuerzo teutón (u14).
function spyDetectionScouting($attackingScouts, $localScouts, $reinforcementScouts, $attackingSoldiers = 0) {
	global $battle, $database, $DEFENDER_WREF, $REINFORCEMENT_WREF, $ATTACKER_WREF;

	$database->units = array($DEFENDER_WREF => spyDetectionUnits($DEFENDER_WREF, array(4 => $localScouts)));
	if($reinforcementScouts > 0) {
		$reinforcement = spyDetectionUnits($REINFORCEMENT_WREF, array(14 => $reinforcementScouts));
		$reinforcement['id'] = 1;
		$reinforcement['from'] = $REINFORCEMENT_WREF;
		$database->reinforcements = array($DEFENDER_WREF => array($reinforcement));
	} else {
		$database->reinforcements = array();
	}

	$attacker = spyDetectionUnits($ATTACKER_WREF, array(1 => $attackingSoldiers, 4 => $attackingScouts));
	$attacker['id'] = 10;
	// El motor suma los refuerzos al defensor antes de pelear, igual que Automation.
	$defender = spyDetectionUnits($DEFENDER_WREF, array(4 => $localScouts, 14 => $reinforcementScouts));
	$defender['id'] = 20;

	$result = $battle->calculateBattle(
		$attacker, $defender, 0, 1, 1, 0, 100, 100, 1,
		array(), array(), 0, 0, 0, 10, 20, $ATTACKER_WREF, $DEFENDER_WREF
	);
	$casualties = 0;
	for($position = 1; $position <= 11; $position++) {
		$casualties += (int)$result['casualties_attacker'][$position];
	}
	return array('casualties' => $casualties, 'defense' => $result['Defend_points']);
}

// Espías defendiendo la aldea tal como los cuenta Automation: $Defender ya trae los
// refuerzos sumados, así que un aliado de otra tribu cuenta igual que las tropas propias.
function spyDetectionDefendingScouts($localScouts, $reinforcementScouts) {
	$defender = spyDetectionUnits(0, array(4 => $localScouts, 14 => $reinforcementScouts));
	$scouts = 0;
	foreach(scoutUnitIds() as $scoutUnit) {
		$scouts += (int)$defender['u'.$scoutUnit];
	}
	return $scouts;
}

// 0. La Naturaleza no tiene espía. Su cuarta unidad (u34) es el murciélago, un animal
// de combate: contarlo como espía hacía que un oasis con murciélagos detectara la
// exploración y matara a los espías. Los natares sí lo tienen, en u44.
spyDetectionAssert(
	!in_array(34, scoutUnitIds(), true),
	'El murciélago (u34) no cuenta como espía: la Naturaleza no tiene explorador.'
);
spyDetectionAssert(
	in_array(44, scoutUnitIds(), true),
	'El Pájaro de Presa (u44) sí cuenta como espía: es el explorador natar.'
);
$batOasis = spyDetectionScouting(20, 0, 0);
spyDetectionAssert(
	(float)$batOasis['defense'] === 0.0,
	'Un oasis sin espías no aporta defensa de espionaje.'
);
$withBats = spyDetectionUnits(0, array(34 => 50));
$batDefense = 0;
foreach(scoutUnitIds() as $scoutUnit) {
	$batDefense += (int)$withBats['u'.$scoutUnit];
}
spyDetectionAssert(
	$batDefense === 0,
	'50 murciélagos en un oasis no cuentan como espías defensores.'
);

// 1. Los espías de refuerzo defienden exactamente igual que los propios: 20 puntos cada
// uno, vengan de donde vengan.
$own = spyDetectionScouting(20, 3, 0);
$reinforced = spyDetectionScouting(20, 0, 3);
spyDetectionAssert(
	(float)$own['defense'] === (float)$reinforced['defense'] && (float)$reinforced['defense'] === 60.0,
	'Los espías enviados como refuerzo aportan la misma defensa que los espías propios.'
);
spyDetectionAssert(
	spyDetectionDefendingScouts(0, 3) === 3 && spyDetectionDefendingScouts(2, 3) === 5,
	'El conteo de espías defensores incluye los refuerzos de otras tribus.'
);

// 2. El motivo del cambio: con un solo espía defensor las bajas del atacante redondean a
// cero siempre, así que la regla vieja (avisar solo si murió alguien) nunca avisaba.
foreach(array(1, 2, 10, 200) as $attackingScouts) {
	$outcome = spyDetectionScouting($attackingScouts, 1, 0);
	spyDetectionAssert(
		$outcome['casualties'] === 0,
		'Un solo espía defensor no mata a ninguno de los '.$attackingScouts.' espías atacantes.'
	);
	spyDetectionAssert(
		$automation->spyAttemptDetected(1, 0, $outcome['casualties']),
		'Con un solo espía defensor el espionaje de '.$attackingScouts.' espías se detecta igual.'
	);
}

// 3. Un espía de refuerzo alcanza tanto como uno propio: la detección es de la aldea
// entera, así que el informe le llega al dueño y a todos los que mandaron refuerzo.
$reinforcedOnly = spyDetectionScouting(200, 0, 1);
spyDetectionAssert(
	$reinforcedOnly['casualties'] === 0
		&& $automation->spyAttemptDetected(spyDetectionDefendingScouts(0, 1), 0, $reinforcedOnly['casualties']),
	'Un único espía llegado como refuerzo alcanza para detectar el espionaje.'
);

// 4. Sin ningún espía defendiendo el espionaje sigue pasando invisible para todos.
$blind = spyDetectionScouting(200, 0, 0);
spyDetectionAssert(
	$blind['casualties'] === 0 && !$automation->spyAttemptDetected(0, 0, $blind['casualties']),
	'Una aldea sin espías no detecta el espionaje.'
);

// 5. Las otras dos vías de detección se mantienen: espías atacantes muertos o capturados
// en trampas galas delatan al espía aunque el defensor no tuviera espías propios.
spyDetectionAssert(
	$automation->spyAttemptDetected(0, 0, 4),
	'Un espionaje que pierde espías se detecta aunque el defensor no tenga espías.'
);
spyDetectionAssert(
	$automation->spyAttemptDetected(0, 2, 0),
	'Un espionaje cuyos espías caen en trampas se detecta aunque el defensor no tenga espías.'
);

// 6. Un espionaje enviado sin espías pierde todas las tropas, así que se detecta incluso
// contra una aldea que no tiene un solo espía.
$noScouts = spyDetectionScouting(0, 0, 0, 5);
spyDetectionAssert(
	$noScouts['casualties'] === 5 && $automation->spyAttemptDetected(0, 0, $noScouts['casualties']),
	'Un espionaje enviado sin espías muere entero y se detecta aun sin espías defendiendo.'
);

echo "Todas las comprobaciones de detección de espionaje pasaron.\n";
