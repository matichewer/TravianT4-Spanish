<?php

    $MyGold = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE `id`='".$session->uid."'") or die(mysql_error());
    $golds = mysql_fetch_array($MyGold);

if($golds['b1'] <= time()) {
mysql_query("UPDATE ".TB_PREFIX."users set b1 = '0' where `id`='".$session->uid."'") or die(mysql_error());
}

if($golds['b2'] <= time()) {
mysql_query("UPDATE ".TB_PREFIX."users set b2 = '0' where `id`='".$session->uid."'") or die(mysql_error());
}
if($golds['b3'] <= time()) {
mysql_query("UPDATE ".TB_PREFIX."users set b3 = '0' where `id`='".$session->uid."'") or die(mysql_error());
}

if($golds['b4'] <= time()) {
mysql_query("UPDATE ".TB_PREFIX."users set b4 = '0' where `id`='".$session->uid."'") or die(mysql_error());
}

include("Templates/Plus/pmenu.tpl");
    $MyGold = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE `id`='".$session->uid."'") or die(mysql_error());
    $golds = mysql_fetch_array($MyGold);

        $today = date("mdHi");

if (mysql_num_rows($MyGold)) {
	if($session->gold == 0) {
		echo "<div class=\"boxes boxesColor gray goldBalance\"><div class=\"boxes-tl\"></div><div class=\"boxes-tr\"></div><div class=\"boxes-tc\"></div><div class=\"boxes-ml\"></div><div class=\"boxes-mr\"></div><div class=\"boxes-mc\"></div><div class=\"boxes-bl\"></div><div class=\"boxes-br\"></div><div class=\"boxes-bc\"></div><div class=\"boxes-contents\">¡No tienes oro!</div></div>";
	} else {
		echo "<div class=\"boxes boxesColor gray goldBalance\"><div class=\"boxes-tl\"></div><div class=\"boxes-tr\"></div><div class=\"boxes-tc\"></div><div class=\"boxes-ml\"></div><div class=\"boxes-mr\"></div><div class=\"boxes-mc\"></div><div class=\"boxes-bl\"></div><div class=\"boxes-br\"></div><div class=\"boxes-bc\"></div><div class=\"boxes-contents\">Tienes <b>$session->gold</b>  piezas de oro</div></div>";
	}
}

if($_GET['action']=='FinishBuilding'){
	$golds = $database->getUserArray($session->uid, 1);

    $MyVilId = mysql_query("SELECT * FROM ".TB_PREFIX."bdata WHERE `wid`='".$village->wid."'") or die(mysql_error());
    $uuVilid = mysql_fetch_array($MyVilId);
    $MyVilId2 = mysql_query("SELECT * FROM ".TB_PREFIX."research WHERE `vref`='".$village->wid."'") or die(mysql_error());
    $uuVilid2 = mysql_fetch_array($MyVilId2);

    $buildnum = mysql_num_rows($MyVilId);
    $resnum = mysql_num_rows($MyVilId2);
    // Una demolición en curso también se apura con oro —es la segunda de las tres
    // formas de demoler del oficial—, así que cuenta como obra. Sin esto el botón
    // contestaba "no tenés nada en curso" cuando lo único que corría era un derribo,
    // y el jugador se quedaba esperando el reloj entero.
    $demolitionnum = count((array)$database->getDemolition($village->wid));

    $goldlog = mysql_query("SELECT * FROM ".TB_PREFIX."gold_fin_log") or die(mysql_error());

if($buildnum == 0 && $resnum == 0 && $demolitionnum == 0) {
	$done1 = "No tienes ninguna construcción, investigación ni demolición en curso.";
} else if($session->gold >= Building::FINISH_ALL_GOLD) {

		if($session->access!=BANNED){
		global $automation;
		// El fin de obra es uno solo: Automation::finishVillageConstructionsNow()
		// adelanta el reloj de los trabajos y deja que buildComplete() los cierre.
		// Acá vivía una segunda copia del fin de obra que se había quedado atrás
		// (producción acreditada hacia atrás, almacenes que no crecían, PC inflados
		// y pedidos al constructor maestro terminados sin cobrar los recursos).
		$finished = $automation->finishVillageConstructionsNow($village->wid);
		if($finished['wonder_village']) {
			// No se apura nada y tampoco se cobra el oro.
			$done1 = "En una Aldea de la Maravilla no se puede completar la construcción con oro.";
		} else {
			$database->finishDemolition($village->wid);
			$technology->finishTech();
			$logging->goldFinLog($village->wid);
			$database->modifyGold($session->uid,Building::FINISH_ALL_GOLD,0);

			// Lo que quedaba encolado detrás arranca ahora: se le descuenta el tiempo
			// que la obra apurada se iba a llevar. Sin esto el jugador paga el oro y
			// sigue esperando lo mismo.
			if($finished['finished'] > 0 && $finished['skipped_from'] > 0) {
				$stillbuildingarray = $database->getJobs($village->wid);
				if(count($stillbuildingarray) == 1 && $stillbuildingarray[0]['loopcon'] == 1) {
					$resumed = time() + $stillbuildingarray[0]['timestamp'] - $finished['skipped_from'];
					$q = "UPDATE ".TB_PREFIX."bdata SET loopcon=0,timestamp=".max(time(), (int)$resumed)." WHERE id=".(int)$stillbuildingarray[0]['id'];
					$database->query($q);
				}
			}
			header("Location: plus.php?id=3");
		}
		}else{
		header("Location: banned.php");
		}
} else {
        $done1 = "No tienes suficiente oro";
}

}
 ?>
