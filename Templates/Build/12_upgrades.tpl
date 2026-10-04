<div class="clear"></div>
<div class="build_details researches">
			<?php
		$abdata = $database->getABTech($village->wid);
		$ABups = $technology->getABUpgrades('b');
		for($i=($session->tribe*10-9);$i<=($session->tribe*10-2);$i++) {
			$j = $i % 10 ;
			$queue = $technology->smithyQueueState($ABups,$j,$abdata['b'.$j],!empty($session->plus),time());
			$queuedLevel = $queue['level'];
			if ( $technology->getTech($i) || $j == 1 ) {
				echo "<div class=\"research\">
		<div class=\"bigUnitSection\">
			<a class=\"unitSection\" href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">
				<img class=\"unitSection u".$i."Section\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\">
			</a>
			<a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(".$i.");\">
				<img class=\"zoom\" src=\"img/x.gif\" alt=\"Zoom\">
			</a>
		</div>
		<div class=\"information\">
<div class=\"title\">
<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">
<img class=\"unit u".$i."\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\"></a> 
<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">".$technology->getUnitName($i)."</a>
<span class=\"level\">Nivel ".$abdata['b'.$j]."</span>
</div>";
if($queuedLevel >= 20) {
	echo "<div class=\"contractLink\"><span class=\"none\">".((int)$abdata['b'.$j] >= 20 ? "Completamente desarrollado" : "Nivel máximo en cola")."</span></div>
</div><div class=\"clear\"></div></div><hr>";
	continue;
}
if($queuedLevel != 20) {
echo "<div class=\"costs\">
<div class=\"showCosts\">
<span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'ab'.$i}[$queuedLevel+1]['wood']."</span>
<span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'ab'.$i}[$queuedLevel+1]['clay']."</span>
<span class=\"resources r3\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'ab'.$i}[$queuedLevel+1]['iron']."</span>
<span class=\"resources r4\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'ab'.$i}[$queuedLevel+1]['crop']."</span>
<div class=\"clear\"></div>

<span class=\"clocks\">
<img class=\"clock\" src=\"img/x.gif\" alt=\"Duración\">";
				echo $generator->getTimeFormat(round(${'ab'.$i}[$queuedLevel+1]['time']*($bid12[$building->getTypeLevel(12)]['attri'] / 100)/SPEED));
echo "</span>";
				if($session->userinfo['gold'] >= 3 && $building->getTypeLevel(17) >= 1) {
echo "
<button type=\"button\" value=\"npc\" class=\"icon\" onclick=\"window.location.href = 'build.php?gid=17&t=3&r1=".${'ab'.$i}[$queuedLevel+1]['wood']."&r2=".${'ab'.$i}[$queuedLevel+1]['clay']."&r3=".${'ab'.$i}[$queuedLevel+1]['iron']."&r4=".${'ab'.$i}[$queuedLevel+1]['crop']."'; return false;\">
<img src=\"img/x.gif\" class=\"npc\" alt=\"npc\"></button>";
}
echo "<div class=\"clear\"></div>
</div>
</div>";
}
		        if (${'ab'.$i}[$queuedLevel+1]['wood'] > $village->maxstore || ${'ab'.$i}[$queuedLevel+1]['clay'] > $village->maxstore || ${'ab'.$i}[$queuedLevel+1]['iron'] > $village->maxstore) {
					echo "<div class=\"contractLink\"><span class=\"none\">Mejora el almacén</span></div>";
				}
				else if (${'ab'.$i}[$queuedLevel+1]['crop'] > $village->maxcrop) {
					echo "<div class=\"contractLink\"><span class=\"none\">Mejora el granero</span></div>";
				}
				else if (${'ab'.$i}[$queuedLevel+1]['wood'] > $village->awood || ${'ab'.$i}[$queuedLevel+1]['clay'] > $village->aclay || ${'ab'.$i}[$queuedLevel+1]['iron'] > $village->airon || ${'ab'.$i}[$queuedLevel+1]['crop'] > $village->acrop) {
					// Sólo es "nunca" si lo que falta es justamente el cereal y encima no se
					// produce. Un balance negativo por manutención de tropas es lo normal
					// en cuanto hay ejército, y no impide juntar madera, barro ni hierro.
					$cropMissing = ${'ab'.$i}[$queuedLevel+1]['crop'] > $village->acrop;
					if(!$cropMissing || $village->getProd("crop") > 0){
						$time = $technology->calculateAvaliable(12,${'ab'.$i}[$queuedLevel+1]);
			            echo "<div class=\"contractLink\"><span class=\"none\">Recursos suficientes: ".$time[0]." ".$time[1]."</span></div>";
					} else {
						echo "<div class=\"contractLink\"><span class=\"none\">Falta cereal y la producción no da: no va a alcanzar solo</span></div>";
					}
		            //echo "<div class=\"contractLink\"><span class=\"none\">few resources</span></div>";
				}
				else if ($building->getTypeLevel(12) <= $queuedLevel) {
                    if ($queuedLevel == 20)
                    {
                        echo "<div class=\"contractLink\"><span class=\"none\">Completamente desarrollado  </span></div>";
                    }
                    else
                    {
                        echo "<div class=\"contractLink\"><span class=\"none\">Mejora la herrería</span></div>";
                    }
				}
				else if ($queue['full']) {
					echo "<div class=\"contractLink\"><span class=\"none\">Investigación en curso</span></div>";
				}
				else {

					echo "<div class=\"contractLink\"><span class=\"none\">
                    <button type=\"button\" value=\"Upgrade level\" class=\"build\" onclick=\"window.location.href = 'build.php?id=$id&amp;a=$j&amp;c=$session->mchecker'; return false;\">
<div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div>
<div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div>
</div><div class=\"button-contents\">".(count($ABups) > 0 ? "Poner en cola" : "mejorar")."</div></div></button>
                    </span></div>";
				}
echo "</div>
<div class=\"clear\"></div>
</div><hr>";
}
}
?>

</div>

<?php
	if(count($ABups) > 0) {
		echo "<table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\"><thead><tr><td>Unidad</td><td>Tiempo restante</td><td>Finaliza</td></tr>
</thead><tbody>";
		$timer = 1;
		$queueLevels = $abdata;
		usort($ABups,function($a,$b) { return $a['timestamp'] <=> $b['timestamp']; });
		foreach($ABups as $black) {
			$targetLevel = ++$queueLevels[$black['tech']];
			$unit = ($session->tribe-1)*10 + substr($black['tech'],1,2);
			echo "<tr><td class=\"desc\"><img class=\"unit u$unit\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($unit)."\" title=\"".$technology->getUnitName($unit)."\" />".$technology->getUnitName($unit)." <span class=\"level\">Nivel ".$targetLevel."</span></td>";
			echo "<td class=\"dur\"><span id=\"timer$timer\">".$generator->getTimeFormat($black['timestamp']-time())."</span></td>";
			$date = $generator->procMtime($black['timestamp']);
			echo "<td class=\"fin\"><span>".$date[1]."</span><span> </span></td>";
			echo "</tr>";
			$timer +=1;
		}
		echo "</tbody></table>";
	}
?>
