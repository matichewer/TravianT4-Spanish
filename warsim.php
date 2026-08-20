<?php

include("GameEngine/Village.php");
$start = $generator->pageLoadTimeStart();
if(isset($_GET['newdid'])) {
	$_SESSION['wid'] = $_GET['newdid'];
	$_SESSION['warsimRefillAttacker'] = true;
	header("Location: ".$_SERVER['PHP_SELF']);
}
$simulationInput = $_POST;
$refillAttackerFromVillage = false;
if(empty($simulationInput) && !isset($_GET['newdid'])) {
	if(!empty($_SESSION['warsimLastInput'])) {
		$simulationInput = $_SESSION['warsimLastInput'];
	}
	$refillAttackerFromVillage = !empty($_SESSION['warsimRefillAttacker']);
	unset($_SESSION['warsimRefillAttacker']);
}
if(empty($_POST) && isset($_GET['bid'])) {
	// Botón "simular" de un informe: precarga el combate tal como lo vio el jugador.
	$simulationInput = $battle->getReportSimulationInput($_GET['bid']);
	if($simulationInput === false) {
		$form->addError('bid', 'No se pudo cargar el informe seleccionado.');
		$simulationInput = array();
	}
}
if(empty($_POST) && isset($_GET['oasis'])) {
	$simulationInput = $battle->getOasisSimulationInput($_GET['oasis']);
	if($simulationInput === false) {
		$form->addError('oasis', 'El objetivo seleccionado no es un oasis desocupado válido.');
		$simulationInput = array();
	}
}
if($refillAttackerFromVillage && isset($simulationInput['a1_v'])) {
	// Al cambiar de aldea desde el simulador conservamos el resto de la
	// configuracion (defensor, tipo de ataque, etc.) pero recargamos las
	// tropas propias desde la aldea recien seleccionada, no la anterior.
	$villageUnits = $database->getUnit($village->wid);
	$attackerUpgrades = $database->getABTech($village->wid);
	$unitOffset = ((int)$simulationInput['a1_v'] - 1) * 10;
	for($i = 1; $i <= 10; $i++) {
		$simulationInput['a1_'.$i] = isset($villageUnits['u'.($unitOffset + $i)]) ? max(0, (int)$villageUnits['u'.($unitOffset + $i)]) : 0;
	}
	for($i = 1; $i <= 8; $i++) {
		$simulationInput['f1_'.$i] = isset($attackerUpgrades['b'.$i]) ? max(0, min(20, (int)$attackerUpgrades['b'.$i])) : 0;
	}
}
$battle->procSim($simulationInput);
if(isset($_POST['target'])) {
	$_SESSION['warsimLastInput'] = $form->valuearray;
}
include "Templates/html.tpl";
?>
<body class="v35 webkit chrome warsim">
	<div id="wrapper"> 
		<img id="staticElements" src="img/x.gif" alt="" /> 
		<div id="logoutContainer"> 
			<a id="logout" href="logout.php" title="<?php echo LOGOUT; ?>">&nbsp;</a> 
		</div> 
		<div class="bodyWrapper"> 
			<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" /> 
			<div id="header"> 
				<div id="mtop">
					<a id="logo" href="<?php echo HOMEPAGE; ?>" target="_blank" title="<?php echo SERVER_NAME ?>"></a>
					<?php
						include("Templates/navigation.tpl");
					?>
<div class="clear"></div> 
</div> 
</div>
					<div id="mid">
<a id="ingameManual" href="help.php"><img class="question" alt="Ayuda" src="img/x.gif"></a>
												<div class="clear"></div> 
						<div id="contentOuterContainer"> 
							<div class="contentTitle">&nbsp;</div>
