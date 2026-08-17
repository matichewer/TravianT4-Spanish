<?php
/**
 * La zona gris: el centro del mapa donde fundar una aldea despierta a los natars.
 *
 * En el T4 oficial es un disco de radio 22 centrado en (0|0). Adentro hay mejor terreno
 * —oasis del 50%, 15-croppers— y están varias de las Aldeas de la Maravilla, y el peaje
 * por instalarse ahí son 14 oleadas natar que llegan a las ~24 h de fundada la aldea: la
 * primera barre toda defensa y las otras trece traen catapultas. No se defiende; se
 * sobrevive teniendo suficientes construcciones como para no llegar a población 0.
 *
 * Acá la zona es un disco chico —radio 5— y no el de 22 del oficial, y eso es a propósito.
 * Este mundo ya estaba jugado cuando se agregó la función, y el premio que en el T4 justifica
 * el peaje (terreno de 15-croppers y oasis del 50%) lo escribe el GENERADOR DE MAPA, que
 * corre una sola vez al instalar: en un mundo en marcha ese premio no existe a ningún radio.
 * Sin premio, agrandar el disco sólo le cierra la expansión a jugadores que ya se instalaron
 * ahí antes de que la regla existiera.
 *
 * El radio 5 deja a las cuatro cuentas completamente afuera —la aldea más cercana al centro
 * está a 5,39— así que no hace falta ninguna cláusula de excepción ni fecha de corte, que es
 * código que no habría que mantener. Y alcanza para lo que la zona tiene que ser acá: el
 * volcán, que llega hasta 2,83, con su caída de ceniza alrededor, y un peligro real para
 * quien decida fundar encima.
 *
 * En un mundo nuevo corresponde volver a los valores oficiales, INNER = 0 y OUTER = 22: ahí
 * el instalador siembra el terreno bueno adentro y coloca las aldeas iniciales lejos, así
 * que el peaje se paga contra un premio y nadie arrastra una aldea previa.
 *
 * Sólo se dispara al FUNDAR. Una aldea que ya existe dentro de la zona no se entera nunca:
 * ni pierde cultura, ni recibe oleadas, ni cambia en nada.
 */

require_once __DIR__.'/Accounts.php';

if(!defined('GREY_ZONE_INNER_RADIUS')) {
    // 0 = disco lleno, que es la forma oficial. El agujero existe sólo como herramienta por
    // si algún mundo necesitara dejar afuera un núcleo ya poblado.
    define('GREY_ZONE_INNER_RADIUS', 0);
}
if(!defined('GREY_ZONE_OUTER_RADIUS')) {
    // 22 en el T4 oficial. 6 acá, por lo explicado arriba.
    define('GREY_ZONE_OUTER_RADIUS', 6);
}
if(!defined('GREY_ZONE_GRANDFATHERED_BEFORE')) {
    // Las aldeas fundadas antes de este instante son anteriores a la zona: no se pintan de
    // ceniza ni se tiñen, porque su dueño se instaló ahí cuando la regla no existía.
    //
    // Hace falta porque el radio 6 alcanza a una aldea que ya estaba —la capital de chewer,
    // a 5,39— y correr el borde a 5 para esquivarla achicaría la zona por una sola casilla
    // de diferencia. Es más honesto decir "esa aldea es anterior" que deformar la geometría.
    //
    // No afecta a las oleadas: ésas se disparan sólo al FUNDAR, y fundar siempre ocurre
    // después de esta fecha. Un mundo nuevo debería poner 0 acá.
    define('GREY_ZONE_GRANDFATHERED_BEFORE', 1786992000);   // 2026-08-17 15:40
}
if(!defined('GREY_ZONE_VOLCANO_OFFSET_X')) {
    // El volcán no va centrado en (0|0) sino corrido, porque el instalador pone ahí la
    // capital natar y en (1|0) a Multihunter. Un sprite de volcán encima de una aldea la
    // dejaría invisible e inalcanzable desde el mapa, y no dibujarlo deja el cráter con un
    // agujero justo en el medio. Corrido 2 al sur, la fila de arriba del volcán cae en
    // y = -1: pegada a la capital natar, sin una casilla de pasto en el medio. No pisa
    // ninguna aldea y entra entero en el radio 6.
    define('GREY_ZONE_VOLCANO_OFFSET_X', 0);
    define('GREY_ZONE_VOLCANO_OFFSET_Y', -2);
}
if(!defined('GREY_ZONE_WAVES')) {
    // Catorce, como el oficial.
    define('GREY_ZONE_WAVES', 14);
}
if(!defined('GREY_ZONE_WAVE_DELAY')) {
    // ~24 h de aviso, escaladas por la velocidad del servidor, igual que las oleadas
    // contra la Maravilla.
    define('GREY_ZONE_WAVE_DELAY', 86400);
}
if(!defined('GREY_ZONE_TARGETS_PER_WAVE')) {
    // Cada oleada apunta a dos objetivos al azar, así que hasta 28 construcciones pueden
    // caer. Es lo que hace que la aldea tenga que ser grande para sobrevivir.
    define('GREY_ZONE_TARGETS_PER_WAVE', 2);
}

