<?php
/**
 * La lista de artefactos del servidor filtrada por tamaño, agrupada por tipo.
 *
 * Quien incluye define $treasuryListSizes, $treasuryListTitle y $treasuryListEmpty, y
 * opcionalmente $treasuryListTypes para filtrar por tipo en vez de por tamaño (la pestaña
 * de planos de construcción, que no son un tamaño).
 * Recorre el catálogo entero, así que un tipo nuevo aparece solo: la versión anterior
 * tenía un bloque escrito a mano por tipo y ya se le había perdido uno.
 */
include("27_rows.tpl");

$hereCoor = $database->getCoor($village->wid);
$treasuryListTypes = isset($treasuryListTypes) && is_array($treasuryListTypes)
	? $treasuryListTypes : null;
$listed = array();
foreach($database->getAllArtefacts() as $row) {
	$type = (int)$row['type'];
	if($treasuryListTypes !== null) {
		if(!in_array($type, $treasuryListTypes, true)) {
			continue;
		}
	} else {
		// El plano de construcción se guarda con tamaño "pequeño" para que su Tesoro 10
		// salga del mismo lugar que el del artefacto pequeño, así que sin esto aparecería
		// mezclado en la pestaña de los pequeños. Tiene su propia pestaña.
		if($type === ARTEFACT_PLAN || !in_array((int)$row['size'], $treasuryListSizes, true)) {
			continue;
		}
	}
	$listed[$type][] = $row;
}
// La variable es global entre includes: si no se limpia, la pestaña siguiente que incluya
// esta plantilla heredaría el filtro por tipo de la anterior.
unset($treasuryListTypes);
?>
        <h4 class="round"><?php echo htmlspecialchars($treasuryListTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
        <table class="show_artefacts" id="show_artefacts" cellpadding="1" cellspacing="1">
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
if(count($listed) === 0) {
	echo '<tr><td colspan="5" class="none">'
		.htmlspecialchars($treasuryListEmpty, ENT_QUOTES, 'UTF-8').'</td></tr>';
} else {
	$first = true;
	foreach(array_keys(artefactTypeCatalog()) as $type) {
		if(!isset($listed[$type])) {
			continue;
		}
		if(!$first) {
			echo '<tr><td colspan="5" class="empty"></td></tr>';
		}
		$first = false;
		foreach($listed[$type] as $row) {
			echo '<tr>';
			echo '<td class="icon">'.treasuryArtefactIcon($row).'</td>';
			echo treasuryArtefactNameCell($row, $id);
			echo treasuryArtefactOwnerCell($row);
			echo treasuryArtefactVillageCell($row);
			echo '<td class="dist">'
				.number_format(treasuryArtefactDistance($row, $hereCoor), 1, ',', '.').'</td>';
			echo '</tr>';
		}
	}
}
?>
            </tbody>
        </table>
