<?php
/**
 * El plan de una liberación de artefactos: cuántos, dónde y con qué defensa.
 *
 * Por qué existe. Sembrar artefactos era una constante escrita a mano en el mod del panel:
 * 6 pequeños, 4 grandes y 1 único por tipo, con una guarnición copiada de las Aldeas de la
 * Maravilla y multiplicada por 1/2/4. En un mundo de cuatro jugadores sin un solo soldado
 * eso son 87 aldeas de 31.000 tropas cada una — decoración inalcanzable. En un mundo grande
 * el mismo número sería un regalo. El oficial no tiene ese problema porque **no usa una
 * constante**:
 *
 *   "Defence values are based on the top 100 offensive armies of the game world."
 *   — support.travian.com, Artefacts
 *
 * O sea que la guarnición se deriva del estado del mundo. Este archivo hace eso, y deja
 * todo lo demás configurable desde el panel para poder apartarse a propósito.
 *
 * Lo que se sabe del oficial y está acá:
 *
 *   - **La defensa sale de los mejores ejércitos ofensivos del mundo**, no de un número.
 *   - **Los tres tamaños guardan una proporción fija**: el grande vale 1,5384 veces el
 *     pequeño y el único 1,5 veces el grande. No es 1/2/4.
 *   - **Se reparten por anillos desde el centro**: los únicos en el medio, los grandes en
 *     la corona intermedia y los pequeños en la periferia. En el mapa oficial (±200) las
 *     bandas son 0-25, 20-60 y 40-110; acá se guardan como fracción de WORLD_MAX para que
 *     el reparto sea el mismo en un mapa de cualquier tamaño.
 *   - **Tesoro nivel 20 en todas las aldeas de artefacto** (los planos de construcción,
 *     que no están implementados, son los del Tesoro 10). El nivel 10/20 que distingue a
 *     los tamaños es el que hace falta en la aldea del ATACANTE, no acá.
 *   - **Muralla 0**, y los natars sólo pueden subirla a 1: el ariete no tiene nada que
 *     hacer contra estas aldeas.
 *   - **Llevan exploradores**, a diferencia de las aldeas natar independientes.
 *
 * Todo lo de acá es función pura salvo `artefactReleaseReferenceOffence()`, que es la única
 * que mira la base: así el plan entero se puede previsualizar y probar sin escribir nada.
 */

require_once __DIR__.'/Accounts.php';
require_once __DIR__.'/NatarVillage.php';
// Por greyZoneDistanceToCentre(), que es la distancia al centro que usa el resto del
// motor: los anillos de artefactos tienen que medirse con la misma regla que la zona gris.
require_once __DIR__.'/GreyZone.php';

/**
 * Los valores por defecto. Donde el oficial dice algo, dice esto.
 *
 * `defence_mode`:
 *   'world'  — la defensa sale de los mejores ejércitos del mundo (oficial).
 *   'manual' — la defensa la fija el administrador en puntos, sin mirar el mundo.
 */
function artefactReleaseDefaults() {
    return array(
        // Cuántas aldeas por tipo de artefacto y tamaño.
        // CALIBRADO para este mundo: ver artefactReleaseOfficialValues().
        'count_small'      => 2,
        'count_large'      => 1,
        'count_unique'     => 1,

        // Planos de construcción de la Maravilla. No son un tamaño: son su propia clase
        // (ver ARTEFACT_PLAN). Con dos alianzas peleando la Maravilla hacen falta al menos
        // cuatro para que las dos puedan pasar del nivel 49, que es donde el oficial pide
        // el segundo plano.
        'count_plans'      => 4,

        // De dónde sale la defensa.
        'defence_mode'     => 'world',
        'defence_sample'   => 100,    // "top 100 offensive armies", oficial
        'defence_factor'   => 35,     // CALIBRADO: el oficial equivale al 100%
        'defence_manual'   => 50000,  // puntos de defensa del PEQUEÑO, si el modo es manual
        'defence_floor'    => 50000,  // piso: un mundo sin ejércitos no puede dar 0

        // La proporción entre tamaños es oficial y no se toca a la ligera.
        'tier_large'       => 1.5384, // el grande, sobre el pequeño
        'tier_unique'      => 1.5,    // el único, sobre el grande
        'tier_plan'        => 1.5384, // el plano, sobre el pequeño (igual que el grande)

        // Anillos, como fracción de WORLD_MAX. Oficial sobre un mapa de ±200.
        'ring_unique_min'  => 0,   'ring_unique_max' => 13,
        'ring_large_min'   => 10,  'ring_large_max'  => 30,
        'ring_small_min'   => 20,  'ring_small_max'  => 55,
        // El plano va en la periferia como el pequeño: es lo primero que se sale a buscar
        // y ponerlo en el centro lo dejaría al alcance de quien ya tiene la Maravilla.
        'ring_plan_min'    => 20,  'ring_plan_max'   => 55,

        // La aldea.
        'treasury'         => 10,   // CALIBRADO: el oficial es 20 (unas 55 catapultas)
        'fields'           => 10,   // nivel de los 18 campos de recurso
        'cranny'           => 10,   // escondite
        'wall'             => 0     // oficial: 0, y los natars sólo llegan a 1
    );
}

