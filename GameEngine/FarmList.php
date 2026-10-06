<?php
/**
 * El envío de la Lista de granjas.
 *
 * Vivía entero en Templates/a2b/startRaid.tpl y por cada objetivo de la lista hacía, estuviera
 * marcado o no, tres consultas; y por cada saqueo que salía, unas veinte más:
 *
 *   - insertaba una fila en `a2b` y la volvía a leer para "normalizar" cantidades que ya
 *     venían de la base, y nunca la borraba. `a2b` es la tabla de la pantalla de confirmación
 *     del envío normal; la lista no confirma nada, así que esas filas eran basura. Además la
 *     releía por `time()`: si el reloj cambiaba de segundo entre las dos llamadas no la
 *     encontraba y el saqueo salía con los datos vacíos;
 *   - descontaba las tropas con ONCE `UPDATE`, uno por tipo de unidad aunque fueran cero,
 *     después de haber creado el ataque y sin comprobar nada: dos envíos a la vez pasaban
 *     los dos el chequeo, y el segundo descuento desbordaba la columna UNSIGNED, fallaba en
 *     silencio y dejaba esas tropas duplicadas;
 *   - la décima unidad (colonos) de teutones y galos se descontaba de `u110` y `u210`,
 *     columnas que no existen: el colono salía a saquear y seguía en casa.
 *
 * Ahora descuenta con `deductUnitsIfAvailable()`, el mismo `UPDATE` único y condicionado del
 * envío normal (`Units::sendTroops`): si las tropas no están, no sale nada, y primero se
 * descuenta y después se crea el ataque. Si el ataque no se puede crear, se devuelven.
 */

/**
 * Columna de `units` de la unidad que ocupa la posición 1..10 de una tribu.
 */
function farmListUnitColumn($tribe, $position) {
    return 'u'.(((int)$tribe - 1) * 10 + (int)$position);
}

/**
 * Manda los saqueos marcados de una lista.
 *
 * $post es el formulario tal cual: un objetivo está marcado si trae `slot<id> = on`.
 * Devuelve false si la lista no es del jugador, o cuántos saqueos salieron y cuántos
 * objetivos marcados se saltearon (sin tropas suficientes, o de una cuenta que no se ataca).
 */
function farmListSendRaids($database, $generator, $uid, $tribe, $lid, array $post) {
    $uid = (int)$uid;
    $tribe = (int)$tribe;
    $lid = (int)$lid;
    // La lista debe pertenecer al jugador logueado: sin esto cualquiera podía disparar un
    // saqueo con la aldea y las tropas de otro con sólo adivinar su `lid`.
    $list = $database->getFLData($lid);
    if(!is_array($list) || (int)$list['owner'] !== $uid) {
        return false;
    }
    $origin = (int)$list['wref'];
    $result = array('sent' => 0, 'skipped' => 0);

    $marked = array();
    foreach($database->query_return("SELECT * FROM ".TB_PREFIX."raidlist WHERE lid = ".$lid." order by id asc") as $row) {
        $slot = 'slot'.$row['id'];
        if(isset($post[$slot]) && $post[$slot] == 'on') {
            $marked[] = $row;
        }
    }
    if(empty($marked)) {
        return $result;
    }

    // Lo que no depende del objetivo se resuelve una vez: de dónde salen y las botas de
    // los titanes de la aldea que lanza el asalto. Es la misma función que usa el punto de
    // reunión, así que la lista no puede tener su propia velocidad.
    $from = $database->getCoor($origin);
    $speedArtefact = artefactTroopSpeedFactor($database, $list['owner'], $origin);

    foreach($marked as $row) {
        $target = (int)$row['towref'];
        // La tribu se toma de la sesión y no del formulario: la aldea de la lista ya se
        // validó como propia, así que su tribu es la del jugador.
        $villageOwner = $database->getVillageField($target, 'owner');
        $userAccess = $database->getUserField($villageOwner, 'access', 0);
        if(!($userAccess != '0' && $userAccess != '8' && $userAccess != '9')) {
            $result['skipped']++;
            continue;
        }

        $troops = array();
        $deductions = array();
        $speeds = array();
        for($position = 1; $position <= 10; $position++) {
            $amount = max(0, (int)$row['t'.$position]);
            $troops[$position] = $amount;
            if($amount > 0) {
                $deductions[farmListUnitColumn($tribe, $position)] = $amount;
                $speeds[] = $GLOBALS['u'.(($tribe - 1) * 10 + $position)]['speed'];
            }
        }
        // Comprueba y descuenta en la misma sentencia. Falla también con el objetivo vacío.
        if(!$database->deductUnitsIfAvailable($origin, $deductions)) {
            $result['skipped']++;
            continue;
        }

        $to = $database->getCoor($target);
        $time = $generator->procDistanceTime(
            array('x' => $from['x'], 'y' => $from['y']),
            array('x' => $to['x'], 'y' => $to['y']),
            min($speeds),
            1,
            0,
            0,
            $speedArtefact
        );
        $ctar1 = $troops[7] > 0 ? 99 : 0;
        $sentAt = time();
        $reference = $database->addAttack($origin, $troops[1], $troops[2], $troops[3], $troops[4], $troops[5],
            $troops[6], $troops[7], $troops[8], $troops[9], $troops[10], 0, 4, $ctar1, 0, 0);
        $movementAdded = $reference > 0
            && $database->addMovement(3, $origin, $target, $reference, $sentAt, ($time + $sentAt));
        if(!$movementAdded) {
            if($reference > 0) {
                $database->removeAttack($reference);
            }
            $database->refundUnits($origin, $deductions);
            $result['skipped']++;
            continue;
        }
        $result['sent']++;
    }
    return $result;
}
