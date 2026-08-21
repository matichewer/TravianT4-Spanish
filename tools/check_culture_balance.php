<?php
/**
 * Regresión completa de los puntos de cultura, contra el T4 oficial.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_culture_balance.php
 *
 * El modelo oficial, que es el que este mundo implementa:
 *
 *   - La producción pasiva de un edificio es `buidata[nivel]['cp']`, y ese número **ya
 *     es el total por día a ese nivel**, no un incremento. Embajada 20 = 153, academia
 *     20 = 153, residencia 20 = 77, campo de recursos 10 = 6.
 *   - La pasiva **no** escala con la velocidad del mundo. Lo que escala es el requisito
 *     de cada aldea, hacia abajo: $cp1 es la columna oficial x1 y $cp0 la x3.
 *   - Los importes fijos (fiestas, cascos de cultura, tope de la obra de arte) valen la
 *     mitad en un mundo de velocidad, y la fiesta además dura la mitad.
 *
 * El bug que cubre: `Automation::buildingCP()` sumaba los niveles 0..N como si `cp`
 * fuera un incremento igual que `pop`. Inflaba la producción entre 1,5x con edificios
 * de nivel 3 y 4,4x con todo a 20 —creciendo con el nivel, así que el mundo arrancaba
 * equilibrado y se descontrolaba solo—, y `getPop()` devolvía el total del nivel nuevo
 * hacia `addCP()`, con lo que la ruta incremental y el recuento coincidían en el error
 * y ninguno delataba al otro. Un factor 0.25 en Hero.php lo tapaba a medias.
 */

error_reporting(E_ALL);

$errors = array();
function cultureBalanceAssert($condition, $message){
	global $errors;
	if(!$condition){
		$errors[] = $message;
	}
}

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
define('ALLOW_BURST', false);
define('STORAGE_BASE', 800);
define('STORAGE_MULTIPLIER', 1);
define('TRAPPER_CAPACITY', 1);
define('CRANNY_CAPACITY', 1);
// Sin SPEED definido, cultureWorldSpeed() cae a x1: las funciones que dependen de la
// velocidad se prueban pasándola por parámetro, para poder cubrir los dos mundos.

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/Data/cp.php';
require dirname(__DIR__).'/GameEngine/Data/cel.php';
require dirname(__DIR__).'/GameEngine/Hero.php';

function mysql_query($sql) { $GLOBALS['writes'][] = $sql; return true; }
function mysql_fetch_assoc($result) { return false; }
function mysql_error() { return ''; }
require dirname(__DIR__).'/GameEngine/Production.php';
require dirname(__DIR__).'/GameEngine/Automation.php';

// --- A. Las dos tablas de requisitos son las oficiales ----------------------------
// Verificadas dígito por dígito contra la tabla de Travian: Legends.

cultureBalanceAssert(
	travianCultureRequiredForVillageCount(2, 1) === 2000
		&& travianCultureRequiredForVillageCount(3, 1) === 8000
		&& travianCultureRequiredForVillageCount(4, 1) === 20000
		&& travianCultureRequiredForVillageCount(10, 1) === 251000
		&& travianCultureRequiredForVillageCount(20, 1) === 1397000
		&& travianCultureRequiredForVillageCount(50, 1) === 12347000,
	'El modo 1 dejó de ser la columna oficial x1.'
);
cultureBalanceAssert(
	travianCultureRequiredForVillageCount(2, 0) === 500
		&& travianCultureRequiredForVillageCount(3, 0) === 2600
		&& travianCultureRequiredForVillageCount(4, 0) === 6700
		&& travianCultureRequiredForVillageCount(10, 0) === 83500
		&& travianCultureRequiredForVillageCount(20, 0) === 465700
		&& travianCultureRequiredForVillageCount(50, 0) === 4115800,
	'El modo 0 dejó de ser la columna oficial x3.'
);

// --- B. El modo sale de la velocidad del mundo ------------------------------------

