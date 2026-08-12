<?php
/**
 * Regresiones de la Embajada: fundación, invitaciones y cupo de alianza.
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_embassy.php
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$checks = 0;
$failures = array();

function embassyCheck($condition, $message) {
    global $checks, $failures;
    $checks++;
    if(!$condition) {
        $failures[] = $message;
        echo "FAIL: ".$message."\n";
    }
}

$alliance = file_get_contents('GameEngine/Alliance.php');
$automation = file_get_contents('GameEngine/Automation.php');
$build = file_get_contents('build.php');
$template = file_get_contents('Templates/Build/18.tpl');
$database = file_get_contents('GameEngine/Database/db_MYSQLi.php');

embassyCheck(strpos($alliance, "(int)\$session->alliance !== 0") !== false,
    'no se puede fundar otra alianza mientras ya se pertenece a una');
embassyCheck(strpos($alliance, "(int)\$village->resarray['f'.\$field.'t'] !== 18") !== false,
    'la fundación comprueba que el campo enviado sea realmente una Embajada');
embassyCheck(strpos($alliance, "(int)\$village->resarray['f'.\$field] < 3") !== false,
    'la fundación exige nivel 3 del lado del servidor');
embassyCheck(strpos($alliance, 'insertAlliNotice($aid') !== false,
    'el aviso de fundación se guarda en la alianza recién creada');
embassyCheck(strpos($alliance, "(int)\$UserData['alliance'] !== 0") !== false,
    'no se invita a jugadores que ya están en cualquier alianza');
embassyCheck(strpos($alliance, 'removeInvitationsForUser($session->uid)') !== false,
    'al aceptar se eliminan las demás invitaciones obsoletas del jugador');
embassyCheck(strpos($alliance, '$accept_error = 0;') !== false,
    'aceptar una invitación no lee un estado sin inicializar');
embassyCheck(strpos($database, 'function removeInvitationsForUser($uid)') !== false,
    'la limpieza de invitaciones queda acotada por jugador');
embassyCheck(strpos($build, '$alliance->procAlliance($_GET)') === false,
    'aceptar o rechazar ya no se dispara mediante una petición GET');
embassyCheck(strpos($template, 'type=\\"submit\\" name=\\"a\\" value=\\"3\\"') !== false,
    'la aceptación se envía por POST');
embassyCheck(strpos($automation, 'private function refreshEmbassyCapacity') !== false,
    'construcción, demolición y catapultas comparten el recálculo de cupo');
embassyCheck(substr_count($automation, 'refreshEmbassyCapacity(') >= 4,
    'el recálculo cubre la función y los tres caminos de cambio de nivel');
embassyCheck(strpos($automation, "\$allyleader = \$database->getVillageField(\$data['to'], \"owner\")") === false,
    'la demolición no usa la variable inexistente del combate');

if($failures) {
    echo "\nEmbassy checks failed: ".count($failures)."/".$checks.".\n";
    exit(1);
}

echo "Embassy checks passed (".$checks." comprobaciones).\n";
