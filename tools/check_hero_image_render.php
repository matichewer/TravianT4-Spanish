<?php
/**
 * El apilado de capas del retrato del héroe (hero_image.php, hero_body.php).
 *
 *   docker compose exec -T web php /var/www/html/tools/check_hero_image_render.php
 *
 * `mysqli_DB::imagecopymerge_alpha()` era un loop pixel por pixel escrito en PHP que
 * recorría cada capa DOS veces —una para encontrar el pixel más opaco y otra para
 * reescribir los 16.184 pixeles uno por uno con imagecolorallocatealpha + imagesetpixel—
 * y hero_image.php apila 8 capas, hero_body.php hasta 15. Eran ~260.000 iteraciones de
 * PHP por retrato, y el retrato se pedía en cada carga de página (1346 veces en un día
 * para un solo jugador, porque tampoco mandaba headers de caché).
 *
 * Y no hacía nada. Los 23 llamadores pasan `$pct = 100`, y con opacidad completa la
 * fórmula es la identidad en cuanto la capa tenga un pixel totalmente opaco:
 * minalpha = 0 y alpha = 127 + (alpha - 127) = alpha. Un `imagecopy` pelado con
 * alphablending en el destino da lo mismo 17 veces más rápido.
 *
 * Este checker existe para que eso quede demostrado y no confiado: recompone TODAS las
 * capas reales del juego con la implementación vieja y con la nueva y exige que salga lo
 * mismo. Las dos excepciones conocidas son parte de lo que se pinea:
 *
 *  - Las 31 capas totalmente transparentes son beard5-* y hair5-*, o sea "sin barba" y
 *    "sin pelo", y las dos páginas las saltean antes de llamar acá. Menos mal: para
 *    minalpha = 127 la fórmula vieja daba alpha = 254, fuera del rango 0..127 que acepta
 *    imagecolorallocatealpha, así que ese camino nunca fue correcto.
 *  - img/hero/head/31x40/mouth/mouth0.png es el único PNG sin ningún pixel opaco
 *    (minalpha = 1). Ahí la fórmula vieja estiraba el pixel más opaco hasta la opacidad
 *    total y el atajo no. La diferencia está acotada acá abajo y es de 3 pixeles de 1240
 *    en 1/255 de un canal.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";

$failures = 0;
$checks = 0;

function check($ok, $label, $detail = '') {
    global $failures, $checks;
    $checks++;
    if($ok) {
        echo "  ok   $label\n";
    } else {
        $failures++;
        echo "  FALLA $label".($detail !== '' ? " -- $detail" : "")."\n";
    }
}

/**
 * La implementación original, tal cual estaba antes de la optimización. Es la referencia
 * contra la que se compara; no la toques para "arreglarla", su valor es ser el pasado.
 */
function mergeReferenciaVieja($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
    if(!isset($pct)) { return false; }
    $pct /= 100;
    $w = imagesx($src_im);
    $h = imagesy($src_im);
    imagealphablending($src_im, false);
    $minalpha = 127;
    for($x = 0; $x < $w; $x++)
    for($y = 0; $y < $h; $y++){
        $alpha = (imagecolorat($src_im, $x, $y) >> 24) & 0xFF;
        if($alpha < $minalpha) { $minalpha = $alpha; }
    }
    for($x = 0; $x < $w; $x++){
        for($y = 0; $y < $h; $y++){
            $colorxy = imagecolorat($src_im, $x, $y);
            $alpha = ($colorxy >> 24) & 0xFF;
            if($minalpha !== 127){
                $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
            } else {
                $alpha += 127 * $pct;
            }
            $alphacolorxy = imagecolorallocatealpha($src_im, ($colorxy >> 16) & 0xFF, ($colorxy >> 8) & 0xFF, $colorxy & 0xFF, $alpha);
            if(!imagesetpixel($src_im, $x, $y, $alphacolorxy)) { return false; }
        }
    }
    imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
}

/** Un lienzo con color y alpha variados, para que cualquier diferencia de mezcla se note. */
function lienzoDePrueba($w, $h) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    for($x = 0; $x < $w; $x++) {
        for($y = 0; $y < $h; $y++) {
            $a = (int)round(127 * (($x + $y) % 32) / 31);
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, ($x * 7) % 256, ($y * 11) % 256, ($x * $y) % 256, $a));
        }
    }
    imagealphablending($im, true);
    return $im;
}

