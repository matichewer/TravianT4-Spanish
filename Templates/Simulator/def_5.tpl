<div class="border">
<table class="fill_in transparent" cellpadding="1" cellspacing="0">
	<tbody>
		<tr>
			<th><?php echo TRIBE5; ?><br><small>Cantidad de tropas</small></th>
		</tr>
		<tr>
			<td class="details">
				<table cellpadding="1" cellspacing="1">
					<tbody>

<?php
// Los natares no investigan en la herreria (natarProvisionVillage nunca sube
// tecnologia), asi que la columna de mejoras va vacia como en la naturaleza.
for($i = 41; $i <= 50; $i++) {
echo "
	<tr>
		<td class=\"ico\">
				<img src=\"img/x.gif\" class=\"unit u".$i."\" title=\"".$technology->unarray[$i]."\">
		</td>
        <td class=\"desc\">".$technology->unarray[$i]."</td>
        <td class=\"value\">
            <input class=\"text\" type=\"text\" name=\"a2_".$i."\" value=\"".$form->getValue('a2_'.$i)."\" maxlength=\"6\" inputmode=\"numeric\" title=\"Cantidad de tropas\">
        </td>
        <td class=\"research\"></td>
	</tr>
";
}
?>
					</tbody>
                </table>
			</td>
		</tr>
	</tbody>
</table>
</div>
