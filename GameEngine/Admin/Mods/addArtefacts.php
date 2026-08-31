<?php
/**
 * Siembra los artefactos del mundo.
 *
 * En el T4 oficial los artefactos aparecen solos en una fecha programada; acá no hay
 * liberación automática, así que este es el único camino y lo dispara el administrador.
 *
 * Qué estaba roto y por qué importa:
 *
 *  - Creaba la aldea con `addVillage()` y SQL a mano, **sin marcarla como NPC**: nacía
 *    como aldea de jugador propiedad de los Natars. O sea que pagaba manutención de
 *    tropas, entraba en `starvation()` y su guarnición de miles de unidades se moría
 *    sola en minutos, dejando el artefacto servido. Es exactamente el bug que ya se
 *    había arreglado en las Aldeas de la Maravilla (ver GameEngine/NatarVillage.php).
 *  - Ponía `pop = 163` fijo, sin relación con los edificios que le escribía después, que
 *    es la forma de dejar una aldea comiendo cereal por edificios que no existen.
 *  - No la aprovisionaba, así que quedaba con 800 de almacén contra un escondite 10 y
 *    nunca tenía nada que saquear.
 *  - El nombre y la descripción del artefacto los escribía a mano en inglés y con la
 *    numeración de tipos de este archivo, que ya no coincidía con la de `cn.php`. Hoy
 *    salen del catálogo de GameEngine/Artefact.php, que es la única definición.
 *  - `include_once("../../config.php")` apuntaba a GameEngine/config.php, que no existe.
 */

ini_set('max_execution_time', 1000);
error_reporting(E_ALL);

// Database.php ya arrastra config/connection.php y config/config.php.
include_once("../../Database.php");
include_once("../../NatarVillage.php");

$adminId = isset($_POST['admid']) ? (int)$_POST['admid'] : 0;
$access = $adminId > 0
    ? $database->query_return("SELECT access FROM ".TB_PREFIX."users WHERE id = ".$adminId)
    : array();
if(!is_array($access) || !count($access) || (int)$access[0]['access'] !== 9) {
    die("<h1><font color=\"red\">Acceso denegado: no eres administrador.</font></h1>");
}

$natarId = natarsAccountId();
if($natarId <= 0 || !count($database->query_return("SELECT id FROM ".TB_PREFIX."users WHERE id = ".$natarId))) {
    header("Location: ../../../Admin/admin.php?p=npctribecreatenatar");
    exit;
}

/**
 * Cuántos artefactos de cada tamaño se siembran por tipo.
 *
 * Son los números que ya tenía este mod (6 pequeños, 4 grandes y 1 único por tipo); no
 * hay una cifra oficial publicada. El plano de almacenamiento no tiene versión única,
 * como en el original.
 */
function artefactSeedCounts() {
    return array(
        ARTEFACT_SIZE_SMALL  => 6,
        ARTEFACT_SIZE_LARGE  => 4,
        ARTEFACT_SIZE_UNIQUE => 1
    );
}

/** La guarnición que defiende una aldea de artefacto, por tamaño. */
function artefactSeedGarrison($size) {
    // El pequeño usa la guarnición de una Aldea de la Maravilla; el grande el doble y el
    // único el cuádruple, que es la proporción que traía este mod.
    $multiplier = $size === ARTEFACT_SIZE_SMALL ? 1 : ($size === ARTEFACT_SIZE_LARGE ? 2 : 4);
    $garrison = array();
    foreach(natarWonderGarrison() as $unit => $amount) {
        $garrison[$unit] = (int)$amount * $multiplier;
    }
    return $garrison;
}

/**
 * Crea una aldea natar con un artefacto adentro.
 *
 * Mismo camino que el instalador y que `addWW.php`: la economía y la clase NPC salen de
 * NatarVillage.php, nunca de SQL escrito acá.
 */
function seedArtefactVillage($type, $size, $natarId) {
    global $database;

    $wid = $database->generateBase(rand(1, 4));
    if((int)$wid <= 0) {
        return false;
    }
    $database->setFieldTaken($wid);
    $name = natarArtefactVillageName($type, $size, $wid);
    $database->addVillage($wid, $natarId, $name, '0');
    $database->addResourceFields($wid, $database->getVillageType($wid));
    $database->addUnits($wid);
    $database->addTech($wid);
    $database->addABTech($wid);

    // `natar = 1` la marca como aldea de escenario y `npckind` estático la deja fuera de
    // la manutención y de la hambruna. Sin estas dos columnas la guarnición se muere sola.
    mysql_query("UPDATE `".TB_PREFIX."vdata` SET `name` = '".mysql_real_escape_string($name)."', "
        ."`capital` = 0, `natar` = 1 WHERE `wref` = ".(int)$wid);
    if(method_exists($database, 'ensureNpcVillageColumns') && $database->ensureNpcVillageColumns()) {
        mysql_query("UPDATE `".TB_PREFIX."vdata` SET `npckind` = ".NPC_KIND_STATIC
            ." WHERE `wref` = ".(int)$wid);
    }

    natarArtefactBuildings($wid, artefactTreasuryRequirement($size));
    natarRestockGarrison($wid, artefactSeedGarrison($size));
    // Aprovisiona campos, almacén y granero, y recalcula la población desde `fdata`: sin
    // esto la aldea queda con 800 de almacén y nunca hay nada que saquear.
    natarProvisionVillage($wid);

    $database->addArtefact($wid, $natarId, $type, $size);
    return true;
}

/**
 * Sembrar sobre un mundo que ya tiene artefactos los DUPLICA: no hay reemplazo ni forma de
 * deshacerlo desde el panel. La pantalla avisa y pide una casilla; esta guarda es la que de
 * verdad protege, porque un doble clic o un F5 sobre el POST no pasan por la pantalla.
 */
$alreadySeeded = count($database->getAllArtefacts());
if($alreadySeeded > 0 && (!isset($_POST['confirmar']) || $_POST['confirmar'] !== 'si')) {
    header("Location: ../../../Admin/admin.php?p=addArtefacts&e=confirmar");
    exit;
}

$counts = artefactSeedCounts();
$created = 0;
foreach(array_keys(artefactTypeCatalog()) as $type) {
    foreach($counts as $size => $amount) {
        // El plano de almacenamiento no tiene versión única, igual que en el original.
        if($type === ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE) {
            continue;
        }
        for($i = 0; $i < $amount; $i++) {
            if(seedArtefactVillage($type, $size, $natarId)) {
                $created++;
            }
        }
    }
}

header("Location: ../../../Admin/admin.php?p=addArtefacts&g=".$created);
