<?php
/**
 * Regenera las imágenes del cartel de aldeas (#villageList) a un ancho distinto
 * del original de 172px, estirando dos bandas lisas del pergamino y dejando
 * intactos los clavos, el nudo central, las esquinas y los bordes de madera.
 *
 * Uso (desde el host):
 *   git checkout gpack/travian_Travian_4.0_41/img/layout/signVillages*-rtl.png
 *   docker compose exec -T web php /var/www/html/tools/widen_village_sign.php 196
 *
 * El primer paso es obligatorio: el script sólo sabe recortar el arte original
 * de 172px, así que hay que restaurarlo antes de volver a ensancharlo.
 *
 * Después de correrlo hay que ajustar a mano en
 * gpack/travian_Travian_4.0_41/lang/ir/compact1.css:
 *   #villageList{width}, #villageList .foot{width}  -> ancho nuevo
 *   #villageList .head a{width}                     -> ancho nuevo - 47
 *   #villageList ul a{width}                        -> ancho nuevo - 36
 *   #villageList ul li.attack a{width}              -> ancho nuevo - 38
 * y subir TRES cache-busters, si no Cloudflare sigue sirviendo lo viejo:
 *   - el ?v=N de las tres url(...signVillages*.png) en compact1.css
 *   - el ?v=N del @import de compact1.css en compact.css
 *   - el ?asdNNN de compact.css en Templates/html.tpl
 */

const SRC_WIDTH = 172;
const IMG_DIR = __DIR__ . "/../gpack/travian_Travian_4.0_41/img/layout/";
const FILES = ["signVillagesTop-rtl.png", "signVillagesMiddle-rtl.png", "signVillagesBottom-rtl.png"];

$newWidth = isset($argv[1]) ? (int)$argv[1] : 196;
if ($newWidth < SRC_WIDTH || $newWidth % 2 !== 0) {
    fwrite(STDERR, "El ancho nuevo debe ser par y >= " . SRC_WIDTH . "\n");
    exit(1);
}
$grow = ($newWidth - SRC_WIDTH) / 2; // se reparte entre las dos bandas

// [x de origen, ancho de origen, ancho de destino]. Los tramos fijos cubren el
// clavo y el rollo izquierdos (0-30), el nudo central (70-102) y el clavo y el
// rollo derechos (142-172). Las dos bandas restantes son pergamino liso: son
// las que se estiran, lo más anchas posible para que el estirado se reparta y
// la curva del borde superior no se quiebre.
$segments = [
    [0,   30, 30],
    [30,  40, 40 + $grow],
    [70,  32, 32],
    [102, 40, 40 + $grow],
    [142, 30, 30],
];

foreach (FILES as $name) {
    $path = IMG_DIR . $name;
    $src = imagecreatefrompng($path);
    if (!$src) {
        fwrite(STDERR, "No se pudo abrir $name\n");
        exit(1);
    }
    $h = imagesy($src);
    if (imagesx($src) !== SRC_WIDTH) {
        fwrite(STDERR, "$name mide " . imagesx($src) . "px: restaurá el original de " . SRC_WIDTH . "px con git checkout\n");
        exit(1);
    }

    // el "middle" es paletizado; se normaliza a truecolor para poder remuestrear
    $tc = imagecreatetruecolor(SRC_WIDTH, $h);
    imagealphablending($tc, false);
    imagesavealpha($tc, true);
    imagefill($tc, 0, 0, imagecolorallocatealpha($tc, 0, 0, 0, 127));
    imagecopy($tc, $src, 0, 0, 0, 0, SRC_WIDTH, $h);

    $out = imagecreatetruecolor($newWidth, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

    $dx = 0;
    foreach ($segments as [$sx, $sw, $dw]) {
        if ($sw === $dw) {
            imagecopy($out, $tc, $dx, 0, $sx, 0, $sw, $h);
        } else {
            imagecopyresampled($out, $tc, $dx, 0, $sx, 0, $dw, $h, $sw, $h);
        }
        $dx += $dw;
    }
    if ($dx !== $newWidth) {
        fwrite(STDERR, "$name: ancho final $dx != $newWidth\n");
        exit(1);
    }

    imagepng($out, $path);
    echo "$name -> {$newWidth}x{$h}\n";
}
