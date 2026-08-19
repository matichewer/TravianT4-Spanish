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
 * Las alianzas que el mapa tiene que pintar como amigas.
 *
 * Aliados y NAP comparten sprite —el del borde verde— porque el gpack trae un solo juego de
 * arte "amistoso". Distinguirlos necesitaria arte que no existe.
 */
function friendlyAlliances($allianceId) {
    $relations = allianceDiplomacy($allianceId);
    return array_values(array_unique(array_merge($relations[DIPLOMACY_ALLY], $relations[DIPLOMACY_NAP])));
}

/**
 * Las alianzas con las que la propia esta en guerra.
 */
function hostileAlliances($allianceId) {
    $relations = allianceDiplomacy($allianceId);
    return $relations[DIPLOMACY_WAR];
}

}

if(!function_exists('mapDiplomacyCss')) {

/**
 * Las reglas del sprite de aldea enemiga.
 *
 * Van INLINE en la pagina y no en `compact.css` a proposito: esa hoja la cachea Cloudflare
 * cuatro horas, asi que tocarla obliga a versionar la URL y a coreografiar el despliegue.
 * El arte si es nuevo (`tools/make_enemy_sprites.php`), pero como son archivos que antes no
 * existian nadie tiene nada cacheado bajo esas URLs.
 *
 * Las otras cuatro relaciones ya tienen su regla en el gpack: propia (b?0), aliado/NAP (b?1,
 * en `ally/nap/`), alianza propia (b?3, en `ally/`) y cualquier otro (b?4).
 */
function mapDiplomacyCss() {
    $rules = array();
    foreach(array(0, 1, 2, 3) as $size) {
        foreach(array(1, 2, 3) as $tribe) {
            $rules[] = 'div.b'.$size.'2-'.$tribe
                .'{background-image:url(\'/gpack/travian_Travian_4.0_41/img/map/d02-'.$tribe.'.gif\');}';
        }
    }
    return implode("\n", $rules);
}

}
