<?php
/**
 * Siembra el premio de la zona gris —oasis del 50% de cereal y valles de 15 campos— en un
 * mundo que ya está en juego.
 *
 * Por qué hace falta. El terreno bueno de la zona lo escribe el generador de mapa, que corre
 * UNA sola vez, al instalar. En un mundo ya en marcha la zona gris existe pero está vacía de
 * recompensa: es sólo un peaje, oleadas de natars a quien funde ahí y nada a cambio. Esto
 * agrega la recompensa sin regenerar el mapa.
 *
 * La regla que decide dónde. No alcanza con "dentro de la zona": si un oasis del 50% queda
 * pegado al borde, cualquiera funda una aldea justo AFUERA de la zona y lo anexa igual, sin
 * pagar el peaje. La anexión alcanza un cuadrado de `Automation::OASIS_ANNEX_RANGE` casillas,
 * así que sólo sirven las casillas cuyo cuadrado de anexión entero cae dentro de la zona.
 * Eso deja un núcleo bastante más chico que el disco —con radio 8, 49 casillas de 197— y es
 * a propósito: el borde no es sembrable.
 *
 * Los 15-croppers no tienen ese problema (para usarlo hay que fundar ENCIMA, o sea dentro de
 * la zona, o sea pagando las oleadas), pero se siembran en el mismo núcleo por coherencia.
 *
 * Nunca toca una casilla con aldea, un oasis ya anexado, ni el recuadro del volcán.
 *
 * Uso:
 *   ... seed_grey_zone_terrain.php                          sólo informa cuánto lugar hay
 *   ... seed_grey_zone_terrain.php --oasis=6 --croppers=4   simula
 *   ... seed_grey_zone_terrain.php --oasis=6 --croppers=4 --aplicar
 *
 * Opciones:
 *   --profundidad=N   cuántas casillas de margen exigir (por defecto, el rango de anexión).
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
set_time_limit(0);

$_SESSION = array();
define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);   // no queremos que corra el barrido entero
include "config/connection.php";
include "config/config.php";
include "Database.php";
include "GeneratorX.php";
include "GreyZone.php";
include "Automation.php";

global $database, $generator;
if(!isset($generator) || !is_object($generator)) {
    $generator = new GeneratorX();
}

$apply = in_array('--aplicar', $argv, true);
$wantOasis = 0;
$wantCroppers = 0;
$depth = Automation::OASIS_ANNEX_RANGE;
foreach($argv as $arg) {
    if(preg_match('/^--oasis=(\d+)$/', $arg, $m)) {
        $wantOasis = (int)$m[1];
    } elseif(preg_match('/^--croppers=(\d+)$/', $arg, $m)) {
        $wantCroppers = (int)$m[1];
    } elseif(preg_match('/^--profundidad=(\d+)$/', $arg, $m)) {
        $depth = (int)$m[1];
    }
}

if(!greyZoneEnabled()) {
    fwrite(STDERR, "La zona gris está desactivada (GREY_ZONE_OUTER_RADIUS = 0).\n");
    exit(1);
}

printf("Zona gris: disco de radio %d · margen exigido: %d casillas%s\n\n",
    GREY_ZONE_OUTER_RADIUS, $depth,
    $depth === Automation::OASIS_ANNEX_RANGE ? ' (el rango de anexión de oasis)' : '');

// --- 1. El núcleo sembrable -------------------------------------------------------------
// Una casilla sirve sólo si TODO su cuadrado de anexión sigue dentro de la zona: si alguna
// esquina cae afuera, ahí se puede fundar y anexar sin pisar la zona gris.
$radius = GREY_ZONE_OUTER_RADIUS;
$core = array();
$discTiles = 0;
for($x = -$radius; $x <= $radius; $x++) {
    for($y = -$radius; $y <= $radius; $y++) {
        if(!greyZoneContainsCoordinates($x, $y)) {
            continue;
        }
        $discTiles++;
        $deep = true;
        for($dx = -$depth; $dx <= $depth && $deep; $dx++) {
            for($dy = -$depth; $dy <= $depth && $deep; $dy++) {
                if(!greyZoneContainsCoordinates($x + $dx, $y + $dy)) {
                    $deep = false;
                }
            }
        }
        if($deep) {
            $core[] = array($x, $y);
        }
    }
}
printf("Núcleo profundo: %d casillas de las %d del disco.\n", count($core), $discTiles);

// --- 2. Qué hay hoy en cada una ---------------------------------------------------------
$free = array();
$upgradable = array();
$blocked = array();
foreach($core as $tile) {
    list($x, $y) = $tile;
    if(greyZoneIsVolcano($x, $y)) {
        $blocked[] = "($x|$y) volcán";
        continue;
    }
    $wref = $generator->getBaseID($x, $y);
    $row = $database->query_return(
        "SELECT w.id, w.fieldtype, w.oasistype, w.occupied, v.wref AS village, "
        ."o.wref AS oasis, o.owner AS oasisowner, o.conqured "
        ."FROM ".TB_PREFIX."wdata w "
        ."LEFT JOIN ".TB_PREFIX."vdata v ON v.wref = w.id "
        ."LEFT JOIN ".TB_PREFIX."odata o ON o.wref = w.id "
        ."WHERE w.id = ".(int)$wref
    );
    if(!is_array($row) || !count($row)) {
        $blocked[] = "($x|$y) sin casilla";
        continue;
    }
    $row = $row[0];
    if($row['village'] !== null) {
        $blocked[] = "($x|$y) aldea";
        continue;
    }
    if((int)$row['occupied'] === 1) {
        $blocked[] = "($x|$y) reservada";
        continue;
    }
    if((int)$row['oasistype'] !== 0) {
        // Anexado = lo tomó una aldea. Ojo con `owner`: un oasis LIBRE no tiene owner 0, tiene
        // owner 3 (la Naturaleza), así que comparar contra 0 los daba a todos por anexados.
        // Las dos columnas hay que mirarlas juntas porque se sincronizan a mano.
        if((int)$row['conqured'] !== 0 || isPlayerAccount((int)$row['oasisowner'])) {
            $blocked[] = "($x|$y) oasis anexado";
            continue;
        }
        if((int)$row['oasistype'] === 12) {
            $blocked[] = "($x|$y) ya es 50% cereal";
            continue;
        }
        $upgradable[] = array($x, $y, (int)$wref, (int)$row['oasistype']);
        continue;
    }
    if((int)$row['fieldtype'] === 6) {
        $blocked[] = "($x|$y) ya es 15c";
        continue;
    }
    $free[] = array($x, $y, (int)$wref, (int)$row['fieldtype']);
}

printf("   %d valles libres · %d oasis mejorables a 50%% cereal · %d ocupadas\n",
    count($free), count($upgradable), count($blocked));
if(in_array('--detalle', $argv, true)) {
    foreach($blocked as $reason) {
        echo '      descartada '.$reason.PHP_EOL;
    }
}
echo PHP_EOL;

/**
 * Reparte N casillas lo más separadas posible entre sí, siempre igual para la misma entrada.
 *
 * Sin esto quedan todas pegadas en una esquina del núcleo y una sola aldea se las lleva
 * todas, que es justo lo que se quiere evitar.
 */
