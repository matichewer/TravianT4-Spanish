<?php
/** Abandono real sobre tablas temporales: no modifica el mundo. */
if(PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$root = dirname(__DIR__);
chdir($root);
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
date_default_timezone_set('America/Argentina/Buenos_Aires');
error_reporting(E_ALL);
$_SESSION = array();
require 'config/connection.php';
require 'config/config.php';
require 'Database.php';
require 'Data/buidata.php';
require 'Data/resdata.php';
require 'Data/unitdata.php';
require 'GameEngine/ArtefactAbandonment.php';

class AbandonmentCheckDatabase extends mysqli_DB {
    public $failProvision = false;
    public function __construct($connection) { $this->connection = $connection; }
    public function query($sql) {
        if($this->failProvision && strpos($sql, 'UPDATE '.TB_PREFIX.'units SET') === 0) {
            $this->failProvision = false;
            // Un error SQL real, a mitad del aprovisionamiento MyISAM.
            return parent::query('UPDATE '.TB_PREFIX.'units SET nonexistent_abandon_test_column = 1');
        }
        return parent::query($sql);
    }
}
$database = new AbandonmentCheckDatabase($database->connection);
$checks = 0;
$failures = 0;
function check($ok, $message) {
    global $checks, $failures;
    $checks++;
    if(!$ok) { $failures++; echo '[FALLA] '.$message.PHP_EOL; }
}
function q($sql) {
    global $database;
    $r = mysqli_query($database->connection, $sql);
    if($r === false) { throw new RuntimeException(mysqli_error($database->connection)); }
    return $r;
}
function scalar($sql) { return mysqli_fetch_row(q($sql))[0]; }
$P = TB_PREFIX;
$tables = array('users', 'vdata', 'wdata', 'fdata', 'units', 'tdata', 'abdata', 'artefacts', 'enforcement', 'hero', 'odata');
foreach($tables as $table) {
    $create = mysqli_fetch_assoc(q('SHOW CREATE TABLE '.$P.$table));
    q(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
}
q("INSERT INTO {$P}users (id, username, tribe) VALUES (".UID_NATARS.", 'Natars', 5), (9601, 'owner', 1), (9602, 'other', 2)");
$now = time();
$source = 800001;
$free = 800002;
q("INSERT INTO {$P}wdata (id, fieldtype, oasistype, x, y, occupied) VALUES
    ($source,3,0,0,0,1), ($free,3,0,30,0,0), (800003,3,1,30,1,0), (800004,3,0,30,2,1), (800005,0,0,30,3,0)");
q("INSERT INTO {$P}vdata (wref, owner, name, created, lastupdate) VALUES ($source,9601,'Original',$now,$now)");
$database->addResourceFields($source, 3);
$database->addUnits($source);
$database->addArtefact($source, 9601, ARTEFACT_DIET, ARTEFACT_SIZE_SMALL);
$id = (int)mysqli_insert_id($database->connection);
q("UPDATE {$P}artefacts SET conquered = 1 WHERE id = $id");
$original = $database->getArtefactDetails($id);
$originalVillage = $database->getVillage($source);
$originalFields = $database->getResourceLevel($source);
$session = (object)array('uid' => 9601, 'mchecker' => 'abandon-session-token', 'is_sitter' => 0);
$post = array('action' => 'abandonArtefact', 'artefact_id' => (string)$id, 'confirm' => '1', 'c' => $session->mchecker,
    'vref' => (string)$source, 'conquered' => '1');

foreach(array('GET', 'HEAD') as $method) {
    check(artefactAbandonRequest($database, $session, $post, $method)['status'] === 'invalid_request', $method.' no escribe');
}
foreach(array(array('confirm' => '0'), array('c' => 'wrong'), array('c' => array('bad')),
    array('artefact_id' => array(1)), array('artefact_id' => '1 OR 1=1'), array('action' => 'other')) as $override) {
    check(artefactAbandonRequest($database, $session, array_replace($post, $override), 'POST')['status'] === 'invalid_request', 'Rechaza entrada inválida');
}
$missing = $post;
unset($missing['confirm']);
check(artefactAbandonRequest($database, $session, $missing, 'POST')['status'] === 'invalid_request', 'Confirmación obligatoria');
$session->is_sitter = 1;
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'invalid_request', 'Representante bloqueado');
$session->is_sitter = 0;
$session->uid = 9602;
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'not_owned', 'Otro jugador bloqueado');
$session->uid = 9601;
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata") === 1, 'Rechazos no crean aldeas');
check($database->getArtefactDetails($id) === $original, 'Rechazos conservan artefacto');

q("UPDATE {$P}wdata SET occupied = 1 WHERE id = $free");
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'no_space', 'Mapa lleno conserva artefacto');
q("UPDATE {$P}wdata SET occupied = 0 WHERE id = $free");
// Una fila huérfana no se puede pisar ni borrar con la compensación.
q("INSERT INTO {$P}tdata (vref) VALUES ($free)");
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'no_space', 'No pisa datos huérfanos');
check((int)scalar("SELECT COUNT(*) FROM {$P}tdata WHERE vref = $free") === 1, 'Conserva fila preexistente');
q("DELETE FROM {$P}tdata WHERE vref = $free");

