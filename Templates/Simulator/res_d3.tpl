<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Galos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u21" alt="Falange">
							</td><td>
								<img src="img/x.gif" class="unit u22" alt="Espadachín">
							</td><td>
								<img src="img/x.gif" class="unit u23" alt="Buscador de senderos">
							</td><td>
								<img src="img/x.gif" class="unit u24" alt="Rayo de Teutates">
							</td><td>
								<img src="img/x.gif" class="unit u25" alt="Jinete druida">
							</td><td>
								<img src="img/x.gif" class="unit u26" alt="Haeduano">
							</td><td>
								<img src="img/x.gif" class="unit u27" alt="Ariete">
							</td><td>
								<img src="img/x.gif" class="unit u28" alt="Catapulta">
							</td><td>
								<img src="img/x.gif" class="unit u29" alt="Cacique">
							</td><td>
								<img src="img/x.gif" class="unit u30" alt="Colono">
							</td></tr>
				</thead>
				<tbody>
					<tr>
						<th>
							Tropas
						</th><td <?php if (!$form->getValue('a2_21')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_21');} ?></td>
                                <td <?php if (!$form->getValue('a2_22')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_22');} ?></td>
                                <td <?php if (!$form->getValue('a2_23')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_23');} ?></td>
                                <td <?php if (!$form->getValue('a2_24')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_24');} ?></td>
                                <td <?php if (!$form->getValue('a2_25')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_25');} ?></td>
                                <td <?php if (!$form->getValue('a2_26')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_26');} ?></td>
                                <td <?php if (!$form->getValue('a2_27')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_27');} ?></td>
                                <td <?php if (!$form->getValue('a2_28')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_28');} ?></td>
                                <td <?php if (!$form->getValue('a2_29')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_29');} ?></td>
                                <td <?php if (!$form->getValue('a2_30')) { echo "class=\"none\">0"; }else{ echo ">".$form->getValue('a2_30');} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_21')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_22')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_23')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_24')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_25')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_26')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_27')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_28')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_29')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_30')) { echo "class=\"none\">0"; }else{ echo ">".$dead = round($troops * $_POST['result'][2]);} ?></td></tr>
				</tbody>
			</table>