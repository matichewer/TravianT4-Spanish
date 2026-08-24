<?php
/**
 * Regresión de las dos funciones de oro que el oficial ofrece para demoler.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_gold_demolition.php
 *
 * El T4 oficial da tres formas de tirar un edificio desde el Edificio Principal de
 * nivel 10: nivel por nivel con su tiempo, apurar con oro el nivel que está en curso
 * (la misma función que termina las construcciones, 2 de oro) y derribar el edificio
 * entero al instante (5 de oro). Este servidor sólo tenía la primera: el "Completar"
 * existía pero era inalcanzable cuando lo único en curso era un derribo, porque tanto
 * la caja de dorf1/dorf2 como la página del Plus se guiaban por la cola de bdata.
 *
 * Cubre:
 *   A. Un solo lugar demuele. demolitionComplete() y el derribo con oro comparten
 *      Automation::demolishFieldLevel(); si alguien vuelve a escribir fdata a mano en
 *      uno de los dos caminos, se pierde la acreditación de producción, la capacidad
 *      de los almacenes o el recuento de habitantes, que es la historia del fin de
 *      obra del Plus repetida.
 *   B. El derribo completo baja hasta el nivel 0, limpia el tipo de la casilla, borra
 *      el reloj que estuviera corriendo y no cobra nada si no había nada que demoler.
 *   C. El candado de alimentos del molino y la panadería se mide contra el nivel que
 *      queda: 0 en el derribo completo, no "un nivel menos".
 *   D. Los permisos: casilla interior ocupada, Edificio Principal en DEMOLISH_LEVEL_REQ,
 *      nada encolado encima y ningún atajo con oro en una Aldea de la Maravilla.
 *   E. La demolición es alcanzable desde las dos entradas del fin de obra con oro.
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

define('TB_PREFIX', 's1_');
define('SPEED', 1);
define('BANNED', 9);
define('MODERATOR', 8);
define('DEMOLISH_LEVEL_REQ', 10);
define('ALLOW_ALL_TRIBE', false);
define('BASIC_MAX', 1);
define('INNER_MAX', 1);
define('PLUS_MAX', 1);
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Production.php';
require dirname(__DIR__).'/GameEngine/Building.php';
require dirname(__DIR__).'/GameEngine/Automation.php';

/** Capa de datos mínima: guarda fdata en memoria y anota oro, cerrojos y borrados. */
class GoldDemolitionDatabase {
    public $fields = array();
    public $village = array('maxstore' => 800, 'maxcrop' => 800);
    public $jobs = array();
    public $masterJobs = array();
    public $demolition = array();
    public $gold = 100;
    public $goldSpent = 0;
    public $locksTaken = 0;
    public $locksReleased = 0;
    public $lockAvailable = true;
    public $demolitionsDeleted = 0;
    public $queries = array();

    public function getResourceLevel($wid) { return $this->fields; }
    public function getFieldType($wid, $field) {
        return isset($this->fields['f'.$field.'t']) ? (int)$this->fields['f'.$field.'t'] : 0;
    }
    public function getFieldLevel($wid, $field) {
        return isset($this->fields['f'.$field]) ? (int)$this->fields['f'.$field] : 0;
    }
    public function setVillageLevel($wid, $field, $value) { $this->fields[$field] = (int)$value; return true; }
    public function getVillage($wid) { return $this->village; }
    public function setVillageCapacity($wid, $column, $value) { $this->village[$column] = $value; return true; }
    public function setVillageField($wid, $column, $value) { $this->village[$column] = $value; return true; }
    public function getVillageField($wid, $column) { return $column === 'owner' ? 7 : 0; }
    public function getVillagesID2($owner) { return array(array('wref' => 1)); }
    public function getDemolition($wid = 0) { return $this->demolition; }
    public function delDemolition($wid) { $this->demolition = array(); $this->demolitionsDeleted++; return true; }
    public function finishDemolition($wid) { return true; }
    public function getBuildingByField($wid, $field) {
        return isset($this->jobs[$field]) ? $this->jobs[$field] : array();
    }
    public function getMasterJobsByField($wid, $field) {
        return isset($this->masterJobs[$field]) ? $this->masterJobs[$field] : array();
    }
    public function getUserField($uid, $column, $mode) { return $column === 'gold' ? $this->gold : 0; }
    public function modifyGold($uid, $amount, $mode) {
        $this->goldSpent += (int)$amount;
        $this->gold -= (int)$amount;
        return true;
    }
    public function acquireDemolitionLock($wid) {
        if(!$this->lockAvailable) { return false; }
        $this->locksTaken++;
        return true;
    }
    public function releaseDemolitionLock($wid) { $this->locksReleased++; return true; }
    public function query($sql) { $this->queries[] = $sql; return true; }
}