/**
 * Los valores oficiales, donde el oficial dice algo.
 *
 * Existe para que la divergencia entre lo que abre el formulario y lo que hace el Travian
 * original esté **declarada y sea comprobable**, en vez de vivir en la memoria de alguien.
 * `artefactReleaseDefaults()` abre calibrado para un mundo chico —de nada sirve un botón que
 * hay que corregir a mano cada vez— y todo lo que se aparta figura acá con su valor real.
 *
 * Las tres divergencias y por qué:
 *
 *   - `count_*`: el oficial no publica cuántos artefactos suelta. Los 6/4/1 que había eran
 *     una constante heredada, y en un mundo de cuatro jugadores dan 87 aldeas natar nuevas.
 *     Lo mismo vale para `count_plans`, `tier_plan` y la banda `ring_plan_*`: el oficial
 *     tampoco publica cuántos planos de construcción libera ni con qué defensa, así que no
 *     figuran acá — no se apartan de un valor oficial, es que no hay ninguno del que
 *     apartarse.
 *   - `defence_factor`: al 100% la aldea más chica pide un ejército entero de los mejores
 *     del servidor y no queda nada para las otras dos oleadas del robo. Al 35% cuesta un
 *     tercio, que es lo que hace la carrera jugable con pocos jugadores.
 *   - `treasury`: el oficial pone 20 en todas las aldeas de artefacto, que son unas 55
 *     catapultas sólo para abrir el Tesoro. Con 10 el robo entra en el alcance de un
 *     jugador solo.
 *
 * Lo que NO se aparta y no debería apartarse: el modo derivado del mundo, la muestra de 100
 * ejércitos, las proporciones entre tamaños y los anillos del mapa.
 */
function artefactReleaseOfficialValues() {
    return array(
        'defence_mode'     => 'world',
        'defence_sample'   => 100,
        'defence_factor'   => 100,
        'tier_large'       => 1.5384,
        'tier_unique'      => 1.5,
        'ring_unique_min'  => 0,   'ring_unique_max' => 13,
        'ring_large_min'   => 10,  'ring_large_max'  => 30,
        'ring_small_min'   => 20,  'ring_small_max'  => 55,
        'treasury'         => 20,
        'wall'             => 0
    );
}

/** Las claves donde este mundo se aparta del oficial a propósito. */
function artefactReleaseDeliberateDivergences() {
    return array('defence_factor', 'treasury');
}

/** Los tres tamaños, con la clave de configuración de cada uno. */
function artefactReleaseSizes() {
    return array(
        ARTEFACT_SIZE_SMALL  => array('count' => 'count_small',  'label' => 'Pequeño',
            'ring' => array('ring_small_min', 'ring_small_max')),
        ARTEFACT_SIZE_LARGE  => array('count' => 'count_large',  'label' => 'Grande',
            'ring' => array('ring_large_min', 'ring_large_max')),
        ARTEFACT_SIZE_UNIQUE => array('count' => 'count_unique', 'label' => 'Único',
            'ring' => array('ring_unique_min', 'ring_unique_max'))
    );
}

/**
 * Todas las bandas de anillo que hay que validar: los tres tamaños y el plano.
 *
 * El plano no es un tamaño y por eso no sale de `artefactReleaseSizes()`, pero su banda
 * se valida igual que las otras tres. Devuelve `array(etiqueta => array(claveMin, claveMax))`.
 */
function artefactReleaseRingBands() {
    $bands = array();
    foreach(artefactReleaseSizes() as $meta) {
        $bands[$meta['label']] = $meta['ring'];
    }
    $bands['Plano de construcción'] = array('ring_plan_min', 'ring_plan_max');
    return $bands;
}

/**
 * Los límites de cada campo: mínimo, máximo y si admite decimales.
 *
 * Vive como tabla porque la validación del POST y la ayuda del formulario tienen que decir
 * lo mismo. Un formulario que promete un rango y un servidor que acepta otro es la forma
 * más común de que entre basura.
 */
function artefactReleaseLimits() {
    return array(
        'count_small'     => array(0, 50, false),
        'count_large'     => array(0, 50, false),
        'count_unique'    => array(0, 10, false),
        'count_plans'     => array(0, 50, false),
        'defence_sample'  => array(1, 1000, false),
        'defence_factor'  => array(1, 1000, false),
        'defence_manual'  => array(0, 500000000, false),
        'defence_floor'   => array(0, 500000000, false),
        'tier_large'      => array(1, 20, true),
        'tier_unique'     => array(1, 20, true),
        'tier_plan'       => array(1, 20, true),
        'ring_unique_min' => array(0, 100, false),
        'ring_unique_max' => array(0, 100, false),
        'ring_large_min'  => array(0, 100, false),
        'ring_large_max'  => array(0, 100, false),
        'ring_small_min'  => array(0, 100, false),
        'ring_small_max'  => array(0, 100, false),
        'ring_plan_min'   => array(0, 100, false),
        'ring_plan_max'   => array(0, 100, false),
        'treasury'        => array(1, 20, false),
        'fields'          => array(0, 20, false),
        'cranny'          => array(0, 20, false),
        'wall'            => array(0, 20, false)
    );
}

