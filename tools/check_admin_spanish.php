<?php
/**
 * El panel de administración está en español, y se queda en español.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_admin_spanish.php
 *
 * El juego se tradujo hace tiempo, pero el panel se había quedado en inglés: `Admin/` y
 * `Admin/Templates/` eran el único rincón donde seguían las cadenas originales de TravianX.
 * Una traducción sin red se deshace sola —el próximo `.tpl` que alguien copie de otro trae
 * el inglés de vuelta—, así que esto barre las pantallas vivas buscando las palabras que un
 * texto en inglés casi siempre trae.
 *
 * Cómo se decide qué es "inglés" sin falsos positivos:
 *
 *   - Sólo se mira **texto visible**: nodos HTML entre `>` y `<`, y los atributos que el
 *     navegador muestra (`title`, `alt`, y el `value` de un botón). El código PHP, los
 *     nombres de campo, las URLs y el SQL quedan fuera a propósito: `name="villagename"` no
 *     es una traducción pendiente.
 *   - Una línea con acentos o signos de apertura se da por traducida. Es una heurística, y
 *     alcanza: en la práctica no hay frase en español de este panel sin uno.
 *   - No hay exclusiones: todo lo que queda bajo `Admin/` está vivo. Las nueve entradas
 *     viejas que no alcanzaba nadie (`home.php`, `Users.php`, `Onlines.php`, `ManageNews.php`,
 *     `news.php`, `top.php`, `jdf.php`, `404.php` y `login.php`), sus assets huérfanos
 *     (`img.php`, `login.css`, `loading.gif` y las 63 imágenes de `Admin/images/`) y el
 *     directorio `Admin/Templates/backup/` se borraron en vez de traducirse. Si alguna
 *     vuelve a aparecer, este checker la va a tratar como pantalla viva, que es lo correcto.
 *
 * La excepción declarada: la cita textual del oficial sobre la defensa de los artefactos,
 * que va en inglés a propósito porque es una cita.
 *
 * De paso el archivo cuida otras tres formas en que este panel se pudre en silencio, porque
 * salieron a la luz traduciéndolo y se detectan igual de barato: que los nombres de edificio
 * salgan de la única lista del juego y no de una copia, que cada `.tpl` se parsee (uno era
 * un error de sintaxis y su pantalla salía en blanco), y que los formularios apunten a un
 * archivo que exista.
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$failures = 0;
$checks = 0;
function check($ok, $message) {
    global $failures, $checks;
    $checks++;
    if(!$ok) {
        $failures++;
        echo '[FALLA] '.$message.PHP_EOL;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

/** Las pantallas vivas del panel. */
function adminLiveFiles($root) {
    $files = array_merge(
        glob($root.'/Admin/Templates/*.tpl'),
        glob($root.'/Admin/Templates/report/*.tpl'),
        glob($root.'/Admin/Mods/*.php'),
        glob($root.'/GameEngine/Admin/Mods/*.php'),
        array($root.'/Admin/admin.php', $root.'/Admin/ajax.php',
              $root.'/GameEngine/Admin/function.php')
    );
    sort($files);
    return array_values(array_filter($files, 'is_file'));
}

/**
 * Palabras que, en un texto visible, sólo aparecen en inglés.
 *
 * Se eligieron mirando lo que de verdad había en el panel. Faltan a propósito las que se
 * escriben igual en los dos idiomas (Total, Plus, Multihunter, Natar, IP, ID) y las que son
 * nombres propios.
 */
function adminEnglishWords() {
    return array('Delete', 'Edit', 'Remove', 'Change', 'Reset', 'Give', 'Show', 'Hide',
        'Search', 'Found', 'Village', 'Villages', 'Player', 'Players', 'User', 'Users',
        'Troops', 'Alliance', 'Access', 'Protection', 'Sitter', 'Sitters', 'Bonus', 'Week',
        'Level', 'Coordinates', 'Population', 'Tribe', 'Email', 'Password', 'Reason',
        'Duration', 'Forever', 'Capital', 'Owner', 'Buildings', 'Building', 'Oases',
        'Loyalty', 'Instructions', 'Activated', 'Create', 'Formula', 'Under Construction',
        'Statistics', 'Profile', 'Warehouse', 'Granary', 'Casualties', 'Attacker',
        'Defender', 'Bounty', 'Reinforcement', 'Description', 'Details', 'Location',
        'Quest', 'Members', 'Capacity', 'Accepted', 'Amount', 'Maximum', 'Resource',
        'Inhabitants', 'Founder', 'Execute', 'Banned', 'Online', 'Denied', 'Points',
        'Newsbox', 'Hostname', 'Mailbox', 'Prefix', 'Woodcutter', 'Cropland', 'Sawmill',
        'Brickyard', 'Bakery', 'Blacksmith', 'Marketplace', 'Embassy', 'Barracks',
        'Stable', 'Workshop', 'Academy', 'Cranny', 'Residence', 'Palace', 'Treasury',
        'Palisade', 'Brewery', 'Trapper', 'Trough');
}

