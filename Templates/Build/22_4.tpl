<table cellpadding="1" cellspacing="1" class="build_details">
<thead><tr>
	<td>Academia</td>
	<td>Acción</td>
</tr></thead>
<tbody>

<?php 
$fail = $success = 0;
$acares = $technology->grabAcademyRes();
for($i=32;$i<=39;$i++) {
	if($technology->meetRRequirement($i) && !$technology->getTech($i) && !$technology->isResearch($i,1)) {
    	echo "<tr><td class=\"desc\">
					<div class=\"tit\">
						<img class=\"unit u".$i."\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\" title=\"".$technology->getUnitName($i)."\" />
						<a href=\"#\" onClick=\"return Popup(".$i.",1);\">".$technology->getUnitName($i)."</a>
					</div>
					<div class=\"details\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\" title=\"Madera\" />".${'r'.$i}['wood']."|<img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\" title=\"Barro\" />".${'r'.$i}['clay']."|<img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\" title=\"Hierro\" />".${'r'.$i}['iron']."|<img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\" title=\"Cereal\" />".${'r'.$i}['crop']."|<img class=\"clock\" src=\"img/x.gif\" alt=\"Duración\" title=\"\" />";
                    echo $generator->getTimeFormat(round(${'r'.$i}['time'] * ($bid22[$village->resarray['f'.$id]]['attri'] / 100)/SPEED));
                    if($session->userinfo['gold'] >= 3 && $building->getTypeSzint(17) > 1) {
                   echo "|<a href=\"build.php?gid=17&t=3&r1=".${'r'.$i}['wood']."&r2=".${'r'.$i}['clay']."&r3=".${'r'.$i}['iron']."&r4=".${'r'.$i}['crop']."\" title=\"Intercambio NPC\"><img class=\"npc\" src=\"img/x.gif\" alt=\"Intercambio NPC\" title=\"Intercambio NPC\" /></a>";
                   }
                   if(${'r'.$i}['wood'] > $village->maxstore || ${'r'.$i}['clay'] > $village->maxstore || ${'r'.$i}['iron'] > $village->maxstore) {
                    echo "<br><span class=\"none\">Mejora el almacén</span></div></td>";
                    echo "<td class=\"none\">
					<div class=\"none\">Mejora<br>el almacén</div>
				</td></tr>";
                }
                else if(${'r'.$i}['crop'] > $village->maxcrop) {
                    echo "<br><span class=\"none\">Mejora el granero</span></div></td>";
                    echo "<td class=\"none\">
					<div class=\"none\">Mejora<br>el granero</div>
				</td></tr>";
                }
                   else if(${'r'.$i}['wood'] > $village->awood || ${'r'.$i}['clay'] > $village->aclay || ${'r'.$i}['iron'] > $village->airon || ${'r'.$i}['crop'] > $village->acrop) {
                   	$time = $technology->calculateAvaliable($i);
                    echo "<br><span class=\"none\">Recursos suficientes ".$time[0]." a las ".$time[1]."</span></div></td>";
                    echo "<td class=\"none\">
					<div class=\"none\">Recursos<br>insuficientes</div>
				</td></tr>";
                }
                else if ( count($acares) > 0 ) {
                     echo "</td>";
                    echo "<td class=\"none\">
					Investigación en curso</td></tr>";
                }
                else {
                     echo "</td>";
                    echo "<td class=\"act\">
					<a class=\"research\" href=\"build.php?id=$id&amp;a=$i&amp;c=".$session->mchecker."\">Investigar</a></td></tr>";
                }
                $success += 1;
    }
    else {
    $fail += 1;
    }
}
if($success == 0) {
echo "<td colspan=\"2\"><div class=\"none\" align=\"center\">No hay unidades para investigar</div></td>";
}
?>		
			</tbody>
            </table>
