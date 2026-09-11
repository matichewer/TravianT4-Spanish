<?php

// Se consulta la alianza actual, también en cada salida de los envíos x2/x3.
function tradeRouteOwnersAllowed($fromOwner, $toOwner) {
    global $database;
    $fromOwner = (int)$fromOwner;
    $toOwner = (int)$toOwner;
    if($fromOwner <= 0 || $toOwner <= 0) {
        return false;
    }
    if($fromOwner === $toOwner) {
        return true;
    }
    $alliance = (int)$database->getUserField($fromOwner, 'alliance', 0);
    return $alliance > 0 && $alliance === (int)$database->getUserField($toOwner, 'alliance', 0);
}

function tradeRouteDestinations($uid, $from) {
    global $database;
    $uid = (int)$uid;
    $from = (int)$from;
    $alliance = (int)$database->getUserField($uid, 'alliance', 0);
    return $database->query_return('SELECT v.wref, v.name, u.username, v.owner FROM '.TB_PREFIX.'vdata v '
        .'JOIN '.TB_PREFIX.'users u ON u.id = v.owner '
        .'WHERE v.wref <> '.$from.' AND (v.owner = '.$uid
        .($alliance > 0 ? ' OR u.alliance = '.$alliance : '').') '
        .'ORDER BY (v.owner = '.$uid.') DESC, u.username, v.created ASC, v.wref ASC');
}
