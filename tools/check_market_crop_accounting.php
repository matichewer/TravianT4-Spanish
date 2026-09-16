<?php
// Regresión de abastecimiento nocturno. Sólo usa una tabla TEMPORARY de esta conexión.
chdir(dirname(__DIR__));
require 'config/connection.php';
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('ALLOW_BURST', false);
require 'GameEngine/Automation.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
class CropAccountingDatabase {
    public $connection;
    public function __construct() {
        $this->connection = new mysqli(SQL_SERVER, SQL_USER, SQL_PASS, SQL_DB);
        $this->connection->query('CREATE TEMPORARY TABLE '.TB_PREFIX.'vdata (
            wref INT PRIMARY KEY, wood DOUBLE, clay DOUBLE, iron DOUBLE, crop DOUBLE,
            maxstore DOUBLE, maxcrop DOUBLE, lastupdate INT)');
    }
    public function query($sql) { return $this->connection->query($sql); }
    public function query_return($sql) { return $this->query($sql)->fetch_all(MYSQLI_ASSOC); }
    public function row() { return $this->query_return('SELECT * FROM '.TB_PREFIX.'vdata')[0]; }
    public function reset($crop, $lastupdate) {
        $this->query('DELETE FROM '.TB_PREFIX.'vdata');
        $this->query('INSERT INTO '.TB_PREFIX.'vdata VALUES (100, 1000, 1000, 1000, '.(float)$crop.', 400000, 226500, '.(int)$lastupdate.')');
    }
}
class CropAccountingAutomation extends Automation {
    public $clock;
    public function __construct() {}
    // Tasa constante del caso reportado; el SQL de recepción y recorte es el real.
    protected function accrueProductionBeforeChange($wref, $until) {
        global $database;
        $row = $database->row();
        $until = $until === null ? $this->clock : $until;
        $elapsed = max(0, $until - $row['lastupdate']);
        if (!$elapsed) return;
        $crop = $row['crop'] - 30556 * $elapsed / 3600;
        $database->query('UPDATE '.TB_PREFIX.'vdata SET crop = LEAST(maxcrop, '. $crop.'), lastupdate = '.(int)$until.' WHERE wref = '.(int)$wref);
    }
    public function deliver($time, $crop) {
        $this->receiveVillageResources(array('to'=>100, 'endtime'=>$time,
            'wood'=>0, 'clay'=>0, 'iron'=>0, 'crop'=>$crop));
    }
    public function prune() {
        $method = new ReflectionMethod('Automation', 'pruneResource');
        $method->setAccessible(true);
        $method->invoke($this);
    }
}
function expectCrop($expected, $label) {
    global $database;
    $actual = (float)$database->row()['crop'];
    if (abs($actual - $expected) > 0.01) {
        throw new RuntimeException($label.': esperado '.$expected.', obtenido '.$actual);
    }
    echo 'OK '.$label."\n";
}
$source = file_get_contents('GameEngine/Automation.php');
$marketStart = strpos($source, 'private function marketComplete()');
$marketEnd = strpos($source, 'const SEND_OK', $marketStart);
$marketSource = substr($source, $marketStart, $marketEnd - $marketStart);
if (strpos($marketSource, '$this->receiveVillageResources($data);') === false
    || strpos($marketSource, 'ORDER BY endtime ASC, moveid ASC') === false) {
    throw new RuntimeException('El mercado debe liquidar entregas en orden de llegada');
}
$constructorStart = strpos($source, 'public function __construct(');
$constructorEnd = strpos($source, 'private function getfieldDistance', $constructorStart);
$constructor = substr($source, $constructorStart, $constructorEnd - $constructorStart);
$prunePosition = strpos($constructor, '$this->pruneResource();');
$marketPosition = strpos($constructor, '$this->marketComplete();', strpos($constructor, '$this->oasisResourcesProduce();'));
if ($marketPosition === false || $marketPosition > $prunePosition) {
    throw new RuntimeException('Las llegadas vencidas deben preceder al recorte y la hambruna');
}
$database = new CropAccountingDatabase();
$automation = new CropAccountingAutomation();
$base = 1000000;
$database->reset(100000, $base);
for ($step = 1; $step <= 96; $step++) {
    $automation->deliver($base + $step * 300, 3000);
}
$automation->clock = $base + 8 * 3600;
$automation->prune();
expectCrop(100000 + 8 * (36000 - 30556), '8 horas de ruta sin visitas conservan el cereal');
// Estado heredado: entregas sumadas, consumo de ocho horas todavía pendiente.
$database->reset(100000 + 8 * 36000, $base);
$automation->prune();
expectCrop(143552, 'primer recorte liquida el consumo antes de limitar el stock');
$database->reset(226000, $base);
$automation->deliver($base + 300, 3000);
expectCrop(226000 - 30556 / 12 + 3000, 'el consumo libera espacio antes de una entrega');
$automation->deliver($base + 300, 3000);
expectCrop(226500, 'el excedente real se descarta inmediatamente');
$database->reset(1000, $base);
$automation->deliver($base + 300, 100);
expectCrop(1000 - 30556 / 12 + 100, 'una entrega insuficiente conserva la deuda real');
$database->reset(10000, $base + 600);
$automation->deliver($base + 300, 3000);
expectCrop(13000, 'una entrega atrasada no vuelve a descontar un período liquidado');
echo "Regresión de cereal comercial OK\n";
