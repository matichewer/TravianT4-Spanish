<?php
/**
 * Programa (o cancela) la liberación automática de artefactos.
 *
 * En el T4 oficial los artefactos aparecen solos en una fecha anunciada de antemano, y esa
 * fecha es la que arranca la carrera final: todo el mundo sabe cuándo empieza y se prepara.
 * Acá el único camino era el botón de sembrar, así que la liberación era una sorpresa para
 * todos menos para el administrador —que además tenía que estar despierto a la hora que
 * quisiera.
 *
 * Lo que se guarda es la fecha MÁS el plan congelado en JSON. Congelado a propósito: la
 * guarnición se deriva de los mejores ejércitos del mundo, y entre programar y disparar el
 * mundo cambia. Si se recalculara al disparar, el administrador estaría aprobando una vista
 * previa y el servidor sembraría otra cosa.
 *
 * Quien dispara es `Automation::artefactRelease()`, no este archivo.
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

if(!$database->ensureArtefactReleaseColumns()) {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=sinmigracion");
    exit;
}

// Cancelar es su propio botón y no una fecha vacía: una fecha vacía por accidente no puede
// desprogramar algo que ya estaba anunciado a los jugadores.
if(isset($_POST['cancelar']) && $_POST['cancelar'] === 'si') {
    artefactReleaseSchedule($database, 0, array());
    header("Location: ../../../Admin/admin.php?p=addArtefacts&cancelado=1");
    exit;
}

$raw = isset($_POST['release_at']) ? trim((string)$_POST['release_at']) : '';
// El navegador manda "2026-09-20T21:00" en la zona del jugador; strtotime la lee con la
// zona del servidor, que es la que el juego usa para todo lo demás.
$at = $raw !== '' ? strtotime(str_replace('T', ' ', $raw)) : false;
if($at === false || $at <= 0) {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=fecha");
    exit;
}
if($at <= time()) {
    // Una fecha ya pasada dispararía en el barrido siguiente, o sea "sembrar ahora" por la
    // puerta de atrás y sin la confirmación de duplicado que pide el otro botón.
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=pasado");
    exit;
}

artefactReleaseSchedule($database, $at, $_POST);

header("Location: ../../../Admin/admin.php?p=addArtefacts&programado=".(int)$at);
