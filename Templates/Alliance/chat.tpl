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
function start_it() { x_get_data(show_data_cb); setTimeout("start_it()",1000); }
function add_cb() {}
function send_data() {
var input = document.form1.msg;
if(input.value == "") return false;
x_add_data(input.value,add_cb);
input.value = "";
return false;
}
</script>
<body onLoad="start_it()">
<form name="form1" onSubmit="return send_data();">
<div id="TitleName" class="chatHeader"><div style="text-align:center;">Chat de alianza</div></div>
<div id="chatContainer" style="position: relative; height: 260px; overflow-x: hidden; overflow-y: auto; background-color: rgb(255, 255, 255); border: 1px solid rgb(192, 192, 192);">
<div id="div_chat" style="padding: 5px;"></div>
</div>
<div style="margin-top: 10px; margin-bottom: 10px;">
<table cellpadding="1" cellspacing="1" id="chat_input">
	<tbody><tr>
		<td style="width:100%;">
        <input class="text" type="text" name="msg" id="message" style="display: block; width:100%; box-sizing: border-box;" />
        </td>
        <td>
        <input type="button" id="btn_ok" value="Enviar" onClick="send_data()" />
        </td>
	</tr>
</tbody></table>
</div>
</form>

