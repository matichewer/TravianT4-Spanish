<?php
/**
 * Quedarse con un artefacto depende de la tesorería del ATACANTE.
 *
 * El fallo que fija: `canClaimArtifact()` leía `$AttackerFields` antes de asignarla —variable
 * indefinida, así que su bandera terminaba siempre en TRUE— cargaba `$DefenderFields` sin
 * usarla, y después consultaba `getResourceLevel()` una segunda vez sobre la MISMA aldea que
 * había recibido, que era la defensora. O sea que el requisito real del T4 (tesorería nivel
 * 10 para un artefacto de aldea, 20 para uno de cuenta o único, en la aldea que se lo lleva)
 * no se comprobaba nunca. Funcionaba de casualidad contra las Maravillas, porque su tesorería
 * nivel 10 satisfacía la condición equivocada.
 *
 * Y el destino: `claimArtefact($data['to'], $data['to'], ...)` dejaba el artefacto en la aldea
 * atacada y sólo le cambiaba el dueño, en vez de mudarlo a la del atacante.
 *
 * Hoy no hay artefactos colocados —sólo aparecen desde el panel de administración—, así que
 * esto cubre el camino antes de que se use.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_artifact_claim.php
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

// --- Lo que el código ya no puede volver a decir ----------------------------------------
$source = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
$start = strpos($source, 'public function canClaimArtifact');
$body = $start === false ? '' : substr($source, $start, 2600);
check($start !== false, 'canClaimArtifact() existe');
check(strpos($body, '$DefenderFields') === false,
    'ya no carga los campos del defensor para no usarlos');
check(strpos($body, '$AttackerFields') === false,
    'ya no usa la variable que se leía antes de asignarse');
check(preg_match('/\$slot\s*<=\s*40/', $body) === 1,
    'recorre las ranuras de edificio hasta la 40 y no hasta la 38');

$caller = file_get_contents($root.'/GameEngine/Automation.php');
check(strpos($caller, "canClaimArtifact(\$data['from']") !== false,
    'se pregunta por la aldea atacante, no por la atacada');
check(strpos($caller, "claimArtefact(\$data['from'], \$data['to']") !== false,
    'el artefacto se muda a la aldea del atacante');

// --- Y lo que hace de verdad contra el mundo real ---------------------------------------
// Las Maravillas tienen tesorería nivel 10, que es justo el umbral del artefacto de aldea.
$wonder = $database->query_return(
    "SELECT f.vref FROM ".TB_PREFIX."fdata f "
    ."INNER JOIN ".TB_PREFIX."vdata v ON v.wref = f.vref "
    ."WHERE f.f22t = 27 AND f.f22 >= 10 LIMIT 1"
);
if(is_array($wonder) && count($wonder)) {
    $vref = (int)$wonder[0]['vref'];
    check($database->canClaimArtifact($vref, 1) === true,
        'una aldea con tesorería 10 puede quedarse un artefacto de aldea');
    check($database->canClaimArtifact($vref, 2) === false,
        'pero no uno de cuenta, que pide tesorería 20');
    check($database->canClaimArtifact($vref, 3) === false,
        'ni uno único');
    check($database->canClaimArtifact($vref, 0) === false,
        'un tamaño desconocido no habilita nada');
} else {
    echo '[--] no hay ninguna aldea con tesorería para probar contra datos reales'.PHP_EOL;
}

// Una aldea sin tesorería no puede, sea cual sea el tamaño.
$plain = $database->query_return(
    "SELECT f.vref FROM ".TB_PREFIX."fdata f WHERE f.f22t <> 27 AND f.f21t <> 27 LIMIT 1"
);
if(is_array($plain) && count($plain)) {
    $bare = (int)$plain[0]['vref'];
    $levels = $database->getResourceLevel($bare);
    $hasTreasury = false;
    for($slot = 19; $slot <= 40; $slot++) {
        if(isset($levels['f'.$slot.'t']) && (int)$levels['f'.$slot.'t'] === 27) {
            $hasTreasury = true;
        }
    }
    if(!$hasTreasury) {
        check($database->canClaimArtifact($bare, 1) === false,
            'sin tesorería no se puede reclamar nada');
    }
}

check($database->canClaimArtifact(0, 1) === false, 'una aldea inexistente no puede reclamar');

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
