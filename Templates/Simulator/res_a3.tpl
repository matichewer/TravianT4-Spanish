<div class="fighterType">
				<div class="boxes boxesColor red"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Atacante: Galos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results attacker" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u21" title="Falange" alt="Falange">
							</td><td>
								<img src="img/x.gif" class="unit u22" title="Espadachín" alt="Espadachín">
							</td><td>
								<img src="img/x.gif" class="unit u23" title="Buscador de senderos" alt="Buscador de senderos">
							</td><td>
								<img src="img/x.gif" class="unit u24" title="Rayo de Teutates" alt="Rayo de Teutates">
							</td><td>
								<img src="img/x.gif" class="unit u25" title="Jinete druida" alt="Jinete druida">
							</td><td>
								<img src="img/x.gif" class="unit u26" title="Jinete Eduo" alt="Jinete Eduo">
							</td><td>
								<img src="img/x.gif" class="unit u27" title="Ariete" alt="Ariete">
							</td><td>
								<img src="img/x.gif" class="unit u28" title="Catapulta" alt="Catapulta">
							</td><td>
								<img src="img/x.gif" class="unit u29" title="Cacique" alt="Cacique">
							</td><td>
								<img src="img/x.gif" class="unit u30" title="Colono" alt="Colono">
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