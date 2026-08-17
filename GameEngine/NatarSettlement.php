<?php
/**
 * Aldeas natar independientes: la tercera clase de aldea natar del T4 oficial.
 *
 * A diferencia de las Maravillas y la capital, que son escenario con una guarnición que se
 * gasta una sola vez, éstas son aldeas de verdad sin jugador detrás. Aparecen de a poco,
 * suben sus campos, producen, entrenan tropas y se pueden saquear y arrasar.
 *
 * La idea que sostiene todo el módulo: **el estado de una aldea viva se deriva de su
 * edad**, no se acumula. Los campos suben en un cronograma, la producción sale de los
 * campos, y la guarnición que la aldea puede sostener sale del cereal. Todo es función
 * pura de (nacimiento, wref, ahora): el wref hace de semilla, así que dos aldeas de la
 * misma edad no salen idénticas pero cada una sí es reproducible. Recalcular dos veces da
 * lo mismo, y no hay estado propio que dos requests simultáneos puedan desincronizar.
 *
 * Lo único que no se puede derivar son las tropas, porque los jugadores las matan. Por eso
 * `units` sigue siendo la verdad y se la empuja HACIA el objetivo derivado, igual que
 * updateRes() empuja los recursos hacia el presente. Eso necesita un reloj que la
 * acreditación de recursos no mueva: `vdata.npcupdate`.
 *
 * El cereal es la perilla de dificultad. Una aldea viva paga manutención y pasa hambre
 * como la de cualquier jugador, así que su guarnición converge sola a lo que sus campos
 * alimentan. No hay ninguna constante de dificultad inventada: se mueve el cronograma de
 * los campos y todo lo demás sigue.
 */

require_once __DIR__.'/Accounts.php';
require_once __DIR__.'/NatarVillage.php';

// --- Perillas ------------------------------------------------------------------------
// Todas configurables: si el balance queda flojo se mueven sin tocar el resto.

if(!defined('NATAR_SETTLEMENT_MAX')) {
    // Tope global de aldeas vivas en el mundo.
    define('NATAR_SETTLEMENT_MAX', 12);
}
if(!defined('NATAR_SETTLEMENT_PER_CLUSTER')) {
    // Cuántas se quieren alrededor de cada racimo de jugadores.
    define('NATAR_SETTLEMENT_PER_CLUSTER', 2);
}
if(!defined('NATAR_SETTLEMENT_SPAWN_INTERVAL')) {
    // Cada cuánto puede nacer una aldea (segundos). 18 h de base.
    //
    // Es también lo que tarda el mundo en reponer una que arrasaron: no hace falta una
    // demora aparte, porque el ritmo se mide desde el nacimiento de la última aldea viva.
    // Y como sólo nace UNA por intervalo, un servidor que estuvo días sin nadie conectado
    // no escupe cinco de golpe cuando alguien vuelve a entrar.
    define('NATAR_SETTLEMENT_SPAWN_INTERVAL', 64800);
}
if(!defined('NATAR_SETTLEMENT_MIN_DISTANCE')) {
    define('NATAR_SETTLEMENT_MIN_DISTANCE', 5);
}
if(!defined('NATAR_SETTLEMENT_MAX_DISTANCE')) {
    define('NATAR_SETTLEMENT_MAX_DISTANCE', 25);
}
if(!defined('NATAR_SETTLEMENT_START_FIELD_LEVEL')) {
    define('NATAR_SETTLEMENT_START_FIELD_LEVEL', 2);
}
if(!defined('NATAR_SETTLEMENT_GROWTH_INTERVAL')) {
    // Un nivel de campo cada 3 días.
    define('NATAR_SETTLEMENT_GROWTH_INTERVAL', 259200);
}
if(!defined('NATAR_SETTLEMENT_MAX_FIELD_LEVEL')) {
    // El mismo tope que trae una Aldea de la Maravilla en el T4 oficial.
    define('NATAR_SETTLEMENT_MAX_FIELD_LEVEL', 10);
}
if(!defined('NATAR_SETTLEMENT_CATCHUP_CAP')) {
    // Cuántos intervalos de entrenamiento puede acreditar UNA puesta al día.
    //
    // Es el cinturón contra el bug que ya nos mordió una vez: una aldea que nadie tocó en
    // meses no puede materializar meses de entrenamiento de golpe. El techo del cereal ya
    // acota el total, pero esto acota además la velocidad a la que se llega.
    define('NATAR_SETTLEMENT_CATCHUP_CAP', 24);
}
if(!defined('NATAR_SETTLEMENT_TRAINING_INTERVAL')) {
    // Duración de un intervalo de entrenamiento (segundos). 1 h.
    define('NATAR_SETTLEMENT_TRAINING_INTERVAL', 3600);
}

