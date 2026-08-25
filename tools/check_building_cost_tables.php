<?php
/**
 * Las 42 tablas de edificios: ningún nivel puede costar ni tardar menos que el anterior.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_building_cost_tables.php
 *
 * Es una comprobación tonta y por eso funciona: `GameEngine/Data/buidata.php` son 42
 * arrays escritos a mano de 20 niveles por 8 columnas, y un número mal tipeado ahí no lo
 * nota nadie — no rompe nada, sólo hace que un nivel salga regalado. Había cuatro:
 *
 *   - **Tesoro**, madera de los niveles 11 y 12: `9045` y `6600` en vez de `29045` y
 *     `36600`. Se les había comido el primer dígito, así que subir el Tesoro del 10 al 11
 *     costaba menos madera que subirlo del 9 al 10.
 *   - **Ayuntamiento**, tiempo de los niveles 14 a 20: estaban guardados **módulo 86400**,
 *     o sea como hora del día en vez de duración. El nivel 18 figuraba en 1h15m cuando son
 *     2 días y 1 hora. Se recuperan sumando los días enteros que faltaban (uno del 14 al
 *     17, dos del 18 al 20): los siete caen dentro de 100 segundos de lo que predice la
 *     progresión de la propia tabla.
 *   - **Mina de hierro**, tiempo del nivel 17: `145546` contra los ~1.444.650 que pide la
 *     progresión (16 días, no 2 minutos).
 *   - **Gran taller**, cereal del nivel 9: `4615` cuando el 8 ya costaba 5600.
 *
 * La monotonía no prueba que un número sea el oficial —para eso están los checkers de
 * cada edificio, como `check_treasury_building.php`, que contrastan la tabla entera contra
 * la fórmula— pero sí atrapa el typo, que es el error que de verdad ocurre.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
require $root.'/GameEngine/Data/buidata.php';

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

/**
 * Los nombres son sólo para que el mensaje de error diga de qué edificio habla.
 * Salen de la misma lista que usa el resto del juego.
 */
require_once $root.'/GameEngine/Catapult.php';

// Las columnas que tienen que crecer con el nivel. `pop` no: es el INCREMENTO de
// habitantes de cada nivel y sube y baja a propósito. `cp` sí crece (es el total del
// edificio a ese nivel) y `attri` no, porque en varios edificios es un porcentaje que
// baja (el ayuntamiento, sin ir más lejos).
$monotonic = array('wood', 'clay', 'iron', 'crop', 'time', 'cp');

$tablesSeen = 0;
for($type = 1; $type <= 42; $type++) {
    $name = 'bid'.$type;
    if(!isset($GLOBALS[$name]) || !is_array($GLOBALS[$name])) {
        continue;
    }
    $table = $GLOBALS[$name];
    $tablesSeen++;
    $levels = array_keys($table);
    sort($levels);
    $label = buildingDisplayName($type);

    foreach($monotonic as $column) {
        $previousLevel = null;
        $previousValue = null;
        foreach($levels as $level) {
            // El índice 0 de las tablas de campos de recurso no es un nivel: guarda
            // `prod` y nada más.
            if($level < 1 || !isset($table[$level][$column])) {
                continue;
            }
            $value = (float)$table[$level][$column];
            // Un cero no es un error por sí solo: el Muro de tierra germano no cuesta
            // hierro en ninguno de sus 20 niveles y la Maravilla del Mundo no da puntos de
            // cultura en ninguno de sus 100. Lo que no puede pasar es que una columna que
            // venía en números CAIGA a cero, y eso ya lo atrapa la monotonía de abajo.
            check($value >= 0,
                $name.' ('.$label.') '.$column.' nivel '.$level.': un valor negativo');
            if($previousValue !== null) {
                check($value >= $previousValue,
                    $name.' ('.$label.') '.$column.': el nivel '.$level.' vale '.$value
                        .' y el nivel '.$previousLevel.' valía '.$previousValue
                        .' — un nivel no puede costar ni tardar menos que el anterior');
            }
            $previousLevel = $level;
            $previousValue = $value;
        }
    }

    // Un salto enorme entre dos niveles seguidos es la otra cara del mismo typo: si a un
    // número se le come un dígito, el nivel siguiente parece multiplicarse por diez.
    // Las tablas de este juego crecen entre x1,16 y x2 por nivel; x4 no es una tabla, es
    // un dedo.
    foreach($monotonic as $column) {
        $previousLevel = null;
        $previousValue = null;
        foreach($levels as $level) {
            if($level < 1 || !isset($table[$level][$column])) {
                continue;
            }
            $value = (float)$table[$level][$column];
            if($previousValue !== null && $previousValue > 0 && $level === $previousLevel + 1) {
                check($value / $previousValue < 4,
                    $name.' ('.$label.') '.$column.': del nivel '.$previousLevel.' al '
                        .$level.' se multiplica por '.round($value / $previousValue, 2)
                        .' — demasiado para ser un nivel más');
            }
            $previousLevel = $level;
            $previousValue = $value;
        }
    }
}

check($tablesSeen >= 40, 'se leyeron las 42 tablas de edificios y se leyeron '.$tablesSeen);

// Los cuatro valores concretos que estaban mal, escritos como afirmaciones sueltas: si
// alguien restaura el archivo de un backup viejo, esto lo dice con nombre y apellido.
global $bid3, $bid24, $bid27, $bid42;
check((int)$bid27[11]['wood'] === 29045, 'Tesoro nivel 11: la madera es 29045, no 9045');
check((int)$bid27[12]['wood'] === 36600, 'Tesoro nivel 12: la madera es 36600, no 6600');
check((int)$bid3[17]['time'] > 1000000, 'Mina de hierro nivel 17: el tiempo son ~16 días, no 2 minutos');
check((int)$bid24[18]['time'] > 86400, 'Ayuntamiento nivel 18: el tiempo son ~2 días, no 1h15m');
check((int)$bid42[9]['crop'] === 6920, 'Gran taller nivel 9: el cereal es 6920, no 4615');

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Tablas de edificios: OK ('.$checks.' comprobaciones sobre '.$tablesSeen.' tablas)').PHP_EOL;
exit($failures ? 1 : 0);
