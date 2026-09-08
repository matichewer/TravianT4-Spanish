<?php
/**
 * Los tres edificios "grandes": Gran cuartel, Gran establo y Gran taller.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_great_buildings.php
 *
 * Qué estaba mal. La tabla del **Gran taller** (`bid42`) no salía de ninguna fórmula: los
 * niveles 19 y 20 se desviaban un 11% y un 20% **en las cuatro columnas a la vez**, y había
 * además cuatro números sueltos mal tipeados (el cereal del 8, el hierro del 11, el cereal
 * del 17 y el barro del 2). No era un typo de un dígito como los otros de `buidata.php`:
 * la tabla entera estaba generada como `3 × la tabla del Taller ya redondeada`, en vez de
 * como `el triple de la base, redondeado`. Las dos formas se llevan ±5 en la mayoría de las
 * casillas, y por eso nadie lo había notado; los niveles 19 y 20 sí eran otra cosa.
 *
 * La regla, comprobada contra las dos tablas oficiales que sí existen:
 *
 *     coste(nivel) = round(3 × base × 1,28^(nivel−1) / 5) × 5
 *
 * `1,28` es el factor de crecimiento de todos los edificios de entrenamiento y `base` es el
 * coste de nivel 1 del edificio normal. El **Gran establo** de este repo encaja en las 80
 * casillas con esa fórmula, y el **Gran cuartel** encajaba en 78: los otros dos (barro y
 * hierro del nivel 8) estaban calculados como `3 × redondeado` y ahora siguen la tabla
 * oficial publicada, que dice 2365 y 4390. O sea que la fórmula no es una interpolación:
 * es la que reproduce las tablas oficiales casilla por casilla.
 *
 * Sobre el Gran taller y el "oficial": **no existe en el Travian T4 original**. Los únicos
 * edificios "grandes" de tropas del oficial son el Gran cuartel y el Gran establo; el Gran
 * taller es un agregado de este servidor (y de TravianX), y por eso no hay tabla oficial que
 * copiar. Lo que sí hay es un patrón oficial, y ahora lo sigue exactamente igual que los
 * otros dos: mismo requisito (el edificio normal a 20), prohibido en la capital, misma
 * población, mismos puntos de cultura, mismo tiempo de obra y mismo tiempo de entrenamiento
 * que el Taller, y el triple de coste de construcción. El triple de coste POR TROPA ya lo
 * aplicaba `Technology::trainUnit()`; lo que estaba mal era sólo el edificio.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
require $root.'/GameEngine/Data/buidata.php';
require_once $root.'/GameEngine/Catapult.php';

$failures = 0;
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        $failures++;
        echo '[FALLA] '.$message.PHP_EOL;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

/** El factor de crecimiento de los edificios de entrenamiento. Oficial, los tres. */
define('CHECK_GROWTH', 1.28);

/** La fórmula oficial de coste: el multiplicador entra ANTES del redondeo, no después. */
function officialCost($base, $level, $multiplier) {
    return (int)(round($multiplier * $base * pow(CHECK_GROWTH, $level - 1) / 5) * 5);
}

// Los costes de nivel 1 del edificio normal, que son la base de todo. Oficiales.
$bases = array(
    19 => array('label' => 'Cuartel',  'base' => array(210, 140, 260, 120)),
    20 => array('label' => 'Establo',  'base' => array(260, 140, 220, 100)),
    21 => array('label' => 'Taller',   'base' => array(460, 510, 600, 320))
);
// Cada grande, con el normal del que sale.
$greats = array(29 => 19, 30 => 20, 42 => 21);
$columns = array('wood', 'clay', 'iron', 'crop');

// =====================================================================================
section('A. Los edificios normales salen de la fórmula oficial');
// =====================================================================================
//
// Se comprueba primero el normal porque es lo que valida la fórmula: si `1,28` o el
// redondeo a múltiplos de 5 estuvieran mal, fallaría acá y no habría por qué creerle a lo
// que venga después.
foreach($bases as $gid => $meta) {
    $table = $GLOBALS['bid'.$gid];
    for($level = 1; $level <= 20; $level++) {
        foreach($columns as $index => $column) {
            $expected = officialCost($meta['base'][$index], $level, 1);
            check((int)$table[$level][$column] === $expected,
                $meta['label'].' nivel '.$level.' '.$column.': la tabla dice '
                    .(int)$table[$level][$column].' y la fórmula oficial '.$expected);
        }
    }
}

// =====================================================================================
section('B. Los grandes cuestan el triple, redondeado una sola vez');
// =====================================================================================
foreach($greats as $greatGid => $normalGid) {
    $table = $GLOBALS['bid'.$greatGid];
    $base = $bases[$normalGid]['base'];
    $label = buildingDisplayName($greatGid);
    for($level = 1; $level <= 20; $level++) {
        foreach($columns as $index => $column) {
            $expected = officialCost($base[$index], $level, 3);
            check((int)$table[$level][$column] === $expected,
                $label.' nivel '.$level.' '.$column.': la tabla dice '
                    .(int)$table[$level][$column].' y la fórmula oficial '.$expected);
        }
    }
}

