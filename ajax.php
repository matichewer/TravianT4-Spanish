<?php
if($_GET){
	$isBbPreview = (isset($_GET['f']) && $_GET['f'] === 'bb')
		|| (isset($_GET['cmd']) && $_GET['cmd'] === 'bb');
	$bbPreviewBufferLevel = ob_get_level();
	if ($isBbPreview) {
		include_once("GameEngine/Village.php");
	}

	$renderBbPreview = function() use ($bbPreviewBufferLevel) {
		global $database, $generator;

		$input = isset($_POST['text']) ? $_POST['text'] : '';
		$input = preg_replace_callback('/\[report([0-9]+)\](.*?)\[\/report\\1\]/is', function($matches) {
			$content = trim($matches[2]);
			if (preg_match('/(?:^|[?&])id=([0-9]+)/i', $content, $idMatch)) {
				$content = $idMatch[1];
			}
			return '[report'.$matches[1].']'.$content.'[/report'.$matches[1].']';
		}, $input);
		$reportIndex = 0;
		$input = preg_replace_callback('/\[report[0-9]+\](.*?)\[\/report[0-9]+\]/is', function($matches) use (&$reportIndex) {
			$tag = '[report'.$reportIndex.']'.$matches[1].'[/report'.$reportIndex.']';
			$reportIndex++;
			return $tag;
		}, $input);
		$input = '[message]'.$input.'[/message]';

		$alliance = -1;
		$player = -1;
		$report = -1;
		$coor = -1;
		$rep1 = 89;
		$rep2 = 89;
		$rep3 = 89;
		$xx = 0;
		$yy = 0;
		$cx = 0;
		$cy = 0;

		if (preg_match_all('/\[alliance([0-9]+)\]/i', $input, $matches) && !empty($matches[1])) {
			$alliance = (int) max($matches[1]);
		}
		if (preg_match_all('/\[player([0-9]+)\]/i', $input, $matches) && !empty($matches[1])) {
			$player = (int) max($matches[1]);
		}
		if (preg_match_all('/\[report([0-9]+)\]/i', $input, $matches) && !empty($matches[1])) {
			$report = (int) max($matches[1]);
		}
		if (preg_match_all('/\[coor([0-9]+)\]/i', $input, $matches) && !empty($matches[1])) {
			$coor = (int) max($matches[1]);
		}

		ob_start();
		include("GameEngine/BBCode.php");
		ob_end_clean();
		$text = isset($bbcoded) ? $bbcoded : $input;

		if (!empty($_POST['nl2br'])) {
			$text = nl2br($text);
		}
		while (ob_get_level() > $bbPreviewBufferLevel) {
			ob_end_clean();
		}

		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode(array(
			'error' => false,
			'text' => $text
		));
		exit;
	};
	
	if (isset($_GET['f'])) {
		switch($_GET['f']) {
			case 'qst':
				if (isset($_GET['qact'])){
					$qact=$_GET['qact'];
				}else {
					$qact=null;
				}
				if (isset($_GET['qact2'])){
					$qact2=$_GET['qact2'];
				}else {
					$qact2=null;
				}
				if (isset($_GET['qact3'])){
					$qact3=$_GET['qact3'];
				}else {
					$qact3=null;
				}
				ob_start();
				include("Templates/Ajax/quest_core.tpl");
				$questResponse = ob_get_clean();
				if (!empty($questRewardClaimed)) {
					$questResponse = preg_replace('/}\s*$/', ',"rewardClaimed":true}', $questResponse, 1);
				}
				echo $questResponse;
			break;
			case 'bb':
				$renderBbPreview();
			break;
		}
	}
	if (isset($_GET['cmd'])) {
		switch($_GET['cmd']) {
			case 'bb':
				$renderBbPreview();
			break;
			
			case 'changeVillageName':
				include_once ("GameEngine/Database.php");
		
				$q = "UPDATE " . TB_PREFIX . "vdata SET `name` = '" . $_POST['name'] . "' where `wref` = '" . $_POST['did'] . "'";
				mysql_query($q);
			break;
			
			case 'mapLowRes':
				$x = $_POST['x'];
				$y = $_POST['y'];
				$xx = $_POST['width'];
				$yy = $_POST['height'];
				
				include("Templates/Ajax/mapscroll.tpl");
			break;

			case 'viewTileDetails':
				include_once("GameEngine/Village.php");

				if(!isset($_POST['x'], $_POST['y']) || !is_numeric($_POST['x']) || !is_numeric($_POST['y'])) {
					header("Content-Type: application/json; charset=UTF-8");
					echo json_encode(array('error' => true, 'errorMsg' => 'Coordenadas inválidas.'));
					exit;
				}

				$x = (int) $_POST['x'];
				$y = (int) $_POST['y'];
				if(abs($x) > WORLD_MAX || abs($y) > WORLD_MAX) {
					header("Content-Type: application/json; charset=UTF-8");
					echo json_encode(array('error' => true, 'errorMsg' => 'Coordenadas fuera del mapa.'));
					exit;
				}

				$_GET['x'] = $x;
				$_GET['y'] = $y;

				ob_start();
				include("Templates/Map/vilview.tpl");
				$html = ob_get_clean();

				header("Content-Type: application/json; charset=UTF-8");
				echo json_encode(array(
					'error' => false,
					'data' => array(
						'html' => $html,
						'title' => 'Detalles de la casilla ('.$x.'|'.$y.')'
					)
				));
				exit;
			break;
		}
	}

}
?>
