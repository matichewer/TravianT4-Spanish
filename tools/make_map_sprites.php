<?php
/**
 * Deriva los sprites de mapa que el gpack no trae, recoloreando el marco de uno que si trae.
 *
 * Por que existe. El paquete grafico trae cuatro marcos —dorado (aldea propia), azul (mi
 * alianza), verde (aliado) y gris punteado (cualquier otro)— pero el juego distingue TRES
 * tipos de diplomacia: aliado, pacto de no agresion y guerra. Faltaban dos colores, y sus
 * clases CSS o no existian o apuntaban a archivos ausentes (`img/m/d02.gif`, `d05.gif`).
 *
 * Como. Los sprites son la MISMA ilustracion y se diferencian solo en el anillo de 1px del
 * borde. Se parte del azul (`ally/d03-N.gif`) y se repinta ese anillo. Importa que la base
 * sea el azul y no el gris: el gris tiene el borde PUNTEADO (120 px de marco contra 171), y
 * un enemigo con borde punteado se lee como menos importante que un aliado, al reves de lo
 * que corresponde. Las relaciones "de verdad" llevan marco solido; el punteado queda para
 * "cualquier otro", que es justamente el caso sin relacion.
 *
 * El castillo no se toca porque solo se recorre el anillo exterior.
 *
 * Es reproducible: se pueden borrar las salidas y volver a generarlas.
 *
 * Uso: docker compose exec -T web php /var/www/html/tools/make_map_sprites.php [--aplicar]
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$apply = in_array('--aplicar', $argv, true);
$mapDir = $root.'/gpack/travian_Travian_4.0_41/img/map';

// El azul del marco de "mi alianza", que es la base.
$baseFrame = array(0x57, 0x5B, 0xD2);

// Los marcos que hay que inventar. La clave es el numero de relacion que usa el mapa.
$wanted = array(
    2 => array('nombre' => 'en guerra', 'rgb' => array(0xC2, 0x1F, 0x09)),   // rojo
    5 => array('nombre' => 'pacto de no agresion', 'rgb' => array(0x00, 0xC8, 0xC8))  // cian
);

$written = array();
foreach($wanted as $relation => $spec) {
    foreach(array(1, 2, 3) as $tribe) {
        $source = $mapDir.'/ally/d03-'.$tribe.'.gif';
        $target = $mapDir.'/d0'.$relation.'-'.$tribe.'.gif';
        if(!is_file($source)) {
            fwrite(STDERR, "No existe $source\n");
            exit(1);
        }
        $image = imagecreatefromgif($source);
        $width = imagesx($image);
        $height = imagesy($image);
        $colour = imagecolorexact($image, $spec['rgb'][0], $spec['rgb'][1], $spec['rgb'][2]);
        if($colour === -1) {
            $colour = imagecolorallocate($image, $spec['rgb'][0], $spec['rgb'][1], $spec['rgb'][2]);
        }
        if($colour === false || $colour === -1) {
            fwrite(STDERR, "La paleta de d03-$tribe.gif esta llena\n");
            exit(1);
        }
        $painted = 0;
        for($x = 0; $x < $width; $x++) {
            for($y = 0; $y < $height; $y++) {
                if($x !== 0 && $y !== 0 && $x !== $width - 1 && $y !== $height - 1) {
                    continue;
                }
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if($rgb['red'] === $baseFrame[0] && $rgb['green'] === $baseFrame[1]
                    && $rgb['blue'] === $baseFrame[2]) {
                    imagesetpixel($image, $x, $y, $colour);
                    $painted++;
                }
            }
        }
        if($painted === 0) {
            fwrite(STDERR, "OJO: no se encontro el color del marco en d03-$tribe.gif\n");
            exit(1);
        }
        if($tribe === 1) {
            printf("relacion %d (%s): #%02X%02X%02X, %d px de marco\n", $relation, $spec['nombre'],
                $spec['rgb'][0], $spec['rgb'][1], $spec['rgb'][2], $painted);
        }
        if($apply) {
            $transparent = imagecolortransparent($image);
            if($transparent >= 0) {
                imagecolortransparent($image, $transparent);
            }
            imagegif($image, $target);
            $written[] = $target;
        }
    }
}

if(!$apply) {
    echo "\nSimulacion. Volve a correrlo con --aplicar para escribir los archivos.\n";
    exit(0);
}
foreach($written as $file) {
    printf("  escrito %s (%d bytes)\n", str_replace($root.'/', '', $file), filesize($file));
}
