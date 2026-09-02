<?php
/**
 * Lo que un jugador puede hacerle a una aldea natar viva: saquearla, arrasarla, y no
 * reforzarla.
 *
 * Va aparte de check_natar_settlements.php porque eso prueba el modelo (edad, crecimiento,
 * entrenamiento) y esto prueba los caminos del motor que la tocan desde afuera.
 *
 * Cubre:
 *   A. Una aldea viva acumula por encima del escondite, así que un saqueo trae botín.
 *   B. El camino que arrasa una aldea con catapultas funciona con una aldea NPC.
 *   C. La capital natar sobrevive a ese mismo camino, porque una capital no se arrasa.
 *   D. No se pueden mandar refuerzos a una aldea natar.
 *   E. Ni las aldeas vivas ni las estáticas ensucian las clasificaciones.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_natar_settlement_combat.php
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
include "Data/buidata.php";
include "Data/cp.php";
include "Data/cel.php";
include "Data/resdata.php";
include "Data/unitdata.php";
include "Data/hero_full.php";
include "Battle.php";
include "GeneratorX.php";
include "Multisort.php";
include "Lang/".LANG.".php";
include "Technology.php";
if(!defined('INCLUDE_ADMIN')) {
    define('INCLUDE_ADMIN', false);
}
include "Ranking.php";
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
include "Automation.php";
// destroyCatapultedVillage() escribe en el catálogo de aldeas arrasadas: sin $logging el
// camino se corta justo al final, que es lo que pasa en producción si alguien lo llama
// desde un proceso sin Session.php.
include "Logging.php";

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}

// --- El banco de pruebas -------------------------------------------------------------
//
// TABLAS TEMPORALES copiadas del esquema real, como check_village_conquest.php. Antes
// esto creaba sus aldeas en el mundo de verdad y las borraba al salir, y dejó de correr
// el día que todas las aldeas de jugador llegaron al cupo de aldeas natar cercanas:
// `natarSettlementPickAnchor()` devolvía null y no había dónde poner la de prueba. Con el
// mundo propio, el cupo lo fija la prueba.
//
// Hacen falta TRES aldeas de jugador porque el cupo es NATAR_SETTLEMENT_PER_CLUSTER (2)
// por aldea de jugador, y este archivo necesita colocar tres asentamientos.
scratchWorld(array(array(0, 0), array(-20, -20), array(20, 20)));

function scratchWorld($players) {
    global $database;
    $P = TB_PREFIX;
    $tables = array();
    $result = mysqli_query($database->connection, "SHOW TABLES LIKE '".$P."%'");
    while($line = mysqli_fetch_row($result)) {
        $name = substr($line[0], strlen($P));
        if($name !== 'config') {
            $tables[] = $name;
        }
    }
    foreach($tables as $table) {
        $create = mysqli_fetch_assoc(mysqli_query($database->connection, "SHOW CREATE TABLE {$P}{$table}"));
        mysqli_query($database->connection,
            preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create['Create Table']));
    }
    foreach($tables as $table) {
        mysqli_query($database->connection, "DELETE FROM {$P}{$table}");
    }

    $users = array("(".UID_NATARS.",'Natars',5,0,2,0)");
    foreach($players as $index => $ignored) {
        $users[] = "(".(9500 + $index).",'vecino".$index."',1,0,3,0)";
    }
    mysqli_query($database->connection,
        "INSERT INTO {$P}users (id,username,tribe,cp,access,alliance) VALUES ".implode(',', $users));

    $rows = array();
    $span = scratchSpan();
    for($x = -$span; $x <= $span; $x++) {
        for($y = -$span; $y <= $span; $y++) {
            $rows[] = "(".scratchTile($x, $y).",3,0,$x,$y,0,0)";
        }
    }
    foreach(array_chunk($rows, 500) as $chunk) {
        mysqli_query($database->connection,
            "INSERT INTO {$P}wdata (id,fieldtype,oasistype,x,y,occupied,image) VALUES ".implode(',', $chunk));
    }

    $now = time();
    foreach($players as $index => $coor) {
        $wref = scratchTile($coor[0], $coor[1]);
        mysqli_query($database->connection, "UPDATE {$P}wdata SET occupied = 1 WHERE id = $wref");
        mysqli_query($database->connection, "INSERT INTO {$P}vdata "
            ."(wref,owner,capital,pop,cp,loyalty,created,lastupdate,maxstore,maxcrop) VALUES "
            ."($wref,".(9500 + $index).",1,100,0,100,$now,$now,800,800)");
        mysqli_query($database->connection, "INSERT INTO {$P}fdata (vref) VALUES ($wref)");
        mysqli_query($database->connection, "INSERT INTO {$P}units (vref) VALUES ($wref)");
    }
}

/** El tablero llega un poco más lejos que la banda de saqueo, para que sobre sitio. */
function scratchSpan() {
    return (int)NATAR_SETTLEMENT_MAX_DISTANCE + 6;
}

