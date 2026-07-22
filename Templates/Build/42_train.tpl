<?php

    for ($i=($session->tribe-1)*10+7;$i<=($session->tribe-1)*10+8;$i++) {
        if ($technology->getTech($i)) {
echo "<tr><td class=\"desc\">
<div class=\"tit\">
<img class=\"unit u".$i."\" src=\"img/x.gif\" alt=\"".$technology->getUnitName($i)."\" title=\"".$technology->getUnitName($i)."\" />
<a href=\"#\" onClick=\"return Popup(".$i.",1);\"> ".$technology->getUnitName($i)."</a> <span class=\"info\">(actual: ".$village->unitarray['u'.$i].")</span>
</div>
<div class=\"details\">
<img class=\"r1\" src=\"img/x.gif\" alt=\"Madera\" title=\"Madera\" />".(${'u'.$i}['wood']*3)."|<img class=\"r2\" src=\"img/x.gif\" alt=\"Barro\" title=\"Barro\" />".(${'u'.$i}['clay']*3)."|<img class=\"r3\" src=\"img/x.gif\" alt=\"Hierro\" title=\"Hierro\" />".(${'u'.$i}['iron']*3)."|<img class=\"r4\" src=\"img/x.gif\" alt=\"Cereal\" title=\"Cereal\" />".(${'u'.$i}['crop']*3)."|<img class=\"r5\" src=\"img/x.gif\" alt=\"Consumo de cereal\" title=\"Consumo de cereal\" />".${'u'.$i}['pop']."|<img class=\"clock\" src=\"img/x.gif\" alt=\"Duración\" title=\"Duración\" />";
echo $generator->getTimeFormat(round(${'u'.$i}['time'] * ($bid42[$village->resarray['f'.$id]]['attri'] / 100) / SPEED));
if($session->userinfo['gold'] >= 3 && $building->getTypeLevel(17) >= 1) {
                   echo "|<a href=\"build.php?gid=17&t=3&r1=".((${'u'.$i}['wood']*3)*$technology->maxUnitPlus($i,true))."&r2=".((${'u'.$i}['clay']*3)*$technology->maxUnitPlus($i,true))."&r3=".((${'u'.$i}['iron']*3)*$technology->maxUnitPlus($i,true))."&r4=".((${'u'.$i}['crop']*3)*$technology->maxUnitPlus($i,true))."\" title=\"Intercambio NPC\"><img class=\"npc\" src=\"img/x.gif\" alt=\"Intercambio NPC\" title=\"Intercambio NPC\" /></a>";
                 }
echo "</div>
</td>
<td class=\"val\"><input type=\"text\" class=\"text\" name=\"t".$i."\" value=\"0\" maxlength=\"4\"></td>
<td class=\"max\"><a href=\"#\" onClick=\"document.snd.t".$i.".value=".$technology->maxUnit($i,true)."; return false;\">(".$technology->maxUnit($i,true).")</a></td></tr></tbody>";
        }
    }
?>
