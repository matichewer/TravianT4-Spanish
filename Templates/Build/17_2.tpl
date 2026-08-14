<h1 class="titleInHeader">Mercado <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid17">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(17,4);" class="build_logo"> 
	<img class="building big white g17" src="img/x.gif" alt="Mercado" title="Mercado" /> 
</a> 
Puedes comerciar recursos con otros jugadores a través del mercado. Cuanto mayor sea el nivel, más mercaderes estarán disponibles.</div> 
<?php
$buildingHelpType = 'marketplace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("17_menu.tpl");
include("17_merchants.tpl");

$offerDraft = array_merge(array(
	'gtype' => 1,
	'gamt' => '',
	'wtype' => 2,
	'wamt' => '',
	'limited' => false,
	'hours' => 2,
	'alliance' => false
),$market->offerDraft);
?>

<form method="POST" name="snd" action="build.php"> 
			<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
			<input type="hidden" name="ft" value="mk2"> 
			<input type="hidden" name="t" value="2">
			<input type="hidden" name="a" value="<?php echo $session->mchecker; ?>">
 
<table id="sell" cellpadding="1" cellspacing="1"> 
<tr> 
	<th>
Ofrezco</th> 
	<td class="val"><input class="text" tabindex="1" name="m1" value="<?php echo (int)$offerDraft['gamt'] ?: ''; ?>" maxlength="6" /></td>
	<td class="res"> 
		<select name="rid1" tabindex="2" class="dropdown"> 
			<option value="1"<?php if((int)$offerDraft['gtype'] === 1) echo ' selected="selected"'; ?>>Madera</option>
			<option value="2"<?php if((int)$offerDraft['gtype'] === 2) echo ' selected="selected"'; ?>>Barro</option>
			<option value="3"<?php if((int)$offerDraft['gtype'] === 3) echo ' selected="selected"'; ?>>Hierro</option>
			<option value="4"<?php if((int)$offerDraft['gtype'] === 4) echo ' selected="selected"'; ?>>Cereal</option>
		</select> 
	</td> 
	<td class="tra"><input class="check" type="checkbox" tabindex="5" name="d1" value="1"<?php if($offerDraft['limited']) echo ' checked="checked"'; ?> /> Tiempo máximo: <input class="text" tabindex="6" name="d2" value="<?php echo (int)$offerDraft['hours']; ?>" maxlength="2" /> horas</td>
</tr> 
<tr> 
	<th>Busco</th> 
	<td class="val"><input class="text" tabindex="3" name="m2" value="<?php echo (int)$offerDraft['wamt'] ?: ''; ?>" maxlength="6" /></td>
	<td class="res"> 
		<select name="rid2" tabindex="4" class="dropdown"> 
			<option value="1"<?php if((int)$offerDraft['wtype'] === 1) echo ' selected="selected"'; ?>>Madera</option>
			<option value="2"<?php if((int)$offerDraft['wtype'] === 2) echo ' selected="selected"'; ?>>Barro</option>
			<option value="3"<?php if((int)$offerDraft['wtype'] === 3) echo ' selected="selected"'; ?>>Hierro</option>
			<option value="4"<?php if((int)$offerDraft['wtype'] === 4) echo ' selected="selected"'; ?>>Cereal</option>
		</select> 
	</td> 
	<td class="al">
    <?php 
    if((int)$session->alliance > 0) {
    echo '<label><input class="check" type="checkbox" tabindex="7" name="ally" value="1"'.($offerDraft['alliance'] ? ' checked="checked"' : '').' /> Solo para miembros de mi alianza</label>';
    }
    ?> 
    </td>
</tr> 
</table>
<button type="submit" value="ok" name="s1" id="btn_ok" tabindex="8"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Aceptar</div></div></button></form><br />
<?php if(count($market->onmarket) > 0) { ?>
<h4 class="spacer">Ofertas</h4>
<table id="sell_overview" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td>&nbsp;</td>
			<td>Oferta</td>
			<th><img src="img/x.gif" class="ratio" title="Proporción"></th>
			<td>Busco</td>
            <?php if((int)$session->alliance > 0){ ?><td>Solo alianza</td><?php } ?>
			<td>Mercaderes</td>
			<td>Tiempo máx.</td>
		</tr>
	</thead>
	<tbody>
<?php foreach($market->onmarket as $offer) { ?>
    <tr>
			<td class="abo"><a href="build.php?id=<?php echo $id; ?>&t=<?php echo $_GET['t']; ?>&a=<?php echo $session->mchecker; ?>&del=<?php echo $offer['id']; ?>"><img class="del" src="img/x.gif" alt="Cancelar"></a></td>
			<td class="val">
<?php
	switch($offer['gtype']) {
		case 1: echo "<img src=\"img/x.gif\" class=\"r1\" alt=\"Madera\" title=\"Madera\" /> "; break;
		case 2: echo "<img src=\"img/x.gif\" class=\"r2\" alt=\"Barro\" title=\"Barro\" /> "; break;
		case 3: echo "<img src=\"img/x.gif\" class=\"r3\" alt=\"Hierro\" title=\"Hierro\" /> "; break;
		case 4: echo "<img src=\"img/x.gif\" class=\"r4\" alt=\"Cereal\" title=\"Cereal\" /> "; break;
	}
	echo $offer['gamt'];
    
	$sss = ($offer['gamt'] > 0)? ($offer['wamt']/$offer['gamt']) : 0;
        $ratio = round($sss, 1);
        if($ratio <= 1){
        	$class = 'red';
        }elseif($ratio > 1 && $ratio < 2){
        	$class = 'orange';
        }elseif($ratio >= 2){
        	$class = 'green';
        }	
?></td>
			<td class="ratio">
				<div class="boxes boxesColor <?php echo $class; ?>"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">
				<?php echo $ratio; ?>
					</div>
				</div>

				</td>

			<td class="val">
<?php
	switch($offer['wtype']) {
		case 1: echo "<img src=\"img/x.gif\" class=\"r1\" alt=\"Madera\" title=\"Madera\" /> "; break;
		case 2: echo "<img src=\"img/x.gif\" class=\"r2\" alt=\"Barro\" title=\"Barro\" /> "; break;
		case 3: echo "<img src=\"img/x.gif\" class=\"r3\" alt=\"Hierro\" title=\"Hierro\" /> "; break;
		case 4: echo "<img src=\"img/x.gif\" class=\"r4\" alt=\"Cereal\" title=\"Cereal\" /> "; break;
	}
	echo $offer['wamt'];		
?></td>
			<?php if((int)$session->alliance > 0){ ?><td class="al"><?php echo ($offer['alliance'] == 0)? 'No' : 'Sí'; ?></td><?php } ?>
			<td class="tra"><?php echo $offer['merchant']; ?></td>
			<td class="dur"><?php
        if($offer['maxtime'] != 0) {
        	echo $offer['maxtime']/3600;
        	echo " horas";
        }else { echo "-"; }
			?></td>
            
		</tr>
		<?php } ?>
		</tbody>
</table>
<?php } ?>
</div> 
