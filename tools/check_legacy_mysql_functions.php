<?php
/**
 * Verifica que todas las llamadas a funciones legacy mysql_*() apunten a
 * alguno de los shims definidos al final de GameEngine/Database/db_MYSQLi.php.
 *
 * PHP 7 eliminó la extensión mysql, así que un mysql_*() sin shim revienta con
 * "Call to undefined function" recién cuando se ejecuta esa rama concreta.
 * El caso que motivó este checker fue mysql_escape_string() en
 * Templates/Build/26.tpl, que solo corría al enviar el formulario de cambio de
 * capital del palacio.
 *
 * $legacyDeadFiles es la foto de los archivos que ya estaban rotos de antes:
 * restos de TravianX (el panel Admin viejo, el instalador, lrpayment, …) que
 * abren su propia conexión con mysql_connect(). No se arreglan, solo se
 * ignoran; lo que este checker cuida es que ningún archivo vivo se sume a la
 * lista. Si borrás uno de esos archivos, sacalo también de acá.
 */

$root = dirname(__DIR__);
$shimFile = $root . '/GameEngine/Database/db_MYSQLi.php';

$legacyDeadFiles = array(
    'Admin/Mods/addTroops.php',
    'Admin/Mods/cp.php',
    'Admin/Mods/deletemedalbyuser.php',
    'Admin/Mods/deletemedalbyweek.php',
    'Admin/Mods/editUser.php',
    'Admin/Mods/gold.php',
    'Admin/Mods/gold_1.php',
    'Admin/Mods/medals.php',
    'Admin/Mods/renameVillage.php',
    'Admin/Mods/sendMessage.php',
    'Admin/Onlines.php',
    'Admin/Templates/village.tpl',
    'GameEngine/Admin/Mods/delallymedalbyaid.php',
    'GameEngine/Admin/Mods/delallymedalbyweek.php',
    'GameEngine/Admin/Mods/deletemedalbyuser.php',
    'GameEngine/Admin/Mods/deletemedalbyweek.php',
    'GameEngine/Admin/Mods/editBuildings.php',
    'GameEngine/Admin/Mods/giveResBonus.php',
    'GameEngine/Admin/Mods/recalcWH.php',
    'GameEngine/Admin/Mods/sendMessage.php',
    'GameEngine/Admin/Mods/silver.php',
    'GameEngine/Admin/Mods/silver_1.php',
    'GameEngine/Admin/database.php',
    'GameEngine/Admin/database1.php',
    'Templates/Manual/52.tpl',
    'cn.php',
    'install/include/database.php',
    'install/process.php',
    'install/uninstall.php',
    'lrpayment/payment.php',
    'lrpayment/payment_fail.php',
    'lrpayment/payment_success.php',
    'massmessage.php',
    'password.php',
    'sysmsg.php',
    'winner.php',
);

$shimSource = file_get_contents($shimFile);
if ($shimSource === false) {
    fwrite(STDERR, "No se pudo leer $shimFile\n");
    exit(1);
}

preg_match_all('/^\s*function\s+(mysql_[a-z0-9_]+)\s*\(/im', $shimSource, $m);
$defined = array_map('strtolower', $m[1]);

if (!$defined) {
    fwrite(STDERR, "No se encontró ningún shim mysql_*() en db_MYSQLi.php\n");
    exit(1);
}

$errors = array();
$scanned = 0;
$calls = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($current) {
            $name = $current->getFilename();
            if ($current->isDir()) {
                return !in_array($name, array('.git', 'node_modules', 'gpack'), true);
            }
            return (bool)preg_match('/\.(php|tpl|inc)$/i', $name);
        }
    )
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    if ($path === $shimFile || in_array($relative, $legacyDeadFiles, true)) {
        continue;
    }

    $source = file_get_contents($path);
    if ($source === false) {
        continue;
    }
    $scanned++;

    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }
        $name = strtolower($token[1]);
        if (strpos($name, 'mysql_') !== 0 || strpos($name, 'mysqli_') === 0) {
            continue;
        }

        // Solo interesa una llamada: nombre seguido de "(", sin -> ni :: ni
        // "function" delante (métodos y definiciones no usan el shim global).
        $next = null;
        for ($j = $i + 1; isset($tokens[$j]); $j++) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            $next = $tokens[$j];
            break;
        }
        if ($next !== '(') {
            continue;
        }

        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            $prev = $tokens[$j];
            break;
        }
        if (is_array($prev) && in_array($prev[0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW), true)) {
            continue;
        }

        $calls++;
        if (!in_array($name, $defined, true) && !function_exists($name)) {
            $errors[] = $relative . ':' . $token[2] . ' llama a ' . $token[1] . '()';
        }
    }
}

if ($errors) {
    echo "FALLA: llamadas a funciones mysql_*() sin shim en db_MYSQLi.php\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\nShims disponibles: " . implode(', ', $defined) . "\n";
    exit(1);
}

echo "OK: $calls llamadas mysql_*() en $scanned archivos, todas con shim.\n";
exit(0);
