<?php
$totalproduction = $village->allcrop; // all crops + bakery + grain mill
$heroData = $database->getHeroData($session->uid);
$heroProduction = heroVillageResourceBonus($heroData,$village->wid,SPEED);
$totalproduction += $heroProduction['crop'];

$formatStorageFillTime = function ($capacity, $currentAmount, $production) {
	if ($currentAmount >= $capacity) {
		return 'Ya está lleno';
	}

	if ($production <= 0) {
		return 'No se llenará con la producción actual';
	}

	$minutes = (int) round(($capacity - $currentAmount) * 60 / $production);
	if ($minutes < 1) {
		return 'Lleno en menos de 1 min';
	}

	$days = intdiv($minutes, 1440);
	$hours = intdiv($minutes % 1440, 60);
	$remainingMinutes = $minutes % 60;
	$parts = array();

	if ($days > 0) {
		$parts[] = $days . ($days === 1 ? ' día' : ' días');
	}
	if ($hours > 0) {
		$parts[] = $hours . ' h';
	}
	if ($remainingMinutes > 0) {
		$parts[] = $remainingMinutes . ' min';
	}

	return 'Lleno en ' . implode(' ', $parts);
};

$woodFillTime = $formatStorageFillTime($village->maxstore, $village->awood, $village->getProd("wood"));
$clayFillTime = $formatStorageFillTime($village->maxstore, $village->aclay, $village->getProd("clay"));
$ironFillTime = $formatStorageFillTime($village->maxstore, $village->airon, $village->getProd("iron"));
$cropFillTime = $formatStorageFillTime($village->maxcrop, $village->acrop, $village->getProd("crop"));
$formatResourceAmount = function ($amount) {
	return number_format((int) round($amount), 0, ',', '.');
};
$resourceAmountDisplay = function ($amounts) use ($formatResourceAmount) {
	$current = (int) round($amounts[0]);
	$capacity = (int) round($amounts[1]);
	$twoLines = strlen((string) abs($current)) + strlen((string) abs($capacity)) > 12;
	return array(
		'class' => $twoLines ? ' resourceTwoLines' : '',
		'value' => $formatResourceAmount($current) . ($twoLines ? ' /<br>' : ' / ') . $formatResourceAmount($capacity),
	);
};
$resourceAmountPreview = isset($_GET['resource_preview']) && $_GET['resource_preview'] === 'digits';
$displayedResources = $resourceAmountPreview
	? array(
		'wood' => array(120000, 160000),
		'clay' => array(1234567, 8000000),
		'iron' => array(12345678, 80000000),
		'crop' => array(87654321, 90000000),
	)
	: array(
		'wood' => array($village->awood, $village->maxstore),
		'clay' => array($village->aclay, $village->maxstore),
		'iron' => array($village->airon, $village->maxstore),
		'crop' => array(max(0, $village->acrop), $village->maxcrop),
	);
$displayedResourceValues = array();
foreach ($displayedResources as $resourceName => $resourceAmounts) {
	$displayedResourceValues[$resourceName] = $resourceAmountDisplay($resourceAmounts);
}
?>
<ul id="res">
		<li class="r1<?php echo $displayedResourceValues['wood']['class']; ?>" title="<div style=color:#FFF><b><?php echo WOOD; ?></b></div><?php echo $woodFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo WOOD; ?>"/> 

			<span id="l1" class="value"><?php echo $displayedResourceValues['wood']['value']; ?></span>
        <div class="bar-bg">
	     	 <div id="lbar1" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	</div>
	    </p>
        </li> 
        
		<li class="r2<?php echo $displayedResourceValues['clay']['class']; ?>" title="<div style=color:#FFF><b><?php echo CLAY; ?></b></div><?php echo $clayFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo CLAY; ?>"/> 
			<span id="l2" class="value"><?php echo $displayedResourceValues['clay']['value']; ?></span>
          <div class="bar-bg">
	      <div id="lbar2" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	  </div>
		</p> 

        	</li> 
		<li class="r3<?php echo $displayedResourceValues['iron']['class']; ?>" title="<div style=color:#FFF><b><?php echo IRON; ?></b></div><?php echo $ironFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo IRON; ?>"/> 
			<span id="l3" class="value"><?php echo $displayedResourceValues['iron']['value']; ?></span>
          <div class="bar-bg">
	      <div id="lbar3" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	  </div> 
		</p> 

        	</li> 
		<li class="r4<?php echo $displayedResourceValues['crop']['class']; ?>" title="<div style=color:#FFF><b><?php echo CROP; ?></b></div><?php echo $cropFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo CROP; ?>"/> 
			<span id="l4" class="value"><?php echo $displayedResourceValues['crop']['value']; ?></span>
          <div class="bar-bg">
	      <div id="lbar4" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	  </div>
		</p> 

        	</li> 
		<li class="r5" title="<div style=color:#FFF><b><?php echo CROP_COM; ?></b></div><?php echo ($village->pop+$technology->getUpkeep($village->unitall,0))."/".$totalproduction.""; ?>"> 
		<p> 
        	<img src="img/x.gif" alt="<?php echo CROP_COM; ?>"/> 
			<span id="l5" class="value "><?php echo ($village->pop+$technology->getUpkeep($village->unitall,0))."/".$totalproduction.""; ?></span> 
		</p> 
			</li> 
	</ul>
<div class="clear"></div>

<script type="text/javascript"> 
	resources.production = {
'l1': <?php echo $village->getProd("wood"); ?>,'l2': <?php echo $village->getProd("clay"); ?>,'l3': <?php echo $village->getProd("iron"); ?>,'l4': <?php echo $village->getProd("crop"); ?>			};
</script>
