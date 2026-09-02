<?php
/**
 * Los ocho artefactos, enchufados al motor de punta a punta.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_artefact_integration.php
 *
 * Qué agrega sobre los otros tres checkers de artefactos. `check_artefact_effects.php` fija
 * la TABLA de valores y `check_artefact_activation.php` fija CUÁNDO un artefacto está
 * activo; los dos son cálculo puro. Lo que ninguno de los dos ve es el hueco que este repo
 * ya tuvo durante años: la tabla podía estar perfecta y el valor no llegar a ningún lado,
 * porque la consulta que los buscaba pedía una columna inexistente y devolvía vacío en
 * silencio. Acá se mide la SALIDA del motor con el artefacto y sin él, y se exige que
 * cambie.
 *
 * Para cada tipo se prueban los tres tamaños, y además los tres casos que apagan un
 * artefacto sin que nadie lo note: uno todavía sin madurar, uno desplazado del podio de
 * tres activos, y uno pequeño mirado desde otra aldea.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
if(!defined('TB_PREFIX')) {
    define('TB_PREFIX', 's1_');
}
if(!defined('SPEED')) {
    define('SPEED', 3);
}
if(!defined('WORLD_MAX')) {
    define('WORLD_MAX', 100);
}
if(!defined('INCREASE_SPEED')) {
    define('INCREASE_SPEED', 1);
}
if(!defined('CRANNY_CAPACITY')) {
    define('CRANNY_CAPACITY', 1);
}
if(!defined('TS_THRESHOLD')) {
    define('TS_THRESHOLD', 30);
}
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
// Lang/es.php interpola varias constantes de configuración en sus textos. Se declaran acá
// para que el checker no ensucie su propia salida con avisos que no son suyos.
foreach(array('SERVER_NAME' => 'checker', 'COMMENCE' => 0,
    'PW_MIN_LENGTH' => 4, 'USRNM_MIN_LENGTH' => 3) as $constant => $value) {
    if(!defined($constant)) {
        define($constant, $value);
    }
}

require $root.'/GameEngine/Data/buidata.php';
require $root.'/GameEngine/Data/unitdata.php';
require $root.'/GameEngine/Data/hero_full.php';
// Technology::getUnitName() lee las constantes U1..U50 del idioma; sin esto cada llamada
// tira un warning por constante indefinida.
require $root.'/GameEngine/Lang/es.php';
require_once $root.'/GameEngine/Artefact.php';
require_once $root.'/GameEngine/Hero.php';
require $root.'/GameEngine/Battle.php';
require $root.'/GameEngine/GeneratorX.php';
require $root.'/GameEngine/Automation.php';
require $root.'/GameEngine/Building.php';
require $root.'/GameEngine/Technology.php';

$failures = array();
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        echo '[FALLA] '.$message.PHP_EOL;
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

define('VILLAGE_A', 500);   // la aldea que guarda los artefactos pequeños
define('VILLAGE_B', 600);   // otra aldea de la misma cuenta
define('OWNER', 77);

/** Una fila de artefacto ya madura, salvo que se diga lo contrario. */
function art($type, $size, $village = VILLAGE_A, $id = 1, $age = 999999999) {
    return array(
        'id' => $id, 'type' => $type, 'size' => $size, 'vref' => $village,
        'owner' => OWNER, 'conquered' => max(0, time() - $age)
    );
}

/**
 * El doble de la capa de datos. Devuelve filas CRUDAS y deja que Artefact.php decida
 * cuáles están activas, que es exactamente lo que hace el motor de verdad: si el doble
 * resolviera la activación por su cuenta, el checker no probaría nada.
 */
class ArtefactDatabaseStub {
    public $artefacts = array();
    public $fields = array();
    public $village = array('maxstore' => 200000, 'maxcrop' => 200000);
    public $owner = OWNER;

