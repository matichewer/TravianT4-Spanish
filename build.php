<?php
ob_start();
include("GameEngine/Village.php");
include("GameEngine/Units.php");

if(isset($_GET['newdid'])) {
	$_SESSION['wid'] = $_GET['newdid'];
	header("Location: ".$_SERVER['PHP_SELF'].(isset($_GET['id'])?'?id='.$_GET['id']:(isset($_GET['gid'])?'?gid='.$_GET['gid']:'')));
	exit;
}
if(isset($_GET['id']) && is_scalar($_GET['id']) && (string)$_GET['id'] === '99' && $village->natar == 0){
	header("Location: dorf2.php");
	exit;
}
		if(isset($_GET['t']) && $_GET['t'] == 99 && $session->goldclub == 1) {
			if(isset($_GET['slid']) && is_numeric($_GET['slid'])) {
			$FLData = $database->getFLData($_GET['slid']);
			if($FLData['owner'] == $session->uid){
			$checked[$_GET['slid']] = 1;
			}
			}
		}
$start = $generator->pageLoadTimeStart();
if(isset($_POST['action']) && $_POST['action'] === 'cancelTroopMovement') {
	$units->cancelTroopMovement($_POST);
}
if(isset($_POST['action']) && $_POST['action'] === 'managePrisoners') {
	$units->managePrisoners($_POST);
}
$alliance->procAlliForm($_POST);
$technology->procTech($_POST);
$market->procMarket($_POST);
$market->procTradeRoutes($_POST,$_GET);
if(isset($_GET['gid']) && is_scalar($_GET['gid']) && ctype_digit((string)$_GET['gid'])) {
	$_GET['id'] = strval($building->getTypeField((int)$_GET['gid']));
} else if(isset($_POST['id']) && is_scalar($_POST['id'])) {
	$_GET['id'] = (string)$_POST['id'];
}
if(isset($_POST['t']) && is_scalar($_POST['t'])){
	$_GET['t'] = (string)$_POST['t'];
}

if(isset($_GET['id'])) {
	if(!is_scalar($_GET['id']) || !ctype_digit((string)$_GET['id']) || (int)$_GET['id'] < 1 || (int)$_GET['id'] > 40){
        $_GET['id'] = "1";
    }
	if($village->resarray['f'.$_GET['id'].'t'] == 17) {
		$market->procRemove($_GET);
	}
	if($village->resarray['f'.$_GET['id'].'t'] == 18) {
		$alliance->procAlliance($_GET);
	}
	if($village->resarray['f'.$_GET['id'].'t'] == 12 || $village->resarray['f'.$_GET['id'].'t'] == 13 || $village->resarray['f'.$_GET['id'].'t'] == 22) {
		$technology->procTechno($_GET);
	}
}
if(isset($_POST['a'], $_POST['id'], $_POST['h']) && $_POST['a'] === 'adventure' && (string)$_POST['id'] === '39') {
	$units->Adventures($_POST);
} elseif(isset($_POST['a'], $_POST['id'], $_POST['s']) && $_POST['a'] === 'new' && (string)$_POST['id'] === '39') {
	$units->Settlers($_POST);
}
if(isset($_GET['id'])){
$automation->isWinner();
}
include "Templates/html.tpl";
?>
<body class="v35 gecko build"> 
	<div id="wrapper"> 
		<img id="staticElements" src="img/x.gif" alt="" /> 
		<div id="logoutContainer"> 
			<a id="logout" href="logout.php" title="<?php echo LOGOUT; ?>">&nbsp;</a> 
		</div> 
		<div class="bodyWrapper"> 
			<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" /> 
			<div id="header"> 
				<div id="mtop">
					<a id="logo" href="<?php echo HOMEPAGE; ?>" target="_blank" title="<?php echo SERVER_NAME ?>"></a>
					<?php
						include("Templates/navigation.tpl");
					?>
<div class="clear"></div> 
</div> 
</div>
					<div id="mid">
