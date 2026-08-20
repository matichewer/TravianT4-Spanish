<?php
/**
 * Fuente única de las dos pestañas de tropas del resumen de aldeas (dorf3.php?s=5).
 *
 * Las dos agrupan por UBICACIÓN, como en el T4 oficial:
 *
 *   - **Tropas propias**: matriz aldea x unidad con las tropas del jugador que están EN
 *     cada aldea, más un total por tipo. Compacta y comparable entre aldeas.
 *   - **Tropas en aldeas**: una tabla por aldea con todo lo que hay dentro, incluidos los
 *     refuerzos de otros jugadores y la guarnición de los oasis anexados, con el consumo
 *     de cereal.
 *
 * **Lo que está fuera de la aldea no se muestra acá, y es a propósito.** Un ejército que
 * refuerza otra aldea sale de `units` y pasa a `enforcement`; uno en camino vive en
 * `movement`+`attacks`. En el T4 oficial esos se ven en la plaza de reuniones (bloque
 * "Refuerzos" de Templates/Build/16.tpl, que lista a qué aldea fue cada grupo), no en el
 * resumen. Una versión de esta pantalla los sumaba a la fila de su aldea natal: es más
 * informativo, pero no es lo que hace el juego original. Si aparece un reporte de que
 * "faltan tropas" en esta pantalla, esa es la respuesta, no un bug.
 *
 * Dónde vive cada tropa, que sí hay que tener presente para no contar dos veces:
 *
 *   units       en su aldea                       vref = aldea
 *   enforcement reforzando otra aldea o un oasis   `from` = aldea natal, vref = destino
 *   movement    en camino, colonos, aventura       (ver mysqli_DB::getVillageMovement)
 *   prisoners   atrapadas en las trampas de otro   `from` = aldea natal
 *
 * Son excluyentes: una tropa sale de `units` en el mismo request en que entra al
 * siguiente. Esta pantalla sólo lee `units` y `enforcement`; el cereal, que sí tiene que
 * cobrarlas todas, las junta en `Technology::getAllUnits()`.
 *
 * Ojo con los ids de unidad: son absolutos (u1..u50, diez por tribu) en `units` y en
 * `enforcement`, y relativos a la tribu (t1..t10, t11 = héroe) en `attacks` y `prisoners`.
 */

/**
 * Ids de unidad de una tribu, o null si no es una tribu con unidades.
 *
 * Tribus: 1 romanos, 2 germanos, 3 galos, 4 naturaleza, 5 natares. La copia que había en
 * la plantilla mandaba la tribu 4 a u41 (natares) en vez de a u31 (naturaleza); no se veía
 * porque un jugador sólo puede ser 1, 2 o 3, pero cualquier reutilización heredaba el
 * error. El resto del motor usa siempre esta fórmula.
 */
function troopOverviewTribeRange($tribe) {
	$tribe = (int)$tribe;
	if($tribe < 1 || $tribe > 5) {
		return null;
	}
	return array(($tribe - 1) * 10 + 1, $tribe * 10);
}

/** Acumulador vacío para el rango de una tribu, con el héroe aparte. */
function troopOverviewEmptyUnits($start, $end) {
	$units = array();
	for($id = (int)$start; $id <= (int)$end; $id++) {
		$units['u'.$id] = 0;
	}
	$units['hero'] = 0;
	return $units;
}

/** Suma una fila de `units` o `enforcement` (ids absolutos u1..u50 + hero). */
function troopOverviewAddUnitRow(array $units, $row) {
	if(!is_array($row)) {
		return $units;
	}
	foreach($units as $key => $amount) {
		if(isset($row[$key])) {
			$units[$key] = $amount + max(0,(int)$row[$key]);
		}
	}
	return $units;
}

/** Suma dos acumuladores del mismo rango. */
function troopOverviewSumUnits(array $units, array $other) {
	foreach($units as $key => $amount) {
		$units[$key] = $amount + (isset($other[$key]) ? max(0,(int)$other[$key]) : 0);
	}
	return $units;
}

