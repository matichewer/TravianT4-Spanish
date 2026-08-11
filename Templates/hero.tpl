<?php 
$hero = $database->getHeroData($session->uid);
$herodetail = $database->HeroFace($session->uid);
$tribe = $session->tribe;
$hero_t = $GLOBALS["hero_t".$tribe];
$plevel = $hero['level']-1;
$heroWrefC = $generator->getMapCheck($hero['wref']);
$attributeLimit = heroAttributeLimit();
$powerPoints = max(0,min($attributeLimit,(int)$hero['power']));
$offBonusPoints = max(0,min($attributeLimit,(int)$hero['offBonus']));
$defBonusPoints = max(0,min($attributeLimit,(int)$hero['defBonus']));
$productPoints = max(0,min($attributeLimit,(int)$hero['product']));
$heroStrength = heroFightingStrength($hero,$tribe);
$itemPower = max(0,(int)$hero['itempower']);
$heroStrengthWithoutItems = $heroStrength-$itemPower;
$offBonusPercent = heroArmyBonusPercent($offBonusPoints);
$defBonusPercent = heroArmyBonusPercent($defBonusPoints);
$resourceRates = heroResourceRates($hero,SPEED);
$allResourceRate = 3*SPEED*$productPoints;
$focusedResourceRate = 10*SPEED*$productPoints;
$canSpendPoint = (int)$hero['points']>0;
$heroStrengthPerPoint = $tribe===1 ? 100 : 80;
$powerPointStyle = $canSpendPoint && $powerPoints<$attributeLimit ? "" : " hidden";
$offBonusPointStyle = $canSpendPoint && $offBonusPoints<$attributeLimit ? "" : " hidden";
$defBonusPointStyle = $canSpendPoint && $defBonusPoints<$attributeLimit ? "" : " hidden";
$productPointStyle = $canSpendPoint && $productPoints<$attributeLimit ? "" : " hidden";
$selectedResourceRate = $resourceRates['wood'];
if(!empty($hero['r2'])){ $selectedResourceRate = $resourceRates['clay']; }
elseif(!empty($hero['r3'])){ $selectedResourceRate = $resourceRates['iron']; }
elseif(!empty($hero['r4'])){ $selectedResourceRate = $resourceRates['crop']; }
$heroHomeVillageId = heroHomeVillage($hero);
$heroHomeVillageData = $heroHomeVillageId > 0 ? $database->getVillage($heroHomeVillageId) : false;
$heroHomeVillageIsValid = is_array($heroHomeVillageData) && (int)$heroHomeVillageData['owner']===(int)$session->uid;
$heroHomeVillageName = $heroHomeVillageIsValid ? $heroHomeVillageData['name'] : '';
$equippedHorse = heroEquippedItem($database,(int)$session->uid,6);
$horseSpeedBonus = is_array($equippedHorse) ? getHeroHorseSpeedBonus((int)$equippedHorse['type']) : 0;
$equippedShoes = heroEquippedItem($database,(int)$session->uid,5);
$shoesBonuses = is_array($equippedShoes)
	? getHeroShoesBonuses((int)$equippedShoes['type'])
	: array('autoregen'=>0,'armyspeed'=>0,'speed'=>0);