/**
 * Limpia lo que llegó del formulario y devuelve una configuración usable más la lista de
 * lo que hubo que corregir.
 *
 * Es la frontera de confianza: el formulario del panel avisa, pero un POST repetido con
 * curl no pasa por el formulario. Nada de lo que sale de acá puede estar fuera de rango ni
 * dejar el plan en un estado imposible (un anillo invertido, un tamaño sin banda, un
 * conteo negativo).
 */
function artefactReleaseNormalizeConfig($input) {
    $defaults = artefactReleaseDefaults();
    $limits = artefactReleaseLimits();
    $config = $defaults;
    $warnings = array();
    $input = is_array($input) ? $input : array();

    foreach($limits as $key => $limit) {
        list($min, $max, $decimal) = $limit;
        if(!isset($input[$key]) || $input[$key] === '') {
            continue;
        }
        $raw = $input[$key];
        if(!is_scalar($raw) || !is_numeric($raw)) {
            $warnings[] = $key.': "'.(is_scalar($raw) ? (string)$raw : 'valor inválido')
                .'" no es un número, se usa '.$defaults[$key];
            continue;
        }
        $value = $decimal ? (float)$raw : (int)$raw;
        if($value < $min || $value > $max) {
            $warnings[] = $key.': '.$value.' está fuera del rango '.$min.'-'.$max
                .', se recorta';
            $value = max($min, min($max, $value));
        }
        $config[$key] = $value;
    }

    $mode = isset($input['defence_mode']) ? (string)$input['defence_mode'] : $defaults['defence_mode'];
    if(!in_array($mode, array('world', 'manual'), true)) {
        $warnings[] = 'defence_mode: "'.$mode.'" no existe, se usa "'.$defaults['defence_mode'].'"';
        $mode = $defaults['defence_mode'];
    }
    $config['defence_mode'] = $mode;

    // Un anillo invertido no puede contener ninguna casilla: se da vuelta en vez de dejar
    // el plan sin sitio donde colocar nada. El plano tiene su propia banda y entra en la
    // misma vuelta, o sería el único campo del formulario sin esta red.
    foreach(artefactReleaseRingBands() as $label => $keys) {
        list($minKey, $maxKey) = $keys;
        if($config[$minKey] > $config[$maxKey]) {
            $warnings[] = $label.': el anillo iba de '.$config[$minKey].'% a '
                .$config[$maxKey].'%, se da vuelta';
            $swap = $config[$minKey];
            $config[$minKey] = $config[$maxKey];
            $config[$maxKey] = $swap;
        }
    }

    if($config['count_small'] + $config['count_large'] + $config['count_unique']
        + $config['count_plans'] === 0) {
        $warnings[] = 'los cuatro conteos están en cero: no se sembraría nada';
    }

    return array('config' => $config, 'warnings' => $warnings);
}

/**
 * La referencia oficial: la potencia ofensiva de los mejores ejércitos del mundo.
 *
 * Suma los puntos de ataque de todas las tropas de cada jugador —las de casa y las que
 * tenga reforzando— y devuelve el promedio de los `$sample` mejores. Con el factor en 100%
 * eso deja la aldea de artefacto pequeña al alcance de un ejército de los de arriba, que es
 * lo que hace que la carrera exista: si nadie puede tomarla, no hay carrera, y si la toma
 * cualquiera, tampoco.
 *
 * Un mundo recién instalado devuelve 0 y el piso de la configuración se encarga.
 */
function artefactReleaseReferenceOffence($database, $sample = 100) {
    $sample = max(1, (int)$sample);
    if(!is_object($database) || !method_exists($database, 'query_return')) {
        return 0;
    }
    $attack = array();
    for($unit = 1; $unit <= 50; $unit++) {
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $attack[$unit] = is_array($data) && isset($data['atk']) ? (float)$data['atk'] : 0.0;
    }

    $power = array();
    $sources = array(
        'SELECT v.`owner` AS owner, t.* FROM '.TB_PREFIX.'units t '
            .'INNER JOIN '.TB_PREFIX.'vdata v ON v.`wref` = t.`vref` '
            .'WHERE '.playerAccountSql('v`.`owner'),
        'SELECT v.`owner` AS owner, t.* FROM '.TB_PREFIX.'enforcement t '
            .'INNER JOIN '.TB_PREFIX.'vdata v ON v.`wref` = t.`from` '
            .'WHERE '.playerAccountSql('v`.`owner')
    );
    foreach($sources as $query) {
        $rows = $database->query_return($query);
        if(!is_array($rows)) {
            continue;
        }
        foreach($rows as $row) {
            $owner = (int)$row['owner'];
            if(!isset($power[$owner])) {
                $power[$owner] = 0.0;
            }
            for($unit = 1; $unit <= 50; $unit++) {
                if(isset($row['u'.$unit])) {
                    $power[$owner] += (float)$row['u'.$unit] * $attack[$unit];
                }
            }
        }
    }
    if(!$power) {
        return 0;
    }
    rsort($power);
    $top = array_slice($power, 0, $sample);
    return (int)round(array_sum($top) / count($top));
}

