<?php

include_once(dirname(__FILE__).'/../config/connection.php');
include_once(dirname(__FILE__).'/../config/config.php');
require_once(dirname(__FILE__).'/Production.php');
// La frontera entre cuentas del sistema y de jugadores. Va acá porque es el único
// include que comparten el motor, esta capa y las páginas públicas (index, serverLogin,
// serverRegister), que no cargan la sesión ni las tablas de juego.
require_once(dirname(__FILE__).'/Accounts.php');
// Nombres, muros y edificios de tribu. Va acá por lo mismo: la conquista (en la capa de
// datos) tiene que saber qué edificios son de tribu para derribar los que el dueño nuevo
// no podría construir, y esa lista es la misma que usa Building.php.
require_once(dirname(__FILE__).'/Catapult.php');
// Qué artefacto hace efecto, cuándo y cuánto. Va acá porque la capa de datos resuelve
// el conjunto activo (retardo, límite de tres, aldea sobre cuenta) antes de devolverlo,
// y porque la pantalla del Tesoro lee el catálogo de nombres sin cargar el motor.
require_once(dirname(__FILE__).'/Artefact.php');
// Las dos pestañas de tropas del resumen de aldeas. Va acá por el mismo motivo que
// Production.php: es una agregación sobre varias tablas que tiene que dar el mismo
// número desde la plantilla y desde los checkers.
require_once(dirname(__FILE__).'/TroopOverview.php');
// La lista de aldeas del resumen, que sus cinco pestañas tienen que compartir para
// listarlas todas en el mismo orden.
require_once(dirname(__FILE__).'/VillageOverview.php');
// La huella y los headers de caché del retrato del héroe. Va acá porque las dos puntas
// tienen que compartir la definición: las plantillas que arman la URL y los dos endpoints
// que la contestan, que además sólo incluyen este archivo y no el motor entero.
require_once(dirname(__FILE__).'/HeroImage.php');
// La version de los archivos estaticos del `?v=`. Va acá porque la usa Templates/html.tpl,
// que se incluye desde todas las páginas del juego, logueadas y públicas.
require_once(dirname(__FILE__).'/AssetVersion.php');

// Sólo queda el driver MySQLi. El de `mysql_*` (DB_TYPE 0) no podía correr en PHP 7
// —la extensión no existe desde PHP 7.0— y sobrevivía como una copia paralela del
// motor que se iba desincronizando: la fórmula de producción ya se había arreglado
// en uno y no en el otro. `DB_TYPE` se conserva porque `config/connection.php` lo
// define y el panel de administración lo muestra.
include("Database/db_MYSQLi.php");
?>