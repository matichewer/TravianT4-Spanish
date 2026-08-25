<?php

$del_protect = 0;

// Temp

$eigen = $database->getCoor($village->wid);

$from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);

$to = array('x'=>$coor['x'], 'y'=>$coor['y']);

$time = $generator->procDistanceTime($from,$to,300,0);

// Temp

$ckey= $generator->generateRandStr(6);

function resolve_valid_value($var)
{
	if (isset($var) && $var != '' && is_numeric($var))
	{
		return $var;
	}
	else
	{
		return 0;
	}
}

$t1  = resolve_valid_value($process['t1']);
$t2  = resolve_valid_value($process['t2']);
$t3  = resolve_valid_value($process['t3']);
$t4  = resolve_valid_value($process['t4']);
$t5  = resolve_valid_value($process['t5']);
$t6  = resolve_valid_value($process['t6']);
$t7  = resolve_valid_value($process['t7']);
$t8  = resolve_valid_value($process['t8']);
$t9  = resolve_valid_value($process['t9']);
$t10 = resolve_valid_value($process['t10']);
$t11 = resolve_valid_value($process['t11']);

if ($session->tribe == 3){ 

	$scout = $t3 > 0;
	$totalunits = $t1 + $t2 + $t4 + $t5 + $t6 + $t7 + $t8 + $t9 + $t10 + $t11;

} else {

	$scout = $t4 > 0;
	$totalunits = $t1 + $t2 + $t3 + $t5 + $t6 + $t7 + $t8 + $t9 + $t10 + $t11;

}

if (($process['c'] == 3 || $process['c'] == 4) && $totalunits== 0) {
$process['c'] = 1;
}

$confirmationTimestamp = time();
$id = $database->addA2b($ckey,$confirmationTimestamp,$process['0'],$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11,$process['c']);

if ($process['c']==1){
$actionType = "Explorar ";
}else if ($process['c']==2){
$actionType = "Refuerzo a ";
}elseif ($process['c']==3){
$actionType = "Ataque normal a ";
}else{
$actionType = "Saqueo a ";
}


$uid = $session->uid;

$tribe = $session->tribe;
$start = ($tribe-1)*10+1;
$end = ($tribe*10);
?>

<h1><?php echo $actionType." ".$process[1]; ?></h1>            

<form method="post" action="a2b.php" onsubmit="var button=this.querySelector('#btn_ok'); if(button){button.disabled=true;}">

            <table id="short_info" cellpadding="1" cellspacing="1">

                <tbody>

                    <tr>

                        <th>Ubicación</th>

                        <td><a href="position_details.php?x=<?php echo $coor['x']; ?>&amp;y=<?php echo $coor['y']; ?>"><?php echo $process[1]; ?> (<?php echo $coor['x']; ?>|<?php echo $coor['y']; ?>)</a></td>

                    </tr>

                    <tr>

                        <th>Jugador</th>

                        <td>
                        <?php if($process['2'] == 3){ ?>
                        <font class="none"><b>Naturaleza</b></font>
                        <?php } else { ?>
                        <a href="spieler.php?uid=<?php echo $process['2']; ?>">
                        <?php if($process['2'] == 2){
                        	echo "Natares";
                            	} else {
                       				echo $database->getUserField($process['2'],'username',0);
                        		} ?>
                        </a>
                        <?php } ?>
                        </td>

                    </tr>

                </tbody>

            </table>



            <table class="troop_details" cellpadding="1" cellspacing="1">

                <thead>

                    <tr>

                        <td><?php echo $village->vname; ?></td>

                        <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>"><?php echo $actionType." ".$process['1']; ?> (<?php echo $coor['x']; ?>|<?php echo $coor['y']; ?>)</td>

                    </tr>

                </thead>

                <tbody class="units">

                    <tr>

                        <td></td>
                 <?php 
                for($i=$start;$i<=($end);$i++) {
                      echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></td>";    
                  } if ($t11 > 0){
                  echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"Héroe\" alt=\"Héroe\" /></td>";
                  
                  }?>
                        
                    </tr>

                    <tr>

                        <th>Unidades</th>

						<?php

							function print_column($var)
							{
								if ($var == 0)
								{
									echo '<td class="none">0</td>';
								}
								else
								{
									echo '<td>'.$var.'</td>';
								}
							}

							print_column($t1);
							print_column($t2);
							print_column($t3);
							print_column($t4);
							print_column($t5);
							print_column($t6);
							print_column($t7);
							print_column($t8);
							print_column($t9);
							print_column($t10);
							if ($t11 > 0) { echo '<td>'.$t11.'</td>'; }

						?>

                     </tr>

                </tbody>
			<?php if ($process['c']==1){ ?>
                <tbody class="options">

                <tr>
            <th>Opciones</th>
            <td colspan="11"><input class="radio" name="spy" value="1" checked="checked" type="radio">Explorar recursos y tropas<br>
            <input class="radio" name="spy" value="2" type="radio">Explorar defensas y tropas                                            </td>
        </tr>
    </tbody>
    <?php } ?>