    public function getArtefactsByOwner($owner) { return $this->artefacts; }
    public function getActiveArtefactsByOwner($owner) {
        return artefactActiveRows($this->getArtefactsByOwner($owner));
    }
    public function getArtefactEffectValue($vref, $owner, $type) {
        return artefactVillageEffectValue($this->getActiveArtefactsByOwner($owner), $type, $vref);
    }
    public function hasActiveArtefactEffect($vref, $owner, $type) {
        return artefactVillageHasEffect($this->getActiveArtefactsByOwner($owner), $type, $vref);
    }

    // Lo mínimo para que los caminos que se prueban corran.
    public function getResourceLevel($villageId) { return $this->fields; }
    public function setVillageLevel($villageId, $field, $value) { $this->fields[$field] = (int)$value; }
    public function getVillage($villageId) { return $this->village; }
    public function setVillageField($villageId, $field, $value) { $this->village[$field] = $value; }
    public function setVillageCapacity($villageId, $field, $value) { $this->village[$field] = $value; }
    public function getVillageField($villageId, $field) { return $field === 'owner' ? $this->owner : 0; }
    public function getVillagesID2($owner) { return array(array('wref' => VILLAGE_A)); }
    public function query($sql) { return true; }
    public function getHeroData($uid) { return array('dead' => 1, 'home' => 0, 'wref' => 0, 'speed' => 7); }

    // La guarnición por aldea: calculateBattle() arma el bando defensor desde acá.
    public $unitsByVillage = array();
    public function getUnit($vref) {
        return isset($this->unitsByVillage[(int)$vref]) ? $this->unitsByVillage[(int)$vref] : array();
    }
    public function getEnforceVillage($vref, $mode) { return array(); }
    public function getBuildingByField($wid, $field) { return array(); }
    public function getMasterJobs($wid) { return array(); }
    public function getDemolition($wid) { return array(); }
}

/**
 * Una clase de Automation con lo que toca disco anulado.
 *
 * `applyStorageCapacityDelta()` y `refreshEmbassyCapacity()` son privadas en Automation, así
 * que no se pueden sobrescribir: no hace falta, porque el doble de la base se traga sus
 * escrituras. Las que sí se anulan son las que necesitan tablas de verdad.
 */
class ArtefactAutomationStub extends Automation {
    public function recountPop($vid) { return 1; }
    protected function accrueProductionBeforeChange($villageId, $until) { return true; }
    public function syncTrapperCapacity($villageId) { return true; }
}

class ArtefactVillageStub {
    public $wid = VILLAGE_A;
    public $resarray = array();
    public $capital = 0;
    public $pop = 100;
}
class ArtefactSessionStub {
    public $uid = OWNER;
    public $tribe = 1;
    public $plus = 0;
}

$database = new ArtefactDatabaseStub();
$battle = new Battle();
$generator = new GeneratorX();
$GLOBALS['database'] = $database;
$GLOBALS['battle'] = $battle;
$GLOBALS['generator'] = $generator;
$GLOBALS['session'] = new ArtefactSessionStub();
$village = new ArtefactVillageStub();
$GLOBALS['village'] = $village;

$sizes = array(
    ARTEFACT_SIZE_SMALL  => 'pequeño',
    ARTEFACT_SIZE_LARGE  => 'grande',
    ARTEFACT_SIZE_UNIQUE => 'único'
);

// =====================================================================================
section('1. Secreto del arquitecto: los edificios aguantan más catapultas');
// =====================================================================================
//
// Se dispara la potencia justa para destruir un edificio de nivel 10 sin artefacto, y se
// exige que con el artefacto el edificio siga en pie.
$automationClass = new ReflectionClass('ArtefactAutomationStub');
$automation = $automationClass->newInstanceWithoutConstructor();
$impact = new ReflectionMethod('Automation', 'applyCatapultImpact');
$impact->setAccessible(true);

