<?php
/**
 * Deriva el sprite de "aldea enemiga" del de "aldea ajena", recoloreando el marco.
 *
 * Por que existe. El gpack trae marco dorado (aldea propia), azul (mi alianza), verde
 * (aliado/NAP) y gris (cualquier otro), pero NO trae rojo: `img/m/d02.gif` esta referenciado
 * en el CSS y no existe en el disco, y las variantes por tribu `b02-N` no tienen ni regla.
 * Sin arte, una alianza en guerra no se puede distinguir en el mapa.
 *
 * Como. Los cuatro sprites son la MISMA ilustracion y se diferencian solo en el anillo de 1px
 * del borde. Se copia `d04-N.gif` (el gris, "cualquier otro") y se repintan de rojo unicamente
 * los pixeles del anillo exterior que tengan el color del marco. El castillo, que tambien
 * tiene grises, no se toca porque no esta en el anillo.
 *
 * Es reproducible: se puede borrar la salida y volver a generarla.
 *
 * Uso: docker compose exec -T web php /var/www/html/tools/make_enemy_sprites.php [--aplicar]
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$apply = in_array('--aplicar', $argv, true);
$mapDir = $root.'/gpack/travian_Travian_4.0_41/img/map';

// El gris del marco de "cualquier otro", y el rojo con que se reemplaza. El rojo mantiene el
// mismo valor y saturacion que el dorado de la aldea propia (#C2AF09) para que la familia de
// colores se vea pareja.
$frameGrey = array(0xB6, 0xB5, 0xB0);
$frameRed  = array(0xC2, 0x1F, 0x09);

$made = array();
foreach(array(1, 2, 3) as $tribe) {
    $source = $mapDir.'/d04-'.$tribe.'.gif';
    $target = $mapDir.'/d02-'.$tribe.'.gif';
    if(!is_file($source)) {
        fwrite(STDERR, "No existe $source\n");
        exit(1);
    }
    $image = imagecreatefromgif($source);
    $width = imagesx($image);
    $height = imagesy($image);
    $red = imagecolorexact($image, $frameRed[0], $frameRed[1], $frameRed[2]);
    if($red === -1) {
        $red = imagecolorallocate($image, $frameRed[0], $frameRed[1], $frameRed[2]);
    }
    if($red === false || $red === -1) {
        fwrite(STDERR, "La paleta de d04-$tribe.gif esta llena\n");
        exit(1);
    }
    // Solo el anillo exterior. Recorrerlo entero y comparar el color evita repintar el
    // castillo, que usa grises parecidos por dentro.
    $painted = 0;
    for($x = 0; $x < $width; $x++) {
        for($y = 0; $y < $height; $y++) {
            if($x !== 0 && $y !== 0 && $x !== $width - 1 && $y !== $height - 1) {
                continue;
            }
            $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if($rgb['red'] === $frameGrey[0] && $rgb['green'] === $frameGrey[1]
                && $rgb['blue'] === $frameGrey[2]) {
                imagesetpixel($image, $x, $y, $red);
                $painted++;
            }
        }
    }
    printf("d04-%d.gif -> d02-%d.gif  (%d pixeles de marco repintados)\n", $tribe, $tribe, $painted);
    if($painted === 0) {
        fwrite(STDERR, "  OJO: no se encontro el color del marco; el arte de origen cambio.\n");
        exit(1);
    }
    if($apply) {
        $transparent = imagecolortransparent($image);
        if($transparent >= 0) {
            imagecolortransparent($image, $transparent);
        }
        imagegif($image, $target);
        $made[] = $target;
    }
}

if(!$apply) {
    echo "\nSimulacion. Volve a correrlo con --aplicar para escribir los archivos.\n";
    exit(0);
}
foreach($made as $file) {
    printf("escrito %s (%d bytes)\n", str_replace($root.'/', '', $file), filesize($file));
}