/**
 * ¿La zona gris está activa? Con el radio exterior en 0 la función queda apagada entera,
 * que es lo que conviene en un mundo donde no se la quiera.
 */
function greyZoneEnabled() {
    return GREY_ZONE_OUTER_RADIUS > 0 && GREY_ZONE_OUTER_RADIUS > GREY_ZONE_INNER_RADIUS;
}

/**
 * Distancia de una casilla al centro del mapa, con la vuelta por los bordes que usa el
 * resto del motor.
 */
function greyZoneDistanceToCentre($x, $y) {
    $span = (int)WORLD_MAX * 2 + 1;
    $dx = abs((int)$x);
    $dy = abs((int)$y);
    $dx = min($dx, $span - $dx);
    $dy = min($dy, $span - $dy);
    return sqrt($dx * $dx + $dy * $dy);
}

function greyZoneContainsCoordinates($x, $y) {
    if(!greyZoneEnabled()) {
        return false;
    }
    $distance = greyZoneDistanceToCentre($x, $y);
    return $distance >= GREY_ZONE_INNER_RADIUS && $distance <= GREY_ZONE_OUTER_RADIUS;
}

function greyZoneContainsField($wref) {
    global $database;
    $coor = $database->getCoor((int)$wref);
    if(!is_array($coor)) {
        return false;
    }
    return greyZoneContainsCoordinates((int)$coor['x'], (int)$coor['y']);
}

/**
 * Las oleadas que le caen a una aldea recién fundada en la zona gris.
 *
 * La primera es la limpiadora: tiene que barrer cualquier defensa que una aldea nueva
 * pueda llegar a tener, y en este servidor el ejército más grande visto ronda las 330
 * tropas. Las otras trece son mucho más chicas pero traen catapultas, que es lo que de
 * verdad hace daño: lo que destruyen no vuelve.
 *
 * Los índices son los de la tribu natar (u41..u50) menos 40, o sea las columnas t1..t10
 * de la tabla `attacks`.
 */
function greyZoneWaveComposition($wave) {
    $speed = defined('SPEED') ? max(1, (int)SPEED) : 1;
    if($wave <= 1) {
        // Limpiadora: infantería y caballería, sin catapultas.
        return array(
            2 => 180 * $speed,   // u42 Guerrero Espinoso
            3 => 150 * $speed,   // u43 Defensor
            5 => 120 * $speed,   // u45 Hachero Jinete
            6 =>  90 * $speed,   // u46 Caballero Natariano
            7 =>  30 * $speed    // u47 Elefante de Guerra
        );
    }
    // Las trece siguientes: escolta chica más catapultas.
    return array(
        2 => 20 * $speed,
        5 => 15 * $speed,
        8 => 12 * $speed        // u48 Ballesta (la catapulta natar)
    );
}