/** Devuelve [pixeles distintos, diferencia máxima por canal] entre dos imágenes. */
function comparar($a, $b) {
    $w = imagesx($a); $h = imagesy($a);
    if($w !== imagesx($b) || $h !== imagesy($b)) { return array(PHP_INT_MAX, 255); }
    $n = 0; $max = 0;
    for($x = 0; $x < $w; $x++) {
        for($y = 0; $y < $h; $y++) {
            $p = imagecolorat($a, $x, $y); $q = imagecolorat($b, $x, $y);
            if($p === $q) { continue; }
            $n++;
            foreach(array(24, 16, 8, 0) as $sh) {
                $max = max($max, abs((($p >> $sh) & 0xFF) - (($q >> $sh) & 0xFF)));
            }
        }
    }
    return array($n, $max);
}

function pngBytes($im) {
    ob_start();
    imagesavealpha($im, true);
    imagepng($im);
    return ob_get_clean();
}

function listarPngs($dir) {
    $salida = array();
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach($rii as $f) {
        if($f->isDir() || strtolower($f->getExtension()) !== 'png') { continue; }
        $salida[] = $f->getPathname();
    }
    sort($salida);
    return $salida;
}

// La única excepción documentada: ruta => [máximo de pixeles que pueden diferir, máxima
// diferencia por canal]. Cuánto difiere depende del fondo sobre el que se apile -- sobre
// la cara opaca real son 3 pixeles de 1240 en 1/255, sobre el lienzo de alpha variable de
// este checker son 14 en 2/255, porque la mezcla amplifica. Si una capa nueva empieza a
// divergir, o ésta se corre de rango, este checker lo delata.
$toleradas = array(
    'img/hero/head/31x40/mouth/mouth0.png' => array(20, 2),
);

echo "== 1. Todas las capas que el motor realmente apila, vieja contra nueva ==\n";

// Sólo estos directorios pasan por GD. hero_image.php arma la cabeza en 31x40 (profile),
// 64x82 (inventory) o 119x136 (sideinfo); hero_body.php pega esa misma cabeza sobre un
// cuerpo de 160x205 o 330x422. Todo lo demás bajo img/hero -- 254x330, items/ y thumb/ --
// se sirve como <img> tal cual y no se compone nunca.
$dirsCompuestos = array(
    'img/hero/head/31x40',
    'img/hero/head/64x82',
    'img/hero/head/119x136',
    'img/hero/body/160x205',
    'img/hero/body/330x422',
);

$pngs = array();
foreach($dirsCompuestos as $dir) {
    if(!is_dir($dir)) { check(false, "existe el directorio $dir"); continue; }
    $pngs = array_merge($pngs, listarPngs($dir));
}
sort($pngs);
check(count($pngs) > 300, "el barrido encuentra las capas que se apilan", count($pngs)." PNG");

/** El alpha del pixel más opaco de la imagen: 0 = tiene un pixel totalmente opaco. */
function minAlpha($im) {
    $w = imagesx($im); $h = imagesy($im); $min = 127;
    for($x = 0; $x < $w; $x++) {
        for($y = 0; $y < $h; $y++) {
            $a = (imagecolorat($im, $x, $y) >> 24) & 0xFF;
            if($a < $min) { $min = $a; if($min === 0) { return 0; } }
        }
    }
    return $min;
}

