<?php
/**
 * La identidad de un retrato del héroe: qué URL lo pide y qué respuesta lo cachea.
 *
 * Por qué existe. `hero_image.php` es, de lejos, la URL más pedida del servidor: 1346
 * veces en un día para un solo jugador, contra 521 de `dorf1.php`, porque el retrato
 * cuelga de la barra lateral y la barra lateral está en todas las páginas. Y no mandaba
 * un solo header de caché -- ni `Cache-Control`, ni `ETag`, ni `Last-Modified` -- así que
 * el navegador no tenía con qué revalidar y se bajaba los 15 KB enteros cada vez, y cada
 * vez el servidor volvía a apilar ocho capas PNG para producir exactamente los mismos
 * bytes que ya había mandado.
 *
 * El cache-buster que había en la URL no servía. Era `hero.hash`, que se escribe una sola
 * vez al crear el héroe y **nunca** se actualiza: `hero.php` edita la cara sin tocarlo.
 * Servía de constante, no de versión. Y `Templates/Profile/overview.tpl` era peor todavía,
 * usaba `md5($_GET['uid'])`, que es constante por definición.
 *
 * Cómo se resuelve acá. La huella se **deriva del contenido**, como el resto del motor:
 * es un hash de la fila entera de `heroface` más el ítem de armadura equipado. De la fila
 * ENTERA a propósito, no de una lista de columnas elegidas a mano -- si mañana alguien
 * agrega una columna que cambia el dibujo, queda cubierta sola. Enumerar columnas es la
 * clase de lista que se desactualiza en silencio, y acá desactualizarse significa que un
 * jugador se queda un año con la cara vieja.
 *
 * Esa huella es a la vez el cache-buster de la URL y el `ETag` de la respuesta, y sale de
 * la misma función en los dos lados justamente para que no puedan divergir: una URL que
 * anuncia una versión y una respuesta que devuelve otra es peor que no cachear nada.
 *
 * OJO con el arte. La huella cubre los DATOS del héroe, no los PNG de `img/hero/`. Si
 * alguna vez se reemplaza una capa del arte, hay que subir `HERO_IMAGE_ART_VERSION` acá
 * abajo o los navegadores van a seguir mostrando el dibujo viejo hasta que venza el año.
 * Es la misma convención que el `?v=N` de las hojas de estilo, por el mismo motivo.
 */

// Subir este número invalida TODOS los retratos cacheados del mundo. Se toca sólo cuando
// cambia el arte de img/hero/, no cuando cambia el código.
if(!defined('HERO_IMAGE_ART_VERSION')) {
    define('HERO_IMAGE_ART_VERSION', 1);
}

// Un año. La URL es content-addressed -- cambia sola cuando cambia el héroe -- así que no
// hay nada que revalidar y `immutable` le dice al navegador que ni lo intente al recargar.
if(!defined('HERO_IMAGE_CACHE_SECONDS')) {
    define('HERO_IMAGE_CACHE_SECONDS', 31536000);
}

/**
 * Los tamaños que sabe dibujar cada página, y cuál se usa si piden cualquier otra cosa.
 *
 * Existe porque las dos páginas resolvían el tamaño con una cadena de `if` sin `else`:
 * con un `?size=` desconocido la variable quedaba sin definir y se armaban rutas como
 * `img/hero/head//face0.png`, o sea una respuesta de 296 bytes que no es un PNG.
 */
function heroImageSizes($variant) {
    if($variant === 'body') {
        return array('profile', 'inventory');
    }
    return array('profile', 'inventory', 'sideinfo');
}

function heroImageNormalizeSize($variant, $size) {
    $validos = heroImageSizes($variant);
    $size = is_string($size) ? $size : '';
    if(in_array($size, $validos, true)) {
        return $size;
    }
    return $variant === 'body' ? 'inventory' : 'sideinfo';
}

/**
 * La huella de un retrato: cambia si y sólo si cambia lo que se dibuja.
 *
 * $faceRow es la fila de `heroface` (o null si el héroe no tiene). $bodyItem es el id del
 * ítem de armadura equipado, y sólo entra en la variante 'body': la cabeza no lo dibuja,
 * y además vive en otra tabla, así que pedirlo obligaría a `heroImageUrl()` a hacer una
 * segunda consulta en cada carga de página para un dato que no cambia el dibujo.
 *
 * El precio de hashear la fila entera es que la cabeza se re-descarga cuando cambia una
 * columna que no dibuja -- `helmet`, `horse`, las manos, que sí usa el cuerpo. Está
 * elegido a propósito: pasa cuando el jugador se cambia el equipo, cuesta una imagen de
 * 15 KB, y la alternativa -- una lista de columnas por variante escrita a mano -- se
 * desactualiza en silencio, y acá desactualizarse cuesta un año de cara equivocada.
 */
