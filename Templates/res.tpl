<?php
$totalproduction = $village->allcrop; // all crops + bakery + grain mill
$heroData = $database->getHeroData($session->uid);
if($heroData['dead']==0 && $heroData['wref']==$village->wid){
$totalproduction += $heroData['r4']*10*SPEED*$heroData['product'];
$totalproduction += $heroData['r0']*3*SPEED*$heroData['product'];
}

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
?>
<ul id="res">
		<li class="r1" title="<div style=color:#FFF><b><?php echo WOOD; ?></b></div>Producción: <?php echo $village->getProd("wood"); ?>
				<br><?php echo $woodFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo WOOD; ?>"/> 

			<span id="l1" class="value "><?php echo round($village->awood)."/".$village->maxstore; ?></span> 
        <div class="bar-bg">
	     	 <div id="lbar1" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	</div>
	    </p>
        </li> 
        
		<li class="r2" title="<div style=color:#FFF><b><?php echo CLAY; ?></b></div>Producción: <?php echo $village->getProd("clay"); ?>
				<br><?php echo $clayFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo CLAY; ?>"/> 
			<span id="l2" class="value "><?php echo round($village->aclay)."/".$village->maxstore; ?></span> 
          <div class="bar-bg">
	      <div id="lbar2" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	  </div>
		</p> 

        	</li> 
		<li class="r3" title="<div style=color:#FFF><b><?php echo IRON; ?></b></div>Producción: <?php echo $village->getProd("iron"); ?>
				<br><?php echo $ironFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo IRON; ?>"/> 
			<span id="l3" class="value "><?php echo round($village->airon)."/".$village->maxstore; ?></span>
          <div class="bar-bg">
	      <div id="lbar3" class="bar" style="width: 0%; background-color: rgb(0, 105, 0); "></div>
      	  </div> 
		</p> 

        	</li> 
		<li class="r4" title="<div style=color:#FFF><b><?php echo CROP; ?></b></div>Producción: <?php echo $village->getProd("crop"); ?>
				<br><?php echo $cropFillTime; ?>">
		<p> 
        	<img src="img/x.gif" alt="<?php echo CROP; ?>"/> 
			<span id="l4" class="value "><?php if(($village->acrop)<0){echo "0";}else{echo round($village->acrop);}echo "/".$village->maxcrop; ?></span>
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