<div class="contentContainer">
<div id="content"  class="warsim">
<h1><?php echo WARSIM; ?></h1>
<?php
if($form->returnErrors() > 0) {
	foreach($form->getErrors() as $error) {
		echo '<p class="error3">'.$error.'</p>';
	}
}
?>
<form action="warsim.php" method="post">
<?php
if(isset($_POST['result'])) {
    $target = isset($_POST['target'])? $_POST['target'] : array();
    $tribe = isset($_POST['mytribe'])? $_POST['mytribe'] : $session->tribe;
    echo '<h4 class="round">Tipo de combate: ';
    echo !empty($_POST['result']['scouting'])
        ? "Exploración"
        : ($form->getValue('ktyp') == 0 ? "Ataque normal" : "Saqueo");
    echo "</h4>";
    include("Templates/Simulator/res_a".$tribe.".tpl");
    foreach($target as $tar) {
        include("Templates/Simulator/res_d".$tar.".tpl");
    }
	include("Templates/Simulator/hero_result.tpl");
    $hasSiegeResult = isset($_POST['result']['target_level_after']) || isset($_POST['result']['wall_level_after']);
    if($hasSiegeResult) {
        echo '<h4 class="round">Configuración del ataque</h4>';
    }
    if(isset($_POST['result']['target_level_after'])) {
        $targetLevel = (int)$form->getValue('kata');
        $remainingLevel = (int)$_POST['result']['target_level_after'];
        if($remainingLevel < $targetLevel) {
            echo "<p>El edificio de nivel <b>".$targetLevel."</b> quedó reducido al nivel <b>".$remainingLevel."</b>.</p>";
        } else {
            echo "<p>Las catapultas supervivientes no alcanzaron a reducir el nivel del edificio.</p>";
        }
    }
    if(isset($_POST['result']['wall_level_after'])) {
        $defenderTribe = isset($_POST['village_tribe']) && in_array((int)$_POST['village_tribe'], array_map('intval', $target), true)
            ? (int)$_POST['village_tribe']
            : (int)$target[0];
        $wallSubjects = array(1 => 'La muralla', 2 => 'El terraplén', 3 => 'La empalizada');
        $wallObjects = array(1 => 'de la muralla', 2 => 'del terraplén', 3 => 'de la empalizada');
        $wallLevel = (int)$form->getValue('wall'.$defenderTribe);
        $remainingWallLevel = (int)$_POST['result']['wall_level_after'];
        if($remainingWallLevel < $wallLevel) {
			echo "<p>".$wallSubjects[$defenderTribe]." bajó del nivel <b>".$wallLevel."</b> al nivel <b>".$remainingWallLevel."</b>.</p>";
        } else {
			echo "<p>Los arietes supervivientes no alcanzaron a reducir el nivel ".$wallObjects[$defenderTribe].".</p>";
        }
    }
}
$target = isset($_POST['target'])? $_POST['target'] : array();
$tribe = isset($_POST['mytribe'])? $_POST['mytribe'] : $session->tribe;
if(count($target) > 0) {
	echo '<input type="hidden" name="displayed_attacker" value="'.(int)$tribe.'">';
	echo '<input type="hidden" name="displayed_targets" value="'.implode(',', array_map('intval', $target)).'">';
	// Cuál de las tribus marcadas es la aldea: sobrevive al re-envío del formulario,
	// que no tiene ningún control visible para elegirla.
	$formVillageTribe = isset($_POST['village_tribe']) && in_array((int)$_POST['village_tribe'], array_map('intval', $target), true)
		? (int)$_POST['village_tribe']
		: (int)$target[0];
	echo '<input type="hidden" name="a2_village" value="'.$formVillageTribe.'">';
    include("Templates/Simulator/att_".$tribe.".tpl");
	echo '<div id="defender"><div class="fighterType"><div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">'.WARSIM_DEFENDER.'</div></div></div><div class="clear"></div>';

    foreach($target as $tar) {
        include("Templates/Simulator/def_".$tar.".tpl");
    }
    include("Templates/Simulator/def_end.tpl");
    echo "</div><div class=\"clear\"></div>";
}
?>
<table id="select" cellpadding="1" cellspacing="1">
		<tbody>
			<tr>
				<td>
					<div class="fighterType">
						<div class="boxes boxesColor red"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><?php echo WARSIM_ATTACKER; ?></div>
				</div>
					</div>
					<div class="clear"></div>

					<div class="choice">
                    <label><input class="radio" type="radio" name="a1_v" value="1" <?php if($tribe == 1) { echo "checked"; } echo "> ".TRIBE1;?> </label><br/>
        <label><input class="radio" type="radio" name="a1_v" value="2" <?php if($tribe == 2) { echo "checked"; } echo "> ".TRIBE2;?> </label><br/>
        <label><input class="radio" type="radio" name="a1_v" value="3" <?php if($tribe == 3) { echo "checked"; } echo "> ".TRIBE3;?> </label>
					</div>
				</td>

				<td>
					<div class="fighterType">
						<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><?php echo WARSIM_DEFENDER; ?></div>
				</div>
					</div>
					<div class="clear"></div>

					<div class="choice">
						<label><input class="check" type="checkbox" name="a2_v1" value="1" <?php if(in_array(1,$target)) { echo "checked"; } echo "> ".TRIBE1;?> </label><br>
						<label><input class="check" type="checkbox" name="a2_v2" value="1" <?php if(in_array(2,$target)) { echo "checked"; } echo "> ".TRIBE2;?> </label><br>
						<label><input class="check" type="checkbox" name="a2_v3" value="1" <?php if(in_array(3,$target)) { echo "checked"; } echo "> ".TRIBE3;?> </label><br>
						<label><input class="check" type="checkbox" name="a2_v4" value="1" <?php if(in_array(4,$target)) { echo "checked"; } echo "> ".TRIBE4;?> </label><br>
						<label><input class="check" type="checkbox" name="a2_v5" value="1" <?php if(in_array(5,$target)) { echo "checked"; } echo "> ".TRIBE5;?> </label><br>
						<small>La primera tribu marcada en esta lista define la aldea y sus defensas.</small>
					</div>
				</td>

				<td>
					<div class="fighterType">
						<div class="boxes boxesColor darkGray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents"><?php echo WARSIM_TYPE; ?></div>
				</div>
					</div>
					<div class="clear"></div>

					<div class="choice">
                    <label><input class="radio" type="radio" name="ktyp" value="0" <?php if($form->getValue('ktyp') == 0 || $form->getValue('ktyp') == "") { echo "checked"; } echo "> ".WARSIM_NORMAL;?> </label><br/>

        <label><input class="radio" type="radio" name="ktyp" value="1" <?php if($form->getValue('ktyp') == 1) { echo "checked"; } echo "> ".WARSIM_RAID;?> </label><br/>
						<input type="hidden" name="uid" value="<?php echo $session->uid; ?>">
					</div>
				</td>
			</tr>
		</tbody>
	</table>

