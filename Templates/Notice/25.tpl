<?php include __DIR__ . "/report_data.tpl"; ?>
<?php
/*
 * Informe de captura con jaulas.
 * data: 0 = aldea atacante, 1 = uid atacante, 2 = oasis,
 *       3..12 = animales capturados (u31..u40),
 *       13 = jaulas usadas, 14 = jaulas que quedan equipadas,
 *       15 = animales que siguen en el oasis.
 */
$cageFrom      = (int)$dataarray[0];
$cageOwner     = (int)$dataarray[1];
$cageOasis     = (int)$dataarray[2];
$cageUsed      = isset($dataarray[13]) ? (int)$dataarray[13] : 0;
$cageLeft      = isset($dataarray[14]) ? (int)$dataarray[14] : 0;
$cageRemaining = isset($dataarray[15]) ? (int)$dataarray[15] : 0;
$cageTotal     = 0;
$cageUnits     = array();
for($i = 1; $i <= 10; $i++) {
	$amount = isset($dataarray[$i + 2]) ? max(0, (int)$dataarray[$i + 2]) : 0;
	$cageUnits['u'.(30 + $i)] = $amount;
	$cageTotal += $amount;
}
$cageOasisCoor = $database->getCoor($cageOasis);
?>
				<table cellpadding="1" cellspacing="1" id="report_surround">
				<thead class="theader">
					<tr>
						<th colspan="2">
							<div id="subject">
								<div class="header label"><?php echo REPORT_SUBJECT; ?></div>
								<div class="header text"><?php echo $topic; ?></div>
								<div class="clear"></div>
							</div>

							<div id="time">
                            <?php $date = $generator->procMtime($time); ?>
								<div class="header label"><?php echo REPORT_SENT; ?></div>
								<div class="header text"><?php echo $date[0]."<span> ".REPORT_AT." ".$date[1]; ?></span></div>

                                <div class="toolList">
<?php if($session->plus){ ?>
					<button type="button" value="reportButton delete" class="icon" title="<?php echo REPORT_DEL_BTN; ?>" onclick="return (function(){
				('<?php echo REPORT_DEL_QST; ?>').dialog(
				{
					onOkay: function(dialog, contentElement)
					{
						window.location.href = '?n1=<?php echo $_GET['id']; ?>&amp;del=1'}
				});
				return false;
			})()"><img src="img/x.gif" class="reportButton delete" alt="reportButton delete" /></button>
<?php } ?>
					<div class="clear"></div></div><div class="clear"></div>
							</div>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="2" class="report_content">
			<img src="img/x.gif" class="reportImage reportType1" alt=""><table cellspacing="0" cellpadding="0" class="tbg">
	<tbody>
	<tr>
	<td class="s7">
    <div class="boxes boxesColor gray trade"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">

    <div class="headline"><?php echo REPORT_SENDER; ?></div>
    <a href="spieler.php?uid=<?php echo $cageOwner; ?>"><?php echo $database->getUserField($cageOwner, "username", 0); ?></a> <?php echo REPORT_FROM_VIL; ?><br>
    <a href="karte.php?d=<?php echo $cageFrom."&amp;c=".$generator->getMapCheck($cageFrom); ?>"><?php echo $database->getVillageField($cageFrom, 'name'); ?></a>
    </div>
</div></td><td class="f16"><img src="img/x.gif" class="bigArrow" alt=""></td><td class="s7"><div class="boxes boxesColor gray trade"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">

<div class="headline"><?php echo REPORT_RECEIVER; ?></div>
<?php echo TRIBE4; ?><br>
<a href="karte.php?d=<?php echo $cageOasis."&amp;c=".$generator->getMapCheck($cageOasis); ?>"><?php echo $database->getOasisField($cageOasis, 'name')." (".$cageOasisCoor['x']."|".$cageOasisCoor['y'].")"; ?></a>
</div>
				</div></td>
	</tr>
	</tbody>
	</table><table cellpadding="0" cellspacing="0">
	<thead>
		<tr>
<td class="role"></td>
<td class="troopHeadline" colspan="11">
Animales capturados con jaulas
</td>
</tr></thead>
<tbody class="units"><tr>
<th class="coords"></th>
<?php
for($i = 31; $i <= 40; $i++) {
	echo "<td class=\"uniticon\"><img src=\"img/x.gif\" class=\"unit u$i\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></td>";
}
echo "</tr></tbody><tbody class=\"units last\"><tr><th>".REPORT_TROOPS."</th>";
for($i = 31; $i <= 40; $i++) {
	if($cageUnits['u'.$i] == 0) {
		echo "<td class=\"unit none\">0</td>";
	} else {
		echo "<td class=\"unit\">".$cageUnits['u'.$i]."</td>";
	}
}
?></tr></tbody>

<tbody class="infos">
<tr><td class="empty" colspan="11"></td></tr>
<tr><th>Jaulas</th>
<td colspan="10">
<?php echo $cageUsed; ?> usadas, <?php echo $cageLeft; ?> equipadas restantes</td>
</tr>
<tr><th>Oasis</th>
<td colspan="10">
<?php
if($cageRemaining > 0) {
	echo "Quedaron ".$cageRemaining." animales en el oasis.";
} else {
	echo "El oasis quedó sin animales.";
}
?></td>
</tr>
<tr><th><?php echo REPORT_UPKEEP; ?></th>
<td colspan="10">
<?php echo $technology->getUpkeep($cageUnits, 0); ?> <img src="img/x.gif" class="r4" title="<?php echo CROP; ?>"><?php echo REPORT_PER_HOURS; ?></td>
</tr></tbody></table></td></tr></tbody></table>