function spreadPick($candidates, $count, $alreadyTaken) {
    $picked = array();
    while(count($picked) < $count && $candidates) {
        $bestIndex = null;
        $bestScore = -1;
        foreach($candidates as $index => $candidate) {
            $score = PHP_INT_MAX;
            foreach(array_merge($picked, $alreadyTaken) as $taken) {
                $score = min($score, max(abs($candidate[0] - $taken[0]), abs($candidate[1] - $taken[1])));
            }
            if($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }
        $picked[] = $candidates[$bestIndex];
        unset($candidates[$bestIndex]);
    }
    return $picked;
}

// --- 3. Elegir --------------------------------------------------------------------------
// Los oasis salen primero de los que ya son oasis (mejorarlos no cambia el dibujo del mapa
// ni el reparto de valles), y recién después de los valles libres.
$oasisPicks = array();
$pool = $upgradable;
$oasisPicks = spreadPick($pool, min($wantOasis, count($pool)), array());
$stillWanted = $wantOasis - count($oasisPicks);
if($stillWanted > 0) {
    $fromValleys = spreadPick($free, min($stillWanted, count($free)), $oasisPicks);
    foreach($fromValleys as $pick) {
        $oasisPicks[] = array($pick[0], $pick[1], $pick[2], 0);
    }
}

$usedKeys = array();
foreach($oasisPicks as $pick) {
    $usedKeys[$pick[0].'|'.$pick[1]] = true;
}
$freeForCroppers = array();
foreach($free as $candidate) {
    if(!isset($usedKeys[$candidate[0].'|'.$candidate[1]])) {
        $freeForCroppers[] = $candidate;
    }
}
$cropperPicks = spreadPick($freeForCroppers, min($wantCroppers, count($freeForCroppers)), $oasisPicks);

if($wantOasis === 0 && $wantCroppers === 0) {
    echo "Nada que sembrar. Pasá --oasis=N y/o --croppers=N.\n";
    echo "Cabrían hasta ".(count($free) + count($upgradable))." oasis del 50%, o "
        .count($free)." 15-croppers (compartiendo el mismo lugar).\n";
    exit(0);
}

if(count($oasisPicks) < $wantOasis) {
    printf("OJO: pediste %d oasis y sólo hay lugar para %d.\n", $wantOasis, count($oasisPicks));
}
if(count($cropperPicks) < $wantCroppers) {
    printf("OJO: pediste %d 15-croppers y sólo hay lugar para %d.\n", $wantCroppers, count($cropperPicks));
}

echo "Oasis del 50% de cereal:\n";
foreach($oasisPicks as $pick) {
    printf("   (%d|%d)  %s\n", $pick[0], $pick[1],
        $pick[3] > 0 ? 'mejorar oasis tipo '.$pick[3] : 'valle -> oasis nuevo');
}
echo "15-croppers:\n";
foreach($cropperPicks as $pick) {
    printf("   (%d|%d)  valle tipo %d -> 15c\n", $pick[0], $pick[1], $pick[3]);
}

if(!$apply) {
    echo "\nSimulación. Volvé a correrlo con --aplicar para escribirlo.\n";
    exit(0);
}

// --- 4. Escribir ------------------------------------------------------------------------
$connection = $database->connection;
$OASIS_CROP_50 = 12;                       // el tipo de oasis "cereal 50%"
$FIELD_15C = 6;                            // el valle 1-1-1-15

foreach($oasisPicks as $pick) {
    list($x, $y, $wref, $wasOasis) = $pick;
    mysqli_query($connection, "UPDATE ".TB_PREFIX."wdata SET oasistype = ".$OASIS_CROP_50
        .", fieldtype = 0 WHERE id = ".(int)$wref);
    // Se borra y se rehace la fila de odata para que los topes de recursos correspondan al
    // tipo nuevo. Es seguro porque más arriba se descartó todo oasis con dueño.
    mysqli_query($connection, "DELETE FROM ".TB_PREFIX."odata WHERE wref = ".(int)$wref);
    mysqli_query($connection,
        "INSERT INTO ".TB_PREFIX."odata"
        ." (wref, type, conqured, wood, iron, clay, maxstore, crop, maxcrop,"
        ." lastupdated, lastupdated2, loyalty, owner, name)"
        ." VALUES (".(int)$wref.",".$OASIS_CROP_50.",0,1000,1000,1000,2000,2000,2000,"
        .time().",".time().",100,3,'Oasis sin ocupar')");
    if(!$wasOasis) {
        $database->addUnits($wref);
    }
    $database->populateOasisUnitsLow2($wref);
    printf("   (%d|%d) sembrado como oasis del 50%% de cereal\n", $x, $y);
}

foreach($cropperPicks as $pick) {
    list($x, $y, $wref) = $pick;
    mysqli_query($connection, "UPDATE ".TB_PREFIX."wdata SET fieldtype = ".$FIELD_15C
        .", oasistype = 0 WHERE id = ".(int)$wref);
    printf("   (%d|%d) convertido en 15-cropper\n", $x, $y);
}

printf("\nListo: %d oasis y %d 15-croppers dentro del núcleo de la zona gris.\n",
    count($oasisPicks), count($cropperPicks));
