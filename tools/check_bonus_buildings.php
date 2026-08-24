<?php
/**
 * Auditoría completa de los edificios de bonus: Aserradero (bid5), Fábrica de
 * ladrillos (bid6), Fundición de hierro (bid7), Molino (bid8) y Panadería (bid9).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_bonus_buildings.php
 *
 * Cubre:
 *   A. Integridad de las tablas de datos (5 niveles, +5% por nivel, costos y tiempos).
 *   B. Fórmula de producción (Production.php): bonos sobre la producción de los
 *      campos, molino y panadería sumados y no en cascada, oasis y bono de oro.
 *   C. Robustez: niveles fuera de rango, edificios duplicados, campos 39/40.
 *   D. Requisitos de construcción (Building::meetRequirement), incluido el límite
 *      de uno por aldea.
 *   E. Validación del constructor maestro (Building::masterBuildingRequest).
 *   F. Que las cuatro rutas que escriben recursos usen la misma fórmula y que la
 *      producción se cobre antes de que cambie el nivel de un edificio.
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

define('SPEED', 1);
require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Production.php';
require_once dirname(__DIR__).'/GameEngine/Building.php';

$BONUS = array(5=>'Aserradero',6=>'Fábrica de ladrillos',7=>'Fundición de hierro',8=>'Molino',9=>'Panadería');

/** Aldea sintética: array('campo' => array(tipo, nivel)). */
function bonusFields($fields) {
	$res = array();
	for($f = 1; $f <= 40; $f++) { $res['f'.$f] = 0; $res['f'.$f.'t'] = 0; }
	foreach($fields as $slot => $pair) {
		$res['f'.$slot.'t'] = $pair[0];
		$res['f'.$slot] = $pair[1];
	}
	return $res;
}

/** 4 campos de cada recurso a nivel 5 => 132/h de cada uno. */
function bonusBaseVillage($extra = array()) {
	$fields = array();
	$slot = 1;
	foreach(array(1,2,3,4) as $type) {
		for($i = 0; $i < 4; $i++) { $fields[$slot++] = array($type,5); }
	}
	foreach($extra as $s => $pair) { $fields[$s] = $pair; }
	return bonusFields($fields);
}

function bonusProduction($resarray, $ocounter = array(0,0,0,0), $flags = array(false,false,false,false), $speed = 1) {
	return villageGrossProduction($resarray,$ocounter,$flags,$speed);
}

// ---------------------------------------------------------------------------
section('A. Tablas de datos');
// ---------------------------------------------------------------------------
foreach($BONUS as $type => $name) {
	$data = $GLOBALS['bid'.$type];
	check(count($data) === 5, $name.' tiene 5 niveles');
	check(array_keys($data) === array(1,2,3,4,5), $name.' se indexa de 1 a 5');
	foreach($data as $level => $row) {
		check((int)$row['attri'] === $level * 5, $name.' nivel '.$level.' da +'.($level*5).'%');
		foreach(array('wood','clay','iron','crop','pop','cp','time') as $key) {
			check(isset($row[$key]) && $row[$key] > 0, $name.' nivel '.$level.' define '.$key);
		}
		if($level > 1) {
			foreach(array('wood','clay','iron','crop','time') as $key) {
				check($row[$key] > $data[$level - 1][$key], $name.' nivel '.$level.' cuesta más '.$key.' que el anterior');
			}
		}
	}
	check((int)$data[5]['attri'] === 25, $name.' llega al 25% en el nivel máximo');
}

// ---------------------------------------------------------------------------
section('B. Fórmula de producción');
// ---------------------------------------------------------------------------
$plain = bonusProduction(bonusBaseVillage());
foreach(array('wood','clay','iron','crop') as $resource) {
	check($plain['production'][$resource] === 132.0 || $plain['production'][$resource] == 132,
		'sin edificios de bonus la producción de '.$resource.' son los campos (132)');
}

