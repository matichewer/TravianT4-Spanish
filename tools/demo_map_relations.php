<?php
/**
 * Arma en el mundo LOCAL una manzana donde se ven las seis relaciones del mapa a la vez.
 *
 * Para que sirve. Los colores del marco de una aldea dependen de la diplomacia entre
 * alianzas, asi que para compararlos hace falta un mundo con aliados, NAP, guerra y
 * desconocidos al mismo tiempo. Montar eso a mano cada vez es tedioso y se hace mal.
 *
 * Deja seis aldeas contiguas alrededor de (38|-19), lejos de la zona gris para que el tinte
 * no ensucie la comparacion, y las relaciones ya firmadas. Se entra con la cuenta `chewer`.
 *
 * SOLO PARA DESARROLLO: crea cuentas de prueba y reescribe la diplomacia entera.
 *
 * Uso: docker compose exec -T web php /var/www/html/tools/demo_map_relations.php --local
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if(!in_array('--local', $argv, true)) {
    fwrite(STDERR,
        "Crea cuentas de prueba y reescribe la diplomacia. Solo contra el Docker local:\n"
        ."  docker compose exec -T web php /var/www/html/tools/demo_map_relations.php --local\n");
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
include "Data/unitdata.php";
include "GeneratorX.php";
include "GreyZone.php";

global $database, $generator;
if(!isset($generator) || !is_object($generator)) {
    $generator = new GeneratorX();
}
$connection = $database->connection;
$password = 'zonagris123';

// El centro de la manzana. Lejos del centro del mapa para que la zona gris no tiña nada.
$centreX = 38;
$centreY = -19;
if(greyZoneContainsCoordinates($centreX, $centreY)) {
    fwrite(STDERR, "La manzana de demostracion cae dentro de la zona gris.\n");
    exit(1);
}

/** Cuenta de prueba: la crea si no existe y devuelve su id. */
function demoUser($name, $tribe, $alliance) {
    global $database, $connection, $password;
    $rows = $database->query_return(
        "SELECT id FROM ".TB_PREFIX."users WHERE username = '".mysql_real_escape_string($name)."' LIMIT 1"
    );
    if(is_array($rows) && count($rows)) {
        $id = (int)$rows[0]['id'];
    } else {
        mysqli_query($connection,
            "INSERT INTO ".TB_PREFIX."users (username,password,access,email,timestamp,tribe,location,act,protect,quest,fquest,cp) "
            ."VALUES ('".mysql_real_escape_string($name)."','".md5($password)."',2,'"
            .strtolower($name)."@local.invalid',".time().",".(int)$tribe.",'','',0,25,35,'0,0,0,0,0,0,0,0,0,0,0')");
        $id = (int)mysqli_insert_id($connection);
    }
    mysqli_query($connection, "UPDATE ".TB_PREFIX."users SET tribe = ".(int)$tribe
        .", alliance = ".(int)$alliance." WHERE id = ".$id);
    return $id;
}

/** Alianza de prueba, con id fijo. */
function demoAlliance($id, $tag, $leader) {
    global $connection;
    mysqli_query($connection,
        "INSERT INTO ".TB_PREFIX."alidata (id,name,tag,leader,coor,advisor,recruiter,notice,`desc`,max) "
        ."VALUES (".(int)$id.",'".$tag."','".$tag."',".(int)$leader.",0,0,0,'','',60) "
        ."ON DUPLICATE KEY UPDATE tag = VALUES(tag), name = VALUES(name)");
}