/** El id de casilla que le toca a una coordenada del tablero de prueba. */
function scratchTile($x, $y) {
    $span = scratchSpan();
    return 500000 + ($x + $span) * ($span * 2 + 1) + ($y + $span);
}

$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$accrueMethod = $reflection->getMethod('accrueProductionBeforeChange');
$accrueMethod->setAccessible(true);
$accrue = function ($wref, $until) use ($accrueMethod, $automation) {
    return $accrueMethod->invoke($automation, $wref, $until);
};
$updateRes = $reflection->getMethod('updateRes');
$updateRes->setAccessible(true);
$destroy = $reflection->getMethod('destroyCatapultedVillage');
$destroy->setAccessible(true);

$now = time();
$wref = natarSettlementSpawn($now, true);
if($wref <= 0) {
    fwrite(STDERR, "No se pudo crear la aldea de prueba.\n");
    exit(1);
}

// Se la envejece para que tenga campos y almacén de verdad.
$mature = $now - (NATAR_SETTLEMENT_GROWTH_INTERVAL * NATAR_SETTLEMENT_MAX_FIELD_LEVEL);
$database->query("UPDATE ".TB_PREFIX."vdata SET created = ".(int)$mature.", npcupdate = ".(int)$mature." WHERE wref = $wref");
natarSettlementBringUpToDate($wref, $now, $accrue);

// --- A. Da botín ----------------------------------------------------------------------
$fields = $database->getResourceLevel($wref);
$cranny = $automation->calculateCrannyProtection($fields, 1, 5);
$database->query("UPDATE ".TB_PREFIX."vdata SET wood = 0, clay = 0, iron = 0, crop = 0, lastupdate = ".(int)($now - 86400)." WHERE wref = $wref");
$updateRes->invoke($automation, $wref, natarsAccountId());

$village = $database->getVillage($wref);
$lootable = array();
foreach(array('wood', 'clay', 'iron') as $resource) {
    $lootable[$resource] = floor((float)$village[$resource] - $cranny['protected']);
}
printf("     escondite %s por recurso · tras 24 h: madera %s, barro %s, hierro %s\n",
    number_format($cranny['protected']), number_format($village['wood']),
    number_format($village['clay']), number_format($village['iron']));
check(min($lootable) > 0,
    'una aldea viva madura junta por encima del escondite: hay botín (madera saqueable '
    .number_format($lootable['wood']).')');
check((float)$village['wood'] <= (float)$village['maxstore'],
    'y no se pasa de su almacén');

// --- B. Se puede arrasar ---------------------------------------------------------------
$razed = $destroy->invoke($automation, $wref, natarsAccountId(), 0);
$stillThere = $database->query_return("SELECT wref FROM ".TB_PREFIX."vdata WHERE wref = $wref");
$fieldFreed = $database->query_return("SELECT occupied FROM ".TB_PREFIX."wdata WHERE id = $wref");
check($razed === true && empty($stillThere),
    'las catapultas arrasan una aldea natar viva igual que la de un jugador');
check((int)$fieldFreed[0]['occupied'] === 0,
    'y la casilla vuelve a quedar libre');

