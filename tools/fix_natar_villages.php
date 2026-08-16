<?php
/**
 * Repara las aldeas natar de un mundo ya instalado.
 *
 * Qué arregla:
 *   1. Repone la guarnición de la capital natar y de las 13 Aldeas de la Maravilla a
 *      los valores del instalador. Sólo suma: si una aldea todavía tiene más tropas de
 *      las que le tocan, no se le saca nada.
 *   2. Les arma la economía que nunca tuvieron (GameEngine/NatarVillage.php): campos de
 *      cereal al nivel que sostiene la guarnición, resto de los campos, molino,
 *      panadería, almacén y granero, más la población y los topes que corresponden.
 *   3. Les corta el rojo de cereal acumulado y pone `lastupdate` en la hora actual, para
 *      que la próxima acreditación de producción no aplique de una todo el tiempo que la
 *      aldea pasó sin simularse.
 *
 * Por defecto sólo muestra lo que haría. Para escribir hay que pasar --aplicar.
 *
 * Uso, dentro del contenedor web:
 *   docker compose exec -T web php /var/www/html/tools/fix_natar_villages.php
 *   docker compose exec -T web php /var/www/html/tools/fix_natar_villages.php --aplicar
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
include "NatarVillage.php";

global $database;

$apply = in_array('--aplicar', $argv, true);
if(!$apply) {
    echo "MODO SIMULACIÓN: no se escribe nada. Volvé a correrlo con --aplicar para aplicar.\n\n";
}

$villages = natarVillages();
$total = count($villages['capital']) + count($villages['wonder']);
if($total === 0) {
    fwrite(STDERR, "No se encontró ninguna aldea de la cuenta Natars. ¿Está instalado el mundo?\n");
    exit(1);
}

echo "Aldeas natar encontradas: ".count($villages['capital'])." capital, "
    .count($villages['wonder'])." de Maravilla.\n\n";

$restockedTotal = 0;
foreach(array('capital' => 'CAPITAL', 'wonder' => 'MARAVILLA') as $kind => $label) {
    foreach($villages[$kind] as $village) {
        $wref = (int)$village['wref'];
        $before = $database->getUnit($wref);
        $troopsBefore = 0;
        for($unit = 41; $unit <= 50; $unit++) {
            $troopsBefore += is_array($before) ? (int)$before['u'.$unit] : 0;
        }
        $garrison = $kind === 'capital' ? natarCapitalGarrison() : natarWonderGarrison(false);
        $target = array_sum($garrison);

        printf("[%s] %-24s (wref %d)\n", $label, $village['name'], $wref);
        printf("   tropas: %s  ->  %s\n", number_format($troopsBefore), number_format(max($troopsBefore, $target)));

        if($apply) {
            $restockedTotal += natarRestockGarrison($wref, $garrison);
            $plan = natarProvisionVillage($wref);
        } else {
            $fields = $database->getResourceLevel($wref);
            $simulated = array();
            foreach($garrison as $unit => $amount) {
                $have = is_array($before) ? (int)$before['u'.$unit] : 0;
                $simulated[$unit] = max($have, $amount);
            }
            $plan = natarVillagePlan($fields, natarGarrisonUpkeep($simulated));
        }

        printf("   campos de cereal: nivel %d (x%d)   almacén/granero: %s / %s\n",
            $plan['crop_level'], count($plan['crop_fields']),
            number_format($plan['maxstore']), number_format($plan['maxcrop']));
        printf("   cereal: %s bruto/h  -  %s población  -  %s tropas  =  %s neto/h%s\n",
            number_format($plan['gross_crop']), number_format($plan['pop']),
            number_format($plan['upkeep']), number_format($plan['net_crop']),
            $plan['net_crop'] < 0 ? "  (la cubre starvation(), que no toca aldeas NPC)" : "");
        echo "\n";
    }
}

if($apply) {
    echo "Listo. Tropas repuestas: ".number_format($restockedTotal).".\n";
} else {
    echo "Nada escrito. Volvé a correrlo con --aplicar.\n";
}
