<?php
// Sin error_reporting(E_ALL) local: php.ini oculta los avisos del código legacy y
// activarlos acá los mostraba dentro de la página del palacio.
if(time() - (int)($_SESSION['time_p'] ?? 0) > 5) {
  $_SESSION['time_p'] = 0;
  $_SESSION['error_p'] = '';
}

if(!empty($_POST) AND isset($_GET['action']) AND $_GET['action'] == 'change_capital') {
  // No se escapa: la contraseña nunca entra en una consulta, solo en md5().
  $pass = (string)($_POST['pass'] ?? '');
  $query = mysql_query('SELECT * FROM `' . TB_PREFIX . 'users` WHERE `id` = ' . $session->uid);
  $data = mysql_fetch_assoc($query);
  if($data['password'] == md5($pass)) {
    $uid = (int)$session->uid;
    $newCapital = (int)$village->wid;
    $lockName = mysql_real_escape_string(TB_PREFIX.'capital_'.$uid);
    $lockResult = mysql_query("SELECT GET_LOCK('$lockName',2)");
    $lockRow = $lockResult ? mysql_fetch_assoc($lockResult) : false;
    if(!$lockRow || (int)reset($lockRow) !== 1) {
      $error = '<b><font class="error">No se pudo bloquear el cambio de capital. Inténtalo nuevamente.</font></b><br />';
    } else {
      $query1 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'vdata` WHERE `owner` = '.$uid.' AND `capital` = 1 LIMIT 1');
      $data1 = mysql_fetch_assoc($query1);
      $oldCapital = $data1 ? (int)$data1['wref'] : 0;
      if($oldCapital > 0 && $oldCapital !== $newCapital) {
        $buildLockName = mysql_real_escape_string(TB_PREFIX.'build_'.$oldCapital);
        $buildLockResult = mysql_query("SELECT GET_LOCK('$buildLockName',2)");
        $buildLockRow = $buildLockResult ? mysql_fetch_assoc($buildLockResult) : false;
        if(!$buildLockRow || (int)reset($buildLockRow) !== 1) {
          $error = '<b><font class="error">No se pudo bloquear la cola de construcción. Inténtalo nuevamente.</font></b><br />';
        } else {
          $pending = mysql_query('SELECT `id` FROM `' . TB_PREFIX . 'bdata` WHERE `wid` = '.$oldCapital.' AND `field` BETWEEN 1 AND 18 AND `type` BETWEEN 1 AND 4 AND `level` > 10 LIMIT 1');
          // Un trabajo encolado sobre el Taller de cantería o la Cervecería vuelve a
          // escribir `f<solar>t` cuando termina, así que resucitaría en la aldea vieja
          // el edificio que la mudanza acaba de derribar.
          $pendingCapitalOnly = mysql_query('SELECT `id` FROM `' . TB_PREFIX . 'bdata` WHERE `wid` = '.$oldCapital.' AND `type` IN (34,35) LIMIT 1');
          if($pending && mysql_num_rows($pending) > 0) {
          $error = '<b><font class="error">Finaliza o cancela las mejoras de campos superiores al nivel 10 antes de cambiar la capital.</font></b><br />';
          } elseif($pendingCapitalOnly && mysql_num_rows($pendingCapitalOnly) > 0) {
          $error = '<b><font class="error">Finaliza o cancela las obras del Taller de cantería y de la Cervecería antes de cambiar la capital.</font></b><br />';
          } else {
            mysql_query('LOCK TABLES `' . TB_PREFIX . 'vdata` WRITE, `' . TB_PREFIX . 'fdata` WRITE');
            $query2 = mysql_query('SELECT * FROM `' . TB_PREFIX . 'fdata` WHERE `vref` = '.$oldCapital);
            $data2 = mysql_fetch_assoc($query2);
            $populationLoss = 0;
            $fieldUpdates = array();
            for($i = 1; $i <= 18; ++$i) {
              $level = (int)$data2['f'.$i];
              $type = (int)$data2['f'.$i.'t'];
              if($level > 10 && $type >= 1 && $type <= 4) {
                $fieldData = $GLOBALS['bid'.$type];
                for($currentLevel = $level; $currentLevel > 10; --$currentLevel) {
                  $populationLoss += (int)$fieldData[$currentLevel]['pop'];
                }
                $fieldUpdates[] = '`f'.$i.'` = 10';
              }
            }
            // Los edificios que sólo existen en la capital se derriban al mudarla: el
            // Taller de cantería (34) y la Cervecería (35). Si la Cervecería sobrevivía
            // quedaba muerta en la aldea vieja —no daba bono (se lo busca en la capital),
            // no se podía celebrar ni mejorar ni demoler— gastando población y un solar,
            // y la cuenta podía levantar una segunda en la capital nueva.
            $capitalOnlyBuildings = array(34, 35);
            $breweryRemoved = false;
            for($i = 19; $i <= 40; ++$i) {
              $slotType = (int)$data2['f'.$i.'t'];
              if(in_array($slotType, $capitalOnlyBuildings, true)) {
                $levelData = $GLOBALS['bid'.$slotType];
                $level = (int)$data2['f'.$i];
                for($currentLevel = $level; $currentLevel >= 1; --$currentLevel) {
                  $populationLoss += (int)$levelData[$currentLevel]['pop'];
                }
                $fieldUpdates[] = '`f'.$i.'t` = 0';
                $fieldUpdates[] = '`f'.$i.'` = 0';
                if($slotType === 35) {
                  $breweryRemoved = true;
                }
              }
            }
            if(!empty($fieldUpdates)) {
              mysql_query('UPDATE `' . TB_PREFIX . 'fdata` SET '.implode(', ',$fieldUpdates).' WHERE `vref` = '.$oldCapital);
            }
            mysql_query('UPDATE `' . TB_PREFIX . 'vdata` SET `pop` = GREATEST(2, `pop` - '.$populationLoss.') WHERE `wref` = '.$oldCapital);
            mysql_query('UPDATE `' . TB_PREFIX . 'vdata` SET `capital` = CASE WHEN `wref` = '.$newCapital.' THEN 1 ELSE 0 END WHERE `owner` = '.$uid);
            mysql_query('UNLOCK TABLES');
            // Sin Cervecería no hay bono de ataque, pero la fiesta seguía corriendo y con
            // ella sus dos castigos: jefes a mitad de persuasión y catapultas al azar.
            if($breweryRemoved) {
              mysql_query('UPDATE `' . TB_PREFIX . 'users` SET `brewery` = 0 WHERE `id` = '.$uid);
            }
          }
          mysql_query("SELECT RELEASE_LOCK('$buildLockName')");
        }
      }
      mysql_query("SELECT RELEASE_LOCK('$lockName')");
    }
    if(isset($error)) {
      $_SESSION['error_p'] = $error;
      $_SESSION['time_p'] = time();
      print '<script>location.href="build.php?id=' . $building->getTypeField(26) . '&confirm=yes";</script>';
    }
    #print '<script language="javascript">location.href="build.php?id=' . $building->getTypeField(26) . '";</script>';
  } else {
    $error = '<b><font class="error"> Contraseña incorrecta</font></b><br />';
    $_SESSION['error_p'] = $error;
    $_SESSION['time_p'] = time();
    print '<script language="javascript">location.href="build.php?id=' . $building->getTypeField(26) . '&confirm=yes";</script>';
  }
}
?>
<h1 class="titleInHeader">Palacio <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid26">
<div class="build_desc">
	<a href="#" onClick="return Travian.Game.iPopup(26,4, 'gid');" class="build_logo"> 
    <img class="building big white g26" src="img/x.gif" alt="Palacio" title="Palacio" /> </a>
	El rey de la nación vive en el palacio. Cuanto mayor sea el nivel, más difícil será para los enemigos conquistar la aldea. Solo con un palacio se puede nombrar capital a una aldea. No se pueden construir un palacio y una residencia en la misma aldea. Solo se permite un palacio por cuenta. </div>