/** Los puntos de defensa que le tocan a una aldea de cada tamaño. */
function artefactReleaseDefenceTarget($config, $reference, $size) {
    $config = is_array($config) ? $config : artefactReleaseDefaults();
    $base = $config['defence_mode'] === 'manual'
        ? (float)$config['defence_manual']
        : (float)$reference * ((float)$config['defence_factor'] / 100);
    $base = max($base, (float)$config['defence_floor']);

    switch((int)$size) {
        case ARTEFACT_SIZE_LARGE:
            return $base * (float)$config['tier_large'];
        case ARTEFACT_SIZE_UNIQUE:
            return $base * (float)$config['tier_large'] * (float)$config['tier_unique'];
    }
    return $base;
}

/**
 * Los puntos de defensa de una aldea con un plano de construcción.
 *
 * El oficial no publica un número para esto. Se le da el mismo peso que al artefacto
 * grande porque es lo que vale: un plano no mejora ninguna aldea, pero sin él la carrera
 * por la Maravilla no arranca, así que tiene que costar tanto como un artefacto de cuenta.
 */
function artefactReleasePlanDefenceTarget($config, $reference) {
    $config = is_array($config) ? $config : artefactReleaseDefaults();
    $base = artefactReleaseDefenceTarget($config, $reference, ARTEFACT_SIZE_SMALL);
    return $base * (float)$config['tier_plan'];
}

/**
 * La composición de referencia de una guarnición natar, en proporción.
 *
 * Sale de `natarWonderGarrison()` para no inventar un segundo ejército natar: si mañana se
 * corrige la composición de las Maravillas, esto la sigue. Lo que cambia acá es la ESCALA,
 * no la mezcla.
 */
function artefactReleaseComposition() {
    return natarWonderGarrison(false);
}

/**
 * Convierte un presupuesto de puntos de defensa en tropas concretas.
 *
 * Escala la composición natar hasta que su defensa contra infantería llegue al objetivo.
 * Se mide contra infantería porque es el número que el defensor ve primero y el que la
 * vista previa muestra; la defensa contra caballería sale de la misma mezcla.
 */
function artefactReleaseGarrison($defenceTarget) {
    $composition = artefactReleaseComposition();
    $unitDefence = 0.0;
    foreach($composition as $unit => $amount) {
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $unitDefence += $amount * (is_array($data) && isset($data['di']) ? (float)$data['di'] : 0.0);
    }
    if($unitDefence <= 0) {
        return array();
    }
    $scale = max(0.0, (float)$defenceTarget) / $unitDefence;
    $garrison = array();
    foreach($composition as $unit => $amount) {
        $garrison[$unit] = (int)round($amount * $scale);
    }
    return $garrison;
}

/** Lo que hay que saber de una guarnición para mostrarla: tropas, defensa y consumo. */
function artefactReleaseGarrisonStats($garrison) {
    $troops = 0;
    $infantry = 0.0;
    $cavalry = 0.0;
    $upkeep = 0;
    foreach((array)$garrison as $unit => $amount) {
        $amount = max(0, (int)$amount);
        $data = isset($GLOBALS['u'.$unit]) ? $GLOBALS['u'.$unit] : null;
        $troops += $amount;
        if(is_array($data)) {
            $infantry += $amount * (isset($data['di']) ? (float)$data['di'] : 0);
            $cavalry += $amount * (isset($data['dc']) ? (float)$data['dc'] : 0);
            $upkeep += $amount * (isset($data['pop']) ? (int)$data['pop'] : 0);
        }
    }
    return array(
        'troops' => $troops,
        'infantry' => (int)round($infantry),
        'cavalry' => (int)round($cavalry),
        'upkeep' => $upkeep
    );
}

/** La banda de distancia al centro, en casillas, de un tamaño de artefacto. */
function artefactReleaseRing($config, $size) {
    $config = is_array($config) ? $config : artefactReleaseDefaults();
    $sizes = artefactReleaseSizes();
    if(!isset($sizes[(int)$size])) {
        return array(0.0, (float)WORLD_MAX);
    }
    list($minKey, $maxKey) = $sizes[(int)$size]['ring'];
    $span = (float)WORLD_MAX;
    return array(
        $span * ((float)$config[$minKey] / 100),
        $span * ((float)$config[$maxKey] / 100)
    );
}

/** La banda de distancia al centro, en casillas, de una aldea con un plano. */
function artefactReleasePlanRing($config) {
    $config = is_array($config) ? $config : artefactReleaseDefaults();
    $span = (float)WORLD_MAX;
    return array(
        $span * ((float)$config['ring_plan_min'] / 100),
        $span * ((float)$config['ring_plan_max'] / 100)
    );
}

/**
 * El plan completo: qué aldeas hay que crear, con qué adentro.
 *
 * Es lo que consume tanto la vista previa como el sembrado, para que lo que se anuncia y
 * lo que se crea no puedan diferir.
 */
