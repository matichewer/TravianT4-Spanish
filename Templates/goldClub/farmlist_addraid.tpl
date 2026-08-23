<?php

$errormsg = '';
$targetX = isset($_POST['x']) ? $_POST['x'] : (isset($_GET['x']) ? $_GET['x'] : '');
$targetY = isset($_POST['y']) ? $_POST['y'] : (isset($_GET['y']) ? $_GET['y'] : '');
$requestedLid = isset($_GET['lid']) && is_numeric($_GET['lid']) ? (int)$_GET['lid'] : 0;
if($requestedLid > 0) {
    $requestedListData = $database->getFLData($requestedLid);
    if(!is_array($requestedListData) || (int)$requestedListData['owner'] !== (int)$session->uid) {
        // No es una lista propia: no mostramos sus objetivos guardados.
        $requestedLid = 0;
    }
}

if(isset($_POST['action'],$_POST['lid']) && $_POST['action'] === 'addSlot' && $_POST['lid']) {

    $troops = 0;
    for($troopIndex = 1; $troopIndex <= 10; $troopIndex++) {
        $troops += isset($_POST['t'.$troopIndex]) ? (int)$_POST['t'.$troopIndex] : 0;
    }
    
    $validX = is_numeric($targetX) && floor($targetX) == $targetX;
    $validY = is_numeric($targetY) && floor($targetY) == $targetY;

    if($validX && $validY){
        $Wref = $database->getVilWref($targetX, $targetY);
        $oasistype = $database->getVillageType2($Wref);
        $vdata = $database->getVillage($Wref);
    }

    if(!$validX && !$validY){
    	$errormsg .= "Ingresa las coordenadas.";
    }elseif(!$validX || !$validY){
    	$errormsg .= "Ingresa las coordenadas correctas.";
    }elseif($oasistype == 0 && $vdata == 0){
    	$errormsg .= "No hay ninguna aldea en esas coordenadas.";
    }elseif($troops == 0){
     	$errormsg .= "No se ha seleccionado ninguna tropa.";
	}elseif($database->farmListTargetExists((int)$_POST['lid'], $session->uid, $Wref)){
		$errormsg .= "Este objetivo ya está agregado a la lista seleccionada.";
    }else{
    
        $coor = $database->getCoor($village->wid);
            
            function getDistance($coorx1, $coory1, $coorx2, $coory2) {
                   $max = 2 * WORLD_MAX + 1;
                   $x1 = intval($coorx1);
                   $y1 = intval($coory1);
                   $x2 = intval($coorx2);
                   $y2 = intval($coory2);
                   $distanceX = min(abs($x2 - $x1), abs($max - abs($x2 - $x1)));
                   $distanceY = min(abs($y2 - $y1), abs($max - abs($y2 - $y1)));
                   $dist = sqrt(pow($distanceX, 2) + pow($distanceY, 2));
                   return round($dist, 1);
               }
            
        $distance = getDistance($coor['x'], $coor['y'], $targetX, $targetY);

        // addSlotFarm valida internamente que `lid` sea una lista del usuario logueado.
        $slotAdded = $database->addSlotFarm($_POST['lid'], $session->uid, $Wref, $targetX, $targetY, $distance, $_POST['t1'], $_POST['t2'], $_POST['t3'], $_POST['t4'], $_POST['t5'], $_POST['t6'], $_POST['t7'], $_POST['t8'], $_POST['t9'], $_POST['t10']);

		if($slotAdded) {
			header("Location: build.php?gid=16&t=99");
			exit;
		}
		$errormsg .= "No se pudo agregar el objetivo a la lista seleccionada.";
}
}
?>

