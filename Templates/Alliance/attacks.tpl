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
// Vistas de la pestana. Los ids 31 y 32 son los que ya usaban los enlaces viejos
// de Atacante y Defensor, asi que los links guardados siguen funcionando.
// Los ntype salen de $noticeClass (ver Templates/Notice/t_4.tpl): 1-3 es combate
// como atacante, 4-7 como defensor, 25 el informe de jaulas que le llega al
// atacante, y para espionaje 0 es el defensor que detecta al espia y 22-24 el
// informe del que espia. Quedan afuera a proposito las aventuras del heroe (9),
// los refuerzos (8) y las entregas de recursos (10-13), que no son eventos
// militares de la alianza, y los refuerzos atacados (15-17), porque ese mismo
// combate ya aparece en el informe del dueno de la aldea.
// Los tipos 18-21 existen en $noticeClass pero ningun addNotice los genera.
$allianceEventViews = array(
    0  => array('name' => 'Todos',     'types' => array(0,1,2,3,4,5,6,7,22,23,24,25)),
    31 => array('name' => 'Ataques',   'types' => array(1,2,3,25)),
    32 => array('name' => 'Defensa',   'types' => array(4,5,6,7)),
    33 => array('name' => 'Espionaje', 'types' => array(0,22,23,24)),
);
$allianceEventView = (isset($_GET['f']) && isset($allianceEventViews[(int)$_GET['f']])) ? (int)$_GET['f'] : 0;
// A que vista pertenece un informe, para que su icono lleve a la pestana propia.
$allianceEventViewOf = function($ntype) use ($allianceEventViews) {
    foreach(array(31, 32, 33) as $view) {
        if(in_array((int)$ntype, $allianceEventViews[$view]['types'], true)) {
            return $view;
        }
    }
    return 0;
};
$allianceEventUrl = function($view, $page = 1) {
    $link = "allianz.php?s=3";
    if($view) { $link .= "&f=".(int)$view; }
    if(isset($_GET['aid'])) { $link .= "&aid=".(int)$_GET['aid']; }
    if($page > 1) { $link .= "&page=".(int)$page; }
    return $link;
};
// Paginacion. Antes la lista cortaba en un LIMIT 20 fijo y no habia forma de
// llegar a nada mas viejo que la fila 20.
$allianceEventsPerPage = 20;
$allianceEventsPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($allianceEventsPage < 1) { $allianceEventsPage = 1; }
$ntypeFilter = "ntype IN (".implode(',', $allianceEventViews[$allianceEventView]['types']).")";
$prefix = "".TB_PREFIX."ndata";
// Cuenta los informes de la vista y ajusta la pagina pedida al rango real.
$count = mysql_fetch_array(mysql_query("SELECT COUNT(*) AS total FROM $prefix WHERE ally = ".(int)$session->alliance." AND $ntypeFilter"));
$lastPage = (int)ceil((int)$count['total'] / $allianceEventsPerPage);
if($lastPage < 1) { $lastPage = 1; }
if($allianceEventsPage > $lastPage) { $allianceEventsPage = $lastPage; }
$allianceEventsOffset = ($allianceEventsPage - 1) * $allianceEventsPerPage;
$allianceEventPaginator = function() use ($lastPage, $allianceEventsPage, $allianceEventView, $allianceEventUrl) {
    if($lastPage < 2) { return ''; }
    $page = $allianceEventsPage;
    $arrow = function($target, $class, $label) use ($allianceEventView, $allianceEventUrl) {
        if($target < 1) {
            return '<img alt="'.$label.'" src="img/x.gif" class="'.$class.' disabled"> ';
        }
        return '<a class="'.$class.'" href="'.$allianceEventUrl($allianceEventView, $target).'"><img alt="'.$label.'" src="img/x.gif"></a> ';
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
            $out .= '<a class="number" href="'.$allianceEventUrl($allianceEventView, $number).'">'.$number.'</a> ';
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
<div class="contentNavi subNavi">
<?php foreach($allianceEventViews as $viewId => $view) { ?>
	<div title="" class="container <?php echo $viewId === $allianceEventView ? 'active' : 'normal'; ?>">
		<div class="background-start">&nbsp;</div>
		<div class="background-end">&nbsp;</div>
		<div class="content"><a href="<?php echo $allianceEventUrl($viewId); ?>"><span class="tabItem"><?php echo $view['name']; ?></span></a></div>
	</div>
<?php } ?>
	<div class="clear"></div>
</div>
<h4 class="chartHeadline">Eventos militares</h4>
<?php
$sql = mysql_query("SELECT * FROM $prefix WHERE ally = $session->alliance AND $ntypeFilter ORDER BY time DESC LIMIT $allianceEventsOffset, $allianceEventsPerPage");
$query = mysql_num_rows($sql);
$outputList = '';
if($query == 0) {
    $outputList .= "<td colspan=\"4\" class=\"none\">No hay informes disponibles.</td>";
}else{
while($row = mysql_fetch_array($sql)){
	$dataarray = explode(",",$row['data']);
    $id = $row["id"];
    $ally = $row["ally"];
    $topic = $row["topic"];
    $ntype = $row["ntype"];
    $time = $row["time"];
    $archive = $row["archive"];

    $outputList .= "<tr>";
	$outputList .= "<td class=\"sub\">";
	$outputList .= "<a href=\"".$allianceEventUrl($allianceEventViewOf($ntype))."\">";
    $type = (isset($_GET['t']) && $_GET['t'] == 5)? $archive : $ntype;
    $outputList .= "<img src=\"img/x.gif\" class=\"iReport iReport$type\" title=\"".$topic."\">";
    $outputList .= "</a>";
    $outputList .= "<div><a href=\"berichte.php?id=".$id."&aid=".$ally."\">";
    // Los cuatro tipos de espionaje se leen igual desde las dos puntas, que es
    // como los nombra el propio juego al armar el topic del informe. El informe
    // de jaulas no guarda un defensor en data[30]: su destino siempre es
    // Naturaleza y necesita una descripción propia.
    if((int)$ntype === 25) {
        $nn = " captura animales en ";
        $defenderId = 3;
    } else {
        $nn = in_array((int)$ntype, array(0,22,23,24), true) ? " espía a " : " ataca ";
        $defenderId = isset($dataarray[30]) ? (int)$dataarray[30] : 0;
    }

    $outputList .= $allianceEventPlayerName($dataarray[0]);

    $outputList .= $nn;
    $outputList .= $allianceEventPlayerName($defenderId);
    $allyName = $allianceEventAlliance((int)$dataarray[0], $defenderId);

    $outputList .= "<td class=\"al\">".$allyName."</td>";
    $date = $generator->procMtime($time);
    $outputList .= "<td class=\"dat\">".$date[0]." ".date('H:i',$time)."</td>";
	$outputList .= "</tr>";
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
<?php echo $allianceEventPaginator(); ?>
