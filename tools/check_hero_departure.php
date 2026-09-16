<?php
require_once dirname(__DIR__).'/GameEngine/Units.php';
require_once dirname(__DIR__).'/GameEngine/Accounts.php';
function departureAssert($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
}
class DepartureDatabase {
    public $hero = array('home'=>100, 'wref'=>200, 'dead'=>0);
    public $type = 3;
    public function getHeroData($uid) { return $this->hero; }
    public function getA2b($key, $time) {
        $data = array('to_vid'=>300, 'type'=>$this->type);
        for ($i=1; $i<=11; $i++) { $data['u'.$i] = $i===11 ? 1 : 0; }
        return $data;
    }
    public function getVillageState($id) { return true; }
    public function isVillageOases($id) { return 0; }
    public function hasBeginnerProtection($id) { return 0; }
    public function getVillageField($id, $field) { return 900; }
    public function getUserField($uid, $field, $mode) { return 2; }
    public function claimA2b($key, $time) { throw new RuntimeException('Invalid hero reached troop dispatch'); }
}
class DepartureForm {
    public $errors = array();
    public function addError($key, $value) { $this->errors[] = $value; }
    public function returnErrors() { return count($this->errors); }
    public function getErrors() { return $this->errors; }
}
class DepartureGenerator {
    public function getBaseID($x, $y) { return 300; }
}
$database = new DepartureDatabase();
$generator = new DepartureGenerator();
$session = (object)array('uid'=>800, 'tribe'=>1);
$village = (object)array('wid'=>200, 'unitarray'=>array('hero'=>1));
for ($i=1; $i<=10; $i++) { $village->unitarray['u'.$i] = 0; }
$_SESSION = $_POST = array();
foreach (array(2,3,4) as $type) {
    $database->type = $type;
    foreach (array('loadUnits','sendTroops') as $method) {
        $form = new DepartureForm();
        $post = array('x'=>1,'y'=>1,'dname'=>'','c'=>$type,'timestamp_checksum'=>'abc123','timestamp'=>'1');
        for ($i=1; $i<=11; $i++) { $post['t'.$i] = $i===11 ? 1 : 0; }
        $call = new ReflectionMethod('Units', $method);
        $call->setAccessible(true);
        $call->invoke($units, $post);
        departureAssert(count($form->errors)===1 && strpos($form->errors[0], 'aldea natal')!==false, "$method allowed a hero outside home for type $type");
    }
}
$hero = array('home'=>100,'wref'=>100,'dead'=>0);
departureAssert(heroCanDepartFromVillage($hero,100,1),'Available home hero rejected');
departureAssert(!heroCanDepartFromVillage($hero,100,0),'Absent hero allowed');
$hero['dead'] = 1;
departureAssert(!heroCanDepartFromVillage($hero,100,1),'Dead hero allowed');
$hero['dead'] = 0;
$hero['home'] = 200;
departureAssert(heroCanDepartFromVillage($hero,200,1),'Explicit new home rejected');
departureAssert(!heroCanDepartFromVillage($hero,100,1),'Old home still allowed');
echo "Hero departure checks passed.\n";
