<div id="villageList" class="listing">
<div class="head">
	<a href="dorf3.php" accesskey="9" title="Resumen de aldeas"><?php echo MULTI_V_HEADER; ?>:</a>
</div> 
<div class="list"> 
	<ul>        
<?php 
    for($i=1;$i<=count($session->villages);$i++) {
        $villageId = $session->villages[$i-1];
        $villageName = $database->getVillageField($villageId, 'name');
        $village_attack = "";
        $village_title = htmlspecialchars($villageName, ENT_QUOTES, 'UTF-8');
        if($session->plus){
            $attack_coming = $database->getMovement2(3,$villageId,1);
            $aantal = count($attack_coming);
            if($aantal > 0){
                $village_attack = "attack ";
                $village_title = "ataques a esta aldea: ".$aantal;
            }
        }
    if($session->villages[$i-1] == $village->wid){ $select = "active"; $sid = "currentVillage"; }else{ $select = ""; $sid = ""; }
    $coorproc = $database->getCoor($session->villages[$i-1]);
    if(isset($_GET['id'])){
    	$vill = "&id=".$_GET['id'];
    }else if(isset($_GET['gid'])){
    	$vill = "&gid=".$_GET['gid'];
    }else if(isset($_GET['w'])) {
    	$vill = "&w=".$_GET['w'];
	}else if(isset($_GET['r'])) {
    	$vill = "&r=".$_GET['r'];
	}else if(isset($_GET['o'])) {
    	$vill = "&o=".$_GET['o'];
	}else if(isset($_GET['z'])) {
    	$vill = "&z=".$_GET['z'];
	}else if(isset($_GET['s'])) {
    	$vill = "&s=".$_GET['s'];
	}else if(isset($_GET['c'])) {
    	$vill = "&c=".$_GET['c'];
	}else if(isset($_GET['t'])) {
    	$vill = "&t=".$_GET['t'];
	}else if(isset($_GET['d'])) {
    	$vill = "&d=".$_GET['d'];
	}else if(isset($_GET['aid'])) {
    	$vill = "&aid=".$_GET['aid'];
	}else if(isset($_GET['uid'])) {
    	$vill = "&uid=".$_GET['uid'];
	}else if(isset($_GET['tid'])) {
    	$vill = "&tid=".$_GET['tid'];
	}else if(isset($_GET['vill']) && isset($_GET['id'])) {
    	$vill = "&id=".$_GET['id']."&vill=".$_GET['vill'];
	}else if(isset($_GET['t']) && isset($_GET['id'])) {
    $vill = "&id=".$_GET['id']."&t=".$_GET['t'];
	}else if(isset($_GET['x']) && isset($_GET['y'])) {
    	$vill = "&x=".$_GET['x']."&y=".$_GET['y'];
    }else{
    	$vill = "";
    }
		// data-name lo usa el CSS para medir el nombre siempre en negrita, asi el
		// ancho del cartel no cambia segun cual sea la aldea activa
		echo "<li class=\"entry ".$village_attack."".$select."\" title=\"".$village_title."\">
	    <a id=\"".$sid."\" title=\"".$village_title." (".$coorproc['x']."|".$coorproc['y'].")\" data-name=\"".htmlspecialchars($villageName, ENT_QUOTES, 'UTF-8')."\" href=\"?newdid=".$villageId."".$vill."\" class=\"".$select."\">".$villageName."<span class=\"villageCoords\" style=\"display:block;font-size:10px;line-height:10px;opacity:0.7;\">(".$coorproc['x']."|".$coorproc['y'].")</span></a></li>";
	}
    	?>
		
	</ul>
</div>
<div class="foot"> 
</div>
</div>
<?php include("Templates/links.tpl"); ?>
