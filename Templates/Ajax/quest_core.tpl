<?php

include("GameEngine/Village.php");
include("GameEngine/Data/cp.php");

$uArray = $database->getUserArray($_SESSION['username'],0);
$dataarray = explode(",",$database->getUserField($_SESSION['username'],'fquest','username'));

if($message->unread && !$message->nunread) { $messagelol = "i2"; }
else if(!$message->unread && $message->nunread) { $messagelol = "i3"; }
else if($message->unread && $message->nunread) { $messagelol = "i1"; }
else { $messagelol = "i4"; }

if (isset($qact)){
 switch($qact) {
	case 'enter':
	$database->updateUserField($_SESSION['username'],'quest','1',0);
	$_SESSION['qst']= 1;
	break;

	case 'skip':
	$database->updateUserField($_SESSION['username'],'quest','23',0);
	$_SESSION['qst']= 23;
	$gold=$database->getUserField($_SESSION['username'],'gold','username');
	$gold+=25;
	$database->updateUserField($_SESSION['username'],'gold',$gold,0);
	$skiped=true;
	break;

	case '2':
	$database->updateUserField($_SESSION['username'],'quest','2',0);
	$_SESSION['qst']= 2;

	//Give Reward
	$database->FinishWoodcutter($session->villages[0]);
	break;

	case '3':
	$database->updateUserField($_SESSION['username'],'quest','3',0);
	$_SESSION['qst']= 3;
    $today = date("mdHi");
	mysql_query("UPDATE ".TB_PREFIX."users set plus = ('".mktime(date("H"),date("i"), date("s"),date("m") , date("d"), date("Y"))."')+3600*24 where `id`='".$session->uid."'") or die(mysql_error());
	break;

	case '4':
	$database->updateUserField($_SESSION['username'],'quest','4',0);
	$_SESSION['qst']= 4;

	//Give Reward
	$database->modifyResource($session->villages[0],130,160,130,100,1);
	break;

	case '5':
    $database->updateUserField($_SESSION['username'],'quest','5',0);
	$_SESSION['qst']= 5;

	$rSubmited=$qact2;
    //Give Reward
	$database->FinishRallyPoint($session->villages[0]);
	break;

	case '6':
	$database->updateUserField($_SESSION['username'],'quest','6',0);
	$_SESSION['qst']= 6;
	//Give Reward
	$database->modifyResource($session->villages[0],80,110,60,40,1);

	break;

	case '7':
	$database->updateUserField($_SESSION['username'],'quest','7',0);
	$_SESSION['qst']= 7;
	$Subject="Mensaje del Taskmaster";
	$Message="Te informamos que te espera una linda recompensa en el taskmaster.<br /><br />Aviso: este mensaje se generó automáticamente. No es necesario responder.";
	$database->sendMessage($session->userinfo['id'],0,$Subject,$Message,0,0,0,0,0);
	$RB=true;

	//Give Reward
	$database->modifyResource($session->villages[0],150,160,130,130,1);
	break;

	case '8':
	$database->updateUserField($_SESSION['username'],'quest','8',0);
	$_SESSION['qst']= 8;

	//Give Reward
	$gold=$database->getUserField($_SESSION['username'],'gold','username');
	$gold+=20;
	$database->updateUserField($_SESSION['username'],'gold',$gold,0);
	break;

	case '9':
	$database->updateUserField($_SESSION['username'],'quest','9',0);
	$_SESSION['qst']= 9;
	//Give Reward
	$database->modifyUnit($session->villages[0],100,120,40,40,1);
	break;

	case 'coor':
	$x=$qact2;
	$y=$qact3;
	break;

	case '10':
	$database->updateUserField($_SESSION['username'],'quest','10',0);
	$_SESSION['qst']= 10;

	//Give Reward
	$database->modifyResource($session->villages[0],60,30,40,90,1);
	break;

	case '11':
	$database->updateUserField($_SESSION['username'],'quest','11',0);
	$_SESSION['qst']= 11;

	//Give Reward
	$database->modifyResource($session->villages[0],75,120,30,50,1);
	break;

	case '12':
	$database->updateUserField($_SESSION['username'],'quest','12',0);
	$_SESSION['qst']= 12;

	//Give Reward
	$database->modifyResource($session->villages[0],120,200,140,100,1);
	break;

	case '13':
	$database->updateUserField($_SESSION['username'],'quest','13',0);
	$_SESSION['qst']= 13;

	//Give Reward
	$database->modifyResource($session->villages[0],150,180,30,130,1);
	break;

    case '14':
	$database->updateUserField($_SESSION['username'],'quest','14',0);
	$_SESSION['qst']= 14;

	//Give Reward
	$database->modifyResource($session->villages[0],60,50,40,30,1);
	break;

	case 'lumber':
	$lSubmited=$qact2;
	break;

	case '15':
	$database->updateUserField($_SESSION['username'],'quest','15',0);
	$_SESSION['qst']= 15;

	//Give Reward
	$database->modifyResource($session->villages[0],50,30,60,20,1);
	break;

	case '16':
	$database->updateUserField($_SESSION['username'],'quest','16',0);
	$_SESSION['qst']= 16;

	//Give Reward
	$database->modifyResource($session->villages[0],75,75,40,40,1);
	break;

    case 'rank':
	$rSubmited=$qact2;
	break;

	case '17':
	$database->updateUserField($_SESSION['username'],'quest','17',0);
	$_SESSION['qst']= 17;

	//Give Reward
	$database->modifyResource($session->villages[0],100,90,100,60,1);
	break;

	case '18':
	$database->updateUserField($_SESSION['username'],'quest','18',0);
	$_SESSION['qst']= 18;
	break;

	case '19':
	$database->updateUserField($_SESSION['username'],'quest','19',0);
	$_SESSION['qst']= 19;

    //Give Reward
	$database->modifyResource($session->villages[0],70,100,90,100,1);
	break;

	case '20':
	$database->updateUserField($_SESSION['username'],'quest','20',0);
	$_SESSION['qst']= 20;
	break;

	case '21':
	$database->updateUserField($_SESSION['username'],'quest','21',0);
	$_SESSION['qst']= 21;

    //Give Reward
    $database->modifyResource($session->villages[0],80,90,60,40,1);
	break;

	case '22':
	$database->updateUserField($_SESSION['username'],'quest','22',0);
	$_SESSION['qst']= 22;

	//Give Reward
	$database->modifyResource($session->villages[0],70,120,90,50,1);
	break;

    case '23':
    $database->updateUserField($_SESSION['username'],'quest','23',0);
    $_SESSION['qst']= 23;

    //Give Reward
    $database->modifyResource($session->villages[0],1200,200,200,450,1);
    break;

    case '24':
    $database->updateUserField($_SESSION['username'],'quest','24',0);
    $_SESSION['qst']= 24;

    //Give Reward
    $database->modifyResource($session->villages[0],80,90,60,40,1);
    break;

	case '225':
	$_SESSION['qst']= 25;
	break;
    case '226':
	$_SESSION['qst']= 26;
	break;
    case '227':
	$_SESSION['qst']= 27;
	break;
    case '228':
	$_SESSION['qst']= 28;
	break;
    case '229':
	$_SESSION['qst']= 29;
	break;
    case '330':
	$_SESSION['qst']= 30;
	break;
    case '331':
	$_SESSION['qst']= 31;
	break;
    case '332':
	$_SESSION['qst']= 32;
	break;
    case '333':
	$_SESSION['qst']= 33;
	break;
    case '334':
	$_SESSION['qst']= 34;
	break;
    case '335':
	$_SESSION['qst']= 35;
	break;

	case '25':
	$dataarray[0] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$gold=$database->getUserField($_SESSION['username'],'gold','username');
	$gold+=15;
	$database->updateUserField($_SESSION['username'],'gold',$gold,0);
	break;

    case '26':
	$dataarray[1] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],140,200,180,200,1);
	break;

    case '27':
	$dataarray[2] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],200,120,180,80,1);
	break;

    case '28':
	$dataarray[3] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],240,280,180,100,1);
	break;

    case '29':
	$dataarray[4] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],600,750,600,300,1);
	break;

    case '30':
	$dataarray[5] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],900,850,600,300,1);
	break;

    case '31':
	$dataarray[6] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],1800,2000,1650,800,1);
	break;

    case '32':
	$dataarray[7] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],1600,1800,1950,1200,1);
	break;

    case '33':
	$dataarray[8] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],3400,2800,3600,2200,1);
	break;

    case '34':
	$dataarray[9] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],1050,800,900,750,1);
	break;

    case '35':
	$dataarray[10] = 1;
	$database->updateUserField($_SESSION['username'],'fquest',''.$dataarray[0].','.$dataarray[1].','.$dataarray[2].','.$dataarray[3].','.$dataarray[4].','.$dataarray[5].','.$dataarray[6].','.$dataarray[7].','.$dataarray[8].','.$dataarray[9].','.$dataarray[10].'',0);

    $database->updateUserField($_SESSION['username'],'quest','',0);
	$_SESSION['qst']= 24;

	//Give Reward
	$database->modifyResource($session->villages[0],1600,2000,1800,1300,1);
	break;


}

}

