<?php
include("GameEngine/Village.php");
$start = $generator->pageLoadTimeStart();

if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']==='POST'
	&& isset($_POST['a']) && $_POST['a']==='disposeHeroItem'){
	$tokenIsValid = isset($_POST['c']) && is_scalar($_POST['c'])
		&& hash_equals((string)$session->mchecker,(string)$_POST['c']);
	if(!$tokenIsValid){
		$message = "La solicitud expiró. Vuelve a intentarlo.";
	}else{
		$itemId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
		$action = isset($_POST['disposalAction']) && is_scalar($_POST['disposalAction'])
			? (string)$_POST['disposalAction'] : '';
		$result = $database->disposeHeroItem((int)$session->uid,$itemId,$amount,$action);
		switch($result['status']){
			case 'success':
				$message = $result['action']==='liquidate'
					? "Objeto liquidado. Recibiste ".(int)$result['silver']." de plata."
					: "Objeto descartado definitivamente.";
				break;
			case 'too_small':
				$message = "Debes liquidar al menos ".(int)$result['minimum']." unidades para recibir 1 de plata.";
				break;
			case 'invalid_amount':
				$message = "La cantidad elegida no es válida para este objeto.";
				break;
			case 'unavailable':
				$message = "El objeto o la cantidad ya no están disponibles.";
				break;
			case 'busy':
				$message = "El inventario está procesando otra operación. Inténtalo nuevamente.";
				break;
			default:
				$message = "No se pudo completar la operación. El objeto no fue procesado.";
		}
	}
	$_SESSION['auctionBidMessage'] = $message;
	header("Location: hero_auction.php?action=sell");
	exit;
}

include "Templates/html.tpl";

// avoid division by zero
mysql_query("DELETE FROM ".TB_PREFIX."auction WHERE silver < 1 and owner = '".$session->uid."'");
?>
<body class="v35 webkit chrome hero_auction">
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
								<div id="content" class="hero_auction"><h1 class="titleInHeader">Héroe</h1>

<div class="contentNavi subNavi">
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_inventory.php"><span class="tabItem">Atributos</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero.php"><span class="tabItem">Apariencia</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_adventure.php"><span class="tabItem">Aventuras</span></a></div>
				</div>
				<div class="container active">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_auction.php"><span class="tabItem">Subasta</span></a></div>
				</div><div class="clear"></div>
		</div><script type="text/javascript">
					window.addEvent('domready', function()
					{
						$$('.subNavi').each(function(element)
						{
							new Travian.Game.Menu(element);
						});
					});
				</script>

<?php
$bidError = '';
if(isset($_SESSION['auctionBidMessage'])) {
	$bidError = $_SESSION['auctionBidMessage'];
	unset($_SESSION['auctionBidMessage']);
}

if(isset($_GET['action'], $_GET['abort']) && $_GET['action'] == 'sell') {
	$tokenIsValid = isset($_GET['c']) && is_scalar($_GET['c'])
		&& hash_equals((string)$session->mchecker,(string)$_GET['c']);
	if(!$tokenIsValid || !$database->delAuction((int) $_GET['abort'], (int) $session->uid)) {
		$bidError = "No se pudo cancelar la subasta. Sólo puedes cancelar una venta propia, vigente y sin ofertas.";
	}
}
$sql = mysql_query("SELECT * FROM ".TB_PREFIX."auction WHERE finish = 0 and owner = '".$session->uid."'");
$query = mysql_num_rows($sql);
if(isset($_GET['action'],$_POST['a']) && $_GET['action']=='sell' && $_POST['a']=='e45'){
	$tokenIsValid = isset($_POST['c']) && is_scalar($_POST['c'])
		&& hash_equals((string)$session->mchecker,(string)$_POST['c']);
	$itemId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
	if(!$tokenIsValid){
		$bidError = "La solicitud de venta expiró. Vuelve a intentarlo.";
	}elseif($query>=5){
		$bidError = "Ya tienes el máximo de cinco subastas activas.";
	}elseif(!$database->addAuction((int)$session->uid,$itemId,0,0,$amount)){
		$bidError = "No se pudo poner el objeto en venta. Comprueba que siga disponible y que la cantidad sea válida.";
	}
}

if(isset($_POST['a'], $_POST['action']) && in_array($_POST['action'], array('buy', 'bids'), true)) {
	$auctionId = (int) $_POST['a'];
	$maxBid = isset($_POST['maxBid']) ? (int) $_POST['maxBid'] : 0;
	$bidResult = $database->placeAuctionBid($auctionId, (int) $session->uid, $maxBid);
	switch($bidResult['status']) {
		case 'winning':
			$message = "Tu oferta fue registrada. Por ahora eres el mejor postor.";
			break;
		case 'outbid':
			$message = "Tu oferta fue superada por la oferta máxima de otro jugador.";
			break;
		case 'too_low':
			$message = "Oferta demasiado baja. Debes ofrecer al menos ".(int) $bidResult['minimum']." de plata.";
			break;
		case 'insufficient':
			$message = "No tienes suficiente plata para esta oferta.";
			break;
		case 'own':
			$message = "No puedes ofertar por tu propia subasta.";
			break;
		case 'closed':
		case 'missing':
			$message = "La subasta ya finalizó o dejó de estar disponible.";
			break;
		case 'busy':
			$message = "La subasta está procesando otra oferta. Inténtalo nuevamente.";
			break;
		default:
			$message = "No se pudo registrar la oferta.";
	}

	$_SESSION['auctionBidMessage'] = $message;
	$params = array('action' => $_POST['action']);
	if(isset($_POST['page']) && (int) $_POST['page'] > 0) {
		$params['page'] = (int) $_POST['page'];
	}
	if(isset($_POST['filter']) && (int) $_POST['filter'] > 0) {
		$params['filter'] = (int) $_POST['filter'];
	}
	if(in_array($bidResult['status'], array('winning', 'outbid', 'too_low', 'insufficient'), true)) {
		$params['a'] = $auctionId;
	}
	header("Location: hero_auction.php?".http_build_query($params));
	exit;
}

include("Templates/Auction/menu.tpl");
if(isset($_GET['action'])){
	if($_GET['action'] == 'buy'){
		include("Templates/Auction/buy.tpl");
	} elseif($_GET['action'] == 'sell'){
		include("Templates/Auction/sell.tpl");
	} elseif($_GET['action'] == 'bids'){
		include("Templates/Auction/bids.tpl");
	}
} else {
		include("Templates/Auction/buy.tpl");
	}
?>

<div class="clear">&nbsp;</div>
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
