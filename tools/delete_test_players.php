<?php

/**
 * Elimina las cuentas ficticias "testN" que creaba el generador público test.php.
 *
 * Sin argumentos solo informa: no modifica nada. Con --confirm ejecuta la limpieza.
 * Antes de borrar devuelve a sus dueños las tropas y los recursos ajenos que estén
 * estacionados o en camino hacia las aldeas ficticias, para que ningún jugador real
 * pierda nada. Después libera los casilleros del mapa y quita las referencias que
 * hayan quedado en listas de granjeo, plantillas del punto de reunión y listas de
 * amigos de otros jugadores.
 *
 *   docker compose exec -T web php tools/delete_test_players.php
 *   docker compose exec -T web php tools/delete_test_players.php --confirm
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
if(!file_exists($root.'/config/installed') || !file_exists($root.'/config/connection.php')) {
    fwrite(STDERR, "El servidor no está instalado.\n");
    exit(1);
}

chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);

require_once $root.'/GameEngine/Data/buidata.php';
require_once $root.'/GameEngine/GeneratorX.php';
require_once $root.'/GameEngine/Database.php';
require_once $root.'/GameEngine/Automation.php';

$generator = new GeneratorX;
$confirm = in_array('--confirm', $argv, true);

/*****************************************
    Utilidades
*****************************************/

