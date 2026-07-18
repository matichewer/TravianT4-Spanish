
<div id="attributes">
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
    	<div class="attribute headline">
			<div class="attributesHeadline">Características</div>
			<div class="pointsHeadline">Puntos</div>
			<div class="clear"></div>
		</div>

		<div class="clear"></div>

  <div class="attribute power tooltip" title="Támadó erő||A hős támadási ereje a védelmi erejének és a támadó erejének kombinációja. Minnél több ez az erő annál kevesebb sérülést kap a kalandnál a hős.<br><font color='#5dcbfb'>Fuerza de ataque: <?php echo $hero_info['attack']; ?></font>">
			<div class="element attribName">Fuerza de ataque</div>
			<div class="element current power"><?php echo $hero_info['attack']; ?></div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo intval($hero_info['attack']/100)-1; ?>%;"></div>
				</div>
			</div>
			<div class="element add">
        <?php
        if($hero_info['points'] > 0){
            echo "<a class=\"setPoint\" href=\"hero_inventory.php?add=off\"></a>";
        }else {
            echo "<a class=\"setPoint hidden\" href=\"hero_inventory.php?add=off\"></a>";
        }
        ?>
			</div>
			<div class="element points"><?php echo intval($hero_info['attack']/150); ?></div>
		</div>

		<div class="clear"></div>
  <div class="attribute offBonus tooltip" title="<font color=white><b>Puntos de ataque</b></font><br>Los puntos de ataque aumentan las probabilidades de supervivencia del héroe<br><font color='#5dcbfb'>Bono de ataque: <?php echo ($hero_info['attackbonus']/200); ?>%</font>">
			<div class="element attribName">Bono de ataque</div>
			<div class="element current power"><span class="value"><?php echo ($hero_info['attackbonus']/200); ?></span>%</div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo intval($hero_info['attackbonus']/100); ?>%;"></div>
				</div>
			</div>
			<div class="element add">
            <?php
        if($hero_info['points'] > 0){
            echo "<a class=\"setPoint\" href=\"hero_inventory.php?add=obonus\"></a>";
        }else {
            echo "<a class=\"setPoint hidden\" href=\"hero_inventory.php?add=obonus\"></a>";
        }
        ?>
			</div>
			<div class="element points"><?php echo intval($hero_info['attackbonus']/100); ?></div>
		</div>

		<div class="clear"></div>

  <div class="attribute defBonus tooltip" title="<font color=white><b>Bonificación defensiva</b></font><br>Aumenta la fuerza defensiva de todas las tropas que acompañan al héroe.<br><font color='#5dcbfb'>Bonificación defensiva: <?php echo ($hero_info['defencebonus']/200); ?>%</font>">
			<div class="element attribName">Bonificación defensiva</div>
			<div class="element current power"><span class="value"><?php echo ($hero_info['defencebonus']/200); ?></span>%</div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo intval($hero_info['defencebonus']/100); ?>%;"></div>
				</div>
			</div>
			<div class="element add">
            <?php
        if($hero_info['points'] > 0){
            echo "<a class=\"setPoint\" href=\"hero_inventory.php?add=dbonus\"></a>";
        }else {
            echo "<a class=\"setPoint hidden\" href=\"hero_inventory.php?add=dbonus\"></a>";
        }
        ?>
			</div>
			<div class="element points"><?php echo intval($hero_info['defencebonus']/100); ?></div>
		</div>

		<div class="clear"></div>

  <div class="attribute productionPoints tooltip" title="<font color=white><b>Recursos</b></font><br>Aumenta la producción de recursos de la aldea en la que reside actualmente el héroe.
