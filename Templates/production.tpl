<?php
function productionBreakdownNumber($value) {
	$rounded = round((float)$value, 2);
	return number_format($rounded, floor($rounded) == $rounded ? 0 : 2, ',', '.');
}

function productionBreakdownTooltip($resource, $label, $details, $total) {
	$lines = array('<b>'.$label.' por hora</b>');
	$speed = $details['speed'];
	$lines[] = 'Campos de recursos: +'.productionBreakdownNumber($details['fields'] * $speed);
	if($resource === 'crop') {
		if($details['grainmill_level'] > 0) {
			$lines[] = 'Molino de cereal (nivel '.$details['grainmill_level'].', +'.$details['grainmill_percent'].'%): incluido';
		}
		if($details['bakery_level'] > 0) {
			$lines[] = 'Panadería (nivel '.$details['bakery_level'].', +'.$details['bakery_percent'].'%): incluido';
		}
		if($details['building_bonus'] != 0) {
			$lines[] = 'Bonos de edificios: +'.productionBreakdownNumber($details['building_bonus'] * $speed);
		}
	} elseif($details['building_level'] > 0) {
		$lines[] = $details['building'].' (nivel '.$details['building_level'].', +'.$details['building_percent'].'%): +'.productionBreakdownNumber($details['building_bonus'] * $speed);
	}
	if($details['oasis_percent'] > 0) {
		$lines[] = 'Oasis (+'.$details['oasis_percent'].'%): +'.productionBreakdownNumber($details['oasis_bonus'] * $speed);
	}
	if($details['plus_percent'] > 0) {
		$lines[] = 'Bono Plus (+'.$details['plus_percent'].'%): +'.productionBreakdownNumber($details['plus_bonus'] * $speed);
	}
	$lines[] = 'Producción bruta: '.productionBreakdownNumber($details['gross']);
	if(!empty($details['hero'])) {
		$lines[] = 'Bono del héroe: +'.productionBreakdownNumber($details['hero']);
	}
	if($resource === 'crop') {
		$lines[] = 'Población: −'.productionBreakdownNumber($details['population']);
		$lines[] = 'Consumo de tropas: −'.productionBreakdownNumber($details['upkeep']);
		if($details['artefact_saving'] > 0) {
			$lines[] = 'Artefacto (consumo ahorrado): +'.productionBreakdownNumber($details['artefact_saving']);
		}
	}
	$lines[] = '<b>Total actual: '.productionBreakdownNumber($total).'</b>';
	return implode('<br>', $lines);
}

$productionResources = array(
	'wood' => array('class'=>'r1', 'label'=>WOOD, 'bonus'=>$session->bonus1),
	'clay' => array('class'=>'r2', 'label'=>CLAY, 'bonus'=>$session->bonus2),
	'iron' => array('class'=>'r3', 'label'=>IRON, 'bonus'=>$session->bonus3),
	'crop' => array('class'=>'r4', 'label'=>CROP, 'bonus'=>$session->bonus4)
);
?>
<div class="boxes villageList production"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
<table id="production" cellpadding="1" cellspacing="1" style="width:100%;">
	<thead>
		<tr>
			<th colspan="4"><?php echo PROD_HEADER; ?> </th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($productionResources as $resource => $resourceInfo) {
			$total = $village->getProd($resource);
			$details = $village->getProductionBreakdown($resource);
			$tooltip = productionBreakdownTooltip($resource, $resourceInfo['label'], $details, $total);
		?>
		<tr>
			<td class="ico"><img class="<?php echo $resourceInfo['class']; ?>" src="img/x.gif" alt="<?php echo $resourceInfo['label']; ?>" title="<?php echo $resourceInfo['label']; ?>" /></td>
			<td class="res"><?php echo $resourceInfo['label']; ?>:</td>
			<td class="num tooltip" title="<?php echo htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $total; ?></td>
			<td class="per" style="text-align:right;width:32px;padding-left:4px;">
				<?php if($resourceInfo['bonus'] == 1){ echo '<span class="bonus" style="color:#3a3;font-size:10px;" title="'.$resourceInfo['label'].' +25%">+25%</span>'; } ?>
			</td>
		</tr>
		<?php } ?>
			</tbody>
</table>
	</div>
				</div>
