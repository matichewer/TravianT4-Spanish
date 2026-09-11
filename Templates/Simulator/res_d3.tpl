<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Galos	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
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
						</th><td <?php if (!$form->getValue('a2_21')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_21'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_22')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_22'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_23')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_23'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_24')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_24'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_25')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_25'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_26')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_26'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_27')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_27'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_28')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_28'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_29')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_29'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_30')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_30'), 0, ",", ".");} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_21')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_22')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_23')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_24')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_25')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_26')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_27')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_28')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_29')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_30')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td></tr>
				</tbody>
			</table>