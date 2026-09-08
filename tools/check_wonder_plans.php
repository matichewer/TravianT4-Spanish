<?php
/**
 * La Maravilla del Mundo y los planos de construcción que la habilitan.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_wonder_plans.php
 *
 * Qué venía mal y por qué este archivo existe. `Building::meetRequirement()` devolvía
 * `false` para el tipo 40 con un comentario de "not implemented", y eso parecía bloquear la
 * Maravilla. No bloqueaba nada: `canBuild()` **no pasa por `meetRequirement()` cuando el
 * campo ya tiene un edificio**, y la Maravilla nunca se construye desde cero — ya viene
 * levantada en la aldea natar que se conquista. O sea que quien tomaba una Aldea de la
 * Maravilla la subía del 1 al 100 sin un solo requisito. El final de partida entero era
 * gratis, y el manual decía que era a propósito.
 *
 * La regla oficial (support.travian.com, "Building a World Wonder"):
 *
 *   - Niveles 1-49: **un** plano de construcción activo dentro de tu alianza, de quien sea.
 *   - Niveles 50-100: **dos**, "one held by the WW owner and one by a co-ally" — o sea uno
 *     tuyo y otro de OTRO jugador de tu alianza.
 *
 * Y tres cosas más que no funcionan en la aldea de la Maravilla y ahora tampoco acá: el
 * Tesoro (no se puede construir), el fin de obra con oro, el constructor maestro y el
 * mercader NPC.
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
require_once $root.'/GameEngine/Artefact.php';
require_once $root.'/GameEngine/Wonder.php';

$failures = 0;
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        $failures++;
        echo '[FALLA] '.$message.PHP_EOL;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

/**
 * Una base de datos de mentira con sólo lo que Wonder.php usa: `query_return`.
 *
 * Se responde por la forma de la consulta y no por un motor SQL de juguete a propósito: lo
 * que se está probando es la REGLA de los planos, y un mundo de verdad no permite armar los
 * doce escenarios que hacen falta (un plano inmaduro, uno de los natars, dos aliados con
 * uno cada uno) sin tocar el servidor.
 */
class FakeWonderDb {
    public $artefacts = array();   // filas: owner, type, conquered
    public $members = array();     // uid => alianza
    public $queries = array();

    public function query_return($sql) {
        $this->queries[] = $sql;
        if(strpos($sql, 'artefacts') !== false) {
            check(strpos($sql, '`type` = '.ARTEFACT_PLAN) !== false,
                'la consulta de planos filtra por el tipo 9 y no trae todos los artefactos');
            return $this->artefacts;
        }
        if(strpos($sql, 'users') !== false && preg_match('/`alliance` = (\d+)/', $sql, $m)) {
            $wanted = (int)$m[1];
            $rows = array();
            foreach($this->members as $uid => $alliance) {
                if((int)$alliance === $wanted) {
                    $rows[] = array('id' => $uid);
                }
            }
            return $rows;
        }
        return array();
    }
}

/** Un plano capturado hace `$hoursAgo` horas por `$owner`. */
function plan($owner, $hoursAgo = 100) {
    return array('owner' => $owner, 'type' => ARTEFACT_PLAN,
        'conquered' => time() - (int)($hoursAgo * 3600));
}

// =====================================================================================
section('A. La tabla oficial: uno hasta el 49, dos de ahí en adelante');
// =====================================================================================
check(WONDER_PLAN_SOLO_MAX_LEVEL === 49, 'el corte oficial está en el nivel 49');
check(WONDER_BUILDING_TYPE === 40 && WONDER_FIELD === 99,
    'la Maravilla es el edificio 40 y vive en el campo 99, fuera de la franja 1..40');

foreach(array(1, 2, 25, 48, 49) as $level) {
    $need = wonderPlanRequirement($level);
    check($need['own'] === 0 && $need['allies'] === 0 && $need['alliance'] === 1,
        'nivel '.$level.': alcanza un plano de cualquiera de la alianza');
}
foreach(array(50, 51, 75, 99, 100) as $level) {
    $need = wonderPlanRequirement($level);
    check($need['own'] === 1 && $need['allies'] === 1 && $need['alliance'] === 2,
        'nivel '.$level.': uno del dueño y otro de un co-aliado, no dos donde sea');
}
// El salto está exactamente entre 49 y 50, no en 48/49 ni en 50/51.
check(wonderPlanRequirement(49)['alliance'] === 1 && wonderPlanRequirement(50)['alliance'] === 2,
    'el salto de uno a dos planos ocurre entre el 49 y el 50, no en otro lado');

