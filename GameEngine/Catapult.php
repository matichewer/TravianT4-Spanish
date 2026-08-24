<?php

/**
 * Política compartida servidor/UI de los edificios: cómo se llaman, qué ícono los
 * representa, cuáles se pueden elegir como objetivo de una catapulta y cómo se redacta
 * el daño en el informe.
 *
 * El nombre vive acá y en un solo lugar. Antes había tres listas: la de
 * `Automation::procResType()`, la copia idéntica de `Building::procResType()` y la del
 * catálogo de objetivos, que se había desincronizado —el desplegable de la catapulta
 * ofrecía "Excavación de barro" y el informe después decía "Barrera", que es el nombre
 * que usa el resto del juego.
 */
function buildingDisplayName($type, $fallback = 'Error') {
    $names = array(
        1 => 'Leñador', 2 => 'Barrera', 3 => 'Mina de hierro', 4 => 'Granja',
        5 => 'Aserradero', 6 => 'Fábrica de ladrillos', 7 => 'Fundición de hierro',
        8 => 'Molino', 9 => 'Panadería', 10 => 'Almacén', 11 => 'Granero',
        12 => 'Herrería', 14 => 'Plaza de torneos', 15 => 'Edificio principal',
        16 => 'Plaza de reuniones', 17 => 'Mercado', 18 => 'Embajada', 19 => 'Cuartel',
        20 => 'Establo', 21 => 'Taller', 22 => 'Academia', 23 => 'Escondite',
        24 => 'Ayuntamiento', 25 => 'Residencia', 26 => 'Palacio', 27 => 'Tesorería',
        28 => 'Oficina de comercio', 29 => 'Gran cuartel', 30 => 'Gran establo',
        31 => 'Muralla', 32 => 'Muro de tierra', 33 => 'Empalizada',
        34 => 'Taller de cantería', 35 => 'Cervecería', 36 => 'Trampero',
        37 => 'Mansión del héroe', 38 => 'Gran almacén', 39 => 'Gran granero',
        40 => 'Maravilla del mundo', 41 => 'Abrevadero', 42 => 'Gran taller'
    );
    return isset($names[(int)$type]) ? $names[(int)$type] : $fallback;
}

/**
 * Edificios cuyo nombre es femenino.
 *
 * Los mensajes del informe se arman pegándole un adjetivo al nombre ("Barrera
 * destruida"), y el adjetivo estaba escrito en masculino a mano. Casi la mitad de los
 * edificios del juego son femeninos, así que la mitad de los informes de catapulta salía
 * mal escrita ("Barrera destruido", "Academia dañado del nivel 5 al nivel 3").
 */
function buildingNameIsFeminine($type) {
    return in_array((int)$type, array(
        2,  // Barrera
        3,  // Mina de hierro
        4,  // Granja
        6,  // Fábrica de ladrillos
        7,  // Fundición de hierro
        9,  // Panadería
        12, // Herrería
        14, // Plaza de torneos
        16, // Plaza de reuniones
        18, // Embajada
        22, // Academia
        25, // Residencia
        27, // Tesorería
        28, // Oficina de comercio
        31, // Muralla
        33, // Empalizada
        35, // Cervecería
        37, // Mansión del héroe
        40  // Maravilla del mundo
    ), true);
}

/**
 * Los tres muros del juego. El T4.0 no tiene un cuarto: la Muralla romana, el Muro de
 * tierra germano y la Empalizada gala son todo lo que puede ocupar el campo 40.
 */
function buildingIsWall($type) {
    return in_array((int)$type, array(31, 32, 33), true);
}

/**
 * Los edificios que sólo puede levantar una tribu, y cuáles son esas tribus.
 *
 * Es la única lista: la usa `Building::isTribeBuildingAllowed()` para decidir qué se
 * puede construir y la conquista para decidir qué se cae cuando la aldea cambia de
 * tribu. Estaban escritas por separado y una conquista entre tribus distintas dejaba en
 * pie una Cervecería germana en manos de un romano —un edificio que el dueño nuevo no
 * podía ni mejorar ni volver a construir si lo demolía.
 *
 * Devuelve null si el edificio no es de tribu.
 */
function buildingTribeLock($type) {
    $locks = array(
        31 => array(1, 5), // Muralla: romanos (y natares)
        32 => array(2, 4), // Muro de tierra: germanos (y la naturaleza)
        33 => array(3),    // Empalizada: galos
        35 => array(2),    // Cervecería: germanos
        36 => array(3),    // Trampero: galos
        41 => array(1)     // Abrevadero: romanos
    );
    return isset($locks[(int)$type]) ? $locks[(int)$type] : null;
}

/** ¿Esta tribu puede tener este edificio? Los que no son de tribu los puede tener cualquiera. */
function tribeCanBuild($type, $tribe) {
    $lock = buildingTribeLock($type);
    return $lock === null || in_array((int)$tribe, $lock, true);
}

