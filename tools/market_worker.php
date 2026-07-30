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

require_once $root.'/GameEngine/Data/buidata.php';
require_once $root.'/GameEngine/GeneratorX.php';
require_once $root.'/GameEngine/Database.php';
require_once $root.'/GameEngine/Automation.php';

$generator = new GeneratorX;
$automation = new Automation(true);
