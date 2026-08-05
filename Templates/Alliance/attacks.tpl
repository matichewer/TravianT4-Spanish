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
// Paginacion compartida por las tres vistas de la pestana (todos / atacante /
// defensor). Antes cada una cortaba en un LIMIT 20 fijo y no habia forma de
// llegar a nada mas viejo que la fila 20.
$allianceEventsPerPage = 50;
$allianceEventsPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($allianceEventsPage < 1) { $allianceEventsPage = 1; }
$allianceEventsOffset = 0;
// Cuenta los eventos de la vista, ajusta la pagina pedida al rango real y deja
// listo el offset del LIMIT. Devuelve la ultima pagina.
$allianceEventPages = function($ntypeFilter) use ($session, $allianceEventsPerPage, &$allianceEventsPage, &$allianceEventsOffset) {
    $prefix = "".TB_PREFIX."ndata";
    $count = mysql_fetch_array(mysql_query("SELECT COUNT(*) AS total FROM $prefix WHERE ally = ".(int)$session->alliance." AND $ntypeFilter"));
    $lastPage = (int)ceil((int)$count['total'] / $allianceEventsPerPage);
    if($lastPage < 1) { $lastPage = 1; }
    if($allianceEventsPage > $lastPage) { $allianceEventsPage = $lastPage; }
    $allianceEventsOffset = ($allianceEventsPage - 1) * $allianceEventsPerPage;
    return $lastPage;
};
$allianceEventPaginator = function($lastPage) use (&$allianceEventsPage) {
    if($lastPage < 2) { return ''; }
    $page = $allianceEventsPage;
    $url = function($target) {
        $link = "allianz.php?s=3";
        if(isset($_GET['f'])) { $link .= "&f=".(int)$_GET['f']; }
        if(isset($_GET['aid'])) { $link .= "&aid=".(int)$_GET['aid']; }
        return $link."&page=".(int)$target;
    };
    $arrow = function($target, $class, $label) use ($url) {
        if($target < 1) {
            return '<img alt="'.$label.'" src="img/x.gif" class="'.$class.' disabled"> ';
        }
        return '<a class="'.$class.'" href="'.$url($target).'"><img alt="'.$label.'" src="img/x.gif"></a> ';
    };
    $out = '<div class="paginator">';
    $out .= $arrow($page > 1 ? 1 : 0, 'first', 'Primera');
    $out .= $arrow($page > 1 ? $page - 1 : 0, 'previous', 'Anterior');
    // Ventana de numeros: primera, las vecinas de la actual y la ultima.
    $numbers = array(1, $page - 1, $page, $page + 1, $lastPage);
    $numbers = array_filter($numbers, function($p) use ($lastPage) { return $p >= 1 && $p <= $lastPage; });
    $numbers = array_unique($numbers);
    sort($numbers);
    $previousNumber = 0;
    foreach($numbers as $number) {
        if($previousNumber && $number > $previousNumber + 1) { $out .= '... '; }
        if($number == $page) {
            $out .= '<span class="number currentPage">'.$number.'</span> ';
        } else {
            $out .= '<a class="number" href="'.$url($number).'">'.$number.'</a> ';
        }
        $previousNumber = $number;
    }
    $out .= $arrow($page < $lastPage ? $page + 1 : 0, 'next', 'Siguiente');
    $out .= $arrow($page < $lastPage ? $lastPage : 0, 'last', 'Ultima');
    $out .= '</div><div class="clear"></div>';
    return $out;
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
$lastPage = $allianceEventPages($limit);
$sql = mysql_query("SELECT * FROM $prefix WHERE ally = $session->alliance AND $limit ORDER BY time DESC LIMIT $allianceEventsOffset, $allianceEventsPerPage");
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
<?php echo $allianceEventPaginator($lastPage); ?>
<?php } ?>
