<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       msg.tpl                                                     ##
##  Developed by:  Dzoki                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

$sql = "SELECT * FROM ".TB_PREFIX."ndata WHERE id = ".$_GET['bid']."";
$result = mysql_query($sql);
$rep = mysql_fetch_assoc($result);
if($rep)
{ 
	$att = $database->getUserArray($rep['uid'],1);
	?>
	<h1>En construcción</h1>
	<div id="content" class="reports" style="padding: 0;">
	<?php
		include("report/".$rep['ntype'].".tpl");
	?>
	</div>
	<?php
}
else
{
	echo "No existe ningún informe con el ID ".(int)$_GET['bid'].".";
}
?>