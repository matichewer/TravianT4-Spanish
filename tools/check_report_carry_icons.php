<?php

$root = dirname(__DIR__);
$expectedResources = '$dataarray[25]+$dataarray[26]+$dataarray[27]+$dataarray[28]';
$expectedCapacity = '$dataarray[29]';
$templates = array('all.tpl', 't_1.tpl', 't_2.tpl', 't_3.tpl', 't_4.tpl');

foreach($templates as $template) {
    $source = file_get_contents($root.'/Templates/Notice/'.$template);
    if($source === false) {
        fwrite(STDERR, "No se pudo leer $template\n");
        exit(1);
    }

    if(substr_count($source, $expectedResources) < 3 || substr_count($source, $expectedCapacity) < 2) {
        fwrite(STDERR, "$template no usa los campos canonicos de botin (25-28) y capacidad (29)\n");
        exit(1);
    }
}

echo "Report carry icons use the canonical loot fields\n";
