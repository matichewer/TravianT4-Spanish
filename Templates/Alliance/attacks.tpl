<?php
if(isset($aid)) {
$aid = $aid;
}
else {
$aid = $session->alliance;
}
$allianceinfo = $database->getAlliance($aid);
$allianceEventPlayerName = function($userId) use ($database) {
    return (int)$userId === 3
        ? 'Naturaleza'
        : $database->getUserField($userId, 'username', 0);
};
$allianceEventAlliance = function($attackerId, $defenderId) use ($database, $session) {
    $attackerAlliance = (int)$database->getUserField((int)$attackerId, 'alliance', 0);
    $defenderAlliance = (int)$database->getUserField((int)$defenderId, 'alliance', 0);
    $ownAlliance = (int)$session->alliance;

    if($attackerAlliance === $ownAlliance) {
        $otherAlliance = $defenderAlliance;
    } elseif($defenderAlliance === $ownAlliance) {
        $otherAlliance = $attackerAlliance;
    } else {
        $otherAlliance = $defenderAlliance ?: $attackerAlliance;
    }

    if(!$otherAlliance) {
        return "-";
    }

    return "<a href=\"allianz.php?aid=".$otherAlliance."\">"
        .$database->getAllianceName($otherAlliance)
        ."</a>";
};
echo "<h1>".$allianceinfo['tag']." - ".$allianceinfo['name']."</h1>";
include("alli_menu.tpl"); 
?>
<div class="clear"></div>
<h4 class="chartHeadline">Eventos militares</h4>
		<div id="submenu">
			<a href="allianz.php?s=3&f=32">
				<img src="img/x.gif" class="btn_def" alt="Defensor" title="Defensor" />
			</a>

			<a href="allianz.php?s=3&f=31">
				<img src="img/x.gif" class="btn_off" alt="Atacante" title="Atacante" />
			</a>
		</div>
<?php
	if($_GET['f']==31){
		include "Templates/Alliance/attack-attacker.tpl";
    }elseif($_GET['f']==32){
		include "Templates/Alliance/attack-defender.tpl";
    }else{
$prefix = "".TB_PREFIX."ndata";
$limit = "ntype!=8 AND ntype!=9 AND ntype!=10 AND ntype!=11 AND ntype!=12 AND ntype!=13 AND ntype!=14 AND ntype!=15 AND ntype!=16 AND ntype!=17";
$sql = mysql_query("SELECT * FROM $prefix WHERE ally = $session->alliance AND $limit ORDER BY time DESC LIMIT 20");
$query = mysql_num_rows($sql);
$outputList = '';
$name = 1;
if($query == 0) {
    $outputList .= "<td colspan=\"4\" class=\"none\">No hay informes disponibles.</td>";
}else{
while($row = mysql_fetch_array($sql)){ 
	$dataarray = explode(",",$row['data']);
    $id = $row["id"];
    $uid = $row["uid"];
	$toWref = $row["toWref"];
    $ally = $row["ally"];
    $topic = $row["topic"];
    $ntype = $row["ntype"];
    $data = $row["data"];
    $time = $row["time"];
    $viewed = $row["viewed"];
    $archive = $row["archive"];
	
    $outputList .= "<tr>";
	$outputList .= "<td class=\"sub\">";
if($ntype==4 || $ntype==5 || $ntype==6 || $ntype==7){
    $type2 = '32';
}else{
    $type2 = '31';
}
	$outputList .= "<a href=\"allianz.php?s=3&f=".$type2."\">";
    $type = (isset($_GET['t']) && $_GET['t'] == 5)? $archive : $ntype;
	if($type==18 or $type==19 or $type==20 or $type==21){
    $outputList .= "<img src=\"gpack/travian_default/img/scouts/$type.gif\" title=\"".$topic."\" />";
	  }else{
    $outputList .= "<img src=\"img/x.gif\" class=\"iReport iReport$type\" title=\"".$topic."\">";
	}
    $outputList .= "</a>";
    $outputList .= "<div><a href=\"berichte.php?id=".$id."&aid=".$ally."\">";
    if($ntype==0){ $nn = " explora "; }else{ $nn = " ataca "; }

    $outputList .= $allianceEventPlayerName($dataarray[0]);
       
    $outputList .= $nn;
    $defenderId = isset($dataarray[30]) ? (int)$dataarray[30] : 0;
    $outputList .= $allianceEventPlayerName($defenderId);
    $allyName = $allianceEventAlliance((int)$dataarray[0], $defenderId);
    
    $outputList .= "<td class=\"al\">".$allyName."</td>";
    $date = $generator->procMtime($time);
    $outputList .= "<td class=\"dat\">".$date[0]." ".date('H:i',$time)."</td>";
	$outputList .= "</tr>";
    
	$name++;
}
}
?>
<table cellpadding="1" cellspacing="1" id="offs">
<thead>
<tr>
<td>Jugador</td>
<td>Alianza</td>
<td>Fecha</td>
</tr>
</thead>

<tbody>
<?php echo $outputList; ?>
</tbody>
</table>
<?php } ?>