/**
 * Composición de la guarnición, en proporciones que suman 1. Mayormente defensiva, como
 * describe el oficial, con algo de ofensiva para que no sea un saco de arena.
 * El explorador (u44) va aparte: es lo que hace que espiarla cueste.
 */
function natarSettlementComposition() {
    return array(
        41 => 0.26,  // Piquero          defensa de infantería
        42 => 0.20,  // Guerrero Espinoso
        43 => 0.22,  // Defensor
        44 => 0.06,  // Pájaro de Presa  explorador
        45 => 0.12,  // Hachero Jinete   ofensiva
        46 => 0.10,  // Caballero Natariano
        47 => 0.04   // Elefante de Guerra
    );
}

/**
 * Semilla estable de una aldea. El wref alcanza: es único y no cambia nunca, así que dos
 * aldeas de la misma edad se ven distintas pero cada una es reproducible.
 */
function natarSettlementSeed($wref) {
    return crc32('natar-settlement-'.(int)$wref);
}

/**
 * Un entero pseudoaleatorio estable en [$min,$max] para una aldea y un propósito dados.
 * No usa rand(): tiene que dar lo mismo en cada recálculo.
 */
function natarSettlementJitter($wref, $purpose, $min, $max) {
    if($max <= $min) {
        return $min;
    }
    // abs(): en 32 bits crc32() puede volver negativo, y un módulo negativo tiraría la
    // variación por debajo del mínimo.
    $hash = abs(crc32($purpose.'#'.natarSettlementSeed($wref)));
    return $min + ($hash % (($max - $min) + 1));
}

/**
 * Nombre de una aldea viva.
 *
 * Sale de la semilla, así que es estable: recalcular el estado de la aldea no la
 * renombra. Y lleva la coordenada al final para que doce aldeas en el mapa se puedan
 * distinguir de un vistazo, que es justo lo que no pasaba cuando todas se llamaban igual.
 *
 * Ninguno arranca con "Aldea": el mapa y varios listados anteponen esa palabra.
 */
function natarSettlementName($wref, $x = null, $y = null) {
    global $database;
    $places = array(
        'Baluarte natar', 'Puesto natar', 'Atalaya natar', 'Vado natar', 'Reducto natar',
        'Campamento natar', 'Bastión natar', 'Cantera natar', 'Fortín natar', 'Talud natar',
        'Cerco natar', 'Villar natar'
    );
    $place = $places[natarSettlementJitter($wref, 'name', 0, count($places) - 1)];
    if($x === null || $y === null) {
        $coor = $database->getCoor((int)$wref);
        if(is_array($coor)) {
            $x = (int)$coor['x'];
            $y = (int)$coor['y'];
        }
    }
    if($x === null || $y === null) {
        return $place;
    }
    return $place.' ('.(int)$x.'|'.(int)$y.')';
}

/**
 * Edad de la aldea en segundos.
 */
function natarSettlementAge($village, $now = null) {
    $now = $now === null ? time() : (int)$now;
    $created = is_array($village) && isset($village['created']) ? (int)$village['created'] : 0;
    if($created <= 0) {
        return 0;
    }
    return max(0, $now - $created);
}

/**
 * Nivel de los campos según la edad. Es el único mando de dificultad: todo lo demás
 * —producción, almacenamiento, guarnición sostenible, botín— sale de acá.
 */
function natarSettlementFieldLevel($village, $now = null) {
    $age = natarSettlementAge($village, $now);
    $steps = (int)floor($age / max(1, NATAR_SETTLEMENT_GROWTH_INTERVAL));
    $level = NATAR_SETTLEMENT_START_FIELD_LEVEL + $steps;
    return max(0, min(NATAR_SETTLEMENT_MAX_FIELD_LEVEL, $level));
}

/**
 * Nivel de los edificios que acompañan a los campos. Crecen más lento: una aldea joven
 * tiene que ser saqueable, y el almacén es justamente lo que le pone techo al botín.
 */
