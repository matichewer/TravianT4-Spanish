<?php

/*
|--------------------------------------------------------------------------
|   PLEASE DO NOT REMOVE THIS COPYRIGHT NOTICE!
|--------------------------------------------------------------------------
|
|   Developed by:   Dzoki < dzoki.travian@gmail.com >
|
|   This script is property of TravianX Project. You are allowed to change
|   its source and release it, but you have no rights to remove copyright
|   notices.
|
|   TravianX All rights reserved
|
*/


    if(isset($aid)) {
        $aid = $aid;
    } else {
        $aid = $session->alliance;
    }
    $allianceinfo = $database->getAlliance($aid);
echo "<h1>Alianza - ".$allianceinfo['tag']."</h1>";
    include("alli_menu.tpl");
?>


<h4 class="round">Diplomacia</h4>
<form method="post" action="allianz.php">
<input type="hidden" name="a" value="6">
<input type="hidden" name="o" value="6">
<input type="hidden" name="s" value="5">
		<div class="option diplomacy">
			<table cellpadding="1" cellspacing="1" class="option transparent">
				<tbody>
					<tr>
						<th>
							Nombre:						</th>
						<td>
							<input class="ally text" type="text" name="a_name" maxlength="8">
						</td>
					</tr>

					<tr>
						<td>
						</td>
						<td>
							<input class="radio" type="radio" id="dipl1" name="dipl" value="1">
							<label for="dipl1">Ofrecer confederación</label>
						</td>
					</tr>
					<tr>
						<td>
						</td>
						<td>
							<input class="radio" type="radio" id="dipl2" name="dipl" value="2">
							<label for="dipl2">Ofrecer pacto de no agresión</label>
						</td>
					</tr>
					<tr>
						<td>
						</td>
						<td>
							<input class="radio" type="radio" id="dipl3" name="dipl" value="3">
                            <label for="dipl3">Declarar la guerra</label>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="option">
				<input type="hidden" name="a" value="6">
				<button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Guardar</div></div></button><p class="error"></p>
                </p>
		</div>
</form>
		<div class="boxes boxesColor gray infos"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">			<div class="title">
				Aviso			</div>
			<div class="text">
				Se considera buena etiqueta diplomática contactar a la otra alianza antes de enviarle una oferta de confederación o pacto de no agresión.			</div>
			</div>
				</div>		<div class="clear"></div>


<h4 class="round">Ofertas propias</h4>
<table cellpadding="1" cellspacing="1" class="option own transparent">
	<tbody>
        <tbody>
        <?php
        $alliance = $session->alliance;

        if(count($database->diplomacyOwnOffers($alliance))){
            foreach($database->diplomacyOwnOffers($alliance) as $key => $value){
                if($value['type'] == 1){
                    $type = "Confederación con";
                } else if($value['type'] == 2){
                    $type = "Pacto de no agresión con";
                } else if($value['type'] == 3){
                    $type = "Guerra con";
                }
                echo "<tr><td class=\"abo\"><form method=\"post\" action=\"allianz.php\"><input type=\"hidden\" name=\"o\" value=\"101\"><input type=\"hidden\" name=\"id\" value=\"".$value['id']."\"><button type=\"submit\" value=\"del\" class=\"icon\"><img src=\"img/x.gif\" class=\"del\" alt=\"Cancel\"></button></form></td>";
                echo '<td><a href="allianz.php?aid='.$value['alli2'].'">'.$type.' '.$database->getAllianceName($value['alli2']).'</a></td></tr>';
            }
        } else {
            echo '<tr><td class="noData">Ninguna</td></tr>';
        }
        ?>
        </tbody>
    </table>

<h4 class="round">Ofertas externas</h4>
<table width="100px" border="0" class="option foreign transparent">
        <tbody>
             <?php
        unset($type);
        $alliance = $session->alliance;

        if(count($database->diplomacyInviteCheck($alliance))){
            foreach($database->diplomacyInviteCheck($alliance) as $key => $row){
                if($row['type'] == 1){
                    $type = "Confederación con";
                } else if($row['type'] == 2){
                    $type = "Pacto de no agresión con";
                } else if($row['type'] == 3){
                    $type = "Guerra con";
                }

                echo '<td><form method="post" action="allianz.php"><input type="hidden" name="o" value="102"><input type="hidden" name="id" value="'.$row['id'].'"><input type="hidden" name="alli1" value="'.$row['alli1'].'"><button type="submit" value="del" class="icon"><img src="img/x.gif" class="del" alt="Rechazar"></button></form></td>';
                echo '<td><a href="allianz.php?aid='.$row['alli1'].'">'.$type.' '.$database->getAllianceName($row['alli1']).'</a></td>';

                echo '<td><form method="post" action="allianz.php"><input type="hidden" name="o" value="103"><input type="hidden" name="id" value="'.$row['id'].'"><input type="hidden" name="alli2" value="'.$row['alli2'].'"><button type="submit" value="ok" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Aceptar</div></div></button></form></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>';

            }
        } else {
            echo '<tr><td class="noData">None</td></tr>';
        }
        ?>
        </tbody>
    </table>


    <h4 class="round">Relaciones actuales</h4>
    <table cellpadding="1" cellspacing="1" class="option existing transparent">
		<tbody>

             <?php
        unset($type);
        unset($row);
        $alliance = $session->alliance;

        if(count($database->diplomacyExistingRelationships($alliance))){
            foreach($database->diplomacyExistingRelationships($alliance) as $key => $row){
                if($row['type'] == 1){
                    $type = "Confederación con";
                } else if($row['type'] == 2){
                    $type = "Pacto de no agresión con";
                } else if($row['type'] == 3){
                    $type = "Guerra con";
                }
                echo '<tr><td class="abo"><form method="post" action="allianz.php"><input type="hidden" name="o" value="104"><input type="hidden" name="id" value="'.$row['id'].'"><input type="hidden" name="alli2" value="'.$row['alli2'].'"><button type="submit" value="del" class="icon"><img src="img/x.gif" class="del" alt="Cancelar"></button></form></td>';
                echo '<td>'.$type.' <a href="allianz.php?aid='.$row['alli1'].'">'.$database->getAllianceName($row['alli1']).'</a></td></tr>';
            }
        } elseif(count($database->diplomacyExistingRelationships2($alliance))){
            foreach($database->diplomacyExistingRelationships2($alliance) as $key => $row){
                if($row['type'] == 1){
                    $type = "Confederación con";
                } else if($row['type'] == 2){
                    $type = "Pacto de no agresión con";
                } else if($row['type'] == 3){
                    $type = "Guerra con";
                }
                echo '<tr><td class="abo"><form method="post" action="allianz.php"><input type="hidden" name="o" value="104"><input type="hidden" name="id" value="'.$row['id'].'"><input type="hidden" name="alli2" value="'.$row['alli1'].'"><button type="submit" value="del" class="icon"><img src="img/x.gif" class="del" alt="Cancelar"></button></form></td>';
                echo '<td>'.$type.' <a href="allianz.php?aid='.$row['alli2'].'">'.$database->getAllianceName($row['alli2']).'</a></td></tr>';
            }
        }else {
            echo '<tr><td class="noData">Ninguna</td></tr>';
        }

        ?>


        </tbody>
    </table>
<div class="boxes boxesColor gray infos"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">		<div class="title">
			Pista		</div>
		<div class="text">
			Para mostrar automáticamente las relaciones de la alianza, escribe <span class="e">[diplomatie]</span> en el perfil, o bien <span class="e">[ally]</span>, <span class="e">[nap]</span> y <span class="e">[war]</span> por separado.
  		</div>
		</div>
				</div>
