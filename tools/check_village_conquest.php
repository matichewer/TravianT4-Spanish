<?php
/**
 * La conquista de aldeas, de punta a punta, contra el T4 oficial.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_village_conquest.php
 *
 * Corre sobre TABLAS TEMPORALES copiadas del esquema real (SHOW CREATE TABLE): tienen el
 * esquema exacto de las reales y tapan sus nombres sólo para esta conexión, así que el
 * motor entero cae sobre ellas y el mundo de verdad no se toca. Por eso puede probar el
 * camino real —`applyConquestLoyalty()` y `completeVillageConquest()`— en vez de leer el
 * código fuente y esperar lo mejor.
 *
 * Las reglas oficiales que fija, todas de support.travian.com:
 *
 *   Para conquistar hacen falta: que la aldea no tenga residencia/palacio en pie, que no
 *   sea capital, que no sea la última del defensor, puntos de cultura para una aldea más,
 *   un cupo de expansión libre en la aldea que ataca, ataque normal (no asalto) y que el
 *   administrador sobreviva.
 *
 *   Al conquistarla: la lealtad queda en 0 y sólo la levanta una residencia nueva (2/3 del
 *   nivel por hora); desaparece **un** administrador, y sólo si la conquista se concreta;
 *   las tropas que lo acompañaban se quedan de guarnición; desaparecen todas las tropas de
 *   la aldea, estén donde estén; se reinician academia y herrería; se cae el muro siempre
 *   y los edificios de tribu sólo si cambia la tribu; los oasis anexados quedan libres; el
 *   héroe muere si esa era su aldea natal; y los cupos de expansión que la aldea ya había
 *   gastado siguen gastados para el dueño nuevo.
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
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Hero.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
include "Ranking.php";
include "Logging.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";

global $database, $generator, $technology;
$generator = new GeneratorX();

$failures = array();
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        echo '[FALLA] '.$message.PHP_EOL;
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}
function q($sql) {
    global $database;
    $result = mysqli_query($database->connection, $sql);
    if($result === false) {
        fwrite(STDERR, 'SQL: '.mysqli_error($database->connection).PHP_EOL.$sql.PHP_EOL);
        exit(1);
    }
    return $result;
}
function row($sql) {
    $result = q($sql);
    return mysqli_fetch_assoc($result);
}
function scalar($sql) {
    $result = q($sql);
    $line = mysqli_fetch_row($result);
    return $line ? $line[0] : null;
}

$P = TB_PREFIX;

// --- El banco de pruebas -------------------------------------------------------------
//
// El esquema sale del CREATE real de cada tabla: si mañana una tabla gana una
// columna, esta prueba la hereda sola.
// Se tapan TODAS las tablas del mundo, no sólo las que la conquista toca: el bloque I
// resuelve un ataque de verdad con `sendunitsComplete()`, que escribe informes, rankings y
// media docena de tablas más. Una tabla sin tapar se escribiría en el mundo real y el
// shim `mysql_query()` no se queja, así que el descuido sería invisible.
// `config` queda afuera: sus constantes ya se leyeron al arrancar y una copia vacía sólo
// puede confundir a lo que la relea.
$tables = array();
$result = q("SHOW TABLES LIKE '".$P."%'");
while($line = mysqli_fetch_row($result)) {
    $name = substr($line[0], strlen($P));
    if($name === 'config') {
        continue;
    }
    $tables[] = $name;
}
foreach($tables as $table) {
    // MariaDB no acepta `CREATE TEMPORARY TABLE x LIKE x` (mismo nombre), asi que el
    // esquema se copia del CREATE real: la prueba hereda sola cualquier columna nueva.
    $create = row("SHOW CREATE TABLE {$P}{$table}");
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
}

define('U_ATT', 9101);   // atacante, romano
define('U_DEF', 9102);   // defensor, germano
define('U_THIRD', 9103); // un tercero, galo

define('V_ATT', 991001);   // aldea que ataca (palacio 20 -> 3 cupos)
define('V_ATT2', 991002);  // segunda aldea del atacante
define('V_TARGET', 991003); // la aldea a conquistar
define('V_DEF2', 991004);  // otra aldea del defensor (para que no sea la última)
define('V_DEF3', 991005);  // la aldea del defensor que fundó V_TARGET
define('V_THIRD', 991006); // aldea del tercero
define('O_OASIS', 991010); // oasis anexado por V_TARGET
define('W_FREE', 991020);  // casilla reservada por los colonos de V_TARGET

/** Deja el banco en el estado inicial: cada bloque arranca de cero. */
function resetWorld() {
    global $P, $tables;
    foreach($tables as $table) {
        q("DELETE FROM {$P}{$table}");
    }

    q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES "
        ."(".U_ATT.",'atacante',1,999999999,3,0),"
        ."(".U_DEF.",'defensor',2,0,3,0),"
        ."(".U_THIRD.",'tercero',3,0,3,0)");

    // Coordenadas: no se usan para la conquista pero sí para soltar oasis y colonos.
    $tiles = array(V_ATT, V_ATT2, V_TARGET, V_DEF2, V_DEF3, V_THIRD, O_OASIS, W_FREE);
    $values = array();
    $x = 1;
    foreach($tiles as $tile) {
        $oasis = $tile === O_OASIS ? 1 : 0;
        $values[] = "($tile,$x,1,".($oasis ? 1 : 3).",$oasis,1)";
        $x++;
    }
    q("INSERT INTO {$P}wdata (id,x,y,fieldtype,oasistype,occupied) VALUES ".implode(',', $values));

    $now = time();
    q("INSERT INTO {$P}vdata (wref,owner,capital,pop,cp,loyalty,loyaltyupdate,created,lastupdate,maxstore,maxcrop) VALUES "
        ."(".V_ATT.",".U_ATT.",1,100,10,100,$now,$now,$now,800,800),"
        ."(".V_ATT2.",".U_ATT.",0,100,10,100,$now,$now,$now,800,800),"
        ."(".V_TARGET.",".U_DEF.",0,100,10,100,$now,$now,$now,800,800),"
        ."(".V_DEF2.",".U_DEF.",1,100,10,100,$now,$now,$now,800,800),"
        ."(".V_DEF3.",".U_DEF.",0,100,10,100,$now,$now,$now,800,800),"
        ."(".V_THIRD.",".U_THIRD.",1,100,10,100,$now,$now,$now,800,800)");

    foreach(array(V_ATT, V_ATT2, V_TARGET, V_DEF2, V_DEF3, V_THIRD) as $village) {
        q("INSERT INTO {$P}fdata (vref) VALUES ($village)");
        q("INSERT INTO {$P}units (vref) VALUES ($village)");
    }
    // La aldea que ataca: palacio nivel 20 -> tres cupos de expansión.
    q("UPDATE {$P}fdata SET f19t = 26, f19 = 20 WHERE vref = ".V_ATT);
    // La aldea objetivo: edificio principal, y NADA de residencia/palacio.
    q("UPDATE {$P}fdata SET f20t = 15, f20 = 10 WHERE vref = ".V_TARGET);

    // Un ataque con un solo administrador, ya resuelto: es lo que queda por volver.
    q("INSERT INTO {$P}attacks (id,vref,t1,t9,attack_type) VALUES (77001,".V_ATT.",100,1,3)");
}