/**
 * Programa el asalto natar sobre una aldea recién fundada en la zona gris.
 *
 * Devuelve cuántas oleadas salieron, o 0 si la aldea no está en la zona o no hay una
 * aldea natar desde donde mandarlas.
 *
 * Las tropas se inventan igual que las oleadas contra la Maravilla y por el mismo motivo:
 * son escenario, no un ejército. No se descuentan de la capital al salir y tampoco vuelven
 * —de eso se encarga sendunitsComplete(), que ya saltea el regreso cuando la aldea de
 * origen es una guarnición estática—.
 */
function greyZoneScheduleAssault($wref, $now = null) {
    global $database;
    $wref = (int)$wref;
    $now = $now === null ? time() : (int)$now;
    if($wref <= 0 || !greyZoneEnabled() || !greyZoneContainsField($wref)) {
        return 0;
    }

    $source = $database->query_return(
        'SELECT `wref` FROM '.TB_PREFIX.'vdata WHERE `owner` = '.natarsAccountId().' '
        .'ORDER BY `capital` DESC, `natar` ASC, `wref` ASC LIMIT 1'
    );
    if(!is_array($source) || !isset($source[0]['wref'])) {
        return 0;
    }
    $from = (int)$source[0]['wref'];

    $speedFactor = defined('INCREASE_SPEED') ? max(1, (int)INCREASE_SPEED) : 1;
    $arrival = $now + (int)round(GREY_ZONE_WAVE_DELAY / $speedFactor);

    $launched = 0;
    for($wave = 1; $wave <= GREY_ZONE_WAVES; $wave++) {
        $troops = array_fill(1, 11, 0);
        foreach(greyZoneWaveComposition($wave) as $slot => $amount) {
            $troops[$slot] = (int)$amount;
        }
        // Cada oleada llega un segundo después de la anterior, así que se resuelven en
        // orden y el jugador ve catorce informes en vez de uno.
        $reference = $database->addAttack(
            $from,
            $troops[1], $troops[2], $troops[3], $troops[4], $troops[5],
            $troops[6], $troops[7], $troops[8], $troops[9], $troops[10], 0,
            3,
            // ctar1/ctar2 en 0 = sin objetivo elegido. El motor entonces reparte el
            // impacto sobre un slot ocupado cualquiera que no sea la muralla, que es
            // exactamente lo que se quiere: las oleadas natar no eligen qué romper.
            0,
            0,
            0,
            0
        );
        if($reference <= 0) {
            continue;
        }
        $database->addMovement(3, $from, $wref, $reference, 0, $arrival + $wave);
        $launched++;
    }
    return $launched;
}

/**
 * Terreno de una casilla de la zona gris. En el T4 oficial el centro tiene muchos
 * 15-croppers y muchos oasis del 50%: es el premio que compensa las catorce oleadas que
 * te caen por fundar ahí. Sin esto la zona gris es todo castigo y ningún motivo para ir.
 *
 * Lo usa el generador del mapa del instalador (install/include/wdata.php). Vive acá y no
 * allá porque es conocimiento de la zona gris: si estuviera en el generador, el checker
 * tendría que duplicarlo para poder probarlo, y una copia se desincroniza sola.
 *
 * Devuelve array(fieldtype, oasistype).
 */
function greyZoneTerrain($x = null, $y = null) {
	// El recuadro del volcán es roca y lava, no un valle: si el sorteo le mete un oasis
	// encima, el dibujo del volcán queda con agujeros. Se devuelve un valle sin oasis para
	// que la casilla exista y el mapa le dibuje el sprite del volcán por arriba.
	if($x !== null && $y !== null && greyZoneIsVolcano($x, $y)) {
		return array(2, 0);
	}
	$roll = rand(1, 1000);
	if($roll <= 150) {
		return array(6, 0);              // 15-cropper
	}
	if($roll <= 250) {
		return array(1, 0);              // 9-cropper
	}
	if($roll <= 700) {
		// Valles normales, con el mismo reparto que el resto del mapa.
		$valleys = array(2, 3, 4, 5, 7, 8, 9, 10, 11, 12);
		return array($valleys[array_rand($valleys)], 0);
	}
	// Oasis, y sólo de los del 50%: madera, barro, hierro y cereal.
	$rich = array(2, 5, 8, 12);
	return array(0, $rich[array_rand($rich)]);
}

