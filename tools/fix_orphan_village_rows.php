<?php
/**
 * Barre las filas por-aldea que quedaron apuntando a aldeas que ya no existen.
 *
 *   docker compose exec -T web php /var/www/html/tools/fix_orphan_village_rows.php
 *   docker compose exec -T web php /var/www/html/tools/fix_orphan_village_rows.php --aplicar
 *
 * Por defecto sólo informa; escribe con `--aplicar`, como el resto de los `fix_*`.
 *
 * De dónde salen. `abdata` (mejoras de herrería) se indexa por `vref`, pero los tres
 * lugares que borraban una aldea lo hacían por `wref`/`wid`, columnas que no existen: el
 * shim legacy `mysql_query()` devuelve false, nadie mira el resultado y la fila sobrevive
 * a la aldea. En este mundo quedaron 88 así. No es cosmético: `wref` es el id de la
 * casilla del mapa y `generateBase()` reutiliza casillas libres, así que la próxima aldea
 * fundada sobre esa casilla nacía con las mejoras de herrería del dueño anterior — y
 * `addABTech()` hace un INSERT que ahí falla por clave duplicada, o sea que la aldea nueva
 * se queda con las viejas y nadie se entera.
 *
 * OJO con los oasis: `units` guarda también la guarnición de cada oasis, y un oasis vive
 * en `odata`, no en `vdata`. Barrer `units` contra `vdata` a secas borraría los animales
 * de los 4.000 oasis del mapa. Por eso cada tabla declara si acepta oasis o no.
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
$apply = in_array('--aplicar', $argv, true);

/**
 * Las tablas que cuelgan de una aldea, y si su `vref` puede ser además un oasis.
 *
 * `units` y `enforcement` sí: un oasis tiene guarnición propia y puede recibir refuerzos.
 * Las demás son estrictamente de aldea.
 */
$tables = array(
    'abdata'      => array('column' => 'vref', 'oasis' => false),
    'tdata'       => array('column' => 'vref', 'oasis' => false),
    'fdata'       => array('column' => 'vref', 'oasis' => false),
    'units'       => array('column' => 'vref', 'oasis' => true),
    'enforcement' => array('column' => 'vref', 'oasis' => true),
    'bdata'       => array('column' => 'wid',  'oasis' => false),
    'market'      => array('column' => 'vref', 'oasis' => false),
    'training'    => array('column' => 'vref', 'oasis' => false)
);

$P = TB_PREFIX;
$totalFound = 0;
$totalDeleted = 0;

echo ($apply ? 'APLICANDO' : 'SIMULACIÓN (agregá --aplicar para escribir)').PHP_EOL.PHP_EOL;

foreach($tables as $table => $config) {
    // Una tabla que este mundo no tenga simplemente se saltea.
    $exists = $database->query_return("SHOW TABLES LIKE '".$P.$table."'");
    if(!is_array($exists) || !count($exists)) {
        continue;
    }
    $column = $config['column'];
    $columns = $database->query_return("SHOW COLUMNS FROM `".$P.$table."` LIKE '".$column."'");
    if(!is_array($columns) || !count($columns)) {
        echo str_pad($table, 14)." [--] no tiene la columna `".$column."`, se saltea".PHP_EOL;
        continue;
    }

    $where = "v.wref IS NULL";
    $join = "LEFT JOIN ".$P."vdata v ON v.wref = t.`".$column."`";
    if($config['oasis']) {
        $join .= " LEFT JOIN ".$P."odata o ON o.wref = t.`".$column."`";
        $where .= " AND o.wref IS NULL";
    }

    $rows = $database->query_return(
        "SELECT COUNT(*) AS n FROM ".$P.$table." t ".$join." WHERE ".$where
    );
    $found = is_array($rows) && count($rows) ? (int)$rows[0]['n'] : 0;
    $totalFound += $found;

    if($found === 0) {
        echo str_pad($table, 14)." OK".PHP_EOL;
        continue;
    }

    echo str_pad($table, 14).' '.$found.' fila(s) huérfana(s)';
    if($apply) {
        $deleted = mysqli_query($database->connection,
            "DELETE t FROM ".$P.$table." t ".$join." WHERE ".$where);
        if($deleted === false) {
            echo '  -> ERROR: '.mysqli_error($database->connection);
        } else {
            $affected = mysqli_affected_rows($database->connection);
            $totalDeleted += $affected;
            echo '  -> borradas '.$affected;
        }
    }
    echo PHP_EOL;
}

echo PHP_EOL.'Total huérfanas: '.$totalFound;
if($apply) {
    echo ' · borradas: '.$totalDeleted;
} else if($totalFound > 0) {
    echo PHP_EOL.'Volvé a correrlo con --aplicar para borrarlas.';
}
echo PHP_EOL;