/** Cuántas tropas hay en total en un acumulador (el héroe cuenta como una). */
function troopOverviewCount(array $units) {
	$total = 0;
	foreach($units as $amount) {
		$total += max(0,(int)$amount);
	}
	return $total;
}

/**
 * Tribu a la que pertenece una fila de `units`/`enforcement`, mirando qué decena tiene
 * tropas. Es el plan B para cuando el origen de un refuerzo ya no existe (una aldea
 * arrasada deja la fila de `enforcement` viva) y no se le puede preguntar la tribu al
 * dueño: sin esto la fila se imprimiría con las columnas de otra tribu.
 */
function troopOverviewDetectTribe($row) {
	if(!is_array($row)) {
		return 0;
	}
	for($tribe = 1; $tribe <= 5; $tribe++) {
		$range = troopOverviewTribeRange($tribe);
		for($id = $range[0]; $id <= $range[1]; $id++) {
			if(isset($row['u'.$id]) && (int)$row['u'.$id] > 0) {
				return $tribe;
			}
		}
	}
	return 0;
}

/** Lista de ids saneada y sin repetidos, lista para un IN (...). */
function troopOverviewIdList($ids) {
	$clean = array();
	foreach((array)$ids as $id) {
		$id = (int)$id;
		if($id > 0) {
			$clean[$id] = $id;
		}
	}
	return $clean;
}

/**
 * Nombre y dueño de cada wref, sea aldea u oasis. Dos consultas para toda la página: las
 * etiquetas de los refuerzos son lo único que necesita salir de vdata/odata y resolverlas
 * de a una multiplicaba las consultas por el número de refuerzos.
 */
function troopOverviewResolvePlaces($wrefs) {
	global $database;
	$ids = troopOverviewIdList($wrefs);
	$places = array();
	if(empty($ids)) {
		return $places;
	}
	$in = implode(',',$ids);
	foreach($database->query_return("SELECT wref,name,owner FROM ".TB_PREFIX."vdata WHERE wref IN ($in)") as $row) {
		$places[(int)$row['wref']] = array(
			'name' => (string)$row['name'],
			'owner' => (int)$row['owner'],
			'oasis' => false,
		);
	}
	foreach($database->query_return("SELECT wref,name,conqured FROM ".TB_PREFIX."odata WHERE wref IN ($in)") as $row) {
		$wref = (int)$row['wref'];
		if(isset($places[$wref])) {
			continue;
		}
		$places[$wref] = array(
			'name' => (string)$row['name'],
			'owner' => (int)$row['conqured'],
			'oasis' => true,
		);
	}
	return $places;
}

/** Nombre de usuario y tribu de cada uid, en una sola consulta. */
function troopOverviewResolveUsers($uids) {
	global $database;
	$ids = troopOverviewIdList($uids);
	$users = array();
	if(empty($ids)) {
		return $users;
	}
	$in = implode(',',$ids);
	foreach($database->query_return("SELECT id,username,tribe FROM ".TB_PREFIX."users WHERE id IN ($in)") as $row) {
		$users[(int)$row['id']] = array(
			'username' => (string)$row['username'],
			'tribe' => (int)$row['tribe'],
		);
	}
	return $users;
}

/** Coordenadas de varios wref de una sola vez, para etiquetar oasis y aldeas ajenas. */
function troopOverviewResolveCoords($wrefs) {
	global $database;
	$ids = troopOverviewIdList($wrefs);
	$coords = array();
	if(empty($ids)) {
		return $coords;
	}
	$in = implode(',',$ids);
	foreach($database->query_return("SELECT id,x,y FROM ".TB_PREFIX."wdata WHERE id IN ($in)") as $row) {
		$coords[(int)$row['id']] = array((int)$row['x'],(int)$row['y']);
	}
	return $coords;
}