// --- C. La capital natar no ------------------------------------------------------------
$capital = $database->query_return(
    "SELECT wref FROM ".TB_PREFIX."vdata WHERE owner = ".natarsAccountId()." AND capital = 1 LIMIT 1"
);
if(!is_array($capital) || !isset($capital[0]['wref'])) {
    // El mundo local no tiene capital natar: se arma una descartable para poder probar
    // que sobrevive. Es la comprobación que más importa de este archivo.
    $free = $database->query_return("SELECT id FROM ".TB_PREFIX."wdata WHERE occupied = 0 AND fieldtype = 3 LIMIT 1");
    if(is_array($free) && isset($free[0]['id'])) {
        $scratchCapital = (int)$free[0]['id'];
        $database->query("UPDATE ".TB_PREFIX."wdata SET occupied = 1 WHERE id = $scratchCapital");
        $database->addVillage($scratchCapital, natarsAccountId(), 'Natars', '1');
        $database->addResourceFields($scratchCapital, $database->getVillageType($scratchCapital));
        $database->addUnits($scratchCapital);
        $database->addTech($scratchCapital);
        $database->addABTech($scratchCapital);
        $database->query("UPDATE ".TB_PREFIX."vdata SET capital = 1 WHERE wref = $scratchCapital");
        $database->setVillageNpcKind($scratchCapital, NPC_KIND_STATIC);
        $capital = array(array('wref' => $scratchCapital));
    }
}
if(is_array($capital) && isset($capital[0]['wref'])) {
    $capitalWref = (int)$capital[0]['wref'];
    $survived = $destroy->invoke($automation, $capitalWref, natarsAccountId(), 1);
    $exists = $database->query_return("SELECT wref FROM ".TB_PREFIX."vdata WHERE wref = $capitalWref");
    check($survived === false && !empty($exists),
        'la capital natar sobrevive: una capital nunca se arrasa');
} else {
    echo "[--] este mundo no tiene capital natar: no se pudo comprobar".PHP_EOL;
}

// --- C bis. Las oleadas no engordan la capital -------------------------------------------
// startNatarAttack() inventa las tropas con addAttack() y nunca las descuenta de `units`,
// pero returnunitsComplete() se las sumaba al volver: la capital crecía con cada Maravilla
// que alguien construía. Son escenario, así que no vuelven.
if(isset($capitalWref)) {
    $startWave = $reflection->getMethod('startNatarAttack');
    $startWave->setAccessible(true);
    $resolveAttacks = $reflection->getMethod('sendunitsComplete');
    $resolveAttacks->setAccessible(true);
    $resolveReturns = $reflection->getMethod('returnunitsComplete');
    $resolveReturns->setAccessible(true);

    $victim = $database->query_return(
        "SELECT wref FROM ".TB_PREFIX."vdata WHERE ".playerAccountSql('owner')." LIMIT 1"
    );
    if(is_array($victim) && isset($victim[0]['wref'])) {
        function capitalGarrison($wref) {
            global $database;
            $units = $database->getUnit($wref);
            $total = 0;
            for($unit = 1; $unit <= 50; $unit++) {
                $total += is_array($units) && isset($units['u'.$unit]) ? (int)$units['u'.$unit] : 0;
            }
            return $total;
        }
        $garrisonBefore = capitalGarrison($capitalWref);
        $attackRowsBefore = (int)$database->query_return("SELECT COUNT(*) AS n FROM ".TB_PREFIX."attacks")[0]['n'];

        $startWave->invoke($automation, 5, (int)$victim[0]['wref']);
        $database->query("UPDATE ".TB_PREFIX."movement SET endtime = ".(time() - 10)
            ." WHERE `from` = $capitalWref AND proc = 0");
        @unlink('GameEngine/Prevention/sendunits.txt');
        $resolveAttacks->invoke($automation);
        @unlink('GameEngine/Prevention/returnunits.txt');
        $resolveReturns->invoke($automation);
        $database->query("UPDATE ".TB_PREFIX."movement SET endtime = ".(time() - 10)
            ." WHERE `to` = $capitalWref AND proc = 0");
        $resolveReturns->invoke($automation);

        check(capitalGarrison($capitalWref) === $garrisonBefore,
            'una oleada contra la Maravilla no le suma tropas a la capital ('
            .number_format($garrisonBefore).' antes y después)');
        check((int)$database->query_return("SELECT COUNT(*) AS n FROM ".TB_PREFIX."attacks")[0]['n'] === $attackRowsBefore,
            'y no deja filas de `attacks` huérfanas al no crear el movimiento de regreso');

        $database->query("DELETE FROM ".TB_PREFIX."movement WHERE `from` = $capitalWref OR `to` = $capitalWref");
        $database->query("DELETE FROM ".TB_PREFIX."ww_attacks");
    }
}

// --- D. No acepta refuerzos ------------------------------------------------------------
$unitsSource = file_get_contents($root.'/GameEngine/Units.php');
check(strpos($unitsSource, 'No puedes enviar refuerzos a una aldea natar.') !== false
    && strpos($unitsSource, "isSystemAccount(\$database->getVillageField(\$data['to_vid'],'owner'))") !== false,
    'el envío revalida que el destino no sea una aldea natar antes de aceptar un refuerzo');

