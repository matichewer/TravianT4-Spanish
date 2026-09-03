<?php
/**
 * La caché del retrato del héroe: la huella de la URL y los headers de la respuesta.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_hero_image_cache.php
 *
 * `hero_image.php` era la URL más pedida del servidor -- 1346 pedidos en un día para un
 * solo jugador, contra 521 de `dorf1.php` -- y no mandaba un solo header de caché. Sin
 * `ETag` ni `Cache-Control` el navegador no tiene con qué revalidar, así que se bajaba los
 * 15 KB enteros en cada carga de página y el servidor volvía a apilar ocho capas PNG para
 * devolver exactamente los mismos bytes.
 *
 * El cache-buster que ya había en la URL no servía de nada: era `hero.hash`, que se
 * escribe al crear el héroe y nunca se actualiza -- `hero.php` edita la cara sin tocarlo.
 * Y `Templates/Profile/overview.tpl` usaba `md5($_GET['uid'])`, que es constante por
 * definición. O sea que las dos "versiones" eran constantes disfrazadas, y por eso poner
 * `max-age` largo sin arreglarlas primero habría congelado caras viejas durante un año.
 *
 * Lo que este checker cuida, en orden de gravedad:
 *
 *  1. Que la huella cambie ante CUALQUIER columna de `heroface`. Se barren todas las
 *     columnas de la tabla real, no una lista escrita acá: si mañana se agrega una que
 *     cambia el dibujo y la huella no la mira, esto falla.
 *  2. Que la URL y el ETag salgan de la misma función. Una URL que anuncia una versión y
 *     una respuesta que devuelve otra es peor que no cachear nada.
 *  3. Que un `?size=` desconocido no rompa. Antes dejaba la variable sin definir y se
 *     pedía 'img/hero/head//face0.png': 296 bytes que no son un PNG.
 *  4. Que el uid siga saliendo casteado a entero. Iba crudo a `HeroFace()`, que lo
 *     concatena en el SQL, en una página que no pide login: un UNION devolvía un retrato
 *     dibujado con las columnas del atacante.
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

/** Pide una URL al Apache local y devuelve [código, headers, cuerpo]. */
function pedir($ruta, $headersExtra = array()) {
    $ctx = stream_context_create(array('http' => array(
        'method' => 'GET',
        'header' => implode("\r\n", $headersExtra),
        'ignore_errors' => true,
        'timeout' => 20,
    )));
    $cuerpo = @file_get_contents('http://127.0.0.1'.$ruta, false, $ctx);
    $codigo = 0;
    $headers = array();
    if(isset($http_response_header)) {
        foreach($http_response_header as $h) {
            if(preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) { $codigo = (int)$m[1]; continue; }
            $partes = explode(':', $h, 2);
            if(count($partes) === 2) { $headers[strtolower(trim($partes[0]))] = trim($partes[1]); }
        }
    }
    return array($codigo, $headers, $cuerpo === false ? '' : $cuerpo);
}

$filaBase = array(
    'uid' => '1', 'beard' => '0', 'ear' => '1', 'eye' => '2', 'eyebrow' => '3',
    'face' => '1', 'hair' => '2', 'mouth' => '1', 'nose' => '0', 'color' => '2',
    'foot' => '0', 'helmet' => '0', 'horse' => '0', 'leftHand' => '0', 'rightHand' => '0',
);

echo "== 1. La huella cambia ante cualquier columna del héroe ==\n";

// Las columnas se leen de la tabla real: una lista escrita acá se desactualizaría igual
// que las que este cambio vino a eliminar.
$columnas = array();
$res = mysqli_query($database->connection, "SHOW COLUMNS FROM ".TB_PREFIX."heroface");
while($fila = mysqli_fetch_assoc($res)) { $columnas[] = $fila['Field']; }
check(count($columnas) >= 15, "se leyeron las columnas de heroface", count($columnas)." columnas");

foreach(array('head', 'body') as $variante) {
    $tam = ($variante === 'body') ? 'inventory' : 'sideinfo';
    $base = heroImageFingerprint($variante, $tam, $filaBase, 0);
    $sinCambio = array();
    foreach($columnas as $col) {
        if($col === 'uid') { continue; }
        $mutada = $filaBase;
        $mutada[$col] = (string)((int)$filaBase[$col] + 1);
        if(heroImageFingerprint($variante, $tam, $mutada, 0) === $base) { $sinCambio[] = $col; }
    }
    check(empty($sinCambio), "variante '$variante': toda columna de heroface mueve la huella",
        empty($sinCambio) ? '' : "no la mueven: ".implode(', ', $sinCambio));
}

