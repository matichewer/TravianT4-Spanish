<?php if($session->plus) { ?>
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

/* --- Large (fullscreen) map grid, rebuilt cleanly --------------------------
   Fixes the old x/y-mixed neighbour math, centres correctly on (x,y), and
   renders a ring of buffer tiles so click-and-drag doesn't reveal blanks.
   Knobs: $VHX/$VHY = visible half-size, $BUF = buffer tiles per side.        */
$VHX    = 10;                 /* visible half-width  -> 2*VHX+1 = 21 columns */
$VHY    = 6;                  /* visible half-height -> 2*VHY+1 = 13 rows    */
$BUF    = 8;                  /* extra tiles rendered beyond the visible area */
$HX     = $VHX + $BUF;
$HY     = $VHY + $BUF;
$COLS   = 2*$HX + 1;
$ROWS   = 2*$HY + 1;
$VCOLS  = 2*$VHX + 1;
$VROWS  = 2*$VHY + 1;
$TILE   = 60;
$PERIOD = 2*WORLD_MAX + 1;
$wrapCoord = function($v) use ($PERIOD) {
    while ($v >  WORLD_MAX) { $v -= $PERIOD; }
    while ($v < -WORLD_MAX) { $v += $PERIOD; }
    return $v;
};
$xfull = array();
for ($dx = -$HX; $dx <= $HX; $dx++) { $xfull[] = $wrapCoord($x + $dx); }   /* west -> east */
$yfull = array();
for ($dy = $HY; $dy >= -$HY; $dy--) { $yfull[] = $wrapCoord($y + $dy); }   /* north -> south */
$xarray = array_slice($xfull, $HX - $VHX, $VCOLS);   /* visible slice, for the rulers */
$yarray = array_slice($yfull, $HY - $VHY, $VROWS);
$maparray = array();
foreach ($yfull as $yy) {
    foreach ($xfull as $xx) {
        $maparray[] = $database->getMInfo($generator->getBaseID($xx, $yy));
    }
}
echo "<h1 dir=\"rtl\">Mapa (<span id=\"x\">".$x."</span>|<span id=\"y\">".$y."</span>)</h1>";
echo "<div class=\"mapTopBar\"><a href=\"dorf1.php\">&laquo; Aldea</a><a href=\"karte.php?x=".$x."&y=".$y."\">Mapa normal</a></div>";
$row = 0;
$coorindex = 0;
?>

<div class="map2 lowRes" style="width:<?php echo $VCOLS*$TILE + 27; ?>px;height:auto;margin:0 auto;">
	<div id="mapContainer" class="lowRes" style="position:relative;left:0;top:0;width:<?php echo $VCOLS*$TILE; ?>px;height:auto;margin-left:27px;display:block;">
<div id="mapViewport" style="position:relative;width:<?php echo $VCOLS*$TILE; ?>px;height:<?php echo $VROWS*$TILE; ?>px;overflow:hidden;">
<div class="mapContainerData" id="mapData" style="position:absolute;left:-<?php echo $BUF*$TILE; ?>px;top:-<?php echo $BUF*$TILE; ?>px;width:<?php echo $COLS*$TILE; ?>px;height:<?php echo $ROWS*$TILE; ?>px;">
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


   	$image = ($maparray[$index]['occupied'] == 1 && $maparray[$index]['fieldtype'] > 0)? (($maparray[$index]['owner'] == $session->uid)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b30-'.$tribe: 'b20-'.$tribe :'b10-'.$tribe : 'b00-'.$tribe) : (($targetalliance != 0)? (in_array($targetalliance,$friendarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b31': 'b21' :'b11' : 'b01') : (in_array($targetalliance,$enemyarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b32': 'b22' :'b12' : 'b02') : (in_array($targetalliance,$neutralarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b35': 'b25' :'b15' : 'b05') : ($targetalliance == $session->alliance? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b33': 'b23' :'b13' : 'b03') : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))))) : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))) : $maparray[$index]['image'];

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
    	$tribename = "Natar";
        }

    $odata = $database->getOMInfo($maparray[$index]['id']);
    $uinfo = $database->getUserField($odata['owner'],'username',0);

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


    	$vid = $maparray[$index]['id'];
		$incoming_attacks = $database->getMovement(3,$vid,1);
		$att = '';

		if (count($incoming_attacks) > 0) {
			$inc_atts = count($incoming_attacks);
				if($incoming_attacks[$i]['attack_type'] == 2) {
					$inc_atts -= 1;
				}
			if($inc_atts > 0) {
				$att = '<img style="margin-right:45px;" class="att1" src="img/x.gif" />';
			}
		}

    if(!$maparray[$index]['fieldtype'] && $maparray[$index]['oasistype'] && $maparray[$index]['occupied']){
    	$occupied = "-s";
    }else{ $occupied = ""; }
    echo "<a class=\"mapTileLink\" href=\"position_details.php?x=".$maparray[$index]['x']."&y=".$maparray[$index]['y']."\" style=\"cursor:default;\"><div class=\"tile tile-".$i."-row".$row1." ".$image."".$occupied."\" title=\"".$targettitle."\" onclick=\"return TravianMapTileDetails(event,".(int)$maparray[$index]['x'].",".(int)$maparray[$index]['y'].");\">";
    if($session->plus) {
    echo $att;
    }
    echo "</div></a>\n";

	$index+=1;

}
?>
</div><!-- #mapData -->
</div><!-- #mapViewport -->

<div class="clear"></div>
<div class="ruler x" style="background-color:#FFF; height:23px;">
	<div class="rulerContainer">
    	<?php
			for($i=0;$i<$VCOLS;$i++) {
				echo "<div class=\"coordinate zoom1\">".$xarray[$i]."</div>\n";
			}
		?>
				<div class="clear"></div>
	</div>
</div>
<div class="ruler y" style="height:<?php echo $VROWS*$TILE; ?>px;">
	<div class="rulerContainer">
    	<?php
			for($i=0;$i<$VROWS;$i++) {
				echo "<div class=\"coordinate zoom1\">".$yarray[$i]."</div>\n";
			}
		?>
</div>
</div>
		<div class="navigation" style="margin-bottom: -15px;">
			<a href="karte2.php?x=<?php echo $wrapCoord($x-1); ?>&y=<?php echo $y; ?>" id="navigationMoveLeft" class="moveLeft">
            <img src="img/x.gif" title="mover izquierda"></a>
			<a href="karte2.php?x=<?php echo $wrapCoord($x+1); ?>&y=<?php echo $y; ?>" id="navigationMoveRight" class="moveRight">
            <img src="img/x.gif" title="mover derecha"></a>
			<a href="karte2.php?x=<?php echo $x; ?>&y=<?php echo $wrapCoord($y+1); ?>" id="navigationMoveUp" class="moveUp">
            <img src="img/x.gif" title="mover arriba"></a>
			<a href="karte2.php?x=<?php echo $x; ?>&y=<?php echo $wrapCoord($y-1); ?>" id="navigationMoveDown" class="moveDown">
            <img src="img/x.gif" title="mover abajo"></a>
            <a href="karte.php?x=<?php echo $x; ?>&y=<?php echo $y; ?>" id="navigationFullScreen" class="viewFullScreen normal"><img src="img/x.gif" alt="mapa normal" title="Mapa normal"></a>
		</div>
		<form id="mapCoordEnter" name="map_coords" method="post" action="karte2.php" class="toolbar" style="margin-bottom: -15px;">
	<div class="ml">
		<div class="mr">
			<div class="mc">
				<div class="contents">
			<div class="coordinatesInput">
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
</form>	</div>
</div>
<style type="text/css">
body.map{background:#c8dd9b;}
.mapTopBar{position:fixed;top:12px;right:20px;z-index:1000;}
.mapTopBar a{display:inline-block;margin-left:8px;padding:7px 15px;background:#6b8e23;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;font-weight:bold;border:1px solid #4d6619;box-shadow:0 1px 2px rgba(0,0,0,.3);}
.mapTopBar a:hover{background:#7ba428;}
#mapContainer.lowRes #mapData{cursor:grab;cursor:-webkit-grab;touch-action:none;user-select:none;-webkit-user-select:none;}
#mapContainer.lowRes.dragPanning #mapData{cursor:grabbing;cursor:-webkit-grabbing;}
#mapContainer.lowRes #mapData a,#mapContainer.lowRes #mapData img{-webkit-user-drag:none;user-select:none;-webkit-user-select:none;}
#mapContainer.lowRes .ruler.y .coordinate{height:<?php echo $TILE; ?>px;}
.dialog.mapTileDetailsDialog{color:#333;font-size:13px;}
.dialog.mapTileDetailsDialog .dialog-container{background:#fff;border:1px solid #9a9a9a;border-radius:8px;box-shadow:0 3px 12px rgba(0,0,0,.45);}
.dialog.mapTileDetailsDialog .dialog-tl,.dialog.mapTileDetailsDialog .dialog-tc,.dialog.mapTileDetailsDialog .dialog-tr,
.dialog.mapTileDetailsDialog .dialog-ml,.dialog.mapTileDetailsDialog .dialog-mc,.dialog.mapTileDetailsDialog .dialog-mr,
.dialog.mapTileDetailsDialog .dialog-bl,.dialog.mapTileDetailsDialog .dialog-bc,.dialog.mapTileDetailsDialog .dialog-br,
.dialog.mapTileDetailsDialog .dialog-background-tl,.dialog.mapTileDetailsDialog .dialog-background-tc,.dialog.mapTileDetailsDialog .dialog-background-tr,
.dialog.mapTileDetailsDialog .dialog-background-ml,.dialog.mapTileDetailsDialog .dialog-background-mc,.dialog.mapTileDetailsDialog .dialog-background-mr,
.dialog.mapTileDetailsDialog .dialog-background-bl,.dialog.mapTileDetailsDialog .dialog-background-bc,.dialog.mapTileDetailsDialog .dialog-background-br{background-image:none!important;}
.dialog.mapTileDetailsDialog .title,.dialog.mapTileDetailsDialog #tileDetails,.dialog.mapTileDetailsDialog #tileDetails h4{color:#333;}
.dialog.mapTileDetailsDialog #tileDetails .detailImage .option{background:#f4f4f4;border-top:1px solid #ddd;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-1 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w2-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-2 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w3-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-3 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w4-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-4 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w6-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-5 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w8-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-6 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w7-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-7 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w10-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-8 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w12-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-9 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w11-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-10 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w10-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-11 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w14-rtl.jpg')!important;}
.dialog.mapTileDetailsDialog #tileDetails.oasis-12 .detailImage{background-image:url('gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w15-rtl.jpg')!important;}
</style>
<script type="text/javascript">
/* Click-and-drag panning (mouse + touch) for the fullscreen map: grab, release
   to re-center. A click without movement opens the selected tile details. */
(function(){
	var TILE=<?php echo (int)$TILE; ?>, THRESHOLD=6, WORLD=<?php echo (int)WORLD_MAX; ?>, PERIOD=2*WORLD+1;
	var curX=<?php echo (int)$x; ?>, curY=<?php echo (int)$y; ?>;
	var dialogOpen=false;
	function bindMapLinks(content){
		$(content).getElements('a[href^="karte.php"]').addEvent('click',function(event){
			event.stop();
			var url=new URI(this.href);
			window.location.href='karte2.php?x='+parseInt(url.getData('x'),10)+'&y='+parseInt(url.getData('y'),10);
		});
	}
	window.TravianMapTileDetails=function(event,x,y){
		if(event){ event.preventDefault(); event.stopPropagation(); }
		if(dialogOpen) return false;
		dialogOpen=true;
		var popup=new Travian.Dialog({
			buttonOk:false,
			cssClass:'white mapTileDetailsDialog',
			title:'Detalles de la casilla ('+x+'|'+y+')',
			onClose:function(){ dialogOpen=false; }
		}).setContent('<div class="loading"></div>').show();
		var showError=function(){
			popup.setContent('<p>No se pudo cargar la información de esta casilla.</p>');
			return false;
		};
		new Request.HTML({
			url:'position_details.php?popup=1&x='+encodeURIComponent(x)+'&y='+encodeURIComponent(y),
			method:'get',
			evalScripts:false,
			onSuccess:function(tree,elements,responseHTML){
				popup.setContent(responseHTML);
				var popupTitle=$(popup.content).getElement('#tileDetailsPopupTitle');
				if(popupTitle){ popup.setTitle(popupTitle.get('html')); popupTitle.destroy(); }
				bindMapLinks(popup.content);
			},
			onFailure:showError,
			onException:showError
		}).send();
		return false;
	};
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
			if(tdx===0 && tdy===0){ data.style.transform=''; return; } /* sub-tile drag: snap back */
			/* Freeze snapped to the new centre so it doesn't flash back while reloading. */
			data.style.transform='translate('+(tdx*TILE)+'px,'+(tdy*TILE)+'px)';
			var nx=curX-tdx, ny=curY+tdy;
			if(nx>WORLD) nx-=PERIOD; if(nx<-WORLD) nx+=PERIOD;
			if(ny>WORLD) ny-=PERIOD; if(ny<-WORLD) ny+=PERIOD;
			window.location.href='karte2.php?x='+nx+'&y='+ny;
		});
	});
})();
</script></div> <?php } ?>
