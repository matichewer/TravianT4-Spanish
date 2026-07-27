<?php
// First-week data repair: seed the current score and population baseline from the
// real village population before the first medals are awarded. Do not run this in
// later weeks: it intentionally sets clp to total population, while normal weekly
// tracking keeps clp as the population gained since the previous medal reset.
include_once("GameEngine/Database.php");

$medals = mysql_query("SELECT 1 FROM " . TB_PREFIX . "medal LIMIT 1");
if(!$medals) {
    fwrite(STDERR, "Could not verify whether weekly medals were already awarded: " . mysql_error() . PHP_EOL);
    exit(1);
}
if(mysql_num_rows($medals) > 0) {
    fwrite(
        STDERR,
        "Refusing to run: first-week medals were already awarded. "
        . "Running this repair now would corrupt the current week's growth scores." . PHP_EOL
    );
    exit(1);
}

$accessLimit = defined('INCLUDE_ADMIN') && INCLUDE_ADMIN ? 10 : 8;

$result = mysql_query(
    "SELECT u.id id, COALESCE(SUM(v.pop),0) totpop
     FROM " . TB_PREFIX . "users u
     LEFT JOIN " . TB_PREFIX . "vdata v ON v.owner = u.id
     WHERE u.tribe <= 3 AND u.access < $accessLimit
     GROUP BY u.id
     ORDER BY totpop DESC, u.id ASC"
);
while ($row = mysql_fetch_assoc($result)) {
    if ($row['id'] <= 3) {
        continue;
    }
    $pop = (int)$row['totpop'];
    mysql_query("UPDATE " . TB_PREFIX . "users SET clp = $pop, oldrank = $pop WHERE id = " . $row['id']);
    echo "user {$row['id']}: score=$pop baseline=$pop\n";
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
