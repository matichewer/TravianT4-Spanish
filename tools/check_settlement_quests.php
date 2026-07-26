<?php

require_once dirname(__DIR__) . '/config/connection.php';
if(DB_TYPE !== 1) {
    fwrite(STDERR, "Settlement quest integration check requires the MySQLi database driver.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/GameEngine/Database/db_MYSQLi.php';

$fieldColumns = array();
for($field = 19; $field <= 40; $field++) {
    $fieldColumns[] = "f$field INT NOT NULL DEFAULT 0";
    $fieldColumns[] = "f{$field}t INT NOT NULL DEFAULT 0";
}

$queries = array(
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "users (
        id INT UNSIGNED NOT NULL PRIMARY KEY,
        quest INT NOT NULL,
        fquest VARCHAR(30) NOT NULL,
        tribe INT NOT NULL
    ) ENGINE=MyISAM",
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "vdata (
        wref INT UNSIGNED NOT NULL PRIMARY KEY,
        owner INT UNSIGNED NOT NULL,
        wood FLOAT NOT NULL DEFAULT 0,
        clay FLOAT NOT NULL DEFAULT 0,
        iron FLOAT NOT NULL DEFAULT 0,
        crop FLOAT NOT NULL DEFAULT 0,
        exp1 INT NOT NULL DEFAULT 0,
        exp2 INT NOT NULL DEFAULT 0,
        exp3 INT NOT NULL DEFAULT 0,
        created INT NOT NULL DEFAULT 0
    ) ENGINE=MyISAM",
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "fdata (
        vref INT UNSIGNED NOT NULL PRIMARY KEY,
        " . implode(",\n", $fieldColumns) . "
    ) ENGINE=MyISAM",
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "units (
        vref INT UNSIGNED NOT NULL PRIMARY KEY,
        u10 INT NOT NULL DEFAULT 0,
        u20 INT NOT NULL DEFAULT 0,
        u30 INT NOT NULL DEFAULT 0,
        u40 INT NOT NULL DEFAULT 0,
        u50 INT NOT NULL DEFAULT 0
    ) ENGINE=MyISAM",
    "CREATE TEMPORARY TABLE " . TB_PREFIX . "movement (
        moveid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        sort_type INT NOT NULL,
        `from` INT UNSIGNED NOT NULL,
        `to` INT UNSIGNED NOT NULL,
        data VARCHAR(30) NOT NULL,
        endtime INT UNSIGNED NOT NULL,
        proc INT NOT NULL DEFAULT 0
    ) ENGINE=MyISAM",
    "INSERT INTO " . TB_PREFIX . "users VALUES
        (1, 24, '0,0,0,0,0,0,0,0,0,0,0', 1)",
    "INSERT INTO " . TB_PREFIX . "vdata (wref,owner,wood,clay,iron,crop,created) VALUES
        (100,1,100,100,100,100,100),
        (200,1,0,0,0,0,200)",
    "INSERT INTO " . TB_PREFIX . "fdata (vref) VALUES (100),(200)",
    "UPDATE " . TB_PREFIX . "fdata SET f19 = 10, f19t = 25 WHERE vref = 200",
    "INSERT INTO " . TB_PREFIX . "units (vref) VALUES (100),(200)",
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

$fetchUser = function() use ($database) {
    $result = mysqli_query(
        $database->connection,
        "SELECT fquest FROM " . TB_PREFIX . "users WHERE id = 1"
    );
    return mysqli_fetch_assoc($result);
};

$assert(
    $database->hasBuildingAtLevelForUser(1, 25, 10),
    'A residence in a non-selected owned village was not detected.'
);
$assert(
    !$database->hasBuildingAtLevelForUser(1, 26, 10),
    'A missing palace was incorrectly detected.'
);
$assert(
    !$database->hasOwnSettlersForUser(1, 1, 3),
    'Settlers were detected without owned settler units.'
);

mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "units SET u10 = 3 WHERE vref = 200");
$assert(
    $database->hasOwnSettlersForUser(1, 1, 3),
    'Owned settlers in another village were not detected.'
);
mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "units SET u10 = 0 WHERE vref = 200");

$assert(
    !$database->hasFoundedVillageForQuest(1),
    'A second village without a settlement was accepted as founded.'
);
mysqli_query(
    $database->connection,
    "INSERT INTO " . TB_PREFIX . "movement (sort_type,`from`,`to`,data,endtime,proc)
     VALUES (5,100,300,'1',1000,0)"
);
$assert(
    $database->hasSettlementAttemptForQuest(1),
    'A queued settlement did not preserve the three-settler achievement.'
);
$assert(
    !$database->hasFoundedVillageForQuest(1),
    'A settlement still in transit was accepted as a founded village.'
);

mysqli_query(
    $database->connection,
    "INSERT INTO " . TB_PREFIX . "vdata (wref,owner,wood,clay,iron,crop,created)
     VALUES (300,1,0,0,0,0,1001)"
);
mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "vdata SET exp1 = 300 WHERE wref = 100");
mysqli_query($database->connection, "UPDATE " . TB_PREFIX . "movement SET proc = 1 WHERE `to` = 300");
$assert(
    $database->hasFoundedVillageForQuest(1),
    'A completed settlement was not detected as a founded village.'
);

$assert(
    $database->markFollowupQuestAchieved(1, 9),
    'The three-settler achievement could not be persisted.'
);
$assert(
    $database->markFollowupQuestAchieved(1, 10),
    'The new-village achievement could not be persisted.'
);
$state = $fetchUser();
$assert(
    $state['fquest'] === '0,0,0,0,0,0,0,0,0,2,2',
    'Settlement achievements were stored in the wrong follow-up slots.'
);

$threeSettlersClaimed = '0,0,0,0,0,0,0,0,0,1,2';
$assert(
    $database->claimFollowupQuestResources(
        1,
        100,
        $state['fquest'],
        $threeSettlersClaimed,
        1050,
        800,
        900,
        750
    ),
    'The persisted three-settler achievement could not be claimed.'
);
$assert(
    !$database->claimFollowupQuestResources(
        1,
        100,
        $state['fquest'],
        $threeSettlersClaimed,
        1050,
        800,
        900,
        750
    ),
    'The three-settler reward was claimable twice.'
);
$assert(
    $database->markFollowupQuestAchieved(1, 9),
    'An already claimed achievement was not treated as complete.'
);
$state = $fetchUser();
$assert(
    $state['fquest'] === $threeSettlersClaimed,
    'Persisting progress overwrote an already claimed quest.'
);

$newVillageClaimed = '0,0,0,0,0,0,0,0,0,1,1';
$assert(
    $database->claimFollowupQuestResources(
        1,
        100,
        $threeSettlersClaimed,
        $newVillageClaimed,
        1600,
        2000,
        1800,
        1300
    ),
    'The persisted new-village achievement could not be claimed.'
);

$questController = file_get_contents(dirname(__DIR__) . '/Templates/Ajax/quest_core.tpl');
$assert(
    strpos($questController, '(int)$dataarray[$questIndex] !== 1 && $requirementMet') !== false,
    'The quest controller does not allow a persisted achievement to be claimed.'
);

echo "Settlement follow-up quests: OK (account-wide building, own settlers, durable achievements and real founding).\n";
