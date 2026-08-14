<?php if($session->goldclub == 1 && count($database->getProfileVillages($session->uid)) > 1) { ?>
<h1 class="titleInHeader">Mercado <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>
<div id="build" class="gid17">
<div class="build_desc">
<a href="#" onClick="return Travian.Game.iPopup(17,4);" class="build_logo"> 
	<img class="building big white g17" src="img/x.gif" alt="Mercado" title="Mercado" /> 
</a> 
En el mercado puedes comerciar recursos con otros jugadores. Cuanto mayor sea su nivel, más recursos se pueden transportar al mismo tiempo.</div>  
<?php
$buildingHelpType = 'marketplace';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');

include("upgrade.tpl");
include("17_menu.tpl"); 

if(isset($_GET['create'])){
include("17_create.tpl");
}else if(isset($_GET['action'],$_GET['routeid']) && $_GET['action'] === 'editRoute' && ctype_digit((string)$_GET['routeid'])){
$edited_route = $database->getTradeRoute2((int)$_GET['routeid']);
if(is_array($edited_route) && (int)$edited_route['uid'] === (int)$session->uid && (int)$edited_route['from'] === (int)$village->wid){
include("17_edit.tpl");
} else {
header("Location: build.php?gid=17&t=4");
exit;
}
}else{
?>

<table id="npc" cellpadding="1" cellspacing="1"> 
<thead>
<tr>
<th colspan="2">Descripción</th>
<th>Inicio</th>
<th>Mercaderes</th>
<th>Acción</th>
</tr></thead><tbody>
<?php
$routes = $database->getTradeRoute($session->uid);
    if(count($routes) == 0) {
    echo "<tr><td colspan=\"5\" class=\"none\">No hay rutas comerciales activas.</td></tr>";
    }else{
foreach($routes as $route){
$isOwnVillage = (int)$route['from'] === (int)$village->wid;
?>
<tr>
<th><?php if($isOwnVillage){ ?><a href="build.php?gid=17&amp;t=4&amp;action=delRoute&amp;routeid=<?php echo (int)$route['id']; ?>&amp;a=<?php echo urlencode($session->mchecker); ?>"><img class="del" src="img/x.gif" alt="eliminar" title="eliminar"></a><?php } ?></th>
<th>
<?php
$routeVillageName = htmlspecialchars((string)$database->getVillageField($route['wid'],"name"),ENT_QUOTES,'UTF-8');
echo "Ruta comercial a <a href=karte.php?d=".(int)$route['wid']."&amp;c=".$generator->getMapCheck($route['wid']).">".$routeVillageName."</a><br>";
if(!$isOwnVillage){
$originVillageName = htmlspecialchars((string)$database->getVillageField($route['from'],"name"),ENT_QUOTES,'UTF-8');
echo "<small>Origen: <a href=\"dorf2.php?newdid=".(int)$route['from']."\">".$originVillageName."</a></small><br>";
}
?>
<img src="<?php echo GP_LOCATE; ?>img/r/1.gif" alt="Madera" title="Madera"> <?php echo $route['wood']; ?>  <img src="<?php echo GP_LOCATE; ?>img/r/2.gif" alt="Barro" title="Barro"> <?php echo $route['clay']; ?>  <img src="<?php echo GP_LOCATE; ?>img/r/3.gif" alt="Hierro" title="Hierro"> <?php echo $route['iron']; ?>  <img src="<?php echo GP_LOCATE; ?>img/r/4.gif" alt="Cereal" title="Cereal"> <?php echo $route['crop']; ?>

</th>
<th><?php if($route['start'] > 9){ echo $route['start'];}else{ echo "0".$route['start'];} echo ":00"; ?></th>
<th><?php echo $route['deliveries']."x".$route['merchant']; ?></th>
<th><?php if($isOwnVillage){ ?><a href="build.php?id=<?php echo $id; ?>&t=4&action=editRoute&routeid=<?php echo $route['id']; ?>">» editar</a><?php }else{ ?><small>gestionar desde esa aldea</small><?php } ?></th>
</tr>
<?php }} ?>
        </tbody></table>
<br>
<div class="options">
    <a class="arrow" href="build.php?gid=17&t=4&create"> Crear nueva ruta comercial</a>
</div>
	</div>
<?php
}}else{
header("Location: build.php?gid=17");
exit;
}
?>
