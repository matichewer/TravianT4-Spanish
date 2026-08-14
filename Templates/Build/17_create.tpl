<?php
$routeFormAction = 'addRoute';
$routeFormOriginalRouteIds = array();
$routeFormHeading = 'Crear ruta comercial';
$routeFormTarget = 0;
$routeFormResource = array(0,0,0,0);
$routeFormDeliveries = 1;
$routeFormSchedules = array(array('hour'=>0,'minute'=>0));

// Si el guardado anterior fue rechazado, se recupera lo que ya se habia completado
// (incluidos los horarios agregados) en vez de mostrar el formulario vacio de nuevo.
$routeFormDraft = $market->routeDraftFor('create');
if($routeFormDraft !== null) {
    $routeFormTarget = $routeFormDraft['target'];
    $routeFormResource = $routeFormDraft['resource'];
    $routeFormDeliveries = $routeFormDraft['deliveries'];
    if($routeFormDraft['schedules'] !== null) {
        $routeFormSchedules = $routeFormDraft['schedules'];
    }
}

include('17_route_form.tpl');
