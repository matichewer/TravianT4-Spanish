<?php
// Exercise the page's preselection and render its actual mission radio controls.
$root = dirname(__DIR__);
$source = file_get_contents($root.'/a2b.php');
$start = strpos($source, '$selectedAttackType =');
$end = strpos($source, '$process =', $start);
$selection = substr($source, $start, $end - $start);
$template = file_get_contents($root.'/Templates/a2b/search.tpl');
$start = strpos($template, '<div class="option">');
$end = strpos($template, '<div class="clear">', $start);
$radios = substr($template, $start, $end - $start);
foreach (array(
    array(null, '', 2),
    array(null, 'checked=checked', 4),
    array(array('legacy'), '', 3),
    array(array('legacy'), 'checked=checked', 3),
    array(array('attack-type-v1', '3'), '', 3),
    array(array('attack-type-v1', '4'), '', 4),
    array(array('heal-v1', '5', 'attack-type-v1', '4'), '', 4),
    array(array('attack-type-v1', '2'), '', 3),
    array(array('attack-type-v1'), '', 3),
) as $case) {
    list($reportdata, $checked, $expected) = $case;
    $disabledr = $disabled = '';
    eval($selection);
    ob_start();
    eval('?>'.$radios);
    $html = ob_get_clean();
    preg_match_all('/<input[^>]*>/', $html, $inputs);
    $selected = array();
    foreach ($inputs[0] as $input) {
        if (strpos($input, 'checked=checked') !== false) {
            preg_match('/value="(\d+)"/', $input, $value);
            $selected[] = (int)$value[1];
        }
    }
    if ($selected !== array($expected)) {
        fwrite(STDERR, 'Wrong mission selection: '.json_encode($case)."\n");
        exit(1);
    }
}
$automation = file_get_contents($root.'/GameEngine/Automation.php');
foreach (array('$data2att', '$data_fail') as $payload) {
    if (strpos($automation, $payload." .= ',attack-type-v1,'.(int)\$data['attack_type'];") === false) {
        fwrite(STDERR, "Missing mission metadata for $payload\n");
        exit(1);
    }
}
echo "Repeat attack checks passed.\n";
