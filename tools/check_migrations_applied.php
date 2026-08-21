<?php
/**
 * Verifica que la base viva tenga todo lo que `tools/migrations.sql` agrega.
 *
 * El esquema no se versiona con el codigo: el instalador lo genera una sola vez y cada
 * cambio posterior vive en `tools/migrations.sql`, que hay que correr a mano contra la
 * base de produccion. Cuando ese paso se olvida, el codigo nuevo escribe una columna
 * que no existe: `mysql_query()` devuelve false, nadie mira el resultado y el juego se
 * comporta como si el write hubiera funcionado. Paso con `odata.conquered_at` (commit
 * "Fix oasis full"): la conquista de oasis fallaba y el informe igual anunciaba que el
 * heroe habia conquistado el oasis.
 *
 * Este checker lee las migraciones idempotentes (`ADD COLUMN IF NOT EXISTS`,
 * `ADD INDEX IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`) y comprueba una por una
 * contra el esquema real. Correrlo despues de cada deploy convierte "falta una
 * migracion" en un error visible en vez de un bug de juego.
 *
 * Ejecutar:  docker compose exec -T web php /var/www/html/tools/check_migrations_applied.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
error_reporting(E_ALL);
ini_set('display_errors', '1');

include "config/connection.php";

$connection = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASS);
if(!$connection) {
    echo "FAIL: no se pudo conectar a la base: ".mysqli_connect_error()."\n";
    exit(1);
}
mysqli_select_db($connection, SQL_DB);

// Acepta otro archivo por argumento para poder probar el propio checker con una
// migracion inventada; sin argumento mira las migraciones reales del repo.
$sqlFile = isset($argv[1]) ? $argv[1] : $root.'/tools/migrations.sql';
$sql = file_get_contents($sqlFile);
if($sql === false) {
    echo "FAIL: no se pudo leer ".$sqlFile."\n";
    exit(1);
}

// Las migraciones estan escritas con el prefijo literal `s1_`; la base puede usar otro.
function migrationTable($name) {
    $name = trim($name, "`\r\n\t ");
    if(strpos($name, 's1_') === 0) {
        return TB_PREFIX.substr($name, 3);
    }
    return $name;
}

// Fuera comentarios de linea, que llevan ejemplos de SQL adentro.
$statements = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = explode(';', $statements);

$expectedTables = array();
$expectedColumns = array();   // tabla => columnas
$expectedIndexes = array();   // tabla => indices

foreach($statements as $statement) {
    $statement = trim($statement);
    if($statement === '') {
        continue;
    }

    if(preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(`?[A-Za-z0-9_]+`?)/i', $statement, $m)) {
        $expectedTables[] = migrationTable($m[1]);
        continue;
    }

    if(!preg_match('/^ALTER\s+TABLE\s+(`?[A-Za-z0-9_]+`?)/i', $statement, $m)) {
        continue;
    }
    $table = migrationTable($m[1]);

    if(preg_match_all('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+(`?[A-Za-z0-9_]+`?)/i', $statement, $cols)) {
        foreach($cols[1] as $column) {
            $expectedColumns[$table][] = trim($column, '`');
        }
    }
    if(preg_match_all('/ADD\s+(?:INDEX|KEY)\s+IF\s+NOT\s+EXISTS\s+(`?[A-Za-z0-9_]+`?)/i', $statement, $idx)) {
        foreach($idx[1] as $index) {
            $expectedIndexes[$table][] = trim($index, '`');
        }
    }
}

$missing = array();
$checked = 0;

$tables = array();
$result = mysqli_query($connection, "SHOW TABLES");
while($result && $row = mysqli_fetch_array($result)) {
    $tables[strtolower($row[0])] = true;
}

foreach(array_unique($expectedTables) as $table) {
    $checked++;
    if(!isset($tables[strtolower($table)])) {
        $missing[] = "tabla ".$table;
    }
}

foreach($expectedColumns as $table => $columns) {
    if(!isset($tables[strtolower($table)])) {
        $checked += count(array_unique($columns));
        $missing[] = "tabla ".$table." (y sus ".count(array_unique($columns))." columnas)";
        continue;
    }
    $present = array();
    $result = mysqli_query($connection, "SHOW COLUMNS FROM `".$table."`");
    while($result && $row = mysqli_fetch_assoc($result)) {
        $present[strtolower($row['Field'])] = true;
    }
    foreach(array_unique($columns) as $column) {
        $checked++;
        if(!isset($present[strtolower($column)])) {
            $missing[] = $table.".".$column;
        }
    }
}

foreach($expectedIndexes as $table => $indexes) {
    if(!isset($tables[strtolower($table)])) {
        continue;
    }
    $present = array();
    $result = mysqli_query($connection, "SHOW INDEX FROM `".$table."`");
    while($result && $row = mysqli_fetch_assoc($result)) {
        $present[strtolower($row['Key_name'])] = true;
    }
    foreach(array_unique($indexes) as $index) {
        $checked++;
        if(!isset($present[strtolower($index)])) {
            $missing[] = "indice ".$table.".".$index;
        }
    }
}

if($checked === 0) {
    echo "FAIL: no se reconocio ninguna migracion en tools/migrations.sql\n";
    exit(1);
}

if(!empty($missing)) {
    echo "FAIL: la base no tiene ".count($missing)." de las ".$checked." migraciones de tools/migrations.sql:\n";
    foreach($missing as $item) {
        echo "  - ".$item."\n";
    }
    echo "Corregir aplicando tools/migrations.sql (es idempotente) contra la base del servidor.\n";
    exit(1);
}

echo "OK: las ".$checked." migraciones de tools/migrations.sql estan aplicadas.\n";
