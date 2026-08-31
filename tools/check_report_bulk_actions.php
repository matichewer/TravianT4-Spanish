<?php
/**
 * Regression check for report bulk actions with page sizes above the legacy 10.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_report_bulk_actions.php
 */

require_once dirname(__DIR__).'/GameEngine/Message.php';

$reflection = new ReflectionClass('Message');
$message = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('selectedNoticeIds');
$method->setAccessible(true);

$post = array(
	'n1' => '101',
	'n10' => '110',
	'n11' => '111',
	'n50' => '150',
	'n100' => '200',
	'n101' => '201',
	'n0' => '999',
	'nonsense' => '998',
	'n12' => '0',
	'markread' => 'markread',
);
$actual = $method->invoke($message, $post);
$expected = array(101, 110, 111, 150, 200, 201);

if($actual !== $expected) {
	fwrite(STDERR, 'Bulk report selection was truncated or accepted invalid fields: '.json_encode($actual)."\n");
	exit(1);
}

$source = file_get_contents(dirname(__DIR__).'/GameEngine/Message.php');
foreach(array('removeNotice', 'markNoticesRead', 'markNoticesUnread', 'archiveNotice', 'unarchiveNotice') as $action) {
	$pattern = '/private function '.preg_quote($action, '/').'\(\$post\).*?\n\s*\}/s';
	if(!preg_match($pattern, $source, $match) || strpos($match[0], 'selectedNoticeIds($post)') === false) {
		fwrite(STDERR, $action." does not use the shared bulk report selection.\n");
		exit(1);
	}
}

$allReportsSource = file_get_contents(dirname(__DIR__).'/Templates/Notice/all.tpl');
if(substr_count($allReportsSource, '$excludeTradeRoutes') < 3
	|| strpos($allReportsSource, 'Automation::NTYPE_ROUTE_NOT_SENT') === false
	|| strpos($allReportsSource, "ntype IN (10,11,12,13) and data LIKE '%,route'") === false) {
	fwrite(STDERR, "The All tab does not consistently exclude successful and failed trade routes.\n");
	exit(1);
}

$databaseSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
if(strpos($databaseSource, '0 => "archive = 0 AND NOT (ntype = 26 OR (ntype IN (10,11,12,13) AND data LIKE') === false) {
	fwrite(STDERR, "The All-tab previous/next navigation can enter a trade-route report.\n");
	exit(1);
}

echo "Report bulk actions: OK (100 selections and route-only filtering).\n";
