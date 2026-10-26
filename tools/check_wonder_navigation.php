<?php
/** Run the actual build.php field validation, including its early Wonder gate. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once dirname(__DIR__).'/GameEngine/Wonder.php';
$source = file_get_contents(dirname(__DIR__).'/build.php');
$start = strpos($source, "if(isset(\$_GET['id']) && is_scalar");
$end = strpos($source, '{', $start);
$gate = substr($source, $start + 3, $end - $start - 4);
$start = strpos($source, "if(isset(\$_GET['id'])) {");
$end = strpos($source, "\tif(\$village->resarray", $start);
if ($start === false || $end === false || $gate === '') {
    throw new RuntimeException('Cannot locate build.php routing');
}
$validation = substr($source, $start, $end - $start)."\n}";
$checks = 0;
foreach (array(false, true) as $wonder) {
    // The legacy natar flag must not decide whether this village has a Wonder.
    foreach (array(0, 1) as $natar) {
        $village = (object)array('natar' => $natar, 'resarray' => array('f99t' => $wonder ? 40 : 0));
        foreach (array('1', '18', '19', '39', '40', '99', '0', '41', '98', '100', '-1', 'x', '99x', array('99')) as $input) {
            $_GET = array('id' => $input);
            $redirect = eval('return '.$gate.';');
            $expectedRedirect = $input === '99' && !$wonder;
            if ($redirect !== $expectedRedirect) {
                throw new RuntimeException('Incorrect early Wonder redirect');
            }
            eval($validation);
            $expected = is_string($input) && (in_array($input, array('1','18','19','39','40'), true) || ($input === '99' && $wonder)) ? $input : '1';
            if ($_GET['id'] !== $expected) {
                throw new RuntimeException('Wrong building destination: '.json_encode(array($wonder, $natar, $input, $_GET['id'])));
            }
            $checks++;
        }
    }
}
echo "PASS: $checks building destinations, including Wonder and rally point\n";