// --- El volcán --------------------------------------------------------------------------

/**
 * El volcán del centro, tal como lo dibuja el T4 oficial: 5 casillas de ancho por 4 de
 * alto alrededor de (0|0), del que sale la ceniza que cubre la zona. El arte ya vive en el
 * repo (gpack/travian_Travian_4.0_41) y nunca se había conectado.
 *
 * La clave es el desplazamiento respecto del centro, "$dx$dy", que es como se llaman los
 * sprites. La fila de arriba es más angosta: no existen las esquinas (±2|+1).
 *
 * Valores: desplazamiento => posición en la spritesheet.
 */
function greyZoneVolcanoSprites() {
    return array(
        '-11' => array(480, 360),  '01' => array(0, 420),   '11' => array(60, 420),
        '-20' => array(120, 420),  '-10' => array(180, 420), '00' => array(240, 420),
        '10' => array(300, 420),   '20' => array(360, 420),
        '-2-1' => array(420, 420), '-1-1' => array(480, 420), '0-1' => array(0, 480),
        '1-1' => array(60, 480),   '2-1' => array(120, 480),
        '-2-2' => array(180, 480), '-1-2' => array(240, 480), '0-2' => array(300, 480),
        '1-2' => array(360, 480),  '2-2' => array(420, 480)
    );
}

/**
 * La clase CSS del volcán para una casilla, o '' si no le toca ninguna.
 */
function greyZoneVolcanoClass($x, $y) {
    if(!greyZoneEnabled()) {
        return '';
    }
    // Las piezas se llaman por su desplazamiento respecto del centro del volcán, no del
    // centro del mapa: hay que descontar el corrimiento antes de buscarlas.
    $key = ((int)$x - GREY_ZONE_VOLCANO_OFFSET_X).''.((int)$y - GREY_ZONE_VOLCANO_OFFSET_Y);
    $sprites = greyZoneVolcanoSprites();
    return isset($sprites[$key]) ? 'ash-vulcano'.$key : '';
}

/**
 * ¿A esta aldea le aplica la zona gris? Las anteriores a la zona quedan exentas: se ven
 * como siempre, sin ceniza ni tinte.
 */
function greyZoneAffectsVillage($village) {
    if(!is_array($village) || !isset($village['wref']) || $village['wref'] === null) {
        return true;
    }
    $created = isset($village['created']) ? (int)$village['created'] : 0;
    return $created === 0 || $created >= GREY_ZONE_GRANDFATHERED_BEFORE;
}

/**
 * ¿La casilla es parte del volcán?
 */
function greyZoneIsVolcano($x, $y) {
    return greyZoneVolcanoClass($x, $y) !== '';
}

/**
 * Las reglas CSS del volcán, para incrustar en la plantilla del mapa. Se generan desde el
 * mismo mapa de arriba para que las coordenadas de los sprites vivan en un solo lugar.
 */
function greyZoneVolcanoCss() {
    $sheet = '/gpack/travian_Travian_4.0_41/img/map/lowRes/tiles.png';
    $rules = array();
    foreach(greyZoneVolcanoSprites() as $key => $pos) {
        $rules[] = 'div.ash-vulcano'.$key.'{background-image:url(\''.$sheet.'\');'
            .'background-position:-'.$pos[0].'px -'.$pos[1].'px;background-repeat:no-repeat;}';
    }
    return implode("\n", $rules);
}

