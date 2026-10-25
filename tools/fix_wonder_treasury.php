<?php
/**
 * Derriba el Tesoro de las Aldeas de la Maravilla.
 *
 * Ejecutar:  docker compose exec -T web php tools/fix_wonder_treasury.php
 *            docker compose exec -T web php tools/fix_wonder_treasury.php --aplicar
 *
 * Sin --aplicar sólo informa. Con --aplicar derriba cada Tesoro con el mismo paso que la
 * demolición con oro (Automation::demolishBuildingNow), que recuenta habitantes y cultura.
 *
 * Por qué hace falta: oficialmente en la aldea de la Maravilla no puede haber Tesoro —es lo
 * que obliga a guardar el plano de construcción en otra aldea y lo vuelve robable—, pero el
 * instalador y el panel las creaban con uno de nivel 10 (natarWonderBuildings) y la
 * conquista lo conservaba. Desde ahí se podía subir al 20 y recibir el plano o cualquier
 * artefacto. El motor ya no deja subirlo ni recibir nada; esto saca el que ya existe.
 *
 * No toca dos casos, y los informa:
 *   - una aldea cuyo Tesoro guarda un artefacto: derribarlo no lo saca de ahí, y moverlo es
 *     una decisión del administrador;
 *   - una aldea con una obra del Tesoro en la cola: al terminar volvería a levantarlo, así
 *     que hay que cancelarla primero.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

error_reporting(E_ALL);
$root = dirname(__DIR__);
chdir($root);
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
date_default_timezone_set('America/Argentina/Buenos_Aires');

$apply = in_array('--aplicar', $argv, true);

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/unitdata.php";
include "Lang/".LANG.".php";
// Automation sin su barrido: el constructor corre todo el mantenimiento del mundo,
// incluido el borrado de cuentas inactivas.
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
require_once $root.'/GameEngine/Technology.php';
require_once $root.'/GameEngine/Automation.php';

$repairs = wonderVillageTreasuryRepairs($database);
if(!$repairs) {
    echo "Ninguna Aldea de la Maravilla tiene Tesoro.\n";
    exit(0);
}

$reasons = array(
    'ok' => 'se derriba',
    'holds_artefact' => 'NO se toca: guarda un artefacto',
    'queued' => 'NO se toca: tiene una obra del Tesoro en la cola'
);
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
$pending = 0;
foreach($repairs as $repair) {
    printf("Aldea %d (dueño %d): Tesoro nivel %d en el campo %d — %s\n",
        $repair['wref'], $repair['owner'], $repair['level'], $repair['slot'], $reasons[$repair['status']]);
    if($repair['status'] !== 'ok') {
        continue;
    }
    if(!$apply) {
        $pending++;
        continue;
    }
    $levels = wonderVillageRemoveTreasury($automation, $repair);
    if($levels !== $repair['level']) {
        printf("  bajaron %d de %d niveles: revisar la aldea a mano\n", $levels, $repair['level']);
    }
}

if(!$apply && $pending > 0) {
    echo "Nada escrito. Volvé a correrlo con --aplicar para derribar ".$pending." Tesoro(s).\n";
}
