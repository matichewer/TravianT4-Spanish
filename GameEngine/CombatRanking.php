<?php

function calculateCombatRankingPoints($casualties, $heroCasualties = 0) {
    $points = max(0, (int)$heroCasualties) * 6;
    if(!is_array($casualties)) {
        return $points;
    }

    foreach($casualties as $unitId => $amount) {
        $unitId = (int)$unitId;
        $amount = max(0, (int)$amount);
        $unitData = isset($GLOBALS['u'.$unitId]) ? $GLOBALS['u'.$unitId] : null;
        if($amount > 0 && is_array($unitData) && isset($unitData['pop'])) {
            $points += $amount * max(0, (int)$unitData['pop']);
        }
    }

    return $points;
}