/** Lo que se deja pasar, con el motivo. */
function adminAllowedEnglish() {
    return array(
        // Cita textual del manual oficial de Travian, en la pantalla de artefactos.
        'Defence values are based on the top 100 offensive armies of the game world'
    );
}

/** El texto que el navegador realmente muestra de una línea. */
function adminVisibleText($line) {
    $out = array();
    if(preg_match_all('/>([^<>]{2,200})</', $line, $m)) {
        foreach($m[1] as $t) { $out[] = $t; }
    }
    if(preg_match_all('/(?:title|alt)\s*=\s*"([^"]{2,200})"/', $line, $m)) {
        foreach($m[1] as $t) { $out[] = $t; }
    }
    // El `value` de un botón se ve; el de un campo oculto o de datos, no.
    if(preg_match('/type\s*=\s*"(submit|button|reset)"/i', $line)
        && preg_match_all('/value\s*=\s*"([^"]{2,200})"/', $line, $m)) {
        foreach($m[1] as $t) { $out[] = $t; }
    }
    // Y los mensajes que el propio PHP escupe.
    if(preg_match_all('/(?:echo|die|print)\s*\(?\s*[\'"]([^\'"]{4,200})[\'"]/', $line, $m)) {
        foreach($m[1] as $t) { $out[] = $t; }
    }
    return $out;
}

// =====================================================================================
section('A. Ninguna pantalla viva del panel muestra texto en inglés');
// =====================================================================================
$words = adminEnglishWords();
$allowed = adminAllowedEnglish();
$pattern = '/\b('.implode('|', array_map(function ($w) {
    return preg_quote($w, '/');
}, $words)).')\b/';

$offenders = array();
foreach(adminLiveFiles($root) as $file) {
    $relative = substr($file, strlen($root) + 1);
    foreach(explode("\n", (string)file_get_contents($file)) as $number => $line) {
        // Una línea con acentos o signos de apertura ya está traducida.
        if(preg_match('/[áéíóúñüÁÉÍÓÚÑ¿¡]/u', $line)) {
            continue;
        }
        foreach(adminVisibleText($line) as $text) {
            $text = trim($text);
            if($text === '' || strpos($text, '<?') !== false) {
                continue;
            }
            $skip = false;
            foreach($allowed as $ok) {
                if(strpos($text, $ok) !== false) { $skip = true; break; }
            }
            if($skip || !preg_match($pattern, $text)) {
                continue;
            }
            $offenders[] = $relative.':'.($number + 1).': '.$text;
        }
    }
}
foreach($offenders as $offender) {
    check(false, 'texto en inglés en el panel — '.$offender);
}
check(count($offenders) === 0,
    'el panel de administración no tiene texto visible en inglés ('.count($offenders).' encontrados)');

// =====================================================================================
section('B. Los nombres de edificio salen de la única lista');
// =====================================================================================
//
// El panel llevaba su propia copia del listado de edificios —la cuarta— y ya había derivado:
// estaba en inglés salvo el 34, no conocía el gid 42 (el Gran taller salía como "Error") y
// decía "Treasury" donde el juego dice Tesoro. Traducirla habría sido crear una cuarta lista
// que traducir de nuevo la próxima vez.
$funct = (string)file_get_contents($root.'/GameEngine/Admin/function.php');
check(strpos($funct, 'return buildingDisplayName(') !== false,
    'el panel pide los nombres de edificio a buildingDisplayName()');
check(strpos($funct, '$build = "Woodcutter"') === false,
    'y ya no lleva su propia lista escrita a mano');
check(preg_match('/case \d+:\s*\$build\s*=/', $funct) !== 1,
    'ni un solo `case` con nombre de edificio quedó en el panel');

$editVillage = (string)file_get_contents($root.'/Admin/Templates/editVillage.tpl');
check(strpos($editVillage, 'Horse Drinking Trough') === false,
    'la referencia de posiciones tampoco escribe ningún nombre a mano');
check(strpos($editVillage, '$i <= 42') !== false,
    'y recorre hasta el 42, así que el Gran taller figura');

