<?php
/**
 * Los artefactos: qué son, cuándo hacen efecto y cuánto valen.
 *
 * Por qué existe este archivo. El motor consultaba los artefactos con cuatro consultas
 * que pedían columnas inexistentes (`artefacts.active`, `artefacts.kind`, y el necio
 * además `bad_effect`/`effect2`). Las cuatro fallaban en silencio —`getActiveArtefactsByType()`
 * devolvía `array()` en vez de reventar—, así que **todos** los efectos de artefacto
 * estaban muertos: la durabilidad contra catapultas, el disparo al azar, el tiempo de
 * entrenamiento y el artefacto del necio. Los dos que sí funcionaban (el consumo de
 * cereal y el plano de almacenamiento) lo hacían porque no pasaban por ahí, y por eso
 * mismo tampoco respetaban el retardo de activación ni el límite de activos.
 *
 * La respuesta no fue agregar las columnas: `active` y `kind` son **derivables**, y en
 * este repo el estado derivado no se guarda (ver las aldeas natar vivas en
 * NatarSettlement.php). `artefacts.conquered` —el momento de la captura, que ya se
 * guardaba— alcanza para las dos cosas:
 *
 *   - el retardo de activación se mide contra él;
 *   - la prioridad entre artefactos ("los más viejos primero") lo usa de orden;
 *   - la tirada del necio sale de una PRNG sembrada con `(id, ventana de 24 h)`, así
 *     que es estable dentro de la ventana y no necesita ni columna ni cron.
 *
 * ---------------------------------------------------------------------------------
 * LA REGLA OFICIAL, que es más restrictiva de lo que parece.
 *
 * Una cuenta tiene **como mucho 3 artefactos activos a la vez, y sólo uno de ellos
 * puede ser de alcance cuenta** (grande o único). El juego activa el más viejo de
 * cuenta más los dos más viejos de aldea; si no hay ninguno de cuenta, activa los tres
 * más viejos de aldea. Tener más artefactos no está prohibido: los de más simplemente
 * no hacen nada. Y "más viejo" es por `conquered`, que se **reinicia en cada captura**
 * —volver a conquistar un artefacto propio, o la aldea que lo guarda, es la forma
 * oficial de mandarlo al fondo de la cola y dejar entrar a otro.
 *
 * Dentro de una aldea, un artefacto **de aldea pisa al de cuenta** del mismo tipo: no
 * se suman ni gana el más fuerte, gana el de aldea. Por eso un único (cuenta, y en
 * varios tipos más fuerte que el pequeño) queda anulado en la aldea que tenga el
 * pequeño del mismo tipo, y sigue valiendo en todas las demás.
 *
 * ---------------------------------------------------------------------------------
 * LOS TIPOS. La numeración es la del panel de administración (`Admin/Mods/addArtefacts.php`)
 * y la que ya usaban los consumidores del motor. `cn.php` traía **otra** numeración
 * incompatible —ahí el 1 era el plano de la Maravilla— y por eso se borró: sembrar
 * artefactos desde ahí daba artefactos cuyo nombre no tenía nada que ver con su efecto.
 *
 * El plano de construcción de la Maravilla del Mundo **no está implementado** en este
 * mundo (la Maravilla no se puede levantar desde cero, ver `Building::meetRequirement`),
 * así que no tiene tipo asignado. El 9 queda libre para cuando se implemente.
 */

if(!defined('ARTEFACT_ARCHITECT')) {
    define('ARTEFACT_ARCHITECT', 1);   // los edificios aguantan más catapultas y arietes
    define('ARTEFACT_BOOTS',     2);   // las tropas se mueven más rápido
    define('ARTEFACT_EAGLE',     3);   // los exploradores espían y defienden mejor
    define('ARTEFACT_DIET',      4);   // las tropas comen menos cereal
    define('ARTEFACT_TRAINER',   5);   // las tropas se entrenan más rápido
    define('ARTEFACT_STORAGE',   6);   // plano: habilita gran almacén y gran granero
    define('ARTEFACT_CONFUSION', 7);   // escondites enormes y catapultas enemigas al azar
    define('ARTEFACT_FOOL',      8);   // un efecto distinto cada 24 h
}

