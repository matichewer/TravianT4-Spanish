<?php
/**
 * Las dos pestañas de tropas del resumen de aldeas (dorf3.php?s=5).
 *
 * Cubre:
 *   A. "Tropas propias" cuenta el ejército entero de cada aldea, no sólo lo que está en
 *      casa: refuerzos en otra aldea (propia o ajena), en camino, colonos, el héroe de
 *      aventura y las tropas atrapadas. Leer sólo `units` las hacía desaparecer de las
 *      dos pestañas a la vez.
 *   B. Nada se cuenta dos veces: un refuerzo entre dos aldeas de la misma cuenta suma una
 *      sola vez, en la aldea que lo entrenó.
 *   C. Lo en camino se agrupa por `attacks.vref` (aldea natal) y no por `movement.from`:
 *      devolver un refuerzo ajeno sale de la aldea propia pero las tropas son de otro.
 *   D. Un movimiento ya procesado (proc = 1) no cuenta.
 *   E. "Tropas en aldeas" lista los grupos por ubicación, con las columnas de la tribu de
 *      cada grupo, e incluye naturaleza, oasis anexados y animales enjaulados.
 *   F. La plantilla no reimplementa nada de esto y las dos pestañas son enlaces reales.
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
section('A. La aldea natal ve todo su ejército, esté donde esté');
// ---------------------------------------------------------------------------

// A: 100 legionarios en casa, 50 de refuerzo en la aldea del aliado, 20 de refuerzo en la
// otra aldea de la misma cuenta, 10 en camino, 5 volviendo, 3 colonos, el héroe de
// aventura y 7 atrapadas en las trampas de otro.
addUnits($A, array('u1' => 100, 'u2' => 8, 'hero' => 0));
addEnforce($A, $ALLY, array('u1' => 50));
addEnforce($A, $B,    array('u1' => 20));
addMove(3, $A, $FOREIGN, $A, array(1 => 10));
addMove(4, $FOREIGN, $A, $A, array(1 => 5));
addPlainMove(5, $A, $FOREIGN);
addPlainMove(9, $A, $FOREIGN);
$database->query("INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) VALUES ($FOREIGN,$A,7,0,0,0,0,0,0,0,0,0,0)");

$own = troopOverviewOwnTroops(array($A,$B), $ROMAN);

check($own[$A]['home']['u1'] === 100, 'en casa cuenta lo que está en `units` (100 legionarios)');
check($own[$A]['away']['u1'] === 50 + 20 + 10 + 5 + 7,
    'fuera suma refuerzo al aliado, refuerzo a aldea propia, ida, vuelta y prisioneras (esperado 92, dio '.$own[$A]['away']['u1'].')');
check($own[$A]['total']['u1'] === 192, 'el total de la aldea es casa + fuera (192)');
check($own[$A]['total']['u10'] === 3, 'los 3 colonos en camino cuentan como la última unidad de la tribu');
check($own[$A]['total']['hero'] === 1, 'el héroe de aventura sigue perteneciendo a su aldea');
check($own[$A]['home']['hero'] === 0, 'el héroe de aventura no está en casa');

$kinds = array();
foreach($own[$A]['groups'] as $group) {
    $kinds[$group['kind']] = true;
}
foreach(array('support','moving','settlers','adventure','captive') as $kind) {
    check(isset($kinds[$kind]), 'el detalle de lo que está fuera distingue el caso "'.$kind.'"');
}

// ---------------------------------------------------------------------------
section('B. Un refuerzo entre aldeas propias se cuenta una sola vez');
// ---------------------------------------------------------------------------

addUnits($B, array('u1' => 40));
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN);
check($own[$B]['total']['u1'] === 40,
    'los 20 legionarios de A que están en B no se suman a B (esperado 40, dio '.$own[$B]['total']['u1'].')');
check($own[$A]['total']['u1'] + $own[$B]['total']['u1'] === 232,
    'el total de la cuenta no cuenta dos veces el refuerzo intra-cuenta (esperado 232)');

// ---------------------------------------------------------------------------
section('C. Lo en camino se agrupa por la aldea natal, no por el origen del movimiento');
// ---------------------------------------------------------------------------

// El jugador devuelve un refuerzo ajeno que tenía en A: el movimiento sale DE A, pero las
// tropas son de FOREIGN. Es el caso que getVillageMovement() cuenta mal.
addMove(3, $A, $FOREIGN, $FOREIGN, array(1 => 500));
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN);
check($own[$A]['total']['u1'] === 192,
    'devolver un refuerzo ajeno no infla el ejército propio (esperado 192, dio '.$own[$A]['total']['u1'].')');

// ---------------------------------------------------------------------------
section('D. Un movimiento ya procesado no cuenta');
// ---------------------------------------------------------------------------

addMove(3, $A, $FOREIGN, $A, array(1 => 999), 1);
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN);
check($own[$A]['total']['u1'] === 192,
    'proc = 1 queda fuera (esperado 192, dio '.$own[$A]['total']['u1'].')');

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

$garrisons = troopOverviewVillageGarrisons(array($A,$B), $ROMAN);
$byKind = array();
foreach($garrisons[$A] as $group) {
    $byKind[$group['kind']][] = $group;
}

check(isset($byKind['own']) && $byKind['own'][0]['units']['u1'] === 100,
    'el grupo propio muestra sólo lo que está en la aldea (100), no el ejército entero');
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

// El refuerzo que B tiene en el oasis de A sale de B en la pestaña 1 y aparece en la
// pestaña 2 bajo A: es la misma tropa vista desde los dos lados, nunca dos veces en la misma.
// (Los 20 legionarios que hay en B son de A, no de B: en `enforcement` `from` es la aldea
// natal y `vref` el destino, y confundirlos es justo el error que esta pestaña arrastraba.)
$own = troopOverviewOwnTroops(array($A,$B), $ROMAN);
check($own[$B]['away']['u1'] === 12,
    'B ve fuera los 12 que tiene en el oasis de A (dio '.$own[$B]['away']['u1'].')');
check($own[$B]['home']['u1'] === 40,
    'los 20 legionarios de A alojados en B no se cuelan en el grupo propio de B');
check($own[$B]['total']['u1'] === 52,
    'el ejército de B es 40 en casa + 12 en el oasis (dio '.$own[$B]['total']['u1'].')');
$oasisGroup = null;
foreach($own[$B]['groups'] as $group) {
    if($group['kind'] === 'support' && (int)$group['where'] === $OASIS) { $oasisGroup = $group; }
}
check($oasisGroup !== null, 'el detalle de B nombra el oasis donde están esos 12');

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

$tpl = file_get_contents(dirname(__DIR__).'/Templates/dorf3/5.tpl');
check(strpos($tpl,'dorf3.php?s=5&amp;su=2') !== false,
    '"Tropas en aldeas" es un enlace real y no un <span> muerto');
check(strpos($tpl,'troopOverviewOwnTroops(') !== false && strpos($tpl,'troopOverviewVillageGarrisons(') !== false,
    'la plantilla usa la agregación compartida');
check(strpos($tpl,'$database->getUnit(') === false,
    'la plantilla ya no lee `units` por su cuenta (era lo que escondía las tropas fuera de casa)');
check(strpos($tpl,'class="vil_troops"') !== false,
    'la segunda pestaña usa la tabla vil_troops que el gpack ya estila');
check(preg_match('/tr class="small/',$tpl) === 1 || substr_count($tpl,'class="small') >= 1,
    'la fila chica de "fuera de la aldea" usa la clase small del gpack');

$engine = file_get_contents(dirname(__DIR__).'/GameEngine/TroopOverview.php');
check(strpos($engine,'a.vref IN ($in)') !== false,
    'el join de movimientos filtra por attacks.vref (la aldea natal)');
check(strpos($engine,'m.sort_type IN (3,4)') !== false,
    'cuenta la ida y la vuelta, que comparten la misma fila de attacks');

$dorf3 = file_get_contents(dirname(__DIR__).'/dorf3.php');
check(strpos($dorf3,"su=") !== false,
    'cambiar de aldea conserva la subpestaña');

echo PHP_EOL;
if(empty($failures)) {
    echo 'TODO OK'.PHP_EOL;
    exit(0);
}
echo count($failures).' fallo(s)'.PHP_EOL;
exit(1);
