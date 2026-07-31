<?php
/**
 * Auditoría completa del Almacén (bid10), Granero (bid11),
 * Gran almacén (bid38) y Gran granero (bid39).
 *
 * Ejecutar:  docker compose exec web php tools/check_storage_buildings.php
 *
 * Cubre:
 *   A. Integridad de las tablas de datos (capacidad, costos, tiempos, pop/cp).
 *   B. Recálculo autoritativo de capacidad (Automation::updateStore).
 *   C. Deltas incrementales al terminar una construcción (Automation::buildComplete).
 *   D. Deltas al terminar una demolición (Automation::demolitionComplete).
 *   E. Deltas al destruir con catapultas (Automation::updateCatapultCapacity).
 *   F. Recorte de recursos por desborde (Automation::pruneResource).
 *   G. Requisitos de construcción (Building::meetRequirement).
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$GLOBALS['checks'] = 0;
$GLOBALS['fails'] = array();

function check($condition, $message) {
    $GLOBALS['checks']++;
    if($condition) {
        return true;
    }
    $GLOBALS['fails'][] = $message;
    echo "  FAIL  ".$message."\n";
    return false;
}

function section($title) {
    echo "\n== ".$title." ==\n";
}

// ---------------------------------------------------------------------------
// Bootstrap mínimo: constantes + datos, sin tocar la base real.
// ---------------------------------------------------------------------------
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
// STORAGE_MULTIPLIER=<n> permite auditar un servidor con capacidad multiplicada.
define('STORAGE_MULTIPLIER', (int)(getenv('STORAGE_MULTIPLIER') ?: 1));
define('STORAGE_BASE', 800 * STORAGE_MULTIPLIER);
define('ALLOW_BURST', false);
define('SPEED', 1);
define('TRAPPER_CAPACITY', 1);
define('CRANNY_CAPACITY', 1);

require dirname(__FILE__).'/../GameEngine/Data/buidata.php';

// Shims de las funciones legacy mysql_* que usa Automation::updateStore().
$GLOBALS['sqlFdata'] = array();
$GLOBALS['sqlUpdates'] = array();
$GLOBALS['sqlCursor'] = 0;

function mysql_query($sql) {
    if(stripos($sql, 'SELECT * FROM `'.TB_PREFIX.'fdata`') === 0) {
        $GLOBALS['sqlCursor'] = 0;
        return 'fdata';
    }
    if(stripos($sql, 'UPDATE') === 0) {
        if(preg_match('/`maxstore` = ([0-9.]+), `maxcrop` = ([0-9.]+) WHERE `wref` = (\d+)/', $sql, $m)) {
            $GLOBALS['sqlUpdates'][(int)$m[3]] = array((float)$m[1], (float)$m[2]);
        }
        return true;
    }
    return true;
}

function mysql_fetch_assoc($result) {
    if($result !== 'fdata') {
        return false;
    }
    $rows = $GLOBALS['sqlFdata'];
    if($GLOBALS['sqlCursor'] >= count($rows)) {
        return false;
    }
    return $rows[$GLOBALS['sqlCursor']++];
}

function mysql_error() { return ''; }

require dirname(__FILE__).'/../GameEngine/Automation.php';

/**
 * Doble de la capa de datos: sólo lo que tocan los caminos de almacenamiento.
 */
class StorageDatabaseStub {
    public $villages = array();   // wref => array(maxstore, maxcrop, wood, clay, iron, crop, owner)
    public $fields = array();     // vref => array('f19' => lvl, 'f19t' => tipo, ...)
    public $bdata = array();      // cola de construcción
    public $demolition = array();
    public $queries = array();

