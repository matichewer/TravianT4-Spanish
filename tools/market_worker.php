<?php

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
if(!file_exists($root.'/config/installed') || !file_exists($root.'/config/connection.php')) {
    exit(0);
}

chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);

// El worker corre las mismas dos fases que corre una carga de pagina
// (marketComplete + TradeRoute), asi que necesita el MISMO motor cargado que
// arma Session.php, no un subconjunto minimo. Cargar de menos no da un error
// visible: la fila de la ruta ya se reclamo (timestamp adelantado al proximo
// horario) antes de que el envio explote, asi que el envio del dia se pierde en
// silencio y el jugador solo ve que la ruta "no hace nada".
//
// Cadena real que necesita el reparto de una ruta:
//   Database.php    -> config/connection.php, config/config.php (LANG, TB_PREFIX,
//                      SPEED...), Production.php y la clase mysqli_DB ($database)
//   Data/*          -> $bid17/$bid28 (mercaderes y capacidad), $u1..$u50 (consumo
//                      de cereal en getUpkeep) y las tablas del heroe
//   Lang/<LANG>     -> las constantes U1..U50 que Technology declara en $unarray
//   GeneratorX      -> $generator y tournamentSquareSpeedFactor()
//   Technology      -> $technology, que usa getAllUnits() al acreditar produccion
//   Logging         -> $logging
// Lo verifica tools/check_market_worker.php arrancando este mismo archivo.
require_once $root.'/GameEngine/Database.php';
require_once $root.'/GameEngine/Data/buidata.php';
require_once $root.'/GameEngine/Data/resdata.php';
require_once $root.'/GameEngine/Data/unitdata.php';
require_once $root.'/GameEngine/Data/hero_full.php';
require_once $root.'/GameEngine/Lang/'.LANG.'.php';
require_once $root.'/GameEngine/GeneratorX.php';
require_once $root.'/GameEngine/Technology.php';
require_once $root.'/GameEngine/Logging.php';
require_once $root.'/GameEngine/Automation.php';

$automation = new Automation(true);
