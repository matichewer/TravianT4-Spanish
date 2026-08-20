<?php
/**
 * Las dos pestañas de tropas del resumen de aldeas (dorf3.php?s=5).
 *
 * Cubre:
 *   A. "Tropas propias" muestra lo que hay EN cada aldea, como en el T4 oficial: un
 *      refuerzo enviado a otra aldea NO aparece en la fila de la aldea que lo entrenó.
 *      Esto es a propósito y por eso está fijado acá — se ve en la plaza de reuniones.
 *   B. Un refuerzo alojado en una aldea propia tampoco se suma a las tropas propias de la
 *      aldea que lo hospeda: son de otro dueño y van en la otra pestaña.
 *   E. "Tropas en aldeas" lista los grupos por ubicación, con las columnas de la tribu de
 *      cada grupo, e incluye naturaleza, oasis anexados y animales enjaulados.
 *   F. La plantilla no reimplementa nada de esto y las dos pestañas son enlaces reales.
 *   G. La manutención (que sí tiene que cobrar todo, esté donde esté) cuenta las tropas en
 *      camino en los dos sentidos y al héroe de aventura.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_troop_overview.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
// Hasta acá alcanza para la agregación de las pestañas. Technology hace falta para la
// sección G, que comprueba el efecto real en el cereal y no sólo el conteo intermedio.
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
// getUpkeep() mira $session->tribe para el descuento del Abrevadero (sólo romano) y
// $building para su nivel; sin aldea activa el descuento simplemente no se aplica.
$session = new stdClass();
$session->tribe = 1;

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

// Ids fuera del mapa (WORLD_MAX llega a ~40.000 wref) para no pisar nada del mundo vivo.
$A = 990001;   // aldea propia que entrena
$B = 990002;   // otra aldea de la misma cuenta
$ALLY = 990003; // aldea de un aliado
$OASIS = 990004; // oasis anexado por A
$FOREIGN = 990005; // aldea ajena cuyo refuerzo está en A
$SCRATCH = array($A,$B,$ALLY,$OASIS,$FOREIGN);

function cleanScratch() {
    global $database, $SCRATCH;
    $in = implode(',',$SCRATCH);
    $database->query("DELETE FROM ".TB_PREFIX."units WHERE vref IN ($in)");
    $database->query("DELETE FROM ".TB_PREFIX."enforcement WHERE `from` IN ($in) OR vref IN ($in)");
    $database->query("DELETE FROM ".TB_PREFIX."prisoners WHERE `from` IN ($in) OR wref IN ($in)");
    $database->query("DELETE FROM ".TB_PREFIX."odata WHERE wref IN ($in) OR conqured IN ($in)");
    $database->query("DELETE FROM ".TB_PREFIX."vdata WHERE wref IN ($in)");
    foreach($database->query_return("SELECT m.moveid, m.ref, m.sort_type FROM ".TB_PREFIX."movement m WHERE m.`from` IN ($in) OR m.`to` IN ($in)") as $row) {
        if((int)$row['sort_type'] === 3 || (int)$row['sort_type'] === 4) {
            $database->query("DELETE FROM ".TB_PREFIX."attacks WHERE id = ".(int)$row['ref']);
        }
        $database->query("DELETE FROM ".TB_PREFIX."movement WHERE moveid = ".(int)$row['moveid']);
    }
}
register_shutdown_function('cleanScratch');
cleanScratch();

$ROMAN = 1;   // u1..u10
$GAUL  = 3;   // u21..u30

function addUnits($vref, array $units) {
    global $database;
    $cols = array('vref');
    $vals = array((int)$vref);
    foreach($units as $key => $amount) {
        $cols[] = '`'.$key.'`';
        $vals[] = (int)$amount;
    }
    $database->query("INSERT INTO ".TB_PREFIX."units (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
}
function addEnforce($from, $vref, array $units) {
    global $database;
    $cols = array('`from`','vref');
    $vals = array((int)$from,(int)$vref);
    foreach($units as $key => $amount) {
        $cols[] = '`'.$key.'`';
        $vals[] = (int)$amount;
    }
    $database->query("INSERT INTO ".TB_PREFIX."enforcement (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
}
/** Un movimiento con su fila de `attacks`, como los crea el motor. */
function addMove($sortType, $from, $to, $homeVillage, array $slots, $proc = 0) {
    global $database;
    $t = array();
    for($i = 1; $i <= 11; $i++) {
        $t[] = (int)(isset($slots[$i]) ? $slots[$i] : 0);
    }
    $database->query("INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy,sethome)"
        ." VALUES (".(int)$homeVillage.",".implode(',',$t).",3,0,0,0,0)");
    $ref = $database->query_return("SELECT LAST_INSERT_ID() AS id");
    $ref = (int)$ref[0]['id'];
    $database->query("INSERT INTO ".TB_PREFIX."movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)"
        ." VALUES (".(int)$sortType.",".(int)$from.",".(int)$to.",$ref,0,'',".(time()+3600).",".(int)$proc.",1,0,0,0,0)");
}
function addPlainMove($sortType, $from, $to) {
    global $database;
    $database->query("INSERT INTO ".TB_PREFIX."movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)"
        ." VALUES (".(int)$sortType.",".(int)$from.",".(int)$to.",0,0,'',".(time()+3600).",0,1,0,0,0,0)");
}

