<?php
$root = dirname(__DIR__);
$berichte = file_get_contents($root . '/berichte.php');
$failures = array();

$assert = function ($condition, $message) use (&$failures) {
	if(!$condition) {
		$failures[] = $message;
	}
};

$assert(strpos($berichte, 'function reportThousands(value)') !== false,
	'Falta el formateador central de miles de los informes.');
$assert(strpos($berichte, "digits.replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')") !== false,
	'El formateador no usa el punto como separador de miles.');
$assert(strpos($berichte, '#content.reports td.unit:not(.uniticon)') !== false,
	'Las cantidades de tropas no pasan por el formateador.');
$assert(strpos($berichte, '#content.reports .rArea') !== false,
	'Los recursos no pasan por el formateador.');
$assert(strpos($berichte, '#content.reports div.carry') !== false,
	'La capacidad visible del botín no pasa por el formateador.');
$assert(strpos($berichte, "div.carry img[title]") !== false,
	'La ayuda emergente de capacidad del botín no pasa por el formateador.');

if($failures) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}

echo "Report number format: OK (troops, resources and carry capacity).\n";
