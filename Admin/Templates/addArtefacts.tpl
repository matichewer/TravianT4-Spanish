<?php
/**
 * Sembrar los artefactos del mundo.
 *
 * El formulario expone TODO el plan de `GameEngine/ArtefactRelease.php` y muestra la vista
 * previa calculada con esas mismas funciones. La vista previa se recalcula en el servidor,
 * no en JavaScript, a propósito: una copia en JS de la fórmula de guarnición sería una
 * segunda definición, y las dos se irían separando hasta que la pantalla anunciara una cosa
 * y el sembrado hiciera otra.
 *
 * Botón "Recalcular": vuelve a esta misma pantalla con los valores puestos.
 * Botón "Sembrar": manda el mismo POST al mod, que vuelve a normalizar todo por su cuenta.
 */
require_once dirname(dirname(dirname(__FILE__))).'/GameEngine/Data/unitdata.php';
require_once dirname(dirname(dirname(__FILE__))).'/GameEngine/ArtefactRelease.php';

$normalized = artefactReleaseNormalizeConfig($_POST);
$config = $normalized['config'];
$limits = artefactReleaseLimits();
$defaults = artefactReleaseDefaults();

$reference = artefactReleaseReferenceOffence($database, $config['defence_sample']);
$plan = artefactReleasePlan($config, $config['defence_mode'] === 'world' ? $reference : 0);

$existing = $database->getAllArtefacts();
$existingCount = count($existing);
$delayHours = round(artefactActivationDelay(SPEED) / 3600);

/** Un campo numérico con su rango a la vista, para que el formulario y el servidor digan lo mismo. */
function releaseField($key, $label, $help = '') {
    global $config, $limits;
    if(!isset($limits[$key])) {
        return;
    }
    list($min, $max, $decimal) = $limits[$key];
    echo '<tr><td style="text-align:left;">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</td>'
        .'<td><input class="fm" style="width:90px;" name="'.$key.'" value="'
        .htmlspecialchars((string)$config[$key], ENT_QUOTES, 'UTF-8').'"></td>'
        .'<td style="text-align:left;color:#666;">'.$min.' &ndash; '.$max
        .($decimal ? ' (admite decimales)' : '')
        .($help !== '' ? ' &middot; '.htmlspecialchars($help, ENT_QUOTES, 'UTF-8') : '')
        .'</td></tr>';
}
function n($number) {
    return number_format((float)$number, 0, ',', '.');
}
?>
<h2>Sembrar artefactos</h2>

<?php if($normalized['warnings']) { ?>
<div style="border:1px solid #c80;background:#fff6e5;padding:8px;margin:8px 0;">
	<b>Se corrigieron algunos valores:</b>
	<ul><?php foreach($normalized['warnings'] as $warning) {
		echo '<li>'.htmlspecialchars($warning, ENT_QUOTES, 'UTF-8').'</li>';
	} ?></ul>
</div>
<?php } ?>

<form action="admin.php?p=addArtefacts" method="POST">
	<input type="hidden" name="admid" value="<?php echo (int)$_SESSION['id']; ?>">

	<h3>Vista previa</h3>
	<p>
		Referencia ofensiva del mundo (promedio de los <b><?php echo (int)$config['defence_sample']; ?></b>
		mejores ejércitos): <b><?php echo n($reference); ?></b> puntos de ataque.
		<?php if($reference <= 0) { ?>
		<br><span style="color:#a00;">No hay ninguna tropa de jugador en el servidor, así que
		manda el piso de <b><?php echo n($config['defence_floor']); ?></b> puntos de defensa.</span>
		<?php } ?>
	</p>
	<table id="member" cellpadding="1" cellspacing="1">
		<thead>
			<tr>
				<td>Tamaño</td><td>Aldeas</td><td>Tropas c/u</td>
				<td>Def. infantería</td><td>Def. caballería</td>
				<td>Consumo</td><td>Anillo (casillas del centro)</td>
			</tr>
		</thead>
		<tbody>
