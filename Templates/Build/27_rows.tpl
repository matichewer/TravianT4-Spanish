<?php
/**
 * Cómo se dibuja una fila de artefacto. Lo comparten las tres pestañas y la ficha.
 *
 * Antes cada pestaña repetía el mismo bloque de `echo` una vez por tipo de artefacto —el
 * 27_2 lo tenía nueve veces— y las copias ya no coincidían: una listaba el tipo 9 dos
 * veces y ninguna listaba el tipo 1, el tipo 7 se dibujaba con el ícono del 10, y los
 * encabezados decían "Aldea | Fecha" mientras las filas traían jugador y alianza.
 */
if(!function_exists('treasuryArtefactIcon')) {

	/** El ícono de 16x16. El necio tiene el suyo propio, no el del efecto que imita. */
	function treasuryArtefactIcon($row) {
		$type = (int)$row['type'];
		$class = $type === ARTEFACT_FOOL ? 'artefact_icon_fool' : 'artefact_icon_'.$type;
		return '<img class="'.$class.'" src="img/x.gif" alt="" title="'
			.htmlspecialchars(artefactTypeName($type), ENT_QUOTES, 'UTF-8').'">';
	}

	/**
	 * El nombre y el efecto. Salen del catálogo y no de las columnas `name`/`effect`,
	 * que guardan lo que escribió quien sembró el artefacto: había artefactos con el
	 * nombre de un efecto y el número de otro.
	 */
	function treasuryArtefactNameCell($row, $buildingId) {
		$type = (int)$row['type'];
		$size = (int)$row['size'];
		$name = htmlspecialchars(artefactDisplayName($type, $size), ENT_QUOTES, 'UTF-8');
		$scope = htmlspecialchars(artefactSizeName($size), ENT_QUOTES, 'UTF-8');
		$effect = artefactTypeEffectText($type);
		if($type === ARTEFACT_FOOL) {
			$roll = artefactFoolRoll($row);
			$effect = 'Ahora mismo imita a '.artefactTypeName($roll['type'])
				.($roll['penalty'] ? ', y en contra.' : '.');
		}
		return '<td class="nam"><a href="build.php?id='.(int)$buildingId.'&amp;show='.(int)$row['id'].'">'.$name.'</a>'
			.'<div class="info">Tesoro <b>'.artefactTreasuryRequirement($size).'</b>, alcance <b>'.$scope.'</b><br>'
			.htmlspecialchars($effect, ENT_QUOTES, 'UTF-8').'</div></td>';
	}

	/** El jugador dueño, enlazado a su perfil. */
	function treasuryArtefactOwnerCell($row) {
		global $database;
		$owner = (int)$row['owner'];
		$name = $database->getUserField($owner, 'username', 0);
		if($name === '' || $name === false || $name === null) {
			$name = 'Natars';
		}
		return '<td class="pla"><a href="spieler.php?uid='.$owner.'">'
			.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</a></td>';
	}

	/**
	 * La aldea que lo guarda, enlazada al mapa.
	 *
	 * La clase de la celda importa: el gpack define anchos distintos para `td.vil` (la
	 * columna de aldea de la tabla de artefactos propios) y `td.pla` (la de las listas
	 * del servidor). Ver `div#build.gid27` en compact1.css.
	 */
	function treasuryArtefactVillageCell($row, $class = 'pla') {
		global $database, $generator;
		$vref = (int)$row['vref'];
		$name = $database->getVillageField($vref, 'name');
		if($name === '' || $name === false || $name === null) {
			$name = '[?]';
		}
		return '<td class="'.$class.'"><a href="karte.php?d='.$vref.'&amp;c='.$generator->getMapCheck($vref).'">'
			.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</a></td>';
	}

	/**
	 * La distancia desde la aldea que se está mirando, respetando que el mapa da la
	 * vuelta en los bordes. Antes esto se ordenaba con una función haversine —la
	 * distancia geodésica sobre la superficie de la Tierra— aplicada a coordenadas del
	 * mapa, con el resultado usado además como clave de un array, así que dos artefactos
	 * a la misma distancia se pisaban y sólo se veía uno.
	 */
	function treasuryArtefactDistance($row, $fromCoor) {
		global $database;
		$coor = $database->getCoor((int)$row['vref']);
		if(!is_array($coor) || !is_array($fromCoor)) {
			return 0.0;
		}
		return natarSettlementDistance($fromCoor['x'], $fromCoor['y'], $coor['x'], $coor['y']);
	}

	/**
	 * El estado de activación, para las filas propias.
	 *
	 * `td.inactive` es la clase que el gpack ya traía para grisar una fila de artefacto:
	 * existía en el CSS y ninguna plantilla la usaba.
	 */
	function treasuryArtefactStateCell($row, $activeRows) {
		global $generator;
		$state = artefactActivationState($row, $activeRows);
		$label = artefactActivationStateLabel($state);
		if($state['state'] === 'pending') {
			return '<td class="cap">'.$label.' '.$generator->getTimeFormat($state['seconds']).'</td>';
		}
		if($state['state'] === 'displaced') {
			return '<td class="cap inactive" title="Sólo pueden estar activos '
				.ARTEFACT_MAX_ACTIVE.' artefactos a la vez, y uno solo de cuenta. Tienen prioridad los más antiguos.">'
				.$label.'</td>';
		}
		return '<td class="cap"><b>'.$label.'</b></td>';
	}
}
