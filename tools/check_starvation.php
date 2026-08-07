<?php
/**
 * Auditoría de la hambruna (Automation::starvation).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_starvation.php
 *
 * Cubre:
 *   A. Detección: la aldea entra en hambruna por su propio estado (granero en rojo),
 *      sin depender de que otro proceso la marque.
 *   B. Con reservas en el granero no muere nadie; con el granero vacío sí.
 *   C. Mueren las justas para equilibrar el balance, del contingente más numeroso.
 *   D. Los refuerzos también comen, y se los elimina cuando se consumen enteros.
 *   E. El estado no se filtra de una aldea a la siguiente (el bug de $enf).
 *   F. Ritmo de una tanda por minuto y aviso al dueño una sola vez.
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

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
define('SPEED', 1);
define('ALLOW_BURST', false);
define('STORAGE_BASE', 800);
define('STORAGE_MULTIPLIER', 1);
define('TRAPPER_CAPACITY', 1);
define('CRANNY_CAPACITY', 1);

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';

function mysql_query($sql) { return true; }
function mysql_fetch_assoc($result) { return false; }
function mysql_error() { return ''; }

require dirname(__DIR__).'/GameEngine/Automation.php';

/**
 * Doble de la capa de datos: sólo lo que toca el camino de la hambruna.
 */
class StarvationDatabaseStub {
    public $villages = array();      // wref => campos de vdata
    public $fields = array();        // wref => fdata
    public $units = array();         // wref => u1..u50
    public $enforcements = array();  // wref => lista de refuerzos
    public $messages = array();
    public $deletedReinforcements = array();

    public function getStarvation() {
        $out = array();
        foreach($this->villages as $wref => $village) {
            if($village['crop'] < 0 || $village['starv'] != 0) {
                $village['wref'] = $wref;
                $out[] = $village;
            }
        }
        return $out;
    }
    public function getVillage($wref) {
        $village = isset($this->villages[$wref]) ? $this->villages[$wref] : array();
        $village['wref'] = $wref;
        return $village;
    }
    public function getVillageField($wref, $field) {
        return isset($this->villages[$wref][$field]) ? $this->villages[$wref][$field] : 0;
    }
    public function setVillageField($wref, $field, $value) {
        $this->villages[$wref][$field] = $value;
    }
    public function getResourceLevel($wref) {
        return isset($this->fields[$wref]) ? $this->fields[$wref] : array();
    }
    public function getOasis($wref) { return array(); }
    public function getUnit($wref) {
        return isset($this->units[$wref]) ? $this->units[$wref] : array();
    }
    public function getEnforceVillage($wref, $mode) {
        return isset($this->enforcements[$wref]) ? $this->enforcements[$wref] : array();
    }
    public function modifyUnit($wref, $unit, $amount, $mode) {
        $this->units[$wref]['u'.$unit] -= $amount;
        return true;
    }
    public function modifyEnforce($id, $unit, $amount, $mode) {
        foreach($this->enforcements as $wref => $rows) {
            foreach($rows as $index => $row) {
                if((int)$row['id'] === (int)$id) {
                    $this->enforcements[$wref][$index]['u'.$unit] -= $amount;
                }
            }
        }
    }
    public function deleteReinf($id) {
        $this->deletedReinforcements[] = (int)$id;
        foreach($this->enforcements as $wref => $rows) {
            foreach($rows as $index => $row) {
                if((int)$row['id'] === (int)$id) {
                    unset($this->enforcements[$wref][$index]);
                    $this->enforcements[$wref] = array_values($this->enforcements[$wref]);
                }
            }
        }
    }
    public function sendMessage($to, $from, $topic, $message, $send, $alliance, $player, $coor, $report) {
        $this->messages[] = array('to'=>$to, 'topic'=>$topic);
        return true;
    }
    public function getUserField($uid, $field, $mode) { return 0; }
    public function getOwnArtefactInfoByType($wref, $type) { return array('type'=>0, 'size'=>0, 'owner'=>0, 'vref'=>0); }
    public function getOwnUniqueArtefactInfo($uid, $type, $size) { return array('type'=>0, 'size'=>0, 'owner'=>0); }
    public function getHeroData($uid) { return array('dead'=>1, 'home'=>0, 'wref'=>0); }
    public function query($q) { return true; }
    public function query_return($q) { return array(); }
}

