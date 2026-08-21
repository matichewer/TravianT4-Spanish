<?php
/**
 * Regresión del "Completar" del Plus: terminar las construcciones con oro.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_gold_finish.php
 *
 * Por qué existe. Templates/Plus/3.tpl tenía su propia copia del fin de obra —un
 * UPDATE a fdata, modifyPop y addCP escritos a mano— en vez de llamar a la del motor.
 * La copia se quedó atrás en tres cosas, todas silenciosas:
 *
 *   - sin `accrueProductionBeforeChange()`, terminar un campo de recursos con oro
 *     aplicaba el nivel nuevo a todas las horas que el jugador estuvo desconectado;
 *   - sin `applyStorageCapacityDelta()`, un almacén o granero terminado con oro no
 *     movía `maxstore` / `maxcrop`;
 *   - sumaba a `vdata.cp` el total de PC del nivel nuevo en vez del incremento, que es
 *     exactamente el bug que infló la cultura de todo el mundo.
 *
 * Y terminaba también los pedidos al constructor maestro (`master = 1`), que todavía
 * no pagaron recursos: comprobaba que alcanzaran y no los descontaba nunca. Edificios
 * gratis por 2 de oro.
 *
 * Todo eso se arregla de una sola forma: que no haya una segunda copia. Esto verifica
 * que siga sin haberla.
 */

error_reporting(E_ALL);

$GLOBALS['fails'] = array();
function goldFinishAssert($condition, $message) {
	if(!$condition) {
		$GLOBALS['fails'][] = $message;
		echo "  FAIL  ".$message."\n";
	}
}

$plus = file_get_contents(dirname(__DIR__).'/Templates/Plus/3.tpl');
$automation = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
goldFinishAssert($plus !== false && $automation !== false, 'No se pudieron leer los archivos.');

// --- La plantilla no vuelve a tener su propio fin de obra -------------------------

goldFinishAssert(
	strpos($plus, 'finishVillageConstructionsNow($village->wid)') !== false,
	'El "Completar" del Plus dejó de usar el fin de obra del motor.'
);
foreach(array(
	'fdata set f' => 'un UPDATE a fdata escrito a mano',
	'modifyPop(' => 'una llamada a modifyPop propia',
	'addCP(' => 'una llamada a addCP propia',
	'resourceRequired(' => 'su propio cálculo de costo/población del nivel',
	'DELETE FROM ".TB_PREFIX."bdata' => 'un borrado de bdata propio'
) as $needle => $what) {
	goldFinishAssert(
		strpos($plus, $needle) === false,
		'Templates/Plus/3.tpl volvió a tener '.$what.'.'
	);
}

// --- Sólo trabajos reales, nunca pedidos del constructor maestro ------------------

goldFinishAssert(
	preg_match('/function finishVillageConstructionsNow\(.*?WHERE wid = ".\$villageId." AND master = 0"/s', $automation) === 1,
	'finishVillageConstructionsNow() dejó de filtrar master = 0: terminaría pedidos del constructor maestro sin cobrar los recursos.'
);
goldFinishAssert(
	preg_match('/function finishVillageConstructionsNow\(.*?\$blocked = array\(25, 26, 40\);/s', $automation) === 1,
	'El oro volvió a poder apurar la Residencia, el Palacio o la Maravilla.'
);
goldFinishAssert(
	preg_match("/function finishVillageConstructionsNow\(.*?f99t'\] === 40.*?wonder_village'\] = true/s", $automation) === 1,
	'Una Aldea de la Maravilla volvió a poder apurar construcciones con oro.'
);
goldFinishAssert(
	strpos($plus, "if(\$finished['wonder_village']) {") !== false
		&& preg_match("/wonder_village'\]\) \{.*?\} else \{.*?modifyGold/s", $plus) === 1,
	'En una Aldea de la Maravilla se cobra el oro aunque no se complete nada.'
);
goldFinishAssert(
	preg_match('/function finishVillageConstructionsNow\(.*?\$this->buildComplete\(\$now\);/s', $automation) === 1,
	'finishVillageConstructionsNow() dejó de delegar en buildComplete().'
);

// --- Y el fin de obra del motor sigue haciendo las tres cosas que faltaban --------

$buildComplete = substr($automation, strpos($automation, 'private function buildComplete('));
$buildComplete = substr($buildComplete, 0, strpos($buildComplete, 'public function finishVillageConstructionsNow('));
if($buildComplete === false || $buildComplete === '') {
	// El orden de los métodos cambió: se compara contra el archivo entero, que es
	// menos preciso pero no da un falso OK.
	$buildComplete = $automation;
}
foreach(array(
	'accrueProductionBeforeChange(' => 'cobrar la producción con el nivel viejo antes de cambiarlo',
	'applyStorageCapacityDelta(' => 'actualizar la capacidad de almacén y granero',
	'refreshEmbassyCapacity(' => 'refrescar el cupo de la embajada',
	'$database->modifyPop(' => 'sumar los habitantes',
	'$database->addCP(' => 'sumar los puntos de cultura'
) as $needle => $what) {
	goldFinishAssert(
		strpos($buildComplete, $needle) !== false,
		'buildComplete() dejó de '.$what.'.'
	);
}
goldFinishAssert(
	strpos($buildComplete, '$pop = $this->getPop($indi[\'type\'], ($level - 1));') !== false,
	'buildComplete() dejó de sacar el incremento de habitantes y de cultura de getPop().'
);

if($GLOBALS['fails']) {
	fwrite(STDERR, "\nFallaron ".count($GLOBALS['fails'])." comprobaciones del fin de obra con oro.\n");
	exit(1);
}

echo "OK: el \"Completar\" del Plus usa el fin de obra del motor\n";