function baseFields() {
    $fields = array();
    for($slot = 1; $slot <= 40; $slot++) {
        $fields['f'.$slot] = 0;
        $fields['f'.$slot.'t'] = 0;
    }
    $fields['f99'] = 0;
    $fields['f99t'] = 0;
    $fields['f20'] = 10;
    $fields['f20t'] = 15;   // edificio principal nivel 10, el objetivo
    return $fields;
}

$required = $battle->calculateSiegeOutcome(0, 10, 0, 1, 1)['required'];
$database->artefacts = array();
$database->fields = baseFields();
$impact->invoke($automation, VILLAGE_A, 15, $required, 0, 1, 0, array('owner' => OWNER, 'capital' => 0));
check((int)$database->fields['f20'] === 0,
    'sin artefacto, la potencia justa destruye el edificio');

foreach($sizes as $size => $label) {
    $database->artefacts = array(art(ARTEFACT_ARCHITECT, $size));
    $database->fields = baseFields();
    $impact->invoke($automation, VILLAGE_A, 15, $required, 0, 1, 0, array('owner' => OWNER, 'capital' => 0));
    check((int)$database->fields['f20'] > 0,
        'con el secreto del arquitecto '.$label.' el edificio sobrevive al mismo disparo (quedó n'
            .(int)$database->fields['f20'].')');
}

// El más fuerte tiene que aguantar más que el más flojo: único (x5) > grande (x3).
$survivors = array();
foreach(array(ARTEFACT_SIZE_LARGE, ARTEFACT_SIZE_UNIQUE) as $size) {
    $database->artefacts = array(art(ARTEFACT_ARCHITECT, $size));
    $database->fields = baseFields();
    $impact->invoke($automation, VILLAGE_A, 15, $required * 3, 0, 1, 0, array('owner' => OWNER, 'capital' => 0));
    $survivors[$size] = (int)$database->fields['f20'];
}
check($survivors[ARTEFACT_SIZE_UNIQUE] >= $survivors[ARTEFACT_SIZE_LARGE],
    'el único (x5) aguanta al menos tanto como el grande (x3)');

// Un artefacto sin madurar no protege nada.
$database->artefacts = array(art(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_SMALL, VILLAGE_A, 1, 0));
$database->fields = baseFields();
$impact->invoke($automation, VILLAGE_A, 15, $required, 0, 1, 0, array('owner' => OWNER, 'capital' => 0));
check((int)$database->fields['f20'] === 0,
    'un artefacto recién capturado todavía no protege: el edificio cae igual');

// Y uno pequeño de OTRA aldea tampoco.
$database->artefacts = array(art(ARTEFACT_ARCHITECT, ARTEFACT_SIZE_SMALL, VILLAGE_B));
$database->fields = baseFields();
$impact->invoke($automation, VILLAGE_A, 15, $required, 0, 1, 0, array('owner' => OWNER, 'capital' => 0));
check((int)$database->fields['f20'] === 0,
    'un artefacto pequeño guardado en otra aldea no protege a ésta');

// =====================================================================================
section('2. Botas de los titanes: las tropas viajan más rápido');
// =====================================================================================
$from = array('x' => 0, 'y' => 0);
$to = array('x' => 20, 'y' => 0);
$database->artefacts = array();
$plain = $generator->procDistanceTime($from, $to, 6, 1, 0, 0,
    artefactTroopSpeedFactor($database, OWNER, VILLAGE_A));
check($plain > 0, 'sin artefacto el viaje tarda '.$plain.' segundos');

foreach(array(ARTEFACT_SIZE_SMALL => 2.0, ARTEFACT_SIZE_LARGE => 1.5, ARTEFACT_SIZE_UNIQUE => 2.0) as $size => $factor) {
    $database->artefacts = array(art(ARTEFACT_BOOTS, $size));
    $withArtefact = $generator->procDistanceTime($from, $to, 6, 1, 0, 0,
        artefactTroopSpeedFactor($database, OWNER, VILLAGE_A));
    check(abs($withArtefact - round($plain / $factor)) <= 1,
        'con las botas '.$sizes[$size].' el viaje pasa de '.$plain.' a '.$withArtefact
            .' segundos (esperado ~'.round($plain / $factor).')');
}

