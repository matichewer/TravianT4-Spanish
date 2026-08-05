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
$allRatesSpeedThree = heroVillageResourceBonus($allResourceHero,100,3);
heroAttributeAssert($allRatesSpeedThree===array('wood'=>36.0,'clay'=>36.0,'iron'=>36.0,'crop'=>36.0),'Speed-three all-resource production is incorrect');
heroAttributeAssert(300+$allRatesSpeedThree['wood']===336.0,'Displayed all-resource rate was not added directly');
$overCapRates = heroResourceRates(array('product'=>150,'r0'=>1,'r1'=>0,'r2'=>0,'r3'=>0,'r4'=>0),1);
heroAttributeAssert($overCapRates===array('wood'=>300.0,'clay'=>300.0,'iron'=>300.0,'crop'=>300.0),'Resource points are not capped at 100');

$focusedHero = $allResourceHero;
$focusedHero['r0'] = 0;
$focusedHero['r2'] = 1;
$focusedRates = heroVillageResourceBonus($focusedHero,100,1);
heroAttributeAssert($focusedRates===array('wood'=>0,'clay'=>40.0,'iron'=>0,'crop'=>0),'Focused resource production is incorrect');
$focusedRatesSpeedThree = heroVillageResourceBonus($focusedHero,100,3);
heroAttributeAssert($focusedRatesSpeedThree===array('wood'=>0,'clay'=>120.0,'iron'=>0,'crop'=>0),'Speed-three focused production is incorrect');
heroAttributeAssert(300+$focusedRatesSpeedThree['clay']===420.0,'Displayed focused rate was not added directly');
$focusedHero['dead'] = 1;
heroAttributeAssert(array_sum(heroVillageResourceBonus($focusedHero,100,1))===0,'Dead hero produced resources');
$focusedHero['dead'] = 0;
heroAttributeAssert(array_sum(heroVillageResourceBonus($focusedHero,101,1))===0,'Hero produced resources in another village');

$villageEngine = file_get_contents(dirname(__DIR__).'/GameEngine/Village.php');
heroAttributeAssert($villageEngine!==false,'Could not read village engine');
$normalizedVillageEngine = preg_replace('/\s+/','',$villageEngine);
foreach(array('wood'=>'getWoodProd','clay'=>'getClayProd','iron'=>'getIronProd') as $resource=>$baseMethod){
	$directAddition = '$this->production[\''.$resource.'\']=$this->'.$baseMethod.'()+$heroProduction[\''.$resource.'\'];';
	heroAttributeAssert(
		strpos($normalizedVillageEngine,$directAddition)!==false,
		'Normal village production does not add the hero '.$resource.' rate directly'
	);
}
heroAttributeAssert(
	substr_count($normalizedVillageEngine,'+$heroProduction[\'crop\'];')===4,
	'Normal village production does not add the hero crop rate directly in every upkeep branch'
);

$heroTemplate = file_get_contents(dirname(__DIR__).'/Templates/hero.tpl');
heroAttributeAssert($heroTemplate!==false,'Could not read hero template');
heroAttributeAssert(
	strpos($heroTemplate,'Aporte directo actual: +<?php echo $selectedResourceRate; ?>/h')!==false,
	'Current hero resource bonus is missing its hourly unit'
);
heroAttributeAssert(
	strpos($heroTemplate,'<span class="current">+<?php echo $allResourceRate; ?>/h</span>')!==false,
	'All-resource option is missing its positive hourly label'
);
heroAttributeAssert(
	substr_count($heroTemplate,'<span class="current">+<?php echo $focusedResourceRate; ?>/h</span>')===4,
	'Focused resource options are missing positive hourly labels'
);
heroAttributeAssert(
	strpos($heroTemplate,'<div class="element attribName">Aldea natal</div>')!==false
	&& strpos($heroTemplate,"heroHomeVillage(\$hero)")!==false
	&& strpos($heroTemplate,'$database->getVillage($heroHomeVillageId)')!==false,
	'Hero home village is missing from the attribute summary'
);
heroAttributeAssert(
	strpos($heroTemplate,'El héroe revivirá en <b><?php echo $heroHomeVillageName')!==false
	&& strpos($heroTemplate,'deductResourcesIfAvailable($heroHomeVillageId')!==false
	&& strpos($heroTemplate,'trainHero($heroHomeVillageId, $each, 0)')!==false,
	'Hero revival is not tied to the home village'
);
heroAttributeAssert(
	strpos($heroTemplate,'<?php if($canSpendPoint){ ?><div class="availableAttributePoints">Puntos disponibles para asignar:')!==false
	&& strpos($heroTemplate,'<div class="pointsHeadline">Puntos</div>')!==false,
	'Available hero attribute points are not clearly identified'
);
foreach(array('Velocidad base: <?php echo $heroBaseSpeed; ?>','Caballo: +<?php echo $horseSpeedBonus; ?>','Espuelas: +<?php echo $spurSpeedBonus; ?>','Velocidad del servidor: ×<?php echo $heroSpeedMultiplier; ?>','Total: <?php echo $heroDisplayedSpeed; ?>') as $speedPart){
	heroAttributeAssert(strpos($heroTemplate,$speedPart)!==false,'Hero speed tooltip breakdown is incomplete');
}
foreach(array(
	'<div class="changeResourcesHeadline"><b>Recursos</b></div>',
	'Como tienes <?php echo $productPoints; ?> puntos en Recursos',
	'el héroe produce <?php echo $allResourceRate; ?> de cada recurso',
	'o <?php echo $focusedResourceRate; ?> de un recurso específico',
	'Este extra de producción se otorga a la aldea natal del héroe',
	'Puedes cambiar la aldea natal del héroe enviándolo entre tus aldeas'
) as $explanationPart){
	heroAttributeAssert(
		strpos($heroTemplate,$explanationPart)!==false,
		'Hero resource distribution explanation is incomplete'
	);
}

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

