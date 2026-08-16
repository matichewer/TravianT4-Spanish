<?php
/**
 * Auditoría de la Cervecería (edificio 35) y de su celebración de hidromiel.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_brewery.php
 *
 * La Cervecería es el único edificio cuyo efecto es de cuenta y no de aldea: mientras
 * la fiesta corre, todos los ataques que salgan de cualquier aldea germana suman 1% de
 * ataque por nivel, los jefes persuaden la mitad y las catapultas disparan al azar. El
 * bono se resuelve cuando *llega* el ataque, no cuando se envía, y el nivel se lee de
 * la capital, así que el edificio, la fiesta y la aldea que la paga son tres cosas que
 * pueden desincronizarse.
 *
 * Cubre:
 *   A. La tabla $bid35: 10 niveles, 1% por nivel, costos y tiempos crecientes.
 *   B. Los requisitos de construcción (germano, capital, plaza 10, granero 20, única).
 *   C. Las guardas del servidor en brewery.php.
 *   D. El bono de ataque en Battle: cuánto, a quién y cuándo.
 *   E. Los dos castigos de la fiesta (jefes y catapultas) atados a que esté activa.
 *   F. Costo y duración con una sola definición (cel.php) para acción y plantilla.
 *   G. Mudar la capital derriba la Cervecería y cierra la fiesta.
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

if(!defined('SPEED')) { define('SPEED', 1); }
if(!defined('ALLOW_ALL_TRIBE')) { define('ALLOW_ALL_TRIBE', false); }
if(!defined('BASIC_MAX')) { define('BASIC_MAX', 1); }
if(!defined('INNER_MAX')) { define('INNER_MAX', 1); }
if(!defined('PLUS_MAX')) { define('PLUS_MAX', 1); }
if(!defined('TB_PREFIX')) { define('TB_PREFIX', 's1_'); }
if(!defined('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP')) { define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true); }

require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Data/cel.php';
require_once dirname(__DIR__).'/GameEngine/Battle.php';
require_once dirname(__DIR__).'/GameEngine/Building.php';
require_once dirname(__DIR__).'/GameEngine/Automation.php';

$breweryPhp = file_get_contents(dirname(__DIR__).'/brewery.php');
$templatePhp = file_get_contents(dirname(__DIR__).'/Templates/Build/35.tpl');
$buildingPhp = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
$databasePhp = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
$automationPhp = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$palacePhp = file_get_contents(dirname(__DIR__).'/Templates/Build/26.tpl');
$availablePhp = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');

// ---------------------------------------------------------------------------
section('A. La tabla $bid35');
// ---------------------------------------------------------------------------
check(is_array($bid35), 'existe la tabla $bid35');
check(count($bid35) === 10, 'la Cervecería tiene exactamente 10 niveles (tiene '.count($bid35).')');
check(array_keys($bid35) === range(1, 10), 'los niveles van de 1 a 10 sin huecos');
// Los dos consumidores del tope: Building::isMax() cuenta las filas y el nivel 11 no
// existe, así que resourceRequired() sobre un nivel de más devolvería costo y tiempo
// vacíos (una mejora gratis e instantánea).
check(!isset($bid35[0]) && !isset($bid35[11]),
	'no hay filas fantasma en el nivel 0 ni en el 11');

foreach($bid35 as $level => $data) {
	check((int)$data['attri'] === $level,
		'el nivel '.$level.' da exactamente '.$level.'% de ataque (da '.$data['attri'].'%)');
	foreach(array('wood','clay','iron','crop','pop','cp','time') as $key) {
		check(isset($data[$key]), 'el nivel '.$level.' declara "'.$key.'"');
	}
	check((int)$data['pop'] > 0 && (int)$data['cp'] > 0,
		'el nivel '.$level.' consume población y da puntos de cultura');
	if($level > 1) {
		$previous = $bid35[$level - 1];
		$creciente = true;
		foreach(array('wood','clay','iron','crop','time') as $key) {
			if($data[$key] <= $previous[$key]) {
				$creciente = false;
			}
		}
		check($creciente, 'el nivel '.$level.' cuesta y tarda más que el '.($level - 1));
	}
}
check((int)$bid35[10]['attri'] === 10,
	'el tope de la tabla y el tope que aplica la batalla coinciden en 10%');
check(preg_match('/min\(10,\s*\(int\)\$fields\[/', $databasePhp) === 1
	|| strpos($databasePhp, 'min(10, $level)') !== false,
	'getBreweryLevel() acota el nivel al mismo tope de la tabla');

// ---------------------------------------------------------------------------
section('B. Requisitos de construcción');
// ---------------------------------------------------------------------------
class BreweryFakeVillage {
	public $capital = 1;
	public $wid = 1;
	public $resarray = array();
}
class BreweryFakeSession {
	public $tribe = 2;
	public $plus = 0;
	public $uid = 5;
}
class BreweryFakeDatabase {
	public $jobs = array();
	public function getDemolition($wid) { return array(); }
	public function getJobs($wid) { return $this->jobs; }
	public function getBuildingByField($wid, $field) { return array(); }
	public function getMasterJobs($wid) { return array(); }
	public function getResourceLevel($wid) { global $village; return $village->resarray; }
}

$GLOBALS['session'] = new BreweryFakeSession();
$GLOBALS['database'] = new BreweryFakeDatabase();
$village = new BreweryFakeVillage();
$GLOBALS['village'] = $village;

// Aldea germana capital con plaza de reuniones 10 y granero 20: lo justo.
function breweryResetVillage() {
	global $village, $session;
	$village->capital = 1;
	$session->tribe = 2;
	$village->resarray = array();
	for($f = 1; $f <= 40; $f++) {
		$village->resarray['f'.$f] = 0;
		$village->resarray['f'.$f.'t'] = 0;
	}
	$village->resarray['f19t'] = 16; $village->resarray['f19'] = 10; // plaza de reuniones
	$village->resarray['f20t'] = 11; $village->resarray['f20'] = 20; // granero
	$village->resarray['f21t'] = 15; $village->resarray['f21'] = 10; // edificio principal
}

$buildingClass = new ReflectionClass('Building');
$building = $buildingClass->newInstanceWithoutConstructor();
$GLOBALS['building'] = $building;
$meetRequirement = new ReflectionMethod('Building', 'meetRequirement');
$meetRequirement->setAccessible(true);

breweryResetVillage();
check($meetRequirement->invoke($building, 35) === true,
	'un germano en su capital con plaza 10 y granero 20 puede construir la Cervecería');

foreach(array(1 => 'romano', 3 => 'galo', 4 => 'naturaleza', 5 => 'natar') as $tribe => $name) {
	breweryResetVillage();
	$session->tribe = $tribe;
	check($meetRequirement->invoke($building, 35) === false,
		'un '.$name.' no puede construir la Cervecería');
}

breweryResetVillage();
$village->capital = 0;
check($meetRequirement->invoke($building, 35) === false,
	'no se puede construir la Cervecería fuera de la capital');

breweryResetVillage();
$village->resarray['f19'] = 9;
check($meetRequirement->invoke($building, 35) === false,
	'con la plaza de reuniones en 9 todavía no se puede');

breweryResetVillage();
$village->resarray['f20'] = 19;
check($meetRequirement->invoke($building, 35) === false,
	'con el granero en 19 todavía no se puede');

breweryResetVillage();
$village->resarray['f22t'] = 35; $village->resarray['f22'] = 1;
check($meetRequirement->invoke($building, 35) === false,
	'no se puede levantar una segunda Cervecería en la misma aldea');

breweryResetVillage();
$building->buildArray = array(array('field' => 22, 'type' => 35, 'level' => 1, 'master' => 0, 'loopcon' => 0));
check($meetRequirement->invoke($building, 35) === false,
	'no se puede encolar una segunda Cervecería mientras la primera está en obra');
$building->buildArray = array();

// El tope de nivel también se aplica del lado del servidor: canBuild() devuelve 1
// ("completamente mejorado") y upgradeBuilding() sólo acepta 8 y 9.
breweryResetVillage();
$village->resarray['f22t'] = 35; $village->resarray['f22'] = 10;
check($building->isMax(35, 22) === true, 'el nivel 10 es el máximo de la Cervecería');
check($building->canBuild(22, 35) === 1, 'canBuild() no ofrece un nivel 11');
check(preg_match('/private function upgradeBuilding\(\$id\).*?\$bindicate != 8 && \$bindicate != 9.*?return;/s', $buildingPhp) === 1,
	'upgradeBuilding() rechaza cualquier estado que no sea "se puede construir"');

// Una Cervecería fuera de la capital (mudanza) queda inservible: no se puede mejorar.
// Por eso la mudanza tiene que derribarla (sección G).
breweryResetVillage();
$village->resarray['f22t'] = 35; $village->resarray['f22'] = 5;
$village->capital = 0;
check($building->canBuild(22, 35) === 1,
	'una Cervecería fuera de la capital no se puede mejorar (queda muerta)');

// La vista tiene que ofrecer exactamente lo mismo que valida el servidor.
check(strpos($availablePhp, '$session->tribe == 2 && $village->capital == 1 && !$database->getBuildList(35) && $brewery == 0 && $rallypoint >= 10 && $granary == 20') !== false,
	'avaliable.tpl ofrece la Cervecería con los mismos requisitos que meetRequirement()');
check(preg_match('/case 35:\s*return \$tribe === 2 && \(int\)\$village->capital === 1;/', $buildingPhp) === 1,
	'isTribeBuildingAllowed() sigue atando la Cervecería a germanos y a la capital');

// ---------------------------------------------------------------------------
section('C. Las guardas del servidor en brewery.php');
// ---------------------------------------------------------------------------
$guardas = array(
	"\$_SERVER['REQUEST_METHOD'] === 'POST'" => 'brewery.php sólo acepta POST',
	'hash_equals' => 'brewery.php valida el token de la sesión',
	'$session->changeChecker()' => 'brewery.php rota el token para que no se pueda reenviar el formulario',
	'BANNED' => 'brewery.php rechaza a una cuenta baneada',
	'(int)$session->tribe === 2' => 'brewery.php exige que la cuenta sea germana',
	'(int)$village->capital === 1' => 'brewery.php exige que la aldea sea la capital',
	"=== 35" => 'brewery.php exige que el solar sea realmente la Cervecería',
	"resarray['f'.\$field] > 0" => 'brewery.php exige que la Cervecería esté construida',
	'$field >= 19 && $field <= 38' => 'brewery.php acota el solar al rango de edificios',
	'getBreweryCelebrationEnd' => 'brewery.php comprueba que no haya otra fiesta activa',
	'startBreweryCelebration' => 'brewery.php cobra y agenda en la misma llamada atómica',
	'breweryCelebrationCost()' => 'brewery.php toma el costo de la definición única',
	'breweryCelebrationDuration()' => 'brewery.php toma la duración de la definición única'
);
foreach($guardas as $fragmento => $mensaje) {
	check(strpos($breweryPhp, $fragmento) !== false, $mensaje);
}
check(preg_match('/\b3870\b|\b10900\b|\b259200\b/', $breweryPhp) !== 1,
	'brewery.php ya no repite a mano el costo ni la duración');
check(preg_match('/\b3870\b|\b10900\b|\b259200\b/', $templatePhp) !== 1,
	'35.tpl ya no repite a mano el costo ni la duración');

// El cobro y el arranque van bajo cerrojo y con reintegro si el segundo paso falla.
$inicio = strpos($databasePhp, 'function startBreweryCelebration(');
$fin = strpos($databasePhp, 'private function acquireBreweryLock(', $inicio);
$startSource = ($inicio !== false && $fin !== false) ? substr($databasePhp, $inicio, $fin - $inicio) : '';
check($startSource !== '', 'se encontró el cuerpo de startBreweryCelebration()');
check(strpos($startSource, 'acquireBreweryLock') !== false && strpos($startSource, 'releaseBreweryLock') !== false,
	'startBreweryCelebration() toma y suelta el cerrojo por cuenta');
check(strpos($startSource, 'finally') !== false,
	'el cerrojo se suelta también si algo tira una excepción');
check(strpos($startSource, 'deductResourcesIfAvailable') !== false,
	'el cobro es condicional y atómico (no descuenta si no alcanza)');
check(strpos($startSource, 'AND brewery <= ') !== false,
	'el UPDATE sólo prende la fiesta si no había otra corriendo');
check(strpos($startSource, 'modifyResource') !== false,
	'si el UPDATE no prende la fiesta, los recursos se devuelven');

// ---------------------------------------------------------------------------
section('D. El bono de ataque en la batalla');
// ---------------------------------------------------------------------------
class BreweryBattleDatabase {
	public $level = 0;
	public $end = 0;
	public $calls = 0;
	public function getUnit($v) { return null; }
	public function getEnforceVillage($v, $m) { return array(); }
	public function getVillageField($v, $f) { return 0; }
	public function getHeroData2($u) { return false; }
	public function getUserField($u, $f, $m) { return $f === 'tribe' ? 2 : $u; }
	public function getABTech($v) { return array(); }
	public function modifyHero2($c, $v, $u, $m) { return true; }
	public function getHeroInventory($u) { return array(); }
	public function getItemData($i) { return false; }
	public function getBreweryCelebrationEnd($u) { return $this->end; }
	// Igual que la real: sin fiesta corriendo el nivel es 0, aunque el edificio exista.
	public function getBreweryLevel($u) {
		$this->calls++;
		return $this->end > time() ? $this->level : 0;
	}
}

$battleDatabase = new BreweryBattleDatabase();
$GLOBALS['database'] = $battleDatabase;
$battle = new Battle();
$upgrades = array_fill_keys(array('b1','b2','b3','b4','b5','b6','b7','b8'), 0);

function breweryUnits($counts) {
	$row = array('hero' => 0);
	for($u = 1; $u <= 50; $u++) {
		$row['u'.$u] = isset($counts[$u]) ? $counts[$u] : 0;
	}
	return $row;
}

// $tropas: unidades germanas (11..20). u11 = Porrero (infantería), u15 = Paladín (caballería).
function breweryAttack($tropas, $level, $active, $tribe = 2, $type = 3) {
	global $battle, $battleDatabase, $upgrades;
	$battleDatabase->level = $level;
	$battleDatabase->end = $active ? time() + 3600 : 0;
	$attacker = breweryUnits($tropas);
	$attacker['id'] = 10;
	$defender = breweryUnits(array(1 => 1));
	$defender['id'] = 20;
	return $battle->calculateBattle(
		$attacker, $defender, 0, $tribe, 1, 0, 100, 100, $type,
		$upgrades, $upgrades, 0, 0, 0, 10, 20, 1000, 2000
	);
}

$infanteria = array(11 => 1000);
$caballeria = array(15 => 300);
$mixto = array(11 => 1000, 15 => 300);

$base = breweryAttack($mixto, 10, false);
check((int)$base['brewery_active'] === 0 && (int)$base['brewery_level'] === 0,
	'sin fiesta corriendo el informe no marca la Cervecería');

foreach(range(1, 10) as $level) {
	$conFiesta = breweryAttack($mixto, $level, true);
	$esperado = $base['Attack_points'] * (1 + $level / 100);
	check(abs($conFiesta['Attack_points'] - $esperado) < 0.001,
		'el nivel '.$level.' multiplica el ataque por exactamente '.(1 + $level / 100));
	check((int)$conFiesta['brewery_level'] === $level && (int)$conFiesta['brewery_active'] === 1,
		'el informe registra nivel '.$level.' y la fiesta activa');
}

// El bono va sobre los dos tipos de tropa, no sobre uno solo.
$infBase = breweryAttack($infanteria, 10, false);
$infBono = breweryAttack($infanteria, 10, true);
$cabBase = breweryAttack($caballeria, 10, false);
$cabBono = breweryAttack($caballeria, 10, true);
check(abs($infBono['Attack_points'] - $infBase['Attack_points'] * 1.10) < 0.001,
	'la infantería germana recibe el bono');
check(abs($cabBono['Attack_points'] - $cabBase['Attack_points'] * 1.10) < 0.001,
	'la caballería germana recibe el bono');

// Un nivel imposible (base tocada a mano, edificio duplicado) no puede pasar del 10%.
$absurdo = breweryAttack($mixto, 99, true);
check(abs($absurdo['Attack_points'] - $base['Attack_points'] * 1.10) < 0.001,
	'un nivel fuera de rango se acota al 10%');
check((int)$absurdo['brewery_level'] === 10, 'el informe también acota el nivel a 10');

$negativo = breweryAttack($mixto, -5, true);
check(abs($negativo['Attack_points'] - $base['Attack_points']) < 0.001,
	'un nivel negativo no resta ataque');

// Sólo germanos. Se ataca con las mismas unidades pero declarando otra tribu: el
// bloque de tropas cambia, así que lo que se mide es la bandera del informe.
foreach(array(1, 3, 4, 5) as $tribe) {
	$otra = breweryAttack(array(), 10, true, $tribe);
	check((int)$otra['brewery_active'] === 0 && (int)$otra['brewery_level'] === 0,
		'la tribu '.$tribe.' no obtiene el bono de la Cervecería');
}

// El espionaje (tipo 1) no lleva bono: no hay combate de ataque que multiplicar.
$espionaje = breweryAttack(array(14 => 50), 10, true, 2, 1);
check((int)$espionaje['brewery_level'] === 0,
	'un espionaje no aplica el bono de ataque');

// Y el defensor no lo recibe nunca: el bono se busca por el atacante.
$defensaBase = breweryAttack($mixto, 10, false);
$defensaBono = breweryAttack($mixto, 10, true);
check(abs($defensaBase['Defend_points'] - $defensaBono['Defend_points']) < 0.001,
	'la Cervecería no toca la defensa');

// La fiesta se evalúa cuando llega el ataque: una que venció hace un segundo no vale.
$battleDatabase->level = 10;
$battleDatabase->end = time() - 1;
$vencida = $battle->calculateBattle(
	array_merge(breweryUnits($mixto), array('id' => 10)),
	array_merge(breweryUnits(array(1 => 1)), array('id' => 20)),
	0, 2, 1, 0, 100, 100, 3, $upgrades, $upgrades, 0, 0, 0, 10, 20, 1000, 2000
);
check((int)$vencida['brewery_active'] === 0 && abs($vencida['Attack_points'] - $base['Attack_points']) < 0.001,
	'una fiesta vencida no da bono');

// ---------------------------------------------------------------------------
section('E. Los dos castigos de la fiesta');
// ---------------------------------------------------------------------------
// El castigo va atado a que la fiesta corra, no al nivel del edificio: si se mira el
// nivel, una capital mudada o una Cervecería arrasada dejaba a los jefes castigados
// pero a las catapultas apuntando.
check(preg_match('/resolveCatapultAttacks\(\$data, \$battleResult, \$stonemasonLevel, \$targetVillage, \$breweryActive\)/', $automationPhp) === 1,
	'resolveCatapultAttacks() recibe si la fiesta está activa, no el nivel');
check(strpos($automationPhp, '$breweryLevel') === false,
	'ya no queda ninguna decisión colgada del nivel de la Cervecería');
check(preg_match('/\$breweryActive = !empty\(\$battlepart\[\'brewery_active\'\]\);/', $automationPhp) === 1,
	'la bandera sale del resultado de la batalla');
check(preg_match('/if\(\$breweryActive\) \{\s*\$loyaltyDamage = max\(1, \(int\)floor\(\$loyaltyDamage \/ 2\)\);/', $automationPhp) === 1,
	'la fiesta parte a la mitad la persuasión de los jefes (con piso 1)');
check(preg_match('/\$breweryActive = false;/', $automationPhp) === 1,
	'la bandera se reinicia por ataque (el foreach comparte el scope)');

// Y ahora, el comportamiento real: con la fiesta corriendo la catapulta ignora el
// objetivo elegido y pega en otro lado.
class BreweryCatapultDatabase {
	public $fields = array();
	public $village = array('maxstore' => 2000, 'maxcrop' => 2000);
	public function getResourceLevel($villageId) { return $this->fields; }
	public function setVillageLevel($villageId, $field, $value) { $this->fields[$field] = (int)$value; }
	public function getVillage($villageId) { return $this->village; }
	public function setVillageField($villageId, $field, $value) { $this->village[$field] = $value; }
	public function setVillageCapacity($villageId, $field, $value) { $this->village[$field] = $value; }
	public function getVillageField($villageId, $field) { return $field === 'owner' ? 77 : 0; }
	public function getVillagesID2($owner) { return array(array('wref' => 900)); }
	public function query($sql) { return true; }
}
class BreweryAutomationForTest extends Automation {
	public function recountPop($villageId) { return 1; }
	protected function accrueProductionBeforeChange($villageId, $until) { return true; }
}

$catapultDatabase = new BreweryCatapultDatabase();
$GLOBALS['database'] = $catapultDatabase;
$GLOBALS['battle'] = new Battle();
$automation = (new ReflectionClass('BreweryAutomationForTest'))->newInstanceWithoutConstructor();
$resolve = new ReflectionMethod('Automation', 'resolveCatapultAttacks');
$resolve->setAccessible(true);

// La aldea tiene sólo dos edificios: el elegido (f19 = plaza de reuniones, tipo 16) y
// un señuelo (f20 = edificio principal, tipo 15). Con puntería, cae el elegido.
function breweryCatapultFields() {
	$fields = array();
	for($f = 1; $f <= 40; $f++) {
		$fields['f'.$f] = 0;
		$fields['f'.$f.'t'] = 0;
	}
	$fields['f19'] = 10; $fields['f19t'] = 16;
	$fields['f20'] = 10; $fields['f20t'] = 15;
	return $fields;
}

$catapultDatabase->fields = breweryCatapultFields();
$resolve->invoke($automation,
	array('to' => 900, 'ctar1' => 16, 'ctar2' => 0),
	array(4 => 100000, 5 => 1, 6 => 0),
	0,
	array('owner' => 77, 'capital' => 0),
	false
);
check((int)$catapultDatabase->fields['f19'] === 0 && (int)$catapultDatabase->fields['f20'] === 10,
	'sin fiesta, la catapulta pega exactamente en el objetivo elegido');

// Con la fiesta corriendo el objetivo se descarta: sobre 40 tiradas, el señuelo tiene
// que comerse al menos una. (La probabilidad de que no pase es despreciable.)
$golpeoElSenuelo = false;
$golpeoElElegido = false;
for($intento = 0; $intento < 40; $intento++) {
	$catapultDatabase->fields = breweryCatapultFields();
	$resolve->invoke($automation,
		array('to' => 900, 'ctar1' => 16, 'ctar2' => 0),
		array(4 => 100000, 5 => 1, 6 => 0),
		0,
		array('owner' => 77, 'capital' => 0),
		true
	);
	if((int)$catapultDatabase->fields['f20'] === 0) { $golpeoElSenuelo = true; }
	if((int)$catapultDatabase->fields['f19'] === 0) { $golpeoElElegido = true; }
}
check($golpeoElSenuelo, 'con la fiesta corriendo la catapulta puede pegar fuera del objetivo elegido');
check($golpeoElElegido, 'el disparo al azar sigue pudiendo caer en el objetivo elegido');

// ---------------------------------------------------------------------------
section('F. Costo y duración con una sola definición');
// ---------------------------------------------------------------------------
$cost = breweryCelebrationCost();
check(is_array($cost) && array_keys($cost) === array('wood','clay','iron','crop'),
	'breweryCelebrationCost() devuelve los cuatro recursos');
foreach($cost as $recurso => $cantidad) {
	check(is_int($cantidad) && $cantidad > 0, 'el costo en '.$recurso.' es un entero positivo');
}
check(breweryCelebrationDuration() === 259200,
	'a velocidad 1 la fiesta dura 72 horas (dura '.breweryCelebrationDuration().'s)');
check(strpos($templatePhp, 'breweryCelebrationCost()') !== false
	&& strpos($templatePhp, 'breweryCelebrationDuration()') !== false,
	'35.tpl muestra el costo y la duración que después cobra brewery.php');
check(strpos($templatePhp, "getBreweryCelebrationEnd(\$session->uid)") !== false,
	'35.tpl lee el reloj de la fiesta de la cuenta, no de la aldea');
// Los cuatro estados del formulario tienen que ser excluyentes y en este orden: fiesta
// corriendo, aldea/tribu que no puede celebrar, sin recursos, botón. La vista tiene que
// negar lo mismo que niega el servidor, o queda un botón que rebota sin explicación.
check(preg_match('/if\(\$breweryEnd > time\(\)\).*?\} elseif\(\(int\)\$session->tribe !== 2 \|\| \(int\)\$village->capital !== 1\).*?\} elseif\(\$breweryCost\[\'wood\'\] > \$village->awood.*?\} else \{.*?<form method="post" action="brewery\.php">/s', $templatePhp) === 1,
	'el botón sólo aparece en la capital germana, sin fiesta activa y con recursos suficientes');
check(strpos($templatePhp, 'htmlspecialchars($session->mchecker') !== false,
	'el token del formulario va escapado');
foreach(array('success', 'active', 'failed') as $estado) {
	check(strpos($templatePhp, "'".$estado."'") !== false,
		'35.tpl distingue el resultado "'.$estado.'"');
}

// La ayuda del edificio tiene que contar la misma duración que se agenda, y eso
// depende de SPEED: la frase fija "72 horas" mentía en cualquier servidor que no
// fuera x1 (en x3 el jugador leía 72 y recibía 24).
$helpPhp = file_get_contents(dirname(__DIR__).'/Templates/Build/build_level_help.tpl');
check(strpos($helpPhp, "\$buildingHelpType === 'brewery'") !== false,
	'la ayuda del edificio cubre la Cervecería');
check(strpos($helpPhp, 'breweryCelebrationDuration()') !== false,
	'la ayuda calcula la duración con la misma definición que agenda brewery.php');
check(preg_match('/dura 72 horas/', $helpPhp) !== 1,
	'la ayuda ya no anuncia una duración fija que sólo vale a velocidad 1');

// Y el texto que sale, con la duración de verdad. Se arma acá el mismo bloque que
// arma la plantilla, para cada velocidad de servidor.
require_once dirname(__DIR__).'/GameEngine/GeneratorX.php';
$generator = new GeneratorX();
check($generator->getTimeFormat(breweryCelebrationDuration()) === '72:00:00',
	'a velocidad 1 la ayuda dice 72:00:00');

$helpStart = strpos($helpPhp, "\$buildingHelpType === 'brewery'");
$helpEnd = strpos($helpPhp, 'elseif ($buildingHelpType', $helpStart + 10);
$helpBrewery = substr($helpPhp, $helpStart, $helpEnd - $helpStart);
check(strpos($helpBrewery, "(int)SPEED !== 1") !== false,
	'la ayuda aclara la velocidad del servidor sólo cuando no es x1');
check(strpos($helpBrewery, '72:00:00 a velocidad 1') !== false,
	'y en ese caso sigue dando la referencia de x1 para comparar con la wiki');

// Se renderiza el bloque de verdad (SPEED vale 1 en este proceso) y se mira el texto,
// que es lo que termina leyendo el jugador.
$buildingHelpType = 'brewery';
$buildingHelpLevel = 7;
ob_start();
include dirname(__DIR__).'/Templates/Build/build_level_help.tpl';
$helpRendered = ob_get_clean();
check(strpos($helpRendered, 'La celebración dura 72:00:00 horas') !== false,
	'a velocidad 1 el texto renderizado anuncia 72:00:00');
check(strpos($helpRendered, 'este servidor va a x') === false,
	'a velocidad 1 no se aclara la velocidad (sería ruido)');
check(strpos($helpRendered, 'Ventajas de la Cervecería') !== false,
	'el bloque renderizado es el de la Cervecería');

// ---------------------------------------------------------------------------
section('G. Mudar la capital derriba la Cervecería');
// ---------------------------------------------------------------------------
check(strpos($palacePhp, '$capitalOnlyBuildings = array(34, 35);') !== false,
	'la mudanza de capital derriba también la Cervecería, no sólo el Taller de cantería');
check(preg_match('/\$fieldUpdates\[\] = \'`f\'\.\$i\.\'t` = 0\';\s*\$fieldUpdates\[\] = \'`f\'\.\$i\.\'` = 0\';/', $palacePhp) === 1,
	'derribarla limpia nivel y tipo del solar');
check(strpos($palacePhp, "IN (34,35)") !== false,
	'no se puede mudar la capital con una obra encolada sobre esos edificios (volvería a levantarlos)');
check(strpos($palacePhp, "users` SET `brewery` = 0") !== false,
	'al derribar la Cervecería se cierra la fiesta (si no, quedan los castigos sin el bono)');
check(preg_match('/UNLOCK TABLES.*?users` SET `brewery` = 0/s', $palacePhp) === 1,
	'el cierre de la fiesta va fuera del LOCK TABLES, que no incluye a users');

echo "\n";
if(count($GLOBALS['fails']) > 0) {
	echo "Brewery checks FAILED (".count($GLOBALS['fails'])." de ".$GLOBALS['checks'].").\n";
	exit(1);
}
echo "Brewery checks passed (".$GLOBALS['checks']." comprobaciones).\n";
exit(0);
