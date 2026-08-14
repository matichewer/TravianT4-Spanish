<?php
/** Regression checks for the Main Building controlled-demolition flow. */

$failures = 0;
$checks = 0;
function demolitionCheck($condition, $message) {
    global $failures, $checks;
    $checks++;
    if(!$condition) {
        $failures++;
        fwrite(STDERR,"FAIL: ".$message."\n");
    }
}

$root = dirname(__DIR__);
$template = file_get_contents($root.'/Templates/Build/15_1.tpl');
$mainView = file_get_contents($root.'/Templates/Build/15.tpl');
$database = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');
$automation = file_get_contents($root.'/GameEngine/Automation.php');

demolitionCheck(strpos($mainView,"DEMOLISH_LEVEL_REQ") !== false,
    'la vista debe respetar el requisito configurable');
demolitionCheck(strpos($template,"\$_REQUEST") === false,
    'iniciar y cancelar no deben aceptar indistintamente GET y POST');
demolitionCheck(substr_count($template,"hash_equals") === 2,
    'inicio y cancelacion deben validar CSRF');
demolitionCheck(strpos($template,"build.php?id=26&cancel=1") === false,
    'la cancelacion no debe depender de un solar fijo');
demolitionCheck(strpos($template,'for($i = 19; $i <= 40; $i++)') !== false,
    'la lista debe recorrer solamente solares interiores validos');
demolitionCheck(strpos($template,'getMasterJobsByField') !== false
    && strpos($template,'getBuildingByField') !== false,
    'la vista debe ocultar edificios con mejoras pendientes');

$start = strpos($database,'function addDemolition($wid, $field)');
$end = strpos($database,'function claimDemolition',$start);
$add = ($start !== false && $end !== false) ? substr($database,$start,$end-$start) : '';
demolitionCheck(strpos($add,'$field < 19 || $field > 40') !== false,
    'el servidor debe rechazar campos y solares fuera del centro');
demolitionCheck(strpos($add,'DEMOLISH_LEVEL_REQ') !== false,
    'el servidor debe validar el nivel del Edificio Principal');
demolitionCheck(strpos($add,'getBuildingByField') !== false
    && strpos($add,'getMasterJobsByField') !== false,
    'el servidor debe rechazar una demolicion solapada con cualquier cola');
demolitionCheck(strpos($add,'DELETE FROM ".TB_PREFIX."bdata') === false,
    'iniciar una demolicion nunca debe borrar trabajos encolados');
demolitionCheck(strpos($add,'GET_LOCK') !== false && strpos($add,'RELEASE_LOCK') !== false,
    'dos solicitudes simultaneas deben serializarse por aldea');
demolitionCheck(strpos($database,'mysqli_affected_rows($this->connection) === 1') !== false
    && strpos($automation,"claimDemolition") !== false,
    'solo una peticion debe completar cada demolicion vencida');
demolitionCheck(strpos($automation,'$this->recountPop($vil[\'vref\']);') !== false,
    'la finalizacion debe recalcular poblacion y puntos de cultura');

echo "Main Building demolition checks: $checks; failures: $failures\n";
exit($failures === 0 ? 0 : 1);
