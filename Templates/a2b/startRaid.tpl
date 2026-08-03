<?php

    $slots = $_POST['slot'];
    $lid = (int)$_POST['lid'];
    // La tribu se toma de la sesion (autoritativa) y no del POST: la aldea de
    // la lista de granjas ya se valido como propia mas abajo, asi que su tribu
    // siempre coincide con la del usuario logueado.
    $tribe = (int)$session->tribe;
    $getFLData = $database->getFLData($lid);
    // La lista de granjas debe pertenecer al usuario logueado: sin esta
    // verificacion cualquiera podia disparar un saqueo usando la aldea y las
    // tropas de otro jugador con solo adivinar su `lid`.
    if(!is_array($getFLData) || (int)$getFLData['owner'] !== (int)$session->uid) {
        header("Location: build.php?id=39&t=99");
        exit;
    }
    $sql = "SELECT * FROM ".TB_PREFIX."raidlist WHERE lid = ".$lid." order by id asc";
	$array = $database->query_return($sql);
    foreach($array as $row){
	$sql1 = mysql_fetch_array(mysql_query("SELECT * FROM ".TB_PREFIX."units WHERE vref = ".$getFLData['wref']));
        $sid = $row['id'];
        $wref = $row['towref'];
        $t1 = $row['t1'];$t2 = $row['t2'];$t3 = $row['t3'];$t4 = $row['t4'];$t5 = $row['t5'];
        $t6 = $row['t6'];$t7 = $row['t7'];$t8 = $row['t8'];$t9 = $row['t9'];$t10 = $row['t10'];
        $t11 = 0;
		$villageOwner = $database->getVillageField($wref,'owner');
		$userAccess = $database->getUserField($villageOwner,'access',0);
		if($userAccess != '0' && $userAccess != '8' && $userAccess != '9'){
		if($tribe == 1){ $uname = "u"; } elseif($tribe == 2){ $uname = "u1"; } elseif($tribe == 3){ $uname = "u2"; }
		if($tribe == 1){ $uname1 = "u1"; } elseif($tribe == 2){ $uname1 = "u2"; } elseif($tribe == 3){ $uname1 = "u3"; }
		if($tribe == 1){ $uname2 = ""; } elseif($tribe == 2){ $uname2 = "1"; } elseif($tribe == 3){ $uname2 = "2"; }
        if($sql1[$uname.'1']>=$t1 && $sql1[$uname.'2']>=$t2 && $sql1[$uname.'3']>=$t3 && $sql1[$uname.'4']>=$t4 && $sql1[$uname.'5']>=$t5 && $sql1[$uname.'6']>=$t6 && $sql1[$uname.'7']>=$t7 && $sql1[$uname.'8']>=$t8 && $sql1[$uname.'9']>=$t9 && $sql1[$uname1.'0']>=$t10 && $sql1['hero']>=$t11){
        if($_POST['slot'.$sid]=='on'){
            $ckey = $generator->generateRandStr(6);
            $id = $database->addA2b($ckey,time(),$wref,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11,4);
            
            $data = $database->getA2b($ckey, time()); 
            
            $eigen = $database->getCoor($getFLData['wref']);
            $from = array('x'=>$eigen['x'], 'y'=>$eigen['y']);
            $ander = $database->getCoor($data['to_vid']);
            $to = array('x'=>$ander['x'], 'y'=>$ander['y']);
            $start = ($tribe-1)*10+1;
            $end = ($tribe*10);
            
            $speeds = array();
            $scout = 1;
    
            //find slowest unit.            
            for($i=1;$i<=10;$i++){
                if ($data['u'.$i]){
                    if($data['u'.$i] != '' && $data['u'.$i] > 0){
                        if($unitarray) { reset($unitarray); }
                        $unitarray = $GLOBALS["u".(($tribe-1)*10+$i)];
                        $speeds[] = $unitarray['speed'];
                    }
                }
            }
            
            $artefact = count($database->getOwnUniqueArtefactInfo2($getFLData['owner'],2,3,0));
			$artefact1 = count($database->getOwnUniqueArtefactInfo2($getFLData['wref'],2,1,1));
			$artefact2 = count($database->getOwnUniqueArtefactInfo2($getFLData['owner'],2,2,0));
			if($artefact > 0){
			$fastertroops = 3;
			}else if($artefact1 > 0){
			$fastertroops = 2;
			}else if($artefact2 > 0){
			$fastertroops = 1.5;
			}else{
			$fastertroops = 1;
			}
			$time = round($generator->procDistanceTime($from,$to,min($speeds),1)/$fastertroops);
			$foolartefact = $database->getFoolArtefactInfo(2,$village->wid,$session->uid);
			if(count($foolartefact) > 0){
			foreach($foolartefact as $arte){
			if($arte['bad_effect'] == 1){
			$time *= $arte['effect2'];
			}else{
			$time /= $arte['effect2'];
			$time = round($time);
			}
			}
			}
			if($data['u7'] > 0){
            $ctar1 = 99;
			}else{
			$ctar1 = 0;
			}
            $ctar2 = 0; 
            $reference = $database->addAttack(($getFLData['wref']),$data['u1'],$data['u2'],$data['u3'],$data['u4'],$data['u5'],$data['u6'],$data['u7'],$data['u8'],$data['u9'],$data['u10'],$data['u11'],$data['type'],$ctar1,$ctar2,0);
            $totalunits = $data['u1']+$data['u2']+$data['u3']+$data['u4']+$data['u5']+$data['u6']+$data['u7']+$data['u8']+$data['u9']+$data['u10']+$data['u11'];
			$database->modifyUnit($getFLData['wref'], $uname2.'1', $data['u1'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'2', $data['u2'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'3', $data['u3'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'4', $data['u4'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'5', $data['u5'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'6', $data['u6'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'7', $data['u7'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'8', $data['u8'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'9', $data['u9'], 0);
			$database->modifyUnit($getFLData['wref'], $uname2.'10', $data['u10'], 0);
			$database->modifyUnit($getFLData['wref'], 'hero', $data['u11'], 0);

			$sentAt = time();
			$database->addMovement(3,$getFLData['wref'],$data['to_vid'],$reference,$sentAt,($time+$sentAt));
        }    
    }
	}
	}
header("Location: build.php?id=39&t=99");
?>