// =====================================================================================
section('A. Los requisitos para conquistar');
// =====================================================================================
resetWorld();

$eligible = $database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF);
check($eligible['status'] === 'eligible', 'una aldea sin residencia, sin capital y con cupo libre es conquistable');
check((int)$eligible['slot'] === 1, 'toma el primer cupo de expansión libre');
check((int)$eligible['loyalty'] === 100, 'informa la lealtad actual de la aldea');

check($database->getConquestEligibility(V_ATT, V_ATT2, U_ATT, U_ATT)['status'] === 'same_owner',
    'no se puede conquistar una aldea propia');

q("UPDATE {$P}vdata SET capital = 1 WHERE wref = ".V_TARGET);
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'capital',
    'no se puede conquistar una capital');
q("UPDATE {$P}vdata SET capital = 0 WHERE wref = ".V_TARGET);

q("DELETE FROM {$P}vdata WHERE wref IN (".V_DEF2.",".V_DEF3.")");
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'last_village',
    'no se puede conquistar la última aldea de un jugador');
resetWorld();

foreach(array(25 => 'residencia', 26 => 'palacio') as $type => $name) {
    q("UPDATE {$P}fdata SET f21t = $type, f21 = 1 WHERE vref = ".V_TARGET);
    check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'residence',
        "una $name en pie, aunque sea de nivel 1, bloquea la conquista");
    q("UPDATE {$P}fdata SET f21t = 0, f21 = 0 WHERE vref = ".V_TARGET);
}
// Y derribada del todo (nivel 0 y tipo 0, que es como la dejan catapultas y demolición)
// deja de bloquear.
q("UPDATE {$P}fdata SET f21t = 0, f21 = 0 WHERE vref = ".V_TARGET);
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'eligible',
    'con la residencia derribada la aldea vuelve a ser conquistable');

// Cupos de expansión: sin residencia ni palacio en la aldea que ataca no hay conquista,
// por más que sus tres columnas exp estén en cero.
q("UPDATE {$P}fdata SET f19t = 0, f19 = 0 WHERE vref = ".V_ATT);
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'no_slot',
    'sin residencia ni palacio la aldea que ataca no tiene cupo para conquistar');

// La tabla oficial de cupos: residencia 10/20 -> 1/2, palacio 10/15/20 -> 1/2/3.
$slotTable = array(
    array(25, 9, 0), array(25, 10, 1), array(25, 19, 1), array(25, 20, 2),
    array(26, 9, 0), array(26, 10, 1), array(26, 14, 1), array(26, 15, 2), array(26, 20, 3)
);
foreach($slotTable as $case) {
    list($type, $level, $expected) = $case;
    q("UPDATE {$P}fdata SET f19t = $type, f19 = $level WHERE vref = ".V_ATT);
    check((int)$database->getExpansionSlotLimit(V_ATT) === $expected,
        ($type === 25 ? 'residencia' : 'palacio')." nivel $level habilita $expected cupos");
}

// Con los cupos habilitados ocupados, tampoco.
q("UPDATE {$P}fdata SET f19t = 25, f19 = 10 WHERE vref = ".V_ATT);
q("UPDATE {$P}vdata SET exp1 = ".V_ATT2." WHERE wref = ".V_ATT);
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'no_slot',
    'una residencia 10 con su único cupo ocupado ya no puede conquistar');
q("UPDATE {$P}fdata SET f19 = 20 WHERE vref = ".V_ATT);
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_DEF)['status'] === 'eligible',
    'subir la residencia a 20 libera el segundo cupo');

// Y las carreras: si alguna de las dos aldeas cambió de dueño entre el envío y la llegada.
resetWorld();
check($database->getConquestEligibility(V_ATT, V_TARGET, U_THIRD, U_DEF)['status'] === 'source_changed',
    'si la aldea que ataca cambió de dueño no se conquista');
check($database->getConquestEligibility(V_ATT, V_TARGET, U_ATT, U_THIRD)['status'] === 'target_changed',
    'si la aldea objetivo ya cambió de dueño no se conquista');

// Los puntos de cultura son requisito, igual que para fundar.
$cultureAt = function($cp, $villages) {
    return travianCultureExpansionEligibility($cp, $villages, 0, CP)['eligible'];
};
check($cultureAt(0, 1) === false, 'sin puntos de cultura no alcanza para una aldea más');
check($cultureAt(99999999, 1) === true, 'con puntos de sobra sí');
$automationSource = file_get_contents($root.'/GameEngine/Automation.php');
check(preg_match('/if\(!\$cultureEligibility\[.eligible.\]\) \{\s*\$conquestStatus = .culture.;/', $automationSource) === 1,
    'la conquista rechaza por cultura como rechaza el colono');