<a id="ingameManual" href="help.php"><img class="question" alt="Ayuda" src="img/x.gif"></a>
												<div class="clear"></div> 
						<div id="contentOuterContainer"> 
							<div class="contentTitle">&nbsp;</div> 
							<div class="contentContainer"> 
								<div id="content" class="build">

<?php

if(isset($_GET['id'])) {
	if(isset($_GET['s']))
	{
		if(!is_scalar($_GET['s']) || !ctype_digit((string)$_GET['s'])) {
			$_GET['s'] = null;
		}
	}
	if(isset($_GET['t']))
	{
		if(!is_scalar($_GET['t']) || !ctype_digit((string)$_GET['t'])) {
			$_GET['t'] = null;
		}
	}
	if(!is_scalar($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
		$_GET['id'] = "1";
	}
	$id = $_GET['id'];
	if($id=='99' AND $village->resarray['f99t'] == 40){
	include("Templates/Build/ww.tpl");
	} else
	if($village->resarray['f'.$_GET['id'].'t'] == 0 && $_GET['id'] >= 19) {
		include("Templates/Build/avaliable.tpl");
	}
	else {
		if(isset($_GET['t'])) {
			if($_GET['t'] == 1) {
			$_SESSION['loadMarket'] = 1;
			}
			include("Templates/Build/".$village->resarray['f'.$_GET['id'].'t']."_".$_GET['t'].".tpl");
		} else
		if(isset($_GET['s'])) {
			include("Templates/Build/".$village->resarray['f'.$_GET['id'].'t']."_".$_GET['s'].".tpl");
		}
		else {
			include("Templates/Build/".$village->resarray['f'.$_GET['id'].'t'].".tpl");
		}
		if(isset($_GET['t']) && $_GET['t'] == 99 && $session->goldclub == 1) {

			if(isset($_GET['action']) && $_GET['action'] == 'addList') {
				include("Templates/goldClub/farmlist_add.tpl");
			}
			if(isset($_GET['action'],$_GET['lid']) && $_GET['action'] == 'showSlot' && $_GET['lid']) {
				include("Templates/goldClub/farmlist_addraid.tpl");
			}elseif(isset($_GET['action'],$_GET['eid']) && $_GET['action'] == 'showSlot' && $_GET['eid']) {
				include("Templates/goldClub/farmlist_editraid.tpl");
			}
			if(isset($_GET['action'], $_GET['lid']) && $_GET['action'] == 'deleteList') {
				$database->delFarmList($_GET['lid'], $session->uid);
				header("Location: build.php?id=39&t=99");
				exit;
			}elseif(isset($_GET['action'], $_GET['eid']) && $_GET['action'] == 'deleteSlot') {
				$database->delSlotFarm($_GET['eid']);
				header("Location: build.php?id=39&t=99");
				exit;
			}
			if(isset($_POST['action']) && $_POST['action'] == 'startRaid'){
			if($session->access != BANNED){
			include ("Templates/a2b/startRaid.tpl");
			}else{
			header("Location: banned.php");
			exit;
			}
			}
		}
		if(isset($_GET['t']) && $_GET['t'] == 100 && $session->goldclub == 1) {
			if(isset($_GET['enable'])) {
			$database->updateUserField($session->uid, "evasion", 1, 1);
			header("Location: build.php?id=39&t=100");
			exit;
			}else if(isset($_GET['disable'])){
			$database->updateUserField($session->uid, "evasion", 0, 1);
			header("Location: build.php?id=39&t=100");
			exit;
			}
		}
	}
}
if($id < 39 || $village->resarray['f'.$id.'t'] > 0){
?>
<div class="clear">&nbsp;</div>
</div>
<?php } ?>
<div class="clear"></div>
</div>
<div class="contentFooter">&nbsp;</div>
</div>                    
<?php
include("Templates/sideinfo.tpl");
include("Templates/footer.tpl");
include("Templates/header.tpl");
include("Templates/res.tpl");
include("Templates/vname.tpl");
include("Templates/quest.tpl");
?>

</div>
<div id="ce"></div>
</div>
</body>
</html>
