<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       cel.php                                                     ##
##  Developed by:  G3n3s!s & JimJam & LoppyLukas                               ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

$cel=array(1=>array('name'=>'Fiesta pequeña','wood'=>6400,'clay'=>6650,'iron'=>5940,'crop'=>1340,'attri'=>500,'time'=>86400),array('name'=>'Fiesta grande','wood'=>29700,'clay'=>33250,'iron'=>32000,'crop'=>6700,'attri'=>2000,'time'=>216000));

$sc = array(
1 => 86400,
2 => 83290,
3 => 80291,
4 => 77401,
5 => 74614,
6 => 71928,
7 => 69338,
8 => 66843,
9 => 64436,
10 => 62117,
11 => 59880,
12 => 57725,
13 => 55647,
14 => 53643,
15 => 51712,
16 => 49850,
17 => 48056,
18 => 46326,
19 => 44658,
20 => 43050);

$gc= array(
10 => 155291,
11 => 149701,
12 => 144312,
13 => 139116,
14 => 134108,
15 => 129280,
16 => 124626,
17 => 120140,
18 => 115815,
19 => 111645,
20 => 107626);

require_once dirname(__FILE__).'/cp.php';

if(!function_exists('celebrationCulturePoints')){
	/**
	 * Tope de puntos de cultura de una celebración: 500 la pequeña y 2000 la grande en
	 * un mundo x1, la mitad en uno de velocidad (que además dura la mitad).
	 *
	 * Es un **tope**, no un importe fijo. En el T4 oficial la fiesta paga producción,
	 * no una cifra suelta: la pequeña otorga lo que produce en un día la aldea donde se
	 * celebra y la grande lo que producen todas las aldeas de la cuenta, cada una
	 * recortada por su tope. Es la misma forma que la obra de arte. Una aldea que
	 * produce 30 PC/día saca 30 de una fiesta pequeña, no 250: la fiesta premia a la
	 * aldea desarrollada, no es un atajo para la que no lo está.
	 */
	function celebrationCulturePointsCap($type){
		$base = array(1 => 500, 2 => 2000);
		$type = (int)$type;

		return isset($base[$type]) ? intdiv($base[$type], cultureFixedAmountDivisor()) : 0;
	}

	/**
	 * Lo que realmente paga una celebración, dada la producción diaria que le
	 * corresponde (la de la aldea para la pequeña, la de la cuenta para la grande).
	 *
	 * Única definición: la usan Automation::celebrationComplete() (que acredita) y
	 * Templates/Build/24_celebrations.tpl (que lo anuncia antes de cobrar), para que no
	 * puedan separarse.
	 */
	function celebrationCulturePoints($type, $dailyProduction){
		return min(celebrationCulturePointsCap($type), max(0, (int)$dailyProduction));
	}
}

if(!function_exists('celebrationDuration')){
	// Duración de una celebración en segundos, según el nivel del Ayuntamiento. Es la
	// única definición: la usan celebration.php y Templates/Build/24_celebrations.tpl, para
	// que el tiempo que se anuncia sea el que después se agenda. Antes la plantilla lo derivaba
	// de `$cel[..]['time']` y el `attri` del edificio, y el servidor de estas tablas, así
	// que se iban unos segundos.
	//
	// La fiesta grande solo existe desde el nivel 10: `$gc` no tiene filas más abajo.
	function celebrationDuration($type, $level){
		global $sc, $gc;
		$type = (int)$type;
		$level = (int)$level;
		$table = $type === 1 ? $sc : ($type === 2 ? $gc : array());
		if(!isset($table[$level])){
			return 0;
		}

		// La mitad en un mundo de velocidad, como el oficial. Antes dividía por SPEED,
		// que en x3 hacía la fiesta un 50% más rentable de lo que corresponde.
		return max(1, (int)round($table[$level] / cultureFixedAmountDivisor()));
	}
}

if(!function_exists('breweryCelebrationCost')){
	// Costo y duración de la celebración de hidromiel de la Cervecería. Es la única
	// definición: la usan brewery.php (que cobra y agenda) y Templates/Build/35.tpl
	// (que muestra el costo y el reloj). Estaban escritos a mano en los dos lados y
	// nada avisaba si uno se movía sin el otro: el jugador veía un precio y pagaba
	// otro.
	function breweryCelebrationCost(){
		return array('wood' => 3870, 'clay' => 1680, 'iron' => 215, 'crop' => 10900);
	}

	// 72 horas a velocidad 1, igual que anuncia la ayuda del edificio.
	function breweryCelebrationDuration(){
		return max(1, (int)round(259200 / SPEED));
	}
}
?>