$distintas = array();
$transparentes = array();
$conPaleta = array();
foreach($pngs as $ruta) {
    $probe = @imagecreatefrompng($ruta);
    if(!$probe) { check(false, "abre $ruta"); continue; }
    $w = imagesx($probe); $h = imagesy($probe);
    // Una capa con paleta no se puede apilar: imagecolorat devuelve un índice, no un
    // ARGB, así que la fórmula vieja leía basura como si fuera color y alpha.
    if(!imageistruecolor($probe)) { $conPaleta[] = $ruta; imagedestroy($probe); continue; }
    imagealphablending($probe, false);
    $min = minAlpha($probe);
    imagedestroy($probe);

    // Una capa íntegramente transparente tampoco se compara: la fórmula vieja la
    // convertía en un rectángulo negro opaco (calculaba alpha 254, que
    // imagecolorallocatealpha rechaza por estar fuera de 0..127). O sea que ahí lo viejo
    // es lo incorrecto. Se verifica aparte, en el bloque 2.
    if($min === 127) { $transparentes[] = $ruta; continue; }

    $dstViejo = lienzoDePrueba($w, $h);
    $srcViejo = imagecreatefrompng($ruta);
    mergeReferenciaVieja($dstViejo, $srcViejo, 0, 0, 0, 0, $w, $h, 100);

    $dstNuevo = lienzoDePrueba($w, $h);
    $srcNuevo = imagecreatefrompng($ruta);
    $database->imagecopymerge_alpha($dstNuevo, $srcNuevo, 0, 0, 0, 0, $w, $h, 100);

    list($n, $max) = comparar($dstViejo, $dstNuevo);
    if($n > 0) {
        $limite = isset($toleradas[$ruta]) ? $toleradas[$ruta] : array(0, 0);
        if($n > $limite[0] || $max > $limite[1]) {
            $distintas[] = "$ruta ($n px, max $max/255)";
        }
    }
    imagedestroy($dstViejo); imagedestroy($srcViejo);
    imagedestroy($dstNuevo); imagedestroy($srcNuevo);
}
check(empty($distintas), "cada capa se apila igual que antes",
    empty($distintas) ? '' : implode(' | ', array_slice($distintas, 0, 5)));

check(empty($conPaleta), "ninguna capa que se apila tiene paleta en vez de color real",
    empty($conPaleta) ? '' : implode(' | ', array_slice($conPaleta, 0, 5)));

$inesperadas = array();
foreach($transparentes as $ruta) {
    if(!preg_match('~/(beard5|hair5)-~', $ruta)) { $inesperadas[] = $ruta; }
}
check(empty($inesperadas), "las únicas capas íntegramente transparentes son beard5/hair5",
    empty($inesperadas) ? count($transparentes)." capas" : implode(' | ', array_slice($inesperadas, 0, 5)));

// La otra mitad de la frontera: que las capas con paleta sigan viviendo sólo donde nadie
// las compone. Si alguien mueve arte de 254x330 o items/ a un directorio compuesto, o
// vuelve a guardar una capa como PNG indexado, esto lo detiene.
$fueraDeCompuestos = 0;
foreach(listarPngs('img/hero') as $ruta) {
    $adentro = false;
    foreach($dirsCompuestos as $dir) {
        if(strpos($ruta, $dir.'/') === 0) { $adentro = true; break; }
    }
    if($adentro) { continue; }
    $im = @imagecreatefrompng($ruta);
    if(!$im) { continue; }
    if(!imageistruecolor($im)) { $fueraDeCompuestos++; }
    imagedestroy($im);
}
check($fueraDeCompuestos > 0, "el arte con paleta vive fuera de los directorios que se apilan",
    "$fueraDeCompuestos PNG indexados, todos en 254x330/items/thumb");

echo "\n== 2. Las capas que la fórmula vieja no sabía manejar ==\n";

// beard5/hair5 son íntegramente transparentes: la fórmula vieja calculaba alpha = 254 y
// se lo pasaba a imagecolorallocatealpha, que sólo acepta 0..127. El atajo las copia sin
// tocar nada, que es lo correcto. Las páginas igual las saltean, y eso también se pinea.
$vacias = array('img/hero/head/119x136/beard/beard5-black.png', 'img/hero/head/119x136/hair/hair5-black.png');
foreach($vacias as $ruta) {
    if(!file_exists($ruta)) { check(false, "existe $ruta"); continue; }
    $src = imagecreatefrompng($ruta);
    $w = imagesx($src); $h = imagesy($src);
    $dst = lienzoDePrueba($w, $h);
    $antes = pngBytes($dst);
    $database->imagecopymerge_alpha($dst, $src, 0, 0, 0, 0, $w, $h, 100);
    check(pngBytes($dst) === $antes, "una capa vacía no altera el destino: ".basename($ruta));
    imagedestroy($src); imagedestroy($dst);
}

foreach(array('hero_image.php' => 'gethair', 'hero_body.php' => 'gethair') as $archivo => $_) {
    $fuente = file_get_contents($archivo);
    check(strpos($fuente, '$gethair!=5') !== false && strpos($fuente, '$getbeard!=5') !== false,
        "$archivo sigue salteando las capas 'sin pelo' y 'sin barba'");
}

