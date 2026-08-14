<?php
// $edited_routes: array (lista, no asociativo) de filas de s1_route que forman el
// grupo que se esta editando -- una ruta con varios horarios es, por dentro, una fila
// por horario, todas comparten destino/recursos/envios y solo difieren en start/
// start_minute. 17_4.tpl ya valido que todas pertenecen al jugador y a esta aldea, y
// las ordeno por horario antes de incluir esta plantilla.
$routeFormAction = 'editRoute';
$routeFormHeading = 'Editar ruta comercial';

$routeFormOriginalRouteIds = array();
$routeFormSchedules = array();
foreach($edited_routes as $editedRoute) {
    $routeFormOriginalRouteIds[] = (int)$editedRoute['id'];
    $routeFormSchedules[] = array('hour'=>(int)$editedRoute['start'],'minute'=>(int)$editedRoute['start_minute']);
}
$firstRoute = $edited_routes[0];
$routeFormTarget = (int)$firstRoute['wid'];
$routeFormResource = array((int)$firstRoute['wood'],(int)$firstRoute['clay'],(int)$firstRoute['iron'],(int)$firstRoute['crop']);
$routeFormDeliveries = (int)$firstRoute['deliveries'];

// Si el guardado anterior fue rechazado, se recuperan destino/recursos/envios y los
// horarios tal como habian quedado (incluidos los agregados o quitados a mano) en vez
// de volver a mostrar el grupo guardado como si nada se hubiera intentado.
$routeFormDraft = $market->routeDraftFor('edit'.implode('-',$routeFormOriginalRouteIds));
if($routeFormDraft !== null) {
    $routeFormTarget = $routeFormDraft['target'];
    $routeFormResource = $routeFormDraft['resource'];
    $routeFormDeliveries = $routeFormDraft['deliveries'];
    if($routeFormDraft['schedules'] !== null) {
        $routeFormSchedules = $routeFormDraft['schedules'];
    }
}

include('17_route_form.tpl');
