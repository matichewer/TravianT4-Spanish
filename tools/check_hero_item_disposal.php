<?php

require_once dirname(__DIR__).'/GameEngine/Hero.php';
require_once dirname(__DIR__).'/GameEngine/Database.php';

function heroItemDisposalAssert($condition,$message)
{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

heroItemDisposalAssert(heroItemIsAuctionStackable(7),'Bandages must remain stackable');
heroItemDisposalAssert(heroItemIsAuctionStackable(14),'Law tablets must remain stackable');
heroItemDisposalAssert(!heroItemIsAuctionStackable(12),'Buckets must remain non-stackable');
heroItemDisposalAssert(!heroItemIsAuctionStackable(15),'Artwork must preserve the existing auction rule');
heroItemDisposalAssert(heroItemAuctionStartingPrice(10,25)===25,'Stackable auction price is incorrect');
heroItemDisposalAssert(heroItemAuctionStartingPrice(1,1)===100,'Equipment auction price is incorrect');
heroItemDisposalAssert(heroItemLiquidationReward(10,9)===0,'Small stack reward must round down to zero');
heroItemDisposalAssert(heroItemLiquidationReward(10,10)===1,'Minimum stack reward is incorrect');
heroItemDisposalAssert(heroItemLiquidationReward(1,1)===10,'Equipment reward is incorrect');

$itemsTable = TB_PREFIX.'heroitems';
$usersTable = TB_PREFIX.'users';
heroItemDisposalAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $itemsTable ("
	."id INT UNSIGNED NOT NULL AUTO_INCREMENT, uid INT UNSIGNED NOT NULL, btype INT UNSIGNED NOT NULL,"
	."type INT UNSIGNED NOT NULL, num INT NOT NULL, proc INT UNSIGNED NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM"),
	'Could not create temporary hero item table');
heroItemDisposalAssert(mysqli_query($database->connection,
	"CREATE TEMPORARY TABLE $usersTable (id INT UNSIGNED NOT NULL, silver INT UNSIGNED NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM"),
	'Could not create temporary user table');
heroItemDisposalAssert(mysqli_query($database->connection,
	"INSERT INTO $usersTable (id,silver) VALUES (910001,50),(910002,70)"),
	'Could not seed temporary users');
heroItemDisposalAssert(mysqli_query($database->connection,
	"INSERT INTO $itemsTable (id,uid,btype,type,num,proc) VALUES"
	." (1,910001,10,0,25,0),"
	." (2,910001,1,1,1,0),"
	." (3,910002,10,0,20,0),"
	." (4,910001,2,82,1,1),"
	." (5,910001,10,0,9,0),"
	." (6,910001,11,0,5,0),"
	." (7,999999,1,1,1,0),"
	." (8,910001,10,0,10,0)"),
	'Could not seed temporary items');

$result = $database->disposeHeroItem(910001,1,10,'liquidate');
heroItemDisposalAssert($result['status']==='success' && $result['silver']===1,'Partial liquidation failed');
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT num FROM $itemsTable WHERE id=1"));
$user = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT silver FROM $usersTable WHERE id=910001"));
heroItemDisposalAssert((int)$item['num']===15 && (int)$user['silver']===51,'Partial liquidation changed the wrong values');

$result = $database->disposeHeroItem(910001,1,20,'liquidate');
heroItemDisposalAssert($result['status']==='invalid_amount','Excess quantity was accepted');
$result = $database->disposeHeroItem(910001,1,15,'liquidate');
heroItemDisposalAssert($result['status']==='success' && $result['silver']===1,'Whole stack liquidation failed');
heroItemDisposalAssert(mysqli_num_rows(mysqli_query($database->connection,"SELECT id FROM $itemsTable WHERE id=1"))===0,'Whole stack was not removed');
$result = $database->disposeHeroItem(910001,1,15,'liquidate');
heroItemDisposalAssert($result['status']==='unavailable','Repeated liquidation was accepted');

