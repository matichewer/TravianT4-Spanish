<?php
/**
 * Regression checker for the Natar source village used by WW attack waves.
 * Run with: php tools/check_natar_attacks.php
 */

$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$installer = file_get_contents(dirname(__DIR__).'/install/include/multihunter.php');

$failures = array();
function checkNatarAttack($condition, $message) {
    global $failures;
    echo ($condition ? '[OK] ' : '[FAIL] ').$message.PHP_EOL;
    if(!$condition) {
        $failures[] = $message;
    }
}

checkNatarAttack(strpos($automation, "'.natarsAccountId().'") !== false,
    'WW waves resolve their source through the shared Natar account resolver');
checkNatarAttack(strpos($automation, "`natar` ASC") !== false,
    'the non-WW Natar village is the fallback when no capital is marked');
checkNatarAttack(strpos($automation, "`owner` = 3 and `capital` = 1") === false,
    'WW waves no longer use the Nature account as their source');
checkNatarAttack(strpos($installer, 'SET capital = IF(wref = ') !== false,
    'new installations mark the central Natar village as capital');

// Una instalación nueva tiene que dejar las aldeas natar aprovisionadas: sin campos de
// cereal el balance nace en unos -45.000/h y la hambruna las vaciaba solas. El
// comportamiento se comprueba de verdad en tools/check_natar_starvation.php; acá sólo se
// fija que el instalador siga llamando al aprovisionamiento.
checkNatarAttack(strpos($installer, 'NatarVillage.php') !== false,
    'el instalador carga GameEngine/NatarVillage.php');
checkNatarAttack(substr_count($installer, 'natarProvisionVillage(') === 2,
    'el instalador aprovisiona la capital natar y las Aldeas de la Maravilla');
checkNatarAttack(strpos($installer, 'natarRestockGarrison($wid, natarCapitalGarrison())') !== false
    && strpos($installer, 'natarRestockGarrison($wid, natarWonderGarrison())') !== false,
    'las guarniciones natar salen de GameEngine/NatarVillage.php y no de SQL suelto');

// La hambruna no puede tocar aldeas NPC: la capital natar consume más cereal del que
// cualquier aldea puede producir, así que sin esta salida se desarma sola.
// La frontera vive en GameEngine/Accounts.php y tiene su propio checker
// (tools/check_npc_accounts.php); acá sólo se fija que estos dos caminos la usen. Desde
// que existen las aldeas natar vivas la exención es por ALDEA, no por cuenta: la cuenta
// Natars es dueña de las dos clases.
checkNatarAttack(strpos($automation, 'isStaticNpcVillage($starv)') !== false,
    'starvation() deja afuera a las guarniciones estáticas, no a toda la cuenta');
checkNatarAttack(strpos($automation, 'isStaticNpcVillage($villageRow)') !== false,
    'la producción de una guarnición estática no le cobra manutención');

exit(count($failures) ? 1 : 0);
