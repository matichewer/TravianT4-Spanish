<?php
$dataarray = explode(",", $message->readingNotice['data']);
$topic = $message->readingNotice['topic'];
$time = $message->readingNotice['time'];
$reportCurrentDelivery = isset($dataarray[7]) ? max(1, (int)$dataarray[7]) : 1;
$reportTotalDeliveries = isset($dataarray[8]) ? max(1, (int)$dataarray[8]) : 1;
?>