<script type="text/javascript">
function validateSimulation() {
	return true;
}

(function() {
	var targetRow = document.getElementById('warsimCatapultTarget');
	var attackTypes = document.getElementsByName('ktyp');
	var sideSelectors = document.querySelectorAll('input[name="a1_v"], input[name^="a2_v"]');
	var displayedAttacker = document.getElementsByName('displayed_attacker');
	var simulatorForm = sideSelectors.length ? sideSelectors[0].form : null;
	function reloadCombatants(event) {
		if(!simulatorForm) {
			return;
		}
		var isDefenderChange = event && event.target && /^a2_v/.test(event.target.name);
		var fields = simulatorForm.elements;
		for(var i = 0; i < fields.length; i++) {
			if(isDefenderChange) {
				if(/^(a2|f2)_\d+$/.test(fields[i].name)) {
					fields[i].value = '';
				}
			} else {
				if(/^(a1|a2|f1|f2)_\d+$/.test(fields[i].name)) {
					fields[i].value = '';
				}
			}
		}
		simulatorForm.submit();
	}
	if(displayedAttacker.length) {
		for(var selectorIndex = 0; selectorIndex < sideSelectors.length; selectorIndex++) {
			sideSelectors[selectorIndex].onchange = reloadCombatants;
		}
	}
	if(!targetRow || !attackTypes.length) {
		return;
	}
	function updateCatapultTarget() {
		for(var i = 0; i < attackTypes.length; i++) {
			if(attackTypes[i].checked) {
				targetRow.style.display = attackTypes[i].value === '1' ? 'none' : '';
				return;
			}
		}
	}
	for(var i = 0; i < attackTypes.length; i++) {
		attackTypes[i].onclick = updateCatapultTarget;
	}
	updateCatapultTarget();
})();
</script>

<p class="btn"><button type="submit" value="Simular ataque" name="s1" id="btn_ok" onclick="return validateSimulation()"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents"><?php echo WARSIM_SIMULATE; ?></div></div></button></p>
</form>
</div>
<div class="clear">&nbsp;</div>

<div class="clear"></div>
</div>
<div class="contentFooter">&nbsp;</div>
</div>

                    
<?php
include("Templates/sideinfo.tpl");
include("Templates/footer.tpl");
include("Templates/header.tpl");
include("Templates/res.tpl");
include("Templates/vname.tpl");
include("Templates/quest.tpl");
?>

</div>
<div id="ce"></div>
</div>
</body>
</html>