// --- E. Fuera de las clasificaciones ----------------------------------------------------
$second = natarSettlementSpawn($now + NATAR_SETTLEMENT_SPAWN_INTERVAL + 1, true);
if($second > 0) {
    $database->query("UPDATE ".TB_PREFIX."vdata SET pop = 9999 WHERE wref = $second");
}
$ranking = new Ranking();
$result = $ranking->procVillagesRanking("LIMIT 20");
$rankedNatar = false;
while($row = mysql_fetch_assoc($result)) {
    if(isSystemAccount($row['owner'])) {
        $rankedNatar = true;
    }
}
check(!$rankedNatar,
    'una aldea natar con 9.999 de población no aparece en la clasificación de aldeas');

$result = $ranking->procUsersRanking("LIMIT 20");
$rankedAccount = false;
while($row = mysql_fetch_assoc($result)) {
    if(isSystemAccount($row['userid'])) {
        $rankedAccount = true;
    }
}
check(!$rankedAccount, 'ni la cuenta natar en la clasificación de jugadores');

// --- F. Conquistar una aldea natar: cambia de dueño y entrega vacía -----------------------
$conqTarget = natarSettlementSpawn($now, true);
if($conqTarget > 0) {
    $attacker = $database->query_return(
        "SELECT wref, owner FROM ".TB_PREFIX."vdata WHERE ".playerAccountSql('owner')." LIMIT 1"
    );
    if(is_array($attacker) && isset($attacker[0]['wref'])) {
        $attackerVillage = (int)$attacker[0]['wref'];
        $attackerOwner = (int)$attacker[0]['owner'];
        $savedFields = $database->getResourceLevel($attackerVillage);
        // Residencia nivel 10 para que tenga un cupo de expansión libre.
        $database->query("UPDATE ".TB_PREFIX."fdata SET f30t = 25, f30 = 10 WHERE vref = $attackerVillage");
        $database->query("UPDATE ".TB_PREFIX."vdata SET exp1 = 0, exp2 = 0, exp3 = 0 WHERE wref = $attackerVillage");
        $chiefAttack = $database->addAttack($attackerVillage, 0,0,0,0,0,0,0,0, 1, 0, 0, 3, 0, 0, 0, 0);

        $garrisonAtConquest = 0;
        $unitsBefore = $database->getUnit($conqTarget);
        for($unit = 1; $unit <= 50; $unit++) {
            $garrisonAtConquest += is_array($unitsBefore) ? (int)$unitsBefore['u'.$unit] : 0;
        }
        check($garrisonAtConquest > 0, 'la aldea a conquistar tiene guarnición ('.$garrisonAtConquest.' tropas)');

        $outcome = '';
        for($hit = 1; $hit <= 5 && (int)$database->getVillageField($conqTarget, 'owner') === natarsAccountId(); $hit++) {
            $outcome = $database->applyConquestLoyalty(
                $attackerVillage, $conqTarget, $attackerOwner, natarsAccountId(), $chiefAttack, 34
            )['status'];
        }
        check($outcome === 'conquered', 'una aldea natar viva se puede conquistar con jefes');

        $conquered = $database->getVillage($conqTarget);
        check((int)$conquered['owner'] === $attackerOwner, 'pasa a ser del jugador');
        check(!isLivingNpcVillage($conquered) && !isStaticNpcVillage($conquered),
            'y deja de ser una aldea NPC, así que paga manutención y puede pasar hambre');

        $unitsAfter = $database->getUnit($conqTarget);
        $inherited = 0;
        for($unit = 1; $unit <= 50; $unit++) {
            $inherited += is_array($unitsAfter) ? (int)$unitsAfter['u'.$unit] : 0;
        }
        check($inherited === 0,
            'la guarnición que sobrevivió se disuelve: el conquistador no hereda tropas natar');

        check(count(array_filter(natarSettlements(), function ($settlement) use ($conqTarget) {
            return (int)$settlement['wref'] === $conqTarget;
        })) === 0, 'y sale del padrón de aldeas vivas, así que deja de crecer');

        $database->query("UPDATE ".TB_PREFIX."fdata SET f30t = ".(int)$savedFields['f30t']
            .", f30 = ".(int)$savedFields['f30']." WHERE vref = $attackerVillage");
        $database->query("UPDATE ".TB_PREFIX."vdata SET exp1 = 0, exp2 = 0, exp3 = 0 WHERE wref = $attackerVillage");
        $database->removeAttack($chiefAttack);
    }
}


echo PHP_EOL.(count($failures) ? count($failures)." FALLA(S)" : "todo en orden").PHP_EOL;
exit(count($failures) ? 1 : 0);