$result = $database->disposeHeroItem(910001,2,1,'liquidate');
heroItemDisposalAssert($result['status']==='success' && $result['silver']===10,'Equipment liquidation failed');
$user = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT silver FROM $usersTable WHERE id=910001"));
heroItemDisposalAssert((int)$user['silver']===62,'Equipment liquidation credited the wrong silver');

heroItemDisposalAssert($database->disposeHeroItem(910001,3,10,'discard')['status']==='unavailable','Foreign item was disposable');
heroItemDisposalAssert($database->disposeHeroItem(910001,4,1,'discard')['status']==='unavailable','Equipped item was disposable');
heroItemDisposalAssert($database->disposeHeroItem(910001,5,9,'liquidate')['status']==='too_small','Small liquidation was accepted');
heroItemDisposalAssert($database->disposeHeroItem(910001,5,0,'discard')['status']==='invalid','Zero quantity was accepted');

$result = $database->disposeHeroItem(910001,6,3,'discard');
heroItemDisposalAssert($result['status']==='success' && $result['silver']===0,'Partial discard failed');
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT num FROM $itemsTable WHERE id=6"));
heroItemDisposalAssert((int)$item['num']===2,'Partial discard removed the wrong amount');
heroItemDisposalAssert($database->disposeHeroItem(910001,6,2,'discard')['status']==='success','Whole discard failed');
heroItemDisposalAssert(mysqli_num_rows(mysqli_query($database->connection,"SELECT id FROM $itemsTable WHERE id=6"))===0,'Whole discard left an item row');

$result = $database->disposeHeroItem(999999,7,1,'liquidate');
heroItemDisposalAssert($result['status']==='error','Missing user did not trigger the compensation path');
$item = mysqli_fetch_assoc(mysqli_query($database->connection,"SELECT num,proc FROM $itemsTable WHERE id=7"));
heroItemDisposalAssert((int)$item['num']===1 && (int)$item['proc']===0,'Failed credit did not restore the item');

$secondConnection = mysqli_connect(SQL_SERVER,SQL_USER,SQL_PASS,SQL_DB);
heroItemDisposalAssert($secondConnection!==false,'Could not create second database connection');
$lockName = 'travian_auction_'.sha1(SQL_DB.':'.TB_PREFIX);
$escapedLockName = mysqli_real_escape_string($secondConnection,$lockName);
$lockRow = mysqli_fetch_assoc(mysqli_query($secondConnection,"SELECT GET_LOCK('$escapedLockName',0) AS acquired"));
heroItemDisposalAssert((int)$lockRow['acquired']===1,'Second connection could not acquire the shared lock');
$result = $database->disposeHeroItem(910001,8,10,'liquidate');
heroItemDisposalAssert($result['status']==='busy','Concurrent disposal bypassed the shared lock');
mysqli_query($secondConnection,"SELECT RELEASE_LOCK('$escapedLockName')");
mysqli_close($secondConnection);
$result = $database->disposeHeroItem(910001,8,10,'liquidate');
heroItemDisposalAssert($result['status']==='success','Disposal did not resume after lock release');

$controller = file_get_contents(dirname(__DIR__).'/hero_auction.php');
$template = file_get_contents(dirname(__DIR__).'/Templates/Auction/sell.tpl');
heroItemDisposalAssert(strpos($controller,"\$_POST['a']==='disposeHeroItem'")!==false,'Disposal POST handler is missing');
heroItemDisposalAssert(strpos($controller,'hash_equals((string)$session->mchecker')!==false,'Disposal CSRF validation is missing');
heroItemDisposalAssert(strpos($template,'Gestionar objetos no deseados')!==false,'Disposal controls are missing');
heroItemDisposalAssert(strpos($template,"submitHeroItemDisposal('liquidate')")!==false,'Liquidation control is missing');
heroItemDisposalAssert(strpos($template,"submitHeroItemDisposal('discard')")!==false,'Discard control is missing');
heroItemDisposalAssert(strpos($template,'confirm(message)')!==false,'Irreversible action confirmation is missing');

echo "Hero item disposal checks passed.\n";