if(!defined('ARTEFACT_SIZE_SMALL')) {
    define('ARTEFACT_SIZE_SMALL',  1); // alcance: la aldea que lo guarda
    define('ARTEFACT_SIZE_LARGE',  2); // alcance: toda la cuenta
    define('ARTEFACT_SIZE_UNIQUE', 3); // alcance: toda la cuenta, y hay uno solo por mundo
}

/** Cuántos artefactos activos admite una cuenta, y cuántos de ellos pueden ser de cuenta. */
if(!defined('ARTEFACT_MAX_ACTIVE')) {
    define('ARTEFACT_MAX_ACTIVE', 3);
    define('ARTEFACT_MAX_ACTIVE_ACCOUNT', 1);
}

/** Cada cuánto vuelve a tirar el dado el artefacto del necio. Oficial: 24 h, sin escalar. */
if(!defined('ARTEFACT_FOOL_WINDOW')) {
    define('ARTEFACT_FOOL_WINDOW', 86400);
}

/**
 * Los ocho tipos, con el nombre que ve el jugador y la línea de efecto.
 *
 * El nombre no sale de `artefacts.name`: esa columna guarda lo que escribió quien sembró
 * el artefacto y por eso ya había artefactos con nombre de un efecto y número de otro.
 * Para lo que el jugador lee mandan estas dos, igual que `buildingDisplayName()` manda
 * sobre los nombres de edificio.
 */
function artefactTypeCatalog() {
    return array(
        ARTEFACT_ARCHITECT => array(
            'name' => 'Secreto del arquitecto',
            'sizes' => array(1 => 'Pequeño secreto del arquitecto', 2 => 'Gran secreto del arquitecto', 3 => 'Secreto único del arquitecto'),
            'effect' => 'Los edificios de la zona de efecto son más resistentes a catapultas y arietes.'),
        ARTEFACT_BOOTS => array(
            'name' => 'Botas de los titanes',
            'sizes' => array(1 => 'Pequeñas botas de los titanes', 2 => 'Grandes botas de los titanes', 3 => 'Botas únicas de los titanes'),
            'effect' => 'Las tropas de la zona de efecto se mueven más rápido.'),
        ARTEFACT_EAGLE => array(
            'name' => 'Ojos del águila',
            'sizes' => array(1 => 'Pequeños ojos del águila', 2 => 'Grandes ojos del águila', 3 => 'Ojos únicos del águila'),
            'effect' => 'Tus exploradores espían mejor y también defienden mejor contra el espionaje.'),
        ARTEFACT_DIET => array(
            'name' => 'Control de dieta',
            'sizes' => array(1 => 'Pequeño control de dieta', 2 => 'Gran control de dieta', 3 => 'Control único de dieta'),
            'effect' => 'Las tropas de la zona de efecto consumen menos cereal.'),
        ARTEFACT_TRAINER => array(
            'name' => 'Talento del entrenador',
            'sizes' => array(1 => 'Pequeño talento del entrenador', 2 => 'Gran talento del entrenador', 3 => 'Talento único del entrenador'),
            'effect' => 'Las tropas se entrenan más rápido en cuartel, establo y taller.'),
        ARTEFACT_STORAGE => array(
            'name' => 'Plano de almacenamiento',
            'sizes' => array(1 => 'Pequeño plano de almacenamiento', 2 => 'Gran plano de almacenamiento', 3 => 'Plano único de almacenamiento'),
            'effect' => 'Permite construir y mejorar el gran almacén y el gran granero.'),
        ARTEFACT_CONFUSION => array(
            'name' => 'Confusión del rival',
            'sizes' => array(1 => 'Pequeña confusión del rival', 2 => 'Gran confusión del rival', 3 => 'Confusión única del rival'),
            'effect' => 'Escondites mucho más grandes, y las catapultas enemigas sólo pueden elegir objetivos al azar.'),
        ARTEFACT_FOOL => array(
            'name' => 'Artefacto del necio',
            'sizes' => array(1 => 'Pequeño artefacto del necio', 2 => 'Gran artefacto del necio', 3 => 'Artefacto único del necio'),
            'effect' => 'Cada 24 horas toma al azar el efecto de otro artefacto, para bien o para mal.')
    );
}