// =====================================================================================
section('B. La lealtad y el administrador');
// =====================================================================================
resetWorld();

$partial = $database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 25);
check($partial['status'] === 'loyalty_reduced', 'un ataque que no llega a 0 sólo baja la lealtad');
check((int)$partial['new_loyalty'] === 75, 'baja exactamente lo que persuadió el administrador');
check((int)scalar("SELECT owner FROM {$P}vdata WHERE wref = ".V_TARGET) === U_DEF,
    'la aldea sigue siendo del defensor');
check((int)scalar("SELECT t9 FROM {$P}attacks WHERE id = 77001") === 1,
    'un administrador que sólo baja lealtad NO se gasta: vuelve a casa');
check((int)scalar("SELECT exp1 FROM {$P}vdata WHERE wref = ".V_ATT) === 0,
    'un ataque parcial no ocupa el cupo de expansión');
check((int)scalar("SELECT loyaltyupdate FROM {$P}vdata WHERE wref = ".V_TARGET) >= time() - 5,
    'el reloj de regeneración arranca en el momento del golpe');

$conquest = $database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 75);
check($conquest['status'] === 'conquered', 'llegar a 0 conquista la aldea');
check((int)$conquest['new_loyalty'] === 0 && (int)scalar("SELECT loyalty FROM {$P}vdata WHERE wref = ".V_TARGET) === 0,
    'la aldea recién conquistada queda en 0% de lealtad, como en el oficial');
check((int)scalar("SELECT owner FROM {$P}vdata WHERE wref = ".V_TARGET) === U_ATT,
    'y pasa a ser del atacante');
check((int)scalar("SELECT t9 FROM {$P}attacks WHERE id = 77001") === 0,
    'la conquista gasta un administrador');
check((int)scalar("SELECT exp1 FROM {$P}vdata WHERE wref = ".V_ATT) === V_TARGET,
    'y ocupa un cupo de expansión de la aldea que atacó');

// Sin administrador vivo en la fila del ataque no hay conquista posible.
resetWorld();
q("UPDATE {$P}attacks SET t9 = 0 WHERE id = 77001");
check($database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100)['status'] === 'no_chief',
    'sin administrador en el ataque la conquista no se concreta');
check((int)scalar("SELECT owner FROM {$P}vdata WHERE wref = ".V_TARGET) === U_DEF,
    'y la aldea no cambia de dueño');

// Tres administradores bajan tres tiradas, y sólo uno se gasta al concretar.
resetWorld();
q("UPDATE {$P}attacks SET t9 = 3 WHERE id = 77001");
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
check((int)scalar("SELECT t9 FROM {$P}attacks WHERE id = 77001") === 2,
    'de tres administradores desaparece sólo el que llevó la lealtad a 0');

// Los rangos por tribu y los modificadores (ver check_conquest_loyalty.php para el detalle).
check(Automation::administratorLoyaltyRange(1) === array(20, 30), 'el senador romano baja 20-30');
check(Automation::administratorLoyaltyRange(2) === array(20, 25), 'el jefe germano baja 20-25');
check(Automation::administratorLoyaltyRange(3) === array(20, 25), 'el cacique galo baja 20-25');

// Regeneración: 2 puntos cada 3 horas por nivel del edificio, y nada sin edificio.
check(abs(Automation::loyaltyRegenerationRate(20, 1) - (40 / 3 / 3600)) < 1e-9,
    'una residencia 20 regenera 13,33 puntos por hora');
check(abs(Automation::loyaltyRegenerationRate(1, 1) - (2 / 3 / 3600)) < 1e-9,
    'cada nivel vale 2 puntos cada 3 horas');
check(Automation::loyaltyRegenerationRate(0, 3) === 0.0,
    'sin residencia ni palacio no hay regeneración: la aldea tomada queda en 0');
check(abs(Automation::loyaltyRegenerationRate(10, 3) - (20 / 3 * 3 / 3600)) < 1e-9,
    'la velocidad del mundo multiplica la regeneración');

// =====================================================================================
section('C. Qué pierde la aldea al cambiar de dueño');
// =====================================================================================
resetWorld();

// Un mundo bien poblado alrededor de la aldea objetivo.
q("UPDATE {$P}fdata SET f21t = 32, f21 = 10, f22t = 35, f22 = 5, f23t = 36, f23 = 8,"
    ." f24t = 10, f24 = 12, f40t = 32, f40 = 15 WHERE vref = ".V_TARGET); // muro germano + cervecería + trampero