// =====================================================================================
section('B. Contar planos: madurez, dueño y cuenta del sistema');
// =====================================================================================
$db = new FakeWonderDb();
$db->artefacts = array(plan(5), plan(5), plan(7));
$counted = wonderPlansByOwner($db);
check($counted === array(5 => 2, 7 => 1), 'cuenta los planos de cada dueño');

// Un plano recién capturado todavía no cuenta: comparte el retardo de activación con el
// resto de los artefactos, así que robarlo no habilita la Maravilla en el mismo segundo.
$db = new FakeWonderDb();
$db->artefacts = array(plan(5, 0));
check(wonderPlansByOwner($db) === array(),
    'un plano recién capturado todavía no habilita nada: le falta el retardo');
$db->artefacts = array(plan(5, (artefactActivationDelay(SPEED) / 3600) + 1));
check(wonderPlansByOwner($db) === array(5 => 1),
    'pasado el retardo sí cuenta');

// Un plano que sigue en manos de los natars no le sirve a nadie.
$db = new FakeWonderDb();
$db->artefacts = array(plan(UID_NATARS), plan(UID_NATURE), plan(UID_SUPPORT), plan(UID_MULTIHUNTER));
check(wonderPlansByOwner($db) === array(),
    'los planos que siguen siendo de los natars no habilitan la Maravilla de nadie');

// =====================================================================================
section('C. El estado: qué falta y de quién');
// =====================================================================================
// Sin ningún plano, ni siquiera el nivel 1.
$db = new FakeWonderDb();
$status = wonderPlanStatus($db, 5, 0, 1);
check(!$status['allowed'], 'sin planos no se levanta ni el nivel 1');
check($status['missing_alliance'] === 1 && $status['missing_own'] === 0,
    'y lo que falta es "un plano en la alianza", no uno propio');
check(wonderPlanMessage($status) !== '', 'y hay un texto que se lo explica al jugador');

// Con el plano propio, hasta el 49.
$db = new FakeWonderDb();
$db->artefacts = array(plan(5));
$db->members = array(5 => 3);
check(wonderPlanStatus($db, 5, 3, 1)['allowed'], 'con el plano propio se llega al nivel 1');
check(wonderPlanStatus($db, 5, 3, WONDER_PLAN_SOLO_MAX_LEVEL)['allowed'],
    'y hasta el '.WONDER_PLAN_SOLO_MAX_LEVEL);
$blocked = wonderPlanStatus($db, 5, 3, WONDER_PLAN_SOLO_MAX_LEVEL + 1);
check(!$blocked['allowed'], 'pero no al '.(WONDER_PLAN_SOLO_MAX_LEVEL + 1).' con un solo plano');
check($blocked['missing_alliance'] === 1 && $blocked['missing_own'] === 0,
    'y lo que falta ahí es el plano del aliado, no el propio');
check(strpos(wonderPlanMessage($blocked), 'dos planos') !== false,
    'el texto le dice que hacen falta dos');

// El plano de un aliado alcanza hasta el 49 aunque el dueño de la Maravilla no tenga ninguno.
$db = new FakeWonderDb();
$db->artefacts = array(plan(9));
$db->members = array(5 => 3, 9 => 3);
check(wonderPlanStatus($db, 5, 3, 10)['allowed'],
    'el plano de un aliado habilita la Maravilla hasta el 49, aunque no sea tuyo');
$fifty = wonderPlanStatus($db, 5, 3, 50);
check(!$fifty['allowed'] && $fifty['missing_own'] === 1,
    'pero del 50 en adelante falta el TUYO: el del aliado no lo reemplaza');
check(strpos(wonderPlanMessage($fifty), 'vos') !== false,
    'y el texto se lo dice con todas las letras');

// Dos planos, uno de cada uno: se llega al 100.
$db = new FakeWonderDb();
$db->artefacts = array(plan(5), plan(9));
$db->members = array(5 => 3, 9 => 3);
check(wonderPlanStatus($db, 5, 3, 100)['allowed'],
    'con un plano propio y uno de un aliado se llega al 100');
check(wonderPlanMessage(wonderPlanStatus($db, 5, 3, 100)) === '',
    'y cuando se puede, no hay nada que explicar');

