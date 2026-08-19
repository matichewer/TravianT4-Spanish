<?php
/**
 * Auditoría de la curva de regeneración de animales de oasis
 * (mysqli_DB::oasisAnimalChain / oasisAnimalRegenEligibleColumns).
 *
 * Ejecutar:  docker compose exec -T web php tools/check_oasis_animal_regen.php
 *
 * Por qué existe. `regenerateOasisTroops()` recorre los oasis libres una vez por día
 * y llama a `populateOasisUnitsLow2()`, que solía sumar de un saque un lote al azar a
 * TODAS las especies del bioma, elefantes incluidos (con 10% de probabilidad de un lote
 * grande 0-31). Un oasis recién vaciado podía amanecer con una docena de elefantes al
 * día siguiente, sin que ninguna especie débil lo hubiera precedido.
 *
 * La cadena por bioma (`oasisAnimalChain`) ya lista cada especie de la más débil a la
 * más fuerte -los ids de unidad respetan ese orden: rata(31) araña(32) serpiente(33)
 * murciélago(34) jabalí(35) lobo(36) oso(37) cocodrilo(38) tigre(39) elefante(40)-, y
 * `oasisAnimalRegenEligibleColumns()` sólo destraba una especie cuando la anterior de
 * la cadena ya llegó a la mitad de su propio rango. Así la repoblación sube un escalón
 * por vez en vez de saltar de 0 a una decena de la especie más fuerte en un solo día.
 *
 * Cubre:
 *   A. Un oasis recién vaciado sólo destraba la especie más débil de su bioma.
 *   B. Cada especie exige que la anterior de la cadena esté a mitad de camino.
 *   C. La cadena nunca salta un eslabón, aunque uno más fuerte ya tenga tropas.
 *   D. Con toda la cadena poblada, se destraban todas las especies (elefante incluido).
 *   E. Un bioma sin fauna débil (sólo jabalí/lobo/oso) también respeta el orden.
 *   F. Un tipo de oasis desconocido no ofrece ninguna especie.
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$GLOBALS['checks'] = 0;
$GLOBALS['fails'] = array();

function check($condition, $message) {
    $GLOBALS['checks']++;
    if($condition) {
        return true;
    }
    $GLOBALS['fails'][] = $message;
    echo "  FAIL  ".$message."\n";
    return false;
}

function section($title) {
    echo "\n== ".$title." ==\n";
}

define('TB_PREFIX', 's1_');
// db_MYSQLi.php instancia un `$database = new mysqli_DB;` global al cargarse, así que
// hacen falta estas cuatro constantes aunque el checker nunca use una conexión real: sólo
// se ejercitan oasisAnimalChain()/oasisAnimalRegenEligibleColumns(), que no tocan
// $this->connection. Se silencia el intento de conexión fallido para no ensuciar la salida.
define('SQL_SERVER', '');
define('SQL_USER', '');
define('SQL_PASS', '');
define('SQL_DB', '');
$previousReporting = error_reporting(0);
require dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php';
error_reporting($previousReporting);

$reflection = new ReflectionClass('mysqli_DB');
$db = $reflection->newInstanceWithoutConstructor();

/** Fila de `units` con todas las especies de oasis en 0, salvo las indicadas. */
function oasisUnitsRow($overrides = array()) {
    $row = array();
    foreach (range(31, 40) as $unit) {
        $row['u'.$unit] = 0;
    }
    foreach ($overrides as $unit => $count) {
        $row['u'.$unit] = $count;
    }
    return $row;
}

// ---------------------------------------------------------------------------
section('A. Oasis recién vaciado: sólo la especie más débil');
// ---------------------------------------------------------------------------
// Tipo 6: rata(31) araña(32) jabalí(35) cocodrilo(38) elefante(40) -el bioma del reporte.
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow()) === array('u31'),
    'tipo 6 vacío sólo destraba la rata'
);

// ---------------------------------------------------------------------------
section('B. Cada especie exige que la anterior llegue a la mitad de su rango');
// ---------------------------------------------------------------------------
// Rango de u31 en el tipo 6 es (5,40): la mitad es 20.
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(31 => 19))) === array('u31'),
    'con la rata todavía por debajo de la mitad de su rango, la araña sigue trabada'
);
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(31 => 20))) === array('u31', 'u32'),
    'la rata a mitad de su rango destraba la araña'
);
// Rango de u32 es (5,30): la mitad es 15.
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(31 => 40, 32 => 14))) === array('u31', 'u32'),
    'con la araña por debajo de su mitad, el jabalí sigue trabado aunque la rata esté al tope'
);
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(31 => 40, 32 => 15))) === array('u31', 'u32', 'u35'),
    'la araña a mitad de su rango destraba el jabalí'
);

// ---------------------------------------------------------------------------
section('C. La cadena nunca salta un eslabón');
// ---------------------------------------------------------------------------
// El cocodrilo ya tiene tropas (quizás de antes de este cambio), pero el jabalí que lo
// precede en la cadena está vacío: el cocodrilo no debe seguir regenerando igual.
check(
    $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(31 => 40, 32 => 30, 38 => 25))) === array('u31', 'u32', 'u35'),
    'un eslabón fuerte con tropas previas no sigue creciendo si el que lo precede está vacío'
);

// ---------------------------------------------------------------------------
section('D. Cadena completa: todas las especies destrabadas');
// ---------------------------------------------------------------------------
$fullChain6 = $db->oasisAnimalRegenEligibleColumns(6, oasisUnitsRow(array(
    31 => 40, 32 => 30, 35 => 25, 38 => 15, 40 => 0,
)));
check(
    $fullChain6 === array('u31', 'u32', 'u35', 'u38', 'u40'),
    'con toda la cadena a mitad de camino, el elefante también se destraba'
);

// ---------------------------------------------------------------------------
section('E. Bioma sin fauna débil respeta el mismo orden');
// ---------------------------------------------------------------------------
// Tipo 1/2: jabalí(35) lobo(36) oso(37), sin rata/araña/serpiente en este bioma.
check(
    $db->oasisAnimalRegenEligibleColumns(1, oasisUnitsRow()) === array('u35'),
    'tipo 1 vacío sólo destraba el jabalí, el primero de su propia cadena'
);
check(
    $db->oasisAnimalRegenEligibleColumns(1, oasisUnitsRow(array(35 => 15))) === array('u35', 'u36'),
    'el jabalí a mitad de su rango destraba el lobo'
);
check(
    $db->oasisAnimalRegenEligibleColumns(1, oasisUnitsRow(array(35 => 15, 36 => 10))) === array('u35', 'u36'),
    'el lobo por debajo de su mitad mantiene el oso trabado'
);

// ---------------------------------------------------------------------------
section('F. Tipo de oasis desconocido');
// ---------------------------------------------------------------------------
check(
    $db->oasisAnimalRegenEligibleColumns(0, oasisUnitsRow()) === array(),
    'un tipo de oasis sin cadena configurada no destraba ninguna especie'
);

// ---------------------------------------------------------------------------
echo "\n";
if(empty($GLOBALS['fails'])) {
    echo "Oasis animal regen checks passed (".$GLOBALS['checks']." comprobaciones).\n";
    exit(0);
}
echo count($GLOBALS['fails'])." de ".$GLOBALS['checks']." comprobaciones fallaron.\n";
exit(1);
