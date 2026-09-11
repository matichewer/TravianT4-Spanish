<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Romanos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
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
						</th><td <?php if (!$form->getValue('a2_1')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_1'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_2')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_2'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_3')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_3'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_4')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_4'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_5')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_5'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_6')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_6'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_7')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_7'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_8')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_8'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_9')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_9'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_10')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_10'), 0, ",", ".");} ?></td>
                  </tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_1')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_2')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_3')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_4')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_5')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_6')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_7')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_8')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_9')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_10')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        </tr>
				</tbody>
			</table>