function artefactTypeName($type) {
    $catalog = artefactTypeCatalog();
    $type = (int)$type;
    return isset($catalog[$type]) ? $catalog[$type]['name'] : 'Artefacto desconocido';
}

function artefactTypeEffectText($type) {
    $catalog = artefactTypeCatalog();
    $type = (int)$type;
    return isset($catalog[$type]) ? $catalog[$type]['effect'] : '';
}

/**
 * El nombre completo de un artefacto concreto, con su tamaño delante.
 * "Pequeño secreto del arquitecto", "Gran control de dieta", "Ojos únicos del águila".
 */
function artefactDisplayName($type, $size) {
    $catalog = artefactTypeCatalog();
    $type = (int)$type;
    $size = (int)$size;
    if(isset($catalog[$type]['sizes'][$size])) {
        return $catalog[$type]['sizes'][$size];
    }
    return artefactTypeName($type);
}

/** 'village' para el pequeño, 'account' para el grande y el único. */
function artefactSizeScope($size) {
    return (int)$size === ARTEFACT_SIZE_SMALL ? 'village' : 'account';
}

function artefactSizeName($size) {
    switch((int)$size) {
        case ARTEFACT_SIZE_SMALL:  return 'Esta aldea';
        case ARTEFACT_SIZE_LARGE:  return 'Todas las aldeas';
        case ARTEFACT_SIZE_UNIQUE: return 'Todas las aldeas';
    }
    return '';
}

/**
 * El nivel de Tesoro que necesita la aldea que se lo queda: 10 para el pequeño, 20 para
 * el grande y el único. Es lo mismo que ya comprobaba `canClaimArtifact()`; vive acá
 * para que la pantalla del edificio no tenga su propia copia (tenía dos, y una decía 10
 * para cualquier tamaño).
 */
function artefactTreasuryRequirement($size) {
    return (int)$size === ARTEFACT_SIZE_SMALL ? 10 : 20;
}

/**
 * El retardo de activación, que **escala con la velocidad del mundo**.
 *
 * Tabla oficial: 24 h en x1, 16 h en x2, 12 h en x3, 8 h en x5 y 4 h en x10. No es
 * 24/velocidad ni ninguna fórmula cerrada, así que es una tabla; una velocidad que no
 * esté en ella cae a la entrada definida más cercana. El repo tenía el 86400 escrito a
 * mano en `27_show.tpl` y en ningún lado más, así que la pantalla decía "inactivo"
 * durante 24 h mientras el motor no miraba nada.
 */
function artefactActivationDelay($speed = null) {
    if($speed === null) {
        $speed = defined('SPEED') ? SPEED : 1;
    }
    $speed = max(1, (float)$speed);
    $table = array(1 => 86400, 2 => 57600, 3 => 43200, 5 => 28800, 10 => 14400);
    if(isset($table[(int)$speed]) && (float)(int)$speed === $speed) {
        return $table[(int)$speed];
    }
    $best = null;
    $bestDistance = null;
    foreach($table as $tableSpeed => $delay) {
        $distance = abs($tableSpeed - $speed);
        if($bestDistance === null || $distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $delay;
        }
    }
    return $best === null ? 86400 : $best;
}

/** Segundos que le faltan a un artefacto para activarse; 0 si ya pasó el retardo. */
function artefactSecondsUntilActive($row, $now = null, $speed = null) {
    $now = $now === null ? time() : (int)$now;
    $conquered = isset($row['conquered']) ? (int)$row['conquered'] : 0;
    $remaining = ($conquered + artefactActivationDelay($speed)) - $now;
    return $remaining > 0 ? $remaining : 0;
}

function artefactIsMature($row, $now = null, $speed = null) {
    return artefactSecondsUntilActive($row, $now, $speed) === 0;
}

