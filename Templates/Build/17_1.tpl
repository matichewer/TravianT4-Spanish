<?php
if(isset($_GET['s']) && (!ctype_digit((string)$_GET['s']) || !in_array((int)$_GET['s'],array(1,2,3,4),true))) unset($_GET['s']);
if(isset($_GET['b']) && (!ctype_digit((string)$_GET['b']) || !in_array((int)$_GET['b'],array(1,2,3,4),true))) unset($_GET['b']);
if(isset($_GET['v']) && $_GET['v'] !== '1:1') unset($_GET['v']);
$u = (isset($_GET['u']) && ctype_digit((string)$_GET['u']))? (int)$_GET['u'] : 0;
$pageSize = 40;
$filterQuery = '';
if(isset($_GET['s']) && in_array((int)$_GET['s'],array(1,2,3,4),true)) $filterQuery .= '&s='.(int)$_GET['s'];
if(isset($_GET['b']) && in_array((int)$_GET['b'],array(1,2,3,4),true)) $filterQuery .= '&b='.(int)$_GET['b'];
if(isset($_GET['v']) && $_GET['v'] === '1:1') $filterQuery .= '&v=1:1';
?>
<h1 class="titleInHeader">Mercado <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid17">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(17,4);" class="build_logo"> 
    <img class="building big white g17" src="img/x.gif" alt="Mercado" title="Mercado" /> 
</a> 
En el mercado puedes comerciar recursos con otros jugadores. Cuanto mayor sea su nivel, más recursos se pueden transportar al mismo tiempo.</div> 
<?php
$buildingHelpType = 'marketplace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("17_menu.tpl");
if($session->plus) {
?>
<div class="boxes boxesColor gray traderCount"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Mercaderes <?php echo $market->merchantAvail(); ?> / <?php echo $market->merchant; ?></div>
				</div><div class="clear"></div>
<div class="boxes boxesColor gray search_select"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><table id="search_select" class="buy_select transparent" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td colspan="4">Busco</td>
		</tr>
	</thead>
	<tbody>
		<tr>
        <td>
        <button type="button" value="r1Big" <?php if(isset($_GET['s']) && $_GET['s'] == 1) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&s=1<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;"><img src="img/x.gif" class="r1Big" alt="r1Big"></button>
        </td>
        <td>
        <button type="button" value="r2Big" <?php if(isset($_GET['s']) && $_GET['s'] == 2) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&s=2<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;"><img src="img/x.gif" class="r2Big" alt="r2Big"></button>
        </td>
        <td>
        <button type="button" value="r3Big" <?php if(isset($_GET['s']) && $_GET['s'] == 3) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&s=3<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;"><img src="img/x.gif" class="r3Big" alt="r3Big"></button>
        </td>
        <td>
        <button type="button" value="r4Big" <?php if(isset($_GET['s']) && $_GET['s'] == 4) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&s=4<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;"><img src="img/x.gif" class="r4Big" alt="r4Big"></button>
        </td>
        </tr>
	</tbody>
</table>
	</div>
</div>

<div class="boxes boxesColor gray ratio_select"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
<table id="ratio_select" class="buy_select transparent" cellpadding="1" cellspacing="1">
<tbody>
	<tr>
    <td>
    <button type="button" value="marketPercentage marketPercentage1_1" <?php if(isset($_GET['v'])) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&v=1:1<?php if(isset($_GET['s'])) echo "&s=".$_GET['s']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;">
    <img src="img/x.gif" class="marketPercentage marketPercentage1_1" alt="marketPercentage marketPercentage1_1"></button>
    </td>
    </tr>
    <tr>
    <td>
    <button type="button" value="marketPercentage marketPercentage1_x" <?php if(!isset($_GET['v'])) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1<?php if(isset($_GET['s'])) echo "&s=".$_GET['s']; if(isset($_GET['b'])) echo "&b=".$_GET['b']; ?>'; return false;">
    <img src="img/x.gif" class="marketPercentage marketPercentage1_x" alt="marketPercentage marketPercentage1_x"></button>
    </td>
	</tr>
</tbody>
</table>
</div></div>

<div class="boxes boxesColor gray bid_select"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
<table id="bid_select" class="buy_select transparent" cellpadding="1" cellspacing="1">
<thead><tr>
<td colspan="4">Ofrezco</td>
</tr></thead>
<tbody>
<tr>
<td>
<button type="button" value="r1Big" <?php if(isset($_GET['b']) && $_GET['b'] == 1) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&b=1<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['s'])) echo "&s=".$_GET['s']; ?>'; return false;">
<img src="img/x.gif" class="r1Big" alt="r1Big"></button>
</td>
<td>
<button type="button" value="r2Big" <?php if(isset($_GET['b']) && $_GET['b'] == 2) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&b=2<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['s'])) echo "&s=".$_GET['s']; ?>'; return false;">
<img src="img/x.gif" class="r2Big" alt="r2Big"></button>
</td>
<td>
<button type="button" value="r3Big" <?php if(isset($_GET['b']) && $_GET['b'] == 3) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&b=3<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['s'])) echo "&s=".$_GET['s']; ?>'; return false;">
<img src="img/x.gif" class="r3Big" alt="r3Big"></button>
</td>
<td>
<button type="button" value="r4Big" <?php if(isset($_GET['b']) && $_GET['b'] == 4) { echo "class=\"iconFilter iconFilterActive\""; } else { echo "class=\"iconFilter\""; } ?> onclick="window.location.href = 'build.php?id=<?php echo $id; ?>&t=1&b=4<?php if(isset($_GET['v'])) echo "&v=".$_GET['v']; if(isset($_GET['s'])) echo "&s=".$_GET['s']; ?>'; return false;">
<img src="img/x.gif" class="r4Big" alt="r4Big"></button>
</td>
</tr>
</tbody>
</table>
</div></div>
 <?php
 }
 ?>