<?php
$buildingHelpType = 'palace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

if ($building->getTypeLevel(26) > 0) {
include("upgrade.tpl");
include("26_menu.tpl"); 

$test=$database->getAvailableExpansionTraining();

if($village->resarray['f'.$id] >= 10){
	include ("26_train.tpl");	
}
else{
	echo '<div class="c">Para fundar una nueva aldea necesitas un palacio de nivel 10, 15 o 20 y 3 colonos. Para conquistar una nueva aldea necesitas un palacio de nivel 10, 15 o 20 y un senador, jefe o caudillo.</div>';
}

?>

<?php
$query = mysql_query('SELECT * FROM `' . TB_PREFIX . 'vdata` WHERE `owner` = ' . $session->uid . ' AND `capital` = 1');
$data = mysql_fetch_assoc($query);
if($data['wref'] == $village->wid) {
?>
<p class="none">Esta aldea es la capital</p>
<?php 
} else {
  if(empty($_GET['confirm'])) {
    print '<p><a class="arrow" href="?id=' . $building->getTypeField(26) . '&confirm=yes">Convertir esta aldea en capital</a></p>';
  } else {
    print '<p>Introduce tu contraseña para convertir esta aldea en capital<br />
    <form method="post" action="build.php?id=' . $building->getTypeField(26) . '&action=change_capital">
     
     Contraseña: <input type="password" name="pass" />' . ($_SESSION['error_p'] ?? '') . '<br />
     <button type="submit" value="ok" name="s1" id="btn_ok" value="ok" class="startTraining">
                    <div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Ok</div></div>
                    </button>
    </form>
    </p>';
  }
}
} else {
	echo "<b>El palacio está siendo mejorado</b>";
}

?>
</div>