// Los mercaderes y los colonos van a velocidad fija: el modo 0 no mira el artefacto.
$database->artefacts = array(art(ARTEFACT_BOOTS, ARTEFACT_SIZE_SMALL));
check($generator->procDistanceTime($from, $to, 1, 0, 0, 0, 2.0)
    === $generator->procDistanceTime($from, $to, 1, 0, 0, 0, 1.0),
    'el artefacto no acelera a los mercaderes ni a los colonos (modo 0)');

// =====================================================================================
section('3. Ojos del águila: el espionaje');
// =====================================================================================
//
// Un ataque de puros exploradores contra un defensor con exploradores: sin artefacto el
// atacante pierde, con el artefacto gana. Es la salida real de calculateBattle().
function scoutBattle($battle, $attackerVillage, $defenderVillage) {
    global $database;
    $attacker = array();
    $defender = array();
    for($unit = 1; $unit <= 50; $unit++) {
        $attacker['u'.$unit] = 0;
        $defender['u'.$unit] = 0;
    }
    $attacker['u4'] = 100;    // explorador romano
    $defender['u14'] = 100;   // explorador germano
    // El bando defensor lo arma calculateBattle() leyendo la guarnición de la aldea.
    $database->unitsByVillage = array($defenderVillage => $defender);
    return $battle->calculateBattle(
        $attacker, array('id' => 1),
        0, 1, 2, 0, 100, 100, 1, array(), array(), 0, 0, 0,
        OWNER, 1, $attackerVillage, $defenderVillage
    );
}

$database->artefacts = array();
$plainScout = scoutBattle($battle, VILLAGE_A, VILLAGE_B);
check(isset($plainScout['Attack_points']) && $plainScout['Attack_points'] > 0,
    'sin artefacto el espionaje suma '.round($plainScout['Attack_points']).' puntos de ataque');

foreach(array(ARTEFACT_SIZE_SMALL => 5, ARTEFACT_SIZE_LARGE => 3, ARTEFACT_SIZE_UNIQUE => 10) as $size => $factor) {
    $database->artefacts = array(art(ARTEFACT_EAGLE, $size));
    $boosted = scoutBattle($battle, VILLAGE_A, VILLAGE_B);
    check(abs($boosted['Attack_points'] / max(1, $plainScout['Attack_points']) - $factor) < 0.01,
        'los ojos del águila '.$sizes[$size].' multiplican el espionaje del atacante por '.$factor);
}

// Y también defienden: el artefacto del DEFENSOR sube sus puntos de defensa.
$database->artefacts = array();
$plainDefence = scoutBattle($battle, VILLAGE_A, VILLAGE_B)['Defend_points'];
$database->artefacts = array(art(ARTEFACT_EAGLE, ARTEFACT_SIZE_UNIQUE, VILLAGE_B));
$boostedDefence = scoutBattle($battle, VILLAGE_A, VILLAGE_B)['Defend_points'];
check($boostedDefence > $plainDefence,
    'el artefacto del defensor también refuerza a sus exploradores ('
        .round($plainDefence).' -> '.round($boostedDefence).')');

// =====================================================================================
section('4. Control de dieta: las tropas comen menos');
// =====================================================================================
$database->artefacts = array();
check(artefactTroopUpkeep($database, OWNER, VILLAGE_A, 1000)['charged'] === 1000,
    'sin artefacto la aldea paga los 1.000 de manutención enteros');

foreach(array(ARTEFACT_SIZE_SMALL => 500, ARTEFACT_SIZE_LARGE => 750, ARTEFACT_SIZE_UNIQUE => 500) as $size => $charged) {
    $database->artefacts = array(art(ARTEFACT_DIET, $size));
    $detail = artefactTroopUpkeep($database, OWNER, VILLAGE_A, 1000);
    check($detail['charged'] === $charged,
        'con la dieta '.$sizes[$size].' paga '.$detail['charged'].' de 1.000 (esperado '.$charged.')');
    check($detail['saving'] === 1000 - $charged, 'y el ahorro que informa dorf1 cuadra');
}

