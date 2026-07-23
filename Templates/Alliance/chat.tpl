<?php
////////////// made by TTMTT //////////////

if(isset($aid)) {
$aid = $aid;
}
else {
$aid = $session->alliance;
}
$allianceinfo = $database->getAlliance($aid);
echo "<h1>Alianza - ".$allianceinfo['tag']."</h1>";
include("alli_menu.tpl"); 


?>

<script type="text/javascript">
<?php sajax_show_javascript();?>
function show_data_cb(text) { document.getElementById("div_chat").innerHTML = text; }
function refresh_chat() { x_get_data(show_data_cb); }
function start_it() {
	refresh_chat();
	window.setInterval(refresh_chat, 1000);
}
function add_cb(saved) {
	var input = document.form1.msg;
	var button = document.getElementById("btn_ok");
	button.disabled = false;
	if(saved) {
		input.value = "";
		refresh_chat();
	} else {
		alert("No se pudo enviar el mensaje. Inténtalo de nuevo.");
	}
	input.focus();
}
function send_data() {
	var input = document.form1.msg;
	var message = input.value.replace(/^\s+|\s+$/g, "");
	if(message == "") return false;
	document.getElementById("btn_ok").disabled = true;
	x_add_data(message, add_cb);
	return false;
}
window.addEvent('domready', start_it);
</script>
<form name="form1" onSubmit="return send_data();">
<div id="TitleName" class="chatHeader"><div style="text-align:center;">Chat de alianza</div></div>
<div id="chatContainer" style="position: relative; height: 260px; overflow-x: hidden; overflow-y: auto; background-color: rgb(255, 255, 255); border: 1px solid rgb(192, 192, 192);">
<div id="div_chat" style="padding: 5px;"></div>
</div>
<div id="chat_input" style="margin-top: 10px; margin-bottom: 10px; display: flex; gap: 4px;">
		<input class="text" type="text" name="msg" id="message" maxlength="255" autocomplete="off" style="flex: 1 1 auto; min-width: 0; box-sizing: border-box;" />
		<input type="submit" id="btn_ok" value="Enviar" style="flex: 0 0 auto;" />
</div>
</form>
