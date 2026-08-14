<?php
/**
 * Regression check: demolishing a building from its maximum level must still
 * queue a real timer, not finish instantly.
 *
 * addDemolition() used to call resourceRequired($field,$type) with the
 * default $plus=1, i.e. look up the cost/time of level+1. That index does
 * not exist once a building is already at its data table's max level
 * (e.g. Hideout/Escondite, type 23, tops out at level 10), so the lookup
 * returned null, the computed time collapsed to 0, and the demolition
 * "finished" on the very next Automation sweep instead of taking real time.
 */

$failures = 0;
$checks = 0;
function demolitionTimerCheck($condition, $message) {
    global $failures, $checks;
    $checks++;
    if(!$condition) {
        $failures++;
        fwrite(STDERR,"FAIL: ".$message."\n");
    }
}

$root = dirname(__DIR__);
$database = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');

$start = strpos($database,'function addDemolition($wid, $field)');
$end = strpos($database,'function claimDemolition',$start);
$add = ($start !== false && $end !== false) ? substr($database,$start,$end-$start) : '';

demolitionTimerCheck(strpos($add,'resourceRequired($field,$type,0)') !== false,
    'addDemolition debe pedir el costo del nivel actual (plus=0), no el del nivel+1');
demolitionTimerCheck(strpos($add,'resourceRequired($field,$type)') === false,
    'addDemolition no debe volver a usar el plus=1 por defecto (indice fuera de rango en el nivel maximo)');

require $root.'/GameEngine/Data/buidata.php';

// Solares del Edificio Principal demolibles: campos 19-40 (bid40 = Maravilla, no demolible).
for($type = 10; $type <= 42; $type++) {
    if($type === 13 || $type === 40) {
        continue;
    }
    $name = 'bid'.$type;
    if(!isset($$name)) {
        continue;
    }
    $dataarray = $$name;
    $maxLevel = count($dataarray);
    demolitionTimerCheck(isset($dataarray[$maxLevel]['time']) && (int)$dataarray[$maxLevel]['time'] > 0,
        "bid$type: el nivel maximo ($maxLevel) debe tener un tiempo de construccion valido para usar como base del temporizador de demolicion");
    demolitionTimerCheck(!isset($dataarray[$maxLevel + 1]),
        "bid$type: no deberia existir nivel ".($maxLevel + 1)." (confirma que plus=1 se sale del arreglo en el nivel maximo)");
}

echo "Demolition timer checks: $checks; failures: $failures\n";
exit($failures === 0 ? 0 : 1);
