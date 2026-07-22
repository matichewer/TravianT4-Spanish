<?php
if($_GET){
	
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
		}
	}
	if (isset($_GET['cmd'])) {
		switch($_GET['cmd']) {
			
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
