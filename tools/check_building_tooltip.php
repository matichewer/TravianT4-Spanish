<?php
/**
 * Auditoría del cartelito de nivel de los mapas de aldea (dorf1/dorf2): el número
 * del círculo, su color y el tooltip con el costo.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_building_tooltip.php
 *
 * Cubre:
 *   A. Con una mejora ya encolada, el tooltip cotiza el nivel siguiente al que está
 *      en obra (antes repetía el nivel y el costo que el jugador ya había pagado).
 *   B. El color del círculo mira ese mismo nivel: verde sólo si alcanza para pedir
 *      la próxima mejora, no la que ya está en construcción.
 *   C. Si lo encolado llega al tope del edificio, tooltip y color dicen "nivel máximo"
 *      en vez de ofrecer un nivel inexistente sin costos.
 *   D. Sin nada en la cola nada cambia: nivel actual + 1.
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

if(!defined('SPEED')) { define('SPEED',1); }
if(!defined('ALLOW_ALL_TRIBE')) { define('ALLOW_ALL_TRIBE',false); }

require_once dirname(__DIR__).'/GameEngine/Data/buidata.php';
require_once dirname(__DIR__).'/GameEngine/Building.php';

// Aldea de prueba: capital, con recursos y depósitos de sobra salvo cuando el caso
// necesite lo contrario.
class FakeVillageForTooltip {
	public $capital = 1;
	public $wid = 1;
	public $maxstore = 800000;
	public $maxcrop = 800000;
	public $awood = 500000, $aclay = 500000, $airon = 500000, $acrop = 500000;
	public $resarray = array();
}

class FakeSessionForTooltip {
	public $tribe = 1;
	public $plus = 0;
}

$GLOBALS['session'] = new FakeSessionForTooltip();
$village = new FakeVillageForTooltip();
$GLOBALS['village'] = $village;

// La mansión del héroe (tipo 37) del reporte original: nivel 5 construido, nivel 6
// en obra. Campo 21 del centro de la aldea.
$village->resarray['f21t'] = 37;
$village->resarray['f21'] = 5;
$village->resarray['f15t'] = 15; // Edificio principal, para el cálculo de tiempos.
$village->resarray['f15'] = 10;

$reflection = new ReflectionClass('Building');
$building = $reflection->newInstanceWithoutConstructor();

// ---------------------------------------------------------------------------
section('A. Tooltip con una mejora encolada');
// ---------------------------------------------------------------------------
$building->buildArray = array(
	array('field'=>21,'type'=>37,'level'=>6,'master'=>0),
);
$tooltip = $building->upgradeTooltip(21,37);
check(strpos($tooltip,'Costo para nivel 7') !== false,
	'con el nivel 6 en obra el tooltip cotiza el nivel 7, no el que ya está pago');
check(strpos($tooltip,'Costo para nivel 6') === false,
	'el tooltip ya no repite el nivel en construcción');
check(strpos($tooltip,'3.875') !== false && strpos($tooltip,'3.710') !== false,
	'los costos son los del nivel 7 (3.875 madera / 3.710 barro), no los del 6');
check(strpos($tooltip,'2.915') === false,
	'no quedan a la vista los costos del nivel 6, ya cobrados al encolar');

// Dos niveles encolados (constructor maestro): hay que saltear los dos.
$building->buildArray = array(
	array('field'=>21,'type'=>37,'level'=>6,'master'=>0),
	array('field'=>21,'type'=>37,'level'=>7,'master'=>1),
);
$tooltip = $building->upgradeTooltip(21,37);
check(strpos($tooltip,'Costo para nivel 8') !== false,
	'con dos niveles encolados el tooltip cotiza el nivel 8');
check(strpos($tooltip,'5.155') !== false,
	'los costos son los del nivel 8 (5.155 madera)');

// ---------------------------------------------------------------------------
section('B. Color del círculo con una mejora encolada');
// ---------------------------------------------------------------------------
$building->buildArray = array(
	array('field'=>21,'type'=>37,'level'=>6,'master'=>0),
);
$village->awood = $village->aclay = $village->airon = $village->acrop = 500000;
check($building->badgeUpgradeState(21,37) === 'canUpgrade',
	'con recursos de sobra para el nivel 7 el círculo queda verde');

// Alcanza para el nivel 6 (ya pago) pero no para el 7: no puede ofrecerse verde.
$village->awood = 3000; $village->aclay = 3000; $village->airon = 3000; $village->acrop = 3000;
check($building->badgeUpgradeState(21,37) === 'cannotUpgrade',
	'si sólo alcanza para el nivel ya encolado, el círculo no se pone verde');

$village->awood = $village->aclay = $village->airon = $village->acrop = 500000;

// ---------------------------------------------------------------------------
section('C. Tope del edificio alcanzado por la cola');
// ---------------------------------------------------------------------------
$village->resarray['f21'] = 19;
$building->buildArray = array(
	array('field'=>21,'type'=>37,'level'=>20,'master'=>0),
);
$tooltip = $building->upgradeTooltip(21,37);
check(strpos($tooltip,'Nivel máximo') !== false,
	'con el nivel 20 (tope) en obra el tooltip dice nivel máximo');
check(strpos($tooltip,'Costo para nivel 21') === false,
	'no se anuncia un nivel 21 inexistente');
check($building->badgeUpgradeState(21,37) === 'maxLevel',
	'el círculo usa el color de nivel máximo en vez del de "no se puede mejorar"');

// ---------------------------------------------------------------------------
section('D. Sin nada encolado el comportamiento no cambia');
// ---------------------------------------------------------------------------
$village->resarray['f21'] = 5;
$building->buildArray = array();
$tooltip = $building->upgradeTooltip(21,37);
check(strpos($tooltip,'Costo para nivel 6') !== false,
	'sin cola, el tooltip sigue cotizando el nivel actual + 1');
check(strpos($tooltip,'2.915') !== false,
	'y con los costos del nivel 6');
check($building->badgeUpgradeState(21,37) === 'canUpgrade',
	'sin cola y con recursos, el círculo sigue verde');

// Una obra en otro campo no debe correr el nivel de éste.
$building->buildArray = array(
	array('field'=>22,'type'=>10,'level'=>4,'master'=>0),
);
check(strpos($building->upgradeTooltip(21,37),'Costo para nivel 6') !== false,
	'una obra en otro campo no altera el tooltip de éste');

// Campo de recursos de una aldea secundaria: el tope es 10, no 20.
$village->capital = 0;
$village->resarray['f1t'] = 1;
$village->resarray['f1'] = 9;
$building->buildArray = array(
	array('field'=>1,'type'=>1,'level'=>10,'master'=>0),
);
check(strpos($building->upgradeTooltip(1,1),'Nivel máximo') !== false,
	'un leñador de aldea secundaria con el nivel 10 en obra ya está al tope');
$village->capital = 1;
check(strpos($building->upgradeTooltip(1,1),'Costo para nivel 11') !== false,
	'en la capital ese mismo leñador todavía puede cotizar el nivel 11');

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
	echo "Building tooltip checks passed (".$GLOBALS['checks']." comprobaciones).\n";
	exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