<?php if($fail > 0) { 
	echo "<p class=\"switch\"><a id=\"researchFutureLink\" href=\"#\" onclick=\"return $('researchFuture').toggle();\">Más</a></p>
		<table id=\"researchFuture\" class=\"build_details hide\" cellspacing=\"1\" cellpadding=\"1\">
			<thead><tr><td colspan=\"2\">Requisitos</td></tr><tbody>";
     if(!$technology->meetRRequirement(33) && !$technology->getTech(33)) {
     echo"<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u33\" title=\"".U33."\" alt=\"".U33."\" src=\"img/x.gif\"/>
			<a onclick=\"return Popup(33, 1);\" href=\"#\">".U33."</a></div></td><td class=\"cond\"><a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a>
			<span title=\"+2\">Nivel 3</span><br /><a href=\"#\" onclick=\"return Popup(12, 4);\">Herrería </a><span title=\"+1\">Nivel 1</span>	</td></tr>";
     }
     if(!$technology->meetRRequirement(34) && !$technology->getTech(34)) {
     echo "<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u34\" title=\"".U34."\" alt=\"".U34."\" src=\"img/x.gif\"/>
		 	<a onclick=\"return Popup(34, 1);\" href=\"#\">".U34."</a></div></td><td class=\"cond\">
            <a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a><span title=\"+2\">Nivel 1</span><br /><a href=\"#\" onclick=\"return Popup(15, 4);\">Edificio principal</a>
			<span title=\"+3\">Nivel 5</span>	</td></tr>";
     }
     if(!$technology->meetRRequirement(35) && !$technology->getTech(35)) {
     echo "<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u35\" title=\"".U35."\" alt=\"".U35."\" src=\"img/x.gif\"/>
			<a onclick=\"return Popup(35, 1);\" href=\"#\">".U35."</a></div></td><td class=\"cond\">
			<a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a><span title=\"+2\">Nivel 5</span><br /><a href=\"#\" onclick=\"return Popup(20, 4);\">Establo </a>
			<span title=\"+5\">Nivel 5</span>	</td></tr>";
     }
     if(!$technology->meetRRequirement(36) && !$technology->getTech(36)) {
     echo "<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u36\" title=\"".U36."\" alt=\"".U36."\" src=\"img/x.gif\"/>
			<a onclick=\"return Popup(36, 1);\" href=\"#\">".U36."</a></div></td><td class=\"cond\">
			<a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a><span title=\"+2\">Nivel 15</span><br /><a href=\"#\" onclick=\"return Popup(20, 4);\">
            Establo </a><span title=\"+3\">Nivel 10</span>	</td></tr>";
     }
     if(!$technology->meetRRequirement(17) && !$technology->getTech(17)) {
     echo "
			<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u37\" title=\"".U37."\" alt=\"".U37."\" src=\"img/x.gif\"/>
			<a onclick=\"return Popup(37, 1);\" href=\"#\">".U37."</a></div></td><td class=\"cond\"><a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a>
			<span title=\"+7\">Nivel 10</span><br /><a href=\"#\" onclick=\"return Popup(21, 4);\">Taller </a><span title=\"+1\">Nivel 1</span></td></tr>";
     }
     if(!$technology->meetRRequirement(38) && !$technology->getTech(38)) {
     echo "<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u38\" title=\"".U38."\" alt=\"".U38."\" src=\"img/x.gif\"/>
            <a onclick=\"return Popup(38, 1);\" href=\"#\">".U38."</a></div></td><td class=\"cond\"><a href=\"#\" onclick=\"return Popup(21, 4);\">Taller</a>
            <span title=\"+10\">Nivel 10</span><br /><a href=\"#\" onclick=\"return Popup(22, 4);\">Academia </a><span title=\"+12\">Nivel 15</span>	</td>
			</tr>";
     }
     if(!$technology->meetRRequirement(39) && !$technology->getTech(39)) {
     echo "	<tr><td class=\"desc\"><div class=\"tit\"><img class=\"unit u39\" title=\"".U33."\" alt=\"".U33."\" src=\"img/x.gif\"/>
			<a onclick=\"return Popup(39, 1);\" href=\"#\">".U39."</a></div></td><td class=\"cond\">
			<a href=\"#\" onclick=\"return Popup(16, 4);\">Plaza de reuniones </a><span title=\"+4\">Nivel 5</span><br /><a href=\"#\" onclick=\"return Popup(22, 4);\">
            Academia </a><span title=\"+17\">Nivel 20</span></td></tr>";
     }
     echo " <script type=\"text/javascript\">
		//<![CDATA[
			$(\"researchFuture\").toggle = (function()
			{
				this.toggleClass(\"hide\");

				$(\"researchFutureLink\").set(\"text\",
					this.hasClass(\"hide\")
					?	\"Mostrar más\"
					:	\"Ocultar\"
				);

				return false;
			}).bind($(\"researchFuture\"));
		//]]>
		</script>";
     echo "</tbody></table>";
}
//$acares = $technology->grabAcademyRes();
if(count($acares) > 0) {
	echo "<table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\"><thead><tr><td>Investigación en curso</td><td>Duración</td><td>Finaliza</td></tr>
	</thead><tbody>";
			$timer = 1;
	foreach($acares as $aca) {
		$unit = substr($aca['tech'],1,2);
		echo "<tr><td class=\"desc\"><img class=\"unit u$unit\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($unit)."\" title=\"".$technology->getUnitName($unit)."\" />".$technology->getUnitName($unit)."</td>";
			echo "<td class=\"dur\"><span id=\"timer$timer\">".$generator->getTimeFormat($aca['timestamp']-time())."</span></td>";
			$date = $generator->procMtime($aca['timestamp']);
		    echo "<td class=\"fin\"><span>".$date[1]."</span><span> hrs</span></td>";
		echo "</tr>";
		$timer +=1;
	}
	echo "</tbody></table></div>";
}
?>
