<?php 
$displayarray = $database->getUserArray($session->uid,1);
if ($displayarray['fquest'] != "1,1,1,1,1,1,1,1,1,1,1" && QUEST==true){
?>

<div class="questMaster">
<div id="anm" style="width:120px; height:140px; visibility:hidden;"></div>
			<div id="qge">
				<?php if ($_SESSION['qst'] == 0 or $_SESSION['qstnew'] == 1){ ?>
				<img onclick="qst_handle();" src="img/x.gif" id="qgei" style="width:160px;height:195px;cursor:pointer;" class="q_l<?php echo $session->userinfo['tribe'];?>g" title="Ir a la tarea">
				<?php }else{ ?>
				<img onclick="qst_handle();" src="img/x.gif" id="qgei" style="width:160px;height:195px;cursor:pointer;" class="q_l<?php echo $session->userinfo['tribe'];?>" title="Ir a la tarea">
				<?php } ?>
			</div>
			<script type="text/javascript">
				<?php if ($_SESSION['qst']==0){ ?>
				quest.number=null;
				<?php }else{ ?>
				quest.number=0;
				<?php } ?>
				quest.last = 25;
			</script>						
</div>
<?php } ?>
<script type="text/javascript">
	try {
		if (window.sessionStorage.getItem('questRewardReopen') === '1') {
			window.sessionStorage.removeItem('questRewardReopen');
			if (document.getElementById('qge')) {
				window.addEvent('domready', function() { qst_handle(); });
			}
		}
	} catch (e) {}
</script>
<script type="text/javascript"> 
	Travian.Translation.add(
	{
		'close' : 'Cerrar'
	});
</script>
<?php
$timestamp = $database->isDeleting($session->uid);
if($displayarray['protect'] > time()){
echo "<div id=\"sideInfoCountdown\"><div class=\"head\"></div>";
echo "<div class=\"content\">";

		$protectionRemaining = $displayarray['protect'] - time();
		$protectionHours = floor($protectionRemaining / 3600);
		$protectionMinutes = floor(($protectionRemaining % 3600) / 60);
        echo "Tienes <b><span id=\"protectionTime\" data-remaining=\"".$protectionRemaining."\">".$protectionHours." h ".$protectionMinutes." min</span></b> de protección.</div></div>";
?>
<script type="text/javascript">
(function() {
	var protectionTime = document.getElementById('protectionTime');
	var initialRemaining = parseInt(protectionTime.getAttribute('data-remaining'), 10);
	var startedAt = new Date().getTime();
	var protectionTimer = window.setInterval(function() {
		var elapsed = Math.floor((new Date().getTime() - startedAt) / 1000);
		var remaining = Math.max(0, initialRemaining - elapsed);
		var hours = Math.floor(remaining / 3600);
		var minutes = Math.floor((remaining % 3600) / 60);

		protectionTime.innerHTML = hours + ' h ' + minutes + ' min';
		if (remaining === 0) {
			window.clearInterval(protectionTimer);
			window.location.reload();
		}
	}, 1000);
})();
</script>
<?php
}
if($timestamp) {
echo "<div id=\"sideInfoCountdown\"><div class=\"head\"></div>";
echo "<div class=\"content\">";
		$time=$generator->getTimeFormat(($timestamp-time()));
       echo " La cuenta será eliminada en <span id=\"timer1\">".$time."</span> .</div></div>";
}
?>
