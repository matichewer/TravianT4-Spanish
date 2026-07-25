<?php

require dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require dirname(__DIR__).'/GameEngine/CombatRanking.php';

function combatRankingAssert($condition, $message) {
    if(!$condition) {
        throw new RuntimeException($message);
    }
}

$mixedRomanLosses = array(
    1 => 3, // 3 Legionnaires: 3 supply
    5 => 2, // 2 Equites Imperatoris: 6 supply
    8 => 1  // 1 Fire Catapult: 6 supply
);
$mixedRomanPoints = calculateCombatRankingPoints($mixedRomanLosses, 1);
combatRankingAssert($mixedRomanPoints === 21, 'Mixed Roman losses were not weighted by supply, including the hero');

$mixedTribeLosses = array(
    11 => 4, // 4 Clubswingers: 4 supply
    15 => 2, // 2 Paladins: 4 supply
    26 => 3, // 3 Haeduans: 9 supply
    40 => 1  // 1 elephant: 5 supply
);
$mixedTribePoints = calculateCombatRankingPoints($mixedTribeLosses, 0);
combatRankingAssert($mixedTribePoints === 22, 'Losses from different tribes were not weighted by their own supply');

$invalidLosses = array(1 => -5, 99 => 10, 999 => 10);
$invalidPoints = calculateCombatRankingPoints($invalidLosses, -1);
combatRankingAssert($invalidPoints === 0, 'Invalid or negative losses contributed ranking points');

echo "Combat ranking points regression: OK\n";
