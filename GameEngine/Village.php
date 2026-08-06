<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

include("Session.php");
include("Building.php");
include("Market.php");
include_once("Technology.php");
require_once __DIR__."/Hero.php";
class Village {
	
	public $type;
	public $coor = array();
	public $awood,$aclay,$airon,$acrop,$pop,$maxstore,$maxcrop;
	public $wid,$vname,$capital;
	public $resarray = array();
	public $unitarray = array(), $techarray = array(), $unitall = array(), $researching = array(), $abarray = array();
	private $infoarray = array();
	private $production = array();
	private $productionBreakdown = array();
	private $oasisowned = array(), $ocounter = array();
	
	function __construct() {
		global $session;
		if(isset($_SESSION['wid'])) {
			$this->wid = $_SESSION['wid'];
		}
		else {
			$this->wid = $session->villages[0];
		}
		$this->LoadTown();
		$this->calculateProduction();
		$this->processProduction();
		$this->ActionControl();
	}
	
	public function getProd($type) {
		return $this->production[$type];
	}

	public function getProductionBreakdown($type) {
		return isset($this->productionBreakdown[$type]) ? $this->productionBreakdown[$type] : array();
	}
	
	public function getAllUnits($vid) {
		global $database,$technology;
		return $technology->getUnits($database->getUnit($vid),$database->getEnforceVillage($vid,0));
	}
		
	private function LoadTown() {
		global $database,$session,$logging,$technology;
		$this->infoarray = $database->getVillage($this->wid);
		if($this->infoarray['owner'] != $session->uid && !$session->isAdmin) {
			unset($_SESSION['wid']);
			$logging->addIllegal($session->uid,$this->wid,1);
			$this->wid = $session->villages[0];
			$this->infoarray = $database->getVillage($this->wid);
		}
		$this->resarray = $database->getResourceLevel($this->wid);
		$this->coor = $database->getCoor($this->wid);
		$this->type = $database->getVillageType($this->wid);
		$this->oasisowned = $database->getOasis($this->wid);
		$this->ocounter = $this->sortOasis();
		$this->unitarray = $database->getUnit($this->wid);
		$this->enforcetome = $database->getEnforceVillage($this->wid,0);
		$this->enforcetoyou = $database->getEnforceVillage($this->wid,1);
		$this->unitall =  $technology->getAllUnits($this->wid);
		$this->techarray = $database->getTech($this->wid);
		$this->abarray = $database->getABTech($this->wid);
		$this->researching = $database->getResearching($this->wid);
		$this->capital = $this->infoarray['capital'];
		$this->natar = $this->infoarray['natar'];
		$this->currentcel = $this->infoarray['celebration'];
		$this->wid = $this->infoarray['wref'];
		$this->vname = $this->infoarray['name'];
		$this->awood = $this->infoarray['wood'];
		$this->aclay = $this->infoarray['clay'];
		$this->airon = $this->infoarray['iron'];
		$this->acrop = $this->infoarray['crop'];
		$this->pop = $this->infoarray['pop'];
		$this->maxstore = $this->infoarray['maxstore'];
		$this->maxcrop = $this->infoarray['maxcrop'];
		$this->allcrop = $this->getCropProd(false);
		$this->loyalty = $this->infoarray['loyalty'];
		$this->master = count($database->getMasterJobs($this->wid));
		//de gs in town, zetten op max pakhuisinhoud
		if($this->awood>$this->maxstore){ $this->awood=$this->maxstore; $database->updateResource($this->wid,'wood',$this->maxstore); }
		if($this->aclay>$this->maxstore){ $this->aclay=$this->maxstore; $database->updateResource($this->wid,'clay',$this->maxstore); }
		if($this->airon>$this->maxstore){ $this->airon=$this->maxstore; $database->updateResource($this->wid,'iron',$this->maxstore); }
		if($this->acrop>$this->maxcrop){ $this->acrop=$this->maxcrop; $database->updateResource($this->wid,'crop',$this->maxcrop); }

	}
	
