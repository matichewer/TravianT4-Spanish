<?php
/**
 * Regression checker for the shared marketplace/rally-point quick target list.
 *
 * Run with:
 *   docker compose exec -T web php /var/www/html/tools/check_quick_target_order.php
 */

$checks = 0;
$fails = array();

function checkQuickTargetOrder($condition,$message) {
	global $checks,$fails;
	$checks++;
	if(!$condition) {
		$fails[] = $message;
		echo "FAIL: $message\n";
		return;
	}
	echo "OK: $message\n";
}

$root = dirname(__DIR__);
$quickTargetTemplate = file_get_contents($root.'/Templates/quick_target_select.tpl');
$marketTemplate = file_get_contents($root.'/Templates/Build/17.tpl');
$rallyPointTemplate = file_get_contents($root.'/Templates/a2b/search.tpl');

checkQuickTargetOrder(
	strpos($quickTargetTemplate,'foreach($session->villages as $quickTargetVillageId)') !== false,
	'el selector recorre el mismo orden de aldeas que la barra lateral'
);
checkQuickTargetOrder(
	strpos($quickTargetTemplate,'$quickTargetOwnById[(int)$quickTargetVillage[\'wref\']]') !== false,
	'los datos de nombre y coordenadas se indexan sin alterar ese orden'
);
checkQuickTargetOrder(
	strpos($marketTemplate,'include("Templates/quick_target_select.tpl")') !== false,
	'el mercado usa el selector compartido'
);
checkQuickTargetOrder(
	strpos($rallyPointTemplate,'include("Templates/quick_target_select.tpl")') !== false,
	'la plaza de reuniones usa el selector compartido'
);

if(empty($fails)) {
	echo "Quick target order checks passed ($checks comprobaciones).\n";
	exit(0);
}

echo count($fails)." de $checks comprobaciones fallaron.\n";
exit(1);