$installerConfigTemplate = file_get_contents(dirname(__DIR__).'/install/data/constant_format_mysqli.tpl');
$worldConfig = file_get_contents(dirname(__DIR__).'/config/config.php');
foreach(array('el instalador' => $installerConfigTemplate, 'config/config.php' => $worldConfig) as $where => $source){
	cultureBalanceAssert(
		strpos($source, 'define("CP", SPEED >= 3 ? 0 : 1);') !== false,
		"En $where la tabla de cultura dejó de derivarse de SPEED."
	);
}
cultureBalanceAssert(
	strpos($installerConfigTemplate, '%VILLAGE_EXPAND%') === false
		&& strpos(file_get_contents(dirname(__DIR__).'/install/process.php'), 'VILLAGE_EXPAND') === false,
	'El instalador volvió a aceptar un modo de expansión por formulario.'
);

// --- C. buidata guarda el total por nivel, no un incremento -----------------------

cultureBalanceAssert(
	$bid18[20]['cp'] === 153 && $bid22[20]['cp'] === 153 && $bid25[20]['cp'] === 77 && $bid1[10]['cp'] === 6,
	'Los PC por nivel de buidata dejaron de coincidir con los oficiales (embajada 153, academia 153, residencia 77, campo 6).'
);

class CultureAutomationStub {
	public $fields = array();
	public function getResourceLevel($vid) { return $this->fields; }
	public function getVillageField($vid, $field) { return $field === 'owner' ? 7 : 0; }
	public function syncClimberPopulation($uid) { return true; }
	public function addCP($vid, $cp) { return true; }
	public function query($q) { $GLOBALS['writes'][] = $q; return true; }
}
$database = new CultureAutomationStub();
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();

cultureBalanceAssert(
	$automation->buildingCP(18, 20) === 153 && $automation->buildingCP(1, 10) === 6,
	'buildingCP() volvió a acumular los niveles 0..N en vez de leer el total del nivel.'
);
cultureBalanceAssert(
	$automation->buildingCP(18, 0) === 0 && $automation->buildingCP(18, 99) === 0,
	'buildingCP() no tolera un nivel fuera de la tabla.'
);

// getPop() alimenta el addCP incremental del fin de obra: tiene que dar el delta.
$getPop = (new ReflectionClass('Automation'))->getMethod('getPop');
$getPop->setAccessible(true);
$step = $getPop->invoke($automation, 18, 19);
cultureBalanceAssert(
	$step[1] === $bid18[20]['cp'] - $bid18[19]['cp'],
	'getPop() volvió a devolver el total del nivel nuevo en vez del incremento de PC.'
);
$firstLevel = $getPop->invoke($automation, 18, 0);
cultureBalanceAssert(
	$firstLevel[1] === $bid18[1]['cp'],
	'El primer nivel de un edificio no aporta sus PC completos.'
);

// La invariante que ata las dos rutas: sumar los deltas nivel a nivel tiene que dar
// exactamente lo que devuelve el recuento. Es lo que hacía que el bug fuera invisible.
$accumulated = 0;
for($level = 0; $level < 20; $level++){
	$pair = $getPop->invoke($automation, 18, $level);
	$accumulated += $pair[1];
}
cultureBalanceAssert(
	$accumulated === $automation->buildingCP(18, 20),
	'La ruta incremental (addCP) y el recuento (buildingCP) dejaron de coincidir.'
);

// --- D. La producción pasiva no lleva factor ni velocidad -------------------------

class CultureProductionDatabaseStub {
	public $rawCulture = 5575;
	public function getVSumField($uid,$field){ return $this->rawCulture; }
	public function getHeroData($uid){ return array('dead'=>1); }
}
$productionDatabase = new CultureProductionDatabaseStub();
cultureBalanceAssert(!function_exists('villageCultureProductionFactor'), 'Volvió el factor de producción pasiva.');
cultureBalanceAssert(villageCulturePointsPerDay(2365,1) === 2365, 'La producción por aldea dejó de ser el valor crudo de vdata.cp.');
cultureBalanceAssert(
	accountVillageCulturePointsPerDay($productionDatabase,5,1) === 5575
		&& accountCulturePointsPerDay($productionDatabase,5,3) === 5575
		&& accountCulturePointsPerDay($productionDatabase,5,10) === 5575,
	'La producción pasiva volvió a escalar con la velocidad del mundo.'
);

