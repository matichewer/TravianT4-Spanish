<?php
// Integration checks on connection-local temporary tables. Never changes live rows.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
chdir($root);
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
date_default_timezone_set('America/Argentina/Buenos_Aires');
$_SESSION = array();
require 'GameEngine/Database.php';
require 'GameEngine/GeneratorX.php';
require 'GameEngine/MapData.php';
function mapDataCheck($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo '[OK] '.$message.PHP_EOL;
}
function mapDataSql($sql) {
    global $database;
    if (!mysqli_query($database->connection, $sql)) {
        throw new RuntimeException(mysqli_error($database->connection));
    }
}
// First compare batched data against existing reads on the actual world.
$database->getWorldRadius();
$actual = mapTilesForCoordinates(range(-8, 8), range(8, -8));
foreach ($actual as $tile) {
    $old = $database->getMInfo($tile['id']);
    foreach (array('id','x','y','fieldtype','oasistype','occupied','image','wref','owner','name','pop','created') as $key) {
        if (($old[$key] ?? null) != ($tile[$key] ?? null)) { throw new RuntimeException('Tile changed: '.$key); }
    }
    if ((int)$tile['occupied'] > 0) {
        $owner = (int)$tile['owner'];
        if ((int)$tile['fieldtype'] === 0 && (int)$tile['oasistype'] > 0) {
            $oasis = $database->getOMInfo($tile['id']);
            $owner = (int)$oasis['owner'];
            if (($oasis['conqured_name'] ?? null) != $tile['map_oasis_village']) { throw new RuntimeException('Oasis village changed'); }
        }
        if ($owner) {
            foreach (array('username','tribe','alliance') as $field) {
                if ($database->getUserField($owner, $field, 0) != $tile['map_'.$field]) { throw new RuntimeException('Owner changed: '.$field); }
            }
        }
    }
}
mapDataCheck(true, 'Batched tiles, ownership, population and oasis data match existing reads');

foreach (array('wdata','vdata','odata','users','alidata','movement','attacks') as $table) {
    $schema = $database->query_return('SHOW CREATE TABLE '.TB_PREFIX.$table);
    mapDataSql(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $schema[0]['Create Table']));
}
$owner = lastSystemAccountId()+100;
$other = $owner+1;
mapDataSql('INSERT INTO '.TB_PREFIX."users (id, username, tribe, alliance) VALUES ($owner, 'Map owner', 1, 71), ($other, 'Oasis owner', 3, 72)");
mapDataSql('INSERT INTO '.TB_PREFIX."alidata (id, tag) VALUES (71, 'OWN'), (72, 'OASIS')");
$ids = array();
foreach (range(0, 6) as $x) {
    $id = $generator->getBaseID($x, 0);
    $ids[] = $id;
    $isOasis = in_array($x, array(1,2), true);
    $field = $isOasis ? 0 : 1;
    $oasis = $isOasis ? 1 : 0;
    $occupied = in_array($x, array(0,1,3), true) ? 1 : 0;
    mapDataSql('INSERT INTO '.TB_PREFIX."wdata (id,x,y,fieldtype,oasistype,occupied,image) VALUES ($id,$x,0,$field,$oasis,$occupied,'t0')");
}
mapDataSql('INSERT INTO '.TB_PREFIX."vdata (wref,owner,name,pop,created) VALUES ($ids[0],$owner,'Map village',222,123), ($ids[6],$other,'Oasis home',99,123)");
mapDataSql('INSERT INTO '.TB_PREFIX."odata (wref,owner,conqured) VALUES ($ids[1],$other,$ids[6]), ($ids[2],$other,$ids[6])");
$tiles = mapTilesForCoordinates(range(0,6), array(0));
mapDataCheck(count($tiles) === 7 && (int)$tiles[0]['id'] === $ids[0], 'Grid keeps west-to-east ordering');
mapDataCheck($tiles[0]['map_username'] === 'Map owner' && $tiles[0]['map_alliance_name'] === 'OWN', 'Village keeps its owner and alliance');
mapDataCheck($tiles[1]['map_username'] === 'Oasis owner' && $tiles[1]['map_oasis_village'] === 'Oasis home' && $tiles[1]['map_alliance_name'] === 'OASIS' && (int)$tiles[1]['map_tribe'] === 3, 'Occupied oasis reads the annexing owner, tribe, alliance and village');
mapDataCheck($tiles[2]['map_username'] === null, 'Free oasis ignores stale annexation data');
mapDataCheck($tiles[3]['wref'] === null && $tiles[3]['map_username'] === null, 'Occupied tile without a village remains terrain');
$repeat = mapTilesForCoordinates(array(0,1,0), array(0));
mapDataCheck(count($repeat) === 3 && $repeat[0] === $repeat[2], 'Repeated coordinates remain repeated after SQL deduplication');

// scout, reinforcement, attack, raid; processed and return movements; foreign origin.
$fixtures = array(
    array(1,1,3,0,$ids[0],$ids[1]), array(2,2,3,0,$ids[0],$ids[2]),
    array(3,3,3,0,$ids[0],$ids[3]), array(4,4,3,0,$ids[6],$ids[4]),
    array(5,3,3,1,$ids[0],$ids[5]), array(6,3,4,0,$ids[0],$ids[5]),
    array(7,3,3,0,$ids[0],$ids[3])
);
foreach ($fixtures as $fixture) {
    list($id,$type,$sort,$proc,$from,$to) = $fixture;
    mapDataSql('INSERT INTO '.TB_PREFIX."attacks (id,attack_type) VALUES ($id,$type)");
    mapDataSql('INSERT INTO '.TB_PREFIX."movement (`from`,`to`,ref,sort_type,proc) VALUES ($from,$to,$id,$sort,$proc)");
}
$small = mapAttackMarkers($tiles, $ids[0], false);
$large = mapAttackMarkers($tiles, $ids[0], true);
mapDataCheck(count($small) === 3 && isset($small[$ids[1]],$small[$ids[2]],$small[$ids[3]]), 'Small map preserves outgoing markers and deduplicates destinations');
mapDataCheck(count($large) === 2 && isset($large[$ids[3]],$large[$ids[4]]), 'Large map excludes scouts, reinforcements, processed and return movements');
foreach ($tiles as $tile) {
    $oldSmall = (bool)$database->checkAttack($ids[0], $tile['id']);
    $oldLarge = false;
    foreach ($database->getMovement(3,$tile['id'],1) as $movement) {
        if (!in_array((int)$movement['attack_type'], array(1,2), true)) { $oldLarge = true; }
    }
    mapDataCheck($oldSmall === isset($small[$tile['id']]) && $oldLarge === isset($large[$tile['id']]), 'Batched markers match legacy reads at '.$tile['x'].'|0');
}
