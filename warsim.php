<?php

include("GameEngine/Village.php");
$start = $generator->pageLoadTimeStart();
$battle->procSim($_POST);
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
    echo '<h4 class="round">Configuración del ataque</h4>';
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
        $defenderTribe = (int)$target[0];
        $wallNames = array(1 => WARSIM_WALL1, 2 => WARSIM_WALL2, 3 => WARSIM_WALL3);
		$wallArticles = array(1 => 'La', 2 => 'El', 3 => 'La');
        $wallLevel = (int)$form->getValue('wall'.$defenderTribe);
        $remainingWallLevel = (int)$_POST['result']['wall_level_after'];
        if($remainingWallLevel < $wallLevel) {
			echo "<p>".$wallArticles[$defenderTribe]." ".$wallNames[$defenderTribe]." bajó del nivel <b>".$wallLevel."</b> al nivel <b>".$remainingWallLevel."</b>.</p>";
        } else {
			echo "<p>Los arietes supervivientes no alcanzaron a reducir el nivel de ".$wallArticles[$defenderTribe]." ".strtolower($wallNames[$defenderTribe]).".</p>";
        }
    }
}
$target = isset($_POST['target'])? $_POST['target'] : array();
$tribe = isset($_POST['mytribe'])? $_POST['mytribe'] : $session->tribe;
if(count($target) > 0) {
	echo '<input type="hidden" name="displayed_attacker" value="'.(int)$tribe.'">';
	echo '<input type="hidden" name="displayed_targets" value="'.implode(',', array_map('intval', $target)).'">';
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
(function() {
	var targetRow = document.getElementById('warsimCatapultTarget');
	var attackTypes = document.getElementsByName('ktyp');
	var sideSelectors = document.querySelectorAll('input[name="a1_v"], input[name^="a2_v"]');
	var displayedAttacker = document.getElementsByName('displayed_attacker');
	var simulatorForm = sideSelectors.length ? sideSelectors[0].form : null;
	function reloadCombatants() {
		if(!simulatorForm) {
			return;
		}
		var fields = simulatorForm.elements;
		for(var i = 0; i < fields.length; i++) {
			if(/^(a1|a2|f1|f2)_\d+$/.test(fields[i].name)) {
				fields[i].value = '';
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

<p class="btn"><button type="submit" value="Simular ataque" name="s1" id="btn_ok"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents"><?php echo WARSIM_SIMULATE; ?></div></div></button></p>
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
