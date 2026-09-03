<?php
/**
 * El `?v=` de los archivos estáticos: que exista, y que no sea la hora actual.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_asset_versions.php
 *
 * `Templates/html.tpl` pedía el bundle del juego como `crypt.js?<?php echo time(); ?>`.
 * Eso no es un cache-buster, es un anti-caché: la URL cambia cada segundo, así que los
 * 427 KB del archivo (unos 99 KB comprimidos) se re-descargaban en CADA carga de página,
 * para siempre, y encima bloqueando el render porque el `<script>` está en el `<head>`.
 * En el panel de red se veía como el único recurso que nunca decía "cached", al lado de
 * un `dorf1.php` de 7 KB.
 *
 * Y `jquery-1.10.1.min.js` y `sandwich.js` tenían el problema simétrico: sin versión
 * ninguna, así que un cambio quedaba invisible detrás de la caché del navegador y de las
 * cuatro horas de Cloudflare.
 *
 * Las dos convenciones que conviven acá y que este checker no deja mezclar:
 *
 *  - Las **hojas de estilo** llevan número a mano (`compact.css?asdNNN`,
 *    `compact1.css?v=NNN`) y `tools/check_report_unread_navigation.php` verifica que no
 *    retrocedan. Pasarlas a filemtime rompería ese control, así que se pinea que siguen
 *    con su número literal.
 *  - El **JavaScript** lleva `filemtime()` vía `assetVersion()`, que es automático y no
 *    se puede olvidar de subir.
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

function pedir($ruta) {
    $ctx = stream_context_create(array('http' => array('method' => 'GET', 'ignore_errors' => true, 'timeout' => 20)));
    $cuerpo = @file_get_contents('http://127.0.0.1'.$ruta, false, $ctx);
    return $cuerpo === false ? '' : $cuerpo;
}

echo "== 1. Ningún recurso se versiona con la hora ==\n";

// El bug original. Se barre el repo entero porque el patrón es fácil de repetir y no
// falla de forma visible: la página anda, sólo que nadie cachea nada nunca.
$muertas = array('hero_inventory3.php');
$archivos = array_merge(glob('*.php'), glob('Templates/*.tpl'), glob('Templates/*/*.tpl'), glob('Templates/*/*/*.tpl'));
$conReloj = array();
foreach($archivos as $archivo) {
    if(in_array(basename($archivo), $muertas, true)) { continue; }
    foreach(file($archivo) as $nro => $linea) {
        // Un time()/rand()/microtime() pegado a un ?  dentro de un src/href/url().
        if(!preg_match('~(src|href)\s*=|url\(~i', $linea)) { continue; }
        if(preg_match('~\?[^"\']*<\?(php)?\s*echo\s+(time|rand|mt_rand|microtime|uniqid)\s*\(~i', $linea)) {
            $conReloj[] = basename($archivo).':'.($nro + 1);
        }
    }
}
check(empty($conReloj), "ningún src/href se versiona con time(), rand() o uniqid()",
    empty($conReloj) ? '' : implode(' ', $conReloj));

echo "\n== 2. Todo script local de html.tpl tiene versión ==\n";

$html = file_get_contents('Templates/html.tpl');
check(strpos($html, "assetScriptTag('crypt.js')") !== false, "crypt.js sale de assetScriptTag()");

// Los <script src> que quedan escritos a mano tienen que llevar un ?v= igual.
preg_match_all('~<script[^>]*src="([^"]+)"~', $html, $m);
$sinVersion = array();
foreach($m[1] as $src) {
    if(preg_match('~^(https?:)?//~', $src)) { continue; }   // externo, no es nuestro
    if(strpos($src, '?') === false) { $sinVersion[] = $src; }
}
check(empty($sinVersion), "ningún script local de html.tpl queda sin versión",
    empty($sinVersion) ? '' : implode(' ', $sinVersion));

// Y las hojas de estilo siguen con su número a mano, que es la otra convención.
check(preg_match('~compact\.css\?asd\d+~', $html) === 1,
    "compact.css conserva su cache-buster numérico a mano");
check(strpos($html, 'compact.css?<?php') === false && strpos($html, "assetScriptTag('gpack") === false,
    "las hojas de estilo NO se pasaron a filemtime, que es lo que verifica el otro checker");

echo "\n== 3. La URL sólo cambia cuando cambia el archivo ==\n";

// La propiedad que le importa al jugador, medida sobre el servidor de verdad: dos cargas
// de la misma página tienen que pedir exactamente los mismos archivos.
$primera = pedir('/anmelden.php');
sleep(2);
$segunda = pedir('/anmelden.php');
preg_match_all('~(?:src|href)="([^"]+\.(?:js|css)[^"]*)"~', $primera, $a);
preg_match_all('~(?:src|href)="([^"]+\.(?:js|css)[^"]*)"~', $segunda, $b);
check(!empty($a[1]), "la página de prueba trae recursos", count($a[1])." recursos");
$movidos = array_values(array_diff($a[1], $b[1]));
check(empty($movidos), "dos cargas separadas por 2 s piden las mismas URLs",
    empty($movidos) ? '' : implode(' ', array_slice($movidos, 0, 3)));

// Y que crypt.js aparezca versionado en la salida real, no sólo en la plantilla.
check(preg_match('~crypt\.js\?v=\d+~', $primera) === 1, "crypt.js sale versionado en el HTML servido");

echo "\n== 4. assetVersion() ==\n";

$tmp = $root.'/tools/.assetversion_probe.tmp';
file_put_contents($tmp, 'uno');
touch($tmp, 1600000000);
clearstatcache();
$v1 = assetVersion('tools/.assetversion_probe.tmp');
check($v1 === '1600000000', "la versión es la fecha de modificación del archivo", $v1);

// La misma llamada dos veces tiene que dar lo mismo dentro del request.
check(assetVersion('tools/.assetversion_probe.tmp') === $v1, "la versión es estable dentro del mismo request");

// Y tiene que moverse cuando el archivo se toca. El caché interno es por request, así que
// se consulta con otra ruta hacia el mismo archivo para saltearlo.
$tmp2 = $root.'/tools/.assetversion_probe2.tmp';
file_put_contents($tmp2, 'dos');
touch($tmp2, 1700000000);
clearstatcache();
check(assetVersion('tools/.assetversion_probe2.tmp') === '1700000000',
    "un archivo con otra fecha da otra versión");

@unlink($tmp);
@unlink($tmp2);

// Un archivo que no está no puede devolver vacío: `foo.js?v=` se cachea para siempre.
$faltante = assetVersion('no_existe_este_archivo_12345.js');
check($faltante !== '' && $faltante !== false, "un archivo ausente devuelve una versión igual", var_export($faltante, true));

// Y la ruta se pega en una URL, así que no puede escaparse del repo.
foreach(array('../../etc/passwd', '/etc/passwd', '') as $malo) {
    $r = assetVersion($malo);
    check($r === '0', "assetVersion('".($malo === '' ? '(vacío)' : $malo)."') se rechaza", var_export($r, true));
}

echo "\n";
if($failures > 0) {
    echo "FALLARON $failures de $checks comprobaciones\n";
    exit(1);
}
echo "OK: $checks comprobaciones\n";
exit(0);
