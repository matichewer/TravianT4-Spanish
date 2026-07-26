<?php
// One-time data repair: the procClimbers()/removeclimberrankpopAlly() bugs left
// users.clp/oldrank and alidata.clp/oldrank with garbage values (including negative
// clp) that weeklyMedals() would otherwise use as-is to pick this week's climber
// medal. This recomputes both from real current population so the medal reflects
// actual growth. Safe to run again later (e.g. after a future medal reset) since it
// only derives values from current vdata population.
include_once("GameEngine/Database.php");

$accessLimit = defined('INCLUDE_ADMIN') && INCLUDE_ADMIN ? 10 : 8;

$result = mysql_query(
    "SELECT u.id id, COALESCE(SUM(v.pop),0) totpop
     FROM " . TB_PREFIX . "users u
     LEFT JOIN " . TB_PREFIX . "vdata v ON v.owner = u.id
     WHERE u.tribe <= 3 AND u.access < $accessLimit
     GROUP BY u.id
     ORDER BY totpop DESC, u.id ASC"
);
$position = 0;
while ($row = mysql_fetch_assoc($result)) {
    $position++;
    if ($row['id'] <= 3) {
        continue;
    }
    $pop = (int)$row['totpop'];
    mysql_query("UPDATE " . TB_PREFIX . "users SET clp = $pop, oldrank = $position WHERE id = " . $row['id']);
    echo "user {$row['id']}: pop=$pop rank=$position\n";
}

$allies = mysql_query("SELECT id FROM " . TB_PREFIX . "alidata");
while ($ally = mysql_fetch_assoc($allies)) {
    $memberPop = mysql_fetch_assoc(mysql_query(
        "SELECT COALESCE(SUM(v.pop),0) totpop FROM " . TB_PREFIX . "vdata v
         JOIN " . TB_PREFIX . "users u ON u.id = v.owner
         WHERE u.alliance = " . $ally['id']
    ));
    $pop = (int)$memberPop['totpop'];
    mysql_query("UPDATE " . TB_PREFIX . "alidata SET clp = $pop, oldrank = $pop WHERE id = " . $ally['id']);
    echo "alliance {$ally['id']}: pop=$pop\n";
}

echo "done\n";
