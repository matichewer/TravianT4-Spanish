<?php

$units = $database->getMovement("34",$village->wid,1);
$total_for = count($units);

// ¿Esta aldea ve el tipo de las tropas que vienen? Es la segunda mitad de los ojos del
// águila (artefacto tipo 3), y se resuelve una vez para toda la pantalla en vez de una
// vez por ataque: el conjunto activo de la cuenta es el mismo para todos.
$incomingTroopTypesVisible = is_object($database)
	&& method_exists($database,'hasActiveArtefactEffect')
	&& $database->hasActiveArtefactEffect((int)$village->wid,(int)$session->uid,ARTEFACT_EAGLE);

for($y=0;$y < $total_for;$y++){
$timer += 1;
if ($units[$y]['sort_type']==3){
	if ($units[$y]['attack_type']==3){
		$actionType = "Ataque a la aldea ";
	} else if ($units[$y]['attack_type']==4){
		$actionType = "Saqueo a la aldea ";
	} else if ($units[$y]['attack_type']==2){
		$actionType = "Refuerzo desde la aldea ";
	}

	if($units[$y]['attack_type'] != 1){
		// Los animales capturados con jaulas llegan como refuerzo de la naturaleza (from = 0),
		// sin aldea de origen ni coordenadas.
		$isNature = ((int)$units[$y]['from'] === 0);
		if($isNature){
			$actionType = "Animales capturados para ";
		}
		echo "<table class=\"troop_details ";

        if($units[$y]['attack_type'] != 2){ echo "inRaid"; } else { echo "inReturn"; }
        	echo"\" cellpadding=\"1\" cellspacing=\"1\"><thead><tr><td class=\"role\">";
        	if($isNature){
        		echo TRIBE4;
        	}else{
        		echo "<a href=\"karte.php?d=".$units[$y]['from']."&c=".$generator->getMapCheck($units[$y]['from'])."\">".$database->getVillageField($units[$y]['from'],"name")."</a>";
        	}
        	echo "</td>
                  <td colspan=\"11\" class=\"troopHeadline\">";
                  echo "<a href=\"spieler.php?uid=".$database->getVillageField($units[$y]['to'],"owner")."\">";
                  echo $actionType ." ". $village->vname;
                  echo "</a></td></tr></thead><tbody class=\"units\">";
                  $tribe = $isNature ? 4 : $database->getUserField($database->getVillageField($units[$y]['from'],"owner"),"tribe",0);
                  if($tribe < 1 || $tribe > 5){ $tribe = 4; }
                  $start = ($tribe-1)*10+1;
                  $end = ($tribe*10);
                  echo "<tr><th class=\"coords\">";
                  if(!$isNature){
                  	$coor = $database->getCoor($units[$y]['from']);
                  	echo "<span class=\"coordinates coordinatesAligned\">
                    <span class=\"coordinateY\">(".$coor['x']."</span>
                    <span class=\"coordinatePipe\">|</span>
                    <span class=\"coordinateX\">".$coor['y'].")</span>
                    </span>";
                  }
                  echo "<span class=\"clear\">".($isNature ? "" : "a")."</span></th>";
                  for($i=$start;$i<=$end;$i++) {
                    echo "<td><a href=\"#\" onclick=\"return Travian.Game.iPopup($i,1);\"><img src=\"img/x.gif\" class=\"unit u$i\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></a></td>";
                  }
                  echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".$technology->getUnitName(52)."\" alt=\"".$technology->getUnitName(52)."\" /></td>";	
                  echo "</tr><tr><th>Tropas</th>";
                  
                  

if($isNature){
	// Son tus propios animales capturados: no hay nada que ocultar.
	for($t=1;$t<=11;$t++){
		if($units[$y]['t'.$t]){
			echo "<td>".$units[$y]['t'.$t]."</td>";
		}else{
			echo "<td class=\"none\">0</td>";
		}
	}
}elseif($incomingTroopTypesVisible){
	// Los ojos del águila: "puedes ver el TIPO de tropas que vienen, pero no cuántas
	// son". O sea que la columna de una unidad que viene queda marcada y la de una que
	// no viene queda en cero — el jugador lee la composición del ataque sin los números.
	//
	// Era la mitad del artefacto que faltaba: el multiplicador de espionaje estaba, pero
	// esto no existía en ningún lado.
	for($t=1;$t<=11;$t++){
		if($units[$y]['t'.$t]){
			echo "<td class=\"none\" title=\"Los ojos del águila revelan el tipo de tropa, no la cantidad\">?</td>";
		}else{
			echo "<td class=\"none\">0</td>";
		}
	}
}else{
	// Sin artefacto no se sabe nada de la composición. Acá había dos ramas —una para
	// punto de reunión 5 o más y otra para el resto— que imprimían exactamente lo mismo:
	// la de arriba tenía además un `if($t!=7 or $t!=8 or $t!=8)` que es siempre cierto.
	for($t=1;$t<=11;$t++){
		echo "<td class=\"none\">?</td>";
	}
}
                  
                  echo "</tr></tbody>";
                  echo '
                  <tbody class="infos">
									<tr>
										<th>Llegada</th>
										<td colspan="11">
										<div class="in small"><span id=timer'.$timer.'>'.$generator->getTimeFormat($units[$y]['endtime']-time()).'</span> hs</div>';
										    $datetime = $generator->procMtime($units[$y]['endtime']);
										    echo "<div class=\"at small\">";
										    echo " ".$datetime[1]." hs</div>
											</div>
										</td>
									</tr>
								</tbody>";
		echo "</table>";
        
	} 
}elseif ($units[$y]['sort_type']==4){
	if ($units[$y]['attack_type']==1){
		$actionType = "Regreso a ";
	} else if ($units[$y]['attack_type']==2){
		$actionType = "Regreso a ";
	} else if ($units[$y]['attack_type']==3){
		$actionType = "Regreso a ";
	} else if ($units[$y]['attack_type']==4){
		$actionType = "Regreso a ";
	}

$originWref = (int)$units[$y]['from'];
$destinationWref = (int)$units[$y]['to'];
$originIsOasis = $database->isVillageOases($originWref);
$origin = $originIsOasis ? $database->getOMInfo($originWref) : $database->getMInfo($originWref);
$destination = $database->getMInfo($destinationWref);
$originName = htmlspecialchars((string)$origin['name'], ENT_QUOTES, 'UTF-8');
$destinationName = htmlspecialchars((string)$destination['name'], ENT_QUOTES, 'UTF-8');
?>
<table class="troop_details inReturn" cellpadding="1" cellspacing="1">            
	<thead>
		<tr>
			<td class="role"><a href="karte.php?d=<?php echo $originWref."&c=".$generator->getMapCheck($originWref); ?>"><?php echo $originName; ?></a></td>
            <?php if($units[$y]['t11']!=0){ $colspan = '11'; }else{ $colspan = '10'; } ?>
			<td colspan="<?php echo $colspan; ?>" class="troopHeadline"><a href="karte.php?d=<?php echo $destinationWref."&c=".$generator->getMapCheck($destinationWref); ?>"><?php echo $actionType ." ". $destinationName; ?></a></td>
		</tr>
	</thead>
	<tbody class="units">
			<?php
				$tribe = $session->tribe;
                  $start = ($tribe-1)*10+1;
                  $end = ($tribe*10);
                  $coor = $database->getCoor($originWref);
                  echo "<tr><th class=\"coords\">
					<a class=\"movementOriginCoordinates\" href=\"karte.php?d=".$originWref."&amp;c=".$generator->getMapCheck($originWref)."\" title=\"Ver origen en el mapa\">
					<span class=\"coordinates coordinatesAligned\">
                    <span class=\"coordinateY\">(".$coor['x']."</span>
                    <span class=\"coordinatePipe\">|</span>
                    <span class=\"coordinateX\">".$coor['y'].")</span>
                    </span>
					</a>
                    <span class=\"clear\"></span></th>";
                  for($i=$start;$i<=($end);$i++) {
                    echo "<td><a href=\"#\" onclick=\"return Travian.Game.iPopup($i,1);\"><img src=\"img/x.gif\" class=\"unit u$i\" title=\"".$technology->getUnitName($i)."\" alt=\"".$technology->getUnitName($i)."\" /></a></td>";
                  }
                  if($units[$y]['t11']!=0){
                  echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".$technology->getUnitName(52)."\" alt=\"".$technology->getUnitName(52)."\" /></td>";
                  }	
			?>
			</tr>
 <tr><th>Tropas</th>
            <?php
            for($i=1;$i<=10;$i++) {
            	if($units[$y]['t'.$i] == 0) {
                	echo "<td class=\"none\">";
                }else {
                echo "<td>";
                }
                echo $units[$y]['t'.$i]."</td>";
            }
            if($units[$y]['t11']!=0){
            	if($units[$y]['t11'] == 0) {
                	echo "<td class=\"none\">";
                }else {
                	echo "<td>";
                }
                echo $units[$y]['t11']."</td>";
            }
            ?>
           </tr>

           </tbody>
		<tbody class="infos">
        <?php
        if($units[$y]['attack_type']==3 || $units[$y]['attack_type']==4){        
        $dataarray = explode(",",$units[$y]['data']);
        
        ?>
    <tr><th>Botín</th>
    <td colspan="<?php echo $colspan; ?>">
    <div class="res">
    <span class="resource" title="Madera"><img class="r1" src="img/x.gif" alt="Madera"><?php echo $dataarray['0']; ?></span>
    <span class="resource" title="Barro"><img class="r2" src="img/x.gif" alt="Barro"><?php echo $dataarray['1']; ?></span>
    <span class="resource" title="Hierro"><img class="r3" src="img/x.gif" alt="Hierro"><?php echo $dataarray['2']; ?></span>
    <span class="resource" title="Cereal"><img class="r4" src="img/x.gif" alt="Cereal"><?php echo $dataarray['3']; ?></span>
    </div>
    <div class="carry">
    <?php
    if ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3] == 0) {
    echo"<img title=\"";
    echo ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3])."/".$dataarray[4];
    echo"\" src=\"img/x.gif\" class=\"carry empty\">";
	} elseif ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3] != $dataarray[4]) {
    echo "<img title=\"";
    echo ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3])."/".$dataarray[4];
    echo"\" src=\"img/x.gif\" class=\"carry half\">";
    } else {
    echo"<img title=\"";
    echo ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3])."/".$dataarray[4];
    echo"\" src=\"img/x.gif\" class=\"carry full\">";
    }

    ?>
    <?php echo ($dataarray[0]+$dataarray[1]+$dataarray[2]+$dataarray[3])."/".$dataarray[4]; ?>
    </td>
    </tr>
    <?php } ?>
			<tr>
				<th>Llegada</th>
				<td colspan="<?php echo $colspan; ?>">
				<?php
                
				    echo "<div class=\"in small\"><span id=timer".$timer.">".$generator->getTimeFormat($units[$y]['endtime']-time())."</span> hs</div>";
				    $datetime = $generator->procMtime($units[$y]['endtime']);
				    echo "<div class=\"at small\">";
				    echo " ".$datetime[1]."</div>";
    		?>
					</div>
				</td>
			</tr>
		</tbody>
</table>
<?php	

	}
}


?>