// ---------------------------------------------------------------------------
section('A. "Tropas propias": las tropas del jugador contadas donde están');
// ---------------------------------------------------------------------------

$UID = 990009;
// A entrena 100 legionarios: 30 se quedan, 20 van de refuerzo a B (aldea propia) y 50 a la
// aldea de un aliado. Además tiene 10 de ataque en camino, 3 colonos y 7 prisioneras.
addUnits($A, array('u1' => 30, 'u2' => 8, 'hero' => 1));
addUnits($B, array('u1' => 40));
addEnforce($A, $B,    array('u1' => 20));
addEnforce($A, $ALLY, array('u1' => 50));
addMove(3, $A, $FOREIGN, $A, array(1 => 10));
addPlainMove(5, $A, $FOREIGN);
$database->query("INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) VALUES ($FOREIGN,$A,7,0,0,0,0,0,0,0,0,0,0)");
// Quién es dueño de qué: A y B son del jugador, la del aliado no.
$database->query("INSERT INTO ".TB_PREFIX."vdata (wref,owner,name) VALUES ($A,$UID,'Aldea propia A'),($B,$UID,'Aldea propia B'),($ALLY,777001,'Aldea del aliado')");

$own = troopOverviewOwnTroops(array($A,$B), $ROMAN, $UID);

check($own[$A]['u1'] === 30,
    'la aldea que los entrenó muestra los 30 que se quedaron (dio '.$own[$A]['u1'].')');
check($own[$B]['u1'] === 60,
    'los 20 alojados en la otra aldea propia se cuentan EN esa aldea: 40 + 20 (dio '.$own[$B]['u1'].')');
check($own[$A]['u1'] + $own[$B]['u1'] === 90,
    'y una sola vez: el total no los duplica (esperado 90)');
check($own[$A]['hero'] === 1, 'el héroe se muestra en la aldea donde está');
check($own[$A]['u2'] === 8, 'las demás unidades de la aldea se muestran igual');

// Lo que está fuera de las aldeas del jugador no aparece acá, y eso es la regla: se ve en
// la plaza de reuniones, como en el T4 oficial.
check($own[$A]['u1'] + $own[$B]['u1'] !== 140,
    'los 50 que refuerzan al aliado no se muestran en esta pantalla');
check($own[$A]['u10'] === 0, 'los colonos en camino tampoco');

// ---------------------------------------------------------------------------
section('B. Los refuerzos ajenos no son tropas propias');
// ---------------------------------------------------------------------------

