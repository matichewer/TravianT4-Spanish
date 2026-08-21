<?php
/**
 * La lista de aldeas del resumen (dorf3.php), compartida por sus cinco pestañas.
 *
 * Existe por una sola razón: **las cinco pestañas tienen que listar las aldeas en el
 * mismo orden, y el mismo que el cartel lateral.** Antes cada `.tpl` llamaba a
 * `getProfileVillages()` por su cuenta, que ordena por población descendente, mientras el
 * cartel usa el orden de fundación — así que la misma lista salía de dos formas distintas
 * según dónde se mirara, y un jugador que numera sus aldeas no reconocía ninguna de las dos.
 *
 * El orden **no** se arregla cambiando `getProfileVillages()`: la usan una treintena de
 * lugares más (panel de Admin, perfiles públicos, alianza, mensajería) donde el orden por
 * población es el que corresponde. Se reordena acá, sólo para esta pantalla.
 */

/**
 * Aldeas del jugador en orden de fundación, con la fila entera de `vdata`.
 *
 * Es lo único que las pestañas necesitan llamar: `foreach(villageOverviewVillages($uid) …)`.
 */
function villageOverviewVillages($uid) {
	global $database;
	$uid = (int)$uid;
	$rows = array();
	foreach($database->getProfileVillages($uid) as $row) {
		$rows[(int)$row['wref']] = $row;
	}
	$ordered = array();
	foreach(villageOverviewFoundationOrder($rows,$database->getVillagesIDByFoundation($uid)) as $wref) {
		$ordered[] = $rows[$wref];
	}
	return $ordered;
}

/**
 * Reordena wrefs según la lista de fundación.
 *
 * Pura a propósito, para poder probar lo único que importa de verdad acá: **ninguna aldea
 * se pierde**. Una que no esté en la lista de fundación —porque la consulta falló y volvió
 * vacía, o porque su fila tiene un `created` raro— se agrega al final en el orden que
 * traía, en vez de desaparecer de la pantalla. Un reordenamiento que hace lookup contra
 * otra lista es exactamente el sitio donde una aldea se esfuma en silencio.
 */
function villageOverviewFoundationOrder(array $villageRows, $foundationOrder) {
	$ordered = array();
	foreach((array)$foundationOrder as $wref) {
		$wref = (int)$wref;
		if(isset($villageRows[$wref]) && !isset($ordered[$wref])) {
			$ordered[$wref] = $wref;
		}
	}
	foreach($villageRows as $wref => $row) {
		$wref = (int)$wref;
		if(!isset($ordered[$wref])) {
			$ordered[$wref] = $wref;
		}
	}
	return array_values($ordered);
}
