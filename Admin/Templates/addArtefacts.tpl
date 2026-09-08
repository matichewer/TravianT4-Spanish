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
$schedule = artefactReleaseScheduleStatus($database);
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
// El plano va en su propia fila y no en el bucle de tamaños porque no es un tamaño: hay un
// solo plano de construcción y se siembran tantas copias como pida la configuración.
$planRow = $plan['plans'];
$totalTroops += $planRow['stats']['troops'] * $planRow['villages'];
echo '<tr style="background:#f3efe0;">'
	.'<td>'.htmlspecialchars($planRow['label'], ENT_QUOTES, 'UTF-8').'</td>'
	.'<td class="hab">'.(int)$planRow['villages'].'<br><span style="color:#666;">no es un tamaño</span></td>'
	.'<td class="hab">'.n($planRow['stats']['troops']).'</td>'
	.'<td class="hab">'.n($planRow['stats']['infantry']).'</td>'
	.'<td class="hab">'.n($planRow['stats']['cavalry']).'</td>'
	.'<td class="hab">'.n($planRow['stats']['upkeep']).'/h</td>'
	.'<td class="hab">'.round($planRow['ring'][0]).' &ndash; '.round($planRow['ring'][1]).'</td>'
	.'</tr>';
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
releaseField('count_unique', 'Aldeas por tipo — único', '7 tipos: el plano de almacenamiento no tiene único');
releaseField('count_plans',  'Planos de construcción', 'no se multiplica por tipos: es uno solo');
?>
	<tr><td colspan="3" style="text-align:left;color:#666;">
		El <b>plano de construcción</b> es lo que habilita la Maravilla del Mundo: uno en la
		alianza llega al nivel <?php echo (int)WONDER_PLAN_SOLO_MAX_LEVEL; ?>, y del
		<?php echo (int)WONDER_PLAN_SOLO_MAX_LEVEL + 1; ?> en adelante hacen falta dos, uno del
		dueño de la Maravilla y otro de un aliado. Con menos de dos nadie termina la partida;
		con dos por alianza, cada alianza puede.
	</td></tr>
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
releaseField('tier_plan',      'El plano vale, sobre el pequeño', 'el oficial no publica un número');
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
releaseField('ring_plan_min',   'Plano — desde (%)');
releaseField('ring_plan_max',   'Plano — hasta (%)');
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
foreach(artefactEffectTypeCatalog() as $type => $info) {
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
<?php
$planInfo = artefactTypeCatalog();
$planInfo = $planInfo[ARTEFACT_PLAN];
echo '<tr style="background:#f3efe0;"><td style="text-align:left;">'
	.htmlspecialchars($planInfo['name'], ENT_QUOTES, 'UTF-8').'</td>'
	.'<td class="hab" colspan="3">no escala con el tamaño</td>'
	.'<td style="text-align:left;">'.htmlspecialchars($planInfo['effect'], ENT_QUOTES, 'UTF-8').'</td></tr>';
?>
		</tbody>
	</table>

	<p>El plano de construcción se guarda en un <b>Tesoro de nivel
	<?php echo (int)artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL, ARTEFACT_PLAN); ?></b>, no 20 como
	los artefactos de cuenta, y <b>no ocupa</b> ninguno de los tres huecos de artefacto activo.
	En la aldea de la Maravilla no se puede construir un Tesoro, así que el plano siempre vive
	en otra aldea &mdash; que es lo que lo vuelve robable.</p>

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

	<h3>Cuándo</h3>
<?php
if(!$schedule['available']) {
	echo '<p style="color:#a00;">Este mundo todavía no tiene las columnas de la liberación '
		.'programada. Aplicá <code>tools/migrations.sql</code> y volvé a entrar.</p>';
} else {
	if($schedule['scheduled']) {
		echo '<div style="border:2px solid #084;background:#eaf7ee;padding:10px;margin:10px 0;">'
			.'<p><b>Hay una liberación programada para el '
			.date('d/m/Y \a \l\a\s H:i', $schedule['at']).'</b> '
			.'(faltan '.floor($schedule['seconds'] / 3600).' h '
			.floor(($schedule['seconds'] % 3600) / 60).' min).</p>'
			.'<p>Va a sembrar el plan tal como se guardó ese día, no el que estés viendo ahora: '
			.'volvé a apretar <b>Programar</b> si querés que use estos números.</p></div>';
	} else if($schedule['done'] > 0) {
		echo '<p>La última liberación programada se disparó el '
			.date('d/m/Y H:i', $schedule['done']).'.</p>';
	} else {
		echo '<p>No hay ninguna liberación programada. El servidor no va a soltar nada solo.</p>';
	}
?>
	<p>En el Travian oficial los artefactos aparecen solos en una fecha anunciada de antemano,
	y esa fecha es la que arranca la carrera final. Programala y avisales a los jugadores: no
	hace falta que estés conectado, lo dispara el primer jugador que entre después de la hora.</p>
	<p>
		<label>Fecha y hora (<?php echo date_default_timezone_get(); ?>):
			<input type="datetime-local" name="release_at" class="fm" style="width:220px;"
				value="<?php echo $schedule['scheduled']
					? date('Y-m-d\TH:i', $schedule['at'])
					: date('Y-m-d\TH:i', time() + 7 * 86400); ?>"></label>
		&nbsp;&nbsp;
		<button type="submit" formaction="../GameEngine/Admin/Mods/scheduleArtefacts.php">
			Programar con estos números</button>
<?php if($schedule['scheduled']) { ?>
		&nbsp;&nbsp;
		<button type="submit" name="cancelar" value="si"
			formaction="../GameEngine/Admin/Mods/scheduleArtefacts.php">Cancelar la programación</button>
<?php } ?>
	</p>
<?php } ?>

	<p>
		<button type="submit">Recalcular vista previa</button>
		&nbsp;&nbsp;&nbsp;
		<button type="submit" formaction="../GameEngine/Admin/Mods/addArtefacts.php"
			style="font-weight:bold;">Sembrar <?php echo (int)$plan['total_villages']; ?> aldeas ahora</button>
	</p>
