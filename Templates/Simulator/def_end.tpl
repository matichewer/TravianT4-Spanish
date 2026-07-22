<?php
$defenderTribe = (int)$target[0];
$isNature = $defenderTribe === 4;
$wallTitles = array(
	1 => WARSIM_WALL1,
	2 => WARSIM_WALL2,
	3 => WARSIM_WALL3
);
$wallClasses = array(
	1 => 'g31Icon',
	2 => 'g32Icon',
	3 => 'g33Icon'
);
?>
<div class="border">
<table class="fill_in transparent" cellpadding="1" cellspacing="0">
	<tbody>
		<tr>
			<th><?php echo WARSIM_ETC; ?></th>
		</tr>
		<tr>
			<td class="details">
			<table cellpadding="1" cellspacing="1">
				<tbody>
					<tr>
						<td class="ico">
							<img src="img/x.gif" class="unit uhab" title="<?php echo WARSIM_POP; ?>">
						</td>
						<td class="desc"><?php echo $isNature ? 'Población del oasis' : WARSIM_POP; ?></td>
						<td class="value">
							<input class="text" type="text" name="ew2"
								value="<?php echo $isNature ? 100 : ($form->getValue('ew2') === '' ? 1 : $form->getValue('ew2')); ?>"
								maxlength="5" inputmode="numeric"
								title="<?php echo $isNature ? 'Valor fijo usado por los combates contra un oasis de Naturaleza' : 'Población de la aldea que recibe el ataque'; ?>"
								<?php if($isNature) { echo 'readonly'; } ?>>
						</td>
						<td class="research"></td>
					</tr>
<?php if(!$isNature) { ?>
					<tr>
						<td class="ico">
							<img src="img/x.gif" class="gebIcon g34Icon" title="<?php echo WARSIM_STONEMASON; ?>">
						</td>
						<td class="desc"><?php echo WARSIM_STONEMASON; ?></td>
						<td class="value">
							<input class="text" type="text" name="stonemason"
								value="<?php echo $form->getValue('stonemason') === '' ? 0 : $form->getValue('stonemason'); ?>"
								maxlength="2" inputmode="numeric" title="Nivel de la Cabaña del picapedrero del defensor (0-20)">
						</td>
						<td class="research"></td>
					</tr>
					<tr>
						<td class="ico">
							<img src="img/x.gif" class="gebIcon <?php echo $wallClasses[$defenderTribe]; ?>" title="<?php echo $wallTitles[$defenderTribe]; ?>">
						</td>
						<td class="desc"><?php echo $wallTitles[$defenderTribe]; ?></td>
						<td class="value">
							<input class="text" type="text" name="wall<?php echo $defenderTribe; ?>"
								value="<?php echo $form->getValue('wall'.$defenderTribe) === '' ? 0 : $form->getValue('wall'.$defenderTribe); ?>"
								maxlength="2" inputmode="numeric" title="Nivel de la defensa perimetral del defensor (0-20)">
						</td>
						<td class="research"></td>
					</tr>
					<tr>
						<td class="ico">
							<img src="img/x.gif" class="gebIcon g26Icon" title="<?php echo WARSIM_PALACE; ?>">
						</td>
						<td class="desc"><?php echo WARSIM_PALACE; ?></td>
						<td class="value">
							<input class="text" type="text" name="palast"
								value="<?php echo $form->getValue('palast') === '' ? 0 : $form->getValue('palast'); ?>"
								maxlength="2" inputmode="numeric" title="Nivel de la Residencia o el Palacio del defensor (0-20)">
						</td>
						<td class="research"></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>
</div>
