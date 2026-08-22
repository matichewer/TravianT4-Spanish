<?php
/**
 * Economía de las aldeas natar: la capital y las 13 "Aldeas de la Maravilla".
 *
 * Por qué existe. El instalador las creaba con miles de tropas natar y los 18 campos en
 * nivel 0, así que su balance de cereal nacía en unos -45.000/h. Mientras nadie las
 * tocaba no se notaba, porque `lastupdate` sólo avanza cuando alguien las ataca; pero el
 * primer ataque —incluso un espionaje que fracasa— acredita de golpe todo el tiempo
 * transcurrido desde la instalación (Automation::updateRes, que corre para cualquier
 * ataque resuelto), el granero queda en rojo y starvation() se come la guarnición a
 * razón de un contingente por minuto. En unos diez minutos la Maravilla quedaba
 * indefensa sola, sin que ningún jugador la tocara.
 *
 * Acá se arma la economía que a esas aldeas les corresponde:
 *   - campos de cereal, molino y panadería en el nivel mínimo que sostiene su
 *     guarnición, para que no se mueran de hambre;
 *   - el resto de los campos más almacén y granero, para que produzcan y guarden
 *     recursos de verdad y se puedan saquear una vez limpiadas, como en Travian;
 *   - población y topes de almacenamiento coherentes con esos niveles.
 *
 * La capital natar es un caso aparte y no tiene arreglo por campos: arranca con más de
 * un millón de tropas, o sea unos 5.000.000 de cereal/h, y el máximo que puede dar una
 * aldea son ~165.000/h. Para ella —y para cualquier aldea NPC— la hambruna directamente
 * no corre (ver Automation::starvation), que es como se comporta el Travian original:
 * la guarnición natar es estática, no se entrena, no se repone y no se muere de hambre.
 */

require_once __DIR__.'/Accounts.php';
require_once __DIR__.'/Production.php';
require_once __DIR__.'/Data/buidata.php';
require_once __DIR__.'/Data/unitdata.php';

// Campos de recursos (f1..f18), slots de edificios (f19..f38), plaza de reuniones y muralla.
define('NATAR_FIRST_RESOURCE_FIELD', 1);
define('NATAR_LAST_RESOURCE_FIELD', 18);
define('NATAR_FIRST_BUILDING_SLOT', 19);
define('NATAR_LAST_BUILDING_SLOT', 38);
// La muralla vive en el campo 40, fuera de la franja de edificios: natarFindBuildingSlot()
// no la encuentra ni la puede colocar, así que quien la planifique la ubica acá a mano.
define('NATAR_WALL_SLOT', 40);

// Los 18 campos, cereal incluido, en nivel 10: es lo que trae una Aldea de la Maravilla
// en el T4 oficial cuando aparece como aldea natar.
define('NATAR_RESOURCE_FIELD_LEVEL', 10);
// Molino y panadería al tope, que es lo que hace que el cereal alcance para la
// guarnición sin salirse del nivel 10 de los campos.
define('NATAR_GRAINMILL_LEVEL', 5);
define('NATAR_BAKERY_LEVEL', 5);
// Almacén y granero: en el T4 oficial la aldea de Maravilla llega con ~91.800 de
// capacidad; el nivel 20 de este servidor da 80.000, que es lo más cerca que llega su
// tabla. Sin esto la aldea tiene tope 800 y el escondite nivel 10 (10.000 por recurso
// acá) la vuelve imposible de saquear para siempre.
define('NATAR_WAREHOUSE_LEVEL', 20);
define('NATAR_GRANARY_LEVEL', 20);
// Edificio principal nivel 15, también como en el T4 oficial.
define('NATAR_MAIN_BUILDING_LEVEL', 15);

function natarVillageIsNpcOwned($wref) {
    global $database;
    return isSystemAccount($database->getVillageField((int)$wref, 'owner'));
}

/**
 * Multiplicadores de tropas del instalador. La capital y las Maravillas nunca usaron el
 * mismo corte (SPEED>3 contra SPEED>5); se respetan los dos para no cambiarle la
 * guarnición a un mundo ya instalado. En SPEED 1..3 dan lo mismo.
 */
function natarGarrisonSpeedFactor() {
    return SPEED > 5 ? 5 : SPEED;
}

function natarCapitalSpeedFactor() {
    return SPEED > 3 ? 5 : SPEED;
}

