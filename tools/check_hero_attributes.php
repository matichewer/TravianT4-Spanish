<?php

require_once dirname(__DIR__).'/GameEngine/Hero.php';

function heroAttributeAssert($condition,$message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

$romanHero = array('power'=>10,'itempower'=>500);
heroAttributeAssert(heroFightingStrength($romanHero,1)===1600,'Roman fighting strength is incorrect');
heroAttributeAssert(heroFightingStrength($romanHero,2)===1400,'Non-Roman fighting strength is incorrect');
heroAttributeAssert(heroFightingStrength(array('power'=>150,'itempower'=>0),1)===10100,'Fighting strength points are not capped at 100');
heroAttributeAssert(abs(heroArmyBonusFactor(50)-1.10)<0.000001,'50 army bonus points must produce a 10 percent multiplier');
heroAttributeAssert(heroArmyBonusPercent(50)===10.0,'Displayed army bonus percent is incorrect');
heroAttributeAssert(abs(heroArmyBonusFactor(150)-1.20)<0.000001,'Army bonus points are not capped at 100');

include dirname(__DIR__).'/GameEngine/Data/hero_full.php';
heroAttributeAssert(heroLevelForExperience(750,0,$hero_levels)===5,'Multiple earned levels were not resolved together');
heroAttributeAssert(heroLevelForExperience(247500,98,$hero_levels)===99,'Final configured hero level was not reached');
heroAttributeAssert(heroLevelForExperience(999999,99,$hero_levels)===99,'Hero progression exceeded the experience table');

$allResourceHero = array(
	'product'=>4,
	'r0'=>1,
	'r1'=>0,
	'r2'=>0,
	'r3'=>0,
	'r4'=>0,
	'dead'=>0,
	'wref'=>100
);
$allRates = heroVillageResourceBonus($allResourceHero,100,1);
heroAttributeAssert($allRates===array('wood'=>12.0,'clay'=>12.0,'iron'=>12.0,'crop'=>12.0),'All-resource production is incorrect');
$overCapRates = heroResourceRates(array('product'=>150,'r0'=>1,'r1'=>0,'r2'=>0,'r3'=>0,'r4'=>0),1);
heroAttributeAssert($overCapRates===array('wood'=>300.0,'clay'=>300.0,'iron'=>300.0,'crop'=>300.0),'Resource points are not capped at 100');

$focusedHero = $allResourceHero;
$focusedHero['r0'] = 0;
$focusedHero['r2'] = 1;
$focusedRates = heroVillageResourceBonus($focusedHero,100,1);
heroAttributeAssert($focusedRates===array('wood'=>0,'clay'=>40.0,'iron'=>0,'crop'=>0),'Focused resource production is incorrect');
$focusedHero['dead'] = 1;
heroAttributeAssert(array_sum(heroVillageResourceBonus($focusedHero,100,1))===0,'Dead hero produced resources');
$focusedHero['dead'] = 0;
heroAttributeAssert(array_sum(heroVillageResourceBonus($focusedHero,101,1))===0,'Hero produced resources in another village');

class HeroAttributeBattleDatabase
{
	public $heroes;

	public function __construct($heroes)
	{
		$this->heroes = $heroes;
	}

	public function getHeroData2($uid)
	{
		return $this->heroes[$uid];
	}

	public function getHeroData3($uid)
	{
		return $this->heroes[$uid];
	}

	public function getEquippedHeroItem($uid,$btype)
	{
		return false;
	}

	public function getUserField($uid,$field,$mode)
	{
		return 1;
	}

	public function modifyHero2($field,$value,$uid,$mode)
	{
		return true;
	}
}

require_once dirname(__DIR__).'/GameEngine/Battle.php';
$battleHero = array('uid'=>1,'power'=>10,'itempower'=>500,'offBonus'=>50,'defBonus'=>0,'health'=>100);
$database = new HeroAttributeBattleDatabase(array(1=>$battleHero));
$attacker = array('id'=>1,'hero'=>1);
$defender = array('id'=>2,'hero'=>0);
$battleResult = $battle->calculateBattle($attacker,$defender,0,1,1,0,1,1,4,array(),array(),0,0);
heroAttributeAssert(abs($battleResult['Attack_points']-1760)<0.000001,'Real battle path did not apply fighting strength and attack bonus');

$database = new HeroAttributeBattleDatabase(array(
	1=>array('uid'=>1,'power'=>0,'itempower'=>0,'offBonus'=>0,'defBonus'=>0,'health'=>100),
	2=>array('uid'=>2,'power'=>0,'itempower'=>0,'offBonus'=>0,'defBonus'=>50,'health'=>100)
));
$defender = array('id'=>2,'hero'=>1);
$battleResult = $battle->calculateBattle($attacker,$defender,0,1,1,0,1,1,4,array(),array(),0,0);
heroAttributeAssert(abs($battleResult['Defend_points']-120)<0.000001,'Real battle path did not apply defense bonus');

require_once dirname(__DIR__).'/GameEngine/Database.php';

$temporaryHeroTable = TB_PREFIX.'hero';
$qualifiedHeroTable = SQL_DB.'.'.TB_PREFIX.'hero';
heroAttributeAssert(
	mysqli_query($database->connection,"CREATE TEMPORARY TABLE $temporaryHeroTable AS SELECT * FROM $qualifiedHeroTable WHERE 0"),
	'Could not create temporary hero table'
);

$insert = "INSERT INTO $temporaryHeroTable"
	." (heroid,uid,level,points,experience,power,itempower,offBonus,defBonus,product,r0,r1,r2,r3,r4)"
	." VALUES (1,900001,98,1,247500,99,0,0,0,0,1,0,0,0,0)";
heroAttributeAssert(mysqli_query($database->connection,$insert),'Could not create temporary hero');

heroAttributeAssert($database->allocateHeroAttributePoint(900001,'power',100),'Valid attribute point was not allocated');
$state = $database->getHeroData(900001);
heroAttributeAssert((int)$state['points']===0 && (int)$state['power']===100,'Attribute allocation did not update both values');
heroAttributeAssert(!$database->allocateHeroAttributePoint(900001,'power',100),'Attribute allocation exceeded the cap or reused a spent point');
$state = $database->getHeroData(900001);
heroAttributeAssert((int)$state['points']===0 && (int)$state['power']===100,'Rejected allocation changed hero state');

heroAttributeAssert($database->advanceHeroLevel(1,98,99),'Final hero level was not applied');
$state = $database->getHeroData(900001);
heroAttributeAssert((int)$state['level']===99 && (int)$state['points']===4,'Level advancement awarded the wrong state');
heroAttributeAssert(!$database->advanceHeroLevel(1,98,99),'Stale concurrent level update was accepted');
$state = $database->getHeroData(900001);
heroAttributeAssert((int)$state['level']===99 && (int)$state['points']===4,'Stale level update awarded duplicate points');

$resetState = "UPDATE $temporaryHeroTable SET points=10,power=10,offBonus=5,defBonus=5,product=10,"
	."r0=0,r1=1,r2=0,r3=0,r4=0 WHERE uid=900001";
heroAttributeAssert(mysqli_query($database->connection,$resetState),'Could not prepare reset state');
heroAttributeAssert($database->resetHeroAttributes(900001),'Hero attributes were not reset');
$state = $database->getHeroData(900001);
heroAttributeAssert((int)$state['points']===40,'Attribute reset discarded unspent points');
heroAttributeAssert(
	(int)$state['power']===0 && (int)$state['offBonus']===0 && (int)$state['defBonus']===0 && (int)$state['product']===0,
	'Attribute reset did not clear allocated points'
);
heroAttributeAssert(
	(int)$state['r0']===1 && (int)$state['r1']===0 && (int)$state['r2']===0 && (int)$state['r3']===0 && (int)$state['r4']===0,
	'Attribute reset did not restore all-resource mode'
);

echo "Hero attribute regression: OK\n";
