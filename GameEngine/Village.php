<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

include("Session.php");
include("Building.php");
include("Market.php");
include_once("Technology.php");
require_once __DIR__."/Hero.php";
require_once __DIR__."/Production.php";
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
	private $freeCrop = null, $baseCropProduction = null;
	
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

	/**
	 * Cereal libre del T4 oficial: producción base menos habitantes, sin tropas.
	 * Es lo único que mira el candado de construcción; el balance que ve el
	 * jugador (getProd('crop')) es otra cuenta. Ver GameEngine/Production.php.
	 */
	public function getFreeCrop() {
		if($this->freeCrop === null) {
			$this->freeCrop = villageFreeCrop($this->resarray,$this->ocounter,$this->pop,SPEED);
		}
		return $this->freeCrop;
	}

	/**
	 * Oasis anexados repartidos por recurso, en el formato que espera
	 * villageGrossProduction(). Lo necesita quien tenga que simular la producción
	 * con un campo en otro nivel (el candado del molino, por ejemplo).
	 */
	public function getOasisCounter() {
		return $this->ocounter;
	}

	/**
	 * Producción base de cereal (campos + molino/panadería + oasis, sin oro ni
	 * héroe), que es contra la que se mide el escape antibloqueo del oficial.
	 */
	public function getBaseCropProduction() {
		if($this->baseCropProduction === null) {
			$this->baseCropProduction = villageBaseCropProduction($this->resarray,$this->ocounter,SPEED);
		}
		return $this->baseCropProduction;
	}
	
	public function getAllUnits($vid) {
		global $database,$technology;
		return $technology->getUnits($database->getUnit($vid),$database->getEnforceVillage($vid,0));
	}
		
	private function LoadTown() {
		global $database,$session,$logging,$technology;
		// LoadTown corre dos veces por petición (constructor y processProduction):
		// el cereal libre depende de los campos y de la población, así que la copia
		// cacheada se tira acá para no responder con la de antes de acreditar.
		$this->freeCrop = null;
		$this->baseCropProduction = null;
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
	
	/**
	 * Producción bruta de los cuatro recursos. La cuenta vive en Production.php
	 * para que la aldea, el saqueo, la hambruna y la vista de aldeas usen la misma.
	 */
	private function grossProduction() {
		global $session;
		$bonusFlags = array(
			$session->bonus1 == 1,
			$session->bonus2 == 1,
			$session->bonus3 == 1,
			$session->bonus4 == 1
		);
		return villageGrossProduction($this->resarray,$this->ocounter,$bonusFlags,SPEED);
	}

	private function getWoodProd() {
		$gross = $this->grossProduction();
		$this->productionBreakdown['wood'] = $gross['breakdown']['wood'];
		return $gross['production']['wood'];
	}

	private function getClayProd() {
		$gross = $this->grossProduction();
		$this->productionBreakdown['clay'] = $gross['breakdown']['clay'];
		return $gross['production']['clay'];
	}

	private function getIronProd() {
		$gross = $this->grossProduction();
		$this->productionBreakdown['iron'] = $gross['breakdown']['iron'];
		return $gross['production']['iron'];
	}

	private function getCropProd($recordBreakdown = true) {
		$gross = $this->grossProduction();
		if($recordBreakdown) {
			$this->productionBreakdown['crop'] = $gross['breakdown']['crop'];
		}
		return $gross['production']['crop'];
	}

	private function sortOasis() {
		return villageOasisCounter($this->oasisowned);
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