// --- E. Los importes fijos valen la mitad en un mundo de velocidad ----------------

cultureBalanceAssert(
	cultureFixedAmountDivisor(1) === 1 && cultureFixedAmountDivisor(2) === 1
		&& cultureFixedAmountDivisor(3) === 2 && cultureFixedAmountDivisor(10) === 2,
	'El divisor de los importes fijos dejó de ser 1 en x1 y 2 en un mundo de velocidad.'
);
cultureBalanceAssert(
	celebrationCulturePoints(1) === 500 && celebrationCulturePoints(2) === 2000,
	'Las fiestas dejaron de pagar 500 y 2000 PC en un mundo x1.'
);
cultureBalanceAssert(celebrationCulturePoints(3) === 0, 'Un tipo de fiesta inexistente pagó PC.');
$celebrationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$celebrationDataSource = file_get_contents(dirname(__DIR__).'/GameEngine/Data/cel.php');
cultureBalanceAssert(
	strpos($celebrationSource, '$rewards = array(1 => celebrationCulturePoints(1), 2 => celebrationCulturePoints(2));') !== false,
	'celebrationComplete() dejó de leer los PC de celebrationCulturePoints().'
);
cultureBalanceAssert(
	strpos($celebrationDataSource, '$table[$level] / cultureFixedAmountDivisor()') !== false
		&& strpos($celebrationDataSource, '$table[$level] / SPEED') === false,
	'La duración de las fiestas volvió a dividirse por SPEED en vez de por la mitad de un mundo de velocidad.'
);
cultureBalanceAssert(
	strpos(file_get_contents(dirname(__DIR__).'/Templates/Build/24_celebrations.tpl'), 'celebrationCulturePoints(1)') !== false,
	'El Ayuntamiento volvió a anunciar los PC de la fiesta por su cuenta.'
);

cultureBalanceAssert(
	getHeroHelmetBonuses(7)['culture'] === 100
		&& getHeroHelmetBonuses(8)['culture'] === 400
		&& getHeroHelmetBonuses(9)['culture'] === 800,
	'Los cascos de cultura dejaron de aportar los 100/400/800 PC oficiales de un mundo x1.'
);

cultureBalanceAssert(
	artworkCulturePointsCap(1) === 2000 && artworkCulturePointsCap(3) === 1000,
	'El tope de la obra de arte dejó de ser 2000 en x1 y 1000 en un mundo de velocidad.'
);
cultureBalanceAssert(
	artworkCulturePoints($productionDatabase,5,1) === 2000 && artworkCulturePoints($productionDatabase,5,3) === 1000,
	'La obra de arte no respeta el tope con una producción diaria de 5575 PC.'
);
$productionDatabase->rawCulture = 700;
cultureBalanceAssert(
	artworkCulturePoints($productionDatabase,5,1) === 700,
	'Por debajo del tope la obra dejó de conceder la producción diaria entera.'
);
$productionDatabase->rawCulture = 5575;
cultureBalanceAssert(artworkCooldownSeconds() === 86400, 'El cooldown de obra de arte dejó de ser 24 horas.');
cultureBalanceAssert(artworkCooldownRemaining(100000,186400) === 0, 'La obra no se habilitó exactamente a las 24 horas.');

// --- F. El traslado entre tablas conserva el avance -------------------------------
// Es lo que hace seguro cambiar de columna en un mundo en juego: nadie gana ni pierde
// un cupo de aldea, y la barra de progreso queda donde estaba.

