<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       GeneratorX.phpp                                               ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

require_once __DIR__."/Hero.php";

if(!function_exists('tournamentSquareSpeedFactor')){
	// Multiplicador de velocidad que aporta la Plaza de Torneos de la aldea de origen
	// del movimiento. Vive suelto acá porque lo necesitan las dos copias de
	// procDistanceTime (GeneratorX para las salidas y las vistas previas, Automation
	// para los regresos): tenerlo en un solo lugar es lo que evita que vuelvan a
	// desincronizarse, que es lo que pasaba cuando el bono valía solo a la vuelta.
	//
	// Conserva la fórmula histórica y su umbral propio (TS_THRESHOLD, distinto al de
	// las botas de mercenario) para no cambiarle los tiempos a quien ya la construyó.
	function tournamentSquareSpeedFactor($originCoor, $distance){
		global $bid14, $database;
		$distance = is_numeric($distance) ? (float)$distance : 0;
		if(!is_finite($distance) || $distance <= TS_THRESHOLD || !is_array($originCoor)
			|| !isset($originCoor['x'], $originCoor['y'])
			|| !is_numeric($originCoor['x']) || !is_numeric($originCoor['y'])){
			return 1;
		}
		if(!is_object($database) || !method_exists($database, 'getResourceLevel')
			|| !method_exists($database, 'getVilWref') || !is_array($bid14)){
			return 1;
		}
		// La aldea se resuelve por coordenada contra `wdata` en vez de calcular el id
		// con getBaseID(): esa fórmula depende de que WORLD_MAX coincida con el radio
		// real del mundo generado, y si no coinciden devuelve el id de otra aldea.
		$originId = (int)$database->getVilWref((int)$originCoor['x'], (int)$originCoor['y']);
		if($originId<=0){
			return 1;
		}
		$fields = $database->getResourceLevel($originId);
		if(!is_array($fields)){
			return 1;
		}
		$attri = 0;
		for($field = 19; $field <= 40; $field++){
			if(isset($fields['f'.$field.'t']) && (int)$fields['f'.$field.'t'] === 14){
				$level = (int)$fields['f'.$field];
				// Una base dañada no debería poder quitar el bono de una Plaza válida si
				// aparecen dos edificios del mismo tipo: se conserva el nivel efectivo mayor.
				$attri = max($attri, isset($bid14[$level]['attri']) ? (int)$bid14[$level]['attri'] : 0);
			}
		}
		if($attri <= 0){
			return 1;
		}

		return (TS_THRESHOLD + ($distance - TS_THRESHOLD) * $attri / 100) / $distance;
	}
}

class GeneratorX {
	
	public function generateRandID(){
		return md5($this->generateRandStr(16));
		}

   public function generateRandStr($length){
      $randstr = "";
      for($i=0; $i<$length; $i++){
         $randnum = mt_rand(0,61);
         if($randnum < 10){
            $randstr .= chr($randnum+48);
         }else if($randnum < 36){
            $randstr .= chr($randnum+55);
         }else{
            $randstr .= chr($randnum+61);
         }
      }
      return $randstr;
   }
   
   public function encodeStr($str,$length) {
	   $encode = md5($str);
	   return substr($encode,0,$length);
   }
   
   // $bootsBonus es el porcentaje de las botas de mercenario del héroe que viaja con
   // el ejército; solo se pasa cuando el héroe va en el movimiento y solo cuenta en
   // los modos que llevan velocidad real de tropas ($mode = 1).
   //
   // $travelBonus es el de la mano izquierda (mapa, estandarte o bandera). A diferencia
   // de las botas, que solo acortan el tramo que pasa el umbral, este sube la velocidad
   // de todo el viaje. Quien llama ya decidió si corresponde para ese trayecto.
   public function procDistanceTime($coor,$thiscoor,$ref,$mode,$bootsBonus=0,$travelBonus=0,$artefactFactor=1) {
		$xdistance = ABS($thiscoor['x'] - $coor['x']);
		if($xdistance > WORLD_MAX) {
			$xdistance = (2 * WORLD_MAX + 1) - $xdistance;
		}
		$ydistance = ABS($thiscoor['y'] - $coor['y']);
		if($ydistance > WORLD_MAX) {
			$ydistance = (2 * WORLD_MAX + 1) - $ydistance;
		}
		$distance = SQRT(POW($xdistance,2)+POW($ydistance,2));
		if(!$mode) {
			if($ref == 1) {
				$speed = 16;
			}
			else if($ref == 2) {
				$speed = 12;
			}
			else if($ref == 3) {
				$speed = 24; 
			}
			else if($ref == 300) {
				$speed = 5;
			}
			else {
				$speed = 1;
			}
		}
		else {
				// La Plaza de Torneos se mira sobre la aldea de origen del movimiento,
				// no sobre la que el jugador tenga abierta.
				$speed = max(1, (float)$ref) * tournamentSquareSpeedFactor($coor, $distance);
		}
		$effectiveDistance = heroBootsTravelDistance($distance, $mode ? $bootsBonus : 0);
		if($mode && $travelBonus > 0) {
			$speed *= 1 + max(0, (float)$travelBonus) / 100;
		}
		// Las botas de los titanes multiplican la velocidad del movimiento entero, como
		// el estandarte y a diferencia de las botas del héroe. Sólo en modo tropa: los
		// mercaderes y los colonos van a velocidad fija y el artefacto no los toca.
		if($mode && $artefactFactor > 0) {
			$speed *= (float)$artefactFactor;
		}
		return round(($effectiveDistance/$speed) * 3600 / INCREASE_SPEED);
	}
   
