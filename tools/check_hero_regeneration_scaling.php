<?php
// La regeneración del héroe vive en una sola columna, `hero.autoregen`, que mezcla la
// base con los bonos de los objetos equipados. Multiplicarla entera por SPEED hacía que
// unas botas de "+20 puntos de salud/día" dieran 60 en un x3 y que el combo máximo
// (casco 6 + armadura 84 + botas 96) curara 270 puntos por día, o sea el héroe entero
// dos veces y media. Solo la base escala con la velocidad del servidor.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_regeneration_scaling.php

require_once dirname(__DIR__).'/GameEngine/Hero.php';

$failures = 0;

function regenCheck($condition, $message) {
	global $failures;
	if($condition) {
		echo "OK: ".$message."\n";
		return;
	}
	$failures++;
	echo "FAIL: ".$message."\n";
}

$base = heroBaseRegeneration();
regenCheck($base === 10, "la base del héroe es 10 puntos por día (es $base)");

// La base sí escala: un héroe pelado en un x3 se cura tres veces más rápido que en un x1.
foreach(array(1 => 10, 2 => 20, 3 => 30, 10 => 100) as $speed => $expected) {
	$got = heroRegenerationPerDay($base, $speed);
	regenCheck($got === $expected, "sin objetos a x$speed la base da $expected por día (dio $got)");
}

// Los objetos no: valen los puntos planos que anuncia su cartel, sea cual sea el server.
$scenarios = array(
	// autoregen, velocidad, esperado, descripción
	array(30, 3, 50, 'botas de la Curación (+20) en un x3'),
	array(30, 1, 30, 'botas de la Curación (+20) en un x1'),
	array(50, 3, 70, 'armadura pesada de la Regeneración (+40) en un x3'),
	array(90, 3, 110, 'casco 6 + armadura 84 + botas 96 (+80) en un x3'),
	array(90, 1, 90, 'el mismo combo en un x1'),
);
foreach($scenarios as $scenario) {
	list($autoRegen, $speed, $expected, $label) = $scenario;
	$got = heroRegenerationPerDay($autoRegen, $speed);
	regenCheck($got === $expected, "$label da $expected por día (dio $got)");
}

// Dicho de otra forma: el aporte de los objetos es el mismo a cualquier velocidad.
foreach(array(1, 2, 3, 5, 10) as $speed) {
	foreach(array(10, 20, 40, 80) as $items) {
		$delta = heroRegenerationPerDay($base + $items, $speed) - heroRegenerationPerDay($base, $speed);
		regenCheck($delta === $items, "a x$speed un bono de $items suma $items y no ".$delta);
	}
}

// Y cada objeto de regeneración que existe entrega exactamente lo que promete su cartel.
$items = array();
foreach(range(4, 6) as $type) {
	$items["casco $type"] = getHeroHelmetBonuses($type)['autoregen'];
}
foreach(range(82, 87) as $type) {
	$items["armadura $type"] = getHeroArmorBonuses($type)['autoregen'];
}
foreach(range(94, 96) as $type) {
	$items["botas $type"] = getHeroShoesBonuses($type)['autoregen'];
}
foreach($items as $label => $bonus) {
	regenCheck($bonus > 0, "$label declara un bono de regeneración (declara $bonus)");
	$got = heroRegenerationPerDay($base + $bonus, 3) - heroRegenerationPerDay($base, 3);
	regenCheck($got === $bonus, "$label promete +$bonus por día y en un x3 entrega +$got");
}

// Un `autoregen` por debajo de la base (héroe viejo, columna a mano) no puede terminar
// restando: los objetos nunca aportan negativo.
regenCheck(heroRegenerationPerDay(0, 3) === 30, 'un autoregen en 0 no resta de la base');
regenCheck(heroRegenerationPerDay(5, 3) === 30, 'un autoregen por debajo de la base no resta');

// Sin velocidad explícita se usa SPEED, y una velocidad inválida no apaga la regeneración.
regenCheck(heroRegenerationPerDay($base, 0) === 10, 'una velocidad 0 no deja al héroe sin regeneración');

// El motor y el cartel de la ficha tienen que salir de la misma función: cuando cada uno
// llevaba su propia fórmula, uno multiplicaba por SPEED y el otro por INCREASE_SPEED.
$callers = array(
	'GameEngine/Automation.php' => 'updateHero',
	'Templates/hero.tpl' => 'el cartel de la ficha del héroe',
);
foreach($callers as $file => $label) {
	$source = file_get_contents(dirname(__DIR__).'/'.$file);
	regenCheck(
		$source !== false && strpos($source, 'heroRegenerationPerDay(') !== false,
		"$label calcula la regeneración con heroRegenerationPerDay()"
	);
	regenCheck(
		$source !== false && !preg_match("/autoregen'\]\s*\*\s*(SPEED|INCREASE_SPEED)/", $source),
		"$label ya no multiplica autoregen entero por la velocidad del servidor"
	);
}

if($failures > 0) {
	echo "Hero regeneration scaling regression: $failures FALLAS\n";
	exit(1);
}

echo "Hero regeneration scaling regression: OK\n";