/** Doble de Technology: la hambruna sólo le pide el consumo de las tropas. */
class StarvationTechnologyStub {
    public $database;
    public function getAllUnits($wref) {
        $units = $this->database->getUnit($wref);
        foreach($this->database->getEnforceVillage($wref, 0) as $reinforcement) {
            for($unit = 1; $unit <= 50; $unit++) {
                if(!isset($units['u'.$unit])) { $units['u'.$unit] = 0; }
                $units['u'.$unit] += isset($reinforcement['u'.$unit]) ? (int)$reinforcement['u'.$unit] : 0;
            }
        }
        return $units;
    }
    public function getUpkeep($array, $type) {
        $upkeep = 0;
        for($unit = 1; $unit <= 50; $unit++) {
            $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
            if(is_array($data) && !empty($array['u'.$unit])) {
                $upkeep += $data['pop'] * (int)$array['u'.$unit];
            }
        }
        return $upkeep;
    }
}

$database = new StarvationDatabaseStub();
$technology = new StarvationTechnologyStub();
$technology->database = $database;
$session = (object)array('tribe'=>1, 'bonus1'=>0, 'bonus2'=>0, 'bonus3'=>0, 'bonus4'=>0);

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$starvation = $reflection->getMethod('starvation');
$starvation->setAccessible(true);

/** Aldea con `$cropFields` plantaciones de cereal a nivel `$level`. */
function starvationFields($cropFields, $level = 5) {
    $fields = array();
    for($field = 1; $field <= 40; $field++) { $fields['f'.$field] = 0; $fields['f'.$field.'t'] = 0; }
    for($field = 1; $field <= $cropFields; $field++) { $fields['f'.$field.'t'] = 4; $fields['f'.$field] = $level; }
    return $fields;
}

function starvationUnits($units) {
    $row = array();
    for($unit = 1; $unit <= 50; $unit++) { $row['u'.$unit] = 0; }
    foreach($units as $type => $count) { $row['u'.$type] = $count; }
    return $row;
}

function starvationVillage($wref, $cropFields, $crop, $units, $pop = 0, $starv = 0, $starvupdate = 0) {
    global $database;
    $database->villages[$wref] = array(
        'owner'=>10, 'name'=>'Aldea '.$wref, 'crop'=>$crop, 'pop'=>$pop,
        'starv'=>$starv, 'starvupdate'=>$starvupdate, 'lastupdate'=>time(),
        'maxstore'=>100000, 'maxcrop'=>100000, 'capital'=>0
    );
    $database->fields[$wref] = starvationFields($cropFields);
    $database->units[$wref] = starvationUnits($units);
}

function runStarvation() {
    global $automation, $starvation;
    $starvation->invoke($automation);
}

// ---------------------------------------------------------------------------
section('A. Detección por estado de la aldea');
// ---------------------------------------------------------------------------
// 4 plantaciones nivel 5 => 132/h de cereal bruto.
starvationVillage(1, 4, 500, array(1=>50));   // consumo 50, balance +82: sana
starvationVillage(2, 4, -10, array(1=>50));   // granero en rojo
$found = array();
foreach($database->getStarvation() as $row) { $found[] = (int)$row['wref']; }
check(in_array(2,$found,true), 'una aldea con el granero en rojo entra en la barrida');
check(!in_array(1,$found,true), 'una aldea con reservas y balance positivo no entra');

$engine = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
check(strpos($engine,'where crop < 0 or starv != 0') !== false,
    'getStarvation() busca por granero en rojo y no sólo por la marca');
$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
check(strpos($automationSource,"\$database->setVillageField(\$indi['wid'], 'starv', \$upkeep)") === false,
    'no quedan disparadores comentados de la hambruna en buildComplete');
check(substr_count($automationSource,'add starv data') === 0,
    'no quedan restos de los tres disparadores viejos');

// ---------------------------------------------------------------------------
section('B. Reservas vs granero vacío');
// ---------------------------------------------------------------------------
$database->villages = array(); $database->units = array(); $database->enforcements = array();
// Balance negativo pero el granero lleno: no es asunto de la hambruna todavía,
// la producción negativa ya vacía el granero sola.
starvationVillage(1, 4, 1000, array(1=>200));  // consumo 200 > 132
runStarvation();
check($database->units[1]['u1'] === 200, 'con cereal en el granero no muere ninguna tropa');
check($database->villages[1]['starv'] == 0, 'y ni siquiera se la marca mientras tenga reservas');
check(count($database->messages) === 0, 'no se avisa al jugador');

// Una aldea ya marcada a la que le mandan cereal deja de matar y vuelve a anotar
// el déficit hasta que el granero se vacíe otra vez.
$database->villages = array(); $database->units = array(); $database->enforcements = array();
starvationVillage(2, 4, 1000, array(1=>200), 0, 50, time() - 120);
runStarvation();
check($database->units[2]['u1'] === 200, 'un envío de cereal frena las muertes');
check(round($database->villages[2]['starv']) == 68, 'y se re-anota el déficit real (200-132)');