/**
 * Los artefactos de una cuenta que están activos ahora mismo.
 *
 * Recibe TODAS las filas de un dueño y devuelve el subconjunto activo, en el orden en
 * que el juego los prioriza. La regla es la oficial y está explicada arriba: el más
 * viejo de cuenta más los dos más viejos de aldea, y si no hay ninguno de cuenta, los
 * tres más viejos de aldea. Sólo entran los que ya pasaron el retardo.
 *
 * Empate de `conquered`: gana el id más bajo. Da igual cuál sea mientras sea estable —
 * si no lo fuera, dos cargas de página seguidas activarían artefactos distintos.
 */
function artefactActiveRows($rows, $now = null, $speed = null) {
    if(!is_array($rows)) {
        return array();
    }
    $mature = array();
    foreach($rows as $row) {
        if(!is_array($row) || !isset($row['size'])) {
            continue;
        }
        if(artefactIsMature($row, $now, $speed)) {
            $mature[] = $row;
        }
    }
    usort($mature, 'artefactPriorityCompare');

    $active = array();
    $accountUsed = 0;
    $villageUsed = 0;
    $villageSlots = ARTEFACT_MAX_ACTIVE - ARTEFACT_MAX_ACTIVE_ACCOUNT;

    // Primera pasada: el (único) hueco de cuenta y los huecos de aldea.
    foreach($mature as $row) {
        if(count($active) >= ARTEFACT_MAX_ACTIVE) {
            break;
        }
        if(artefactSizeScope($row['size']) === 'account') {
            if($accountUsed >= ARTEFACT_MAX_ACTIVE_ACCOUNT) {
                continue;
            }
            $accountUsed++;
        } else {
            if($villageUsed >= $villageSlots) {
                continue;
            }
            $villageUsed++;
        }
        $active[] = $row;
    }

    // Sin artefacto de cuenta, el hueco reservado se lo queda un tercero de aldea.
    if($accountUsed === 0 && count($active) < ARTEFACT_MAX_ACTIVE) {
        foreach($mature as $row) {
            if(count($active) >= ARTEFACT_MAX_ACTIVE) {
                break;
            }
            if(artefactSizeScope($row['size']) !== 'village') {
                continue;
            }
            if(artefactRowInList($row, $active)) {
                continue;
            }
            $active[] = $row;
        }
    }

    return $active;
}

function artefactPriorityCompare($a, $b) {
    $ca = isset($a['conquered']) ? (int)$a['conquered'] : 0;
    $cb = isset($b['conquered']) ? (int)$b['conquered'] : 0;
    if($ca !== $cb) {
        return $ca < $cb ? -1 : 1;
    }
    $ia = isset($a['id']) ? (int)$a['id'] : 0;
    $ib = isset($b['id']) ? (int)$b['id'] : 0;
    if($ia === $ib) {
        return 0;
    }
    return $ia < $ib ? -1 : 1;
}

function artefactRowInList($row, $list) {
    $id = isset($row['id']) ? (int)$row['id'] : 0;
    foreach($list as $candidate) {
        if((int)(isset($candidate['id']) ? $candidate['id'] : 0) === $id) {
            return true;
        }
    }
    return false;
}

/**
 * El tipo de efecto que un artefacto aplica HOY.
 *
 * Para siete de los ocho es su propio tipo. El necio imita a otro y cambia cada 24 h.
 */
function artefactEffectiveType($row, $now = null) {
    $type = isset($row['type']) ? (int)$row['type'] : 0;
    if($type !== ARTEFACT_FOOL) {
        return $type;
    }
    $roll = artefactFoolRoll($row, $now);
    return $roll['type'];
}

/**
 * La tirada del necio: derivada, no guardada.
 *
 * Sale de un hash del id del artefacto y del número de ventana de 24 h desde su captura,
 * así que es la misma para todos los procesos dentro de la ventana, cambia sola al
 * cerrarse, y no necesita ni columna ni barrido.
 *
 * Es un hash y **no** `mt_srand()` a propósito: la semilla de PHP es global, así que
 * sembrarla acá volvería determinista el `rand()` de la mitad del motor —los animales del
 * oasis, los objetos de aventura, el tipo de aldea que sale del mapa— hasta la siguiente
 * siembra. Y no se puede "guardar y restaurar": `mt_srand(mt_rand())` deja la secuencia
 * en otro lado, no donde estaba. Es justo el tipo de efecto a distancia que después nadie
 * encuentra.
 *
 * Oficial: puede tomar cualquier efecto salvo los planos (almacenamiento y Maravilla), y
 * puede salir para bien o para mal, salvo el único, que siempre sale para bien.
 */