	private function calculateProduction() { 
		global $technology,$database,$session;
        $normalA = $database->getOwnArtefactInfoByType($_SESSION['wid'],4);  
		$largeA = $database->getOwnUniqueArtefactInfo($session->uid,4,2);
			$uniqueA = $database->getOwnUniqueArtefactInfo($session->uid,4,3);
	        $upkeep = $technology->getUpkeep($this->unitall,0);
			$heroData = $database->getHeroData($session->uid);
			$heroProduction = heroVillageResourceBonus($heroData, $this->wid, SPEED);

	        $this->production['wood'] = $this->getWoodProd()+$heroProduction['wood'];
			$this->production['clay'] = $this->getClayProd()+$heroProduction['clay'];
			$this->production['iron'] = $this->getIronProd()+$heroProduction['iron'];
			$cropProduction = $this->getCropProd();
			foreach(array('wood','clay','iron','crop') as $resource) {
				$this->productionBreakdown[$resource]['hero'] = $heroProduction[$resource];
			}
			$this->productionBreakdown['crop']['population'] = $this->pop;
			$this->productionBreakdown['crop']['upkeep'] = $upkeep;
			$this->productionBreakdown['crop']['artefact_saving'] = 0;

	        if ($uniqueA['size']==3 && $uniqueA['owner']==$session->uid){
	        $this->production['crop'] = $cropProduction-$this->pop-(($upkeep)-round($upkeep*0.50))+$heroProduction['crop'];
			$this->productionBreakdown['crop']['artefact_saving'] = round($upkeep*0.50);

	        }else if ($normalA['type']==4 && $normalA['size']==1 && $normalA['owner']==$session->uid){
	        $this->production['crop'] = $cropProduction-$this->pop-(($upkeep)-round($upkeep*0.25))+$heroProduction['crop'];
			$this->productionBreakdown['crop']['artefact_saving'] = round($upkeep*0.25);

	        }else if ($largeA['size']==2 && $largeA['owner']==$session->uid){
	        $this->production['crop'] = $cropProduction-$this->pop-(($upkeep)-round($upkeep*0.25))+$heroProduction['crop'];
			$this->productionBreakdown['crop']['artefact_saving'] = round($upkeep*0.25);

	        }else{
			$this->production['crop'] = $cropProduction-$this->pop-$upkeep+$heroProduction['crop'];
	}
    }
	
	
	private function processProduction() {
		global $database;
		$now = time();
		$timepast = max(0, $now - (int)$this->infoarray['lastupdate']);
		$nwood = ($this->production['wood'] / 3600) * $timepast;
		$nclay = ($this->production['clay'] / 3600) * $timepast;
		$niron = ($this->production['iron'] / 3600) * $timepast;
		$ncrop = ($this->production['crop'] / 3600) * $timepast;

		$database->accrueVillageResources(
			$this->wid,
			$this->infoarray['lastupdate'],
			$now,
			$nwood,
			$nclay,
			$niron,
			$ncrop
		);
		$this->LoadTown();
	}
	
