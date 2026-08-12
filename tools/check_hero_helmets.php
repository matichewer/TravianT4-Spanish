<?php
// Regresión del slot de cabeza: bono de experiencia, bono de regeneración y la tabla
// de botines de aventura (que es lo que decide qué cascos se pueden llegar a tener).
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_helmets.php

$_POST = array();
chdir(dirname(__DIR__).'/GameEngine');
require 'Inventory.php';

class FakeHelmetDatabase
{
	public $inventory = array('helmet'=>0,'body'=>0,'leftHand'=>0,'rightHand'=>0,'shoes'=>0,'horse'=>0,'bag'=>0);
	public $items = array();
	public $hero = array('itempower'=>0,'autoregen'=>10,'speed'=>7,'dead'=>0,'wref'=>500,'home'=>500);
	public $face = array('helmet'=>0,'leftHand'=>0,'rightHand'=>0,'foot'=>0,'horse'=>0);

	public function __construct($items = array())
	{
		$this->items = $items;
	}

	public $villageCulturePoints = 250;

	public function getHeroInventory($uid){ return $this->inventory; }
	public function getVSumField($uid, $field){ return $this->villageCulturePoints; }
	public function getItemData($id){ return isset($this->items[$id]) ? $this->items[$id] : false; }
	public function getHeroData($uid){ return $this->hero; }
	public function editProcItem($id, $mode){ $this->items[$id]['proc'] = (int)$mode; return true; }
	public function setHeroInventory($uid, $field, $value){ $this->inventory[$field] = (int)$value; return true; }
	public function modifyHeroFace($uid, $field, $value){ $this->face[$field] = (int)$value; return true; }
	public function modifyHero2($field, $value, $uid, $mode)
	{
		if($mode==0){ $this->hero[$field] = $value; } else { $this->hero[$field] += $value; }
		return true;
	}
}

