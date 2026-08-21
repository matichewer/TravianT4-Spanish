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

// El arranque de la celebración es una sola escritura condicionada: dos pedidos
// simultáneos no pueden pisar una fiesta ya empezada, y el cierre sólo paga los
// puntos de cultura al proceso que realmente cerró la fila.
$db = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
celebrationAssert($db !== false, 'No se pudo leer db_MYSQLi.php');
celebrationAssert(
	preg_match('/function addCel\(.*?celebration = 0/s', $db) === 1,
	'addCel() dejó de exigir que la aldea no tuviera otra celebración'
);
celebrationAssert(
	preg_match('/function clearCel\(.*?celebration <> 0.*?mysqli_affected_rows/s', $db) === 1,
	'clearCel() dejó de informar si realmente cerró la celebración'
);

$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
celebrationAssert($automation !== false, 'No se pudo leer Automation.php');
celebrationAssert(
	preg_match('/\$rewards = array\(1 => celebrationCulturePoints\(1\), 2 => celebrationCulturePoints\(2\)\)/', $automation) === 1,
	'celebrationComplete() volvió a decidir los puntos de cultura por su cuenta en vez de leer celebrationCulturePoints()'
);
celebrationAssert(
	preg_match('/if\(!\$database->clearCel\(\$id\)\)\s*\{\s*continue;/', $automation) === 1,
	'celebrationComplete() acredita los puntos sin haber ganado el cierre de la fila'
);
celebrationAssert(
	preg_match('/if\(!isset\(\$rewards\[\$type\]\)/', $automation) === 1,
	'celebrationComplete() volvió a arrastrar $cp de la aldea anterior del bucle'
);

// --- La plantilla ---------------------------------------------------------------
//
// Los dos bloques de celebración salen del mismo bucle, así que ya no pueden
// desbalancearse entre sí; lo que se revisa acá es que la estructura cierre todo
// lo que abre en cualquiera de las ramas (botón, "en curso", faltan recursos).

$template = file_get_contents(dirname(__DIR__).'/Templates/Build/24_celebrations.tpl');
celebrationAssert($template !== false, 'No se pudo leer 24_celebrations.tpl');
celebrationAssert(
	substr_count($template, 'celebrationDuration($i, $level)') === 1,
	'24_celebrations.tpl dejó de derivar la duración (y la disponibilidad) de la función compartida'
);
celebrationAssert(
	substr_count($template, "celebration.php?id=") === 1
		&& strpos($template, 'session->mchecker') !== false,
	'24_celebrations.tpl dejó de mandar el token en el botón de celebrar'
);
celebrationAssert(
	strpos($template, '$time =') === false,
	'24_celebrations.tpl volvió a pisar $time con el resultado de calculateAvaliable()'
);

$progress = file_get_contents(dirname(__DIR__).'/Templates/Build/24_progress.tpl');
celebrationAssert($progress !== false, 'No se pudo leer 24_progress.tpl');
celebrationAssert(
	strpos($progress, '\\"') === false,
	'24_progress.tpl volvió a escribir comillas escapadas en HTML crudo'
);
celebrationAssert(
	strpos($progress, 'Party') === false,
	'24_progress.tpl volvió a mostrar la celebración sin traducir'
);

foreach(array('24_celebrations.tpl' => $template, '24_progress.tpl' => $progress) as $name => $source) {
	// Las ramas del bloque de acción son excluyentes y cada una se cierra sola, así
	// que la cuenta cruda del archivo tiene que dar pareja. Se descartan las líneas
	// de comentario, que también hablan de <div>.
	$markup = implode("\n", array_filter(
		explode("\n", $source),
		function($line) { return strpos(ltrim($line), '//') !== 0; }
	));
	celebrationAssert(
		substr_count($markup, '<div') === substr_count($markup, '</div>'),
		"$name abre y cierra distinta cantidad de contenedores"
	);
}

// --- Las partes no se pueden pedir sueltas por URL ------------------------------
//
// build.php trata "Templates/Build/<gid>_<n>.tpl" como una pestaña cuando llega
// ?t=<n> o ?s=<n> (siempre numéricos). Con los nombres viejos, build.php?id=<ayto>&t=1
// devolvía la lista de celebraciones sin encabezado ni bloque de mejora.
foreach(array('24_1.tpl', '24_2.tpl') as $numbered) {
	celebrationAssert(
		!file_exists(dirname(__DIR__).'/Templates/Build/'.$numbered),
		"$numbered volvió a existir: se puede pedir suelta con ?t=/?s="
	);
}

echo "Celebrations regression: OK\n";