/**
 * Clase CSS del ícono de 16x16 de un edificio.
 *
 * El gpack activo trae `img/g/icon/gNIcon.gif` para casi todos, pero con la numeración
 * del T4 oficial, que no es exactamente la de este repo:
 *
 * - la Herrería es el gid 12 acá y el 13 en el arte (el T4 fusionó la Herrería y la
 *   Armería del T3 en un solo edificio), así que su ícono es `g13Icon`;
 * - el Gran taller (42) no tiene ícono en ningún gpack, así que cae en el genérico de
 *   edificios `gebIcon` en lugar de dejar un hueco de 16x16.
 *
 * El tamaño lo pone la clase `unit` (16x16), que es con la que se dibujan los íconos
 * dentro del informe.
 */
function buildingIconClass($type) {
    $type = (int)$type;
    if($type === 12) {
        return 'g13Icon';
    }
    return $type >= 1 && $type <= 41 && $type !== 13 ? 'g'.$type.'Icon' : 'gebIcon';
}

/** El `<img>` del ícono de un edificio, con el nombre como título. */
function buildingIconHtml($type) {
    $name = buildingDisplayName($type, '');
    $label = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    return '<img class="unit '.buildingIconClass($type).'" src="img/x.gif" alt="'.$label.'" title="'.$label.'" />';
}

/**
 * Cómo se cuenta en el informe lo que le pasó a un edificio (o a la muralla, que se
 * redacta igual). Sin ícono: la fila de la catapulta se lo agrega con
 * catapultReportInfoHtml(), y la del ariete ya muestra el ícono del ariete.
 */
function buildingDamageSentence($type, $oldLevel, $newLevel) {
    $name = buildingDisplayName($type);
    $feminine = buildingNameIsFeminine($type);
    $oldLevel = (int)$oldLevel;
    $newLevel = (int)$newLevel;
    if($newLevel <= 0 && $oldLevel > 0) {
        return $name.($feminine ? ' destruida.' : ' destruido.');
    }
    if($newLevel < $oldLevel) {
        return $name.($feminine ? ' dañada' : ' dañado')
            .' del nivel <b>'.$oldLevel.'</b> al nivel <b>'.$newLevel.'</b>.';
    }
    return ($feminine ? 'La ' : 'El ').$name.' no sufrió daños.';
}

/** Una línea de la fila "información" de la catapulta: el ícono del edificio y el texto. */
function catapultImpactLine($type, $oldLevel, $newLevel) {
    return buildingIconHtml($type).' '.buildingDamageSentence($type, $oldLevel, $newLevel);
}

/**
 * La fila "información" de las catapultas dentro del informe.
 *
 * Cada línea trae su propio ícono, porque con dos objetivos el informe mostraba uno solo
 * —el del primero— y el segundo edificio salía sin ícono. Los informes viejos guardaron
 * el texto pelado, así que para ésos se antepone el ícono del tipo que viaja aparte en el
 * dato del informe; ese ícono además se dibujaba con el nombre de una UNIDAD como título
 * (`Technology::unarray` está indexado por unidad, no por edificio).
 */
function catapultReportInfoHtml($iconType, $text) {
    if(strpos((string)$text, '<img') !== false) {
        return $text;
    }
    return buildingIconHtml($iconType).' '.$text;
}

/** Objetivos que se pueden elegir a mano, con el nivel de Plaza de reuniones que piden. */
function catapultTargetCatalog() {
    $levels = array(
        1=>5, 2=>5, 3=>5, 4=>5, 5=>5, 6=>5, 7=>5, 8=>5, 9=>5, 10=>3, 11=>3, 12=>10,
        14=>10, 15=>10, 16=>10, 17=>10, 18=>10, 19=>10, 20=>10, 21=>10, 22=>10,
        23=>PHP_INT_MAX, 24=>10, 25=>10, 26=>10, 27=>10, 28=>10, 29=>10, 30=>10,
        34=>PHP_INT_MAX, 35=>10, 36=>PHP_INT_MAX, 37=>10, 38=>3, 39=>3, 40=>10,
        41=>10, 42=>10
    );
    $catalog = array();
    foreach($levels as $type => $level) {
        $catalog[$type] = array('name' => buildingDisplayName($type), 'level' => $level);
    }
    return $catalog;
}

function catapultNormalizeTarget($value, $rallyPointLevel, $allowSecondRandom = false) {
    if(!is_scalar($value) || !is_numeric($value)) return 0;
    $target = (int)$value;
    if($target === 0 || ($allowSecondRandom && $target === 99)) return $target;
    $catalog = catapultTargetCatalog();
    return isset($catalog[$target]) && (int)$rallyPointLevel >= $catalog[$target]['level'] ? $target : 0;
}

function catapultIsKnownTarget($value, $allowSecondRandom = false) {
    $target = (int)$value;
    return $target === 0 || ($allowSecondRandom && $target === 99) || isset(catapultTargetCatalog()[$target]);
}
