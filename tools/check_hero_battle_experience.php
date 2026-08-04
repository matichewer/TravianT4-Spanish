<?php
// Un héroe caído vale 6 de población para el rival. Antes las bajas de héroes
// defensores se resolvían después de darle experiencia al atacante, así que el
// atacante nunca cobraba esos 6 y el defensor sí: acá se comprueba la simetría.

function heroBattleExperienceAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

class HeroBattleExperienceDatabase {
	public $heroes = array();
	public $experience = array();

	public function __construct($heroes) {
		$this->heroes = $heroes;
	}

	public function getHeroData2($uid) {
		return isset($this->heroes[$uid]) ? $this->heroes[$uid] : false;
	}

	public function getHeroData3($uid) {
		return $this->getHeroData2($uid);
	}

	public function getEquippedHeroItem($uid, $btype) {
		return false;
	}

	public function getUserField($uid, $field, $mode) {
		return 1;
	}

	public function getABTech($villageId) {
		return array();
	}

	public function modifyHero2($column, $value, $uid, $mode) {
		if($column === 'experience') {
			$uid = (int)$uid;
			$this->experience[$uid] = (isset($this->experience[$uid]) ? $this->experience[$uid] : 0) + (int)$value;
		}
		return true;
	}
}

function heroBattleExperienceUnits($tribe, $hero) {
	$units = array('hero' => $hero);
	for($unit = 1; $unit <= 50; $unit++) {
		$units['u'.$unit] = 0;
	}
	$units['id'] = $tribe;
	return $units;
}

require_once dirname(__DIR__).'/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__).'/GameEngine/Battle.php';

// El uid tiene que coincidir con el dueño, que es con lo que se busca al héroe.
function heroBattleExperienceHero($uid, $power) {
	return array('uid' => $uid, 'power' => $power, 'itempower' => 0, 'offBonus' => 0, 'defBonus' => 0, 'health' => 100);
}

// El atacante arrasa: su héroe sobrevive y mata al héroe defensor.
$database = new HeroBattleExperienceDatabase(array(
	1 => heroBattleExperienceHero(1, 100),
	2 => heroBattleExperienceHero(2, 0)
));
$attacker = heroBattleExperienceUnits(1, 1);
$defender = heroBattleExperienceUnits(2, 1);
$result = $battle->calculateBattle($attacker, $defender, 0, 1, 1, 0, 1, 1, 3, array(), array(), 0, 0);

heroBattleExperienceAssert(
	(int)$result['deadherodef'] === 1,
	'El héroe defensor no murió en un ataque perdido por completo'
);
heroBattleExperienceAssert(
	(int)$result['casualties_attacker'][11] === 0,
	'El héroe atacante murió pese a ganar sin bajas'
);
heroBattleExperienceAssert(
	isset($database->experience[1]) && $database->experience[1] === 6,
	'El héroe atacante no cobró los 6 de experiencia por matar al héroe defensor'
);
heroBattleExperienceAssert(
	!isset($database->experience[2]) || $database->experience[2] === 0,
	'El héroe defensor cobró experiencia sin matar nada'
);

// Espejo: la defensa arrasa y el héroe atacante cae.
$database = new HeroBattleExperienceDatabase(array(
	1 => heroBattleExperienceHero(1, 0),
	2 => heroBattleExperienceHero(2, 100)
));
$attacker = heroBattleExperienceUnits(1, 1);
$defender = heroBattleExperienceUnits(2, 1);
$result = $battle->calculateBattle($attacker, $defender, 0, 1, 1, 0, 1, 1, 3, array(), array(), 0, 0);

heroBattleExperienceAssert(
	(int)$result['casualties_attacker'][11] === 1,
	'El héroe atacante no murió en un ataque perdido por completo'
);
heroBattleExperienceAssert(
	(int)$result['deadherodef'] === 0,
	'El héroe defensor murió pese a ganar sin bajas'
);
heroBattleExperienceAssert(
	isset($database->experience[2]) && $database->experience[2] === 6,
	'El héroe defensor no cobró los 6 de experiencia por matar al héroe atacante'
);

echo "Hero battle experience regression: OK\n";
