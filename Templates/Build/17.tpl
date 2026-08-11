<h1 class="titleInHeader">Mercado <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid17">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(17,4);" class="build_logo"> 
	<img class="building big white g17" src="img/x.gif" alt="Mercado" title="Mercado" /> 
</a> 
En el mercado puedes comerciar recursos con otros jugadores. Cuanto mayor sea su nivel, más recursos se pueden transportar al mismo tiempo.</div> 
 
<?php
$buildingHelpType = 'marketplace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("17_menu.tpl");
?>

<script language="JavaScript"> 
var haendler = <?php echo $market->merchantAvail(); ?>;
var carry = <?php echo $market->maxcarry; ?>;
</script>
<div class="boxes boxesColor gray traderCount"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Mercaderes <?php echo $market->merchantAvail(); ?> / <?php echo $market->merchant; ?></div>
				</div><div class="clear"></div>
<?php

$marketFormType = isset($_POST['ft']) && is_string($_POST['ft']) ? $_POST['ft'] : '';
foreach(array('r1','r2','r3','r4') as $marketResourceField) {
	if(!isset($_POST[$marketResourceField]) || !is_scalar($_POST[$marketResourceField]) || !ctype_digit((string)$_POST[$marketResourceField])) {
		$_POST[$marketResourceField] = 0;
	}
}
$marketX = isset($_POST['x']) && is_scalar($_POST['x']) ? (string)$_POST['x'] : '';
$marketY = isset($_POST['y']) && is_scalar($_POST['y']) ? (string)$_POST['y'] : '';
$marketVillageName = isset($_POST['dname']) && is_scalar($_POST['dname']) ? (string)$_POST['dname'] : '';
$marketSendCount = isset($_POST['send3']) && is_scalar($_POST['send3']) && ctype_digit((string)$_POST['send3']) ? (int)$_POST['send3'] : 1;
if(!$session->goldclub || $marketSendCount < 1 || $marketSendCount > 3) {
	$marketSendCount = 1;
}
$marketCoordinatesValid = preg_match('/^-?\d+$/',$marketX) && preg_match('/^-?\d+$/',$marketY)
	&& abs((int)$marketX) <= WORLD_MAX && abs((int)$marketY) <= WORLD_MAX;
$_POST['x'] = $marketX;
$_POST['y'] = $marketY;
$_POST['dname'] = $marketVillageName;
$getwref = 0;
$checkexist = false;

$allres = $_POST['r1']+$_POST['r2']+$_POST['r3']+$_POST['r4'];
if($marketCoordinatesValid){
	$getwref = $database->getVilWref((int)$marketX,(int)$marketY);
	$checkexist = $database->checkVilExist($getwref);
}
else if($marketVillageName !== ''){
	$getwref = $database->getVillageByName($marketVillageName);
	$checkexist = $database->checkVilExist($getwref);
}
// Need to set the max allowed to send as maxcarry * available merchants
$canSend = $market->maxcarry * $market->merchantAvail();