/**
 * Guarnición de referencia de una Aldea de la Maravilla, con los mismos números que
 * usa el instalador. `$randomize` a false devuelve el promedio de cada rango, que es
 * lo que conviene para reponer y para los checkers.
 */
function natarWonderGarrison($randomize = true) {
    $ranges = array(
        41 => array(1000, 2000),
        42 => array(1500, 2000),
        43 => array(2300, 2800),
        44 => array(235, 575),
        45 => array(1200, 1900),
        46 => array(1500, 2000),
        47 => array(500, 900),
        48 => array(100, 300),
        49 => array(1, 5),
        50 => array(1, 5)
    );
    $factor = natarGarrisonSpeedFactor();
    $garrison = array();
    foreach($ranges as $unit => $range) {
        $amount = $randomize
            ? rand($range[0], $range[1])
            : (int)round(($range[0] + $range[1]) / 2);
        $garrison[$unit] = $amount * $factor;
    }
    return $garrison;
}

/**
 * Guarnición de la capital natar, la aldea desde la que salen las oleadas contra las
 * Maravillas. Mismos números que el instalador.
 */
function natarCapitalGarrison() {
    $base = array(
        41 => 94700, 42 => 295231, 43 => 180747, 44 => 1048, 45 => 364401,
        46 => 217602, 47 => 2034, 48 => 1040, 49 => 1, 50 => 9
    );
    $factor = natarCapitalSpeedFactor();
    $garrison = array();
    foreach($base as $unit => $amount) {
        $garrison[$unit] = $amount * $factor;
    }
    return $garrison;
}

/**
 * Consumo de cereal por hora de un conjunto de tropas, leído de la tabla de unidades.
 * Acepta tanto una fila de `units` (claves u1..u50) como un array id => cantidad.
 */
function natarGarrisonUpkeep($units) {
    if(!is_array($units)) {
        return 0;
    }
    $upkeep = 0;
    for($unit = 1; $unit <= 50; $unit++) {
        if(isset($units['u'.$unit])) {
            $amount = (int)$units['u'.$unit];
        } elseif(isset($units[$unit])) {
            $amount = (int)$units[$unit];
        } else {
            continue;
        }
        if($amount <= 0) {
            continue;
        }
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $upkeep += $amount * (is_array($data) && isset($data['pop']) ? (int)$data['pop'] : 0);
    }
    return $upkeep;
}

/**
 * Población acumulada de una configuración de campos y edificios, sumando el `pop` de
 * cada nivel construido igual que lo hace Building.php al levantar uno por uno.
 */
function natarVillagePopulation($fields) {
    if(!is_array($fields)) {
        return 0;
    }
    $pop = 0;
    // villagePopulationSlots() y no un rango: incluye el campo 99, donde vive el
    // Palacio de la Maravilla. Ver el comentario de esa funcion en Production.php.
    foreach(villagePopulationSlots() as $slot) {
        if(!isset($fields['f'.$slot.'t'])) {
            continue;
        }
        $type = (int)$fields['f'.$slot.'t'];
        $level = (int)$fields['f'.$slot];
        if($type <= 0 || $level <= 0) {
            continue;
        }
        $table = isset($GLOBALS['bid'.$type]) ? $GLOBALS['bid'.$type] : null;
        if(!is_array($table)) {
            continue;
        }
        $maxLevel = max(array_keys($table));
        for($step = 1; $step <= min($level, $maxLevel); $step++) {
            if(isset($table[$step]['pop'])) {
                $pop += (int)$table[$step]['pop'];
            }
        }
    }
    return $pop;
}

/**
 * Capacidad de almacenamiento que dan los edificios de una aldea, con la misma cuenta
 * que Automation::applyStorageCapacityDelta (almacén 10 y gran almacén 38 para
 * `maxstore`; granero 11 y gran granero 39 para `maxcrop`).
 */
