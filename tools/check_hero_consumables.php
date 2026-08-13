<?php
// Regresión de los consumibles del héroe (btype 10-15) sobre el despachador real de
// Inventory.php: cantidades límite, topes y el estado del stack.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_consumables.php

error_reporting(E_ALL & ~E_NOTICE);

// Data/hero_full.php emite un salto de línea al cerrar su etiqueta PHP, así que los
// header() de Inventory.php avisarían por salida ya enviada. Se descarta esa salida.
ob_start();

$failures = array();
function consumableAssert($condition, $message){
	global $failures;
	if(!$condition){
		$failures[] = $message;
	}
}

class ConsumableDatabase {
	public $hero = array('uid'=>7,'itempower'=>0,'autoregen'=>10,'speed'=>7,'dead'=>0,'health'=>50,'experience'=>0,'wref'=>10,'home'=>10);
	public $items = array();
	public $inventory = array('helmet'=>0,'body'=>0,'leftHand'=>0,'rightHand'=>0,'shoes'=>0,'horse'=>0,'bag'=>0);
	public $userCp = 1000;
	public $villageLoyalty = 50;
	public $dailyProduction = 100;
	public $artworkLastUsed = 0;
	public $revived = false;

	public function getHeroData($uid){ return $this->hero; }
	public function getItemData($id){ return isset($this->items[$id]) ? $this->items[$id] : false; }
	public function getHeroInventory($uid){ return $this->inventory; }
	public function getVSumField($uid,$field){ return $this->dailyProduction; }
	public function getUserField($uid,$field,$mode){ return $field==='artwork_last_used' ? $this->artworkLastUsed : $this->userCp; }
	public function modifyHero2($field,$value,$uid,$mode){
		if($mode==0){ $this->hero[$field]=$value; }
		elseif($mode==1){ $this->hero[$field]+=$value; }
		elseif($mode==2){ $this->hero[$field]-=$value; }
		return true;
	}
	// editHeroNum del motor clampea el descuento a 0.
	public function editHeroNum($id,$num,$mode){
		$num = max(0,(int)$num);
		if($mode==0){ $this->items[$id]['num'] -= $num; }
		return true;
	}
	public function editProcItem($id,$mode){ $this->items[$id]['proc']=(int)$mode; return true; }
	public function editHeroType($id,$a,$m){ return true; }
	public function setHeroInventory($uid,$f,$v){ $this->inventory[$f]=(int)$v; return true; }
	public function modifyHeroFace($uid,$f,$v){ return true; }
	public function setVillageField($wid,$field,$value){ $this->villageLoyalty=$value; return true; }
	public function updateUserField($uid,$field,$value,$mode){
		if($mode==2){ $this->userCp += $value; }
		return true;
	}
	public function editTableField($t,$f,$v,$rf,$r){ $this->revived = true; return true; }
	public function consumeHeroRevivalBucket($uid,$id,$selectedVillageId){
		if((int)$this->hero['dead']===0 || !isset($this->items[$id])
			|| (int)$this->items[$id]['btype']!==12 || (int)$this->items[$id]['proc']!==0){
			return array('ok'=>false,'status'=>'unavailable','vref'=>0);
		}
		$this->hero['dead']=0;
		$this->hero['health']=100;
		$this->hero['wref']=(int)$selectedVillageId;
		$this->items[$id]['proc']=1;
		$this->revived=true;
		return array('ok'=>true,'status'=>'success','vref'=>(int)$selectedVillageId);
	}
	public function consumeBookOfWisdom($uid,$id){ return true; }
	public function consumeArtwork($uid,$id,$points,$now=null){
		$now = $now===null ? time() : (int)$now;
		$remaining = artworkCooldownRemaining($this->artworkLastUsed,$now);
		if($remaining>0){ return array('ok'=>false,'status'=>'cooldown','remaining'=>$remaining); }
		if(!isset($this->items[$id]) || (int)$this->items[$id]['num']<1 || (int)$this->items[$id]['proc']!==0){
			return array('ok'=>false,'status'=>'invalid','remaining'=>0);
		}
		$this->artworkLastUsed=$now;
		if((int)$this->items[$id]['num']>1){ $this->items[$id]['num']--; }
		else{ $this->items[$id]['proc']=1; }
		$this->userCp+=(int)$points;
		return array('ok'=>true,'status'=>'consumed','remaining'=>artworkCooldownSeconds(),'points'=>(int)$points);
	}
}

