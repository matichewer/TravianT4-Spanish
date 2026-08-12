<?php
// Este codigo es de la epoca de PHP 5 y dispara avisos de deprecacion en PHP 7.
// Imprimirlos rompe los header("Location: ...") de mas abajo con "headers
// already sent", asi que se silencian. Warnings y errores reales siguen visibles.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('Asia/Tehran');
if (file_exists("installconfig/constant.php") && file_exists("installconfig/connection.php")) {
set_time_limit(0);
    include ("installconfig/connection.php");
    include ("installconfig/constant.php");
    include ("include/database.php");
}

class Process {

    function __construct() {
        if (isset($_POST['subconst'])) {
            $this->constForm();
        } else
        if (isset($_POST['substruc'])) {
            $this->createStruc();
        } else
        if (isset($_POST['subwdata'])) {
            $this->createWdata();
        } else
        if (isset($_POST['subacc'])) {
            $this->createAcc();
        } else {
            header("Location: index.php");
        }
    }

    function constForm() {
        if (!file_exists("installconfig")) {
            if (!mkdir("installconfig", 0775)) {
                die("<br/><br/><br/>Can't create config folder: install\installconfig");
            }
        }
        global $database;
        $myFile = "installconfig/constant.php";
        $fh = fopen($myFile, 'w') or die("<br/><br/><br/>Can't open or create file: install\installconfig\constant.php");
        // Sólo hay un driver: MySQLi. El de `mysql_*` no existe desde PHP 7.0, así
        // que elegirlo dejaba una instalación que no arrancaba.
        $dbType = 1;
        $text = file_get_contents("data/constant_format_mysqli.tpl");
        $text = preg_replace("'%TRADERCAP%'", $_POST['tradercap'], $text);
        $text = preg_replace("'%CRANNYCAP%'", $_POST['crannycap'], $text);
        $text = preg_replace("'%TRAPPERCAP%'", $_POST['trappercap'], $text);
        $text = preg_replace("'%UTRACK%'", $_POST['trackusers'], $text);
        $text = preg_replace("'%UTOUT%'", $_POST['timeout'], $text);
        $text = preg_replace("'%MAX%'", $_POST['wmax'], $text);
        $text = preg_replace("'%ANAME%'", $_POST['aname'], $text);
        $text = preg_replace("'%ARANK%'", $_POST['admin_rank'], $text);
        $text = preg_replace("'%STARTTIME%'", time(), $text);
        $text = preg_replace("'%LIMIT_MAILBOX%'", $_POST['limit_mailbox'], $text);
        $text = preg_replace("'%MAX_MAILS%'", $_POST['max_mails'], $text);
        $text = preg_replace("'%ERROR%'", $_POST['error'], $text);
        $text = preg_replace("'%GREAT_WKS%'", $_POST['great_wks'], $text);
        $text = preg_replace("'%TS_THRESHOLD%'", $_POST['ts_threshold'], $text);
        $text = preg_replace("'%SSTARTDATE%'", $_POST['start_date'], $text);
        $text = preg_replace("'%SSTARTTIME%'", $_POST['start_time'], $text);
        $text = preg_replace("'%REG_OPEN%'", $_POST['reg_open'], $text);
        $text = preg_replace("'%PEACE%'", $_POST['peace'], $text);
        $text = preg_replace("'%LIMIT_TROOPS%'", $_POST['limit_troops'], $text);
        $text = preg_replace("'%STORAGE_MULTIPLIER%'", $_POST['storage_multiplier'], $text);
        $text = preg_replace("'%SINGLEPLAYERMODE%'", $_POST['singleplayer_mode'], $text);
        fwrite($fh, $text);
        fclose($fh);

        $myFile = "installconfig/connection.php";
        $fh = fopen($myFile, 'w') or die("<br/><br/><br/>Can't open or create file: install\installconfig\connection.php");
        $text = file_get_contents("data/connection.tpl");
        $text = preg_replace("'%SSERVER%'", $_POST['sserver'], $text);
        $text = preg_replace("'%SUSER%'", $_POST['suser'], $text);
        $text = preg_replace("'%SPASS%'", $_POST['spass'], $text);
        $text = preg_replace("'%SDB%'", $_POST['sdb'], $text);
        $text = preg_replace("'%PREFIX%'", $_POST['prefix'], $text);
        $text = preg_replace("'%CONNECTT%'", $dbType, $text);

        fwrite($fh, $text);

        if (file_exists("installconfig/constant.php") && file_exists("installconfig/connection.php")) {
            // constant.php arranca con un "SELECT * FROM config", asi que no se
            // puede incluir hasta que la tabla exista y tenga la fila: en una
            // base vacia el query devuelve false, mysqli_fetch_array() avisa por
            // pantalla y esa salida rompe el header() del final.
            include_once ("installconfig/connection.php");
            include_once 'include/database.php';
            $str = file_get_contents("data/config.sql");
            $str = preg_replace("'%PREFIX%'", TB_PREFIX, $str);
            if (DB_TYPE) {
                $database->connection->multi_query($str);
                while($database->connection->more_results()) {// flush multi_queries
                   $database->connection->next_result();
                }
            } else {
                $database->mysql_exec_batch($str);
            }
            $database->query("INSERT into " . $_POST['prefix'] . "config values ('" . $_POST['servername'] . "', '" . $_POST['lang'] . "', '" . $_POST['speed'] . "', 'gpack/travian_Travian_4.0_41/', '" . $_POST['incspeed'] . "', '" . $_POST['evaspeed'] . "', '" . $_POST['healspeed'] . "', '" . $_POST['advspeed'] . "', '" . $_POST['demolish'] . "', '" . $_POST['quest'] . "', '" . $_POST['beginner'] . "', '" . $_POST['auction_time'] . "', '" . $_POST['ww'] . "', '" . $_POST['activate'] . "', '" . $_POST['plus_time'] . "', '" . $_POST['plus_production'] . "', '" . $_POST['log_build'] . "', '" . $_POST['log_tech'] . "', '" . $_POST['log_login'] . "', '" . $_POST['log_gold_fin'] . "', '" . $_POST['log_admin'] . "', '" . $_POST['log_war'] . "', '" . $_POST['log_market'] . "', '" . $_POST['log_illegal'] . "', '" . $_POST['box1'] . "', '" . $_POST['box2'] . "', '" . $_POST['box3'] . "', '" . $_POST['home1'] . "', '" . $_POST['home2'] . "', '" . $_POST['home3'] . "', '" . $_POST['aemail'] . "', '" . $_POST['homepage'] . "', '" . $_POST['paypal_gold'] . "', '" . (int)$_POST['medal_top'] . "', '" . (int)$_POST['medal_ally_top'] . "')");

            // Ahora si: la tabla config existe y tiene su fila.
            include_once ("installconfig/constant.php");

            header("Location: index.php?s=2");
        } else {
            header("Location: index.php?s=1&c=1");
        }

        fclose($fh);
    }

    function createStruc() {
        global $database;
        $str = file_get_contents("data/sql.sql");
        $str = preg_replace("'%PREFIX%'", TB_PREFIX, $str);
        if (DB_TYPE) {
            $result = $database->connection->multi_query($str);
        } else {
            $result = $database->mysql_exec_batch($str);
        }
        if ($result) {
            header("Location: index.php?s=3");
        } else {
            header("Location: index.php?s=2&c=1");
        }
    }

    function createWdata() {
        header("Location: include/wdata.php");
    }

}

$process = new Process;
?>
