<?php
session_start();
include("../GameEngine/Database.php");
include("../GameEngine/Admin/database.php");
if($funct->CheckLogin()){
	if(isset($_GET['cmd'])){
		if($_GET['cmd']=='delLog'){
			$q = "DELETE FROM " . TB_PREFIX . "admin_log where `id` = '" . $_POST['did'] . "'";
			mysql_query($q);
		}
		
		if($_GET['cmd']=='NewsManage'){
			$box = "newsbox".$_POST['id'];
			$q = "UPDATE " . TB_PREFIX . "config set $box = '".$_POST['status']."'";
			mysql_query($q);
		}

		if($_GET['cmd']=='PaypalGold'){
			$status = ($_POST['status'] === '1') ? '1' : '0';
			$q = "UPDATE " . TB_PREFIX . "config SET paypal_gold = '" . $status . "'";
			mysql_query($q);
		}

		if($_GET['cmd']=='MedalTop'){
			$col = ($_POST['scope'] === 'ally') ? 'medal_ally_top' : 'medal_top';
			$value = max(1, min(10, (int)$_POST['status']));
			$q = "UPDATE " . TB_PREFIX . "config SET $col = '" . $value . "'";
			mysql_query($q);
		}
	}
}else{
	echo '<h2>Error: You are not Admin!</h2>';
}
?>