<?php
/**
 * Sembrar los artefactos del mundo.
 *
 * El formulario ya no pide rangos de tropas: la guarnición sale de
 * `artefactSeedGarrison()`, que deriva de la de las Aldeas de la Maravilla y escala con
 * la velocidad del servidor. Los campos que había acá se enviaban a un mod que los metía
 * en el SQL sin validar, y encima describían los artefactos con otra numeración de tipos
 * que la del motor.
 */
$counts = array(1 => 6, 2 => 4, 3 => 1);
$delayHours = round(artefactActivationDelay(SPEED) / 3600);
// Cuántos hay ya. Sembrar sobre un mundo que ya tiene artefactos los DUPLICA, y no hay
// forma de deshacerlo desde el panel: por eso, si ya hay, el formulario pide una
// confirmación explícita en vez de limitarse a un cartel que nadie lee.
$existing = $database->getAllArtefacts();
$existingCount = count($existing);
$existingVillages = 0;
foreach($existing as $artefact) {
    if((int)$artefact['vref'] > 0) {
        $existingVillages++;
    }
}
?>
<form action="../GameEngine/Admin/Mods/addArtefacts.php" method="POST">
	<input type="hidden" name="admid" id="admid" value="<?php echo (int)$_SESSION['id']; ?>">
	<table id="member" cellpadding="1" cellspacing="1">
		<thead>
			<tr><th colspan="4">Sembrar artefactos</th></tr>
			<tr>
				<td>Artefacto</td>
				<td>Efecto</td>
				<td>Pequeño / Grande / Único</td>
				<td>Tesoro</td>
			</tr>
		</thead>
		<tbody>
<?php
$table = artefactValueTable();
foreach(artefactTypeCatalog() as $type => $info) {
	$values = array();
	foreach(array(1, 2, 3) as $size) {
		if($type === ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE) {
			$values[] = '—';
			continue;
		}
		$row = array('id' => 0, 'type' => $type, 'size' => $size, 'conquered' => 0);
		$values[] = htmlspecialchars(artefactEffectValueLabel($row), ENT_QUOTES, 'UTF-8');
	}
	echo '<tr>';
	echo '<td>'.htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8').'</td>';
	echo '<td>'.htmlspecialchars($info['effect'], ENT_QUOTES, 'UTF-8').'</td>';
	echo '<td class="hab">'.implode(' / ', $values).'</td>';
	echo '<td class="hab">10 / 20 / 20</td>';
	echo '</tr>';
}
?>
		</tbody>
	</table>

	<br />

	<p>Se crean <b><?php echo $counts[1]; ?></b> aldeas natar por cada artefacto pequeño,
	<b><?php echo $counts[2]; ?></b> por cada grande y <b><?php echo $counts[3]; ?></b> por cada único.
	El plano de almacenamiento no tiene versión única. Cada aldea nace marcada como escenario
	(sin manutención de tropas ni hambruna), con su Tesoro al nivel que pide el artefacto,
	sin residencia —así se toma con catapultas— y aprovisionada para que valga la pena saquearla.</p>

	<p>Recordá que en este mundo (velocidad <?php echo SPEED; ?>x) un artefacto capturado tarda
	<b><?php echo $delayHours; ?> horas</b> en hacer efecto, y que una cuenta sólo puede tener
	<b><?php echo ARTEFACT_MAX_ACTIVE; ?></b> artefactos activos a la vez, uno solo de ellos de cuenta.</p>

<?php
if($existingCount > 0) {
	echo '<div style="border:2px solid #a00;background:#ffe8e8;padding:10px;margin:10px 0;">'
		.'<p style="color:#a00;font-size:14px;"><b>Este mundo YA tiene '.$existingCount
		.' artefacto(s) repartido(s) en '.$existingVillages.' aldea(s).</b></p>'
		.'<p>Volver a sembrar <b>no reemplaza nada</b>: crea otro juego completo de aldeas y '
		.'artefactos encima de los que ya hay. Los jugadores se encontrarían con dos '
		.'artefactos únicos del mismo tipo, y el podio de tres activos por cuenta pasaría a '
		.'llenarse con duplicados. <b>No se puede deshacer desde el panel.</b></p>'
		.'<p><label><input type="checkbox" name="confirmar" value="si"> '
		.'Entiendo que voy a <b>duplicar</b> los artefactos que ya existen y quiero hacerlo igual.'
		.'</label></p>'
		.'</div>';
	echo '<h4>Los que ya están:</h4><ul>';
	foreach($existing as $artefact) {
		echo '<li>'.htmlspecialchars(artefactDisplayName((int)$artefact['type'], (int)$artefact['size']), ENT_QUOTES, 'UTF-8')
			.' — '.htmlspecialchars((string)$database->getVillageField((int)$artefact['vref'], 'name'), ENT_QUOTES, 'UTF-8')
			.'</li>';
	}
	echo '</ul>';
} else {
	echo '<p>Este mundo todavía no tiene artefactos. <b>Esto no se puede deshacer desde el panel.</b></p>';
}
?>
	<center><input type="image" src="../img/admin/b/ok1.gif" value="submit"></center>
</form>
<?php
if(isset($_GET['g'])) {
	echo '<p><b>Artefactos creados: '.(int)$_GET['g'].' aldeas.</b></p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'confirmar') {
	echo '<p style="color:#a00;"><b>No se sembró nada:</b> el mundo ya tiene artefactos y no '
		.'marcaste la casilla de confirmación.</p>';
}
?>
