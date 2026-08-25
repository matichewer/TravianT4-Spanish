<?php
/**
 * Pestaña "Resumen" del Tesoro: los artefactos propios y los del resto del mundo.
 *
 * Un Tesoro de nivel 1 ya deja consultar dónde están todos los artefactos del servidor:
 * es lo que hace útil el edificio antes de llegar al 10.
 */
include("27_rows.tpl");

$ownArtefacts = $database->getArtefactsByOwner($session->uid);
$activeArtefacts = artefactActiveRows($ownArtefacts);
$allArtefacts = $database->getAllArtefacts();
$hereCoor = $database->getCoor($village->wid);
?>
<div class="gid27">
<h4 class="round">Tus artefactos</h4>
    <table id="own" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <td></td>
                <td>Artefacto</td>
                <td>Aldea</td>
                <td>Capturado</td>
                <td>Estado</td>
            </tr>
        </thead>
        <tbody>
<?php
if(count($ownArtefacts) === 0) {
	echo '<tr><td colspan="5" class="none">No tienes ningún artefacto</td></tr>';
} else {
	foreach($ownArtefacts as $row) {
		echo '<tr>';
		echo '<td class="icon">'.treasuryArtefactIcon($row).'</td>';
		echo treasuryArtefactNameCell($row, $id);
		echo treasuryArtefactVillageCell($row, 'vil');
		echo '<td class="cap">'.date("d/m/Y H:i", (int)$row['conquered']).'</td>';
		echo treasuryArtefactStateCell($row, $activeArtefacts);
		echo '</tr>';
	}
	if(count($ownArtefacts) > count($activeArtefacts)) {
		echo '<tr><td colspan="5" class="none">Sólo pueden estar activos '.ARTEFACT_MAX_ACTIVE
			.' artefactos a la vez y uno solo de cuenta. Tienen prioridad los capturados hace más tiempo.</td></tr>';
	}
}
?>
        </tbody>
    </table>
<br /><h4 class="round">Artefactos del servidor</h4>
    <table id="near" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <td></td>
                <td>Artefacto</td>
                <td>Jugador</td>
                <td>Aldea</td>
                <td>Distancia</td>
            </tr>
        </thead>
        <tbody>
<?php
if(count($allArtefacts) === 0) {
	echo '<tr><td colspan="5" class="none">Todavía no hay artefactos en el servidor</td></tr>';
} else {
	// Se ordena por distancia sin usarla de clave: dos artefactos equidistantes son dos
	// filas, no una.
	$sorted = array();
	foreach($allArtefacts as $row) {
		$sorted[] = array('row' => $row, 'distance' => treasuryArtefactDistance($row, $hereCoor));
	}
	usort($sorted, function($a, $b) {
		if($a['distance'] == $b['distance']) {
			return (int)$a['row']['id'] < (int)$b['row']['id'] ? -1 : 1;
		}
		return $a['distance'] < $b['distance'] ? -1 : 1;
	});
	foreach($sorted as $entry) {
		$row = $entry['row'];
		echo '<tr>';
		echo '<td class="icon">'.treasuryArtefactIcon($row).'</td>';
		echo treasuryArtefactNameCell($row, $id);
		echo treasuryArtefactOwnerCell($row);
		echo treasuryArtefactVillageCell($row);
		echo '<td class="dist">'.number_format($entry['distance'], 1, ',', '.').'</td>';
		echo '</tr>';
	}
}
?>
        </tbody>
    </table>
</div>
