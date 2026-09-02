<?php
/**
 * Siembra los artefactos del mundo, ejecutando el plan de GameEngine/ArtefactRelease.php.
 *
 * En el T4 oficial los artefactos aparecen solos en una fecha programada; acá no hay
 * liberación automática, así que este es el único camino y lo dispara el administrador.
 *
 * Este archivo NO decide nada: cuántas aldeas, con qué guarnición y en qué anillo del mapa
 * sale todo de `artefactReleasePlan()`, que es el mismo que dibuja la vista previa del
 * formulario. Así lo que se anuncia y lo que se crea no pueden diferir.
 *
 * Qué estaba roto antes y por qué importa:
 *
 *  - Los números estaban escritos acá adentro (6/4/1 aldeas, guarnición de Maravilla x1/x2/x4).
 *    En un mundo de cuatro jugadores sin un solo soldado eso son 87 aldeas de 31.000 tropas:
 *    decoración inalcanzable. El oficial deriva la defensa de los mejores ejércitos del
 *    mundo, que es lo que ahora hace `artefactReleaseReferenceOffence()`.
 *  - Creaba la aldea con `addVillage()` y SQL a mano, **sin marcarla como NPC**: nacía como
 *    aldea de jugador propiedad de los Natars, o sea que pagaba manutención de tropas,
 *    entraba en `starvation()` y su guarnición se moría sola en minutos dejando el artefacto
 *    servido. Es el mismo bug que ya se había arreglado en las Aldeas de la Maravilla.
 *  - Ponía `pop = 163` fijo, sin relación con los edificios que le escribía después.
 *  - No la aprovisionaba, así que quedaba con 800 de almacén contra un escondite 10 y nunca
 *    tenía nada que saquear.
 *  - `include_once("../../config.php")` apuntaba a GameEngine/config.php, que no existe.
 */

ini_set('max_execution_time', 1000);
error_reporting(E_ALL);

// Database.php ya arrastra config/connection.php y config/config.php.
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
    die("<h1><font color=\"red\">Acceso denegado: no eres administrador.</font></h1>");
}

$natarId = natarsAccountId();
if($natarId <= 0 || !count($database->query_return("SELECT id FROM ".TB_PREFIX."users WHERE id = ".$natarId))) {
    header("Location: ../../../Admin/admin.php?p=npctribecreatenatar");
    exit;
}

/**
 * Sembrar sobre un mundo que ya tiene artefactos los DUPLICA: no hay reemplazo ni forma de
 * deshacerlo desde el panel. La pantalla avisa y pide una casilla; esta guarda es la que de
 * verdad protege, porque un doble clic o un F5 sobre el POST no pasan por la pantalla.
 */
$alreadySeeded = count($database->getAllArtefacts());
if($alreadySeeded > 0 && (!isset($_POST['confirmar']) || $_POST['confirmar'] !== 'si')) {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=confirmar");
    exit;
}

// Todo lo que llega del formulario pasa por acá antes de tocar nada.
$normalized = artefactReleaseNormalizeConfig($_POST);
$config = $normalized['config'];
$reference = $config['defence_mode'] === 'world'
    ? artefactReleaseReferenceOffence($database, $config['defence_sample'])
    : 0;
$plan = artefactReleasePlan($config, $reference);

if(!$plan['villages']) {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=vacio");
    exit;
}

// La creación de cada aldea vive en ArtefactRelease.php para que los checkers puedan
// llamarla sobre tablas temporales: acá sólo queda disparar el plan.
$result = artefactReleaseExecute($database, $plan, $natarId);

header("Location: ../../../Admin/admin.php?p=addArtefacts&g=".count($result['created'])
    .($result['failed'] > 0 ? "&sinsitio=".$result['failed'] : ""));
