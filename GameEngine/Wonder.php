<?php
/**
 * La Maravilla del Mundo y los planos de construcción que la habilitan.
 *
 * Hasta ahora la Maravilla no pedía nada: `Building::meetRequirement()` devolvía `false`
 * para el tipo 40 ("not implemented"), pero eso sólo bloquea CONSTRUIR desde cero, y la
 * Maravilla nunca se construye desde cero — ya viene levantada en las aldeas natar. Como
 * `canBuild()` no pasa por `meetRequirement()` cuando el campo ya tiene un edificio, quien
 * conquistaba una Aldea de la Maravilla la subía del 1 al 100 sin ningún requisito. El
 * final de partida entero era gratis.
 *
 * La regla oficial (support.travian.com, "Building a World Wonder"):
 *
 *   - **Niveles 1 a 49**: hace falta **un** plano de construcción activo *en tu alianza*.
 *     No tiene que ser tuyo: alcanza con que lo tenga cualquier miembro.
 *   - **Niveles 50 a 100**: hacen falta **dos**, y ahí sí se especifica de quién son —
 *     "one held by the WW owner and one by a co-ally". O sea: uno tuyo y otro de OTRO
 *     jugador de la alianza.
 *
 * Y dos detalles oficiales más que estaban sueltos:
 *
 *   - **En la aldea de la Maravilla no se puede construir un Tesoro.** Como el plano vive
 *     en un Tesoro, eso obliga a guardarlo en otra aldea, que es justamente lo que lo
 *     vuelve robable: si pudiera estar en la propia aldea de la Maravilla, defender una
 *     cosa defendería las dos.
 *   - **Ni el fin de obra con oro, ni el constructor maestro, ni el mercader NPC funcionan
 *     en la aldea de la Maravilla.** Los dos primeros ya estaban; el tercero no.
 *
 * Un jugador **sin alianza** puede llegar al 49 con su propio plano —es un caso que el
 * oficial no contempla porque allá nadie llega ahí solo, pero prohibirlo sería peor: en un
 * mundo chico dejaría la Maravilla inalcanzable— y no puede pasar de ahí, porque el segundo
 * plano tiene que estar en manos de otro jugador de su alianza.
 */

require_once __DIR__.'/Artefact.php';
require_once __DIR__.'/Accounts.php';

/** El tipo de edificio de la Maravilla y el campo donde vive, fuera de la franja 1..40. */
if(!defined('WONDER_BUILDING_TYPE')) {
    define('WONDER_BUILDING_TYPE', 40);
    define('WONDER_FIELD', 99);
}

/**
 * Cuántos planos hace falta tener para llegar a `$level`, y de quién.
 *
 * Tres números y hacen falta los tres, porque la regla oficial del 50 en adelante no es
 * "dos planos en la alianza" sino "uno del dueño de la Maravilla y otro de un co-aliado":
 *
 *   `own`      — planos que tiene que tener el dueño de la Maravilla.
 *   `allies`   — planos que tienen que estar en manos de OTROS miembros de la alianza.
 *   `alliance` — el total, contando el propio. Es lo que se muestra en pantalla.
 *
 * Sin `allies` la trampa evidente queda abierta: juntar los dos planos en la misma cuenta
 * satisface "dos en la alianza" y llega al 100 sin depender de nadie, que es justo lo que
 * la redacción oficial cierra.
 */
function wonderPlanRequirement($level) {
    $level = (int)$level;
    if($level <= WONDER_PLAN_SOLO_MAX_LEVEL) {
        // Hasta el 49 alcanza con que el plano esté en la alianza, sea de quien sea — el
        // propio incluido, así que no se exige ni uno propio ni uno ajeno en particular.
        return array('own' => 0, 'allies' => 0, 'alliance' => 1);
    }
    return array('own' => 1, 'allies' => 1, 'alliance' => 2);
}

/**
 * Los planos de construcción activos que hay, contados por dueño.
 *
 * "Activo" acá quiere decir que ya pasó el retardo de captura, no que esté en el podio de
 * tres: un plano no compite por esos huecos (ver `artefactActiveRows()`).
 *
 * Devuelve `array(uid => cantidad)`.
 */