// Cada nivel suma su 5% sobre la producción de los campos.
foreach(array(5=>'wood',6=>'clay',7=>'iron') as $type => $resource) {
	foreach(array(1,2,3,4,5) as $level) {
		$out = bonusProduction(bonusBaseVillage(array(19=>array($type,$level))));
		$expected = round(132 * (1 + $level * 0.05));
		check($out['production'][$resource] == $expected,
			$BONUS[$type].' nivel '.$level.' produce '.$expected.' de '.$resource.' (dio '.$out['production'][$resource].')');
		check($out['breakdown'][$resource]['building_level'] === $level
			&& $out['breakdown'][$resource]['building_percent'] == $level * 5,
			$BONUS[$type].' nivel '.$level.' informa su nivel y porcentaje en el desglose');
	}
	// Un edificio de bonus no toca los otros recursos.
	$out = bonusProduction(bonusBaseVillage(array(19=>array($type,5))));
	foreach(array('wood','clay','iron','crop') as $other) {
		if($other === $resource) { continue; }
		check($out['production'][$other] == 132, $BONUS[$type].' no altera la producción de '.$other);
	}
}

// Molino y panadería suman sobre los campos: 5+5 son +50%, no +56,25%.
$mill = bonusProduction(bonusBaseVillage(array(19=>array(8,5))));
check($mill['production']['crop'] == 165, 'molino nivel 5 da +25% de cereal');
$bakeryOnly = bonusProduction(bonusBaseVillage(array(19=>array(9,5))));
check($bakeryOnly['production']['crop'] == 165, 'la panadería sigue dando su bono sin molino (si lo demolieron)');
$both = bonusProduction(bonusBaseVillage(array(19=>array(8,5),20=>array(9,5))));
check($both['production']['crop'] == 198, 'molino 5 + panadería 5 son +50% (198), no en cascada (206)');
check($both['breakdown']['crop']['grainmill_bonus'] == 33 && $both['breakdown']['crop']['bakery_bonus'] == 33,
	'el desglose reparte 33 y 33 entre molino y panadería');
foreach(array(array(1,1,10),array(3,2,25),array(5,3,40),array(2,5,35)) as $combo) {
	list($millLevel,$bakeryLevel,$percent) = $combo;
	$out = bonusProduction(bonusBaseVillage(array(19=>array(8,$millLevel),20=>array(9,$bakeryLevel))));
	check($out['production']['crop'] == round(132 * (1 + $percent / 100)),
		'molino '.$millLevel.' + panadería '.$bakeryLevel.' son +'.$percent.'%');
}

// Oasis y bono de oro se aplican sobre lo ya bonificado, igual que en dorf1.
$withOasis = bonusProduction(bonusBaseVillage(array(19=>array(5,5))), array(1,0,0,0));
check($withOasis['production']['wood'] == 206, 'aserradero 5 + un oasis de madera dan 206');
$withGold = bonusProduction(bonusBaseVillage(array(19=>array(5,5))), array(0,0,0,0), array(true,false,false,false));
check($withGold['production']['wood'] == 206, 'aserradero 5 + bono de oro dan 206');
$withBoth = bonusProduction(bonusBaseVillage(array(19=>array(5,5))), array(1,0,0,0), array(true,false,false,false));
check($withBoth['production']['wood'] == round(132 * 1.25 * 1.25 * 1.25), 'aserradero, oasis y bono de oro se componen');
$fast = bonusProduction(bonusBaseVillage(array(19=>array(5,5))), array(0,0,0,0), array(false,false,false,false), 3);
check($fast['production']['wood'] == 495, 'la velocidad del servidor multiplica al final');

// El desglose que consume el tooltip de dorf1 mantiene sus claves.
foreach(array('fields','building_bonus','oasis_percent','oasis_bonus','plus_percent','plus_bonus','speed','gross') as $key) {
	check(array_key_exists($key,$withBoth['breakdown']['wood']), 'el desglose de madera expone '.$key);
}
foreach(array('grainmill_level','grainmill_percent','grainmill_bonus','bakery_level','bakery_percent','bakery_bonus','building_bonus') as $key) {
	check(array_key_exists($key,$both['breakdown']['crop']), 'el desglose de cereal expone '.$key);
}

// ---------------------------------------------------------------------------
section('C. Casos límite');
// ---------------------------------------------------------------------------
$zero = bonusProduction(bonusBaseVillage(array(19=>array(5,0))));
check($zero['production']['wood'] == 132, 'un aserradero en construcción (nivel 0) no da bono');
check($zero['breakdown']['wood']['building_percent'] == 0, 'y el desglose lo muestra en 0%');