	private function getWoodProd() {
		global $bid1,$bid5,$session;
		$wood = $sawmill = 0;
		$woodholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 1) {
				array_push($woodholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 5) {
				$sawmill = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($woodholder)-1;$i++) { $wood+= $bid1[$this->resarray[$woodholder[$i]]]['prod']; }
		$fields = $wood;
		$buildingPercent = $sawmill >= 1 ? $bid5[$sawmill]['attri'] : 0;
		$buildingBonus = $wood / 100 * $buildingPercent;
		if($sawmill >= 1) {
			$wood += $buildingBonus;
		}
		$oasisBonus = $wood*0.25*$this->ocounter[0];
		if($this->ocounter[0] != 0) {
			$wood += $wood*0.25*$this->ocounter[0];
		}
		$plusBonus = $session->bonus1 == 1 ? $wood * 0.25 : 0;
		if($session->bonus1 == 1) {
			$wood *= 1.25;
		}
		$beforeSpeed = $wood;
		$wood *= SPEED;
		$gross = round($wood);
		$this->productionBreakdown['wood'] = array('fields'=>$fields,'building'=>'Aserradero','building_level'=>$sawmill,'building_percent'=>$buildingPercent,'building_bonus'=>$buildingBonus,'oasis_percent'=>25*$this->ocounter[0],'oasis_bonus'=>$oasisBonus,'plus_percent'=>$session->bonus1 == 1 ? 25 : 0,'plus_bonus'=>$plusBonus,'speed'=>SPEED,'speed_bonus'=>$wood-$beforeSpeed,'gross'=>$gross);
		return $gross;
	}
	
	private function getClayProd() {
		global $bid2,$bid6,$session;
		$clay = $brick = 0;
		$clayholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 2) {
				array_push($clayholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 6) {
				$brick = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($clayholder)-1;$i++) { $clay+= $bid2[$this->resarray[$clayholder[$i]]]['prod']; }
		$fields = $clay;
		$buildingPercent = $brick >= 1 ? $bid6[$brick]['attri'] : 0;
		$buildingBonus = $clay / 100 * $buildingPercent;
		if($brick >= 1) {
			$clay += $buildingBonus;
		}
		$oasisBonus = $clay*0.25*$this->ocounter[1];
		if($this->ocounter[1] != 0) {
			$clay += $clay*0.25*$this->ocounter[1];
		}
		$plusBonus = $session->bonus2 == 1 ? $clay * 0.25 : 0;
		if($session->bonus2 == 1) {
			$clay *= 1.25;
		}
		$beforeSpeed = $clay;
		$clay *= SPEED;
		$gross = round($clay);
		$this->productionBreakdown['clay'] = array('fields'=>$fields,'building'=>'Fábrica de ladrillos','building_level'=>$brick,'building_percent'=>$buildingPercent,'building_bonus'=>$buildingBonus,'oasis_percent'=>25*$this->ocounter[1],'oasis_bonus'=>$oasisBonus,'plus_percent'=>$session->bonus2 == 1 ? 25 : 0,'plus_bonus'=>$plusBonus,'speed'=>SPEED,'speed_bonus'=>$clay-$beforeSpeed,'gross'=>$gross);
		return $gross;
	}
	
	private function getIronProd() {
		global $bid3,$bid7,$session;
		$iron = $foundry = 0;
		$ironholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 3) {
				array_push($ironholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 7) {
				$foundry = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($ironholder)-1;$i++) { $iron+= $bid3[$this->resarray[$ironholder[$i]]]['prod']; }
		$fields = $iron;
		$buildingPercent = $foundry >= 1 ? $bid7[$foundry]['attri'] : 0;
		$buildingBonus = $iron / 100 * $buildingPercent;
		if($foundry >= 1) {
			$iron += $buildingBonus;
		}
		$oasisBonus = $iron*0.25*$this->ocounter[2];
		if($this->ocounter[2] != 0) {
			$iron += $iron*0.25*$this->ocounter[2];
		}
		$plusBonus = $session->bonus3 == 1 ? $iron * 0.25 : 0;
		if($session->bonus3 == 1) {
			$iron *= 1.25;
		}
		$beforeSpeed = $iron;
		$iron *= SPEED;
		$gross = round($iron);
		$this->productionBreakdown['iron'] = array('fields'=>$fields,'building'=>'Fundición de hierro','building_level'=>$foundry,'building_percent'=>$buildingPercent,'building_bonus'=>$buildingBonus,'oasis_percent'=>25*$this->ocounter[2],'oasis_bonus'=>$oasisBonus,'plus_percent'=>$session->bonus3 == 1 ? 25 : 0,'plus_bonus'=>$plusBonus,'speed'=>SPEED,'speed_bonus'=>$iron-$beforeSpeed,'gross'=>$gross);
		return $gross;
	}
	