// Usa un consumible y devuelve el estado resultante.
function useItem($btype, $amount, $stack = 10, $setup = null){
	global $database, $session, $village;
	$database = new ConsumableDatabase();
	$database->items = array(1 => array('id'=>1,'uid'=>7,'btype'=>$btype,'type'=>0,'num'=>$stack,'proc'=>0));
	if(is_callable($setup)){ $setup($database); }
	$session = (object)array('uid'=>7,'mchecker'=>'TOK');
	$village = (object)array('wid'=>10,'loyalty'=>$database->villageLoyalty);

	$_POST = array('a'=>'inventory','c'=>'TOK','id'=>1,'amount'=>$amount);
	include 'Inventory.php';

	return array(
		'exp' => (int)$database->hero['experience'],
		'salud' => $database->hero['health'],
		'muerto' => (int)$database->hero['dead'],
		'cp' => (int)$database->userCp,
		'lealtad' => $database->villageLoyalty,
		'stack' => (int)$database->items[1]['num'],
		'proc' => (int)$database->items[1]['proc'],
		'revivido' => $database->revived
	);
}

chdir(dirname(__DIR__).'/GameEngine');

// --- Cantidades inválidas: ni efecto ni consumo ---------------------------------
//
// Una cantidad negativa pasaba el chequeo de "no más que el stack" y terminaba
// restando: quitaba salud, bajaba la lealtad de la aldea propia y descontaba puntos de
// cultura, gratis y las veces que quisieras.

foreach(array(-1, -5, -1000, 0) as $amount){
	$r = useItem(10, $amount);
	consumableAssert($r['exp'] === 0, "pergamino con amount=$amount cambió la experiencia a ".$r['exp']);

	$r = useItem(11, $amount);
	consumableAssert($r['salud'] == 50, "ungüento con amount=$amount dejó la salud en ".$r['salud']);

	$r = useItem(14, $amount);
	consumableAssert($r['lealtad'] == 50, "tabla de la ley con amount=$amount dejó la lealtad en ".$r['lealtad']);

	$r = useItem(15, $amount);
	consumableAssert($r['cp'] === 1000, "obra de arte con amount=$amount dejó los PC en ".$r['cp']);

	foreach(array(10, 11, 14, 15) as $btype){
		$r = useItem($btype, $amount);
		consumableAssert($r['stack'] === 10, "el objeto $btype con amount=$amount tocó el stack");
		consumableAssert($r['proc'] === 0, "el objeto $btype con amount=$amount se marcó como gastado");
	}
}

// Pedir más de lo que hay tampoco hace nada.
foreach(array(10, 11, 14, 15) as $btype){
	$r = useItem($btype, 11);
	consumableAssert($r['stack'] === 10, "el objeto $btype dejó gastar más de lo que había");
	consumableAssert($r['proc'] === 0, "el objeto $btype se marcó gastado pidiendo de más");
}

// --- Uso normal ------------------------------------------------------------------

$r = useItem(10, 3);
consumableAssert($r['exp'] === 30, 'tres pergaminos no dieron 30 de experiencia: '.$r['exp']);
consumableAssert($r['stack'] === 7, 'tres pergaminos no bajaron el stack a 7');

$r = useItem(10, 10);
consumableAssert($r['exp'] === 100, 'gastar el stack entero de pergaminos no dio 100');
consumableAssert($r['proc'] === 1, 'gastar el stack entero de pergaminos no lo marcó agotado');

$r = useItem(11, 3);
consumableAssert($r['salud'] == 53, 'tres ungüentos no curaron 3 puntos');
consumableAssert($r['stack'] === 7, 'tres ungüentos no bajaron el stack');

$r = useItem(14, 3);
consumableAssert($r['lealtad'] == 53, 'tres tablas de la ley no subieron 3 de lealtad');

// El tope de salud y el de lealtad consumen solo lo que hizo falta.
$r = useItem(11, 10, 10, function($db){ $db->hero['health'] = 95; });
consumableAssert($r['salud'] == 100, 'el ungüento pasó de 100 de salud: '.$r['salud']);
consumableAssert($r['stack'] === 5, 'el ungüento gastó de más al topar en 100: quedaron '.$r['stack']);

