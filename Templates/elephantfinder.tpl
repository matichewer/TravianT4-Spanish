<?php
$currentCoords = $database->getCoor($village->wid);

function getMapDistance($fromX, $fromY, $toX, $toY) {
	$period = 2 * WORLD_MAX + 1;
	$distanceX = min(abs($toX - $fromX), abs($period - abs($toX - $fromX)));
	$distanceY = min(abs($toY - $fromY), abs($period - abs($toY - $fromY)));
	return round(sqrt(pow($distanceX, 2) + pow($distanceY, 2)), 1);
}

$oases = array();
$query = mysql_query(
	"SELECT w.id,w.x,w.y,w.occupied,u.u31,u.u32,u.u33,u.u34,u.u35,u.u36,u.u37,u.u38,u.u39,u.u40
	 FROM " . TB_PREFIX . "wdata w
	 INNER JOIN " . TB_PREFIX . "units u ON u.vref = w.id
	 WHERE w.fieldtype = 0 AND w.oasistype > 0 AND u.u40 > 0"
);
while($row = mysql_fetch_assoc($query)) {
	$row['distance'] = getMapDistance((int)$currentCoords['x'], (int)$currentCoords['y'], (int)$row['x'], (int)$row['y']);
	$oases[] = $row;
}

usort($oases, function($a, $b) {
	if($a['distance'] == $b['distance']) {
		if((int)$a['x'] === (int)$b['x']) {
			return (int)$a['y'] <=> (int)$b['y'];
		}
		return (int)$a['x'] <=> (int)$b['x'];
	}
	return ($a['distance'] < $b['distance']) ? -1 : 1;
});

$animalNames = array(
	31 => U31,
	32 => U32,
	33 => U33,
	34 => U34,
	35 => U35,
	36 => U36,
	37 => U37,
	38 => U38,
	39 => U39,
	40 => U40
);
?>

<h1 class="titleInHeader">Buscador de elefantes</h1>

<div class="spacer"></div>
<table cellpadding="1" cellspacing="1" id="croplist" class="elephantFinderTable">
	<colgroup>
		<col class="colDist" />
		<col class="colCoords" />
		<col class="colAnimals" />
	</colgroup>
	<thead>
		<tr>
			<th>Distancia</th>
			<th>Coordenadas</th>
			<th>Animales</th>
		</tr>
	</thead>
	<tbody>
	<?php if(count($oases) === 0) { ?>
		<tr>
			<td colspan="3">No hay oasis con elefantes.</td>
		</tr>
	<?php } else { ?>
		<?php foreach($oases as $oasis) { ?>
		<tr>
			<td class="dist"><?php echo number_format((float)$oasis['distance'], 1, '.', ''); ?></td>
			<td class="coords">
				<a
					href="position_details.php?x=<?php echo (int)$oasis['x']; ?>&y=<?php echo (int)$oasis['y']; ?>"
					class="js-elephant-oasis-popup"
					data-x="<?php echo (int)$oasis['x']; ?>"
					data-y="<?php echo (int)$oasis['y']; ?>"
				>(<?php echo (int)$oasis['x']; ?>|<?php echo (int)$oasis['y']; ?>)</a>
				<?php if(!empty($oasis['occupied'])) { ?><span class="elephantFinderOccupied" title="Oasis conquistado por un jugador">(ocupado)</span><?php } ?>
			</td>
			<td class="typ">
				<?php
				for($i = 31; $i <= 40; $i++) {
					$count = (int)$oasis['u'.$i];
					if($count <= 0) {
						continue;
					}
					echo '<span class="elephantFinderAnimal">';
					echo '<span class="elephantFinderAnimalCount">'.$count.'</span>';
					echo '<img class="unit u'.$i.'" src="img/x.gif" alt="'.$animalNames[$i].'" title="'.$animalNames[$i].'" />';
					echo '</span>';
				}
				?>
			</td>
		</tr>
		<?php } ?>
	<?php } ?>
	</tbody>
</table>