function artefactFoolRoll($row, $now = null) {
    $now = $now === null ? time() : (int)$now;
    $id = isset($row['id']) ? (int)$row['id'] : 0;
    $size = isset($row['size']) ? (int)$row['size'] : ARTEFACT_SIZE_SMALL;
    $conquered = isset($row['conquered']) ? (int)$row['conquered'] : 0;
    $window = (int)floor(max(0, $now - $conquered) / ARTEFACT_FOOL_WINDOW);

    $candidates = artefactFoolCandidateTypes();
    $type = $candidates[artefactFoolEntropy($id, $window, 'type') % count($candidates)];
    // El único nunca sale para mal; los otros dos, una de cada tres veces.
    $penalty = $size === ARTEFACT_SIZE_UNIQUE
        ? false
        : (artefactFoolEntropy($id, $window, 'sign') % 3 === 0);

    return array('type' => $type, 'penalty' => $penalty, 'window' => $window);
}

/** Un entero estable a partir de (artefacto, ventana, propósito). Sin estado global. */
function artefactFoolEntropy($id, $window, $salt) {
    return (int)hexdec(substr(md5($salt.':'.(int)$id.':'.(int)$window), 0, 7));
}

/** Los efectos que el necio puede imitar: todos menos los planos y él mismo. */
function artefactFoolCandidateTypes() {
    return array(
        ARTEFACT_ARCHITECT,
        ARTEFACT_BOOTS,
        ARTEFACT_EAGLE,
        ARTEFACT_DIET,
        ARTEFACT_TRAINER,
        ARTEFACT_CONFUSION
    );
}

/** Cuándo vuelve a tirar el dado el necio (timestamp), o 0 si la fila no es un necio. */
function artefactFoolNextRoll($row, $now = null) {
    if((int)(isset($row['type']) ? $row['type'] : 0) !== ARTEFACT_FOOL) {
        return 0;
    }
    $roll = artefactFoolRoll($row, $now);
    $conquered = isset($row['conquered']) ? (int)$row['conquered'] : 0;
    return $conquered + ($roll['window'] + 1) * ARTEFACT_FOOL_WINDOW;
}

/**
 * La tabla oficial de valores, por tipo y tamaño.
 *
 * El patrón general: el pequeño es fuerte pero sólo en su aldea, el grande es flojo en
 * toda la cuenta, y el único junta las dos cosas. El águila se sale del patrón (el único
 * es más fuerte que el pequeño) y el arquitecto también (el grande vale 3 y el pequeño 4).
 * No inventes interpolaciones: cada casilla es un número publicado.
 */
function artefactValueTable() {
    return array(
        // multiplicador de durabilidad de los edificios
        ARTEFACT_ARCHITECT => array(1 => 4.0,   2 => 3.0,   3 => 5.0),
        // multiplicador de velocidad de las tropas
        ARTEFACT_BOOTS     => array(1 => 2.0,   2 => 1.5,   3 => 2.0),
        // multiplicador de eficacia de los exploradores
        ARTEFACT_EAGLE     => array(1 => 5.0,   2 => 3.0,   3 => 10.0),
        // fracción del cereal que las tropas SIGUEN comiendo (1/2, 3/4, 1/2)
        ARTEFACT_DIET      => array(1 => 0.5,   2 => 0.75,  3 => 0.5),
        // fracción del tiempo de entrenamiento que queda (1/2, 3/4, 1/2)
        ARTEFACT_TRAINER   => array(1 => 0.5,   2 => 0.75,  3 => 0.5),
        // el plano es binario: no escala con el tamaño
        ARTEFACT_STORAGE   => array(1 => 1.0,   2 => 1.0,   3 => 1.0),
        // multiplicador de capacidad del escondite
        ARTEFACT_CONFUSION => array(1 => 200.0, 2 => 100.0, 3 => 500.0)
    );
}