<!-- TODO - Show Construction and research in progress here? -->
<!-- <h4 class="spacer">Construcción</h4> -->
<br><br>
<?php echo $done1; ?>
<table class="plusFunctions" cellpadding="1" cellspacing="1">
	<thead>

		<tr>
			<td>Descripción</td>
			<td>Duración</td>
			<td>Oro</td>
			<td>Acción</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="desc">
				Cuenta <b><font color="#71D000">Plus</font></b><br><span class="run">
<?php
$datetimep=$golds['plus'];
$datetime1=$golds['b1'];
$datetime2=$golds['b2'];
$datetime3=$golds['b3'];
$datetime4=$golds['b4'];
$datetimeap=$golds['ap'];
$datetimedp=$golds['dp'];
//Retrieve the current date/time
$date2=strtotime("NOW");


	if ($datetimep == 0) {
		print "";
	}elseif ($datetimep <= $date2) {
		mysql_query("UPDATE ".TB_PREFIX."users set plus = '0' where `id`='".$session->uid."'") or die(mysql_error());
 	} else {

$holdtotmin=(($datetimep-$date2)/60);
$holdtothr=(($datetimep-$date2)/3600);
$holdtotday=round(($datetimep-$date2)/86400, 1);
$holdhr=intval($holdtothr-($holdtotday*24));
$holdmr=intval($holdtotmin-(($holdhr*60)+($holdtotday*1440)));

    echo "Te quedan <b>".$holdtotday. "</b> días hasta las ".date('H:i',$golds['plus'])."";
 }
?>
                </span>			</td>
			<td class="dur"><?php if(PLUS_TIME >= 86400){
			echo ''.(PLUS_TIME/86400).' Días';
			} else if(PLUS_TIME < 86400){
			echo ''.(PLUS_TIME/3600).' Días';
			} ?></td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">10</td>
			<td class="act">
<?php
    $MyGold = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE `id`='".$session->uid."'") or die(mysql_error());
    $golds = mysql_fetch_array($MyGold);

if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 9 && $datetimep < $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=8'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}elseif
	($golds['gold'] > 9 && $datetimep > $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=8'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Extender</div></div></button>";

} else {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
    }
}
 ?>
            </td>
		</tr>
  </tbody>
</table>
<table class="plusFunctions" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td>Descripción</td>
			<td>Duración</td>
			<td>Oro</td>
			<td>Acción</td>
		</tr>
	</thead>
	<tbody>
				<tr>
			<td class="desc">
				+<b>25</b>% Madera <img class="r1" src="img/x.gif" alt="<br>
				<span class="run">
<?php

$tl_b1=$golds['b1'];
 if ($tl_b1 < $date2) {
     print "";
 } else {
$holdtotmin1=(($tl_b1-$date2)/60);
$holdtothr1=(($tl_b1-$date2)/3600);
$holdtotday1=round(($tl_b1-$date2)/86400, 1);
$holdhr1=intval($holdtothr1-($holdtotday1*24));
$holdmr1=intval($holdtotmin1-(($holdhr1*60)+($holdtotday1*1440)));
}

 if ($tl_b1 < $date2) {
     print " ";
 } else {
echo "    <br>Te quedan <b>".$holdtotday1. "</b> días hasta las   ".date('H:i',$golds['b1'])."";

 }
