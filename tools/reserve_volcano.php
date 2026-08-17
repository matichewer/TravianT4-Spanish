<?php
/**
 * Deja el recuadro del volcán como escenario en un mundo ya instalado: le saca los oasis
 * que le hacen agujeros al dibujo y lo marca como no fundable.
 *
 * En un mundo nuevo no hace falta: el generador ya lo hace. Esto es para los que ya están
 * en marcha, donde el terreno se escribió una sola vez.
 *
 * Por defecto sólo informa. Para escribir, --aplicar.
 *
 *   docker compose exec -T web php /var/www/html/tools/reserve_volcano.php
 *   docker compose exec -T web php /var/www/html/tools/reserve_volcano.php --aplicar
 */
if(PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$root = dirname(__DIR__);
chdir($root);
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL); ini_set('display_errors','1');
$_SESSION = array();
include "config/connection.php"; include "config/config.php"; include "Database.php";
include "Data/buidata.php"; include "Data/unitdata.php"; include "GreyZone.php";

$apply = in_array('--aplicar', $argv, true);
if(!$apply) {
    echo "MODO SIMULACIÓN: no se escribe nada. Volvé a correrlo con --aplicar.\n\n";
}
printf("Volcán: %d piezas centradas en (%d|%d).\n\n",
    count(greyZoneVolcanoSprites()), GREY_ZONE_VOLCANO_OFFSET_X, GREY_ZONE_VOLCANO_OFFSET_Y);
$report = greyZoneReserveVolcano($apply);
printf("  casillas reservadas : %d\n", $report['reservadas']);
printf("  oasis retirados     : %d\n", $report['oasis_limpiados']);
printf("  reservas liberadas  : %d  (casillas de una posición anterior del volcán)\n", $report['liberadas']);
foreach($report['saltadas'] as $skipped) {
    echo "  se deja como está   : $skipped\n";
}
echo $apply ? "\nAplicado.\n" : "\nNada escrito. Volvé a correrlo con --aplicar.\n";
