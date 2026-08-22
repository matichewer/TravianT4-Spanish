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
    public $productionAccruals = array();

    public function recountPop($villageId) {
        return 1;
    }

    protected function accrueProductionBeforeChange($villageId, $until) {
        $this->productionAccruals[] = array((int)$villageId, (int)$until);
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
catapultAssert(catapultNormalizeTarget(15, 9) === 0, 'rechaza un objetivo que la plaza todavía no desbloqueó');
catapultAssert(catapultNormalizeTarget(15, 10) === 15, 'acepta un objetivo desbloqueado por la plaza');
catapultAssert(catapultNormalizeTarget(23, 20) === 0, 'el escondite sólo puede recibir impactos aleatorios');
catapultAssert(catapultNormalizeTarget(42, 10) === 42, 'permite seleccionar el Gran taller');

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

$impactMethod->invoke($persistenceAutomation, 900, 1, 10000, 0, 1, 0, $targetVillage, 123456);
catapultAssert(
    $database->fields['f1'] === 0 && $database->fields['f1t'] === 1,
    'destruir un recurso conserva su tipo de campo'
);
catapultAssert(
    $persistenceAutomation->productionAccruals === array(array(900,123456)),
    'acredita la producción anterior antes de cambiar el campo'
);
$cancelledFieldQueue = false;
foreach($database->queries as $query) {
    if(strpos($query, 'bdata WHERE wid = 900 AND field = 1') !== false) $cancelledFieldQueue = true;
}
catapultAssert($cancelledFieldQueue, 'el impacto cancela la construcción pendiente de la casilla');

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

// --- Redacción del informe ------------------------------------------------------------
// El adjetivo concuerda con el nombre. Estaba escrito en masculino a mano, así que la
// mitad de los edificios del juego —los femeninos— salían con "destruido"/"dañado".
catapultAssert(
    buildingDamageSentence(2, 5, 0) === 'Barrera destruida.',
    'un edificio femenino se destruye en femenino'
);
catapultAssert(
    buildingDamageSentence(10, 5, 0) === 'Almacén destruido.',
    'y uno masculino en masculino'
);
catapultAssert(
    buildingDamageSentence(22, 5, 3) === 'Academia dañada del nivel <b>5</b> al nivel <b>3</b>.',
    'el daño parcial también concuerda'
);
catapultAssert(
    buildingDamageSentence(15, 5, 5) === 'El Edificio principal no sufrió daños.',
    'y el artículo del mensaje sin daño'
);
$genderMismatch = array();
foreach(array_keys(catapultTargetCatalog()) as $targetType) {
    $sentence = buildingDamageSentence($targetType, 5, 0);
    $name = buildingDisplayName($targetType);
    if($sentence !== $name.(buildingNameIsFeminine($targetType) ? ' destruida.' : ' destruido.')) {
        $genderMismatch[] = $name;
    }
}
catapultAssert(!$genderMismatch, 'todos los objetivos tienen género declarado');

// Un nombre por edificio: el desplegable de objetivos y el informe salían de listas
// distintas, así que se elegía "Excavación de barro" y el informe hablaba de la Barrera.
$catalog = catapultTargetCatalog();
catapultAssert(
    $catalog[2]['name'] === 'Barrera' && $catalog[4]['name'] === 'Granja',
    'el desplegable usa los mismos nombres que el resto del juego'
);
$building = new ReflectionClass('Automation');
$nameMethod = new ReflectionMethod('Automation', 'procResType');
$mismatchedNames = array();
foreach($catalog as $targetType => $meta) {
    if($nameMethod->invoke($persistenceAutomation, $targetType) !== $meta['name']) {
        $mismatchedNames[] = $targetType;
    }
}
catapultAssert(!$mismatchedNames, 'y los mismos que el informe (procResType)');

// Cada línea trae su ícono: con dos objetivos el informe mostraba sólo el del primero.
$database->fields['f1'] = 10;
$database->fields['f1t'] = 1;
$database->fields['f20'] = 5;
$database->fields['f20t'] = 15;
$twoTargets = $resolveMethod->invoke(
    $persistenceAutomation,
    array('to' => 900, 'ctar1' => 1, 'ctar2' => 15),
    array(4 => 10000, 5 => 1, 6 => 0),
    0,
    $targetVillage,
    0
);
catapultAssert(
    substr_count($twoTargets['report'], '<img') === 2,
    'el informe de dos objetivos trae un ícono por línea'
);
catapultAssert(
    strpos($twoTargets['report'], 'g1Icon') !== false && strpos($twoTargets['report'], 'g15Icon') !== false,
    'y cada ícono es el del edificio que le tocó'
);

// El ícono existe para todo lo que se puede destruir. El gpack numera con los gids del
// T4 oficial: la Herrería (12 acá) usa el arte del 13, y el Gran taller no tiene ícono
// propio en ningún gpack, así que cae en el genérico en vez de dejar un hueco.
// compact.css es el que carga html.tpl y hace @import de compact1.css: las reglas de los
// íconos están repartidas entre los dos, así que se miran juntos.
$iconCss = '';
foreach(array('compact.css', 'compact1.css') as $styleSheet) {
    $iconCss .= file_get_contents(dirname(__DIR__).'/gpack/travian_Travian_4.0_41/lang/ir/'.$styleSheet);
}
$missingIcons = array();
foreach(array_keys($catalog) as $targetType) {
    if(strpos($iconCss, 'img.'.buildingIconClass($targetType).'{') === false) {
        $missingIcons[] = buildingDisplayName($targetType);
    }
}
catapultAssert(!$missingIcons, 'todo objetivo tiene una clase de ícono definida en el gpack activo');
catapultAssert(buildingIconClass(12) === 'g13Icon', 'la Herrería usa el ícono del gid 13 del arte');
catapultAssert(buildingIconClass(42) === 'gebIcon', 'el Gran taller cae en el ícono genérico');

// Los informes viejos guardaron el texto sin ícono: hay que seguir dibujándoselo.
catapultAssert(
    strpos(catapultReportInfoHtml(15, 'Edificio principal destruido.'), 'g15Icon') !== false,
    'un informe viejo sigue mostrando su ícono'
);
catapultAssert(
    substr_count(catapultReportInfoHtml(1, buildingIconHtml(1).' Leñador destruido.'), '<img') === 1,
    'y uno nuevo no lo duplica'
);

$noticeTemplates = glob(dirname(__DIR__).'/Templates/Notice/*.tpl');
$staleIconTemplates = array();
foreach($noticeTemplates as $noticeTemplate) {
    $noticeSource = file_get_contents($noticeTemplate);
    if(strpos($noticeSource, 'unarray[$dataarray[153]]') !== false) {
        $staleIconTemplates[] = basename($noticeTemplate);
    }
}
catapultAssert(
    !$staleIconTemplates,
    'ninguna plantilla dibuja el ícono del edificio con el nombre de una unidad'
);

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
catapultAssert(
    strpos($automationSource,'ORDER BY endtime ASC, moveid ASC') !== false,
    'las oleadas empatadas se resuelven por identificador de movimiento'
);
catapultAssert(
    strpos($automationSource,'isPendingAttackMovement((int)$data[\'moveid\'])') !== false,
    'una instantánea no procesa movimientos eliminados por un ataque anterior'
);

echo "Catapult attack checks passed.\n";
