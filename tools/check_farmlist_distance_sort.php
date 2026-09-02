<?php
/**
 * La lista de granjas debe ordenar la distancia como número aunque la columna
 * raidlist.distance siga siendo VARCHAR por compatibilidad con mundos existentes.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_farmlist_distance_sort.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__).'/Templates/goldClub/farmlist.tpl');
$expectedOrder = 'ORDER BY CAST(distance AS DECIMAL(10,2)) ASC, id ASC';

if(strpos($template, $expectedOrder) === false) {
    fwrite(STDERR, "[FALLA] La lista de granjas volvió a ordenar distance como texto.\n");
    exit(1);
}

if(strpos($template, 'ORDER BY distance ASC') !== false) {
    fwrite(STDERR, "[FALLA] Queda una consulta de lista de granjas con orden lexicográfico.\n");
    exit(1);
}

echo "[OK] La lista de granjas ordena la distancia numéricamente y de forma estable.\n";
