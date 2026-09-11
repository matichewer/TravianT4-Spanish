<div class="fighterType">
				<div class="boxes boxesColor red"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Atacante: Germanos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results attacker" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u11" alt="Luchador de porra">
							</td><td>
								<img src="img/x.gif" class="unit u12" alt="Lancero">
							</td><td>
								<img src="img/x.gif" class="unit u13" alt="Guerrero de hacha">
							</td><td>
								<img src="img/x.gif" class="unit u14" alt="Emisario">
							</td><td>
								<img src="img/x.gif" class="unit u15" alt="Paladín">
							</td><td>
								<img src="img/x.gif" class="unit u16" alt="Caballero germano">
							</td><td>
								<img src="img/x.gif" class="unit u17" alt="Ariete">
							</td><td>
								<img src="img/x.gif" class="unit u18" alt="Catapulta">
							</td><td>
								<img src="img/x.gif" class="unit u19" alt="Cabecilla">
							</td><td>
								<img src="img/x.gif" class="unit u20" alt="Colono">
							</td></tr>
				</thead>
				<tbody>
					<tr>
						<th>
							Tropas
						</th><td <?php if (!$form->getValue('a1_1')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_1'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_2')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_2'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_3')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_3'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_4')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_4'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_5')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_5'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_6')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_6'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_7')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_7'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_8')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_8'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_9')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_9'), 0, ",", ".");} ?></td>
                        <td <?php if (!$form->getValue('a1_10')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a1_10'), 0, ",", ".");} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a1_1')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_2')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_3')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_4')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_5')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_6')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_7')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_8')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_9')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a1_10')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][1]), 0, ",", ".");} ?></td></tr>
				</tbody>
			</table>