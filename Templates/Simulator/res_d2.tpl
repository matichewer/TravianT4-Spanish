<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Germanos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
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
						</th><td <?php if (!$form->getValue('a2_11')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_11');} ?></td>
                                <td <?php if (!$form->getValue('a2_12')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_12');} ?></td>
                                <td <?php if (!$form->getValue('a2_13')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_13');} ?></td>
                                <td <?php if (!$form->getValue('a2_14')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_14');} ?></td>
                                <td <?php if (!$form->getValue('a2_15')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_15');} ?></td>
                                <td <?php if (!$form->getValue('a2_16')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_16');} ?></td>
                                <td <?php if (!$form->getValue('a2_17')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_17');} ?></td>
                                <td <?php if (!$form->getValue('a2_18')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_18');} ?></td>
                                <td <?php if (!$form->getValue('a2_19')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_19');} ?></td>
                                <td <?php if (!$form->getValue('a2_20')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_20');} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_11')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_12')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_13')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_14')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_15')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_16')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_17')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_18')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_19')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_20')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td></tr>
				</tbody>
			</table>