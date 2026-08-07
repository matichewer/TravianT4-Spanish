<?php

include("GameEngine/Village.php");
$start = $generator->pageLoadTimeStart();
if(isset($_GET['ok'])){
	$database->updateUserField($session->username,'ok','0','0'); $_SESSION['ok'] = '0'; }
if(isset($_GET['newdid'])) {
	$_SESSION['wid'] = $_GET['newdid'];
	header("Location: ".$_SERVER['PHP_SELF']);
}
else {
	$building->procBuild($_GET);
}
if(isset($_GET['master']) && isset($_GET['id']) && $session->gold >= 1 && $session->goldclub && $village->master == 0) {
if($session->access!=BANNED){
// El nivel y la duración salen de las tablas del juego, no de la URL.
$masterRequest = $building->masterBuildingRequest($_GET['id'],$_GET['master']);
if($masterRequest !== false) {
$database->addBuilding($village->wid, (int)$_GET['id'], (int)$_GET['master'], 1, $masterRequest['time'], 1, $masterRequest['level']);
}
header("Location: ".$_SERVER['PHP_SELF']);
}else{
header("Location: banned.php");
}
}
include "Templates/html.tpl";
?>
<body class="v35 webkit chrome village2">
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
						<div id="content" class="village2">
<?php
include("Templates/dorf2.tpl");
if($building->NewBuilding) {
	include("Templates/Building.tpl");
}
include("Templates/build_notifications.tpl");
?>
</div>
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
