<?php
/**
 * Regenera el arte de un cartel de madera a un ancho distinto del original,
 * estirando sólo las bandas lisas del pergamino y dejando intactos los clavos,
 * los tornillos, el nudo central, la punta de flecha y los bordes de madera.
 *
 * Uso (desde el host):
 *   git checkout <commit-con-el-arte-original> -- gpack/.../img/layout/<png>
 *   docker compose exec -T web php /var/www/html/tools/widen_sign.php vname 225
 *
 * El git checkout es obligatorio: el script sólo sabe recortar el arte original,
 * así que hay que restaurarlo antes de volver a ensancharlo.
 *
 * Carteles disponibles:
 *   villages -> signVillages{Top,Middle,Bottom}-rtl.png, el cartel de la lista de
 *               aldeas (y el panel de cultura, que usa el mismo arte). Hoy está
 *               generado a 194px, elegido como punto medio del rango de anchos
 *               que toman los dos: el cartel va de 172 a 220 y el panel mide 168,
 *               así que background-size nunca lo estira ni lo encoge más de ~20%.
 *   vname    -> sign_village-rtl.png, el cartel con el nombre de la aldea actual.
 *               Hoy está generado a 225px.
 *
 * Los segmentos son [x de origen, ancho de origen, estirable]. El crecimiento se
 * reparte entre las bandas estirables en proporción a su ancho, así todas quedan
 * con el mismo factor de estirado y no se nota el empalme.
 *
 * Después de correrlo hay que subir los cache-busters o Cloudflare sigue
 * sirviendo el arte viejo (ver la nota de deployment en AGENTS.md): el ?v=N de
 * las url(...) en compact1.css, el del @import en compact.css y el ?asdNNN de
 * compact.css en Templates/html.tpl.
 */

const IMG_DIR = __DIR__ . "/../gpack/travian_Travian_4.0_41/img/layout/";

$signs = [
    'villages' => [
        'width' => 172,
        'files' => ["signVillagesTop-rtl.png", "signVillagesMiddle-rtl.png", "signVillagesBottom-rtl.png"],
        // fijo: rollo y clavo izquierdos / nudo central / clavo y rollo derechos
        'segments' => [[0, 30, false], [30, 40, true], [70, 32, false], [102, 40, true], [142, 30, false]],
    ],
    'vname' => [
        'width' => 192,
        'files' => ["sign_village-rtl.png"],
        // fijo: poste y tornillos izquierdos / clavo de arriba / tornillos
        // derechos y punta de flecha
        'segments' => [[0, 30, false], [30, 60, true], [90, 25, false], [115, 30, true], [145, 47, false]],
    ],
];

$which = $argv[1] ?? '';
$newWidth = isset($argv[2]) ? (int)$argv[2] : 0;
if (!isset($signs[$which]) || $newWidth <= 0) {
    fwrite(STDERR, "Uso: widen_sign.php <" . implode('|', array_keys($signs)) . "> <ancho>\n");
    exit(1);
}

$sign = $signs[$which];
$srcWidth = $sign['width'];
if ($newWidth < $srcWidth) {
    fwrite(STDERR, "El ancho nuevo debe ser >= $srcWidth (el script estira, no encoge; para achicar usá background-size)\n");
    exit(1);
}

$stretchable = 0;
foreach ($sign['segments'] as [, $sw, $isStretch]) {
    if ($isStretch) {
        $stretchable += $sw;
    }
}
$grow = $newWidth - $srcWidth;

// reparto proporcional, con el resto al último tramo para que cierre exacto
$dests = [];
$given = 0;
$lastStretch = null;
foreach ($sign['segments'] as $i => [, $sw, $isStretch]) {
    if (!$isStretch) {
        $dests[$i] = $sw;
        continue;
    }
    $extra = (int)round($grow * $sw / $stretchable);
    $dests[$i] = $sw + $extra;
    $given += $extra;
    $lastStretch = $i;
}
if ($lastStretch !== null) {
    $dests[$lastStretch] += $grow - $given;
}

foreach ($sign['files'] as $name) {
    $path = IMG_DIR . $name;
    $src = @imagecreatefrompng($path);
    if (!$src) {
        fwrite(STDERR, "No se pudo abrir $name\n");
        exit(1);
    }
    $h = imagesy($src);
    if (imagesx($src) !== $srcWidth) {
        fwrite(STDERR, "$name mide " . imagesx($src) . "px: restaurá el original de {$srcWidth}px con git checkout\n");
        exit(1);
    }

    // algunos son paletizados; se normalizan a truecolor para poder remuestrear
    $tc = imagecreatetruecolor($srcWidth, $h);
    imagealphablending($tc, false);
    imagesavealpha($tc, true);
    imagefill($tc, 0, 0, imagecolorallocatealpha($tc, 0, 0, 0, 127));
    imagecopy($tc, $src, 0, 0, 0, 0, $srcWidth, $h);

    $out = imagecreatetruecolor($newWidth, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

    $dx = 0;
    foreach ($sign['segments'] as $i => [$sx, $sw, $isStretch]) {
        $dw = $dests[$i];
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
