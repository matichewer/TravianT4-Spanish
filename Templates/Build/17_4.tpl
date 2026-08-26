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

if(!empty($market->routeError['code'])){
	$routeErrorParams = isset($market->routeError['params']) && is_array($market->routeError['params']) ? $market->routeError['params'] : array();
	switch($market->routeError['code']){
		case 'noresources':
			$routeErrorText = 'Indicá al menos un recurso para enviar.';
			break;
		case 'merchants':
			// "need" es el pico de mercaderes que estarian de viaje a la vez si esto se
			// guarda (esta ruta mas las que ya existen), no una simple suma: dos salidas
			// que nunca coinciden en el tiempo pueden compartir mercaderes.
			$routeErrorText = 'Mercaderes insuficientes: en el momento de mayor superposición esta aldea necesitaría '
				.(isset($routeErrorParams['need']) ? (int)$routeErrorParams['need'] : 0)
				.' mercaderes viajando a la vez, y el Mercado solo tiene '
				.(isset($routeErrorParams['free']) ? (int)$routeErrorParams['free'] : 0).' en total.';
			break;
		case 'target':
			$routeErrorText = 'La aldea de destino no es válida.';
			break;
		case 'invalid':
			$routeErrorText = 'Revisá los valores ingresados.';
			break;
		default:
			$routeErrorText = 'No se pudo guardar la ruta comercial. Intentalo de nuevo.';
	}
	echo '<p class="error"><b>'.htmlspecialchars($routeErrorText,ENT_QUOTES,'UTF-8').'</b></p>';
}

