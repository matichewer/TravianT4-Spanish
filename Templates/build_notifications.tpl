<?php
$buildNotificationJobs = array();

foreach($building->buildArray as $notificationJob) {
	if((int)$notificationJob['master'] !== 0) {
		continue;
	}

	$notificationPage = ((int)$notificationJob['field'] <= 18) ? 'dorf1.php' : 'dorf2.php';
	$buildNotificationJobs[] = array(
		'id' => (int)$notificationJob['id'],
		'villageId' => (int)$notificationJob['wid'],
		'village' => $village->vname,
		'building' => $building->procResType($notificationJob['type']),
		'level' => (int)$notificationJob['level'],
		'finishAt' => (int)$notificationJob['timestamp'],
		'url' => $notificationPage.'?newdid='.(int)$notificationJob['wid']
	);
}

$buildNotificationJson = json_encode(
	$buildNotificationJobs,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
);
?>
<script type="text/javascript">
	if(window.TravianBuildNotifications) {
		TravianBuildNotifications.syncVillage(
			<?php echo (int)$village->wid; ?>,
			<?php echo $buildNotificationJson; ?>,
			<?php echo time(); ?>
		);
	}
</script>
