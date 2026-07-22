<?php

require_once dirname(__DIR__) . '/config/connection.php';
if(DB_TYPE !== 1) {
    fwrite(STDERR, "Quest claim integration check requires the MySQLi database driver.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/GameEngine/Database/db_MYSQLi.php';

$queries = array(
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "users (
        id INT UNSIGNED NOT NULL PRIMARY KEY,
        quest INT NOT NULL,
        quest_choose INT NOT NULL DEFAULT 0,
        fquest VARCHAR(30) NOT NULL,
        gold INT UNSIGNED NOT NULL DEFAULT 0,
        plus INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=MyISAM",
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "vdata (
        wref INT UNSIGNED NOT NULL PRIMARY KEY,
        owner INT UNSIGNED NOT NULL,
        wood FLOAT NOT NULL,
        clay FLOAT NOT NULL,
        iron FLOAT NOT NULL,
        crop FLOAT NOT NULL
    ) ENGINE=MyISAM",
    "INSERT INTO " . TB_PREFIX . "users (id, quest, fquest, gold, plus) VALUES (1, 3, '0,0,0,0,0,0,0,0,0,0,0', 10, UNIX_TIMESTAMP() + 120)",
    "INSERT INTO " . TB_PREFIX . "vdata VALUES (100, 1, 100, 100, 100, 100)",
);

foreach($queries as $query) {
    if(!mysqli_query($database->connection, $query)) {
        fwrite(STDERR, mysqli_error($database->connection) . "\n");
        exit(1);
    }
}

$assert = function($condition, $message) {
    if(!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$fetchState = function() use ($database) {
    $result = mysqli_query(
        $database->connection,
        "SELECT u.quest, u.quest_choose, u.fquest, u.gold, u.plus, v.wood, v.clay, v.iron, v.crop
         FROM " . TB_PREFIX . "users AS u
         INNER JOIN " . TB_PREFIX . "vdata AS v ON v.owner = u.id
         WHERE u.id = 1 AND v.wref = 100"
    );
    return mysqli_fetch_assoc($result);
};

$assert($database->claimQuestResources(1, 100, 3, 4, 30, 60, 30, 20), 'Initial resource claim failed.');
$assert(!$database->claimQuestResources(1, 100, 3, 4, 30, 60, 30, 20), 'Repeated resource claim was accepted.');
$state = $fetchState();
$assert((int)$state['quest'] === 4, 'Resource claim did not advance the quest.');
$assert((int)$state['wood'] === 130 && (int)$state['clay'] === 160 && (int)$state['iron'] === 130 && (int)$state['crop'] === 120, 'Resource claim awarded an incorrect amount.');

$assert(!$database->advanceQuest(1, 3, 18, 1), 'Out-of-order quest transition was accepted.');
$assert($database->advanceQuest(1, 4, 17), 'Valid quest transition failed.');
$assert($database->advanceQuest(1, 17, 18, 1), 'Quest branch selection failed.');
$state = $fetchState();
$assert((int)$state['quest'] === 18 && (int)$state['quest_choose'] === 1, 'Quest branch was not stored atomically.');

mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "users SET quest = 2 WHERE id = 1");
$plusBefore = (int)$fetchState()['plus'];
$assert($database->claimQuestPlus(1, 2, 3, 86400), 'Plus claim failed.');
$assert(!$database->claimQuestPlus(1, 2, 3, 86400), 'Repeated Plus claim was accepted.');
$state = $fetchState();
$assert((int)$state['plus'] === $plusBefore + 86400, 'Plus claim did not preserve existing Plus time.');

mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "users SET quest = 7 WHERE id = 1");
$assert($database->claimQuestGold(1, 7, 8, 20), 'Gold claim failed.');
$assert(!$database->claimQuestGold(1, 7, 8, 20), 'Repeated gold claim was accepted.');
$state = $fetchState();
$assert((int)$state['gold'] === 30, 'Gold claim awarded an incorrect amount.');

mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "users SET quest = 24 WHERE id = 1");
$currentFquest = '0,0,0,0,0,0,0,0,0,0,0';
$nextFquest = '0,1,0,0,0,0,0,0,0,0,0';
$assert($database->claimFollowupQuestResources(1, 100, $currentFquest, $nextFquest, 140, 200, 180, 200), 'Follow-up resource claim failed.');
$assert(!$database->claimFollowupQuestResources(1, 100, $currentFquest, $nextFquest, 140, 200, 180, 200), 'Repeated follow-up resource claim was accepted.');
$state = $fetchState();
$assert($state['fquest'] === $nextFquest, 'Follow-up claim did not mark the task as collected.');
$assert((int)$state['wood'] === 270 && (int)$state['clay'] === 360 && (int)$state['iron'] === 310 && (int)$state['crop'] === 320, 'Follow-up claim awarded an incorrect amount.');

echo "Quest claim atomicity: OK (resources, Plus, gold, branches and follow-up rewards).\n";