    public function getVillage($vid) {
        return isset($this->villages[$vid]) ? $this->villages[$vid] : false;
    }
    public function getVillageField($vid, $field) {
        return isset($this->villages[$vid][$field]) ? $this->villages[$vid][$field] : 0;
    }
    public function setVillageField($vid, $field, $value) {
        $this->villages[$vid][$field] = $value;
    }
    public function getResourceLevel($vid) {
        return isset($this->fields[$vid]) ? $this->fields[$vid] : array();
    }
    public function setVillageLevel($vid, $field, $value) {
        $this->fields[$vid][$field] = (int)$value;
    }
    public function getFieldLevel($vid, $slot) {
        return isset($this->fields[$vid]['f'.$slot]) ? (int)$this->fields[$vid]['f'.$slot] : 0;
    }
    public function getFieldType($vid, $slot) {
        return isset($this->fields[$vid]['f'.$slot.'t']) ? (int)$this->fields[$vid]['f'.$slot.'t'] : 0;
    }
    public function query_return($q) {
        if(strpos($q, TB_PREFIX.'bdata') !== false) {
            return $this->bdata;
        }
        if(strpos($q, TB_PREFIX.'vdata') !== false) {
            return $this->pruneSelect($q);
        }
        return array();
    }
    private function pruneSelect($q) {
        $out = array();
        foreach($this->villages as $wref => $v) {
            $v['wref'] = $wref;
            if(strpos($q, 'maxstore < 800') !== false) {
                if($v['maxstore'] < 800 || $v['maxcrop'] < 800) { $out[] = $v; }
            } elseif(strpos($q, 'wood > maxstore') !== false) {
                if($v['wood'] > $v['maxstore'] || $v['clay'] > $v['maxstore']
                    || $v['iron'] > $v['maxstore'] || $v['crop'] > $v['maxcrop']) { $out[] = $v; }
            } elseif(strpos($q, 'wood < 0') !== false) {
                if($v['wood'] < 0 || $v['clay'] < 0 || $v['iron'] < 0 || $v['crop'] < 0) { $out[] = $v; }
            }
        }
        return $out;
    }
    public function query($q) {
        $this->queries[] = $q;
        // UPDATE ... fdata SET fN=lvl[,fNt=0] WHERE vref=X
        if(preg_match('/'.TB_PREFIX.'fdata SET f(\d+)=(-?\d+)(,f\d+t=0)? WHERE vref=(\d+)/', $q, $m)) {
            $this->fields[(int)$m[4]]['f'.$m[1]] = (int)$m[2];
            if(!empty($m[3])) { $this->fields[(int)$m[4]]['f'.$m[1].'t'] = 0; }
            return true;
        }
        // UPDATE ... fdata set fN = lvl, fNt = tipo where vref = X
        if(preg_match('/'.TB_PREFIX.'fdata set f(\d+) = (\d+), f\d+t = (\d+) where vref = (\d+)/', $q, $m)) {
            $this->fields[(int)$m[4]]['f'.$m[1]] = (int)$m[2];
            $this->fields[(int)$m[4]]['f'.$m[1].'t'] = (int)$m[3];
            return true;
        }
        // UPDATE ... vdata SET `maxstore`=`maxstore`-A+B WHERE wref=X
        if(preg_match('/vdata` SET `(maxstore|maxcrop)`=`\\1`-([0-9.]+)\+([0-9.]+) WHERE wref=(\d+)/', $q, $m)) {
            $this->villages[(int)$m[4]][$m[1]] -= (float)$m[2];
            $this->villages[(int)$m[4]][$m[1]] += (float)$m[3];
            return true;
        }
        // UPDATE ... vdata SET `maxstore`=BASE WHERE `maxstore`<= BASE AND wref=X
        if(preg_match('/vdata SET `(maxstore|maxcrop)`=([0-9.]+) WHERE `\\1`<=\s*([0-9.]+) AND wref=(\d+)/', $q, $m)) {
            if($this->villages[(int)$m[4]][$m[1]] <= (float)$m[3]) {
                $this->villages[(int)$m[4]][$m[1]] = (float)$m[2];
            }
            return true;
        }
        // UPDATE ... vdata set maxstore = A, maxcrop = B where wref = X
        if(preg_match('/vdata set maxstore = ([0-9.]+), maxcrop = ([0-9.]+) where wref = (\d+)/', $q, $m)) {
            $this->villages[(int)$m[3]]['maxstore'] = (float)$m[1];
            $this->villages[(int)$m[3]]['maxcrop'] = (float)$m[2];
            return true;
        }
        // UPDATE ... vdata set wood = A, clay = B, iron = C, crop = D where wref = X
        if(preg_match('/vdata set wood = (-?[0-9.]*), clay = (-?[0-9.]*), iron = (-?[0-9.]*), crop = (-?[0-9.]*) where wref = (\d+)/', $q, $m)) {
            $this->villages[(int)$m[5]]['wood'] = (float)$m[1];
            $this->villages[(int)$m[5]]['clay'] = (float)$m[2];
            $this->villages[(int)$m[5]]['iron'] = (float)$m[3];
            $this->villages[(int)$m[5]]['crop'] = (float)$m[4];
            return true;
        }
        return true;
    }
    public function getDemolition($vid = 0) { return $this->demolition; }
    public function delDemolition($vid) { $this->demolition = array(); }
    public function modifyPop($vid, $pop, $mode) {}
    public function addCP($vid, $cp) {}
    public function syncClimberPopulation($uid) {}
    public function getUserField($uid, $field, $x) { return 1; }
    public function getVillagesID2($uid) { return array(); }
}

$database = new StorageDatabaseStub();
$GLOBALS['database'] = $database;
$GLOBALS['storageDb'] = $database;

$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();

