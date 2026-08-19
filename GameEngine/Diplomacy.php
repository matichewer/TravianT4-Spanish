<?php
/**
 * Relaciones entre alianzas, en un solo lugar.
 *
 * `s1_diplomacy` guarda un acuerdo por fila con `alli1`, `alli2`, `type` y `accepted`. El
 * acuerdo es simetrico —da igual quien lo propuso— pero la tabla no lo es: la alianza propia
 * puede estar en cualquiera de las dos columnas, y olvidarse de mirar las dos es la forma
 * facil de que media diplomacia no aparezca. Por eso nadie deberia consultar esta tabla a
 * mano.
 *
 * Los tres tipos salen de la pantalla de diplomacia (`Templates/Alliance/chgdiplo.tpl`) y de
 * `Templates/Alliance/medal.php`, que ya los rotula:
 *   1 = aliado (confederacion)   2 = pacto de no agresion   3 = en guerra
 */

if(!defined('DIPLOMACY_ALLY')) {
    define('DIPLOMACY_ALLY', 1);
    define('DIPLOMACY_NAP', 2);
    define('DIPLOMACY_WAR', 3);
}

if(!function_exists('allianceDiplomacy')) {

/**
 * Las alianzas relacionadas con una, agrupadas por tipo, mirando las dos columnas.
 *
 * Devuelve `array(1 => array(ids), 2 => array(ids), 3 => array(ids))`, siempre con las tres
 * claves para que quien llame no tenga que comprobar.
 */
function allianceDiplomacy($allianceId) {
    global $database;
    $allianceId = (int)$allianceId;
    $relations = array(DIPLOMACY_ALLY => array(), DIPLOMACY_NAP => array(), DIPLOMACY_WAR => array());
    if($allianceId <= 0 || !isset($database) || !is_object($database)
        || !method_exists($database, 'query_return')) {
        return $relations;
    }
    // Una sola consulta para los tres tipos y las dos columnas: esto corre por cada vista de
    // mapa, y el mapa ya hace un getUserField por casilla.
    $rows = $database->query_return(
        "SELECT `alli1`, `alli2`, `type` FROM ".TB_PREFIX."diplomacy "
        ."WHERE `accepted` = 1 AND (`alli1` = ".$allianceId." OR `alli2` = ".$allianceId.")"
    );
    if(!is_array($rows)) {
        return $relations;
    }
    foreach($rows as $row) {
        $type = (int)$row['type'];
        if(!isset($relations[$type])) {
            continue;
        }
        $other = ((int)$row['alli1'] === $allianceId) ? (int)$row['alli2'] : (int)$row['alli1'];
        if($other > 0 && $other !== $allianceId && !in_array($other, $relations[$type], true)) {
            $relations[$type][] = $other;
        }
    }
    return $relations;
}

/**
 * Las alianzas con un pacto de alianza (confederacion). Van con el marco verde.
 */
function alliedAlliances($allianceId) {
    $relations = allianceDiplomacy($allianceId);
    return $relations[DIPLOMACY_ALLY];
}

/**
 * Las alianzas con un pacto de no agresion. Van con el marco cian.
 *
 * Se pintan distinto de los aliados a proposito: el juego distingue los dos pactos en la
 * pantalla de diplomacia y en el perfil de la alianza, asi que meterlos en el mismo color
 * perdia informacion que el jugador ya tiene en otro lado.
 */
function napAlliances($allianceId) {
    $relations = allianceDiplomacy($allianceId);
    return $relations[DIPLOMACY_NAP];
}

/**
 * Las alianzas con las que la propia esta en guerra. Van con el marco rojo.
 */
function hostileAlliances($allianceId) {
    $relations = allianceDiplomacy($allianceId);
    return $relations[DIPLOMACY_WAR];
}

}

if(!function_exists('mapDiplomacyCss')) {

/**
 * Las reglas de los dos sprites que el gpack no trae: guerra (rojo) y NAP (cian).
 *
 * Van INLINE en la pagina y no en `compact.css` a proposito: esa hoja la cachea Cloudflare
 * cuatro horas, asi que tocarla obliga a versionar la URL y a coreografiar el despliegue.
 * El arte si es nuevo (`tools/make_map_sprites.php`), pero como son archivos que antes no
 * existian nadie tiene nada cacheado bajo esas URLs.
 *
 * Las otras tres relaciones ya tienen su regla en el gpack: propia (b?0), aliado (b?1, en
 * `ally/nap/`), alianza propia (b?3, en `ally/`) y cualquier otro (b?4).
 */
function mapDiplomacyCss() {
    $rules = array();
    foreach(array(2, 5) as $relation) {
        foreach(array(0, 1, 2, 3) as $size) {
            foreach(array(1, 2, 3) as $tribe) {
                $rules[] = 'div.b'.$size.$relation.'-'.$tribe
                    .'{background-image:url(\'/gpack/travian_Travian_4.0_41/img/map/d0'
                    .$relation.'-'.$tribe.'.gif\');}';
            }
        }
    }
    return implode("\n", $rules);
}

}
