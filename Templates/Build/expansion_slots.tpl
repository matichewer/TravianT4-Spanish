<?php
/**
 * Listado de aldeas fundadas desde esta residencia/palacio.
 * Compartido por 25_4.tpl y 26_4.tpl.
 *
 * Un cupo puede quedar apuntando a una aldea que ya no existe (destruida con
 * catapultas, cuenta borrada). En ese caso se libera el cupo en lugar de dibujar
 * una fila vacia con coordenadas "(|)" y fecha 1969.
 */
$expansionSlots = array();
$expansionStale = array();
for($slotIndex = 1; $slotIndex <= 3; $slotIndex++) {
	$slotVillage = (int)$database->getVillageField($village->wid, 'exp'.$slotIndex);
	if($slotVillage <= 0) {
		continue;
	}
	$slotInfo = $database->getVillage($slotVillage);
	if(!is_array($slotInfo) || empty($slotInfo['wref'])) {
		$expansionStale[] = $slotVillage;
		continue;
	}
	$expansionSlots[$slotIndex] = $slotInfo;
}
foreach($expansionStale as $staleVillage) {
	if(method_exists($database, 'releaseExpansionSlots')) {
		$database->releaseExpansionSlots($staleVillage);
	}
}
?>
<h4>Expansiones</h4>
<table cellpadding="1" cellspacing="1" id="expansion">
<thead>
<tr>
	<td colspan="2">Aldea</td>
	<td>Jugador</td>
	<td>Población</td>
	<td>Coordenadas</td>
	<td>Fecha</td>
</tr></thead>
<tbody>
<?php
if(!empty($expansionSlots)) {
	foreach($expansionSlots as $slotIndex => $slotInfo) {
		$slotVillage = (int)$slotInfo['wref'];
		$coor = $database->getCoor($slotVillage);
		$owner = (int)$slotInfo['owner'];
		$ownername = $database->getUserField($owner, 'username', 0);
		$mapLink = 'karte.php?d='.$slotVillage.'&amp;c='.$generator->getMapCheck($slotVillage);
		$coordinates = is_array($coor) ? '('.(int)$coor['x'].'|'.(int)$coor['y'].')' : '';
		echo '
<tr class="hover">
<td class="ra">'.$slotIndex.'.</td>
<td class="vil"><a href="'.$mapLink.'">'.htmlspecialchars($slotInfo['name'], ENT_QUOTES, 'UTF-8').'</a></td>
<td class="pla"><center><a href="spieler.php?uid='.$owner.'">'.htmlspecialchars((string)$ownername, ENT_QUOTES, 'UTF-8').'</a></center></td>
<td class="ha"><center>'.(int)$slotInfo['pop'].'</center></td>
<td class="aligned_coords"><center><a href="'.$mapLink.'">'.$coordinates.'</a></center></td>
<td class="dat"><center>'.date('d/m/Y', (int)$slotInfo['created']).'</center></td>
</tr>';
	}
}
else {
	echo '<tr><td colspan="6" class="none">Esta aldea todavía no fundó nuevas aldeas.</td></tr>';
}
?>
</tbody></table>
