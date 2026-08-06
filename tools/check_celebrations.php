<?php
// Regresión del Ayuntamiento: la duración que se anuncia tiene que ser la que se
// agenda, y la fiesta grande no puede existir por debajo del nivel 10.
//
//   docker compose exec -T web php /var/www/html/tools/check_celebrations.php

if(!defined('SPEED')) {
	define('SPEED', 1);
}

require dirname(__DIR__).'/GameEngine/Data/cel.php';

function celebrationAssert($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

// --- Fiesta pequeña: existe en todos los niveles del Ayuntamiento ---------------

for($level = 1; $level <= 20; $level++) {
	$duration = celebrationDuration(1, $level);
	celebrationAssert($duration > 0, "la fiesta pequeña no dura nada en el nivel $level");
	celebrationAssert(
		$duration === (int)round($sc[$level] / SPEED),
		"la fiesta pequeña del nivel $level no sale de la tabla \$sc"
	);
	if($level > 1) {
		celebrationAssert(
			$duration < celebrationDuration(1, $level-1),
			"la fiesta pequeña del nivel $level no es más corta que la del anterior"
		);
	}
}

// --- Fiesta grande: solo desde el nivel 10 -------------------------------------
//
// Por debajo de 10 la tabla no tiene fila. Antes celebration.php la leía igual, el
// tiempo quedaba vacío y la celebración terminaba en el acto: 2000 puntos de cultura
// instantáneos con un Ayuntamiento de nivel 1.

for($level = 0; $level <= 9; $level++) {
	celebrationAssert(
		celebrationDuration(2, $level) === 0,
		"la fiesta grande devolvió una duración en el nivel $level"
	);
}
for($level = 10; $level <= 20; $level++) {
	$duration = celebrationDuration(2, $level);
	celebrationAssert($duration > 0, "la fiesta grande no dura nada en el nivel $level");
	celebrationAssert(
		$duration === (int)round($gc[$level] / SPEED),
		"la fiesta grande del nivel $level no sale de la tabla \$gc"
	);
	celebrationAssert(
		$duration > celebrationDuration(1, $level),
		"la fiesta grande del nivel $level no dura más que la pequeña"
	);
}

// --- Bordes --------------------------------------------------------------------

foreach(array(0, 3, -1) as $type) {
	celebrationAssert(celebrationDuration($type, 10) === 0, "el tipo $type devolvió una duración");
}
celebrationAssert(celebrationDuration(1, 0) === 0, 'un Ayuntamiento de nivel 0 dio duración');
celebrationAssert(celebrationDuration(1, 21) === 0, 'un nivel fuera de tabla dio duración');
celebrationAssert(celebrationDuration(1, 999) === 0, 'un nivel absurdo dio duración');

// --- El servidor valida lo que hace falta --------------------------------------
//
// Se revisa en el código: la validación vive en un único archivo y es fácil que un
// cambio futuro se lleve puesta alguna de estas piezas sin que nada lo note.

$source = file_get_contents(dirname(__DIR__).'/celebration.php');
celebrationAssert($source !== false, 'No se pudo leer celebration.php');
$required = array(
	'hash_equals' => 'celebration.php ya no valida el token de la sesión',
	'deductResourcesIfAvailable' => 'celebration.php ya no cobra los recursos de forma atómica',
	'celebrationDuration' => 'celebration.php ya no usa la duración compartida',
	'$session->villages' => 'celebration.php ya no valida que la aldea del selector sea propia'
);
foreach($required as $fragment => $message) {
	celebrationAssert(strpos($source, $fragment) !== false, $message);
}
celebrationAssert(
	strpos($source, 'modifyResource') !== false && strpos($source, ',$mode') === false,
	'celebration.php volvió a descontar recursos con un $mode sin definir'
);

// La plantilla tiene que pedir la misma duración que agenda el servidor y mandar el token.
$template = file_get_contents(dirname(__DIR__).'/Templates/Build/24_1.tpl');
celebrationAssert($template !== false, 'No se pudo leer 24_1.tpl');
celebrationAssert(
	substr_count($template, 'celebrationDuration($i,$level)') === 2,
	'24_1.tpl dejó de mostrar la duración compartida en alguna de las dos celebraciones'
);
celebrationAssert(
	substr_count($template, 'c=$session->mchecker') === 2,
	'24_1.tpl dejó de mandar el token en alguno de los dos botones'
);

echo "Celebrations regression: OK\n";