<?php
$totalTroops = 0;
foreach($plan['summary'] as $size => $row) {
	$totalTroops += $row['stats']['troops'] * $row['villages'];
	echo '<tr>'
		.'<td>'.htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8').'</td>'
		.'<td class="hab">'.(int)$row['villages'].'<br><span style="color:#666;">'
			.(int)$row['per_type'].' × '.(int)$row['types'].' tipos</span></td>'
		.'<td class="hab">'.n($row['stats']['troops']).'</td>'
		.'<td class="hab">'.n($row['stats']['infantry']).'</td>'
		.'<td class="hab">'.n($row['stats']['cavalry']).'</td>'
		.'<td class="hab">'.n($row['stats']['upkeep']).'/h</td>'
		.'<td class="hab">'.round($row['ring'][0]).' &ndash; '.round($row['ring'][1]).'</td>'
		.'</tr>';
}
?>
		</tbody>
		<tfoot>
			<tr><td colspan="7" style="text-align:left;">
				<b>Total: <?php echo (int)$plan['total_villages']; ?> aldeas natar nuevas</b>
				con <b><?php echo n($totalTroops); ?></b> tropas entre todas.
			</td></tr>
		</tfoot>
	</table>

	<h3>Cuántos</h3>
	<table id="member" cellpadding="1" cellspacing="1"><tbody>
<?php
releaseField('count_small',  'Aldeas por tipo — pequeño', '8 tipos');
releaseField('count_large',  'Aldeas por tipo — grande', '8 tipos');
releaseField('count_unique', 'Aldeas por tipo — único', '7 tipos: el plano no tiene único');
?>
	</tbody></table>

	<h3>Qué tan duras</h3>
	<p>Oficial: <i>"Defence values are based on the top 100 offensive armies of the game world"</i>.
	En modo <b>mundo</b> la guarnición se deriva de los ejércitos que hay; en modo <b>manual</b>
	la fijás vos en puntos de defensa. En los dos casos el piso se respeta.</p>
	<table id="member" cellpadding="1" cellspacing="1"><tbody>
		<tr>
			<td style="text-align:left;">Modo</td>
			<td colspan="2" style="text-align:left;">
				<label><input type="radio" name="defence_mode" value="world"<?php
					echo $config['defence_mode'] === 'world' ? ' checked' : ''; ?>> derivada del mundo</label>
				&nbsp;&nbsp;
				<label><input type="radio" name="defence_mode" value="manual"<?php
					echo $config['defence_mode'] === 'manual' ? ' checked' : ''; ?>> manual</label>
			</td>
		</tr>
<?php
releaseField('defence_sample', 'Cuántos ejércitos promedia', 'oficial: 100');
releaseField('defence_factor', 'Factor sobre esa referencia (%)', '100 = una aldea pequeña por ejército');
releaseField('defence_manual', 'Defensa del pequeño (modo manual)', 'puntos de defensa');
releaseField('defence_floor',  'Piso de defensa', 'manda si el mundo da menos');
releaseField('tier_large',     'El grande vale, sobre el pequeño', 'oficial: 1.5384');
releaseField('tier_unique',    'El único vale, sobre el grande', 'oficial: 1.5');
?>
	</tbody></table>

	<h3>Dónde</h3>
	<p>Porcentaje de <?php echo (int)WORLD_MAX; ?> casillas (el borde del mapa) medido desde el
	centro. Oficial: los únicos en el medio, los grandes en la corona intermedia y los pequeños
	en la periferia.</p>
	<table id="member" cellpadding="1" cellspacing="1"><tbody>
<?php
releaseField('ring_unique_min', 'Único — desde (%)');
releaseField('ring_unique_max', 'Único — hasta (%)');
releaseField('ring_large_min',  'Grande — desde (%)');
releaseField('ring_large_max',  'Grande — hasta (%)');
releaseField('ring_small_min',  'Pequeño — desde (%)');
releaseField('ring_small_max',  'Pequeño — hasta (%)');
?>
	</tbody></table>

	<h3>Cómo es la aldea</h3>
	<table id="member" cellpadding="1" cellspacing="1"><tbody>
<?php
releaseField('treasury', 'Nivel del Tesoro', 'oficial 20; con 10 hacen falta muchas menos catapultas');
releaseField('fields',   'Nivel de los 18 campos', 'decide cuánto hay para saquear');
releaseField('cranny',   'Nivel del escondite', '0 = sin escondite');
releaseField('wall',     'Nivel de la muralla', 'oficial 0: los natars sólo llegan a 1');
?>
	</tbody></table>

	<h3>Qué hace cada artefacto</h3>
	<table id="member" cellpadding="1" cellspacing="1">
		<thead><tr><td>Artefacto</td><td>Pequeño</td><td>Grande</td><td>Único</td><td>Efecto</td></tr></thead>
		<tbody>
