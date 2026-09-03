<?php 
// Keep GD/libpng warnings and accidental include output out of the binary PNG response.
ob_start();
include("GameEngine/Database.php");

// El uid entra a `HeroFace()`, que lo concatena crudo en el SQL, y esta pagina no pide
// login: sin el cast era inyeccion SQL abierta. Un UNION devolvia un retrato distinto,
// o sea que el atacante veia el resultado de su propia consulta dibujado.
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 1;

// `?size=` desconocido dejaba $size sin definir y se pedia 'img/hero/head//face0.png':
// 296 bytes que no son un PNG. heroImageNormalizeSize() elige el tamano por defecto.
$sizeKey = heroImageNormalizeSize('head', isset($_GET['size']) ? $_GET['size'] : '');
$medidas = array('profile' => '31x40', 'inventory' => '64x82', 'sideinfo' => '119x136');
$size = $medidas[$sizeKey];

$herodetail = $database->HeroFace($uid);

// La huella sale de la fila que se acaba de leer, asi que la respuesta no puede anunciar
// una version distinta de la que dibuja. Si el navegador ya la tiene, se contesta 304 sin
// abrir un solo PNG: ahi esta la mitad grande del ahorro.
if(heroImageCacheHeaders(heroImageFingerprint('head', $sizeKey, $herodetail))) {
	ob_end_clean();
	http_response_code(304);
	exit;
}
if($herodetail['color']==0){
	$color = "black";
}
if($herodetail['color']==1){
	$color = "brown";
}
if($herodetail['color']==2){
	$color = "darkbrown";
}
if($herodetail['color']==3){
	$color = "yellow";
}
if($herodetail['color']==4){
	$color = "red";
}
$geteye = $herodetail['eye'];
$geteyebrow = $herodetail['eyebrow'];
$getnose = $herodetail['nose'];
$getear = $herodetail['ear'];
$getmouth = $herodetail['mouth'];
$getbeard = $herodetail['beard'];
$gethair = $herodetail['hair'];
$getface = $herodetail['face'];



// USAGE EXAMPLE: 
$body = imagecreatefrompng('img/hero/head/'.$size.'/face0.png');
if($getbeard!=5){
	$beard = imagecreatefrompng('img/hero/head/'.$size.'/beard/beard'.$getbeard.'-'.$color.'.png');
}
$ear = imagecreatefrompng('img/hero/head/'.$size.'/ear/ear'.$getear.'.png');
$eye = imagecreatefrompng('img/hero/head/'.$size.'/eye/eye'.$geteye.'.png');
$eyebrow = imagecreatefrompng('img/hero/head/'.$size.'/eyebrow/eyebrow'.$geteyebrow.'-'.$color.'.png');
if($gethair!=5){
	$hair = imagecreatefrompng('img/hero/head/'.$size.'/hair/hair'.$gethair.'-'.$color.'.png');
}
$mouth = imagecreatefrompng('img/hero/head/'.$size.'/mouth/mouth'.$getmouth.'.png');
$nose = imagecreatefrompng('img/hero/head/'.$size.'/nose/nose'.$getnose.'.png');
$face = imagecreatefrompng('img/hero/head/'.$size.'/face/face'.$getface.'.png');



// SAME COMMANDS: 
$database->imagecopymerge_alpha($body, $face, 0, 0, 0, 0, imagesx($face), imagesy($face),100); 
$database->imagecopymerge_alpha($body, $ear, 0, 0, 0, 0, imagesx($ear), imagesy($ear),100);
$database->imagecopymerge_alpha($body, $eye, 0, 0, 0, 0, imagesx($eye), imagesy($eye),100);
$database->imagecopymerge_alpha($body, $eyebrow, 0, 0, 0, 0, imagesx($eyebrow), imagesy($eyebrow),100);
if($gethair!=5){
$database->imagecopymerge_alpha($body, $hair, 0, 0, 0, 0, imagesx($hair), imagesy($hair),100);
}
$database->imagecopymerge_alpha($body, $mouth, 0, 0, 0, 0, imagesx($mouth), imagesy($mouth),100);
$database->imagecopymerge_alpha($body, $nose, 0, 0, 0, 0, imagesx($nose), imagesy($nose),100);
if($getbeard!=5){
$database->imagecopymerge_alpha($body, $beard, 0, 0, 0, 0, imagesx($beard), imagesy($beard),100);
}

ob_end_clean();

// OUTPUT IMAGE:
// Se arma en memoria para poder mandar Content-Length: sin el la respuesta salia con
// Transfer-Encoding: chunked y el navegador no sabia cuanto venia.
imagesavealpha($body, true);
ob_start();
imagepng($body);
$png = ob_get_clean();
header("Content-Type: image/png");
header("Content-Length: ".strlen($png));
echo $png;

?>