q("UPDATE {$P}units SET u11 = 500, u19 = 2, u99 = 40 WHERE vref = ".V_TARGET);
q("INSERT INTO {$P}tdata (vref,t12,t13,t14) VALUES (".V_TARGET.",1,1,1)");
q("INSERT INTO {$P}abdata (vref,a1,b1) VALUES (".V_TARGET.",5,3)");
q("INSERT INTO {$P}research (id,vref,tech,timestamp) VALUES (0,".V_TARGET.",'15',".(time()+600).")");
q("INSERT INTO {$P}bdata (id,wid,field,type,loopcon,timestamp,master,level) VALUES (0,".V_TARGET.",25,22,0,".(time()+600).",0,1)");
q("INSERT INTO {$P}training (id,vref,unit,amt,pop,timestamp,eachtime,timestamp2) VALUES (0,".V_TARGET.",11,50,1,".(time()+600).",10,".(time()+10).")");
q("INSERT INTO {$P}demolition (vref,buildnumber,lvl,timetofinish) VALUES (".V_TARGET.",24,11,".(time()+60).")");
q("INSERT INTO {$P}market (id,vref,gtype,gamt,wtype,wamt,accept,maxtime,alliance,merchant) VALUES (0,".V_TARGET.",1,500,2,500,0,0,0,1)");
q("INSERT INTO {$P}route (id,uid,wid,`from`,wood,clay,iron,crop,start,start_minute,deliveries,merchant,timestamp) VALUES (0,".U_DEF.",".V_DEF2.",".V_TARGET.",100,0,0,0,8,0,1,1,".time().")");
q("INSERT INTO {$P}farmlist (id,wref,owner,name) VALUES (501,".V_TARGET.",".U_DEF.",'granja')");
q("INSERT INTO {$P}raidlist (id,lid,towref,x,y,distance,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10) VALUES (0,501,".V_THIRD.",1,1,'1',10,0,0,0,0,0,0,0,0,0)");
q("INSERT INTO {$P}artefacts (id,vref,owner,type,size,conquered,name,`desc`,effect,img) VALUES (0,".V_TARGET.",".U_DEF.",1,1,0,'x','x','x','x')");
// Tropas de la aldea desperdigadas por el mundo.
q("INSERT INTO {$P}enforcement (id,u11,`from`,vref) VALUES (0,300,".V_TARGET.",".V_THIRD.")");
q("INSERT INTO {$P}enforcement (id,u11,`from`,vref) VALUES (0,100,".V_DEF2.",".V_TARGET.")"); // de otra aldea del defensor: NO es de esta aldea
q("INSERT INTO {$P}attacks (id,vref,t1,attack_type) VALUES (77010,".V_TARGET.",50,3)"); // ataque saliendo
q("INSERT INTO {$P}attacks (id,vref,t1,attack_type) VALUES (77011,".V_TARGET.",50,3)"); // volviendo a casa
q("INSERT INTO {$P}attacks (id,vref,t1,attack_type) VALUES (77012,".V_THIRD.",50,3)");  // ataque entrante de un tercero
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,endtime,proc) VALUES (0,3,".V_TARGET.",".V_THIRD.",77010,".(time()+600).",0)");
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,endtime,proc) VALUES (0,4,".V_THIRD.",".V_TARGET.",77011,".(time()+600).",0)");
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,endtime,proc) VALUES (0,3,".V_THIRD.",".V_TARGET.",77012,".(time()+600).",0)");
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,endtime,proc) VALUES (0,5,".V_TARGET.",".W_FREE.",0,".(time()+600).",0)");
// Una aldea del defensor había fundado ésta: el cupo tiene que quedarle libre.
q("UPDATE {$P}vdata SET exp1 = ".V_TARGET." WHERE wref = ".V_DEF3);
// Y ésta a su vez fundó otra: ese cupo lo hereda ocupado el conquistador.
q("UPDATE {$P}vdata SET exp1 = ".V_DEF2.", celebration = ".(time()+3600).", type = 2, starv = 500 WHERE wref = ".V_TARGET);

$conquest = $database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
check($conquest['status'] === 'conquered', 'la conquista del bloque C se concretó: '.$conquest['status']);

$fields = row("SELECT * FROM {$P}fdata WHERE vref = ".V_TARGET);
check((int)$fields['f40'] === 0 && (int)$fields['f40t'] === 0, 'el muro se cae siempre');
check((int)$fields['f22t'] === 0, 'la Cervecería germana no sobrevive a un conquistador romano');
check((int)$fields['f23t'] === 0, 'el Trampero galo tampoco sobreviviría (edificio de tribu)');
check((int)$fields['f21t'] === 0, 'un segundo muro fuera del campo 40 tampoco queda en pie');
check((int)$fields['f24t'] === 10 && (int)$fields['f24'] === 12,
    'los edificios que cualquier tribu puede tener quedan intactos, con su nivel');

$tdata = row("SELECT * FROM {$P}tdata WHERE vref = ".V_TARGET);
check((int)$tdata['t12'] === 0 && (int)$tdata['t13'] === 0 && (int)$tdata['t14'] === 0,
    'la investigación de la academia se reinicia');
$abdata = row("SELECT * FROM {$P}abdata WHERE vref = ".V_TARGET);
check((int)$abdata['a1'] === 0 && (int)$abdata['b1'] === 0, 'las mejoras de la herrería se reinician');
check((int)scalar("SELECT COUNT(*) FROM {$P}research WHERE vref = ".V_TARGET) === 0,
    'la investigación en curso se cancela');

check((int)scalar("SELECT COUNT(*) FROM {$P}bdata WHERE wid = ".V_TARGET) === 0, 'la cola de construcción se vacía');
check((int)scalar("SELECT COUNT(*) FROM {$P}training WHERE vref = ".V_TARGET) === 0, 'la cola de entrenamiento se vacía');
check((int)scalar("SELECT COUNT(*) FROM {$P}demolition WHERE vref = ".V_TARGET) === 0, 'la demolición en curso se cancela');
check((int)scalar("SELECT COUNT(*) FROM {$P}market WHERE vref = ".V_TARGET) === 0, 'las ofertas del mercado se retiran');
check((int)scalar("SELECT COUNT(*) FROM {$P}route WHERE `from` = ".V_TARGET) === 0, 'las rutas comerciales se borran');
check((int)scalar("SELECT COUNT(*) FROM {$P}farmlist WHERE wref = ".V_TARGET) === 0, 'las listas de granjeo no se heredan');
check((int)scalar("SELECT COUNT(*) FROM {$P}raidlist WHERE lid = 501") === 0, 'ni los objetivos que tenían cargados');

$units = row("SELECT * FROM {$P}units WHERE vref = ".V_TARGET);
check((int)$units['u11'] === 0 && (int)$units['u19'] === 0, 'la guarnición que quedaba adentro se disuelve');