function natarSettlementBuildingLevels($village, $now = null) {
    $fieldLevel = natarSettlementFieldLevel($village, $now);
    $half = (int)max(1, floor($fieldLevel / 2));
    return array(
        // Cuartel y establo: de acá sale el ritmo de entrenamiento.
        19 => $half,
        20 => (int)max(1, floor($fieldLevel / 3)),
        // Almacén y granero: el techo de lo que puede juntar, y por lo tanto del botín.
        10 => $half,
        11 => $half,
        // Escondite: modesto, para que una aldea joven siga dando algo.
        23 => (int)max(1, floor($fieldLevel / 4)),
        // Edificio principal, sólo cosmético para la población.
        15 => $half
    );
}

/**
 * Los campos y edificios que le corresponden a una aldea viva a esta edad, en el mismo
 * formato de `fdata` que usa el resto del motor.
 */
function natarSettlementPlan($fields, $village, $now = null) {
    $plan = is_array($fields) ? $fields : array();
    $fieldLevel = natarSettlementFieldLevel($village, $now);

    for($slot = NATAR_FIRST_RESOURCE_FIELD; $slot <= NATAR_LAST_RESOURCE_FIELD; $slot++) {
        if(!isset($plan['f'.$slot.'t'])) {
            continue;
        }
        $plan['f'.$slot] = $fieldLevel;
    }
    foreach(natarSettlementBuildingLevels($village, $now) as $type => $level) {
        $slot = natarFindBuildingSlot($plan, $type);
        if($slot > 0 && $level > 0) {
            $plan['f'.$slot.'t'] = $type;
            $plan['f'.$slot] = $level;
        }
    }
    return $plan;
}

/**
 * Cuántas tropas puede alimentar esta aldea: producción bruta de cereal menos población,
 * dividido por lo que come una tropa promedio de su composición.
 *
 * Éste es el tope real de la guarnición, y no hace falta ninguna constante: si la aldea
 * se pasara, la hambruna —que a una aldea viva sí la toca— se encargaría.
 */
function natarSettlementGarrisonTarget($fields, $village, $now = null) {
    $plan = natarSettlementPlan($fields, $village, $now);
    $netCrop = natarVillageGrossCrop($plan) - natarVillagePopulation($plan);
    if($netCrop <= 0) {
        return array_fill_keys(array_keys(natarSettlementComposition()), 0);
    }

    $composition = natarSettlementComposition();
    $averageUpkeep = 0;
    foreach($composition as $unit => $share) {
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $averageUpkeep += $share * (is_array($data) && isset($data['pop']) ? (int)$data['pop'] : 1);
    }
    if($averageUpkeep <= 0) {
        return array_fill_keys(array_keys($composition), 0);
    }

    $total = (int)floor($netCrop / $averageUpkeep);
    // Un poco de variación por aldea para que no salgan todas clonadas.
    $total = (int)round($total * (natarSettlementJitter($village['wref'], 'garrison', 85, 115) / 100));

    $target = array();
    foreach($composition as $unit => $share) {
        $target[$unit] = (int)floor($total * $share);
    }
    return $target;
}

/**
 * Tropas por hora que la aldea puede entrenar, a partir del cuartel y el establo que
 * tiene construidos y de los tiempos de `unitdata`. Es el mismo insumo que usa una aldea
 * de jugador, así que "entrena como una aldea de verdad" es literal.
 */
function natarSettlementTrainingRate($fields, $village, $now = null) {
    $plan = natarSettlementPlan($fields, $village, $now);
    $barracks = productionBuildingLevel($plan, 19);
    $stable = productionBuildingLevel($plan, 20);

    $speed = defined('SPEED') ? max(1, (float)SPEED) : 1;
    $rate = array();
    foreach(natarSettlementComposition() as $unit => $share) {
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $baseTime = is_array($data) && isset($data['time']) ? max(1, (int)$data['time']) : 1;
        // La caballería sale del establo; el resto, del cuartel.
        $level = in_array($unit, array(45, 46, 47), true) ? $stable : $barracks;
        if($level <= 0) {
            $rate[$unit] = 0;
            continue;
        }
        $table = $unit >= 45 && $unit <= 47 ? $GLOBALS['bid20'] : $GLOBALS['bid19'];
        $percent = isset($table[$level]['attri']) ? (float)$table[$level]['attri'] : 100;
        $effectiveTime = ($baseTime * $percent / 100) / $speed;
        $rate[$unit] = $effectiveTime > 0 ? (3600 / $effectiveTime) * $share : 0;
    }
    return $rate;
}

