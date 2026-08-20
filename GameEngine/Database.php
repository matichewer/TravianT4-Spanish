<?php

include_once(dirname(__FILE__).'/../config/connection.php');
include_once(dirname(__FILE__).'/../config/config.php');
require_once(dirname(__FILE__).'/Production.php');
// La frontera entre cuentas del sistema y de jugadores. Va acá porque es el único
// include que comparten el motor, esta capa y las páginas públicas (index, serverLogin,
// serverRegister), que no cargan la sesión ni las tablas de juego.
require_once(dirname(__FILE__).'/Accounts.php');
// Las dos pestañas de tropas del resumen de aldeas. Va acá por el mismo motivo que
// Production.php: es una agregación sobre varias tablas que tiene que dar el mismo
// número desde la plantilla y desde los checkers.
require_once(dirname(__FILE__).'/TroopOverview.php');

// Sólo queda el driver MySQLi. El de `mysql_*` (DB_TYPE 0) no podía correr en PHP 7
// —la extensión no existe desde PHP 7.0— y sobrevivía como una copia paralela del
// motor que se iba desincronizando: la fórmula de producción ya se había arreglado
// en uno y no en el otro. `DB_TYPE` se conserva porque `config/connection.php` lo
// define y el panel de administración lo muestra.
include("Database/db_MYSQLi.php");
?>