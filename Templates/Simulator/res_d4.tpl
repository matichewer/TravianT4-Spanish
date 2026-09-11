<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: Naturaleza	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><td>
								<img src="img/x.gif" class="unit u31" title="Rata" alt="Rata">
							</td><td>
								<img src="img/x.gif" class="unit u32" title="Araña" alt="Araña">
							</td><td>
								<img src="img/x.gif" class="unit u33" title="Serpiente" alt="Serpiente">
							</td><td>
								<img src="img/x.gif" class="unit u34" title="Murciélago" alt="Murciélago">
							</td><td>
								<img src="img/x.gif" class="unit u35" title="Jabalí" alt="Jabalí">
							</td><td>
								<img src="img/x.gif" class="unit u36" title="Lobo" alt="Lobo">
							</td><td>
								<img src="img/x.gif" class="unit u37" title="Oso" alt="Oso">
							</td><td>
								<img src="img/x.gif" class="unit u38" title="Cocodrilo" alt="Cocodrilo">
							</td><td>
								<img src="img/x.gif" class="unit u39" title="Tigre" alt="Tigre">
							</td><td>
								<img src="img/x.gif" class="unit u40" title="Elefante" alt="Elefante">
							</td></tr>
				</thead>
				<tbody>
					<tr>
						<th>
							Tropas
						</th><td <?php if (!$form->getValue('a2_31')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_31'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_32')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_32'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_33')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_33'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_34')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_34'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_35')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_35'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_36')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_36'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_37')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_37'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_38')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_38'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_39')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_39'), 0, ",", ".");} ?></td>
                                <td <?php if (!$form->getValue('a2_40')) { echo "class=\"none\">0"; }else{ echo ">".number_format((int)$form->getValue('a2_40'), 0, ",", ".");} ?></td></tr>
					<tr>
						<th>
							Bajas
						</th><td <?php if (!$troops = $form->getValue('a2_31')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_32')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_33')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_34')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_35')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_36')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_37')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_38')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_39')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td>
                        <td <?php if (!$troops = $form->getValue('a2_40')) { echo "class=\"none\">0"; }else{ echo ">".number_format($dead = round($troops * $_POST['result'][2]), 0, ",", ".");} ?></td></tr>
				</tbody>
			</table>
