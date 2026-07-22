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

preg_match_all(
    '/\$claimMainQuestResources\(\d+,\s*(\d+),[^\n]*?array\((\d+),\s*(\d+),\s*(\d+),\s*(\d+)\)\)/',
    $source,
    $mainRewardMatches,
    PREG_SET_ORDER
);
foreach ($mainRewardMatches as $rewardMatch) {
    $action = (int)$rewardMatch[1];
    if (!isset($awardedRewards[$action])) {
        $awardedRewards[$action] = array();
    }
    $awardedRewards[$action][] = array_map('intval', array_slice($rewardMatch, 2));
}

$followupSwitchStart = strpos($source, 'switch($questNumber)');
$followupClaimStart = strpos($source, "if(\$_SESSION['qst'] === 24", $followupSwitchStart);
if ($followupSwitchStart === false || $followupClaimStart === false) {
    $errors[] = 'Follow-up resource claims are missing their validated reward switch.';
} else {
    $followupSwitch = substr($source, $followupSwitchStart, $followupClaimStart - $followupSwitchStart);
    preg_match_all('/case (\d+):(.*?)(?=\n\t\tcase \d+:|\n\t})/s', $followupSwitch, $followupCases, PREG_SET_ORDER);

    foreach ($followupCases as $followupCase) {
        $action = (int)$followupCase[1];
        if (strpos($followupCase[2], '$requirementMet =') === false) {
            $errors[] = 'Follow-up action ' . $action . ' has no server-side completion check.';
        }
        preg_match_all('/\$reward = array\((\d+), (\d+), (\d+), (\d+)\);/', $followupCase[2], $rewardMatches, PREG_SET_ORDER);
        foreach ($rewardMatches as $rewardMatch) {
            if (!isset($awardedRewards[$action])) {
                $awardedRewards[$action] = array();
            }
            $awardedRewards[$action][] = array_map('intval', array_slice($rewardMatch, 1));
        }
    }

    foreach (array('$requirementMet', '(int)$dataarray[$questIndex] === 0', 'claimFollowupQuestResources') as $claimGuard) {
        if (strpos(substr($source, $followupSwitchStart, 6000), $claimGuard) === false) {
            $errors[] = 'Follow-up resource claims are missing guard: ' . $claimGuard;
        }
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
        '$database->advanceQuest($session->uid, 1, 2)',
        '$database->FinishWoodcutter($session->villages[0]);',
    ),
    'Task 2 one day Plus' => array(
        '"reward":{"plus":1}',
        '$database->claimQuestPlus($session->uid, 2, 3, 86400)',
    ),
    'Task 4 instant rally point' => array(
        'Plaza de reuniones completada al instante.',
        '$database->advanceQuest($session->uid, 4, 5)',
        '$database->FinishRallyPoint($session->villages[0]);',
    ),
    'Task 7 gold' => array(
        '"reward":{"gold":20}',
        '$database->claimQuestGold($session->uid, 7, 8, 20)',
    ),
    'Tutorial skip gold' => array(
        "qst_next('','skip')",
        '$database->claimQuestGold($session->uid, 0, 23, 25)',
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

$securityChecks = array(
    'Main resource rewards are atomic' => '$database->claimQuestResources(',
    'Follow-up resource rewards are atomic' => '$database->claimFollowupQuestResources(',
    'Manual answer proofs are stored' => "\$_SESSION['quest_validated']",
);
foreach ($securityChecks as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $errors[] = $name . ': missing ' . $needle;
    }
}

if (preg_match("/updateUserField\([^\n]*'quest'/", $source)) {
    $errors[] = 'Quest progression must use conditional claim methods, not updateUserField().';
}

if (strpos($source, '$database->modifyResource($session->villages[0]') !== false) {
    $errors[] = 'Quest rewards must not use an unconditional modifyResource() call.';
}

$refreshFiles = array(
    'AJAX response' => dirname(__DIR__) . '/ajax.php',
    'quest client' => dirname(__DIR__) . '/unx.js',
    'quest template' => dirname(__DIR__) . '/Templates/quest.tpl',
);
$refreshSources = array();
foreach ($refreshFiles as $name => $file) {
    $refreshSources[$name] = file_get_contents($file);
    if ($refreshSources[$name] === false) {
        $errors[] = 'Could not read ' . $name . ' for reward refresh checks.';
        $refreshSources[$name] = '';
    }
}

$rewardRefreshChecks = array(
    'Successful claims set the server signal' => array($source, '$questRewardClaimed = true;'),
    'AJAX exposes the server signal' => array($refreshSources['AJAX response'], '"rewardClaimed":true'),
    'The client reloads after a successful claim' => array($refreshSources['quest client'], 'window.location.reload();'),
    'The task popup is reopened after reload' => array($refreshSources['quest template'], "window.sessionStorage.getItem('questRewardReopen')"),
);
foreach ($rewardRefreshChecks as $name => $check) {
    if (strpos($check[0], $check[1]) === false) {
        $errors[] = $name . ': missing ' . $check[1];
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

$resourceVariantCount = array_sum(array_map('count', $displayedRewards));
echo 'Quest reward consistency: OK (' . $resourceVariantCount . ' resource variants and ' . count($specialRewardChecks) . " special rewards).\n";
