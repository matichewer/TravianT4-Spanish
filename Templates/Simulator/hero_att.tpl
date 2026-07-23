<tr>
	<td class="ico">
		<img src="img/x.gif" class="unit uhero" title="Héroe">
	</td>
	<td class="desc">Héroe</td>
	<td class="value">
		<input class="check" type="checkbox" name="a1_hero" value="1"
			<?php if((int)$form->getValue('a1_hero') === 1) { echo 'checked'; } ?>
			title="Agregar al héroe atacante">
	</td>
	<td class="research"></td>
</tr>
<tr>
	<td class="ico">
		<img src="img/x.gif" class="unit uhero" title="Fuerza de lucha del héroe">
	</td>
	<td class="desc">Fuerza de lucha del héroe</td>
	<td class="value">
		<input class="text" type="text" name="h_att_power"
			value="<?php echo $form->getValue('h_att_power') === '' ? 100 : $form->getValue('h_att_power'); ?>"
			maxlength="5" inputmode="numeric" title="Autocompletada con la fuerza de tu héroe y la bonificación de sus objetos equipados. Podés editarla para probar otro valor." placeholder="Fuerza">
	</td>
	<td class="research"></td>
</tr>
