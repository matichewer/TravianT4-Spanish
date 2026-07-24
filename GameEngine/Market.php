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
     
    public $onsale,$onmarket,$sending,$recieving,$return = array(); 
    public $maxcarry,$merchant,$used; 
     
    public function procMarket($post) { 
        global $session;
        $this->loadMarket(); 
        if(isset($_SESSION['loadMarket'])) { 
            $this->loadOnsale(); 
            unset($_SESSION['loadMarket']); 
        }
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
            $this->cancelOffer($get['del']);
            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,$get['t']);
        }
    } 
     
    public function merchantAvail() { 
        return max(0,$this->merchant - $this->used);
    }

    public function procTradeRoutes($post,$get) {
        global $database,$village,$session;
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
            $routeId = $this->positiveInteger(isset($get['routeid']) ? $get['routeid'] : null);
            if($routeId) {
                $database->deleteTradeRouteOwned($routeId,$session->uid,$village->wid);
            }
            $this->redirectToMarket(0,4);
        }

        $resource = array();
        foreach(array('r1','r2','r3','r4') as $field) {
            $value = isset($post[$field]) && $post[$field] === '' ? 0 : $this->nonNegativeInteger(isset($post[$field]) ? $post[$field] : null);
            if($value === false) {
                $this->redirectToMarket(0,4);
            }
            $resource[] = $value;
        }
        $start = $this->nonNegativeInteger(isset($post['start']) ? $post['start'] : null);
        $deliveries = $this->positiveInteger(isset($post['deliveries']) ? $post['deliveries'] : null);
        $reqMerc = $this->requiredMerchants(array_sum($resource));
        if(array_sum($resource) <= 0 || $start === false || $start > 23 || $deliveries < 1 || $deliveries > 3
            || $reqMerc <= 0 || $reqMerc > $this->merchant) {
            $this->redirectToMarket(0,4);
        }
        $timestamp = strtotime('today '.sprintf('%02d',$start).':00:00');
        if($timestamp <= time()) {
            $timestamp += 86400;
        }

        if($postAction === 'addRoute') {
            $target = $this->positiveInteger(isset($post['tvillage']) ? $post['tvillage'] : null);
            if(!$target || $target === (int)$village->wid || (int)$database->getVillageField($target,'owner') !== (int)$session->uid) {
                $this->redirectToMarket(0,4);
            }
            $database->createTradeRoute($session->uid,$target,$village->wid,$resource[0],$resource[1],$resource[2],$resource[3],$start,$deliveries,$reqMerc,$timestamp);
        } else {
            $routeId = $this->positiveInteger(isset($post['routeid']) ? $post['routeid'] : null);
            if($routeId) {
                $database->updateTradeRouteOwned($routeId,$session->uid,$village->wid,$resource[0],$resource[1],$resource[2],$resource[3],$start,$deliveries,$reqMerc,$timestamp);
            }
        }
        $this->redirectToMarket(0,4);
    }
     
    private function loadMarket() { 
        global $session,$building,$bid28,$bid17,$database,$village; 
        $this->recieving = $database->getMovement(0,$village->wid,1); 
        $this->sending = $database->getMovement(0,$village->wid,0); 
        $this->return  = $database->getMovement(2,$village->wid,1); 
        $this->merchant = ($building->getTypeLevel(17) > 0)? $bid17[$building->getTypeLevel(17)]['attri'] : 0; 
        $this->used = $database->totalMerchantUsed($village->wid); 
        $this->onmarket = $database->getMarket($village->wid,0); 
        $this->maxcarry = ($session->tribe == 1)? 500 : (($session->tribe == 2)? 1000 : 750); 
		$this->maxcarry *= TRADER_CAPACITY; 
        if($building->getTypeLevel(28) != 0) { 
            $this->maxcarry *= $bid28[$building->getTypeLevel(28)]['attri'] / 100; 
        } 
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
	        $reqMerc = $this->requiredMerchants(array_sum($resource));
	        if(in_array(false,$resource,true) || $target == 0 || !$database->checkVilExist($target)
	            || $sendCount < 1 || $sendCount > 3 || $reqMerc == 0 || $reqMerc > $this->merchantAvail()) {
	            $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0);
	        }

	        $coor = $database->getCoor($target);
	        if(!is_array($coor) || !$database->deductResourcesIfAvailable($village->wid,$resource[0],$resource[1],$resource[2],$resource[3])) {
	            $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0);
	        }

	        $resdata = implode(",",$resource);
	        $timetaken = $generator->procDistanceTime($coor,$village->coor,$session->tribe,0);
	        $reference = $database->sendResource($resource[0],$resource[1],$resource[2],$resource[3],$reqMerc,0);
	        $movement = $reference ? $database->addMovement(0,$village->wid,$target,$reference,$resdata,time()+$timetaken,$sendCount) : false;
	        if(!$movement) {
	            if($reference) {
	                $database->sendResource($reference,0,0,0,0,1);
	            }
	            $database->modifyResource($village->wid,$resource[0],$resource[1],$resource[2],$resource[3],1);
	            $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0);
	        }
	        $logging->addMarketLog($village->wid,1,array($resource[0],$resource[1],$resource[2],$resource[3],$target));
	        $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0);
	    }
     
    private function addOffer($post) { 
        global $database,$village,$session; 
        $gtype = isset($post['rid1']) ? (int)$post['rid1'] : 0;
        $wtype = isset($post['rid2']) ? (int)$post['rid2'] : 0;
        $gamt = $this->positiveInteger(isset($post['m1']) ? $post['m1'] : null);
        $wamt = $this->positiveInteger(isset($post['m2']) ? $post['m2'] : null);

	        if(!$this->validResourceType($gtype) || !$this->validResourceType($wtype) || $gtype == $wtype || $gamt == 0 || $wamt == 0) {
	            $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0,isset($post['t']) ? $post['t'] : 2);
        }

        $time = 0;
        if(isset($post['d1'])) {
            $hours = $this->positiveInteger(isset($post['d2']) ? $post['d2'] : null);
            if($hours == 0 || $hours > 99) {
	                $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0,isset($post['t']) ? $post['t'] : 2);
            }
            $time = $hours * 3600;
        }

        $resource = $this->resourceArray($gtype,$gamt);
        $reqMerc = $this->requiredMerchants($gamt);
        if($reqMerc == 0 || $reqMerc > $this->merchantAvail()) {
	            $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0,isset($post['t']) ? $post['t'] : 2);
        }

        if($database->deductResourcesIfAvailable($village->wid,$resource[1],$resource[2],$resource[3],$resource[4])) {
            $alliance = (isset($post['ally']) && $post['ally'] == 1 && $session->plus)? (int)$session->userinfo['alliance'] : 0;
            $offerId = $database->addMarket($village->wid,$gtype,$gamt,$wtype,$wamt,$time,$alliance,$reqMerc,0);
            if(!$offerId) {
                $database->modifyResource($village->wid,$resource[1],$resource[2],$resource[3],$resource[4],1);
            }
        }
	        $this->redirectToMarket(isset($post['id']) ? $post['id'] : 0,isset($post['t']) ? $post['t'] : 2);
	    }
     
    private function acceptOffer($get) { 
        global $database,$village,$session,$logging,$generator; 
	        $offerId = $this->positiveInteger(isset($get['g']) ? $get['g'] : null);
	        $infoarray = $offerId ? $database->getMarketInfo($offerId) : false;
	        if(!$this->validOffer($infoarray) || (int)$infoarray['vref'] == (int)$village->wid) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
	        }

        $buyerAlliance = (int)$session->alliance;
        if((int)$infoarray['alliance'] != 0 && (int)$infoarray['alliance'] != $buyerAlliance) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

        $sellerOwner = (int)$database->getVillageField($infoarray['vref'],"owner");
        if($sellerOwner == 0 || $sellerOwner == (int)$session->uid) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

        $hiscoor = $database->getCoor($infoarray['vref']);
        if(!is_array($hiscoor)) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }
        $mytime = $generator->procDistanceTime($hiscoor,$village->coor,$session->tribe,0);
        if((int)$infoarray['maxtime'] > 0 && $mytime > (int)$infoarray['maxtime']) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

        $reqMerc = $this->requiredMerchants((int)$infoarray['wamt']);
        if($reqMerc == 0 || $reqMerc > $this->merchantAvail()) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

        if(!$database->claimMarketOffer($offerId,$village->wid,$buyerAlliance)) {
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

        $currentMerchantAvail = max(0,$this->merchant - $database->totalMerchantUsed($village->wid));
        $myresource = $this->resourceArray((int)$infoarray['wtype'],(int)$infoarray['wamt']);
        $hisresource = $this->resourceArray((int)$infoarray['gtype'],(int)$infoarray['gamt']);
        if($reqMerc > $currentMerchantAvail || !$database->deductResourcesIfAvailable($village->wid,$myresource[1],$myresource[2],$myresource[3],$myresource[4])) {
	            $database->releaseMarketOffer($offerId);
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
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
	            $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
        }

	        $database->removeAcceptedOffer($offerId);
	        $logging->addMarketLog($village->wid,2,array($infoarray['vref'],$offerId));
	        $this->redirectToMarket(isset($get['id']) ? $get['id'] : 0,isset($get['t']) ? $get['t'] : 1);
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
        if($amount <= 0 || $this->maxcarry <= 0) {
            return 0;
        }
        return (int)ceil($amount/$this->maxcarry);
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
	            $this->redirectToMarket($id,3);
	        }
	        foreach($values as $index => $value) {
	            $value = (is_scalar($value) && (string)$value === '')? 0 : $this->nonNegativeInteger($value);
	            if($value === false) {
	                $this->redirectToMarket($id,3);
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
	                $this->redirectToMarket($id,3);
	            }
	        }
	        // la aldea sigue produciendo entre que se dibuja el formulario y se envia,
	        // por eso solo se exige no crear recursos de la nada
	        if(array_sum($values) > $available) {
	            $this->redirectToMarket($id,3);
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
	            $this->redirectToMarket($id,3);
	        }
	        $this->redirectToMarket($id,3,"c");
	    }
     
}; 
$market = new Market; 
?>
