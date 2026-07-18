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

foreach($varmedal as $medal) {

switch ($medal['categorie']) {
    case "1":
        $titel="Attackers of the Week";
		$woord="Score";
        break;
    case "2":
        $titel="Defenders of the Week";
 		$woord="Score";
       break;
    case "3":
        $titel="Climbers of the Week";
 		$woord="Score";
       break;
    case "4":
        $titel="Raiders of the Week";
		$woord="Score";
        break;
	 case "5":
        $titel="Top 10 both attackers and defenders.";
        $bonus[$medal['id']]=1;
		break;
	 case "6":
        $titel="Top Attackers of the Week ".$medal['points']." top 3.";
        $bonus[$medal['id']]=1;
		break;
	 case "7":
        $titel="Top Defenders of the Week ".$medal['points']." top 3.";
        $bonus[$medal['id']]=1;
		break;
	 case "8":
        $titel="Top Climbers of the Week ".$medal['points']." top 3.";
        $bonus[$medal['id']]=1;
		break;
	 case "9":
        $titel="Top Raiders of the Week  ".$medal['points']." top 3.";
        $bonus[$medal['id']]=1;
		break;
    case "11":
        $titel="Climbers of the Week".$medal['points']." top 3.";
        $bonus[$medal['id']]=1;
        break;
         case "12":
        $titel="Attackers of the Week ".$medal['points']." top 10.";
        $bonus[$medal['id']]=1;
        break;
        case "13":
        $titel="Defenders of the Week ".$medal['points']." top 10.";
        $bonus[$medal['id']]=1;
        break;
        case "15":
        $titel="Raiders of the Week ".$medal['points']." top 10.";
        $bonus[$medal['id']]=1;
        break;
        case "16":
        $titel="Climbers of the Week ".$medal['points']." top 10.";
        $bonus[$medal['id']]=1;
        break;

}

//if(isset($bonus[$medal['id']])){
//$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img src="'.$gpack.'img/t/'.$medal['img'].'.gif" border="0" onmouseout="med_closeDescription()" onmousemove="med_mouseMoveHandler(arguments[0],\'<table><tr><td>'.$titel.'<br /><br />Recibida en la semana: '.$medal['week'].'</td></tr></table>\')">', $profiel, 1);
//} else {
//$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img src="'.$gpack.'img/t/'.$medal['img'].'.gif" border="0" onmouseout="med_closeDescription()" onmousemove="med_mouseMoveHandler(arguments[0],\'<table><tr><td>Categoría:</td><td>'.$titel.'</td></tr><tr><td>Semana:</td><td>'.$medal['week'].'</td></tr><tr><td>Posición:</td><td>'.$medal['plaats'].'</td></tr><tr><td>'.$woord.':</td><td>'.$medal['points'].'</td></tr></table>\')">', $profiel, 1);
//}

if(isset($bonus[$medal['id']]))
{
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="'.$titel.'<br />Week: '.$medal['week'].'">', $profiel, 1);
}
else
{
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="Category: '.$titel.'<br />Week: '.$medal['week'].'<br />Rank: '.$medal['plaats'].'<br />'.$woord.': '.$medal['points'].'<br />">', $profiel, 1);
}
}



?>