function artefactReleasePlan($config, $reference) {
    $config = is_array($config) ? $config : artefactReleaseDefaults();
    $sizes = artefactReleaseSizes();
    $villages = array();
    $summary = array();

    foreach($sizes as $size => $meta) {
        $count = (int)$config[$meta['count']];
        $defence = artefactReleaseDefenceTarget($config, $reference, $size);
        $garrison = artefactReleaseGarrison($defence);
        $stats = artefactReleaseGarrisonStats($garrison);
        $ring = artefactReleaseRing($config, $size);

        $types = array();
        // `artefactEffectTypeCatalog()` y no el catálogo entero: el plano de construcción
        // no se siembra por tamaño, tiene su propia vuelta más abajo. Recorriendo los nueve
        // se sembraban tres planos por cada uno pedido, uno por cada tamaño.
        foreach(array_keys(artefactEffectTypeCatalog()) as $type) {
            // El plano de almacenamiento no tiene versión única, igual que en el original.
            if($type === ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE) {
                continue;
            }
            $types[] = $type;
        }

        $summary[$size] = array(
            'label' => $meta['label'],
            'per_type' => $count,
            'types' => count($types),
            'villages' => $count * count($types),
            'defence_target' => (int)round($defence),
            'ring' => $ring,
            'garrison' => $garrison,
            'stats' => $stats
        );

        foreach($types as $type) {
            for($i = 0; $i < $count; $i++) {
                $villages[] = array(
                    'type' => $type,
                    'size' => $size,
                    'garrison' => $garrison,
                    'ring' => $ring,
                    'treasury' => (int)$config['treasury'],
                    'fields' => (int)$config['fields'],
                    'cranny' => (int)$config['cranny'],
                    'wall' => (int)$config['wall']
                );
            }
        }
    }

    // Los planos de construcción, que no son un tamaño sino su propia clase. Van en una
    // vuelta aparte porque no se multiplican por los ocho tipos: hay UN plano y se siembran
    // tantas copias como pida la configuración. Se guardan con tamaño "pequeño" a propósito,
    // que es el que pide un Tesoro de nivel 10 — el mismo que pide el plano por regla
    // oficial, así que las dos formas de calcularlo coinciden en vez de contradecirse.
    $planCount = (int)$config['count_plans'];
    $planDefence = artefactReleasePlanDefenceTarget($config, $reference);
    $planGarrison = artefactReleaseGarrison($planDefence);
    $planStats = artefactReleaseGarrisonStats($planGarrison);
    $planRing = artefactReleasePlanRing($config);

    $plan = array(
        'label' => 'Plano de construcción',
        'per_type' => $planCount,
        'types' => 1,
        'villages' => $planCount,
        'defence_target' => (int)round($planDefence),
        'ring' => $planRing,
        'garrison' => $planGarrison,
        'stats' => $planStats
    );

    for($i = 0; $i < $planCount; $i++) {
        $villages[] = array(
            'type' => ARTEFACT_PLAN,
            'size' => ARTEFACT_SIZE_SMALL,
            'garrison' => $planGarrison,
            'ring' => $planRing,
            'treasury' => (int)$config['treasury'],
            'fields' => (int)$config['fields'],
            'cranny' => (int)$config['cranny'],
            'wall' => (int)$config['wall']
        );
    }

    $totalTroops = array_sum(array_map(function ($row) {
        return $row['stats']['troops'] * $row['villages'];
    }, $summary)) + $planStats['troops'] * $planCount;

    return array(
        'config' => $config,
        'reference' => (int)$reference,
        'summary' => $summary,
        'plans' => $plan,
        'villages' => $villages,
        'total_villages' => count($villages),
        'total_troops' => $totalTroops
    );
}

/**
 * Una casilla libre dentro de un anillo de distancia al centro.
 *
 * Devuelve 0 si el anillo no tiene ninguna libre; el que llama decide si ensancha o se
 * rinde. No marca nada como ocupado: eso es del que crea la aldea, para no reservar una
 * casilla que después no se use.
 */
function artefactReleaseFindTile($database, $ringMin, $ringMax, $taken = array()) {
    if(!is_object($database) || !method_exists($database, 'query_return')) {
        return 0;
    }
    $max = (int)ceil($ringMax);
    $rows = $database->query_return(
        'SELECT `id`, `x`, `y` FROM '.TB_PREFIX.'wdata '
        .'WHERE `occupied` = 0 AND `oasistype` = 0 AND `fieldtype` > 0 '
        .'AND `x` BETWEEN '.(-$max).' AND '.$max.' '
        .'AND `y` BETWEEN '.(-$max).' AND '.$max
    );
    if(!is_array($rows) || !$rows) {
        return 0;
    }
    $candidates = array();
    foreach($rows as $row) {
        $id = (int)$row['id'];
        if(isset($taken[$id])) {
            continue;
        }
        $distance = greyZoneDistanceToCentre((int)$row['x'], (int)$row['y']);
        if($distance < $ringMin || $distance > $ringMax) {
            continue;
        }
        $candidates[] = $id;
    }
    if(!$candidates) {
        return 0;
    }
    shuffle($candidates);
    return $candidates[0];
}

