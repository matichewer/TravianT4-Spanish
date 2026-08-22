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
// El único desvío deliberado del oficial: la columna x1 de umbrales sobre una economía
// x3, o sea expansión 3 veces más lenta. La producción pasiva sigue siendo la oficial
// sin tocar, que es lo que el resto de este archivo verifica.
foreach(array('el instalador' => $installerConfigTemplate, 'config/config.php' => $worldConfig) as $where => $source){
	cultureBalanceAssert(
		strpos($source, 'define("CP", 1);') !== false,
		"En $where la tabla de cultura dejó de ser la columna x1."
	);
}
// Cambiar la tabla en un mundo en juego sin trasladar los saldos regala (o quita) tres
// aldeas de golpe a cada cuenta. Que la herramienta exista y sepa ir en los dos
// sentidos es parte del contrato de este knob.
cultureBalanceAssert(
	travianCultureRescale(travianCultureRescale(33368, 1, 0)['newPoints'], 0, 1)['newPoints'] === 33367,
	'El traslado de saldos dejó de poder deshacerse: cambiar de tabla sería irreversible.'
);
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
	$automation->buildingCP(18, 0) === 0 && $automation->buildingCP(18, 99) === 0
		&& buildingCulturePointsAtLevel(999, 5) === 0,
	'El cálculo de PC por edificio no tolera un nivel o un tipo fuera de la tabla.'
);
// Los cuatro caminos que escriben cultura tienen que salir de la misma definición: el
// recuento, el fin de obra, el "terminar ahora" con oro y la herramienta de recuento.
$culturePointSources = array(
	'GameEngine/Automation.php' => 'buildingCulturePointsAtLevel(',
	'tools/fix_village_cp.php' => 'buildingCulturePointsAtLevel('
);
foreach($culturePointSources as $sourceFile => $needle){
	cultureBalanceAssert(
		strpos(file_get_contents(dirname(__DIR__).'/'.$sourceFile), $needle) !== false,
		$sourceFile.' dejó de usar la definición compartida de PC por edificio.'
	);
}
// El "Completar" con oro del Plus no calcula cultura: pasa por el fin de obra del
// motor, que ya suma el incremento correcto. Que no vuelva a tener la suya está
// cubierto en detalle por tools/check_gold_finish.php.
cultureBalanceAssert(
	strpos(file_get_contents(dirname(__DIR__).'/Templates/Plus/3.tpl'), 'addCP(') === false,
	'El "Completar" con oro del Plus volvió a escribir puntos de cultura por su cuenta.'
);
cultureBalanceAssert(
	strpos(file_get_contents(dirname(__DIR__).'/tools/normalize_culture_points.php'), '$slowCultureMode = CP;') !== false,
	'La normalización volvió a clavar la tabla de cultura en vez de leer la del mundo.'
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
	celebrationCulturePointsCap(1) === 500 && celebrationCulturePointsCap(2) === 2000,
	'El tope de las fiestas dejó de ser 500 y 2000 PC en un mundo x1.'
);
// La fiesta paga producción recortada por el tope, no una cifra fija: la pequeña la de
// su aldea, la grande la de la cuenta. Una aldea que produce 30 PC/día saca 30.
cultureBalanceAssert(
	celebrationCulturePoints(1, 30) === 30 && celebrationCulturePoints(2, 30) === 30,
	'Una fiesta en una aldea floja volvió a pagar el tope en vez de la producción.'
);
cultureBalanceAssert(
	celebrationCulturePoints(1, 9000) === 500 && celebrationCulturePoints(2, 9000) === 2000,
	'Una fiesta con producción de sobra no se recortó al tope.'
);
cultureBalanceAssert(
	celebrationCulturePoints(1, -50) === 0 && celebrationCulturePoints(3, 9000) === 0,
	'Una producción negativa o un tipo de fiesta inexistente pagó PC.'
);
$celebrationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$celebrationDataSource = file_get_contents(dirname(__DIR__).'/GameEngine/Data/cel.php');
cultureBalanceAssert(
	strpos($celebrationSource, '$database->setCelCp($user, celebrationCulturePoints($type, $production));') !== false
		&& strpos($celebrationSource, "? (int)\$vil['cp']") !== false
		&& strpos($celebrationSource, ': accountCulturePointsPerDay($database, $user);') !== false,
	'celebrationComplete() dejó de pagar la producción (de la aldea la pequeña, de la cuenta la grande) a través de celebrationCulturePoints().'
);
cultureBalanceAssert(
	strpos($celebrationDataSource, '$table[$level] / cultureFixedAmountDivisor()') !== false
		&& strpos($celebrationDataSource, '$table[$level] / SPEED') === false,
	'La duración de las fiestas volvió a dividirse por SPEED en vez de por la mitad de un mundo de velocidad.'
);
$townHallTemplate = file_get_contents(dirname(__DIR__).'/Templates/Build/24_celebrations.tpl');
cultureBalanceAssert(
	strpos($townHallTemplate, 'celebrationCulturePoints(1, $celebrationVillageProduction)') !== false
		&& strpos($townHallTemplate, 'celebrationCulturePoints(2, $celebrationAccountProduction)') !== false,
	'El Ayuntamiento volvió a anunciar un número distinto del que después acredita.'
);