// El detalle que distingue las dos formas de calcularlo, escrito como afirmación: si
// alguien vuelve a generar la tabla como `3 × la del edificio normal`, esto lo dice.
global $bid21, $bid42, $bid19, $bid29;
// Las dos formas de calcularlo coinciden en muchas casillas —se llevan ±5— así que la
// afirmación útil no es sobre una casilla concreta sino sobre la tabla entera: si alguien
// la regenera como `3 × la del Taller ya redondeada`, las 80 casillas coincidirían y esto
// lo dice. Hoy difieren en 19 de las 80.
$tripledCells = 0;
for($level = 1; $level <= 20; $level++) {
    foreach($columns as $column) {
        if((int)$bid42[$level][$column] === 3 * (int)$bid21[$level][$column]) {
            $tripledCells++;
        }
    }
}
check($tripledCells < 80,
    'el Gran taller no está generado como "3 × la tabla del Taller ya redondeada": '
    .'el multiplicador va antes del redondeo, no después');
check((int)$bid29[8]['clay'] === 2365,
    'Gran cuartel nivel 8: el barro es 2365 (tabla oficial), no 2370');
check((int)$bid29[8]['iron'] === 4390,
    'Gran cuartel nivel 8: el hierro es 4390 (tabla oficial), no 4395');

// =====================================================================================
section('C. Los cuatro números del Gran taller que estaban mal, por su nombre');
// =====================================================================================
//
// Los desvíos de ±5 del resto de la tabla eran cosméticos; estos cuatro no. Se listan
// sueltos para que restaurar un backup viejo del archivo se note con nombre y apellido.
check((int)$bid42[19]['wood'] === 117395,
    'Gran taller nivel 19: la madera es 117395, no 105650 (era un 11% de regalo)');
check((int)$bid42[20]['wood'] === 150270,
    'Gran taller nivel 20: la madera es 150270, no 125225 (era un 20% de regalo)');
check((int)$bid42[11]['iron'] === 21250,
    'Gran taller nivel 11: el hierro es 21250, no 23415');
check((int)$bid42[17]['crop'] === 49845,
    'Gran taller nivel 17: el cereal es 49845, no 49485 — dos dígitos cambiados de lugar');
check((int)$bid42[8]['crop'] === 5405,
    'Gran taller nivel 8: el cereal es 5405, no 5600');

// La progresión, además, tiene que ser una progresión: ningún nivel más barato que el
// anterior y ninguno más del doble. Los 19 y 20 rompían esto por el otro lado, quedándose
// cortos, que es el caso que la comprobación de monotonía de check_building_cost_tables.php
// no atrapa — subir poco no es subir menos.
foreach($greats as $greatGid => $normalGid) {
    $table = $GLOBALS['bid'.$greatGid];
    $label = buildingDisplayName($greatGid);
    for($level = 2; $level <= 20; $level++) {
        foreach($columns as $column) {
            $ratio = (int)$table[$level][$column] / (int)$table[$level - 1][$column];
            check($ratio > 1.27 && $ratio < 1.29,
                $label.' nivel '.$level.' '.$column.': crece x'.round($ratio, 4)
                    .' y el factor oficial es 1,28');
        }
    }
}

// =====================================================================================
section('D. Todo lo demás lo comparten con el edificio normal');
// =====================================================================================
//
// Un edificio grande es el mismo edificio con otro precio: mismos habitantes, mismos
// puntos de cultura, mismo tiempo de obra y mismo tiempo de entrenamiento. Si alguna de
// estas cuatro columnas se separa, el grande deja de ser una segunda cola y pasa a ser un
// edificio distinto.
foreach($greats as $greatGid => $normalGid) {
    $great = $GLOBALS['bid'.$greatGid];
    $normal = $GLOBALS['bid'.$normalGid];
    $label = buildingDisplayName($greatGid);
    foreach(array('pop', 'cp', 'attri', 'time') as $column) {
        $same = true;
        for($level = 1; $level <= 20; $level++) {
            if((string)$great[$level][$column] !== (string)$normal[$level][$column]) {
                $same = false;
                break;
            }
        }
        check($same, $label.': la columna '.$column.' tiene que ser la misma que la del '
            .$bases[$normalGid]['label']);
    }
}

// =====================================================================================
section('E. Se construyen bajo las mismas reglas');
// =====================================================================================
foreach($greats as $greatGid => $normalGid) {
    $label = buildingDisplayName($greatGid);
    $requirements = buildingLevelRequirements($greatGid);
    check(is_array($requirements) && $requirements === array($normalGid => 20),
        $label.': el único requisito es el '.$bases[$normalGid]['label'].' a nivel 20');
}
$building = file_get_contents($root.'/GameEngine/Building.php');
check(preg_match('/case 29:\s*\n\s*case 30:\s*\n\s*case 42:\s*\n\s*return \(int\)\$village->capital === 0;/', $building) === 1,
    'los tres están prohibidos en la capital, con la misma línea');

// El triple de coste POR TROPA es la regla oficial del Gran cuartel y ya estaba: se pina
// acá porque es la mitad que le da sentido al edificio, y vive en otro archivo.
$technology = file_get_contents($root.'/GameEngine/Technology.php');
check(strpos($technology, '$multiplier = $great ? 3 : 1;') !== false,
    'entrenar en un edificio grande cuesta el triple por tropa');
check(strpos($technology, '42=>$bid42') !== false,
    'y el Gran taller usa su propia tabla para el tiempo de entrenamiento');

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Edificios grandes: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
