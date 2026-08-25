<?php
/**
 * Cuándo un artefacto hace efecto: el retardo de activación y el podio de tres.
 *
 * Estas dos reglas son las que el motor consultaba con `artefacts.active`, una columna
 * que nunca existió. La consulta fallaba en silencio y devolvía vacío, así que ningún
 * efecto de artefacto funcionaba — y de paso nadie notó que la regla oficial detrás de
 * esa columna es bastante más restrictiva de lo que parece:
 *
 *   - un artefacto recién capturado NO hace nada hasta que pasa el retardo, que escala
 *     con la velocidad del mundo (24 h en x1, 12 h en x3, 4 h en x10);
 *   - una cuenta tiene como mucho TRES activos a la vez y sólo UNO puede ser de cuenta;
 *   - entre los que compiten ganan los capturados hace más tiempo;
 *   - dentro de una aldea, el artefacto de aldea PISA al de cuenta del mismo tipo: no se
 *     suman ni gana el más fuerte.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_artefact_activation.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__).'/GameEngine/Artefact.php';

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

/** Una fila de artefacto de mentira. */
function art($id, $type, $size, $conquered, $vref = 0, $owner = 7) {
    return array(
        'id' => $id, 'type' => $type, 'size' => $size,
        'conquered' => $conquered, 'vref' => $vref, 'owner' => $owner
    );
}
function ids($rows) {
    $out = array();
    foreach($rows as $row) {
        $out[] = (int)$row['id'];
    }
    sort($out);
    return $out;
}

// -------------------------------------------------------------------------------------
section('A. El retardo de activación escala con la velocidad del mundo');

// La tabla oficial, exacta. No es 24/velocidad ni ninguna fórmula cerrada.
$official = array(1 => 24, 2 => 16, 3 => 12, 5 => 8, 10 => 4);
foreach($official as $speed => $hours) {
    check(artefactActivationDelay($speed) === $hours * 3600,
        'x'.$speed.' debe tardar '.$hours.' h y tarda '.round(artefactActivationDelay($speed) / 3600).' h');
}
check(artefactActivationDelay(1) > artefactActivationDelay(10),
    'un mundo rápido no puede tardar más que uno lento');
// Una velocidad fuera de tabla cae a la entrada más cercana en vez de romperse.
check(artefactActivationDelay(4) === artefactActivationDelay(5) || artefactActivationDelay(4) === artefactActivationDelay(3),
    'una velocidad sin entrada propia cae a la más cercana');
check(artefactActivationDelay(0) === artefactActivationDelay(1),
    'una velocidad inválida no puede dar un retardo negativo ni cero');

$now = 1000000;
$delay = artefactActivationDelay(3);
$fresh = art(1, ARTEFACT_TRAINER, 1, $now);
$almost = art(2, ARTEFACT_TRAINER, 1, $now - $delay + 1);
$ripe = art(3, ARTEFACT_TRAINER, 1, $now - $delay);

check(artefactIsMature($fresh, $now, 3) === false, 'recién capturado no puede estar activo');
check(artefactIsMature($almost, $now, 3) === false, 'un segundo antes tampoco');
check(artefactIsMature($ripe, $now, 3) === true, 'justo al cumplirse el retardo, sí');
check(artefactSecondsUntilActive($fresh, $now, 3) === $delay, 'la cuenta atrás arranca en el retardo entero');
check(artefactSecondsUntilActive($ripe, $now, 3) === 0, 'y llega a cero, nunca a un negativo');
check(artefactSecondsUntilActive(art(4, 1, 1, $now - 10 * $delay), $now, 3) === 0,
    'un artefacto viejo no acumula un "menos" que después haya que interpretar');

// -------------------------------------------------------------------------------------
section('B. El podio: tres activos, uno solo de cuenta, los más viejos primero');

$old = $now - 10 * $delay;
$mid = $now - 5 * $delay;
$new = $now - 2 * $delay;

// Cinco de aldea: entran los tres más viejos.
$rows = array(
    art(1, ARTEFACT_ARCHITECT, 1, $old),
    art(2, ARTEFACT_BOOTS,     1, $old + 1),
    art(3, ARTEFACT_EAGLE,     1, $mid),
    art(4, ARTEFACT_DIET,      1, $new),
    art(5, ARTEFACT_TRAINER,   1, $new + 1)
);
check(ids(artefactActiveRows($rows, $now, 3)) === array(1, 2, 3),
    'con cinco de aldea deben quedar activos los tres más viejos');

// Uno de cuenta y cuatro de aldea: el de cuenta + los dos de aldea más viejos.
$rows = array(
    art(1, ARTEFACT_ARCHITECT, 2, $mid),
    art(2, ARTEFACT_BOOTS,     1, $old),
    art(3, ARTEFACT_EAGLE,     1, $old + 1),
    art(4, ARTEFACT_DIET,      1, $old + 2),
    art(5, ARTEFACT_TRAINER,   1, $new)
);
$active = artefactActiveRows($rows, $now, 3);
check(ids($active) === array(1, 2, 3),
    'con uno de cuenta deben entrar ése y los DOS de aldea más viejos, no los tres');
check(count($active) === ARTEFACT_MAX_ACTIVE, 'y deben ser exactamente tres');

// Dos de cuenta: sólo el más viejo. Aunque el otro sea único y más fuerte.
$rows = array(
    art(1, ARTEFACT_EAGLE, 2, $old),
    art(2, ARTEFACT_EAGLE, 3, $new)
);
$active = artefactActiveRows($rows, $now, 3);
check(ids($active) === array(1),
    'dos artefactos de cuenta: sólo se activa el más viejo, aunque el otro sea único');
check(count($active) === 1, 'y no se rellenan los huecos de aldea con artefactos de cuenta');

