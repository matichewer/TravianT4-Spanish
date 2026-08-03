<?php
/**
 * Lista las tropas en camino hacia una aldea (ataques, asaltos, refuerzos,
 * espionajes, colonos y regresos) leyendo directamente s1_movement.
 *
 * Uso:
 *   php tools/incoming_troops.php 12345          # por id de campo (wref)
 *   php tools/incoming_troops.php "0|0"          # por coordenadas
 *   php tools/incoming_troops.php "Mi aldea"     # por nombre de aldea (parcial)
 *   php tools/incoming_troops.php @Jugador       # todas las aldeas de un jugador
 *   php tools/incoming_troops.php --all          # todo el servidor
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
if(!file_exists($root.'/config/installed') || !file_exists($root.'/config/connection.php')) {
    fwrite(STDERR, "El servidor no está instalado (falta config/connection.php).\n");
    exit(1);
}

chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once $root.'/config/connection.php';
require_once $root.'/config/config.php'; // define LANG y deja $connection abierto

$langFile = $root.'/GameEngine/Lang/'.LANG.'.php';
if(file_exists($langFile)) {
    require_once $langFile;
}

$target = isset($argv[1]) ? trim($argv[1]) : '';
if($target === '' || $target === '--help' || $target === '-h') {
    fwrite(STDERR, "Uso: php tools/incoming_troops.php <wref | \"x|y\" | nombre de aldea | @jugador | --all>\n");
    exit(1);
}

$tribes = array(1 => 'romanos', 2 => 'teutones', 3 => 'galos', 4 => 'naturaleza', 5 => 'natars');

function esc($value) {
    global $connection;
    return mysqli_real_escape_string($connection, $value);
}

function rows($query) {
    global $connection;
    $result = mysqli_query($connection, $query);
    if(!$result) {
        fwrite(STDERR, "Error SQL: ".mysqli_error($connection)."\n");
        exit(1);
    }
    $out = array();
    while($row = mysqli_fetch_assoc($result)) {
        $out[] = $row;
    }
    return $out;
}

/** Resuelve el argumento a una lista de wrefs destino. */
function resolveTargets($target) {
    if($target === '--all') {
        $found = rows("SELECT DISTINCT `to` AS wref FROM ".TB_PREFIX."movement WHERE proc = 0 AND sort_type IN (3,4,5)");
        return array_map('intval', array_column($found, 'wref'));
    }
    if(preg_match('/^-?\d+$/', $target)) {
        return array((int)$target);
    }
    if(preg_match('/^\s*(-?\d+)\s*[|,;: ]\s*(-?\d+)\s*$/', $target, $m)) {
        $found = rows("SELECT id FROM ".TB_PREFIX."wdata WHERE x = ".(int)$m[1]." AND y = ".(int)$m[2]);
        return array_map('intval', array_column($found, 'id'));
    }
    if($target[0] === '@') {
        $name = esc(substr($target, 1));
        $found = rows("SELECT v.wref FROM ".TB_PREFIX."vdata v INNER JOIN ".TB_PREFIX."users u ON u.id = v.owner WHERE u.username LIKE '%$name%'");
        return array_map('intval', array_column($found, 'wref'));
    }
    $name = esc($target);
    $found = rows("SELECT wref FROM ".TB_PREFIX."vdata WHERE name LIKE '%$name%'");
    return array_map('intval', array_column($found, 'wref'));
}

/** Nombre + coords + dueño de un campo del mapa (aldea u oasis). */
function placeInfo($wref) {
    static $cache = array();
    $wref = (int)$wref;
    if(isset($cache[$wref])) {
        return $cache[$wref];
    }
    if($wref === 0) {
        return $cache[0] = array('name' => 'Naturaleza', 'coords' => '', 'owner' => '', 'tribe' => 4);
    }
    $info = array('name' => "campo #$wref", 'coords' => '', 'owner' => '', 'tribe' => 0);
    $coords = rows("SELECT x, y FROM ".TB_PREFIX."wdata WHERE id = $wref");
    if($coords) {
        $info['coords'] = '('.$coords[0]['x'].'|'.$coords[0]['y'].')';
    }
    $village = rows("SELECT v.name, v.owner, u.username, u.tribe FROM ".TB_PREFIX."vdata v LEFT JOIN ".TB_PREFIX."users u ON u.id = v.owner WHERE v.wref = $wref");
    if($village) {
        $info['name'] = $village[0]['name'];
        $info['owner'] = $village[0]['username'];
        $info['tribe'] = (int)$village[0]['tribe'];
    } else {
        $oasis = rows("SELECT oasistype, conqured FROM ".TB_PREFIX."odata WHERE wref = $wref");
        if($oasis) {
            $info['name'] = 'Oasis';
            $info['tribe'] = 4;
        }
    }
    return $cache[$wref] = $info;
}