function natarVillageStorage($fields, $column) {
    $buildings = $column === 'maxcrop' ? array(11, 39) : array(10, 38);
    $multiplier = defined('STORAGE_MULTIPLIER') ? (float)STORAGE_MULTIPLIER : 1;
    $base = defined('STORAGE_BASE') ? (float)STORAGE_BASE : 800 * $multiplier;
    $capacity = 0;
    for($slot = NATAR_FIRST_BUILDING_SLOT; $slot <= NATAR_LAST_BUILDING_SLOT; $slot++) {
        if(!isset($fields['f'.$slot.'t'])) {
            continue;
        }
        $type = (int)$fields['f'.$slot.'t'];
        $level = (int)$fields['f'.$slot];
        if(!in_array($type, $buildings, true) || $level <= 0) {
            continue;
        }
        $table = isset($GLOBALS['bid'.$type]) ? $GLOBALS['bid'.$type] : null;
        if(is_array($table) && isset($table[$level]['attri'])) {
            $capacity += (float)$table[$level]['attri'] * $multiplier;
        }
    }
    return max($base, $capacity);
}

/**
 * Producción bruta de cereal por hora de una configuración de campos. Pasa por la
 * fórmula única de Production.php: las aldeas natar no tienen oasis anexados, bono de
 * oro ni héroe, así que los tres modificadores van en cero.
 */
function natarVillageGrossCrop($fields) {
    $gross = villageGrossProduction($fields, array(0, 0, 0, 0), array(0, 0, 0, 0), SPEED);
    return $gross['production']['crop'];
}

/**
 * Ubica un edificio dentro de la aldea. Devuelve el número de campo si ya existe, o el
 * primer slot libre si no, o 0 si no queda lugar.
 */
function natarFindBuildingSlot($fields, $type) {
    $type = (int)$type;
    $free = 0;
    for($slot = NATAR_FIRST_BUILDING_SLOT; $slot <= NATAR_LAST_BUILDING_SLOT; $slot++) {
        $slotType = isset($fields['f'.$slot.'t']) ? (int)$fields['f'.$slot.'t'] : 0;
        if($slotType === $type) {
            return $slot;
        }
        if($slotType === 0 && $free === 0) {
            $free = $slot;
        }
    }
    return $free;
}

/**
 * Arma la configuración de una aldea natar: los 18 campos en nivel 10, molino,
 * panadería, almacén, granero y edificio principal, tal como llegan las Aldeas de la
 * Maravilla en el T4 oficial. Respeta lo que ya esté construido más alto.
 *
 * La guarnición NO entra en la cuenta de los niveles: una aldea NPC no paga manutención
 * (ver Automation::bountycalculateProduction), así que no hay nada que compensar
 * subiendo campos por encima del nivel oficial. `upkeep`
 * viaja igual en el resultado porque es el número que hace falta mirar cuando la aldea
 * cambia de dueño y pasa a ser una aldea de jugador de verdad.
 *
 * No escribe nada: devuelve el plan para que lo aplique natarProvisionVillage() y para
 * que los checkers puedan verificarlo sin tocar la base.
 */
function natarVillagePlan($fields, $upkeep) {
    $plan = is_array($fields) ? $fields : array();
    $cropFields = array();
    // Lo que la aldea tiene HOY, antes de que el plan lo pise. Sin esto el informe de
    // tools/fix_natar_villages.php mostraba la constante del plan pasara lo que pasara,
    // o sea que decía "nivel 10" aunque los campos estuvieran en otro lado.
    $measured = array();
    for($slot = NATAR_FIRST_RESOURCE_FIELD; $slot <= NATAR_LAST_RESOURCE_FIELD; $slot++) {
        if(!isset($plan['f'.$slot.'t'])) {
            continue;
        }
        if((int)$plan['f'.$slot.'t'] === 4) {
            $cropFields[] = $slot;
        }
        $measured[] = (int)$plan['f'.$slot];
        $plan['f'.$slot] = max(NATAR_RESOURCE_FIELD_LEVEL, (int)$plan['f'.$slot]);
    }

    $buildings = array(
        8 => NATAR_GRAINMILL_LEVEL,
        9 => NATAR_BAKERY_LEVEL,
        10 => NATAR_WAREHOUSE_LEVEL,
        11 => NATAR_GRANARY_LEVEL,
        15 => NATAR_MAIN_BUILDING_LEVEL
    );
    foreach($buildings as $type => $level) {
        $slot = natarFindBuildingSlot($plan, $type);
        if($slot > 0) {
            $plan['f'.$slot.'t'] = $type;
            $plan['f'.$slot] = max($level, isset($plan['f'.$slot]) ? (int)$plan['f'.$slot] : 0);
        }
    }

    $pop = natarVillagePopulation($plan);
    $grossCrop = natarVillageGrossCrop($plan);
    return array(
        'fields' => $plan,
        'crop_fields' => $cropFields,
        'crop_level' => NATAR_RESOURCE_FIELD_LEVEL,
        // Medido, no planeado. `above_max` importa porque el plan sólo sube niveles con
        // max(): un campo por encima del nivel oficial es invisible para la reparación y
        // hay que decirlo en vez de dejarlo pasar.
        'measured_min_level' => $measured ? min($measured) : 0,
        'measured_max_level' => $measured ? max($measured) : 0,
        'above_max' => $measured ? count(array_filter($measured, function ($level) {
            return $level > NATAR_RESOURCE_FIELD_LEVEL;
        })) : 0,
        'pop' => $pop,
        'gross_crop' => $grossCrop,
        'upkeep' => $upkeep,
        // Lo que produce de verdad mientras es natar: sin manutención de tropas.
        'net_crop' => $grossCrop - $pop,
        // Lo que produciría si fuera de un jugador, con esta misma guarnición.
        'net_crop_as_player' => $grossCrop - $pop - $upkeep,
        'maxstore' => natarVillageStorage($plan, 'maxstore'),
        'maxcrop' => natarVillageStorage($plan, 'maxcrop')
    );
}