if(isset($_GET['create'])){
// Todas las rutas de la aldea tienen que caber a la vez en el Mercado: sin este dato a la
// vista, pasarse de capacidad era la otra forma de que "guardar" no hiciera nada. Lo que
// se cuenta es el momento de mayor superposición, la misma cuenta que valida el guardado:
// dos salidas que nunca coinciden en el tiempo comparten los mismos mercaderes.
$routeMerchantsUsed = (int)$market->routeMerchantsCommitted();
echo '<p>Mercaderes libres en el momento de mayor superposición: '.max(0,(int)$market->merchant - $routeMerchantsUsed).' de '.(int)$market->merchant
	.' (cada uno transporta '.round($market->maxcarry).' recursos). Los mercaderes de una ruta'
	.' sólo están ocupados mientras viajan, así que dos horarios que no se pisan pueden usar los mismos.</p>';
include("17_create.tpl");
}else if(isset($_GET['action']) && $_GET['action'] === 'editRoute' && isset($_GET['routeid'])){
// Una ruta con varios horarios es varias filas: se piden todos los ids del grupo
// (routeid[]=A&routeid[]=B&...) y se valida que TODOS existan y sean del jugador y de
// esta aldea, no solo el primero.
$requestedRouteIds = array();
foreach((is_array($_GET['routeid']) ? $_GET['routeid'] : array($_GET['routeid'])) as $rawId) {
	if(is_scalar($rawId) && ctype_digit((string)$rawId)) {
		$requestedRouteIds[] = (int)$rawId;
	}
}
$requestedRouteIds = array_values(array_unique($requestedRouteIds));
$edited_routes = !empty($requestedRouteIds) ? $database->getTradeRoutesByIds($requestedRouteIds) : array();
$validGroup = !empty($requestedRouteIds) && count($edited_routes) === count($requestedRouteIds);
if($validGroup){
	foreach($edited_routes as $editedRouteRow){
		if((int)$editedRouteRow['uid'] !== (int)$session->uid || (int)$editedRouteRow['from'] !== (int)$village->wid){
			$validGroup = false;
			break;
		}
	}
}
if($validGroup){
	// Orden estable por horario, no por el orden en que llegaron los ids: asi la
	// posicion de cada horario en el formulario es predecible.
	usort($edited_routes,function($a,$b){
		return ((int)$a['start']*3600 + (int)$a['start_minute']*60) <=> ((int)$b['start']*3600 + (int)$b['start_minute']*60);
	});
	$edited_routes = array_values($edited_routes);
	include("17_edit.tpl");
} else {
	header("Location: build.php?gid=17&t=4");
	exit;
}
}else{
?>

<style type="text/css">
div#build.gid17 table.tradeRoutes col.resourcesColumn { width: 120px; }
div#build.gid17 table.tradeRoutes .routeResource {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	white-space: nowrap;
}
div#build.gid17 table.tradeRoutes .routeResource img { flex: 0 0 auto; }
</style>
<table id="npc" class="tradeRoutes" cellpadding="1" cellspacing="1">
<colgroup>
<col class="resourcesColumn">
<col>
<col>
<col>
<col>
<col>
</colgroup>
<thead>
<tr>
<th>Recursos</th>
<th>Destino</th>
<th>Horarios</th>
<th>Mercaderes</th>
<th>Envíos</th>
<th>Acción</th>
</tr></thead><tbody>
<?php
// Solo las rutas que salen de ESTA aldea: las que salen de otras aldeas propias se
// gestionan entrando a esa otra aldea y abriendo su propio Mercado, asi que no hace
// falta repetirlas aca ni distinguir "propia vs ajena" en cada fila: todo lo que
// aparece en esta tabla es siempre propio.
$routes = $database->getTradeRoute($session->uid,$village->wid);
    if(count($routes) == 0) {
    echo "<tr><td colspan=\"6\" class=\"none\">No hay rutas comerciales activas.</td></tr>";
    }else{
$routeIntervalLabel = function($seconds){
	$seconds = (int)$seconds;
	if($seconds % 3600 === 0){
		$hours = (int)($seconds / 3600);
		return $hours === 1 ? 'hora' : $hours.' horas';
	}
	$minutes = (int)($seconds / 60);
	return $minutes === 1 ? 'minuto' : $minutes.' minutos';
};
// Una ruta con varios horarios es, por dentro, una fila por horario: se agrupan las
// que comparten destino+recursos+envios para mostrar un solo renglon con todos sus
// horarios juntos, en vez de repetir la descripcion una vez por horario.
$routeGroups = array();
$routeGroupOrder = array();
foreach($routes as $route){
	$groupKey = $route['wid'].'|'.$route['wood'].'|'.$route['clay'].'|'.$route['iron'].'|'.$route['crop'].'|'.$route['deliveries'];
	if(!isset($routeGroups[$groupKey])){
		$routeGroups[$groupKey] = array();
		$routeGroupOrder[] = $groupKey;
	}
	$routeGroups[$groupKey][] = $route;
}
foreach($routeGroupOrder as $groupKey){
	$groupRoutes = $routeGroups[$groupKey];
	usort($groupRoutes,function($a,$b){
		return ((int)$a['start']*3600 + (int)$a['start_minute']*60) <=> ((int)$b['start']*3600 + (int)$b['start_minute']*60);
	});
	$firstRoute = $groupRoutes[0];
	$groupIdsQuery = '';
	foreach($groupRoutes as $groupRoute){
		$groupIdsQuery .= '&amp;routeid%5B%5D='.(int)$groupRoute['id'];
	}
?>
<tr>
<td>
<?php
$routeResourceLabels = array(1=>'Madera',2=>'Barro',3=>'Hierro',4=>'Cereal');
$routeResourceValues = array(1=>$firstRoute['wood'],2=>$firstRoute['clay'],3=>$firstRoute['iron'],4=>$firstRoute['crop']);
$routeResourceLines = array();
foreach($routeResourceLabels as $resIndex => $resLabel){
	$routeResourceLines[] = '<span class="routeResource"><img src="'.GP_LOCATE.'img/r/'.$resIndex.'.gif" alt="'.$resLabel.'" title="'.$resLabel.'"><span>'.number_format((int)$routeResourceValues[$resIndex],0,',','.').'</span></span>';
}
echo implode('',$routeResourceLines);
?>
</td>
<td>
<?php
$routeVillageName = htmlspecialchars((string)$database->getVillageField($firstRoute['wid'],"name"),ENT_QUOTES,'UTF-8');
echo "<a href=karte.php?d=".(int)$firstRoute['wid']."&amp;c=".$generator->getMapCheck($firstRoute['wid']).">".$routeVillageName."</a>";
?>
</td>
<td>
<?php
$scheduleTimes = array();
$scheduleSeconds = array();
foreach($groupRoutes as $groupRoute){
	$scheduleTimes[] = sprintf('%02d:%02d',(int)$groupRoute['start'],(int)$groupRoute['start_minute']);
	$scheduleSeconds[] = (int)$groupRoute['start']*3600 + (int)$groupRoute['start_minute']*60;
}
$scheduleSummary = implode(', ',$scheduleTimes);
$scheduleCount = count($scheduleSeconds);
if($scheduleCount >= 3){
	$interval = $scheduleSeconds[1] - $scheduleSeconds[0];
	$regular = $interval > 0;
	for($scheduleIndex = 2; $regular && $scheduleIndex < $scheduleCount; $scheduleIndex++){
		$regular = ($scheduleSeconds[$scheduleIndex] - $scheduleSeconds[$scheduleIndex - 1]) === $interval;
	}
	if($regular){
		$wrapInterval = 86400 - $scheduleSeconds[$scheduleCount - 1] + $scheduleSeconds[0];
		if($wrapInterval === $interval){
			$scheduleSummary = $interval === 3600 ? 'Cada hora' : 'Cada '.$routeIntervalLabel($interval);
		}else{
			$scheduleSummary = $scheduleTimes[0].'–'.$scheduleTimes[$scheduleCount - 1].', cada '.$routeIntervalLabel($interval);
		}
	}else if($scheduleCount > 3){
		$scheduleSummary = implode(', ',array_slice($scheduleTimes,0,3)).' y '.($scheduleCount - 3).' más';
	}
}
$scheduleTooltip = 'Horarios: '.implode(', ',$scheduleTimes);
echo '<span title="'.htmlspecialchars($scheduleTooltip,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($scheduleSummary,ENT_QUOTES,'UTF-8').'</span>';
?>
</td>
<td>
<?php
// La misma cantidad de mercaderes sale en CADA horario (todas las filas del grupo
// comparten recursos): mostrar el total de la aldea sumando por horario daria una
// cifra que nunca coincide con "Mercaderes X/Y" de arriba, que es por salida.
$routeMerchantsPerDeparture = (int)$market->routeMerchants($firstRoute);
$routeDeliveries = (int)$firstRoute['deliveries'];
echo $routeMerchantsPerDeparture.' '.($routeMerchantsPerDeparture === 1 ? 'mercader' : 'mercaderes').' por salida';
?>
</td>
<td>
<?php
// Columna aparte y siempre visible, tambien en x1: sin esto, dos rutas identicas
// salvo por los envios se ven exactamente iguales en la lista y hay que abrir
// "editar" para notar la diferencia.
echo '<span title="Cada vez que sale esta ruta, esos mercaderes hacen '.$routeDeliveries.' '.($routeDeliveries === 1 ? 'viaje' : 'viajes seguidos').' antes de volver.">x'.$routeDeliveries.'</span>';
?>
</td>
<td>
<a href="build.php?id=<?php echo $id; ?>&amp;t=4&amp;action=editRoute<?php echo $groupIdsQuery; ?>">Editar</a><br>
<a href="build.php?gid=17&amp;t=4&amp;action=delRoute<?php echo $groupIdsQuery; ?>&amp;a=<?php echo urlencode($session->mchecker); ?>" onclick="return confirm('¿Eliminar esta ruta comercial<?php echo count($groupRoutes) > 1 ? ' y sus '.count($groupRoutes).' horarios' : ''; ?>?');">Eliminar</a>
</td>
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