// Dos planos PROPIOS no alcanzan: el oficial pide "one held by the WW owner and one by a
// co-ally". Es la trampa evidente —juntar los dos en una cuenta— y tiene que estar cerrada.
$db = new FakeWonderDb();
$db->artefacts = array(plan(5), plan(5));
$db->members = array(5 => 3);
$hoarded = wonderPlanStatus($db, 5, 3, 50);
check(!$hoarded['allowed'],
    'dos planos en la misma cuenta no llegan al 50: el segundo tiene que ser de un aliado');
check($hoarded['own'] === 2 && $hoarded['allies'] === 0,
    'y el informe lo dice: dos propios, cero de aliados');

// Un jugador sin alianza llega al 49 con el suyo y no pasa de ahí.
$db = new FakeWonderDb();
$db->artefacts = array(plan(5));
check(wonderPlanStatus($db, 5, 0, WONDER_PLAN_SOLO_MAX_LEVEL)['allowed'],
    'un jugador sin alianza llega al '.WONDER_PLAN_SOLO_MAX_LEVEL.' con su propio plano');
check(!wonderPlanStatus($db, 5, 0, WONDER_PLAN_SOLO_MAX_LEVEL + 1)['allowed'],
    'y no pasa de ahí, porque el segundo plano tiene que ser de otro jugador');

// El plano de alguien de OTRA alianza no cuenta.
$db = new FakeWonderDb();
$db->artefacts = array(plan(9));
$db->members = array(5 => 3, 9 => 4);
check(!wonderPlanStatus($db, 5, 3, 1)['allowed'],
    'el plano de un jugador de otra alianza no habilita nada');

// =====================================================================================
section('D. Reconocer la aldea de la Maravilla');
// =====================================================================================
check(wonderVillage(array('f99t' => 40)), 'una aldea con la Maravilla en el campo 99 se reconoce');
check(!wonderVillage(array('f99t' => 0)), 'y una aldea normal no');
check(!wonderVillage(array()), 'una fila vacía no rompe');
check(!wonderVillage(array('f40t' => 40)),
    'un Muro de tierra en el campo 40 no convierte la aldea en Maravilla');

// =====================================================================================
section('E. Está enchufado al motor, no sólo definido');
// =====================================================================================
$building = file_get_contents($root.'/GameEngine/Building.php');
check(strpos($building, "require_once __DIR__.'/Wonder.php'") !== false,
    'Building.php carga las reglas de la Maravilla');
check(preg_match('/\$tid === WONDER_BUILDING_TYPE && !\$this->wonderPlansAllowNextLevel\(/', $building) === 1,
    'canBuild() gatea la Maravilla por planos — que es el camino por el que se subía gratis');
check(preg_match('/case 27:\s*\n\s*return !wonderVillage/', $building) === 1,
    'no se puede construir un Tesoro en la aldea de la Maravilla');
check(strpos($building, 'wonderPlanStatusForVillage') !== false,
    'y hay una forma de preguntar QUÉ falta, no sólo si se puede');

$upgrade = file_get_contents($root.'/Templates/Build/upgrade.tpl');
check(strpos($upgrade, 'bindicate == 12') !== false,
    'la pantalla de construcción dibuja el motivo cuando faltan planos');
check(strpos($upgrade, 'wonderPlanMessage') !== false,
    'y usa el mismo texto que el motor, no una copia');

$market = file_get_contents($root.'/GameEngine/Market.php');
check(preg_match('/if\(wonderVillage\(\$village->resarray\)\)\s*\{\s*\n\s*\$this->marketFailure\(.wonder./', $market) === 1,
    'el mercader NPC no trabaja en la aldea de la Maravilla');
check(strpos($market, "case 'wonder':") !== false,
    'y el rechazo tiene un texto propio en vez del genérico');
$menu = file_get_contents($root.'/Templates/Build/17_menu.tpl');
check(strpos($menu, 'wonderVillage($village->resarray)') !== false,
    'la pestaña del NPC no se dibuja en la aldea de la Maravilla');

// El plano es un artefacto de pleno derecho: se roba, se guarda y se ve como los otros.
check(defined('ARTEFACT_PLAN') && ARTEFACT_PLAN === 9, 'el plano es el tipo 9');
$catalog = artefactTypeCatalog();
check(isset($catalog[ARTEFACT_PLAN]), 'y está en el catálogo, así que tiene nombre en pantalla');
check(!isset(artefactEffectTypeCatalog()[ARTEFACT_PLAN]),
    'pero fuera del catálogo de efectos: no aplica ningún bono');
$list = file_get_contents($root.'/Templates/Build/27_4.tpl');
check(strpos($list, 'ARTEFACT_PLAN') !== false,
    'el Tesoro tiene una pestaña propia para los planos');
