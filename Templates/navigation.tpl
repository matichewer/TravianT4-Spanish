<?php
$navigationPage = basename($_SERVER['SCRIPT_NAME']);
$activeNavigation = array(
	'dorf1.php' => 'resources',
	'dorf2.php' => 'village',
	'karte.php' => 'map',
	'position_details.php' => 'map',
	'statistiken.php' => 'stats',
	'berichte.php' => 'reports',
	'nachrichten.php' => 'messages'
);
$activeNavigation = isset($activeNavigation[$navigationPage]) ? $activeNavigation[$navigationPage] : '';

if($navigationPage == 'build.php' && isset($_GET['id'])) {
	$activeNavigation = (int) $_GET['id'] <= 18 ? 'resources' : 'village';
}
?>
<ul id="navigation">
	<li id="n1" class="resources">
		<a class="<?php echo $activeNavigation == 'resources' ? 'active' : ''; ?>" href="dorf1.php" accesskey="1" title="<?php echo HEADER_DORF1; ?>"></a>
	</li>
	<li id="n2" class="village">
		<a class="<?php echo $activeNavigation == 'village' ? 'active' : ''; ?>" href="dorf2.php" accesskey="2" title="<?php echo HEADER_DORF2; ?>"></a>
	</li>
	<li id="n3" class="map">
		<a class="<?php echo $activeNavigation == 'map' ? 'active' : ''; ?>" href="karte.php" accesskey="3" title="<?php echo HEADER_MAP; ?>"></a>
	</li>
	<li id="n4" class="stats">
		<a class="<?php echo $activeNavigation == 'stats' ? 'active' : ''; ?>" href="statistiken.php" accesskey="4" title="<?php echo HEADER_STATS; ?>"></a>
	</li>
	<?php
		$unmsg = $database->getUnreadMessageCount($session->uid);
    	if($unmsg > 1000) { $unmsg = "+1000"; }
		
		$unreadNoticeCategories = $database->getUnreadNoticeCountsByCategory($session->uid);
		$unnotice = array_sum($unreadNoticeCategories);
		$hasUnreadNotices = $unnotice > 0;
		$noticeCategoryLabels = array(
			'attack' => 'Ataque',
			'defense' => 'Defensa',
			'spy' => 'Espionaje',
			'trade' => 'Comercio',
			'routes' => 'Rutas',
			'reinforcement' => 'Refuerzo',
			'misc' => 'Varios'
		);
		$unnoticeDisplay = $unnotice > 1000 ? '+1000' : $unnotice;
	?>
	<li id="n5" class="reports"> 
		<a class="<?php echo $activeNavigation == 'reports' ? 'active' : ''; ?>" href="berichte.php" accesskey="5" title="<?php echo HEADER_NOTICES; ?><?php if($hasUnreadNotices){ echo' ('.$unnoticeDisplay.')'; } ?>"></a>
		<?php
		if($hasUnreadNotices){
			echo '<div class="report-badges">';
			foreach($unreadNoticeCategories as $category => $count) {
				if($count <= 0) {
					continue;
				}
				$countDisplay = $count > 9 ? '9+' : $count;
				$title = $count.' '.HEADER_NOTICES_NEW.' · '.$noticeCategoryLabels[$category];
				echo '<span class="report-badge report-badge-'.$category.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'
					.'<span class="report-badge-background report-badge-background-l"></span>'
					.'<span class="report-badge-background report-badge-background-r"></span>'
					.'<span class="report-badge-content">'.$countDisplay.'</span></span>';
			}
			echo '</div>';
		}
		?>
	</li>
	<li id="n6" class="messages"> 
		<a class="<?php echo $activeNavigation == 'messages' ? 'active' : ''; ?>" href="nachrichten.php" accesskey="6" title="<?php echo HEADER_MESSAGES; ?><?php if($message->unread){ echo' ('.$unmsg.')'; } ?>"></a>
		<?php
		if($message->unread) {
			echo "<div class=\"ltr bubble\" title=\"".$unmsg." ".HEADER_MESSAGES_NEW."\" style=\"display:block\">
					<div class=\"bubble-background-l\"></div>
					<div class=\"bubble-background-r\"></div>
					<div class=\"bubble-content\">".$unmsg."</div></div>";
		}
		?>
	</li>

</ul>