check((int)scalar("SELECT COUNT(*) FROM {$P}enforcement WHERE `from` = ".V_TARGET) === 0,
    'las tropas de la aldea que reforzaban en otro lado desaparecen');
check((int)scalar("SELECT COUNT(*) FROM {$P}enforcement WHERE vref = ".V_TARGET." AND `from` = ".V_DEF2) === 1,
    'pero el refuerzo que otra aldea puso acá no es de esta aldea y no se toca');

check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 3 AND `from` = ".V_TARGET) === 0,
    'el ataque que había salido de la aldea desaparece');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE id = 77010") === 0, 'y su fila de tropas con él');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 4 AND `to` = ".V_TARGET) === 0,
    'lo que volvía a casa tampoco llega');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE id = 77011") === 0, 'y su fila de tropas con él');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 3 AND `to` = ".V_TARGET) === 1
    && (int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE id = 77012") === 1,
    'los ataques que vienen en camino hacia la aldea siguen su curso: ahora le llegan al dueño nuevo');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 5 AND `from` = ".V_TARGET) === 0,
    'los colonos en camino se pierden con la aldea');
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = ".W_FREE) === 0,
    'y la casilla que tenían reservada queda libre otra vez');

check((int)scalar("SELECT exp1 FROM {$P}vdata WHERE wref = ".V_DEF3) === 0,
    'el cupo de expansión con el que el defensor fundó esta aldea le queda libre');
check((int)scalar("SELECT exp1 FROM {$P}vdata WHERE wref = ".V_TARGET) === V_DEF2,
    'los cupos que la aldea ya había gastado los hereda ocupados el conquistador (regla oficial)');

check((int)scalar("SELECT celebration FROM {$P}vdata WHERE wref = ".V_TARGET) === 0,
    'la celebración en curso se cancela');
check((int)scalar("SELECT starv FROM {$P}vdata WHERE wref = ".V_TARGET) === 0,
    'la deuda de cereal del dueño anterior no se hereda');
check((int)scalar("SELECT created FROM {$P}vdata WHERE wref = ".V_TARGET) >= time() - 5,
    'para el conquistador la aldea nace hoy');
check((int)scalar("SELECT owner FROM {$P}artefacts WHERE vref = ".V_TARGET) === U_ATT,
    'el artefacto que guardaba la aldea cambia de dueño con ella y se queda donde está');
// Y su reloj vuelve a cero: conquistar la aldea es una captura, así que el artefacto
// arranca de nuevo el retardo de activación en vez de llegar activo al conquistador.
check((int)scalar("SELECT conquered FROM {$P}artefacts WHERE vref = ".V_TARGET) > 0,
    'la conquista reinicia la fecha de captura del artefacto');

// =====================================================================================
section('D. Misma tribu: los edificios de tribu sobreviven');
// =====================================================================================
resetWorld();
q("UPDATE {$P}users SET tribe = 2 WHERE id = ".U_ATT); // el atacante también es germano
q("UPDATE {$P}fdata SET f22t = 35, f22 = 5, f40t = 32, f40 = 15 WHERE vref = ".V_TARGET);
$conquest = $database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
check($conquest['status'] === 'conquered', 'la conquista entre germanos se concretó');
$fields = row("SELECT * FROM {$P}fdata WHERE vref = ".V_TARGET);
check((int)$fields['f22t'] === 35 && (int)$fields['f22'] === 5,
    'un germano que conquista a otro germano conserva la Cervecería');
check((int)$fields['f40'] === 0 && (int)$fields['f40t'] === 0,
    'pero el muro se cae igual, aunque las dos tribus sean la misma');

// La lista de edificios de tribu es una sola y la comparten Building.php y la conquista.
check(buildingTribeLock(35) === array(2) && buildingTribeLock(36) === array(3)
    && buildingTribeLock(41) === array(1) && buildingTribeLock(10) === null,
    'buildingTribeLock() nombra Cervecería, Trampero y Abrevadero y deja libre lo demás');
check(tribeCanBuild(31, 1) && !tribeCanBuild(31, 2) && tribeCanBuild(33, 3) && !tribeCanBuild(33, 1),
    'cada muro sigue siendo de su tribu');

// =====================================================================================
section('E. Conquistar una aldea natar');
// =====================================================================================
resetWorld();
$natars = natarsAccountId();
q("INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES ($natars,'Natars',5,0,3,0)");
q("UPDATE {$P}vdata SET owner = $natars, npckind = ".NPC_KIND_LIVING.", npcupdate = ".time()." WHERE wref = ".V_TARGET);
q("INSERT INTO {$P}vdata (wref,owner,capital,loyalty,created,lastupdate) VALUES (991007,$natars,1,100,".time().",".time().")");
q("UPDATE {$P}fdata SET f40t = 31, f40 = 12 WHERE vref = ".V_TARGET); // los natares llevan Muralla
q("UPDATE {$P}units SET u42 = 400, u45 = 120 WHERE vref = ".V_TARGET);

$conquest = $database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, $natars, 77001, 100);
check($conquest['status'] === 'conquered', 'una aldea natar se puede conquistar con administradores: '.$conquest['status']);
$conquered = $database->getVillage(V_TARGET);
check((int)$conquered['owner'] === U_ATT, 'y pasa a ser del jugador');
check(!isLivingNpcVillage($conquered) && !isStaticNpcVillage($conquered),
    'deja de ser NPC en la misma escritura, así que paga manutención y puede pasar hambre');
$units = row("SELECT * FROM {$P}units WHERE vref = ".V_TARGET);
check((int)$units['u42'] === 0 && (int)$units['u45'] === 0,
    'la guarnición natar se disuelve: nadie hereda tropas de una tribu que no puede reentrenar');
check((int)scalar("SELECT f40 FROM {$P}fdata WHERE vref = ".V_TARGET) === 0,
    'y la Muralla natar se cae como cualquier otro muro');