$r = useItem(14, 10, 10, function($db){ $db->villageLoyalty = 120; });
consumableAssert($r['lealtad'] == 125, 'la tabla de la ley pasó de 125: '.$r['lealtad']);
consumableAssert($r['stack'] === 5, 'la tabla de la ley gastó de más al topar en 125: quedaron '.$r['stack']);

// --- Héroe muerto ----------------------------------------------------------------
//
// Un héroe muerto tiene salud 0, así que entraba en la rama del ungüento y se gastaban
// objetos que no servían: el balde lo deja en 100 igual.

$muerto = function($db){ $db->hero['dead'] = 1; $db->hero['health'] = 0; };

$r = useItem(11, 5, 10, $muerto);
consumableAssert($r['stack'] === 10, 'se gastaron ungüentos con el héroe muerto');
consumableAssert($r['salud'] == 0, 'el ungüento curó a un héroe muerto');

// El balde sí funciona, y solo con el héroe muerto.
$r = useItem(12, 1, 1, $muerto);
consumableAssert($r['muerto'] === 0, 'el balde no revivió al héroe');
consumableAssert($r['salud'] == 100, 'el balde no dejó la salud en 100');
consumableAssert($r['proc'] === 1, 'el balde no se marcó gastado');
consumableAssert($r['revivido'] === true, 'el balde no puso al héroe en la aldea');

$r = useItem(12, 1, 1);
consumableAssert($r['proc'] === 0, 'el balde se gastó con el héroe vivo');

// --- Obra de arte: el tope de 5000 que promete el objeto --------------------------

consumableAssert(artworkCulturePointsCap() === 5000, 'el tope de la obra de arte dejó de ser 5000');

// Solo una obra por petición: intentar usar varias no concede PC ni consume el stack.
$r = useItem(15, 2, 10, function($db){ $db->dailyProduction = 100; });
consumableAssert($r['cp'] === 1000 && $r['stack']===10, 'se pudieron usar varias obras en una petición');

// La obra concede la producción balanceada: 25% de la producción base de aldeas.
$r = useItem(15, 1, 10, function($db){ $db->dailyProduction = 9000; });
consumableAssert($r['cp'] === 3250, 'una obra con 9000 base/día no dio 2250 PC: '.$r['cp']);

// La producción balanceada por encima de 5000 conserva el tope existente.
$r = useItem(15, 1, 10, function($db){ $db->dailyProduction = 30000; });
consumableAssert($r['cp'] === 6000, 'una obra con 7500 balanceados no se recortó a 5000: '.$r['cp']);

// Justo en el tope no se recorta nada.
$r = useItem(15, 1, 10, function($db){ $db->dailyProduction = 20000; });
consumableAssert($r['cp'] === 6000, 'una obra con exactamente 5000/día no dio 5000');

// Un uso dentro de las 24 horas se rechaza sin efecto ni consumo.
$r = useItem(15, 1, 10, function($db){ $db->dailyProduction=4000; $db->artworkLastUsed=time()-3600; });
consumableAssert($r['cp']===1000 && $r['stack']===10 && $r['proc']===0, 'el cooldown consumió una obra o concedió PC');

// Al cumplirse exactamente las 24 horas vuelve a estar disponible.
$r = useItem(15, 1, 10, function($db){ $db->dailyProduction=4000; $db->artworkLastUsed=time()-86400; });
consumableAssert($r['cp']===2000 && $r['stack']===9, 'la obra siguió bloqueada después de 24 horas');

// El diálogo del inventario tiene que anunciar el valor ya recortado.
$dialog = file_get_contents(dirname(__DIR__).'/hero_inventory.php');
consumableAssert($dialog !== false, 'No se pudo leer hero_inventory.php');
consumableAssert(strpos($dialog, 'artworkCulturePoints($database') !== false,
	'el diálogo del inventario dejó de usar el valor recortado de la obra de arte');

ob_end_clean();

if($failures){
	fwrite(STDERR, "Hero consumables regression: FAILED\n");
	foreach($failures as $failure){
		fwrite(STDERR, "  - $failure\n");
	}
	exit(1);
}

echo "Hero consumables regression: OK\n";