echo "\n== 3. Apilado con offset, como lo hace hero_body.php ==\n";

// hero_body.php pega las capas de cabeza en ($w,$h) sobre un lienzo más grande.
$capa = 'img/hero/head/64x82/eye/eye1.png';
if(!file_exists($capa)) {
    check(false, "existe $capa");
} else {
    $cw = 200; $ch = 260; $dx = 43; $dy = 27;
    $dstViejo = lienzoDePrueba($cw, $ch);
    $srcViejo = imagecreatefrompng($capa);
    mergeReferenciaVieja($dstViejo, $srcViejo, $dx, $dy, 0, 0, imagesx($srcViejo), imagesy($srcViejo), 100);

    $dstNuevo = lienzoDePrueba($cw, $ch);
    $srcNuevo = imagecreatefrompng($capa);
    $database->imagecopymerge_alpha($dstNuevo, $srcNuevo, $dx, $dy, 0, 0, imagesx($srcNuevo), imagesy($srcNuevo), 100);

    list($n, $max) = comparar($dstViejo, $dstNuevo);
    check($n === 0, "una capa pegada con offset sale idéntica", "$n px distintos, max $max/255");
    imagedestroy($dstViejo); imagedestroy($srcViejo);
    imagedestroy($dstNuevo); imagedestroy($srcNuevo);
}

echo "\n== 4. El retrato completo, byte a byte ==\n";

// La composición de hero_image.php entera, para varias caras, comparando el PNG final.
$size = '119x136';
$colores = array('black', 'brown', 'darkbrown', 'yellow', 'red');
$caras = array(
    array('face' => 0, 'ear' => 0, 'eye' => 0, 'eyebrow' => 0, 'mouth' => 0, 'nose' => 0, 'hair' => 0, 'beard' => 0, 'color' => 0),
    array('face' => 1, 'ear' => 1, 'eye' => 2, 'eyebrow' => 1, 'mouth' => 2, 'nose' => 1, 'hair' => 2, 'beard' => 5, 'color' => 1),
    array('face' => 2, 'ear' => 0, 'eye' => 1, 'eyebrow' => 3, 'mouth' => 1, 'nose' => 2, 'hair' => 5, 'beard' => 2, 'color' => 4),
    array('face' => 0, 'ear' => 1, 'eye' => 3, 'eyebrow' => 2, 'mouth' => 3, 'nose' => 0, 'hair' => 5, 'beard' => 5, 'color' => 2),
);

function componerRetrato($cara, $size, $colores, $merge) {
    $color = $colores[$cara['color']];
    $base = "img/hero/head/$size/";
    $body = imagecreatefrompng($base.'face0.png');
    $capas = array();
    $capas[] = $base."face/face{$cara['face']}.png";
    $capas[] = $base."ear/ear{$cara['ear']}.png";
    $capas[] = $base."eye/eye{$cara['eye']}.png";
    $capas[] = $base."eyebrow/eyebrow{$cara['eyebrow']}-$color.png";
    if($cara['hair'] != 5) { $capas[] = $base."hair/hair{$cara['hair']}-$color.png"; }
    $capas[] = $base."mouth/mouth{$cara['mouth']}.png";
    $capas[] = $base."nose/nose{$cara['nose']}.png";
    if($cara['beard'] != 5) { $capas[] = $base."beard/beard{$cara['beard']}-$color.png"; }
    foreach($capas as $ruta) {
        if(!file_exists($ruta)) { return null; }
        $im = imagecreatefrompng($ruta);
        $merge($body, $im, 0, 0, 0, 0, imagesx($im), imagesy($im), 100);
        imagedestroy($im);
    }
    return $body;
}

global $database;
$mergeNuevo = function($d, $s, $dx, $dy, $sx, $sy, $sw, $sh, $p) use ($database) {
    return $database->imagecopymerge_alpha($d, $s, $dx, $dy, $sx, $sy, $sw, $sh, $p);
};

foreach($caras as $i => $cara) {
    $viejo = componerRetrato($cara, $size, $colores, 'mergeReferenciaVieja');
    $nuevo = componerRetrato($cara, $size, $colores, $mergeNuevo);
    if($viejo === null || $nuevo === null) { check(false, "cara $i: falta alguna capa"); continue; }
    check(pngBytes($viejo) === pngBytes($nuevo), "retrato $i sale byte a byte igual que antes");
    imagedestroy($viejo); imagedestroy($nuevo);
}