$spurSpeedBonus = $shoesBonuses['speed'];
// El bono de las botas de mercenario no entra en la suma de casillas por hora: solo
// acorta el tramo de viaje que pasa el umbral, así que se muestra aparte.
$bootsArmySpeedBonus = $shoesBonuses['armyspeed'];
$bootsDistanceThreshold = heroBootsDistanceThreshold();
$heroBaseSpeed = max(7,(int)$hero['speed']-$horseSpeedBonus-$spurSpeedBonus);
$heroSpeedMultiplier = max(1,(int)INCREASE_SPEED);
$heroDisplayedSpeed = (int)$hero['speed']*$heroSpeedMultiplier;
$maximumHeroLevel = count($hero_levels)-1;
$displayHeroLevel = max(0,min($maximumHeroLevel,(int)$hero['level']));
if($displayHeroLevel>=$maximumHeroLevel){
	$experienceTitle = "Tu héroe ha alcanzado el nivel máximo";
	$experienceProgress = 100;
}else{
	$experienceTitle = "Tu héroe necesita ".($hero_levels[$displayHeroLevel+1]-$hero['experience'])." puntos de experiencia más para alcanzar el nivel ".($displayHeroLevel+1);
	$experienceProgress = round(100*(($hero['experience']-$hero_levels[$displayHeroLevel])/($hero_levels[$displayHeroLevel+1]-$hero_levels[$displayHeroLevel])),1);
}
ob_start();
?>
<div id="attributes"><form id="heroAttributeForm" method="post" action="hero_inventory.php" data-available="<?php echo (int)$hero['points']; ?>" data-limit="<?php echo $attributeLimit; ?>" data-strength-per-point="<?php echo $heroStrengthPerPoint; ?>" data-speed="<?php echo (float)SPEED; ?>">
	<input type="hidden" name="a" value="allocateHeroAttributes">
	<input type="hidden" name="c" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
	<input type="hidden" name="power" value="0">
	<input type="hidden" name="offBonus" value="0">
	<input type="hidden" name="defBonus" value="0">
	<input type="hidden" name="product" value="0">
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
    	<div class="attribute headline">
			<div class="attributesHeadline">Atributos</div>
			<div class="pointsHeadline">Puntos</div>
			<div class="clear"></div>
		</div>
			<div class="clear"></div>
	  <div class="attribute power" data-attribute="power" data-points="<?php echo $powerPoints; ?>">
				<?php if($hero['itempower']==0){ ?>
				<div class="element attribName tooltip" title="La fuerza de lucha se combina con el ataque y la defensa de tu héroe. Cuanto más alta sea, mejor te irá en las batallas.<br><font color='#5dcbfb'>Fuerza de lucha: <?php echo $heroStrengthWithoutItems; ?> del héroe</font>">Fuerza de lucha</div>
				<?php }else{ ?>
				<div class="element attribName tooltip" title="La fuerza de lucha se combina con el ataque y la defensa de tu héroe. Cuanto más alta sea, mejor te irá en las batallas.<br><font color='#5dcbfb'>Fuerza de lucha: <?php echo $heroStrengthWithoutItems; ?> del héroe + <?php echo $itemPower; ?> de bonificación por objetos</font>">Fuerza de lucha</div>
				<?php } ?>
				<div class="element current power"><?php echo $heroStrength; ?></div>
				<div class="element progress">
					<div class="bar-bg">
						<div class="bar" style="width:<?php echo $powerPoints; ?>%;"></div>
					</div>
				</div>
				<div class="element add">
	        <a class="setPoint<?php echo $powerPointStyle; ?>" href="#" role="button" data-add-attribute="power"></a>
				</div>
				<div class="element points"><?php echo $powerPoints; ?></div>
			</div>

	  <div class="clear"></div>
	  <div class="attribute offBonus" data-attribute="offBonus" data-points="<?php echo $offBonusPoints; ?>">
				<div class="element attribName tooltip" title="La bonificación de ataque otorga un bono al atacar.<br><font color='#5dcbfb'>Bonificación de ataque <?php echo $offBonusPercent; ?>%</font>">Bonificación de ataque</div>
				<div class="element current power"><span class="value"><?php echo $offBonusPercent; ?></span>%</div>
				<div class="element progress">
					<div class="bar-bg">
						<div class="bar" style="width:<?php echo $offBonusPoints; ?>%;"></div>
					</div>
				</div>
				<div class="element add">
	            <a class="setPoint<?php echo $offBonusPointStyle; ?>" href="#" role="button" data-add-attribute="offBonus"></a>
				</div>
				<div class="element points"><?php echo $offBonusPoints; ?></div>
			</div>

		<div class="clear"></div>

	  <div class="attribute defBonus" data-attribute="defBonus" data-points="<?php echo $defBonusPoints; ?>">
				<div class="element attribName tooltip" title="La bonificación de defensa otorga un bono extra al ser atacado.<br><font color='#5dcbfb'>Bonificación de defensa: <?php echo $defBonusPercent; ?>%</font>">Bonificación de defensa</div>
				<div class="element current power"><span class="value"><?php echo $defBonusPercent; ?></span>%</div>
				<div class="element progress">
					<div class="bar-bg">
						<div class="bar" style="width:<?php echo $defBonusPoints; ?>%;"></div>
					</div>
				</div>
				<div class="element add">
	            <a class="setPoint<?php echo $defBonusPointStyle; ?>" href="#" role="button" data-add-attribute="defBonus"></a>
				</div>
				<div class="element points"><?php echo $defBonusPoints; ?></div>
			</div>

		<div class="clear"></div>

	  <div class="attribute productionPoints" data-attribute="product" data-points="<?php echo $productPoints; ?>">
				<div class="element attribName tooltip" title="El héroe recolecta recursos de forma continua. Cuantos más puntos asignes, mayor será la producción.<br><font color='#5dcbfb'>Aporte directo actual: +<?php echo $selectedResourceRate; ?>/h</font>">Recursos</div>
				<div class="element current power"><?php echo $productPoints; ?></div>
				<div class="element progress">
					<div class="bar-bg">
						<div class="bar" style="width:<?php echo $productPoints; ?>%;"></div>

					</div>
				</div>
				<div class="element add">
	             <a class="setPoint<?php echo $productPointStyle; ?>" href="#" role="button" data-add-attribute="product"></a>
				</div>
				<div class="element points"><?php echo $productPoints; ?></div>
		</div>

		<div class="clear"></div>
		<div class="attributeAllocationFooter">
			<div class="availableAttributePoints<?php echo (int)$hero['points']>0 ? ' hasPoints' : ''; ?>">Puntos disponibles para asignar: <strong><?php echo (int)$hero['points']; ?></strong></div>
			<div class="attributeAllocationActions">
				<button type="submit" class="heroAttributeApply disabled" disabled="disabled"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Aplicar</div></div></button>
				<button type="button" class="heroAttributeCancel disabled" disabled="disabled"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Cancelar</div></div></button>
			</div>
		</div>
		<div class="clear"></div>
		</div>
	</div>
	</form>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
    <div class="attribute res" id="setResource">
		<div class="changeResourcesHeadline"><b>Recursos</b></div>
		<p class="resourceProductionHelp">Como tienes <span class="productPointsValue"><?php echo $productPoints; ?></span> puntos en Recursos, el héroe produce <span class="allResourceRate"><?php echo $allResourceRate; ?></span> de cada recurso o <span class="focusedResourceRate"><?php echo $focusedResourceRate; ?></span> de un recurso específico. Este extra de producción se otorga a la aldea natal del héroe. Puedes cambiar la aldea natal del héroe enviándolo entre tus aldeas.</p>
		<div class="clear"></div>
		<div class="resource">
		  <input type="radio" onclick="window.location.href = '?product=r0';" name="resource" value="0" id="resourceHero0" <?php if($hero['r0']!=0){ echo $checked="checked"; } ?>>
			<label for="resourceHero0">
					<img title="Todos los recursos" class="r0" src="img/x.gif">
	                <span class="current">+<span class="allResourceRate"><?php echo $allResourceRate; ?></span>/h</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" onclick="window.location.href = '?product=r1';" name="resource" value="1" id="resourceHero1" <?php if($hero['r1']!=0){ echo $checked="checked"; } ?> <?php echo $form->getRadio('resource',1); ?>>
			<label for="resourceHero1">
					<img title="Madera" class="r1" src="img/x.gif">
	                <span class="current">+<span class="focusedResourceRate"><?php echo $focusedResourceRate; ?></span>/h</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" onclick="window.location.href = '?product=r2';" name="resource" value="2" id="resourceHero2" <?php if($hero['r2']!=0){ echo $checked="checked"; } ?> <?php echo $form->getRadio('resource',2); ?>>
			<label for="resourceHero2">
					<img title="Barro" class="r2" src="img/x.gif">
	                <span class="current">+<span class="focusedResourceRate"><?php echo $focusedResourceRate; ?></span>/h</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" onclick="window.location.href = '?product=r3';" name="resource" value="3" id="resourceHero3" <?php if($hero['r3']!=0){ echo $checked="checked"; } ?> <?php echo $form->getRadio('resource',3); ?>>
			<label for="resourceHero3">
					<img title="Hierro" class="r3" src="img/x.gif">
	                <span class="current">+<span class="focusedResourceRate"><?php echo $focusedResourceRate; ?></span>/h</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" onclick="window.location.href = '?product=r4';" name="resource" value="4" id="resourceHero4" <?php if($hero['r4']!=0){ echo $checked="checked"; } ?> <?php echo $form->getRadio('resource',4); ?>>
			<label for="resourceHero4">
					<img title="Cereal" class="r4" src="img/x.gif">
	                <span class="current">+<span class="focusedResourceRate"><?php echo $focusedResourceRate; ?></span>/h</span>
			</label>
		</div>
			</div>
	<div class="clear"></div>
		</div>
  </div>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">

