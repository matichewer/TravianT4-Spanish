<?php
// Render each tribe's form to verify that Editar restores all eleven quantities.
$root = dirname(__DIR__);
require_once $root.'/GameEngine/Hero.php';
class TroopEditDatabase {
    public function getHeroData($uid) { return array(); }
}
$database = new TroopEditDatabase();
$session = (object)array('uid' => 100);
$village = (object)array('wid' => 100, 'unitarray' => array('hero' => 0));
for ($unit = 1; $unit <= 50; $unit++) {
    $village->unitarray['u'.$unit] = 999;
    if (!defined('U'.$unit)) { define('U'.$unit, 'Unidad '.$unit); }
}
$reportdata = null;
$editingTroopSend = true;
$troopDraft = array();
for ($position = 1; $position <= 11; $position++) {
    $troopDraft['t'.$position] = $position === 11 ? 1 : $position * 7;
}
for ($tribe = 1; $tribe <= 5; $tribe++) {
    ob_start();
    include $root.'/Templates/a2b/units_'.$tribe.'.tpl';
    $html = ob_get_clean();
    for ($position = 1; $position <= ($tribe <= 3 ? 11 : 10); $position++) {
        if (!preg_match('/name="t'.$position.'" value="'.$troopDraft['t'.$position].'"/', $html)) {
            fwrite(STDERR, "FAIL: tribu $tribe, unidad $position sin precargar\n");
            exit(1);
        }
    }
}
$page = file_get_contents($root.'/a2b.php');
$confirmation = file_get_contents($root.'/Templates/a2b/attack.tpl');
if (strpos($page, '$process = $editingTroopSend ? null : $units->procUnits($_POST);') === false
    || strpos($confirmation, 'name="edit_send"') === false
    || strpos($confirmation, '>Editar</div>') === false) {
    fwrite(STDERR, "FAIL: Editar debe omitir el envío definitivo\n");
    exit(1);
}
echo "OK: Editar no envía tropas y precarga las cantidades de tropas y héroe de cada tribu\n";
