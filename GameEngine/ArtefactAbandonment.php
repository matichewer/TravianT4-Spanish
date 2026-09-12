<?php
/** Abandono voluntario: el mismo artefacto vuelve al mapa con defensa natar. */
require_once __DIR__.'/ArtefactRelease.php';

/** La confirmación es del servidor: no depende de JavaScript. */
function artefactAbandonRequest($database, $session, $post, $method, $accrue = null) {
    if($method !== 'POST' || !is_array($post)
        || !isset($post['action'], $post['confirm'], $post['c'], $post['artefact_id'], $post['vref'], $post['conquered'])
        || $post['action'] !== 'abandonArtefact' || $post['confirm'] !== '1'
        || !is_string($post['c']) || empty($session->mchecker)
        || !hash_equals((string)$session->mchecker, $post['c'])
        || !is_scalar($post['artefact_id']) || !ctype_digit((string)$post['artefact_id'])
        || !is_scalar($post['vref']) || !ctype_digit((string)$post['vref'])
        || !is_scalar($post['conquered']) || !ctype_digit((string)$post['conquered'])
        || !empty($session->is_sitter) || !isPlayerAccount($session->uid)) {
        return array('status' => 'invalid_request');
    }
    return artefactAbandon($database, (int)$session->uid, (int)$post['artefact_id'],
        array('vref' => (int)$post['vref'], 'conquered' => (int)$post['conquered']), $accrue);
}

/** Una sola aldea, con las mismas reglas de defensa y ubicación que la liberación. */
function artefactAbandonVillagePlan($artefact, $reference) {
    $config = artefactReleaseDefaults();
    $isPlan = (int)$artefact['type'] === ARTEFACT_PLAN;
    $defence = $isPlan ? artefactReleasePlanDefenceTarget($config, $reference)
        : artefactReleaseDefenceTarget($config, $reference, (int)$artefact['size']);
    return array(
        'type' => (int)$artefact['type'], 'size' => (int)$artefact['size'],
        'garrison' => artefactReleaseGarrison($defence),
        'ring' => $isPlan ? artefactReleasePlanRing($config)
            : artefactReleaseRing($config, (int)$artefact['size']),
        'treasury' => $config['treasury'], 'fields' => $config['fields'],
        'cranny' => $config['cranny'], 'wall' => $config['wall']
    );
}

/**
 * MyISAM no deshace writes con ROLLBACK. Bloqueamos las tablas mientras nace la aldea,
 * trasladamos la fila al final y, ante un fallo, limpiamos sólo la casilla nueva.
 * El bloqueo también impide que una captura simultánea o dos POSTs creen duplicados.
 */