cultureBalanceAssert(
	getHeroHelmetBonuses(7)['culture'] === 100
		&& getHeroHelmetBonuses(8)['culture'] === 400
		&& getHeroHelmetBonuses(9)['culture'] === 800,
	'Los cascos de cultura dejaron de aportar los 100/400/800 PC oficiales de un mundo x1.'
);
cultureBalanceAssert(
	heroHelmetCulturePointsForType(7,1) === 100 && heroHelmetCulturePointsForType(7,3) === 50
		&& heroHelmetCulturePointsForType(8,3) === 200 && heroHelmetCulturePointsForType(9,3) === 400,
	'El casco de cultura dejó de valer la mitad en un mundo de velocidad.'
);

// El tooltip del objeto tiene que anunciar lo que después acredita el motor. Anunciaba
// una base inventada (25/100/200) multiplicada por la velocidad del mundo: prometía 75
// PC/día con el Gladiador donde la cuenta recibe 50, y encima ataba a la velocidad una
// producción que en el oficial no escala con ella.
foreach(array(7,8,9) as $helmetType){
	$btype = 1;
	$type = $helmetType;
	$name = '';
	$title = '';
	$item = '';
	$effect = '';
	include dirname(__DIR__).'/Templates/Auction/alt.tpl';
	cultureBalanceAssert(
		$title === '+'.number_format(heroHelmetCulturePointsForType($helmetType),0,',','.').' puntos de cultura/día',
		'El tooltip del casco de cultura '.$helmetType.' no anuncia los PC/día que acredita el motor.'
	);
	cultureBalanceAssert(
		strpos($title,'velocidad') === false,
		'El tooltip del casco de cultura '.$helmetType.' volvió a atar la pasiva a la velocidad del mundo.'
	);
}

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

// --- E bis. Cuándo cae el pago diario ---------------------------------------------
// La pasiva no gotea: se paga entera una vez cada 24 h, a la hora propia de cada
// cuenta. Que el jugador pueda verlo es la única forma de que el "+X PC/día" no
// parezca que aparece porque sí.

$creditNow = mktime(14, 30, 0, 8, 22, 2026);
cultureBalanceAssert(
	cultureNextCreditAt($creditNow - 3600 * 7, $creditNow) === $creditNow + 3600 * 17,
	'El próximo pago dejó de caer 24 horas después del último.'
);
cultureBalanceAssert(
	cultureNextCreditAt(0, $creditNow) === $creditNow + 86400,
	'Una cuenta sin reloj (lastupdate 0) no anuncia su pago a 24 horas vista.'
);
cultureBalanceAssert(
	cultureNextCreditAt($creditNow - 86400 * 3, $creditNow) === $creditNow
		&& cultureNextCreditIn($creditNow - 86400 * 3, $creditNow) === 0,
	'Un pago ya vencido debería caer en la próxima carga de página, no en el futuro.'
);
cultureBalanceAssert(
	cultureNextCreditLabel($creditNow - 3600 * 20, $creditNow) === 'hoy a las 18:30'
		&& cultureNextCreditLabel($creditNow - 3600 * 7, $creditNow) === 'mañana a las 07:30'
		&& cultureNextCreditLabel($creditNow - 86400 * 3, $creditNow) === 'en cuanto recargues',
	'La etiqueta del próximo pago dejó de decir hoy/mañana con la hora exacta.'
);
cultureBalanceAssert(
	cultureNextCreditCountdown(3600 * 7 + 720) === '7 h 12 min'
		&& cultureNextCreditCountdown(2880) === '48 min'
		&& cultureNextCreditCountdown(0) === 'ya',
	'La cuenta regresiva del próximo pago cambió de formato.'
);
// Va sólo en la ficha de la Residencia y del Palacio. El cartel de la barra lateral
// se dejó como estaba a propósito: es angosto, frágil (ver AGENTS.md) y lo que ahí
// importa es el ritmo, no el reloj.
foreach(array('Templates/Build/25_2.tpl', 'Templates/Build/26_2.tpl') as $creditView){
	cultureBalanceAssert(
		strpos(file_get_contents(dirname(__DIR__).'/'.$creditView), 'cultureNextCreditLabel(') !== false,
		$creditView.' dejó de mostrar cuándo cae el pago diario de cultura.'
	);
}
cultureBalanceAssert(
	strpos(file_get_contents(dirname(__DIR__).'/Templates/culture_progress.tpl'), 'cultureNextCredit') === false,
	'El cartel de la barra lateral volvió a mostrar el próximo pago.'
);

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
