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

checkNatarAttack(strpos($automation, "u.`username` = \\'Natars\\'") !== false,
    'WW waves resolve their source through the Natars account');
checkNatarAttack(strpos($automation, "v.`natar` ASC") !== false,
    'the non-WW Natar village is the fallback when no capital is marked');
checkNatarAttack(strpos($automation, "`owner` = 3 and `capital` = 1") === false,
    'WW waves no longer use the Nature account as their source');
checkNatarAttack(strpos($installer, 'SET capital = IF(wref = ') !== false,
    'new installations mark the central Natar village as capital');

exit(count($failures) ? 1 : 0);