/**
 * El valor que aplica una fila concreta para un tipo de efecto.
 *
 * Devuelve el valor neutro (1 para los multiplicadores, 1 para las fracciones) si la
 * fila no aplica a ese tipo, así que quien llama puede multiplicar sin preguntar.
 * Un necio "para mal" devuelve el recíproco: el bono al revés. Los números del castigo
 * no están publicados, así que el recíproco es una decisión de este mundo, no oficial —
 * pero es la única que mantiene la simetría (un x4 de durabilidad se vuelve x1/4).
 */
function artefactEffectValue($row, $type, $now = null) {
    $type = (int)$type;
    if(artefactEffectiveType($row, $now) !== $type) {
        return artefactNeutralValue($type);
    }
    $table = artefactValueTable();
    $size = isset($row['size']) ? (int)$row['size'] : ARTEFACT_SIZE_SMALL;
    if(!isset($table[$type][$size])) {
        return artefactNeutralValue($type);
    }
    $value = (float)$table[$type][$size];
    if((int)(isset($row['type']) ? $row['type'] : 0) === ARTEFACT_FOOL) {
        $roll = artefactFoolRoll($row, $now);
        if($roll['penalty'] && $value > 0) {
            $value = 1 / $value;
        }
    }
    return $value;
}

/** El valor que no cambia nada. Es 1 para todos: son todos multiplicadores o fracciones. */
function artefactNeutralValue($type) {
    return 1.0;
}

/**
 * El artefacto que manda sobre una aldea para un tipo de efecto, o null.
 *
 * Acá vive la regla de que **el de aldea pisa al de cuenta**: no se suman ni gana el más
 * fuerte. Recibe ya filtrado el conjunto activo, así que no vuelve a mirar el retardo.
 */
function artefactEffectiveRow($activeRows, $type, $villageId, $now = null) {
    $type = (int)$type;
    $villageId = (int)$villageId;
    $accountRow = null;
    foreach((array)$activeRows as $row) {
        if(!is_array($row) || artefactEffectiveType($row, $now) !== $type) {
            continue;
        }
        if(artefactSizeScope(isset($row['size']) ? $row['size'] : 0) === 'village') {
            if((int)(isset($row['vref']) ? $row['vref'] : 0) === $villageId) {
                return $row;   // el de la aldea gana siempre
            }
            continue;          // el pequeño de otra aldea no llega hasta acá
        }
        if($accountRow === null) {
            $accountRow = $row;
        }
    }
    return $accountRow;
}

/**
 * El valor efectivo de un tipo de efecto en una aldea, resolviendo todo de una vez.
 * Es lo que usan los consumidores del motor; devuelve el valor neutro si no hay nada.
 */
function artefactVillageEffectValue($activeRows, $type, $villageId, $now = null) {
    $row = artefactEffectiveRow($activeRows, $type, $villageId, $now);
    if($row === null) {
        return artefactNeutralValue($type);
    }
    return artefactEffectValue($row, $type, $now);
}

/**
 * Un necio único no puede ser un castigo, y el pequeño de aldea sólo pisa dentro de su
 * aldea: las dos cosas ya están arriba. Esto es sólo azúcar para el caso booleano del
 * plano de almacenamiento, que no tiene valor sino presencia.
 */
function artefactVillageHasEffect($activeRows, $type, $villageId, $now = null) {
    return artefactEffectiveRow($activeRows, $type, $villageId, $now) !== null;
}

/**
 * Si un ataque se lleva el artefacto, y si no, por qué no.
 *
 * Está separado del motor por lo mismo que `oasisAnnexationOutcome()`: es la parte que
 * hay que poder probar sin resolver una batalla entera, y es la que tenía agujeros. Las
 * cinco condiciones son las oficiales y ninguna sobra:
 *
 *   - **Ataque normal, nunca asalto.** En un asalto el héroe saquea y se va.
 *   - **El héroe tiene que ir y volver vivo.** Esto no se comprobaba: un héroe muerto en
 *     la batalla se llevaba el artefacto igual. (El oasis sí lo comprobaba, con `$dead11`.)
 *   - **El Tesoro de la aldea atacada tiene que estar derribado.** Tampoco se comprobaba,
 *     y era el agujero grande: el héroe entraba a una aldea con el Tesoro intacto y se
 *     llevaba el artefacto sin necesidad de una sola catapulta. Oficial: la oleada de
 *     catapultas y la del héroe pueden ser el mismo ataque, y acá también, porque las
 *     catapultas se resuelven antes en `sendunitsComplete()`.
 *   - **La aldea que se lo lleva necesita el Tesoro al nivel del tamaño** (10 para el
 *     pequeño, 20 para el grande y el único).
 *   - **Y ese Tesoro tiene que estar vacío**: una aldea sostiene un solo artefacto.
 *
 * El orden importa para el informe: primero lo que decide el jugador al armar el ataque
 * (asalto, héroe), después lo que decide en el campo (el Tesoro enemigo) y al final lo
 * que decide en su casa (su propio Tesoro).
 */