$overflow = bonusProduction(bonusBaseVillage(array(19=>array(5,9))));
check($overflow['production']['wood'] == 165, 'un nivel imposible se recorta al máximo en vez de anular el bono');

$twin = bonusProduction(bonusBaseVillage(array(19=>array(5,5),20=>array(5,1))));
check($twin['production']['wood'] == 165, 'con dos aserraderos manda el de mayor nivel, no el del campo más alto');
$twinReversed = bonusProduction(bonusBaseVillage(array(19=>array(5,1),20=>array(5,5))));
check($twinReversed['production']['wood'] == 165, 'y el resultado no depende del orden de los campos');

$noFields = bonusProduction(bonusFields(array(19=>array(5,5))));
check($noFields['production']['wood'] == 0, 'sin campos de madera el bono sobre 0 sigue siendo 0');

$highSlot = bonusProduction(bonusBaseVillage(array(39=>array(5,5))));
check($highSlot['production']['wood'] == 165, 'un edificio de bonus en los campos 39/40 también cuenta');

$empty = villageGrossProduction(array(), array(), array(), 1);
check($empty['production']['crop'] == 0, 'una aldea sin datos no rompe la fórmula');

check(villageOasisCounter(array(array('type'=>1),array('type'=>12))) === array(1,0,0,2),
	'el contador de oasis coincide con Village::sortOasis');

// ---------------------------------------------------------------------------
section('D. Requisitos de construcción');
// ---------------------------------------------------------------------------
$reflection = new ReflectionClass('Building');
$buildingProbe = $reflection->newInstanceWithoutConstructor();
$meetRequirement = $reflection->getMethod('meetRequirement');
$meetRequirement->setAccessible(true);
$buildArrayProperty = $reflection->getProperty('buildArray');
$buildArrayProperty->setAccessible(true);

function bonusBuildingProbe($fields, $jobs = array(), $capital = 0) {
	global $buildingProbe, $buildArrayProperty, $village, $session;
	$village = new BonusProbeVillage();
	$village->capital = $capital;
	$village->resarray = bonusFields($fields);
	$session = (object)array('tribe'=>1);
	$buildArrayProperty->setValue($buildingProbe,$jobs);
	return $buildingProbe;
}

$requirements = array(
	5 => array('field'=>array(1,10),'main'=>5,'name'=>'Aserradero'),
	6 => array('field'=>array(2,10),'main'=>5,'name'=>'Fábrica de ladrillos'),
	7 => array('field'=>array(3,10),'main'=>5,'name'=>'Fundición de hierro')
);
foreach($requirements as $type => $spec) {
	list($fieldType,$fieldLevel) = $spec['field'];
	bonusBuildingProbe(array(1=>array($fieldType,$fieldLevel),19=>array(15,5)));
	check($meetRequirement->invoke($buildingProbe,$type) === true, $spec['name'].': permitido con los requisitos');
	bonusBuildingProbe(array(1=>array($fieldType,$fieldLevel - 1),19=>array(15,5)));
	check($meetRequirement->invoke($buildingProbe,$type) === false, $spec['name'].': exige el campo a nivel '.$fieldLevel);
	bonusBuildingProbe(array(1=>array($fieldType,$fieldLevel),19=>array(15,4)));
	check($meetRequirement->invoke($buildingProbe,$type) === false, $spec['name'].': exige Edificio principal 5');
	// Ya construido o en cola: no se puede repetir.
	bonusBuildingProbe(array(1=>array($fieldType,$fieldLevel),19=>array(15,5),20=>array($type,1)));
	check($meetRequirement->invoke($buildingProbe,$type) === false, $spec['name'].': uno solo por aldea');
	bonusBuildingProbe(array(1=>array($fieldType,$fieldLevel),19=>array(15,5)),
		array(array('field'=>21,'type'=>$type,'master'=>0,'level'=>1)));
	check($meetRequirement->invoke($buildingProbe,$type) === false, $spec['name'].': tampoco si ya está en la cola');
}