addEnforce($FOREIGN, $B, array('u1' => 500));
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN, $UID);
check($own[$B]['u1'] === 60,
    'un refuerzo de otro jugador alojado en B no engorda las tropas propias de B (esperado 60, dio '.$own[$B]['u1'].')');

// El invariante que evita que las dos pestañas vuelvan a discrepar.
$garrisonsB = troopOverviewVillageGarrisons(array($B), $ROMAN, $UID);
$sum = troopOverviewEmptyUnits(1,10);
foreach($garrisonsB[$B] as $group) {
    if((int)$group['owner'] === $UID) {
        $sum = troopOverviewSumUnits($sum,$group['units']);
    }
}
check($sum['u1'] === $own[$B]['u1'],
    'la celda de la pestaña 1 es la suma de los grupos propios de la pestaña 2');


// ---------------------------------------------------------------------------
section('E. "Tropas en aldeas" agrupa por ubicación');
// ---------------------------------------------------------------------------

// En A: sus propias tropas, un refuerzo galo de otro jugador, animales enjaulados, y la
// guarnición del oasis que anexó.
addEnforce($FOREIGN, $A, array('u21' => 33));
addEnforce(0, $A, array('u31' => 4));
$database->query("UPDATE ".TB_PREFIX."units SET u32 = 6 WHERE vref = $A");
$database->query("INSERT INTO ".TB_PREFIX."odata (wref,conqured,name) VALUES ($OASIS,$A,'Oasis de prueba')");
addEnforce($B, $OASIS, array('u1' => 12));

$garrisons = troopOverviewVillageGarrisons(array($A,$B), $ROMAN, $UID);
$byKind = array();
foreach($garrisons[$A] as $group) {
    $byKind[$group['kind']][] = $group;
}

check(isset($byKind['own']) && $byKind['own'][0]['units']['u1'] === 30,
    'el grupo propio son las tropas que la aldea tiene en su fila de `units` (30)');
check(isset($byKind['support']),'los refuerzos de otros jugadores aparecen como grupo aparte');

$gaul = null; $nature = null;
foreach((isset($byKind['support']) ? $byKind['support'] : array()) as $group) {
    if($group['tribe'] === 3) { $gaul = $group; }
    if((int)$group['from'] === 0) { $nature = $group; }
}
check($gaul !== null && $gaul['start'] === 21 && $gaul['end'] === 30 && $gaul['units']['u21'] === 33,
    'un refuerzo galo trae sus propias columnas (u21..u30)');
check($nature !== null && $nature['tribe'] === 4 && $nature['units']['u31'] === 4,
    'un refuerzo con `from` = 0 se resuelve como Naturaleza');
check(isset($byKind['caged']) && $byKind['caged'][0]['units']['u32'] === 6,
    'los animales enjaulados de la fila `units` se muestran como grupo de la Naturaleza');
check(isset($byKind['caged']) && $byKind['caged'][0]['units']['hero'] === 0,
    'el héroe no se duplica entre el grupo propio y el de los animales enjaulados');
check(isset($byKind['oasis']) && $byKind['oasis'][0]['units']['u1'] === 12
    && (int)$byKind['oasis'][0]['where'] === $OASIS,
    'la guarnición de un oasis anexado cuelga de la aldea que lo anexó');

check(count($garrisons[$B]) >= 1, 'una aldea sin refuerzos igual aparece con su grupo propio');

// El refuerzo que B tiene en el oasis de A se ve UNA vez y en un solo lado: en la pestaña
// 2, bajo la aldea A, que es donde está y quien le paga el cereal. En la fila de B de la
// pestaña 1 no aparece, porque no está en B.
// (Los 20 legionarios que hay en B son de A, no de B: en `enforcement` `from` es la aldea
// natal y `vref` el destino, y confundirlos es justo el error que esta pestaña arrastraba.)
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN, $UID);
check($own[$A]['u1'] === 30 + 12,
    'los 12 que B tiene en el oasis de A se cuentan en A, que es donde está el oasis (dio '.$own[$A]['u1'].')');