<div class="attribute health tooltip" title="Regeneración de tu héroe: <?php echo heroRegenerationPerDay($hero['autoregen']); ?>% por día">
<?php if($hero['dead']==0){ ?>
			<div class="element attribName">Salud</div>
			<div class="element current power"><span class="value"><?php echo round($hero['health']); ?></span>%</div>
			<div class="element progress">
				<div class="bar-bg">
                <?php
                if($hero['health']<=10){
                	$color = '#F00';
                }elseif($hero['health']<=25){
                	$color = '#F0B300';
                }elseif($hero['health']<=50){
                	$color = '#FFFF00';
                }elseif($hero['health']<=90){
                	$color = '#99C01A';
                }else{
                	$color = '#006900';
                }
                ?>
                
					<div class="bar" style="width:<?php echo $hero['health']; ?>%;background-color:<?php echo $color; ?>"></div>
				</div>
			</div>
<?php }else{ ?>
<div class="attributesHeadline reviveHeadline">El héroe revivirá en <b><?php echo $heroHomeVillageName !== '' ? htmlspecialchars(stripslashes($heroHomeVillageName),ENT_QUOTES,'UTF-8') : 'su aldea natal'; ?></b></div>
<div class="clear"></div>
    <?php
    $vRes = ($village->awood+$village->aclay+$village->airon+$village->acrop);
    $hRes = ($hero_t[$hero['level']]['wood']+$hero_t[$hero['level']]['clay']+$hero_t[$hero['level']]['iron']+$hero_t[$hero['level']]['crop']);
$checkT = $database->getHeroTrain($hero['wref']);

if(!$checkT){
	if(!$heroHomeVillageIsValid || $heroHomeVillageData['wood'] < $hero_t[$hero['level']]['wood'] || $heroHomeVillageData['clay'] < $hero_t[$hero['level']]['clay'] || $heroHomeVillageData['iron'] < $hero_t[$hero['level']]['iron'] || $heroHomeVillageData['crop'] < $hero_t[$hero['level']]['crop']){
    	echo '<span class="none">No hay suficientes recursos para revivir al héroe</span>';
    }else{
        echo "<span class=\"regeneratebtn\"><button type=\"submit\" value=\"Revive\" onclick=\"window.location.href = 'hero_inventory.php?revive=1'; return false;\" name=\"save\" id=\"save\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Revivir</div></div></button></span>";
    }
}else{
	echo "El héroe estará listo en <span id='timer1'>".$generator->getTimeFormat($checkT['eachtime']-time())."</span></br>";
}
if(!$checkT){
    ?>
        <div class="regenerateCosts">
        	<div class="showCosts">
            	<span class="resources r1 little_res" title="Madera">
                	<img class="r1" src="img/x.gif" title="Madera" />
					<?php echo $hero_t[$hero['level']]['wood']; ?>
                </span>
                <span class="resources r2 little_res" title="Barro">
                	<img class="r2" src="img/x.gif" title="Barro" />
					<?php echo $hero_t[$hero['level']]['clay']; ?>
                </span>
                <span class="resources r3 little_res" title="Hierro">
                	<img class="r3" src="img/x.gif" title="Hierro" />
					<?php echo $hero_t[$hero['level']]['iron']; ?>
                </span>
                <span class="resources r4 little_res" title="Cereal">
                	<img class="r4" src="img/x.gif" title="Cereal" />
					<?php echo $hero_t[$hero['level']]['crop']; ?>
                </span>
                <span class="resources r5" title="Consumo de cereal">
                	<img class="r5" src="img/x.gif" title="Consumo de cereal" />
                    6
                </span>
                <div class="clear"></div>
                <span class="clock">
                	<img class="clock" src="img/x.gif" title="Duración">
                    <?php echo $generator->getTimeFormat(($hero_t[$hero['level']]['time']/SPEED*1.5)); ?>
                </span>
                <button type="button" value="" class="icon" onclick="window.location.href = 'build.php?gid=17&amp;t=3&amp;r1=<?php echo $hero_t[$hero['level']]['wood']; ?>&amp;r2=<?php echo $hero_t[$hero['level']]['clay']; ?>&amp;r3=<?php echo $hero_t[$hero['level']]['iron']; ?>&amp;r4=<?php echo $hero_t[$hero['level']]['crop']; ?>'; return false;"><img src="img/x.gif" class="npc" alt="npc"></button>
                <div class="clear"></div>
            </div>
        </div>
        <div class="clear"></div>
<?php }} ?>
		</div>

		<div class="clear"></div>
			</div>
  </div>
  
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
    