function callPrivate($object, $method, array $args = array()) {
    $ref = new ReflectionMethod(get_class($object), $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}

// ---------------------------------------------------------------------------
// A. Integridad de las tablas de datos
// ---------------------------------------------------------------------------
section('A. Tablas de datos (bid10 almacén, bid11 granero, bid38/39 grandes)');

// Capacidades oficiales T4 del almacén/granero, niveles 1..20.
$officialCapacity = array(
    1 => 1200, 2 => 1700, 3 => 2300, 4 => 3100, 5 => 4000,
    6 => 5000, 7 => 6300, 8 => 7800, 9 => 9600, 10 => 11800,
    11 => 14400, 12 => 17600, 13 => 21400, 14 => 25900, 15 => 31300,
    16 => 37900, 17 => 45700, 18 => 55100, 19 => 66400, 20 => 80000,
);
// Costo oficial T4 del almacén (bid10), niveles 1..20.
$officialWarehouseCost = array(
    1 => array(130, 160, 90, 40),        2 => array(165, 205, 115, 50),
    3 => array(215, 260, 145, 65),       4 => array(275, 335, 190, 85),
    5 => array(350, 430, 240, 105),      6 => array(445, 550, 310, 135),
    7 => array(570, 705, 395, 175),      8 => array(730, 900, 505, 225),
    9 => array(935, 1150, 650, 290),     10 => array(1200, 1475, 830, 370),
    11 => array(1535, 1890, 1065, 470),  12 => array(1965, 2420, 1360, 605),
    13 => array(2515, 3095, 1740, 775),  14 => array(3220, 3960, 2230, 990),
    15 => array(4120, 5070, 2850, 1270), 16 => array(5275, 6490, 3650, 1625),
    17 => array(6750, 8310, 4675, 2075), 18 => array(8640, 10635, 5980, 2660),
    19 => array(11060, 13610, 7655, 3405), 20 => array(14155, 17420, 9800, 4355),
);
// Costo oficial T4 del granero (bid11), niveles 1..20.
$officialGranaryCost = array(
    1 => array(80, 100, 70, 20),        2 => array(100, 130, 90, 25),
    3 => array(130, 165, 115, 35),      4 => array(170, 210, 145, 40),
    5 => array(215, 270, 190, 55),      6 => array(275, 345, 240, 70),
    7 => array(350, 440, 310, 90),      8 => array(450, 565, 395, 115),
    9 => array(575, 720, 505, 145),     10 => array(740, 920, 645, 185),
    11 => array(945, 1180, 825, 235),   12 => array(1210, 1510, 1060, 300),
    13 => array(1545, 1935, 1355, 385), 14 => array(1980, 2475, 1735, 495),
    15 => array(2535, 3170, 2220, 635), 16 => array(3245, 4055, 2840, 810),
    17 => array(4155, 5190, 3635, 1040), 18 => array(5315, 6645, 4650, 1330),
    19 => array(6805, 8505, 5955, 1700), 20 => array(8710, 10890, 7620, 2180),
);
$officialPop = array(1,1,1,1,1,1,1,1,1,1,2,2,2,2,2,2,2,2,2,2);
$officialCp  = array(1,1,2,2,2,3,4,4,5,6,7,9,11,13,15,18,22,27,32,38);

foreach(array(10 => 'Almacén', 11 => 'Granero', 38 => 'Gran almacén', 39 => 'Gran granero') as $tid => $label) {
    $data = $GLOBALS['bid'.$tid];
    check(count($data) === 20, $label." (bid$tid): debe tener 20 niveles, tiene ".count($data));
    check(array_keys($data) === range(1, 20), $label." (bid$tid): las claves deben ser 1..20");

    $factor = ($tid === 38 || $tid === 39) ? 30 : 1;
    for($lvl = 1; $lvl <= 20; $lvl++) {
        $expected = $officialCapacity[$lvl] * $factor;
        check((int)$data[$lvl]['attri'] === $expected,
            $label." nivel $lvl: capacidad esperada $expected, encontrada ".$data[$lvl]['attri']);
        check((int)$data[$lvl]['pop'] === $officialPop[$lvl - 1],
            $label." nivel $lvl: población esperada ".$officialPop[$lvl - 1].", encontrada ".$data[$lvl]['pop']);
        check((int)$data[$lvl]['cp'] === $officialCp[$lvl - 1],
            $label." nivel $lvl: puntos de cultura esperados ".$officialCp[$lvl - 1].", encontrados ".$data[$lvl]['cp']);
        if($lvl > 1) {
            check($data[$lvl]['attri'] > $data[$lvl - 1]['attri'],
                $label." nivel $lvl: la capacidad debe crecer respecto al nivel ".($lvl - 1));
            check($data[$lvl]['time'] > $data[$lvl - 1]['time'],
                $label." nivel $lvl: el tiempo debe crecer respecto al nivel ".($lvl - 1));
            foreach(array('wood', 'clay', 'iron', 'crop') as $res) {
                check($data[$lvl][$res] > $data[$lvl - 1][$res],
                    $label." nivel $lvl: el costo de $res debe crecer respecto al nivel ".($lvl - 1));
            }
        }
    }
}

// Costos exactos contra los valores oficiales T4.
foreach(array(10 => array('Almacén', $officialWarehouseCost), 11 => array('Granero', $officialGranaryCost)) as $tid => $spec) {
    list($label, $table) = $spec;
    $data = $GLOBALS['bid'.$tid];
    for($lvl = 1; $lvl <= 20; $lvl++) {
        $names = array('wood', 'clay', 'iron', 'crop');
        foreach($names as $i => $res) {
            check((int)$data[$lvl][$res] === $table[$lvl][$i],
                $label." nivel $lvl: costo de $res esperado ".$table[$lvl][$i].", encontrado ".$data[$lvl][$res]);
        }
    }
}

// El costo debe seguir la progresión geométrica 1.28 del juego.
// Debajo de ~100 el redondeo del original distorsiona la razón, así que no se exige.
foreach(array(10 => 'Almacén', 11 => 'Granero', 38 => 'Gran almacén', 39 => 'Gran granero') as $tid => $label) {
    $data = $GLOBALS['bid'.$tid];
    for($lvl = 2; $lvl <= 20; $lvl++) {
        foreach(array('wood', 'clay', 'iron', 'crop') as $res) {
            if($data[$lvl - 1][$res] < 100) {
                continue;
            }
            $ratio = $data[$lvl][$res] / $data[$lvl - 1][$res];
            check($ratio > 1.24 && $ratio < 1.32,
                $label." nivel $lvl: el costo de $res crece x".round($ratio, 3)." (se espera ~1.28)");
        }
    }
}

// Almacén y granero comparten capacidad; el gran almacén/granero es 30x.
for($lvl = 1; $lvl <= 20; $lvl++) {
    check($bid10[$lvl]['attri'] === $bid11[$lvl]['attri'],
        "Nivel $lvl: almacén y granero deben tener la misma capacidad");
    check($bid38[$lvl]['attri'] === $bid39[$lvl]['attri'],
        "Nivel $lvl: gran almacén y gran granero deben tener la misma capacidad");
}

// ---------------------------------------------------------------------------
// B. Recálculo autoritativo (updateStore)
// ---------------------------------------------------------------------------
section('B. Automation::updateStore() — recálculo de maxstore/maxcrop');

function runUpdateStore($automation, array $fdataRows) {
    $GLOBALS['sqlFdata'] = $fdataRows;
    $GLOBALS['sqlUpdates'] = array();
    callPrivate($automation, 'updateStore');
    return $GLOBALS['sqlUpdates'];
}

function emptyFields($vref) {
    $row = array('vref' => $vref);
    for($i = 1; $i <= 40; $i++) {
        $row['f'.$i] = 0;
        $row['f'.$i.'t'] = 0;
    }
    return $row;
}

// Aldea sin almacén ni granero: capacidad base.
$row = emptyFields(1);
$out = runUpdateStore($automation, array($row));
check($out[1][0] == STORAGE_BASE, "Sin almacén: maxstore debe ser ".STORAGE_BASE.", es ".$out[1][0]);
check($out[1][1] == STORAGE_BASE, "Sin granero: maxcrop debe ser ".STORAGE_BASE.", es ".$out[1][1]);

// Un almacén en cada nivel.
for($lvl = 1; $lvl <= 20; $lvl++) {
    $row = emptyFields(1);
    $row['f19'] = $lvl; $row['f19t'] = 10;
    $row['f20'] = $lvl; $row['f20t'] = 11;
    $out = runUpdateStore($automation, array($row));
    check($out[1][0] == $officialCapacity[$lvl] * STORAGE_MULTIPLIER,
        "Almacén nivel $lvl solo: maxstore esperado ".$officialCapacity[$lvl].", obtenido ".$out[1][0]);
    check($out[1][1] == $officialCapacity[$lvl] * STORAGE_MULTIPLIER,
        "Granero nivel $lvl solo: maxcrop esperado ".$officialCapacity[$lvl].", obtenido ".$out[1][1]);
}

// Dos almacenes y dos graneros: las capacidades se suman.
$row = emptyFields(1);
$row['f19'] = 20; $row['f19t'] = 10;
$row['f20'] = 7;  $row['f20t'] = 10;
$row['f21'] = 20; $row['f21t'] = 11;
$row['f22'] = 3;  $row['f22t'] = 11;
$out = runUpdateStore($automation, array($row));
check($out[1][0] == (80000 + 6300) * STORAGE_MULTIPLIER, "Dos almacenes (20+7): maxstore esperado 86300, obtenido ".$out[1][0]);
check($out[1][1] == (80000 + 2300) * STORAGE_MULTIPLIER, "Dos graneros (20+3): maxcrop esperado 82300, obtenido ".$out[1][1]);

// Gran almacén / gran granero.
$row = emptyFields(1);
$row['f19'] = 20; $row['f19t'] = 38;
$row['f20'] = 20; $row['f20t'] = 39;
$out = runUpdateStore($automation, array($row));
check($out[1][0] == 2400000 * STORAGE_MULTIPLIER, "Gran almacén nivel 20: maxstore esperado 2400000, obtenido ".$out[1][0]);
check($out[1][1] == 2400000 * STORAGE_MULTIPLIER, "Gran granero nivel 20: maxcrop esperado 2400000, obtenido ".$out[1][1]);

// Mezcla de almacén normal y gran almacén.
$row = emptyFields(1);
$row['f19'] = 20; $row['f19t'] = 10;
$row['f20'] = 5;  $row['f20t'] = 38;
$out = runUpdateStore($automation, array($row));
check($out[1][0] == (80000 + 120000) * STORAGE_MULTIPLIER,
    "Almacén 20 + gran almacén 5: maxstore esperado 200000, obtenido ".$out[1][0]);

// Los slots 19..38 son los válidos para edificios; ninguno debe quedar fuera del recálculo.
for($slot = 19; $slot <= 38; $slot++) {
    $row = emptyFields(1);
    $row['f'.$slot] = 20; $row['f'.$slot.'t'] = 10;
    $out = runUpdateStore($automation, array($row));
    check($out[1][0] == 80000 * STORAGE_MULTIPLIER, "Almacén nivel 20 en el slot $slot: maxstore esperado 80000, obtenido ".$out[1][0]);
}

// ---------------------------------------------------------------------------
// C. Deltas al completar una construcción (buildComplete)
// ---------------------------------------------------------------------------
section('C. Automation::buildComplete() — capacidad al terminar de construir');

/**
 * Sube un edificio de almacenamiento nivel por nivel desde 0 y comprueba
 * que la capacidad acumulada coincida con el recálculo autoritativo.
 */
function buildUpTo($automation, $database, $tid, $targetLevel, $slot = 19, $preset = array()) {
    $vref = 500;
    $database->villages[$vref] = array(
        'maxstore' => STORAGE_BASE, 'maxcrop' => STORAGE_BASE,
        'wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'owner' => 1,
    );
    $database->fields[$vref] = emptyFields($vref);
    foreach($preset as $presetSlot => $spec) {
        $database->fields[$vref]['f'.$presetSlot] = $spec[1];
        $database->fields[$vref]['f'.$presetSlot.'t'] = $spec[0];
    }
    if(!empty($preset)) {
        $out = runUpdateStore($automation, array($database->fields[$vref]));
        $database->villages[$vref]['maxstore'] = $out[$vref][0];
        $database->villages[$vref]['maxcrop'] = $out[$vref][1];
    }
    for($lvl = 1; $lvl <= $targetLevel; $lvl++) {
        $database->bdata = array(array(
            'id' => $lvl, 'wid' => $vref, 'field' => $slot, 'type' => $tid,
            'level' => $lvl, 'timestamp' => 1, 'master' => 0, 'loopcon' => 0,
        ));
        callPrivate($automation, 'buildComplete', array(null, false));
        $database->bdata = array();
    }
    return $vref;
}

foreach(array(10 => array('Almacén', 'maxstore'), 11 => array('Granero', 'maxcrop'),
              38 => array('Gran almacén', 'maxstore'), 39 => array('Gran granero', 'maxcrop')) as $tid => $spec) {
    list($label, $column) = $spec;
    for($target = 1; $target <= 20; $target++) {
        $vref = buildUpTo($automation, $database, $tid, $target);
        $expected = $GLOBALS['bid'.$tid][$target]['attri'] * STORAGE_MULTIPLIER;
        $actual = $database->villages[$vref][$column];
        check($actual == $expected,
            $label.": construir de 0 a nivel $target deja $column=$actual, se espera $expected");
    }
}

// Segundo almacén mientras el primero ya existe.
$vref = buildUpTo($automation, $database, 10, 3, 20, array(19 => array(10, 20)));
check($database->villages[$vref]['maxstore'] == (80000 + 2300) * STORAGE_MULTIPLIER,
    "Segundo almacén a nivel 3 con uno de nivel 20: maxstore esperado 82300, obtenido ".$database->villages[$vref]['maxstore']);

// Construir el primer almacén cuando la aldea ya tiene un granero (maxstore sigue en la base).
$vref = buildUpTo($automation, $database, 10, 1, 20, array(19 => array(11, 10)));
check($database->villages[$vref]['maxstore'] == 1200 * STORAGE_MULTIPLIER,
    "Primer almacén nivel 1 con granero previo: maxstore esperado 1200, obtenido ".$database->villages[$vref]['maxstore']);

// ---------------------------------------------------------------------------
// D. Deltas al demoler (demolitionComplete)
// ---------------------------------------------------------------------------
section('D. Automation::demolitionComplete() — capacidad al demoler');

function demolishOnce($automation, $database, $tid, $fromLevel, $slot = 19, $preset = array()) {
    $vref = 600;
    $database->fields[$vref] = emptyFields($vref);
    $database->fields[$vref]['f'.$slot] = $fromLevel;
    $database->fields[$vref]['f'.$slot.'t'] = $tid;
    foreach($preset as $presetSlot => $spec) {
        $database->fields[$vref]['f'.$presetSlot] = $spec[1];
        $database->fields[$vref]['f'.$presetSlot.'t'] = $spec[0];
    }
    $out = runUpdateStore($automation, array($database->fields[$vref]));
    $database->villages[$vref] = array(
        'maxstore' => $out[$vref][0], 'maxcrop' => $out[$vref][1],
        'wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'owner' => 1,
    );
    $database->demolition = array(array(
        'vref' => $vref, 'buildnumber' => $slot, 'timetofinish' => time() - 1,
    ));
    callPrivate($automation, 'demolitionComplete');
    return $vref;
}

foreach(array(10 => array('Almacén', 'maxstore'), 11 => array('Granero', 'maxcrop'),
              38 => array('Gran almacén', 'maxstore'), 39 => array('Gran granero', 'maxcrop')) as $tid => $spec) {
    list($label, $column) = $spec;
    for($from = 1; $from <= 20; $from++) {
        $vref = demolishOnce($automation, $database, $tid, $from);
        $expected = $from === 1
            ? STORAGE_BASE
            : $GLOBALS['bid'.$tid][$from - 1]['attri'] * STORAGE_MULTIPLIER;
        $actual = $database->villages[$vref][$column];
        check($actual == $expected,
            $label.": demoler de nivel $from a ".($from - 1)." deja $column=$actual, se espera $expected");
        $expectedLevel = $from - 1;
        check($database->fields[$vref]['f19'] == $expectedLevel,
            $label.": demoler de nivel $from debe dejar el campo en nivel $expectedLevel");
    }
}

// Demoler el segundo almacén no debe tocar la capacidad del primero.
$vref = demolishOnce($automation, $database, 10, 1, 20, array(19 => array(10, 20)));
check($database->villages[$vref]['maxstore'] == 80000 * STORAGE_MULTIPLIER,
    "Demoler el segundo almacén (nivel 1) con otro de nivel 20: maxstore esperado 80000, obtenido ".$database->villages[$vref]['maxstore']);

// ---------------------------------------------------------------------------
// E. Destrucción por catapultas (updateCatapultCapacity)
// ---------------------------------------------------------------------------
section('E. Automation::applyStorageCapacityDelta() — capacidad tras catapultas');

function catapultTo($automation, $database, $tid, $oldLevel, $newLevel, $preset = array()) {
    $vref = 700;
    $fields = emptyFields($vref);
    $fields['f19'] = $oldLevel; $fields['f19t'] = $tid;
    foreach($preset as $slot => $spec) {
        $fields['f'.$slot] = $spec[1];
        $fields['f'.$slot.'t'] = $spec[0];
    }
    $database->fields[$vref] = $fields;
    $out = runUpdateStore($automation, array($fields));
    $database->villages[$vref] = array(
        'maxstore' => $out[$vref][0], 'maxcrop' => $out[$vref][1],
        'wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'owner' => 1,
    );
    callPrivate($automation, 'applyStorageCapacityDelta', array($vref, $tid, $oldLevel, $newLevel));
    return $vref;
}

foreach(array(10 => array('Almacén', 'maxstore'), 11 => array('Granero', 'maxcrop'),
              38 => array('Gran almacén', 'maxstore'), 39 => array('Gran granero', 'maxcrop')) as $tid => $spec) {
    list($label, $column) = $spec;
    for($old = 1; $old <= 20; $old++) {
        for($new = 0; $new < $old; $new++) {
            $vref = catapultTo($automation, $database, $tid, $old, $new);
            $expected = $new === 0
                ? STORAGE_BASE
                : $GLOBALS['bid'.$tid][$new]['attri'] * STORAGE_MULTIPLIER;
            $actual = $database->villages[$vref][$column];
            check($actual == $expected,
                $label.": catapultas de nivel $old a $new dejan $column=$actual, se espera $expected");
        }
    }
}

// Con dos almacenes, destruir uno conserva la capacidad del otro.
$vref = catapultTo($automation, $database, 10, 10, 0, array(20 => array(10, 20)));
check($database->villages[$vref]['maxstore'] == 80000 * STORAGE_MULTIPLIER,
    "Destruir un almacén de nivel 10 junto a otro de nivel 20: maxstore esperado 80000, obtenido ".$database->villages[$vref]['maxstore']);

// ---------------------------------------------------------------------------
// F. Recorte por desborde (pruneResource)
// ---------------------------------------------------------------------------
section('F. Automation::pruneResource() — recorte de recursos por desborde');

$database->villages = array(
    1 => array('maxstore' => 4000, 'maxcrop' => 80000,
               'wood' => 9999, 'clay' => 100, 'iron' => 100, 'crop' => 90000, 'owner' => 1),
);
callPrivate($automation, 'pruneResource');
check($database->villages[1]['wood'] == 4000,
    "Desborde de madera: se recorta a maxstore (4000), quedó ".$database->villages[1]['wood']);
check($database->villages[1]['crop'] == 80000,
    "Desborde de cereal: se recorta a maxcrop (80000), quedó ".$database->villages[1]['crop']);

$database->villages = array(
    1 => array('maxstore' => 80000, 'maxcrop' => 4000,
               'wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 50000, 'owner' => 1),
);
callPrivate($automation, 'pruneResource');
check($database->villages[1]['crop'] == 4000,
    "Granero más chico que el almacén: el cereal se recorta a maxcrop (4000), quedó ".$database->villages[1]['crop']);

// El cereal negativo (hambruna) no debe contaminarse con el valor de otra aldea.
$database->villages = array(
    1 => array('maxstore' => 800, 'maxcrop' => 800,
               'wood' => -5, 'clay' => 10, 'iron' => 10, 'crop' => 700, 'owner' => 1),
    2 => array('maxstore' => 800, 'maxcrop' => 800,
               'wood' => 10, 'clay' => 10, 'iron' => 10, 'crop' => -50, 'owner' => 1),
);
callPrivate($automation, 'pruneResource');
check($database->villages[2]['crop'] == -50 || $database->villages[2]['crop'] == 0,
    "Cereal negativo: debe quedar en -50 o 0, quedó ".$database->villages[2]['crop']);

// ---------------------------------------------------------------------------
// G. Requisitos de construcción
// ---------------------------------------------------------------------------
section('G. Requisitos de construcción (Building::meetRequirement)');

require dirname(__FILE__).'/../GameEngine/Building.php';

class StorageVillageStub {
    public $resarray = array();
    public $capital = 0;
    public $wid = 1;
    public function __construct() {
        for($field = 1; $field <= 40; $field++) {
            $this->resarray['f'.$field] = 0;
            $this->resarray['f'.$field.'t'] = 0;
        }
    }
    public function place($slot, $tid, $level) {
        $this->resarray['f'.$slot] = $level;
        $this->resarray['f'.$slot.'t'] = $tid;
        return $this;
    }
}

// Doble mínimo para las consultas de artefactos que hace hasStorageArtefact().
class StorageArtefactDatabaseStub {
    public $inVillage = null;   // artefacto tipo 6 en la aldea
    public $account = array();  // tamaño => artefacto tipo 6 de la cuenta
    public function getOwnArtefactInfoByType($vref, $type) {
        return ((int)$type === 6 && $this->inVillage !== null)
            ? array('vref' => $vref, 'size' => $this->inVillage) : false;
    }
    public function getOwnUniqueArtefactInfo($uid, $type, $size) {
        return ((int)$type === 6 && in_array($size, $this->account, true))
            ? array('owner' => $uid, 'size' => $size) : false;
    }
}
class StorageSessionStub { public $uid = 7; public $tribe = 1; }

$artefactDb = new StorageArtefactDatabaseStub();
$buildingObj = (new ReflectionClass('Building'))->newInstanceWithoutConstructor();

function requirementFor($buildingObj, $tid, StorageVillageStub $stub, array $queue = array()) {
    global $artefactDb;
    $GLOBALS['village'] = $stub;
    $GLOBALS['database'] = $artefactDb;
    $GLOBALS['session'] = new StorageSessionStub();
    $queueProperty = new ReflectionProperty('Building', 'buildArray');
    $queueProperty->setAccessible(true);
    $queueProperty->setValue($buildingObj, $queue);
    $result = callPrivate($buildingObj, 'meetRequirement', array($tid));
    $GLOBALS['database'] = $GLOBALS['storageDb'];
    return $result;
}

// Sin edificio principal no se puede levantar almacén ni granero.
$stub = new StorageVillageStub();
check(requirementFor($buildingObj, 10, $stub) === false,
    "Almacén: sin edificio principal debe rechazarse");
check(requirementFor($buildingObj, 11, $stub) === false,
    "Granero: sin edificio principal debe rechazarse");

// Con edificio principal nivel 1 alcanza para el primero.
$stub = (new StorageVillageStub())->place(19, 15, 1);
check(requirementFor($buildingObj, 10, $stub) === true,
    "Almacén: con edificio principal nivel 1 debe permitirse el primero");
check(requirementFor($buildingObj, 11, $stub) === true,
    "Granero: con edificio principal nivel 1 debe permitirse el primero");

// Un almacén por debajo del máximo bloquea levantar otro.
foreach(array(10 => 'Almacén', 11 => 'Granero') as $tid => $label) {
    for($lvl = 1; $lvl <= 19; $lvl++) {
        $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, $lvl);
        check(requirementFor($buildingObj, $tid, $stub) === false,
            $label.": con uno a nivel $lvl (no máximo) no se debe permitir otro");
    }
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 20);
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con uno a nivel 20 se debe permitir otro");

    // Dos existentes: si uno no está al máximo, sigue bloqueado.
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 20)->place(21, $tid, 4);
    check(requirementFor($buildingObj, $tid, $stub) === false,
        $label.": con uno a nivel 20 y otro a nivel 4 no se debe permitir un tercero");
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 20)->place(21, $tid, 20);
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con dos a nivel 20 se debe permitir un tercero");

    // Uno en cola bloquea encolar otro.
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 20);
    $queue = array(array('type' => $tid, 'field' => 21, 'level' => 1));
    check(requirementFor($buildingObj, $tid, $stub, $queue) === false,
        $label.": con uno en la cola de construcción no se debe permitir otro");
}