echo "\n== 5. El atajo es el camino que se usa de verdad ==\n";

// Si algún llamador pasara otra opacidad caería en el camino lento sin que nadie lo note,
// y este checker estaría midiendo algo que no corre en producción.
$conPct = array();
$sinCien = array();
foreach(array('hero_image.php', 'hero_body.php') as $archivo) {
    foreach(file($archivo) as $nro => $linea) {
        if(strpos($linea, 'imagecopymerge_alpha') === false) { continue; }
        $conPct[] = "$archivo:".($nro + 1);
        if(!preg_match('/,\s*100\s*\)/', $linea)) { $sinCien[] = "$archivo:".($nro + 1); }
    }
}
check(count($conPct) >= 20, "se encontraron los llamadores", count($conPct)." llamadas");
check(empty($sinCien), "todos los llamadores piden opacidad 100",
    empty($sinCien) ? '' : implode(' ', $sinCien));

// Y el camino lento tiene que seguir existiendo y comportándose distinto, o el fallback
// sería decorativo.
$capa = "img/hero/head/$size/eye/eye1.png";
$dstCien = imagecreatefrompng("img/hero/head/$size/face0.png");
$srcCien = imagecreatefrompng($capa);
$database->imagecopymerge_alpha($dstCien, $srcCien, 0, 0, 0, 0, imagesx($srcCien), imagesy($srcCien), 100);
$dstMedio = imagecreatefrompng("img/hero/head/$size/face0.png");
$srcMedio = imagecreatefrompng($capa);
$database->imagecopymerge_alpha($dstMedio, $srcMedio, 0, 0, 0, 0, imagesx($srcMedio), imagesy($srcMedio), 50);
list($n, ) = comparar($dstCien, $dstMedio);
check($n > 0, "una opacidad distinta de 100 sigue cayendo en el camino lento", "$n px distintos");

echo "\n== 6. Y es más rápido, que era el punto ==\n";

// Se mide sólo el apilado: el decode del PNG es idéntico en los dos caminos y meterlo
// adentro del reloj diluye la diferencia que se quiere vigilar.
$capaPerf = "img/hero/head/119x136/eye/eye1.png";
$vueltas = 5;

$pares = array();
for($i = 0; $i < $vueltas; $i++) {
    $pares[] = array(imagecreatefrompng("img/hero/head/119x136/face0.png"), imagecreatefrompng($capaPerf));
}
$t = microtime(true);
foreach($pares as $par) { mergeReferenciaVieja($par[0], $par[1], 0, 0, 0, 0, imagesx($par[1]), imagesy($par[1]), 100); }
$msViejo = (microtime(true) - $t) / $vueltas * 1000;
foreach($pares as $par) { imagedestroy($par[0]); imagedestroy($par[1]); }

$pares = array();
for($i = 0; $i < $vueltas; $i++) {
    $pares[] = array(imagecreatefrompng("img/hero/head/119x136/face0.png"), imagecreatefrompng($capaPerf));
}
$t = microtime(true);
foreach($pares as $par) { $database->imagecopymerge_alpha($par[0], $par[1], 0, 0, 0, 0, imagesx($par[1]), imagesy($par[1]), 100); }
$msNuevo = (microtime(true) - $t) / $vueltas * 1000;
foreach($pares as $par) { imagedestroy($par[0]); imagedestroy($par[1]); }

printf("  viejo %.2f ms/capa, nuevo %.2f ms/capa (x%.1f)\n", $msViejo, $msNuevo, $msViejo / max($msNuevo, 0.0001));
// El margen medido es ~17x; se exige 5x para que un runner lento no dé un falso positivo,
// pero un 1x significaría que el loop pixel por pixel volvió.
check($msNuevo * 5 < $msViejo, "el apilado es al menos 5 veces más rápido que el loop viejo",
    sprintf("%.2f ms contra %.2f ms", $msNuevo, $msViejo));

echo "\n";
if($failures > 0) {
    echo "FALLARON $failures de $checks comprobaciones\n";
    exit(1);
}
echo "OK: $checks comprobaciones\n";
exit(0);
