<?php
/*
 * Renderiza el informe de captura con jaulas (Templates/Notice/25.tpl) fuera del
 * navegador, para comprobar que compila y no deja variables sin definir.
 * Uso: php tools/test_cage_report_render.php
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
    "SELECT * FROM " . TB_PREFIX . "ndata WHERE ntype = 25 ORDER BY id DESC LIMIT 1"
));
if(!$row) {
    die("No hay ningún informe ntype=25. Corré antes tools/test_cage_capture.php\n");
}
$_GET['id'] = $row['id'];

$message = new stdClass;
$message->readingNotice = $row;

$errors = array();
set_error_handler(function($no, $str, $file, $line) use (&$errors) {
    $errors[] = "$str  ($file:$line)";
    return true;
});

ob_start();
include "Templates/Notice/25.tpl";
$html = ob_get_clean();

restore_error_handler();

echo "topic: " . $row['topic'] . "\n";
echo "data : " . $row['data'] . "\n\n";

if($errors) {
    echo "AVISOS/ERRORES DE PHP:\n";
    foreach($errors as $e) { echo "  - $e\n"; }
} else {
    echo "Sin avisos de PHP.\n";
}

echo "\nHTML: " . strlen($html) . " bytes\n";

// Resumen legible del contenido renderizado
$text = preg_replace('/\s+/', ' ', strip_tags($html));
echo "texto: " . trim($text) . "\n";

// ------------------------------------------- listado de informes (Todos / Ataque)
foreach(array('all' => array(), 't_1' => array('t' => 1)) as $tpl => $get) {
    echo "\n== Templates/Notice/$tpl.tpl ==\n";
    $_GET = $get;
    $errors = array();
    set_error_handler(function($no, $str, $file, $line) use (&$errors) {
        $errors[] = "$str  ($file:$line)";
        return true;
    });
    ob_start();
    include "Templates/Notice/$tpl.tpl";
    $list = ob_get_clean();
    restore_error_handler();

    if($errors) {
        echo "AVISOS/ERRORES DE PHP:\n";
        foreach($errors as $e) { echo "  - $e\n"; }
    } else {
        echo "Sin avisos de PHP.\n";
    }
    $found = strpos($list, 'Animales capturados') !== false;
    echo ($found ? "  [ok]   " : "  [FALLA] ") . "el informe de captura aparece en el listado\n";
    if(preg_match('/iReport iReport(\d+)[^>]*Animales capturados/', $list, $m)) {
        echo "  icono: iReport{$m[1]}\n";
    }
}
