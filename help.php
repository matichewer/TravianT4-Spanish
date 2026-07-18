<?php

include("GameEngine/Village.php");
include "Templates/html.tpl";
?>
<body class="v35 webkit chrome plus">
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
												<a id="ingameManual" href="help.php" title="Aide">
							<img src="img/x.gif" class="question" alt="Aide"/>
						</a>

												<div class="clear"></div>
						<div id="contentOuterContainer">
							<div class="contentTitle">&nbsp;</div>
							<div class="contentContainer">
								<div id="content" class="universal"><h1 class="titleInHeader"><?php echo $lang['HELP1']['TITRE']; ?></h1>

<div class="helpInfoBlock helpInfoLinkLess">
	<div class="helpHeadLine"><?php echo $lang['HELP1']['Partie_7']; ?></div>
	<div class="helpText"><?php echo $lang['HELP1']['Text_7']; ?></div>
</div>
<div class="clear"></div>
</div></div>
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

<div id="ce"></div>
</div>
</body>
</html>
