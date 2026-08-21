<?php
$source = file_get_contents(dirname(__DIR__).'/Templates/Alliance/attacks.tpl');

$expectations = array(
    'array(10, 20, 50, 100)' => 'No están definidas las cantidades permitidas.',
    "\$_SESSION['alliance_events_per_page']" => 'La preferencia no se conserva en la sesión.',
    '$allianceEventsPerPage' => 'La consulta no usa la cantidad seleccionada.',
    'Eventos por página:' => 'No se muestra el selector.',
    '&per_page=' => 'El selector no envía la cantidad elegida.',
);

foreach($expectations as $needle => $error) {
    if(strpos($source, $needle) === false) {
        fwrite(STDERR, $error."\n");
        exit(1);
    }
}

$paginatorPosition = strpos($source, '<?php echo $allianceEventPaginator(); ?>');
$selectorPosition = strpos($source, '<div class="allianceEventsPerPage">');
if($paginatorPosition === false || $selectorPosition === false || $paginatorPosition > $selectorPosition) {
    fwrite(STDERR, "El selector debe quedar visualmente a la izquierda del paginador flotante.\n");
    exit(1);
}

echo "Alliance event pagination selector checks passed.\n";
