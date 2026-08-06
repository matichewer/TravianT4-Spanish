<?php
// Regresión de las armaduras (btype 2): la reducción de pérdida de vitalidad que
// prometen las de escamas (85-87) y las articuladas (91-93), y que las aventuras
// puedan soltar las doce.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_armor_vitality.php

function armorAssert($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

class HeroArmorDatabase {
	public $inventory = array('helmet'=>0,'body'=>0,'leftHand'=>0,'rightHand'=>0,'shoes'=>0,'horse'=>0,'bag'=>0);
	public $items = array();
	public $changes = array();

	public function getHeroInventory($uid) { return $this->inventory; }
	public function getVillageField($wref,$field) { return 0; }
	public function getUserField($uid,$field,$mode) { return 0; }
	public function getItemData($id) { return isset($this->items[$id]) ? $this->items[$id] : false; }

	public function modifyHero2($column, $value, $uid, $mode) {
		$this->changes[] = array($column, $value, (int)$uid, $mode);
		return true;
	}

	public function wearArmor($type) {
		if($type === 0) {
			$this->inventory['body'] = 0;
			$this->items = array();
			return;
		}
		$this->items = array(1 => array('id'=>1,'uid'=>7,'btype'=>2,'type'=>$type,'num'=>1,'proc'=>1));
		$this->inventory['body'] = 1;
	}
}

require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Battle.php';

$database = new HeroArmorDatabase();
$damageMethod = new ReflectionMethod('Battle', 'battleHeroDamage');
$damageMethod->setAccessible(true);
$diesMethod = new ReflectionMethod('Battle', 'battleHeroDies');
$diesMethod->setAccessible(true);

function hero($health = 100) {
	return array('uid' => 7, 'health' => $health);
}

// --- Cuánto descuenta cada armadura -------------------------------------------

$reductions = array(
	82 => 0, 83 => 0, 84 => 0,   // regeneración: no reduce daño
	85 => 4, 86 => 6, 87 => 8,   // escamas
	88 => 0, 89 => 0, 90 => 0,   // petos: solo fuerza de combate
	91 => 3, 92 => 4, 93 => 5    // articuladas
);

foreach($reductions as $type => $reduction) {
	$database->wearArmor($type);
	armorAssert(
		heroArmorVitalityReduction($database, 7) === $reduction,
		"la armadura $type descontó ".heroArmorVitalityReduction($database, 7)." en vez de $reduction"
	);

	// 40% de bajas son 40 puntos de daño menos lo que absorba la armadura.
	$damage = $damageMethod->invoke($battle, hero(), 0.40);
	armorAssert($damage === 40-$reduction, "la armadura $type dejó el daño en $damage y no en ".(40-$reduction));
}

$database->wearArmor(0);
armorAssert(heroArmorVitalityReduction($database, 7) === 0, 'sin armadura apareció una reducción');
armorAssert($damageMethod->invoke($battle, hero(), 0.40) === 40, 'sin armadura el daño no fue el crudo');

// --- Bordes -------------------------------------------------------------------

// La reducción no puede dar daño negativo ni curar.
$database->wearArmor(87);
armorAssert($damageMethod->invoke($battle, hero(), 0.05) === 0, 'la armadura convirtió un daño chico en negativo');
armorAssert($damageMethod->invoke($battle, hero(), 0.0) === 0, 'sin bajas la armadura inventó daño');

// Sin héroe no hay daño que calcular.
armorAssert($damageMethod->invoke($battle, false, 0.50) === 0, 'se calculó daño sin héroe');
armorAssert($diesMethod->invoke($battle, false, 1.0) === false, 'un héroe inexistente murió');

// --- Muerte -------------------------------------------------------------------

// La armadura salva al héroe que se quedaría sin vitalidad por poco.
$database->wearArmor(87);
armorAssert($diesMethod->invoke($battle, hero(45), 0.50) === false, 'la armadura no salvó al héroe (50% de bajas, 45 de salud)');
$database->wearArmor(0);
armorAssert($diesMethod->invoke($battle, hero(45), 0.50) === true, 'sin armadura el héroe sobrevivió a un daño mayor que su salud');

// La regla de bajas catastróficas no la toca ninguna armadura.
$database->wearArmor(87);
armorAssert($diesMethod->invoke($battle, hero(100), 0.95) === true, 'la armadura salvó al héroe de un ejército arrasado');
$database->wearArmor(93);
armorAssert($diesMethod->invoke($battle, hero(100), 0.91) === true, 'la armadura salvó al héroe de un ejército arrasado');

// Con el ejército intacto el héroe no muere lleve lo que lleve.
foreach(array(0, 85, 93) as $type) {
	$database->wearArmor($type);
	armorAssert($diesMethod->invoke($battle, hero(100), 0.0) === false, "el héroe murió sin bajas con la armadura $type");
}

// --- Botín de aventura --------------------------------------------------------

$thresholds = heroAdventureTierThresholds();
$armorTiers = array(
	heroAdventureItemTypes(2, 1, 0),
	heroAdventureItemTypes(2, 1, $thresholds['second']),
	heroAdventureItemTypes(2, 1, $thresholds['third'])
);
armorAssert(count($armorTiers[0]) === 4, 'el primer nivel de armaduras no tiene 4 tipos');
armorAssert(count($armorTiers[1]) === 8, 'el segundo nivel de armaduras no tiene 8 tipos');
armorAssert(count($armorTiers[2]) === 12, 'el tercer nivel de armaduras no tiene 12 tipos');
foreach(range(82, 93) as $type) {
	armorAssert(in_array($type, $armorTiers[2], true), "la armadura $type no puede caer nunca");
}
foreach($armorTiers[0] as $type) {
	armorAssert(in_array($type, $armorTiers[1], true), "la armadura $type dejó de caer al subir de nivel");
}

// Cada nivel trae una armadura de cada familia, no tres de la misma.
foreach($armorTiers as $index => $types) {
	$families = array();
	foreach($types as $type) {
		$families[intdiv($type-82, 3)] = true;
	}
	armorAssert(count($families) === 4, "el nivel ".($index+1)." de armaduras no cubre las cuatro familias");
}

echo "Hero armor regression: OK\n";
