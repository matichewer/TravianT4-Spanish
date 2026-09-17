<?php
// Render both real templates against a deterministic world; no live game writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require 'GameEngine/MapZoom.php';
require 'GameEngine/GreyZone.php';
define('WORLD_MAX', 100);
define('TB_PREFIX', 'zoom_');
set_error_handler(function($severity, $message, $file, $line) {
    throw new RuntimeException("$message at $file:$line");
});
function checkMapZoom($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
}
class ZoomGenerator {
    function getBaseID($x, $y) { return ($y+100)*201+$x+101; }
}
class ZoomDatabase {
    public $queries = 0;
    function query_return($sql) {
        $this->queries++;
        if (!preg_match('/WHERE w.id IN \(([^)]+)\)/', $sql, $matches)) { return array(); }
        $rows = array();
        foreach (array_reverse(explode(',', $matches[1])) as $id) {
            $rows[] = array('x'=>((int)$id-1)%201-100, 'y'=>intdiv((int)$id-1,201)-100, 'id'=>$id,
                'occupied'=>0, 'fieldtype'=>1, 'oasistype'=>0, 'image'=>'t0');
        }
        return $rows;
    }
}
$database = new ZoomDatabase();
$generator = new ZoomGenerator();
$session = (object)array('plus'=>true, 'goldclub'=>false, 'alliance'=>0, 'uid'=>10);
$village = (object)array('wid'=>1, 'coor'=>array('x'=>0,'y'=>0));
foreach (array(-1, 4, '999999', array(1), '1.5', null) as $invalid) {
    checkMapZoom(mapZoomLevel($invalid) === 0, 'Invalid zoom must use the default');
}
foreach (array('mapview.tpl', 'mapviewlarge.tpl') as $template) {
    foreach (array(0, 1, 2, 3) as $level) {
        foreach (array(0, 100, -100) as $centre) {
            $_GET = array('x'=>$centre, 'y'=>$centre, 'zoom'=>$level);
            $_POST = array();
            $database->queries = 0;
            ob_start();
            include 'Templates/Map/'.$template;
            $html = ob_get_clean();
            checkMapZoom(strpos($html, '<div class="coordinate zoom1"></div>') === false, 'Unlabelled ruler cells must retain their layout space');
            checkMapZoom($database->queries === 2, 'Tile/marker queries must remain constant at every zoom');
            checkMapZoom($VCOLS*$TILE >= $VIEW_WIDTH && $VROWS*$TILE >= $VIEW_HEIGHT, 'Viewport must be filled');
            checkMapZoom($VHX*$TILE + $TILE/2 - $OFFSET_X == $VIEW_WIDTH/2, 'X centre must remain fixed');
            checkMapZoom($VHY*$TILE + $TILE/2 - $OFFSET_Y == $VIEW_HEIGHT/2, 'Y centre must remain fixed');
            checkMapZoom($xarray[$VHX] == $centre && $yarray[$VHY] == $centre, 'Rulers must match the centre');
            checkMapZoom(min($xfull) >= -100 && max($xfull) <= 100 && min($yfull) >= -100 && max($yfull) <= 100, 'Wrap at world edges');
            checkMapZoom(substr_count($html, 'transform-origin:top left;') === $COLS*$ROWS, 'Every tile has scaled positioning');
            checkMapZoom(strpos($html, 'left:'.$TILE.'px;top:0px;transform:scale(') !== false, 'Adjacent tiles must meet without gaps');
            checkMapZoom(strpos($html, 'name="zoom" value="'.$level.'"') !== false, 'Coordinate form must preserve zoom');
            checkMapZoom(strpos($html, '?zoom='.$level.'&x=\'+nx') !== false, 'Dragging must preserve zoom');
            checkMapZoom(substr_count($html, 'aria-disabled="true"') === ($level === 0 || $level === 3 ? 1 : 0), 'Disable the control at each limit');
        }
        echo "[OK] $template zoom=$level: geometry, controls, navigation and world edges\n";
    }
}