/**
 * Crea una aldea natar con un artefacto adentro, siguiendo una línea del plan.
 *
 * Vive acá y no en el mod del panel para que un checker la pueda llamar de verdad sobre
 * tablas temporales: mientras estaba dentro del script del panel —que empieza validando
 * `$_POST` y termina en un `header()`— no había forma de probarla sin un navegador.
 *
 * Mismo camino que el instalador y que `addWW.php`: la economía y la clase NPC salen de
 * NatarVillage.php, nunca de SQL escrito acá.
 *
 * `$taken` acumula las casillas ya elegidas en esta misma corrida. `artefactReleaseFindTile()`
 * no reserva nada, así que sin esto dos aldeas del mismo anillo podrían pelearse la misma.
 * Devuelve el wref creado, o 0 si no quedaba sitio.
 */
function artefactReleaseCreateVillage($database, $village, $natarId, &$taken) {
    $wref = artefactReleaseFindTile($database, $village['ring'][0], $village['ring'][1], $taken);
    if($wref <= 0) {
        // El anillo se quedó sin casillas: se ensancha a todo el mapa antes de rendirse,
        // porque es peor sembrar la mitad de los artefactos que correrlos de lugar.
        $wref = artefactReleaseFindTile($database, 0, WORLD_MAX, $taken);
    }
    if($wref <= 0) {
        return 0;
    }
    $taken[$wref] = true;

    $database->setFieldTaken($wref);
    artefactReleasePopulateVillage($database, $village, $natarId, $wref);
    $database->addArtefact($wref, $natarId, $village['type'], $village['size']);
    return $wref;
}

/** Provisiona la casilla ya reservada; el llamador crea o traslada el artefacto. */
function artefactReleasePopulateVillage($database, $village, $natarId, $wref, $syncPopulation = true) {
    $name = natarArtefactVillageName($village['type'], $village['size'], $wref);
    $database->addVillage($wref, $natarId, $name, '0', $syncPopulation);
    $database->addResourceFields($wref, $database->getVillageType($wref));
    $database->addUnits($wref);
    $database->addTech($wref);
    $database->addABTech($wref);

    // `natar = 1` la marca como aldea de escenario y `npckind` estático la deja fuera de la
    // manutención y de la hambruna. Sin estas dos columnas la guarnición se muere sola y el
    // artefacto queda servido: es el bug que ya se había arreglado en las Maravillas.
    $database->query("UPDATE `".TB_PREFIX."vdata` SET `name` = '".mysql_real_escape_string($name)."', "
        ."`capital` = 0, `natar` = 1 WHERE `wref` = ".(int)$wref);
    if(method_exists($database, 'ensureNpcVillageColumns') && $database->ensureNpcVillageColumns()) {
        $database->query("UPDATE `".TB_PREFIX."vdata` SET `npckind` = ".NPC_KIND_STATIC
            ." WHERE `wref` = ".(int)$wref);
    }

    natarArtefactBuildings($wref, $village['treasury'], $village['cranny'],
        $village['wall'], artefactReleaseWallType());
    natarRestockGarrison($wref, $village['garrison']);
    // Aprovisiona campos, almacén y granero, y recalcula la población desde `fdata`: sin
    // esto la aldea queda con 800 de almacén y nunca hay nada que saquear.
    natarProvisionVillage($wref, $village['fields']);
}

/**
 * El muro que levantan estas aldeas cuando la configuración pide muralla.
 *
 * Se resuelve acá y no en NatarVillage.php porque esa constante la define
 * NatarSettlement.php, que a su vez incluye a NatarVillage: mirarla desde allá sería una
 * dependencia circular.
 */
function artefactReleaseWallType() {
    return defined('NATAR_SETTLEMENT_WALL_TYPE') ? (int)NATAR_SETTLEMENT_WALL_TYPE : 31;
}

/** Ejecuta un plan entero. Devuelve cuántas aldeas se crearon y cuántas quedaron sin sitio. */
function artefactReleaseExecute($database, $plan, $natarId) {
    $taken = array();
    $created = array();
    $failed = 0;
    foreach($plan['villages'] as $village) {
        $wref = artefactReleaseCreateVillage($database, $village, $natarId, $taken);
        if($wref > 0) {
            $created[] = $wref;
        } else {
            $failed++;
        }
    }
    return array('created' => $created, 'failed' => $failed);
}

/**
 * Deshacer una liberación: borra los artefactos y las aldeas natar que los guardaban.
 *
 * Por qué hace falta. Sembrar no se podía deshacer, y el sembrado tiene una docena de
 * perillas que hay que calibrar mirando la vista previa: sin una forma de volver atrás, el
 * primer intento con los números mal deja el mapa lleno de aldeas natar que ya no se pueden
 * sacar. Un botón de "probar" sin botón de "descartar" no es un botón de probar.
 *
 * Qué borra y qué NO, que es la parte delicada:
 *
 *   - **Sí**: las aldeas natar que guardan un artefacto, con todas sus filas y liberando su
 *     casilla del mapa.
 *   - **No**: las Aldeas de la Maravilla (`f99t = 40`) ni la capital natar. Están en el mapa
 *     desde el día uno, no las creó el sembrado, y son el final de partida entero.
 *   - **No**: la aldea de un JUGADOR que capturó el artefacto. Ahí se borra la fila del
 *     artefacto y nada más — arrasarle la aldea a alguien por limpiar un sembrado sería
 *     bastante peor que el problema que esto resuelve.
 *   - **No**: las aldeas natar independientes (`npckind` vivo) que no guarden artefactos.
 *
 * `$apply` a false devuelve el mismo informe sin escribir nada, que es como corre por
 * defecto la herramienta de línea de comandos.
 */