$oasisGroup = null;
foreach($garrisons[$A] as $group) {
    if($group['kind'] === 'oasis' && (int)$group['from'] === $B) { $oasisGroup = $group; }
}
check($oasisGroup !== null && $oasisGroup['units']['u1'] === 12,
    'y sí se ven, una sola vez, en la pestaña 2 bajo la aldea que anexó el oasis');

// ---------------------------------------------------------------------------
section('F. Rangos de tribu y plantilla');
// ---------------------------------------------------------------------------

check(troopOverviewTribeRange(1) === array(1,10), 'romanos = u1..u10');
check(troopOverviewTribeRange(3) === array(21,30), 'galos = u21..u30');
check(troopOverviewTribeRange(4) === array(31,40), 'naturaleza = u31..u40, no u41 como decía la copia de la plantilla');
check(troopOverviewTribeRange(5) === array(41,50), 'natares = u41..u50');
check(troopOverviewTribeRange(0) === null && troopOverviewTribeRange(6) === null, 'una tribu inexistente no devuelve rango');

$row = array('u21' => 0, 'u23' => 7);
check(troopOverviewDetectTribe($row) === 3, 'si el origen de un refuerzo ya no existe, la tribu se deduce de la fila');

// Orden de fundación, el mismo que el cartel lateral. Lo que se prueba de verdad acá es
// que ninguna aldea se pierda por el camino: la pantalla no puede esconder una aldea
// porque su wref falte en la lista de fundación.
$rows = array(50 => 'grande', 10 => 'chica', 30 => 'mediana');
check(troopOverviewFoundationOrder($rows, array(10,30,50)) === array(10,30,50),
    'las aldeas salen en el orden de fundación, no en el de población');
check(troopOverviewFoundationOrder($rows, array(30)) === array(30,50,10),
    'una aldea que falta en la lista de fundación se agrega al final, no desaparece');
check(troopOverviewFoundationOrder($rows, array()) === array(50,10,30),
    'si la consulta de fundación viene vacía se conserva el orden original');
check(troopOverviewFoundationOrder($rows, array(10,10,30,999)) === array(10,30,50),
    'ids repetidos o inexistentes en la lista de fundación no duplican ni inventan filas');
check(count(troopOverviewFoundationOrder($rows, array(10,30,50))) === count($rows),
    'el orden nunca cambia la cantidad de aldeas');

$tplOrder = file_get_contents(dirname(__DIR__).'/Templates/dorf3/5.tpl');
check(strpos($tplOrder,'troopOverviewFoundationOrder(') !== false
    && strpos($tplOrder,'getVillagesIDByFoundation(') !== false,
    'la pantalla de tropas ordena por fundación, como el cartel lateral');
check(strpos(file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php'),
    'function getProfileVillages($uid) {'."\n".'        		$q = "SELECT * from " . TB_PREFIX . "vdata where owner = $uid order by pop desc";') !== false,
    'getProfileVillages() sigue ordenando por población: la usan una treintena de lugares más');

// Los nombres de las aldeas natar independientes ya llevan las coordenadas pegadas para
// ser únicos, así que agregárselas otra vez daba "Atalaya natar (15|78) (15|78)".
$places = array(700 => array('name' => 'Atalaya natar (15|78)', 'owner' => 2, 'oasis' => false),
                701 => array('name' => 'Zeus', 'owner' => 6, 'oasis' => false));
$coords = array(700 => array(15,78), 701 => array(8,26));
check(troopOverviewPlaceName(700,$places,$coords) === 'Atalaya natar (15|78)',
    'un nombre que ya termina en sus coordenadas no las repite');
check(troopOverviewPlaceName(701,$places,$coords) === 'Zeus (8|26)',
    'un nombre normal sí las lleva');
check(troopOverviewPlaceName(999,$places,$coords) === 'Lugar desconocido',
    'un destino que ya no existe no imprime un nombre vacío');

