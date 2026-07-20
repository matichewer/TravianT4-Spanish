<?php
#################################################################################
##                                                                             ##
##              -= YOU MUST NOT REMOVE OR CHANGE THIS NOTICE =-                ##
##                                                                             ##
## --------------------------------------------------------------------------- ##
##                                                                             ##
##  Project:       ZravianX                                                    ##
##  Version:       2011.11.08                                                  ##
##  Filename:      dmedals.php                                                 ##
##  Developed by:  Dixie                                                       ##
##  Reworked by:   ZZJHONS                                                     ##
##  License:       Creative Commons BY-NC-SA 3.0                               ##
##  Copyright:     ZravianX (c) 2011 - All rights reserved                     ##
##  URLs:          http://zravianx.zzjhons.com                                 ##
##  Source code:   http://www.github.com/ZZJHONS/ZravianX                      ##
##                                                                             ##
#################################################################################

/******************************
INDELING CATEGORIEEN:
===============================
== 1. Aanvallers top 10      ==
== 2. Defence top 10         ==
== 3. Klimmers top 10        ==
== 4. Overvallers top 10     ==
== 5. In att en def tegelijk ==
== 6. in top 3 - aanval      ==
== 7. in top 3 - verdediging ==
== 8. in top 3 - klimmers    ==
== 9. in top 3 - overval     ==
******************************/
include_once("GameEngine/Account.php");

global $session, $automation;
if ($session->access != ADMIN) die("Hacking attemp!");

		//bepaal welke week we zitten
		$q = "SELECT * FROM ".TB_PREFIX."medal order by week DESC LIMIT 0, 1";
		$result = mysql_query($q);
		if(mysql_num_rows($result)) {
			$row=mysql_fetch_assoc($result);
			$week=($row['week']+1);
		} else {
			$week='1';
		}
        
        //Do same for ally week
        $q = "SELECT * FROM ".TB_PREFIX."allimedal order by week DESC LIMIT 0, 1";
        $result = mysql_query($q);
        if(mysql_num_rows($result)) {
            $row=mysql_fetch_assoc($result);
            $allyweek=($row['week']+1);
        } else {
            $allyweek='1';
        }
include "Templates/html.tpl";
?>
<body class="v35 webkit chrome statistics">
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
					<?php include("Templates/navigation.tpl"); ?>
					<div class="clear"></div>
				</div>
			</div>
			<div id="mid">
				<div class="clear"></div>
				<div id="contentOuterContainer">
					<div class="contentTitle">&nbsp;</div>
					<div class="contentContainer">
						<div id="content" class="statistics">
							<h1 class="titleInHeader">Top 10 Medals</h1>
							Las medallas ahora se reparten automáticamente todos los lunes.<br />
							Próxima semana a repartir: <b><?php echo $week; ?></b> (jugadores) / <b><?php echo $allyweek; ?></b> (alianzas).
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
			</div>
		</div>
		<div id="ce"></div>
	</div>
</body>
</html>