/**
 * Nombre de un lugar con sus coordenadas, en texto plano.
 *
 * Las coordenadas no se agregan si el nombre ya termina en ellas: los nombres de las
 * aldeas natar independientes las llevan pegadas para ser únicos (`natarSettlementName()`),
 * y repetirlas daba "Atalaya natar (15|78) (15|78)".
 */
function troopOverviewPlaceName($wref, $places, $coords) {
	$wref = (int)$wref;
	$name = isset($places[$wref]) ? trim($places[$wref]['name']) : '';
	if($name === '') {
		$name = 'Lugar desconocido';
	}
	if(isset($coords[$wref])) {
		$suffix = '('.$coords[$wref][0].'|'.$coords[$wref][1].')';
		if(substr($name,-strlen($suffix)) !== $suffix) {
			$name .= ' '.$suffix;
		}
	}
	return $name;
}

/**
 * Lo mismo, con enlace al mapa.
 *
 * `vdata.name` y `odata.name` se guardan ya escapados (`Profile.php` los pasa por
 * RemoveXSS), así que volver a escaparlos convertiría cada `&amp;` en `&amp;amp;`.
 * `users.username`, en cambio, se guarda crudo: ese sí lo escapa quien lo imprime.
 */
function troopOverviewPlaceLabel($wref, $places, $coords) {
	global $generator;
	$wref = (int)$wref;
	$name = troopOverviewPlaceName($wref,$places,$coords);
	if($wref > 0 && is_object($generator)) {
		return '<a href="karte.php?d='.$wref.'&amp;c='.$generator->getMapCheck($wref).'">'.$name.'</a>';
	}
	return $name;
}

/**
 * Pestaña "Tropas propias": lo que el jugador tiene EN cada una de sus aldeas.
 *
 * Devuelve wref => acumulador de la tribu del jugador. No incluye lo que está fuera (ver
 * la cabecera del archivo) ni los refuerzos ajenos, que son de otro dueño y van en la otra
 * pestaña.
 *
 * Una sola consulta para toda la página: un jugador con veinte aldeas abre esta pestaña
 * igual que uno con dos.
 */
function troopOverviewOwnTroops($villageIds, $tribe) {
	global $database;
	$range = troopOverviewTribeRange($tribe);
	$ids = troopOverviewIdList($villageIds);
	$out = array();
	if($range === null || empty($ids)) {
		return $out;
	}
	list($start,$end) = $range;
	$empty = troopOverviewEmptyUnits($start,$end);
	foreach($ids as $id) {
		$out[$id] = $empty;
	}
	$in = implode(',',$ids);
	foreach($database->query_return("SELECT * FROM ".TB_PREFIX."units WHERE vref IN ($in)") as $row) {
		$vid = (int)$row['vref'];
		if(isset($out[$vid])) {
			$out[$vid] = troopOverviewAddUnitRow($out[$vid],$row);
		}
	}
	return $out;
}

/**
 * Pestaña "Tropas en aldeas": los grupos que hay dentro de cada aldea propia.
 *
 * Un grupo es lo que en la plaza de reuniones se ve como una tabla: tropas propias,
 * refuerzos de cada jugador, animales enjaulados, y la guarnición de cada oasis anexado
 * (que no está en la aldea pero la aldea es la que le paga el cereal, igual que en
 * `Technology::getAllUnits`). Cada grupo trae su tribu porque las columnas que se imprimen
 * son las de la tribu del grupo, no las del jugador que mira.
 */
