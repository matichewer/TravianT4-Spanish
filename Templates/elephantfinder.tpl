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
	"SELECT w.id,w.x,w.y,u.u31,u.u32,u.u33,u.u34,u.u35,u.u36,u.u37,u.u38,u.u39,u.u40
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
<table cellpadding="1" cellspacing="1" id="croplist">
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
.elephantFinderAnimal{display:inline-flex;align-items:center;margin-right:10px;white-space:nowrap;}
.elephantFinderAnimalCount{margin-right:4px;font-weight:bold;}
</style>

<script type="text/javascript">
(function(){
	var dialogOpen = false;

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
			onClose:function(){ dialogOpen = false; }
		}).setContent('<div class="loading"></div>').show();

		var showError = function(){
			popup.setContent('<p>No se pudo cargar la información de esta casilla.</p>');
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
