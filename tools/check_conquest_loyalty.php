<?php
/**
 * Regresión de la lealtad en la conquista, contra el T4 oficial.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_conquest_loyalty.php
 *
 * Las reglas que cubre, todas oficiales:
 *
 *   - Cuánto baja **un** administrador depende de la tribu del atacante: senador
 *     romano 20-30, jefe germano y cacique galo 20-25. Acá había un rand(15,25) para
 *     las tres, que le quitaba al romano lo que lo hace bueno chifeando.
 *   - La **Gran celebración** mueve 5 puntos por administrador: a favor si la aldea que
 *     manda los jefes está de fiesta, en contra si la que festeja es la atacada, y con
 *     fiesta en las dos se anulan. Son puntos absolutos, no un porcentaje de la tirada.
 *   - La **cerveza** germana le baja a la mitad el poder de persuasión a los jefes, y es
 *     del atacante (la fábrica vale para toda la cuenta).
 *   - Un administrador nunca deja de persuadir del todo: el piso es 1 punto.
 */

error_reporting(E_ALL);

$GLOBALS['fails'] = array();
function loyaltyAssert($condition, $message) {
	if(!$condition) {
		$GLOBALS['fails'][] = $message;
		echo "  FAIL  ".$message."\n";
	}
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
require dirname(__DIR__).'/GameEngine/Data/cp.php';
require dirname(__DIR__).'/GameEngine/Data/cel.php';
require dirname(__DIR__).'/GameEngine/Hero.php';
function mysql_query($sql) { return true; }
function mysql_fetch_assoc($result) { return false; }
function mysql_error() { return ''; }
require dirname(__DIR__).'/GameEngine/Production.php';
require dirname(__DIR__).'/GameEngine/Automation.php';

// --- Rango por tribu --------------------------------------------------------------

loyaltyAssert(Automation::administratorLoyaltyRange(1) === array(20, 30), 'El senador romano dejó de bajar 20-30 de lealtad.');
loyaltyAssert(Automation::administratorLoyaltyRange(2) === array(20, 25), 'El jefe germano dejó de bajar 20-25 de lealtad.');
loyaltyAssert(Automation::administratorLoyaltyRange(3) === array(20, 25), 'El cacique galo dejó de bajar 20-25 de lealtad.');
loyaltyAssert(Automation::administratorLoyaltyRange(0) === array(20, 25), 'Una tribu desconocida se salió del rango común.');
loyaltyAssert(Automation::administratorLoyaltyRange(99) === array(20, 25), 'Una tribu fuera de tabla se salió del rango común.');

// --- Gran celebración -------------------------------------------------------------

loyaltyAssert(Automation::administratorLoyaltyCelebrationBonus(true, false) === 5, 'La gran fiesta del atacante dejó de sumar 5 por administrador.');
loyaltyAssert(Automation::administratorLoyaltyCelebrationBonus(false, true) === -5, 'La gran fiesta del defensor dejó de restar 5 por administrador.');
loyaltyAssert(Automation::administratorLoyaltyCelebrationBonus(true, true) === 0, 'Con gran fiesta en las dos aldeas los efectos dejaron de anularse.');
loyaltyAssert(Automation::administratorLoyaltyCelebrationBonus(false, false) === 0, 'Sin fiestas apareció un ajuste de lealtad.');

// El bono es absoluto: con tres senadores romanos y fiesta propia el peor caso pasa de
// 60 a 75 y el mejor de 90 a 105 (la lealtad se recorta después contra el 0).
$range = Automation::administratorLoyaltyRange(1);
$bonus = Automation::administratorLoyaltyCelebrationBonus(true, false);
loyaltyAssert(3 * ($range[0] + $bonus) === 75 && 3 * ($range[1] + $bonus) === 105,
	'Tres senadores con gran fiesta dejaron de moverse entre 75 y 105 puntos.');

// --- El código de la conquista usa las dos reglas ---------------------------------

$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
loyaltyAssert(strpos($automation, 'rand(15, 25)') === false,
	'Volvió el rand(15, 25) plano para todas las tribus.');
loyaltyAssert(strpos($automation, 'rand($loyaltyRange[0], $loyaltyRange[1]) + $celebrationBonus') !== false,
	'La conquista dejó de tirar dentro del rango de la tribu con el ajuste de la fiesta.');
loyaltyAssert(strpos($automation, 'max(1, rand($loyaltyRange[0]') !== false,
	'Un administrador puede quedar en 0 puntos de persuasión.');
loyaltyAssert(preg_match('/self::administratorLoyaltyCelebrationBonus\(\s*\$database->hasActiveGreatCelebration\(\$data\[.from.\]\),\s*\$database->hasActiveGreatCelebration\(\$data\[.to.\]\)/s', $automation) === 1,
	'El ajuste de la fiesta dejó de leer la aldea que ataca y la atacada, en ese orden.');
loyaltyAssert(preg_match('/if\(\$breweryActive\) \{\s*\$loyaltyDamage = max\(1, \(int\)floor\(\$loyaltyDamage \/ 2\)\);/', $automation) === 1,
	'La cerveza dejó de bajar a la mitad el poder de persuasión.');

// --- La consulta de la fiesta activa ----------------------------------------------

$db = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
loyaltyAssert(preg_match('/function hasActiveGreatCelebration\(.*?type = 2 AND celebration > \$time/s', $db) === 1,
	'hasActiveGreatCelebration() dejó de exigir que la fiesta sea grande y esté en curso.');

// --- Lo que impide conquistar sigue en pie ----------------------------------------
//
// Son las condiciones oficiales, y todas viven en la misma función: si alguna se cae,
// se puede chifear una capital, la última aldea de alguien, o una aldea con la
// residencia intacta.
foreach(array(
	"'capital'" => 'una capital',
	"'last_village'" => 'la última aldea de un jugador',
	"'residence'" => 'una aldea con residencia o palacio en pie',
	"'no_slot'" => 'sin cupo de expansión en la aldea que ataca',
	"'same_owner'" => 'una aldea propia'
) as $status => $what) {
	loyaltyAssert(strpos($db, $status) !== false, "getConquestEligibility() dejó de rechazar $what.");
}

if($GLOBALS['fails']) {
	fwrite(STDERR, "\nFallaron ".count($GLOBALS['fails'])." comprobaciones de lealtad.\n");
	exit(1);
}

echo "OK: lealtad de conquista verificada contra el modelo oficial\n";