<script type="text/javascript">
    var targets = {};

    function fillTargets()
    {
        var targetId = $('target_id');

        targetId.empty();

        var option = new Element('option',
        {
            'html': 'Selecciona una aldea'
        });
        targetId.insert(option);

        $each(targets[lid], function(data)
        {
            var option = new Element('option',
            {
                'value': data.did,
                'html': data.name
            });
            targetId.insert(option);
        });
    }

    function getTargetsByLid()
    {
        var lidSelect = $('lid');
        lid = lidSelect.getSelected()[0].value;

        if (targets[lid])
        {
            fillTargets();
        }
        else
        {
            Travian.ajax(
            {
                data:
                {
                    cmd: 'raidListTargets',
                    'lid': lid
                },
                onSuccess: function(data)
                {
                    targets[data.lid] = data.targets;
                    fillTargets();
                }
            });

        }
    }

    function selectCoordinates()
    {
        var targetId = $('target_id');
        var did = targetId.getSelected()[0].value;

        if (did == '')
        {
            $('xCoordInput').value = '';
            $('yCoordInput').value = '';
        }
        else
        {
            var array;
            $each(targets[lid], function(data)
            {
                if (data.did == did)
                {
                    array = data;
                    return;
                }
            });


            $('xCoordInput').value = array.x;
            $('yCoordInput').value = array.y;
        }
    }

    var lid = <?php echo $requestedLid; ?>;targets[lid] = {};

</script>

<div id="raidListSlot">
    <h4>Agregar campo</h4>
<font color="#FF0000"><b>    
<?php echo $errormsg; ?>
</b></font>
    
    <form action="build.php?id=39&t=99&action=showSlot&lid=<?php echo $requestedLid; ?>" method="post">
        <div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">

        <input type="hidden" name="action" value="addSlot">
        <input type="hidden" name="lid" value="<?php echo $requestedLid; ?>">
        
            
            <table cellpadding="1" cellspacing="1" class="transparent">
                <tbody><tr>
                    <th>Nombre de la lista:</th>
                    <td>
                        <select onchange="getTargetsByLid();" id="lid" name="lid">
<?php

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."farmlist WHERE owner = $session->uid ORDER BY name ASC");
while($row = mysql_fetch_array($sql)){ 
$lid = $row["id"];
$lname = $row["name"];
$lowner = $row["owner"];
$lwref = $row["wref"];
$lvname = $database->getVillageField($row["wref"], 'name');
    if($requestedLid === (int)$lid){
        $selected = 'selected=""';
        }else{ $selected = ''; }
    echo '<option value="'.$lid.'" '.$selected.'>'.$lvname.' - '.$lname.'</option>';
}
?>    
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Seleccionar objetivo:</th>
                    <td class="target">
                        
            <div class="coordinatesInput">
                <div class="xCoord">
                    <label for="xCoordInput">X:</label>
                    <input value="<?php echo htmlspecialchars((string)$targetX, ENT_QUOTES, 'UTF-8'); ?>" name="x" id="xCoordInput" class="text coordinates x ">
                </div>
                <div class="yCoord">
                    <label for="yCoordInput">Y:</label>
                    <input value="<?php echo htmlspecialchars((string)$targetY, ENT_QUOTES, 'UTF-8'); ?>" name="y" id="yCoordInput" class="text coordinates y ">
                </div>
                <div class="clear"></div>
            </div>
                                <div class="targetSelect">
                            <label class="lastTargets" for="last_targets">Últimos objetivos:</label>
							<select name="target_id">
<?php
$getwref = "SELECT * FROM ".TB_PREFIX."raidlist WHERE lid = ".$requestedLid;
$arraywref = $database->query_return($getwref);
	echo '<option value="">Selecciona una aldea</option>';
if(mysql_num_rows(mysql_query($getwref)) != 0){
foreach($arraywref as $row){
$towref = $row["towref"];
$tocoor = $database->getCoor($towref);
$tooasistype = $database->getVillageType2($towref);
if($tooasistype == 0){
$tovname = $database->getVillageField($towref, 'name');
}else{
$tovname = $database->getOasisField($towref, 'name');
}
if($vill[$towref] == 0){
	echo '<option value="'.$towref.'">'.$tovname.'('.$tocoor['x'].'|'.$tocoor['y'].')</option>';
}
$vill[$towref] = 1;
}
}
?>
							</select>
                        </div>
                        <div class="clear"></div>
                    </td>
                </tr>
            </tbody></table>
            </div>
                </div>
        <?php include "Templates/goldClub/trooplist2.tpl"; ?>

        
<button type="submit" value="save" name="save" id="save"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">guardar</div></div></button>
        
</form>
</div>
