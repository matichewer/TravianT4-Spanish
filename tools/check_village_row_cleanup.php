<?php
/**
 * Cuando una aldea desaparece, sus filas tienen que desaparecer con ella.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_village_row_cleanup.php
 *
 * El fallo que fija. Las tablas por-aldea no usan todas la misma columna: casi todas son
 * `vref`, pero `bdata` es `wid`. Tres lugares que borraban una aldea escribían `wref` o
 * `wid` sobre tablas que se indexan por `vref`, y como el shim legacy `mysql_query()`
 * devuelve `false` sin que nadie lo mire, el DELETE fallaba **en silencio**:
 *
 *   - `Automation::destroyCatapultedVillage()` — arrasar una aldea con catapultas;
 *   - `Automation::ClearUser()` — el jugador que borra su cuenta;
 *   - `GameEngine/Admin/database.php` — borrar una aldea desde el panel.
 *
 * En el mundo local quedaron 88 filas de `abdata` (mejoras de herrería) sobreviviendo a
 * su aldea. Y no es cosmético: `wref` es el id de la casilla del mapa y `generateBase()`
 * reutiliza casillas libres, así que la próxima aldea fundada sobre esa casilla nacía con
 * las mejoras de herrería del dueño anterior — `addABTech()` hace un INSERT que ahí choca
 * con la clave duplicada y la aldea nueva se queda con las viejas.
 *
 * Las que ya quedaron se limpian con `tools/fix_orphan_village_rows.php`.
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

// =====================================================================================
section('A. Cada tabla por aldea se borra por SU columna');
// =====================================================================================
//
// La columna correcta sale del esquema vivo, no de una lista escrita acá: si mañana una
// tabla cambia de columna, esta prueba lo nota sola.
$perVillage = array('abdata', 'tdata', 'fdata', 'units', 'enforcement', 'bdata', 'market', 'training');
$keyColumn = array();
foreach($perVillage as $table) {
    $exists = $database->query_return("SHOW TABLES LIKE '".TB_PREFIX.$table."'");
    if(!is_array($exists) || !count($exists)) {
        continue;
    }
    foreach(array('vref', 'wid', 'wref') as $candidate) {
        $found = $database->query_return("SHOW COLUMNS FROM `".TB_PREFIX.$table."` LIKE '".$candidate."'");
        if(is_array($found) && count($found)) {
            $keyColumn[$table] = $candidate;
            break;
        }
    }
    check(isset($keyColumn[$table]),
        'la tabla '.$table.' tiene alguna de las columnas de aldea conocidas');
}
check(isset($keyColumn['abdata']) && $keyColumn['abdata'] === 'vref',
    'abdata se indexa por `vref` (es de donde salió todo esto)');
check(isset($keyColumn['bdata']) && $keyColumn['bdata'] === 'wid',
    'y bdata por `wid`, que es justamente por qué no alcanza con recordar una sola');

// Ninguna sentencia puede nombrar una columna que esa tabla no tiene.
$sources = array(
    'GameEngine/Automation.php',
    'GameEngine/Database/db_MYSQLi.php',
    'GameEngine/Admin/database.php'
);
foreach($sources as $relative) {
    $path = $root.'/'.$relative;
    if(!is_file($path)) {
        continue;
    }
    $source = file_get_contents($path);
    foreach($keyColumn as $table => $correct) {
        // `DELETE FROM ..."tabla" ... WHERE <col>` y `UPDATE ..."tabla" ... SET/WHERE <col>`,
        // con la tabla y la columna escritas como literal. Se corta en el cierre de la
        // sentencia para no cruzar a la siguiente.
        $pattern = '/(?:DELETE\s+FROM|UPDATE)\s+["\'`]?\s*\.?\s*TB_PREFIX\s*\.?\s*["\'`]?'
            .preg_quote($table, '/').'(?![a-z0-9_])[^;]{0,200}?\bWHERE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*=/is';
        if(!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach($matches as $match) {
            $used = strtolower($match[1]);
            // Sólo interesan las columnas "de aldea": estas tablas tienen además su propia
            // clave `id` y filtrar por ella es correcto (borrar UNA oferta del mercado, UNA
            // orden de entrenamiento). El bug es nombrar la columna de aldea equivocada.
            if(!in_array($used, array('vref', 'wid', 'wref'), true)) {
                continue;
            }
            check($used === $correct,
                $relative.': una sentencia sobre `'.$table.'` filtra por `'.$used
                    .'` y la columna de esa tabla es `'.$correct.'`');
        }
    }
}

// El duplicado muerto del panel no puede volver: era una copia byte a byte de
// GameEngine/Admin/database.php que nadie incluía, con el mismo bug adentro.
check(!is_file($root.'/GameEngine/Admin/database1.php'),
    'GameEngine/Admin/database1.php era una copia muerta y no debe volver');

// =====================================================================================
section('B. El mundo real, a modo informativo');
// =====================================================================================
//
// No hace fallar el checker: un mundo que todavía no corrió el fix arrastra filas viejas
// y eso no es un defecto del código de hoy. Lo que falla es el código, arriba.
$orphans = 0;
foreach($keyColumn as $table => $column) {
    // `units` y `enforcement` cuelgan también de un oasis, que vive en `odata` y no en
    // `vdata`: contarlos contra `vdata` a secas daría los 4.000 oasis del mapa.
    $oasisAllowed = in_array($table, array('units', 'enforcement'), true);
    $join = "LEFT JOIN ".TB_PREFIX."vdata v ON v.wref = t.`".$column."`";
    $where = "v.wref IS NULL";
    if($oasisAllowed) {
        $join .= " LEFT JOIN ".TB_PREFIX."odata o ON o.wref = t.`".$column."`";
        $where .= " AND o.wref IS NULL";
    }
    $rows = $database->query_return(
        "SELECT COUNT(*) AS n FROM ".TB_PREFIX.$table." t ".$join." WHERE ".$where
    );
    $count = is_array($rows) && count($rows) ? (int)$rows[0]['n'] : 0;
    if($count > 0) {
        $orphans += $count;
        echo '[--] '.$table.': '.$count.' fila(s) huérfana(s)'.PHP_EOL;
    }
}
if($orphans > 0) {
    echo '[--] corré tools/fix_orphan_village_rows.php --aplicar para limpiarlas'.PHP_EOL;
} else {
    echo 'sin filas huérfanas en este mundo'.PHP_EOL;
}

// =====================================================================================
echo PHP_EOL.($failures
    ? $failures.' FALLA(S) sobre '.$checks.' comprobaciones'
    : 'Limpieza de filas por aldea: OK ('.$checks.' comprobaciones)').PHP_EOL;
exit($failures ? 1 : 0);