<?php
// Mudar la aldea natal solo tiene sentido si el héroe viaja a otra aldea propia.
// El bono de recursos del héroe se produce en la aldea natal, así que el cambio
// se ofrece acá y se aplica cuando el héroe llega.
$heroData = $database->getHeroData($session->uid);
$homeVillage = heroHomeVillage($heroData);
$canMoveHome = $t11 > 0 && $process['c'] == 2 && $process['2'] == $session->uid
	&& $process['0'] != $homeVillage;
if($canMoveHome){
	$homeName = $homeVillage > 0 ? $database->getVillageField($homeVillage,'name') : '';
	$heroRates = heroResourceRates($heroData, SPEED);
?>
	<tbody class="options">
		<tr>
			<th>Aldea natal</th>
			<td colspan="11">
				<label><input class="checkbox" name="sethome" value="1" type="checkbox"> Convertir <b><?php echo stripslashes($process['1']); ?></b> en la aldea natal de mi héroe</label>
				<div class="none" style="margin-top:4px">
				<?php
				if($homeName !== ''){
					echo 'Ahora es <b>'.stripslashes($homeName).'</b>. ';
				}
				if(array_sum($heroRates) > 0){
					echo 'Ahí se produce el bono de recursos del héroe.';
				} else {
					echo 'Tu héroe todavía no tiene puntos en producción de recursos.';
				}
				?>
				</div>
			</td>
		</tr>
	</tbody>
<?php } ?>


        <?php
        $catapultUnitId = $battle->getTribeCatapultUnit((int)$session->tribe);
        $hasCatapultArmy = $catapultUnitId > 0 && (int)$t8 > 0;
        if($hasCatapultArmy && $process['c'] != '2'){
        ?>

            <?php if($process['c']=='3'){ ?><tbody class="cata">
                <tr>
                    <th>Catapultas</th>
                    <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>">
                    
                        <select name="ctar1" class="dropdown">
                            <option value="0">Aleatorio</option>
                            <?php foreach(catapultTargetCatalog() as $targetId => $targetMeta) {
                                if($building->getTypeLevel(16) >= $targetMeta['level']) {
                                    echo '<option value="'.$targetId.'">'.htmlspecialchars($targetMeta['name'],ENT_QUOTES,'UTF-8').'</option>';
                                }
                            } ?>
                        </select>

            <?php if(catapultSecondTargetAllowed($building->getTypeLevel(16), $t8)) { ?>
                     <select name="ctar2" class="dropdown">
                <option value="0">-</option>
                <option value="99">Aleatorio</option>
                            <?php foreach(catapultTargetCatalog() as $targetId => $targetMeta) {
                                if($building->getTypeLevel(16) >= $targetMeta['level']) {
                                    echo '<option value="'.$targetId.'">'.htmlspecialchars($targetMeta['name'],ENT_QUOTES,'UTF-8').'</option>';
                                }
                            } ?>
                        </select>
                    <?php }?>

                    <span class="info">(será atacado por catapulta(s))</span>
                     </td>
                </tr>
            </tbody><?PHP  
            }
            else if($process['c']=='4')
            {
                ?><tbody class="infos">  
                <th>Destino:</th>

            <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>">
                <?PHP
                
                echo"Advertencia: la catapulta <b>SOLO</b> dispara en un ataque normal (¡no dispara en saqueos!)";
                ?>
                </td>

                <?PHP
            }
            ?>

        <?php } ?>



             <tbody class="infos">
    <tr>

   <th>Llegada</th>

            

            <?php
            $speeds = array();
			$bootsBonus = 0;
                //find slowest unit.
                for($i=1;$i<=11;$i++){
                    if (isset($process['t'.$i])){
                        if( $process['t'.$i] != '' && $process['t'.$i] > 0){
                            if ($i<11) {
								$speeds[] = ${'u'.(($session->tribe-1)*10+$i)}['speed'];
							}else{
								$herodetail = $database->getHeroData($session->uid);
								$speeds[] = $herodetail['speed'];
								$bootsBonus = heroEquippedBootsSpeedBonus($database, $session->uid);
								$travelBonus = heroEquippedTravelSpeedBonus($database,$session->uid,$village->wid,$process['0'],false);
							}
                        }
                    }
                }

                // Esta es la vista previa de la llegada y tiene que dar exactamente lo mismo
                // que Units::sendUnits(), que es quien crea el movimiento: las botas de los
                // titanes se aplican en los dos o la pantalla anuncia una hora y llega a otra.
                $time = $generator->procDistanceTime($from,$to,min($speeds),1,$bootsBonus,isset($travelBonus) ? $travelBonus : 0,artefactTroopSpeedFactor($database,$session->uid,$village->wid));

            ?>

            

            <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>">

            <div class="in">En <?php echo $generator->getTimeFormat($time); ?> hora</div>

            <div class="at"><span id="tp2"> <?php echo date("H:i:s",time()+$time)?></span> Hora</div>

            </td>

        </tr>

    </tbody>

