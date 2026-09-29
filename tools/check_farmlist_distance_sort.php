<?php
/** Distancias actuales desde el origen de la lista, no desde la aldea abierta. */
if(PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
define('WORLD_MAX', 100);
require_once dirname(__DIR__).'/GameEngine/NatarSettlement.php';
require_once dirname(__DIR__).'/Templates/Build/27_rows.tpl';
define('TB_PREFIX', 'test_');
class FarmDistanceFixture {
    function getCoor($id) {
        $coords = array(1 => array('x'=>38, 'y'=>-19), 2 => array('x'=>42, 'y'=>-28),
            3 => array('x'=>34, 'y'=>-30), 4 => array('x'=>48, 'y'=>-19));
        return $coords[$id];
    }
}
$database = new FarmDistanceFixture();
function mysql_query($sql) { return true; }
function mysql_fetch_array($result) {
    static $rows = array(
        array('id'=>7, 'towref'=>3, 'distance'=>'1'),
        array('id'=>5, 'towref'=>4, 'distance'=>'999'),
        array('id'=>9, 'towref'=>2, 'distance'=>'100'),
        array('id'=>3, 'towref'=>2, 'distance'=>'0')
    );
    return array_shift($rows);
}
$template = file_get_contents(dirname(__DIR__).'/Templates/goldClub/farmlist.tpl');
$start = strpos($template, '$sql2 = mysql_query(');
$end = strpos($template, '$query2 = count($farmRows);', $start);
$lid = 1; $lwref = 1;
eval(substr($template, $start, $end-$start));
if(array_column($farmRows, 'id') !== array(3,9,5,7)) {
    fwrite(STDERR, "FAIL: orden numérico actual y desempate por id.\n"); exit(1);
}
foreach($farmRows as $row) {
    $treasury = treasuryArtefactDistance(array('vref'=>$row['towref']), $database->getCoor(1));
    if(abs($row['distance']-$treasury) > 0.000001) {
        fwrite(STDERR, "FAIL: tesoro y granjas difieren.\n"); exit(1);
    }
}
if(abs(natarSettlementDistance(100,100,-100,-100)-sqrt(2)) > 0.000001) {
    fwrite(STDERR, "FAIL: distancia en los bordes.\n"); exit(1);
}
foreach(array('addraid','editraid') as $action) {
    $source = file_get_contents(dirname(__DIR__).'/Templates/goldClub/farmlist_'.$action.'.tpl');
    if(strpos($source, "getCoor((int)\$distanceList['wref'])") === false || strpos($source, 'natarSettlementDistance(') === false) {
        fwrite(STDERR, "FAIL: el formulario no usa el origen de la lista.\n"); exit(1);
    }
}
echo "OK: distancias actuales iguales al tesoro, orden estable y bordes del mapa.\n";