   /**
    * Segundos a "h:mm:ss".
    *
    * Restaba de a 60 en un while, así que un INF —lo que devuelve una división por
    * una producción de cero, por ejemplo el tiempo de llenado de un almacén que no
    * produce— dejaba el proceso girando para siempre y colgaba la página entera.
    * Un valor no finito o negativo no es un tiempo: vale cero.
    */
   public function getTimeFormat($time) {
	   if(!is_numeric($time) || !is_finite((float)$time) || $time < 0) {
		   $time = 0;
	   }
	   $time = (int)$time;
	   $hr = intdiv($time, 3600);
	   $min = intdiv($time % 3600, 60);
	   $sec = $time % 60;
	   if($min < 10) {
		   $min = "0".$min;
	   }
	   if($sec < 10) {
		   $sec = "0".$sec;
	   }
	   return $hr.":".$min.":".$sec;
   }

	public function procMtime($time) {
		/*$timezone = 7;
		switch($timezone) {
			case 7:
			$time -= 3600;
			break;
		}*/
		$now = time();
		$reportDate = date('Y/m/d', $time);
		
		if (date('Y/m/d', $now) == $reportDate) {
		//if ((time()-$time) < 24*60*60 && (time()-$time) > 0) {
			$day = "hoy";
		}elseif(date('Y/m/d', strtotime('-1 day', $now)) == $reportDate){
			$day = "ayer";
		}
		else {
			$pref = 3;
			switch($pref) {
			case 1:
			$day = date("m/j/y",$time);
			break;
			case 2:
			$day = date("j/m/y",$time);
			break;
			case 3:
			$day = date("j/m/y",$time);
			break;
			default:
			$day = date("y/m/j",$time);
			break;
			}
		}
		$new = date("H:i:s",$time);
		return array($day,$new);
	}
   
	/**
	 * Id de la casilla en (x|y), a partir del orden en que el instalador generó `wdata`.
	 *
	 * El radio sale del mundo REAL y no de WORLD_MAX. La fórmula depende de que los dos
	 * coincidan, y cuando no coinciden no falla ruidosamente: devuelve el id de otra
	 * casilla, o de ninguna. Un mundo generado a ±25 con WORLD_MAX en 100 —el caso del
	 * Docker de desarrollo— hacía que el mapa entero saliera en blanco, porque cada
	 * consulta pedía una casilla inexistente. En un mundo bien instalado los dos números
	 * son el mismo y esto no cambia nada.
	 */
	public function getBaseID($x,$y) {
		global $database;
		$radius = 0;
		if(isset($database) && is_object($database) && method_exists($database,'getWorldRadius')){
			$radius = (int)$database->getWorldRadius();
		}
		if($radius <= 0){
			$radius = (int)WORLD_MAX;
		}
		// El mundo da la vuelta por los bordes: pasarse del norte lleva al sur, igual que
		// en Travian, y es lo que ya hacen todas las cuentas de distancia del motor
		// (procDistanceTime, la anexión de oasis, la zona gris). getBaseID() no lo hacía,
		// así que una vista de mapa cerca de un borde pedía casillas inexistentes y salía
		// media pantalla en blanco. Dentro de rango esto no cambia nada.
		$span = $radius * 2 + 1;
		$x = ((((int)$x + $radius) % $span) + $span) % $span - $radius;
		$y = ((((int)$y + $radius) % $span) + $span) % $span - $radius;
		return (($radius-$y) * $span) + ($radius + $x + 1);
	}
   
	public function getMapCheck($wref) {
		return substr(md5($wref),5,2);
	}
   
	public function pageLoadTimeStart() {
		$starttime = microtime();
		$startarray = explode(" ", $starttime);
		//$starttime = $startarray[1] + $startarray[0];
		return $startarray[0];
	}

	public function pageLoadTimeEnd() {
		$endtime = microtime();
		$endarray = explode(" ", $endtime);
		//$endtime = $endarray[1] + $endarray[0];
		return $endarray[0];
	}
	
};
$generator = new GeneratorX;
