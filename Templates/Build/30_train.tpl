<?php 
$success = 0;
$stableUnits = array(
	1 => array(4,5,6),
	2 => array(15,16),
	3 => array(23,24,25,26),
	4 => array(35,36),
	5 => array(45,46)
);
foreach($stableUnits[$session->tribe] as $i) {
	if($technology->getTech($i)) {
    echo "<div class=\"action first\">
                	<div class=\"bigUnitSection\">
						<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">
							<img class=\"unitSection u".$i."Section\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\">
						</a>
						<a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(".$i.");\">
							<img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\">
						</a>
					</div>
					<div class=\"details\">
						<div class=\"tit\">
							<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\"><img class=\"unit u".$i."\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\"></a>
							<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">".$technology->getUnitName($i)."</a>
							<span class=\"furtherInfo\">(Disponibles: ".$village->unitarray['u'.$i].")</span>
						</div>
                        <div class=\"showCosts\">
                        <span class=\"resources r1\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".(${'u'.$i}['wood']*3)."</span>
                        <span class=\"resources r2\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".(${'u'.$i}['clay']*3)."</span>
                        <span class=\"resources r3\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".(${'u'.$i}['iron']*3)."</span>
                        <span class=\"resources r4\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".(${'u'.$i}['crop']*3)."</span>
                        <span class=\"resources r5\"><img class=\"r5\" src=\"img/x.gif\" alt=\"Consumo de cereal\">".${'u'.$i}['pop']."</span>
                        <div class=\"clear\"></div>
                        <span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
                        echo $generator->getTimeFormat(round(${'u'.$i}['time'] * ($bid30[$village->resarray['f'.$id]]['attri'] / 100) / SPEED));
						echo "</span><div class=\"clear\"></div></div><span class=\"value\"></span>
						<input type=\"text\" class=\"text\" name=\"t$i\" value=\"0\" maxlength=\"4\">
                        <span class=\"value\"> / </span>
						<a href=\"#\" onClick=\"document.snd.t".$i.".value=".$technology->maxUnit($i,true)."; return false;\">".$technology->maxUnit($i,true)."</a>
					</div></div>
					<div class=\"clear\"></div><br />";
          $success += 1;
    }
    }
if($success == 0) {
	echo "<tr><td colspan=\"3\"><div class=\"none\"><center>Primero investiga unidades de caballería en la academia.</center></div></td></tr>";
}
?>