<div class="clear"></div>
<h4 class="spacer">Ofertas en el mercado</h4>

<table id="range" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<th>Ofrecido</th>
			<th><img src="img/x.gif" class="ratio" alt="Proporción"></th>
			<th>Buscado</th>
			<th>Jugador</th>
			<th>Duración</th>
			<th>Acción</th>
		</tr>
	</thead>
<tbody>
<?php
if(count($market->onsale) > 0) {
for($i=$u;$i<$u+$pageSize;$i++) {
if(isset($market->onsale[$i])) {
echo "<tr><td class=\"val\">";
$reqMerc = (int)ceil($market->onsale[$i]['wamt']/$market->maxcarry);

	        $sss = ($market->onsale[$i]['gamt'] > 0)? ($market->onsale[$i]['wamt']/$market->onsale[$i]['gamt']) : 0;
        $ratio = round($sss, 1);
        if($ratio <= 1){
        	$class = 'green';
        }elseif($ratio > 1 && $ratio < 2){
        	$class = 'orange';
        }elseif($ratio >= 2){
        	$class = 'red';
        }
            
switch($market->onsale[$i]['gtype']) {
    case 1: echo "<img src=\"img/x.gif\" class=\"r1\" alt=\"Madera\" title=\"Madera\" /> "; break;
    case 2: echo "<img src=\"img/x.gif\" class=\"r2\" alt=\"Barro\" title=\"Barro\" /> "; break;
    case 3: echo "<img src=\"img/x.gif\" class=\"r3\" alt=\"Hierro\" title=\"Hierro\" /> "; break;
    case 4: echo "<img src=\"img/x.gif\" class=\"r4\" alt=\"Cereal\" title=\"Cereal\" /> "; break;
 	}
    echo $market->onsale[$i]['gamt'];
    
    echo "</td><td class=\"ratio\">
	      <div class=\"boxes boxesColor ".$class."\"><div class=\"boxes-tl\"></div><div class=\"boxes-tr\"></div><div class=\"boxes-tc\"></div><div class=\"boxes-ml\"></div><div class=\"boxes-mr\"></div><div class=\"boxes-mc\"></div><div class=\"boxes-bl\"></div><div class=\"boxes-br\"></div><div class=\"boxes-bc\"></div><div class=\"boxes-contents\">".$ratio."</div>
      </div>
      </td><td class=\"val\">";
    switch($market->onsale[$i]['wtype']) {
    case 1: echo "<img src=\"img/x.gif\" class=\"r1\" alt=\"Madera\" title=\"Madera\" /> "; break;
    case 2: echo "<img src=\"img/x.gif\" class=\"r2\" alt=\"Barro\" title=\"Barro\" /> "; break;
    case 3: echo "<img src=\"img/x.gif\" class=\"r3\" alt=\"Hierro\" title=\"Hierro\" /> "; break;
    case 4: echo "<img src=\"img/x.gif\" class=\"r4\" alt=\"Cereal\" title=\"Cereal\" /> "; break;
    }
    echo $market->onsale[$i]['wamt'];
    echo "</td><td class=\"pla\">";
    echo "<img src=\"img/x.gif\" class=\"nation nation".$database->getUserField($database->getVillageField($market->onsale[$i]['vref'],"owner"),"tribe",0)."\"> <a href=\"karte.php?d=".$market->onsale[$i]['vref']."&c=".$generator->getMapCheck($market->onsale[$i]['vref'])."\" title=\"".$database->getVillageField($market->onsale[$i]['vref'],"name")."\">".$database->getUserField($database->getVillageField($market->onsale[$i]['vref'],"owner"),"username",0)."</a></td>";
    
    echo "<td class=\"dur\">".$generator->getTimeFormat($market->onsale[$i]['duration'])."</td>";
    
    if(($market->onsale[$i]['wtype'] == 1 && $village->awood < $market->onsale[$i]['wamt']) ||($market->onsale[$i]['wtype'] == 2 && $village->aclay < $market->onsale[$i]['wamt']) ||($market->onsale[$i]['wtype'] == 3 && $village->airon < $market->onsale[$i]['wamt']) ||($market->onsale[$i]['wtype'] == 4 && $village->acrop < $market->onsale[$i]['wamt'])) {
    echo "<td class=\"act none\">Materiales insuficientes.</td></tr>";
    }
    else if($reqMerc > $market->merchantAvail()) {
    echo "<td class=\"act none\">Mercaderes insuficientes</td></tr>";
    }
    else {
    echo "<td class=\"act\">
    <button type=\"button\" class=\"build\" onclick=\"window.location.href = 'build.php?id=$id&t=1&a=".$session->mchecker."&g=".$market->onsale[$i]['id']."'; return false;\">
<div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div>
<div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div>
</div><div class=\"button-contents\">Aceptar oferta</div></div></button></td>";
    }
    echo"</tr>";
    }
}
}
else {
echo "<tr><td class=\"none\" colspan=\"6\"><center>No hay ofertas actualmente</center></td></tr>";
}
?>
</tbody></table>
<tr><td colspan="5"><p>
<span class="none">
<?php
$previous = max(0,$u-$pageSize);
$next = $u+$pageSize;
echo ($u > 0)? "<a href=\"build.php?id=$id&t=1&u=$previous$filterQuery\">&laquo;</a>" : "<span class=\"none\"><b>&laquo;</b></span>";
echo " Páginas ";
echo ($next < count($market->onsale))? "<a href=\"build.php?id=$id&t=1&u=$next$filterQuery\">&raquo;</a>" : "<span class=\"none\"><b>&raquo;</b></span>";
?>
</td></tr> 
</div> 