function artefactReleaseWipe($database, $apply = false) {
    $report = array(
        'artefacts' => 0,
        'villages' => array(),
        'player_held' => array(),
        'protected' => array()
    );
    if(!is_object($database) || !method_exists($database, 'query_return')) {
        return $report;
    }

    $rows = $database->query_return(
        'SELECT a.`id`, a.`vref`, a.`type`, a.`size`, a.`owner` AS holder, '
        .'v.`owner` AS village_owner, v.`capital`, f.`f99t` '
        .'FROM '.TB_PREFIX.'artefacts a '
        .'LEFT JOIN '.TB_PREFIX.'vdata v ON v.`wref` = a.`vref` '
        .'LEFT JOIN '.TB_PREFIX.'fdata f ON f.`vref` = a.`vref`'
    );
    if(!is_array($rows)) {
        return $report;
    }

    $natarId = natarsAccountId();
    foreach($rows as $row) {
        $report['artefacts']++;
        $wref = (int)$row['vref'];
        $name = artefactDisplayName((int)$row['type'], (int)$row['size']);

        if($row['village_owner'] === null) {
            // El artefacto apunta a una aldea que ya no existe: sólo se va la fila.
            continue;
        }
        if((int)$row['village_owner'] !== $natarId) {
            $report['player_held'][] = array('wref' => $wref, 'name' => $name);
            continue;
        }
        if((int)$row['f99t'] === 40 || (int)$row['capital'] === 1) {
            // Una Maravilla o la capital natar con un artefacto adentro: se le saca el
            // artefacto y la aldea queda donde está.
            $report['protected'][] = array('wref' => $wref, 'name' => $name);
            continue;
        }
        $report['villages'][] = array('wref' => $wref, 'name' => $name);
    }

    if(!$apply) {
        return $report;
    }

    foreach($report['villages'] as $village) {
        artefactReleaseDeleteVillage($database, (int)$village['wref']);
    }
    $database->query('DELETE FROM '.TB_PREFIX.'artefacts');
    if(method_exists($database, 'flushArtefactCache')) {
        $database->flushArtefactCache();
    }
    return $report;
}

/**
 * Borra una aldea natar de artefacto y todo lo que colgaba de ella.
 *
 * La lista de tablas es la misma que usa `Automation::destroyCatapultedVillage()`, y cada
 * una con SU columna: `bdata` es `wid` y el resto `vref`, que es el error que ya dejó 88
 * filas huérfanas en este mundo. `movement` y `enforcement` se filtran por `from` y por
 * `to`/`vref` con backticks, porque son palabras reservadas y sin ellas el DELETE ni
 * siquiera compila.
 *
 * No se usa `destroyCatapultedVillage()` a propósito: esa función se niega —con razón— a
 * arrasar una aldea que guarda un artefacto, que es justo lo que acá hay que hacer.
 */
function artefactReleaseDeleteVillage($database, $wref) {
    $wref = (int)$wref;
    if($wref <= 0) {
        return false;
    }
    $P = TB_PREFIX;

    // Los movimientos que entran o salen, y las filas de tropas a las que apuntan.
    $database->query('DELETE a FROM '.$P.'attacks a '
        .'INNER JOIN '.$P.'movement m ON m.`ref` = a.`id` '
        .'WHERE m.`to` = '.$wref.' OR m.`from` = '.$wref);
    $database->query('DELETE FROM '.$P.'movement WHERE `from` = '.$wref.' OR `to` = '.$wref);
    $database->query('DELETE FROM '.$P.'enforcement WHERE `vref` = '.$wref.' OR `from` = '.$wref);

    foreach(array('abdata' => 'vref', 'bdata' => 'wid', 'fdata' => 'vref', 'market' => 'vref',
        'research' => 'vref', 'tdata' => 'vref', 'training' => 'vref', 'units' => 'vref') as $table => $column) {
        $database->query('DELETE FROM '.$P.$table.' WHERE `'.$column.'` = '.$wref);
    }
    // Las entradas de listas de granjeo de OTROS jugadores que apuntaban acá.
    $database->query('DELETE FROM '.$P.'raidlist WHERE `towref` = '.$wref);
    $database->query('DELETE FROM '.$P.'farmlist WHERE `wref` = '.$wref);

    $database->query('DELETE FROM '.$P.'vdata WHERE `wref` = '.$wref);
    $database->query('UPDATE '.$P.'wdata SET `occupied` = 0 WHERE `id` = '.$wref);
    return true;
}

