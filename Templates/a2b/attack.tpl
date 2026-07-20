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

$id = $database->addA2b($ckey,time(),$process['0'],$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11,$process['c']);

if ($process['c']==1){
$actionType = "Scouting ";
}else if ($process['c']==2){
$actionType = "Reinforcement to ";
}elseif ($process['c']==3){
$actionType = "Normal Attack to ";
}else{
$actionType = "Raid to ";
}


$uid = $session->uid;

$tribe = $session->tribe;
$start = ($tribe-1)*10+1;
$end = ($tribe*10);
?>

<h1><?php echo $actionType." ".$process[1]; ?></h1>            

<form method="post" action="a2b.php">

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
                

        <?php if(isset($kata) AND $process['c']!='2'){?>

            <?php if($process['c']=='3'){ ?><tbody class="cata">
                <tr>
                    <th>Catapultas</th>
                    <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>">
                    
                        <select name="ctar1" class="dropdown">
                            <option value="0">Aleatorio</option>
                            <?php if($building->getTypeLevel(16) >= 5) { ?>
                			<optgroup label="Recursos">
                                <option value="1">Aserradero</option>
                                <option value="2">Excavación de barro</option>
                                <option value="3">Mina de hierro</option>
                                <option value="4">Campo de cereal</option>
                                <option value="5">Aserradero</option>
                                <option value="6">Fábrica de ladrillos</option>
                                <option value="7">Fundición de hierro</option>
                                <option value="8">Molino</option>
                                <option value="9">Panadería</option>
                            </optgroup>
                            <?php } ?>
                            
                            <?php if($building->getTypeLevel(16) >= 3) { ?>
                            <optgroup label="Infraestructura">
                                <option value="10">almacén</option>
                                <option value="11">granero</option>
                                <?php if($building->getTypeLevel(16) >= 10) { ?>
                                <option value="15">edificio principal</option>
                                <option value="17">mercado</option>
                                <option value="18">embajada</option>
                                <option value="24">ayuntamiento</option>
                                <option value="25">Residencia</option>
                                <option value="26">Palacio</option>
                                <option value="27">tesorería</option>
                                <option value="28">Centro comercial</option>
                                <?php } ?>
                                <option value="38">Gran almacén</option>
                                <option value="39">Gran granero</option>
                            </optgroup>
                            <?php } ?>
                            <?php if($building->getTypeLevel(16) >= 10) { ?>
                            <optgroup label="Militar">
                                <option value="12">herrería</option>
                                <option value="13">Armería</option>
                                <option value="14">Plaza de torneos</option>
                                <option value="16">Plaza de reuniones</option>
 								<option value="19">cuartel</option>
 								<option value="20">establo</option>
 								<option value="21">taller</option>
                                <option value="22">Academia</option>
 								<option value="29">Gran cuartel</option>
  								<option value="30">Gran establo</option>
  								<option value="37">Mansión del héroe</option>
                            </optgroup>
                            <?php } ?>
                        </select>

            <?php if($building->getTypeLevel(16) == 20) { ?>
                     <select name="ctar2" class="dropdown">
                <option value="0">-</option>
                <option value="0">Aleatorio</option>
                            <?php if($building->getTypeLevel(16) >= 5) { ?>
                            <optgroup label="Recursos">
                                <option value="1">aserradero</option>
                                <option value="2">excavación de barro</option>
                                <option value="3">mina de hierro</option>
                                <option value="4">campo de cereal</option>
                                <option value="5">aserradero</option>
                                <option value="6">fábrica de ladrillos</option>
                                <option value="7">fundición de hierro</option>
                                <option value="8">molino</option>
                                <option value="9">panadería</option>
                            </optgroup>
                            <?php } ?>
                            
                            <?php if($building->getTypeLevel(16) >= 3) { ?>
                            <optgroup label="Infraestructura">
                                <option value="10">almacén</option>
                                <option value="11">granero</option>
                                <?php if($building->getTypeLevel(16) >= 10) { ?>
                                <option value="15">edificio principal</option>
                                <option value="17">mercado</option>
                                <option value="18">Embajada</option>
                                <option value="24">ayuntamiento</option>
                                <option value="25">residencia</option>
                                <option value="26">Palacio</option>
                                <option value="27">tesorería</option>
                                <option value="28">Centro comercial</option>
                                <?php } ?>
                                <option value="38">Gran almacén</option>
                                <option value="39">Gran granero</option>
                            </optgroup>
                            <?php } ?>
                            <?php if($building->getTypeLevel(16) >= 10) { ?>
                            <optgroup label="Militar">
                                <option value="12">herrería</option>
                                <option value="13">Armería</option>
                                <option value="14">Plaza de torneos</option>
                                <option value="16">Plaza de reuniones</option>
                                <option value="19">cuartel</option>
                                <option value="20">establo</option>
                                <option value="21">taller</option>
                                <option value="22">academia</option>
                                <option value="29">Gran cuartel</option>
                                <option value="30">Gran establo</option>
                                <option value="37">Mansión del héroe</option>
                            </optgroup>
                            <?php } ?>
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
            $scout = 1;
                //find slowest unit.
                for($i=1;$i<=11;$i++){
                    if (isset($process['t'.$i])){
                        if( $process['t'.$i] != '' && $process['t'.$i] > 0){
                            if ($i<11) {
								$speeds[] = ${'u'.(($session->tribe-1)*10+$i)}['speed'];
							}else{
								$herodetail = $database->getHeroData($session->uid);
								$speeds[] = $herodetail['speed'];
							}

                            if($i != 4){
                                $scout = 0;
                            }
                        }
                    }
                }
            

            if($scout){
                $process['c'] = 1;
			}
                
                $time = $generator->procDistanceTime($from,$to,min($speeds),1);

            ?>

            

            <td colspan="<?php if($t11 > 0){ echo"11"; }else{ echo"10"; } ?>">

            <div class="in">En <?php echo $generator->getTimeFormat($time); ?> hora</div>

            <div class="at"><span id="tp2"> <?php echo date("H:i:s",time()+$time)?></span> Hora</div>

            </td>

        </tr>

    </tbody>

</table>

<input name="timestamp" value="<?php echo time(); ?>" type="hidden"> 

<input name="timestamp_checksum" value="<?php echo $ckey; ?>" type="hidden"> 

<input name="ckey" value="<?php echo $id; ?>" type="hidden"> 

<input name="id" value="39" type="hidden"> 

<input name="a" value="533374" type="hidden">
<input name="c" value="3" type="hidden">

<?php
$attacker = $database->getUserField($session->uid,'alliance',0);
$defender = $database->getUserField($process['2'],'alliance',0);
		if($attacker!=0 && $attacker==$defender){
			echo "<div class=\"alert\">¡Advertencia! ¿Estás seguro de que quieres atacar a este jugador?</div>";
		}

    if($database->hasBeginnerProtection($process['0'])==1) {  
        echo"<div class=\"alert\"><b>No se puede atacar, está bajo protección de principiante.</b></div>"; 
    } else { 
?> 
<button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">confirmar</div></div></button>
<?php
if($database->hasBeginnerProtection($village->wid)==1 && $process['2']!=2 && $process['2']!=3) { 
		$del_protect = 1;
?>
		</br></br><span style="color: #DD0000"><b>Advertencia:</b> si atacas a este jugador perderás tu protección de principiante.</span>
<?php }} ?>
<input name="del_protect" value="<?php echo $del_protect; ?>" type="hidden"> 
</form>
