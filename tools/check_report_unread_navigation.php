<?php

$root = dirname(__DIR__);
$assert = function($condition, $message) {
    if(!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$berichte = file_get_contents($root . '/berichte.php');
$message = file_get_contents($root . '/GameEngine/Message.php');
$database = file_get_contents($root . '/GameEngine/Database/db_MYSQLi.php');
$tradeList = file_get_contents($root . '/Templates/Notice/t_2.tpl');
$routeList = file_get_contents($root . '/Templates/Notice/t_7.tpl');
$navigation = file_get_contents($root . '/Templates/navigation.tpl');
$html = file_get_contents($root . '/Templates/html.tpl');
$compact = file_get_contents($root . '/gpack/travian_Travian_4.0_41/lang/ir/compact.css');
$css = file_get_contents($root . '/gpack/travian_Travian_4.0_41/lang/ir/compact1.css');

$assert(strpos($berichte, "array('1', '2', '3', '4', '5', '6', '7', '8')") !== false, 'The unread report filter is not accepted by the controller.');
$assert(strpos($berichte, 'berichte.php?t=8') !== false && strpos($berichte, 'No leídos') !== false, 'The No leídos tab is missing.');
$assert(strpos($berichte, '$noticeSqlFilter = "and viewed = 0"') !== false, 'The No leídos tab is not restricted to unread reports.');
$assert(strpos($berichte, '.reportsNavi { display: flex; width: 597px; padding-left: 0; padding-right: 0; }') !== false, 'The report tab background does not cover the complete navigation width.');
$assert(strpos($berichte, 'flex: 1 1 auto') !== false && strpos($berichte, 'text-align: center') !== false, 'The report tabs do not allocate width according to their labels.');

$assert(strpos($tradeList, '&amp;t=".$reportFilter.') !== false, 'The shared trade/route list still hardcodes its report detail filter.');
$assert(strpos($tradeList, '&amp;t=2') === false, 'A hardcoded Commerce detail link remains in the shared route list.');
$assert(strpos($routeList, 'include("Templates/Notice/t_2.tpl")') !== false, 'The route list no longer exercises the shared-filter link path.');
$assert(strpos($database, '8 => "archive = 0 AND (viewed = 0 OR id = $id)"') !== false, 'Unread detail neighbors cannot locate the report after it is marked read.');
$assert(strpos($database, '7 => "archive = 0 AND (ntype = 26') !== false, 'Route detail navigation is not restricted to route reports.');
$assert(strpos($message, "if(\$get['t'] == 7)") !== false, 'Message report filtering does not recognize Rutas.');

$assert(strpos($navigation, "'adventure' => 'Aventura'") !== false, 'Adventure badges have no navigation label.');
$assert(strpos($css, '.report-badge-trade .report-badge-background{background-color:#555;}') !== false, 'Trade badges are not dark gray.');
$assert(strpos($css, '.report-badge-routes{color:#555;background:transparent!important;border-radius:8px;}') !== false, 'The route badge container compounds the translucent background.');
$assert(strpos($css, '.report-badge-routes .report-badge-background{background:rgba(80,80,80,.25)!important;}') !== false, 'The route badge background layers are not light and translucent.');
$assert(strpos($html, 'compact.css?asd485') !== false && strpos($compact, 'compact1.css?v=71') !== false, 'The two stylesheet cache-busters were not advanced with the route badge style.');
$assert(strpos($css, '.report-badge-adventure .report-badge-background{background-color:#7eaa16;}') !== false, 'Adventure badges are not green.');
$assert(substr_count($css, 'background-color:#7eaa16;') === 1, 'Adventure green is assigned to another report category.');

echo "Unread report navigation: OK (filter, layout, route context, neighbor scope, semantic colors).\n";

?>