$tabs = file_get_contents($root.'/Templates/Build/27_menu.tpl');
check(strpos($tabs, 't=4') !== false, 'y la pestaña está en el menú del Tesoro');

// =====================================================================================
section('F. El plano se siembra como su propia clase, no como un tamaño');
// =====================================================================================
require_once $root.'/GameEngine/Data/unitdata.php';
require_once $root.'/GameEngine/NatarVillage.php';
require_once $root.'/GameEngine/NatarSettlement.php';
require_once $root.'/GameEngine/ArtefactRelease.php';

$config = artefactReleaseNormalizeConfig(array(
    'count_small' => 1, 'count_large' => 1, 'count_unique' => 1, 'count_plans' => 3))['config'];
$plan = artefactReleasePlan($config, 100000);
$planVillages = array();
foreach($plan['villages'] as $village) {
    if($village['type'] === ARTEFACT_PLAN) {
        $planVillages[] = $village;
    }
}
check(count($planVillages) === 3, 'tres planos pedidos, tres aldeas de plano — no tres por tipo');
foreach($planVillages as $village) {
    check($village['size'] === ARTEFACT_SIZE_SMALL,
        'el plano se guarda con tamaño pequeño, que es el que pide Tesoro 10');
    check(artefactTreasuryRequirement($village['size'], $village['type']) === 10,
        'y las dos formas de pedir el nivel de Tesoro coinciden en 10');
    check($village['garrison'] === $plan['plans']['garrison'],
        'la guarnición de la aldea es la que anuncia la vista previa');
}
check($plan['plans']['stats']['troops'] > $plan['summary'][ARTEFACT_SIZE_SMALL]['stats']['troops'],
    'un plano está mejor defendido que un artefacto pequeño: sin él no hay final de partida');

// =====================================================================================
section('G. El motor de verdad: canBuild() sobre una Maravilla');
// =====================================================================================
//
// Las secciones anteriores prueban la regla y que las llamadas están escritas. Esta es la
// que importa: hace pasar una Maravilla real por `Building::canBuild()` y mira lo que
// devuelve. Es el hueco por el que se coló el bug original — `meetRequirement()` decía que
// no y nadie lo llamaba, así que revisar la regla sin ejercitar el camino habría dado verde
// con el nivel 100 gratis.
//
// El mundo de mentira son TEMPORARY TABLES, que en MySQL tapan a las reales para esta
// conexión y desaparecen al cerrarla: el servidor de verdad no se toca.
$P = TB_PREFIX;
function tmp($table) {
    global $database, $P;
    $create = $database->query_return("SHOW CREATE TABLE `{$P}{$table}`");
    if(!is_array($create) || !count($create)) {
        return false;
    }
    $sql = $create[0]['Create Table'];
    $sql = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $sql, 1);
    $database->query("DROP TEMPORARY TABLE IF EXISTS `{$P}{$table}`");
    return (bool)$database->query($sql);
}
$ready = true;
foreach(array('artefacts', 'users', 'bdata', 'demolition') as $table) {
    $ready = tmp($table) && $ready;
}
check($ready, 'se pudo clonar el mundo de prueba en tablas temporales');