function unitName($id) {
    $constant = 'U'.$id;
    return defined($constant) ? constant($constant) : "u$id";
}

function heroName() {
    return defined('U0') ? U0 : 'Héroe';
}

/** Detalle de tropas de una fila de s1_attacks según la tribu de origen. */
function troopList($move, $tribe) {
    if($tribe < 1 || $tribe > 5) {
        $tribe = 4;
    }
    $parts = array();
    for($slot = 1; $slot <= 10; $slot++) {
        $amount = (int)$move['t'.$slot];
        if($amount > 0) {
            $parts[] = unitName(($tribe - 1) * 10 + $slot).' '.number_format($amount, 0, ',', '.');
        }
    }
    if((int)$move['t11'] > 0) {
        $parts[] = heroName();
    }
    return $parts ? implode(', ', $parts) : 'sin tropas';
}

function movementLabel($move) {
    if((int)$move['sort_type'] === 5) {
        return 'COLONOS';
    }
    if((int)$move['sort_type'] === 4) {
        return 'REGRESO';
    }
    switch((int)$move['attack_type']) {
        case 1: return 'ESPIONAJE';
        case 2: return 'REFUERZO';
        case 3: return 'ATAQUE';
        case 4: return 'ASALTO';
        default: return 'MOVIMIENTO';
    }
}

function countdown($seconds) {
    if($seconds < 0) {
        return 'venciendo';
    }
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

$targets = resolveTargets($target);
if(!$targets) {
    if($target === '--all') {
        echo "Sin tropas en camino en todo el servidor.\n";
        exit(0);
    }
    // Las llaves evitan que PHP lea los bytes de «» como parte del nombre de variable.
    fwrite(STDERR, "No se encontró ninguna aldea para «{$target}».\n");
    exit(1);
}

$list = implode(',', array_map('intval', $targets));
$moves = rows(
    "SELECT m.moveid, m.sort_type, m.`from`, m.`to`, m.endtime, m.wood, m.clay, m.iron, m.crop,"
    ." a.attack_type, a.t1, a.t2, a.t3, a.t4, a.t5, a.t6, a.t7, a.t8, a.t9, a.t10, a.t11"
    ." FROM ".TB_PREFIX."movement m"
    ." LEFT JOIN ".TB_PREFIX."attacks a ON a.id = m.ref AND m.sort_type IN (3,4)"
    ." WHERE m.proc = 0 AND m.sort_type IN (3,4,5) AND m.`to` IN ($list)"
    ." ORDER BY m.`to` ASC, m.endtime ASC"
);

if(!$moves) {
    echo "Sin tropas en camino hacia «{$target}».\n";
    exit(0);
}

$now = time();
$grouped = array();
foreach($moves as $move) {
    $grouped[(int)$move['to']][] = $move;
}

foreach($grouped as $wref => $group) {
    $dest = placeInfo($wref);
    echo "\n=== ".$dest['name'].' '.$dest['coords'];
    if($dest['owner'] !== '') {
        echo ' — '.$dest['owner'];
    }
    echo ' [wref '.$wref."] ===\n";

    foreach($group as $move) {
        $origin = placeInfo($move['from']);
        $tribe = (int)$move['sort_type'] === 4 ? $dest['tribe'] : $origin['tribe'];
        $when = date('d/m H:i:s', (int)$move['endtime']);
        $left = countdown((int)$move['endtime'] - $now);

        printf(
            "  %-9s  %s %s%s  ->  llega %s (en %s)\n",
            movementLabel($move),
            $origin['name'],
            $origin['coords'],
            $origin['owner'] !== '' ? ' de '.$origin['owner'].' ['.(isset($tribes[$tribe]) ? $tribes[$tribe] : '?').']' : '',
            $when,
            $left
        );

        if($move['attack_type'] !== null) {
            echo "             ".troopList($move, $tribe)."\n";
        }
        $carried = (int)$move['wood'] + (int)$move['clay'] + (int)$move['iron'] + (int)$move['crop'];
        if($carried > 0) {
            printf(
                "             recursos: %s / %s / %s / %s\n",
                number_format((int)$move['wood'], 0, ',', '.'),
                number_format((int)$move['clay'], 0, ',', '.'),
                number_format((int)$move['iron'], 0, ',', '.'),
                number_format((int)$move['crop'], 0, ',', '.')
            );
        }
    }
}

echo "\n";
