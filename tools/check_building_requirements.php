<?php
/**
 * Auditoría de los requisitos de construcción (qué edificio pide qué).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_building_requirements.php
 *
 * El bug que motivó este checker: la ficha del Ayuntamiento en la lista de
 * "construcciones disponibles próximamente" anunciaba "Academia 10, Edificio principal
 * Nivel 3" mientras el motor pedía Edificio principal 10. El jugador leía que cumplía
 * los requisitos, el edificio no aparecía nunca en la lista de disponibles y no había
 * forma de darse cuenta de por qué. El requisito estaba escrito a mano en CUATRO
 * lugares distintos (el motor, la reja de "disponible", la de "próximamente" y el texto
 * de la ficha) y con el tiempo divergieron.
 *
 * Cubre:
 *   A. buildingLevelRequirements() es la tabla oficial de T4, edificio por edificio.
 *   B. meetRequirement() ya no compara niveles a mano: los saca de la tabla.
 *   C. Ninguna ficha de "próximamente" repite el requisito escrito a mano.
 *   D. La lista de disponibles pregunta lo mismo que decide el motor.
 *   E. El caso del Ayuntamiento, resuelto por el motor sobre una aldea de prueba.
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
define('ALLOW_ALL_TRIBE', false);
define('BASIC_MAX', 1);
define('INNER_MAX', 1);
define('PLUS_MAX', 1);
define('BANNED', 9);
define('MODERATOR', 8);

require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Production.php';
require dirname(__DIR__).'/GameEngine/Building.php';

// ---------------------------------------------------------------------------
section('A. La tabla es la oficial de T4');
// ---------------------------------------------------------------------------
// Columna del manual oficial. Un cambio acá tiene que ser una decisión, no un descuido.
$oficial = array(
    1 => array(), 2 => array(), 3 => array(), 4 => array(),
    5  => array(1 => 10, 15 => 5),      // Aserradero
    6  => array(2 => 10, 15 => 5),      // Fábrica de ladrillos
    7  => array(3 => 10, 15 => 5),      // Fundición de hierro
    8  => array(4 => 5, 15 => 5),       // Molino
    9  => array(4 => 10, 15 => 5, 8 => 5), // Panadería
    10 => array(15 => 1),               // Almacén
    11 => array(15 => 1),               // Granero
    12 => array(22 => 3, 15 => 3),      // Herrería
    14 => array(16 => 15),              // Plaza de torneos
    15 => array(), 16 => array(),
    17 => array(10 => 1, 11 => 1, 15 => 3), // Mercado
    18 => array(),                      // Embajada (ver el comentario de la tabla)
    19 => array(16 => 1, 15 => 3),      // Cuartel
    20 => array(12 => 3, 22 => 5),      // Establo
    21 => array(22 => 10, 15 => 5),     // Taller
    22 => array(19 => 3, 15 => 3),      // Academia
    23 => array(),                      // Escondite
    24 => array(22 => 10, 15 => 10),    // Ayuntamiento
    25 => array(15 => 5),               // Residencia
    26 => array(18 => 1, 15 => 5),      // Palacio
    27 => array(15 => 10),              // Tesoro
    28 => array(17 => 20, 20 => 10),    // Oficina de comercio
    29 => array(19 => 20),              // Gran cuartel
    30 => array(20 => 20),              // Gran establo
    31 => array(), 32 => array(), 33 => array(),
    34 => array(26 => 3, 15 => 5),      // Taller de cantería
    35 => array(11 => 20, 16 => 10),    // Cervecería
    36 => array(16 => 1),               // Trampero
    37 => array(16 => 1, 15 => 3),      // Mansión del héroe
    38 => array(15 => 10),              // Gran almacén
    39 => array(15 => 10),              // Gran granero
    40 => array(),                      // Maravilla del mundo
    41 => array(20 => 20, 16 => 10),    // Abrevadero
    42 => array(21 => 20),              // Gran taller
);
foreach($oficial as $gid => $esperado) {
    $tabla = buildingLevelRequirements($gid);
    $ok = is_array($tabla) && count($tabla) === count($esperado);
    if($ok) {
        foreach($esperado as $req => $nivel) {
            if(!isset($tabla[$req]) || (int)$tabla[$req] !== (int)$nivel) { $ok = false; }
        }
    }
    check($ok, buildingDisplayName($gid).' (gid '.$gid.') pide exactamente lo que dice el oficial');
}
check(buildingLevelRequirements(13) === null,'un gid que no es ningún edificio no tiene tabla');
check(buildingLevelRequirements(99) === null,'un gid inventado no tiene tabla');

// El requisito que le faltaba a este repo: la Herrería es Academia 3, no Academia 1.
$herreria = buildingLevelRequirements(12);
check(isset($herreria[22]) && (int)$herreria[22] === 3,'la Herrería pide Academia 3 (el repo pedía 1)');
// Y el que estaba mal escrito en la ficha: Ayuntamiento con Edificio principal 10.
$ayuntamiento = buildingLevelRequirements(24);
check(isset($ayuntamiento[15]) && (int)$ayuntamiento[15] === 10,'el Ayuntamiento pide Edificio principal 10');

// ---------------------------------------------------------------------------
section('B. El motor no vuelve a escribir los niveles a mano');
// ---------------------------------------------------------------------------
$buildingSource = file_get_contents(dirname(__DIR__).'/GameEngine/Building.php');
$inicio = strpos($buildingSource,'public function meetRequirement(');
$fin = strpos($buildingSource,'public function meetsLevelRequirements(');
check($inicio !== false && $fin !== false && $fin > $inicio,'meetRequirement() y meetsLevelRequirements() siguen ahí');
$cuerpo = substr($buildingSource,$inicio,$fin - $inicio);
// Lo único que puede quedar mirando un nivel acá son las reglas que no son requisitos:
// el escondite y el trampero repetibles (miran su propio nivel) y la capital sin
// Residencia. Cualquier otro gid sería un requisito escrito a mano otra vez.
preg_match_all('/getTypeLevel\(\s*(\d+)\s*\)/',$cuerpo,$niveles);
$aMano = array_values(array_diff(array_unique($niveles[1]),array('23','25','36')));
check(count($aMano) === 0,
    'meetRequirement() no compara niveles de otro edificio a mano: sale todo de la tabla'
    .(count($aMano) ? ' (quedó gid '.implode(', ',$aMano).')' : ''));
check(strpos($cuerpo,'meetsLevelRequirements') !== false,'meetRequirement() consulta la tabla');
check(strpos($buildingSource,'function requirementsHtml') !== false,
    'existe el único armador del bloque "Necesario:"');

// ---------------------------------------------------------------------------
section('C. Las fichas de "próximamente" no repiten el requisito');
// ---------------------------------------------------------------------------
$fichas = glob(dirname(__DIR__).'/Templates/Build/soon/*.tpl');
check(count($fichas) > 20,'se encontraron las fichas de "próximamente"');
foreach($fichas as $ficha) {
    $nombre = basename($ficha);
    $source = file_get_contents($ficha);
    $bloque = '';
    if(preg_match('/Necesario:<\/div>(.*?)<\/div>/s',$source,$m)) {
        $bloque = $m[1];
    }
    check($bloque !== '' && strpos($bloque,'requirementsHtml') !== false,
        $nombre.': el bloque "Necesario:" sale de requirementsHtml()');
    check(stripos($bloque,'Nivel ') === false,
        $nombre.': no queda ningún nivel escrito a mano en la ficha');
}

// ---------------------------------------------------------------------------
section('D. La lista de disponibles pregunta lo que decide el motor');
// ---------------------------------------------------------------------------
$lista = file_get_contents(dirname(__DIR__).'/Templates/Build/avaliable.tpl');
// La Plaza de reuniones y las murallas van por casilla fija (39 y 40), no por requisitos.
$porCasilla = array('rallypoint','citywall','earthwall','palisade');
preg_match_all('/^(?<cond>if\(.*?\)\s*\{)\s*\n\s*include\("avaliable\/(?<tpl>[a-z]+)\.tpl"\);/mi',$lista,$reja,PREG_SET_ORDER);
check(count($reja) > 20,'se encontraron las rejas de la lista de disponibles');
foreach($reja as $entrada) {
    if(in_array($entrada['tpl'],$porCasilla,true)) { continue; }
    check(strpos($entrada['cond'],'meetRequirement(') !== false,
        'avaliable/'.$entrada['tpl'].'.tpl se ofrece si y sólo si el motor lo permite');
}
check(strpos($lista,'mysql_query(') === false,
    'la lista ya no busca el Palacio de la cuenta con SQL a mano (in_array(26,$fila) daba falsos positivos)');

// ---------------------------------------------------------------------------
section('E. El Ayuntamiento, resuelto por el motor');
// ---------------------------------------------------------------------------
class RequirementsDatabase {
    public $jobs = array();
    public function getJobs($wid) { return $this->jobs; }
    public function getBuildingByField($wid,$field) { return array(); }
    public function getDemolition($wid) { return array(); }
    public function getMasterJobs($wid) { return array(); }
    public function hasPalace($uid,$exclude = 0) { return false; }
    public function hasActiveArtefactEffect($wid,$uid,$type) { return false; }
}
class RequirementsVillage {
    public $wid = 1;
    public $capital = 0;
    public $pop = 0;
    public $resarray = array();
    public $ocounter = array(0,0,0,0);
}

$database = new RequirementsDatabase();
$village = new RequirementsVillage();
$session = (object)array('tribe'=>1,'plus'=>0,'access'=>1,'uid'=>1);

$reflection = new ReflectionClass('Building');
$building = $reflection->newInstanceWithoutConstructor();
$buildArray = $reflection->getProperty('buildArray');
$buildArray->setAccessible(true);
$buildArray->setValue($building,array());

/** Aldea con los edificios que se le pasen: campo => array(tipo, nivel). */
function requirementsVillage($buildings) {
    global $village;
    $resarray = array();
    for($slot = 1; $slot <= 40; $slot++) { $resarray['f'.$slot] = 0; $resarray['f'.$slot.'t'] = 0; }
    $slot = 19;
    foreach($buildings as $tipo => $nivel) {
        $resarray['f'.$slot.'t'] = $tipo;
        $resarray['f'.$slot] = $nivel;
        $slot++;
    }
    $village->resarray = $resarray;
}

