<?php
// Sólo se puede soltar un oasis de la aldea que se está mirando: antes `del` entraba
// crudo en el SQL y sin comprobar dueño, así que cualquiera podía liberar el oasis de
// cualquier otro jugador (y colar SQL de paso).
if(isset($_GET['gid'], $_GET['del']) && (int)$_GET['gid'] === 37) {
	$oasisToRelease = is_scalar($_GET['del']) && ctype_digit((string)$_GET['del'])
		? (int)$_GET['del']
		: 0;
	if($oasisToRelease > 0) {
		$oasisInfo = $database->getOasisInfo($oasisToRelease);
		if(is_array($oasisInfo)
			&& (int)$oasisInfo['conqured'] === (int)$village->wid
			&& (int)$database->getVillageField($village->wid, 'owner') === (int)$session->uid) {
			// Los refuerzos se van a casa antes de soltarlo: después el oasis ya no es
			// de nadie y las tropas quedarían defendiéndolo sin dueño. Va primero porque
			// necesita las coordenadas y la fila de odata todavía en pie.
			$automation->returnOasisReinforcements($oasisToRelease);
			$database->removeOases($oasisToRelease);
		}
	}
}

// Las dos tablas describían el tipo de oasis con el mismo par de switch copiados, y
// ninguno tenía `default`: un tipo inesperado dejaba en pie el nombre y el bonus de
// la fila anterior. Una sola definición, que además coincide con el reparto real de
// villageOasisCounter() en Production.php (cada unidad = 25%).
if(!function_exists('oasisResourceName')) {
	function oasisResourceName($type) {
		switch((int)$type) {
			case 1: case 2: case 3: return "Madera";
			case 4: case 5: case 6: return "Barro";
			case 7: case 8: case 9: return "Hierro";
			case 10: case 11: case 12: return "Cereal";
		}
		return "Desconocido";
	}
}

if(!function_exists('oasisResourceBonus')) {
	function oasisResourceBonus($type) {
		$wood = "<span><img class='r1' src='img/x.gif' title='Madera'> ";
		$clay = "<span><img class='r2' src='img/x.gif' title='Barro'> ";
		$iron = "<span><img class='r3' src='img/x.gif' title='Hierro'> ";
		$crop = "<span><img class='r4' src='img/x.gif' title='Cereal'> ";
		switch((int)$type) {
			case 1: return $wood."25%</span>";
			case 2: return $wood."50%</span>";
			case 3: return $wood."25%</span>".$crop."25%</span>";
			case 4: return $clay."25%</span>";
			case 5: return $clay."50%</span>";
			case 6: return $clay."25%</span>".$crop."25%</span>";
			case 7: return $iron."25%</span>";
			case 8: return $iron."50%</span>";
			case 9: return $iron."25%</span>".$crop."25%</span>";
			case 10: case 11: return $crop."25%</span>";
			case 12: return $crop."50%</span>";
		}
		return "";
	}
}

?>
<div class="clear"></div>
<h4>Oasis ocupado por la aldea <?php echo $village->vname; ?></h4>

<table id="oasesOwned" cellpadding="1" cellspacing="1">
	<thead><tr><td>Tipo</td><td>Lealtad</td><td>Conquistado</td><td>Coordenadas</td><td>Recursos</td></tr></thead>
	<tbody>
<?php
$prefix = "".TB_PREFIX."odata";
// El filtro va por `conqured`: es la columna que ata el oasis a la aldea. Filtrar
// también por `owner` escondía los oasis heredados al conquistar una aldea ajena.
$sql = mysql_query("SELECT * FROM $prefix WHERE conqured = ".(int)$village->wid." ORDER BY lastupdated ASC");
$query = mysql_num_rows($sql);
if($query>0){
while($row = mysql_fetch_array($sql)){ 
    $wref = $row["wref"];
    $type = $row["type"];
    $conqured = $row["conqured"];
    $lastupdated = $row["lastupdated"];
    $loyalty = $row["loyalty"];
    $owner = $row["owner"];
?>
    <tr>
				<td class="type">
					<a onclick="return (function(){
				('¿Estás seguro?').dialog(
				{
					onOkay: function(dialog, contentElement)
					{
						window.location.href = 'build.php?gid=37&amp;c=<?php echo $generator->getMapCheck($wref); ?>&amp;del=<?php echo $wref; ?>'}
				});
				return false;
			})()">
						<img class="del" src="img/x.gif" alt="eliminar">
					</a>
<?php $tname = oasisResourceName($type); ?>
					<a href="karte.php?d=<?php echo $wref; ?>&c=<?php echo $generator->getMapCheck($wref); ?>"><?php echo $tname; ?></a>
				</td>
				<td class="zp"><?php echo $loyalty; ?>%</td>
				<td class="owned"><?php echo date('y.m.d.',$lastupdated); ?> <?php echo date('H:i',$lastupdated); ?></td>
				<td class="coords">
                <?php
$coor = $database->getCoor($wref);
$tt = oasisResourceBonus($type);
?>
                <a class="" href="karte.php?x=<?php echo $coor['y']; ?>&amp;y=<?php echo $coor['x']; ?>">
                <span class="coordinates coordinatesAligned">
                <span class="coordinatesWrapper">
                <span class="coordinateY">(<?php echo $coor['x']; ?></span>
                <span class="coordinatePipe">|</span>
                <span class="coordinateX"><?php echo $coor['y']; ?>)</span>
                </span></span>
                <span class="clear"></span></a>
                </td>
				<td class="res"><?php echo $tt; ?></td>
                </tr>
