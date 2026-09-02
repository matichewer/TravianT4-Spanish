<?php
/**
 * Auditoría de la unicidad de edificios por aldea.
 *
 * Ejecutar:  docker compose exec -T web php tools/check_unique_buildings.php
 *
 * La lista de construcciones (`avaliable.tpl`) sólo ofrece cada edificio cuando la
 * aldea no lo tiene ni lo tiene encolado, pero eso es sólo la vista: quien pedía
 * `dorf2.php?a=<gid>&id=<solar libre>&c=<token>` a mano levantaba un segundo Mercado,
 * una segunda Academia o una segunda Oficina de comercio. Ninguno suma nada
 * (getTypeLevel() se queda con el nivel más alto): sólo gastan recursos, población y
 * un solar que no se recupera.
 *
 * Cubre:
 *   A. La lista de únicos coincide con lo que ofrece avaliable.tpl.
 *   B. Los que sí se repiten quedan fuera de la lista y conservan su propia regla.
 *   C. meetRequirement() aplica la unicidad antes de mirar los requisitos del edificio.
 *   D. Los requisitos propios de cada edificio siguen intactos.
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

$buildingSource = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
$availableSource = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');

// Edificio => variable que usa avaliable.tpl para comprobar que todavía no existe.
$unicos = array(
	5 => 'sawmill', 6 => 'brickyard', 7 => 'ironfoundry', 8 => 'grainmill', 9 => 'bakery',
	12 => 'blacksmith', 14 => 'tournamentsquare', 15 => 'mainbuilding', 17 => 'market',
	18 => 'embassy', 19 => 'barrack', 20 => 'stable', 21 => 'workshop', 22 => 'academy',
	24 => 'townhall', 25 => 'residence', 26 => 'palace', 27 => 'treasury',
	28 => 'tradeoffice', 29 => 'greatbarracks', 30 => 'greatstable',
	34 => 'stonemasonslodge', 35 => 'brewery', 37 => 'hero', 41 => 'horsedrinkingtrough',
	42 => 'greatworkshop'
);
// Se repiten: cada uno con su propia condición (nivel máximo alcanzado, etc.).
$repetibles = array(10,11,23,36,38,39);
// Van a un solar fijo: constructBuilding ya rechaza el solar ocupado.
$solarFijo = array(16,31,32,33);

// ---------------------------------------------------------------------------
section('A. La lista de únicos es la misma que aplica la vista');
// ---------------------------------------------------------------------------
check(preg_match('/private static \$singlePerVillage = array\(\s*([0-9,\s]+)\)/',$buildingSource,$m) === 1,
	'existe la lista singlePerVillage en Building');
$declarados = array();
if(isset($m[1])) {
	foreach(explode(',',preg_replace('/\s+/','',$m[1])) as $valor) {
		if($valor !== '') {
			$declarados[] = (int)$valor;
		}
	}
}
sort($declarados);
$esperados = array_keys($unicos);
sort($esperados);
check($declarados === $esperados,
	'la lista declara exactamente los edificios únicos ('.implode(',',array_diff($esperados,$declarados))
		.' faltan / '.implode(',',array_diff($declarados,$esperados)).' sobran)');

// La vista ya no repite la regla: pregunta lo mismo que decide el motor, y ahí la
// unicidad la aplica isSingleBuildingAllowed() —construido o encolado, da igual—.
foreach($unicos as $gid => $variable) {
	check(preg_match('/if\(\$building->meetRequirement\('.$gid.'\)/',$availableSource) === 1,
		'avaliable.tpl ofrece el edificio '.$gid.' si y sólo si el motor lo permite');
}
check(strpos($availableSource,'getBuildList(') === false
	|| preg_match('/getBuildList\((1[06]|3[123])\)/',$availableSource) === 1,
	'la lista sólo consulta la cola a mano para lo que va a un solar fijo');

// ---------------------------------------------------------------------------
section('B. Los repetibles quedan fuera y conservan su regla');
// ---------------------------------------------------------------------------
foreach($repetibles as $gid) {
	check(!in_array($gid,$declarados,true),
		'el edificio '.$gid.' se puede repetir y no está en la lista de únicos');
}
foreach($solarFijo as $gid) {
	check(!in_array($gid,$declarados,true),
		'el edificio '.$gid.' va a un solar fijo y no necesita la regla de unicidad');
}
check(preg_match('/case 10:\s*case 11:\s*return \$this->canBuildAnotherOfType\(\$id\);/s',$buildingSource) === 1
	&& preg_match('/case 38:\s*case 39:.*?canBuildAnotherOfType\(\$id\)/s',$buildingSource) === 1,
	'almacén, granero y sus versiones grandes siguen exigiendo el anterior al máximo');
check(preg_match('/case 23:.*?getTypeCount\(23\) == 0 \|\| \$this->getTypeLevel\(23\) >= 10/s',$buildingSource) === 1,
	'el escondite sigue admitiendo otro sólo a partir del nivel 10');
check(preg_match('/case 36:.*?getTypeCount\(36\) == 0 \|\| \$this->getTypeLevel\(36\) == 20/s',$buildingSource) === 1,
	'el trampero sigue admitiendo otro sólo con el anterior al máximo');

// ---------------------------------------------------------------------------
section('C. meetRequirement aplica la unicidad antes del switch');
// ---------------------------------------------------------------------------
check(preg_match('/public function meetRequirement\(\$id\).*?isSingleBuildingAllowed\(\$id\).*?switch\(\$id\)/s',$buildingSource) === 1,
	'meetRequirement() corta por unicidad antes de evaluar los requisitos del edificio');
check(preg_match('/function isSingleBuildingAllowed\(\$tid\).*?getTypeCount\(\$tid\) == 0 && !\$this->hasQueuedType\(\$tid\)/s',$buildingSource) === 1,
	'la regla mira tanto lo construido como lo que hay en la cola');
check(strpos($buildingSource,'isSingleBonusBuildingAllowed') === false,
	'ya no queda la regla parcial que sólo cubría los edificios de bonus');
check(preg_match('/case 25:.*?getTypeCount\(26\) == 0 && !\$this->hasQueuedType\(26\)/s',$buildingSource) === 1,
	'la residencia sigue excluyendo al palacio (no es unicidad, es exclusión mutua)');
check(preg_match('/case 26:.*?getTypeCount\(25\) == 0 && !\$this->hasQueuedType\(25\)/s',$buildingSource) === 1,
	'el palacio sigue excluyendo a la residencia');
check(preg_match('/case 26:.*?hasPalaceInAnotherVillage\(\)/s',$buildingSource) === 1,
	'el palacio sigue siendo único por cuenta, no sólo por aldea');

// ---------------------------------------------------------------------------
section('D. Los requisitos propios de cada edificio siguen en pie');
// ---------------------------------------------------------------------------
// Los requisitos de nivel viven en una sola tabla (tools/check_building_requirements.php
// la compara entera contra la oficial); acá sólo se comprueba que la unicidad no se los
// haya llevado puestos.
require_once dirname(__DIR__).'/GameEngine/Catapult.php';
$requisitos = array(
	5  => array(1 => 10, 15 => 5),
	9  => array(4 => 10, 15 => 5, 8 => 5),
	12 => array(22 => 3, 15 => 3),
	14 => array(16 => 15),
	17 => array(10 => 1, 11 => 1, 15 => 3),
	20 => array(12 => 3, 22 => 5),
	24 => array(22 => 10, 15 => 10),
	28 => array(17 => 20, 20 => 10),
	35 => array(11 => 20, 16 => 10),
	41 => array(20 => 20, 16 => 10),
	42 => array(21 => 20)
);
foreach($requisitos as $gid => $esperado) {
	$tabla = buildingLevelRequirements($gid);
	$igual = is_array($tabla) && count($tabla) === count($esperado);
	if($igual) {
		foreach($esperado as $req => $nivel) {
			if(!isset($tabla[$req]) || (int)$tabla[$req] !== (int)$nivel) { $igual = false; }
		}
	}
	check($igual,'el edificio '.$gid.' conserva sus requisitos de nivel');
}
check(preg_match('/case 34:.*?capital === 1 && \$this->getTypeLevel\(25\) == 0/s',$buildingSource) === 1,
	'el taller de cantería sigue pidiendo capital y una aldea sin Residencia');

check(strpos($availableSource,'$marketplace') === false,
	'la lista no consulta $marketplace, que nunca se define (el nivel del Mercado está en $market)');
check(strpos($availableSource,'if($market == 0 && !$building->meetsLevelRequirements(17))') !== false,
	'el Mercado sólo se anuncia como "próximamente" si la aldea todavía no lo tiene');

// ---------------------------------------------------------------------------
section('E. La ficha del edificio al máximo dice de qué edificio habla');
// ---------------------------------------------------------------------------
$upgradeSource = file_get_contents(dirname(__DIR__).'/Templates/Build/upgrade.tpl');
check(strpos($upgradeSource,'<!--".$building->procResType') === false,
	'el nombre del edificio ya no viaja dentro de un comentario HTML en el nivel máximo');
check(strpos($upgradeSource,'" completamente mejorado</b></span></p>"') !== false,
	'el nivel máximo se anuncia con el nombre del edificio delante');
check(strpos($upgradeSource,'El último nivel de ') !== false,
	'el último nivel en obra ya no se anuncia como "completamente mejorado"');

echo "\n";
if(count($GLOBALS['fails']) > 0) {
	echo "Unique building checks FAILED (".count($GLOBALS['fails'])." de ".$GLOBALS['checks'].").\n";
	exit(1);
}
echo "Unique building checks passed (".$GLOBALS['checks']." comprobaciones).\n";
exit(0);