function heroImageFingerprint($variant, $size, $faceRow, $bodyItem = 0) {
    $variant = ($variant === 'body') ? 'body' : 'head';
    $size = heroImageNormalizeSize($variant, $size);

    $datos = array();
    if(is_array($faceRow)) {
        foreach($faceRow as $col => $val) {
            // mysqli_fetch_array devuelve las columnas por nombre Y por posición; quedarse
            // con los nombres evita que la huella dependa del orden del SELECT.
            if(is_string($col)) {
                $datos[$col] = (string)$val;
            }
        }
        ksort($datos);
    }

    $semilla = array(
        'v' => HERO_IMAGE_ART_VERSION,
        'variant' => $variant,
        'size' => $size,
        'face' => $datos,
    );
    if($variant === 'body') {
        $semilla['body'] = (int)$bodyItem;
    }

    return substr(md5(serialize($semilla)), 0, 12);
}

/** La huella de la cabeza (hero_image.php) de un jugador, leyendo su fila. */
function heroImageFingerprintFor($uid, $size) {
    global $database;
    $uid = (int)$uid;
    return heroImageFingerprint('head', $size, $database->HeroFace($uid));
}

/** La huella del cuerpo (hero_body.php), que además depende de la armadura equipada. */
function heroBodyFingerprintFor($uid, $size) {
    global $database;
    $uid = (int)$uid;
    $inventario = $database->getHeroInventory($uid);
    $body = (is_array($inventario) && isset($inventario['body'])) ? (int)$inventario['body'] : 0;
    return heroImageFingerprint('body', $size, $database->HeroFace($uid), $body);
}

/**
 * Las URLs. Devuelven el `&` crudo; escapar es tarea de quien las mete en HTML.
 *
 * Que las arme una sola función es el punto: las cinco plantillas que pedían un retrato
 * las escribían a mano y tres habían quedado con un cache-buster que no cambiaba nunca.
 *
 * Y el `&` va crudo justamente porque no todos los destinos son HTML. `hero.php` mete la
 * URL adentro de un `<script>`, donde el navegador NO decodifica entidades: la que había
 * escrita a mano ahí llevaba `&amp;` y por lo tanto PHP recibía un parámetro llamado
 * `amp;size` en vez de `size`. Nunca se notó porque el tamaño que quería esa pantalla es
 * justo el que se usa por defecto.
 */
function heroImageUrl($uid, $size) {
    $uid = (int)$uid;
    $size = heroImageNormalizeSize('head', $size);
    return 'hero_image.php?uid='.$uid.'&size='.$size.'&v='.heroImageFingerprintFor($uid, $size);
}

function heroBodyUrl($uid, $size) {
    $uid = (int)$uid;
    $size = heroImageNormalizeSize('body', $size);
    return 'hero_body.php?uid='.$uid.'&size='.$size.'&v='.heroBodyFingerprintFor($uid, $size);
}

/**
 * Manda los headers de caché y contesta si el navegador ya tiene esta versión.
 *
 * Devuelve true cuando corresponde un 304: el llamador tiene que salir sin generar la
 * imagen. Ahí está la mitad más valiosa del ahorro, porque el 304 se contesta antes de
 * abrir un solo PNG.
 */
function heroImageCacheHeaders($fingerprint) {
    header('ETag: "'.$fingerprint.'"');
    header('Cache-Control: public, max-age='.HERO_IMAGE_CACHE_SECONDS.', immutable');

    if(!isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        return false;
    }
    foreach(explode(',', $_SERVER['HTTP_IF_NONE_MATCH']) as $tag) {
        $tag = trim($tag);
        if($tag === '*') {
            return true;
        }
        // Un proxy puede devolver el ETag debilitado como W/"abc".
        if(strncmp($tag, 'W/', 2) === 0) {
            $tag = substr($tag, 2);
        }
        if(trim($tag, '"') === $fingerprint) {
            return true;
        }
    }
    return false;
}
