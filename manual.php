<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       manual.php                                                  ##
##  Developed by:  Dzoki                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

include("config/connection.php");
include("config/config.php");

$manualType = isset($_GET['typ']) && ctype_digit((string) $_GET['typ']) ? (int) $_GET['typ'] : null;
$manualSection = isset($_GET['s']) && ctype_digit((string) $_GET['s']) ? (int) $_GET['s'] : 0;
$manualGid = isset($_GET['gid']) && ctype_digit((string) $_GET['gid']) ? (int) $_GET['gid'] : null;
$manualUnitId = $manualType === 1 ? ($manualGid !== null ? $manualGid : $manualSection) : null;
$isUnitManual = $manualUnitId !== null && $manualUnitId > 0;
$unitPortrait = null;
if ($isUnitManual) {
	$portraitCandidates = array(
		"gpack/travian_Travian_4.0_41/img/u/big/u" . $manualUnitId . "-rtl.png",
		"gpack/travian_Travian_4.0_41/img/u/big/u" . $manualUnitId . "-rtl.gif",
		"gpack/travian_default/img/u2rtl/u" . $manualUnitId . ".gif"
	);
	foreach ($portraitCandidates as $portraitCandidate) {
		if (is_file($portraitCandidate)) {
			$unitPortrait = $portraitCandidate;
			break;
		}
	}
}
?>

<html>
	<head>
	<title><?php echo SERVER_NAME; ?></title>
        <link REL="shortcut icon" HREF="favicon.ico"/>
	<meta name="content-language" content="en" />
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="imagetoolbar" content="no" />
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<script src="mt-core.js?0faaa" type="text/javascript"></script>
	<script src="mt-more.js?0faaa" type="text/javascript"></script>
	<script src="unx.js?0faaa" type="text/javascript"></script>
	<script src="new.js?0faaa" type="text/javascript"></script>

<!--    TODO - We need a lang pack for en, only one there is ir -->
<!--   	<link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?f4b7c" rel="stylesheet" type="text/css" />-->
<!--   	<link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?f4b7c" rel="stylesheet" type="text/css" />-->
<!--	<link href="<?php echo GP_LOCATE; ?>travian.css?f4b7c" rel="stylesheet" type="text/css" />-->
<!--    	<link href="<?php echo GP_LOCATE; ?>lang/en/lang.css" rel="stylesheet" type="text/css" />	-->

	<link href="gpack/travian_Travian_4.0_41/lang/ir/compact.css?asd423" rel="stylesheet" type="text/css" />
	<link href="img/manual.css?v=1" rel="stylesheet" type="text/css" />

	<style type="text/css">
		img.building {
			display: block;
		}
		body {
			background-color: white;
			background-image: none;
			min-width: 0;
			padding: 0;
		}
		<?php if ($unitPortrait !== null) { ?>
		body.unit-manual #big_unit {
			background-image: url("<?php echo htmlspecialchars($unitPortrait, ENT_QUOTES, 'UTF-8'); ?>");
		}
		<?php } ?>
	</style>

    </head>
    <body class="manual<?php echo $isUnitManual ? ' unit-manual' : ''; ?><?php echo $isUnitManual && $unitPortrait === null ? ' no-unit-portrait' : ''; ?>">
	<main class="manual-content">
<?php

if ($manualType === null && !isset($_GET['s'])) {
	include("Templates/Manual/00.tpl");
}
else if ($manualType === null && $manualSection === 1) {
	include("Templates/Manual/00.tpl");
}
else if ($manualType === null && $manualSection === 2) {
	include("Templates/Manual/direct.tpl");
}
else if ($manualType === 5 && $manualSection === 3) {
	include("Templates/Manual/medal.tpl");
}
else {
	if ($manualGid !== null) {
		$template = "Templates/Manual/" . $manualType . $manualGid . ".tpl";
	}
	else {
		if ($manualType === 4 && $manualSection === 0) {
			$manualSection = 1;
		}
		$template = "Templates/Manual/" . $manualType . $manualSection . ".tpl";
	}

	if (is_file($template)) {
		include($template);
	}
}
?>
	</main>
	<?php if ($isUnitManual) { ?>
	<script type="text/javascript">
	(function () {
		var table = document.getElementById('troop_info');
		if (!table) {
			return;
		}

		var labels = {
			att_all: 'Ataque',
			def_i: 'Def. infantería',
			def_c: 'Def. caballería',
			r1: 'Madera',
			r2: 'Barro',
			r3: 'Hierro',
			r4: 'Cereal'
		};
		var headers = table.getElementsByTagName('th');
		for (var i = 0; i < headers.length; i++) {
			var icon = headers[i].getElementsByTagName('img')[0];
			if (!icon || !labels[icon.className]) {
				continue;
			}
			var label = document.createElement('span');
			label.className = 'stat-label';
			label.appendChild(document.createTextNode(labels[icon.className]));
			headers[i].appendChild(label);
		}

		var heading = document.createElement('h2');
		heading.className = 'unit-stats-title';
		heading.appendChild(document.createTextNode('Atributos y costo de entrenamiento'));
		table.parentNode.insertBefore(heading, table);
	}());
	</script>
	<?php } ?>
</body>

</html>
