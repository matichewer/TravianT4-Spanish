<?php

$template = file_get_contents(dirname(__DIR__).'/Templates/Notice/8.tpl');

$checks = array(
	array(strpos($template, 'karte.php?z=') === false,
		'el informe ya no usa el parámetro de mapa incorrecto z'),
	array(strpos($template, '$dataarray[0]."&amp;c=".$generator->getMapCheck($dataarray[0])') !== false,
		'la aldea de origen enlaza al mapa con su comprobación'),
	array(strpos($template, '$dataarray[14]."&amp;c=".$generator->getMapCheck($dataarray[14])') !== false,
		'el destino reforzado enlaza al mapa con su comprobación')
);

foreach($checks as $check) {
	list($passed, $message) = $check;
	if(!$passed) {
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
	echo "OK: ".$message."\n";
}

echo "Reinforcement report links: OK\n";