/**
 * Pone al día una aldea NPC viva: sube sus campos a lo que le toca por edad y avanza su
 * guarnición hacia el objetivo, sin pasarlo nunca.
 *
 * El avance del reloj es un compare-and-swap: si otro request ya acreditó este intervalo,
 * esta llamada no hace nada. Y la puesta al día está acotada a NATAR_SETTLEMENT_CATCHUP_CAP
 * intervalos, así que una aldea que nadie tocó en meses no materializa meses de tropas.
 */
function natarSettlementBringUpToDate($wref, $now = null, $accrue = null) {
    global $database;
    $wref = (int)$wref;
    $now = $now === null ? time() : (int)$now;
    if($wref <= 0) {
        return false;
    }
    $village = $database->getVillage($wref);
    if(!is_array($village) || !isLivingNpcVillage($village)) {
        return false;
    }
    $fields = $database->getResourceLevel($wref);
    if(!is_array($fields)) {
        return false;
    }

    $clock = isset($village['npcupdate']) ? (int)$village['npcupdate'] : 0;
    if($clock <= 0) {
        $clock = isset($village['created']) ? (int)$village['created'] : $now;
    }
    $elapsed = max(0, $now - $clock);
    $intervals = (int)floor($elapsed / max(1, NATAR_SETTLEMENT_TRAINING_INTERVAL));
    if($intervals <= 0) {
        // Igual hay que mantener los campos al día: crecen con la edad, no con el reloj.
        natarSettlementApplyGrowth($wref, $fields, $village, $now, $accrue);
        return true;
    }
    $consumed = min($intervals, NATAR_SETTLEMENT_CATCHUP_CAP);
    // Adónde queda el reloj:
    //  - si NO se recortó, avanza sólo lo que se acreditó y el resto del intervalo queda
    //    para la próxima. Saltar a $now perdería hasta una hora en cada pasada, y eso se
    //    acumula: la aldea entrenaría sistemáticamente más lento de lo que le toca.
    //  - si SÍ se recortó, salta a $now y el atraso se descarta a propósito. Guardarlo
    //    haría que la pasada siguiente —que llega 50 segundos después— acreditara otras
    //    24 horas, y el tope dejaría de ser un tope.
    $capped = $intervals > NATAR_SETTLEMENT_CATCHUP_CAP;
    $newClock = $capped ? $now : ($clock + $consumed * NATAR_SETTLEMENT_TRAINING_INTERVAL);
    // Reclamar el tramo ANTES de acreditar nada: si dos requests entran juntos, sólo uno
    // gana el compare-and-swap y el otro se va sin tocar tropas.
    if(!$database->advanceNpcVillageClock($wref, (int)$village['npcupdate'], $newClock)) {
        return false;
    }

    natarSettlementApplyGrowth($wref, $fields, $village, $now, $accrue);

    $fields = $database->getResourceLevel($wref);
    $target = natarSettlementGarrisonTarget($fields, $village, $now);
    $rate = natarSettlementTrainingRate($fields, $village, $now);
    $current = $database->getUnit($wref);

    foreach($target as $unit => $ceiling) {
        $have = is_array($current) && isset($current['u'.$unit]) ? (int)$current['u'.$unit] : 0;
        if($have >= $ceiling) {
            continue;
        }
        $trained = (int)floor((isset($rate[$unit]) ? $rate[$unit] : 0) * $consumed);
        if($trained <= 0) {
            continue;
        }
        $database->modifyUnit($wref, $unit, min($trained, $ceiling - $have), 1);
    }
    return true;
}

/**
 * Escribe los campos, la población y los topes de almacenamiento que le tocan por edad.
 * Sólo toca la base si algo cambió, para no escribir en cada carga de página.
 */
