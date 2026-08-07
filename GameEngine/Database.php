<?php

include_once(dirname(__FILE__).'/../config/connection.php');
include_once(dirname(__FILE__).'/../config/config.php');
require_once(dirname(__FILE__).'/Production.php');

// Sólo queda el driver MySQLi. El de `mysql_*` (DB_TYPE 0) no podía correr en PHP 7
// —la extensión no existe desde PHP 7.0— y sobrevivía como una copia paralela del
// motor que se iba desincronizando: la fórmula de producción ya se había arreglado
// en uno y no en el otro. `DB_TYPE` se conserva porque `config/connection.php` lo
// define y el panel de administración lo muestra.
include("Database/db_MYSQLi.php");
?>