header("Content-Type: application/json;");

      if($_SESSION['qst']== 0){
      if($session->userinfo['tribe'] == 1) {
                $tribes = "Romans";
                }
                else if($session->userinfo['tribe'] == 2) {
                $tribes = "Teutons";
                }
                else if($session->userinfo['tribe'] == 3) {
                $tribes = "Gauls";
                }
	  ?>

{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/> ¡Bienvenido a <?php echo SERVER_NAME; ?>!<\/h1><br \/><i>&rdquo;Por lo que veo, te han nombrado senador de esta pequeña aldea. Seré tu consejero durante los primeros días y nunca me apartaré de tu lado (izquierdo).&rdquo;<\/i><br \/><br \/><span id=\"qst_accpt\"><a class=\"qle\" href=\"javascript: qst_next('','enter'); \">A la primera tarea.<\/a><a class=\"qri\" href=\"javascript: qst_fhandle();\">Mirar\u00a0alrededor\u00a0por\u00a0tu\u00a0cuenta.<\/a><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/><br \/><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"intro\"><\/div>\n\t\t","number":null,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":1}

<?php } elseif($_SESSION['qst']== 1){

//Check one of Woodcutters is level 1 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$woodL=$tRes['f1']+$tRes['f3']+$tRes['f14']+$tRes['f17'];
	//check if you are building a woodcutter to level 1
	foreach($building->buildArray as $jobs) {
			if($jobs['type']==1){
				$woodL="99";
			}
      	}
if ($woodL<1){?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/> Tarea 1: Leñador<\/h1><br \/><i>&rdquo;Alrededor de tu aldea hay cuatro bosques verdes. Construye un leñador en uno de ellos. La madera es un recurso importante para nuestro nuevo asentamiento.&rdquo;<\/i><br \/><br \/><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un leñador.<\/div><br \/><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"wood\"><\/div>\n\t\t","number":"-1","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/> Tarea 1: Leñador<\/h1><br \/><i>&rdquo;Sí, así consigues más madera. Te ayudé un poco y completé la orden al instante.&rdquo;<\/i><br \/><br \/><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/><div class=\"rew\"><p class=\"ta_aw\">Tu recompensa:<\/p>Leñador completado al instante.<br \/><\/div><br \/><span id=\"qst_accpt\"><a href=\"javascript: qst_next('','2');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"wood\"><\/div>\n\t\t","number":"-1","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php }?>

<?php } elseif($_SESSION['qst']== 2){

//Check one of Croplands is level 1 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$cropL=$tRes['f2']+$tRes['f8']+$tRes['f9']+$tRes['f12']+$tRes['f13']+$tRes['f15'];
	//check if you are building a cropland to level 1
	foreach($building->buildArray as $jobs) {
			if($jobs['type']==4){
				$cropL="99";
			}
      	}
if ($cropL<1){?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/> Tarea 2: Cereal<\/h1><br \/><i>&rdquo;Ahora tus súbditos tienen hambre de tanto trabajar todo el día. Amplía una granja para mejorar el abastecimiento de tus súbditos. Vuelve aquí una vez que el edificio esté terminado.&rdquo;<\/i><br \/><br \/><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía una granja.<\/div><br \/><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"farm\"><\/div>\n\t\t","number":"-2","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/> Tarea 2: Cereal<\/h1><br \/><i>&rdquo;Muy bien. Ahora tus súbditos vuelven a tener suficiente para comer...&rdquo;<\/i><br \/><br \/><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Tu recompensa:<\/p>1 día de Travian <b><span class=\"plus_g\">P</span><span class=\"plus_o\">l</span><span class=\"plus_g\">u</span><span class=\"plus_o\">s</span></b><\/div><br \/><span id=\"qst_accpt\"><a href=\"javascript: qst_next('','3');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"farm\"><\/div>\n\t\t","number":2,"reward":{"plus":1},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php }?>

<?php } elseif($_SESSION['qst']== 3){

$vName=$village->vname;
$defaultVillageName = "Aldea de ".$session->userinfo['username'];
$legacyDefaultVillageName = $session->userinfo['username']."'s village";
if ($vName==$defaultVillageName || $vName==$legacyDefaultVillageName){?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/>Tarea 3: El nombre de tu aldea<\/h1><br \/><i>&rdquo;Con la creatividad que tienes, puedes darle a tu aldea el nombre definitivo.\r\n<br \/><br \/>\r\nHaz clic en 'perfil' en el menú de la izquierda y luego selecciona 'cambiar perfil'...&rdquo;<\/i><br \/><br \/><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Cambia el nombre de tu aldea por algo bonito.<\/div><br \/><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"village_name\"><\/div>\n\t\t","number":"-3","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"qstd\"><h1> <img class=\"point\" src=\"img\/x.gif\" alt=\"\" title=\"\"\/>Tarea 3: El nombre de tu aldea<\/h1><br \/><i>&rdquo;¡Vaya, un gran nombre para su aldea! ¡Podría haber sido el nombre de mi aldea!...&rdquo;<\/i><br \/><br \/><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Tu recompensa:<\/p><img src=\"img\/x.gif\" class=\"r1\" alt=\"Madera\" title=\"Madera\" \/>30&nbsp;&nbsp;<img src=\"img\/x.gif\" class=\"r2\" alt=\"Barro\" title=\"Barro\" \/>60&nbsp;&nbsp;<img src=\"img\/x.gif\" class=\"r3\" alt=\"Hierro\" title=\"Hierro\" \/>30&nbsp;&nbsp;<img src=\"img\/x.gif\" class=\"r4\" alt=\"Cereal\" title=\"Cereal\" \/>20&nbsp;&nbsp;<\/div><br \/><span id=\"qst_accpt\"><a href=\"javascript: qst_next('','4');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"village_name\"><\/div>\n\t\t","number":3,"reward":{"wood":30,"clay":60,"iron":30,"crop":20},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":99}
<?php }?>

<?php } elseif($_SESSION['qst']== 4){

$rallypoint = $building->getTypeLevel(16);
	foreach($building->buildArray as $jobs) {
			if($jobs['type']==16){
				$rallypoint += 1;
			}
      	}
if ($rallypoint == 0){
?>
{"markup":"\n\t\<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 4: Plaza de reuniones<\/h4><div class=\"spoken\">&rdquo;En tus alrededores hay muchos lugares misteriosos para explorar. Para prepararte para estas aventuras, necesitas una plaza de reuniones. <br> La plaza de reuniones debe construirse en un sitio de construcción específico en el centro de tu aldea. El <a href=\"build.php?id=39\">sitio de construcción<\/a> en sí está curvado.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye la plaza de reuniones.<\/div><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"rally\"><\/div>\n\t\t","number":"-4","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}

<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 4: Plaza de reuniones<\/h4><div class=\"spoken\">&rdquo;¡Tu plaza de reuniones ha sido erigida! ¡Un buen paso hacia la dominación mundial!&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p>Plaza de reuniones completada al instante.<\/div><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','5');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"rally\"><\/div>\n\t\t","number":4,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}

<?php }?>

<?php } elseif($_SESSION['qst']== 5){

// Compare real player rank with submited rank
$temp['uid']=$session->userinfo['id'];
$displayarray = $database->getUserArray($temp['uid'],1);
$rRes=$ranking->getUserRank($displayarray['username']);
if ($rRes!=$rSubmited){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 5: Otros jugadores<\/h4><div class=\"spoken\">&rdquo;En Travian, juegas con muchos otros jugadores. Haz clic en 'estadísticas' en el menú superior para consultar tu clasificación e introdúcela aquí.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Busca tu clasificación en las estadísticas e introdúcela aquí.<\/div><input id=\"qst_val\" class=\"text\" type=\"text\" name=\"qstin\" \/><button type=\"button\" value=\"Completar tarea\" /> <input onclick=\"qst_next('','rank',document.getElementById('qst_val').value)\" type=\"button\" value=\"completar tarea\"\/><\/button><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"rank1\"><\/div>\n\t\t","number":5,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}

<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 5: Otros jugadores<\/h4><div class=\"spoken\">&rdquo;¡Exacto! Esa es tu clasificación.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">80<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">110<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">60<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">40<\/span><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','6');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"rank1\"><\/div>\n\t\t","number":5,"reward":{"wood":80,"clay":110,"iron":60,"crop":40},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 6){

//Check one of Iron Mines and one of Clay Pites are level 1 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=$tRes['f4']+$tRes['f7']+$tRes['f10']+$tRes['f11'];
$clayL=$tRes['f5']+$tRes['f6']+$tRes['f16']+$tRes['f18'];
if ($ironL<1 || $clayL<1){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 6: Dos órdenes de construcción<\/h4><div class=\"spoken\">&rdquo;Construye una mina de hierro y una barrera. De hierro y barro nunca se tiene suficiente. Gracias a la cuenta Plus que te di hace un momento, puedes dar ambas órdenes de inmediato&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><b>Orden:<\/b><\/p><ul><li> Amplía una mina de hierro.<\/li><li>Amplía una barrera.<\/li><\/ul><\/div><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"clay_iron\"><\/div>\n\t\t","number":-6,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 6: Dos órdenes de construcción<\/h4><div class=\"spoken\">&rdquo;Como habrás notado, las órdenes de construcción tardan bastante. El mundo de <?php echo SERVER_NAME; ?> seguirá girando incluso si estás desconectado. Incluso dentro de unos meses habrá muchas cosas nuevas por descubrir.\r\n<br \/><br \/>\r\nLo mejor es revisar tu aldea de vez en cuando y darles nuevas tareas a tus súbditos.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"1Fa\">150<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">160<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">130<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">130<\/span><\/div><br \/><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','7');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"clay_iron\"><\/div>\n\t\t","number":6,"reward":{"wood":150,"clay":160,"iron":130,"crop":130},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 7){

//Check message is viewed or no
if ($message->unread || $RB==true){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 7: Mensajes<\/h4><div class=\"spoken\">&rdquo;Puedes hablar con otros jugadores usando el sistema de mensajería. Te envié un mensaje. Léelo y vuelve aquí.\r\n<br \/><br \/>\r\nP.D.: No olvides: a la izquierda los informes, a la derecha los mensajes.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Lee tu nuevo mensaje.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"msg\"><\/div>\n\t\t","number":"-6","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"i2","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 7: Mensajes<\/h4><div class=\"spoken\">&rdquo;¿Lo recibiste? Muy bien.\r\n<br \/><br \/>\r\nAquí tienes algo de oro. Con el oro puedes hacer varias cosas, por ejemplo ampliar tu <b><font color=\"#71D000\">P<\/font><font color=\"#FF6F0F\">l<\/font><font color=\"#71D000\">u<\/font><font color=\"#FF6F0F\">s<\/font><\/b> o aumentar tu producción de recursos. Para ello, haz clic en <a href=\"plus.php?id=3\"><font color=\"#000000\"><?php echo SERVER_NAME; ?><\/font> <b><font color=\"#71D000\">P<\/font><font color=\"#FF6F0F\">l<\/font><font color=\"#71D000\">u<\/font><font color=\"#FF6F0F\">s<\/font><\/b><\/a> en el menú de la izquierda.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"gold\"><img src=\"img\/x.gif\" class=\"gold\" title=\"Oro\"> 20<\/span><\/div><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','8');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"msg\"><\/div>\n\t\t","number":6,"reward":{"gold":20},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 8){

$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=0;$clayL=0;$woodL=0;$cropL=0;

if($tRes['f1']>0){$woodL++;};
if($tRes['f3']>0){$woodL++;};
if($tRes['f14']>0){$woodL++;};
if($tRes['f17']>0){$woodL++;};
if($tRes['f5']>0){$clayL++;};
if($tRes['f6']>0){$clayL++;};
if($tRes['f16']>0){$clayL++;};
if($tRes['f18']>0){$clayL++;};
if($tRes['f4']>0){$ironL++;};
if($tRes['f7']>0){$ironL++;};
if($tRes['f10']>0){$ironL++;};
if($tRes['f11']>0){$ironL++;};
if($tRes['f2']>0){$cropL++;};
if($tRes['f8']>0){$cropL++;};
if($tRes['f9']>0){$cropL++;};
if($tRes['f12']>0){$cropL++;};
if($tRes['f13']>0){$cropL++;};
if($tRes['f15']>0){$cropL++;};

if ($ironL<2 || $clayL<2 || $woodL<2 || $cropL<2){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 8: ¡Uno de cada!<\/h4><div class=\"spoken\">&rdquo;¡En Travian siempre hay algo que hacer! Mientras esperas el resultado de tu aventura, construye un leñador, una barrera, una mina de hierro y una granja adicionales de nivel 1.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía un campo más de cada recurso hasta el nivel 1.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"res_one_of_each\"><\/div>\n\t\t","number":-8,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 8: ¡Uno de cada!<\/h4><div class=\"spoken\">&rdquo;Muy bien, un gran desarrollo de la producción de recursos.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">100<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">40<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">40<\/span><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','9');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"res_one_of_each\"><\/div>\n\t\t","number":8,"reward":{"wood":100,"clay":120,"iron":40,"crop":40},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>


<?php } elseif($_SESSION['qst']== 9){

$getvID = $database->getVillageID($session->uid);
$nvillage = $database->getFieldDistance($getvID);
$ncoor = $database->getCoor($nvillage);
$nvillagename = $database->getVillageField($nvillage,"name");
if ($x!=$ncoor['x'] or $y!=$ncoor['y']){

?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 9: ¡Vecinos!<\/h4><div class=\"spoken\">&rdquo;A tu alrededor hay muchas aldeas diferentes. Una de ellas se llama <b><?php echo $nvillagename; ?><\/b>. Haz clic en 'mapa' en el menú superior y busca esa aldea. El nombre de las aldeas de tus vecinos se puede ver pasando el mouse sobre ellas.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Busca las coordenadas de <b><?php echo $nvillagename; ?><\/b> e introdúcelas aquí.<\/div><div class=\"coordinatesInput\"><div class=\"xCoord\"><label for=\"xCoordInputQuest\">X:<\/label><input maxlength=\"4\" value=\"\" id=\"qst_val\" name=\"qstin\" id=\"xCoordInputQuest\" class=\"text coordinates x \"><\/div><div class=\"yCoord\"><label for=\"yCoordInputQuest\">Y:<\/label><input maxlength=\"4\" value=\"\" name=\"qstin\" id=\"yCoordInputQuest\" class=\"text coordinates y \"><\/div><div class=\"clear\"><\/div><\/div><button type=\"button\" value=\"completar tarea\" class=\"coordinatesButton\" onclick=\"qst_next2('','coor',document.getElementById('qst_val').value,document.getElementById('yCoordInputQuest').value)\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"><\/div><\/div><\/div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"><\/div><\/div><\/div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"><\/div><\/div><\/div><\/div><div class=\"button-contents\">completar tarea<\/div><\/div><\/button><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"neighbour\"><\/div>\n\t\t","number":-9,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 9: ¡Vecinos!<\/h4><div class=\"spoken\">&rdquo;¡Exacto, ahí está la aldea <b> <?php echo $nvillagename;?> <\/b>! Tienes tantos recursos como los que reúne esa aldea. Bueno, casi tantos...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">60<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">30<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">40<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">90<\/span><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','10');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"neighbour\"><\/div>\n\t\t","number":9,"reward":{"wood":60,"clay":30,"iron":40,"crop":90},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 10){

//Check additional of each resource upgraded to lvl1 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=0;$clayL=0;$woodL=0;$cropL=0;
if($tRes['f4']>0){$ironL++;};if($tRes['f7']>0){$ironL++;};if($tRes['f10']>0){$ironL++;};if($tRes['f11']>0){$ironL++;}
if($tRes['f5']>0){$clayL++;};if($tRes['f6']>0){$clayL++;};if($tRes['f16']>0){$clayL++;};if($tRes['f18']>0){$clayL++;}
if($tRes['f1']>0){$woodL++;};if($tRes['f3']>0){$woodL++;};if($tRes['f14']>0){$woodL++;};if($tRes['f17']>0){$woodL++;}
if($tRes['f2']>0){$cropL++;};if($tRes['f8']>0){$cropL++;};if($tRes['f9']>0){$cropL++;};if($tRes['f12']>0){$cropL++;};if($tRes['f13']>0){$cropL++;};if($tRes['f15']>0){$cropL++;}
if ($ironL<4 || $clayL<4 || $woodL<4 || $cropL<6){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 10: ¡Todo al nivel 1!<\/h4><div class=\"spoken\">&rdquo;Ahora deberíamos aumentar un poco tu producción de recursos. Amplía todos tus campos de recursos hasta el nivel 1.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía todos los campos de recursos hasta el nivel 1.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"res_all_one\"><\/div>\n\t\t","number":-12,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 10: ¡Todo al nivel 1!<\/h4><div class=\"spoken\">&rdquo;Muy bien, tu producción de recursos prospera. <br><br>Pronto podremos empezar a construir edificios en la aldea.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">75<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">30<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">50<\/span><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','11');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"res_all_one\"><\/div>\n\t\t","number":12,"reward":{"wood":75,"clay":120,"iron":30,"crop":50},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 11){

//Check player Descriptions for [#0]
$Dave= strrpos ($uArray['desc1'],'[#0]');
$Dave2=strrpos ($uArray['desc2'],'[#0]');
if (!is_numeric($Dave) and !is_numeric($Dave2)){?>
{"markup":"\n\t\t<div id=\"qstd\"><h4>Tarea 11: Paloma de la paz<\/h4><div class=\"spoken\">&rdquo;Los primeros días después de registrarte, estás protegido de los ataques de otros jugadores. Puedes ver cuánto dura esta protección agregando el código  <b>[#0]<\/b> a tu perfil.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Escribe el código [#0] en tu perfil agregándolo a uno de los dos campos de descripción.<\/div><span id=\"qst_accpt\"><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"medal\"><\/div>\n\t\t","number":"-11","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 11: Paloma de la paz<\/h4><div class=\"spoken\">&rdquo;¡Bien hecho! Ahora todos pueden ver qué gran guerrero se acerca al mundo.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">120<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">200<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">140<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">100<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','12');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"medal\"><\/div>\n\t\t","number":11,"reward":{"wood":120,"clay":200,"iron":140,"crop":100},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 12){

//Check cranny builded or no
$cranny = $building->getTypeLevel(23);
if ($cranny == 0){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 12: Escondite<\/h4><div class=\"spoken\">&rdquo;Es hora de construir un escondite. El mundo de Travian es peligroso. Muchos jugadores viven robando los recursos de otros jugadores. Construye un escondite para ocultar parte de tus recursos de los enemigos.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un escondite.</div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"cranny\"><\/div>\n\t\t","number":-12,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 12: Escondite<\/h4><div class=\"spoken\">&rdquo;Bien hecho, ahora es mucho más difícil para tus malvados compañeros de juego saquear tu aldea.\r\n<br \/><br \/>\r\nSi eres atacado, tus aldeanos ocultarán los recursos en el escondite por su cuenta.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">150<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">180<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">30<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">130<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','13');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"cranny\"><\/div>\n\t\t","number":12,"reward":{"wood":150,"clay":180,"iron":30,"crop":130},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 13){

//Check one of each resource is lvl2 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=0;$clayL=0;$woodL=0;$cropL=0;
if($tRes['f4']>1){$ironL++;};if($tRes['f7']>1){$ironL++;};if($tRes['f10']>1){$ironL++;};if($tRes['f11']>1){$ironL++;}
if($tRes['f5']>1){$clayL++;};if($tRes['f6']>1){$clayL++;};if($tRes['f16']>1){$clayL++;};if($tRes['f18']>1){$clayL++;}
if($tRes['f1']>1){$woodL++;};if($tRes['f3']>1){$woodL++;};if($tRes['f14']>1){$woodL++;};if($tRes['f17']>1){$woodL++;}
if($tRes['f2']>1){$cropL++;};if($tRes['f8']>1){$cropL++;};if($tRes['f9']>1){$cropL++;};if($tRes['f12']>1){$cropL++;};if($tRes['f13']>1){$cropL++;};if($tRes['f15']>1){$cropL++;}
if ($ironL<1 || $clayL<1 || $woodL<1 || $cropL<1){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 13: ¡A nivel 2!<\/h4><div class=\"spoken\">&rdquo;¡En Travian siempre hay algo que hacer! Amplía un leñador, una barrera, una mina de hierro y una granja al nivel 2 cada uno.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía un campo de cada recurso hasta el nivel 2.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"res_two_of_each\"><\/div>\n\t\t","number":"-13","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 13: ¡A nivel 2!<\/h4><div class=\"spoken\">&rdquo;¡Muy bien, tu aldea crece y prospera!&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">60<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">50<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">40<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">30<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','14');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"res_two_of_each\"><\/div>\n\t\t","number":13,"reward":{"wood":60,"clay":50,"iron":40,"crop":30},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 14){

//Check player submited number Barracks cost lumber
if ($lSubmited!=210){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 14: Instrucciones<\/h4><div class=\"spoken\">&rdquo;En las instrucciones del juego puedes encontrar breves textos informativos sobre diferentes edificios y tipos de unidades. Haz clic en el libro negro en la esquina inferior izquierda para averiguar cuánta madera se necesita para el cuartel.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Indica cuánta madera cuesta el cuartel.<\/div><input id=\"qst_val\" class=\"text\" type=\"text\" name=\"qstin\" onkeypress=\"if (event.keyCode==13) {qst_next('','lumber',document.getElementById('qst_val').value);}\"> <button type=\"button\" value=\"completar tarea\" onclick=\"qst_next('','lumber',document.getElementById('qst_val').value)\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"><\/div><\/div><\/div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"><\/div><\/div><\/div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"><\/div><\/div><\/div><\/div><div class=\"button-contents\">completar tarea<\/div><\/div><\/button><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"cost\"><\/div>\n\t\t","number":"-14","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 14: Instrucciones<\/h4><div class=\"spoken\">&rdquo;¡Exacto! El cuartel cuesta 210 de madera.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">50<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">30<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">60<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">20<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','15');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"cost\"><\/div>\n\t\t","number":14,"reward":{"wood":50,"clay":30,"iron":60,"crop":20},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 15){

//Check main building lvl is 3 or upper
$mainbuilding = $building->getTypeLevel(15);
if ($mainbuilding<3){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 15: Edificio principal<\/h4><div class=\"spoken\">&rdquo;Tus maestros de obra necesitan un edificio principal de nivel 3 para erigir edificios importantes como el mercado o el cuartel.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía tu edificio principal al nivel 3.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"main\"><\/div>\n\t\t","number":-15,"reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 15: Edificio principal<\/h4><div class=\"spoken\">&rdquo;Bien hecho. El edificio principal de nivel 3 ha sido completado.\r\n<br><br>\r\nCon esta mejora, tus maestros de obra no solo pueden construir más tipos de edificios, sino también hacerlo más rápido.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">75<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">75<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">40<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">40<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','16');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"main\"><\/div>\n\t\t","number":15,"reward":{"wood":75,"clay":75,"iron":40,"crop":40},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 16){

// Compare real player rank with submited rank
$temp['uid']=$session->userinfo['id'];
$displayarray = $database->getUserArray($temp['uid'],1);
$rRes=$ranking->getUserRank($displayarray['username']);
if ($rRes!=$rSubmited){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 16: ¡Avanzando!<\/h4><div class=\"spoken\">&rdquo;Consulta de nuevo tu clasificación en las estadísticas de jugadores y disfruta de tu progreso.&rdquo;</div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Busca tu clasificación en las estadísticas e introdúcela aquí.<\/div><input id=\"qst_val\" class=\"text\" type=\"text\" name=\"qstin\" onkeypress=\"if (event.keyCode==13) {qst_next('','rank',document.getElementById('qst_val').value);}\"> <button type=\"button\" value=\"completar tarea\" onclick=\"qst_next('','rank',document.getElementById('qst_val').value)\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"><\/div><\/div><\/div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"><\/div><\/div><\/div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"><\/div><\/div><\/div><\/div><div class=\"button-contents\">completar tarea<\/div><\/div><\/button><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"rank2\"><\/div>\n\t\t","number":"-16","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 16: ¡Avanzando!<\/h4><div class=\"spoken\">&rdquo;¡Bien hecho! Esa es tu clasificación actual.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">100<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">90<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">100<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img/x.gif\" alt=\"Cereal\">60<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','17');\">Continuar con la siguiente tarea<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"rank2\"><\/div>\n\t\t","number":16,"reward":{"wood":100,"clay":90,"iron":100,"crop":60},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }?>

<?php } elseif($_SESSION['qst']== 17){

// Ask from plyer ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 17: Armas o riquezas<\/h4><div class=\"spoken\">&rdquo;Ahora tienes que tomar una decisión: comerciar pacíficamente o convertirte en un temido guerrero. <br><br> Para el mercado necesitas un granero, para las tropas necesitas un cuartel.&rdquo;<\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><button type=\"button\" value=\"Economía\" class=\"qb1 granary_barracks\" onclick=\"javascript: qst_next('','21');\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"><\/div><\/div><\/div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"><\/div><\/div><\/div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"><\/div><\/div><\/div><\/div><div class=\"button-contents\">Economía<\/div><\/div><\/button><button type=\"button\" value=\"Ejército\" class=\"qb2 granary_barracks\" onclick=\"javascript: qst_next('','18');\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"><\/div><\/div><\/div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"><\/div><\/div><\/div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"><\/div><\/div><\/div><\/div><div class=\"button-contents\">Ejército<\/div><\/div><\/button><div class=\"clear\"><\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks\"><\/div>\n\t\t","number":"-17","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}

<?php } elseif($_SESSION['qst']== 18){
$database->updateUserField($_SESSION['username'],'quest_choose','1',0);
// Checking barracks builded or no
$barrack = $building->getTypeLevel(19);
if ($barrack==0){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Cuartel<\/h4><div class=\"spoken\">&rdquo;Una elección valiente. Para entrenar tropas necesitas un cuartel.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un cuartel.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks2\"><\/div>\n\t\t","number":"-18","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Cuartel<\/h4><div class=\"spoken\">&rdquo;Bien hecho... Los mejores instructores de todo el país se han reunido para entrenar las habilidades de combate de tus hombres hasta la perfección.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">70<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">100<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">90<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">100<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','19');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks2\"><\/div>\n\t\t","number":18,"reward":{"wood":70,"clay":100,"iron":90,"crop":100},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } elseif($_SESSION['qst']== 19){

// Checking 2 warrior trained or no
$units = $village->unitall;
$unarray=array("","Legionario", "Luchador de porra","Falange");
$unarray2=array("","u1", "u11","u21");
if ($units[$unarray2[$session->userinfo['tribe']]]<2){ ?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 19: ¡Entrena!<\/h4><div class=\"spoken\">&rdquo;Ahora que tienes un cuartel, puedes empezar a entrenar tropas. Entrena dos <?php echo $unarray[$session->userinfo['tribe']];?>.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Entrena 2 <?php echo $unarray[$session->userinfo['tribe']];?>.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"units\"><\/div>\n\t\t","number":"-19","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 19: ¡Entrena!<\/h4><div class=\"spoken\">&rdquo;Se han sentado las bases de tu glorioso ejército.<br \/><br \/>\r\nAntes de enviar tu ejército a saquear, deberías consultar el <a href=\"warsim.php\">Simulador de combate<\/a> para ver cuántas tropas necesitas para vencer a una rata sin sufrir bajas.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">300<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">320<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">360<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">570<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','20');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"units\"><\/div>\n\t\t","number":19,"reward":{"wood":300,"clay":320,"iron":360,"crop":570},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } elseif($_SESSION['qst']== 20){

$unarray=array("","muralla", "muro de tierra","empalizada");


$wall = $village->resarray['f40'];
if ($wall==0){?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 20: Construye tu <?php echo $unarray[$session->userinfo['tribe']];?><\/h4><div class=\"spoken\">&rdquo;Ahora que has entrenado algunos soldados, también deberías construir tu <?php echo $unarray[$session->userinfo['tribe']];?>. Aumenta la defensa base y tus soldados recibirán una bonificación defensiva.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye tu <?php echo $unarray[$session->userinfo['tribe']];?>.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"wall<?php echo $session->userinfo['tribe'];?>\"><\/div>\n\t\t","number":"-23","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 20: Construye tu <?php echo $unarray[$session->userinfo['tribe']];?><\/h4><div class=\"spoken\">&rdquo;¡Eso es lo que digo! Tu <?php echo $unarray[$session->userinfo['tribe']];?> es muy útil. Aumenta la defensa de las tropas en la aldea.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">200<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">80<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','24');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"wall<?php echo $session->userinfo['tribe'];?>\"><\/div>\n\t\t","number":23,"reward":{"wood":80,"clay":90,"iron":60,"crop":40},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } elseif($_SESSION['qst']==21){
$database->updateUserField($_SESSION['username'],'quest_choose','2',0);
// Checking granary builded or no
$granary = $building->getTypeLevel(11);
if ($granary ==0){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Comercio y economía<\/h4><div class=\"spoken\">&rdquo;Comercio y economía fue tu elección. ¡Seguro que te esperan tiempos dorados!&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un granero.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks1\"><\/div>\n\t\t","number":"-20","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Comercio y economía<\/h4><div class=\"spoken\">&rdquo;¡Bien hecho! Con el granero puedes almacenar más cereal.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">80<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">90<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">60<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">40<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','22');\">Continuar con la siguiente tarea<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks1\"><\/div>\n\t\t","number":20,"reward":{"wood":80,"clay":90,"iron":60,"crop":40},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } elseif($_SESSION['qst']== 22){

// Checking warehouse builded or no
$warehouse = $building->getTypeLevel(10);
if ($warehouse==0){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 19: Almacén<\/h4><div class=\"spoken\">&rdquo;El cereal no es el único recurso que hay que guardar; otros recursos también se pueden desperdiciar si no se almacenan correctamente. ¡Construye un almacén!&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un almacén.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"warehouse\"><\/div>\n\t\t","number":"-21","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 19: Almacén<\/h4><div class=\"spoken\">&rdquo;Bien hecho, tu almacén está completo...&rdquo;<\/i><br \/>Ahora has cumplido todos los requisitos necesarios para construir un mercado.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">70<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">90<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">50<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','23');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"warehouse\"><\/div>\n\t\t","number":21,"reward":{"wood":70,"clay":120,"iron":90,"crop":50},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } elseif($_SESSION['qst']== 23){

// Checking market builded or no
$market = $building->getTypeLevel(17);
if ($market==0){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 20: Mercado<\/h4><div class=\"spoken\">&rdquo;Construye un mercado para poder comerciar con otros jugadores.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un mercado.<\/div><span id=\"qst_accpt\"><\/span><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"market\"><\/div>\n\t\t","number":"-22","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 20: Mercado<\/h4><div class=\"spoken\">&rdquo;El mercado ha sido completado. Ahora puedes crear tus propias ofertas y aceptar ofertas de otros jugadores. Al crear tus propias ofertas, deberías pensar en ofrecer lo que otros jugadores más necesitan para obtener más ganancias.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1200<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">200<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">200<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">450<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a class=\"arrow\" href=\"javascript: qst_next('','24');\">Continuar con la siguiente tarea.<\/a><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"market\"><\/div>\n\t\t","number":22,"reward":{"wood":1200,"clay":200,"iron":200,"crop":450},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } ?>

<?php } ?>

<?php
if ($dataarray[0]==1){
$ff25 = "<div class=none><b>¡Todo al nivel 2!<\/b><\/div>";
} else { $ff25 = "<a onclick=qst_next('','225')>¡Todo al nivel 2!<\/a>"; }

if($uArray['quest_choose'] == 2){
if ($dataarray[1]==1){
$ff26 = "<div class=none><b>Cuartel al nivel 1.<\/b><\/div>";
} else { $ff26 = "<a onclick=qst_next('','226')>Cuartel al nivel 1.<\/a>"; }
}else{
if ($dataarray[1]==1){
$ff26 = "<div class=none><b>Mercado al nivel 1.<\/b><\/div>";
} else { $ff26 = "<a onclick=qst_next('','226')>Mercado al nivel 1.<\/a>"; }
}
if ($dataarray[2]==1){
$ff27 = "<div class=none><b>Embajada<\/b><\/div>";
} else { $ff27 = "<a onclick=qst_next('','227')>Embajada<\/a>"; }

if ($dataarray[3]==1){
$ff28 = "<div class=none><b>Alianza<\/b><\/div>";
} else { $ff28 = "<a onclick=qst_next('','228')>Alianza<\/a>"; }

if ($dataarray[4]==1){
$ff29 = "<div class=none><b>Edificio principal al nivel 5<\/b><\/div>";
} else { $ff29 = "<a onclick=qst_next('','229')>Edificio principal al nivel 5<\/a>"; }

if ($dataarray[5]==1){
$ff30 = "<div class=none><b>Granero al nivel 3<\/b><\/div>";
} else { $ff30 = "<a onclick=qst_next('','330')>Granero al nivel 3<\/a>"; }

if ($dataarray[6]==1){
$ff31 = "<div class=none><b>Almacén al nivel 7<\/b><\/div>";
} else { $ff31 = "<a onclick=qst_next('','331')>Almacén al nivel 7<\/a>"; }

if ($dataarray[7]==1){
$ff32 = "<div class=none><b>¡Todo al nivel 5!<\/b><\/div>";
} else { $ff32 = "<a onclick=qst_next('','332')>¡Todo al nivel 5!<\/a>"; }

if ($dataarray[8]==1){
$ff33 = "<div class=none><b>¿Palacio o residencia?<\/b><\/div>";
} else { $ff33 = "<a onclick=qst_next('','333')>¿Palacio o residencia?<\/a>"; }

if ($dataarray[9]==1){
$ff34 = "<div class=none><b>3 colonos<\/b><\/div>";
} else { $ff34 = "<a onclick=qst_next('','334')>3 colonos<\/a>"; }

if ($dataarray[10]==1){
$ff35 = "<div class=none><b>Nueva aldea<\/b><\/div>";
} else { $ff35 = "<a onclick=qst_next('','335')>Nueva aldea<\/a>"; }


if($_SESSION['qst']== 24){ ?>

{"markup":"\n\t\t<div id=\"popup3\"><div><b>Todavía quedan muchas tareas por completar; puedes terminarlas a tu propio ritmo:</b><ul><input type=\"hidden\" id=\"qst_val\" value=\"\"><li><?php echo $ff25;?><\/li><li><?php echo $ff26;?><\/li><li><?php echo $ff27;?><\/li><li><?php echo $ff28;?><\/li><li><?php echo $ff29;?><\/li><li><?php echo $ff30;?><\/li><li><?php echo $ff31;?><\/li><li><?php echo $ff32;?><\/li><li><?php echo $ff33;?><\/li><li><?php echo $ff34;?><\/li><li><?php echo $ff35;?><\/li><\/ul><\/div><\/div>\n\t\t\n\t\t","number":"-24","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}


<?php } elseif($_SESSION['qst']== 25){

$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=0;$clayL=0;$woodL=0;$cropL=0;
if($tRes['f4']>1){$ironL++;};if($tRes['f7']>1){$ironL++;};if($tRes['f10']>1){$ironL++;};if($tRes['f11']>1){$ironL++;}
if($tRes['f5']>1){$clayL++;};if($tRes['f6']>1){$clayL++;};if($tRes['f16']>1){$clayL++;};if($tRes['f18']>1){$clayL++;}
if($tRes['f1']>1){$woodL++;};if($tRes['f3']>1){$woodL++;};if($tRes['f14']>1){$woodL++;};if($tRes['f17']>1){$woodL++;}
if($tRes['f2']>1){$cropL++;};if($tRes['f8']>1){$cropL++;};if($tRes['f9']>1){$cropL++;};if($tRes['f12']>1){$cropL++;};if($tRes['f13']>1){$cropL++;};if($tRes['f15']>1){$cropL++;}
if ($ironL<4 || $clayL<4 || $woodL<4 || $cropL<6){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¡Todo al nivel 2!<\/h4><div class=\"spoken\">&rdquo;¡Es hora de nuevo de ampliar los pilares del poder y la riqueza! Esta vez el nivel 1 no es suficiente... llevará un tiempo, pero al final valdrá la pena. Amplía todos tus campos de recursos al nivel 2...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Amplía todos los campos de recursos al nivel 2.<\/div><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_res_all_two\" id=\"qstbg\"><\/div>\n\t\t","number":"-25","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¡Todo al nivel 2!<\/h4><div class=\"spoken\">&rdquo;¡Felicidades! Tu aldea crece y prospera...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources gold\" alt=\"Oro de Travian\"><img alt=\"Oro de Travian\" class=\"gold\" src=\"img/x.gif\">15<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','25');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_res_all_two\"><\/div>\n\t\t","number":25,"reward":{"gold":15},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 26){

if($uArray['quest_choose'] == 2){

// Checking barracks builded or no
$barrack = $building->getTypeLevel(19);
if ($barrack == 0){ ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Cuartel<\/h4><div class=\"spoken\">&rdquo;Para entrenar tropas necesitas un cuartel.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un cuartel.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">70<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">100<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">90<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">100<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks2\"><\/div>\n\t\t","number":"-26","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Tarea 18: Cuartel<\/h4><div class=\"spoken\">&rdquo;Bien hecho... Los mejores instructores de todo el país se han reunido para entrenar las habilidades de combate de tus hombres hasta la perfección.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\"><input type=\"hidden\" id=\"qst_val\" value=\"2\" \/>Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">70<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">100<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">90<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">100<\/span><\/div><div class=\"clear\"><\/div><br><span id=\"qst_accpt\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','26');\" class=\"qri arrow\">Recoger recompensa<\/a></br><\/span><\/div>\n\t\t<div id=\"qstbg\" class=\"granary_barracks2\"><\/div>\n\t\t","number":26,"reward":{"wood":70,"clay":100,"iron":90,"crop":100},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }

}else{

$market = $building->getTypeLevel(17);
if ($market == 0){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Mercado al nivel 1.<\/h4><div class=\"spoken\">&rdquo;Construye un mercado de nivel 1.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un mercado.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">140<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">200<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_market_barracks1\" id=\"qstbg\"><\/div>\n\t\t","number":"-26","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Mercado al nivel 1.<\/h4><div class=\"spoken\">&rdquo;El mercado ha sido completado. Ahora puedes crear tus propias ofertas y aceptar ofertas de otros jugadores. Al crear tus propias ofertas, deberías pensar en ofrecer lo que otros jugadores más necesitan para obtener más ganancias.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">140<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">200<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','26');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_market_barracks1\"><\/div>\n\t\t","number":26,"reward":{"wood":140,"clay":200,"iron":180,"crop":200},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php }}$_SESSION['qst'] = 24; ?>

<?php } elseif($_SESSION['qst']== 27){

$embassy = $building->getTypeLevel(18);
if ($embassy == 0){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Embajada<\/h4><div class=\"spoken\">&rdquo;El mundo de Travian es peligroso. Ya construiste un escondite para proteger tus recursos de los atacantes. Una buena alianza te dará una protección aún mejor. Para aceptar invitaciones de alianzas, construye una embajada.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye una embajada.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">200<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">80<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_dispatch\" id=\"qstbg\"><\/div>\n\t\t","number":"-27","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Embajada<\/h4><div class=\"spoken\">&rdquo;Bien hecho, la construcción de tu embajada ha terminado. Ahora puedes aceptar invitaciones de alianzas. Para ello, simplemente haz clic en la embajada.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">200<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">120<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">80<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','27');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_dispatch\"><\/div>\n\t\t","number":27,"reward":{"wood":200,"clay":120,"iron":180,"crop":80},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 28){

$aid = $session->alliance;
$allianceinfo = $database->getAlliance($aid);
if ($aid['alliance'] == 0){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Alianza<\/h4><div class=\"spoken\">&rdquo;El trabajo en equipo es importante en Travian. Los jugadores que trabajan juntos se organizan en alianzas. Consigue una invitación de una alianza en tu región y únete a ella. También puedes fundar tu propia alianza. Para ello necesitas una embajada de nivel 3.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Únete a una alianza o funda una por tu cuenta.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">240<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">280<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">100<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_alliance\" id=\"qstbg\"><\/div>\n\t\t","number":"-28","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Alianza<\/h4><div class=\"spoken\">&rdquo;¡Muy bien! Ahora formas parte de una unión llamada <b><?php echo $allianceinfo['tag'];?><\/b>, y al ser miembro de esa alianza progresarás más rápido...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">240<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">280<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">180<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">100<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','28');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_alliance\"><\/div>\n\t\t","number":28,"reward":{"wood":240,"clay":280,"iron":180,"crop":100},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 29){

$mainbuilding = $building->getTypeLevel(15);
if ($mainbuilding < 5){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Edificio principal al nivel 5<\/h4><div class=\"spoken\">&rdquo;Para poder construir un palacio o una residencia, necesitarás un edificio principal de nivel 5.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Mejora tu edificio principal al nivel 5.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">750<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_main_on_five\" id=\"qstbg\"><\/div>\n\t\t","number":"-29","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Edificio principal al nivel 5<\/h4><div class=\"spoken\">&rdquo;El edificio principal ya está en nivel 5 y puedes construir un palacio o una residencia...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">750<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','29');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_main_on_five\"><\/div>\n\t\t","number":29,"reward":{"wood":600,"clay":750,"iron":600,"crop":300},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 30){

$granary = $building->getTypeLevel(11);
if ($granary < 3){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Granero al nivel 3<\/h4><div class=\"spoken\">&rdquo;Para no perder cereal, deberías mejorar tu granero.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Mejora tu granero al nivel 3.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">900<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">850<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_granary_on_three\" id=\"qstbg\"><\/div>\n\t\t","number":"-30","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Granero al nivel 3<\/h4><div class=\"spoken\">&rdquo;El granero ya está en nivel 3...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">900<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">850<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','30');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_granary_on_three\"><\/div>\n\t\t","number":30,"reward":{"wood":900,"clay":850,"iron":600,"crop":300},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 31){

$warehouse = $building->getTypeLevel(10);
if ($warehouse < 7){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Almacén al nivel 7<\/h4><div class=\"spoken\">&rdquo;Para asegurarte de que tus recursos no se desborden, deberías mejorar tu almacén.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Mejora tu almacén al nivel 7.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1800<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2000<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1650<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">800<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_warehouse_on_seven\" id=\"qstbg\"><\/div>\n\t\t","number":"-31","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Almacén al nivel 7<\/h4><div class=\"spoken\">&rdquo;El almacén se ha mejorado al nivel 7...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1800<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2000<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1650<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">800<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','31');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_warehouse_on_seven\"><\/div>\n\t\t","number":31,"reward":{"wood":1800,"clay":2000,"iron":1650,"crop":800},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 32){

//Check one of each resource is lvl5 or upper
$tRes = $database->getResourceLevel($session->villages[0]);
$ironL=0;$clayL=0;$woodL=0;$cropL=0;
if($tRes['f4']>4){$ironL++;};if($tRes['f7']>4){$ironL++;};if($tRes['f10']>4){$ironL++;};if($tRes['f11']>4){$ironL++;}
if($tRes['f5']>4){$clayL++;};if($tRes['f6']>4){$clayL++;};if($tRes['f16']>4){$clayL++;};if($tRes['f18']>4){$clayL++;}
if($tRes['f1']>4){$woodL++;};if($tRes['f3']>4){$woodL++;};if($tRes['f14']>4){$woodL++;};if($tRes['f17']>4){$woodL++;}
if($tRes['f2']>4){$cropL++;};if($tRes['f8']>4){$cropL++;};if($tRes['f9']>4){$cropL++;};if($tRes['f12']>4){$cropL++;};if($tRes['f13']>4){$cropL++;};if($tRes['f15']>4){$cropL++;}
if ($ironL<4 || $clayL<4 || $woodL<4 || $cropL<6){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¡Todo al nivel 5!<\/h4><div class=\"spoken\">&rdquo;Siempre necesitarás más recursos. Los campos de recursos son bastante caros, pero siempre valen la pena a largo plazo.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Mejora todos los campos de recursos al nivel 5.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">1800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1950<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">1200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_res_all_five\" id=\"qstbg\"><\/div>\n\t\t","number":"-32","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¡Todo al nivel 5!<\/h4><div class=\"spoken\">&rdquo;Todos los recursos llegaron al nivel 5, la producción de la aldea ha aumentado y ha dado un paso adelante...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">1800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1950<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">1200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','32');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_res_all_five\"><\/div>\n\t\t","number":32,"reward":{"wood":1600,"clay":1800,"iron":1950,"crop":1200},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 33){

$residence = $building->getTypeLevel(25);
$palace = $building->getTypeLevel(26);
if($palace >= 10){
$text = "El palacio ";
}else if($residence >= 10){
$text = "La residencia ";
}
if ($residence<10 && $palace<10){?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¿Palacio o residencia?<\/h4><div class=\"spoken\">&rdquo;Para fundar una nueva aldea necesitarás colonos. Puedes entrenarlos tanto en un palacio como en una residencia.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Construye un palacio o una residencia hasta el nivel 10.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">3400<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">3600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">2200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_palace_or_residence\" id=\"qstbg\"><\/div>\n\t\t","number":"-33","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>¿Palacio o residencia?<\/h4><div class=\"spoken\">&rdquo;¡<?php echo $text; ?>ha alcanzado el nivel 10! Ahora puedes entrenar colonos y fundar tu segunda aldea. Ten en cuenta los puntos de cultura...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">3400<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">3600<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">2200<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','33');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_palace_or_residence\"><\/div>\n\t\t","number":33,"reward":{"wood":3400,"clay":2800,"iron":3600,"crop":2200},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 34){

// Checking 3 settlers trained or no
$units = $village->unitall;
$unarray2=array("","u10", "u20","u30");
if ($units[$unarray2[$session->userinfo['tribe']]]<3){ $cp = CP;?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>3 colonos<\/h4><div class=\"spoken\">&rdquo;Para fundar una nueva aldea necesitarás colonos. Puedes entrenarlos en el palacio o la residencia.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Entrena 3 colonos.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1050<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">900<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">750<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_three_settlers\" id=\"qstbg\"><\/div>\n\t\t","number":"-34","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>3 colonos<\/h4><div class=\"spoken\">&rdquo;Se entrenaron 3 colonos. Para fundar una nueva aldea necesitas al menos <?php echo number_format(travianCultureRequiredForVillageCount(2, CP), 0, ',', '.');?> puntos de cultura...&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1050<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">800<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">900<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">750<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','34');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_three_settlers\"><\/div>\n\t\t","number":34,"reward":{"wood":1050,"clay":800,"iron":900,"crop":750},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>

<?php } elseif($_SESSION['qst']== 35){

$vil = $database->getProfileVillages($session->uid);
if (count($vil)<2){ ?>

{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Nueva aldea<\/h4><div class=\"spoken\">&rdquo;Hay muchas casillas vacías en el mapa. Encuentra una que te convenga y funda una nueva aldea.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Orden:<\/p>Funda una nueva aldea.<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2000<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1800<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">1300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"javascript: qst_next('','24');\" class=\"qle arrow\">Volver al resumen.<\/a><div class=\"clear\"><\/div><\/div><\/div>\n\t\t<div class=\"quest_new_village\" id=\"qstbg\"><\/div>\n\t\t","number":"-35","reward":false,"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php $_SESSION['qstnew']='0'; }else{ $_SESSION['qstnew']='1'; ?>
{"markup":"\n\t\t<div id=\"popup3\"><div id=\"qstd\"><h4>Nueva aldea<\/h4><div class=\"spoken\">&rdquo;¡Estoy orgulloso de ti! Ahora tienes dos aldeas y todas las posibilidades para construir un poderoso imperio. Te deseo suerte con esto.&rdquo;<\/div><div class=\"rew\"><p class=\"ta_aw\">Recompensa:<\/p><span class=\"resources r1\"><img class=\"r1\" title=\"Madera\" src=\"img\/x.gif\" alt=\"Madera\">1600<\/span><span class=\"resources r2\"><img class=\"r2\" title=\"Barro\" src=\"img\/x.gif\" alt=\"Barro\">2000<\/span><span class=\"resources r3\"><img class=\"r3\" title=\"Hierro\" src=\"img\/x.gif\" alt=\"Hierro\">1800<\/span><span class=\"resources r4\"><img class=\"r4\" title=\"Cereal\" src=\"img\/x.gif\" alt=\"Cereal\">1300<\/span><div class=\"clear\"><\/div><\/div><input type=\"hidden\" id=\"qst_val\" value=\"\"><a href=\"#\" onclick=\"qst_next('','24')\" class=\"qle arrow\">Volver al resumen.<\/a><a href=\"javascript: qst_next('','35');\" class=\"qri arrow\">Recoger recompensa<\/a><div class=\"clear\"><\/div><\/div>\n\t\t<div id=\"qstbg\" class=\"quest_new_village\"><\/div>\n\t\t","number":35,"reward":{"wood":1600,"clay":2000,"iron":1800,"crop":1300},"qgsrc":"q_l<?php echo $session->userinfo['tribe'];?>g","msrc":"<?php echo $messagelol; ?>","altstep":0}
<?php } $_SESSION['qst'] = 24;?>




// End tasks message
<?php } ?>
