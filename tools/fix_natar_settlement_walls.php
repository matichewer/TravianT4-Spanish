<?php
/**
 * Le pone la muralla a las aldeas natar vivas de un mundo ya en juego.
 *
 * Las aldeas vivas ahora levantan muralla (NATAR_SETTLEMENT_WALL_TYPE, ver
 * GameEngine/NatarSettlement.php): es lo que le da sentido al ariete contra ellas, que
 * hasta ahora viajaba para que el informe dijera "No hay muralla que destruir".
 *
 * El barrido normal ya se la construye sola, pero de a un nivel por intervalo de
 * reparación —un fortín viejo tarda unos diez días en llegar al nivel que le corresponde
 * por edad—. Esto se lo pone de una, en el nivel que su cronograma de campos indica. Es
 * el único caso: una aldea que nace después de este cambio ya arranca con muro.
 *
 * No toca la capital natar ni las Aldeas de la Maravilla (guarniciones estáticas, sin
 * muro), ni ninguna aldea de jugador.
 *
 * Uso, dentro del contenedor web:
 *   docker compose exec -T web php /var/www/html/tools/fix_natar_settlement_walls.php
 *   docker compose exec -T web php /var/www/html/tools/fix_natar_settlement_walls.php --aplicar
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
include "Data/unitdata.php";
include "NatarSettlement.php";

global $database;

$apply = in_array('--aplicar', $argv, true);
if(!$apply) {
    echo "MODO SIMULACIÓN: no se escribe nada. Volvé a correrlo con --aplicar para aplicar.\n\n";
}

$settlements = natarSettlements();
if(!$settlements) {
    echo "No hay aldeas natar independientes en este mundo. Nada que hacer.\n";
    exit(0);
}

$now = time();
$pending = array();
foreach($settlements as $settlement) {
    $wref = (int)$settlement['wref'];
    $fields = $database->getResourceLevel($wref);
    if(!is_array($fields)) {
        continue;
    }
    $ideal = natarSettlementFieldLevel($settlement, $now);
    $level = isset($fields['f'.NATAR_WALL_SLOT]) ? (int)$fields['f'.NATAR_WALL_SLOT] : 0;
    $type = isset($fields['f'.NATAR_WALL_SLOT.'t']) ? (int)$fields['f'.NATAR_WALL_SLOT.'t'] : 0;
    if($type === NATAR_SETTLEMENT_WALL_TYPE && $level >= $ideal) {
        continue;
    }
    $pending[] = array(
        'wref' => $wref,
        'name' => $settlement['name'],
        'from' => $type === NATAR_SETTLEMENT_WALL_TYPE ? $level : 0,
        'to' => $ideal
    );
}

printf("%d aldea(s) viva(s); %d sin la muralla que le toca.\n\n", count($settlements), count($pending));
if(!$pending) {
    exit(0);
}

printf("%-8s %-30s %8s %8s\n", 'wref', 'aldea', 'nivel', 'queda');
echo str_repeat('-', 58)."\n";
foreach($pending as $village) {
    printf("%-8d %-30s %8d %8d\n", $village['wref'], $village['name'], $village['from'], $village['to']);
}
echo "\n";

if(!$apply) {
    echo "Volvé a correrlo con --aplicar para escribir.\n";
    exit(0);
}

// La muralla no cambia producción ni almacenamiento, pero sí suma habitantes, así que
// vdata.pop se recalcula con la misma cuenta que usa el barrido de las aldeas vivas.
foreach($pending as $village) {
    $wref = (int)$village['wref'];
    $database->query('UPDATE '.TB_PREFIX.'fdata SET '
        .'`f'.NATAR_WALL_SLOT.'` = '.(int)$village['to'].', '
        .'`f'.NATAR_WALL_SLOT.'t` = '.(int)NATAR_SETTLEMENT_WALL_TYPE.' '
        .'WHERE vref = '.$wref);
    $database->query('UPDATE '.TB_PREFIX.'vdata SET pop = '
        .(int)natarVillagePopulation($database->getResourceLevel($wref))
        .' WHERE wref = '.$wref);
    printf("   %-30s muralla nivel %d\n", $village['name'], (int)$village['to']);
}

echo "\nListo.\n";