function check($condition, $message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

function helmet($type)
{
	return array('id' => 1, 'uid' => 7, 'btype' => 1, 'type' => $type, 'num' => 1, 'proc' => 0);
}

function withHelmet($type)
{
	$db = new FakeHelmetDatabase(array(1 => helmet($type)));
	check(equipHeroItem($db, 7, $db->getItemData(1)), "no se pudo equipar el casco $type");

	return $db;
}

// --- Experiencia (types 1-3) --------------------------------------------------

$expected = array(1 => 115, 2 => 120, 3 => 125);
foreach($expected as $type => $result){
	$db = withHelmet($type);
	check(heroExperienceWithHelmet($db, 7, 100)===$result,
		"el casco $type no dio $result de experiencia sobre 100");
}

// El truncado tiene que ser exacto: en coma flotante 100*(1+15/100) da 114.
$db = withHelmet(1);
check(heroExperienceWithHelmet($db, 7, 200)===230, 'el casco de experiencia trunca de menos');
check(heroExperienceWithHelmet($db, 7, 10)===11, 'el casco de experiencia redondea de más');
check(heroExperienceWithHelmet($db, 7, 0)===0, 'sin experiencia el casco inventó puntos');

// Un casco de otra familia no toca la experiencia.
foreach(array(4, 7, 10, 13) as $type){
	$db = withHelmet($type);
	check(heroExperienceWithHelmet($db, 7, 100)===100, "el casco $type alteró la experiencia");
}

$db = new FakeHelmetDatabase();
check(heroExperienceWithHelmet($db, 7, 100)===100, 'sin casco la experiencia cambió');

// --- Regeneración (types 4-6) -------------------------------------------------

$expected = array(4 => 20, 5 => 25, 6 => 30);
foreach($expected as $type => $result){
	$db = withHelmet($type);
	check((int)$db->hero['autoregen']===$result,
		"el casco $type dejó la regeneración en ".$db->hero['autoregen']." en vez de $result");

	check(unequipHeroItem($db, 7, 1, 1), "no se pudo sacar el casco $type");
	check((int)$db->hero['autoregen']===10,
		"sacar el casco $type dejó la regeneración en ".$db->hero['autoregen']);
}

// Cambiar un casco de regeneración por otro no acumula ni deja resto.
$db = new FakeHelmetDatabase(array(
	1 => array('id'=>1,'uid'=>7,'btype'=>1,'type'=>4,'num'=>1,'proc'=>0),
	2 => array('id'=>2,'uid'=>7,'btype'=>1,'type'=>6,'num'=>1,'proc'=>0)
));
check(equipHeroItem($db, 7, $db->getItemData(1)), 'primer casco de regeneración rechazado');
check(equipHeroItem($db, 7, $db->getItemData(2)), 'reemplazo de casco rechazado');
check((int)$db->hero['autoregen']===30, 'el reemplazo acumuló regeneración: '.$db->hero['autoregen']);
check(unequipHeroItem($db, 7, 1, 2), 'no se pudo sacar el casco de reemplazo');
check((int)$db->hero['autoregen']===10, 'quedó regeneración fantasma tras el reemplazo');

// Un casco sin efecto de regeneración no puede tocarla.
foreach(array(1, 7, 10, 13) as $type){
	$db = withHelmet($type);
	check((int)$db->hero['autoregen']===10, "el casco $type cambió la regeneración");
}

// --- Puntos de cultura (types 7-9) --------------------------------------------

$expected = array(7 => 25, 8 => 100, 9 => 200);
foreach($expected as $type => $bonus){
	$db = withHelmet($type);
	check(heroHelmetCulturePoints($db,7,1)===$bonus,
		"el casco $type aportó ".heroHelmetCulturePoints($db,7,1)." PC x1 en vez de $bonus");
	check(accountCulturePointsPerDay($db,7,1)===63+$bonus,
		"la producción diaria con el casco $type no sumó el bono");

	// A diferencia de la regeneración, el bono no se guarda en ninguna columna.
	check((int)$db->hero['autoregen']===10, "el casco $type tocó la regeneración");

	check(unequipHeroItem($db, 7, 1, 1), "no se pudo sacar el casco $type");
	check(heroHelmetCulturePoints($db,7,1)===0, "sacar el casco $type dejó PC fantasma");
	check(accountCulturePointsPerDay($db,7,1)===63, "la producción diaria quedó inflada");
}

// Un héroe muerto no aporta cultura, igual que no regenera ni produce recursos.
$db = withHelmet(9);
check(accountCulturePointsPerDay($db,7,1)===263, 'el casco del Cónsul no sumó estando vivo');
check(heroHelmetCulturePoints($db,7,3)===600, 'el casco del Cónsul no escaló a 600 PC en x3');
check(accountCulturePointsPerDay($db,7,3)===788, 'la cuenta x3 no combinó 188 PC pasivos y 600 del casco');
$db->hero['dead'] = 1;
check(heroHelmetCulturePoints($db,7,3)===0, 'un héroe muerto siguió aportando cultura');
check(accountCulturePointsPerDay($db,7,1)===63, 'un héroe muerto infló la producción diaria');

// Un casco de otra familia no aporta cultura.
foreach(array(1, 4, 10, 13) as $type){
	$db = withHelmet($type);
	check(heroHelmetCulturePoints($db,7,1)===0, "el casco $type aportó cultura");
}

$db = new FakeHelmetDatabase();
check(heroHelmetCulturePoints($db,7,1)===0, 'sin casco apareció cultura');
check(accountCulturePointsPerDay($db,7,1)===63, 'sin casco no aplicó el factor pasivo');

// --- Tiempo de entrenamiento (types 10-15) ------------------------------------

$HOME = 500;
$OTHER = 501;

// Establo (10-12) acelera el Establo y el Gran Establo; cuartel (13-15), el Cuartel y
// el Gran Cuartel. Ninguno toca al otro edificio.
$training = array(
	10 => array('stable' => 0.90), 11 => array('stable' => 0.85), 12 => array('stable' => 0.80),
	13 => array('barracks' => 0.90), 14 => array('barracks' => 0.85), 15 => array('barracks' => 0.80)
);
foreach($training as $type => $expected){
	$db = withHelmet($type);
	$stable = isset($expected['stable']) ? $expected['stable'] : 1.0;
	$barracks = isset($expected['barracks']) ? $expected['barracks'] : 1.0;

	foreach(array(20, 30) as $buildingType){
		$factor = heroTrainingTimeFactor($db, 7, $HOME, $buildingType);
		check(abs($factor-$stable)<0.0001,
			"el casco $type dio factor $factor en el edificio $buildingType, se esperaba $stable");
	}
	foreach(array(19, 29) as $buildingType){
		$factor = heroTrainingTimeFactor($db, 7, $HOME, $buildingType);
		check(abs($factor-$barracks)<0.0001,
			"el casco $type dio factor $factor en el edificio $buildingType, se esperaba $barracks");
	}

	// El bono es de la aldea natal: en cualquier otra aldea no se cobra.
	foreach(array(19, 20, 29, 30) as $buildingType){
		check(heroTrainingTimeFactor($db, 7, $OTHER, $buildingType)===1,
			"el casco $type aceleró el edificio $buildingType de otra aldea");
	}

	// Un héroe muerto no acelera nada.
	$db->hero['dead'] = 1;
	foreach(array(19, 20, 29, 30) as $buildingType){
		check(heroTrainingTimeFactor($db, 7, $HOME, $buildingType)===1,
			"un héroe muerto con el casco $type aceleró el edificio $buildingType");
	}
	$db->hero['dead'] = 0;

	// El casco no acelera talleres, residencia ni trampero.
	foreach(array(21, 42, 25, 26, 36) as $buildingType){
		check(heroTrainingTimeFactor($db, 7, $HOME, $buildingType)===1,
			"el casco $type aceleró el edificio $buildingType");
	}

	// Nada de esto se guarda en el héroe.
	check((int)$db->hero['autoregen']===10, "el casco $type tocó la regeneración");
	check(heroHelmetCulturePoints($db, 7)===0, "el casco $type aportó cultura");
}

// Los cascos de las otras familias no aceleran el entrenamiento.
foreach(array(1, 4, 7) as $type){
	$db = withHelmet($type);
	foreach(array(19, 20, 29, 30) as $buildingType){
		check(heroTrainingTimeFactor($db, 7, $HOME, $buildingType)===1,
			"el casco $type aceleró el edificio $buildingType");
	}
}

$db = new FakeHelmetDatabase();
check(heroTrainingTimeFactor($db, 7, $HOME, 19)===1, 'sin casco apareció un bono de entrenamiento');

// `home` manda sobre `wref`: mover al héroe a otra aldea no mueve el bono.
$db = withHelmet(15);
$db->hero['wref'] = $OTHER;
check(abs(heroTrainingTimeFactor($db, 7, $HOME, 19)-0.80)<0.0001,
	'el bono se fue con el héroe en vez de quedarse en la aldea natal');
check(heroTrainingTimeFactor($db, 7, $OTHER, 19)===1,
	'el bono siguió al héroe a la aldea donde está parado');

// Los héroes viejos sin `home` caen en `wref`, igual que el bono de recursos.
$db = withHelmet(15);
$db->hero['home'] = 0;
$db->hero['wref'] = $HOME;
check(abs(heroTrainingTimeFactor($db, 7, $HOME, 19)-0.80)<0.0001,
	'un héroe sin `home` no cobró el bono en su `wref`');

// --- Botín de aventura --------------------------------------------------------

$thresholds = heroAdventureTierThresholds();
$tiers = array(0, $thresholds['second'], $thresholds['third']);

// Cada nivel tiene que ser alcanzable: con las ramas invertidas la de 14 días era
// código muerto y el tercer nivel de cada familia no salía nunca.
$helmets = array();
foreach($tiers as $elapsed){
	$helmets[] = heroAdventureItemTypes(1, 1, $elapsed);
}
check(count($helmets[0])===5, 'el primer nivel de cascos no tiene 5 tipos');
check(count($helmets[1])===10, 'el segundo nivel de cascos no tiene 10 tipos');
check(count($helmets[2])===15, 'el tercer nivel de cascos no tiene 15 tipos');
foreach(range(1, 15) as $type){
	check(in_array($type, $helmets[2], true), "el casco $type no puede caer nunca");
}

// Los niveles son acumulativos y no hay huecos dentro de una familia.
foreach(array(1, 2, 3, 4, 5, 6) as $btype){
	$previous = array();
	foreach($tiers as $elapsed){
		$types = heroAdventureItemTypes($btype, 1, $elapsed);
		check(!empty($types), "btype $btype no suelta nada a los $elapsed segundos");
		foreach($previous as $type){
			check(in_array($type, $types, true), "btype $btype dejó de soltar el tipo $type al subir de nivel");
		}
		check(count($types)===count(array_unique($types)), "btype $btype repite tipos");
		$previous = $types;
	}
}

// El calzado agrupa tres familias de tres: el nivel máximo son los nueve.
$shoes = heroAdventureItemTypes(5, 1, $thresholds['third']);
foreach(range(94, 102) as $type){
	check(in_array($type, $shoes, true), "el calzado $type no puede caer nunca");
}

// Las armas dependen de la tribu y no se mezclan entre sí.
$ranges = array(1 => array(16, 30), 2 => array(46, 60), 3 => array(31, 45));
foreach($ranges as $tribe => $range){
	$weapons = heroAdventureItemTypes(4, $tribe, $thresholds['third']);
	check(count($weapons)===15, "la tribu $tribe no tiene 15 armas en el nivel máximo");
	foreach($weapons as $type){
		check($type>=$range[0] && $type<=$range[1], "la tribu $tribe puede recibir el arma $type");
	}
}

// Una tribu fuera de rango no suelta arma en vez de arrastrar la tabla anterior.
check(heroAdventureItemTypes(4, 0, $thresholds['third'])===array(), 'una tribu inválida soltó un arma');
check(heroAdventureItemTypes(4, 6, 0)===array(), 'una tribu inválida soltó un arma');

// Las categorías sin tabla no sueltan equipo.
check(heroAdventureItemTypes(0, 1, $thresholds['third'])===array(), 'btype 0 soltó equipo');
check(heroAdventureItemTypes(7, 1, $thresholds['third'])===array(), 'un consumible salió por la tabla de equipo');

// Los consumibles son uno por categoría y no se pisan entre sí.
$consumables = array();
foreach(range(7, 15) as $btype){
	$type = heroAdventureConsumableType($btype);
	check($type>0, "el consumible $btype no tiene tipo");
	check(!in_array($type, $consumables, true), "el consumible $btype repite el tipo $type");
	$consumables[] = $type;
}
check(heroAdventureConsumableType(6)===0, 'el caballo salió por la tabla de consumibles');
check(heroAdventureConsumableType(16)===0, 'una categoría inexistente devolvió un consumible');

echo "Hero helmets regression: OK\n";