// El ítem de armadura vive en otra tabla y sólo lo dibuja el cuerpo.
$cuerpoA = heroImageFingerprint('body', 'inventory', $filaBase, 0);
$cuerpoB = heroImageFingerprint('body', 'inventory', $filaBase, 87);
check($cuerpoA !== $cuerpoB, "la armadura equipada mueve la huella del cuerpo");
$cabezaA = heroImageFingerprint('head', 'sideinfo', $filaBase, 0);
$cabezaB = heroImageFingerprint('head', 'sideinfo', $filaBase, 87);
check($cabezaA === $cabezaB, "la armadura no mueve la huella de la cabeza, que no la dibuja");

// Tamaño, variante y versión del arte.
check(heroImageFingerprint('head', 'sideinfo', $filaBase) !== heroImageFingerprint('head', 'profile', $filaBase),
    "dos tamaños distintos tienen huellas distintas");
check(heroImageFingerprint('head', 'inventory', $filaBase) !== heroImageFingerprint('body', 'inventory', $filaBase),
    "cabeza y cuerpo tienen huellas distintas");
check(heroImageFingerprint('head', 'sideinfo', $filaBase) === heroImageFingerprint('head', 'sideinfo', $filaBase),
    "la huella es estable entre llamadas");
check(heroImageFingerprint('head', 'sideinfo', null) !== heroImageFingerprint('head', 'sideinfo', $filaBase),
    "un héroe sin fila tiene una huella propia");

// La huella cubre los datos, no el arte de img/hero. Esa es la razón de existir de la
// constante, y si alguien la borra el arte nuevo queda invisible por un año.
check(defined('HERO_IMAGE_ART_VERSION'), "existe la versión del arte, que es lo único que invalida un cambio de PNG");

echo "\n== 2. La URL y la respuesta no pueden divergir ==\n";

$uidPrueba = 1;
foreach(array('sideinfo', 'profile', 'inventory') as $tam) {
    $url = heroImageUrl($uidPrueba, $tam);
    $esperada = heroImageFingerprintFor($uidPrueba, $tam);
    check(strpos($url, 'v='.$esperada) !== false, "heroImageUrl('$tam') lleva la huella que calcula el motor", $url);

    list($codigo, $headers, $cuerpo) = pedir('/'.$url);
    $etag = isset($headers['etag']) ? trim($headers['etag'], '"') : '';
    check($codigo === 200, "GET $tam responde 200", "http $codigo");
    check($etag === $esperada, "el ETag de la respuesta es la huella de la URL", "etag=$etag esperada=$esperada");
    check(isset($headers['cache-control']) && strpos($headers['cache-control'], 'max-age=') !== false,
        "la respuesta manda Cache-Control", isset($headers['cache-control']) ? $headers['cache-control'] : 'ausente');
    check(isset($headers['content-length']) && (int)$headers['content-length'] === strlen($cuerpo),
        "la respuesta manda un Content-Length correcto");
    check(strncmp($cuerpo, "\x89PNG", 4) === 0, "el cuerpo es un PNG");

    // Y la revalidación: mismo ETag => 304 sin cuerpo.
    list($codigo304, $h304, $cuerpo304) = pedir('/'.$url, array('If-None-Match: "'.$esperada.'"'));
    check($codigo304 === 304 && $cuerpo304 === '', "con el mismo ETag responde 304 sin cuerpo", "http $codigo304, ".strlen($cuerpo304)." bytes");

    list($codigoOtro, , $cuerpoOtro) = pedir('/'.$url, array('If-None-Match: "0000deadbeef"'));
    check($codigoOtro === 200 && strlen($cuerpoOtro) > 0, "con otro ETag vuelve a mandar la imagen", "http $codigoOtro");
}

foreach(array('profile', 'inventory') as $tam) {
    $url = heroBodyUrl($uidPrueba, $tam);
    $esperada = heroBodyFingerprintFor($uidPrueba, $tam);
    check(strpos($url, 'v='.$esperada) !== false, "heroBodyUrl('$tam') lleva la huella que calcula el motor");
    list($codigo, $headers, ) = pedir('/'.$url);
    $etag = isset($headers['etag']) ? trim($headers['etag'], '"') : '';
    check($codigo === 200 && $etag === $esperada, "hero_body '$tam': el ETag coincide con la URL", "http $codigo etag=$etag");
}

