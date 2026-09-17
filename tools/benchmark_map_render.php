<?php
// Read-only benchmark: render the map against local data without Session/Automation.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
chdir($root);
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
date_default_timezone_set('America/Argentina/Buenos_Aires');
$_SESSION = array();
require 'GameEngine/Database.php';
require 'GameEngine/GeneratorX.php';
require 'GameEngine/GreyZone.php';
$rows = $database->query_return('SELECT v.wref, w.x, w.y, u.id, u.alliance FROM '.TB_PREFIX.'vdata v JOIN '.TB_PREFIX.'users u ON u.id = v.owner JOIN '.TB_PREFIX.'wdata w ON w.id = v.wref WHERE '.playerAccountSql('owner').' ORDER BY v.wref LIMIT 1');
if (!$rows) { fwrite(STDERR, "No player village available\n"); exit(1); }
$viewer = $rows[0];
$session = (object)array('uid'=>(int)$viewer['id'], 'alliance'=>(int)$viewer['alliance'], 'plus'=>true, 'goldclub'=>false);
$village = (object)array('wid'=>(int)$viewer['wref'], 'coor'=>array('x'=>$viewer['x'], 'y'=>$viewer['y']));
function mapBenchmarkQueries() {
    global $database;
    $rows = $database->query_return("SHOW SESSION STATUS LIKE 'Questions'");
    return (int)$rows[0]['Value'];
}
foreach (array('mapview.tpl', 'mapviewlarge.tpl') as $template) {
    foreach (array(0, 1, 2, 3) as $level) {
        $times = array();
        for ($sample = 0; $sample < 3; $sample++) {
            $_GET = array('x'=>0, 'y'=>0, 'zoom'=>$level);
            $_POST = array();
            $before = mapBenchmarkQueries();
            $start = microtime(true);
            ob_start();
            include 'Templates/Map/'.$template;
            $html = ob_get_clean();
            $times[] = (microtime(true)-$start)*1000;
            $queries = mapBenchmarkQueries()-$before-1;
        }
        sort($times);
        printf("%s zoom=%d tiles=%d queries=%d median=%.1fms html=%dKB\n", $template, $level, $COLS*$ROWS, $queries, $times[1], strlen($html)/1024);
    }
}
