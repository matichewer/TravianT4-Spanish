<?php
/*
#################################################################################
##  Etiquetas de las medallas semanales                                        ##
##  -------------------------------------------------------------------------  ##
##  Las medallas se guardan en s1_medal / s1_allimedal con una `categorie`      ##
##  numérica y, en las medallas bonus, una palabra en inglés dentro de          ##
##  `points` ('Three'/'Five'/'Ten' para las rachas, ''/'twice '/'three times '  ##
##  para el bonus de ataque+defensa) escrita por Automation::WeeklyMedals().    ##
##  Este archivo es el único lugar donde esos códigos se traducen a texto.      ##
#################################################################################
*/

if(!function_exists('medalPodiumSize')) {

/**
 * Cuántos puestos premia el ranking semanal (medal_top / medal_ally_top).
 */
function medalPodiumSize($allianceMedal = false) {
    if($allianceMedal) {
        return defined('MEDAL_ALLY_TOP') ? max(1, (int)MEDAL_ALLY_TOP) : 10;
    }
    return defined('MEDAL_TOP') ? max(1, (int)MEDAL_TOP) : 10;
}

/**
 * Cuántas veces se repitió la medalla, leyendo la palabra guardada en `points`.
 * Devuelve 0 cuando el valor no se reconoce.
 */
function medalStreakTimes($points) {
    $words = array(
        'three'       => 3,
        'five'        => 5,
        'ten'         => 10,
        'twice'       => 2,
        'three times' => 3
    );
    $key = strtolower(trim((string)$points));
    if($key === '') {
        return 1;
    }
    if(isset($words[$key])) {
        return $words[$key];
    }
    return is_numeric($key) ? (int)$key : 0;
}

/**
 * Las medallas bonus no tienen puesto ni puntos: `plaats` es 0 y `points`
 * guarda la palabra de la racha.
 */
function medalIsBonus($categorie) {
    return in_array((int)$categorie, array(5, 6, 7, 8, 9, 11, 12, 13, 14, 15, 16), true);
}

/**
 * Texto de una medalla. $points solo se usa en las medallas bonus.
 */
function medalCategoryLabel($categorie, $points = '', $allianceMedal = false) {
    $categorie = (int)$categorie;
    $top = medalPodiumSize($allianceMedal);

    // Rankings semanales: el puesto se muestra en su propia columna, acá solo
    // interesa qué ranking era y cuántos puestos premiaba.
    $weekly = array(
        1  => array('Mejor atacante de la semana',    'Top %d atacantes de la semana'),
        2  => array('Mejor defensor de la semana',    'Top %d defensores de la semana'),
        3  => array('Mayor crecimiento de la semana', 'Top %d en crecimiento de la semana'),
        4  => array('Mejor saqueador de la semana',   'Top %d saqueadores de la semana'),
        10 => array('Mayor crecimiento de la semana', 'Top %d en crecimiento de la semana')
    );
    if(isset($weekly[$categorie])) {
        return $top == 1 ? $weekly[$categorie][0] : sprintf($weekly[$categorie][1], $top);
    }

    // Bonus por entrar en el ranking de ataque y en el de defensa la misma semana.
    if($categorie == 5) {
        $times = medalStreakTimes($points);
        $label = $top == 1
            ? 'Nº 1 en ataque y defensa la misma semana'
            : 'Top '.$top.' en ataque y defensa la misma semana';
        return $times > 1 ? $label.' ('.$times.'.ª vez)' : $label;
    }

    // Rachas. La serie corta cuenta semanas en el top 3 y la larga en el top 10,
    // pero ninguna puede exigir más puestos de los que el servidor premia.
    $streaks = array(
        6  => array('atacantes',   3),
        7  => array('defensores',  3),
        8  => array('crecimiento', 3),
        9  => array('saqueadores', 3),
        11 => array('crecimiento', 3),
        12 => array('atacantes',   10),
        13 => array('defensores',  10),
        14 => array('crecimiento', 10),
        15 => array('saqueadores', 10),
        16 => array('crecimiento', 10)
    );
    if(isset($streaks[$categorie])) {
        list($ranking, $series) = $streaks[$categorie];
        $podium = min($series, $top);
        $times = medalStreakTimes($points);
        if($podium == 1) {
            $where = $ranking == 'crecimiento'
                ? 'con el mayor crecimiento'
                : 'como nº 1 de '.$ranking;
            return $times > 0
                ? $times.'.ª semana '.$where
                : 'Racha de semanas '.$where;
        }
        $where = $ranking == 'crecimiento'
            ? 'en el top '.$podium.' de crecimiento'
            : 'en el top '.$podium.' de '.$ranking;
        return $times > 0 ? $times.'.ª vez '.$where : 'Racha '.$where;
    }

    return 'Medalla';
}

}