function artefactTheftOutcome($attack, $target, $attacker) {
    $status = function($code, $extra = array()) {
        return array_merge(array('status' => $code), $extra);
    };

    $size = isset($target['size']) ? (int)$target['size'] : 0;
    if(!isset($target['artefact']) || !$target['artefact']) {
        return $status('no_artefact');
    }
    if((int)(isset($attack['type']) ? $attack['type'] : 0) !== 3) {
        return $status('raid');
    }
    if((int)(isset($attack['hero_sent']) ? $attack['hero_sent'] : 0) <= 0) {
        return $status('no_hero');
    }
    if(!empty($attack['hero_dead'])) {
        return $status('hero_dead');
    }
    if((int)(isset($target['treasury']) ? $target['treasury'] : 0) > 0) {
        return $status('defender_treasury_standing',
            array('treasury' => (int)$target['treasury']));
    }
    $required = artefactTreasuryRequirement($size);
    if((int)(isset($attacker['treasury']) ? $attacker['treasury'] : 0) < $required) {
        return $status('attacker_treasury_low', array('needed' => $required));
    }
    if(!empty($attacker['artefact'])) {
        return $status('attacker_treasury_occupied');
    }
    return $status('claimed', array('size' => $size));
}

/** Los textos del informe para cada resultado de `artefactTheftOutcome()`. */
function artefactTheftMessages() {
    return array(
        'raid' => 'un asalto no alcanza para llevarse un artefacto: hace falta un ataque normal.',
        'no_hero' => 'hace falta que tu héroe participe del ataque para llevarse el artefacto.',
        'hero_dead' => 'tu héroe murió en la batalla, así que el artefacto se quedó donde estaba.',
        'defender_treasury_standing' => 'el Tesoro enemigo sigue en pie: hay que derribarlo para llevarse el artefacto.',
        'attacker_treasury_low' => 'tu aldea necesita un Tesoro de nivel %d para guardar este artefacto.',
        'attacker_treasury_occupied' => 'el Tesoro de tu aldea ya guarda un artefacto.',
        'claimed' => 'tu héroe se lleva el artefacto a la aldea.',
        'database_error' => 'el artefacto no pudo cambiar de manos por un error del servidor.'
    );
}

function artefactTheftMessage($outcome) {
    $messages = artefactTheftMessages();
    $code = isset($outcome['status']) ? $outcome['status'] : '';
    if(!isset($messages[$code])) {
        return '';
    }
    if($code === 'attacker_treasury_low') {
        return sprintf($messages[$code], isset($outcome['needed']) ? (int)$outcome['needed'] : 10);
    }
    return $messages[$code];
}

/**
 * El consumo de cereal de una guarnición después del artefacto de dieta.
 *
 * Una sola definición porque hay dos caminos que la necesitan y tienen que dar el mismo
 * número: `Village::calculateProduction()` (lo que el jugador ve en dorf1) y
 * `Automation::bountycalculateProduction()` (lo que se acredita de verdad, y de lo que
 * come la hambruna). Las dos traían su propia copia de la cascada de tres `if` y las dos
 * cobraban **3/4 al artefacto pequeño**, cuando el oficial le cobra 1/2: el pequeño es el
 * fuerte y el grande el flojo, no al revés.
 *
 * Devuelve lo que la aldea paga, lo que ahorra y el factor. El ahorro puede ser negativo:
 * un artefacto del necio en su cara mala cobra de más, y esa es su gracia.
 */
