<?php
	//gp link
	if($session->gpack == null || GP_ENABLE == false) {
	$gpack= GP_LOCATE;
	} else {
	$gpack= $session->gpack;
	}



//Don't think this is accurate
/******************************
INDELING CATEGORIEEN:
===============================
== 1. Attackers top 10      ==
== 2. Defenders top 10      ==
== 3. Climbers top 10       ==
== 4. Raiders top 10        ==
== 5. Attack and Defence    ==
== 6. in top 3 - Attackers  ==
== 7. in top 3 - Defenders  ==
== 8. in top 3 - Climbers   ==
== 9. in top 3 - Raiders    ==
******************************/
$geregistreerd=date('Y/m/d', ($allianceinfo['timestamp']));

$profiel = preg_replace("/\[war]/s",'En guerra con<br>'.$database->getAllianceDipProfile($aid,3), $profiel, 1);
$profiel = preg_replace("/\[ally]/s",'Aliado con<br>'.$database->getAllianceDipProfile($aid,1), $profiel, 1);
$profiel = preg_replace("/\[nap]/s",'Pacto de no agresión con<br>'.$database->getAllianceDipProfile($aid,2), $profiel, 1);
$profiel = preg_replace("/\[diplomatie]/s",'Aliado con<br>'.$database->getAllianceDipProfile($aid,1).'<br>Pacto de no agresión con<br>'.$database->getAllianceDipProfile($aid,2).'<br>En guerra con<br>'.$database->getAllianceDipProfile($aid,3), $profiel, 1);

require_once(__DIR__."/../../GameEngine/MedalLabels.php");

foreach($varmedal as $medal) {

$titel = medalCategoryLabel($medal['categorie'], $medal['points'], true);
$woord = "Puntuación";
if(medalIsBonus($medal['categorie'])) {
	$bonus[$medal['id']]=1;
}

//if(isset($bonus[$medal['id']])){
//$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img src="'.$gpack.'img/t/'.$medal['img'].'.gif" border="0" onmouseout="med_closeDescription()" onmousemove="med_mouseMoveHandler(arguments[0],\'<table><tr><td>'.$titel.'<br /><br />Recibida en la semana: '.$medal['week'].'</td></tr></table>\')">', $profiel, 1);
//} else {
//$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img src="'.$gpack.'img/t/'.$medal['img'].'.gif" border="0" onmouseout="med_closeDescription()" onmousemove="med_mouseMoveHandler(arguments[0],\'<table><tr><td>Categoría:</td><td>'.$titel.'</td></tr><tr><td>Semana:</td><td>'.$medal['week'].'</td></tr><tr><td>Posición:</td><td>'.$medal['plaats'].'</td></tr><tr><td>'.$woord.':</td><td>'.$medal['points'].'</td></tr></table>\')">', $profiel, 1);
//}

if(isset($bonus[$medal['id']]))
{
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="'.$titel.'<br />Semana: '.$medal['week'].'">', $profiel, 1);
}
else
{
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="Categoría: '.$titel.'<br />Semana: '.$medal['week'].'<br />Posición: '.$medal['plaats'].'<br />'.$woord.': '.$medal['points'].'<br />">', $profiel, 1);
}
}



?>