	private function getCropProd($recordBreakdown = true) {
		global $bid4,$bid8,$bid9,$session;
		$crop = $grainmill = $bakery = 0;
		$cropholder = array();
		for($i=1;$i<=38;$i++) {
			if($this->resarray['f'.$i.'t'] == 4) {
				array_push($cropholder,'f'.$i);
			}
			if($this->resarray['f'.$i.'t'] == 8) {
				$grainmill = $this->resarray['f'.$i];
			}
			if($this->resarray['f'.$i.'t'] == 9) {
				$bakery = $this->resarray['f'.$i];
			}
		}
		for($i=0;$i<=count($cropholder)-1;$i++) { $crop+= $bid4[$this->resarray[$cropholder[$i]]]['prod']; }
		$fields = $crop;
		$grainmillPercent = $grainmill >= 1 ? $bid8[$grainmill]['attri'] : 0;
		$bakeryPercent = $bakery >= 1 ? $bid9[$bakery]['attri'] : 0;
		$buildingBonus = $crop / 100 * ($grainmillPercent + $bakeryPercent);
		if($grainmill >= 1 || $bakery >= 1) {
			$crop += $buildingBonus;
		}
		$oasisBonus = $crop*0.25*$this->ocounter[3];
		if($this->ocounter[3] != 0) {
			$crop += $crop*0.25*$this->ocounter[3];
		}
		$plusBonus = $session->bonus4 == 1 ? $crop * 0.25 : 0;
		if($session->bonus4 == 1) {
			$crop *= 1.25;
		}
		$beforeSpeed = $crop;
		$crop *= SPEED;
		$gross = round($crop);
		if($recordBreakdown) {
			$this->productionBreakdown['crop'] = array('fields'=>$fields,'grainmill_level'=>$grainmill,'grainmill_percent'=>$grainmillPercent,'bakery_level'=>$bakery,'bakery_percent'=>$bakeryPercent,'building_bonus'=>$buildingBonus,'oasis_percent'=>25*$this->ocounter[3],'oasis_bonus'=>$oasisBonus,'plus_percent'=>$session->bonus4 == 1 ? 25 : 0,'plus_bonus'=>$plusBonus,'speed'=>SPEED,'speed_bonus'=>$crop-$beforeSpeed,'gross'=>$gross);
		}
		return $gross;
	}
	
	private function sortOasis() {
		$crop = $clay = $wood = $iron = 0;
		if (!empty($this->oasisowned)) {
			foreach ($this->oasisowned as $oasis) {
			switch($oasis['type']) {
					case 1:
					$wood += 1;
					break;
					case 2:
					$wood += 2;
					break;
					case 3:
					$wood += 1;
					$crop += 1;
					break;
					case 4:
					$clay += 1;
					break;
					case 5:
					$clay += 2;
					break;
					case 6:
					$clay += 1;
					$crop += 1;
					break;
					case 7:
					$iron += 1;
					break;
					case 8:
					$iron += 2;
					break;
					case 9:
					$iron += 1;
					$crop += 1;
					break;
					case 10:
					case 11:
					$crop += 1;
					break;
					case 12:
					$crop += 2;
					break;
				}
			}
		}
		return array($wood,$clay,$iron,$crop);
	}
	
	private function ActionControl() {
		global $session;
		if(SERVER_WEB_ROOT) {
			$page = $_SERVER['SCRIPT_NAME'];
		}
		else {
			$explode = explode("/",$_SERVER['SCRIPT_NAME']);
			$i = count($explode)-1;
			$page = $explode[$i];
		}
		if($page == "build.php" && $session->uid != $this->infoarray['owner']) {
			unset($_SESSION['wid']);
			header("Location: dorf1.php");
		}
	}
	
};
$village = new Village;
$building = new Building;

?>