// =====================================================================================
section('C. Las guardas de acceso hablan español y siguen guardando');
// =====================================================================================
$guarded = 0;
foreach(array_merge(glob($root.'/Admin/Mods/*.php'), glob($root.'/GameEngine/Admin/Mods/*.php'),
    glob($root.'/Admin/Templates/*.tpl')) as $file) {
    $source = (string)file_get_contents($file);
    if(strpos($source, 'Access Denied') !== false) {
        check(false, basename($file).': la guarda de acceso sigue en inglés');
    }
    if(strpos($source, 'Acceso denegado') !== false) {
        $guarded++;
    }
}
check($guarded >= 18, 'siguen en pie las guardas de acceso traducidas ('.$guarded.')');

// =====================================================================================
section('D. Las pantallas del panel se parsean');
// =====================================================================================
//
// `renameVillage.tpl` era un error de sintaxis: abría `if(isset($id)) {` y el archivo
// terminaba en `</table>` sin cerrarlo, así que la pantalla salía en blanco. Un `.tpl` de
// este panel es PHP, y php -l lo dice en un segundo.
foreach(adminLiveFiles($root) as $file) {
    $output = array();
    $status = 0;
    exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);
    check($status === 0, substr($file, strlen($root) + 1).': '.implode(' ', $output));
}

// =====================================================================================
section('E. Los formularios y las imágenes apuntan a un archivo que existe');
// =====================================================================================
//
// Tres formularios de `village.tpl` —cambiar el dueño de una aldea, renombrarla y recalcular
// su almacén— apuntaban a `GameEngine/Admin/mods/` con eme minúscula, y el directorio es
// `Mods`. En Linux eso es un 404, o sea que las tres acciones no funcionaron nunca: el
// navegador se iba a una página de error y la aldea quedaba igual. No es un problema de
// idioma, pero es el mismo tipo de podredumbre silenciosa y se detecta igual de barato.
$formTargets = 0;
foreach(adminLiveFiles($root) as $file) {
    $relative = substr($file, strlen($root) + 1);
    // La ruta se resuelve desde la página que EMITE el HTML, no desde el archivo: los
    // `.tpl` los incluye `Admin/admin.php`, así que un `../GameEngine/...` sale de `Admin/`.
    $directory = strpos($relative, 'Admin/') === 0 ? $root.'/Admin' : dirname($file);
    if(!preg_match_all('/(?:action|formaction)\s*=\s*"([^"?#]+)/', (string)file_get_contents($file), $m)) {
        continue;
    }
    foreach($m[1] as $target) {
        $target = trim($target);
        // Sólo rutas relativas a un archivo del repo: las vacías y las absolutas no se miran.
        if($target === '' || $target[0] === '/' || strpos($target, '://') !== false) {
            continue;
        }
        $formTargets++;
        check(is_file($directory.'/'.$target),
            $relative.': el formulario apunta a "'.$target.'" y ese archivo no existe');
    }
}
check($formTargets > 0, 'se revisó el destino de los formularios del panel ('.$formTargets.')');

// Lo mismo con las imágenes y las hojas de estilo, que estaban peor que los formularios.
// Ocho `<img>` de cinco pantallas pedían `../img/Admin/del.gif` con A mayúscula y el
// directorio es `img/admin`, así que los iconos de borrar una aldea y de levantar una
// sanción salían rotos. Otras 30 —las 28 de los informes de batalla, que son una copia de
// las del juego— pedían `img/x.gif` sin el `../`, y las rutas se resuelven desde `Admin/`,
// no desde la raíz: los informes del panel no dibujaban una sola unidad. Y seis plantillas
// cargaban `gpack/travian_default/lang/**en**/compact.css`, un idioma que ese pack no trae.
// Se miran sólo las rutas literales —sin PHP adentro— porque una ruta armada en tiempo de
// ejecución no se puede resolver acá.
$imageTargets = 0;
foreach(adminLiveFiles($root) as $file) {
    $relative = substr($file, strlen($root) + 1);
    $directory = strpos($relative, 'Admin/') === 0 ? $root.'/Admin' : dirname($file);
    if(!preg_match_all('~(?:src|href)\s*=\s*\\\\?["\']([^"\'<>$]+\.(?:gif|png|jpg|jpeg|css|js))~', (string)file_get_contents($file), $m)) {
        continue;
    }
    foreach($m[1] as $target) {
        $target = trim($target);
        if($target === '' || $target[0] === '/' || strpos($target, '://') !== false) {
            continue;
        }
        $imageTargets++;
        check(is_file($directory.'/'.$target),
            $relative.': el recurso "'.$target.'" no existe (¿le falta un ../, o son mayúsculas?)');
    }
}
check($imageTargets > 0, 'se revisaron las imágenes y hojas de estilo del panel ('.$imageTargets.')');

echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Panel de administración en español: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