function purge_select($q) {
    global $database;
    $result = mysqli_query($database->connection, $q);
    if($result === false) {
        fwrite(STDERR, "  ! Error SQL: ".mysqli_error($database->connection)."\n    ".$q."\n");
        return array();
    }
    $rows = array();
    while($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function purge_query($q) {
    global $database;
    $result = mysqli_query($database->connection, $q);
    if($result === false) {
        fwrite(STDERR, "  ! Error SQL: ".mysqli_error($database->connection)."\n    ".$q."\n");
        return 0;
    }
    return mysqli_affected_rows($database->connection);
}

function purge_count($q) {
    $rows = purge_select($q);
    return empty($rows) ? 0 : (int)reset($rows[0]);
}

/**
 * Tiempo de viaje de vuelta a casa. Usa la misma cuenta que Automation cuando
 * devuelve refuerzos desde una aldea que se está borrando.
 */
function purge_travel_time($fromWref, $toWref, $tribe) {
    global $database;
    static $method = null;
    static $automation = null;
    if($method === null) {
        $class = new ReflectionClass('Automation');
        $automation = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('procDistanceTime');
        $method->setAccessible(true);
    }
    $fromcoor = $database->getCoor($fromWref);
    $tocoor = $database->getCoor($toWref);
    $seconds = (int)$method->invoke($automation, $tocoor, $fromcoor, $tribe, 0);
    return max(60, $seconds);
}

function purge_line($text = '') {
    echo $text."\n";
}

/*****************************************
    Cuentas ficticias a borrar
*****************************************/

// El generador creaba siempre el mismo patrón: nombre testN, correo testN@mail.com
// y acceso de jugador normal. Las cuentas del sistema (Support, Natars, Nature,
// Multihunter) quedan fuera por partida doble: por el patrón y por el id.
$candidates = purge_select(
    "SELECT id, username, email, access FROM ".TB_PREFIX."users
     WHERE username REGEXP '^test[0-9]+$'
       AND email = CONCAT(username, '@mail.com')
       AND access = ".USER."
       AND id NOT IN (1,2,3,4)
     ORDER BY id+0"
);

$uids = array();
foreach ($candidates as $candidate) {
    $uids[] = (int)$candidate['id'];
}

purge_line("== Cuentas de prueba encontradas: ".count($uids)." ==");
if(empty($uids)) {
    // Aun así avisamos si hay cuentas con nombre testN que no cumplen el resto del patrón.
    $similar = purge_select(
        "SELECT id, username, email, access FROM ".TB_PREFIX."users
         WHERE username REGEXP '^test[0-9]+$' ORDER BY id+0"
    );
    foreach ($similar as $row) {
        purge_line("  - ".$row['username']." (id ".$row['id'].", ".$row['email'].", acceso ".$row['access'].") no coincide con el patrón del generador; se ignora.");
    }
    purge_line("No hay nada que borrar.");
    exit(0);
}

$uidList = implode(',', $uids);
purge_line("  ids: ".$uidList);

// Cuentas parecidas que quedan fuera del filtro, para que no se borren en silencio.
$skipped = purge_select(
    "SELECT id, username, email, access FROM ".TB_PREFIX."users
     WHERE username REGEXP '^test[0-9]+$' AND id NOT IN ($uidList) ORDER BY id+0"
);
if(!empty($skipped)) {
    purge_line();
    purge_line("== Cuentas con nombre testN que NO se van a tocar ==");
    foreach ($skipped as $row) {
        purge_line("  - ".$row['username']." (id ".$row['id'].", ".$row['email'].", acceso ".$row['access'].")");
    }
}

$villages = purge_select("SELECT wref, name FROM ".TB_PREFIX."vdata WHERE owner IN ($uidList)");
$vids = array();
foreach ($villages as $village) {
    $vids[] = (int)$village['wref'];
}
$vidList = empty($vids) ? '0' : implode(',', $vids);

purge_line();
purge_line("== Aldeas de esas cuentas: ".count($vids)." ==");

/*****************************************
    Qué hay de jugadores reales metido ahí
*****************************************/

$incomingAttacks = purge_select(
    "SELECT m.moveid, m.`from`, m.`to`, m.ref, m.sort_type FROM ".TB_PREFIX."movement m
     WHERE m.`to` IN ($vidList) AND m.proc = 0 AND m.sort_type = 3 AND m.`from` NOT IN ($vidList)"
);
$incomingMerchants = purge_select(
    "SELECT m.moveid, m.`from`, m.`to`, m.ref, s.wood, s.clay, s.iron, s.crop
     FROM ".TB_PREFIX."movement m, ".TB_PREFIX."send s
     WHERE m.ref = s.id AND m.`to` IN ($vidList) AND m.proc = 0 AND m.sort_type IN (0,2)
       AND m.`from` NOT IN ($vidList)"
);
$foreignEnforcements = purge_select(
    "SELECT * FROM ".TB_PREFIX."enforcement WHERE vref IN ($vidList) AND `from` NOT IN ($vidList)"
);
$foreignPrisoners = purge_select(
    "SELECT * FROM ".TB_PREFIX."prisoners WHERE wref IN ($vidList) AND `from` NOT IN ($vidList)"
);
$farmEntries = purge_count("SELECT COUNT(*) FROM ".TB_PREFIX."raidlist WHERE towref IN ($vidList)");
$a2bEntries = purge_count("SELECT COUNT(*) FROM ".TB_PREFIX."a2b WHERE to_vid IN ($vidList)");
$returningHome = purge_count(
    "SELECT COUNT(*) FROM ".TB_PREFIX."movement
     WHERE `from` IN ($vidList) AND `to` NOT IN ($vidList) AND proc = 0 AND sort_type IN (0,2,4)"
);

purge_line();
purge_line("== Cosas de jugadores reales relacionadas con esas aldeas ==");
purge_line("  Ataques/incursiones en curso hacia ellas : ".count($incomingAttacks)." (se devuelven a casa)");
purge_line("  Mercaderes en curso hacia ellas          : ".count($incomingMerchants)." (vuelven con la carga)");
purge_line("  Refuerzos ajenos estacionados            : ".count($foreignEnforcements)." (se devuelven a casa)");
purge_line("  Prisioneros ajenos retenidos             : ".count($foreignPrisoners)." (se devuelven a casa)");
purge_line("  Tropas/mercaderes ya de vuelta a casa    : ".$returningHome." (siguen su viaje, no se tocan)");
purge_line("  Entradas en listas de granjeo ajenas     : ".$farmEntries." (se borran)");
purge_line("  Plantillas del punto de reunión          : ".$a2bEntries." (se borran)");

if(!$confirm) {
    purge_line();
    purge_line("Modo informe: no se modificó nada. Volvé a ejecutarlo con --confirm para borrar.");
    exit(0);
}

/*****************************************
    1. Devolver lo ajeno
*****************************************/

purge_line();
purge_line("== Devolviendo tropas y recursos ajenos ==");

$time = time();

// Refuerzos ajenos estacionados en aldeas de prueba: mismo camino que usa Automation
// al borrar una cuenta (fila nueva en attacks + movimiento de regreso).
$returned = 0;
foreach ($foreignEnforcements as $enforce) {
    $ownerUid = (int)$database->getVillageField($enforce['from'], "owner");
    $tribe = (int)$database->getUserField($ownerUid, "tribe", 0);
    if($tribe < 1) {
        continue;
    }
    $start = 10 * ($tribe - 1);
    $troops = array();
    for ($i = 1; $i <= 10; $i++) {
        $troops[$i] = (int)$enforce['u'.($start + $i)];
    }
    $troops[11] = (int)$enforce['hero'];
    $ref = $database->addAttack(
        $enforce['from'], $troops[1], $troops[2], $troops[3], $troops[4], $troops[5],
        $troops[6], $troops[7], $troops[8], $troops[9], $troops[10], $troops[11], 2, 0, 0, 0
    );
    $travel = purge_travel_time($enforce['vref'], $enforce['from'], $tribe);
    $database->addMovement(4, $enforce['vref'], $enforce['from'], $ref, $time, $time + $travel);
    purge_query("DELETE FROM ".TB_PREFIX."enforcement WHERE id = ".(int)$enforce['id']);
    $returned++;
}
purge_line("  Refuerzos devueltos            : ".$returned);

// Prisioneros ajenos retenidos en una aldea de prueba: vuelven con su dueño.
$freed = 0;
foreach ($foreignPrisoners as $pris) {
    $ownerUid = (int)$database->getVillageField($pris['from'], "owner");
    $tribe = (int)$database->getUserField($ownerUid, "tribe", 0);
    if($tribe < 1) {
        continue;
    }
    $ref = $database->addAttack(
        $pris['from'], (int)$pris['t1'], (int)$pris['t2'], (int)$pris['t3'], (int)$pris['t4'],
        (int)$pris['t5'], (int)$pris['t6'], (int)$pris['t7'], (int)$pris['t8'], (int)$pris['t9'],
        (int)$pris['t10'], (int)$pris['t11'], 2, 0, 0, 0
    );
    $travel = purge_travel_time($pris['wref'], $pris['from'], $tribe);
    $database->addMovement(4, $pris['wref'], $pris['from'], $ref, $time, $time + $travel);
    purge_query("DELETE FROM ".TB_PREFIX."prisoners WHERE id = ".(int)$pris['id']);
    $freed++;
}
purge_line("  Prisioneros liberados          : ".$freed);

// Ataques en curso contra una aldea de prueba: se rebotan reusando la misma fila de
// attacks, igual que hace Automation, para que las tropas vuelvan a su aldea.
$bounced = 0;
foreach ($incomingAttacks as $move) {
    $ownerUid = (int)$database->getVillageField($move['from'], "owner");
    $tribe = (int)$database->getUserField($ownerUid, "tribe", 0);
    if($tribe < 1) {
        $tribe = 1;
    }
    $travel = purge_travel_time($move['to'], $move['from'], $tribe);
    $database->addMovement(4, $move['to'], $move['from'], $move['ref'], '0,0,0,0,0', $time + $travel);
    purge_query("DELETE FROM ".TB_PREFIX."movement WHERE moveid = ".(int)$move['moveid']);
    $bounced++;
}
purge_line("  Ataques devueltos              : ".$bounced);

// Mercaderes en camino: la aldea de destino ya no va a existir, así que los recursos
// se acreditan de vuelta en la aldea de origen y los mercaderes quedan libres.
$refunded = 0;
foreach ($incomingMerchants as $move) {
    purge_query(
        "UPDATE ".TB_PREFIX."vdata SET
            wood = LEAST(maxstore, wood + ".(int)$move['wood']."),
            clay = LEAST(maxstore, clay + ".(int)$move['clay']."),
            iron = LEAST(maxstore, iron + ".(int)$move['iron']."),
            crop = LEAST(maxcrop, crop + ".(int)$move['crop'].")
         WHERE wref = ".(int)$move['from']
    );
    purge_query("DELETE FROM ".TB_PREFIX."send WHERE id = ".(int)$move['ref']);
    purge_query("DELETE FROM ".TB_PREFIX."movement WHERE moveid = ".(int)$move['moveid']);
    $refunded++;
}
purge_line("  Envíos de recursos reintegrados: ".$refunded);

/*****************************************
    2. Movimientos que ya no tienen sentido
*****************************************/

// Todo lo que iba hacia una aldea de prueba y lo que salía de ella como ataque o
// colonización. Los regresos hacia aldeas reales (sort_type 0, 2 y 4) se dejan vivos:
// el motor solo necesita la aldea de destino para procesarlos.
$deadMovements = purge_select(
    "SELECT moveid, ref, sort_type FROM ".TB_PREFIX."movement
     WHERE `to` IN ($vidList)
        OR (`from` IN ($vidList) AND sort_type IN (3,5,6,9))"
);
$deadRefs = array('attacks' => array(), 'send' => array());
foreach ($deadMovements as $move) {
    if(in_array((int)$move['sort_type'], array(3, 4, 6), true)) {
        $deadRefs['attacks'][] = (int)$move['ref'];
    } elseif(in_array((int)$move['sort_type'], array(0, 2), true)) {
        $deadRefs['send'][] = (int)$move['ref'];
    }
}
$removed = purge_query(
    "DELETE FROM ".TB_PREFIX."movement
     WHERE `to` IN ($vidList)
        OR (`from` IN ($vidList) AND sort_type IN (3,5,6,9))"
);
foreach ($deadRefs as $table => $refs) {
    if(!empty($refs)) {
        purge_query("DELETE FROM ".TB_PREFIX.$table." WHERE id IN (".implode(',', array_unique($refs)).")");
    }
}
purge_line("  Movimientos sin destino borrados: ".$removed);

/*****************************************
    3. Datos de las aldeas
*****************************************/

purge_line();
purge_line("== Borrando aldeas ==");

// Oasis que estuvieran ocupados por una aldea de prueba: vuelven a estar libres.
purge_query("UPDATE ".TB_PREFIX."odata SET conqured = 0, owner = 0 WHERE conqured IN ($vidList)");

// Las listas de granjeo y las plantillas del punto de reunión de CUALQUIER jugador
// que apunten a estas aldeas quedarían rotas, así que se limpian también.
purge_line("  Entradas de listas de granjeo   : ".purge_query("DELETE FROM ".TB_PREFIX."raidlist WHERE towref IN ($vidList)"));
purge_line("  Plantillas del punto de reunión : ".purge_query("DELETE FROM ".TB_PREFIX."a2b WHERE to_vid IN ($vidList)"));

$villageTables = array(
    'abdata'      => 'vref',
    'artefacts'   => 'vref',
    'attacks'     => 'vref',
    'bdata'       => 'wid',
    'build_log'   => 'wid',
    'demolition'  => 'vref',
    'destroy_log' => 'wid',
    'fdata'       => 'vref',
    'gold_fin_log'=> 'wid',
    'market'      => 'vref',
    'market_log'  => 'wid',
    'research'    => 'vref',
    'tdata'       => 'vref',
    'tech_log'    => 'wid',
    'training'    => 'vref',
    'units'       => 'vref',
    'ww_attacks'  => 'vid',
    'farmlist'    => 'wref',
    'adventure'   => 'wref',
    'vdata'       => 'wref',
);
$villageRows = 0;
foreach ($villageTables as $table => $column) {
    $villageRows += purge_query("DELETE FROM ".TB_PREFIX.$table." WHERE `$column` IN ($vidList)");
}
// Tablas donde la aldea puede aparecer en cualquiera de los dos extremos.
$villageRows += purge_query("DELETE FROM ".TB_PREFIX."enforcement WHERE vref IN ($vidList) OR `from` IN ($vidList)");
$villageRows += purge_query("DELETE FROM ".TB_PREFIX."prisoners WHERE wref IN ($vidList) OR `from` IN ($vidList)");
$villageRows += purge_query("DELETE FROM ".TB_PREFIX."route WHERE wid IN ($vidList) OR `from` IN ($vidList)");
purge_line("  Filas de aldea borradas         : ".$villageRows);

// Casilleros del mapa liberados.
purge_line("  Casilleros liberados            : ".purge_query("UPDATE ".TB_PREFIX."wdata SET occupied = 0 WHERE id IN ($vidList)"));

/*****************************************
    4. Datos de las cuentas
*****************************************/

purge_line();
purge_line("== Borrando cuentas ==");

$userTables = array(
    'ali_invite'     => 'uid',
    'ali_permission' => 'uid',
    'adventure'      => 'uid',
    'auction_bids'   => 'uid',
    'banlist'        => 'uid',
    'chat'           => 'id_user',
    'deleting'       => 'uid',
    'hero'           => 'uid',
    'heroface'       => 'uid',
    'heroinventory'  => 'uid',
    'heroitems'      => 'uid',
    'illegal_log'    => 'user',
    'links'          => 'userid',
    'login_log'      => 'uid',
    'medal'          => 'userid',
    'ndata'          => 'uid',
    'newproc'        => 'uid',
    'users'          => 'id',
);
$userRows = 0;
foreach ($userTables as $table => $column) {
    $userRows += purge_query("DELETE FROM ".TB_PREFIX.$table." WHERE `$column` IN ($uidList)");
}
$userRows += purge_query("DELETE FROM ".TB_PREFIX."mdata WHERE target IN ($uidList) OR owner IN ($uidList)");
$userRows += purge_query("DELETE FROM ".TB_PREFIX."auction WHERE uid IN ($uidList) OR owner IN ($uidList)");

// Tablas que referencian al jugador por nombre.
$names = array();
foreach ($candidates as $candidate) {
    $names[] = "'".mysqli_real_escape_string($database->connection, $candidate['username'])."'";
}
$nameList = implode(',', $names);
$userRows += purge_query("DELETE FROM ".TB_PREFIX."online WHERE name IN ($nameList)");
$userRows += purge_query("DELETE FROM ".TB_PREFIX."active WHERE username IN ($nameList)");
$userRows += purge_query("DELETE FROM ".TB_PREFIX."activate WHERE username IN ($nameList)");
purge_line("  Filas de cuenta borradas        : ".$userRows);

// Listas de amigos de jugadores reales que apuntaban a estas cuentas.
$friendRows = 0;
for ($i = 0; $i < 20; $i++) {
    $friendRows += purge_query("UPDATE ".TB_PREFIX."users SET friend".$i." = 0 WHERE friend".$i." IN ($uidList)");
    $friendRows += purge_query("UPDATE ".TB_PREFIX."users SET friend".$i."wait = 0 WHERE friend".$i."wait IN ($uidList)");
}
purge_line("  Amistades limpiadas             : ".$friendRows);

/*****************************************
    5. Verificación
*****************************************/

purge_line();
purge_line("== Verificación ==");
purge_line("  Cuentas testN restantes         : ".purge_count("SELECT COUNT(*) FROM ".TB_PREFIX."users WHERE id IN ($uidList)"));
purge_line("  Aldeas restantes                : ".purge_count("SELECT COUNT(*) FROM ".TB_PREFIX."vdata WHERE wref IN ($vidList)"));
purge_line("  Aldeas sin dueño en el mundo    : ".purge_count(
    "SELECT COUNT(*) FROM ".TB_PREFIX."vdata v LEFT JOIN ".TB_PREFIX."users u ON u.id = v.owner WHERE u.id IS NULL"
));
purge_line("  Casilleros ocupados sin aldea   : ".purge_count(
    "SELECT COUNT(*) FROM ".TB_PREFIX."wdata w LEFT JOIN ".TB_PREFIX."vdata v ON v.wref = w.id
     WHERE w.occupied = 1 AND w.oasistype = 0 AND v.wref IS NULL"
));
purge_line();
purge_line("Listo.");