/**
 * Deja una aldea natar en condiciones: campos, edificios de bonus, almacenamiento,
 * población y relojes. Devuelve el plan aplicado, con `net_crop` para que quien la
 * llame pueda avisar si la guarnición sigue siendo demasiado grande para alimentarse
 * (el caso de la capital natar).
 *
 * `lastupdate` se pone en la hora actual a propósito: si quedara la vieja, la primera
 * acreditación de producción aplicaría de golpe todo el tiempo que la aldea pasó sin
 * simularse.
 */
function natarProvisionVillage($wref) {
    global $database;
    $wref = (int)$wref;
    if($wref <= 0) {
        return null;
    }
    $fields = $database->getResourceLevel($wref);
    if(!is_array($fields)) {
        return null;
    }
    $upkeep = natarGarrisonUpkeep($database->getUnit($wref));
    $plan = natarVillagePlan($fields, $upkeep);

    $updates = array();
    for($slot = NATAR_FIRST_RESOURCE_FIELD; $slot <= NATAR_LAST_BUILDING_SLOT; $slot++) {
        if(!isset($plan['fields']['f'.$slot])) {
            continue;
        }
        $updates[] = '`f'.$slot.'` = '.(int)$plan['fields']['f'.$slot];
        $updates[] = '`f'.$slot.'t` = '.(int)$plan['fields']['f'.$slot.'t'];
    }
    if($updates) {
        $database->query('UPDATE '.TB_PREFIX.'fdata SET '.implode(', ', $updates).' WHERE vref = '.$wref);
    }

    $time = time();
    $database->query('UPDATE '.TB_PREFIX.'vdata SET '
        .'pop = '.(int)$plan['pop'].', '
        .'maxstore = '.(int)$plan['maxstore'].', '
        .'maxcrop = '.(int)$plan['maxcrop'].', '
        .'crop = '.(int)$plan['maxcrop'].', '
        .'wood = LEAST(wood, '.(int)$plan['maxstore'].'), '
        .'clay = LEAST(clay, '.(int)$plan['maxstore'].'), '
        .'iron = LEAST(iron, '.(int)$plan['maxstore'].'), '
        .'starv = 0, starvupdate = 0, lastupdate = '.$time.' '
        .'WHERE wref = '.$wref);

    // Todo lo que pasa por acá es escenario: la capital y las 13 Maravillas. Las aldeas
    // natar vivas tienen su propio camino (GameEngine/NatarSettlement.php).
    $database->setVillageNpcKind($wref, NPC_KIND_STATIC);

    return $plan;
}

/**
 * Repone la guarnición de una aldea natar a sus valores de referencia. Sólo sube: no le
 * saca tropas a una aldea que todavía tiene más de las que le tocan, para no deshacer
 * lo que un jugador ya mató si el script se corre dos veces.
 */
function natarRestockGarrison($wref, $garrison) {
    global $database;
    $wref = (int)$wref;
    $current = $database->getUnit($wref);
    $updates = array();
    $restored = 0;
    foreach($garrison as $unit => $amount) {
        $have = is_array($current) && isset($current['u'.$unit]) ? (int)$current['u'.$unit] : 0;
        if($have >= $amount) {
            continue;
        }
        $updates[] = '`u'.(int)$unit.'` = '.(int)$amount;
        $restored += $amount - $have;
    }
    if($updates) {
        $database->query('UPDATE '.TB_PREFIX.'units SET '.implode(', ', $updates).' WHERE vref = '.$wref);
    }
    return $restored;
}

