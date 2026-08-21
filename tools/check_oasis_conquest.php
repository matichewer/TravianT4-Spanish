<?php
error_reporting(E_ALL);

function oasisAssert($condition, $message) {
    if(!$condition) {
        echo "FAIL: ".$message."\n";
        exit(1);
    }
    echo "OK: ".$message."\n";
}

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');
define('WORLD_MAX', 200);
require dirname(__DIR__).'/GameEngine/Automation.php';

$automationClass = new ReflectionClass('Automation');
$automation = $automationClass->newInstanceWithoutConstructor();

function oasisVillage($overrides = array()) {
    return array_merge(
        array('wref' => 1000, 'x' => 0, 'y' => 0, 'mansion' => 10, 'oases' => 0),
        $overrides
    );
}

function oasisTarget($overrides = array()) {
    return array_merge(
        array('x' => 1, 'y' => 1, 'conqured' => 0, 'loyalty' => 100, 'holder_oases' => 0),
        $overrides
    );
}

// Distance: only oases inside the 3 field radius of the village can be annexed.
$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 3, 'y' => -3)));
oasisAssert($outcome['status'] === 'conquered', 'anexa un oasis libre en el límite de 3 casillas');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 4, 'y' => 0)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis a 4 casillas en X');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 0, 'y' => -4)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis a 4 casillas en Y');

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(), oasisTarget(array('x' => 120, 'y' => 87)));
oasisAssert($outcome['status'] === 'out_of_range', 'rechaza un oasis del otro lado del mapa');

// The map wraps around, so the borders are neighbours.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('x' => WORLD_MAX, 'y' => 0)),
    oasisTarget(array('x' => -WORLD_MAX + 1, 'y' => 0))
);
oasisAssert($outcome['status'] === 'conquered', 'cruza el borde del mapa: ambos extremos son vecinos');

// Hero's mansion: level 10, 15 and 20 for the first, second and third oasis.
foreach(array(0 => 10, 1 => 15, 2 => 20) as $held => $needed) {
    $outcome = $automation->oasisAnnexationOutcome(
        oasisVillage(array('mansion' => $needed - 1, 'oases' => $held)),
        oasisTarget()
    );
    oasisAssert(
        $outcome['status'] === 'mansion_too_low' && $outcome['needed_mansion'] === $needed,
        'exige mansión del héroe nivel '.$needed.' para el oasis número '.($held + 1)
    );

    $outcome = $automation->oasisAnnexationOutcome(
        oasisVillage(array('mansion' => $needed, 'oases' => $held)),
        oasisTarget()
    );
    oasisAssert($outcome['status'] === 'conquered', 'con mansión nivel '.$needed.' anexa el oasis número '.($held + 1));
}

$outcome = $automation->oasisAnnexationOutcome(oasisVillage(array('mansion' => 20, 'oases' => 3)), oasisTarget());
oasisAssert($outcome['status'] === 'oasis_limit', 'no permite un cuarto oasis');

// Attacking an oasis the village already holds changes nothing.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('oases' => 3)),
    oasisTarget(array('conqured' => 1000, 'holder_oases' => 3))
);
oasisAssert($outcome['status'] === 'already_owned', 'atacar un oasis propio no lo vuelve a conquistar');

// Oasis held by another player: 1, 2 or 3 raids depending on how many the holder has.
$attacker = oasisVillage(array('mansion' => 10, 'oases' => 0));

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 3))
);
oasisAssert($outcome['status'] === 'conquered', 'un solo ataque toma el oasis de quien tiene 3');

$loyalty = 100;
$raids = 0;
$status = '';
do {
    $outcome = $automation->oasisAnnexationOutcome(
        $attacker,
        oasisTarget(array('conqured' => 2000, 'loyalty' => $loyalty, 'holder_oases' => 2))
    );
    $loyalty = $outcome['loyalty'];
    $status = $outcome['status'];
    $raids++;
} while($status === 'loyalty_reduced' && $raids < 10);
oasisAssert($status === 'conquered' && $raids === 2, 'hacen falta 2 ataques contra quien tiene 2 oasis');

