<div class="fighterType">
				<div class="boxes boxesColor red"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Atacante: Romanos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results attacker" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u1" title="Legionario" alt="Legionario">
							</td><td>
								<img src="img/x.gif" class="unit u2" title="Pretoriano" alt="Pretoriano">
							</td><td>
								<img src="img/x.gif" class="unit u3" title="Espadachín" alt="Espadachín">
							</td><td>
								<img src="img/x.gif" class="unit u4" title="Equites Legati" alt="Equites Legati">
							</td><td>
								<img src="img/x.gif" class="unit u5" title="Equites Imperatoris" alt="Equites Imperatoris">
							</td><td>
								<img src="img/x.gif" class="unit u6" title="Equites Caesaris" alt="Equites Caesaris">
							</td><td>
								<img src="img/x.gif" class="unit u7" title="Ariete" alt="Ariete">
							</td><td>
								<img src="img/x.gif" class="unit u8" title="Catapulta de fuego" alt="Catapulta de fuego">
							</td><td>
								<img src="img/x.gif" class="unit u9" title="Senador" alt="Senador">
							</td><td>
								<img src="img/x.gif" class="unit u10" title="Colono" alt="Colono">
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