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

	<p><b>Esto no se puede deshacer desde el panel.</b> Sembrar dos veces duplica todos los artefactos.</p>

	<center><input type="image" src="../img/admin/b/ok1.gif" value="submit"></center>
</form>
<?php
if(isset($_GET['g'])) {
	echo '<p><b>Artefactos creados: '.(int)$_GET['g'].' aldeas.</b></p>';
}
?>
