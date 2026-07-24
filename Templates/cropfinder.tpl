<?php

   if(is_numeric($_GET['x']) AND is_numeric($_GET['y'])) {
       $coor2['x'] = $_GET['x'];
       $coor2['y'] = $_GET['y'];
   } else {
       $wref2 = $village->wid;
       $coor2 = $database->getCoor($wref2);
   }

?>
<h1 class="titleInHeader">Buscador de oasis 15-9</h1>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?s" id="cropfinder_form">
<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
    <table class="transparent">
		<tbody>
			<tr>
				<td>
					<span class="coordInputLabel">Coordenadas</span>

			<div class="coordinatesInput">
				<div class="xCoord">
					<label for="xCoordInput">X:</label>
					<input maxlength="4" value="<?php print $coor2['x']; ?>" name="x" class="text">
				</div>
				<div class="yCoord">
					<label for="yCoordInput">Y:</label>
					<input maxlength="4" value="<?php print $coor2['y']; ?>" name="y" class="text">
				</div>
				<div class="clear"></div>
			</div>
							<span class="clear"></span>
				</td>
			</tr>
			<tr>
				<td>
										<span class="type">
						<input type="radio" class="radio" name="type" value="15" <?php if($_GET['s'] == 1) { print 'checked="checked"'; } ?> />15 de cereal					</span>
					<span class="type">
		    			<input type="radio" class="radio" name="type" value="9" <?php if($_GET['s'] == 2) { print 'checked="checked"'; } ?> />9 de cereal		    		</span>
		    		<span class="type">
		    			<input type="radio" class="radio" name="type" value="both" <?php if($_GET['s'] == 3) { print 'checked="checked"'; } ?> /> Ambos		    		</span>
				</td>
			</tr>
		</tbody>
	<br /></table><br />
		</div>
				</div>
	<button type="submit" value="Buscar" name="Search" id="Search"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Buscar</div></div></button>	<div class="spacer"></div>
	</form>
    <?php

   $type15 = mysql_query("SELECT id,x,y,occupied FROM ".TB_PREFIX."wdata WHERE fieldtype = 6 LIMIT 100");
   $type9 = mysql_query("SELECT id,x,y,occupied FROM ".TB_PREFIX."wdata WHERE fieldtype = 1 LIMIT 100");
   $type_both = mysql_query("SELECT id,x,y,occupied,fieldtype FROM ".TB_PREFIX."wdata WHERE fieldtype = 1 OR fieldtype = 6 LIMIT 100");
   $cropOases = array();
   $cropOasisResult = mysql_query("SELECT x,y,oasistype FROM ".TB_PREFIX."wdata WHERE oasistype IN (3,6,9,10,11,12)");
   while($cropOasis = mysql_fetch_array($cropOasisResult)) {
       $cropOases[] = $cropOasis;
   }

   if(is_numeric($_GET['x']) AND is_numeric($_GET['y'])) {
       $coor['x'] = $_GET['x'];
       $coor['y'] = $_GET['y'];
   } else {
       $coor = $database->getCoor($village->wid);
   }

	function getDistance($coorx1, $coory1, $coorx2, $coory2) {
		$max = 2 * WORLD_MAX + 1;
		$x1 = intval($coorx1);
		$y1 = intval($coory1);
		$x2 = intval($coorx2);
		$y2 = intval($coory2);
		$distanceX = min(abs($x2 - $x1), abs($max - abs($x2 - $x1)));
		$distanceY = min(abs($y2 - $y1), abs($max - abs($y2 - $y1)));
		$dist = sqrt(pow($distanceX, 2) + pow($distanceY, 2));
		return round($dist, 1);
	}

	function getMaxCropOasisBonus($villageX, $villageY, $cropOases) {
		$bonuses = array();
		foreach($cropOases as $oasis) {
			if(abs($oasis['x'] - $villageX) <= 3 && abs($oasis['y'] - $villageY) <= 3) {
				$bonuses[] = $oasis['oasistype'] == 12 ? 50 : 25;
			}
		}
		rsort($bonuses, SORT_NUMERIC);
		return array_sum(array_slice($bonuses, 0, 3));
	}

   if($_GET['s'] == 1) {

?>
<div class="spacer"></div>
<h4 class="round">Oasis de cereal</h4>
<table cellpadding="1" cellspacing="1" id="croplist">
<thead>
				<tr>
					<th>Distancia</th>
					<th>Coordenadas</th>
					<th>Tipo</th>
					<th>Oasis</th>
					<th>Ocupado por</th>
				</tr>
</thead>

<?php

   while($row = mysql_fetch_array($type15)) {
       $dist = getDistance($coor['x'], $coor['y'], $row['x'], $row['y']);

       $rows[$dist] = $row;

   }
   ksort($rows);

foreach($rows as $dist => $row) {
echo "<tr>";
echo "<td class=\"dist\">".getDistance($coor['x'], $coor['y'], $row['x'], $row['y'])."</td>";
echo "<td class=\"coords\"><a href=\"karte.php?x=".$row['x']."&y=".$row['y']."\">(".$row['x']."|".$row['y'].")</a></td>";
echo "<td class=\"typ\">15 de cereal</td>";
echo "<td class=\"oase\"><img src=\"img/x.gif\" class=\"r4\"> <b><font color=\"green\">".getMaxCropOasisBonus($row['x'], $row['y'], $cropOases)."%</font></b></td>";
if($row['occupied'] == 0) {
echo "<td class=\"owned\"><a href=\"karte.php?d=".$row['id']."\">----</a></td>";
} else {
echo "<td class=\"owned\"><a href=\"spieler.php?uid=".$database->getVillageField($row['id'], "owner")."\">".$database->getUserField($database->getVillageField($row['id'], "owner"), "username", 0)."</a></td>";

}
echo "</tr>";
   }

?>

</table>

<?php

   }
   else if($_GET['s'] == 2) {

?>
<div class="spacer"></div>
<h4 class="round">Oasis de cereal</h4>
<table cellpadding="1" cellspacing="1" id="croplist">
<thead>
				<tr>
					<th>Distancia</th>
					<th>Coordenadas</th>
					<th>Tipo</th>
					<th>Oasis</th>
					<th>Ocupado por</th>
				</tr>
</thead>
<?php

   unset($rows);
   while($row = mysql_fetch_array($type9)) {
       $dist = getDistance($coor['x'], $coor['y'], $row['x'], $row['y']);

       $rows[$dist] = $row;

   }
   ksort($rows);
   foreach($rows as $dist => $row) {


echo "<tr>";
echo "<td class=\"dist\">".getDistance($coor['x'], $coor['y'], $row['x'], $row['y'])."</td>";
echo "<td class=\"coords\"><a href=\"karte.php?x=".$row['x']."&y=".$row['y']."\">(".$row['x']."|".$row['y'].")</a></td>";
echo "<td class=\"typ\">9 de cereal</td>";
echo "<td class=\"oase\"><img src=\"img/x.gif\" class=\"r4\"> <b><font color=\"green\">".getMaxCropOasisBonus($row['x'], $row['y'], $cropOases)."%</font></b></td>";
if($row['occupied'] == 0) {
echo "<td class=\"owned\"><a href=\"karte.php?d=".$row['id']."\">----</a></td>";
} else {
echo "<td class=\"owned\"><a href=\"spieler.php?uid=".$database->getVillageField($row['id'], "owner")."\">".$database->getUserField($database->getVillageField($row['id'], "owner"), "username", 0)."</a></td>";

}
echo "</tr>";
   }

?>
    </table>

<?php

   }
   else if($_GET['s'] == 3) {

?>
<div class="spacer"></div>
<h4 class="round">Oasis de cereal</h4>
<table cellpadding="1" cellspacing="1" id="croplist">
<thead>
				<tr>
					<th>Distancia</th>
					<th>Coordenadas</th>
					<th>Tipo</th>
					<th>Oasis</th>
					<th>Ocupado por</th>
				</tr>
</thead>

<?php

   unset($rows);
   while($row = mysql_fetch_array($type_both)) {
       $dist = getDistance($coor['x'], $coor['y'], $row['x'], $row['y']);

       $rows[$dist] = $row;

   }
   ksort($rows);
   foreach($rows as $dist => $row) {

       if($row['fieldtype'] == 1) {
           $field = '9 de cereal';
       } elseif($row['fieldtype'] == 6) {
           $field = '15 de cereal';
       }

echo "<tr>";
echo "<td class=\"dist\">".getDistance($coor['x'], $coor['y'], $row['x'], $row['y'])."</td>";
echo "<td class=\"coords\"><a href=\"karte.php?x=".$row['x']."&y=".$row['y']."\">(".$row['x']."|".$row['y'].")</a></td>";
echo "<td class=\"typ\">" . $field . "</td>";
echo "<td class=\"oase\"><img src=\"img/x.gif\" class=\"r4\"> <b><font color=\"green\">".getMaxCropOasisBonus($row['x'], $row['y'], $cropOases)."%</font></b></td>";
if($row['occupied'] == 0) {
echo "<td class=\"owned\"><a href=\"karte.php?d=".$row['id']."\">----</a></td>";
} else {
echo "<td class=\"owned\"><a href=\"spieler.php?uid=".$database->getVillageField($row['id'], "owner")."\">".$database->getUserField($database->getVillageField($row['id'], "owner"), "username", 0)."</a></td>";

}
echo "</tr>";
}

?>
    </table>

<?php

   }
?>

    <div class="clear">&nbsp;</div>