/** El recuento y la acreditación de producción tocan la base de verdad: se anotan. */
class GoldDemolitionAutomation extends Automation {
    public $recounts = 0;
    public $accruals = array();
    public function recountPop($vid) { $this->recounts++; return 0; }
    protected function accrueProductionBeforeChange($villageId, $until) {
        $this->accruals[] = (int)$villageId;
    }
}

class GoldDemolitionVillage {
    public $wid = 1;
    public $pop = 0;
    public $resarray = array();
    public $ocounter = array(0, 0, 0, 0);
    public function getOasisCounter() { return $this->ocounter; }
    public function getBaseCropProduction() {
        return villageBaseCropProduction($this->resarray, $this->ocounter, SPEED);
    }
}

class GoldDemolitionLogging {
    public $logged = 0;
    public function goldDemolitionLog($wid) { $this->logged++; }
    public function goldFinLog($wid) { $this->logged++; }
}

$database = new GoldDemolitionDatabase();
$village = new GoldDemolitionVillage();
$session = (object)array('uid' => 7, 'access' => 1, 'tribe' => 1, 'plus' => 0);
$logging = new GoldDemolitionLogging();
$automation = (new ReflectionClass('GoldDemolitionAutomation'))->newInstanceWithoutConstructor();
$building = (new ReflectionClass('Building'))->newInstanceWithoutConstructor();

/** Aldea con `$cropFields` plantaciones y los edificios que se le pasen. */
function goldDemolitionVillage($cropFields, $cropLevel, $buildings = array()) {
    global $village, $database, $automation;
    $resarray = array();
    for($slot = 1; $slot <= 40; $slot++) { $resarray['f'.$slot] = 0; $resarray['f'.$slot.'t'] = 0; }
    $resarray['f99'] = 0;
    $resarray['f99t'] = 0;
    for($slot = 1; $slot <= $cropFields; $slot++) {
        $resarray['f'.$slot.'t'] = 4;
        $resarray['f'.$slot] = $cropLevel;
    }
    foreach($buildings as $slot => $pair) {
        $resarray['f'.$slot.'t'] = $pair[0];
        $resarray['f'.$slot] = $pair[1];
    }
    $village->resarray = $resarray;
    $village->pop = 0;
    $database->fields = $resarray;
    $database->jobs = array();
    $database->masterJobs = array();
    $database->demolition = array();
    $database->gold = 100;
    $database->goldSpent = 0;
    $database->demolitionsDeleted = 0;
    $database->village = array('maxstore' => 800, 'maxcrop' => 800);
    $automation->recounts = 0;
    $automation->accruals = array();
    return $village;
}

/** Cuántos habitantes hacen falta para que el cereal libre quede en $target. */
function goldDemolitionPopFor($target) {
    global $village;
    return (int)round($village->getBaseCropProduction() - $target);
}

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$buildingSource = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
$template = file_get_contents(dirname(__DIR__).'/Templates/Build/15_1.tpl');
$plus = file_get_contents(dirname(__DIR__).'/Templates/Plus/3.tpl');
$queueTemplate = file_get_contents(dirname(__DIR__).'/Templates/Building.tpl');
$dorf1 = file_get_contents(dirname(__DIR__).'/dorf1.php');
$dorf2 = file_get_contents(dirname(__DIR__).'/dorf2.php');

// ---------------------------------------------------------------------------
section('A. Un solo lugar demuele');
// ---------------------------------------------------------------------------
check(preg_match('/private function demolishFieldLevel\(\$villageId, \$field, \$until = null\)/', $automationSource) === 1,
    'Automation tiene el paso único de demolición');
check(preg_match('/function demolishFieldLevel\(.*?accrueProductionBeforeChange\(/s', $automationSource) === 1,
    'ese paso acredita la producción del nivel viejo antes de cambiarlo');
check(preg_match('/function demolishFieldLevel\(.*?applyStorageCapacityDelta\(/s', $automationSource) === 1,
    'y mueve la capacidad de almacenamiento');
