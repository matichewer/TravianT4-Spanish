<?php
$root = dirname(__DIR__);
$entry = file_get_contents($root.'/berichte.php');
$templates = array('all', 't_1', 't_2', 't_3', 't_4', 't_5');

if(strpos($entry, 'array(10, 20, 50, 100)') === false
	|| strpos($entry, "\$_SESSION['reports_per_page']") === false) {
	fwrite(STDERR, "La entrada de informes no valida ni conserva la cantidad por página.\n");
	exit(1);
}

foreach($templates as $template) {
	$source = file_get_contents($root.'/Templates/Notice/'.$template.'.tpl');
	if(strpos($source, '$itemsPerPage = $reportsPerPage;') === false) {
		fwrite(STDERR, "$template.tpl no usa la cantidad elegida.\n");
		exit(1);
	}
	if(strpos($source, 'per_page.tpl') === false) {
		fwrite(STDERR, "$template.tpl no muestra el selector.\n");
		exit(1);
	}
}

echo "Report pagination selector checks passed.\n";