requirementsVillage(array(15 => 9, 22 => 10));
check(!$building->meetsLevelRequirements(24),'con Edificio principal 9 y Academia 10 el Ayuntamiento no se puede');
check(!$building->meetRequirement(24),'y el motor lo rechaza');
$html = $building->requirementsHtml(24);
check(strpos($html,'Nivel 10') !== false && strpos($html,'Nivel 3') === false,
    'la ficha anuncia Edificio principal 10, que es lo que el motor pide');
check(substr_count($html,'color:#a10000') >= 2,'el requisito que falta sale marcado, no mezclado con los cumplidos');

requirementsVillage(array(15 => 10, 22 => 10));
check($building->meetsLevelRequirements(24),'con los dos en 10 se cumplen los requisitos');
check($building->meetRequirement(24),'y el motor lo permite');
check(strpos($building->requirementsHtml(24),'color:#a10000') === false,'la ficha ya no marca nada en rojo');

requirementsVillage(array(15 => 10, 22 => 10, 24 => 1));
check(!$building->meetRequirement(24),'el Ayuntamiento es único por aldea');

// La Herrería, con el requisito oficial.
requirementsVillage(array(15 => 3, 22 => 2));
check(!$building->meetRequirement(12),'con Academia 2 no hay Herrería');
requirementsVillage(array(15 => 3, 22 => 3));
check($building->meetRequirement(12),'con Academia 3 sí');

// Un gid que no existe no se construye ni por accidente.
requirementsVillage(array(15 => 20));
check(!$building->meetRequirement(13),'el gid 13 no es construible');

echo "\n";
if(count($GLOBALS['fails']) > 0) {
    echo "Building requirement checks FAILED (".count($GLOBALS['fails'])." de ".$GLOBALS['checks'].").\n";
    exit(1);
}
echo "Building requirement checks passed (".$GLOBALS['checks']." comprobaciones).\n";
exit(0);