$tpl = file_get_contents(dirname(__DIR__).'/Templates/dorf3/5.tpl');
check(strpos($tpl,'dorf3.php?s=5&amp;su=2') !== false,
    '"Tropas en aldeas" es un enlace real y no un <span> muerto');
check(strpos($tpl,'troopOverviewOwnTroops(') !== false && strpos($tpl,'troopOverviewVillageGarrisons(') !== false,
    'la plantilla usa la agregación compartida');
check(strpos($tpl,'$database->getUnit(') === false,
    'la plantilla no lee `units` por su cuenta: la agregación es una sola');
check(strpos($tpl,'class="vil_troops"') !== false,
    'la segunda pestaña usa la tabla vil_troops que el gpack ya estila');
check(strpos($tpl,'<tr class="small') === false,
    'la pestaña no agrega filas propias por aldea: una fila por aldea más el Total, como el T4 oficial');
check(strpos($tpl,'<td class="vil fc" colspan="12">') === false,
    'los destinos ya no se imprimen en una fila de texto a lo ancho: parten la cuadrícula en dos');


// La pantalla lee `units` y `enforcement` y nada más: lo que está en camino o atrapado no
// se muestra acá a propósito. Si alguien vuelve a sumarlo, estas dos aserciones lo avisan.
$engine = file_get_contents(dirname(__DIR__).'/GameEngine/TroopOverview.php');
check(strpos($engine,TB_PREFIX."movement") === false && strpos($engine,'."movement') === false,
    'la agregación del resumen no mira los movimientos: lo que está en camino se ve en la plaza de reuniones');
check(strpos($engine,'."prisoners') === false,
    'ni las tropas atrapadas en trampas ajenas');

$dorf3 = file_get_contents(dirname(__DIR__).'/dorf3.php');
check(strpos($dorf3,"su=") !== false,
    'cambiar de aldea conserva la subpestaña');

// ---------------------------------------------------------------------------
section('G. La manutención cobra las tropas en camino en los dos sentidos');
// ---------------------------------------------------------------------------

// getVillageMovement() es la cuenta vieja, la que alimenta el cereal y la hambruna
// (Technology::getAllUnits -> getUpkeep -> Automation::updateRes, que escribe recursos
// reales). Se le cobraba la ida de los exploradores pero no la vuelta: el filtro
// `attack_type != 1` es de pantalla —en getMovement2() oculta el espionaje entrante al
// defensor— y acá dejaba a la aldea produciendo cereal de más durante todo el regreso.
$SCOUTUSER = 990009;
$SCOUTVIL = 990006;
$SCRATCH[] = $SCOUTVIL;
$database->query("DELETE FROM ".TB_PREFIX."users WHERE id = $SCOUTUSER");
$database->query("INSERT INTO ".TB_PREFIX."users (id,username,tribe) VALUES ($SCOUTUSER,'checkTroopOverview',1)");
$database->query("INSERT INTO ".TB_PREFIX."vdata (wref,owner,name) VALUES ($SCOUTVIL,$SCOUTUSER,'Aldea de prueba')");
register_shutdown_function(function() use ($database,$SCOUTUSER) {
    $database->query("DELETE FROM ".TB_PREFIX."users WHERE id = $SCOUTUSER");
});

/** Un movimiento con attack_type explícito, para distinguir espionaje de ataque. */
function addTypedMove($sortType, $from, $to, $homeVillage, $slot, $amount, $attackType) {
    global $database;
    $t = array_fill(1,11,0);
    $t[$slot] = (int)$amount;
    $database->query("INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy,sethome)"
        ." VALUES (".(int)$homeVillage.",".implode(',',$t).",".(int)$attackType.",0,0,0,0)");
    $ref = $database->query_return("SELECT LAST_INSERT_ID() AS id");
    $ref = (int)$ref[0]['id'];
    $database->query("INSERT INTO ".TB_PREFIX."movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop)"
        ." VALUES (".(int)$sortType.",".(int)$from.",".(int)$to.",$ref,0,'',".(time()+3600).",0,1,0,0,0,0)");
}

