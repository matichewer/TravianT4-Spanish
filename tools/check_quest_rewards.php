<?php

$questFile = dirname(__DIR__) . '/Templates/Ajax/quest_core.tpl';
$source = file_get_contents($questFile);
if ($source === false) {
    fwrite(STDERR, "Could not read quest template.\n");
    exit(1);
}

$errors = array();
$displayedRewards = array();
$lines = file($questFile);

foreach ($lines as $lineNumber => $line) {
    if (!preg_match(
        '/"reward":\{"wood":(\d+),"clay":(\d+),"iron":(\d+),"crop":(\d+)\}/',
        $line,
        $rewardMatch
    )) {
        continue;
    }

    preg_match_all("/qst_next\('','(\d+)'/", $line, $actionMatches);
    if (empty($actionMatches[1])) {
        $errors[] = 'Resource reward without a numeric claim action on line ' . ($lineNumber + 1) . '.';
        continue;
    }

    $action = (int)end($actionMatches[1]);
    $jsonReward = array_map('intval', array_slice($rewardMatch, 1));
    if (!isset($displayedRewards[$action])) {
        $displayedRewards[$action] = array();
    }
    $displayedRewards[$action][] = $jsonReward;

    $visibleReward = array();
    foreach (array('r1', 'r2', 'r3', 'r4') as $resourceClass) {
        if (preg_match('/resources ' . $resourceClass . '.*?>(\d+)</', $line, $visibleMatch)) {
            $visibleReward[] = (int)$visibleMatch[1];
        }
    }

    // Task 3 uses standalone resource icons instead of the standard resource spans.
    if (count($visibleReward) === 4 && $visibleReward !== $jsonReward) {
        $errors[] = sprintf(
            'Visible reward and JSON metadata differ on line %d: %s vs %s.',
            $lineNumber + 1,
            implode(',', $visibleReward),
            implode(',', $jsonReward)
        );
    }
}

$awardedRewards = array();
preg_match_all("/^\s*case '(\d+)':/m", $source, $caseMatches, PREG_OFFSET_CAPTURE);
$caseCount = count($caseMatches[0]);

for ($index = 0; $index < $caseCount; $index++) {
    $action = (int)$caseMatches[1][$index][0];
    $start = $caseMatches[0][$index][1] + strlen($caseMatches[0][$index][0]);
    $end = $index + 1 < $caseCount ? $caseMatches[0][$index + 1][1] : strlen($source);
    $caseBody = substr($source, $start, $end - $start);

    preg_match_all(
        '/modifyResource\(\$session->villages\[0\],\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*1\)/',
        $caseBody,
        $rewardMatches,
        PREG_SET_ORDER
    );

    foreach ($rewardMatches as $rewardMatch) {
        if (!isset($awardedRewards[$action])) {
            $awardedRewards[$action] = array();
        }
        $awardedRewards[$action][] = array_map('intval', array_slice($rewardMatch, 1));
    }
}

$normalise = function ($rewards) {
    foreach ($rewards as &$variants) {
        usort($variants, function ($left, $right) {
            return strcmp(implode(',', $left), implode(',', $right));
        });
    }
    unset($variants);
    ksort($rewards);
    return $rewards;
};

$displayedRewards = $normalise($displayedRewards);
$awardedRewards = $normalise($awardedRewards);

if ($displayedRewards !== $awardedRewards) {
    $allActions = array_unique(array_merge(array_keys($displayedRewards), array_keys($awardedRewards)));
    sort($allActions);

    foreach ($allActions as $action) {
        $shown = isset($displayedRewards[$action]) ? $displayedRewards[$action] : array();
        $awarded = isset($awardedRewards[$action]) ? $awardedRewards[$action] : array();
        if ($shown !== $awarded) {
            $errors[] = sprintf(
                'Action %d differs: shown [%s], awarded [%s].',
                $action,
                implode(' | ', array_map(function ($reward) {
                    return implode(',', $reward);
                }, $shown)),
                implode(' | ', array_map(function ($reward) {
                    return implode(',', $reward);
                }, $awarded))
            );
        }
    }
}

$specialRewardChecks = array(
    'Task 1 instant woodcutter' => array(
        'Leñador completado al instante.',
        '$database->FinishWoodcutter($session->villages[0]);',
    ),
    'Task 2 one day Plus' => array(
        '"reward":{"plus":1}',
        '+3600*24',
    ),
    'Task 4 instant rally point' => array(
        'Plaza de reuniones completada al instante.',
        '$database->FinishRallyPoint($session->villages[0]);',
    ),
    'Task 7 gold' => array(
        '"reward":{"gold":20}',
        '$database->claimQuestGold($session->uid, 7, 8, 20)',
    ),
    'All fields level 2 gold' => array(
        '"reward":{"gold":15}',
        '$database->claimFollowupQuestGold($session->uid, $currentFquest, $nextFquest, 15)',
    ),
);

foreach ($specialRewardChecks as $name => $needles) {
    foreach ($needles as $needle) {
        if (strpos($source, $needle) === false) {
            $errors[] = $name . ' is missing expected implementation: ' . $needle;
        }
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

$resourceVariantCount = array_sum(array_map('count', $displayedRewards));
echo 'Quest reward consistency: OK (' . $resourceVariantCount . " resource variants and 5 special rewards).\n";
