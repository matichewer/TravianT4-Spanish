<?php
/**
 * Load the requested tiles in two bounded queries, rather than querying each tile.
 * Data is local to this render: ownership and movements are never cached across requests.
 */
function mapTilesForCoordinates($xs, $ys) {
    global $database, $generator;
    $ids = array();
    foreach ($ys as $y) {
        foreach ($xs as $x) { $ids[] = (int)$generator->getBaseID($x, $y); }
    }
    if (!$ids) { return array(); }
    $idList = implode(',', array_unique($ids));
    // An occupied oasis belongs to odata.owner, not to the (absent) village row.
    $rows = $database->query_return(
        'SELECT w.*, v.wref, v.owner, v.name, v.pop, v.created, '
        .'o.owner AS map_oasis_owner, ov.name AS map_oasis_village, '
        .'u.alliance AS map_alliance, u.tribe AS map_tribe, u.username AS map_username, '
        .'a.tag AS map_alliance_name '
        .'FROM '.TB_PREFIX.'wdata w '
        .'LEFT JOIN '.TB_PREFIX.'vdata v ON v.wref = w.id '
        .'LEFT JOIN '.TB_PREFIX.'odata o ON o.wref = w.id AND w.fieldtype = 0 AND w.oasistype > 0 AND w.occupied > 0 '
        .'LEFT JOIN '.TB_PREFIX.'vdata ov ON ov.wref = o.conqured '
        .'LEFT JOIN '.TB_PREFIX.'users u ON u.id = CASE WHEN w.fieldtype = 0 AND w.oasistype > 0 THEN o.owner ELSE v.owner END '
        .'LEFT JOIN '.TB_PREFIX.'alidata a ON a.id = u.alliance '
        .'WHERE w.id IN ('.$idList.')'
    );
    $byId = array();
    foreach ($rows as $row) { $byId[(int)$row['id']] = $row; }
    $tiles = array();
    // SQL order is not map order; repeated ids at small world edges must repeat visually.
    foreach ($ids as $id) {
        if (!isset($byId[$id])) { throw new RuntimeException('Missing map tile: '.$id); }
        $tiles[] = $byId[$id];
    }
    return $tiles;
}

function mapAttackMarkers($tiles, $sourceVillage, $largeMap) {
    global $database;
    $ids = array();
    foreach ($tiles as $tile) { $ids[] = (int)$tile['id']; }
    if (!$ids) { return array(); }
    // Keep the existing display rules: small map marks this village's outgoing
    // movements; large map marks incoming attacks/raids, excluding scouts/reinforcements.
    $join = $largeMap ? ' JOIN '.TB_PREFIX.'attacks a ON a.id = m.ref' : '';
    $filter = $largeMap ? ' AND a.attack_type NOT IN (1,2)' : ' AND m.`from` = '.(int)$sourceVillage;
    $rows = $database->query_return('SELECT DISTINCT m.`to` AS target FROM '.TB_PREFIX.'movement m'
        .$join.' WHERE m.proc = 0 AND m.sort_type = 3 AND m.`to` IN ('
        .implode(',', array_unique($ids)).')'.$filter);
    $markers = array();
    foreach ($rows as $row) { $markers[(int)$row['target']] = true; }
    return $markers;
}