$loyalty = 100;
$raids = 0;
$status = '';
do {
    $outcome = $automation->oasisAnnexationOutcome(
        $attacker,
        oasisTarget(array('conqured' => 2000, 'loyalty' => $loyalty, 'holder_oases' => 1))
    );
    $loyalty = $outcome['loyalty'];
    $status = $outcome['status'];
    $raids++;
} while($status === 'loyalty_reduced' && $raids < 10);
oasisAssert($status === 'conquered' && $raids === 3, 'hacen falta 3 ataques contra quien tiene su último oasis');

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 1))
);
oasisAssert(
    $outcome['status'] === 'loyalty_reduced' && $outcome['loyalty'] === 66,
    'el primer ataque deja la lealtad en 66%'
);

$outcome = $automation->oasisAnnexationOutcome(
    $attacker,
    oasisTarget(array('conqured' => 2000, 'loyalty' => 100, 'holder_oases' => 0))
);
oasisAssert($outcome['status'] === 'loyalty_reduced', 'un dato inconsistente de oasis del dueño no rompe el cálculo');

// Every check runs before the loyalty damage: a far away oasis is never touched.
$outcome = $automation->oasisAnnexationOutcome(
    oasisVillage(array('mansion' => 20)),
    oasisTarget(array('x' => 50, 'y' => 50, 'conqured' => 2000, 'loyalty' => 50, 'holder_oases' => 3))
);
oasisAssert(
    $outcome['status'] === 'out_of_range' && $outcome['loyalty'] === 50,
    'un oasis fuera de alcance conserva su lealtad'
);

// Loyalty regeneration must not depend on how frequently automation runs.
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 10, 1100, 1);
oasisAssert($regen['loyalty'] === 50 && $regen['clock'] === 1000,
    'conserva el reloj mientras todavía no completa un punto de lealtad');
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, $regen['clock'], 10, 1360, 1);
oasisAssert($regen['loyalty'] === 51 && $regen['clock'] === 1360,
    'acredita el punto exacto al acumular el tiempo suficiente');
$regen = Automation::oasisLoyaltyRegenerationOutcome(99, 1000, 20, 2000, 5);
oasisAssert($regen['loyalty'] === 100 && $regen['clock'] === 2000,
    'al llegar a 100 cierra el reloj sin dejar tiempo retroactivo');
$regen = Automation::oasisLoyaltyRegenerationOutcome(50, 1000, 0, 2000, 1);
oasisAssert($regen['loyalty'] === 50 && $regen['clock'] === 2000,
    'sin residencia ni palacio no acumula regeneración pendiente');

$automationSource = file_get_contents(dirname(__DIR__).'/GameEngine/Automation.php');
$mansionSource = file_get_contents(dirname(__DIR__).'/Templates/Build/37_heromansion.tpl');
$profileSource = file_get_contents(dirname(__DIR__).'/Templates/Profile/profile.tpl');
$overviewSource = file_get_contents(dirname(__DIR__).'/Templates/Profile/overview.tpl');
oasisAssert(strpos($automationSource, 'releaseOasisSafely($oasisToRelease') === false,
    'la automatización no queda acoplada a variables de plantilla');
oasisAssert(strpos($mansionSource, 'releaseOasisSafely($oasisToRelease, $village->wid)') !== false,
    'la Mansión libera un solo oasis mediante el flujo seguro');
$releaseStart = strpos($automationSource, 'public function releaseOasisSafely(');
$releaseEnd = strpos($automationSource, 'public function releaseVillageOasesSafely(', $releaseStart);
$releaseSource = substr($automationSource, $releaseStart, $releaseEnd - $releaseStart);
$accruePos = strpos($releaseSource, 'accrueProductionBeforeChange(');
$returnPos = strpos($releaseSource, 'returnOasisReinforcements(');
$removePos = strpos($releaseSource, 'removeOases(');
oasisAssert($accruePos !== false && $returnPos > $accruePos && $removePos > $returnPos,
    'la liberación acredita producción, devuelve tropas y recién después libera');
oasisAssert(substr_count($automationSource, 'releaseVillageOasesSafely($village') >= 2,
    'destrucción y borrado de cuenta usan la liberación segura');
oasisAssert(strpos($mansionSource, "karte.php?x=<?php echo \$coor['x']; ?>&amp;y=<?php echo \$coor['y']; ?>") !== false,
    'el enlace del oasis conserva el orden X/Y');
oasisAssert(strpos($mansionSource, '$row["conquered_at"]') !== false,
    'la fecha visible usa el historial de conquista');
