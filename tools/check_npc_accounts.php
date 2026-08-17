<?php
/**
 * La frontera entre cuentas del sistema y de jugadores vive en un solo lugar.
 *
 * El fallo que fija: el motor no tenía nombre para "esto no es un jugador", así que la
 * idea estaba escrita de siete maneras distintas —`owner <= 4`, `owner > 4`,
 * `username = 'Natars'`, `tribe != 0 AND tribe != 4 AND tribe != 5`— repartidas por el
 * motor, la capa de base de datos y las páginas públicas, y ninguna sabía de las otras.
 * De ahí salió la hambruna que vaciaba sola las Aldeas de la Maravilla, y de ahí salió
 * que el contador de conectados siguiera contando a Support.
 *
 * Cubre:
 *   A. La frontera clasifica bien a las cuatro cuentas del sistema y a los jugadores.
 *   B. El instalador sigue sembrando los ids que la frontera da por sentados.
 *   C. El resolutor de la cuenta natar funciona contra la base real.
 *   D. Nadie volvió a escribir la frontera a mano fuera de GameEngine/Accounts.php,
 *      sin confundirla con las comparaciones de tipo de campo, que se le parecen.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_npc_accounts.php
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

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

// --- A. La frontera clasifica -------------------------------------------------------
check(function_exists('isSystemAccount') && function_exists('isPlayerAccount'),
    'GameEngine/Accounts.php se carga junto con la capa de base de datos');

$systemAccounts = systemAccounts();
check(count($systemAccounts) === 4 && array_keys($systemAccounts) === array('Support', 'Natars', 'Nature', 'Multihunter'),
    'las cuatro cuentas del sistema están nombradas: '.implode(', ', array_keys($systemAccounts)));

foreach($systemAccounts as $name => $uid) {
    check(isSystemAccount($uid) && !isPlayerAccount($uid),
        "$name (uid $uid) cuenta como cuenta del sistema");
}
check(isPlayerAccount(lastSystemAccountId() + 1) && !isSystemAccount(lastSystemAccountId() + 1),
    'el primer id por encima del rango del sistema cuenta como jugador');
check(!isSystemAccount(0) && !isPlayerAccount(0),
    'el id 0 no es ni una cosa ni la otra: no existe');

check(strpos(playerAccountSql('owner'), '`owner`') === 0,
    'playerAccountSql() entrecomilla la columna: '.playerAccountSql('owner'));
check(playerAccountSql('owner') !== systemAccountSql('owner'),
    'los dos fragmentos SQL son complementarios, no el mismo');

// --- B. El instalador siembra esos ids ----------------------------------------------
foreach($systemAccounts as $name => $uid) {
    $row = $database->query_return("SELECT id FROM ".TB_PREFIX."users WHERE username = '".mysql_real_escape_string($name)."'");
    $actual = is_array($row) && isset($row[0]['id']) ? (int)$row[0]['id'] : 0;
    check($actual === (int)$uid,
        "el mundo tiene a $name en el uid $uid".($actual === (int)$uid ? '' : " (encontrado: $actual)"));
}

$strays = $database->query_return(
    "SELECT username FROM ".TB_PREFIX."users WHERE ".systemAccountSql('id')
    ." AND username NOT IN ('".implode("','", array_keys($systemAccounts))."')"
);
check(empty($strays),
    'no hay cuentas de jugador dentro del rango reservado al sistema');

// El fragmento de los contadores públicos tiene que dejar afuera a las cuatro cuentas.
// El filtro por tribu que había antes no lo lograba: Support es tribu 1, así que contaba
// como jugador conectado.
$counted = $database->query_return(
    "SELECT COUNT(*) AS n FROM ".TB_PREFIX."users WHERE ".playerAccountSql('id')
    ." AND id IN (".implode(',', $systemAccounts).")"
);
check((int)$counted[0]['n'] === 0,
    'el fragmento de jugadores excluye a las cuatro cuentas del sistema');

$oldFilter = $database->query_return(
    "SELECT COUNT(*) AS n FROM ".TB_PREFIX."users WHERE tribe != 0 AND tribe != 4 AND tribe != 5"
    ." AND id IN (".implode(',', $systemAccounts).")"
);
check((int)$oldFilter[0]['n'] > 0,
    'y el filtro por tribu que reemplazó no lo lograba: dejaba pasar '
    .(int)$oldFilter[0]['n'].' cuenta(s) del sistema');

// --- C. El resolutor de la cuenta natar ---------------------------------------------
check(natarsAccountId() === (int)$systemAccounts['Natars'],
    'natarsAccountId() resuelve a '.natarsAccountId());

// --- D. Nadie reescribió la frontera a mano -----------------------------------------
// Los patrones apuntan al identificador que rodea la comparación, no a un `<= 4` pelado:
// Building.php y Automation.php comparan tipos de campo de recurso contra ese mismo
// número y no tienen nada que ver con esto.
$spellings = array(
    '/\bowner\b\s*(<=|>|<|>=)\s*[\'"]?4[\'"]?/i' => 'comparación directa de `owner` contra el rango del sistema',
    '/\$\w*(uid|owner)\w*\s*(<=|>=)\s*4\b/i'     => 'comparación directa de un id de usuario contra el rango del sistema',
    // El lookbehind deja pasar la asignación PHP `$username = "Natars"`, que no es una
    // grafía de la frontera sino el instalador nombrando la cuenta que está creando.
    '/(?<![\$\w])username`?\s*=\s*\\\\?[\'"]Natars[\'"]/i' => "búsqueda de la cuenta natar por nombre (usá natarsAccountId())",
    '/tribe\s*!=\s*0\s*AND\s*tribe\s*!=\s*4/i'   => 'contador de jugadores filtrando por tribu (usá playerAccountSql())',
    // La octava grafía: las clasificaciones excluían a las cuatro cuentas del sistema por
    // la conjunción de dos condiciones sueltas —Natars y Nature caían por tribu, Support y
    // Multihunter por `access`—. Daba el resultado correcto de casualidad.
    //
    // Se exige que `access` aparezca cerca: `tribe <= 3` a secas es un recorrido de las
    // tres tribus jugables (Battle.php lo hace varias veces) y no tiene nada que ver.
    '/tribe\s*<=\s*3[^;]{0,120}access\s*</is'    => 'clasificación filtrando por tribu (usá playerAccountSql())',
    '/access\s*<[^;]{0,120}tribe\s*<=\s*3/is'    => 'clasificación filtrando por tribu (usá playerAccountSql())'
);

$exempt = array(
    'GameEngine/Accounts.php',      // es el dueño de la frontera
    'tools/check_npc_accounts.php', // este archivo lleva los patrones
);

$scanned = 0;
$offences = array();
$directory = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($file) {
            $name = $file->getFilename();
            if($file->isDir()) {
                return !in_array($name, array('.git', 'gpack', 'img', 'openspec', 'docker'), true);
            }
            return preg_match('/\.(php|tpl)$/', $name) === 1;
        }
    )
);
foreach($directory as $file) {
    $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
    if(in_array($relative, $exempt, true)) {
        continue;
    }
    $scanned++;
    $source = file_get_contents($file->getPathname());
    foreach($spellings as $pattern => $what) {
        if(preg_match($pattern, $source, $match)) {
            $offences[] = $relative.': '.$what.'  ->  '.trim($match[0]);
        }
    }
}

check($scanned > 100, "se revisaron $scanned archivos PHP y de plantilla");
check(empty($offences), 'ningún archivo reescribe la frontera a mano');
foreach($offences as $offence) {
    echo '        '.$offence.PHP_EOL;
}

// El escáner tiene que seguir tolerando lo que se le parece pero es otra cosa.
$fieldTypeComparisons = array(
    'GameEngine/Building.php' => '$id <= 4',
    'GameEngine/Automation.php' => '$tid <= 4'
);
foreach($fieldTypeComparisons as $file => $snippet) {
    $source = file_get_contents($root.'/'.$file);
    $present = strpos($source, $snippet) !== false;
    $flagged = false;
    foreach($spellings as $pattern => $what) {
        if(preg_match($pattern, $snippet)) {
            $flagged = true;
        }
    }
    check($present && !$flagged,
        "el escáner no confunde `$snippet` de $file, que compara tipos de campo");
}

echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
