<?php
/**
 * La versión de un archivo estático, para el `?v=` que lo hace cacheable.
 *
 * Por qué existe. `Templates/html.tpl` pedía el bundle de JavaScript del juego así:
 *
 *     <script src="crypt.js?<?php echo time(); ?>">
 *
 * O sea que la URL cambiaba **cada segundo**. `crypt.js` son 427 KB (unos 99 KB
 * comprimidos) y con esa URL no se cachea nunca: se re-descarga entero en cada carga de
 * página, para siempre, y encima bloquea el render porque está en el `<head>` sin
 * `defer`. En el panel de red se veía como el único recurso que nunca decía "cached".
 *
 * `time()` como cache-buster es la forma más cara de decir "no cachees": consigue lo
 * mismo que un `Cache-Control: no-store` pero además ensucia la caché del navegador y la
 * del edge con una entrada nueva por segundo y por visitante.
 *
 * Qué hace esto. Deriva la versión de `filemtime()`, así que la URL cambia exactamente
 * cuando cambia el archivo -- que es lo que un cache-buster tiene que hacer -- y no en
 * ningún otro momento. Es automático a propósito: la alternativa es el `?v=N` a mano, que
 * funciona hasta que alguien se olvida de subirlo y despacha JavaScript nuevo contra una
 * caché vieja.
 *
 * OJO, esto NO es para las hojas de estilo. `compact.css?asdNNN` y `compact1.css?v=NNN`
 * se suben a mano a propósito y `tools/check_report_unread_navigation.php` verifica que
 * no retrocedan; pasarlas a filemtime rompería ese control. La regla práctica: los CSS
 * llevan número a mano y verificado, el JavaScript lleva filemtime.
 *
 * Y OJO con el deploy: cambiar la versión cambia la URL, y Cloudflare SÍ cachea `.js`
 * por extensión (a diferencia de `.php`). Pedir la URL nueva antes de que el deploy haya
 * aterrizado en la Pi guarda el archivo VIEJO bajo la clave NUEVA durante cuatro horas.
 * Está explicado en AGENTS.md, en la sección de deploy.
 */

/**
 * Devuelve la versión de un archivo estático del repo, lista para pegar en un `?v=`.
 *
 * $ruta es relativa a la raíz del repo ('crypt.js', 'js/foo.js'). Si el archivo no está,
 * devuelve una constante en vez de una cadena vacía o un warning: una URL sin versión se
 * cachea para siempre, que es peor que una versión inútil.
 */
function assetVersion($ruta) {
    static $cache = array();

    if(isset($cache[$ruta])) {
        return $cache[$ruta];
    }

    $raiz = dirname(dirname(__FILE__));
    // Nada de rutas que se escapen del repo: esto se pega en una URL.
    $limpia = str_replace('\\', '/', (string)$ruta);
    if($limpia === '' || strpos($limpia, '..') !== false || $limpia[0] === '/') {
        return $cache[$ruta] = '0';
    }

    $completa = $raiz.'/'.$limpia;
    $mtime = @filemtime($completa);
    $cache[$ruta] = ($mtime === false) ? '0' : (string)$mtime;
    return $cache[$ruta];
}

/** El `<script src>` completo, para no repetir el patrón en cada plantilla. */
function assetScriptTag($ruta) {
    return '<script src="'.htmlspecialchars($ruta.'?v='.assetVersion($ruta), ENT_QUOTES).'" type="text/javascript"></script>';
}