<style type="text/css">
.elephantFinderTable{table-layout:fixed;width:100%;}
.elephantFinderTable col.colDist{width:90px;}
.elephantFinderTable col.colCoords{width:130px;}
.elephantFinderTable td.typ{white-space:normal;}
.elephantFinderAnimal{display:inline-flex;align-items:center;margin-right:10px;white-space:nowrap;}
.elephantFinderAnimalCount{margin-right:4px;font-weight:bold;}
.elephantFinderOccupied{margin-left:4px;font-size:10px;color:#8a1f11;}

.dialog.mapTileDetailsDialog .content{max-height:70vh;overflow-y:auto;overflow-x:hidden;}

body.cropfinder .dialog .dialog-contents .cancel{box-sizing:border-box;width:22px;height:22px;right:-10px;top:-10px;z-index:30;border:1px solid #777;border-radius:50%;background:#fff!important;color:#333;text-align:center;line-height:18px;}
body.cropfinder .dialog .dialog-contents .cancel:before{content:'\00d7';font-family:Arial,sans-serif;font-size:22px;font-weight:normal;}
body.cropfinder .dialog .dialog-contents .cancel:hover{background:#eee!important;color:#000;}
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
(function(){
	var dialogOpen = false;
	var openPopup = null;

	// Travian.Dialog.update() centers the (position:fixed) wrapper using the height of
	// document.body instead of the viewport. This page's table makes the body much taller
	// than the window, so the dialog gets pushed down and its lower part -- the last troop
	// rows, i.e. always the elephants -- ends up off-screen and unreachable. Re-center it
	// against the viewport ourselves.
	function fitToViewport(popup){
		if(!popup || typeof window.getSize !== 'function' || typeof document.id !== 'function') {
			return;
		}
		var wrapper = document.id(popup);
		if(!wrapper) {
			return;
		}
		var view = window.getSize();
		var size = wrapper.getSize();
		wrapper.setStyles({
			top: Math.max(5, Math.round((view.y - size.y) / 2)),
			left: Math.max(5, Math.round((view.x - size.x) / 2))
		});
	}

	if(typeof window.addEvent === 'function') {
		window.addEvent('resize', function(){
			if(dialogOpen) {
				fitToViewport(openPopup);
			}
		});
	}

	function bindMapLinks(content){
		if(typeof $ === 'undefined' || !content) {
			return;
		}
		$(content).getElements('a[href^="karte.php"]').addEvent('click', function(event){
			event.stop();
			var url = new URI(this.href);
			window.location.href = 'karte.php?x=' + parseInt(url.getData('x'), 10) + '&y=' + parseInt(url.getData('y'), 10);
		});
	}

	function openTileDetails(x, y) {
		if(dialogOpen || typeof Travian === 'undefined' || typeof Request === 'undefined') {
			return false;
		}
		dialogOpen = true;
		var popup = new Travian.Dialog({
			buttonOk:false,
			cssClass:'white mapTileDetailsDialog',
			title:'Detalles de la casilla (' + x + '|' + y + ')',
			onClose:function(){ dialogOpen = false; openPopup = null; }
		}).setContent('<div class="loading"></div>').show();
		openPopup = popup;
		fitToViewport(popup);

		var showError = function(){
			popup.setContent('<p>No se pudo cargar la información de esta casilla.</p>');
			fitToViewport(popup);
			return false;
		};

		new Request.HTML({
			url:'position_details.php?popup=1&x=' + encodeURIComponent(x) + '&y=' + encodeURIComponent(y),
			method:'get',
			evalScripts:false,
			onSuccess:function(tree, elements, responseHTML){
				popup.setContent(responseHTML);
				var popupTitle = $(popup.content).getElement('#tileDetailsPopupTitle');
				if(popupTitle){
					popup.setTitle(popupTitle.get('html'));
					popupTitle.destroy();
				}
				bindMapLinks(popup.content);
				fitToViewport(popup);
			},
			onFailure:showError,
			onException:showError
		}).send();
		return true;
	}

	var links = document.querySelectorAll('.js-elephant-oasis-popup');
	for(var i = 0; i < links.length; i++) {
		links[i].addEventListener('click', function(event){
			var x = parseInt(this.getAttribute('data-x'), 10);
			var y = parseInt(this.getAttribute('data-y'), 10);
			if(!isNaN(x) && !isNaN(y) && openTileDetails(x, y)) {
				event.preventDefault();
			}
		});
	}
})();
</script>
