<?php
##################################################################
## Page:        winner.php                                      ##
## Description: When the player builds Wonder of the World      ##
##              to level 100 the winner details are shown.      ##
##              tells the players the game is over              ##
## Authors:     aggenkeech - and a little help from Eyas95      ##
## Created:     31/03/2012                                      ##
##################################################################
include("GameEngine/Village.php");
$start = $generator->pageLoadTimeStart();
if(isset($_GET['newdid'])) {
	$_SESSION['wid'] = $_GET['newdid'];
	header("Location: ".$_SERVER['PHP_SELF']);
}
else {
	$building->procBuild($_GET);
}

## Get Rankings for Ranking Section
## Top 3 Population
$q = "
	SELECT ".TB_PREFIX."users.id userid, ".TB_PREFIX."users.username username,".TB_PREFIX."users.alliance alliance, (
	SELECT SUM( ".TB_PREFIX."vdata.pop )
	FROM ".TB_PREFIX."vdata
	WHERE ".TB_PREFIX."vdata.owner = userid
	)totalpop, (
		SELECT COUNT( " . TB_PREFIX . "vdata.wref )
	FROM " . TB_PREFIX . "vdata
	WHERE " . TB_PREFIX . "vdata.owner = userid AND type != 99
	)totalvillages, (
	SELECT " . TB_PREFIX . "alidata.tag
	FROM " . TB_PREFIX . "alidata, " . TB_PREFIX . "users
	WHERE " . TB_PREFIX . "alidata.id = " . TB_PREFIX . "users.alliance
	AND " . TB_PREFIX . "users.id = userid
	)allitag
	FROM " . TB_PREFIX . "users
	WHERE " . TB_PREFIX . "users.access < " . (INCLUDE_ADMIN ? "10" : "8") . "
	ORDER BY totalpop DESC, totalvillages DESC, username ASC";

	$result = (mysql_query($q));
	while($row = mysql_fetch_assoc($result))
	{
		$datas[] = $row;
	}
		foreach($datas as $result)
	{
		$value['userid'] = $result['userid'];
		$value['username'] = $result['username'];
		$value['alliance'] = $result['alliance'];
		$value['aname'] = $result['allitag'];
		$value['totalpop'] = $result['totalpop'];
		$value['totalvillage'] = $result['totalvillages'];
	}
	## Top Attacker
	$q = "
	SELECT " . TB_PREFIX . "users.id userid, " . TB_PREFIX . "users.username username, " . TB_PREFIX . "users.apall,  (
	SELECT COUNT( " . TB_PREFIX . "vdata.wref )
	FROM " . TB_PREFIX . "vdata
	WHERE " . TB_PREFIX . "vdata.owner = userid AND type != 99
	)totalvillages, (
	SELECT SUM( " . TB_PREFIX . "vdata.pop )
	FROM " . TB_PREFIX . "vdata
			WHERE " . TB_PREFIX . "vdata.owner = userid
	)pop
	FROM " . TB_PREFIX . "users
	WHERE " . TB_PREFIX . "users.apall >=0 AND " . TB_PREFIX . "users.access < " . (INCLUDE_ADMIN ? "10" : "8") . " AND " . TB_PREFIX . "users.tribe <= 3
	ORDER BY " . TB_PREFIX . "users.apall DESC, pop DESC, username ASC";

	$result = mysql_query($q) or die(mysql_error());
	while($row = mysql_fetch_assoc($result))
	{
		$attacker[] = $row;
	}
	foreach($attacker as $key => $row)
	{
		$value['username'] = $row['username'];
		$value['totalvillages'] = $row['totalvillages'];
		$value['id'] = $row['userid'];
		$value['totalpop'] = $row['pop'];
		$value['apall'] = $row['apall'];
	}
	## Top Defender
	$q = "
	SELECT " . TB_PREFIX . "users.id userid, " . TB_PREFIX . "users.username username, " . TB_PREFIX . "users.dpall,  (
	SELECT COUNT( " . TB_PREFIX . "vdata.wref )
	FROM " . TB_PREFIX . "vdata
	WHERE " . TB_PREFIX . "vdata.owner = userid AND type != 99
	)totalvillages, (
	SELECT SUM( " . TB_PREFIX . "vdata.pop )
	FROM " . TB_PREFIX . "vdata
	WHERE " . TB_PREFIX . "vdata.owner = userid
	)pop
	FROM " . TB_PREFIX . "users
	WHERE " . TB_PREFIX . "users.dpall >=0 AND " . TB_PREFIX . "users.access < " . (INCLUDE_ADMIN ? "10" : "8") . "
	ORDER BY " . TB_PREFIX . "users.dpall DESC, pop DESC, username ASC";
	$result = mysql_query($q) or die(mysql_error());
	while($row = mysql_fetch_assoc($result))
	{
		$defender[] = $row;
	}
	foreach($defender as $key => $row)
	{
		$value['username'] = $row['username'];
		$value['totalvillages'] = $row['totalvillages'];
		$value['id'] = $row['userid'];
		$value['totalpop'] = $row['pop'];
		$value['dpall'] = $row['dpall'];
	}

	## Get WW Winner Details
	$sql = mysql_query("SELECT vref FROM ".TB_PREFIX."fdata WHERE f99 = '100' and f99t = '40'");
	$vref = mysql_result($sql, 0);

	$sql = mysql_query("SELECT name FROM ".TB_PREFIX."vdata WHERE wref = '$vref'")or die(mysql_error());
	$winningvillagename = mysql_result($sql, 0);

	$sql = mysql_query("SELECT owner FROM ".TB_PREFIX."vdata WHERE wref = '$vref'")or die(mysql_error());
	$owner = mysql_result($sql, 0);

	$sql = mysql_query("SELECT username FROM ".TB_PREFIX."users WHERE id = '$owner'")or die(mysql_error());
	$username = mysql_result($sql, 0);

	$sql = mysql_query("SELECT alliance FROM ".TB_PREFIX."users WHERE id = '$owner'")or die(mysql_error());
	$allianceid = mysql_result($sql, 0);

	$sql = mysql_query("SELECT name, tag FROM ".TB_PREFIX."alidata WHERE id = '$allianceid'")or die(mysql_error());
	$winningalliance = mysql_result($sql, 0);

	$sql = mysql_query("SELECT tag FROM ".TB_PREFIX."alidata WHERE id = '$allianceid'")or die(mysql_error());
	$winningalliancetag = mysql_result($sql, 0);

	$sql = mysql_query("SELECT vref FROM ".TB_PREFIX."fdata WHERE f99 = '100' and f99t = '40'");
	$winner = mysql_num_rows($sql);

	if($winner!=0){
include "Templates/html.tpl";
?>
<body class="v35 webkit chrome winner">
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
					<ul id="navigation">
						<li id="n1" class="resources">
							<a class="" href="dorf1.php" accesskey="1" title="<?php echo HEADER_DORF1; ?>"></a>
						</li>
						<li id="n2" class="village">
							<a class="" href="dorf2.php" accesskey="2" title="<?php echo HEADER_DORF2; ?>"></a>
						</li>
						<li id="n3" class="map">
							<a class="" href="karte.php" accesskey="3" title="<?php echo HEADER_MAP; ?>"></a>
						</li>
						<li id="n4" class="stats">
							<a class="" href="statistiken.php" accesskey="4" title="<?php echo HEADER_STATS; ?>"></a>
						</li>
<?php
    	if(count($database->getMessage($session->uid,9)) >= 1000) {
			$unmsg = "+1000";
		} else { $unmsg = count($database->getMessage($session->uid,9)); }
		
    	if(count($database->getNotice5($session->uid)) >= 1000) {
			$unnotice = "+1000";
		} else { $unnotice = count($database->getNotice5($session->uid)); }
?>
<li id="n5" class="reports"> 
<a href="berichte.php" accesskey="5" title="<?php echo HEADER_NOTICES; ?><?php if($message->nunread){ echo' ('.count($database->getNotice5($session->uid)).')'; } ?>"></a>
<?php
if($message->nunread){
	echo "<div class=\"ltr bubble\" title=\"".$unnotice." ".HEADER_NOTICES_NEW."\" style=\"display:block\">
			<div class=\"bubble-background-l\"></div>
			<div class=\"bubble-background-r\"></div>
			<div class=\"bubble-content\">".$unnotice."</div></div>";
}
?>
</li>
<li id="n6" class="messages"> 
<a href="nachrichten.php" accesskey="6" title="<?php echo HEADER_MESSAGES; ?><?php if($message->unread){ echo' ('.count($database->getMessage($session->uid,9)).')'; } ?>"></a> 
<?php
if($message->unread) {
	echo "<div class=\"ltr bubble\" title=\"".$unmsg." ".HEADER_MESSAGES_NEW."\" style=\"display:block\">
			<div class=\"bubble-background-l\"></div>
			<div class=\"bubble-background-r\"></div>
			<div class=\"bubble-content\">".$unmsg."</div></div>";
}
?>
</li>

</ul>
<div class="clear"></div> 
</div> 
</div>
			<div id="mid">
<a id="ingameManual" href="help.php"><img class="question" alt="Ayuda" src="img/x.gif"></a>			
<div class="clear"></div>
			<div id="contentOuterContainer"> 
							<div class="contentTitle">&nbsp;</div>
<div class="contentContainer">
				<div id="content" class="winner">
					<img src="<?php echo GP_LOCATE; ?>img/g/g40_100-rtl.png" align="right" style="padding-top: 40px;">
					<div style="width:50%">
					<p>
					<b>Estimados jugadores de <?php echo SERVER_NAME; ?>,</b>
					<br /><br />
					Todas las cosas buenas deben llegar a su fin, y esta era no es la excepción. Cuentan que a Salomón le fue entregado un anillo, en el que estaba inscrito un mensaje que podía disipar
					todas las alegrías o penas del mundo; ese mensaje, traducido a grandes rasgos, decía "esto también pasará". Con alegría y también con tristeza anunciamos a todos los jugadores que
					¡esto también ha pasado! Esperamos que hayan disfrutado su tiempo con nosotros tanto como nosotros disfrutamos servirles, y les agradecemos por quedarse hasta el final.<br /><br />

					El resultado: el día había dado paso a la noche hacía tiempo, pero los trabajadores de <?php echo "<a href=\"karte.php?d=$vref&c=".$generator->getMapCheck($vref)."\">$winningvillagename</a>"; ?>
					siguieron trabajando durante la fría noche de invierno, siempre atentos a los incontables ejércitos que marchaban para destruir su obra, sabiendo que corrían contra el tiempo y contra la mayor
					amenaza que jamás había enfrentado el pueblo libre. Sus incansables esfuerzos fueron recompensados a las <strike><b>Hora</b></strike> del <strike><b>Fecha</b></strike>, cuando un
					trabajador anónimo colocó la piedra final de lo que será recordado para siempre como la creación más grande y magnífica de toda la historia desde la caída de los Natars.<br /><br />

					Junto con la alianza "<?php echo "<a href=\"allianz.php?aid=$allianceid\">$winningalliancetag</a>"; ?>", "<?php echo "<a href=\"spieler.php?uid=$owner\">$username</a>"; ?>"
					fue el primero en completar la Maravilla del Mundo, empleando millones de recursos y protegiéndola con cientos de miles de valientes defensores. Por ello, es <b><?php echo "<a href=\"spieler.php?uid=$owner\">$username</a>"; ?></b>
					quien recibe el título de "¡Ganador de esta era"!<br /><br />


					"<a href="spieler.php?uid=<?php echo $datas[0]['userid']; ?>" title="Aldeas totales: <?php echo $datas[0]['totalvillages']; echo "\n";?>Población total: <?php echo $datas[0]['totalpop']; ?>"><?php echo $datas[0]['username']; ?></a>" fue el gobernante del mayor imperio personal, seguido de cerca por "<a href="spieler.php?uid=<?php echo $datas[1]['userid']; ?>" title="Aldeas totales: <?php echo $datas[1]['totalvillages']; echo "\n";?>Población total: <?php echo $datas[1]['totalpop']; ?>"><?php echo $datas[1]['username']; ?></a>" y "<a href="spieler.php?uid=<?php echo $datas[2]['userid']; ?>" title="Aldeas totales: <?php echo $datas[2]['totalvillages']; echo "\n";?>Población total: <?php echo $datas[2]['totalpop']; ?>"><?php echo $datas[2]['username']; ?></a>".<br />
					"<a href="spieler.php?uid=<?php echo $attacker[0]['userid']; ?>" title="Aldeas totales: <?php echo $attacker[0]['totalvillages']; echo "\n"; ?>Puntos de ataque: <?php echo $attacker[0]['apall']; ?>"><?php echo $attacker[0]['username']; ?></a>" acabó con más enemigos que ningún otro, y fue el comandante más poderoso y temido.<br />
					"<a href="spieler.php?uid=<?php echo $defender[0]['userid']; ?>" title="Aldeas totales: <?php echo $defender[0]['totalvillages']; echo "\n"; ?>Puntos de defensa: <?php echo $defender[0]['dpall'];?>"><?php echo $defender[0]['username']; ?></a>" fue el defensor más glorioso, masacrando enemigos a las puertas de la aldea y tiñendo con su sangre las tierras alrededor de esas aldeas.
					<br /><br />
					<p>Felicitaciones a todos.</p>
					<br /><br />
					Saludos cordiales,<br />
					Equipo de <?php echo SERVER_NAME; ?><br /><br /><br /><br />
					<small><i>(By: aggenkeech & Eyas95)</i></small></p></div>

					<br /><br />
					<center><a href="dorf1.php">&raquo; Continuar</a></center>
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
<?php
}else{
header("Location: dorf1.php");
}
?>
