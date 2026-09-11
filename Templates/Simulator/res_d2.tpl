<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Germanos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u11" title="Luchador de porra" alt="Luchador de porra">
							</td><td>
								<img src="img/x.gif" class="unit u12" title="Lancero" alt="Lancero">
							</td><td>
								<img src="img/x.gif" class="unit u13" title="Guerrero de hacha" alt="Guerrero de hacha">
							</td><td>
								<img src="img/x.gif" class="unit u14" title="Emisario" alt="Emisario">
							</td><td>
								<img src="img/x.gif" class="unit u15" title="Paladín" alt="Paladín">
							</td><td>
								<img src="img/x.gif" class="unit u16" title="Caballero germano" alt="Caballero germano">
							</td><td>
								<img src="img/x.gif" class="unit u17" title="Ariete" alt="Ariete">
							</td><td>
								<img src="img/x.gif" class="unit u18" title="Catapulta" alt="Catapulta">
							</td><td>
								<img src="img/x.gif" class="unit u19" title="Cabecilla" alt="Cabecilla">
							</td><td>
								<img src="img/x.gif" class="unit u20" title="Colono" alt="Colono">
							</td></tr>
				</thead>
				<tbody>
					<tr>
						<th>
							Tropas
						</th><td <?php if (!$form->getValue('a2_11')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_11'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_12')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_12'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_13')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_13'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_14')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_14'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_15')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_15'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_16')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_16'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_17')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_17'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_18')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_18'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_19')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_19'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_20')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_20'), 0, ",", ".");} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_11')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_12')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_13')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_14')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_15')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_16')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_17')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_18')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_19')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_20')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td></tr>
				</tbody>
			</table>