<div class="attribute experience tooltip" title="<?php echo $experienceTitle; ?>">
			<div class="element attribName">Experiencia</div>
			<div class="element current power"><?php echo $hero['experience']; ?></div>
			<div class="element progress">
				<div class="bar-bg">
						<div class="bar" style="width:<?php echo max(0,min(100,$experienceProgress)); ?>%;"></div>
				</div>
			</div>
            
			<div class="element add"></div>
			<div class="clear"></div>
		</div>

<div class="attribute level tooltip" title="Cuanto más alto el nivel del héroe, más puntos obtienes.<br><font color='#5dcbfb'>Nivel del héroe: <?php echo $hero['level']; ?></font>">
			<div class="element attribName">Nivel del héroe</div>
			<div class="element current power"><?php echo $hero['level']; ?></div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo min(100,$hero['level']); ?>%"></div>
				</div>
			</div>
			<div class="clear"></div>
</div>
<div class="attribute speed tooltip" title="La velocidad de tu héroe determina cuántas casillas recorre por hora.<br><font color='#5dcbfb'>Velocidad base: <?php echo $heroBaseSpeed; ?> casillas por hora<?php if($horseSpeedBonus>0){ ?><br>Caballo: +<?php echo $horseSpeedBonus; ?> casillas por hora<?php } ?><?php if($spurSpeedBonus>0){ ?><br>Espuelas: +<?php echo $spurSpeedBonus; ?> casillas por hora<?php } ?><br>Velocidad del servidor: ×<?php echo $heroSpeedMultiplier; ?><br>Total: <?php echo $heroDisplayedSpeed; ?> casillas por hora<?php if($bootsArmySpeedBonus>0){ ?><br>Botas: +<?php echo $bootsArmySpeedBonus; ?>% al ejército más allá de las <?php echo $bootsDistanceThreshold; ?> casillas<?php } ?></font>">
	<div class="element attribName">Velocidad</div>
    <div class="element power">
		<span class="currect"><?php echo $heroDisplayedSpeed; ?></span> Casillas por hora
    </div>
    <div class="clear"></div>