// Gran almacén / gran granero: edificio principal 10, fuera de la capital
// y artefacto de almacenamiento (tipo 6).
foreach(array(38 => 'Gran almacén', 39 => 'Gran granero') as $tid => $label) {
    // Sin ningún artefacto no se puede, por más que se cumpla el resto.
    $artefactDb->inVillage = null;
    $artefactDb->account = array();
    $stub = (new StorageVillageStub())->place(19, 15, 10);
    check(requirementFor($buildingObj, $tid, $stub) === false,
        $label.": sin artefacto de almacenamiento debe rechazarse");

    // Artefacto de tipo 6 pero de otro tipo de artefacto no sirve: sólo el 6 cuenta.
    $artefactDb->inVillage = 1;   // artefacto pequeño en esta aldea
    $artefactDb->account = array();
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con artefacto pequeño en la aldea debe permitirse");

    $artefactDb->inVillage = null;
    $artefactDb->account = array(2);  // artefacto grande de la cuenta
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con artefacto grande de la cuenta debe permitirse");

    $artefactDb->account = array(3);  // artefacto único de la cuenta
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con artefacto único de la cuenta debe permitirse");

    // A partir de acá el artefacto está garantizado; se prueba el resto de reglas.
    $artefactDb->inVillage = 1;
    $artefactDb->account = array();

    $stub = (new StorageVillageStub())->place(19, 15, 9);
    check(requirementFor($buildingObj, $tid, $stub) === false,
        $label.": con edificio principal nivel 9 debe rechazarse");
    $stub = (new StorageVillageStub())->place(19, 15, 10);
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con edificio principal nivel 10 fuera de la capital debe permitirse");
    $stub = (new StorageVillageStub())->place(19, 15, 10);
    $stub->capital = 1;
    check(requirementFor($buildingObj, $tid, $stub) === false,
        $label.": en la capital debe rechazarse");
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 19);
    check(requirementFor($buildingObj, $tid, $stub) === false,
        $label.": con uno a nivel 19 no se debe permitir otro");
    $stub = (new StorageVillageStub())->place(19, 15, 10)->place(20, $tid, 20);
    check(requirementFor($buildingObj, $tid, $stub) === true,
        $label.": con uno a nivel 20 se debe permitir otro");
}

// ---------------------------------------------------------------------------
echo "\n";
$total = $GLOBALS['checks'];
$failed = count($GLOBALS['fails']);
echo "Comprobaciones: ".$total." · fallidas: ".$failed."\n";
if($failed > 0) {
    echo "\nResumen de fallas:\n";
    $seen = array();
    foreach($GLOBALS['fails'] as $fail) {
        $key = preg_replace('/\d+/', '#', $fail);
        if(isset($seen[$key])) { continue; }
        $seen[$key] = true;
        echo " - ".$fail."\n";
    }
    exit(1);
}
echo "Todo en orden.\n";
exit(0);
