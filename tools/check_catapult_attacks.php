<?php
error_reporting(E_ALL);

function catapultAssert($condition, $message) {
    if(!$condition) {
        echo "FAIL: ".$message."\n";
        exit(1);
    }
    echo "OK: ".$message."\n";
}

require dirname(__DIR__).'/GameEngine/Battle.php';
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
require dirname(__DIR__).'/GameEngine/Automation.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';

class CatapultPersistenceDatabaseStub {
    public $fields = array();
    public $village = array('maxstore' => 2000, 'maxcrop' => 2000);
    public $queries = array();
    public $artefacts = array();

    public function getResourceLevel($villageId) {
        return $this->fields;
    }

    public function setVillageLevel($villageId, $field, $value) {
        $this->fields[$field] = (int)$value;
    }

    public function getVillage($villageId) {
        return $this->village;
    }

    public function setVillageField($villageId, $field, $value) {
        $this->village[$field] = $value;
    }

    public function setVillageCapacity($villageId, $field, $value) {
        $this->village[$field] = $value;
    }

    public function getVillageField($villageId, $field) {
        return $field === 'owner' ? 77 : 0;
    }

    public function getVillagesID2($owner) {
        return array(array('wref' => 900));
    }

    public function query($sql) {
        $this->queries[] = $sql;
        return true;
    }

    public function getActiveArtefactsByType($villageId, $owner, $type) {
        return isset($this->artefacts[$type]) ? $this->artefacts[$type] : array();
    }
}

class CatapultAutomationForTest extends Automation {
    public function recountPop($villageId) {
        return 1;
    }
}

$battle = new Battle();
$expectedUnits = array(1 => 8, 2 => 18, 3 => 28, 5 => 48);
foreach($expectedUnits as $tribe => $unitId) {
    catapultAssert(
        $battle->getTribeCatapultUnit($tribe) === $unitId,
        'reconoce la catapulta de la tribu '.$tribe
    );
}
catapultAssert(!$battle->isCatapultUnit(38), 'no reconoce al Cocodrilo de Naturaleza como catapulta');
catapultAssert($battle->getTribeCatapultUnit(4) === 0, 'Naturaleza no obtiene unidad de catapulta');

$firing = $battle->calculateSiegeFiring(100, 0.25, 1000, 500);
catapultAssert(is_finite($firing) && $firing > 0 && $firing < 75, 'calcula potencia de disparo finita tras las bajas');
catapultAssert(
    $battle->calculateSiegeFiring(100, 1, 1000, 500) === 0.0,
    'no dispara cuando no sobrevive ninguna catapulta'
);

$baseline = $battle->calculateSiegeOutcome(0, 10, 0, 1, 1);
$destroyed = $battle->calculateSiegeOutcome($baseline['required'], 10, 0, 1, 1);
$partial = $battle->calculateSiegeOutcome($baseline['required'] / 2, 10, 0, 1, 1);
$protected = $battle->calculateSiegeOutcome($baseline['required'] / 2, 10, 0, 1, 2);
$upgraded = $battle->calculateSiegeOutcome($baseline['required'] / 2, 10, 20, 1, 1);
catapultAssert($baseline['level_after'] === 10, 'cero potencia no modifica el objetivo');
catapultAssert($destroyed['level_after'] === 0, 'la potencia exacta requerida destruye el objetivo');
catapultAssert($partial['level_after'] > 0 && $partial['level_after'] < 10, 'la potencia parcial reduce niveles');
catapultAssert($protected['level_after'] >= $partial['level_after'], 'el taller de cantería reduce el daño');
catapultAssert($upgraded['level_after'] <= $partial['level_after'], 'la mejora de herrería aumenta el daño');

$simulateMethod = new ReflectionMethod('Battle', 'simulate');
$simulateMethod->setAccessible(true);
foreach(array(1 => 'romanos', 2 => 'germanos', 3 => 'galos') as $tribe => $tribeName) {
    $simulation = array(
        'a1_v' => $tribe,
        'tribe' => 3,
        'a1_hero' => 0,
        'h_att_power' => 0,
        'h_att_health' => 100,
        'h_off_bonus' => 0,
        'ew1' => 100,
        'ew2' => 100,
        'ktyp' => 0,
        'kata' => 10,
        'stonemason' => 0,
        'palast' => 0
    );
    for($position = 1; $position <= 10; $position++) {
        $simulation['a1_'.$position] = $position === 8 ? 100 : 0;
        $simulation['f1_'.$position] = 0;
    }
    for($unit = 1; $unit <= 40; $unit++) {
        $simulation['a2_'.$unit] = 0;
        $simulation['f2_'.$unit] = 0;
    }
    for($defenderTribe = 1; $defenderTribe <= 3; $defenderTribe++) {
        $simulation['a2_hero_'.$defenderTribe] = 0;
        $simulation['h_def_power_'.$defenderTribe] = 0;
        $simulation['h_def_bonus_'.$defenderTribe] = 0;
        $simulation['h_def_health_'.$defenderTribe] = 100;
    }
    for($wallTribe = 1; $wallTribe <= 4; $wallTribe++) {
        $simulation['wall'.$wallTribe] = 0;
    }
    $simulationResult = $simulateMethod->invoke($battle, $simulation);
    catapultAssert(
        isset($simulationResult[4], $simulationResult['target_level_after'])
            && $simulationResult[4] > 0 && $simulationResult['target_level_after'] === 0,
        'las catapultas '.$tribeName.' destruyen un objetivo de nivel 10 sin defensa'
    );
}

