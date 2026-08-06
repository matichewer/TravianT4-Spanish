<?php
include("GameEngine/Village.php");

// El selector de aldea de las demás páginas valida que la aldea sea propia antes de
// moverla a la sesión; acá no lo hacía, así que se podía apuntar la sesión a cualquier
// aldea del mundo.
if(isset($_GET['newdid'])){
	$newVillageId = (int)$_GET['newdid'];
	if(in_array($newVillageId,array_map('intval',$session->villages),true)){
		$_SESSION['wid'] = $newVillageId;
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

$fieldId = isset($_GET['id']) && is_scalar($_GET['id']) && ctype_digit((string)$_GET['id'])
	? (int)$_GET['id']
	: 0;
$redirect = "build.php?id=".($fieldId >= 1 && $fieldId <= 40 ? $fieldId : 1);

// Igual que entrenar tropas o investigar: es una acción que cambia el estado, así que
// lleva el token de la sesión.
$tokenIsValid = isset($_GET['c']) && is_scalar($_GET['c'])
	&& hash_equals((string)$session->mchecker,(string)$_GET['c']);
$type = isset($_GET['type']) && is_scalar($_GET['type']) ? (int)$_GET['type'] : 0;

if($tokenIsValid && $fieldId >= 1 && $fieldId <= 40 && ($type === 1 || $type === 2)
	&& (int)$village->resarray['f'.$fieldId.'t'] === 24 && (int)$village->currentcel === 0){

	$session->changeChecker();
	$level = (int)$village->resarray['f'.$fieldId];
	// celebrationDuration() devuelve 0 cuando el nivel no existe en la tabla. Eso es lo
	// que pasaba con la fiesta grande por debajo del nivel 10: `$gc` no tiene esas filas,
	// así que el tiempo quedaba vacío y la celebración terminaba en el acto, regalando
	// los 2000 puntos de cultura.
	$duration = celebrationDuration($type, $level);
	$cost = isset($cel[$type]) ? $cel[$type] : null;

	if($duration > 0 && is_array($cost)){
		// Cobrar y agendar en un solo paso: el chequeo viejo aceptaba la celebración si
		// alcanzaba *alguno* de los cuatro recursos (usaba `||`) y después descontaba los
		// cuatro igual, dejando los otros en negativo.
		$paid = $database->deductResourcesIfAvailable(
			$village->resarray['vref'],
			$cost['wood'],
			$cost['clay'],
			$cost['iron'],
			$cost['crop']
		);
		if($paid){
			if(!$database->addCel($village->resarray['vref'], time()+$duration, $type)){
				$database->modifyResource(
					$village->resarray['vref'],
					$cost['wood'],
					$cost['clay'],
					$cost['iron'],
					$cost['crop'],
					1
				);
			}
		}
	}
}

header("Location: ".$redirect);
exit;
