<?php
include("GameEngine/Village.php");

$field = isset($_POST['id']) && is_scalar($_POST['id']) ? (int)$_POST['id'] : 0;
$token = isset($_POST['c']) && is_scalar($_POST['c']) ? (string)$_POST['c'] : '';
$validField = $field >= 19 && $field <= 38
	&& (int)$village->resarray['f'.$field.'t'] === 35
	&& (int)$village->resarray['f'.$field] > 0;
$validOwner = (int)$session->tribe === 2
	&& (int)$village->capital === 1
	&& (int)$database->getVillageField($village->wid,'owner') === (int)$session->uid;

if($_SERVER['REQUEST_METHOD'] === 'POST'
	&& $validField
	&& $validOwner
	&& hash_equals((string)$session->mchecker,$token)
) {
	$cost = array('wood' => 3870, 'clay' => 1680, 'iron' => 215, 'crop' => 10900);
	$duration = max(1, (int)round(259200 / SPEED));
	$started = $database->startBreweryCelebration(
		$session->uid,
		$village->wid,
		time() + $duration,
		$cost['wood'],
		$cost['clay'],
		$cost['iron'],
		$cost['crop']
	);
	$_SESSION['brewery_status'] = $started ? 'success' : 'failed';
}

header("Location: build.php?id=".($field ?: 1));
exit;