// El pequeño sólo vale en su aldea; el grande vale en las dos.
$database->artefacts = array(art(ARTEFACT_DIET, ARTEFACT_SIZE_SMALL, VILLAGE_A));
check(artefactTroopUpkeep($database, OWNER, VILLAGE_B, 1000)['charged'] === 1000,
    'la dieta pequeña no le ahorra nada a la otra aldea');
$database->artefacts = array(art(ARTEFACT_DIET, ARTEFACT_SIZE_LARGE, VILLAGE_A));
check(artefactTroopUpkeep($database, OWNER, VILLAGE_B, 1000)['charged'] === 750,
    'pero la grande sí, porque es de cuenta');

// =====================================================================================
section('5. Talento del entrenador: el tiempo de entrenamiento');
// =====================================================================================
$technology = new Technology();
$GLOBALS['technology'] = $technology;
$database->artefacts = array();
check(abs($technology->getTrainingArtefactFactor() - 1) < 0.0001,
    'sin artefacto el tiempo de entrenamiento no cambia');
foreach(array(ARTEFACT_SIZE_SMALL => 0.5, ARTEFACT_SIZE_LARGE => 0.75, ARTEFACT_SIZE_UNIQUE => 0.5) as $size => $factor) {
    $database->artefacts = array(art(ARTEFACT_TRAINER, $size));
    check(abs($technology->getTrainingArtefactFactor() - $factor) < 0.0001,
        'con el entrenador '.$sizes[$size].' el tiempo queda en '.$factor);
}

// =====================================================================================
section('6. Plano de almacenamiento: el gran almacén y el gran granero');
// =====================================================================================
$building = (new ReflectionClass('Building'))->newInstanceWithoutConstructor();
$database->artefacts = array();
check($building->hasStorageArtefact() === false, 'sin plano no se puede construir el gran almacén');
$database->artefacts = array(art(ARTEFACT_STORAGE, ARTEFACT_SIZE_SMALL, VILLAGE_A));
check($building->hasStorageArtefact() === true, 'con el plano pequeño en esta aldea, sí');
$database->artefacts = array(art(ARTEFACT_STORAGE, ARTEFACT_SIZE_SMALL, VILLAGE_B));
check($building->hasStorageArtefact() === false, 'pero el plano pequeño de otra aldea no habilita ésta');
$database->artefacts = array(art(ARTEFACT_STORAGE, ARTEFACT_SIZE_LARGE, VILLAGE_B));
check($building->hasStorageArtefact() === true, 'y el grande sí, esté donde esté');
$database->artefacts = array(art(ARTEFACT_STORAGE, ARTEFACT_SIZE_LARGE, VILLAGE_B, 1, 0));
check($building->hasStorageArtefact() === false,
    'un plano recién capturado todavía no habilita nada: primero pasa el retardo');

// =====================================================================================
section('7. Confusión del rival: escondite y catapultas al azar');
// =====================================================================================
$crannyFields = baseFields();
$crannyFields['f21'] = 10;
$crannyFields['f21t'] = 23;   // escondite nivel 10
$database->artefacts = array();
$plainCranny = $automation->calculateCrannyProtection($crannyFields, 1, 2, 1);
check($plainCranny['capacity'] > 0, 'un escondite nivel 10 protege '.number_format($plainCranny['capacity']));

