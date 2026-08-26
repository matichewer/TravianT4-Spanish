
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
echo "<div class=\"mapElephantFinderButton\"><a href=\"elephantfinder.php\">Buscador de elefantes</a></div>";
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



// La diplomacia de la alianza de quien mira, resuelta una sola vez para toda la vista.
// Antes estos tres arreglos se creaban vacios dentro del bucle, casilla por casilla, asi que
// las ramas de aliado y enemigo del sprite eran inalcanzables por construccion: un aliado se
// veia igual que un desconocido. Se calcula aca, fuera del bucle, porque el mapa ya hace una
// consulta por casilla y esto seria una mas por cada una.
include_once(dirname(__DIR__, 2)."/GameEngine/Diplomacy.php");
$friendarray = alliedAlliances($session->alliance);      // marco verde
$enemyarray = hostileAlliances($session->alliance);      // marco rojo
$neutralarray = napAlliances($session->alliance);        // marco cian

for($i=0;$i<count($maparray);$i++) {
	$row1 = intdiv($i, $COLS);
	$targetalliance = 0;
	$tribe = 0;
	$username = '';
	$uinfo = '';
	$allyname = '-';
	$tribename = '-';
	if($maparray[$index]['occupied'] > 0 && $maparray[$index]['fieldtype'] >= 0) {
	$tileowner = (int)$maparray[$index]['owner'];
	if($maparray[$index]['fieldtype'] == 0 && $maparray[$index]['oasistype'] > 0) {
		$odata = $database->getOMInfo($maparray[$index]['id']);
		$tileowner = (int)$odata['owner'];
	}
	$targetalliance = $database->getUserField($tileowner,"alliance",0);
    $tribe = $database->getUserField($tileowner,"tribe",0);
    $username = $database->getUserField($tileowner,"username",0);
    $uinfo = $username;
    // Los tres arreglos se calculan UNA vez antes del bucle (ver arriba): antes se
    // reinicializaban vacios en cada casilla, asi que la diplomacia nunca podia influir.
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
// El tipo de oasis se describe en un solo lugar, oasisTypeBonuses() en
// Production.php, derivado del mismo reparto que cobra la producción. Un tipo
// inesperado ahora da vacío: $tt no se reinicia en cada casilla, y el switch sin
// default dejaba en pie el tooltip de la casilla anterior.
$tt = oasisBonusTooltip($maparray[$index]['oasistype']);
break;
}

    // Una casilla marcada como ocupada pero sin fila en `vdata` no tiene dueño ni tribu,
    // así que la clase del sprite salía como `b04-0`, que no existe: la casilla se veía
    // BLANCA en el mapa. Pasa cuando algún camino borra una aldea sin liberar el campo.
    // Ante la duda se dibuja el terreno, que es lo que la casilla realmente es.
    $hasVillage = isset($maparray[$index]['wref']) && $maparray[$index]['wref'] !== null;
   	$image = ($maparray[$index]['occupied'] == 1 && $maparray[$index]['fieldtype'] > 0 && $hasVillage)? (($maparray[$index]['owner'] == $session->uid)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b30-'.$tribe: 'b20-'.$tribe :'b10-'.$tribe : 'b00-'.$tribe) : (($targetalliance != 0)? (in_array($targetalliance,$friendarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b31-'.$tribe: 'b21-'.$tribe :'b11-'.$tribe : 'b01-'.$tribe) : (in_array($targetalliance,$enemyarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b32-'.$tribe: 'b22-'.$tribe :'b12-'.$tribe : 'b02-'.$tribe) : (in_array($targetalliance,$neutralarray)? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b35-'.$tribe: 'b25-'.$tribe :'b15-'.$tribe : 'b05-'.$tribe) : ($targetalliance == $session->alliance? ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b33-'.$tribe: 'b23-'.$tribe :'b13-'.$tribe : 'b03-'.$tribe) : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))))) : ($maparray[$index]['pop']>=100? $maparray[$index]['pop']>= 250?$maparray[$index]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))) : $maparray[$index]['image'];
    
    if($targetalliance!=0) {
    	$allyname = $database->getAllianceName($targetalliance);
    	}
    if($tribe==1) {
    	$tribename = "Romano";
    }elseif($tribe==2) {
		$tribename = "Germano";
    }elseif($tribe==3) {
    	$tribename = "Galo";
    }elseif($tribe==5) {
    	$tribename = "Natares";
        }
    if($maparray[$index]['fieldtype'] > 0 && $maparray[$index]['occupied'] == 1) {
    // Sin "Aldea " adelante: el nombre ya suele traer la palabra —"Aldea de la
    // Maravilla" salía como "Aldea Aldea de la Maravilla"— y el tooltip ya dice de qué
    // se trata en las líneas de Jugador, Población y Tribu.
    $targettitle = "<font color='white'><b>".$maparray[$index]['name']."</b></font><br>(".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br>Jugador: ".$username."<br>Población: ".$maparray[$index]['pop']."<br>Alianza ".$allyname."<br>Tribu: ".$tribename."";
    }
    if($maparray[$index]['oasistype'] == 0 && $maparray[$index]['occupied'] == 0) {
    // La zona gris tiene que verse, o fundar ahí es una trampa: el jugador no puede
    // adivinar dónde empieza. Sólo se marca en los valles libres, que son los únicos donde
    // la advertencia sirve para algo.
    $greyZoneWarning = greyZoneContainsCoordinates($maparray[$index]['x'], $maparray[$index]['y'])
        ? "<br><b>Zona gris</b>: fundar acá despierta a los natars"
        : "";
    $targettitle = "<font color='white'><b>Valle abandonado ".$tt."</b></font><br>(".$maparray[$index]['x']."|".$maparray[$index]['y'].")".$greyZoneWarning;
    }
    
    if($maparray[$index]['fieldtype'] == 0 && $maparray[$index]['oasistype'] > 0 && $maparray[$index]['occupied'] == 0) {
    $targettitle = "<font color='white'><b>Oasis desocupado</b></font><br /> (".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br />".$tt."";
    }elseif($maparray[$index]['fieldtype'] == 0 && $maparray[$index]['oasistype'] > 0 && $maparray[$index]['occupied'] > 0) {
    $targettitle = "<font color='white'><b>oasis ocupado</b></font><br /> (".$maparray[$index]['x']."|".$maparray[$index]['y'].")<br />".$tt."<br>Jugador: ".$uinfo."<br>Alianza: ".$allyname."<br>Tribu: ".$tribename."";
    }
    
    if(!$maparray[$index]['fieldtype'] && $maparray[$index]['oasistype'] && $maparray[$index]['occupied']){
    	$occupied = "-s";
    }else{ $occupied = ""; }
    // El tinte va en TODA casilla de la zona, ocupada o libre: lo que el jugador tiene
    // que poder ver de un vistazo es la región entera, no sólo dónde puede fundar.
    // Una aldea anterior a la zona se ve como siempre: su dueño se instaló ahí cuando la
    // regla no existía, y pintarla de ceniza sería contarle una historia que no vivió.
    $greyZoneTile = (greyZoneContainsCoordinates($maparray[$index]['x'], $maparray[$index]['y'])
        && greyZoneAffectsVillage($maparray[$index]))
        ? ' greyzone'
        : '';
    // El suelo de la zona gris es CENIZA, no pasto tintado: así se ve en el T4 oficial, y
    // el sprite está en el gpack Travian_4.0_41 que ya vive en el repo. Los t0..t9 son
    // todos variantes de pasto con adornos distintos, así que el reemplazo es directo.
    // Los oasis (oN) y las aldeas (bNN-T) conservan su propio sprite: ahí el tinte alcanza
    // para que se lea que están dentro de la zona.
    // El volcán del centro va antes que la ceniza: es el que la escupe. Sólo se dibuja en
    // casillas sin aldea ni oasis — en este servidor (0|0) y (1|0) tienen la capital natar
    // y a Multihunter, y taparlas dejaría dos aldeas invisibles e inalcanzables desde el
    // mapa. Se pierden dos casillas del dibujo y se ganan dos aldeas clicables.
    $volcanoTile = (!$hasVillage && (int)$maparray[$index]['oasistype'] === 0)
        ? greyZoneVolcanoClass($maparray[$index]['x'], $maparray[$index]['y'])
        : '';
    if($volcanoTile !== '') {
        $image = $volcanoTile;
        $greyZoneTile = '';
    }
    elseif($greyZoneTile !== '' && preg_match('/^t[0-9]$/', $image)) {
        $image = greyZoneAshClass($maparray[$index]['x'], $maparray[$index]['y']);
        // Con el suelo de ceniza el tinte sobra: el oficial no tiñe nada, la zona SE VE
        // porque el terreno es distinto. El tinte queda sólo para los oasis y las aldeas
        // de adentro, que conservan su propio sprite y si no no se leerían como parte de
        // la región.
        $greyZoneTile = '';
    }
    echo "<a href=\"position_details.php?x=".$maparray[$index]['x']."&y=".$maparray[$index]['y']."\" style=\"cursor:default;\"><div class=\"tile tile-".$i."-row".$row1." ".$image."".$occupied.$greyZoneTile."\" title=\"".$targettitle."\">";
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
<?php /* Aca vivia un bloque que instanciaba Travian.Game.Map.LowRes.Container. Esa clase
   solo estaba definida en crypt-lowres.js, que no lo cargaba ninguna pagina, asi que el
   bloque lanzaba un TypeError en cada carga del mapa. El arrastre y el desplazamiento los
   resuelve el JS de mas abajo, que recarga karte.php con las coordenadas nuevas. */ ?>
<style type="text/css">
/* La zona gris tiene que verse de un vistazo: un tooltip obliga a pasar el mouse por la
   casilla exacta y en la práctica es invisible. El tinte va como capa encima del sprite,
   con pointer-events:none para no robarle el clic ni el tooltip a la casilla. */
.tile.greyzone{position:relative;}
/* El suelo de ceniza del T4 oficial. La ruta es absoluta porque la spritesheet vive en un
   gpack distinto del que el jugador tenga activo. */
<?php echo greyZoneAshCss(); ?>
<?php echo mapDiplomacyCss(); ?>
<?php echo greyZoneVolcanoCss(); ?>
.tile.greyzone:after{content:'';position:absolute;left:0;top:0;right:0;bottom:0;
  background:rgba(74,76,96,.30);box-shadow:inset 0 0 0 1px rgba(74,76,96,.55);
  pointer-events:none;}

#mapContainer.lowRes #mapData{cursor:grab;cursor:-webkit-grab;touch-action:none;user-select:none;-webkit-user-select:none;}
#mapContainer.lowRes.dragPanning #mapData{cursor:grabbing;cursor:-webkit-grabbing;}
#mapContainer.lowRes #mapData a,#mapContainer.lowRes #mapData img{-webkit-user-drag:none;user-select:none;-webkit-user-select:none;}
body.map .dialog .dialog-contents .cancel{box-sizing:border-box;width:22px;height:22px;right:-10px;top:-10px;z-index:30;border:1px solid #777;border-radius:50%;background:#fff!important;color:#333;text-align:center;line-height:18px;}
body.map .dialog .dialog-contents .cancel:before{content:'\00d7';font-family:Arial,sans-serif;font-size:22px;font-weight:normal;}
body.map .dialog .dialog-contents .cancel:hover{background:#eee!important;color:#000;}
.mapElephantFinderButton{margin:8px 0 12px 0;}
.mapElephantFinderButton a{display:inline-block;padding:6px 10px;background:#6b8e23;color:#fff;text-decoration:none;border-radius:4px;font-size:12px;font-weight:bold;border:1px solid #4d6619;box-shadow:0 1px 2px rgba(0,0,0,.2);}
.mapElephantFinderButton a:hover{background:#7ba428;}
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
.dialog #tileDetails.oasis-1 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w2-rtl.jpg')!important;}
.dialog #tileDetails.oasis-2 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w3-rtl.jpg')!important;}
.dialog #tileDetails.oasis-3 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w4-rtl.jpg')!important;}
.dialog #tileDetails.oasis-4 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w6-rtl.jpg')!important;}
.dialog #tileDetails.oasis-5 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w8-rtl.jpg')!important;}
.dialog #tileDetails.oasis-6 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w7-rtl.jpg')!important;}
.dialog #tileDetails.oasis-7 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w10-rtl.jpg')!important;}
.dialog #tileDetails.oasis-8 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w12-rtl.jpg')!important;}
.dialog #tileDetails.oasis-9 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w11-rtl.jpg')!important;}
.dialog #tileDetails.oasis-10 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w10-rtl.jpg')!important;}
.dialog #tileDetails.oasis-11 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w14-rtl.jpg')!important;}
.dialog #tileDetails.oasis-12 .detailImage{background-image:url('/gpack/travian_Travian_4.0_41/img/g/detail.popup/oasis/w15-rtl.jpg')!important;}
</style>
<script type="text/javascript">
/* Click-and-drag panning plus in-place tile details for the small map. */
(function(){
	var TILE=60, THRESHOLD=6, WORLD=<?php echo (int)WORLD_MAX; ?>, PERIOD=2*WORLD+1;
	var curX=<?php echo (int)$x; ?>, curY=<?php echo (int)$y; ?>;
	var dialogOpen=false;
	function bindMapLinks(content){
		$(content).getElements('a[href^="karte.php"]').addEvent('click',function(event){
			event.stop();
			var url=new URI(this.href);
			window.location.href='karte.php?x='+parseInt(url.getData('x'),10)+'&y='+parseInt(url.getData('y'),10);
		});
	}
	function openTileDetails(event,link){
		if(event){
			event.preventDefault();
			event.stopPropagation();
		}
		if(dialogOpen) return false;
		var url=new URI(link.href);
		var x=parseInt(url.getData('x'),10), y=parseInt(url.getData('y'),10);
		if(isNaN(x)||isNaN(y)) return false;
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
	}
	function ready(fn){ if(document.readyState!='loading'){fn();} else {document.addEventListener('DOMContentLoaded',fn);} }
	ready(function(){
		var container=document.getElementById('mapContainer');
		var data=document.getElementById('mapData');
		if(!container||!data) return;
		var dragging=false, moved=false, sx=0, sy=0, dx=0, dy=0;
		data.addEventListener('click',function(event){
			var link=event.target;
			while(link&&link!==data&&link.tagName!=='A'){ link=link.parentNode; }
			if(link&&link.tagName==='A'&&link.href.indexOf('position_details.php')!==-1){
				openTileDetails(event,link);
			}
		},true);
		<?php if(isset($_GET['popup']) && $_GET['popup'] === '1' && isset($_GET['d']) && isset($_GET['c']) && $generator->getMapCheck($_GET['d']) == $_GET['c']) { ?>
		/* Los enlaces de informes pueden pedir el mismo detalle que abre un clic en la
		   casilla, después de que el mapa ya quedó centrado en el destino firmado. */
		openTileDetails(null,{href:'position_details.php?x=<?php echo (int)$x; ?>&y=<?php echo (int)$y; ?>'});
		<?php } ?>
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
			if(!moved) return; /* a tap/click opens the tile dialog */
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
