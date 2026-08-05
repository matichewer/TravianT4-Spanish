<?php
// Selector rapido de destino: aldeas propias + aldeas de la alianza.
// Se usa en el mercado (Build/17.tpl) y en el envio de tropas (a2b/search.tpl).
// El select no tiene name: solo completa los campos x/y del formulario "snd",
// asi que el POST y la validacion del servidor siguen siendo los de siempre.
$quickTargetOwn = '';
foreach($database->getOwnVillagesWithCoor($session->uid) as $quickTargetVillage) {
	if((int)$quickTargetVillage['wref'] == (int)$village->wid) {
		continue;
	}
	$quickTargetOwn .= '<option value="'.(int)$quickTargetVillage['x'].'|'.(int)$quickTargetVillage['y'].'">'
		.htmlspecialchars((string)$quickTargetVillage['name'],ENT_QUOTES,'UTF-8')
		.' ('.(int)$quickTargetVillage['x'].'|'.(int)$quickTargetVillage['y'].')</option>';
}
$quickTargetAlly = '';
if((int)$session->alliance > 0) {
	foreach($database->getAllianceVillagesWithCoor($session->alliance,$session->uid) as $quickTargetVillage) {
		$quickTargetAlly .= '<option value="'.(int)$quickTargetVillage['x'].'|'.(int)$quickTargetVillage['y'].'">'
			.htmlspecialchars((string)$quickTargetVillage['username'],ENT_QUOTES,'UTF-8').': '
			.htmlspecialchars((string)$quickTargetVillage['name'],ENT_QUOTES,'UTF-8')
			.' ('.(int)$quickTargetVillage['x'].'|'.(int)$quickTargetVillage['y'].')</option>';
	}
}
if($quickTargetOwn !== '' || $quickTargetAlly !== '') {
?>
<table cellpadding="0" cellspacing="0" class="transparent compact">
				<tbody>
					<tr>
						<td>
							<span>Destino rápido:</span>
							<select class="quickTargetSelect" style="display:block;width:100%;margin-top:2px;">
								<option value="">-- Elegir aldea --</option>
<?php if($quickTargetOwn !== '') { ?>
								<optgroup label="Mis aldeas"><?php echo $quickTargetOwn; ?></optgroup>
<?php } if($quickTargetAlly !== '') { ?>
								<optgroup label="Alianza"><?php echo $quickTargetAlly; ?></optgroup>
<?php } ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
<script type="text/javascript">
(function()
{
	var selects = document.getElementsByClassName('quickTargetSelect');
	for (var i = 0; i < selects.length; i++)
	{
		selects[i].onchange = function()
		{
			if (!this.value)
			{
				return;
			}
			var form = this.form || document.snd;
			if (!form || !form.x || !form.y)
			{
				return;
			}
			var coords = this.value.split('|');
			form.x.value = coords[0];
			form.y.value = coords[1];
			if (form.dname)
			{
				form.dname.value = '';
			}
		};
	}
})();
</script>
<?php } ?>
