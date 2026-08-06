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
    // En build.php seguimos el edificio, no el hueco: si el hueco actual tiene un
    // edificio, cambiamos de aldea con &gid= para abrir el mismo tipo de edificio
    // alla (build.php manda a dorf2.php si esa aldea no lo tiene).
    $buildingGid = 0;
    if(basename($_SERVER['PHP_SELF']) == 'build.php' && isset($_GET['id']) && is_scalar($_GET['id']) && ctype_digit((string)$_GET['id'])
    	&& (int)$_GET['id'] >= 19 && (int)$_GET['id'] <= 40) {
    	$buildingGid = (int)$village->resarray['f'.(int)$_GET['id'].'t'];
    }
    if($buildingGid > 0){
    	$vill = "&gid=".$buildingGid;
    }else if(isset($_GET['id'])){
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
		// El nombre y la coordenada van en un solo renglon. data-name lo usa el CSS
		// para medir el nombre siempre en negrita, asi el ancho del cartel no
		// cambia segun cual sea la aldea activa (la activa se dibuja en negrita).
		echo "<li class=\"entry ".$village_attack."".$select."\" title=\"".$village_title."\">
	    <a id=\"".$sid."\" title=\"".$village_title." (".$coorproc['x']."|".$coorproc['y'].")\" href=\"?newdid=".$villageId."".$vill."\" class=\"".$select."\"><span class=\"villageNameText\" data-name=\"".htmlspecialchars($villageName, ENT_QUOTES, 'UTF-8')."\">".$villageName."</span><span class=\"villageCoords\">(".$coorproc['x']."|".$coorproc['y'].")</span></a></li>";
	}
    	?>
		
	</ul>
</div>
<div class="foot"> 
</div>
</div>
<?php include("Templates/links.tpl"); ?>
