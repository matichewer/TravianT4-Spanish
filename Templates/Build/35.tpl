<h1 class="titleInHeader">Cervecería <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid35">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(35,4);" class="build_logo">
        <img class="building big white g35" src="img/x.gif" alt="Cervecería" title="Cervecería"></a>
       En la cervecería se elaboran bebidas para las celebraciones de hidromiel. Mientras una celebración está activa, aumenta un 1% por nivel el ataque de todas las tropas germanas de la cuenta. A cambio, el poder de persuasión de los jefes se reduce a la mitad y las catapultas atacan objetivos aleatorios. Solo puede construirse en la capital.</div>

<?php
$buildingHelpType = 'brewery';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');
?>

	<table cellpadding="1" cellspacing="1" id="build_value">
		<tr>
			<th>Bono de ataque:</th>
			<td><b><?php echo $bid35[$village->resarray['f'.$id]]['attri']; ?></b>%</td>
		</tr>
		<tr>
		<?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
			<th>Bono de ataque en el nivel <?php echo $village->resarray['f'.$id]+1; ?> </th>
			<td><b><?php echo $bid35[$village->resarray['f'.$id]+1]['attri']; ?></b>%</td>
            <?php
            }
            ?>
		</tr>
	</table>
<?php
$breweryEnd = $database->getBreweryCelebrationEnd($session->uid);
$breweryCost = array('wood' => 3870, 'clay' => 1680, 'iron' => 215, 'crop' => 10900);
$breweryDuration = max(1, (int)round(259200 / SPEED));
if(isset($_SESSION['brewery_status'])) {
	$status = $_SESSION['brewery_status'];
	unset($_SESSION['brewery_status']);
	echo $status === 'success'
		? '<p class="notice">La celebración de hidromiel ha comenzado.</p>'
		: '<p class="error">No se pudo iniciar la celebración. Comprueba los recursos y que no haya otra activa.</p>';
}
?>
	<h4 class="spacer">Celebración de hidromiel</h4>
	<div class="build_details researches">
		<div class="research">
			<div class="information">
				<div class="title">Celebración de hidromiel</div>
				<div class="costs">
					<div class="showCosts">
						<span class="resources r1 little_res"><img class="r1" src="img/x.gif" alt="Madera"><?php echo $breweryCost['wood']; ?></span>
						<span class="resources r2 little_res"><img class="r2" src="img/x.gif" alt="Barro"><?php echo $breweryCost['clay']; ?></span>
						<span class="resources r3 little_res"><img class="r3" src="img/x.gif" alt="Hierro"><?php echo $breweryCost['iron']; ?></span>
						<span class="resources r4 little_res"><img class="r4" src="img/x.gif" alt="Cereal"><?php echo $breweryCost['crop']; ?></span>
						<span class="clocks"><img class="clock" src="img/x.gif" alt="Duración"><?php echo $generator->getTimeFormat($breweryDuration); ?></span>
					</div>
				</div>
<?php if($breweryEnd > time()) { ?>
				<div class="contractLink"><span class="none">Activa durante <?php echo $generator->getTimeFormat($breweryEnd - time()); ?></span></div>
<?php } elseif($breweryCost['wood'] > $village->awood || $breweryCost['clay'] > $village->aclay || $breweryCost['iron'] > $village->airon || $breweryCost['crop'] > $village->acrop) { ?>
				<div class="contractLink"><span class="none">No hay recursos suficientes</span></div>
<?php } else { ?>
				<form method="post" action="brewery.php">
					<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
					<input type="hidden" name="c" value="<?php echo htmlspecialchars($session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
					<button type="submit" class="build"><span class="button-contents">Celebrar</span></button>
				</form>
<?php } ?>
			</div>
			<div class="clear"></div>
		</div>
	</div>
<?php 
include("upgrade.tpl");
?>
</p></div>