bonusBuildingProbe(array(1=>array(4,5),19=>array(15,5)));
check($meetRequirement->invoke($buildingProbe,8) === true, 'Molino: permitido con Granja 5 y Edificio principal 5');
bonusBuildingProbe(array(1=>array(4,4),19=>array(15,5)));
check($meetRequirement->invoke($buildingProbe,8) === false, 'Molino: exige Granja 5');
bonusBuildingProbe(array(1=>array(4,5),19=>array(15,4)));
check($meetRequirement->invoke($buildingProbe,8) === false, 'Molino: exige Edificio principal 5');
bonusBuildingProbe(array(1=>array(4,5),19=>array(15,5),20=>array(8,1)));
check($meetRequirement->invoke($buildingProbe,8) === false, 'Molino: uno solo por aldea');

bonusBuildingProbe(array(1=>array(4,10),19=>array(15,5),20=>array(8,5)));
check($meetRequirement->invoke($buildingProbe,9) === true, 'Panadería: permitida con Granja 10, Molino 5 y Edificio principal 5');
bonusBuildingProbe(array(1=>array(4,9),19=>array(15,5),20=>array(8,5)));
check($meetRequirement->invoke($buildingProbe,9) === false, 'Panadería: exige Granja 10');
bonusBuildingProbe(array(1=>array(4,10),19=>array(15,5),20=>array(8,4)));
check($meetRequirement->invoke($buildingProbe,9) === false, 'Panadería: exige Molino 5');
bonusBuildingProbe(array(1=>array(4,10),19=>array(15,4),20=>array(8,5)));
check($meetRequirement->invoke($buildingProbe,9) === false, 'Panadería: exige Edificio principal 5');
bonusBuildingProbe(array(1=>array(4,10),19=>array(15,5),20=>array(8,5),21=>array(9,2)));
check($meetRequirement->invoke($buildingProbe,9) === false, 'Panadería: una sola por aldea');

// La lista de construcción tiene que ocultar lo mismo que rechaza el motor.
$available = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');
check(strpos($available,'$cropland >= 5 && $mainbuilding >= 5 && !$database->getBuildList(8) && $grainmill == 0') !== false,
	'la lista de construcción exige Edificio principal 5 para el Molino');
foreach(array(5=>'sawmill',6=>'brickyard',7=>'ironfoundry',8=>'grainmill',9=>'bakery') as $type => $var) {
	check(strpos($available,'$'.$var.' == 0 && !$database->getBuildList('.$type.')') !== false
		|| strpos($available,'!$database->getBuildList('.$type.') && ') !== false,
		'la lista de construcción oculta '.$BONUS[$type].' cuando ya existe o está en cola');
}

// ---------------------------------------------------------------------------
section('E. Constructor maestro');
// ---------------------------------------------------------------------------
class BonusMasterDatabaseStub {
	public $fields = array();
	public $jobs = array();
	public function getDemolition($wid) { return array(); }
	public function getBuildingByField($wid,$field) {
		$out = array();
		foreach($this->jobs as $job) {
			if((int)$job['field'] === (int)$field && (int)$job['master'] === 0) { $out[] = $job; }
		}
		return $out;
	}
	public function getResourceLevel($wid) { return $this->fields; }
	public function getJobs($wid) { return $this->jobs; }
}

/**
 * Aldea del sondeo. Tiene que saber su cereal libre porque el maestro constructor
 * pasa por el candado de alimentos igual que la construccion normal.
 */
class BonusProbeVillage {
	public $capital = 0;
	public $wid = 1;
	public $pop = 0;
	public $resarray = array();
	public $ocounter = array(0,0,0,0);
	public function getOasisCounter() { return $this->ocounter; }
	public function getBaseCropProduction() {
		return villageBaseCropProduction($this->resarray,$this->ocounter,SPEED);
	}
	public function getFreeCrop() {
		return villageFreeCrop($this->resarray,$this->ocounter,$this->pop,SPEED);
	}
	public function getProd($type) { return 1000; }
}

$masterRequest = $reflection->getMethod('masterBuildingRequest');
$masterRequest->setAccessible(true);