function wonderPlansByOwner($database) {
    $plans = array();
    if(!is_object($database) || !method_exists($database, 'query_return')) {
        return $plans;
    }
    $rows = $database->query_return(
        'SELECT `owner`, `conquered` FROM '.TB_PREFIX.'artefacts WHERE `type` = '.ARTEFACT_PLAN
    );
    if(!is_array($rows)) {
        return $plans;
    }
    foreach($rows as $row) {
        if(!artefactIsMature($row)) {
            continue;
        }
        $owner = (int)$row['owner'];
        if(!isPlayerAccount($owner)) {
            // Un plano que sigue en manos de los natars no habilita nada.
            continue;
        }
        if(!isset($plans[$owner])) {
            $plans[$owner] = 0;
        }
        $plans[$owner]++;
    }
    return $plans;
}

/**
 * ¿El dueño de esta Maravilla puede subirla al nivel `$level`?
 *
 * Devuelve un informe en vez de un booleano porque la pantalla tiene que poder decir QUÉ
 * falta: un "no se puede" sin motivo es exactamente lo que hacía que un jugador con la
 * Academia al día no entendiera por qué no le aparecía el Ayuntamiento.
 */
function wonderPlanStatus($database, $ownerId, $allianceId, $level) {
    $ownerId = (int)$ownerId;
    $allianceId = (int)$allianceId;
    $need = wonderPlanRequirement($level);
    $plans = wonderPlansByOwner($database);

    $own = isset($plans[$ownerId]) ? $plans[$ownerId] : 0;
    $allies = 0;
    if($allianceId > 0 && is_object($database) && method_exists($database, 'query_return')) {
        $members = $database->query_return(
            'SELECT `id` FROM '.TB_PREFIX.'users WHERE `alliance` = '.$allianceId
        );
        if(is_array($members)) {
            foreach($members as $member) {
                $memberId = (int)$member['id'];
                if($memberId === $ownerId) {
                    continue;
                }
                $allies += isset($plans[$memberId]) ? $plans[$memberId] : 0;
            }
        }
    }

    $total = $own + $allies;
    $missingOwn = max(0, $need['own'] - $own);
    $missingAllies = max(0, $need['allies'] - $allies);
    $missingTotal = max(0, $need['alliance'] - $total);
    // Lo que la pantalla llama "falta en la alianza" es el peor de los dos huecos ajenos:
    // el del total y el de los co-aliados. Acumular planos propios tapa el primero pero
    // nunca el segundo, que es exactamente la diferencia entre la regla oficial y "dos
    // planos donde sea".
    $missingAlliance = max($missingAllies, $missingTotal);

    return array(
        'level' => (int)$level,
        'needed_own' => $need['own'],
        'needed_allies' => $need['allies'],
        'needed_alliance' => $need['alliance'],
        'own' => $own,
        'allies' => $allies,
        'total' => $total,
        'allowed' => $missingOwn === 0 && $missingAlliance === 0,
        'missing_own' => $missingOwn,
        'missing_alliance' => $missingAlliance
    );
}

/** Lo que la pantalla le dice al jugador cuando no puede seguir subiendo la Maravilla. */
function wonderPlanMessage($status) {
    if(!is_array($status) || !empty($status['allowed'])) {
        return '';
    }
    if(!empty($status['missing_own'])) {
        return 'Para pasar del nivel '.WONDER_PLAN_SOLO_MAX_LEVEL.' necesitás tener vos un plano'
            .' de construcción activo, además del de otro miembro de tu alianza.';
    }
    if((int)$status['needed_alliance'] > 1) {
        return 'Para pasar del nivel '.WONDER_PLAN_SOLO_MAX_LEVEL.' hacen falta dos planos de'
            .' construcción activos: el tuyo y el de otro jugador de tu alianza.';
    }
    return 'Necesitás un plano de construcción activo en tu alianza para levantar la Maravilla'
        .' del Mundo.';
}

/** ¿Esta aldea es la de una Maravilla del Mundo? */
function wonderVillage($fields) {
    return is_array($fields)
        && isset($fields['f'.WONDER_FIELD.'t'])
        && (int)$fields['f'.WONDER_FIELD.'t'] === WONDER_BUILDING_TYPE;
}
