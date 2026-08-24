<div class="clear"></div>
<?php

if(isset($_POST['cancel'],$_POST['c']) && is_scalar($_POST['c']) && $_POST['cancel'] === '1'
	&& hash_equals((string)$session->mchecker,(string)$_POST['c'])) {
	$database->delDemolition($village->wid);
	$session->changeChecker();
	header('Location: build.php?gid=15');
	exit;
}

// Tercera forma del oficial: tirar el edificio entero al instante.
if(isset($_POST['razeall'],$_POST['c']) && is_scalar($_POST['c']) && $_POST['razeall'] === '1'
	&& hash_equals((string)$session->mchecker,(string)$_POST['c'])) {
	if(isset($_POST['type']) && is_scalar($_POST['type']) && ctype_digit((string)$_POST['type'])) {
		switch($building->demolishInstantly((int)$_POST['type'])) {
			case 'ok':
				break;
			case 'gold':
				$_SESSION['demolitionError'] = 'No tenés suficiente oro para el derribo completo.';
				break;
			default:
				$_SESSION['demolitionError'] = 'No se puede derribar ese edificio de una sola vez.';
				break;
		}
		$session->changeChecker();
		header('Location: build.php?gid=15');
		exit;
	}
}

// El derribo completo comparte el formulario del desplegable y llega con demolish=1
// encima, así que se resuelve antes y este pedido no debe atenderlo.
if(isset($_POST['demolish'],$_POST['c']) && !isset($_POST['razeall'])
	&& is_scalar($_POST['c']) && $_POST['demolish'] === '1'
	&& hash_equals((string)$session->mchecker,(string)$_POST['c'])) {
	// El campo llega en 'type' aunque sea un número de campo, no un tipo de edificio.
	if(isset($_POST['type']) && is_scalar($_POST['type']) && ctype_digit((string)$_POST['type'])) {
		if($building->demolitionAllowed((int)$_POST['type'])) {
			$database->addDemolition($village->wid,(int)$_POST['type']);
		}
		else {
			$_SESSION['demolitionError'] = 'No podés demoler el molino ni la panadería si eso deja el cereal libre por debajo de 1.';
		}
		$session->changeChecker();
		header('Location: build.php?gid=15');
		exit;
	}
}

// Segunda forma de demoler del oficial: apurar con oro el nivel que está en curso.
// Es el mismo "Finalizar todo" de dorf1/dorf2 y cuesta lo mismo, así que si además
// hay construcciones en marcha también terminan con este pago.
if(isset($_POST['finishnow'],$_POST['c']) && is_scalar($_POST['c']) && $_POST['finishnow'] === '1'
	&& hash_equals((string)$session->mchecker,(string)$_POST['c'])) {
	if(!$building->finishAllNow()) {
		$_SESSION['demolitionError'] = 'No se pudo finalizar la demolición con oro.';
	}
	$session->changeChecker();
	header('Location: build.php?gid=15');
	exit;
}