/**
 * Deja el recuadro del volcán como escenario puro en un mundo YA generado: sin oasis
 * encima, y marcado como ocupado para que nadie pueda fundar ni anexar ahí.
 *
 * Hace falta porque el terreno se escribe una sola vez, al instalar. En un mundo nuevo el
 * generador ya evita poner oasis sobre el volcán (ver greyZoneTerrain), pero en uno en
 * marcha los que salieron sorteados siguen ahí y le hacen agujeros al dibujo.
 *
 * Sólo toca casillas sin aldea y sin oasis ANEXADO: un oasis que alguien ya conquistó le
 * está dando producción a su dueño y no se le saca por una cuestión de estética.
 *
 * Devuelve el detalle de lo que hizo.
 */
function greyZoneReserveVolcano($apply = false) {
    global $database;
    $report = array('reservadas' => 0, 'oasis_limpiados' => 0, 'liberadas' => 0, 'saltadas' => array());

    // Si el volcán se movió, las casillas que reservó en su posición anterior quedarían
    // ocupadas para siempre: nadie podría fundar ahí y nada lo explicaría en el mapa. Se
    // sueltan las que están reservadas (ocupadas, sin aldea y sin oasis) dentro de la zona
    // pero fuera del recuadro actual.
    $stale = $database->query_return(
        'SELECT w.`id`, w.`x`, w.`y` FROM '.TB_PREFIX.'wdata w '
        .'LEFT JOIN '.TB_PREFIX.'vdata v ON v.`wref` = w.`id` '
        .'LEFT JOIN '.TB_PREFIX.'odata o ON o.`wref` = w.`id` '
        .'WHERE w.`occupied` = 1 AND v.`wref` IS NULL AND o.`wref` IS NULL'
    );
    foreach(is_array($stale) ? $stale : array() as $tile) {
        if(greyZoneIsVolcano((int)$tile['x'], (int)$tile['y'])) {
            continue;
        }
        if($apply) {
            $database->query('UPDATE '.TB_PREFIX.'wdata SET `occupied` = 0 WHERE `id` = '.(int)$tile['id']);
        }
        $report['liberadas']++;
    }
    foreach(array_keys(greyZoneVolcanoSprites()) as $key) {
        preg_match('/^(-?\d)(-?\d)$/', $key, $parts);
        $x = (int)$parts[1] + GREY_ZONE_VOLCANO_OFFSET_X;
        $y = (int)$parts[2] + GREY_ZONE_VOLCANO_OFFSET_Y;
        $wref = (int)$database->getVilWref($x, $y);
        if($wref <= 0) {
            continue;
        }
        $tile = $database->getMInfo($wref);
        if(!is_array($tile)) {
            continue;
        }
        if(isset($tile['wref']) && $tile['wref'] !== null) {
            $report['saltadas'][] = "($x|$y) tiene una aldea";
            continue;
        }
        $oasis = (int)$tile['oasistype'];
        if($oasis > 0) {
            $annexed = $database->query_return(
                'SELECT `conqured` FROM '.TB_PREFIX.'odata WHERE `wref` = '.$wref
            );
            if(is_array($annexed) && isset($annexed[0]['conqured']) && (int)$annexed[0]['conqured'] > 0) {
                $report['saltadas'][] = "($x|$y) es un oasis anexado";
                continue;
            }
            if($apply) {
                $database->query('DELETE FROM '.TB_PREFIX.'odata WHERE `wref` = '.$wref);
                $database->query('UPDATE '.TB_PREFIX.'wdata SET `oasistype` = 0, `fieldtype` = 2, '
                    ."`image` = 't0' WHERE `id` = ".$wref);
            }
            $report['oasis_limpiados']++;
        }
        if($apply) {
            // Ocupada sin aldea: el motor ya sabe dibujar el terreno en ese caso, y así la
            // casilla queda fuera del alcance de fundadores y de las aldeas natar vivas.
            $database->query('UPDATE '.TB_PREFIX.'wdata SET `occupied` = 1 WHERE `id` = '.$wref);
        }
        $report['reservadas']++;
    }
    return $report;
}
