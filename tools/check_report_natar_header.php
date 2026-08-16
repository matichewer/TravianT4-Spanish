<?php
/**
 * El encabezado del defensor natar en el informe de batalla.
 *
 * El fallo que fija: Templates/Notice/tribe_5.tpl tenía escrito "Natares" a mano (el
 * nombre de la TRIBU) donde las otras cuatro plantillas ponen el nombre de la cuenta, y
 * un "aldea" suelto en lugar de REPORT_FROM_VIL. Resultado: el mismo defensor aparecía
 * como "Natares aldea Aldea de la Maravilla" en un informe de batalla y como "Natars de
 * la aldea Aldea de la Maravilla" en uno de espionaje fallido (unknown_defender.tpl),
 * que sí lee la cuenta. Si mañana la cuenta natar se llama distinto, el informe tiene
 * que seguirla.
 *
 * Renderiza la plantilla de verdad y le mira la salida.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_report_natar_header.php
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
include "GeneratorX.php";
include "Lang/".LANG.".php";
include "Technology.php";

global $database, $generator, $technology;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

$natar = $database->query_return("SELECT id, username FROM ".TB_PREFIX."users WHERE tribe = 5 ORDER BY id ASC LIMIT 1");
if(!is_array($natar) || !isset($natar[0]['id'])) {
    fwrite(STDERR, "No hay ninguna cuenta natar en el mundo local.\n");
    exit(1);
}
$natarId = (int)$natar[0]['id'];
$natarName = $natar[0]['username'];

// Informe de batalla mínimo: sólo importan las posiciones que lee el encabezado.
// 30 = uid del defensor, 31 = wref de su aldea, 32 = nombre de la aldea,
// 129..139 = tropas defensoras natar, 140..150 = sus bajas.
$dataarray = array_fill(0, 160, '0');
$dataarray[30] = $natarId;
$dataarray[31] = 1;
$dataarray[32] = 'Aldea de la Maravilla';
$dataarray[33] = '5';
$targettribe = '5';
$faild = false;

ob_start();
include "Templates/Notice/tribe_5.tpl";
$html = ob_get_clean();

check(strpos($html, '>'.$natarName.'</a>') !== false,
    "el encabezado muestra el nombre de la cuenta ($natarName), no el de la tribu");
check(strpos($html, 'spieler.php?uid='.$natarId) !== false,
    "el nombre enlaza al perfil del defensor (uid $natarId)");
check(strpos($html, REPORT_FROM_VIL.' <a href="karte.php') !== false,
    'usa REPORT_FROM_VIL ("'.REPORT_FROM_VIL.'") antes del nombre de la aldea');
check(strpos($html, '</a> aldea <a') === false,
    'ya no queda el "aldea" suelto que rompía la frase');
check(strpos($html, REPORT_DEFENDER) !== false,
    'el rótulo del rol sale de REPORT_DEFENDER y no está escrito a mano');

// Las cinco plantillas de tribu tienen que resolver el nombre igual.
foreach(array(1, 2, 3, 5) as $tribe) {
    $source = file_get_contents("Templates/Notice/tribe_".$tribe.".tpl");
    check(strpos($source, 'getUserField($dataarray[30],"username",0)') !== false,
        "tribe_$tribe.tpl lee el nombre de la cuenta desde la base");
}

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