<br><font color='#5dcbfb'>Aumento de producción: <?php echo ($hero_info['resource']); ?>%</font>">
			<div class="element attribName">Recursos</div>
			<div class="element current power"><?php echo ($hero_info['resource']/10); ?></div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo intval($hero_info['resource']); ?>%;"></div>
				</div>
			</div>
			<div class="element add">
             <?php
        if($hero_info['points'] > 0){
            echo "<a class=\"setPoint\" href=\"hero_inventory.php?add=resource\"></a>";
        }else {
            echo "<a class=\"setPoint hidden\" href=\"hero_inventory.php?add=resource\"></a>";
        }
        ?>
			</div>
			<div class="element points"><?php echo intval($hero_info['resource']/10); ?></div>
		</div>

		<div class="clear"></div>
		</div>
  </div>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">	<div class="attribute res" id="setResource">
		<div class="changeResourcesHeadline">
			Cambiar el tipo de recurso producido por el héroe		</div>
		<div class="clear"></div>
		<div class="resource">
		  <input type="radio" name="resource" value="0" id="resourceHero0" checked="checked">
			<label for="resourceHero0">
				<img alt="Todos los recursos" class="r0" src="img/x.gif">				<span class="current">0</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" name="resource" value="1" id="resourceHero1">
			<label for="resourceHero1">
				<img alt="Madera" class="r1" src="img/x.gif">				<span class="current">0</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" name="resource" value="2" id="resourceHero2">
			<label for="resourceHero2">
				<img alt="Barro" class="r2" src="img/x.gif">				<span class="current">0</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" name="resource" value="3" id="resourceHero3">
			<label for="resourceHero3">
				<img alt="Hierro" class="r3" src="img/x.gif">				<span class="current">0</span>
			</label>
		</div>
				<div class="resource">
			<input type="radio" name="resource" value="4" id="resourceHero4">
			<label for="resourceHero4">
				<img alt="Cereal" class="r4" src="img/x.gif">				<span class="current">0</span>
			</label>
		</div>
			</div>
	<div class="clear"></div>
		</div>
  </div>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">

<div class="attribute health tooltip" title="<font color=white><b>Salud</b></font><br>Regeneración del héroe: 20% al día
<br><font color='#5dcbfb'>Salud: 20% al día</font>">
			<div class="element attribName">Salud</div>
			<div class="element current power"><span class="value"><?php echo floor($hero_info['health']); ?></span>%</div>
			<div class="element progress">
				<div class="bar-bg">
                <?php
                if($hero_info['health']>=90){
                $color = '#006900';
                } elseif($hero_info['health']>50){
                $color = '#99c01a';
                }
                if($hero_info['health']<=50 && $hero_info['health']>35){
                $color = '#FFFF00';
                }
                if($hero_info['health']<=35 && $hero_info['health']>15){
                $color = '#F0B300';
                } elseif($hero_info['health']<=15) {
                $color = '#f00000';
                }
                ?>
              <font color="#006900"></font>
					<div class="bar" style="width:<?php echo floor($hero_info['health']); ?>%;background-color:<?php echo $color; ?>"></div>
				</div>
			</div>
		</div>
		<div class="clear"></div>
			</div>
  </div>
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">

<div class="attribute experience tooltip" title="<font color=white><b>Experiencia <?php echo floor($hero_info['level']/2); ?>%</b></font><br>Para alcanzar el nivel <?php echo ($hero_info['experience']+1); ?>, tu héroe necesita <?php echo (150-$hero_info['level']); ?> puntos de experiencia.">
			<div class="element attribName">Experiencia</div>
			<div class="element current power"><?php echo $hero_info['level']; ?></div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo floor($hero_info['level']); ?>%;"></div>
				</div>
			</div>
			<div class="element add"></div>
			<div class="element points"><?php echo intval($hero_info['points']); ?></div>
			<div class="clear"></div>
		</div>





  <div class="attribute level tooltip" title="<font color=white><b>Nivel</b></font><br>Nivel<br><font color='#5dcbfb'>La fuerza ofensiva de tu héroe aumentará 100 puntos, en lugar de 80, por cada punto asignado.</font>">
			<div class="element attribName">Nivel</div>
			<div class="element current power"><?php echo $hero_info['experience']; ?></div>
			<div class="element progress">
				<div class="bar-bg">
					<div class="bar" style="width:<?php echo min(100,$hero_info['experience']); ?>%"></div>
				</div>
			</div>
			<div class="clear"></div>
		</div>

		</div>
  </div></div>