function bonusMasterProbe($fields, $jobs = array(), $capital = 0) {
	global $buildingProbe, $buildArrayProperty, $village, $session, $database;
	$village = new BonusProbeVillage();
	$village->capital = $capital;
	$village->resarray = bonusFields($fields);
	$session = (object)array('tribe'=>1);
	$database = new BonusMasterDatabaseStub();
	$database->fields = $village->resarray;
	$database->jobs = $jobs;
	$buildArrayProperty->setValue($buildingProbe,$jobs);
	return $buildingProbe;
}

$ready = array(1=>array(1,10),2=>array(2,10),3=>array(3,10),4=>array(4,10),19=>array(15,5));

bonusMasterProbe($ready + array(20=>array(5,3)));
$request = $masterRequest->invoke($buildingProbe,20,5);
check(is_array($request) && $request['level'] === 4, 'el maestro sube el aserradero al nivel siguiente');
check(is_array($request) && $request['time'] > 1, 'y calcula la duración desde las tablas, no desde la URL');

bonusMasterProbe($ready + array(20=>array(5,5)));
check($masterRequest->invoke($buildingProbe,20,5) === false, 'el maestro no puede pasar del nivel 5 del aserradero');

bonusMasterProbe($ready + array(20=>array(5,5)));
check($masterRequest->invoke($buildingProbe,21,5) === false, 'el maestro no puede construir un segundo aserradero');

bonusMasterProbe($ready + array(20=>array(5,3)));
check($masterRequest->invoke($buildingProbe,20,6) === false, 'el maestro no puede cambiar el tipo de un campo ocupado');

bonusMasterProbe($ready);
$newRequest = $masterRequest->invoke($buildingProbe,20,5);
check(is_array($newRequest) && $newRequest['level'] === 1, 'el maestro puede construir el aserradero que falta');
check($masterRequest->invoke($buildingProbe,5,5) === false, 'pero no en un campo de recursos');
check($masterRequest->invoke($buildingProbe,41,5) === false, 'ni en un campo inexistente');
check($masterRequest->invoke($buildingProbe,20,99) === false, 'ni con un tipo de edificio inexistente');
check($masterRequest->invoke($buildingProbe,20,40) === false, 'ni una Maravilla del Mundo');

bonusMasterProbe(array(1=>array(1,9),19=>array(15,5)));
check($masterRequest->invoke($buildingProbe,20,5) === false, 'el maestro respeta los requisitos del aserradero');

bonusMasterProbe($ready + array(20=>array(5,3)), array(array('field'=>20,'type'=>5,'master'=>0,'level'=>4)));
$queued = $masterRequest->invoke($buildingProbe,20,5);
check(is_array($queued) && $queued['level'] === 5, 'el maestro encola el nivel siguiente al que ya está en obra');

bonusMasterProbe($ready + array(20=>array(5,4)), array(array('field'=>20,'type'=>5,'master'=>0,'level'=>5)));
check($masterRequest->invoke($buildingProbe,20,5) === false, 'y no encola un nivel 6 detrás del nivel 5 en obra');

bonusMasterProbe($ready + array(20=>array(5,3)), array(array('field'=>20,'type'=>5,'master'=>1,'level'=>4)));
check($masterRequest->invoke($buildingProbe,20,5) === false, 'no se acumulan dos pedidos del maestro en el mismo campo');

foreach(array('dorf1.php','dorf2.php') as $entry) {
	$source = file_get_contents(dirname(__DIR__).'/'.$entry);
	check(strpos($source,'$building->masterBuildingRequest($_GET[\'id\'],$_GET[\'master\'])') !== false,
		$entry.' valida el pedido del constructor maestro');
	check(strpos($source,"\$_GET['time']") === false, $entry.' ya no confía en la duración que viene por la URL');
	// El pedido gasta oro: sin token, un enlace externo podía dispararlo.
	check(strpos($source,'hash_equals((string)$session->mchecker,(string)$_GET[\'c\'])') !== false,
		$entry.' exige el token de sesión para usar el constructor maestro');
}
$upgradeTemplate = file_get_contents(dirname(__DIR__).'/Templates/Build/upgrade.tpl');
check(strpos($upgradeTemplate,'$masterToken = urlencode((string)$session->mchecker);') !== false,
	'el enlace del constructor maestro lleva el token de sesión');