/* ------------------------------------------------------------------------------------
 * LA LIBERACIÓN PROGRAMADA
 *
 * En el Travian oficial los artefactos no los suelta nadie a mano: aparecen solos en una
 * fecha anunciada de antemano, y esa fecha es la que arranca la carrera final —todo el
 * mundo sabe cuándo empieza y se prepara. Este servidor sólo tenía el botón del panel, así
 * que la liberación era una sorpresa para todos menos para el administrador, que además
 * tenía que estar despierto a la hora que quisiera.
 *
 * Cómo funciona: el panel guarda la fecha y el plan CONGELADO en JSON. Cualquier request
 * que pase por `Automation` mira si la fecha ya pasó y, si toca, toma el turno con un
 * compare-and-swap sobre `artefact_release_done` y siembra. No hay cron: es el mismo patrón
 * que las aldeas natar vivas y las aventuras.
 *
 * Por qué se congela el plan y no se recalcula al disparar. La guarnición se deriva de los
 * mejores ejércitos del mundo, y entre programar y disparar el mundo cambia: si se
 * recalculara, el administrador estaría aprobando una vista previa y el servidor sembraría
 * otra cosa. Lo que se ve es lo que se siembra, aunque pasen dos semanas.
 *
 * Lo que sí se recalcula al disparar es DÓNDE va cada aldea: `artefactReleaseExecute()`
 * busca casillas libres en el momento, porque una casilla libre hoy puede tener una aldea
 * mañana.
 * ------------------------------------------------------------------------------------ */

/**
 * La programación actual, normalizada.
 *
 * `available` es false en un mundo al que todavía no le llegó la migración: ahí no hay
 * liberación programada y el panel lo dice en vez de fingir que la guardó.
 */
function artefactReleaseScheduleStatus($database, $now = null) {
    $now = $now === null ? time() : (int)$now;
    $status = array(
        'available' => false,
        'at' => 0,
        'done' => 0,
        'config' => artefactReleaseDefaults(),
        'scheduled' => false,
        'due' => false,
        'seconds' => 0
    );
    if(!is_object($database) || !method_exists($database, 'getArtefactReleaseSchedule')) {
        return $status;
    }
    $row = $database->getArtefactReleaseSchedule();
    if(!is_array($row)) {
        return $status;
    }
    $status['available'] = true;
    $status['at'] = (int)$row['at'];
    $status['done'] = (int)$row['done'];

    $decoded = $row['config'] !== '' ? json_decode($row['config'], true) : null;
    // Un JSON corrupto o de una versión anterior del formulario no puede dejar el sembrado
    // sin configuración: se vuelve a normalizar contra los límites de hoy, que es lo mismo
    // que hace el POST del panel.
    $normalized = artefactReleaseNormalizeConfig(is_array($decoded) ? $decoded : array());
    $status['config'] = $normalized['config'];
    $status['config_warnings'] = $normalized['warnings'];

    $status['scheduled'] = $status['at'] > 0 && $status['done'] === 0;
    $status['due'] = $status['scheduled'] && $status['at'] <= $now;
    $status['seconds'] = $status['scheduled'] ? max(0, $status['at'] - $now) : 0;
    return $status;
}

/** Guarda la fecha y el plan congelado. `$at = 0` cancela la programación. */
function artefactReleaseSchedule($database, $at, $config) {
    if(!is_object($database) || !method_exists($database, 'setArtefactReleaseSchedule')) {
        return false;
    }
    $normalized = artefactReleaseNormalizeConfig($config);
    $json = json_encode($normalized['config']);
    return $database->setArtefactReleaseSchedule($at, $json === false ? '' : $json);
}

/**
 * Dispara la liberación si toca. Devuelve el informe, o null si no había nada que hacer.
 *
 * El turno se toma ANTES de sembrar, no después: si se tomara después, dos requests
 * simultáneas sembrarían las dos y recién entonces se pelearían por marcar el turno. Y si
 * el sembrado se cae a la mitad, el turno queda tomado igual — es lo correcto: mejor media
 * liberación que dos enteras, y el panel muestra lo que hay.
 */
function artefactReleaseRunScheduled($database, $now = null) {
    $now = $now === null ? time() : (int)$now;
    $status = artefactReleaseScheduleStatus($database, $now);
    if(!$status['available'] || !$status['due']) {
        return null;
    }
    if(!method_exists($database, 'claimArtefactRelease') || !$database->claimArtefactRelease($now)) {
        return null;
    }

    $config = $status['config'];
    $reference = $config['defence_mode'] === 'world'
        ? artefactReleaseReferenceOffence($database, $config['defence_sample'])
        : 0;
    $plan = artefactReleasePlan($config, $reference);
    $natarId = natarsAccountId();
    if($natarId <= 0) {
        // Sin cuenta natar no hay a quién ponerle las aldeas. El turno ya está tomado, así
        // que esto no se reintenta solo: es un mundo mal instalado y hay que mirarlo.
        return array('created' => array(), 'failed' => $plan['total_villages'],
            'error' => 'no existe la cuenta Natars');
    }
    $result = artefactReleaseExecute($database, $plan, $natarId);
    $result['plan'] = $plan;
    return $result;
}