$automationClass = new ReflectionClass('Automation');
$automation = $automationClass->newInstanceWithoutConstructor();
$fields = array();
foreach(array_merge(range(1, 40), array(99)) as $slot) {
    $fields['f'.$slot] = 0;
    $fields['f'.$slot.'t'] = 0;
}
$fields['f1'] = 10;
$fields['f1t'] = 1;
$fields['f20'] = 5;
$fields['f20t'] = 15;
$fields['f99'] = 1;
$fields['f99t'] = 40;

catapultAssert($automation->selectCatapultTargetSlot($fields, 1) === 1, 'selecciona un campo de recurso explícito');
catapultAssert($automation->selectCatapultTargetSlot($fields, 15) === 20, 'selecciona un edificio explícito');
catapultAssert($automation->selectCatapultTargetSlot($fields, 40) === 99, 'selecciona la Maravilla en la casilla 99');

$wallOnly = array('f40' => 20, 'f40t' => 31);
catapultAssert($automation->selectCatapultTargetSlot($wallOnly, 0) === 0, 'el objetivo aleatorio nunca selecciona la muralla');

$validSlots = array(1, 20, 99);
$randomTargetsAreValid = true;
$missingTargetsAreValid = true;
for($attempt = 0; $attempt < 100; $attempt++) {
    $randomTargetsAreValid = $randomTargetsAreValid
        && in_array($automation->selectCatapultTargetSlot($fields, 0), $validSlots, true);
    $missingTargetsAreValid = $missingTargetsAreValid
        && in_array($automation->selectCatapultTargetSlot($fields, 30), $validSlots, true);
}
catapultAssert($randomTargetsAreValid, 'el objetivo aleatorio siempre pertenece a una casilla ocupada');
catapultAssert($missingTargetsAreValid, 'un tipo ausente siempre cae en una casilla ocupada');

$emptyFields = array();
catapultAssert($automation->selectCatapultTargetSlot($emptyFields, 0) === 0, 'una aldea sin objetivos no produce índices inválidos');

$database = new CatapultPersistenceDatabaseStub();
$battle = new Battle();
$database->fields = $fields;
$database->fields['f21'] = 1;
$database->fields['f21t'] = 10;
$database->fields['f22'] = 3;
$database->fields['f22t'] = 18;
$automationClass = new ReflectionClass('CatapultAutomationForTest');
$persistenceAutomation = $automationClass->newInstanceWithoutConstructor();
$impactMethod = new ReflectionMethod('Automation', 'applyCatapultImpact');
$impactMethod->setAccessible(true);
$targetVillage = array('owner' => 77, 'capital' => 1);

$database->fields['f23'] = 5;
$database->fields['f23t'] = 14;
$requiredForFive = $battle->calculateSiegeOutcome(0, 5, 0, 1, 1)['required'];
$database->artefacts[1] = array(array('size' => 1));
$impactMethod->invoke($persistenceAutomation, 900, 14, $requiredForFive, 0, 1, 0, $targetVillage);
catapultAssert($database->fields['f23'] > 0, 'el artefacto del arquitecto aumenta la resistencia del edificio');
$database->artefacts = array();

$impactMethod->invoke($persistenceAutomation, 900, 15, 10000, 0, 1, 0, $targetVillage);
catapultAssert(
    $database->fields['f20'] === 0 && $database->fields['f20t'] === 0,
    'destruir un edificio limpia nivel y tipo'
);

$impactMethod->invoke($persistenceAutomation, 900, 1, 10000, 0, 1, 0, $targetVillage);
catapultAssert(
    $database->fields['f1'] === 0 && $database->fields['f1t'] === 1,
    'destruir un recurso conserva su tipo de campo'
);

$impactMethod->invoke($persistenceAutomation, 900, 10, 10000, 0, 1, 0, $targetVillage);
catapultAssert(
    (int)$database->village['maxstore'] === 800,
    'destruir un almacén actualiza la capacidad y respeta el mínimo'
);

$impactMethod->invoke($persistenceAutomation, 900, 18, 10000, 0, 1, 0, $targetVillage);
$allianceCapacityUpdated = false;
foreach($database->queries as $query) {
    if(strpos($query, 'alidata SET max = 0 WHERE leader = 77') !== false) {
        $allianceCapacityUpdated = true;
    }
}
catapultAssert($allianceCapacityUpdated, 'destruir una embajada actualiza la capacidad de alianza');

$database->fields['f1'] = 10;
$database->fields['f1t'] = 1;
$database->fields['f20'] = 5;
$database->fields['f20t'] = 15;
$resolveMethod = new ReflectionMethod('Automation', 'resolveCatapultAttacks');
$resolveMethod->setAccessible(true);
$resolution = $resolveMethod->invoke(
    $persistenceAutomation,
    array('to' => 900, 'ctar1' => 1, 'ctar2' => 15),
    array(4 => 10000, 5 => 1, 6 => 0),
    0,
    $targetVillage,
    0
);
catapultAssert(
    $database->fields['f1'] === 0 && $database->fields['f20'] === 0,
    'el doble objetivo divide el disparo y persiste ambos impactos'
);
catapultAssert(
    strpos($resolution['report'], '1,') === 0 && strpos($resolution['report'], '<br>') !== false,
    'el doble impacto conserva el formato del reporte'
);

echo "Catapult attack checks passed.\n";