// =====================================================================================
section('F. Lo que hace el motor después de la conquista');
// =====================================================================================
resetWorld();

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$invoke = function ($name, $args) use ($reflection, $automation) {
    $method = $reflection->getMethod($name);
    $method->setAccessible(true);
    return $method->invokeArgs($automation, $args);
};

// F.1 La población y la cultura se recuentan desde fdata.
q("UPDATE {$P}fdata SET f1t = 1, f1 = 5, f24t = 10, f24 = 5, f40t = 32, f40 = 10 WHERE vref = ".V_TARGET);
q("UPDATE {$P}vdata SET pop = 9999, cp = 9999 WHERE wref = ".V_TARGET);
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
$invoke('completeVillageConquest', array(V_TARGET, U_ATT, U_DEF));
$expectedPop = $automation->recountPop(V_TARGET);
check((int)scalar("SELECT pop FROM {$P}vdata WHERE wref = ".V_TARGET) === (int)$expectedPop && (int)$expectedPop < 9999,
    'la población se recuenta después de derribar el muro (si no, la aldea come cereal por edificios que no existen)');
check((int)scalar("SELECT cp FROM {$P}vdata WHERE wref = ".V_TARGET) < 9999,
    'y los puntos de cultura también');

// F.2 Los oasis anexados quedan libres, no cambian de dueño.
resetWorld();
q("INSERT INTO {$P}odata (wref,type,conqured,owner,loyalty,lastupdated,lastupdated2,name) VALUES "
    ."(".O_OASIS.",1,".V_TARGET.",".U_DEF.",100,".time().",".time().",'oasis')");
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
$invoke('completeVillageConquest', array(V_TARGET, U_ATT, U_DEF));
$oasis = row("SELECT * FROM {$P}odata WHERE wref = ".O_OASIS);
check((int)$oasis['conqured'] === 0, 'el oasis anexado queda libre (oficial), no lo hereda el conquistador');
check((int)$oasis['owner'] !== U_ATT, 'y no pasa a nombre del conquistador');

// F.3 El héroe muere si la aldea tomada era su aldea natal.
resetWorld();
q("INSERT INTO {$P}hero (heroid,uid,wref,home,level,speed,dead,health) VALUES (0,".U_DEF.",".V_DEF2.",".V_TARGET.",5,7,0,100)");
q("UPDATE {$P}units SET hero = 1 WHERE vref = ".V_DEF2);
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
$invoke('completeVillageConquest', array(V_TARGET, U_ATT, U_DEF));
$hero = row("SELECT * FROM {$P}hero WHERE uid = ".U_DEF);
check((int)$hero['dead'] === 1 && (float)$hero['health'] == 0,
    'perder la aldea natal mata al héroe, como en el oficial');
check((int)$hero['home'] !== V_TARGET && (int)$hero['home'] > 0,
    'y le reasigna una aldea natal propia, para que se lo pueda revivir');
check((int)scalar("SELECT hero FROM {$P}units WHERE vref = ".V_DEF2) === 0,
    'el héroe muerto deja de estar en la aldea donde estaba');
check((int)scalar("SELECT COUNT(*) FROM {$P}mdata WHERE target = ".U_DEF) === 1,
    'y al jugador le llega un mensaje: un héroe que muere en silencio es un héroe perdido');

// Si la natal era otra, el héroe no se toca.
resetWorld();
q("INSERT INTO {$P}hero (heroid,uid,wref,home,level,speed,dead,health) VALUES (0,".U_DEF.",".V_DEF2.",".V_DEF2.",5,7,0,100)");
q("UPDATE {$P}units SET hero = 1 WHERE vref = ".V_DEF2);
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
$invoke('completeVillageConquest', array(V_TARGET, U_ATT, U_DEF));
$hero = row("SELECT * FROM {$P}hero WHERE uid = ".U_DEF);
check((int)$hero['dead'] === 0 && (int)scalar("SELECT hero FROM {$P}units WHERE vref = ".V_DEF2) === 1,
    'si la aldea tomada no era la natal, el héroe sigue vivo donde estaba');

// F.4 Las tropas que acompañaron al administrador se quedan de guarnición.
resetWorld();
q("UPDATE {$P}attacks SET t1 = 250, t2 = 30, t9 = 0, t11 = 1 WHERE id = 77001"); // lo que sobrevivió, sin el jefe gastado
q("INSERT INTO {$P}hero (heroid,uid,wref,home,level,speed,dead,health) VALUES (0,".U_ATT.",".V_ATT.",".V_ATT.",5,7,0,100)");
$database->applyConquestLoyalty(V_ATT, V_TARGET, U_ATT, U_DEF, 77001, 100);
$invoke('stationConqueringArmy', array(array('ref' => 77001), V_ATT, V_TARGET, 1));
$garrison = row("SELECT * FROM {$P}enforcement WHERE `from` = ".V_ATT." AND vref = ".V_TARGET);
check(is_array($garrison), 'el ejército que conquistó se queda estacionado en la aldea tomada');
check(is_array($garrison) && (int)$garrison['u1'] === 250 && (int)$garrison['u2'] === 30,
    'con las unidades que sobrevivieron, en los huecos absolutos de su tribu');
check((int)scalar("SELECT hero FROM {$P}units WHERE vref = ".V_TARGET) === 1
    && (int)scalar("SELECT wref FROM {$P}hero WHERE uid = ".U_ATT) === V_TARGET,
    'y el héroe se queda con ellas');