// El tipo de oasis se describe en un solo lugar, oasisTypeBonuses(), derivado del mismo
// reparto que cobra la producción: el mapa, la Mansión y el perfil tenían cada uno su
// copia del switch de los 12 tipos y nada las obligaba a coincidir con el bono real.
foreach(range(1, 12) as $oasisType) {
    list($wood, $clay, $iron, $crop) = villageOasisCounter(array(array('type' => $oasisType)));
    $shown = array(1 => 0, 2 => 0, 3 => 0, 4 => 0);
    foreach(oasisTypeBonuses($oasisType) as $bonus) {
        $shown[$bonus['res']] = $bonus['percent'];
    }
    $expected = array(1 => $wood * 25, 2 => $clay * 25, 3 => $iron * 25, 4 => $crop * 25);
    oasisAssert($shown === $expected,
        'el bono que se muestra del oasis tipo '.$oasisType.' es el que cobra la producción');
}
oasisAssert(strpos(oasisBonusIcons(5), "title='Barro +50%'") !== false,
    'el tooltip distingue el oasis del 50% del que da 25%');
oasisAssert(strpos(oasisBonusIcons(6), "title='Barro +25%'") !== false
    && strpos(oasisBonusIcons(6), "title='Cereal +25%'") !== false,
    'el oasis mixto muestra sus dos recursos con el porcentaje de cada uno');
foreach(array('profile.tpl' => $profileSource, 'overview.tpl' => $overviewSource) as $name => $source) {
    oasisAssert(strpos($source, 'echo oasisBonusIcons(') !== false,
        $name.' describe el oasis con la definición única');
    oasisAssert(strpos($source, "title='Barro'") === false,
        $name.' ya no conserva su copia del switch de tipos');
}
oasisAssert(strpos($mansionSource, 'function oasisResourceBonus') === false,
    'la Mansión del Héroe tampoco redefine la descripción del oasis');
foreach(range(1, 12) as $oasisType) {
    $tooltip = oasisBonusTooltip($oasisType);
    $rows = oasisBonusDistributionRows($oasisType);
    $expected = oasisTypeBonuses($oasisType);
    $ok = true;
    foreach($expected as $bonus) {
        if(strpos($tooltip, $bonus['percent'].'%') === false || strpos($rows, $bonus['percent'].'%') === false) {
            $ok = false;
        }
    }
    oasisAssert($ok && substr_count($tooltip, '<img') === count($expected),
        'el tooltip del mapa del oasis tipo '.$oasisType.' muestra un bono por recurso, con su porcentaje');
}


// Un write que falla no puede anunciarse como conquista. La columna `conquered_at` faltaba
// en la base de produccion, el UPDATE devolvia false —mysql_query() no chequea nada— y el
// informe igual decia "tu heroe conquisto este oasis" mientras el oasis seguia libre.
$conquestStart = strpos($automationSource, "case 'conquered':");
$conquestEnd = strpos($automationSource, "case 'already_owned':", $conquestStart);
$conquestSource = substr($automationSource, $conquestStart, $conquestEnd - $conquestStart);
oasisAssert(strpos($conquestSource, '$conquestWritten = mysql_query("UPDATE ".TB_PREFIX."odata') !== false,
    'la conquista guarda el resultado del UPDATE de odata');
$writtenPos = strpos($conquestSource, 'if($conquestWritten)');
$occupiedPos = strpos($conquestSource, 'wdata SET `occupied`');
$announcePos = strpos($conquestSource, 'tu héroe conquistó este oasis');
oasisAssert($writtenPos !== false && $occupiedPos > $writtenPos && $announcePos > $writtenPos,
    'marcar la casilla ocupada y anunciar la conquista dependen de que el UPDATE haya andado');
oasisAssert(strpos($conquestSource, 'if(mysql_query("UPDATE ".TB_PREFIX."odata SET `loyalty`') !== false,
    'la bajada de lealtad tampoco se anuncia sin confirmar el UPDATE');

// La capa de base deja rastro de cualquier write fallido: sin esto el fallo es invisible.
$dbSource = file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
oasisAssert(strpos($dbSource, 'function travian_log_failed_query(') !== false,
    'la capa de base tiene un registro de queries fallidas');
oasisAssert(preg_match('/function mysql_query\(\$sql\) \{.*?travian_log_failed_query/s', $dbSource) === 1,
    'el shim mysql_query() loguea los writes que fallan');
oasisAssert(preg_match('/function removeOases\(.*?travian_log_failed_query.*?return false;.*?wdata SET occupied = 0/s', $dbSource) === 1,
    'soltar un oasis no limpia la casilla si el UPDATE de odata fallo');

echo "Todas las comprobaciones de conquista de oasis pasaron.\n";
