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
		
		$unnotice = $database->getUnreadNoticeCount($session->uid);
    	if($unnotice > 1000) { $unnotice = "+1000"; }
	?>
	<li id="n5" class="reports"> 
		<a class="<?php echo $activeNavigation == 'reports' ? 'active' : ''; ?>" href="berichte.php" accesskey="5" title="<?php echo HEADER_NOTICES; ?><?php if($message->nunread){ echo' ('.$unnotice.')'; } ?>"></a>
		<?php
		if($message->nunread){
			echo "<div class=\"ltr bubble\" title=\"".$unnotice." ".HEADER_NOTICES_NEW."\" style=\"display:block\">
					<div class=\"bubble-background-l\"></div>
					<div class=\"bubble-background-r\"></div>
					<div class=\"bubble-content\">".$unnotice."</div></div>";
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