$database->failProvision = true;
$mode = (new mysqli_driver())->report_mode;
$failed = artefactAbandonRequest($database, $session, $post, 'POST');
check($failed['status'] === 'unavailable', 'Error SQL no anuncia éxito');
check((new mysqli_driver())->report_mode === $mode, 'Restaura modo SQL después del error');
check($database->getArtefactDetails($id) === $original, 'Error conserva identidad y dueño');
foreach(array('vdata' => 'wref', 'fdata' => 'vref', 'units' => 'vref', 'tdata' => 'vref', 'abdata' => 'vref') as $table => $key) {
    check((int)scalar("SELECT COUNT(*) FROM {$P}{$table} WHERE $key = $free") === 0, 'Limpia '.$table.' después del fallo');
}
check((int)scalar("SELECT occupied FROM {$P}wdata WHERE id = $free") === 0, 'Libera casilla tras fallo');
check($database->getArtefactEffectValue($source, 9601, ARTEFACT_DIET) < 1, 'Efecto activo antes de abandonar');
$accrued = false;
$result = artefactAbandonRequest($database, $session, $post, 'POST', function($owner, $until) use (&$accrued, $database, $source, $now) {
    $accrued = $owner === 9601 && $until >= $now
        && $database->getArtefactEffectValue($source, $owner, ARTEFACT_DIET) < 1;
});
check($accrued, 'Acredita recursos con el efecto anterior antes de trasladar');
check($result['status'] === 'abandoned', 'Abandono confirmado funciona');
check(isset($result['vref']) && $result['vref'] === $free, 'Elige únicamente la casilla libre apta');
$moved = $database->getArtefactDetails($id);
check((int)$moved['owner'] === UID_NATARS && (int)$moved['vref'] === $free, 'Mismo artefacto en aldea natar');
foreach(array('id','type','size','name','desc','effect','img') as $key) {
    check($moved[$key] === $original[$key], 'Conserva '.$key);
}
check((int)$moved['conquered'] >= $now, 'Reinicia reloj');
check((float)$database->getArtefactEffectValue($source, 9601, ARTEFACT_DIET) === 1.0, 'Invalida caché y quita efecto inmediatamente');
check(!$database->getOwnArtefactInfo($source), 'Tesoro de origen vacío');
check($database->getVillage($source) === $originalVillage && $database->getResourceLevel($source) === $originalFields, 'Aldea original intacta');
$natar = $database->getVillage($free);
check(isStaticNpcVillage($natar) && (int)$natar['owner'] === UID_NATARS, 'NPC estático: no pierde defensores por hambre');
check((int)$natar['maxcrop'] > 800 && (int)$natar['pop'] > 0, 'Aldea aprovisionada con población y almacenamiento');
$fields = $database->getResourceLevel($free);
$config = artefactReleaseDefaults();
check((int)$fields['f22t'] === 27 && (int)$fields['f22'] === $config['treasury'], 'Tesoro según generador');
$garrison = $database->getUnit($free);
foreach(artefactAbandonVillagePlan($original, 0)['garrison'] as $unit => $amount) {
    check((int)$garrison['u'.$unit] === $amount, 'Defensa derivada u'.$unit);
}
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'not_owned', 'Repetir POST no vuelve a abandonar');
check((int)scalar("SELECT COUNT(*) FROM {$P}vdata") === 2 && (int)scalar("SELECT COUNT(*) FROM {$P}artefacts") === 1, 'Sin aldeas ni artefactos duplicados');

