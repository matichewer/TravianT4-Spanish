<?php
/**
 * Recalcula los habitantes de cada aldea a partir de sus campos y edificios.
 *
 * Ejecutar:  docker compose exec -T web php tools/fix_village_pop.php
 *            docker compose exec -T web php tools/fix_village_pop.php --aplicar
 *
 * Sin --aplicar sólo informa. Con --aplicar escribe vdata.pop.
 *
 * Por qué hace falta: `vdata.pop` es un contador incremental (mysqli_DB::modifyPop
 * suma y resta), no un número derivado. Cualquier camino que toque fdata sin pasar
 * por él —una demolición vieja, un mundo a medio migrar, una aldea armada a mano
 * desde el panel— deja la población más alta de lo que corresponde, y a partir de
 * ahí la aldea consume cereal por edificios que no existen.
 *
 * En este mundo eso dejó cinco aldeas produciendo entre -150 y -570 de cereal por
 * hora sin una sola tropa, con el granero en números como -47.000. El jugador no lo
 * veía —la barra de recursos muestra max(0, cereal)— pero no podía entrenar, ni
 * construir, ni mandar cereal al mercado, porque toda esa deuda hay que pagarla
 * antes de que el granero vuelva a ser positivo. Automation::starvation ya no deja
 * que la deuda se acumule; esto arregla la causa.
 *
 * La población también decide el cereal libre, así que una aldea con este problema
 * queda además bloqueada para construir.
 *
 * Las aldeas de las cuentas del sistema quedan afuera: su población la escribe
 * natarProvisionVillage() desde su propio plan, así que corregirlas acá sólo daría
 * lugar a que la próxima provisión las volviera a mover. Se informan aparte. En este
 * mundo las diez Aldeas de la Maravilla están 10 habitantes por encima, que es
 * exactamente lo que suma un Palacio de la Maravilla de nivel 10: `vdata.pop` lo
 * cuenta y `fdata.f40` está vacío. Es un desfase propio de esas aldeas y se arregla
 * en NatarVillage.php, no acá.
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$apply = in_array('--aplicar', $argv, true);

require dirname(__DIR__).'/config/connection.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Accounts.php';

$mysqli = @new mysqli(SQL_SERVER, SQL_USER, SQL_PASS, SQL_DB);
if($mysqli->connect_errno) {
    fwrite(STDERR, "No se pudo conectar a la base: ".$mysqli->connect_error."\n");
    exit(2);
}

/** Habitantes acumulados de un edificio hasta cierto nivel, igual que Automation::buildingPOP. */
function popForBuilding($type, $level) {
    $dataarray = isset($GLOBALS['bid'.(int)$type]) ? $GLOBALS['bid'.(int)$type] : null;
    if(!is_array($dataarray)) {
        return 0;
    }
    $total = 0;
    for($step = 0; $step <= (int)$level; $step++) {
        if(isset($dataarray[$step]['pop'])) {
            $total += (int)$dataarray[$step]['pop'];
        }
    }
    return $total;
}

$rows = $mysqli->query(
    "SELECT v.wref, v.name, v.owner, v.pop, u.username, f.* "
    ."FROM ".TB_PREFIX."vdata v "
    ."JOIN ".TB_PREFIX."fdata f ON f.vref = v.wref "
    ."LEFT JOIN ".TB_PREFIX."users u ON u.id = v.owner "
    ."ORDER BY v.wref"
);
if(!$rows) {
    fwrite(STDERR, "No se pudo leer vdata/fdata: ".$mysqli->error."\n");
    exit(2);
}

$checked = 0;
$wrong = array();
$system = array();
while($row = $rows->fetch_assoc()) {
    $checked++;
    $expected = 0;
    for($field = 1; $field <= 40; $field++) {
        $type = isset($row['f'.$field.'t']) ? (int)$row['f'.$field.'t'] : 0;
        $level = isset($row['f'.$field]) ? (int)$row['f'.$field] : 0;
        if($type > 0 && $level > 0) {
            $expected += popForBuilding($type, $level);
        }
    }
    $stored = (int)$row['pop'];
    if($stored === $expected) {
        continue;
    }
    $village = array(
        'wref' => (int)$row['wref'],
        'name' => $row['name'],
        'owner' => $row['username'] !== null ? $row['username'] : ('uid '.$row['owner']),
        'stored' => $stored,
        'expected' => $expected
    );
    if(isSystemAccount($row['owner'])) {
        $system[] = $village;
    }
    else {
        $wrong[] = $village;
    }
}

printf("%d aldeas revisadas, %d de jugador con la poblacion desincronizada.\n", $checked, count($wrong));
if($system) {
    printf("(%d aldeas de cuentas del sistema tambien difieren; las maneja natarProvisionVillage y no se tocan)\n", count($system));
}
echo "\n";
if(empty($wrong)) {
    exit(0);
}

printf("%-8s %-26s %-14s %10s %10s %9s\n", 'wref', 'aldea', 'dueno', 'guardada', 'real', 'delta');
echo str_repeat('-', 82)."\n";
foreach($wrong as $village) {
    printf("%-8d %-26s %-14s %10d %10d %+9d\n",
        $village['wref'],
        mb_substr($village['name'], 0, 26),
        mb_substr($village['owner'], 0, 14),
        $village['stored'],
        $village['expected'],
        $village['expected'] - $village['stored']
    );
}
echo "\n";

if(!$apply) {
    echo "Nada escrito. Volve a correrlo con --aplicar para corregirlas.\n";
    exit(1);
}

$updated = 0;
foreach($wrong as $village) {
    $q = "UPDATE ".TB_PREFIX."vdata SET pop = ".(int)$village['expected']." WHERE wref = ".(int)$village['wref'];
    if($mysqli->query($q)) {
        $updated++;
    }
    else {
        fwrite(STDERR, "Fallo al escribir la aldea ".$village['wref'].": ".$mysqli->error."\n");
    }
}
// Una deuda de cereal que venia de la poblacion inflada ya no corresponde: el granero
// del oficial no baja de cero y starvation() la habria cortado igual en la pasada
// siguiente, pero asi la aldea queda usable de entrada.
$mysqli->query("UPDATE ".TB_PREFIX."vdata SET crop = 0, starv = 0, starvupdate = 0 WHERE crop < 0");

printf("%d aldeas corregidas.\n", $updated);
exit($updated === count($wrong) ? 0 : 2);
