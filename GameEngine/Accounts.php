<?php
/**
 * La frontera entre las cuentas del sistema y las de los jugadores.
 *
 * Por qué existe. El motor no tenía nombre para "esto no es un jugador", así que la idea
 * estaba escrita de siete maneras distintas repartidas por el motor, la capa de base de
 * datos y las páginas públicas —`owner <= 4`, `owner > 4`, `username = 'Natars'`,
 * `tribe != 0 AND tribe != 4 AND tribe != 5`— y ninguna sabía de la existencia de las
 * otras. No es cosmético: la hambruna que vaciaba sola las Aldeas de la Maravilla era
 * exactamente eso, el motor aplicándole reglas de jugador al escenario. Y una de las
 * grafías ya había derivado: el contador de conectados filtraba por tribu y por eso
 * seguía contando a `Support`, que es tribu 1.
 *
 * Qué NO es esto. Hay tres cosas que se le parecen y son otra cosa; no las metas acá:
 *   - `vdata.natar`, que marca una Aldea de la Maravilla, no una cuenta;
 *   - los repliegues a tribu 4 de `unknown_defender.tpl` y `defenders.tpl`, que hablan de
 *     oasis y de animales enjaulados;
 *   - los `$tid <= 4` de `Building.php` y `Automation.php`, que comparan tipos de campo
 *     de recurso y no ids de usuario.
 *
 * Los cuatro ids los fija el instalador: `Support` y `Nature` vienen sembrados en
 * `install/data/sql.sql`, `Natars` y `Multihunter` los inserta
 * `install/include/multihunter.php`. El registro de jugadores siempre escribe una tribu
 * real, así que en 1..4 no puede quedar nadie más.
 */

if(!defined('UID_SUPPORT')) {
    define('UID_SUPPORT', 1);
    define('UID_NATARS', 2);
    define('UID_NATURE', 3);
    define('UID_MULTIHUNTER', 4);
}

/**
 * Las cuentas del sistema, por nombre. El orden es el de sus ids.
 */
function systemAccounts() {
    return array(
        'Support' => UID_SUPPORT,
        'Natars' => UID_NATARS,
        'Nature' => UID_NATURE,
        'Multihunter' => UID_MULTIHUNTER
    );
}

/**
 * El id más alto reservado para el sistema. Todo lo que esté por encima es un jugador.
 */
function lastSystemAccountId() {
    return max(systemAccounts());
}

function isSystemAccount($uid) {
    $uid = (int)$uid;
    return $uid > 0 && $uid <= lastSystemAccountId();
}

function isPlayerAccount($uid) {
    return (int)$uid > lastSystemAccountId();
}

/**
 * El mismo criterio para una consulta. `$column` tiene que ser un nombre de columna del
 * código, nunca algo que venga del usuario.
 *
 * Existe porque escribir `owner > 4` a mano en el SQL es justo la costumbre que este
 * módulo viene a sacar: si la frontera sólo viviera como función de PHP, la capa de base
 * de datos seguiría teniendo su propia copia.
 */
function systemAccountSql($column) {
    return '`'.$column.'` > 0 AND `'.$column.'` <= '.lastSystemAccountId();
}

function playerAccountSql($column) {
    return '`'.$column.'` > '.lastSystemAccountId();
}

/**
 * El id real de una cuenta del sistema en este mundo.
 *
 * Prefiere el nombre y cae al id del instalador. La búsqueda por nombre no está de más:
 * las oleadas contra la Maravilla ya resolvían así la aldea natar, porque hubo mundos
 * viejos cuyos ids no coincidían con los de hoy. Se resuelve una vez por request.
 */
function systemAccountId($username) {
    global $database;
    static $resolved = array();

    $accounts = systemAccounts();
    if(!isset($accounts[$username])) {
        return 0;
    }
    if(isset($resolved[$username])) {
        return $resolved[$username];
    }

    $id = 0;
    if(isset($database) && is_object($database)) {
        $id = (int)$database->getUserField($username, 'id', 1);
    }
    if($id <= 0) {
        $id = $accounts[$username];
    }
    $resolved[$username] = $id;
    return $id;
}

/**
 * La cuenta de los natars: dueña de la capital natar y de las 13 Aldeas de la Maravilla.
 */
function natarsAccountId() {
    return systemAccountId('Natars');
}
