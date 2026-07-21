
<?php 
$fail = $success = 0;
$acares = $technology->grabAcademyRes();
for($i=12;$i<=19;$i++) {
	if($technology->meetRRequirement($i) && !$technology->getTech($i) && !$technology->isResearch($i,1)) {
    	echo "<div class=\"build_details researches\">
        <div class=\"research\">
			<div class=\"bigUnitSection\">
				<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">
					<img class=\"unitSection u".$i."Section\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\">
				</a>
				<a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(".$i.");\">
					<img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\">
				</a>
			</div>
			<div class=\"information\">
<div class=\"title\">
<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">
<img class=\"unit u".$i."\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\"></a>
<a href=\"#\" onclick=\"return Travian.Game.iPopup(".$i.",1);\">".$technology->getUnitName($i)."</a>
</div>
<div class=\"costs\">
<div class=\"showCosts\">
                    <span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r'.$i}['wood']."</span>
                    <span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r'.$i}['clay']."</span>
                    <span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r'.$i}['iron']."</span>
                    <span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r'.$i}['crop']."</span>
                    <div class=\"clear\"></div>
                    <span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
                    echo $generator->getTimeFormat(round(${'r'.$i}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
                    echo "</span>";
                    if($session->userinfo['gold'] >= 3 && $building->getTypeLevel(17) > 1) {
                   echo "<button type=\"button\" value=\"npc\" class=\"icon\" onclick=\"window.location.href = 'build.php?gid=17&t=3&r1=".${'r'.$i}['wood']."&r2=".${'r'.$i}['clay']."&r3=".${'r'.$i}['iron']."&r4=".${'r'.$i}['crop']."'; return false;\"><img src=\"img/x.gif\" class=\"npc\" alt=\"npc\"></button>
                    <div class=\"clear\"></div>";
                   }
                   if(${'r'.$i}['wood'] > $village->maxstore || ${'r'.$i}['clay'] > $village->maxstore || ${'r'.$i}['iron'] > $village->maxstore) {
                    echo "<br><div class=\"contractLink\"><span class=\"none\">Mejora el almacén</span></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div>";
echo "<div class=\"clear\">&nbsp;</div></div></div>";
                }
                else if(${'r'.$i}['crop'] > $village->maxcrop) {
                    echo "<br><div class=\"contractLink\"><span class=\"none\">Mejora el granero</span></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div>";
                    echo "<div class=\"clear\">&nbsp;</div></div></div>";
                }
                   else if(${'r'.$i}['wood'] > $village->awood || ${'r'.$i}['clay'] > $village->aclay || ${'r'.$i}['iron'] > $village->airon || ${'r'.$i}['crop'] > $village->acrop) {
                   	$time = $technology->calculateAvaliable(22,${'r'.$i});
                    echo "<br><div class=\"contractLink\"><span class=\"none\">Recursos insuficientes ~ disponibles a las ".$time[1]." </span></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div>";
                    echo "<div class=\"clear\">&nbsp;</div></div></div>";
                }
                else if ( count($acares) > 0 ) {
                echo "<br><div class=\"contractLink\"><span class=\"none\">
                    Investigación en curso</span></div></div></div></div>
                    <div class=\"clear\">&nbsp;</div>
                    </div></div>";
                }
                else {
                
                
                    echo "<div class=\"contractLink\"><button type=\"button\" value=\"Investigar\" class=\"build\" onclick=\"window.location.href = 'build.php?id=$id&amp;a=$i&amp;c=".$session->mchecker."'; return false;\">
<div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div>
<div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div>
</div><div class=\"button-contents\">Investigar</div></div></button></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div><div class=\"clear\">&nbsp;</div></div></div>";
                }
                $success += 1;
   }
    else {
    $fail += 1;
    }
}
if($success == 0) {
echo "<div class=\"build_details researches\"><div class=\"noResearchPossible\"><span class=\"none\">No hay nuevas unidades para investigar</span></div></div>";
}
?>		
<?php
if($fail > 0) { 
	echo "<p class=\"switch\"><a class=\"openedClosedSwitch switchOpened\" id=\"researchFutureLink\" href=\"#\" onclick=\"return $('researchFuture').toggle();\">Más</a></p>
    <div id=\"researchFuture\" class=\"researches hide \">";
     if(!$technology->meetRRequirement(13) && !$technology->getTech(13)) {
     echo"<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(13,1);\"><img class=\"unitSection u13Section\" src=\"img/x.gif\" alt=\"Hachero\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(13);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(13,1);\"><img class=\"unit u13\" src=\"img/x.gif\" alt=\"Hachero\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(13,1);\">Hachero</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r13'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r13'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r13'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r13'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"Duración\">";
	 echo $generator->getTimeFormat(round(${'r13'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 3</span>, <a href=\"#\">Herrería</a><span class=\"level\"> Nivel 1</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(14) && !$technology->getTech(14)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(14,1);\"><img class=\"unitSection u14Section\" src=\"img/x.gif\" alt=\"Emisario\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(14);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(14,1);\"><img class=\"unit u14\" src=\"img/x.gif\" alt=\"Emisario\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(14,1);\">Emisario</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r14'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r14'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r14'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r14'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r14'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 1</span>, <a href=\"#\">Edificio principal</a><span class=\"level\"> Nivel 5</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(15) && !$technology->getTech(15)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(15,1);\"><img class=\"unitSection u15Section\" src=\"img/x.gif\" alt=\"Paladin\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(15);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(15,1);\"><img class=\"unit u15\" src=\"img/x.gif\" alt=\"Paladin\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(15,1);\">Paladín</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r15'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r15'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r15'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r15'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r15'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 5</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 5</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(16) && !$technology->getTech(16)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(16,1);\"><img class=\"unitSection u16Section\" src=\"img/x.gif\" alt=\"Jinete Teutón\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(16);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(16,1);\"><img class=\"unit u16\" src=\"img/x.gif\" alt=\"Jinete Teutón\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(16,1);\">Jinete Teutón</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r16'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r16'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r16'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r16'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r16'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 15</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 10</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(17) && !$technology->getTech(17)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(17,1);\"><img class=\"unitSection u17Section\" src=\"img/x.gif\" alt=\"Ariete\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(17);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(17,1);\"><img class=\"unit u17\" src=\"img/x.gif\" alt=\"Ariete\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(17,1);\">Ariete</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r17'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r17'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r17'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r17'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r17'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
     echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 10</span>, <a href=\"#\">Taller</a><span class=\"level\"> Nivel 1</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(18) && !$technology->getTech(18)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(18,1);\"><img class=\"unitSection u18Section\" src=\"img/x.gif\" alt=\"Catapulta\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(18);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(18,1);\"><img class=\"unit u18\" src=\"img/x.gif\" alt=\"Catapulta\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(18,1);\">Catapulta</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r18'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r18'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r18'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r18'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r18'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
     echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 15</span>, <a href=\"#\">Taller</a><span class=\"level\"> Nivel 10</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(19) && !$technology->getTech(19)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(19,1);\"><img class=\"unitSection u19Section\" src=\"img/x.gif\" alt=\"Cabecilla\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(19);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(19,1);\"><img class=\"unit u19\" src=\"img/x.gif\" alt=\"Cabecilla\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(19,1);\">Cabecilla</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r19'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r19'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r19'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r19'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r19'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 20</span>, <a href=\"#\">Plaza de reuniones</a><span class=\"level\"> Nivel 5</span></div></div><div class=\"clear\"></div></div>";
     }
?>
<script type="text/javascript">
        //<![CDATA[
            $("researchFuture").toggle = (function()
            {
                this.toggleClass("hide");

                $("researchFutureLink").set("text",
                    this.hasClass("hide")
                    ?   "Mostrar más"
                    :   "Ocultar"
                );

                return false;
            }).bind($("researchFuture"));
        //]]>
</script>
<?php
     echo "<div class=\"clear\"></div></div>";
}

if(count($acares) > 0) {
    echo "<table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\"><thead><tr><td>Entrenamiento</td><td>Duración</td><td>Finaliza</td></tr>
    </thead><tbody>";
            $timer = 1;
    foreach($acares as $aca) {
        $unit = substr($aca['tech'],1,2);
        echo "<tr><td class=\"desc\"><img class=\"unit u$unit\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($unit)."\" title=\"".$technology->getUnitName($unit)."\" />".$technology->getUnitName($unit)."</td>";
            echo "<td class=\"dur\"><span id=\"timer$timer\">".$generator->getTimeFormat($aca['timestamp']-time())."</span></td>";
            $date = $generator->procMtime($aca['timestamp']);
            echo "<td class=\"fin\"><span>".$date[1]."</span><span> hs</span></td>";
        echo "</tr>";
        $timer +=1;
    }
    echo "</tbody></table>";
}

/*if()  {
    echo "</div></div><div class=\"clear\"></div>";
}*/

?>