check(substr_count($upgradeTemplate,'master=$bid&id=$id&c=$masterToken') === 6,
	'los seis enlaces del constructor maestro usan el token y no la duración');
check(strpos($upgradeTemplate,'time=$mastertime') === false,
	'ningún enlace manda ya la duración por la URL');

// ---------------------------------------------------------------------------
section('F. Una sola fórmula y producción no retroactiva');
// ---------------------------------------------------------------------------
$sources = array(
	'GameEngine/Village.php',
	'GameEngine/Automation.php',
	'Templates/dorf3/3.tpl'
);
foreach($sources as $source) {
	$code = file_get_contents(dirname(__DIR__).'/'.$source);
	check(strpos($code,'villageGrossProduction(') !== false, $source.' usa la fórmula compartida');
}
// Nadie vuelve a calcular un bono de edificio a mano, ni siquiera la capa de datos.
foreach(array_merge($sources,array('GameEngine/Database/db_MYSQLi.php')) as $source) {
	$code = file_get_contents(dirname(__DIR__).'/'.$source);
	check(!preg_match('/bid9\[[^\]]+\]\[.attri.\]/',$code), $source.' no recalcula el bono de la panadería por su cuenta');
	check(!preg_match('/bid5\[[^\]]+\]\[.attri.\]/',$code), $source.' no recalcula el bono del aserradero por su cuenta');
}

$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
check(strpos($automation,'accrueProductionBeforeChange($indi[\'wid\'], $indi[\'timestamp\'])') !== false,
	'al terminar una construcción se cobra la producción vieja hasta ese instante');
// La demolición pasa por Automation::demolishFieldLevel(), que recibe el instante en
// que vence el reloj y acredita con él antes de tocar el campo.
check(strpos($automation,'$this->demolishFieldLevel($vil[\'vref\'], $vil[\'buildnumber\'], $vil[\'timetofinish\'])') !== false
	&& preg_match('/function demolishFieldLevel\(.*?accrueProductionBeforeChange\(\$villageId, \$until/s',$automation) === 1,
	'al terminar una demolición también');
check(strpos($automation,'$this->accrueProductionBeforeChange($data[\'from\'], $conquestTime)') !== false,
	'al conquistar un oasis se cierra el tramo de producción de la aldea que lo gana');
check(strpos($automation,'$this->accrueProductionBeforeChange($o_conqured, $conquestTime)') !== false,
	'y también el de la aldea que lo pierde');
check(preg_match('/if\(\(int\)\$indi\[.type.\] >= 1 && \(int\)\$indi\[.type.\] <= 9\)/',$automation) === 1,
	'la corrección alcanza a los campos de recursos y a los cinco edificios de bonus');
check(strpos($automation,'$database->accrueVillageResources($bountywid, $lastupdate, $now,') !== false,
	'la acreditación por saqueo respeta el tope del almacén y el reloj de la aldea');
check(strpos($automation,'villageGoldBonusFlags($database, $uid)') !== false,
	'la acreditación por saqueo aplica el bono de producción de oro del dueño');

$villageEngine = file_get_contents(dirname(__DIR__).'/GameEngine/Village.php');
check(strpos($villageEngine,'require_once __DIR__."/Production.php"') !== false, 'Village.php carga la fórmula compartida');

// La ficha del edificio muestra 0% mientras está en obra (nivel 0), no un hueco.
foreach($BONUS as $type => $name) {
	$tpl = file_get_contents(dirname(__DIR__).'/Templates/Build/'.$type.'.tpl');
	check(strpos($tpl,'$village->resarray[\'f\'.$id] >= 1 ? $bid'.$type.'[$village->resarray[\'f\'.$id]][\'attri\'] : 0') !== false,
		'la ficha de '.$name.' muestra 0% mientras está en construcción');
	check(strpos($tpl,'if(!$building->isMax($village->resarray[\'f\'.$id.\'t\'],$id))') !== false,
		'la ficha de '.$name.' no ofrece un nivel por encima del máximo');
}

$buildingEngine = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
check(strpos($buildingEngine,'if(!isset($dataarray[$target])) {') !== false,
	'el cálculo de costos rechaza un nivel que no existe en la tabla');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Bonus building checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
