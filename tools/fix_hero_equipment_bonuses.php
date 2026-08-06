<?php
// Reconcilia `hero.speed` y `hero.autoregen` con lo que el héroe tiene equipado.
//
// Cada vez que un slot empieza a aplicar un bono que antes ignoraba, los héroes que ya
// tenían el objeto puesto quedan con el bono sin sumar, y al desequiparlo el código
// nuevo se lo resta igual: un héroe con corcel + espuelas bajaría de 20 a 15, y uno con
// botas o casco de regeneración se quedaría en 0 de regeneración. Pasó con el slot de
// pies y volvió a pasar con los cascos de regeneración (4-6).
//
// Recalcula desde cero a partir de los objetos equipados, así que es idempotente y se
// puede volver a correr sin miedo.
//
//   docker compose exec -T web php /var/www/html/tools/fix_hero_equipment_bonuses.php
//   docker compose exec -T web php /var/www/html/tools/fix_hero_equipment_bonuses.php --apply
//
// Sin --apply solo informa lo que cambiaría.

require_once dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Hero.php';

// getHeroArmorBonuses vive en Inventory.php, que en su cola procesa $_POST.
$_POST = array();
require_once dirname(__DIR__).'/GameEngine/Inventory.php';

$apply = in_array('--apply', $argv, true);
$heroTable = TB_PREFIX.'hero';
$itemTable = TB_PREFIX.'heroitems';

// Valores con los que addHero() crea al héroe: todo lo que exceda esto tiene que
// venir de un objeto equipado.
$baseSpeed = 7;
$baseAutoRegen = 10;

$rows = $database->query_return("SELECT uid, speed, autoregen FROM $heroTable ORDER BY uid");
if(!is_array($rows)){
	fwrite(STDERR, "No se pudo leer la tabla de héroes\n");
	exit(1);
}

$checked = 0;
$wrong = 0;
foreach($rows as $row){
	$uid = (int)$row['uid'];
	$checked++;

	$speed = $baseSpeed;
	$autoRegen = $baseAutoRegen;

	$helmet = heroEquippedItem($database, $uid, 1);
	if(is_array($helmet)){
		$helmetBonuses = getHeroHelmetBonuses((int)$helmet['type']);
		$autoRegen += $helmetBonuses['autoregen'];
	}

	$horse = heroEquippedItem($database, $uid, 6);
	if(is_array($horse)){
		$speed += getHeroHorseSpeedBonus((int)$horse['type']);
	}

	$shoes = heroEquippedItem($database, $uid, 5);
	if(is_array($shoes)){
		$shoesBonuses = getHeroShoesBonuses((int)$shoes['type']);
		$speed += $shoesBonuses['speed'];
		$autoRegen += $shoesBonuses['autoregen'];
	}

	$armor = heroEquippedItem($database, $uid, 2);
	if(is_array($armor)){
		$armorBonuses = getHeroArmorBonuses((int)$armor['type']);
		$autoRegen += $armorBonuses['autoregen'];
	}

	$currentSpeed = (int)$row['speed'];
	$currentAutoRegen = (int)$row['autoregen'];
	if($currentSpeed===$speed && $currentAutoRegen===$autoRegen){
		continue;
	}

	$wrong++;
	printf(
		"uid %-7d speed %3d -> %-3d   autoregen %3d -> %-3d%s\n",
		$uid, $currentSpeed, $speed, $currentAutoRegen, $autoRegen,
		$apply ? '' : '   (simulacion)'
	);

	if($apply){
		$updated = mysqli_query(
			$database->connection,
			"UPDATE $heroTable SET speed = $speed, autoregen = $autoRegen WHERE uid = $uid"
		);
		if(!$updated){
			fwrite(STDERR, "No se pudo actualizar el héroe $uid: ".mysqli_error($database->connection)."\n");
			exit(1);
		}
	}
}

printf(
	"\n%d héroes revisados, %d con los bonos desalineados%s\n",
	$checked,
	$wrong,
	($wrong>0 && !$apply) ? " (volver a correr con --apply para corregirlos)" : ''
);
