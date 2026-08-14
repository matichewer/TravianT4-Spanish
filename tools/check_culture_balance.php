<?php
// Regresión de la curva lenta y del cálculo usado por la normalización productiva.
//
//   docker compose exec -T web php /var/www/html/tools/check_culture_balance.php

error_reporting(E_ALL);
require dirname(__DIR__).'/GameEngine/Data/cp.php';
require dirname(__DIR__).'/GameEngine/Hero.php';

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

$installerConfigTemplate = file_get_contents(dirname(__DIR__).'/install/data/constant_format_mysqli.tpl');
$installerProcess = file_get_contents(dirname(__DIR__).'/install/process.php');
$installerForm = file_get_contents(dirname(__DIR__).'/install/templates/config.tpl');
cultureBalanceAssert(
	strpos($installerConfigTemplate, 'define("CP", 1);') !== false
		&& strpos($installerConfigTemplate, '%VILLAGE_EXPAND%') === false,
	'El instalador debe generar CP=1 directamente, sin un marcador configurable.'
);
cultureBalanceAssert(
	strpos($installerProcess, 'VILLAGE_EXPAND') === false
		&& strpos($installerProcess, "['village_expand']") === false,
	'El proceso de instalación no debe aceptar un modo de expansión enviado por formulario.'
);
cultureBalanceAssert(
	strpos($installerForm, 'name="village_expand"') === false
		&& strpos($installerForm, 'Slow (balanced)') !== false,
	'El instalador debe mostrar la curva lenta fija y no ofrecer Normal o Fast.'
);

class CultureProductionDatabaseStub {
	public $rawCulture = 5575;
	public function getVSumField($uid,$field){ return $this->rawCulture; }
	public function getHeroData($uid){ return array('dead'=>1); }
}
$productionDatabase = new CultureProductionDatabaseStub();
cultureBalanceAssert(villageCultureProductionFactor()===0.25, 'La producción pasiva dejó de usar el factor 25%.');
cultureBalanceAssert(villageCulturePointsPerDay(2365,1)===591.25, 'El desglose x1 por aldea no conserva cuartos de PC.');
cultureBalanceAssert(
	accountVillageCulturePointsPerDay($productionDatabase,5,1)===1394
		&& accountCulturePointsPerDay($productionDatabase,5,1)===1394,
	'La suma base de 5575 PC no se redondeó una sola vez a 1394.'
);
cultureBalanceAssert(
	accountCulturePointsPerDay($productionDatabase,5,3)===4181
		&& accountCulturePointsPerDay($productionDatabase,5,10)===13938,
	'La producción pasiva no se adaptó correctamente a mundos x3 y x10.'
);
cultureBalanceAssert(
	artworkCulturePoints($productionDatabase,5,3)===4181
		&& artworkCulturePoints($productionDatabase,5,10)===5000,
	'La obra no usa la producción escalada o no respeta el máximo de 5.000 PC.'
);
cultureBalanceAssert(
	getHeroHelmetBonuses(7)['culture']===25
		&& getHeroHelmetBonuses(8)['culture']===100
		&& getHeroHelmetBonuses(9)['culture']===200,
	'Los cascos de cultura dejaron de aportar 25/100/200 PC.'
);
cultureBalanceAssert(
	(int)round(getHeroHelmetBonuses(7)['culture']*cultureWorldSpeed(3))===75
		&& (int)round(getHeroHelmetBonuses(8)['culture']*cultureWorldSpeed(3))===300
		&& (int)round(getHeroHelmetBonuses(9)['culture']*cultureWorldSpeed(3))===600,
	'Los cascos no escalan a 75/300/600 PC en un mundo x3.'
);
$celebrationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$celebrationDataSource = file_get_contents(dirname(__DIR__).'/GameEngine/Data/cel.php');
cultureBalanceAssert(
	strpos($celebrationSource, '$rewards = array(1 => 500, 2 => 2000);') !== false
		&& strpos($celebrationDataSource, '$table[$level] / SPEED') !== false,
	'Las fiestas deben conservar 500/2000 PC y adaptar únicamente su duración a SPEED.'
);
cultureBalanceAssert(artworkCooldownSeconds()===86400, 'El cooldown de obra de arte dejó de ser 24 horas.');
cultureBalanceAssert(artworkCooldownRemaining(100000,100100)===86300, 'El tiempo restante de la obra es incorrecto.');
cultureBalanceAssert(artworkCooldownRemaining(100000,186400)===0, 'La obra no se habilitó exactamente a las 24 horas.');

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
