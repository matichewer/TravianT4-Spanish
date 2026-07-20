
<div id="content" class="map">

<div class="t2"></div>

<?php
if(isset($_GET['d']) && isset($_GET['c'])) {
	if($generator->getMapCheck($_GET['d']) == $_GET['c']) {
        $wref = $_GET['d'];
        $coor = $database->getCoor($wref);
        $x = $coor['x'];
        $y = $coor['y'];
	}
}
else if(isset($_GET['x']) && isset($_GET['y'])) {
    $x = $_GET['x'];
    $y = $_GET['y'];
    $bigmid = $generator->getBaseID($x,$y);
}
else if(isset($_POST['xp']) && isset($_POST['yp'])){
	$x = $_POST['xp'];
    $y = $_POST['yp'];
    $bigmid = $generator->getBaseID($x,$y);
}
else {
    $y = $village->coor['y'];
	$x = $village->coor['x'];
    $bigmid = $village->wid;
}

/* --- Map grid with a render buffer, so dragging doesn't reveal blank tiles --- */
$BUF    = 6;              /* extra tiles rendered beyond the visible area, each side */
$HX     = 4 + $BUF;       /* half width  (visible half 4 + buffer) -> 2*HX+1 = 21 cols */
$HY     = 3 + $BUF;       /* half height (visible half 3 + buffer) -> 2*HY+1 = 19 rows */
$COLS   = 2 * $HX + 1;
$ROWS   = 2 * $HY + 1;
$PERIOD = 2 * WORLD_MAX + 1;
$wrapCoord = function($v) use ($PERIOD) {
    while ($v >  WORLD_MAX) { $v -= $PERIOD; }
    while ($v < -WORLD_MAX) { $v += $PERIOD; }
    return $v;
};

$xfull = array();
for ($dx = -$HX; $dx <= $HX; $dx++) { $xfull[] = $wrapCoord($x + $dx); }   /* west -> east */
$yfull = array();
for ($dy = $HY; $dy >= -$HY; $dy--) { $yfull[] = $wrapCoord($y + $dy); }   /* north -> south */

/* visible center slices, used only for the coordinate rulers */
$xarray = array_slice($xfull, $HX - 4, 9);
$yarray = array_slice($yfull, $HY - 3, 7);

$maparray = array();
foreach ($yfull as $yy) {
    foreach ($xfull as $xx) {
        $maparray[] = $database->getMInfo($generator->getBaseID($xx, $yy));
    }
}
echo "<h1 dir=\"rtl\">Mapa</h1>";
$row = 0;
$coorindex = 0;
?>

<div class="map2 lowRes">
	<div id="mapContainer" class="lowRes">
  <?php if($session->goldclub) { ?>
  
    <div id="toolbar" class="toolbar">
	<div class="ml">
		<div class="mr">
			<div class="mc">
				<div class="contents">
                	<a href="cropfinder.php"><div class="iconButton linkCropfinder" title="15-9 Buscador de cereal"></div></a>
				</div>
			</div>
		</div>
	</div>
	<div class="bl">
		<div class="mr">
			<div class="bc"></div>
		</div>
	</div>
</div>
<?php } ?>
<div id="mapViewport" style="position:relative;width:540px;height:420px;overflow:hidden;">
<div class="mapContainerData" id="mapData" style="position:absolute;left:-<?php echo $BUF*60; ?>px;top:-<?php echo $BUF*60; ?>px;width:<?php echo $COLS*60; ?>px;height:<?php echo $ROWS*60; ?>px;">
<?php
$index = 0;
$row1 = 0;