</form>

<?php
// El "deshacer" del sembrado. Va en su propio formulario y no en el de arriba porque pide
// otra confirmación: mezclarlos haría que la casilla de "sí, duplicá los artefactos" sirviera
// también para borrarlos, que es exactamente el accidente que esto viene a evitar.
if($existingCount > 0) {
	$wipe = artefactReleaseWipe($database, false);
?>
<hr style="margin:20px 0;">
<h3>Borrar todo y empezar de nuevo</h3>
<div style="border:2px solid #a00;background:#ffe8e8;padding:10px;margin:10px 0;">
	<p>Esto borra <b><?php echo (int)$wipe['artefacts']; ?> artefacto(s)</b> y arrasa
	<b><?php echo count($wipe['villages']); ?> aldea(s) natar</b>, liberando sus casillas del mapa.
	Sirve para volver a sembrar con otros números.</p>
	<p><b>No toca</b> las Aldeas de la Maravilla, la capital natar, ni la aldea de ningún jugador.
<?php if($wipe['player_held']) { ?>
	Hay <b><?php echo count($wipe['player_held']); ?></b> artefacto(s) ya capturados por jugadores:
	a esos se les saca el artefacto y su aldea queda intacta.
<?php } ?>
<?php if($wipe['protected']) { ?>
	Hay <b><?php echo count($wipe['protected']); ?></b> en una Maravilla o en la capital natar:
	esas aldeas tampoco se borran.
<?php } ?>
	</p>
	<p><b>No se puede deshacer.</b> Si algún jugador ya capturó un artefacto, se lo estás sacando.</p>
	<form action="../GameEngine/Admin/Mods/wipeArtefacts.php" method="POST">
		<input type="hidden" name="admid" value="<?php echo (int)$_SESSION['id']; ?>">
		<p><label><input type="checkbox" name="confirmar_borrado" value="si">
		Entiendo que voy a <b>borrar todos los artefactos</b> del servidor y quiero hacerlo.</label></p>
		<button type="submit">Borrar los <?php echo (int)$wipe['artefacts']; ?> artefactos</button>
	</form>
</div>
<?php } ?>
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
if(isset($_GET['e']) && $_GET['e'] === 'confirmarborrado') {
	echo '<p style="color:#a00;"><b>No se borró nada:</b> no marcaste la casilla de confirmación.</p>';
}
if(isset($_GET['borrados'])) {
	echo '<p><b>Borrados '.(int)$_GET['borrados'].' artefacto(s) y '.(int)$_GET['aldeas']
		.' aldea(s) natar.</b>';
	if(isset($_GET['dejugadores'])) {
		echo ' '.(int)$_GET['dejugadores'].' estaban en manos de jugadores: se les quitó el artefacto'
			.' y su aldea quedó intacta.';
	}
	echo '</p>';
}
if(isset($_GET['programado'])) {
	echo '<p><b>Liberación programada para el '
		.date('d/m/Y H:i', (int)$_GET['programado']).'.</b> El plan quedó congelado tal como '
		.'estaba en la vista previa.</p>';
}
if(isset($_GET['cancelado'])) {
	echo '<p><b>Programación cancelada.</b> El servidor no va a soltar nada solo.</p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'fecha') {
	echo '<p style="color:#a00;"><b>No se programó nada:</b> esa fecha no se entiende.</p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'pasado') {
	echo '<p style="color:#a00;"><b>No se programó nada:</b> esa fecha ya pasó, así que se '
		.'dispararía enseguida. Si querés sembrar ahora usá el botón de sembrar.</p>';
}
if(isset($_GET['e']) && $_GET['e'] === 'sinmigracion') {
	echo '<p style="color:#a00;"><b>No se programó nada:</b> a esta base le falta la migración '
		.'de <code>artefact_release_at</code>. Aplicá <code>tools/migrations.sql</code>.</p>';
}
?>