?>

                </span>			</td>
			<td class="dur"><?php if(PLUS_PRODUCTION >= 86400){
			echo ''.(PLUS_PRODUCTION/86400).' Días';
			} else if(PLUS_PRODUCTION < 86400){
			echo ''.(PLUS_PRODUCTION/3600).' horas';
			} ?></td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">5</td>
			<td class="act">
<?php

if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 4 && $tl_b1 < $date2) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=9'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}elseif
	($golds['gold'] > 4 && $datetime1 > $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=9'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Extender</div></div></button>";
} else {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
    }
}
?>
            </td>
		</tr>
			<tr>
			<td class="desc">
				+<b>25</b>% Barro <img class="r2" src="img/x.gif" alt="<br>
				<span class="run">
                <?php

$tl_b2=$golds['b2'];
 if ($tl_b2 < $date2) {
     print " ";
 } else {
$holdtotmin2=(($tl_b2-$date2)/60);
$holdtothr2=(($tl_b2-$date2)/3600);
$holdtotday2=round(($tl_b2-$date2)/86400, 1);
$holdhr2=intval($holdtothr2-($holdtotday2*24));
$holdmr2=intval($holdtotmin2-(($holdhr2*60)+($holdtotday2*1440)));
}

 if ($tl_b2 < $date2) {
     print " ";
 } else {

echo "<br> Te quedan <b>".$holdtotday2. "</b> días hasta las ".date('H:i',$golds['b2'])."";

 }
?>

                </span>			</td>
			<td class="dur"><?php if(PLUS_PRODUCTION >= 86400){
			echo ''.(PLUS_PRODUCTION/86400).' Días';
			} else if(PLUS_PRODUCTION < 86400){
			echo ''.(PLUS_PRODUCTION/3600).' horas';
			} ?></td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">5</td>
			<td class="act">
<?php

if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 4 && $tl_b2 < $date2) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=10'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}elseif
	($golds['gold'] > 4 && $tl_b2 > $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=10'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Extender</div></div></button>";
} else {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
    }
    }
 ?>

            </td>
		</tr>
			<tr>
			<td class="desc">
				+<b>25</b>% Hierro <img class="r3" src="img/x.gif" alt="<br>
				<span class="run">
<?php

$tl_b3=$golds['b3'];
 if ($tl_b3 < $date2) {
     print " ";
 } else {
$holdtotmin3=(($tl_b3-$date2)/60);
$holdtothr3=(($tl_b3-$date2)/3600);
$holdtotday3=round(($tl_b3-$date2)/86400, 1);
$holdhr3=intval($holdtothr3-($holdtotday3*24));
$holdmr3=intval($holdtotmin3-(($holdhr3*60)+($holdtotday3*1440)));
}

 if ($tl_b3 < $date2) {
     print " ";
 } else {
echo " <br> Te quedan <b>".$holdtotday3. "</b> días hasta las ".date('H:i',$golds['b3'])."";

 }
?>

                </span>			</td>
			<td class="dur"><?php if(PLUS_PRODUCTION >= 86400){
			echo ''.(PLUS_PRODUCTION/86400).' Días';
			} else if(PLUS_PRODUCTION < 86400){
			echo ''.(PLUS_PRODUCTION/3600).' horas';
			} ?></td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">5</td>
			<td class="act">
<?php

if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 4 && $tl_b3  < $date2) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=11'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}elseif
	($golds['gold'] > 4 && $tl_b3 > $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=11'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Extender</div></div></button>";
} else  {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
} }
 ?>
            </td>
		</tr>
			<tr>
			<td class="desc">
				+<b>25</b>% Cereal <img class="r4" src="img/x.gif" alt="<br>
				<span class="run">
<?php
$tl_b4=$golds['b4'];
 if ($tl_b4 < $date2) {
     print " ";
 } else {
$holdtotmin4=(($tl_b4-$date2)/60);
$holdtothr4=(($tl_b4-$date2)/3600);
$holdtotday4=round(($tl_b4-$date2)/86400, 1);
$holdhr4=intval($holdtothr4-($holdtotday4*24));
$holdmr4=intval($holdtotmin4-(($holdhr4*60)+($holdtotday4*1440)));
}

 if ($tl_b4 < $date2) {
     print " ";
 } else {

echo "<br> Te quedan <b>".$holdtotday4. "</b> días hasta las ".date('H:i',$golds['b4'])."";
 }
