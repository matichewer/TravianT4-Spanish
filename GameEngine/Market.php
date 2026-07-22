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
                $this->sendResource($post);
                break;
                case "mk2":
                if(isset($post['a']) && hash_equals($session->mchecker,(string)$post['a'])) {
                    $session->changeChecker();
                    $this->addOffer($post);
                }
                break;
                case "mk3":
                $this->tradeResource($post);
                break;
            } 
        } 
    } 
     
    public function procRemove($get) { 
        global $database,$village,$session; 
        if(isset($get['t']) && $get['t'] == 1 && isset($get['a']) && isset($get['g']) && hash_equals($session->mchecker,(string)$get['a'])) {
            $session->changeChecker();
            $this->acceptOffer($get);
            return;
        }
        if(isset($get['t']) && $get['t'] == 1) { 
            $this->filterNeed($get); 
        } 
        else if(isset($get['t']) && $get['t'] == 2 && isset($get['a']) && isset($get['del']) && hash_equals($session->mchecker,(string)$get['a'])) {
            $session->changeChecker();
            $this->cancelOffer($get['del']);
            header("Location: build.php?id=".$get['id']."&t=".$get['t']); 
        } 
    } 
     
    public function merchantAvail() { 
        return max(0,$this->merchant - $this->used);
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
        $wtrans = (isset($post['r1']) && $post['r1'] != "")? $post['r1'] : 0; 
        $ctrans = (isset($post['r2']) && $post['r2'] != "")? $post['r2'] : 0; 
        $itrans = (isset($post['r3']) && $post['r3'] != "")? $post['r3'] : 0; 
        $crtrans = (isset($post['r4']) && $post['r4'] != "")? $post['r4'] : 0; 
        $wtrans = str_replace("-", "", $wtrans); 
        $ctrans = str_replace("-", "", $ctrans); 
        $itrans = str_replace("-", "", $itrans); 
        $crtrans = str_replace("-", "", $crtrans); 
        $availableWood = $database->getWoodAvailable($village->wid); 
        $availableClay = $database->getClayAvailable($village->wid); 
        $availableIron = $database->getIronAvailable($village->wid); 
        $availableCrop = $database->getCropAvailable($village->wid); 
        if($availableWood >= $post['r1'] AND $availableClay >= $post['r2'] AND $availableIron >= $post['r3'] AND $availableCrop >= $post['r4']){ 
         
        $resource = array($wtrans,$ctrans,$itrans,$crtrans); 
        $reqMerc = ceil((array_sum($resource)-0.1)/$this->maxcarry); 

        if($this->merchantAvail() != 0 && $reqMerc <= $this->merchantAvail()) { 
					$id = $post['vid'];
                    $coor = $database->getCoor($id); 
                if($database->getVillageState($id)) {
					$resdata = "".$resource[0].",".$resource[1].",".$resource[2].",".$resource[3]."";
					$timetaken = $generator->procDistanceTime($coor,$village->coor,$session->tribe,0); 
	                $reference = $database->sendResource($resource[0],$resource[1],$resource[2],$resource[3],$reqMerc,0); 
		            $database->modifyResource($village->wid,$resource[0],$resource[1],$resource[2],$resource[3],0); 
			        $database->addMovement(0,$village->wid,$id,$reference,$resdata,time()+$timetaken,$post['send3']);
				    $logging->addMarketLog($village->wid,1,array($resource[0],$resource[1],$resource[2],$resource[3],$id));
				}
        } 
        header("Location: build.php?id=".$post['id']); 
    } else {} 
    } 
     
    private function addOffer($post) { 
        global $database,$village,$session; 
        $gtype = isset($post['rid1']) ? (int)$post['rid1'] : 0;
        $wtype = isset($post['rid2']) ? (int)$post['rid2'] : 0;
        $gamt = $this->positiveInteger(isset($post['m1']) ? $post['m1'] : null);
        $wamt = $this->positiveInteger(isset($post['m2']) ? $post['m2'] : null);

        if(!$this->validResourceType($gtype) || !$this->validResourceType($wtype) || $gtype == $wtype || $gamt == 0 || $wamt == 0) {
            header("Location: build.php?id=".$post['id']."&t=".$post['t']);
            return;
        }

        $time = 0;
        if(isset($post['d1'])) {
            $hours = $this->positiveInteger(isset($post['d2']) ? $post['d2'] : null);
            if($hours == 0 || $hours > 99) {
                header("Location: build.php?id=".$post['id']."&t=".$post['t']);
                return;
            }
            $time = $hours * 3600;
        }

        $resource = $this->resourceArray($gtype,$gamt);
        $reqMerc = $this->requiredMerchants($gamt);
        if($reqMerc == 0 || $reqMerc > $this->merchantAvail()) {
            header("Location: build.php?id=".$post['id']."&t=".$post['t']);
            return;
        }

        if($database->deductResourcesIfAvailable($village->wid,$resource[1],$resource[2],$resource[3],$resource[4])) {
            $alliance = (isset($post['ally']) && $post['ally'] == 1 && $session->plus)? (int)$session->userinfo['alliance'] : 0;
            $offerId = $database->addMarket($village->wid,$gtype,$gamt,$wtype,$wamt,$time,$alliance,$reqMerc,0);
            if(!$offerId) {
                $database->modifyResource($village->wid,$resource[1],$resource[2],$resource[3],$resource[4],1);
            }
        }
        header("Location: build.php?id=".$post['id']."&t=".$post['t']); 
    } 
     
    private function acceptOffer($get) { 
        global $database,$village,$session,$logging,$generator; 
        $offerId = $this->positiveInteger(isset($get['g']) ? $get['g'] : null);
        $infoarray = $offerId ? $database->getMarketInfo($offerId) : false;
        if(!$this->validOffer($infoarray) || (int)$infoarray['vref'] == (int)$village->wid) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $buyerAlliance = (int)$session->alliance;
        if((int)$infoarray['alliance'] != 0 && (int)$infoarray['alliance'] != $buyerAlliance) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $sellerOwner = (int)$database->getVillageField($infoarray['vref'],"owner");
        if($sellerOwner == 0 || $sellerOwner == (int)$session->uid) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $hiscoor = $database->getCoor($infoarray['vref']);
        if(!is_array($hiscoor)) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }
        $mytime = $generator->procDistanceTime($hiscoor,$village->coor,$session->tribe,0);
        if((int)$infoarray['maxtime'] > 0 && $mytime > (int)$infoarray['maxtime']) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $reqMerc = $this->requiredMerchants((int)$infoarray['wamt']);
        if($reqMerc == 0 || $reqMerc > $this->merchantAvail()) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        if(!$database->claimMarketOffer($offerId,$village->wid,$buyerAlliance)) {
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $currentMerchantAvail = max(0,$this->merchant - $database->totalMerchantUsed($village->wid));
        $myresource = $this->resourceArray((int)$infoarray['wtype'],(int)$infoarray['wamt']);
        $hisresource = $this->resourceArray((int)$infoarray['gtype'],(int)$infoarray['gamt']);
        if($reqMerc > $currentMerchantAvail || !$database->deductResourcesIfAvailable($village->wid,$myresource[1],$myresource[2],$myresource[3],$myresource[4])) {
            $database->releaseMarketOffer($offerId);
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
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
            header("Location: build.php?id=".$get['id']."&t=".$get['t']);
            return;
        }

        $database->removeAcceptedOffer($offerId);
        $logging->addMarketLog($village->wid,2,array($infoarray['vref'],$offerId));
        header("Location: build.php?id=".$get['id']."&t=".$get['t']);
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
        if($session->userinfo['gold'] >= 3) { 
            //kijken of ze niet meer gs invoeren dan ze hebben 
            if (($post['m2'][0]+$post['m2'][1]+$post['m2'][2]+$post['m2'][3])<=(round($village->awood)+round($village->aclay)+round($village->airon)+round($village->acrop))){ 
                $database->setVillageField($village->wid,"wood",$post['m2'][0]); 
                $database->setVillageField($village->wid,"clay",$post['m2'][1]); 
                $database->setVillageField($village->wid,"iron",$post['m2'][2]); 
                $database->setVillageField($village->wid,"crop",$post['m2'][3]); 
                $database->modifyGold($session->uid,3,0); 
                header("Location: build.php?id=".$post['id']."&t=3&c");; 
            } else { 
                header("Location: build.php?id=".$post['id']."&t=3"); 
            } 
        } else {         
            header("Location: build.php?id=".$post['id']."&t=3"); 
        } 
    } 
     
}; 
$market = new Market; 
?>
