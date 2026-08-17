<?php 
################################################################################# 
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ## 
## --------------------------------------------------------------------------- ## 
##  Filename       Market.php                                                  ## 
##  Developed by:  Dzoki                                                       ## 
##  License:       TravianX Project                                            ## 
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ## 
##                                                                             ## 
################################################################################# 
class Market {

    // Tope de horarios que se pueden declarar en un solo guardado de ruta comercial.
    // La reserva de mercaderes ya lo limita en la practica (cada horario extra suma
    // otro reqMerc completo), esto es solo un techo duro contra un payload absurdo.
    const MAX_ROUTE_SCHEDULES = 12;

    // Una sola asignacion al final solo inicializa la ultima propiedad: el resto
    // quedaba en null y disparaba warnings de count() al abrir el mercado por una
    // pestaña que no carga los datos.
    public $onsale = array(), $onmarket = array(), $sending = array(), $recieving = array(), $return = array();
    public $offerDraft = array();
    public $routeDraft = array();
    public $routeError = array();
    public $error = array();
    public $routeReserved = 0;
    public $maxcarry,$merchant,$used;
     
    public function procMarket($post) { 
        global $session;
        // La pantalla de confirmacion permite volver al formulario conservando todos
        // los valores. El boton cancelar no debe pasar por el envio definitivo ni
        // consumir el token de seguridad.
        if(isset($post['cancel'])) {
            return;
        }
        // Las ofertas de otras aldeas las carga filterNeed() cuando se abre la pestaña
        // "Comprar" (procRemove, t=1). Cargarlas aca ademas, por una marca en la sesion,
        // repetia la lista entera —una consulta por oferta— en cada visita a esa pestaña.
        $this->loadMarket();
        if(isset($post['ft'])) {
            switch($post['ft']) {
                case "mk1":
                case "mk2":
                case "mk3":
                    if(!isset($post['a']) || !is_scalar($post['a']) || !hash_equals((string)$session->mchecker,(string)$post['a'])) {
                        $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0,isset($post['t']) ? $post['t'] : null);
                    }
                    $session->changeChecker();
                    if($post['ft'] === "mk1") {
                        $this->sendResource($post);
                    } elseif($post['ft'] === "mk2") {
                        $this->addOffer($post);
                    } else {
                        $this->tradeResource($post);
                    }
                    break;
            }
        }
    } 
     
    public function procRemove($get) { 
        global $database,$village,$session; 
        if(isset($get['t'],$get['a'],$get['g']) && (string)$get['t'] === '1' && is_scalar($get['a']) && hash_equals((string)$session->mchecker,(string)$get['a'])) {
            $session->changeChecker();
            $this->acceptOffer($get);
            return;
        }
        if(isset($get['t']) && (string)$get['t'] === '1') {
            $this->filterNeed($get); 
        } 
        else if(isset($get['t'],$get['a'],$get['del']) && (string)$get['t'] === '2' && is_scalar($get['a']) && hash_equals((string)$session->mchecker,(string)$get['a'])) {
            $session->changeChecker();
            if(!$this->cancelOffer($get['del'])) {
                $this->marketFailure('cancel',isset($get['id']) ? $get['id'] : 0,$get['t']);
            }
            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,$get['t']);
        }
    } 
     
    public function merchantAvail() { 
        return max(0,$this->merchant - $this->used);
    }

    public function procTradeRoutes($post,$get) {
        global $database,$village,$session,$generator;
        $postAction = isset($post['action']) && is_scalar($post['action']) ? (string)$post['action'] : '';
        $getAction = isset($get['action']) && is_scalar($get['action']) ? (string)$get['action'] : '';
        if(in_array($postAction,array('addRoute','editRoute'),true)) {
            $getAction = '';
        } elseif($getAction !== 'delRoute') {
            return;
        }
        if((int)$session->access == BANNED) {
            header("Location: banned.php");
            exit;
        }
        if(!$session->goldclub || count($session->villages) <= 1) {
            $this->redirectToMarket(0,4);
        }

        $request = $postAction !== '' ? $post : $get;
        if(!isset($request['a']) || !is_scalar($request['a']) || !hash_equals((string)$session->mchecker,(string)$request['a'])) {
            $this->redirectToMarket(0,4);
        }
        $session->changeChecker();

        if($getAction === 'delRoute') {
            // Una ruta con varios horarios es varias filas: borrarla del todo es borrar
            // cada id del grupo, no solo uno.
            $rawIds = isset($get['routeid']) ? $get['routeid'] : null;
            foreach(is_array($rawIds) ? $rawIds : array($rawIds) as $rawId) {
                $routeId = $this->positiveInteger($rawId);
                if($routeId) {
                    $database->deleteTradeRouteOwned($routeId,$session->uid,$village->wid);
                }
            }
            $this->redirectToMarket(0,4);
        }

        // Los ids que ya existian en el grupo que se esta editando (una fila por cada
        // horario ya guardado); vacio al crear. Se necesitan antes de armar el destino
        // del redirect (para poder volver al mismo formulario de edicion, no a la
        // lista), antes de excluirlos al validar la capacidad, y para reconciliar el
        // guardado contra el grupo real (que horarios actualizan una fila existente,
        // cuales crean una nueva, cuales sobran y hay que borrar).
        $originalRouteIds = array();
        if($postAction === 'editRoute' && isset($post['original_routeid']) && is_array($post['original_routeid'])) {
            foreach($post['original_routeid'] as $rawId) {
                $rid = $this->positiveInteger($rawId);
                if($rid) {
                    $originalRouteIds[] = $rid;
                }
            }
            $originalRouteIds = array_values(array_unique($originalRouteIds));
        }
        if($postAction === 'editRoute') {
            $backToForm = '';
            if(!empty($originalRouteIds)) {
                $backToForm = 'action=editRoute';
                foreach($originalRouteIds as $rid) {
                    $backToForm .= '&routeid%5B%5D='.$rid;
                }
            }
        } else {
            $backToForm = 'create';
        }

        // Un rechazo repetia el formulario vacio: lo que el jugador ya habia
        // completado (incluidos los horarios que agrego) se perdia y habia que
        // escribirlo de nuevo. Se guarda ANTES de validar nada, asi que sea cual sea
        // el motivo del rechazo, 17_create.tpl/17_edit.tpl pueden recuperarlo. Se
        // borra recien cuando el guardado termina bien.
        $routeDraftKey = $postAction === 'editRoute' ? 'edit'.implode('-',$originalRouteIds) : 'create';
        $_SESSION['tradeRouteDraft'][$village->wid][$routeDraftKey] = array(
            'tvillage' => $this->draftScalar(isset($post['tvillage']) ? $post['tvillage'] : null),
            'r1' => $this->draftScalar(isset($post['r1']) ? $post['r1'] : null),
            'r2' => $this->draftScalar(isset($post['r2']) ? $post['r2'] : null),
            'r3' => $this->draftScalar(isset($post['r3']) ? $post['r3'] : null),
            'r4' => $this->draftScalar(isset($post['r4']) ? $post['r4'] : null),
            'deliveries' => $this->draftScalar(isset($post['deliveries']) ? $post['deliveries'] : null),
            'schedule_hour' => $this->draftScalarArray(isset($post['schedule_hour']) ? $post['schedule_hour'] : null),
            'schedule_minute' => $this->draftScalarArray(isset($post['schedule_minute']) ? $post['schedule_minute'] : null),
        );

        $resource = array();
        foreach(array('r1','r2','r3','r4') as $field) {
            $value = isset($post[$field]) && $post[$field] === '' ? 0 : $this->nonNegativeInteger(isset($post[$field]) ? $post[$field] : null);
            if($value === false) {
                $this->tradeRouteFailure('invalid',array(),$backToForm);
            }
            $resource[] = $value;
        }
        $deliveries = $this->positiveInteger(isset($post['deliveries']) ? $post['deliveries'] : null);
        $reqMerc = $this->requiredMerchants(array_sum($resource));

        $target = $this->positiveInteger(isset($post['tvillage']) ? $post['tvillage'] : null);
        if(!$target || $target === (int)$village->wid || (int)$database->getVillageField($target,'owner') !== (int)$session->uid) {
            $this->tradeRouteFailure('target',array(),$backToForm);
        }

        if($postAction === 'editRoute' && empty($originalRouteIds)) {
            $this->tradeRouteFailure('invalid',array(),$backToForm);
        }
        // Las filas del grupo tienen que ser realmente del jugador y de esta aldea: sin
        // esto, un routeid ajeno colado a mano en el formulario se podria sobreescribir
        // o terminar borrado por sobrar en la reconciliacion de abajo.
        if(!empty($originalRouteIds)) {
            $ownedRoutes = $database->getTradeRoutesByIds($originalRouteIds);
            foreach($originalRouteIds as $rid) {
                if(!isset($ownedRoutes[$rid]) || (int)$ownedRoutes[$rid]['uid'] !== (int)$session->uid || (int)$ownedRoutes[$rid]['from'] !== (int)$village->wid) {
                    $this->tradeRouteFailure('invalid',array(),$backToForm);
                }
            }
        }

        // Editar ahora permite el mismo formulario completo que crear (destino, recursos
        // y varios horarios a la vez).
        $schedules = $this->parseRouteSchedules($post);

        if(array_sum($resource) <= 0) {
            $this->tradeRouteFailure('noresources',array(),$backToForm);
        }
        if($deliveries < 1 || $deliveries > 3 || $reqMerc <= 0 || empty($schedules)) {
            $this->tradeRouteFailure('invalid',array(),$backToForm);
        }

        // Los mercaderes de una ruta se ocupan recien cuando sale, y solo mientras dura
        // el viaje de ida y vuelta: dos salidas que nunca coinciden en el tiempo (por
        // ejemplo, aldeas vecinas con horarios espaciados mas que el viaje de vuelta)
        // pueden compartir el mismo cupo. Antes se sumaba ciegamente el reqMerc de cada
        // horario contra el total del edificio, lo que exigia el doble de mercaderes
        // aunque nunca fueran a estar afuera a la vez. Aca se arma el calendario real
        // de esta aldea (las otras rutas ya guardadas + los horarios de este guardado)
        // y se valida contra el pico de mercaderes simultaneos, no contra la suma.
        $peakEntries = Automation::routeScheduleEntries($village->wid,$this->maxcarry,$session->tribe,$originalRouteIds);
        $newRouteDuration = Automation::routeTripSeconds($village->wid,$target,$session->tribe,$deliveries);
        foreach($schedules as $schedule) {
            $peakEntries[] = array(
                'start' => $schedule['hour'] * 3600 + $schedule['minute'] * 60,
                'duration' => $newRouteDuration,
                'merchants' => $reqMerc,
            );
        }
        $peakDemand = Automation::peakConcurrentMerchants($peakEntries);
        if($peakDemand > $this->merchant) {
            $this->tradeRouteFailure('merchants',array('need'=>$peakDemand,'free'=>$this->merchant),$backToForm);
        }

        // Reconciliacion por posicion, no por id: el horario N actualiza la fila
        // original N si existe, o crea una fila nueva si no. Como todas las filas del
        // grupo comparten destino/recursos/envios, no importa CUAL id original se quede
        // con CUAL horario tras agregar/quitar horarios en el medio de la lista — lo
        // unico que importa es que el CONJUNTO final de horarios sea el correcto, y eso
        // se cumple sin necesitar rastrear que horario "es" cada id.
        foreach($schedules as $scheduleIndex => $schedule) {
            $timestamp = Automation::nextTradeRouteTimestamp($schedule['hour'],$schedule['minute']);
            if(isset($originalRouteIds[$scheduleIndex])) {
                if(!$database->updateTradeRouteOwned($originalRouteIds[$scheduleIndex],$session->uid,$village->wid,$target,$resource[0],$resource[1],$resource[2],$resource[3],$schedule['hour'],$schedule['minute'],$deliveries,$reqMerc,$timestamp)) {
                    $this->tradeRouteFailure('failed',array(),$backToForm);
                }
            } else {
                if(!$database->createTradeRoute($session->uid,$target,$village->wid,$resource[0],$resource[1],$resource[2],$resource[3],$schedule['hour'],$schedule['minute'],$deliveries,$reqMerc,$timestamp)) {
                    $this->tradeRouteFailure('failed',array(),$backToForm);
                }
            }
        }
        // Ids originales que sobran (el jugador quito ese horario del formulario): ya
        // no tienen horario asignado, se borran.
        for($i = count($schedules); $i < count($originalRouteIds); $i++) {
            $database->deleteTradeRouteOwned($originalRouteIds[$i],$session->uid,$village->wid);
        }
        // El guardado termino bien: el borrador de este formulario ya no hace falta.
        unset($_SESSION['tradeRouteDraft'][$village->wid][$routeDraftKey]);
        if(empty($_SESSION['tradeRouteDraft'][$village->wid])) {
            unset($_SESSION['tradeRouteDraft'][$village->wid]);
        }
        $this->redirectToMarket(0,4);
    }

    /**
     * Valor escalar tal cual lo mando el formulario, para guardarlo en el borrador de
     * sesion sin validar (la validacion real es la que ya hace procTradeRoutes; esto
     * es solo para poder repoblar el formulario si rechaza el guardado).
     */
    private function draftScalar($value) {
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * Igual que draftScalar() pero para los arrays de horarios (schedule_hour[] /
     * schedule_minute[]): cada elemento se guarda como string, cualquier elemento que
     * no sea escalar (payload manipulado) se descarta en vez de romper el guardado.
     */
    private function draftScalarArray($value) {
        if(!is_array($value)) {
            return array();
        }
        $result = array();
        foreach($value as $item) {
            if(is_scalar($item)) {
                $result[] = (string)$item;
            }
        }
        return $result;
    }

    /**
     * Uno o mas pares hora/minuto declarados en el formulario (schedule_hour[] /
     * schedule_minute[]). Cada par se guarda como su propia ruta; devuelve un array
     * vacio si falta alguno, no vienen parejos, o algun valor es invalido.
     */
    private function parseRouteSchedules($post) {
        $hours = isset($post['schedule_hour']) && is_array($post['schedule_hour']) ? array_values($post['schedule_hour']) : array();
        $minutes = isset($post['schedule_minute']) && is_array($post['schedule_minute']) ? array_values($post['schedule_minute']) : array();
        if(empty($hours) || count($hours) !== count($minutes) || count($hours) > self::MAX_ROUTE_SCHEDULES) {
            return array();
        }
        $schedules = array();
        foreach($hours as $index => $hourValue) {
            $hour = $this->nonNegativeInteger($hourValue);
            $minute = $this->nonNegativeInteger($minutes[$index]);
            if($hour === false || $hour > 23 || $minute === false || $minute > 59) {
                return array();
            }
            $schedules[] = array('hour'=>$hour,'minute'=>$minute);
        }
        return $schedules;
    }

    // Todos los rechazos al guardar una ruta terminaban en el mismo redirect mudo: la
    // pagina se recargaba igual que si hubiera funcionado. Guardamos el motivo en la
    // sesion para que 17_4.tpl lo muestre una sola vez.
    private function tradeRouteFailure($code,$params=array(),$backToForm='') {
        $_SESSION['tradeRouteError'] = array('code'=>$code,'params'=>$params);
        $this->redirectToMarket(0,4,$backToForm);
    }

    // Lo mismo para enviar, ofertar y aceptar: el rechazo era un redirect mudo y el
    // jugador solo veia que "no pasaba nada". El motivo se muestra una sola vez, en la
    // pestaña donde estaba trabajando.
    private function marketFailure($code,$id,$tab,$params=array()) {
        $_SESSION['marketError'] = array('code'=>$code,'params'=>$params);
        $this->redirectToMarket($id,$tab);
    }

    private function loadMarket() {
        global $session,$building,$bid17,$database,$village;
        $this->recieving = $database->getMovement(0,$village->wid,1);
        $this->sending = $database->getMovement(0,$village->wid,0);
        $this->return  = $database->getMovement(2,$village->wid,1);
        // Un nivel fuera de la tabla (editado desde el panel) dejaba $merchant en null y
        // el Mercado sin poder mover nada; se recorta igual que la capacidad de carga.
        $this->merchant = Automation::marketMerchants($building->getTypeLevel(17));
        // La capacidad se calcula antes que los mercaderes ocupados porque las rutas
        // reservan segun la capacidad de hoy, no la del dia en que se crearon.
        $this->maxcarry = Automation::merchantCarryCapacity($session->tribe,$building->getTypeLevel(28));
        // Ocupados = solo los que estan realmente fuera de casa: viajes de ida, de vuelta
        // y los que esperan a que alguien acepte una oferta. Las rutas comerciales NO
        // ocupan mercaderes hasta que salen (igual que en Travian): descontarlas todo el
        // dia dejaba "Mercaderes 1/16" sin un solo movimiento a la vista, bloqueaba
        // vender y comprar, y ademas los contaba dos veces mientras la ruta viajaba.
        $this->used = (int)$database->totalMerchantUsed($village->wid);
        $this->routeReserved = $this->routeMerchantsCommitted();
        $this->onmarket = $database->getMarket($village->wid,0);
        if(isset($_SESSION['marketOfferDraft'][$village->wid]) && is_array($_SESSION['marketOfferDraft'][$village->wid])) {
            $this->offerDraft = $_SESSION['marketOfferDraft'][$village->wid];
        }
        if(isset($_SESSION['tradeRouteDraft'][$village->wid]) && is_array($_SESSION['tradeRouteDraft'][$village->wid])) {
            $this->routeDraft = $_SESSION['tradeRouteDraft'][$village->wid];
        }
        if(isset($_SESSION['tradeRouteError']) && is_array($_SESSION['tradeRouteError'])) {
            $this->routeError = $_SESSION['tradeRouteError'];
            unset($_SESSION['tradeRouteError']);
        }
        if(isset($_SESSION['marketError']) && is_array($_SESSION['marketError'])) {
            $this->error = $_SESSION['marketError'];
            unset($_SESSION['marketError']);
        }
    }

    /**
     * Borrador del formulario de rutas para $key ('create' o 'edit<id>'), ya
     * normalizado a los mismos tipos que usan los valores por defecto del
     * formulario (17_create.tpl / 17_edit.tpl). null si no hay borrador para esa
     * clave, que es el caso normal (solo existe tras un guardado rechazado).
     */
    public function routeDraftFor($key) {
        global $village;
        if(!isset($this->routeDraft[$key]) || !is_array($this->routeDraft[$key])) {
            return null;
        }
        $draft = $this->routeDraft[$key];
        // Se consume aca, en el momento en que el formulario realmente lo muestra: la
        // proxima vez que se abra este mismo formulario sin haber fallado un guardado
        // recien arranca en blanco, en vez de repetir un intento viejo para siempre
        // (antes solo se borraba al guardar bien, asi que un intento fallido que el
        // jugador abandona queda pegado en la sesion indefinidamente).
        unset($_SESSION['tradeRouteDraft'][$village->wid][$key]);
        if(empty($_SESSION['tradeRouteDraft'][$village->wid])) {
            unset($_SESSION['tradeRouteDraft'][$village->wid]);
        }
        $resource = array();
        foreach(array('r1','r2','r3','r4') as $field) {
            $resource[] = isset($draft[$field]) ? max(0,(int)$draft[$field]) : 0;
        }
        $deliveries = isset($draft['deliveries']) ? (int)$draft['deliveries'] : 1;
        if($deliveries < 1 || $deliveries > 3) {
            $deliveries = 1;
        }
        $hours = isset($draft['schedule_hour']) && is_array($draft['schedule_hour']) ? $draft['schedule_hour'] : array();
        $minutes = isset($draft['schedule_minute']) && is_array($draft['schedule_minute']) ? $draft['schedule_minute'] : array();
        $schedules = array();
        foreach($hours as $index => $hourValue) {
            $schedules[] = array(
                'hour' => max(0,min(23,(int)$hourValue)),
                'minute' => isset($minutes[$index]) ? max(0,min(59,(int)$minutes[$index])) : 0,
            );
        }
        return array(
            'target' => isset($draft['tvillage']) ? (int)$draft['tvillage'] : 0,
            'resource' => $resource,
            'deliveries' => $deliveries,
            'schedules' => empty($schedules) ? null : $schedules,
        );
    }

    /**
     * Horarios de salida de las rutas de esta aldea, para poder explicar en pantalla
     * a que hora se van los mercaderes comprometidos.
     */
    public function routeDepartureHours() {
        global $database,$village;
        $hours = array();
        foreach($database->getTradeRoutesFrom($village->wid) as $route) {
            $key = ((int)$route['start']) * 60 + (int)$route['start_minute'];
            $hours[$key] = sprintf('%02d:%02d',(int)$route['start'],(int)$route['start_minute']);
        }
        ksort($hours);
        return array_values($hours);
    }

    /**
     * Texto del ultimo rechazo, para mostrarlo una sola vez arriba de la pestaña.
     */
    public function errorText() {
        if(empty($this->error['code'])) {
            return '';
        }
        $params = (isset($this->error['params']) && is_array($this->error['params'])) ? $this->error['params'] : array();
        $need = isset($params['need']) ? (int)$params['need'] : 0;
        $free = isset($params['free']) ? (int)$params['free'] : 0;
        switch($this->error['code']) {
            case 'merchants':
                return 'Mercaderes insuficientes: hacen falta '.$need.' y hay '.$free.' libres.';
            case 'resources':
                return 'No hay suficientes recursos en el almacén de esta aldea.';
            case 'target':
                return 'No hay ninguna aldea en ese destino.';
            case 'gone':
                return 'Esa oferta ya no está disponible.';
            case 'taken':
                return 'Otro jugador aceptó esa oferta primero.';
            case 'alliance':
                return 'Esa oferta es sólo para miembros de otra alianza.';
            case 'maxtime':
                return 'Tus mercaderes tardarían más de lo que acepta esa oferta.';
            case 'sameresource':
                return 'Hay que ofrecer y pedir recursos distintos.';
            case 'hours':
                return 'El tiempo máximo tiene que estar entre 1 y 99 horas.';
            case 'cancel':
                return 'No se pudo cancelar la oferta: puede que ya la hayan aceptado.';
            case 'npcstorage':
                return 'El reparto no entra en el almacén de esta aldea.';
            case 'npctotal':
                return 'El reparto pide más recursos de los que hay en el almacén.';
            case 'gold':
                return 'No se pudo hacer el cambio: hacen falta 3 de oro.';
            case 'failed':
                return 'No se pudo completar la operación. Intentalo de nuevo.';
            case 'invalid':
                return 'Revisá los valores ingresados.';
        }
        return 'No se pudo completar la operación. Intentalo de nuevo.';
    }

    /**
     * Mercaderes que hacen falta para mover $amount recursos desde esta aldea, con la
     * capacidad que da la Oficina de comercio ahora mismo. Lo usan las plantillas del
     * Mercado para no repetir la regla del redondeo.
     */
    public function merchantsFor($amount) {
        return Automation::merchantsRequired($amount,$this->maxcarry);
    }

    /**
     * Mercaderes que las rutas de esta aldea tienen comprometidos: el pico de los que
     * estan de viaje a la vez, la misma cuenta que valida el guardado.
     */
    public function routeMerchantsCommitted($excludeRouteIds = array()) {
        global $village,$session;
        return Automation::routeMerchantsCommitted($village->wid,$this->maxcarry,$session->tribe,$excludeRouteIds);
    }

    /**
     * Mercaderes que ocupa una ruta concreta. El listado muestra tambien las rutas que
     * salen de otras aldeas del jugador, y cada aldea tiene su propia Oficina de
     * comercio, asi que la capacidad se toma de la aldea de origen de la ruta.
     */
    public function routeMerchants($route) {
        global $session,$building,$village;
        $from = isset($route['from']) ? (int)$route['from'] : 0;
        $carry = ($from === (int)$village->wid)
            ? $this->maxcarry
            : Automation::merchantCarryCapacity($session->tribe,$building->getTypeLevel(28,$from));
        $amount = (int)$route['wood'] + (int)$route['clay'] + (int)$route['iron'] + (int)$route['crop'];
        return Automation::merchantsRequired($amount,$carry);
    }
     
	    private function sendResource($post) {
	        global $database,$village,$session,$generator,$logging;
	        $resource = array(
	            $this->nonNegativeInteger(isset($post['r1']) ? $post['r1'] : null),
	            $this->nonNegativeInteger(isset($post['r2']) ? $post['r2'] : null),
	            $this->nonNegativeInteger(isset($post['r3']) ? $post['r3'] : null),
	            $this->nonNegativeInteger(isset($post['r4']) ? $post['r4'] : null)
	        );
	        $target = $this->positiveInteger(isset($post['vid']) ? $post['vid'] : null);
	        $sendCount = $this->positiveInteger(isset($post['send3']) ? $post['send3'] : 1);
	        if(!$session->goldclub) {
	            $sendCount = 1;
	        }
	        $id = isset($post['id']) ? $post['id'] : 0;
	        $reqMerc = $this->requiredMerchants(array_sum($resource));
	        if(in_array(false,$resource,true) || $sendCount < 1 || $sendCount > 3 || $reqMerc == 0) {
	            $this->marketFailure('invalid',$id,null);
	        }
	        if($target == 0 || !$database->checkVilExist($target)) {
	            $this->marketFailure('target',$id,null);
	        }
	        if($reqMerc > $this->merchantAvail()) {
	            $this->marketFailure('merchants',$id,null,array('need'=>$reqMerc,'free'=>$this->merchantAvail()));
	        }

	        $coor = $database->getCoor($target);
	        if(!is_array($coor)) {
	            $this->marketFailure('target',$id,null);
	        }
	        if(!$database->deductResourcesIfAvailable($village->wid,$resource[0],$resource[1],$resource[2],$resource[3])) {
	            $this->marketFailure('resources',$id,null);
	        }

	        $resdata = implode(",",$resource);
	        $timetaken = $generator->procDistanceTime($coor,$village->coor,$session->tribe,0);
	        $reference = $database->sendResource($resource[0],$resource[1],$resource[2],$resource[3],$reqMerc,0);
	        $movement = $reference ? $database->addMovement(0,$village->wid,$target,$reference,$resdata,time()+$timetaken,$sendCount,0,0,0,0,$sendCount) : false;
	        if(!$movement) {
	            if($reference) {
	                $database->sendResource($reference,0,0,0,0,1);
	            }
	            $database->modifyResource($village->wid,$resource[0],$resource[1],$resource[2],$resource[3],1);
	            $this->marketFailure('failed',$id,null);
	        }
	        $logging->addMarketLog($village->wid,1,array($resource[0],$resource[1],$resource[2],$resource[3],$target));
	        $this->redirectToMarket($id);
	    }
     
    private function addOffer($post) { 
        global $database,$village,$session; 
        $gtype = isset($post['rid1']) ? (int)$post['rid1'] : 0;
        $wtype = isset($post['rid2']) ? (int)$post['rid2'] : 0;
        $gamt = $this->positiveInteger(isset($post['m1']) ? $post['m1'] : null);
        $wamt = $this->positiveInteger(isset($post['m2']) ? $post['m2'] : null);
        $id = isset($post['id']) ? $post['id'] : 0;
        $tab = isset($post['t']) ? $post['t'] : 2;

        if(!$this->validResourceType($gtype) || !$this->validResourceType($wtype) || $gamt == 0 || $wamt == 0) {
            $this->marketFailure('invalid',$id,$tab);
        }
        if($gtype == $wtype) {
            $this->marketFailure('sameresource',$id,$tab);
        }

        $time = 0;
        if(isset($post['d1'])) {
            $hours = $this->positiveInteger(isset($post['d2']) ? $post['d2'] : null);
            if($hours == 0 || $hours > 99) {
                $this->marketFailure('hours',$id,$tab);
            }
            $time = $hours * 3600;
        }

        $playerAlliance = (int)$session->userinfo['alliance'];
        $alliance = (isset($post['ally']) && (string)$post['ally'] === '1' && $playerAlliance > 0) ? $playerAlliance : 0;
        $_SESSION['marketOfferDraft'][$village->wid] = array(
            'gtype' => $gtype,
            'gamt' => $gamt,
            'wtype' => $wtype,
            'wamt' => $wamt,
            'limited' => $time > 0,
            'hours' => $time > 0 ? (int)($time / 3600) : 2,
            'alliance' => $alliance > 0
        );

        $resource = $this->resourceArray($gtype,$gamt);
        $reqMerc = $this->requiredMerchants($gamt);
        if($reqMerc == 0) {
            $this->marketFailure('invalid',$id,$tab);
        }
        if($reqMerc > $this->merchantAvail()) {
            $this->marketFailure('merchants',$id,$tab,array('need'=>$reqMerc,'free'=>$this->merchantAvail()));
        }

        if(!$database->deductResourcesIfAvailable($village->wid,$resource[1],$resource[2],$resource[3],$resource[4])) {
            $this->marketFailure('resources',$id,$tab);
        }
        $offerId = $database->addMarket($village->wid,$gtype,$gamt,$wtype,$wamt,$time,$alliance,$reqMerc,0);
        if(!$offerId) {
            $database->modifyResource($village->wid,$resource[1],$resource[2],$resource[3],$resource[4],1);
            $this->marketFailure('failed',$id,$tab);
        }
        // La oferta entro: el formulario vuelve vacio en vez de repetir el borrador.
        unset($_SESSION['marketOfferDraft'][$village->wid]);
        $this->redirectToMarket($id,$tab);
    }
     
    private function acceptOffer($get) {
        global $database,$village,$session,$logging,$generator;
        $offerId = $this->positiveInteger(isset($get['g']) ? $get['g'] : null);
        $infoarray = $offerId ? $database->getMarketInfo($offerId) : false;
        $id = isset($get['id']) ? $get['id'] : 0;
        $tab = isset($get['t']) ? $get['t'] : 1;
        if(!$this->validOffer($infoarray) || (int)$infoarray['vref'] == (int)$village->wid) {
            $this->marketFailure('gone',$id,$tab);
        }

        $buyerAlliance = (int)$session->alliance;
        if((int)$infoarray['alliance'] != 0 && (int)$infoarray['alliance'] != $buyerAlliance) {
            $this->marketFailure('alliance',$id,$tab);
        }

        $sellerOwner = (int)$database->getVillageField($infoarray['vref'],"owner");
        if($sellerOwner == 0 || $sellerOwner == (int)$session->uid) {
            $this->marketFailure('gone',$id,$tab);
        }

        $hiscoor = $database->getCoor($infoarray['vref']);
        if(!is_array($hiscoor)) {
            $this->marketFailure('gone',$id,$tab);
        }
        $mytime = $generator->procDistanceTime($hiscoor,$village->coor,$session->tribe,0);
        if((int)$infoarray['maxtime'] > 0 && $mytime > (int)$infoarray['maxtime']) {
            $this->marketFailure('maxtime',$id,$tab);
        }

        $reqMerc = $this->requiredMerchants((int)$infoarray['wamt']);
        if($reqMerc == 0) {
            $this->marketFailure('gone',$id,$tab);
        }
        if($reqMerc > $this->merchantAvail()) {
            $this->marketFailure('merchants',$id,$tab,array('need'=>$reqMerc,'free'=>$this->merchantAvail()));
        }

        if(!$database->claimMarketOffer($offerId,$village->wid,$buyerAlliance)) {
            // Otro jugador la acepto entre que se dibujo la lista y este click.
            $this->marketFailure('taken',$id,$tab);
        }

        // Se relee lo ocupado despues de reclamar la oferta: entre el chequeo de arriba y
        // este punto pudo salir otro envio de esta misma aldea.
        $currentMerchantAvail = max(0,$this->merchant - (int)$database->totalMerchantUsed($village->wid));
        $myresource = $this->resourceArray((int)$infoarray['wtype'],(int)$infoarray['wamt']);
        $hisresource = $this->resourceArray((int)$infoarray['gtype'],(int)$infoarray['gamt']);
        if($reqMerc > $currentMerchantAvail) {
            $database->releaseMarketOffer($offerId);
            $this->marketFailure('merchants',$id,$tab,array('need'=>$reqMerc,'free'=>$currentMerchantAvail));
        }
        if(!$database->deductResourcesIfAvailable($village->wid,$myresource[1],$myresource[2],$myresource[3],$myresource[4])) {
            $database->releaseMarketOffer($offerId);
            $this->marketFailure('resources',$id,$tab);
        }

        $mysendid = $database->sendResource($myresource[1],$myresource[2],$myresource[3],$myresource[4],$reqMerc,0);
        $hissendid = $database->sendResource($hisresource[1],$hisresource[2],$hisresource[3],$hisresource[4],(int)$infoarray['merchant'],0);
        $targettribe = $database->getUserField($database->getVillageField($infoarray['vref'],"owner"),"tribe",0);
        $histime = $generator->procDistanceTime($village->coor,$hiscoor,$targettribe,0);
        $myresdata = implode(",",$myresource);
        $hisresdata = implode(",",$hisresource);
        $mymovement = $mysendid ? $database->addMovement(0,$village->wid,$infoarray['vref'],$mysendid,$myresdata,$mytime+time()) : false;
        $hismovement = $hissendid ? $database->addMovement(0,$infoarray['vref'],$village->wid,$hissendid,$hisresdata,$histime+time()) : false;

        if(!$mymovement || !$hismovement) {
            $this->rollbackAcceptedOffer($offerId,$myresource,$mysendid,$hissendid);
            $this->marketFailure('failed',$id,$tab);
        }

        $database->removeAcceptedOffer($offerId);
        $logging->addMarketLog($village->wid,2,array($infoarray['vref'],$offerId));
        $this->redirectToMarket($id,$tab);
    }

    private function cancelOffer($offerId) {
        global $database,$village;
        $offerId = $this->positiveInteger($offerId);
        $infoarray = $offerId ? $database->getMarketInfo($offerId) : false;
        if(!is_array($infoarray)
            || !isset($infoarray['vref'],$infoarray['gtype'],$infoarray['gamt'],$infoarray['accept'])
            || (int)$infoarray['accept'] !== 0
            || (int)$infoarray['gamt'] < 0
            || (int)$infoarray['vref'] != (int)$village->wid) {
            return false;
        }
        if(!$database->claimOwnedMarketOffer($offerId,$village->wid)) {
            return false;
        }
        if($this->validResourceType($infoarray['gtype'])
            && (int)$infoarray['gamt'] > 0
            && !$database->getResourcesBack($village->wid,(int)$infoarray['gtype'],(int)$infoarray['gamt'])) {
            $database->releaseMarketOffer($offerId);
            return false;
        }
        return $database->removeAcceptedOffer($offerId);
    }

    private function rollbackAcceptedOffer($offerId,$myresource,$mysendid,$hissendid) {
        global $database,$village;
        foreach(array($mysendid,$hissendid) as $sendid) {
            if($sendid) {
                $database->removeMarketMovementBySend($sendid);
                $database->sendResource($sendid,0,0,0,0,1);
            }
        }
        $database->modifyResource($village->wid,$myresource[1],$myresource[2],$myresource[3],$myresource[4],1);
        $database->releaseMarketOffer($offerId);
    }

	    private function positiveInteger($value) {
        if(!is_scalar($value)) {
            return 0;
        }
        $value = (string)$value;
        if($value === '' || !ctype_digit($value)) {
            return 0;
        }
        $value = (int)$value;
	        return ($value > 0 && $value <= 2147483647)? $value : 0;
	    }

	    private function nonNegativeInteger($value) {
	        if(!is_scalar($value)) {
	            return false;
	        }
	        $value = (string)$value;
	        if($value === '' || !ctype_digit($value)) {
	            return false;
	        }
	        $value = (int)$value;
	        return ($value >= 0 && $value <= 2147483647)? $value : false;
	    }

	    private function redirectToMarket($id,$tab=null,$extra='') {
	        global $village;
	        $id = $this->positiveInteger($id);
	        $isMarketField = $id > 0 && isset($village->resarray['f'.$id.'t']) && (int)$village->resarray['f'.$id.'t'] === 17;
	        $location = $isMarketField ? "build.php?id=".$id : "build.php?gid=17";
	        $tab = $this->positiveInteger($tab);
	        if($tab >= 1 && $tab <= 4) {
	            $location .= "&t=".$tab;
	        }
	        if($extra !== '') {
	            $location .= "&".$extra;
	        }
	        header("Location: ".$location);
	        exit;
	    }

    private function validResourceType($type) {
        return in_array((int)$type,array(1,2,3,4),true);
    }

    private function validOffer($offer) {
        return is_array($offer)
            && isset($offer['vref'],$offer['gtype'],$offer['gamt'],$offer['wtype'],$offer['wamt'],$offer['accept'],$offer['alliance'],$offer['merchant'],$offer['maxtime'])
            && (int)$offer['accept'] === 0
            && $this->validResourceType($offer['gtype'])
            && $this->validResourceType($offer['wtype'])
            && (int)$offer['gtype'] !== (int)$offer['wtype']
            && (int)$offer['gamt'] > 0
            && (int)$offer['wamt'] > 0
            && (int)$offer['merchant'] > 0;
    }

    private function resourceArray($type,$amount) {
        $resource = array(1=>0,0,0,0);
        if($this->validResourceType($type) && $amount > 0) {
            $resource[(int)$type] = (int)$amount;
        }
        return $resource;
    }

    private function requiredMerchants($amount) {
        return Automation::merchantsRequired($amount,$this->maxcarry);
    }

    private function loadOnsale() { 
        global $database,$village,$session,$multisort,$generator; 
        $displayarray = $database->getMarket($village->wid,1); 
        $holderarray = array(); 
        foreach($displayarray as $value) { 
            if(!$this->validOffer($value)) {
                continue;
            }
            if((int)$database->getVillageField($value['vref'],"owner") == (int)$session->uid) {
                continue;
            }
            $targetcoor = $database->getCoor($value['vref']); 
            $duration = $generator->procDistanceTime($targetcoor,$village->coor,$session->tribe,0); 
            if($duration <= $value['maxtime'] || $value['maxtime'] == 0) { 
                  $value['duration'] = $duration; 
                  array_push($holderarray,$value); 
            } 
        } 
        $this->onsale = $multisort->sorte($holderarray, "'duration'", true, 2); 
    } 
     
    private function filterNeed($get) { 
        if(isset($get['v']) || isset($get['s']) || isset($get['b'])) { 
            $holder = $holder2 = array(); 
            if(isset($get['v']) && $get['v'] == "1:1") { 
                foreach($this->onsale as $equal) { 
                    if($equal['wamt'] <= $equal['gamt']) { 
                        array_push($holder,$equal); 
                    } 
                } 
            } 
            else { 
                $holder = $this->onsale; 
            } 
            foreach($holder as $sale) { 
                if(isset($get['s']) && isset($get['b'])) { 
                    if($sale['gtype'] == $get['s'] && $sale['wtype'] == $get['b']) { 
                        array_push($holder2,$sale); 
                    } 
                } 
                else if(isset($get['s']) && !isset($get['b'])) { 
                    if($sale['gtype'] == $get['s']) { 
                        array_push($holder2,$sale); 
                    } 
                } 
                else if(isset($get['b']) && !isset($get['s'])) { 
                    if($sale['wtype'] == $get['b']) { 
                        array_push($holder2,$sale); 
                    } 
                } 
                else { 
                    $holder2 = $holder; 
                } 
            } 
            $this->onsale = $holder2; 
        } 
        else {  
         $this->loadOnsale(); 
        } 
    } 
     
	    private function tradeResource($post) {
	        global $session,$database,$village;
	        $id = isset($post['id']) ? $post['id'] : 0;
	        $values = isset($post['m2']) && is_array($post['m2']) ? array_values($post['m2']) : array();
	        if(count($values) !== 4) {
	            $this->marketFailure('invalid',$id,3);
	        }
	        foreach($values as $index => $value) {
	            $value = (is_scalar($value) && (string)$value === '')? 0 : $this->nonNegativeInteger($value);
	            if($value === false) {
	                $this->marketFailure('invalid',$id,3);
	            }
	            $values[$index] = $value;
	        }

	        $current = array(
	            (float)$database->getVillageField($village->wid,"wood"),
	            (float)$database->getVillageField($village->wid,"clay"),
	            (float)$database->getVillageField($village->wid,"iron"),
	            (float)$database->getVillageField($village->wid,"crop")
	        );
	        $available = (int)floor(array_sum($current));
	        $limits = array((int)$village->maxstore,(int)$village->maxstore,(int)$village->maxstore,(int)$village->maxcrop);
	        foreach($values as $index => $value) {
	            if($value > $limits[$index]) {
	                $this->marketFailure('npcstorage',$id,3);
	            }
	        }
	        // la aldea sigue produciendo entre que se dibuja el formulario y se envia,
	        // por eso solo se exige no crear recursos de la nada
	        if(array_sum($values) > $available) {
	            $this->marketFailure('npctotal',$id,3);
	        }
	        $rest = $available - array_sum($values);
	        foreach($values as $index => $value) {
	            if($rest <= 0) {
	                break;
	            }
	            $free = $limits[$index] - $value;
	            if($free <= 0) {
	                continue;
	            }
	            $add = min($free,$rest);
	            $values[$index] = $value + $add;
	            $rest -= $add;
	        }

	        if(!$database->redistributeResourcesWithGold($session->uid,$village->wid,$values[0],$values[1],$values[2],$values[3],3)) {
	            $this->marketFailure('gold',$id,3);
	        }
	        $this->redirectToMarket($id,3,"c");
	    }
     
}; 
$market = new Market; 
?>