<?php
foreach(artefactTypeCatalog() as $type => $info) {
	$values = array();
	foreach(array(ARTEFACT_SIZE_SMALL, ARTEFACT_SIZE_LARGE, ARTEFACT_SIZE_UNIQUE) as $size) {
		if($type === ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE) {
			$values[] = '&mdash;';
			continue;
		}
		$row = array('id' => 0, 'type' => $type, 'size' => $size, 'conquered' => 0);
		$values[] = htmlspecialchars(artefactEffectValueLabel($row), ENT_QUOTES, 'UTF-8');
	}
	echo '<tr><td style="text-align:left;">'.htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8').'</td>'
		.'<td class="hab">'.$values[0].'</td><td class="hab">'.$values[1].'</td><td class="hab">'.$values[2].'</td>'
		.'<td style="text-align:left;">'.htmlspecialchars($info['effect'], ENT_QUOTES, 'UTF-8').'</td></tr>';
}
?>
		</tbody>
	</table>

	<p>En este mundo (velocidad <?php echo SPEED; ?>x) un artefacto capturado tarda
	<b><?php echo $delayHours; ?> horas</b> en hacer efecto, y una cuenta sólo puede tener
	<b><?php echo ARTEFACT_MAX_ACTIVE; ?></b> activos a la vez, uno solo de ellos de cuenta.
	Cada aldea nace marcada como escenario (sin manutención de tropas ni hambruna, y sin
	reponer las tropas que le maten), sin residencia —así se toma con catapultas o con jefes—
	y aprovisionada para que valga la pena saquearla.</p>

<?php
if($existingCount > 0) {
	$villages = 0;
	foreach($existing as $artefact) {
		if((int)$artefact['vref'] > 0) {
			$villages++;
		}
	}
	echo '<div style="border:2px solid #a00;background:#ffe8e8;padding:10px;margin:10px 0;">'
		.'<p style="color:#a00;font-size:14px;"><b>Este mundo YA tiene '.$existingCount
		.' artefacto(s) repartido(s) en '.$villages.' aldea(s).</b></p>'
		.'<p>Volver a sembrar <b>no reemplaza nada</b>: crea otro juego completo de aldeas y '
		.'artefactos encima de los que ya hay. Los jugadores se encontrarían con dos artefactos '
		.'únicos del mismo tipo, y el podio de tres activos por cuenta pasaría a llenarse con '
		.'duplicados. <b>No se puede deshacer desde el panel.</b></p>'
		.'<p><label><input type="checkbox" name="confirmar" value="si"> '
		.'Entiendo que voy a <b>duplicar</b> los artefactos que ya existen y quiero hacerlo igual.'
		.'</label></p></div>';
	echo '<h4>Los que ya están:</h4><ul>';
	foreach($existing as $artefact) {
		echo '<li>'.htmlspecialchars(artefactDisplayName((int)$artefact['type'], (int)$artefact['size']), ENT_QUOTES, 'UTF-8')
			.' &mdash; '.htmlspecialchars((string)$database->getVillageField((int)$artefact['vref'], 'name'), ENT_QUOTES, 'UTF-8')
			.'</li>';
	}
	echo '</ul>';
} else {
	echo '<p>Este mundo todavía no tiene artefactos.</p>';
}
?>

	<p>
		<button type="submit">Recalcular vista previa</button>
		&nbsp;&nbsp;&nbsp;
		<button type="submit" formaction="../GameEngine/Admin/Mods/addArtefacts.php"
			style="font-weight:bold;">Sembrar <?php echo (int)$plan['total_villages']; ?> aldeas</button>
	</p>
</form>
<?php
if(isset($_GET['g'])) {
	echo '<p><b>Artefactos creados: '.(int)$_GET['g'].' aldeas.</b></p>';
}
if(isset($_GET['sinsitio'])) {
	echo '<p style="color:#a00;"><b>'.(int)$_GET['sinsitio'].' aldea(s) no se pudieron colocar:</b> '
		.'no quedaban casillas libres. Bajá los conteos o ensanchá los anillos.</p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'confirmar') {
	echo '<p style="color:#a00;"><b>No se sembró nada:</b> el mundo ya tiene artefactos y no '
		.'marcaste la casilla de confirmación.</p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'vacio') {
	echo '<p style="color:#a00;"><b>No se sembró nada:</b> con esos conteos el plan queda vacío.</p>';
}
?>
