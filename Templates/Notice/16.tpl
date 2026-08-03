<?php include __DIR__ . "/report_data.tpl"; ?>
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
					<div class="clear"></div></div>
                                
                                <div class="clear"></div>
							</div>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="2" class="report_content">
<table cellspacing="0" cellpadding="0" id="attacker">
<?php $reinforcementHasOrigin = isset($dataarray[26]) && $dataarray[26] === 'reinforcement-origin-v1'; ?>
	<thead>
		<tr>
			<td class="role">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">
                <div class="role"><?php echo REPORT_REINF; ?></div>
					</div>
				</div>			</td>
			<td colspan="11" class="troopHeadline">
				<p>
					<a href="spieler.php?uid=<?php echo $database->getUserField($dataarray[0],"id",0); ?>"><?php echo $database->getUserField($dataarray[0],"username",0); ?></a>
					<?php echo $reinforcementHasOrigin ? REPORT_FROM_VIL : 'en la aldea'; ?>
					<a href="karte.php?d=<?php echo $dataarray[1]."&amp;c=".$generator->getMapCheck($dataarray[1]); ?>"><?php echo $database->getVillageField($dataarray[1],"name"); ?></a>
				</p>
			</td>
		</tr>
	</thead>
    
    <tbody class="units"><tr>
    <th class="coords"></th>
<?php
$targettribe = $dataarray['3'];
$start = ($targettribe-1)*10+1;
$end = ($targettribe*10);
    for($i=$start;$i<=$end;$i++) {
        echo "<td class=\"uniticon\"><img src=\"img/x.gif\" class=\"unit u$i\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></td>";
    }
        echo "<td class=\"uniticon last\"><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".U0."\" alt=\"".U0."\" /></td>";
    echo "</tr></tbody><tbody class=\"units\"><tr><th>".REPORT_TROOPS."</th>";
    for($i=4;$i<=13;$i++) {
        if($dataarray[$i] == 0) {
            echo "<td class=\"unit none\">0</td>";
        } else {
            echo "<td class=\"unit\">".$dataarray[$i]."</td>";
        }
    }
    if($dataarray[24] == 0) {
        echo "<td class=\"unit none last\">0</td>";
    } else {
        echo "<td class=\"unit last\">".$dataarray[24]."</td>";
    }
    echo "</tr></tbody>";
    
    echo "<tbody class=\"units last\"><th>".REPORT_CASUALTIES."</th>";
    for($i=14;$i<=23;$i++) {
        if($dataarray[$i] == 0) {
            echo "<td class=\"unit none\">0</td>";
        } else {
            echo "<td class=\"unit\">".$dataarray[$i]."</td>";
        }
    }
    if($dataarray[25] == 0) {
        echo "<td class=\"unit none last\">0</td>";
    } else {
        echo "<td class=\"unit last\">".$dataarray[25]."</td>";
    }
    echo "</tr></tbody>";
?>

</table>

</td></tr></tbody></table>
<div class="clear">&nbsp;</div>