for($i=0;$i<count($maparray);$i++) {
	$row1 = intdiv($i, $COLS);
	if($maparray[$index]['occupied'] > 0 && $maparray[$index]['fieldtype'] >= 0) {
	$targetalliance = $database->getUserField($maparray[$index]['owner'],"alliance",0);
    $tribe = $database->getUserField($maparray[$index]['owner'],"tribe",0);
    $username = $database->getUserField($maparray[$index]['owner'],"username",0);
    $oasisowner = $database->getUserField($maparray[$index]['owner'],"username",0);
    $friendarray = array();
    $enemyarray = array();
    $neutralarray = array();
    }
    
    
switch($maparray[$index]['fieldtype']) {
case 1:
$tt =  "3-3-3-9";
break;
case 2:
$tt =  "3-4-5-6";
break;
case 3:
$tt =  "4-4-4-6";
break;
case 4:
$tt =  "4-5-3-6";
break;
case 5:
$tt =  "5-3-4-6";
break;
case 6:
$tt =  "1-1-1-15";
break;
case 7:
$tt =  "4-4-3-7";
break;
case 8:
$tt =  "3-4-4-7";
break;
case 9:
$tt =  "4-3-4-7";
break;
case 10:
$tt =  "3-5-4-6";
break;
case 11:
$tt =  "4-3-5-6";
break;
case 12:
$tt =  "5-4-3-6";
break;
case 0:
switch($maparray[$index]['oasistype']) {
case 1:
$tt =  "<img class='r1' src='img/x.gif' /> Madera 25%";
break;
case 2:
$tt =  "<img class='r1' src='img/x.gif' /> Madera 50%";
break;
case 3:
$tt =  "<img class='r1' src='img/x.gif' /> Madera 25%<br><img class='r4' src='img/x.gif' /> Cereal 25%";
break;
case 4:
$tt =  "<img class='r2' src='img/x.gif' /> Barro 25%";
break;
case 5:
$tt =  "<img class='r2' src='img/x.gif' /> Barro 50%";
break;
case 6:
$tt =  "<img class='r2' src='img/x.gif' /> Barro 25%<br><img class='r4' src='img/x.gif' /> Cereal 25%";
break;
case 7:
$tt =  "<img class='r3' src='img/x.gif' /> Hierro 25%";
break;
case 8:
$tt =  "<img class='r3' src='img/x.gif' /> Hierro 50%";
break;
case 9:
$tt =  "<img class='r3' src='img/x.gif' /> Hierro 25%<br><img class='r4' src='img/x.gif' /> Cereal 25%";
break;
case 10:
case 11:
$tt =  "<img class='r4' src='img/x.gif' /> Cereal 25%";
break;
case 12:
$tt =  "<img class='r4' src='img/x.gif' /> Cereal 50%";
break;
}
break;
}

   	$image = ($maparray[$index]['occupied'] == 1 && $maparray[$index]['fieldtype'] > 0)? (($maparray[$index]['owner'] == $session->uid)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b30-'.$tribe: 'b20-'.$tribe :'b10-'.$tribe : 'b00-'.$tribe) : (($targetalliance != 0)? (in_array($targetalliance,$friendarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b31-'.$tribe: 'b21-'.$tribe :'b11-'.$tribe : 'b01-'.$tribe) : (in_array($targetalliance,$enemyarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b32-'.$tribe: 'b22-'.$tribe :'b12-'.$tribe : 'b02-'.$tribe) : (in_array($targetalliance,$neutralarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b35-'.$tribe: 'b25-'.$tribe :'b15-'.$tribe : 'b05-'.$tribe) : ($targetalliance == $session->alliance? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b33-'.$tribe: 'b23-'.$tribe :'b13-'.$tribe : 'b03-'.$tribe) : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))))) : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))) : $maparray[$index]['image'];
    
    if($targetalliance!=0) {
    	$allyname = $database->getAllianceName($targetalliance);
    	}
    if($tribe==1) {
    	$tribename = "Romano";
    }elseif($tribe==2) {
    	$tribename = "Teutón";
    }elseif($tribe==3) {
    	$tribename = "Galo";
    }elseif($tribe==5) {
    	$tribename = "Natares";
        }
    if($maparray[$index]['fieldtype'] > 0){
    $odata = $database->getOMInfo($maparray[$index]['id']);
    $uinfo = $database->getUserField($odata['owner'],'username',0);
	}else{
	$vdata = $database->getMInfo($maparray[$index]['id']);
    $uinfo = $database->getUserField($vdata['owner'],'username',0);
	}
    
    if($maparray[$index]['fieldtype'] > 0 && $maparray[$index]['occupied'] == 1) {
    $targettitle = "<font color='white'><b>Aldea ".$maparray[$index]['name']."</b></font><br>(".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br>Jugador: ".$username."<br>Población: ".$maparray[$index]['pop']."<br>Alianza ".$allyname."<br>Tribu: ".$tribename."";
    }
    if($maparray[$index]['oasistype'] == 0 && $maparray[$index]['occupied'] == 0) {
    $targettitle = "<font color='white'><b>Valle abandonado ".$tt."</b></font><br>(".$maparray[$index]['x']."|".$maparray[$index]['y'].")";
    }
    
    if($maparray[$index]['fieldtype'] == 0 && $maparray[$index]['oasistype'] > 0 && $maparray[$index]['occupied'] == 0) {
    $targettitle = "<font color='white'><b>Oasis desocupado</b></font><br /> (".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br />".$tt."";
    }elseif($maparray[$index]['fieldtype'] == 0 && $maparray[$index]['oasistype'] > 0 && $maparray[$index]['occupied'] > 0) {
    $targettitle = "<font color='white'><b>oasis ocupado</b></font><br /> (".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br />".$tt."<br>Jugador: ".$uinfo."<br>Alianza: ".$allyname."<br>Tribu: ".$tribename."";
    }
    
    if(!$maparray[$index]['fieldtype'] && $maparray[$index]['oasistype'] && $maparray[$index]['occupied']){
    	$occupied = "-s";
    }else{ $occupied = ""; }
    echo "<a href=\"position_details.php?x=".$maparray[$index]['x']."&y=".$maparray[$index]['y']."\" style=\"cursor:default;\"><div class=\"tile tile-".$i."-row".$row1." ".$image."".$occupied."\" title=\"".$targettitle."\">";
    if($session->plus) {
    	$wref = $village->wid;
        $toWref = $maparray[$index]['id'];
    	if ($database->checkAttack($wref,$toWref) != 0) {
			echo '<img style="margin-right:45px;" class="att1" src="img/x.gif" />';
		}
    }
    echo "</div></a>\n";
    
	$index+=1;

}
?>
</div><!-- #mapData -->
</div><!-- #mapViewport -->
<div class="clear"></div>
<div class="ruler x">
	<div class="rulerContainer">
    	<?php
			for($i=0;$i<=8;$i++) {
				echo "<div class=\"coordinate zoom1\">".$xarray[$i]."</div>\n";
			}
		?>
				<div class="clear"></div>
	</div>
</div>
<div class="ruler y">
	<div class="rulerContainer">
    	<?php
			for($i=0;$i<=6;$i++) {
				echo "<div class=\"coordinate zoom1\">".$yarray[$i]."</div>\n";
			}
		?>
</div>
</div>
		<div class="navigation">
			<a href="karte.php?x=<?php echo $x-1; ?>&y=<?php echo $y; ?>" id="navigationMoveLeft" class="moveLeft"><img src="img/x.gif" title="mover a la izquierda"></a>
            <a href="karte.php?x=<?php echo $x+1; ?>&y=<?php echo $y; ?>" id="navigationMoveRight" class="moveRight"><img src="img/x.gif" title="mover a la derecha"></a>
			<a href="karte.php?x=<?php echo $x; ?>&y=<?php echo $y+1; ?>" id="navigationMoveUp" class="moveUp"><img src="img/x.gif" title="mover arriba"></a>
			<a href="karte.php?x=<?php echo $x; ?>&y=<?php echo $y-1; ?>" id="navigationMoveDown" class="moveDown"><img src="img/x.gif" title="mover abajo"></a>
            <?php if($session->plus) { ?>
            <a href="karte2.php?x=<?php echo $x ?>&y=<?php echo $y; ?>" id="navigationFullScreen" class="viewFullScreen full"><img src="img/x.gif" alt="ver a pantalla completa" title="ver a pantalla completa"></a>
            <?php } ?>
		</div>
		<form id="mapCoordEnter" name="map_coords" method="post" action="karte.php" class="toolbar ">
	<div class="ml">
		<div class="mr">
			<div class="mc">
				<div class="contents">
			<div class="coordinatesInput">
            <?php
            if(isset($_GET['x']) && isset($_GET['y'])) {
            	$x = $_GET['x'];
                $y = $_GET['y'];
                }else{
                //$x = "0";
                //$y = "0";
                }
            ?>
				<div class="xCoord">
					<label for="xCoordInputMap">X:</label>
                    <input id="mcx" class="text" name="xp" value="" maxlength="4"/>
				</div>
				<div class="yCoord">
					<label for="yCoordInputMap">Y:</label>
					<input id="mcy" class="text" name="yp" value="" maxlength="4"/>
				</div>
			</div>
			<button type="submit" value="OK" class="small"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">OK</div></div></button>					<div class="clear"></div>
				</div>
			</div>
		</div>
	</div>
</form>
</div>
</div>
<script type="text/javascript">
		window.addEvent('domready', function()
	{
		
		Travian.Game.Map.LowRes.Options.default.tileDisplayInformation.type = 'dialog';
		new Travian.Game.Map.LowRes.Container($merge(Travian.Game.Map.LowRes.Options.default,
		{
			fullScreen:	false,
			mapInitialPosition:
			{
				x:	<?php echo $x; ?>,
				y:	<?php echo $y; ?>			}
		}));
	});
</script>
<style type="text/css">
#mapContainer.lowRes #mapData{cursor:grab;cursor:-webkit-grab;touch-action:none;user-select:none;-webkit-user-select:none;}
#mapContainer.lowRes.dragPanning #mapData{cursor:grabbing;cursor:-webkit-grabbing;}
#mapContainer.lowRes #mapData a,#mapContainer.lowRes #mapData img{-webkit-user-drag:none;user-select:none;-webkit-user-select:none;}
</style>
<script type="text/javascript">
/* Click-and-drag panning: grab the map, release to re-center. Falls back to
   the plain tile links when the pointer barely moves (a real click). */
(function(){
	var TILE=60, THRESHOLD=6, WORLD=<?php echo (int)WORLD_MAX; ?>, PERIOD=2*WORLD+1;
	var curX=<?php echo (int)$x; ?>, curY=<?php echo (int)$y; ?>;
	function ready(fn){ if(document.readyState!='loading'){fn();} else {document.addEventListener('DOMContentLoaded',fn);} }
	ready(function(){
		var container=document.getElementById('mapContainer');
		var data=document.getElementById('mapData');
		if(!container||!data) return;
		var dragging=false, moved=false, sx=0, sy=0, dx=0, dy=0;
		data.addEventListener('dragstart', function(e){ e.preventDefault(); });
		data.addEventListener('pointerdown', function(e){
			if(e.button!==0) return;               /* primary mouse button / touch / pen */
			dragging=true; moved=false; sx=e.clientX; sy=e.clientY; dx=0; dy=0;
		});
		document.addEventListener('pointermove', function(e){
			if(!dragging) return;
			dx=e.clientX-sx; dy=e.clientY-sy;
			if(!moved && (Math.abs(dx)>THRESHOLD||Math.abs(dy)>THRESHOLD)){
				moved=true; container.classList.add('dragPanning');
			}
			if(moved){ e.preventDefault(); data.style.transform='translate('+dx+'px,'+dy+'px)'; }
		});
		document.addEventListener('pointercancel', function(){
			if(!dragging) return;
			dragging=false; container.classList.remove('dragPanning');
			if(moved){ data.style.transform=''; }
		});
		document.addEventListener('pointerup', function(){
			if(!dragging) return;
			dragging=false;
			if(!moved) return; /* a tap/click: let the tile <a href> do its thing */
			container.classList.remove('dragPanning');
			/* swallow the click that fires right after a drag */
			var kill=function(ev){ ev.preventDefault(); ev.stopPropagation(); document.removeEventListener('click',kill,true); };
			document.addEventListener('click',kill,true);
			setTimeout(function(){ document.removeEventListener('click',kill,true); },50);
			var tdx=Math.round(dx/TILE); /* drag right -> reveal west -> x decreases */
			var tdy=Math.round(dy/TILE); /* drag down  -> reveal north -> y increases */
			if(tdx===0 && tdy===0){ data.style.transform=''; return; } /* sub-tile drag: snap back, no reload */
			/* Freeze the map snapped to the new centre (whole tiles) instead of
			   resetting it, so it doesn't flash back to the old position while the
			   reload renders. The snap lines up with what the reload will show. */
			data.style.transform='translate('+(tdx*TILE)+'px,'+(tdy*TILE)+'px)';
			var nx=curX-tdx, ny=curY+tdy;
			if(nx>WORLD) nx-=PERIOD; if(nx<-WORLD) nx+=PERIOD;
			if(ny>WORLD) ny-=PERIOD; if(ny<-WORLD) ny+=PERIOD;
			window.location.href='karte.php?x='+nx+'&y='+ny;
		});
	});
})();
</script></div>