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
require_once(__DIR__."/../../GameEngine/MedalLabels.php");

foreach($varmedal as $medal) {

$titel = medalCategoryLabel($medal['categorie'], $medal['points']);
$woord = "Puntuación";
if(medalIsBonus($medal['categorie'])) {
	$bonus[$medal['id']]=1;
}

if(isset($bonus[$medal['id']])){

	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="'.$titel.'
<br />Semana: '.$medal['week'].'">', $profiel, 1);
} else {
	$profiel = preg_replace("/\[#".$medal['id']."]/is",'<img class="medal '.$medal['img'].'" src="img/x.gif" title="Categoría: '.$titel.'<br />Semana: '.$medal['week'].'<br />Posición: '.$medal['plaats'].'<br />'.$woord.': '.$medal['points'].'<br />">', $profiel, 1);
}
}



?>