if($village->resarray['f'.$id] >= DEMOLISH_LEVEL_REQ) {
	$demolitionGold = (int)$database->getUserField($session->uid,'gold',0);
	$demolitionToken = htmlspecialchars($session->mchecker,ENT_QUOTES,'UTF-8');
	echo '<h4>Demoler edificio:</h4><p>Tus arquitectos pueden demoler los edificios que ya no necesites:</p>';
	if(!empty($_SESSION['demolitionError'])) {
		echo '<p class="none">'.htmlspecialchars($_SESSION['demolitionError'],ENT_QUOTES,'UTF-8').'</p>';
		unset($_SESSION['demolitionError']);
	}
	$VillageResourceLevels = $database->getResourceLevel($village->wid);
	$DemolitionProgress = $database->getDemolition($village->wid);
	if(!empty($DemolitionProgress)) {
		$Demolition = $DemolitionProgress[0];
		$field = (int)$Demolition['buildnumber'];
		$name = isset($VillageResourceLevels['f'.$field.'t'])
			? $building->procResType($VillageResourceLevels['f'.$field.'t'])
			: 'Edificio';
		echo "<table cellpadding='1' cellspacing='1' id='demolish'><tbody><tr>
		<td><form action='build.php?gid=15' method='POST'><input type='hidden' name='cancel' value='1'><input type='hidden' name='c' value='".$demolitionToken."'><button type='submit' title='Cancelar' aria-label='Cancelar demolición' style='border:0;background:transparent;padding:0'><img class='del' src='img/x.gif' alt='Cancelar'></button></form></td><td>
		<b>".htmlspecialchars($name,ENT_QUOTES,'UTF-8')."</b></td><td><span id='timer1'>".$generator->getTimeFormat(max(0,$Demolition['timetofinish']-time()))."</span></td>
		</tr></tbody></table>";
		if($demolitionGold >= Building::FINISH_ALL_GOLD) {
			echo '<form action="build.php?gid=15" method="POST" style="display:inline">
			<input type="hidden" name="finishnow" value="1"><input type="hidden" name="c" value="'.$demolitionToken.'">
			<button type="submit" value="Finalizar" onclick="return confirm(\'¿Finalizar el nivel en curso por '.Building::FINISH_ALL_GOLD.' de oro?\')"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Finalizar ahora ('.Building::FINISH_ALL_GOLD.' <img src="img/x.gif" class="gold" alt="oro">)</div></div></button></form>';
		}
		if($demolitionGold >= Building::DEMOLISH_ALL_GOLD && $building->canDemolishInstantly($field)) {
			echo '<form action="build.php?gid=15" method="POST" style="display:inline">
			<input type="hidden" name="razeall" value="1"><input type="hidden" name="c" value="'.$demolitionToken.'"><input type="hidden" name="type" value="'.$field.'">
			<button type="submit" value="Derribar" onclick="return confirm(\'¿Derribar el edificio entero por '.Building::DEMOLISH_ALL_GOLD.' de oro?\')"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Derribar entero ('.Building::DEMOLISH_ALL_GOLD.' <img src="img/x.gif" class="gold" alt="oro">)</div></div></button></form>';
		}
	} else {
		$options = '';
		for($i = 19; $i <= 40; $i++) {
			if((int)$VillageResourceLevels['f'.$i.'t'] >= 1 && (int)$VillageResourceLevels['f'.$i] >= 1
				&& empty($database->getBuildingByField($village->wid,$i))
				&& empty($database->getMasterJobsByField($village->wid,$i))) {
				$name = $building->procResType($VillageResourceLevels['f'.$i.'t']);
				$options .= '<option value="'.$i.'">'.$i.'. '.htmlspecialchars($name,ENT_QUOTES,'UTF-8').' '.(int)$VillageResourceLevels['f'.$i].'</option>';
			}
		}
		if($options === '') {
			echo '<p class="none">No hay edificios disponibles para demoler.</p>';
		} else {
			echo '<form action="build.php?gid=15" method="POST" style="display:inline">
			<input type="hidden" name="demolish" value="1"><input type="hidden" name="c" value="'.$demolitionToken.'">
			<select name="type" class="dropdown">'.$options.'</select>
			<button type="submit" value="Demoler" id="btn_demolish"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Demoler</div></div></button>';
			// El derribo completo comparte el desplegable: el mismo formulario cambia de
			// acción según el botón que se apretó, así no hay dos selectores que puedan
			// apuntar a edificios distintos.
			if($demolitionGold >= Building::DEMOLISH_ALL_GOLD && (int)$VillageResourceLevels['f99t'] !== 40) {
				echo '<button type="submit" name="razeall" value="1" id="btn_demolish_all" onclick="return confirm(\'¿Derribar el edificio entero por '.Building::DEMOLISH_ALL_GOLD.' de oro?\')"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Derribar entero ('.Building::DEMOLISH_ALL_GOLD.' <img src="img/x.gif" class="gold" alt="oro">)</div></div></button>';
			}
			echo '</form>';
		}
	}
}
?>