// Tres de cuenta y ninguno de aldea: sigue quedando uno solo activo.
$rows = array(
    art(1, ARTEFACT_DIET, 2, $old),
    art(2, ARTEFACT_DIET, 2, $mid),
    art(3, ARTEFACT_DIET, 3, $new)
);
check(count(artefactActiveRows($rows, $now, 3)) === 1,
    'tener tres artefactos de cuenta no llena el podio: uno solo puede estar activo');

// Los que todavía no maduraron no ocupan lugar.
$rows = array(
    art(1, ARTEFACT_ARCHITECT, 1, $now),         // recién capturado
    art(2, ARTEFACT_BOOTS,     1, $old),
    art(3, ARTEFACT_EAGLE,     1, $mid)
);
check(ids(artefactActiveRows($rows, $now, 3)) === array(2, 3),
    'un artefacto sin madurar no ocupa un hueco del podio');

// Empate exacto de fecha: se resuelve por id, y siempre igual.
$rows = array(
    art(9, ARTEFACT_ARCHITECT, 1, $old),
    art(4, ARTEFACT_BOOTS,     1, $old),
    art(7, ARTEFACT_EAGLE,     1, $old),
    art(2, ARTEFACT_DIET,      1, $old)
);
$first = ids(artefactActiveRows($rows, $now, 3));
check($first === array(2, 4, 7), 'un empate de fecha se resuelve por id, del más bajo al más alto');
check($first === ids(artefactActiveRows(array_reverse($rows), $now, 3)),
    'y el resultado no puede depender del orden en que vengan las filas');

check(artefactActiveRows(array(), $now, 3) === array(), 'sin artefactos no hay activos');
check(artefactActiveRows(null, $now, 3) === array(), 'una entrada inválida no puede reventar');

// -------------------------------------------------------------------------------------
section('C. El de aldea pisa al de cuenta, y sólo en su aldea');

$VILLAGE_A = 100;
$VILLAGE_B = 200;
$rows = array(
    art(1, ARTEFACT_DIET, 3, $old, 999),         // único, alcance cuenta
    art(2, ARTEFACT_DIET, 1, $mid, $VILLAGE_A)   // pequeño, sólo en la aldea A
);
$active = artefactActiveRows($rows, $now, 3);
check(count($active) === 2, 'los dos están activos: ocupan huecos distintos del podio');

$inA = artefactEffectiveRow($active, ARTEFACT_DIET, $VILLAGE_A);
$inB = artefactEffectiveRow($active, ARTEFACT_DIET, $VILLAGE_B);
check($inA !== null && (int)$inA['id'] === 2,
    'en la aldea que tiene el pequeño manda el pequeño, no el único');
check($inB !== null && (int)$inB['id'] === 1,
    'y en las demás aldeas sigue mandando el de cuenta');

// No se suman: el valor efectivo es el de uno solo.
check(artefactVillageEffectValue($active, ARTEFACT_DIET, $VILLAGE_A) === 0.5,
    'el pequeño de dieta deja el consumo en 1/2');
check(artefactVillageEffectValue($active, ARTEFACT_DIET, $VILLAGE_B) === 0.5,
    'el único de dieta también deja el consumo en 1/2 en el resto de las aldeas');

// Un pequeño de OTRA aldea no llega.
$rows = array(art(1, ARTEFACT_BOOTS, 1, $old, $VILLAGE_A));
$active = artefactActiveRows($rows, $now, 3);
check(artefactEffectiveRow($active, ARTEFACT_BOOTS, $VILLAGE_B) === null,
    'un artefacto pequeño no hace nada fuera de su aldea');
check(artefactVillageEffectValue($active, ARTEFACT_BOOTS, $VILLAGE_B) === 1.0,
    'y su valor efectivo afuera es el neutro, no cero');

// Tipos distintos no se pisan entre sí.
$rows = array(
    art(1, ARTEFACT_BOOTS, 1, $old, $VILLAGE_A),
    art(2, ARTEFACT_DIET,  1, $mid, $VILLAGE_A)
);
$active = artefactActiveRows($rows, $now, 3);
check(artefactVillageEffectValue($active, ARTEFACT_BOOTS, $VILLAGE_A) === 2.0
    && artefactVillageEffectValue($active, ARTEFACT_DIET, $VILLAGE_A) === 0.5,
    'dos artefactos de tipos distintos en la misma aldea valen los dos');

// -------------------------------------------------------------------------------------
section('D. El estado que ve el jugador');

$rows = array(
    art(1, ARTEFACT_ARCHITECT, 1, $old),
    art(2, ARTEFACT_BOOTS,     1, $old + 1),
    art(3, ARTEFACT_EAGLE,     1, $old + 2),
    art(4, ARTEFACT_DIET,      1, $mid),         // maduro pero fuera del podio
    art(5, ARTEFACT_TRAINER,   1, $now)          // todavía corriendo el retardo
);
$active = artefactActiveRows($rows, $now, 3);
$states = array();
foreach($rows as $row) {
    $states[(int)$row['id']] = artefactActivationState($row, $active, $now, 3);
}
check($states[1]['state'] === 'active', 'el más viejo figura como activo');
check($states[4]['state'] === 'displaced',
    'un artefacto maduro que no entra en el podio figura como inactivo, no como activo');
check($states[5]['state'] === 'pending', 'y uno recién capturado figura como pendiente');
check($states[5]['seconds'] > 0, 'con los segundos que le faltan');
check($states[4]['seconds'] === 0, 'el desplazado no tiene cuenta atrás: ya maduró');
check(artefactActivationStateLabel($states[1]) !== '' && artefactActivationStateLabel($states[4]) !== '',
    'los tres estados tienen texto en español');

// -------------------------------------------------------------------------------------
echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Activación de artefactos: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
