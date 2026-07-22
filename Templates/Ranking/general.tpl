<?php
   $statisticsAccessLimit = INCLUDE_ADMIN ? 10 : 8;
   $tribe1 = mysql_query("SELECT SQL_CACHE * FROM ".TB_PREFIX."users WHERE tribe = 1 AND access < ".$statisticsAccessLimit);
   $tribe2 = mysql_query("SELECT SQL_CACHE * FROM ".TB_PREFIX."users WHERE tribe = 2 AND access < ".$statisticsAccessLimit);
   $tribe3 = mysql_query("SELECT SQL_CACHE * FROM ".TB_PREFIX."users WHERE tribe = 3 AND access < ".$statisticsAccessLimit);
   $tribes = array(mysql_num_rows($tribe1), mysql_num_rows($tribe2), mysql_num_rows($tribe3));
   $users = mysql_num_rows(mysql_query("SELECT SQL_CACHE * FROM ".TB_PREFIX."users WHERE tribe <= 3 AND access < ".$statisticsAccessLimit)); ?>

<h4 class="round">Estadísticas</h4>
<table cellpadding="1" cellspacing="1" id="world_player" class="transparent">
     <tbody>
     <tr>
      <th>Jugadores registrados</th>
      <td><?php echo $users; ?></td>
     </tr>
     <tr>
      <th>Jugadores activos</th>
      <td><?php
                   $active = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE tribe <= 3 AND access < ".$statisticsAccessLimit." AND ".time()."-timestamp < (3600*24)"));
                   echo $active; ?></td>
     </tr>     <tr>
      <th>Jugadores en línea</th>
      <td><?php
				$result = mysql_query("SELECT o.* FROM ".TB_PREFIX."online AS o INNER JOIN ".TB_PREFIX."users AS u ON u.username = o.name WHERE u.tribe <= 3 AND u.access < ".$statisticsAccessLimit);
				$num_rows = mysql_num_rows($result);
				echo $num_rows;
				?></td>
     </tr>
    </tbody>
   </table>
<h4 class="round spacer">Tribus</h4>
<table cellpadding="1" cellspacing="1" id="world_tribes" class="world">
    <thead>
     <tr class="hover">
      <td>Tribu</td>
      <td>Registrados</td>
      <td>Porcentaje</td>
     </tr>
     </thead>
     <tbody>
     <tr class="hover">
      <td>Romanos</td>
      <td><?php echo $tribes[0]; ?></td>
      <td><?php
$percents = 100*($tribes[0] / $users);
echo $percents = intval ($percents);
echo "%"; ?></td>
     </tr>
     <tr class="hover">
      <td>Germanos</td>
      <td><?php echo $tribes[1]; ?></td>
      <td><?php
$percents = 100*($tribes[1] / $users);
echo $percents = intval ($percents);
echo "%"; ?></td>
     </tr>
     <tr class="hover">
      <td>Galos</td>
      <td><?php echo $tribes[2]; ?></td>
      <td><?php
$percents = 100*($tribes[2] / $users);
echo $percents = intval ($percents);
echo "%"; ?></td>
     </tr>
    </tbody>
   </table>

   <h4 class="round spacer">Varios</h4>
    <table cellpadding="1" cellspacing="1" id="world_tribes" class="world">
    <thead>
     <tr class="hover">
      <td>Ataques</td>
      <td>Bajas</td>
      <td>Fecha</td>
     </tr>
     </thead>
        <tbody>
            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()); ?></td>

                <td><?php echo $database->getAttackCasualties(time()); ?></td>

                <td><?php echo date("j. M"); ?></td>
            </tr>
			
            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*1)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*1)); ?></td>

                <td><?php echo date("j. M",time()-(86400*1)); ?></td>
            </tr>

            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*2)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*2)); ?></td>

                <td><?php echo date("j. M",time()-(86400*2)); ?></td>
            </tr>

            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*3)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*3)); ?></td>

                <td><?php echo date("j. M",time()-(86400*3)); ?></td>
            </tr>
            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*4)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*4)); ?></td>

                <td><?php echo date("j. M",time()-(86400*4)); ?></td>
            </tr>

            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*5)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*5)); ?></td>

                <td><?php echo date("j. M",time()-(86400*5)); ?></td>
            </tr>

            <tr class="hover">
                <td><?php echo $database->getAttackByDate(time()-(86400*6)); ?></td>

                <td><?php echo $database->getAttackCasualties(time()-(86400*6)); ?></td>

                <td><?php echo date("j. M",time()-(86400*6)); ?></td>
            </tr>
        </tbody>
    </table>