function natarSettlementApplyGrowth($wref, $fields, $village, $now = null, $accrue = null) {
    global $database;
    $plan = natarSettlementPlan($fields, $village, $now);

    $updates = array();
    for($slot = NATAR_FIRST_RESOURCE_FIELD; $slot <= NATAR_LAST_BUILDING_SLOT; $slot++) {
        if(!isset($plan['f'.$slot])) {
            continue;
        }
        if((int)$plan['f'.$slot] === (int)$fields['f'.$slot]
            && (int)$plan['f'.$slot.'t'] === (int)$fields['f'.$slot.'t']) {
            continue;
        }
        $updates[] = '`f'.$slot.'` = '.(int)$plan['f'.$slot];
        $updates[] = '`f'.$slot.'t` = '.(int)$plan['f'.$slot.'t'];
    }
    if(!$updates) {
        return false;
    }
    // Los campos cambian la producción, así que hay que cerrar el tramo viejo antes de
    // que la tasa nueva se aplique retroactivamente a las horas ya pasadas. Es la misma
    // regla que rige para los jugadores (ver AGENTS.md). El cierre lo hace
    // Automation::accrueProductionBeforeChange(), que es `protected`, así que llega hasta
    // acá como un closure que Automation arma dentro de su propio alcance.
    if(is_callable($accrue)) {
        $accrue($wref, $now);
    }
    $database->query('UPDATE '.TB_PREFIX.'fdata SET '.implode(', ', $updates).' WHERE vref = '.(int)$wref);
    $database->query('UPDATE '.TB_PREFIX.'vdata SET '
        .'pop = '.(int)natarVillagePopulation($plan).', '
        .'maxstore = '.(int)natarVillageStorage($plan, 'maxstore').', '
        .'maxcrop = '.(int)natarVillageStorage($plan, 'maxcrop').' '
        .'WHERE wref = '.(int)$wref);
    return true;
}

// --- Aparición -----------------------------------------------------------------------

/**
 * Las aldeas vivas del mundo.
 */
function natarSettlements() {
    global $database;
    $rows = $database->query_return(
        // SELECT *: nombrar `npckind` en la lista reventaría la consulta en un mundo sin
        // migrar, aunque el WHERE ya sea imposible de satisfacer ahí.
        'SELECT * FROM '.TB_PREFIX.'vdata '
        .'WHERE '.villageKindSql(NPC_KIND_LIVING).' ORDER BY `created` ASC'
    );
    return is_array($rows) ? $rows : array();
}

/**
 * Distancia entre dos casillas, respetando que el mapa da la vuelta en los bordes: la
 * misma cuenta que usa la anexión de oasis.
 */
function natarSettlementDistance($ax, $ay, $bx, $by) {
    $span = (int)WORLD_MAX * 2 + 1;
    $dx = abs($ax - $bx);
    $dy = abs($ay - $by);
    $dx = min($dx, $span - $dx);
    $dy = min($dy, $span - $dy);
    return sqrt($dx * $dx + $dy * $dy);
}

/**
 * Elige la aldea de jugador alrededor de la cual conviene sembrar la próxima: la que menos
 * aldeas vivas tiene cerca. Así se reparten solas entre los racimos de jugadores sin
 * necesidad de calcular racimos.
 *
 * Devuelve null si todas las aldeas de jugador ya tienen su cupo.
 */
function natarSettlementPickAnchor() {
    global $database;
    $players = $database->query_return(
        'SELECT v.`wref`, w.`x`, w.`y` FROM '.TB_PREFIX.'vdata v '
        .'INNER JOIN '.TB_PREFIX.'wdata w ON w.`id` = v.`wref` '
        .'WHERE '.playerAccountSql('v`.`owner')
    );
    if(!is_array($players) || !$players) {
        return null;
    }
    $settlements = array();
    foreach(natarSettlements() as $settlement) {
        $coor = $database->getCoor((int)$settlement['wref']);
        if(is_array($coor)) {
            $settlements[] = $coor;
        }
    }

    $best = null;
    $bestCount = null;
    shuffle($players);
    foreach($players as $player) {
        $near = 0;
        foreach($settlements as $coor) {
            if(natarSettlementDistance((int)$player['x'], (int)$player['y'], (int)$coor['x'], (int)$coor['y']) <= NATAR_SETTLEMENT_MAX_DISTANCE) {
                $near++;
            }
        }
        if($near >= NATAR_SETTLEMENT_PER_CLUSTER) {
            continue;
        }
        if($bestCount === null || $near < $bestCount) {
            $best = $player;
            $bestCount = $near;
        }
    }
    return $best;
}

/**
 * Una casilla de aldea libre dentro de la banda de distancia alrededor de un punto.
 * Devuelve 0 si la banda está llena, y en ese caso no se marca nada como ocupado.
 */
