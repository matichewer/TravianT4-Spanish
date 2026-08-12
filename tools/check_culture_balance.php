<?php
// Regresión de la curva lenta y del cálculo usado por la normalización productiva.
//
//   docker compose exec -T web php /var/www/html/tools/check_culture_balance.php

error_reporting(E_ALL);
require dirname(__DIR__).'/GameEngine/Data/cp.php';

$errors = array();
function cultureBalanceAssert($condition, $message){
	global $errors;
	if(!$condition){
		$errors[] = $message;
	}
}

cultureBalanceAssert(
	travianCultureRequiredForVillageCount(2, 1) === 2000
		&& travianCultureRequiredForVillageCount(3, 1) === 8000
		&& travianCultureRequiredForVillageCount(4, 1) === 20000,
	'La progresión lenta inicial dejó de ser 2.000 / 8.000 / 20.000.'
);

$surplus = travianCultureNormalization(50000, 3, 1);
cultureBalanceAssert(
	$surplus['changed'] === true && $surplus['cap'] === 20000 && $surplus['newPoints'] === 20000,
	'Una cuenta con excedente no conserva exactamente el umbral de una aldea adicional.'
);

$below = travianCultureNormalization(7500, 2, 1);
cultureBalanceAssert(
	$below['changed'] === false && $below['newPoints'] === 7500,
	'La normalización no debe regalar ni quitar PC a una cuenta por debajo del tope.'
);

$normalizedAgain = travianCultureNormalization($surplus['newPoints'], 3, 1);
cultureBalanceAssert(
	$normalizedAgain['changed'] === false && $normalizedAgain['newPoints'] === 20000,
	'La normalización debe ser idempotente.'
);

$beyondTable = travianCultureNormalization(PHP_INT_MAX, 125, 1);
cultureBalanceAssert(
	$beyondTable['cap'] === null && $beyondTable['changed'] === false,
	'Una cuenta fuera de la tabla debe informarse y quedar sin cambios.'
);

$toolSource = file_get_contents(__DIR__.'/normalize_culture_points.php');
cultureBalanceAssert(strpos($toolSource, "in_array('--apply', \$argv, true)") !== false, 'La herramienta debe requerir --apply explícito.');
cultureBalanceAssert(strpos($toolSource, 'u.access = ".(int)USER') !== false, 'La herramienta debe excluir cuentas que no sean jugadores regulares.');
cultureBalanceAssert(strpos($toolSource, 'INNER JOIN $villageTable') !== false, 'La herramienta debe excluir cuentas sin aldeas.');
cultureBalanceAssert(strpos($toolSource, 'mysqli_begin_transaction') !== false, 'La aplicación debe ejecutarse dentro de una transacción.');

if($errors){
	fwrite(STDERR, "Fallaron ".count($errors)." comprobaciones de cultura:\n - ".implode("\n - ", $errors)."\n");
	exit(1);
}

echo "OK: curva lenta y normalización de puntos de cultura verificadas\n";