// Sumar sobre un refuerzo que ya existía no crea una segunda fila.
resetWorld();
q("UPDATE {$P}attacks SET t1 = 100, t9 = 0, t11 = 0 WHERE id = 77001");
q("INSERT INTO {$P}enforcement (id,u1,`from`,vref) VALUES (0,7,".V_ATT.",".V_TARGET.")");
$invoke('stationConqueringArmy', array(array('ref' => 77001), V_ATT, V_TARGET, 1));
check((int)scalar("SELECT COUNT(*) FROM {$P}enforcement WHERE `from` = ".V_ATT." AND vref = ".V_TARGET) === 1
    && (int)scalar("SELECT u1 FROM {$P}enforcement WHERE `from` = ".V_ATT." AND vref = ".V_TARGET) === 107,
    'si ya había un refuerzo de esa aldea, se suma en la misma fila');

// =====================================================================================
section('G. Las reglas que viven en el camino del ataque');
// =====================================================================================

// Sólo el ataque normal conquista: en un asalto el administrador no habla con nadie.
check(preg_match('/\$survivingChiefs = max\(0, \(int\)\$data\[.t9.\] - \(int\)\$dead9 - \(int\)\$traped9\);/', $automationSource) === 1,
    'sólo cuentan los administradores que sobrevivieron y no quedaron en una trampa');
check(preg_match('/if\(!\$catapultDestroyedVillage && \(int\)\$type === 3 && \$survivingChiefs > 0\)/', $automationSource) === 1,
    'la conquista sólo corre en ataque normal (type 3) y si la aldea no quedó arrasada');
check(strpos($automationSource, '$conquestGarrisonStays = false;') !== false,
    'la marca de "las tropas se quedan" se reinicia en cada ataque del barrido');
check(preg_match('/\} elseif\(\$conquestGarrisonStays\) \{.*?stationConqueringArmy.*?removeAttack/s', $automationSource) === 1,
    'al conquistar no se crea el movimiento de regreso: las tropas se quedan');
check(preg_match('/completeVillageConquest\(\$data\[.to.\], \$attackerOwner, \$defenderOwner\)/', $automationSource) === 1,
    'la conquista dispara la limpieza del motor');
check(strpos($automationSource, 'reassignHeroHomeVillage($database, $defenderOwner)') !== false,
    'y la reasignación de la aldea natal del héroe sigue en pie');

// El bloque del héroe no puede pisar el aviso de la conquista.
check(preg_match('/\} elseif\(!\$conquestGarrisonStays\) \{\s*\/\/ Robar un artefacto\./s', $automationSource) === 1,
    'reclamar el artefacto con el héroe no corre sobre una aldea recién conquistada');

// El bloqueo por una conquista simultánea y el cerrojo de expansión.
$dbSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(strpos($dbSource, "'status' => 'busy'") !== false,
    'dos conquistas simultáneas sobre la misma aldea no se pisan');
check(preg_match('/\$settlementLockAcquired = \$attackerOwner > 0\s*&& \$database->acquireSettlementLock\(\$attackerOwner, 5\)/', $automationSource) === 1,
    'la conquista toma el mismo cerrojo de expansión que el colono');
check(preg_match('/destination\.npckind = " \. NPC_KIND_PLAYER/', $dbSource) === 1,
    'una aldea NPC conquistada deja de serlo en la misma escritura que cambia de dueño');

// =====================================================================================
section('H. El administrador de cada tribu');
// =====================================================================================
//
// Los números oficiales (support.travian.com, tablas de unidades por tribu). El que más
// costó: el galo es "un 25% más rápido que los otros", o sea 5 contra 4, y acá estaba al
// revés —cacique 4, senador y jefe 5—, que le quitaba a la tribu galo lo único que su
// administrador tiene de mejor y le regalaba velocidad a las otras dos.
$administrators = array(
    9  => array('nombre' => 'senador romano',  'atk' => 50, 'di' => 40, 'dc' => 30,
                'wood' => 30750, 'clay' => 27200, 'iron' => 45000, 'crop' => 37500,
                'pop' => 5, 'speed' => 4, 'time' => 90700),
    19 => array('nombre' => 'jefe germano',    'atk' => 40, 'di' => 60, 'dc' => 40,
                'wood' => 35500, 'clay' => 26600, 'iron' => 25000, 'crop' => 27200,
                'pop' => 4, 'speed' => 4, 'time' => 70500),
    29 => array('nombre' => 'cacique galo',    'atk' => 40, 'di' => 50, 'dc' => 50,
                'wood' => 30750, 'clay' => 45400, 'iron' => 31000, 'crop' => 37500,
                'pop' => 4, 'speed' => 5, 'time' => 90700)
);
foreach($administrators as $unit => $expected) {
    $actual = $GLOBALS['u'.$unit];
    $name = $expected['nombre'];
    unset($expected['nombre']);
    foreach($expected as $field => $value) {
        check((int)$actual[$field] === $value,
            "el $name tiene $field = $value (tiene ".$actual[$field].")");
    }
}
check((int)$GLOBALS['u29']['speed'] > (int)$GLOBALS['u9']['speed']
    && (int)$GLOBALS['u29']['speed'] > (int)$GLOBALS['u19']['speed'],
    'el cacique galo es el más rápido de los tres administradores, como dice el oficial');

// Entrenarlo: academia 20 y plaza de reuniones (10 para romano y galo, 5 para el germano),
// residencia/palacio desde nivel 10, y un cupo de expansión libre en esa misma aldea.
$technologySource = file_get_contents($root.'/GameEngine/Technology.php');
check(preg_match('/case 9:\s*case 29:\s*if\(\$building->getTypeLevel\(22\) >= 20 && \$building->getTypeLevel\(16\) >= 10\)/', $technologySource) === 1,
    'senador y cacique exigen academia 20 y plaza de reuniones 10');
check(preg_match('/case 19:\s*case 39:\s*case 49:\s*if\(\$building->getTypeLevel\(22\) >= 20 && \$building->getTypeLevel\(16\) >= 5\)/', $technologySource) === 1,
    'el jefe germano exige academia 20 y plaza de reuniones 5');