/**
 * Las aldeas natar del mundo, en sus tres clases: la capital (la que lanza las oleadas
 * contra las Maravillas), las Aldeas de la Maravilla, y las independientes.
 *
 * La clase sale de `npckind`, no de la marca de capital. Cuando esto separaba sólo por
 * `capital`, una aldea natar VIVA caía en el grupo de las Maravillas, y la herramienta de
 * reparación la habría rellenado con 31.000 tropas y convertido en guarnición estática:
 * justo lo contrario de lo que es.
 */
function natarVillages() {
    global $database;
    $rows = $database->query_return(
        // SELECT *: villageKindFromRow() necesita `npckind`, y nombrarla en la lista
        // reventaría la consulta en un mundo donde la columna todavía no existe.
        'SELECT * FROM '.TB_PREFIX.'vdata '
        .'WHERE `owner` = '.natarsAccountId().' ORDER BY `capital` DESC, `wref` ASC'
    );
    $villages = array('capital' => array(), 'wonder' => array(), 'living' => array());
    foreach(is_array($rows) ? $rows : array() as $row) {
        if(villageKindFromRow($row) === NPC_KIND_LIVING) {
            $villages['living'][] = $row;
        } elseif((int)$row['capital'] === 1) {
            $villages['capital'][] = $row;
        } else {
            $villages['wonder'][] = $row;
        }
    }
    return $villages;
}

/**
 * El nombre de una Aldea de la Maravilla, con su coordenada.
 *
 * Por que lleva la coordenada. En el T4 oficial las aldeas natar se distinguen justamente
 * por ahi: su nombre normal es de estilo coordenada (`Natars -71|24`), y solo las que
 * guardan un artefacto usan otro. Aca las 13 compartian el literal 'Aldea de la Maravilla',
 * asi que en el ranking, en los informes y en la lista de aldeas salian trece filas
 * identicas y no habia forma de saber cual era cual. Las aldeas natar independientes ya
 * seguian este criterio (`natarSettlementName()`); esto lo extiende a las Maravillas.
 */
function natarWonderVillageName($wref, $x = null, $y = null) {
    global $database;
    if($x === null || $y === null) {
        $coor = is_object($database) && method_exists($database, 'getCoor')
            ? $database->getCoor((int)$wref) : null;
        if(is_array($coor) && isset($coor['x'], $coor['y'])) {
            $x = (int)$coor['x'];
            $y = (int)$coor['y'];
        }
    }
    if($x === null || $y === null) {
        return 'Aldea de la Maravilla';
    }
    return 'Aldea de la Maravilla ('.(int)$x.'|'.(int)$y.')';
}

/**
 * Los edificios fijos de una Aldea de la Maravilla.
 *
 * Vive aca y no copiado en el instalador y en los dos modulos del panel de administracion
 * porque ya se desincronizo una vez: al sacarle la residencia (f28) para igualar al T4
 * oficial se corrigio el instalador y las dos copias del panel quedaron poniendola igual,
 * asi que una Maravilla creada desde el panel nacia otra vez inconquistable sin catapultas.
 *
 * Sin residencia ni palacio, como en el original. Tesoreria 10, escondite 10, Maravilla en
 * la ranura 99, edificio principal y punto de reunion en 1.
 */
function natarWonderBuildings($wref) {
    global $database;
    $wref = (int)$wref;
    return mysqli_query($database->connection,
        "UPDATE ".TB_PREFIX."fdata SET "
        ."`f22t` = 27, `f22` = 10, "      // tesoreria
        ."`f28t` = 0,  `f28` = 0, "       // sin residencia: ver el comentario de arriba
        ."`f19t` = 23, `f19` = 10, "      // escondite
        ."`f99t` = 40, "                  // la Maravilla
        ."`f26t` = 0,  `f26` = 0, "       // sin muralla
        ."`f21t` = 15, `f21` = 1, "       // edificio principal
        ."`f39t` = 16, `f39` = 1 "        // punto de reunion
        ."WHERE `vref` = ".$wref);
}