foreach(array(0, 3, 1999, 2000, 22164, 24978, 33368, 251000, 12347000) as $points){
	$rescaled = travianCultureRescale($points, 1, 0);
	$before = travianCultureStatus($points, 0, 1);
	$after = travianCultureStatus($rescaled['newPoints'], 0, 0);
	cultureBalanceAssert(
		$before['cultureCapacity'] === $after['cultureCapacity'],
		"Trasladar $points PC cambió el cupo de aldeas (".$before['cultureCapacity'].' -> '.$after['cultureCapacity'].').'
	);
	cultureBalanceAssert(
		abs($before['progressPercent'] - $after['progressPercent']) < 0.5,
		"Trasladar $points PC movió el avance hacia la aldea siguiente."
	);
}
cultureBalanceAssert(travianCultureRescale(33368, 1, 0)['newPoints'] === 11062, 'El traslado dejó de conservar la posición en la curva.');
cultureBalanceAssert(travianCultureRescale(0, 1, 0)['newPoints'] === 0, 'Una cuenta sin PC no puede salir del traslado con PC.');

$rescaleTool = file_get_contents(dirname(__DIR__).'/tools/rescale_culture_points.php');
cultureBalanceAssert(strpos($rescaleTool, "in_array('--aplicar', \$argv, true)") !== false, 'La herramienta de traslado debe requerir --aplicar explícito.');
cultureBalanceAssert(strpos($rescaleTool, 'mysqli_begin_transaction') !== false, 'El traslado debe correr dentro de una transacción.');
cultureBalanceAssert(strpos($rescaleTool, 'admin_log') !== false, 'El traslado debe anotarse para no poder repetirse.');
cultureBalanceAssert(strpos($rescaleTool, "playerAccountSql") !== false, 'El traslado debe excluir las cuentas del sistema con el helper compartido.');

$recountTool = file_get_contents(dirname(__DIR__).'/tools/fix_village_cp.php');
cultureBalanceAssert(strpos($recountTool, "in_array('--aplicar', \$argv, true)") !== false, 'El recuento de vdata.cp debe requerir --aplicar explícito.');
cultureBalanceAssert(strpos($recountTool, 'villagePopulationSlots()') !== false, 'El recuento de vdata.cp debe usar la lista compartida de campos (la Maravilla vive en f99).');

// --- G. El crédito diario paga días enteros y acota la puesta al día --------------

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
cultureBalanceAssert(
	strpos($automationSource, '$newupdate = $lastUpdate + $elapsedDays * 86400;') !== false,
	'El crédito diario volvió a poner lastupdate en "ahora" y a perder las horas sobrantes.'
);
cultureBalanceAssert(
	preg_match('/\$creditedDays = min\(\$catchupCap, \$elapsedDays\);/', $automationSource) === 1,
	'El crédito diario dejó de acotar la puesta al día: una cuenta con lastupdate en 0 reclamaría décadas de cultura.'
);
cultureBalanceAssert(
	strpos(file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php'), "fquest,cp,lastupdate) VALUES") !== false,
	'El registro dejó de anclar users.lastupdate, así que la primera acreditación de una cuenta nueva es imprevisible.'
);

// --- H. La normalización vieja sigue siendo idempotente ---------------------------

$surplus = travianCultureNormalization(50000, 3, 1);
cultureBalanceAssert(
	$surplus['changed'] === true && $surplus['cap'] === 20000 && $surplus['newPoints'] === 20000,
	'Una cuenta con excedente no conserva exactamente el umbral de una aldea adicional.'
);
cultureBalanceAssert(
	travianCultureNormalization(7500, 2, 1)['changed'] === false,
	'La normalización no debe regalar ni quitar PC a una cuenta por debajo del tope.'
);
cultureBalanceAssert(
	travianCultureNormalization($surplus['newPoints'], 3, 1)['changed'] === false,
	'La normalización debe ser idempotente.'
);
cultureBalanceAssert(
	travianCultureNormalization(PHP_INT_MAX, 125, 1)['cap'] === null,
	'Una cuenta fuera de la tabla debe informarse y quedar sin cambios.'
);

if($errors){
	fwrite(STDERR, "Fallaron ".count($errors)." comprobaciones de cultura:\n - ".implode("\n - ", $errors)."\n");
	exit(1);
}

echo "OK: modelo oficial de puntos de cultura verificado\n";