check(preg_match('/if\(\$isExpansionUnit && \(\$great \|\| !in_array\(\$fieldType,array\(25,26\),true\) \|\| \$fieldLevel < 10\)\)/', $technologySource) === 1,
    'sólo se entrena en residencia o palacio de nivel 10 o más');
check(strpos($technologySource, '$available = $unit%10 == 0 ? (int)$slots[\'settlers\'] : (int)$slots[\'chiefs\'];') !== false,
    'y contra los cupos de expansión libres de esa aldea');
$dbSource2 = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
check(strpos($dbSource2, "if(!\$technology->getTech((\$session->tribe - 1) * 10 + 9)) {") !== false,
    'sin la investigación de la academia no hay administrador que entrenar');

// =====================================================================================
section('I. Una conquista de verdad, resuelta por el motor');
// =====================================================================================
//
// Los bloques anteriores prueban las piezas; éste resuelve un ataque completo con
// `sendunitsComplete()`, que es el único camino por el que una conquista ocurre en el
// juego. Sirve para que el cableado no se rompa en silencio: la conquista podría estar
// perfecta y no dispararse nunca.
resetWorld();

$village = $database->getVillage(V_ATT);
$session = new stdClass();
$session->uid = U_ATT;
$session->tribe = 1;

// El defensor no tiene nada adentro: el ataque se gana sin bajas y con eso alcanza, que
// la batalla en sí ya la cubren otros checkers.
q("UPDATE {$P}vdata SET wood = 1000, clay = 1000, iron = 1000, crop = 1000 WHERE wref = ".V_TARGET);
// Cinco senadores: entre 100 y 150 puntos de persuasión, así que la lealtad llega a 0 en
// una sola oleada.
q("UPDATE {$P}attacks SET t1 = 120, t9 = 5 WHERE id = 77001");
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,ref2,`data`,endtime,proc) "
    ."VALUES (0,3,".V_ATT.",".V_TARGET.",77001,0,'0',".(time() - 30).",0)");

@unlink($root.'/GameEngine/Prevention/sendunits.txt');
$resolve = $reflection->getMethod('sendunitsComplete');
$resolve->setAccessible(true);
$resolve->invoke($automation);

check((int)scalar("SELECT owner FROM {$P}vdata WHERE wref = ".V_TARGET) === U_ATT,
    'el motor conquista la aldea al resolver el ataque');
check((int)scalar("SELECT loyalty FROM {$P}vdata WHERE wref = ".V_TARGET) === 0,
    'y la deja en 0% de lealtad');
check((int)scalar("SELECT exp1 FROM {$P}vdata WHERE wref = ".V_ATT) === V_TARGET,
    'ocupando un cupo de expansión de la aldea que atacó');

$garrison = row("SELECT * FROM {$P}enforcement WHERE `from` = ".V_ATT." AND vref = ".V_TARGET);
check(is_array($garrison) && (int)$garrison['u1'] === 120,
    'las tropas que acompañaron al administrador se quedan de guarnición');
check(is_array($garrison) && (int)$garrison['u9'] === 4,
    'y con ellas los cuatro administradores que no se gastaron');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 4 AND `to` = ".V_ATT) === 0,
    'no vuelve nadie a la aldea de origen');
check((int)scalar("SELECT COUNT(*) FROM {$P}attacks WHERE id = 77001") === 0,
    'y la fila de tropas del ataque se borra: ya no la referencia ningún movimiento');
check((int)scalar("SELECT proc FROM {$P}movement WHERE ref = 77001") === 1,
    'el movimiento queda marcado como resuelto');
check((int)scalar("SELECT COUNT(*) FROM {$P}ndata WHERE uid = ".U_ATT) >= 1
    && (int)scalar("SELECT COUNT(*) FROM {$P}ndata WHERE uid = ".U_DEF) >= 1,
    'los dos jugadores reciben su informe');
$report = scalar("SELECT `data` FROM {$P}ndata WHERE uid = ".U_DEF." ORDER BY id DESC LIMIT 1");
check(is_string($report) && strpos($report, 'La aldea fue conquistada') !== false,
    'y el aviso de la conquista está redactado para los dos lados, no sólo para el que ganó');
check(is_string($report) && strpos($report, 'Conquistaste') === false,
    'el que perdió la aldea no lee "¡Conquistaste la aldea!" en su propio informe');

// Un asalto (type 4) con los mismos jefes no conquista nada: el administrador no llega a
// hablar con nadie.
resetWorld();
$village = $database->getVillage(V_ATT);
q("UPDATE {$P}vdata SET wood = 1000, clay = 1000, iron = 1000, crop = 1000 WHERE wref = ".V_TARGET);
q("UPDATE {$P}attacks SET t1 = 120, t9 = 5, attack_type = 4 WHERE id = 77001");
q("INSERT INTO {$P}movement (moveid,sort_type,`from`,`to`,ref,ref2,`data`,endtime,proc) "
    ."VALUES (0,3,".V_ATT.",".V_TARGET.",77001,0,'0',".(time() - 30).",0)");
@unlink($root.'/GameEngine/Prevention/sendunits.txt');
$resolve->invoke($automation);
check((int)scalar("SELECT owner FROM {$P}vdata WHERE wref = ".V_TARGET) === U_DEF,
    'un asalto con administradores no conquista: sólo el ataque normal cuenta');
check((int)scalar("SELECT loyalty FROM {$P}vdata WHERE wref = ".V_TARGET) === 100,
    'y ni siquiera le baja la lealtad');
check((int)scalar("SELECT COUNT(*) FROM {$P}movement WHERE sort_type = 4 AND `to` = ".V_ATT) === 1,
    'las tropas del asalto vuelven a casa como siempre');

echo PHP_EOL;
if($failures) {
    fwrite(STDERR, 'Fallaron '.count($failures).' de '.$checks." comprobaciones de conquista.\n");
    exit(1);
}
echo 'Conquista de aldeas: OK ('.$checks." comprobaciones)\n";
