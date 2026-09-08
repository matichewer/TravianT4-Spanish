<?php
/**
 * Deshace una liberación de artefactos.
 *
 *   docker compose exec -T web php /var/www/html/tools/wipe_artefacts.php
 *   docker compose exec -T web php /var/www/html/tools/wipe_artefacts.php --aplicar
 *
 * Por defecto sólo informa; escribe con `--aplicar`, como el resto de los `fix_*`.
 *
 * Existe porque sembrar tiene una docena de perillas que hay que calibrar mirando la vista
 * previa del panel, y sin una forma de volver atrás el primer intento con los números mal
 * deja el mapa lleno de aldeas natar que ya no se pueden sacar.
 *
 * Lo que NO toca, y conviene leerlo antes de correrlo: las Aldeas de la Maravilla, la
 * capital natar y la aldea de cualquier JUGADOR que haya capturado un artefacto. A esas se
 * les saca el artefacto y la aldea queda donde está. La regla completa está en
 * `artefactReleaseWipe()`.
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
$apply = in_array('--aplicar', $argv, true);

echo ($apply ? 'APLICANDO' : 'SIMULACIÓN (agregá --aplicar para escribir)').PHP_EOL.PHP_EOL;

$report = artefactReleaseWipe($database, $apply);

echo 'Artefactos encontrados : '.$report['artefacts'].PHP_EOL;
echo 'Aldeas natar a borrar  : '.count($report['villages']).PHP_EOL;

if($report['player_held']) {
    echo PHP_EOL.'Artefactos en manos de JUGADORES ('.count($report['player_held']).'): se les saca el'
        .' artefacto y la aldea queda intacta.'.PHP_EOL;
    foreach($report['player_held'] as $held) {
        echo '  - '.$held['name'].' (aldea '.$held['wref'].')'.PHP_EOL;
    }
}
if($report['protected']) {
    echo PHP_EOL.'En una Maravilla o en la capital natar ('.count($report['protected']).'): esas aldeas'
        .' no se borran nunca.'.PHP_EOL;
    foreach($report['protected'] as $protected) {
        echo '  - '.$protected['name'].' (aldea '.$protected['wref'].')'.PHP_EOL;
    }
}

if($report['artefacts'] === 0) {
    echo PHP_EOL.'No hay nada que borrar.'.PHP_EOL;
    exit(0);
}

if($apply) {
    $left = $database->query_return("SELECT COUNT(*) AS n FROM ".TB_PREFIX."artefacts");
    echo PHP_EOL.'Listo. Artefactos que quedan: '.(is_array($left) && count($left) ? (int)$left[0]['n'] : '?').PHP_EOL;
} else {
    echo PHP_EOL.'Volvé a correrlo con --aplicar para borrarlos.'.PHP_EOL;
}