foreach(array(ARTEFACT_SIZE_SMALL => 200, ARTEFACT_SIZE_LARGE => 100, ARTEFACT_SIZE_UNIQUE => 500) as $size => $factor) {
    $database->artefacts = array(art(ARTEFACT_CONFUSION, $size));
    $value = $database->getArtefactEffectValue(VILLAGE_A, OWNER, ARTEFACT_CONFUSION);
    $boosted = $automation->calculateCrannyProtection($crannyFields, 1, 2, 1 * $value);
    check(abs($boosted['capacity'] / $plainCranny['capacity'] - $factor) < 0.01,
        'la confusión '.$sizes[$size].' multiplica el escondite por '.$factor);
}

// La otra mitad: las catapultas enemigas pierden la puntería.
$resolve = new ReflectionMethod('Automation', 'resolveCatapultAttacks');
$resolve->setAccessible(true);
$database->fields = baseFields();
$database->fields['f21'] = 10;
$database->fields['f21t'] = 23;
$attackData = array('to' => VILLAGE_A, 'from' => VILLAGE_B, 'ctar1' => 15, 'ctar2' => 0);

// El resultado de batalla que consume resolveCatapultAttacks(): [4] es la potencia de
// fuego, [5] el bono de moral y [6] el nivel de herrería.
$battleResult = array(4 => 5000, 5 => 1.0, 6 => 0);
$database->artefacts = array();
$plainTargets = $resolve->invoke($automation, $attackData, $battleResult, 0, array('owner' => OWNER), false);
check(is_array($plainTargets), 'sin artefacto el disparo dirigido se resuelve');

$database->artefacts = array(art(ARTEFACT_CONFUSION, ARTEFACT_SIZE_SMALL));
$source = file_get_contents($root.'/GameEngine/Automation.php');
check(preg_match('/\$confusion = \$this->artefactRow\(/', $source) === 1,
    'la confusión se resuelve con el mismo resolvedor que todo lo demás');
check(strpos($source, '$firstTarget !== 40 && ($unique || $firstTarget !== 27)') !== false,
    'el Tesoro y la Maravilla se siguen pudiendo apuntar, salvo el Tesoro con el único');

// =====================================================================================
section('8. Artefacto del necio: imita a otro y el efecto llega igual');
// =====================================================================================
//
// Se busca un necio cuya tirada sea el entrenador y se exige que el tiempo de
// entrenamiento cambie de verdad, no que la tirada "diga" que cambia.
$foolFound = false;
for($id = 1; $id <= 500 && !$foolFound; $id++) {
    $candidate = art(ARTEFACT_FOOL, ARTEFACT_SIZE_SMALL, VILLAGE_A, $id);
    $roll = artefactFoolRoll($candidate);
    if($roll['type'] !== ARTEFACT_TRAINER || $roll['penalty']) {
        continue;
    }
    $foolFound = true;
    $database->artefacts = array($candidate);
    check(abs($technology->getTrainingArtefactFactor() - 0.5) < 0.0001,
        'un necio que imita al entrenador acelera el entrenamiento de verdad (id '.$id.')');
    check(artefactVillageEffectValue($database->getActiveArtefactsByOwner(OWNER),
        ARTEFACT_TRAINER, VILLAGE_A) === 0.5,
        'y el resolvedor lo devuelve bajo el tipo imitado, no bajo el suyo');
}
check($foolFound, 'en 500 ids hay algún necio que imita al entrenador a favor');

// El necio en contra empeora: un entrenador invertido tarda el doble.
$badFool = null;
for($id = 1; $id <= 500 && $badFool === null; $id++) {
    $candidate = art(ARTEFACT_FOOL, ARTEFACT_SIZE_SMALL, VILLAGE_A, $id);
    $roll = artefactFoolRoll($candidate);
    if($roll['type'] === ARTEFACT_TRAINER && $roll['penalty']) {
        $badFool = $candidate;
    }
}
if($badFool !== null) {
    $database->artefacts = array($badFool);
    check(abs($technology->getTrainingArtefactFactor() - 2.0) < 0.0001,
        'un necio en contra imitando al entrenador DUPLICA el tiempo de entrenamiento');
} else {
    echo '[--] no salió ningún necio en contra imitando al entrenador en 500 ids'.PHP_EOL;
}

