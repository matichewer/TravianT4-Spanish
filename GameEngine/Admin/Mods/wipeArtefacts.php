<?php
/**
 * Borra todos los artefactos del mundo y las aldeas natar que los guardaban.
 *
 * Es el "deshacer" del sembrado: sin él, calibrar los números de una liberación es un
 * disparo de una sola bala. La regla de qué se borra y qué no vive en
 * `artefactReleaseWipe()`, la misma que usa `tools/wipe_artefacts.php`.
 *
 * Dos guardas, y las dos son del lado del servidor porque un F5 sobre el POST no vuelve a
 * pasar por la pantalla: hay que ser administrador, y hay que mandar la confirmación
 * explícita que la pantalla pide con una casilla.
 */

ini_set('max_execution_time', 1000);
error_reporting(E_ALL);

include_once("../../Database.php");
include_once("../../Data/unitdata.php");
include_once("../../Data/buidata.php");
include_once("../../NatarVillage.php");
include_once("../../NatarSettlement.php");
include_once("../../ArtefactRelease.php");

$adminId = isset($_POST['admid']) ? (int)$_POST['admid'] : 0;
$access = $adminId > 0
    ? $database->query_return("SELECT access FROM ".TB_PREFIX."users WHERE id = ".$adminId)
    : array();
if(!is_array($access) || !count($access) || (int)$access[0]['access'] !== 9) {
    die("<h1><font color=\"red\">Acceso denegado: esta pantalla es sólo para administradores.</font></h1>");
}

if(!isset($_POST['confirmar_borrado']) || $_POST['confirmar_borrado'] !== 'si') {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=confirmarborrado");
    exit;
}

$report = artefactReleaseWipe($database, true);

header("Location: ../../../Admin/admin.php?p=addArtefacts"
    ."&borrados=".(int)$report['artefacts']
    ."&aldeas=".count($report['villages'])
    .(count($report['player_held']) ? "&dejugadores=".count($report['player_held']) : ""));