</div>
<div class="attribute homeVillage">
	<div class="element attribName">Aldea natal</div>
	<div class="element power"><?php echo $heroHomeVillageName !== '' ? htmlspecialchars(stripslashes($heroHomeVillageName),ENT_QUOTES,'UTF-8') : 'Sin aldea natal'; ?></div>
	<div class="clear"></div>
</div>

		</div>
  </div></div>

<script type="text/javascript">
window.addEvent('domready',function(){
	var form = document.getElementById('heroAttributeForm');
	if(!form){ return; }
	var attributes = ['power','offBonus','defBonus','product'];
	var available = parseInt(form.getAttribute('data-available'),10) || 0;
	var limit = parseInt(form.getAttribute('data-limit'),10) || 100;
	var strengthPerPoint = parseInt(form.getAttribute('data-strength-per-point'),10) || 0;
	var speed = parseFloat(form.getAttribute('data-speed')) || 0;
	var baseStrength = <?php echo (int)$heroStrength; ?>;
	var rows = {};
	var basePoints = {};

	function setHidden(element,hidden){
		if(!element){ return; }
		var classes = element.className.replace(/\s*hidden\b/g,'');
		element.className = hidden ? classes+' hidden' : classes;
	}

	function setText(selector,value){
		var elements = document.querySelectorAll(selector);
		for(var i=0;i<elements.length;i++){
			elements[i].textContent = value;
		}
	}

	function setButtonEnabled(button,enabled){
		button.disabled = !enabled;
		button.className = button.className.replace(/\s*disabled\b/g,'')+(enabled ? '' : ' disabled');
	}

	for(var i=0;i<attributes.length;i++){
		var name = attributes[i];
		rows[name] = form.querySelector('[data-attribute="'+name+'"]');
		basePoints[name] = parseInt(rows[name].getAttribute('data-points'),10) || 0;
	}

	function render(){
		var spent = 0;
		for(var i=0;i<attributes.length;i++){
			spent += parseInt(form.elements[attributes[i]].value,10) || 0;
		}
		var remaining = available-spent;
		var availableElement = form.querySelector('.availableAttributePoints strong');
		if(availableElement){ availableElement.textContent = remaining; }

		for(var j=0;j<attributes.length;j++){
			var attribute = attributes[j];
			var points = basePoints[attribute]+(parseInt(form.elements[attribute].value,10) || 0);
			var row = rows[attribute];
			row.querySelector('.points').textContent = points;
			row.querySelector('.bar').style.width = points+'%';
			setHidden(row.querySelector('.setPoint'),remaining<1 || points>=limit);
			if(attribute==='power'){
				row.querySelector('.current.power').textContent = baseStrength+(points-basePoints.power)*strengthPerPoint;
			}else if(attribute==='offBonus' || attribute==='defBonus'){
				row.querySelector('.current.power .value').textContent = points/5;
			}else{
				row.querySelector('.current.power').textContent = points;
				setText('.productPointsValue',points);
				setText('.allResourceRate',3*speed*points);
				setText('.focusedResourceRate',10*speed*points);
			}
		}
		var hasPendingPoints = spent>0;
		var balance = form.querySelector('.availableAttributePoints');
		balance.className = balance.className.replace(/\s*hasPoints\b/g,'')+(remaining>0 ? ' hasPoints' : '');
		setButtonEnabled(form.querySelector('.heroAttributeApply'),hasPendingPoints);
		setButtonEnabled(form.querySelector('.heroAttributeCancel'),hasPendingPoints);
	}

	var addButtons = form.querySelectorAll('[data-add-attribute]');
	for(var buttonIndex=0;buttonIndex<addButtons.length;buttonIndex++){
		addButtons[buttonIndex].addEventListener('click',function(event){
			event.preventDefault();
			var attribute = this.getAttribute('data-add-attribute');
			var input = form.elements[attribute];
			var spent = 0;
			for(var i=0;i<attributes.length;i++){
				spent += parseInt(form.elements[attributes[i]].value,10) || 0;
			}
			if(spent<available && basePoints[attribute]+parseInt(input.value,10)<limit){
				input.value = (parseInt(input.value,10) || 0)+1;
				render();
			}
		});
	}

	form.querySelector('.heroAttributeCancel').addEventListener('click',function(){
		for(var i=0;i<attributes.length;i++){
			form.elements[attributes[i]].value = 0;
		}
		render();
	});
	render();
});
</script>

