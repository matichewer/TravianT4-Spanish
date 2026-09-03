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
		array(strpos($template, '$odata[\'conqured_name\']') !== false,
			'resuelve la aldea a la que está anexado el oasis'),
		array(strpos($template, '<br>Aldea: ".$oasisVillageName.') !== false,
			'muestra la aldea anexadora en el tooltip'),
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

$databaseSource = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
if(strpos($databaseSource, 'AS conqured_name') === false
	|| strpos($databaseSource, 'odata.conqured LIMIT 1') === false) {
	fwrite(STDERR, "FAIL getOMInfo: no trae el nombre de la aldea anexadora\n");
	exit(1);
}
echo "OK getOMInfo: trae el nombre de la aldea anexadora en la consulta del oasis\n";

// El bono del oasis se describe en un solo lugar: los dos mapas y el detalle de casilla
// tenían cada uno su copia del switch de los 12 tipos, sin nada que los atara al reparto
// que efectivamente cobra la producción (villageOasisCounter, en Production.php).
$sources = array(
	'Templates/Map/mapview.tpl' => 'oasisBonusTooltip($maparray[$index][\'oasistype\'])',
	'Templates/Map/mapviewlarge.tpl' => 'oasisBonusTooltip($maparray[$index][\'oasistype\'])',
	'Templates/Map/vilview.tpl' => 'oasisBonusDistributionRows($basearray[\'oasistype\'])'
);
foreach($sources as $relativePath => $call) {
	$template = file_get_contents($root.'/'.$relativePath);
	if(strpos($template, $call) === false) {
		fwrite(STDERR, 'FAIL '.$relativePath.": no describe el oasis con la definición única\n");
		exit(1);
	}
	echo 'OK '.$relativePath.": describe el oasis con la definición única\n";
	if(preg_match('/Madera 25%|Barro 25%|Hierro 25%|Cereal 50%/', $template)) {
		fwrite(STDERR, 'FAIL '.$relativePath.": conserva su copia del switch de tipos de oasis\n");
		exit(1);
	}
	echo 'OK '.$relativePath.": ya no conserva su copia del switch de tipos\n";
}

echo "Oasis map tooltip ownership: OK\n";