/** Deja una aldea de $owner en (x|y), creandola si hace falta. */
function demoVillage($x, $y, $ownerId, $ownerName, $villageName, $pop) {
    global $database, $generator, $connection;
    $wref = $generator->getBaseID($x, $y);
    // La casilla tiene que ser un valle: si el generador la dejo como oasis, addResourceFields()
    // no tiene caso para el tipo 0 y la aldea nace sin campos.
    mysqli_query($connection, "UPDATE ".TB_PREFIX."wdata SET oasistype = 0, fieldtype = "
        ."IF(fieldtype = 0, 3, fieldtype), image = 't3' WHERE id = ".(int)$wref);
    mysqli_query($connection, "DELETE FROM ".TB_PREFIX."odata WHERE wref = ".(int)$wref);
    if(!$database->getVillageState($wref)) {
        $database->setFieldTaken($wref);
        $database->addVillage($wref, $ownerId, $ownerName, 0);
        $database->addResourceFields($wref, $database->getVillageType($wref));
        $database->addUnits($wref);
        $database->addTech($wref);
        $database->addABTech($wref);
    }
    mysqli_query($connection, "UPDATE ".TB_PREFIX."vdata SET owner = ".(int)$ownerId
        .", name = '".mysql_real_escape_string($villageName)."', pop = ".(int)$pop
        .", created = ".(time() - 7776000)." WHERE wref = ".(int)$wref);
    return $wref;
}

$chewer = (int)$database->query_return(
    "SELECT id FROM ".TB_PREFIX."users WHERE username = 'chewer' LIMIT 1")[0]['id'];

demoAlliance(1, 'RDC', $chewer);
mysqli_query($connection, "UPDATE ".TB_PREFIX."users SET alliance = 1 WHERE id = ".$chewer);

// Un jugador por relacion. La tribu cambia el dibujo del castillo, no el marco; se usan las
// tres para que de paso se vea que el sufijo de tribu resuelve en todas.
$mate    = demoUser('CompaDemo',  3, 1);   // misma alianza -> azul
$ally    = demoUser('AliadoDemo', 1, 4);   // aliado        -> verde
$nap     = demoUser('NapDemo',    2, 3);   // NAP           -> cian
$foe     = demoUser('EnemigoDemo',1, 2);   // guerra        -> rojo
$other   = demoUser('AjenoDemo',  3, 5);   // sin pacto     -> gris
demoAlliance(2, 'WAR', $foe);
demoAlliance(3, 'NAP', $nap);
demoAlliance(4, 'ALI', $ally);
demoAlliance(5, 'AJE', $other);

// La diplomacia: 1 = aliado, 2 = NAP, 3 = guerra.
mysqli_query($connection, "TRUNCATE TABLE ".TB_PREFIX."diplomacy");
mysqli_query($connection,
    "INSERT INTO ".TB_PREFIX."diplomacy (alli1,alli2,type,accepted) VALUES "
    ."(1,4,1,1),"      // RDC aliada con ALI
    ."(3,1,2,1),"      // NAP con RDC, a proposito con RDC en la SEGUNDA columna
    ."(1,2,3,1)");     // RDC en guerra con WAR

// La manzana. Misma poblacion en todas para que solo cambie el marco.
$pop = 300;
$plots = array(
    array($centreX,     $centreY,     $chewer, 'chewer',      'DEMO propia',      $pop),
    array($centreX - 1, $centreY,     $mate,   'CompaDemo',   'DEMO mi alianza',  $pop),
    array($centreX + 1, $centreY,     $ally,   'AliadoDemo',  'DEMO aliado',      $pop),
    array($centreX,     $centreY + 1, $nap,    'NapDemo',     'DEMO NAP',         $pop),
    array($centreX,     $centreY - 1, $foe,    'EnemigoDemo', 'DEMO guerra',      $pop),
    array($centreX + 1, $centreY - 1, $other,  'AjenoDemo',   'DEMO sin pacto',   $pop)
);
foreach($plots as $plot) {
    list($x, $y, $owner, $ownerName, $name, $population) = $plot;
    demoVillage($x, $y, $owner, $ownerName, $name, $population);
    printf("  (%d|%d)  %-16s %s\n", $x, $y, $ownerName, $name);
}

// Plus para chewer, que es con la que se mira.
mysqli_query($connection, "UPDATE ".TB_PREFIX."users SET plus = ".(time() + 31536000)
    ." WHERE id = ".$chewer);

printf("\nListo. Entra como 'chewer' con la clave '%s' y abri:\n", $password);
printf("   http://localhost:8080/karte.php?x=%d&y=%d\n", $centreX, $centreY);
printf("   http://localhost:8080/karte2.php?x=%d&y=%d   (mapa grande)\n", $centreX, $centreY);
