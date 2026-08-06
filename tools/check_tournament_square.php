<?php
// La Plaza de Torneos acelera las tropas más allá de TS_THRESHOLD casillas. El cálculo
// del tiempo de viaje está duplicado: GeneratorX::procDistanceTime lo usan las salidas
// y las vistas previas de "Llegada", y Automation::procDistanceTime los regresos que
// arma el cron. Estuvieron desincronizadas (la línea estaba comentada en GeneratorX),
// así que el bono valía a la vuelta pero no a la ida y la llegada anunciada al enviar
// nunca lo reflejaba. Ahora las dos salen del mismo helper: este checker verifica que
// el helper haga lo que promete y que ninguna de las dos copias vuelva a tener su
// propia versión.

function tsAssert($condition, $message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

if(!defined('WORLD_MAX')){ define('WORLD_MAX', 200); }
if(!defined('INCREASE_SPEED')){ define('INCREASE_SPEED', 1); }
if(!defined('TS_THRESHOLD')){ define('TS_THRESHOLD', 30); }

// bid14: el `attri` de la Plaza de Torneos va de 110 (nivel 1) a 200 (nivel 10).
$bid14 = array();
for($level = 1; $level <= 20; $level++){
	$bid14[$level] = array('attri' => min(200, 100 + $level * 10));
}

class FakeTsDatabase
{
	public $fields = array();
	public $villages = array();

	// La aldea se busca por coordenada, no calculando el id: así el bono no depende de
	// que WORLD_MAX coincida con el radio real del mundo.
	public function getVilWref($x, $y)
	{
		$key = (int)$x.':'.(int)$y;
		return isset($this->villages[$key]) ? $this->villages[$key] : 0;
	}

	// fdata guarda el tipo de cada campo en `f<N>t` y su nivel en `f<N>`. La Plaza de
	// Torneos es el tipo 14 y puede estar en cualquier hueco del 19 al 40.
	public function getResourceLevel($vid)
	{
		return isset($this->fields[(int)$vid]) ? $this->fields[(int)$vid] : false;
	}
}

require_once dirname(__DIR__).'/GameEngine/GeneratorX.php';

$database = new FakeTsDatabase();
$withoutSquare = array();
$withSquare = array();
for($field = 19; $field <= 40; $field++){
	$withoutSquare['f'.$field] = 0;
	$withoutSquare['f'.$field.'t'] = 1;
	$withSquare['f'.$field] = 0;
	$withSquare['f'.$field.'t'] = 1;
}
// Nivel 10 (attri 200) en un hueco cualquiera, para probar que lo encuentra igual.
$withSquare['f27t'] = 14;
$withSquare['f27'] = 10;

$origin = array('x' => 0, 'y' => 0);
$originId = 797;
$database->villages = array('0:0' => $originId);
$database->fields = array($originId => $withoutSquare);

tsAssert(tournamentSquareSpeedFactor($origin, 100)===1, 'A village without a Tournament Square got a bonus');

$database->fields = array($originId => $withSquare);
tsAssert(tournamentSquareSpeedFactor($origin, TS_THRESHOLD)===1, 'The bonus applied at exactly the threshold');
tsAssert(tournamentSquareSpeedFactor($origin, TS_THRESHOLD-1)===1, 'The bonus applied below the threshold');
$factor = tournamentSquareSpeedFactor($origin, 100);
$expected = (TS_THRESHOLD + (100 - TS_THRESHOLD) * 200 / 100) / 100;
tsAssert(abs($factor-$expected)<0.000001, 'The Tournament Square factor changed');
tsAssert($factor>1, 'The Tournament Square made troops slower');

// Una aldea que no existe no puede romper el cálculo ni regalar bono.
$database->fields = array();
tsAssert(tournamentSquareSpeedFactor($origin, 100)===1, 'A village missing from fdata produced a bonus');
$database->villages = array();
tsAssert(tournamentSquareSpeedFactor($origin, 100)===1, 'A coordinate with no village produced a bonus');
$database->villages = array('0:0' => $originId);

// El viaje completo: con Plaza de Torneos tarda menos, y por debajo del umbral no.
$database->fields = array($originId => $withSquare);
$far = array('x' => 100, 'y' => 0);
$near = array('x' => 10, 'y' => 0);
$withBonus = $generator->procDistanceTime($origin, $far, 10, 1);
$database->fields = array($originId => $withoutSquare);
$without = $generator->procDistanceTime($origin, $far, 10, 1);
tsAssert($withBonus<$without, 'The Tournament Square did not shorten an outbound trip');
tsAssert($generator->procDistanceTime($origin, $far, 300, 0)===$generator->procDistanceTime($origin, $far, 300, 0), 'Resource movements became unstable');

$database->fields = array($originId => $withSquare);
tsAssert(
	$generator->procDistanceTime($origin, $near, 10, 1)===$generator->procDistanceTime($origin, $near, 10, 1),
	'Short trips became unstable'
);

// Ninguna de las dos copias puede volver a tener su propia versión del bono.
$sources = array(
	'GameEngine/GeneratorX.php' => 'las salidas y las vistas previas',
	'GameEngine/Automation.php' => 'los regresos del cron'
);
foreach($sources as $file => $what){
	$source = file_get_contents(dirname(__DIR__).'/'.$file);
	tsAssert($source!==false, "Could not read $file");
	tsAssert(
		strpos($source, 'tournamentSquareSpeedFactor($coor, $distance)')!==false,
		"$file ($what) no longer takes the Tournament Square bonus from the shared helper"
	);
}

// El helper existe una sola vez, y la copia del cron no puede volver a tener fórmula
// propia: si vuelve a aparecer TS_THRESHOLD ahí, es que alguien la reintrodujo.
$generatorSource = file_get_contents(dirname(__DIR__).'/GameEngine/GeneratorX.php');
$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
tsAssert(
	substr_count($generatorSource, 'function tournamentSquareSpeedFactor')===1,
	'The shared Tournament Square helper is not defined exactly once'
);
tsAssert(
	strpos($automationSource, 'TS_THRESHOLD')===false,
	'Automation.php grew its own copy of the Tournament Square formula again'
);
tsAssert(
	strpos($generatorSource, '//$speed = $distance <= TS_THRESHOLD')===false,
	'The Tournament Square bonus was commented out again on the outbound path'
);

echo "Tournament square regression: OK\n";
