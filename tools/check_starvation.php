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
 *   C. El ritmo lo pone la deuda de cereal, cada muerto devuelve su costo de
 *      entrenamiento al granero, y la matanza se corta cuando el balance vuelve a
 *      cero aunque quede deuda sin pagar.
 *   D. El orden de bajas del oficial: refuerzos de otros jugadores, después los
 *      propios venidos de otra aldea, y al final la guarnición de casa; dentro de
 *      cada grupo el ejército más numeroso y dentro de él el tipo más numeroso.
 *      Incluye las tropas de un oasis anexado y excluye a los animales enjaulados.
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
    public $oases = array();          // wref de la aldea => lista de oasis anexados
    public function getOasis($wref) {
        return isset($this->oases[$wref]) ? $this->oases[$wref] : array();
    }
    public function getEnforceArray($id, $mode) {
        foreach($this->enforcements as $rows) {
            foreach($rows as $row) {
                if((int)$row['id'] === (int)$id) { return $row; }
            }
        }
        return array();
    }
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
        $rows = $this->database->getEnforceVillage($wref, 0);
        // Un oasis no tiene granero: a sus tropas las alimenta la aldea que lo anexó,
        // igual que en Technology::getAllUnits.
        foreach($this->database->getOasis($wref) as $oasis) {
            foreach($this->database->getEnforceVillage((int)$oasis['wref'], 0) as $row) {
                $rows[] = $row;
            }
        }
        foreach($rows as $reinforcement) {
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

/**
 * Aldea que sólo existe para que un refuerzo tenga origen: es lo que decide si sus
 * tropas son de otro jugador (grupo 1) o tuyas venidas de otra aldea (grupo 2).
 */
function starvationSourceVillage($wref, $owner) {
    global $database;
    $database->villages[$wref] = array(
        'owner'=>$owner, 'name'=>'Origen '.$wref, 'crop'=>1000, 'pop'=>0,
        'starv'=>0, 'starvupdate'=>0, 'lastupdate'=>time(),
        'maxstore'=>100000, 'maxcrop'=>100000, 'capital'=>0
    );
}

/** Refuerzo alojado en `$vref` que salió de `$from`. */
function starvationReinforcement($id, $vref, $from, $units) {
    global $database;
    if(!isset($database->enforcements[$vref])) { $database->enforcements[$vref] = array(); }
    $database->enforcements[$vref][] = array('id'=>$id, 'vref'=>$vref, 'from'=>$from) + starvationUnits($units);
}

/** Deja el mundo del test vacío. */
function starvationReset() {
    global $database;
    $database->villages = array();
    $database->units = array();
    $database->enforcements = array();
    $database->oases = array();
    $database->messages = array();
    $database->deletedReinforcements = array();
}

/** Busca un refuerzo por id, o null si ya no existe. */
function starvationFindReinforcement($id) {
    global $database;
    foreach($database->enforcements as $rows) {
        foreach($rows as $row) {
            if((int)$row['id'] === (int)$id) { return $row; }
        }
    }
    return null;
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
section('C. Ritmo, refund y corte');
// ---------------------------------------------------------------------------
// El granero en negativo es el cereal que faltó desde la pasada anterior. Se mata
// sólo lo necesario para cubrir esa deuda, y cada muerto devuelve al granero lo que
// costó entrenarlo (legionario 30). Antes se mataba de una todo lo necesario para
// dejar el balance en cero, así que un minuto en rojo costaba lo mismo que diez horas.
starvationReset();
starvationVillage(1, 4, -5, array(1=>200));   // 132/h de producción, 200 de consumo
runStarvation();
check($database->units[1]['u1'] === 199, 'una deuda chica cuesta una sola tropa, no las 68 del balance');
check($database->villages[1]['crop'] == 25, 'el muerto devuelve su costo de entrenamiento (30 - 5 de deuda)');
check(round($database->villages[1]['starv']) == 67, 'y queda anotado el déficit que sigue habiendo');
check(count($database->messages) === 1 && $database->messages[0]['to'] === 10,
    'se avisa al dueño la primera vez');

// Segunda pasada inmediata: el ritmo la frena.
$before = $database->units[1]['u1'];
runStarvation();
check($database->units[1]['u1'] === $before, 'no vuelve a matar dentro del mismo minuto');
check(count($database->messages) === 1, 'ni se avisa dos veces');

// Una deuda grande sí paga con muchas tropas, pero nunca más allá del equilibrio.
starvationReset();
starvationVillage(2, 4, -3000, array(1=>200));
runStarvation();
check($database->units[2]['u1'] === 132, 'con deuda de sobra mueren las 68 que faltaban para equilibrar');
check($database->villages[2]['crop'] == 0, 'el granero queda en cero: lo que devolvieron no cubre la deuda');
check($database->villages[2]['starv'] == 0, 'y con el balance en cero se levanta la hambruna');

// El corte por equilibrio es lo que evita que una aldea que estuvo un mes sin que la
// visitaran pierda el ejército entero para saldar una deuda imposible.
starvationReset();
starvationVillage(3, 4, -100000, array(1=>134));   // 132/h contra 134 de consumo: -2
runStarvation();
check($database->units[3]['u1'] === 132,
    'con un déficit de 2 mueren 2, por enorme que sea la deuda ('.$database->units[3]['u1'].' vivas)');
check($database->villages[3]['crop'] == 0, 'y el resto de la deuda se perdona');

// Unidades de consumo alto: cada muerte alcanza para más, así que mueren menos.
starvationReset();
starvationVillage(4, 4, -1000, array(8=>100));   // catapulta romana: 6 de consumo, 90 de cereal
runStarvation();
check($database->units[4]['u8'] === 88, 'las catapultas pagan la deuda de a 90 ('.$database->units[4]['u8'].' vivas)');
check($database->villages[4]['crop'] == 80, 'y el sobrante del refund queda en el granero');

// Nadie a quien matar: la deuda se borra igual. Sin esto una aldea sin tropas y con
// producción negativa —a la que le catapultaron las plantaciones, o con la población
// desincronizada— acumulaba un rojo eterno, y con el granero en -47.000 el dueño no
// podía entrenar, ni construir, ni mandar cereal, sin ver por qué.
starvationReset();
starvationVillage(5, 0, -500, array(), 100);   // sin tropas, 100 habitantes
runStarvation();
check($database->villages[5]['crop'] == 0, 'sin tropas que matar la deuda se corta igual');
check(count($database->messages) === 0, 'y no se avisa de una hambruna en la que no muere nadie');
$database->villages[5]['starvupdate'] = time() - 120;
runStarvation();
check($database->villages[5]['crop'] == 0, 'y no vuelve a acumularse en la pasada siguiente');

// Los animales enjaulados defienden gratis, así que matarlos no arreglaría nada.
starvationReset();
starvationVillage(6, 0, -500, array(31=>50), 100);
runStarvation();
check($database->units[6]['u31'] === 50, 'los animales de oasis enjaulados no mueren de hambre');
check($database->villages[6]['crop'] == 0, 'y la deuda se corta igual');

// ---------------------------------------------------------------------------
section('D. Orden de bajas del oficial');
// ---------------------------------------------------------------------------
// Grupo 1 refuerzos de otros jugadores, grupo 2 tus tropas venidas de otra aldea
// tuya, grupo 3 la guarnición de casa. El grupo manda sobre el tamaño: un refuerzo
// ajeno de 10 muere entero antes de que se toque una guarnición propia de 200.
starvationReset();
starvationVillage(1, 4, -1000, array(1=>200));
starvationSourceVillage(9, 77);                       // otro jugador
starvationReinforcement(70, 1, 9, array(2=>10));
runStarvation();
check(starvationFindReinforcement(70) === null,
    'el refuerzo del otro jugador se consume entero antes que la guarnición propia');
check($database->units[1]['u1'] === 190, 'y recién después se le pide a la guarnición de casa');

// Los tres grupos a la vez, con deuda para llegar hasta el último.
starvationReset();
starvationVillage(2, 0, -400, array(1=>50));
starvationSourceVillage(8, 77);                       // otro jugador
starvationSourceVillage(9, 10);                       // otra aldea del mismo dueño
starvationReinforcement(80, 2, 8, array(1=>5));       // grupo 1
starvationReinforcement(81, 2, 9, array(1=>5));       // grupo 2
runStarvation();
check(starvationFindReinforcement(80) === null, 'primero se acaba el refuerzo ajeno');
check(starvationFindReinforcement(81) === null, 'después el propio venido de otra aldea');
check($database->units[2]['u1'] === 46, 'y al final la guarnición de casa ('.$database->units[2]['u1'].' vivas)');

// Dentro de un grupo, primero el ejército más numeroso.
starvationReset();
starvationVillage(3, 0, -60, array());
starvationSourceVillage(9, 77);
starvationReinforcement(90, 3, 9, array(1=>100));
starvationReinforcement(91, 3, 9, array(1=>10));
runStarvation();
$big = starvationFindReinforcement(90);
$small = starvationFindReinforcement(91);
check($big['u1'] === 98, 'el refuerzo más numeroso es el que pierde tropas');
check($small['u1'] === 10, 'y el chico queda intacto');

// Dentro de un ejército, primero el tipo de unidad más numeroso.
starvationReset();
starvationVillage(4, 0, -60, array(1=>10, 2=>100));
runStarvation();
check($database->units[4]['u2'] === 99, 'muere el tipo más numeroso');
check($database->units[4]['u1'] === 10, 'y el otro no se toca');

// Un refuerzo que se queda sin ninguna unidad se borra...
starvationReset();
starvationVillage(5, 0, -1000, array());
starvationSourceVillage(9, 77);
starvationReinforcement(95, 5, 9, array(1=>2, 2=>5));
runStarvation();
check(starvationFindReinforcement(95) === null && in_array(95,$database->deletedReinforcements,true),
    'un refuerzo consumido entero se elimina');

// ...pero uno al que sólo se le acabó un tipo de unidad, no. Borrar la fila entera
// cuando se agotaba el tipo más numeroso se llevaba puestas las otras unidades.
starvationReset();
starvationVillage(6, 0, -300, array());
starvationSourceVillage(9, 77);
starvationReinforcement(96, 6, 9, array(1=>2, 2=>5));
runStarvation();
$partial = starvationFindReinforcement(96);
check($partial !== null && !in_array(96,$database->deletedReinforcements,true),
    'el refuerzo que conserva unidades no se borra');
check($partial !== null && $partial['u2'] === 0 && $partial['u1'] === 2,
    'se le acabaron los pretorianos pero conserva los legionarios');

// Las tropas de un oasis anexado cuentan como si estuvieran en la aldea, tal cual
// lo dice el oficial: la aldea las alimenta, así que también pasan hambre.
starvationReset();
starvationVillage(7, 0, -60, array());
$database->oases[7] = array(array('wref'=>500, 'type'=>1));   // oasis de madera: no toca el cereal
starvationReinforcement(97, 500, 7, array(1=>100));
runStarvation();
$garrison = starvationFindReinforcement(97);
check($garrison !== null && $garrison['u1'] === 98,
    'la guarnición de un oasis anexado también pasa hambre ('.($garrison ? $garrison['u1'] : 'borrada').')');

// ---------------------------------------------------------------------------
section('E. Sin fugas entre aldeas');
// ---------------------------------------------------------------------------
starvationReset();
// La primera aldea tiene refuerzos, la segunda no: la segunda no puede terminar
// matando tropas del refuerzo de la primera.
starvationVillage(1, 1, -5, array());
starvationSourceVillage(9, 77);
starvationReinforcement(91, 1, 9, array(1=>500));
starvationVillage(2, 4, -5, array(1=>200));
runStarvation();
check($database->units[2]['u1'] === 199, 'la segunda aldea mata sus propias tropas');
$reinforcement = starvationFindReinforcement(91);
check($reinforcement !== null && $reinforcement['u1'] === 499,
    'y el refuerzo de la primera paga sólo su propia deuda');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Starvation checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