function troopOverviewVillageGarrisons($villageIds, $ownerTribe) {
	global $database;
	$ids = troopOverviewIdList($villageIds);
	$out = array();
	if(empty($ids)) {
		return $out;
	}
	foreach($ids as $id) {
		$out[$id] = array();
	}
	$in = implode(',',$ids);
	$ownRange = troopOverviewTribeRange($ownerTribe);
	$natureRange = troopOverviewTribeRange(4);

	// Tropas propias en la aldea, y los animales enjaulados, que viven en la misma fila de
	// `units` pero en la decena de la naturaleza y no se veían en ninguna pantalla.
	foreach($database->query_return("SELECT * FROM ".TB_PREFIX."units WHERE vref IN ($in)") as $row) {
		$vid = (int)$row['vref'];
		if(!isset($out[$vid])) {
			continue;
		}
		if($ownRange !== null) {
			$out[$vid][] = array(
				'kind' => 'own',
				'tribe' => (int)$ownerTribe,
				'start' => $ownRange[0],
				'end' => $ownRange[1],
				'where' => $vid,
				'from' => $vid,
				'units' => troopOverviewAddUnitRow(troopOverviewEmptyUnits($ownRange[0],$ownRange[1]),$row),
			);
		}
		$caged = troopOverviewAddUnitRow(troopOverviewEmptyUnits($natureRange[0],$natureRange[1]),$row);
		// El héroe está en la misma fila de `units` y ya lo cuenta el grupo propio: dejarlo
		// acá lo mostraría —y lo cobraría— dos veces en la misma aldea.
		$caged['hero'] = 0;
		if(troopOverviewCount($caged) > 0) {
			$out[$vid][] = array(
				'kind' => 'caged',
				'tribe' => 4,
				'start' => $natureRange[0],
				'end' => $natureRange[1],
				'where' => $vid,
				'from' => 0,
				'units' => $caged,
			);
		}
	}

	// Refuerzos dentro de la aldea, y dentro de los oasis que la aldea anexó.
	$oasisOwner = array();
	foreach($database->query_return("SELECT wref,conqured FROM ".TB_PREFIX."odata WHERE conqured IN ($in)") as $row) {
		$oasisOwner[(int)$row['wref']] = (int)$row['conqured'];
	}
	$hosts = $ids;
	foreach($oasisOwner as $wref => $vid) {
		$hosts[$wref] = $wref;
	}
	$hostIn = implode(',',$hosts);
	$rows = $database->query_return("SELECT * FROM ".TB_PREFIX."enforcement WHERE vref IN ($hostIn)");

	$origins = array();
	foreach($rows as $row) {
		$origins[] = (int)$row['from'];
	}
	$places = troopOverviewResolvePlaces($origins);
	$users = troopOverviewResolveUsers(array_map(function($place){ return $place['owner']; },$places));

	foreach($rows as $row) {
		$host = (int)$row['vref'];
		$vid = isset($oasisOwner[$host]) ? $oasisOwner[$host] : $host;
		if(!isset($out[$vid])) {
			continue;
		}
		$from = (int)$row['from'];
		// `from = 0` es la naturaleza: los animales de un oasis viven en `enforcement` sin
		// aldea de origen. La plaza de reuniones ya los trata así.
		$ownerId = ($from > 0 && isset($places[$from])) ? $places[$from]['owner'] : 0;
		$tribe = ($ownerId > 0 && isset($users[$ownerId])) ? $users[$ownerId]['tribe'] : 0;
		if($tribe < 1 || $tribe > 5) {
			$tribe = $from === 0 ? 4 : troopOverviewDetectTribe($row);
		}
		$range = troopOverviewTribeRange($tribe);
		if($range === null) {
			continue;
		}
		$units = troopOverviewAddUnitRow(troopOverviewEmptyUnits($range[0],$range[1]),$row);
		if(troopOverviewCount($units) === 0) {
			continue;
		}
		$out[$vid][] = array(
			'kind' => $host === $vid ? 'support' : 'oasis',
			'tribe' => $tribe,
			'start' => $range[0],
			'end' => $range[1],
			'where' => $host,
			'from' => $from,
			'owner' => $ownerId,
			'units' => $units,
		);
	}
	return $out;
}