function artefactAbandon($database, $owner, $artefactId, $expected = null, $accrue = null) {
    $owner = (int)$owner;
    $artefactId = (int)$artefactId;
    if(!isPlayerAccount($owner) || $artefactId <= 0) {
        return array('status' => 'not_owned');
    }
    $connection = $database->connection;
    $driver = new mysqli_driver();
    $reportMode = $driver->report_mode;
    $locked = false;
    $reserved = 0;
    $transferred = false;
    $villageTables = array('vdata' => 'wref', 'fdata' => 'vref', 'units' => 'vref',
        'tdata' => 'vref', 'abdata' => 'vref');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $artefact = $database->getArtefactDetails($artefactId);
        if(!$artefact || (int)$artefact['owner'] !== $owner) {
            return array('status' => 'not_owned');
        }
        if($expected !== null && ((int)$artefact['vref'] !== $expected['vref']
            || (int)$artefact['conquered'] !== $expected['conquered'])) {
            return array('status' => 'not_owned');
        }
        $natarId = natarsAccountId();
        if((int)$database->getUserField($natarId, 'tribe', 0) !== 5
            || !$database->ensureNpcVillageColumns()) {
            return array('status' => 'unavailable');
        }
        $plan = artefactAbandonVillagePlan($artefact, artefactReleaseReferenceOffence($database));
        // Perder un artefacto puede activar otro de la cuenta. Se acredita el tramo
        // pasado con los efectos viejos, incluso en las aldeas que no se están mirando.
        if($accrue !== null) {
            call_user_func($accrue, $owner, time());
        }
        $locks = array();
        foreach(array_merge(array_keys($villageTables), array('wdata', 'artefacts')) as $table) {
            $locks[] = TB_PREFIX.$table.' WRITE';
        }
        mysqli_query($connection, 'LOCK TABLES '.implode(', ', $locks));
        $locked = true;
        $current = $database->getArtefactDetails($artefactId);
        if(!$current || (int)$current['owner'] !== $owner
            || (int)$current['vref'] !== (int)$artefact['vref']
            || (int)$current['type'] !== (int)$artefact['type']
            || (int)$current['size'] !== (int)$artefact['size']
            || (int)$current['conquered'] !== (int)$artefact['conquered']) {
            return array('status' => 'not_owned');
        }
        $source = $database->getVillage((int)$current['vref']);
        if(!$source || (int)$source['owner'] !== $owner) {
            return array('status' => 'not_owned');
        }

        $taken = array();
        $wref = 0;
        // La búsqueda global incluye las esquinas del mapa cuadrado.
        foreach(array($plan['ring'], array(0, ceil(sqrt(2) * WORLD_MAX))) as $ring) {
            while($candidate = artefactReleaseFindTile($database, $ring[0], $ring[1], $taken)) {
                $taken[$candidate] = true;
                $empty = true;
                // No reutilizar filas huérfanas ni sobrescribir aldeas aunque occupied esté mal.
                foreach($villageTables + array('artefacts' => 'vref') as $table => $column) {
                    $rows = mysqli_query($connection, 'SELECT '.$column.' FROM '.TB_PREFIX.$table
                        .' WHERE '.$column.' = '.(int)$candidate.' LIMIT 1');
                    if(mysqli_num_rows($rows) > 0) {
                        $empty = false;
                        break;
                    }
                }
                if($empty) {
                    $wref = (int)$candidate;
                    break 2;
                }
            }
        }
        if(!$wref) {
            return array('status' => 'no_space');
        }
        if(!$database->claimFieldForSettlement($wref)) {
            return array('status' => 'no_space');
        }
        $reserved = $wref;
        // Los natars no participan del ranking; evita writes ajenos a la aldea nueva.
        artefactReleasePopulateVillage($database, $plan, $natarId, $wref, false);
        mysqli_query($connection, 'UPDATE '.TB_PREFIX.'artefacts SET owner = '.$natarId
            .', vref = '.$wref.', conquered = '.time().' WHERE id = '.$artefactId
            .' AND owner = '.$owner.' AND vref = '.(int)$current['vref']);
        if(mysqli_affected_rows($connection) !== 1) {
            throw new RuntimeException('El artefacto cambió de dueño durante el abandono.');
        }
        $transferred = true;
        $database->flushArtefactCache();
        return array('status' => 'abandoned', 'artefact_id' => $artefactId, 'vref' => $wref);
    } catch(Throwable $error) {
        error_log('[ARTEFACT ABANDON] '.$error->getMessage());
        if($reserved && !$transferred) {
            foreach($villageTables as $table => $column) {
                mysqli_query($connection, 'DELETE FROM '.TB_PREFIX.$table.' WHERE '.$column.' = '.$reserved);
            }
            mysqli_query($connection, 'UPDATE '.TB_PREFIX.'wdata SET occupied = 0 WHERE id = '.$reserved);
        }
        return array('status' => 'unavailable');
    } finally {
        try {
            if($locked) {
                mysqli_query($connection, 'UNLOCK TABLES');
            }
        } finally {
            mysqli_report($reportMode);
        }
    }
}

function artefactAbandonMessage($status) {
    $messages = array(
        'abandoned' => 'Has abandonado el artefacto. Ahora está en una nueva aldea natar defendida; puedes ver su ubicación en esta ficha.',
        'invalid_request' => 'No se pudo confirmar el abandono. Debe hacerlo el titular de la cuenta, marcando la confirmación.',
        'not_owned' => 'No puedes abandonar este artefacto: ya no pertenece a tu cuenta o ha cambiado de aldea.',
        'no_space' => 'No hay una casilla libre para la nueva aldea natar. Conservas el artefacto.',
        'unavailable' => 'No se pudo trasladar el artefacto. Inténtalo de nuevo más tarde.'
    );
    return isset($messages[$status]) ? $messages[$status] : '';
}