</table>

<input name="timestamp" value="<?php echo $confirmationTimestamp; ?>" type="hidden">

<input name="timestamp_checksum" value="<?php echo $ckey; ?>" type="hidden"> 

<input name="ckey" value="<?php echo $id; ?>" type="hidden"> 

<input name="id" value="39" type="hidden"> 

<input name="a" value="533374" type="hidden">
<input name="c" value="3" type="hidden">

<?php
$attacker = $database->getUserField($session->uid,'alliance',0);
$defender = $database->getUserField($process['2'],'alliance',0);
// c: 1 = explorar, 2 = refuerzo, 3 = ataque normal, 4 = saqueo.
// El refuerzo no es hostil, así que no dispara ninguna de las advertencias.
$hostile = $process['c'] != 2;
		if($hostile && $attacker!=0 && $attacker==$defender){
			$hostileAction = ($process['c'] == 1) ? "explorar a" : "atacar a";
			echo "<div class=\"alert\">¡Advertencia! ¿Estás seguro de que quieres ".$hostileAction." este jugador de tu alianza?</div>";
		}

    if($hostile && $database->hasBeginnerProtection($process['0'])==1) {
        echo"<div class=\"alert\"><b>No se puede atacar, está bajo protección de principiante.</b></div>";
    } else {
?> 
<button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">confirmar</div></div></button>
<?php
if($hostile && $database->hasBeginnerProtection($village->wid)==1 && $process['2']!=2 && $process['2']!=3) {
		$del_protect = 1;
?>
		</br></br><span style="color: #DD0000"><b>Advertencia:</b> si atacas a este jugador perderás tu protección de principiante.</span>
<?php }} ?>
<input name="del_protect" value="<?php echo $del_protect; ?>" type="hidden"> 
</form>
