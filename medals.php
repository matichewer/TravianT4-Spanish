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
/**
 * @todo Find (or create) proper templates for this
 */
//include ("Templates/head.tpl");
//include ("Templates/body.tpl");
?>
<div id="content"  class="login">
<h1><br />Top 10 Medals</h1><br />
Las medallas ahora se reparten automáticamente todos los lunes.<br />
Próxima semana a repartir: <b><?php echo $week; ?></b> (jugadores) / <b><?php echo $allyweek; ?></b> (alianzas).
</div>
        <div id="side_info" class="outgame"></div>
        <div class="clear"></div>
    </div>
    <div class="footer-stopper outgame"></div>
    <div class="clear"></div>
	<?php 
        /**
         * @todo This will need a proper menu, we can't use Templates/footer.tpl
         * Temporary link back to admin.php
         */
        //include("Templates/footer.tpl");
        echo '<a href="/admin.php">Back To Admin Page</a>';
    ?>
    <div id="ce"></div>
</body>
</html>