// ---------------------------------------------------------------------------
section('C. Muertes con el granero vacío');
// ---------------------------------------------------------------------------
$database->villages = array(); $database->units = array(); $database->enforcements = array(); $database->messages = array();
starvationVillage(1, 4, -5, array(1=>200));   // legionarios: 1 de consumo cada uno
runStarvation();
check($database->units[1]['u1'] === 132, 'mueren las justas para equilibrar (200 - 68)');
check($database->villages[1]['crop'] == 0, 'el granero queda en cero, no en negativo');
check(count($database->messages) === 1 && $database->messages[0]['to'] === 10,
    'se avisa al dueño la primera vez');

// Segunda pasada inmediata: el ritmo la frena.
$before = $database->units[1]['u1'];
runStarvation();
check($database->units[1]['u1'] === $before, 'no vuelve a matar dentro del mismo minuto');

// Con el reloj vencido y el balance ya equilibrado, se limpia la marca.
$database->villages[1]['starvupdate'] = time() - 120;
runStarvation();
check($database->villages[1]['starv'] == 0, 'con el balance equilibrado se levanta la hambruna');
check($database->units[1]['u1'] === 132, 'y no se mata a nadie más');

// Cuando el contingente más numeroso no alcanza para cubrir el déficit se lo mata
// entero y el resto espera al minuto siguiente: la mortandad no se acelera porque
// haya más visitas a la página.
$database->villages = array(); $database->units = array(); $database->enforcements = array(); $database->messages = array();
starvationVillage(5, 0, -1, array(1=>100, 2=>50));   // 150 de consumo, 0 de producción
runStarvation();
check($database->units[5]['u1'] === 0 && $database->units[5]['u2'] === 50,
    'se consume entero el contingente más numeroso y el resto queda para después');
runStarvation();
check($database->units[5]['u2'] === 50, 'la tanda siguiente espera el minuto de rigor');
$database->villages[5]['starvupdate'] = time() - 120;
runStarvation();
check($database->units[5]['u2'] === 0, 'pasado el minuto sigue con el contingente que queda');

// Unidades de consumo mayor: mueren menos.
$database->villages = array(); $database->units = array(); $database->enforcements = array(); $database->messages = array();
starvationVillage(2, 4, -1, array(8=>100));   // catapulta romana: 6 de consumo
runStarvation();
$expectedKill = (int)ceil((100 * $GLOBALS['u8']['pop'] - 132) / $GLOBALS['u8']['pop']);
check($database->units[2]['u8'] === 100 - $expectedKill,
    'con unidades de consumo alto mueren menos ('.$expectedKill.' catapultas)');

// Nadie a quien matar: se corta el rojo en vez de arrastrar deuda.
$database->villages = array(); $database->units = array(); $database->enforcements = array();
starvationVillage(3, 0, -500, array());
runStarvation();
check($database->villages[3]['crop'] == 0 && $database->villages[3]['starv'] == 0,
    'sin tropas que matar la aldea deja de estar en hambruna');

// ---------------------------------------------------------------------------
section('D. Refuerzos');
// ---------------------------------------------------------------------------
$database->villages = array(); $database->units = array(); $database->enforcements = array(); $database->messages = array();
starvationVillage(1, 4, -5, array(1=>10));
$database->enforcements[1] = array(array('id'=>77, 'vref'=>1, 'from'=>9) + starvationUnits(array(2=>300)));
runStarvation();
check($database->units[1]['u1'] === 10, 'se empieza por el contingente más numeroso, no por las tropas propias');
check($database->enforcements[1][0]['u2'] === 300 - 178,
    'el refuerzo pierde las unidades que hacen falta (300 - 178)');

$database->villages = array(); $database->units = array(); $database->enforcements = array(); $database->messages = array();
starvationVillage(1, 0, -5, array());  // sin plantaciones: 0/h
$database->enforcements[1] = array(array('id'=>88, 'vref'=>1, 'from'=>9) + starvationUnits(array(1=>20)));
runStarvation();
check(in_array(88,$database->deletedReinforcements,true) || empty($database->enforcements[1]),
    'un refuerzo que se consume entero se elimina');

// ---------------------------------------------------------------------------
section('E. Sin fugas entre aldeas');
// ---------------------------------------------------------------------------
$database->villages = array(); $database->units = array(); $database->enforcements = array();
$database->deletedReinforcements = array(); $database->messages = array();
// La primera aldea tiene refuerzos, la segunda no: la segunda no puede terminar
// matando tropas del refuerzo de la primera.
starvationVillage(1, 1, -5, array());
$database->enforcements[1] = array(array('id'=>91, 'vref'=>1, 'from'=>9) + starvationUnits(array(1=>500)));
starvationVillage(2, 4, -5, array(1=>200));
runStarvation();
check($database->units[2]['u1'] === 132, 'la segunda aldea mata sus propias tropas');
check($database->enforcements[1][0]['u1'] === 500 - 467,
    'el refuerzo de la primera aldea paga su propio déficit (500 - 467)');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Starvation checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