$temporaryHeroItemsTable = TB_PREFIX.'heroitems';
$qualifiedHeroItemsTable = SQL_DB.'.'.TB_PREFIX.'heroitems';
heroAttributeAssert(
	mysqli_query($database->connection,"CREATE TEMPORARY TABLE $temporaryHeroItemsTable AS SELECT * FROM $qualifiedHeroItemsTable WHERE 0"),
	'Could not create temporary hero-item table'
);

$temporaryAuctionTable = TB_PREFIX.'auction';
$qualifiedAuctionTable = SQL_DB.'.'.TB_PREFIX.'auction';
heroAttributeAssert(
	mysqli_query($database->connection,"CREATE TEMPORARY TABLE $temporaryAuctionTable AS SELECT * FROM $qualifiedAuctionTable WHERE 0"),
	'Could not create temporary auction table'
);

$bookResetState = "UPDATE $temporaryHeroTable SET points=10,power=10,offBonus=5,defBonus=5,product=10,"
	."dead=0,r0=0,r1=1,r2=0,r3=0,r4=0 WHERE uid=900001";
heroAttributeAssert(mysqli_query($database->connection,$bookResetState),'Could not prepare Book of Wisdom state');
heroAttributeAssert(
	mysqli_query(
		$database->connection,
		"INSERT INTO $temporaryHeroItemsTable (id,uid,btype,type,num,proc) VALUES (1,900001,13,110,1,0)"
	),
	'Could not create Book of Wisdom'
);
heroAttributeAssert($database->consumeBookOfWisdom(900001,1),'Book of Wisdom was not consumed');
$state = $database->getHeroData(900001);
$book = $database->getItemData(1);
heroAttributeAssert((int)$state['points']===40,'Book of Wisdom did not preserve all points');
heroAttributeAssert(
	(int)$state['power']===0 && (int)$state['offBonus']===0 && (int)$state['defBonus']===0 && (int)$state['product']===0,
	'Book of Wisdom did not clear allocated attributes'
);
heroAttributeAssert((int)$book['proc']===1,'Book of Wisdom was not marked as consumed');
heroAttributeAssert(!$database->consumeBookOfWisdom(900001,1),'Consumed Book of Wisdom was reused');

$deadHeroState = "UPDATE $temporaryHeroTable SET points=10,power=10,offBonus=5,defBonus=5,product=10,"
	."dead=1,r0=0,r1=1,r2=0,r3=0,r4=0 WHERE uid=900001";
heroAttributeAssert(mysqli_query($database->connection,$deadHeroState),'Could not prepare dead hero state');
heroAttributeAssert(
	mysqli_query(
		$database->connection,
		"INSERT INTO $temporaryHeroItemsTable (id,uid,btype,type,num,proc) VALUES (2,900001,13,110,1,0)"
	),
	'Could not create dead-hero Book of Wisdom'
);
heroAttributeAssert(!$database->consumeBookOfWisdom(900001,2),'Dead hero used a Book of Wisdom');
$state = $database->getHeroData(900001);
$book = $database->getItemData(2);
heroAttributeAssert((int)$state['points']===10 && (int)$state['power']===10,'Rejected Book use changed dead hero state');
heroAttributeAssert((int)$book['proc']===0,'Rejected Book use consumed the item');

heroAttributeAssert(
	mysqli_query(
		$database->connection,
		"INSERT INTO $temporaryHeroItemsTable (id,uid,btype,type,num,proc) VALUES"
			." (3,900002,13,110,1,0),(4,900001,13,110,1,0)"
	),
	'Could not create auction security items'
);
heroAttributeAssert(!$database->addAuction(900001,3,13,110,1),'Auction accepted another user\'s item');
heroAttributeAssert((int)$database->getItemData(3)['uid']===900002,'Rejected auction removed another user\'s item');
heroAttributeAssert(!$database->addAuction(900001,4,13,110,-1),'Auction accepted a negative amount');
heroAttributeAssert((int)$database->getItemData(4)['num']===1,'Negative auction amount changed item quantity');
heroAttributeAssert($database->addAuction(900001,4,0,0,1),'Valid Book of Wisdom auction was rejected');
heroAttributeAssert(!$database->getItemData(4),'Auctioned Book of Wisdom remained in inventory');
$auctionResult = mysqli_query(
	$database->connection,
	"SELECT owner,btype,type,num FROM $temporaryAuctionTable WHERE owner=900001 LIMIT 1"
);
$auction = $auctionResult ? mysqli_fetch_assoc($auctionResult) : false;
heroAttributeAssert(
	$auction && (int)$auction['btype']===13 && (int)$auction['type']===110 && (int)$auction['num']===1,
	'Auction did not derive Book of Wisdom data from the owned item'
);

echo "Hero attribute regression: OK\n";
