<?php
$dataarray = explode(",", $message->readingNotice['data']);
$topic = $message->readingNotice['topic'];
$time = $message->readingNotice['time'];
$reportCurrentDelivery = isset($dataarray[7]) ? max(1, (int)$dataarray[7]) : 1;
$reportTotalDeliveries = isset($dataarray[8]) ? max(1, (int)$dataarray[8]) : 1;
$reportTrapStart = null;
$noDefenseMarkerIndex = null;
foreach($dataarray as $reportIndex => $reportField) {
	if($reportField === 'trap-data-v1') {
		$reportTrapStart = $reportIndex + 1;
		break;
	}
	if($reportField === 'no-defense-info-v1') {
		$noDefenseMarkerIndex = $reportIndex;
	}
}
if($reportTrapStart === null && $noDefenseMarkerIndex !== null && $noDefenseMarkerIndex >= 11) {
	$reportTrapStart = $noDefenseMarkerIndex - 11;
}
$reportHasPrisoners = false;
if($reportTrapStart !== null) {
	for($reportIndex = $reportTrapStart; $reportIndex <= $reportTrapStart + 10; $reportIndex++) {
		if(isset($dataarray[$reportIndex]) && (int)$dataarray[$reportIndex] !== 0) {
			$reportHasPrisoners = true;
			break;
		}
	}
}

// Desglose de defensores por jugador. Va en un único campo detrás del marcador
// `defenders-v1`, con los bandos separados por "|" y sus valores por ";". El bucle de
// arriba corta en `trap-data-v1`, que aparece antes, así que hace falta buscarlo aparte.
// Los informes viejos no lo traen: ahí se cae al desglose por tribu de siempre.
$reportDefenderParties = array();
$reportViewerIsDefender = false;
foreach($dataarray as $reportIndex => $reportField) {
	if($reportField !== 'defenders-v1' || !isset($dataarray[$reportIndex + 1])) {
		continue;
	}
	foreach(explode('|', $dataarray[$reportIndex + 1]) as $reportGroup) {
		$reportFields = explode(';', $reportGroup);
		if(count($reportFields) < 25) {
			continue;
		}
		$reportParty = array(
			'uid' => (int)$reportFields[0],
			'wref' => (int)$reportFields[1],
			'tribe' => (int)$reportFields[2],
			'sent' => array(),
			'dead' => array()
		);
		for($reportPosition = 0; $reportPosition < 11; $reportPosition++) {
			$reportParty['sent'][$reportPosition + 1] = (int)$reportFields[3 + $reportPosition];
			$reportParty['dead'][$reportPosition + 1] = (int)$reportFields[14 + $reportPosition];
		}
		if($reportParty['tribe'] < 1 || $reportParty['tribe'] > 5) {
			continue;
		}
		$reportDefenderParties[] = $reportParty;
		if($reportParty['uid'] > 0 && $reportParty['uid'] === (int)$session->uid) {
			$reportViewerIsDefender = true;
		}
	}
	break;
}
?>