if($marketFormType === 'check' && $allres > 0 && $allres <= $canSend && ($marketCoordinatesValid || $marketVillageName !== '') && !empty($checkexist)){
?>
<form method="POST" name="snd" action="build.php"> 
	<input type="hidden" name="ft" value="mk1">
	<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
	<input type="hidden" name="a" value="<?php echo $session->mchecker; ?>">
<input type="hidden" name="send3" value="<?php echo $marketSendCount; ?>">
<input type="hidden" name="vid" value="<?php echo $getwref; ?>">
<table id="send_select" class="send_res" cellpadding="1" cellspacing="1">
	<tr>
		<td class="ico"><img class="r1" src="img/x.gif" alt="Madera" title="Madera" /></td> 
		<td class="nam"> Madera</td> 
		<td class="val"><input class="text disabled" type="text" name="r1" id="r1" value="<?php echo $_POST['r1']; ?>" readonly="readonly"></td> 
		<td class="max"> / <span class="none"><B><?php echo $market->maxcarry; ?></B></span> </td> 
	</tr>
    <tr> 
		<td class="ico"><img class="r2" src="img/x.gif" alt="Hierro" title="Hierro" /></td> 
		<td class="nam"> Barro</td> 
		<td class="val"><input class="text disabled" type="text" name="r2" id="r2" value="<?php echo $_POST['r2']; ?>" readonly="readonly"></td> 
		<td class="max"> / <span class="none"><b><?php echo$market->maxcarry; ?></b></span> </td> 
	</tr>
    <tr> 
		<td class="ico"><img class="r3" src="img/x.gif" alt="Hierro" title="Hierro" /></td> 
		<td class="nam"> Hierro</td> 
		<td class="val"><input class="text disabled" type="text" name="r3" id="r3" value="<?php echo $_POST['r3']; ?>" readonly="readonly"> 
		</td> 
		<td class="max"> / <span class="none"><b><?php echo $market->maxcarry; ?></b></span> </td> 
	</tr>
    <tr> 
		<td class="ico"><img class="r4" src="img/x.gif" alt="Cereal" title="Cereal" /></td> 
		<td class="nam"> Cereal</td> 
		<td class="val"> <input class="text disabled" type="text" name="r4" id="r4" value="<?php echo $_POST['r4']; ?>" readonly="readonly"> 
		</td> 
		<td class="max"> / <span class="none"><B><?php echo $market->maxcarry; ?></B></span></td> 
	</tr></table> 
<table id="target_validate" class="res_target" cellpadding="1" cellspacing="1">
	<tbody><tr>
		<th>Coordenadas:</th>
        <?php
		$getvilcoor = $database->getCoor($getwref);
		$getvilname = $database->getVillageField($getwref, "name");
		$getvilowner = $database->getVillageField($getwref, "owner");
		$time = $generator->procDistanceTime($getvilcoor,$village->coor,$session->tribe,0);
		$getvilnameEscaped = htmlspecialchars((string)$getvilname,ENT_QUOTES,'UTF-8');
		$getvilownerName = htmlspecialchars((string)$database->getUserField($getvilowner,'username',0),ENT_QUOTES,'UTF-8');
        ?>
		<td class="vil"><a href="position_details.php?x=<?php echo (int)$getvilcoor['x']; ?>&amp;y=<?php echo (int)$getvilcoor['y']; ?>"><span class="coordinates coordinatesWithText"><span class="coordText"><?php echo $getvilnameEscaped; ?></span><span class="coordinatesWrapper"><span class="coordinateX">(<?php echo (int)$getvilcoor['x']; ?></span><span class="coordinatePipe">|</span><span class="coordinateY"><?php echo (int)$getvilcoor['y']; ?>)</span></span></span><span class="clear"></span></a></td>
	</tr>
	<tr>
		<th>Jugador:</th>
		<td><a href="spieler.php?uid=<?php echo (int)$getvilowner; ?>"><?php echo $getvilownerName; ?></a></td>
	</tr>
	<tr>
		<th>Duración:</th>
		<td><?php echo $generator->getTimeFormat($time); ?></td>
	</tr>
	<tr>
		<th>Mercaderes:</th>
		<td><?php
        $resource = array($_POST['r1'],$_POST['r2'],$_POST['r3'],$_POST['r4']); 
        echo ceil((array_sum($resource)-0.1)/$market->maxcarry); ?></td>
	</tr>
	<tr>
		<th>Envíos:</th>
		<td><?php echo $marketSendCount; ?>x</td>
	</tr>

	<tr>
		<td colspan="2">
					</td>
	</tr>

</tbody></table>
<div class="clear"></div>
<p>
<button type="submit" value="ok" name="s1" id="btn_ok" class="dynamic_img" tabindex="8"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Enviar mercaderes</div></div></button>
</p></form>
<?php }else{ ?>
<form method="POST" name="snd" action="build.php"> 
<input type="hidden" name="ft" value="check">
<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
<table id="send_select" class="send_res" cellpadding="1" cellspacing="1">

<tr>

		<td class="ico"> 
			<a href="#" onClick="upd_res(1,1); return false;"><img class="r1" src="img/x.gif" alt="Madera" title="Madera" /></a> 
		</td> 
		<td class="nam"> 
			Madera
		</td> 
		<td class="val"> 
			<input class="text" type="text" name="r1" id="r1" value="<?php echo $_POST['r1']; ?>" maxlength="5" onKeyUp="upd_res(1)" tabindex="1"> 
		</td> 
		<td class="max"> 
			/ <a href="#" id="marketQuickAdd1" onMouseUp="add_res(1);" onClick="return false;"><?php echo $market->maxcarry; ?></a>
		</td> 
	</tr><tr> 
		<td class="ico"> 
			<a href="#" onClick="upd_res(2,1); return false;"><img class="r2" src="img/x.gif" alt="Barro" title="Barro" /></a> 

		</td> 
		<td class="nam"> 
			Barro
		</td> 
		<td class="val"> 
			<input class="text" type="text" name="r2" id="r2" value="<?php echo $_POST['r2']; ?>" maxlength="5" onKeyUp="upd_res(2)" tabindex="2"> 
		</td> 
		<td class="max"> 
			/ <a href="#" id="marketQuickAdd2" onMouseUp="add_res(2);" onClick="return false;"><?php echo$market->maxcarry; ?></a>
		</td> 
	</tr><tr> 
		<td class="ico"> 
			<a href="#" onClick="upd_res(3,1); return false;"><img class="r3" src="img/x.gif" alt="Hierro" title="Hierro" /></a> 

		</td> 
		<td class="nam"> 
			Hierro
		</td> 
		<td class="val"> 
			<input class="text" type="text" name="r3" id="r3" value="<?php echo $_POST['r3']; ?>" maxlength="5" onKeyUp="upd_res(3)" tabindex="3"> 
		</td> 
		<td class="max"> 
			/ <a href="#" id="marketQuickAdd3" onMouseUp="add_res(3);" onClick="return false;"><?php echo $market->maxcarry; ?></a>
		</td> 
	</tr><tr> 
		<td class="ico"> 
			<a href="#" onClick="upd_res(4,1); return false;"><img class="r4" src="img/x.gif" alt="Cereal" title="Cereal" /></a> 

		</td> 
		<td class="nam"> 
			Cereal
		</td> 
		<td class="val"> 
			<input class="text" type="text" name="r4" id="r4" value="<?php echo $_POST['r4']; ?>" maxlength="5" onKeyUp="upd_res(4)" tabindex="4"> 
		</td> 
		<td class="max"> 
			/ <a href="#" id="marketQuickAdd4" onMouseUp="add_res(4);" onClick="return false;"><?php echo $market->maxcarry; ?></a>

		</td> 
	</tr></table> 

<div class="destination"><div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
<?php include("Templates/quick_target_select.tpl"); ?>
<table cellpadding="0" cellspacing="0" class="transparent compact">
				<tbody>
					<tr>
						<td>
							<span>Nombre de la aldea:</span>
						</td>
						<td class="compactInput">
							<input class="text village" type="text" name="dname" value="<?php echo htmlspecialchars(stripslashes($_POST['dname']),ENT_QUOTES,'UTF-8'); ?>" maxlength="30" tabindex="5">
						</td>
					</tr>
				</tbody>
			</table>

			<table cellpadding="0" cellspacing="0" class="transparent compact">
				<tbody>
					<tr>
						<td>
							<span class="or">Coordenadas</span>
<?php
$coor = false;
if(isset($_GET['z']) && is_scalar($_GET['z']) && ctype_digit((string)$_GET['z'])){
$coor = $database->getCoor((int)$_GET['z']);
}
if(!is_array($coor)){
$coor['x'] = "";
$coor['y'] = "";
}
?>			<div class="coordinatesInput">
				<div class="xCoord">
					<label for="xCoordInput">X:</label>
                    <input class="text coordinates x " type="text" name="x" value="<?php echo $coor['x']; ?>" maxlength="4" tabindex="6">
				</div>
				<div class="yCoord">
					<label for="yCoordInput">Y:</label>
                    <input class="text coordinates y " type="text" name="y" value="<?php echo $coor['y']; ?>" maxlength="4" tabindex="7">
				</div>
				<div class="clear"></div>
			</div>
									<div class="clear"></div>
						</td>
					</tr>
				</tbody>
			</table>
								</div>
				</div>
<?php if($session->goldclub == 1){?>
<p><select name="send3"><option value="1" selected="selected">1x</option><option value="2">2x</option><option value="3">3x</option></select> envíos</p>
<?php
}else{
?>
<input type="hidden" name="send3" value="1">
<?php
}
?>
				</div>
<div class="clear"></div>
<p>Cada mercader puede transportar <b><?php echo $market->maxcarry; ?></b> unidades de recursos</p>
<p>

<button type="submit" value="ok" name="s1" id="btn_ok" class="dynamic_img" tabindex="8"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Comprobar</div></div></button>
</p>
</form>
<?php
$error = '';
if($marketFormType === 'check'){

	if(!$checkexist){
		$error = '<span class="error"><b>No hay ninguna aldea en esas coordenadas.</b></span>';
    }elseif($_POST['r1']==0 && $_POST['r2']==0 && $_POST['r3']==0 && $_POST['r4']==0){
		$error = '<span class="error"><b>No se seleccionaron recursos.</b></span>';
    }elseif(!$_POST['x'] && !$_POST['y'] && !$_POST['dname']){
		$error = '<span class="error"><b>Introduce las coordenadas o el nombre de la aldea.</b></span>';
    }elseif($allres > $canSend){
		$error = '<span class="error"><b>Mercaderes insuficientes.</b></span>';
    }
    echo $error;
}
?>
<p>
<?php } ?>
<script type="text/javascript">
	window.addEvent('domready', function()
	{
		$('r1').focus();
		updateQuickAddState();
	});
	var haendler = <?php echo $market->merchantAvail(); ?>;
	var carry = <?php echo $market->maxcarry; ?>;
	var merchantRes = new Array(0,0,0,0,0);
	function selectedResources(excludeResNr)
	{
		var total = 0;
		for (var i = 1; i <= 4; i++)
		{
			if (i == excludeResNr)
			{
				continue;
			}
			var value = parseInt($('r' + i).value);
			if (!isNaN(value) && value > 0)
			{
				total += value;
			}
		}
		return total;
	}
	function updateQuickAddState()
	{
		var capacityUsed = selectedResources(0) >= haendler * carry;
		for (var i = 1; i <= 4; i++)
		{
			var link = $('marketQuickAdd' + i);
			if (link)
			{
				link.style.color = capacityUsed ? '#999999' : '';
			}
		}
	}
	function add_res(resNr)
	{
		var currentRes = resources['l' + resNr].value;
		var merchantMax = Math.max(0, haendler * carry - selectedResources(resNr));
		var inputRes = parseInt($('r' + resNr).value);
		if (isNaN(inputRes))
		{
			inputRes = 0;
		}
		merchantRes[resNr] = res_max(inputRes, currentRes, merchantMax, carry);
		$('r' + resNr).value = merchantRes[resNr];
		updateQuickAddState();
	}
	function upd_res(resNr, max)
	{
		var currentRes = resources['l' + resNr].value;
		var merchantMax = Math.max(0, haendler * carry - selectedResources(resNr));
		var inputRes;
		if (max)
		{
			inputRes = currentRes;
		}
		else
		{
			inputRes = parseInt($('r' + resNr).value);
		}
		if (isNaN(inputRes))
		{
			inputRes = 0;
		}
		merchantRes[resNr] = res_max(parseInt(inputRes), currentRes, merchantMax, 0);
		$('r' + resNr).value = merchantRes[resNr];
		updateQuickAddState();
	}
	function res_max(_merchantRes, _actualRes , _merchantMax , _carry)
	{
		var res = Number(_merchantRes) + _carry;
		if (res > _actualRes)
		{
			res = _actualRes;
		}
		if (res > _merchantMax)
		{
			res = _merchantMax;
		}
		if (res == 0)
		{
			res = '';
		}
		return res;
	}
