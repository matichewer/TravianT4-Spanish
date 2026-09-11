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
	strpos($quickTargetTemplate,'foreach($database->getVillagesIDByFoundation($session->uid) as $quickTargetVillageId)') !== false,
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

// Render con capital/nombres en un orden distinto al de fundación.
$database = new class {
    function getOwnVillagesWithCoor($uid) {
        return array(
            array('wref'=>30,'name'=>'Capital','x'=>3,'y'=>0),
            array('wref'=>20,'name'=>'Antigua','x'=>2,'y'=>0),
            array('wref'=>10,'name'=>'Actual','x'=>1,'y'=>0)
        );
    }
    function getVillagesIDByFoundation($uid) { return array(20,10,30); }
    function getAllianceVillagesWithCoor($alliance,$uid) {
        return array(array('wref'=>40,'name'=>'Aliada','username'=>'Socio','x'=>4,'y'=>0));
    }
};
$session = (object)array('uid'=>101,'villages'=>array(30,10,20),'alliance'=>7);
$village = (object)array('wid'=>10);
ob_start();
include $root.'/Templates/quick_target_select.tpl';
$html = ob_get_clean();
checkQuickTargetOrder(strpos($html, 'Antigua') < strpos($html, 'Capital'), 'fundación prevalece sobre capital y orden interno');
checkQuickTargetOrder(strpos($html, 'Actual') === false, 'excluye la aldea de origen');
checkQuickTargetOrder(strpos($html, 'Capital') < strpos($html, 'Aliada'), 'mantiene todas las propias antes de las aliadas');
$routeSource = file_get_contents($root.'/GameEngine/TradeRoutes.php');
checkQuickTargetOrder(strpos($routeSource, 'v.created ASC, v.wref ASC') !== false, 'las rutas ordenan por fundación con desempate estable');

if(empty($fails)) {
	echo "Quick target order checks passed ($checks comprobaciones).\n";
	exit(0);
}

echo count($fails)." de $checks comprobaciones fallaron.\n";
exit(1);