check(preg_match('/function demolitionComplete\(.*?\$this->demolishFieldLevel\(/s', $automationSource) === 1,
    'el reloj del Edificio Principal delega en él');
check(preg_match('/function demolishBuildingNow\(.*?\$this->demolishFieldLevel\(/s', $automationSource) === 1,
    'y el derribo completo con oro también');
check(preg_match('/function demolitionComplete\(.*?UPDATE ".TB_PREFIX."fdata/s', $automationSource) !== 1,
    'demolitionComplete ya no escribe fdata por su cuenta');
check(strpos($buildingSource, 'fdata') === false || strpos($buildingSource, 'demolishBuildingNow($village->wid,$field)') !== false,
    'Building delega el derribo en Automation en vez de escribir los campos');

// ---------------------------------------------------------------------------
section('B. El derribo completo baja hasta el suelo y cobra una sola vez');
// ---------------------------------------------------------------------------
goldDemolitionVillage(6, 5, array(20 => array(15, 10), 21 => array(19, 12)));
$database->demolition = array(array('vref' => 1, 'buildnumber' => 21, 'lvl' => 11, 'timetofinish' => time() + 600));
$levels = $automation->demolishBuildingNow(1, 21);
check($levels === 12, 'caen los 12 niveles del cuartel de una sola vez (cayeron '.$levels.')');
check((int)$database->fields['f21'] === 0, 'la casilla queda en nivel 0');
check((int)$database->fields['f21t'] === 0, 'y sin tipo, como cualquier solar vacío');
check($automation->recounts === 12, 'cada nivel recuenta habitantes y puntos de cultura');
check($database->demolitionsDeleted === 1, 'el reloj que corría sobre esa casilla se borra');
check($database->locksTaken >= 1 && $database->locksTaken === $database->locksReleased,
    'el derribo toma y suelta el cerrojo de la aldea');

$again = $automation->demolishBuildingNow(1, 21);
check($again === 0, 'un segundo pedido sobre la casilla vacía no derriba nada');

$database->lockAvailable = false;
check($automation->demolishBuildingNow(1, 20) === 0, 'sin cerrojo no se derriba (otra petición está demoliendo)');
check((int)$database->fields['f20'] === 10, 'y el edificio queda intacto');
$database->lockAvailable = true;

// La producción sólo se acredita cuando el edificio la afectaba.
goldDemolitionVillage(6, 5, array(22 => array(9, 5), 23 => array(19, 5)));
$automation->demolishBuildingNow(1, 23);
check(empty($automation->accruals), 'un cuartel no acredita producción: no la cambia');
$automation->demolishBuildingNow(1, 22);
check(count($automation->accruals) === 5, 'la panadería sí, una vez por nivel');

// Un granero devuelve su capacidad al caer.
goldDemolitionVillage(6, 5, array(24 => array(11, 5)));
$database->village['maxcrop'] = 800 + 3800;
$automation->demolishBuildingNow(1, 24);
check((int)$database->village['maxcrop'] === 800,
    'el granero devuelve la capacidad que sumaba (quedó '.(int)$database->village['maxcrop'].')');

// ---------------------------------------------------------------------------
section('C. El candado de alimentos se mide contra el nivel que queda');
// ---------------------------------------------------------------------------
goldDemolitionVillage(6, 5, array(22 => array(9, 5), 23 => array(15, 10)));
$village->pop = goldDemolitionPopFor(1);
check($building->demolitionAllowed(22) === false,
    'con el libre justo, ni un nivel de panadería se puede tirar');
// Con 20 de libre alcanza para perder un nivel de panadería (10) pero no los cinco (50).
$village->pop = goldDemolitionPopFor(20);
check($building->demolitionAllowed(22) === true,
    'un nivel sí, cuando el libre lo aguanta');
check($building->demolitionAllowed(22, 0) === false,
    'pero la panadería entera no: el derribo completo mira el nivel 0, no "uno menos"');
check($building->demolitionAllowed(23, 0) === true,
    'cualquier otro edificio se derriba entero siempre');
$village->pop = 0;
check($building->demolitionAllowed(22, 0) === true,
    'y la panadería también, cuando el libre aguanta perderla toda');

