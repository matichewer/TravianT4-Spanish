

<div class="boxes villageList production"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
<table id="production" cellpadding="1" cellspacing="1" style="width:100%;">
	<thead>
		<tr>
			<th colspan="4"><?php echo PROD_HEADER; ?> </th>
		</tr>
	</thead>
	<tbody>
				<tr>
			<td class="ico">
				<img class="r1" src="img/x.gif" alt="<?php echo WOOD; ?>" title="<?php echo WOOD; ?>" />
			</td>
			<td class="res">
				<?php echo WOOD; ?>:
			</td>
			<td class="num">
				<?php echo $village->getProd("wood"); ?>			</td>
			<td class="per" style="text-align:right;width:32px;padding-left:4px;">
				<?php if($session->bonus1 == 1){ echo '<span class="bonus" style="color:#3a3;font-size:10px;" title="'.WOOD.' +25%">+25%</span>'; } ?>			</td>
		</tr>
				<tr>
			<td class="ico">
				<img class="r2" src="img/x.gif" alt="<?php echo CLAY; ?>" title="<?php echo CLAY; ?>" />
			</td>
			<td class="res">
				<?php echo CLAY; ?>:
			</td>
			<td class="num">
				<?php echo $village->getProd("clay"); ?>			</td>
			<td class="per" style="text-align:right;width:32px;padding-left:4px;">
				<?php if($session->bonus2 == 1){ echo '<span class="bonus" style="color:#3a3;font-size:10px;" title="'.CLAY.' +25%">+25%</span>'; } ?>			</td>
		</tr>
				<tr>
			<td class="ico">
				<img class="r3" src="img/x.gif" alt="<?php echo IRON; ?>" title="<?php echo IRON; ?>" />
			</td>
			<td class="res">
				<?php echo IRON; ?>:
			</td>
			<td class="num">
				<?php echo $village->getProd("iron"); ?>			</td>
			<td class="per" style="text-align:right;width:32px;padding-left:4px;">
				<?php if($session->bonus3 == 1){ echo '<span class="bonus" style="color:#3a3;font-size:10px;" title="'.IRON.' +25%">+25%</span>'; } ?>			</td>
		</tr>
				<tr>
			<td class="ico">
				<img class="r4" src="img/x.gif" alt="<?php echo CROP; ?>" title="<?php echo CROP; ?>" />
			</td>
			<td class="res">
				<?php echo CROP; ?>:
			</td>
			<td class="num">
				<?php echo $village->getProd("crop"); ?>			</td>
			<td class="per" style="text-align:right;width:32px;padding-left:4px;">
				<?php if($session->bonus4 == 1){ echo '<span class="bonus" style="color:#3a3;font-size:10px;" title="'.CROP.' +25%">+25%</span>'; } ?>			</td>
		</tr>
			</tbody>
</table>
	</div>
				</div>
