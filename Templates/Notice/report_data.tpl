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
?>