// ---------------------------------------------------------------------------
section('D. Permisos del derribo completo');
// ---------------------------------------------------------------------------
goldDemolitionVillage(6, 5, array(20 => array(15, 10), 21 => array(19, 5), 25 => array(10, 3)));
check($building->canDemolishInstantly(21) === true, 'un edificio interior ocupado se puede derribar');
check($building->canDemolishInstantly(30) === false, 'un solar vacío no');
check($building->canDemolishInstantly(4) === false, 'una plantación tampoco: el Edificio Principal sólo demuele el centro');
check($building->canDemolishInstantly(99) === false, 'ni la Maravilla del Mundo');

$database->jobs = array(21 => array(array('id' => 1, 'field' => 21)));
check($building->canDemolishInstantly(21) === false, 'no con una mejora encolada encima');
$database->jobs = array();
$database->masterJobs = array(21 => array(array('id' => 2, 'field' => 21)));
check($building->canDemolishInstantly(21) === false, 'ni con un pedido al constructor maestro');
$database->masterJobs = array();

goldDemolitionVillage(6, 5, array(20 => array(15, 9), 21 => array(19, 5)));
check($building->canDemolishInstantly(21) === false,
    'con el Edificio Principal por debajo de '.DEMOLISH_LEVEL_REQ.' no se demuele nada');

goldDemolitionVillage(6, 5, array(20 => array(15, 10), 21 => array(19, 5)));
$village->resarray['f99t'] = 40;
$village->resarray['f99'] = 1;
check($building->canDemolishInstantly(21) === false,
    'una Aldea de la Maravilla no compra atajos con oro, igual que en el fin de obra');

// El cobro: sólo si cayó algo, y una sola vez.
goldDemolitionVillage(6, 5, array(20 => array(15, 10), 21 => array(19, 5)));
check($building->demolishInstantly(21) === 'ok', 'el derribo con oro se acepta');
check($database->goldSpent === Building::DEMOLISH_ALL_GOLD,
    'cobra exactamente '.Building::DEMOLISH_ALL_GOLD.' de oro (cobró '.$database->goldSpent.')');
check($logging->logged === 1, 'y lo deja anotado en el registro de oro');
$village->resarray['f21'] = 0;
$village->resarray['f21t'] = 0;
check($building->demolishInstantly(21) !== 'ok', 'sobre la casilla ya vacía no vuelve a aceptar');
check($database->goldSpent === Building::DEMOLISH_ALL_GOLD, 'ni vuelve a cobrar');

goldDemolitionVillage(6, 5, array(20 => array(15, 10), 21 => array(19, 5)));
$database->gold = Building::DEMOLISH_ALL_GOLD - 1;
check($building->demolishInstantly(21) === 'gold', 'sin oro suficiente no se derriba');
check($database->goldSpent === 0 && (int)$database->fields['f21'] === 5, 'y el edificio sigue en pie');

$database->gold = 100;
$session->access = BANNED;
check($building->demolishInstantly(21) === 'banned', 'una cuenta baneada no derriba');
$session->access = 1;

check(Building::DEMOLISH_ALL_GOLD === 5, 'el precio oficial del derribo completo es 5 de oro');
check(Building::FINISH_ALL_GOLD === 2, 'y el del fin de obra sigue siendo 2');

// ---------------------------------------------------------------------------
section('E. Las dos entradas del fin de obra llegan a la demolición');
// ---------------------------------------------------------------------------
check(strpos($buildingSource, 'public function finishAllNow()') !== false,
    'el fin de obra con oro se puede pedir sin redirección, desde el Edificio Principal');
check(preg_match('/function finishAllNow\(.*?finishDemolition\(\$village->wid\)/s', $buildingSource) === 1,
    'y sigue apurando la demolición en curso');
check(strpos($template, '$building->finishAllNow()') !== false,
    'la pantalla de demolición ofrece finalizar el nivel en curso con oro');
check(strpos($template, '$building->demolishInstantly((int)$_POST[\'type\'])') !== false,
    'y el derribo completo');
check(substr_count($template, 'hash_equals') === 4,
    'las cuatro acciones de la pantalla validan CSRF');
check(strpos($plus, '$demolitionnum') !== false && strpos($plus, '$demolitionnum == 0') !== false,
    'el "Completar" del Plus cuenta la demolición como obra en curso');
check(strpos($queueTemplate, 'demolitionInProgress()') !== false,
    'la caja de construcciones muestra la demolición');
check(strpos($dorf1, 'demolitionInProgress() !== null') !== false
    && strpos($dorf2, 'demolitionInProgress() !== null') !== false,
    'y dorf1/dorf2 la dibujan aunque no haya ninguna construcción');

echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Gold demolition checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