<?php
$heroid = $hero['heroid'];
// El botón se muestra con los recursos justos (>=), así que acá se compara igual: con
// `>` estricto el clic no hacía nada y tampoco avisaba. Se exige además que el héroe
// esté muerto y que no haya otro rescate ya encargado, porque entrar a mano por
// `?revive=1` cobraba los recursos de nuevo.
if(isset($_GET['revive']) && $_GET['revive'] == 1 && (int)$hero['dead'] !== 0 && $heroHomeVillageIsValid && !$database->getHeroTrain($hero['wref']) && $database->deductResourcesIfAvailable($heroHomeVillageId,$hero_t[$hero['level']]['wood'],$hero_t[$hero['level']]['clay'],$hero_t[$hero['level']]['iron'],$hero_t[$hero['level']]['crop'])){
	if($tribe==1){
		$each = (time() + ($hero_t1[$hero['level']]['time']/SPEED*1.5));
	}elseif($tribe==2){
		$each = (time() + ($hero_t2[$hero['level']]['time']/SPEED*1.5));
	}elseif($tribe==3){
		$each = (time() + ($hero_t3[$hero['level']]['time']/SPEED*1.5));
	}
	$database->trainHero($heroHomeVillageId, $each, 0);
    $database->modifyHero2('wref', $heroHomeVillageId, $session->uid, 0);
    header("Location: hero_inventory.php");
}
if(isset($_GET['product'])){
	if(preg_match('/^r([0-4])$/',$_GET['product'],$resourceMatch)){
		$database->setHeroResourceMode($session->uid,(int)$resourceMatch[1]);
	}
	header("Location: hero_inventory.php");
	}elseif($hero['r0'] == 0 && $hero['r1'] == 0 && $hero['r2'] == 0 && $hero['r3'] == 0 && $hero['r4'] == 0){
$database->setHeroResourceMode($session->uid,0);
header("Location: hero_inventory.php");
}
?>