?>
		</td>
			<td class="dur"><?php if(PLUS_PRODUCTION >= 86400){
			echo ''.(PLUS_PRODUCTION/86400).' Días';
			} else if(PLUS_PRODUCTION < 86400){
			echo ''.(PLUS_PRODUCTION/3600).' horas';
			} ?></td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">5</td>
			<td class="act">
<?php

if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 4 && $tl_b4 < $date2) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=12'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}elseif
	($golds['gold'] > 4 && $tl_b4 > $date2) {
	echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=12'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Extender</div></div></button>";
} else {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
} }
?>

            </td>
		</tr>
  </tbody>
</table>
<table class="plusFunctions" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td>Descripción</td>
			<td>Duración</td>
			<td>Oro</td>
			<td>Acción</td>
		</tr>
	</thead>
	<tbody>

		<tr>
			<td class="desc">Completa todas las construcciones, investigaciones y la demolición en curso al instante.</td>
			<td class="dur">Instantáneo</td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold"><?php echo Building::FINISH_ALL_GOLD; ?></td>
			<td class="act">
<?php
if (mysql_num_rows($MyGold)) {
	if($golds['gold'] > 1) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=3&action=FinishBuilding'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Completar</div></div></button>";

} else {
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Oro insuficiente</div></div></button>";
	}
}
 ?>
			</td>
		</tr>
		<tr>
			<td class="desc">Derriba un edificio entero al instante (Edificio Principal nivel <?php echo DEMOLISH_LEVEL_REQ; ?>).</td>
			<td class="dur">Instantáneo</td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold"><?php echo Building::DEMOLISH_ALL_GOLD; ?></td>
			<td class="act link">
            <?php if($building->getTypeLevel(15) >= DEMOLISH_LEVEL_REQ){ ?>
            <a class="arrow" href="build.php?gid=15">Ir al edificio principal</a>
            <?php }else{ ?>
            <span class="none"><center>Ir al edificio principal</center></span>
            <?php } ?>
			</td>
		</tr>
			<tr>
			<td class="desc">Mercader NPC 1:1</td>
			<td class="dur">instantáneo</td>
			<td class="cost"><img src="img/x.gif" class="gold" alt="gold">3</td>
			<td class="act link">
            <?php
            if($building->getTypeLevel(17)){ ?>
            <a class="arrow" href="build.php?gid=17&amp;t=3">Ir al mercado</a>
            <?php }else{ ?>
            <span class="none"><center>Ir al mercado</center></span>
            <?php } ?>
			</td>
		</tr>
        <tr>
				<td class="desc">Intercambiar oro y plata</td>
				<td class="dur">Instantáneo</td>
				<td class="cost"><img src="img/x.gif" class="gold" title= "Oro" alt="Oro"></td>
				<td class="act arrow" style="text-align: right"><a class="arrow" href="plus.php?id=6">Casa de cambio</a></td>
			</tr>
        </tbody>
</table>
<h4 class="spacer">Club de oro</h4>
<table class="plusFunctions" cellpadding="1" cellspacing="1">
		<thead>
			<tr>
				<td>Descripción</td>
				<td>Duración</td>
				<td>Oro</td>
				<td>Acción</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="desc">
					<a name="goldclub"></a>
					<b>Club de oro</b>

<div class="run">Activa el Club de oro para obtener ventajas extra.</div>
				</td>
				<td class="dur">
					Todo el juego

				</td>
				<td class="cost"><img src="img/x.gif" class="gold" alt="gold">100</td>
				<td class="act">
<?php
if($session->gold >= 100){
	if($golds['goldclub'] == 0) {
		echo "<button type=\"button\" value=\"Activar\" onclick=\"window.location.href = 'plus.php?id=15'; return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";

	} else {
		echo "<button type=\"button\" value=\"Activado\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activado</div></div></button>";
	}
}else{
	echo "<button type=\"button\" value=\"Activar\" class=\" disabled\" onclick=\"(new Event(event)).stop(); return false;\" onfocus=\"$$('button', 'input[type!=hidden]', 'select')[0].focus(); (new Event(event)).stop(); return false;\"><div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div><div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div></div><div class=\"button-contents\">Activar</div></div></button>";
}
                ?></td>
			</tr>



	</tbody>
</table>
<div class="clear">&nbsp;</div>