// El necio único nunca perjudica.
for($id = 1; $id <= 200; $id++) {
    $roll = artefactFoolRoll(art(ARTEFACT_FOOL, ARTEFACT_SIZE_UNIQUE, VILLAGE_A, $id));
    check($roll['penalty'] === false, 'el necio único nunca sale en contra (id '.$id.')');
}

// =====================================================================================
section('9. El podio de tres apaga lo que sobra, y se nota en el motor');
// =====================================================================================
//
// Cuatro artefactos de aldea: el cuarto no entra y su efecto NO puede aplicarse.
$database->artefacts = array(
    art(ARTEFACT_DIET,    ARTEFACT_SIZE_SMALL, VILLAGE_A, 1, 900000),
    art(ARTEFACT_TRAINER, ARTEFACT_SIZE_SMALL, VILLAGE_A, 2, 800000),
    art(ARTEFACT_EAGLE,   ARTEFACT_SIZE_SMALL, VILLAGE_A, 3, 700000),
    art(ARTEFACT_BOOTS,   ARTEFACT_SIZE_SMALL, VILLAGE_A, 4, 600000)
);
check(count($database->getActiveArtefactsByOwner(OWNER)) === 3, 'sólo tres quedan activos');
check(artefactTroopUpkeep($database, OWNER, VILLAGE_A, 1000)['charged'] === 500,
    'el más viejo (dieta) sí hace efecto');
check(abs($technology->getTrainingArtefactFactor() - 0.5) < 0.0001,
    'el segundo (entrenador) también');
check(abs(artefactTroopSpeedFactor($database, OWNER, VILLAGE_A) - 1.0) < 0.0001,
    'y el cuarto (botas) queda desplazado: la velocidad no cambia');

// Con uno de cuenta, el podio es 1 + 2.
$database->artefacts = array(
    art(ARTEFACT_DIET,    ARTEFACT_SIZE_LARGE, VILLAGE_A, 1, 900000),
    art(ARTEFACT_TRAINER, ARTEFACT_SIZE_LARGE, VILLAGE_A, 2, 800000),
    art(ARTEFACT_EAGLE,   ARTEFACT_SIZE_SMALL, VILLAGE_A, 3, 700000),
    art(ARTEFACT_BOOTS,   ARTEFACT_SIZE_SMALL, VILLAGE_A, 4, 600000)
);
check(artefactTroopUpkeep($database, OWNER, VILLAGE_A, 1000)['charged'] === 750,
    'el artefacto de cuenta más viejo entra');
check(abs($technology->getTrainingArtefactFactor() - 1.0) < 0.0001,
    'el segundo de cuenta NO: sólo uno de alcance cuenta puede estar activo');
check(abs(artefactTroopSpeedFactor($database, OWNER, VILLAGE_A) - 2.0) < 0.0001,
    'y los dos de aldea sí, porque ocupan huecos distintos');

// El de aldea pisa al de cuenta dentro de su aldea, y sólo ahí.
$database->artefacts = array(
    art(ARTEFACT_DIET, ARTEFACT_SIZE_UNIQUE, VILLAGE_B, 1, 900000),
    art(ARTEFACT_DIET, ARTEFACT_SIZE_LARGE,  VILLAGE_B, 9, 100000),
    art(ARTEFACT_DIET, ARTEFACT_SIZE_SMALL,  VILLAGE_A, 2, 800000)
);
check(artefactTroopUpkeep($database, OWNER, VILLAGE_A, 1000)['charged'] === 500,
    'en la aldea con el pequeño manda el pequeño (1/2)');
check(artefactTroopUpkeep($database, OWNER, VILLAGE_B, 1000)['charged'] === 500,
    'y en la otra manda el único, que también es 1/2');

echo PHP_EOL.(count($failures)
    ? count($failures).' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Artefactos enchufados al motor: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit(count($failures) ? 1 : 0);
