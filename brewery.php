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
	// Igual que el Ayuntamiento: el token es de un solo uso, así que se rota apenas
	// se acepta el pedido. Sin esto, volver atrás y reenviar el formulario disparaba
	// otra celebración con el mismo token.
	$session->changeChecker();
	if($session->access == BANNED) {
		header("Location: banned.php");
		exit;
	}
	// El costo y la duración salen de cel.php, la misma definición que muestra la
	// plantilla del edificio.
	$cost = breweryCelebrationCost();
	$duration = breweryCelebrationDuration();
	if($database->getBreweryCelebrationEnd($session->uid) > time()) {
		// Se distingue de la falta de recursos: son los dos motivos por los que el
		// pedido puede rebotar y el mensaje único no dejaba saber cuál fue.
		$_SESSION['brewery_status'] = 'active';
	} else {
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
}

header("Location: build.php?id=".($field ?: 1));
exit;
