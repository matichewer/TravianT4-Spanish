<?php
/**
 * La liberación programada de artefactos.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_artefact_schedule.php
 *
 * En el Travian oficial los artefactos no los suelta nadie a mano: aparecen en una fecha
 * anunciada de antemano, y esa fecha es la que arranca la carrera final — todo el mundo sabe
 * cuándo empieza y se prepara. Este servidor sólo tenía el botón del panel, así que la
 * liberación era una sorpresa para todos menos para el administrador, que además tenía que
 * estar despierto a la hora que quisiera.
 *
 * Las dos cosas que este archivo cuida y que son fáciles de romper sin darse cuenta:
 *
 *  - **El plan se congela al programarlo.** La guarnición se deriva de los mejores ejércitos
 *    del mundo, y entre programar y disparar el mundo cambia. Si se recalculara al disparar,
 *    el administrador estaría aprobando una vista previa y el servidor sembraría otra cosa.
 *  - **Se dispara UNA vez.** El turno se toma con un compare-and-swap en la base, no con la
 *    marca de `Prevention/`, porque dos requests simultáneas sembrarían el mundo dos veces y
 *    eso no se puede deshacer sin arrasar aldeas.
 *
 * Este checker escribe en la fila de `config` del mundo real —es la única fila que hay— así
 * que guarda las tres columnas al empezar y las restaura al terminar, pase lo que pase. Y
 * nunca dispara un plan con aldeas: el escenario que ejercita el disparo usa los conteos en
 * cero, así que el camino se recorre entero sin crear una sola aldea natar.
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
include "Data/unitdata.php";
require_once $root.'/GameEngine/NatarVillage.php';
require_once $root.'/GameEngine/NatarSettlement.php';
require_once $root.'/GameEngine/ArtefactRelease.php';

global $database;

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

// -------------------------------------------------------------------------------------
// Guardar el estado real y devolverlo pase lo que pase. Sin esto, un checker que se cae a
// la mitad deja el servidor con una liberación programada que nadie pidió.
// -------------------------------------------------------------------------------------
check($database->ensureArtefactReleaseColumns(),
    'las columnas de la programación existen (o se crean solas, como npckind)');
$saved = $database->getArtefactReleaseSchedule();
check(is_array($saved), 'se puede leer la fila de programación del mundo');
register_shutdown_function(function () use ($database, $saved) {
    if(is_array($saved)) {
        $database->setArtefactReleaseSchedule($saved['at'], $saved['config']);
        // setArtefactReleaseSchedule() pone `done` en 0 por diseño (reprogramar es pedir
        // otra liberación), así que el valor original se repone aparte.
        $database->query("UPDATE ".TB_PREFIX."config SET `artefact_release_done` = "
            .(int)$saved['done']);
    }
});

// =====================================================================================
section('A. Sin programar, no pasa nada');
// =====================================================================================
$database->setArtefactReleaseSchedule(0, '');
$status = artefactReleaseScheduleStatus($database);
check($status['available'], 'el mundo tiene la migración aplicada');
check(!$status['scheduled'] && !$status['due'], 'sin fecha no hay nada programado');
check(artefactReleaseRunScheduled($database) === null,
    'y el barrido no hace nada: devuelve null en vez de sembrar');

// =====================================================================================
section('B. Programar congela el plan');
// =====================================================================================
$future = time() + 3600;
$wanted = array(
    'count_small' => 3, 'count_large' => 2, 'count_unique' => 1, 'count_plans' => 5,
    'defence_mode' => 'manual', 'defence_manual' => 123456, 'defence_floor' => 0,
    'treasury' => 15, 'fields' => 8, 'cranny' => 4, 'wall' => 2
);
check($database->setArtefactReleaseSchedule($future, json_encode(
    artefactReleaseNormalizeConfig($wanted)['config'])), 'se guarda la programación');

$status = artefactReleaseScheduleStatus($database);
check($status['at'] === $future, 'la fecha se lee tal cual se guardó');
check($status['scheduled'] && !$status['due'], 'está programada pero todavía no toca');
check($status['seconds'] > 3500 && $status['seconds'] <= 3600,
    'y faltan más o menos los 3600 segundos que se pidieron');
foreach($wanted as $key => $value) {
    if($key === 'defence_mode') {
        check($status['config'][$key] === $value, 'se congeló '.$key);
        continue;
    }
    check((int)$status['config'][$key] === (int)$value, 'se congeló '.$key.' = '.$value);
}

// Lo que se guarda pasa por la misma normalización que el POST: un valor fuera de rango en
// la base —de una versión anterior del formulario, o escrito a mano— no puede sembrar algo
// imposible al dispararse semanas después.
$database->setArtefactReleaseSchedule($future, json_encode(array(
    'count_small' => 9999, 'ring_small_min' => 90, 'ring_small_max' => 5)));
$dirty = artefactReleaseScheduleStatus($database);
check((int)$dirty['config']['count_small'] === 50,
    'un conteo fuera de rango guardado en la base se recorta al leerlo');
check((int)$dirty['config']['ring_small_min'] === 5 && (int)$dirty['config']['ring_small_max'] === 90,
    'y un anillo invertido se da vuelta, igual que en el formulario');
check(!empty($dirty['config_warnings']), 'y queda constancia de que hubo que corregir');

// Un JSON roto no puede dejar el sembrado sin configuración.
$database->query("UPDATE ".TB_PREFIX."config SET `artefact_release_config` = 'no soy json'");
$broken = artefactReleaseScheduleStatus($database);
check($broken['config'] === artefactReleaseDefaults(),
    'un JSON corrupto cae a los valores por defecto en vez de reventar');

// =====================================================================================
section('C. Se dispara una sola vez');
// =====================================================================================
// Conteos en cero: el camino se recorre entero (leer, tomar el turno, armar el plan,
// ejecutarlo) sin crear una sola aldea en el mundo real.
$emptyPlan = artefactReleaseNormalizeConfig(array(
    'count_small' => 0, 'count_large' => 0, 'count_unique' => 0, 'count_plans' => 0))['config'];
$artefactsBefore = count($database->getAllArtefacts());

$past = time() - 60;
$database->setArtefactReleaseSchedule($past, json_encode($emptyPlan));
$status = artefactReleaseScheduleStatus($database);
check($status['due'], 'una fecha ya pasada figura como vencida');

$result = artefactReleaseRunScheduled($database);
check(is_array($result), 'el barrido la dispara');
check(isset($result['created']) && count($result['created']) === 0,
    'con los conteos en cero no crea ninguna aldea');
check(count($database->getAllArtefacts()) === $artefactsBefore,
    'y el mundo real quedó exactamente como estaba');

$after = artefactReleaseScheduleStatus($database);
check($after['done'] > 0, 'queda marcada como disparada');
check(!$after['scheduled'] && !$after['due'], 'y ya no figura como pendiente');
check(artefactReleaseRunScheduled($database) === null,
    'un segundo barrido no la vuelve a disparar — que es la diferencia entre sembrar una vez y sembrar dos');

// El compare-and-swap directo: sólo el primero se lleva el turno.
//
// Los dos intentos van con relojes DISTINTOS a propósito. `mysqli_affected_rows` cuenta las
// filas que cambiaron, no las que coincidieron, así que con el mismo timestamp un UPDATE que
// se olvidó del `done = 0` en el WHERE igual devuelve 0 —está reescribiendo el mismo valor— y
// la comprobación pasaría sin que el candado exista. Con cinco segundos de diferencia, un
// candado ausente escribe un valor nuevo y se delata.
$database->setArtefactReleaseSchedule(time() - 10, json_encode($emptyPlan));
$claimAt = time();
check($database->claimArtefactRelease($claimAt), 'el primero que llega toma el turno');
check(!$database->claimArtefactRelease($claimAt + 5),
    'y el segundo se queda sin nada que hacer, aunque llegue unos segundos después');
check((int)artefactReleaseScheduleStatus($database)['done'] === $claimAt,
    'la marca de disparo sigue siendo la del primero: el segundo no la pisó');

// Una fecha futura no se puede reclamar aunque alguien llame a la función a mano.
$database->setArtefactReleaseSchedule(time() + 86400, json_encode($emptyPlan));
check(!$database->claimArtefactRelease(time()),
    'una liberación futura no se puede adelantar reclamándola');

// Reprogramar reabre el turno: es lo que hace que el botón sirva dos veces.
$database->setArtefactReleaseSchedule(time() - 10, json_encode($emptyPlan));
check($database->claimArtefactRelease(time()),
    'reprogramar vuelve a habilitar el disparo, o el botón serviría una sola vez en la vida');

// =====================================================================================
section('D. Está enchufado al barrido y al panel');
// =====================================================================================
$automation = file_get_contents($root.'/GameEngine/Automation.php');
check(strpos($automation, "require_once __DIR__.'/ArtefactRelease.php'") !== false,
    'Automation carga el módulo de liberación');
check(strpos($automation, 'Prevention/artefactrelease.txt') !== false,
    'y el barrido tiene su propia marca de Prevention, para no consultar config en cada request');
check(strpos($automation, 'artefactReleaseRunScheduled($database)') !== false,
    'el barrido llama a la liberación programada');
check(strpos($automation, '[ARTEFACTOS]') !== false,
    'y deja rastro en el log: una liberación a medias tiene que poder verse en algún lado');

$mod = file_get_contents($root.'/GameEngine/Admin/Mods/scheduleArtefacts.php');
check(strpos($mod, "(int)\$access[0]['access'] !== 9") !== false,
    'el mod que programa exige ser administrador, del lado del servidor');
check(strpos($mod, 'e=pasado') !== false,
    'una fecha ya pasada se rechaza: sería "sembrar ahora" sin la confirmación de duplicado');
check(strpos($mod, "\$_POST['cancelar']") !== false,
    'cancelar es su propio botón y no una fecha vacía por accidente');
check(strpos($mod, 'ensureArtefactReleaseColumns') !== false,
    'y avisa si a la base le falta la migración en vez de fallar en silencio');

$tpl = file_get_contents($root.'/Admin/Templates/addArtefacts.tpl');
check(strpos($tpl, 'artefactReleaseScheduleStatus($database)') !== false,
    'el panel muestra la programación que hay');
check(strpos($tpl, 'scheduleArtefacts.php') !== false, 'y tiene el botón para programarla');
check(strpos($tpl, 'release_at') !== false, 'con un campo de fecha');

$migrations = file_get_contents($root.'/tools/migrations.sql');
foreach(array('artefact_release_at', 'artefact_release_done', 'artefact_release_config') as $column) {
    check(strpos($migrations, $column) !== false,
        'la columna '.$column.' está en migrations.sql, así que producción la va a tener');
}

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Liberación programada: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
