<?php
/**
 * Recalcula los puntos de cultura por día de cada aldea a partir de sus campos y
 * edificios.
 *
 * Ejecutar:  docker compose exec -T web php tools/fix_village_cp.php
 *            docker compose exec -T web php tools/fix_village_cp.php --aplicar
 *
 * Sin --aplicar sólo informa. Con --aplicar escribe vdata.cp.
 *
 * Por qué hace falta: `vdata.cp` es un contador incremental igual que `vdata.pop`
 * (mysqli_DB::addCP suma al terminar cada nivel), y durante toda la vida de este
 * mundo se le sumó el **total** de PC/día del edificio al nivel nuevo en vez del
 * incremento contra el nivel anterior. `Automation::recountCP()`, que es lo único
 * que reescribe la columna entera, hacía la misma cuenta acumulada, así que los dos
 * caminos coincidían y ningún recuento delataba el error.
 *
 * En Data/buidata.php el campo `pop` es el incremento de habitantes de cada nivel,
 * pero el campo `cp` es el total por día del edificio a ese nivel: embajada 20 = 153,
 * academia 20 = 153, residencia 20 = 77, campo de recursos 10 = 6, los mismos números
 * que publica el T4 oficial. Sumar 0..N inflaba la producción entre 1,5x con
 * edificios de nivel 3 y 4,4x con todo a nivel 20 — y como el factor crece con el
 * nivel, el mundo arrancaba equilibrado y se descontrolaba solo.
 *
 * La Maravilla vive en `fdata.f99`, fuera del rango 1..40, así que el recuento usa
 * villagePopulationSlots() y no un rango a mano: es la misma lista que usa
 * Automation::recountCP().
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$apply = in_array('--aplicar', $argv, true);

require dirname(__DIR__).'/config/connection.php';
require dirname(__DIR__).'/GameEngine/Data/buidata.php';
require dirname(__DIR__).'/GameEngine/Accounts.php';
require dirname(__DIR__).'/GameEngine/Production.php';

$mysqli = @new mysqli(SQL_SERVER, SQL_USER, SQL_PASS, SQL_DB);
if($mysqli->connect_errno) {
    fwrite(STDERR, "No se pudo conectar a la base: ".$mysqli->connect_error."\n");
    exit(2);
}
$mysqli->set_charset('utf8mb4');

/** PC/día de un edificio al nivel dado. Igual que Automation::buildingCP. */
function culturePointsForBuilding($type, $level) {
    $dataarray = isset($GLOBALS['bid'.(int)$type]) ? $GLOBALS['bid'.(int)$type] : null;
    if(!is_array($dataarray)) {
        return 0;
    }
    $level = (int)$level;

    return isset($dataarray[$level]['cp']) ? (int)$dataarray[$level]['cp'] : 0;
}

$villageTable = TB_PREFIX.'vdata';
$fieldTable = TB_PREFIX.'fdata';
$userTable = TB_PREFIX.'users';

$sql = "SELECT v.wref, v.name, v.owner, v.cp, u.username, f.* "
    ."FROM $villageTable v "
    ."INNER JOIN $fieldTable f ON f.vref = v.wref "
    ."LEFT JOIN $userTable u ON u.id = v.owner "
    ."ORDER BY v.owner, v.wref";
$result = $mysqli->query($sql);
if(!$result) {
    fwrite(STDERR, "No se pudieron leer las aldeas: ".$mysqli->error."\n");
    exit(2);
}

$slots = villagePopulationSlots();
$reviewed = 0;
$changed = 0;
$systemChanged = 0;
$before = 0;
$after = 0;
$perOwner = array();

while($row = $result->fetch_assoc()) {
    $reviewed++;
    $current = (int)$row['cp'];
    $correct = 0;
    foreach($slots as $i) {
        if(!isset($row['f'.$i])) {
            continue;
        }
        $type = (int)$row['f'.$i.'t'];
        if($type) {
            $correct += culturePointsForBuilding($type, (int)$row['f'.$i]);
        }
    }

    $owner = (int)$row['owner'];
    $isSystem = isSystemAccount($owner);
    if(!$isSystem) {
        $before += $current;
        $after += $correct;
        if(!isset($perOwner[$owner])) {
            $perOwner[$owner] = array('name' => (string)$row['username'], 'before' => 0, 'after' => 0);
        }
        $perOwner[$owner]['before'] += $current;
        $perOwner[$owner]['after'] += $correct;
    }

    if($current === $correct) {
        continue;
    }
    $changed++;
    if($isSystem) {
        $systemChanged++;
    }
    printf(
        "%-7d %-22s %-16s %8d -> %8d  (%+d)\n",
        (int)$row['wref'],
        substr((string)$row['name'], 0, 22),
        $isSystem ? '[sistema]' : substr((string)$row['username'], 0, 16),
        $current,
        $correct,
        $correct - $current
    );

    if($apply) {
        $update = $mysqli->prepare("UPDATE $villageTable SET cp = ? WHERE wref = ?");
        $update->bind_param('ii', $correct, $row['wref']);
        if(!$update->execute()) {
            fwrite(STDERR, "Falló la escritura de la aldea ".(int)$row['wref'].": ".$mysqli->error."\n");
            exit(2);
        }
        $update->close();
    }
}

echo "\nProducción diaria por jugador (sólo cuentas de jugador):\n";
foreach($perOwner as $uid => $data) {
    printf(
        "  uid %-6d %-20s %8d -> %8d PC/día  (x%.2f)\n",
        $uid,
        substr($data['name'], 0, 20),
        $data['before'],
        $data['after'],
        $data['after'] > 0 ? $data['before'] / $data['after'] : 0
    );
}

printf(
    "\n%d aldeas revisadas, %d con la producción inflada (%d de cuentas del sistema).\n",
    $reviewed,
    $changed,
    $systemChanged
);
printf("Total de jugadores: %d -> %d PC/día.\n", $before, $after);
echo $apply ? "Cambios aplicados.\n" : "Simulación: volvé a correrlo con --aplicar para escribir.\n";
