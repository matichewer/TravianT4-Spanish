<?php
/*
 * Renderiza el informe de refuerzo (Templates/Notice/8.tpl) fuera del navegador para un
 * refuerzo enviado a un oasis, y comprueba que el destinatario muestre nombre y
 * coordenadas en vez de quedar en blanco (getVillageField no tiene fila para un oasis).
 * Uso: php tools/test_reinforcement_report_render.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root . PATH_SEPARATOR . $root . '/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = array();
$_GET = array('id' => 1);

include "config/connection.php";
include "config/config.php";
include "Database.php";
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/" . LANG . ".php";
include "Technology.php";

global $database, $generator, $technology;

$session = new stdClass;
$session->plus = 0;
$session->tribe = 1;
$session->uid = 5;

$row = mysqli_fetch_assoc(mysqli_query(
    $database->connection,
    "SELECT * FROM " . TB_PREFIX . "ndata WHERE ntype = 8 AND `data` NOT LIKE 'visual-report-%' ORDER BY id DESC LIMIT 1"
));
if(!$row) {
    die("No hay ningún informe ntype=8 real (no fixture). Generá uno reforzando un oasis.\n");
}
$_GET['id'] = $row['id'];

$dataarray = explode(",", $row['data']);
$targetWref = (int)$dataarray[14];
if($database->isVillageOases($targetWref) == 0) {
    die("El informe más reciente (id={$row['id']}) no apunta a un oasis (wref=$targetWref); no prueba el caso.\n");
}

$message = new stdClass;
$message->readingNotice = $row;

$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) {
    $errors[] = "$str  ($file:$line)";
    return true;
});

ob_start();
include "Templates/Notice/8.tpl";
$html = ob_get_clean();

restore_error_handler();

echo "topic: " . $row['topic'] . "\n";
echo "data : " . $row['data'] . "\n";

if($errors) {
    echo "AVISOS/ERRORES DE PHP:\n";
    foreach($errors as $e) { echo "  - $e\n"; }
    exit(1);
}
echo "Sin avisos de PHP.\n";

$expectedCoor = $database->getCoor($targetWref);
$expectedName = $database->getOasisField($targetWref, 'name') . " (" . $expectedCoor['x'] . "|" . $expectedCoor['y'] . ")";

if(strpos($html, htmlspecialchars($expectedName)) === false && strpos($html, $expectedName) === false) {
    fwrite(STDERR, "FALLA: el HTML no incluye el nombre+coordenadas del oasis destino ('$expectedName')\n");
    exit(1);
}
echo "OK: el destinatario del informe muestra '$expectedName'\n";

if(strpos($html, "karte.php?d=$targetWref&amp;c=") === false) {
    fwrite(STDERR, "FALLA: falta el link al mapa del oasis destino\n");
    exit(1);
}
echo "OK: el destinatario enlaza a karte.php?d=$targetWref\n";

echo "\nHTML: " . strlen($html) . " bytes\n";