if($ready) {
    require_once $root.'/GameEngine/Data/cp.php';
    require_once $root.'/GameEngine/Building.php';

    // Dos jugadores en la misma alianza. El 5 es el dueño de la Maravilla.
    $database->query("INSERT INTO `{$P}users` (`id`,`username`,`alliance`,`access`) "
        ."VALUES (5,'duenio',3,1),(9,'aliado',3,1)");

    // La aldea: campo 99 con la Maravilla en nivel 10.
    $wonderVillage = new stdClass();
    $wonderVillage->wid = 991001;
    $wonderVillage->capital = 0;
    $wonderVillage->resarray = array('f99' => 10, 'f99t' => WONDER_BUILDING_TYPE);
    $GLOBALS['village'] = $wonderVillage;

    $fakeSession = new stdClass();
    $fakeSession->uid = 5;
    $fakeSession->tribe = 1;
    $fakeSession->plus = 0;
    $GLOBALS['session'] = $fakeSession;

    $builder = new Building();

    // Sin ningún plano: la Maravilla no sube ni del 10 al 11.
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) === 12,
        'sin plano, canBuild() devuelve 12 — el nivel 100 ya no es gratis');
    $status = $builder->wonderPlanStatusForVillage(WONDER_FIELD);
    check($status['level'] === 11, 'y el nivel que mira es el siguiente, no el actual');
    check(wonderPlanMessage($status) !== '', 'con un texto que explica qué falta');

    // Con un plano del aliado alcanza para seguir subiendo hasta el 49.
    $database->query("INSERT INTO `{$P}artefacts` (`vref`,`owner`,`type`,`size`,`conquered`) "
        ."VALUES (1,9,".ARTEFACT_PLAN.",".ARTEFACT_SIZE_SMALL.",".(time() - 864000).")");
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) !== 12,
        'con el plano de un aliado la Maravilla vuelve a subir');

    // Pero al llegar al 49 se frena de nuevo: falta el propio.
    $wonderVillage->resarray['f99'] = WONDER_PLAN_SOLO_MAX_LEVEL;
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) === 12,
        'del '.WONDER_PLAN_SOLO_MAX_LEVEL.' al '.(WONDER_PLAN_SOLO_MAX_LEVEL + 1)
        .' se frena: hace falta un plano propio');
    check($builder->wonderPlanStatusForVillage(WONDER_FIELD)['missing_own'] === 1,
        'y dice exactamente eso: falta el tuyo');

    // Con el propio, sigue.
    $database->query("INSERT INTO `{$P}artefacts` (`vref`,`owner`,`type`,`size`,`conquered`) "
        ."VALUES (2,5,".ARTEFACT_PLAN.",".ARTEFACT_SIZE_SMALL.",".(time() - 864000).")");
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) !== 12,
        'con el propio y el del aliado, la Maravilla llega al 100');

    // Un plano recién robado no habilita nada todavía: comparte el retardo de activación.
    $database->query("UPDATE `{$P}artefacts` SET `conquered` = ".time());
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) === 12,
        'dos planos recién capturados no habilitan nada hasta que pase el retardo');

    // La cola cuenta: con el 49 construido y una mejora ya encolada, el próximo nivel es el
    // 51, así que el gateo de los dos planos tiene que aplicar igual.
    $database->query("UPDATE `{$P}artefacts` SET `conquered` = ".(time() - 864000)
        ." WHERE `owner` = 9");
    $database->query("DELETE FROM `{$P}artefacts` WHERE `owner` = 5");
    $wonderVillage->resarray['f99'] = WONDER_PLAN_SOLO_MAX_LEVEL - 1;
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) !== 12,
        'con la Maravilla en '.(WONDER_PLAN_SOLO_MAX_LEVEL - 1).' y un plano ajeno, se puede subir al '
        .WONDER_PLAN_SOLO_MAX_LEVEL);
    $database->query("INSERT INTO `{$P}bdata` (`wid`,`field`,`type`,`level`,`timestamp`,`master`) "
        ."VALUES (991001,".WONDER_FIELD.",".WONDER_BUILDING_TYPE.",".WONDER_PLAN_SOLO_MAX_LEVEL
        .",".(time() + 3600).",0)");
    check($builder->canBuild(WONDER_FIELD, WONDER_BUILDING_TYPE) === 12,
        'pero con esa mejora ya en la cola el próximo nivel es el '.(WONDER_PLAN_SOLO_MAX_LEVEL + 1)
        .' y vuelve a pedir dos planos');

    // Y el Tesoro no se puede construir en esa aldea. Las dos aldeas se arman idénticas
    // salvo por el campo 99, para que lo único que pueda explicar la diferencia sea la
    // Maravilla y no un requisito de nivel que falte en una de las dos.
    $withMainBuilding = array('f19t' => 15, 'f19' => 10, 'f25' => 0, 'f25t' => 0);
    $wonderVillage->resarray = $withMainBuilding + array('f99' => 10, 'f99t' => WONDER_BUILDING_TYPE);
    check($builder->meetRequirement(27) === false,
        'no se puede construir un Tesoro en la aldea de la Maravilla');

    $normalVillage = new stdClass();
    $normalVillage->wid = 991002;
    $normalVillage->capital = 1;
    $normalVillage->resarray = $withMainBuilding + array('f99' => 0, 'f99t' => 0);
    $GLOBALS['village'] = $normalVillage;
    check($builder->meetRequirement(27) === true,
        'y en una aldea normal sí, o el Tesoro quedaría prohibido en todos lados');

    foreach(array('artefacts', 'users', 'bdata', 'demolition') as $table) {
        $database->query("DROP TEMPORARY TABLE IF EXISTS `{$P}{$table}`");
    }
}

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Maravilla y planos de construcción: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