</script>
<script language="JavaScript" type="text/javascript"> 
//<!--
document.snd.r1.focus();
//-->
</script>

<?php
$timer = 1;
if(count($market->recieving) > 0) { 
echo "<h4>Mercaderes entrantes</h4>";
    foreach($market->recieving as $recieve) {
       echo "<table class=\"traders\" cellpadding=\"1\" cellspacing=\"1\">";
    $ownerid = $database->getVillageField($recieve['from'],"owner");
    $ownername = $database->getUserField($ownerid,"username",0);
    $sendtovil = $database->getVillage($recieve['from']);
	$villageowner = $database->getVillageField($recieve['from'],"owner");
	echo "<thead><tr><td><a href=\"spieler.php?uid=".$ownerid."\">".$ownername."</a></td>";
    echo "<td class=\"dorf\">Recursos desde <a href=\"karte.php?d=".$recieve['from']."&c=".$generator->getMapCheck($recieve['from'])."\">".$sendtovil['name']."</a></td>";
    echo "</tr></thead><tbody><tr><th>Llegada</th><td>";
    echo "<div class=\"in\"><span id=timer$timer>".$generator->getTimeFormat($recieve['endtime']-time())."</span> horas</div>";
    $datetime = $generator->procMtime($recieve['endtime']);
    echo "<div class=\"at\">";

    echo $datetime[1]."</div>";
    $totalDeliveries = (int)$recieve['ref2'] > 0 ? (int)$recieve['ref2'] : max(1,(int)$recieve['send']);
    $currentDelivery = max(1,$totalDeliveries-(int)$recieve['send']+1);
    echo "</td></tr><tr><th>Viaje</th><td>".$currentDelivery." de ".$totalDeliveries."</td></tr></tbody> <tr class=\"res\"> <th>Recursos</th> <td colspan=\"2\"><span class=\"f10\">";
    echo "<img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\" title=\"Madera\" /> ".$recieve['wood']
    ." <img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\" title=\"Barro\" /> ".$recieve['clay']
    ." <img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\" title=\"Hierro\" /> ".$recieve['iron']
    ." <img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\" title=\"Cereal\" /> ".$recieve['crop']."</td></tr></tbody>";
    echo "</table>";
    $timer +=1;
    }
}
if(count($market->sending) > 0) {
	echo "<h4>Mercaderes en movimiento:</h4>";
    foreach($market->sending as $send) {
        $ownerid = $database->getVillageField($send['to'],"owner");
        $ownername = $database->getUserField($ownerid,"username",0);
        $sendtovil = $database->getVillage($send['to']);
        echo "<table class=\"traders\" cellpadding=\"1\" cellspacing=\"1\">";
        echo "<thead><tr> <td><a href=\"spieler.php?uid=".$ownerid."\">".$ownername."</a></td>";
        echo "<td class=\"dorf\">Enviando suministros a <a href=\"karte.php?d=".$send['to']."&c=".$generator->getMapCheck($send['to'])."\">".$sendtovil['name']."</a></td>";
        echo "</tr></thead> <tbody><tr> <th>Llegada</th> <td>";
        echo "<div class=\"in\"><span id=timer".$timer.">".$generator->getTimeFormat($send['endtime']-time())."</span> horas</div>";
        $datetime = $generator->procMtime($send['endtime']);
        echo "<div class=\"at\">";

        echo $datetime[1]."</div>";
        $totalDeliveries = (int)$send['ref2'] > 0 ? (int)$send['ref2'] : max(1,(int)$send['send']);
        $currentDelivery = max(1,$totalDeliveries-(int)$send['send']+1);
        echo "</td></tr><tr><th>Viaje</th><td>".$currentDelivery." de ".$totalDeliveries."</td></tr> <tr class=\"res\"> <th>Recursos</th><td>";
        echo "<img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\" title=\"Madera\" /> ".$send['wood']
        ." <img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\" title=\"Barro\" /> ".$send['clay']
        ." <img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\" title=\"Hierro\" /> ".$send['iron'].
        " <img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\" title=\"Cereal\" /> ".$send['crop']."</td></tr></tbody>";
        echo "</table>";
        $timer += 1;
    }
}
if(count($market->return) > 0) {
	echo "<h4>Mercaderes en movimiento:</h4>";
    foreach($market->return as $return) {
        $villageowner = $database->getVillageField($return['from'],"owner");
        $ownername = $database->getUserField($villageowner,"username",0);
        $villagename = $database->getVillageField($return['from'],"name");
        echo "<table class=\"traders\" cellpadding=\"1\" cellspacing=\"1\">";
        echo "<thead><tr> <td></td>";
        //echo "<td class=\"dorf\">Regreso desde <a href=\"karte.php?d=".$return['from']."&c=".$generator->getMapCheck($return['from'])." \">$ownername</a></td>";
        echo "<td class=\"dorf\">Regreso desde <a href=\"karte.php?d=".$return['from']."&c=".$generator->getMapCheck($return['from'])." \">$villagename</a></td>";
        echo "</tr></thead> <tbody><tr> <th>Llegada</th> <td>";
        echo "<div class=\"in\"><span id=timer".$timer.">".$generator->getTimeFormat($return['endtime']-time())."</span> horas</div>";
        $datetime = $generator->procMtime($return['endtime']);
        echo "<div class=\"at\">";
        if($datetime[0] != "hoy") {
        echo $datetime[0]." ";
        }
        echo $datetime[1]."</div>";
        $totalDeliveries = (int)$return['ref2'] > 0 ? (int)$return['ref2'] : max(1,(int)$return['send']);
        $currentDelivery = max(1,$totalDeliveries-(int)$return['send']+1);
        echo "</td></tr><tr><th>Viaje</th><td>".$currentDelivery." de ".$totalDeliveries."</td></tr> <tr class=\"res\"> <th>Recursos</th><td>";
                echo "<img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\" title=\"Madera\" /> ".$return['wood']."<img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\" title=\"Barro\" /> ".$return['clay']."<img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\" title=\"Hierro\" /> ".$return['iron']."<img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\" title=\"Cereal\" />".$return['crop']."</td></tr></tbody>";

        echo "</tbody></table>";
        $timer += 1;
    }
}
?>

</p><Br /></div> 
