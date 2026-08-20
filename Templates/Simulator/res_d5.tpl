<div class="fighterType">
				<div class="boxes boxesColor green"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">Defensor: <?php echo TRIBE5; ?>	</div>
				</div>			</div>
<div class="clear"></div>
<table class="results defender" cellpadding="1" cellspacing="1">
				<thead>
					<tr>
						<td class="role">
						</td><?php for($i = 41; $i <= 50; $i++) { ?><td>
								<img src="img/x.gif" class="unit u<?php echo $i; ?>" alt="<?php echo $technology->unarray[$i]; ?>">
							</td><?php } ?></tr>
				</thead>
				<tbody>
					<tr>
						<th>
							Tropas
						</th><?php for($i = 41; $i <= 50; $i++) {
							$troops = (int)$form->getValue('a2_'.$i);
							echo '<td'.($troops ? '>'.$troops : ' class="none">0').'</td>';
						} ?></tr>
					<tr>
						<th>
							Bajas
						</th><?php for($i = 41; $i <= 50; $i++) {
							$troops = (int)$form->getValue('a2_'.$i);
							echo '<td'.($troops ? '>'.round($troops * $_POST['result'][2]) : ' class="none">0').'</td>';
						} ?></tr>
				</tbody>
			</table>