// 100 Equites Legati (u4) de espionaje, de ida.
addTypedMove(3, $SCOUTVIL, $FOREIGN, $SCOUTVIL, 4, 100, 1);
$moving = $database->getVillageMovement($SCOUTVIL);
check((int)$moving['u4'] === 100, 'los exploradores que van cuentan (dio '.(int)$moving['u4'].')');

// 50 más volviendo del espionaje: son las que no se cobraban.
addTypedMove(4, $FOREIGN, $SCOUTVIL, $SCOUTVIL, 4, 50, 1);
$moving = $database->getVillageMovement($SCOUTVIL);
check((int)$moving['u4'] === 150,
    'los exploradores que vuelven también cuentan (esperado 150, dio '.(int)$moving['u4'].')');

// Y un ataque normal volviendo sigue contando, que era lo único que contaba antes.
addTypedMove(4, $FOREIGN, $SCOUTVIL, $SCOUTVIL, 1, 7, 3);
$moving = $database->getVillageMovement($SCOUTVIL);
check((int)$moving['u1'] === 7, 'un ataque normal que vuelve no se rompió');

// El héroe de aventura: sale de `units` al partir y su movimiento (sort_type 9) no tiene
// fila en `attacks`, así que la aldea dejaba de pagar sus 6 de cereal durante toda la
// aventura. La vuelta ya se cobraba (llega como sort_type 4 con t11 = 1).
$database->query("UPDATE ".TB_PREFIX."units SET hero = 1 WHERE vref = $SCOUTVIL");
if(count($database->query_return("SELECT vref FROM ".TB_PREFIX."units WHERE vref = $SCOUTVIL")) === 0) {
    $database->query("INSERT INTO ".TB_PREFIX."units (vref,hero) VALUES ($SCOUTVIL,1)");
}
$upkeepAtHome = (int)$technology->getUpkeep($technology->getAllUnits($SCOUTVIL),0,$SCOUTVIL);
$database->query("UPDATE ".TB_PREFIX."units SET hero = 0 WHERE vref = $SCOUTVIL");
addPlainMove(9, $SCOUTVIL, $FOREIGN);
$moving = $database->getVillageMovement($SCOUTVIL);
check((int)$moving['hero'] === 1, 'el héroe de aventura cuenta como tropa de su aldea (dio '.(int)$moving['hero'].')');

$upkeepOnAdventure = (int)$technology->getUpkeep($technology->getAllUnits($SCOUTVIL),0,$SCOUTVIL);
check($upkeepOnAdventure === $upkeepAtHome,
    'la aldea paga lo mismo con el héroe en casa que de aventura (casa '.$upkeepAtHome.', aventura '.$upkeepOnAdventure.')');

$database->query("DELETE FROM ".TB_PREFIX."movement WHERE sort_type = 9 AND `from` = $SCOUTVIL");
$upkeepWithoutHero = (int)$technology->getUpkeep($technology->getAllUnits($SCOUTVIL),0,$SCOUTVIL);
check($upkeepAtHome - $upkeepWithoutHero === 6,
    'y esos 6 de cereal son exactamente los del héroe (diferencia '.($upkeepAtHome - $upkeepWithoutHero).')');

$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
check(strpos($dbSource,"if(\$ret['attack_type'] != 1)") === false,
    'getVillageMovement() ya no filtra la vuelta por attack_type: ese filtro es de pantalla, no de manutención');
check(strpos($dbSource,"\$movingunits['u' . (\$vtribe * 10)] += ") === false,
    'el contador de colonos ya no hace += sobre una clave sin inicializar');
check(strpos($dbSource,'$adventurearray = $this->getMovement(9, $id, 0);') !== false,
    'getVillageMovement() mira también los movimientos de aventura (sort_type 9)');

echo PHP_EOL;
if(empty($failures)) {
    echo 'TODO OK'.PHP_EOL;
    exit(0);
}
echo count($failures).' fallo(s)'.PHP_EOL;
exit(1);
