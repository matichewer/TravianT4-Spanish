<?php			   
	//gp link
	if($session->gpack == null || GP_ENABLE == false) {
	$gpack= GP_LOCATE;
	} else {
	$gpack= $session->gpack;
	}

	
//de bird
if($displayarray['protect'] > time()){
$uurover=date('H:i:s', ($displayarray['protect'] - time()));
$profiel = preg_replace("/\[#0]/is",'<img src="'.$gpack.'img/t/tn.gif" border="0" title="Protección de principiante restante: '.$uurover.'" >', $profiel, 1);
} else {
$geregistreerd=date('Y/m/d', ($displayarray['timestamp']));
$tregistreerd=date('H:i', ($displayarray['timestamp']));
$profiel = preg_replace("/\[#0]/is",'<img src="'.$gpack.'img/t/tnd.gif" border="0" title="Jugador registrado el '.$geregistreerd.' a las '.$tregistreerd.'">', $profiel, 1);
}

//natar image
if($displayarray['username'] == "Natars"){
$profiel = preg_replace("/\[#natars]/is",'<img src="gpack/travian_Travian_4.0_41/img/t/t10_2.jpg" border="0">', $profiel, 1);
$profiel = preg_replace("/\[#natars]/is",'<img src="gpack/travian_Travian_4.0_41/img/t/t10_2.jpg" border="0">', $profiel, 1);
}

//de lintjes
/******************************
INDELING CATEGORIEEN:
===============================
== 1. Attackers top 10       ==
== 2. Defence top 10         ==
== 3. Climbers top 10        ==
== 4. Raiders top 10 	     ==
== 5. attackers and defences ==
== 6. in top 3 - Attackers   ==
== 7. in top 3 - Defence 	 ==
== 8. in top 3 - Climbers    ==
== 9. in top 3 - Raiders     ==
******************************/

foreach($varmedal as $medal) {

switch ($medal['categorie']) {
    case "1":
        $titel="Top 10 atacantes de la semana";
		$woord="Puntuación";
        break;
    case "2":
        $titel="Top 10 defensores de la semana";
 		$woord="Puntuación";
       break;
    case "3":
        $titel="Top 10 en ascenso de la semana";
 		$woord="Puntuación";
       break;
    case "4":
        $titel="Top 10 saqueadores de la semana";
		$woord="Puntuación";
        break;
	 case "5":
        $titel="Top 10 en ataque y defensa de la semana";
        $bonus[$medal['id']]=1;
		break;
	 case "6":
        $titel="Top atacantes de la semana ".$medal['points']." (top 3).";
        $bonus[$medal['id']]=1;
		break;
	 case "7":
        $titel="Top defensores de la semana ".$medal['points']." (top 3).";
        $bonus[$medal['id']]=1;
		break;
	 case "8":
        $titel="Top en ascenso de la semana ".$medal['points']." (top 3).";
        $bonus[$medal['id']]=1;
		break;
	 case "9":
        $titel="Top saqueadores de la semana  ".$medal['points']." (top 3).";
        $bonus[$medal['id']]=1;
		break;
     case "10":
        $titel="Ascenso de la semana";
        $woord="Puntuación"; 
        break;
     case "11":
        $titel="Ascenso de la semana ".$medal['points']." (top 3).";
        $bonus[$medal['id']]=1;
        break;
     case "12":
        $titel="Atacantes de la semana ".$medal['points']." (top 10).";
        $bonus[$medal['id']]=1;
        break;
        case "13":
        $titel="Defensores de la semana ".$medal['points']." (top 10).";
        $bonus[$medal['id']]=1;
        break;
        case "14":
        $titel="Ascenso de la semana ".$medal['points']." (top 10).";
        $bonus[$medal['id']]=1;
        break;
        case "15":
        $titel="Saqueadores de la semana ".$medal['points']." (top 10).";
        $bonus[$medal['id']]=1;
        break;
        case "16":
        $titel="Ascenso de la semana ".$medal['points']." (top 10).";
        $bonus[$medal['id']]=1;
        break;
        

}

if(isset($bonus[$medal['id']])){

	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="'.$titel.'
<br />Semana: '.$medal['week'].'">', $profiel, 1);
} else {
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="Categoría: '.$titel.'<br />Semana: '.$medal['week'].'<br />Posición: '.$medal['plaats'].'<br />'.$woord.': '.$medal['points'].'<br />">', $profiel, 1);
}
}



?>

