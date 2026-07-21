<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<title><?php echo SERVER_NAME ?></title>
		<link rel="shortcut icon" href="favicon.ico" />
		<meta http-equiv="cache-control" content="max-age=0" />
		<meta http-equiv="pragma" content="no-cache" />
		<meta http-equiv="expires" content="0" />
		<meta http-equiv="imagetoolbar" content="no" />
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="content-language" content="ir" />
		<link href="gpack/travian_Travian_4.0_41/lang/ir/compact.css?asd423" rel="stylesheet" type="text/css" />
        <link href="gpack/travian_Travian_4.0_41/lang/ir/lang.css?asd423" rel="stylesheet" type="text/css" />										
		<link href="img/travian_basics.css?v=19" rel="stylesheet" type="text/css" />
		<script src="jquery-1.10.1.min.js" type="text/javascript"></script>
		<script src="sandwich.js" type="text/javascript"></script>
		<script src="unx.js" type="text/javascript"></script>
		<script src="crypt.js?<?php echo time(); ?>" type="text/javascript"></script>
		<?php if(isset($session) && $session->logged_in) { ?>
		<script src="build-notifications.js?v=1" type="text/javascript"></script>
		<script type="text/javascript">
			TravianBuildNotifications.configure({
				accountId: <?php echo (int)$session->uid; ?>,
				serverName: <?php echo json_encode(SERVER_NAME, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
				serverNow: <?php echo time(); ?>
			});
		</script>
		<?php } ?>
</head>