function artefactTroopUpkeep($database, $owner, $villageId, $upkeep) {
    $upkeep = (int)$upkeep;
    $factor = 1.0;
    if(is_object($database) && method_exists($database, 'getArtefactEffectValue')) {
        $candidate = (float)$database->getArtefactEffectValue($villageId, $owner, ARTEFACT_DIET);
        if($candidate > 0) {
            $factor = $candidate;
        }
    }
    $charged = (int)round($upkeep * $factor);
    return array('charged' => $charged, 'saving' => $upkeep - $charged, 'factor' => $factor);
}

/**
 * El multiplicador de velocidad de las tropas de una aldea (1 si no hay artefacto).
 *
 * Las botas de los titanes valen x2 el pequeño, x1,5 el grande y x2 el único, y valen
 * para las tropas de la aldea que las guarda —o de toda la cuenta si es grande o único—,
 * en la ida y en la vuelta. Antes este efecto **sólo existía en la lista de granjeo**
 * (`Templates/a2b/startRaid.tpl`): el mismo ataque lanzado desde el punto de reunión
 * tardaba el doble, y la vuelta de un asalto acelerado tardaba lo normal.
 *
 * Se aplica sobre la aldea de ORIGEN de las tropas, que en un movimiento de vuelta es la
 * de destino del movimiento. Quien llama ya sabe cuál es; esta función no lo adivina.
 */
function artefactTroopSpeedFactor($database, $owner, $villageId) {
    if(!is_object($database) || !method_exists($database, 'getArtefactEffectValue')) {
        return 1.0;
    }
    $value = (float)$database->getArtefactEffectValue((int)$villageId, (int)$owner, ARTEFACT_BOOTS);
    return $value > 0 ? $value : 1.0;
}

/**
 * En qué estado está un artefacto para su dueño, para la pantalla del Tesoro.
 *
 *   'active'    — está haciendo efecto.
 *   'pending'   — todavía corre el retardo de activación; 'seconds' dice cuánto falta.
 *   'displaced' — ya pasó el retardo pero no entra en el podio de tres (o de uno, si es
 *                 de cuenta y hay otro de cuenta más viejo). Es el estado que sorprende:
 *                 el artefacto es tuyo y no hace nada.
 */
function artefactActivationState($row, $activeRows, $now = null, $speed = null) {
    $pending = artefactSecondsUntilActive($row, $now, $speed);
    if($pending > 0) {
        return array('state' => 'pending', 'seconds' => $pending);
    }
    return array(
        'state' => artefactRowInList($row, (array)$activeRows) ? 'active' : 'displaced',
        'seconds' => 0
    );
}

function artefactActivationStateLabel($state) {
    switch(isset($state['state']) ? $state['state'] : '') {
        case 'active':    return 'Activo';
        case 'pending':   return 'Se activa en';
        case 'displaced': return 'Inactivo';
    }
    return '';
}

/**
 * El valor del efecto tal como se lee en la ficha: "x4", "1/2", "x200".
 *
 * Los multiplicadores se muestran como "xN" y las fracciones (dieta y entrenamiento,
 * donde un valor menor es mejor) como "1/2" y "3/4", que es como los anuncia el oficial.
 * El necio en su cara mala invierte el número, así que sale por la otra rama.
 */
function artefactEffectValueLabel($row, $now = null) {
    $type = artefactEffectiveType($row, $now);
    if($type === ARTEFACT_STORAGE) {
        return 'Gran almacén y gran granero';
    }
    $value = artefactEffectValue($row, $type, $now);
    if($value <= 0) {
        return '';
    }
    if($value < 1) {
        $inverse = 1 / $value;
        // 0,5 -> 1/2 y 0,75 -> 4/3 no: la fracción que anuncia el oficial es la del
        // consumo/tiempo que QUEDA, o sea 1/2 y 3/4.
        if(abs($value - 0.5) < 0.001)  { return '1/2'; }
        if(abs($value - 0.75) < 0.001) { return '3/4'; }
        return '1/'.rtrim(rtrim(number_format($inverse, 2, ',', '.'), '0'), ',');
    }
    return 'x'.rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
}