<?php } } ?>
                </tbody></table>
<?php
	if($query == 0){
    	echo '<div class="nextOases none">1er oasis con mansión del héroe a nivel 10</div><div class="nextOases none">2do oasis con mansión del héroe a nivel 15</div><div class="nextOases none">3er oasis con mansión del héroe a nivel 20</div>';
	}if($query == 1){
    	echo '<div class="nextOases none">2do oasis con mansión del héroe a nivel 15</div><div class="nextOases none">3er oasis con mansión del héroe a nivel 20</div>';
	}elseif($query == 2){
    	echo '<div class="nextOases none">3er oasis con mansión del héroe a nivel 20</div>';
    }else{
    	echo '';
    }
?>


<h4 class="spacer">Otros oasis </h4>



<table id="oasesSurround" cellpadding="1" cellspacing="1">
	<thead><tr><td>Tipo</td><td>Propietario</td><td>Aldea</td><td>Coordenadas</td><td>Recurso</td></tr></thead>
    <tbody>
<?php
	// Sólo los oasis que esta aldea podría anexar, o sea los del cuadrado de 3
	// casillas que usa Automation::oasisAnnexationOutcome. Antes esto listaba "los
	// 10 más cercanos" del mundo entero, y encima los juntaba en $rows[$dist]: como
	// $dist es un float, PHP truncaba la clave a entero y cada oasis pisaba al
	// anterior de su misma distancia redondeada, así que sobrevivía uno solo por
	// anillo (1, 2, 3, ... casillas) y la tabla mezclaba oasis inalcanzables a 10
	// casillas mientras escondía vecinos anexables.
	$coor2 = $database->getCoor($village->wid);
	$windowX = implode(',', array_map('intval', Automation::oasisAnnexationAxisWindow($coor2['x'])));
	$windowY = implode(',', array_map('intval', Automation::oasisAnnexationAxisWindow($coor2['y'])));

	$getoasis = mysql_query(
		"SELECT w.id, w.x, w.y, w.oasistype, o.type, o.owner, o.conqured"
		." FROM ".TB_PREFIX."wdata w"
		." LEFT JOIN ".TB_PREFIX."odata o ON o.wref = w.id"
		." WHERE w.oasistype > 0 AND w.x IN ($windowX) AND w.y IN ($windowY)"
	);

	$rows = array();
	while($row2 = mysql_fetch_assoc($getoasis)) {
		if(!Automation::oasisWithinAnnexationRange($coor2['x'], $coor2['y'], $row2['x'], $row2['y'])) {
			continue;
		}
		// Los propios ya salen en la tabla de arriba.
		if((int)$row2['conqured'] === (int)$village->wid) {
			continue;
		}
		$rows[] = $row2;
	}

	// Ordenar por cercanía real, con el id de desempate para que el orden no cambie
	// entre recargas cuando dos oasis están a la misma distancia.
	$worldSize = 2 * WORLD_MAX + 1;
	$squaredDistance = function($row) use ($coor2, $worldSize) {
		$dx = abs((int)$row['x'] - (int)$coor2['x']);
		$dy = abs((int)$row['y'] - (int)$coor2['y']);
		$dx = min($dx, $worldSize - $dx);
		$dy = min($dy, $worldSize - $dy);
		return $dx * $dx + $dy * $dy;
	};
	usort($rows, function($a, $b) use ($squaredDistance) {
		$byDistance = $squaredDistance($a) - $squaredDistance($b);
		return $byDistance !== 0 ? $byDistance : ((int)$a['id'] - (int)$b['id']);
	});

	foreach($rows as $row2) {
		// odata puede faltar si el oasis todavía no fue poblado; wdata.oasistype es
		// la misma clasificación y evita heredar el tipo de la fila anterior.
		$otype = (int)$row2['type'] > 0 ? (int)$row2['type'] : (int)$row2['oasistype'];
		$tname = oasisResourceName($otype);
		$ttt = oasisResourceBonus($otype);

		echo "<tr><td class=\"type\">";
		echo "<a href=\"position_details.php?x=".(int)$row2['x']."&y=".(int)$row2['y']."\">".$tname."</a></td>";

		if((int)$row2['owner'] == 3 || (int)$row2['owner'] == 0){
			$oOwner = "-";
		}else{
			$oOwner = $database->getUserField($row2['owner'],'username',0);
		}
		echo "<td class=\"nam\">".$oOwner."</td>";
		if((int)$row2['conqured'] == 0){
			$oVillage = "-";
		}else{
			$tempVillage = $database->getVillage($row2['conqured']);
			$oVillage = $tempVillage['name'];
		}
		echo "<td class=\"vil\">".$oVillage."</td>";
		echo "<td class=\"coords\">";
		echo "<a href=\"karte.php?d=".(int)$row2['id']."&c=".$generator->getMapCheck($row2['id'])."\">
              <span class=\"coordinates coordinatesAligned\"><span class=\"coordinatesWrapper\">
              <span class=\"coordinateY\">(".(int)$row2['x']."</span>
              <span class=\"coordinatePipe\">|</span>
              <span class=\"coordinateX\">".(int)$row2['y'].")</span></span></span><span class=\"clear\">‎</span></a>";
		echo "</td>";
		echo "<td class=\"res\">".$ttt."</td>";
		echo "</tr>";
	}

	if(empty($rows)) {
		echo "<tr><td class=\"none\" colspan=\"5\">No hay más oasis a menos de 3 casillas de esta aldea.</td></tr>";
	}
?>

	</tbody>
</table>