// La captura normal sigue funcionando y reinicia su retardo normal.
check($database->claimArtefact($source, $free, 9601, time()), 'Se puede volver a capturar');
check(!artefactIsMature($database->getArtefactDetails($id)), 'Recaptura espera activación');
check(artefactAbandonRequest($database, $session, $post, 'POST')['status'] === 'not_owned', 'Formulario anterior a la recaptura no puede abandonar');
$post['conquered'] = $database->getArtefactDetails($id)['conquered'];
// Fallback al rincón del mapa y plano con tamaño legacy grande.
q("INSERT INTO {$P}wdata (id,fieldtype,oasistype,x,y,occupied) VALUES (800006,3,0,".WORLD_MAX.','.WORLD_MAX.",0)");
q("UPDATE {$P}artefacts SET type = ".ARTEFACT_PLAN.', size = '.ARTEFACT_SIZE_LARGE." WHERE id = $id");
$planResult = artefactAbandonRequest($database, $session, $post, 'POST');
check($planResult['status'] === 'abandoned' && $planResult['vref'] === 800006, 'Plano vuelve al mapa aunque sólo quede una esquina');
check((int)$database->getArtefactDetails($id)['size'] === ARTEFACT_SIZE_LARGE, 'Conserva tamaño legacy del plano');
check((int)scalar("SELECT COUNT(*) FROM {$P}artefacts") === 1, 'Plano no duplicado');

// Renderiza la misma ficha que incluye build.php, en los dos estados.
class AbandonmentCheckGenerator { public function getMapCheck($id) { return 'map-check'; } }
$generator = new AbandonmentCheckGenerator();
$_GET['show'] = (string)$id;
set_include_path($root.'/Templates/Build'.PATH_SEPARATOR.get_include_path());
q("UPDATE {$P}artefacts SET owner = 9601, vref = $source WHERE id = $id");
$database->flushArtefactCache();
ob_start(); include 'Templates/Build/27_show.tpl'; $html = ob_get_clean();
check(strpos($html, 'name="action" value="abandonArtefact"') !== false, 'Dueño ve botón');
check(strpos($html, 'name="confirm" value="1" required') !== false, 'Confirmación visible');
check(strpos($html, 'name="c" value="abandon-session-token"') !== false, 'Formulario incluye token');
check(strpos($html, 'tu alianza podría perder') !== false, 'Explica consecuencia de abandonar plano');
$session->is_sitter = 1;
ob_start(); include 'Templates/Build/27_show.tpl'; $html = ob_get_clean();
check(strpos($html, 'name="action" value="abandonArtefact"') === false, 'Representante no ve botón');
$session->is_sitter = 0;
$session->uid = 9602;
ob_start(); include 'Templates/Build/27_show.tpl'; $html = ob_get_clean();
check(strpos($html, 'name="action" value="abandonArtefact"') === false, 'Otro jugador no ve botón');

// Recursos reales: cerrar el tiempo pasado en TODAS las aldeas propias con la dieta vieja.
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require_once 'GameEngine/Automation.php';
class AbandonmentProductionCheck extends Automation {
    public function getAllUnits($vref) {
        global $database;
        return $database->getUnit($vref);
    }
}
$automation = new AbandonmentProductionCheck();
foreach(array(800020 => 9601, 800021 => 9602) as $vref => $owner) {
    q("INSERT INTO {$P}vdata (wref,owner,name) VALUES ($vref,$owner,'Production check')");
    $database->addResourceFields($vref, 3);
    $database->addUnits($vref);
}
q("UPDATE {$P}vdata SET crop = 10000, maxcrop = 100000, pop = 2, lastupdate = ".($now - 3600)." WHERE wref IN ($source,800020,800021)");
q("UPDATE {$P}units SET u1 = 100 WHERE vref IN ($source,800020,800021)");
q("UPDATE {$P}artefacts SET type = ".ARTEFACT_DIET.', size = '.ARTEFACT_SIZE_LARGE.", conquered = 1 WHERE id = $id");
$database->flushArtefactCache();
$oldFactor = $database->getArtefactEffectValue($source, 9601, ARTEFACT_DIET);
$automation->accrueAccountProductionBeforeArtefactChange(9601, $now);
foreach(array($source, 800020) as $vref) {
    $gross = villageGrossProduction($database->getResourceLevel($vref), array(), array(), SPEED);
    $expected = 10000 + $gross['production']['crop'] - 2 - 100 * $oldFactor;
    check(abs((float)scalar("SELECT crop FROM {$P}vdata WHERE wref = $vref") - $expected) < 1,
        'Aldea '.$vref.' acredita una hora con dieta, sin aplicar pérdida retroactiva');
    check((int)scalar("SELECT lastupdate FROM {$P}vdata WHERE wref = $vref") === $now,
        'Cierra reloj de '.$vref);
}
check((float)scalar("SELECT crop FROM {$P}vdata WHERE wref = 800021") === 10000.0, 'No modifica recursos de otro jugador');
echo 'Abandono de artefactos: '.($failures ? 'FALLA' : 'OK').' ('.$checks.' comprobaciones)'.PHP_EOL;
exit($failures ? 1 : 0);
