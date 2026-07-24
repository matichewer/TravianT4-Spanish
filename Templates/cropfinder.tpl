<?php

   $searchType = isset($_GET['s']) ? (int) $_GET['s'] : 0;
   if(isset($_GET['x'], $_GET['y']) && is_numeric($_GET['x']) && is_numeric($_GET['y'])) {
       $coor2['x'] = (int) $_GET['x'];
       $coor2['y'] = (int) $_GET['y'];
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
						<input type="radio" class="radio" name="type" value="15" <?php if($searchType == 1) { print 'checked="checked"'; } ?> />15 de cereal
					</span>
					<span class="type">
						<input type="radio" class="radio" name="type" value="9" <?php if($searchType == 2) { print 'checked="checked"'; } ?> />9 de cereal
					</span>
					<span class="type">
						<input type="radio" class="radio" name="type" value="both" <?php if($searchType == 3) { print 'checked="checked"'; } ?> /> Ambos
					</span>
				</td>
			</tr>
		</tbody>
	<br /></table><br />
		</div>
				</div>
	<button type="submit" value="Buscar" name="Search" id="Search"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Buscar</div></div></button>	<div class="spacer"></div>
	</form>
<?php

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

	function compareCropfinderRows($first, $second) {
		if($first['distance'] == $second['distance']) {
			return (int) $first['id'] - (int) $second['id'];
		}
		return $first['distance'] < $second['distance'] ? -1 : 1;
	}

	function getCropfinderPageUrl($searchType, $x, $y, $page) {
		return "cropfinder.php?s=".(int) $searchType."&amp;x=".(int) $x."&amp;y=".(int) $y."&amp;page=".(int) $page;
	}

   if(in_array($searchType, array(1, 2, 3))) {
       if($searchType == 1) {
           $fieldCondition = "fieldtype = 6";
       } elseif($searchType == 2) {
           $fieldCondition = "fieldtype = 1";
       } else {
           $fieldCondition = "fieldtype IN (1,6)";
       }

       $cropOases = array();
       $cropOasisResult = mysql_query("SELECT x,y,oasistype FROM ".TB_PREFIX."wdata WHERE oasistype IN (3,6,9,10,11,12)");
       while($cropOasis = mysql_fetch_array($cropOasisResult)) {
           $cropOases[] = $cropOasis;
       }

       $rows = array();
       $cropResult = mysql_query("SELECT id,x,y,occupied,fieldtype FROM ".TB_PREFIX."wdata WHERE ".$fieldCondition);
       while($row = mysql_fetch_array($cropResult)) {
           $row['distance'] = getDistance($coor2['x'], $coor2['y'], $row['x'], $row['y']);
           $row['crop_bonus'] = getMaxCropOasisBonus($row['x'], $row['y'], $cropOases);
           $rows[] = $row;
       }
       usort($rows, 'compareCropfinderRows');

       $rowsPerPage = 100;
       $totalRows = count($rows);
       $totalPages = max(1, (int) ceil($totalRows / $rowsPerPage));
       $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
       $currentPage = min($currentPage, $totalPages);
       $firstRow = ($currentPage - 1) * $rowsPerPage;
       $pageRows = array_slice($rows, $firstRow, $rowsPerPage);
       $lastRow = min($firstRow + count($pageRows), $totalRows);

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
	<tbody>
<?php
       foreach($pageRows as $row) {
           $field = $row['fieldtype'] == 1 ? '9 de cereal' : '15 de cereal';
           echo "<tr>";
           echo "<td class=\"dist\">".$row['distance']."</td>";
           echo "<td class=\"coords\"><a href=\"karte.php?x=".$row['x']."&y=".$row['y']."\">(".$row['x']."|".$row['y'].")</a></td>";
           echo "<td class=\"typ\">".$field."</td>";
           echo "<td class=\"oase\"><img src=\"img/x.gif\" class=\"r4\"> <b><font color=\"green\">".$row['crop_bonus']."%</font></b></td>";
           if($row['occupied'] == 0) {
               echo "<td class=\"owned\"><a href=\"karte.php?d=".$row['id']."\">----</a></td>";
           } else {
               $owner = $database->getVillageField($row['id'], "owner");
               echo "<td class=\"owned\"><a href=\"spieler.php?uid=".$owner."\">".$database->getUserField($owner, "username", 0)."</a></td>";
           }
           echo "</tr>";
       }
?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="5" class="footer">
				Mostrando <?php echo $totalRows > 0 ? $firstRow + 1 : 0; ?>-<?php echo $lastRow; ?> de <?php echo $totalRows; ?> cerealeras
			</td>
		</tr>
	</tfoot>
</table>
<?php if($totalPages > 1) { ?>
<div class="paginator">
<?php
       if($currentPage > 1) {
           echo '<a class="previous" href="'.getCropfinderPageUrl($searchType, $coor2['x'], $coor2['y'], $currentPage - 1).'"><img alt="Página anterior" src="img/x.gif"></a>';
       } else {
           echo '<img alt="Página anterior" src="img/x.gif" class="previous disabled">';
       }

       $visiblePages = array(1, $totalPages);
       for($page = max(1, $currentPage - 2); $page <= min($totalPages, $currentPage + 2); $page++) {
           $visiblePages[] = $page;
       }
       $visiblePages = array_unique($visiblePages);
       sort($visiblePages);
       $previousVisiblePage = 0;
       foreach($visiblePages as $page) {
           if($previousVisiblePage > 0 && $page > $previousVisiblePage + 1) {
               echo ' ... ';
           }
           if($page == $currentPage) {
               echo '<span class="number currentPage">'.$page.'</span>';
           } else {
               echo '<a class="number" href="'.getCropfinderPageUrl($searchType, $coor2['x'], $coor2['y'], $page).'">'.$page.'</a>';
           }
           $previousVisiblePage = $page;
       }

       if($currentPage < $totalPages) {
           echo '<a class="next" href="'.getCropfinderPageUrl($searchType, $coor2['x'], $coor2['y'], $currentPage + 1).'"><img alt="Página siguiente" src="img/x.gif"></a>';
       } else {
           echo '<img alt="Página siguiente" src="img/x.gif" class="next disabled">';
       }
?>
</div>
<?php
   }
}
?>

    <div class="clear">&nbsp;</div>
