<h1 class="titleInHeader">Embajada <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid18">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(18,4);" class="build_logo">
	<img class="building big white g18" src="img/x.gif" alt="Embajada" title="Embajada" />
</a>
La embajada es la oficina de los diplomáticos. Cuanto mayor sea el nivel, más opciones tendrá el rey.</div>

<?php
$buildingHelpType = 'embassy';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');
?>

<?php
include("upgrade.tpl");
if($village->resarray['f'.$id] >= 3 && $session->alliance == 0) {
include("18_create.tpl");
}
if($session->alliance != 0) {
echo "
<table cellpadding=\"1\" cellspacing=\"1\" id=\"ally_info\" class=\"transparent\"><div class=\"clear\"></div>
        <h4 class=\"round\">Alianza</h4>
	<tbody><tr>
		<th>Abreviatura:</th>
		<td>".$alliance->allianceArray['tag']."</td>
	</tr>
	<tr>
		<th>Nombre:</th>
		<td>".$alliance->allianceArray['name']."</td>

	</tr>
	<tr>
		<td colspan=\"2\"><a href=\"allianz.php\" class=\"arrow\">ir a la alianza</a></td>
	</tr></tbody>
	</table>";
    }
    else if($village->resarray['f'.$id] >= 1) {
    ?>
    <div class="clear"></div>
    <h4 class="round">Invitaciones</h4>
<form method="post" action="build.php">
<table cellpadding="1" cellspacing="1" id="join" class="transparent">
<input type="hidden" name="id" value="<?php echo $id ?>">

<thead></thead>
<tbody><tr>
	<?php
    if($alliance->gotInvite) {
    	foreach($alliance->inviteArray as $invite) {
        	 echo "
             <div>
             <button type=\"submit\" name=\"a\" value=\"2\" class=\"icon\" formaction=\"build.php?id=".$id."&amp;d=".(int)$invite['id']."\"><img class=\"del\" src=\"img/x.gif\" alt=\"Eliminar\" title=\"Eliminar\" /></button>
        <a href=\"allianz.php?aid=".$invite['alliance']."\">&nbsp;".$database->getAllianceName($invite['alliance'])."</a>
         <button type=\"submit\" name=\"a\" value=\"3\" class=\"build\" formaction=\"build.php?id=".$id."&amp;d=".(int)$invite['id']."\">
<div class=\"button-container\"><div class=\"button-position\"><div class=\"btl\"><div class=\"btr\"><div class=\"btc\"></div></div></div>
<div class=\"bml\"><div class=\"bmr\"><div class=\"bmc\"></div></div></div><div class=\"bbl\"><div class=\"bbr\"><div class=\"bbc\"></div></div></div>
</div><div class=\"button-contents\">Aceptar</div></div></button></div>";
        }
        }
    else {
		echo "<td colspan=\"3\" class=\"noData\">Sin invitaciones</td>";
        }
        ?>
	</tr></tbody></table>
    <?php 
        if($alliance->gotInvite) {
        echo "<p class=\"error2\">".$form->getError("ally_accept")."</p>";
        } 
    }
?></form><div class="clear"></div><br />
</div>