function natarSettlementFindField($x, $y) {
    global $database;
    $max = (int)NATAR_SETTLEMENT_MAX_DISTANCE;
    $candidates = $database->query_return(
        'SELECT `id`, `x`, `y` FROM '.TB_PREFIX.'wdata '
        .'WHERE `occupied` = 0 AND `oasistype` = 0 AND `fieldtype` > 0 '
        .'AND `x` BETWEEN '.((int)$x - $max).' AND '.((int)$x + $max).' '
        .'AND `y` BETWEEN '.((int)$y - $max).' AND '.((int)$y + $max)
    );
    if(!is_array($candidates) || !$candidates) {
        return 0;
    }
    $inBand = array();
    foreach($candidates as $candidate) {
        $distance = natarSettlementDistance($x, $y, (int)$candidate['x'], (int)$candidate['y']);
        if($distance >= NATAR_SETTLEMENT_MIN_DISTANCE && $distance <= $max) {
            $inBand[] = (int)$candidate['id'];
        }
    }
    if(!$inBand) {
        return 0;
    }
    shuffle($inBand);
    foreach($inBand as $wref) {
        // Reclamo atómico: si otro proceso la tomó entre el SELECT y esto, se prueba la
        // siguiente en vez de pisarla.
        if($database->claimFieldForSettlement($wref)) {
            return $wref;
        }
    }
    return 0;
}

/**
 * ¿Toca que nazca una aldea? El reloj se deriva del nacimiento de la última aldea viva,
 * así que no hace falta guardar un contador aparte, y sólo puede nacer una por intervalo.
 */
function natarSettlementSpawnDue($now = null) {
    $now = $now === null ? time() : (int)$now;
    $settlements = natarSettlements();
    if(count($settlements) >= NATAR_SETTLEMENT_MAX) {
        return false;
    }
    $latest = 0;
    foreach($settlements as $settlement) {
        $latest = max($latest, (int)$settlement['created']);
    }
    if($latest <= 0) {
        return true;
    }
    return ($now - $latest) >= NATAR_SETTLEMENT_SPAWN_INTERVAL;
}

/**
 * Crea una aldea natar viva. Devuelve su wref, o 0 si no correspondía o no había lugar.
 *
 * `$force` saltea el reloj y el tope, no la colocación: sigue necesitando una casilla
 * libre dentro de la banda. Lo usan los checkers, que no pueden depender de en qué
 * momento del ciclo esté el mundo, y sirve para sembrar una a mano desde una herramienta.
 */
function natarSettlementSpawn($now = null, $force = false) {
    global $database;
    $now = $now === null ? time() : (int)$now;
    if(!$force && !natarSettlementSpawnDue($now)) {
        return 0;
    }
    $anchor = natarSettlementPickAnchor();
    if(!is_array($anchor)) {
        return 0;
    }
    $wref = natarSettlementFindField((int)$anchor['x'], (int)$anchor['y']);
    if($wref <= 0) {
        return 0;
    }

    $owner = natarsAccountId();
    $database->addVillage($wref, $owner, 'Natars', '0');
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);
    $coor = $database->getCoor($wref);
    $name = natarSettlementName($wref, isset($coor['x']) ? (int)$coor['x'] : null, isset($coor['y']) ? (int)$coor['y'] : null);
    $database->query('UPDATE '.TB_PREFIX.'vdata SET '
        ."`name` = '".mysql_real_escape_string($name)."', `capital` = 0, `natar` = 0, "
        .'`created` = '.$now.', `lastupdate` = '.$now.', `npcupdate` = '.$now.' '
        .'WHERE `wref` = '.(int)$wref);
    $database->setVillageNpcKind($wref, NPC_KIND_LIVING);

    // Nace con sus campos, su guarnición inicial y su granero lleno.
    $village = $database->getVillage($wref);
    $fields = $database->getResourceLevel($wref);
    natarSettlementApplyGrowth($wref, $fields, $village, $now);
    $fields = $database->getResourceLevel($wref);
    foreach(natarSettlementGarrisonTarget($fields, $village, $now) as $unit => $amount) {
        if($amount > 0) {
            $database->modifyUnit($wref, $unit, $amount, 1);
        }
    }
    $database->query('UPDATE '.TB_PREFIX.'vdata SET `wood` = `maxstore`, `clay` = `maxstore`, '
        .'`iron` = `maxstore`, `crop` = `maxcrop` WHERE `wref` = '.(int)$wref);
    return $wref;
}