echo "\n== 3. Ninguna plantilla vuelve a armar la URL a mano ==\n";

// El bug original era exactamente esto: cinco copias de la URL, tres con un cache-buster
// que nunca cambiaba. hero_inventory3.php queda afuera: no está referenciado desde ningún
// lado y tiene el uid 4 hardcodeado, o sea que es una página muerta.
$muertas = array('hero_inventory3.php');
$aMano = array();
foreach(array_merge(glob('*.php'), glob('Templates/*.tpl'), glob('Templates/*/*.tpl')) as $archivo) {
    if(in_array(basename($archivo), $muertas, true)) { continue; }
    foreach(file($archivo) as $nro => $linea) {
        if(!preg_match('~["\']hero_(image|body)\.php\?~', $linea)) { continue; }
        // hero.php arma la del editor a partir del helper y le agrega el {time}; se
        // reconoce porque la línea llama al helper.
        if(strpos($linea, 'heroImageUrl(') !== false || strpos($linea, 'heroBodyUrl(') !== false) { continue; }
        $aMano[] = basename($archivo).':'.($nro + 1);
    }
}
check(empty($aMano), "todas las URLs de retrato salen de heroImageUrl()/heroBodyUrl()",
    empty($aMano) ? '' : implode(' ', $aMano));

// Y que el cache-buster viejo no vuelva por la ventana.
$conHash = array();
foreach(array_merge(glob('*.php'), glob('Templates/*.tpl'), glob('Templates/*/*.tpl')) as $archivo) {
    foreach(file($archivo) as $nro => $linea) {
        if(preg_match('~hero_(image|body)\.php~', $linea) && preg_match("~\\\$hero\\['hash'\\]|md5\\(\\\$_GET~", $linea)) {
            $conHash[] = basename($archivo).':'.($nro + 1);
        }
    }
}
check(empty($conHash), "ninguna URL usa hero.hash ni md5(uid) como versión",
    empty($conHash) ? '' : implode(' ', $conHash));

echo "\n== 4. Los parámetros de la URL, que llegan de afuera ==\n";

// El uid iba crudo al SQL. Un UNION con las columnas del atacante devolvía un retrato
// distinto: la prueba de que la inyección se ejecutaba y se veía.
$inyeccion = '-1%20UNION%20SELECT%201,2,3,4,5,6,7,8,9,4,0,0,0,0,0';
list($c1, , $imgInexistente) = pedir('/hero_image.php?uid=-1&size=sideinfo');
list($c2, , $imgInyectada) = pedir('/hero_image.php?uid='.$inyeccion.'&size=sideinfo');
check($c1 === 200 && $c2 === 200, "las dos respuestas salen bien", "http $c1 / $c2");
check($imgInexistente === $imgInyectada,
    "un uid con SQL adentro rinde lo mismo que un uid inexistente, o sea que no se ejecuta");

// Y que el cast siga en el fuente, para que no vuelva por un refactor.
foreach(array('hero_image.php', 'hero_body.php') as $archivo) {
    $fuente = file_get_contents($archivo);
    check(preg_match('~\$uid\s*=\s*isset\(\$_GET\[.uid.\]\)\s*\?\s*\(int\)~', $fuente) === 1,
        "$archivo castea el uid a entero antes de usarlo");
}

// El tamaño desconocido.
foreach(array('hero_image.php' => 'head', 'hero_body.php' => 'body') as $archivo => $variante) {
    foreach(array('zzz', '', '../../etc/passwd') as $malo) {
        list($codigo, , $cuerpo) = pedir('/'.$archivo.'?uid=1&size='.urlencode($malo));
        check($codigo === 200 && strncmp($cuerpo, "\x89PNG", 4) === 0,
            "$archivo con size='".($malo === '' ? '(vacío)' : $malo)."' devuelve un PNG válido",
            "http $codigo, ".strlen($cuerpo)." bytes");
    }
    // Y que normalize devuelva siempre algo dibujable.
    foreach(array('zzz', '', 'sideinfo', 'profile', 'inventory') as $pedido) {
        $r = heroImageNormalizeSize($variante, $pedido);
        check(in_array($r, heroImageSizes($variante), true),
            "heroImageNormalizeSize('$variante', '".($pedido === '' ? '(vacío)' : $pedido)."') da un tamaño válido", $r);
    }
}

echo "\n";
if($failures > 0) {
    echo "FALLARON $failures de $checks comprobaciones\n";
    exit(1);
}
echo "OK: $checks comprobaciones\n";
exit(0);
