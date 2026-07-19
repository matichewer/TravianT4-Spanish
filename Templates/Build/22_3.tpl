
<?php 
$fail = $success = 0;
$acares = $technology->grabAcademyRes();
for($i=22;$i<=29;$i++) {
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
                    <span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"Duración\">";
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
                    echo "<br><div class=\"contractLink\"><span class=\"none\">Recursos insuficientes ~ suficientes a las ".$time[1]."</span></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div>";
                    echo "<div class=\"clear\">&nbsp;</div></div></div>";
                }
                else if ( count($acares) > 0 ) {
                echo "<br><div class=\"contractLink\"><span class=\"none\">
                    Fejlesztés folyamatban</span></div></div></div></div>
                    <div class=\"clear\">&nbsp;</div>
                    </div></div>";
                }
                else {
                echo "<div class=\"contractLink\"><button type=\"button\" value=\"Fejlesztés\" class=\"build\" onclick=\"window.location.href = 'build.php?id=$id&amp;a=$i&amp;c=".$session->mchecker."'; return false;\">
<div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div>
<div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div>
</div><div class=\"button-contents\">Investigar</div></div></button></div>
</div>
<div class=\"clear\">&nbsp;</div>
</div></div><div class=\"clear\">&nbsp;</div></div></div>";
                }
                $success +=1;
    }
    else {
    $fail += 1;
    }
}
if($success == 0) {
echo "<div class=\"buildActionOverview trainUnits\"><td colspan=\"2\"><div class=\"none\" align=\"center\">No hay nuevas unidades para investigar</div></td></div>";
}
?>		
<?php if($fail > 0) { 
	echo "<p class=\"switch\"><a class=\"openedClosedSwitch switchOpened\" id=\"researchFutureLink\" href=\"#\" onclick=\"return $('researchFuture').toggle();\">Más</a></p>
    <div id=\"researchFuture\" class=\"researches hide \">";
      if(!$technology->meetRRequirement(22) && !$technology->getTech(22)) {
     echo"<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(22,1);\"><img class=\"unitSection u22Section\" src=\"img/x.gif\" alt=\"Espadachín\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(22);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(22,1);\"><img class=\"unit u22\" src=\"img/x.gif\" alt=\"Espadachín\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(22,1);\">Espadachín</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r22'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r22'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r22'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r22'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r22'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 3</span>, <a href=\"#\">Herrería</a><span class=\"level\"> Nivel 1</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(23) && !$technology->getTech(23)) {
     echo"<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(23,1);\"><img class=\"unitSection u23Section\" src=\"img/x.gif\" alt=\"Pionero\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(23);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(23,1);\"><img class=\"unit u23\" src=\"img/x.gif\" alt=\"Pionero\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(23,1);\">Explorador</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r23'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r23'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r23'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r23'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r23'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 5</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 1</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(24) && !$technology->getTech(24)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(24,1);\"><img class=\"unitSection u24Section\" src=\"img/x.gif\" alt=\"Trueno de Tutatis\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(24);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(24,1);\"><img class=\"unit u24\" src=\"img/x.gif\" alt=\"Trueno de Tutatis\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(24,1);\">Trueno de Tutatis</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r24'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r24'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r24'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r24'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r24'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 5</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 3</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(25) && !$technology->getTech(25)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(25,1);\"><img class=\"unitSection u25Section\" src=\"img/x.gif\" alt=\"Druida\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(25);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(25,1);\"><img class=\"unit u25\" src=\"img/x.gif\" alt=\"Druida\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(25,1);\">Druida</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r25'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r25'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r25'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r25'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r25'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 5</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 5</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(26) && !$technology->getTech(26)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(26,1);\"><img class=\"unitSection u26Section\" src=\"img/x.gif\" alt=\"Haeduan\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(26);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(26,1);\"><img class=\"unit u26\" src=\"img/x.gif\" alt=\"Haeduan\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(26,1);\">Haeduan</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r26'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r26'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r26'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r26'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r26'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 15</span>, <a href=\"#\">Establo</a><span class=\"level\"> Nivel 10</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(27) && !$technology->getTech(27)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(27,1);\"><img class=\"unitSection u27Section\" src=\"img/x.gif\" alt=\"Ariete\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(27);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(27,1);\"><img class=\"unit u27\" src=\"img/x.gif\" alt=\"Ariete\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(27,1);\">Ariete</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r27'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r27'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r27'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r27'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r27'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 10</span>, <a href=\"#\">Taller</a><span class=\"level\"> Nivel 1</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(28) && !$technology->getTech(28)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(28,1);\"><img class=\"unitSection u28Section\" src=\"img/x.gif\" alt=\"Trebuchet\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(28);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(28,1);\"><img class=\"unit u28\" src=\"img/x.gif\" alt=\"Trebuchet\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(28,1);\">Catapulta</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r28'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r28'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r28'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r28'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r28'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 15</span>, <a href=\"#\">Taller</a><span class=\"level\"> Nivel 10</span></div></div><div class=\"clear\"></div></div><hr>";
     }
     if(!$technology->meetRRequirement(29) && !$technology->getTech(29)) {
     echo "<div class=\"research\"><div class=\"bigUnitSection\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(29,1);\"><img class=\"unitSection u29Section\" src=\"img/x.gif\" alt=\"Jefe Galo\"></a><a href=\"#\" class=\"zoom\" onclick=\"return Travian.Game.unitZoom(29);\"><img class=\"zoom\" src=\"img/x.gif\" alt=\"ampliar\"></a></div><div class=\"information\"><div class=\"title\"><a href=\"#\" onclick=\"return Travian.Game.iPopup(29,1);\"><img class=\"unit u29\" src=\"img/x.gif\" alt=\"Jefe Galo\"></a><a href=\"#\" onclick=\"return Travian.Game.iPopup(29,1);\">Főnök</a></div><div class=\"costs\"><div class=\"showCosts\"><span class=\"resources r1 little_res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\">".${'r29'}['wood']."</span><span class=\"resources r2 little_res\"><img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\">".${'r29'}['clay']."</span><span class=\"resources r3 little_res\"><img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\">".${'r29'}['iron']."</span><span class=\"resources r4 little_res\"><img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\">".${'r29'}['crop']."</span><div class=\"clear\"></div><span class=\"clocks\"><img class=\"clock\" src=\"img/x.gif\" alt=\"duración\">";
	 echo $generator->getTimeFormat(round(${'r29'}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
	 echo"</span><div class=\"clear\"></div></div></div><div class=\"contractLink\"><a href=\"#\">Academia</a><span class=\"level\"> Nivel 20</span>, <a href=\"#\">Plaza de reuniones</a><span class=\"level\"> Nivel 10</span></div></div><div class=\"clear\"></div></div>";
     }
?>
<script type="text/javascript">
        //<![CDATA[
            $("researchFuture").toggle = (function()
            {
                this.toggleClass("hide");

                $("researchFutureLink").set("text",
                    this.hasClass("hide")
                    ?   "Továbbiak"
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
