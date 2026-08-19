<?php
include("GameEngine/Session.php");
    if($y < $yy) {
    $y = $y + (($yy - $y) /2);
    }
    else {
    $y = $yy + (($y - $yy) /2);
    }
    $x = $x - (($x - $xx) / 2);
    $x = ($x < -WORLD_MAX)? $x+WORLD_MAX*2+1 : $x;
    $x = ($x > WORLD_MAX)? $x-WORLD_MAX*2-1 : $x;
    $y = ($y < -WORLD_MAX)? $y+WORLD_MAX*2+1 : $y;
    $y = ($y > WORLD_MAX)? $y-WORLD_MAX*2-1 : $y;
    $xm3 = ($x-3) < -WORLD_MAX? $x+WORLD_MAX+WORLD_MAX-2 : $x-3;
    $xm2 = ($x-2) < -WORLD_MAX? $x+WORLD_MAX+WORLD_MAX-1 : $x-2;
    $xm1 = ($x-1) < -WORLD_MAX? $x+WORLD_MAX+WORLD_MAX : $x-1;
    $xp1 = ($x+1) > WORLD_MAX? $x-WORLD_MAX-WORLD_MAX : $x+1;
    $xp2 = ($x+2) > WORLD_MAX? $x-WORLD_MAX-WORLD_MAX+1 : $x+2;
    $xp3 = ($x+3) > WORLD_MAX? $x-WORLD_MAX-WORLD_MAX+2: $x+3;
    $ym3 = ($y-3) < -WORLD_MAX? $y+WORLD_MAX+WORLD_MAX-2 : $y-3;
    $ym2 = ($y-2) < -WORLD_MAX? $y+WORLD_MAX+WORLD_MAX-1 : $y-2;
    $ym1 = ($y-1) < -WORLD_MAX? $y+WORLD_MAX+WORLD_MAX : $y-1;
    $yp1 = ($y+1) > WORLD_MAX? $y-WORLD_MAX-WORLD_MAX : $y+1;
    $yp2 = ($y+2) > WORLD_MAX? $y-WORLD_MAX-WORLD_MAX+1 : $y+2;
    $yp3 = ($y+3) > WORLD_MAX? $y-WORLD_MAX-WORLD_MAX+2: $y+3;
    $xarray = array($xm3,$xm2,$xm1,$x,$xp1,$xp2,$xp3);
	$yarray = array($ym3,$ym2,$ym1,$y,$yp1,$yp2,$yp3);
	$maparray = array();
$xcount = 0;

// La diplomacia de la alianza de quien mira, resuelta una sola vez para toda la vista.
// Antes estos tres arreglos se creaban vacios dentro del bucle, casilla por casilla, asi que
// las ramas de aliado y enemigo del sprite eran inalcanzables por construccion: un aliado se
// veia igual que un desconocido. Se calcula aca, fuera del bucle, porque el mapa ya hace una
// consulta por casilla y esto seria una mas por cada una.
include_once(dirname(__DIR__, 2)."/GameEngine/Diplomacy.php");
$friendarray = friendlyAlliances($session->alliance);
$enemyarray = hostileAlliances($session->alliance);
$neutralarray = array();     // el gpack no trae arte neutral; ver mapDiplomacyCss()

for($i=0;$i<=6;$i++) {
if($xcount != 7) {
array_push($maparray,$database->getMInfo($generator->getBaseID($xarray[$xcount],$yarray[$i])));
if($i==6) {
$i = -1;
$xcount +=1;
}
}
}
			$yrow = 0;
$regcount = 0;
header("Content-Type: application/json;");
		echo "[[";
for($h=0;$h<=6;$h++) {
	if($yrow!=7) {
    if($maparray[$regcount]['occupied'] == 1 && $maparray[$regcount]['fieldtype'] > 0) {
	$targetalliance = $database->getUserField($maparray[$regcount]['owner'],"alliance",0);
	// La tribu del dueño decide el sufijo del sprite. Sin ella la clase quedaba en 'b34',
	// que no existe en el gpack activo, y TODA aldea ajena salia como casilla vacia.
	$tribe = $database->getUserField($maparray[$regcount]['owner'],"tribe",0);
    // Los tres arreglos se calculan UNA vez antes del bucle (ver arriba): antes se
    // reinicializaban vacios en cada casilla, asi que la diplomacia nunca podia influir.
    }
   	$image = ($maparray[$regcount]['occupied'] == 1 && $maparray[$regcount]['fieldtype'] > 0)? (($maparray[$regcount]['owner'] == $session->uid)? ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b30-'.$tribe: 'b20-'.$tribe :'b10-'.$tribe : 'b00-'.$tribe) : (($targetalliance != 0)? (in_array($targetalliance,$friendarray)? ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b31-'.$tribe: 'b21-'.$tribe :'b11-'.$tribe : 'b01-'.$tribe) : (in_array($targetalliance,$enemyarray)? ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b32-'.$tribe: 'b22-'.$tribe :'b12-'.$tribe : 'b02-'.$tribe) : (in_array($targetalliance,$neutralarray)? ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b35-'.$tribe: 'b25-'.$tribe :'b15-'.$tribe : 'b05-'.$tribe) : ($targetalliance == $session->alliance? ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b33-'.$tribe: 'b23-'.$tribe :'b13-'.$tribe : 'b03-'.$tribe) : ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))))) : ($maparray[$regcount]['pop']>=100? $maparray[$regcount]['pop']>= 250?$maparray[$regcount]['pop']>=500? 'b34-'.$tribe: 'b24-'.$tribe :'b14-'.$tribe : 'b04-'.$tribe))) : $maparray[$regcount]['image'];
		$text = "[".$maparray[$regcount]['x'].",".$maparray[$regcount]['y'].",".$maparray[$regcount]['fieldtype'].",".$maparray[$regcount]['oasistype'].",\"d=".$maparray[$regcount]['id']."&c=".$generator->getMapCheck($maparray[$regcount]['id'])."\",\"".$image."\"";
		if($maparray[$regcount]['occupied']) {
			if($maparray[$regcount]['fieldtype'] != 0) {
			$text.= ",\"".$maparray[$regcount]['name']."\",\"".$database->getUserField($maparray[$regcount]['owner'],'username',0)."\",\"".$maparray[$regcount]['pop']."\",\"".$database->getUserAlliance($maparray[$regcount]['owner'])."\",\"".$database->getUserField($maparray[$regcount]['owner'],'tribe',0)."\"]";
			}
			else {
				$oasisinfo = $database->getOasisInfo($maparray[$regcount]['id']);
				$oowner = $database->getVillageField($oasisinfo['conqured'],"owner");
				$text.= ",\"\",\"".$database->getUserField($oowner,'username',0)."\",\"-\",\"".$database->getUserAlliance($oowner)."\",\"".$database->getUserField($oowner,'tribe',0)."\"]";
			}
		}
		else {
			$text .= "]";
		}
		echo $text;
		if($h == 6 && $yrow !=6) {
			$h = -1;
			$yrow +=1;
			echo "],[";
		}
		else {
			if($yrow == 6 && $h == 6) {
				echo "]]";
			}
			else {
			echo ",";
			}
		}
		$regcount += 1;
	}

}
?>
