<?php

$root = dirname(__DIR__);
$templates = array(
	'Templates/Map/mapview.tpl',
	'Templates/Map/mapviewlarge.tpl'
);

foreach($templates as $relativePath) {
	$template = file_get_contents($root.'/'.$relativePath);
	$checks = array(
		array(strpos($template, '$allyname = \'-\';') !== false,
			'reinicia la alianza antes de renderizar cada casilla'),
		array(strpos($template, '$tribename = \'-\';') !== false,
			'reinicia la tribu antes de renderizar cada casilla'),
		array(strpos($template, '$odata = $database->getOMInfo($maparray[$index][\'id\']);') !== false,
			'consulta los datos específicos del oasis'),
		array(strpos($template, '$tileowner = (int)$odata[\'owner\'];') !== false,
			'usa al propietario real del oasis'),
		array(strpos($template, '$targetalliance = $database->getUserField($tileowner,"alliance",0);') !== false,
			'resuelve la alianza desde el propietario seleccionado'),
		array(strpos($template, '$tribe = $database->getUserField($tileowner,"tribe",0);') !== false,
			'resuelve la tribu desde el propietario seleccionado'),
		array(strpos($template, '$uinfo = $username;') !== false,
			'mantiene jugador, alianza y tribu sobre la misma identidad')
	);

	foreach($checks as $check) {
		list($passed, $message) = $check;
		if(!$passed) {
			fwrite(STDERR, 'FAIL '.$relativePath.': '.$message."\n");
			exit(1);
		}
		echo 'OK '.$relativePath.': '.$message."\n";
	}
}

echo "Oasis map tooltip ownership: OK\n";
