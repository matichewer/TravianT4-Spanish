<?php
	if($btype==1){
    
    	if($type==1){
        	$name = "Casco de la Atención";
        	$title = "+15% más experiencia.";
            $item = "1";
            $effect = "15";
		}elseif($type==2){
        	$name = "Casco de la Iluminación";
        	$title = "+20 más experiencia.";
            $item = "2";
            $effect = "20";
		}elseif($type==3){
        	$name = "Casco de la Sabiduría";
        	$title = "+25% más experiencia.";
            $item = "3";
            $effect = "25";
		}
        if($type==4){
        	$name = "Casco de la Regeneración";
        	$title = "+10 puntos de salud/día";
            $item = "4";
            $effect = "10";
        }elseif($type==5){
        	$name = "Casco de la Salud";
        	$title = "+15 puntos de salud/día";
            $item = "5";
            $effect = "15";
        }elseif($type==6){
        	$name = "Casco de la Curación";
        	$title = "+20 puntos de salud/día";
            $item = "6";
            $effect = "20";
        }
        if($type==7){
        	$name = "Casco del Gladiador";
			$title = "+100 puntos de cultura/día";
            $item = "7";
            $effect = "100";
		}elseif($type==8){
        	$name = "Casco del Tribuno";
			$title = "+400 puntos de cultura/día";
            $item = "8";
            $effect = "400";
		}elseif($type==9){
        	$name = "Casco del Cónsul";
			$title = "+800 puntos de cultura/día";
            $item = "9";
            $effect = "800";
		}
        if($type==10){
        	$name = "Casco del Jinete";
			$title = "Tiempo de entrenamiento en el establo reducido en 10%";
            $item = "10";
            $effect = "10";
		}elseif($type==11){
        	$name = "Casco de la Caballería";
			$title = "Tiempo de entrenamiento en el establo reducido en 15%";
            $item = "11";
            $effect = "15";
		}elseif($type==12){
        	$name = "Casco de la Caballería Pesada";
			$title = "Tiempo de entrenamiento en el establo reducido en 20%";
            $item = "12";
            $effect = "20";
		}
        if($type==13){
        	$name = "Casco del Mercenario";
			$title = "Tiempo de entrenamiento en el cuartel reducido en 10%";
            $item = "13";
            $effect = "10";
		}elseif($type==14){
        	$name = "Casco del Guerrero";
			$title = "Tiempo de entrenamiento en el cuartel reducido en 15%";
            $item = "14";
            $effect = "15";
		}elseif($type==15){
        	$name = "Casco del Arconte";
			$title = "Tiempo de entrenamiento en el cuartel reducido en 20%";
            $item = "15";
            $effect = "20";
		}
        
	}elseif($btype==2){
    
		if($type==82){
        	$name = "Armadura ligera de la Regeneración";
        	$title = "+20 puntos de salud al día";
            $item = "82";
            $effect = "20";
		}elseif($type==83){
        	$name = "Armadura de la Regeneración";
        	$title = "+30 puntos de salud al día";
            $item = "83";
            $effect = "30";
		}elseif($type==84){
        	$name = "Armadura pesada de la Regeneración";
        	$title = "+40 puntos de salud al día";
            $item = "84";
            $effect = "40";
		}
        if($type==85){
        	$name = "Armadura ligera de escamas";
        	$title = "Reduce la pérdida de vitalidad en 4 puntos, +10 puntos de salud al día";
            $item = "85";
            $effect = "10";
        }elseif($type==86){
        	$name = "Armadura de escamas";
        	$title = "Reduce la pérdida de vitalidad en 6 puntos, +15 puntos de salud al día";
            $item = "86";
            $effect = "15";
        }elseif($type==87){
        	$name = "Armadura pesada de escamas";
        	$title = "Reduce la pérdida de vitalidad en 8 puntos, +20 puntos de salud al día";
            $item = "87";
            $effect = "20";
        }
        if($type==88){
        	$name = "Peto ligero";
			$title = "+500 fuerza de combate al héroe";
            $item = "88";
            $effect = "500";
		}elseif($type==89){
        	$name = "Peto";
			$title = "+1000 fuerza de combate al héroe";
            $item = "89";
            $effect = "1000";
		}elseif($type==90){
        	$name = "Peto pesado";
			$title = "+1500 fuerza de combate al héroe";
            $item = "90";
            $effect = "1500";
		}
        if($type==91){
        	$name = "Armadura articulada ligera";
			$title = "Reduce la pérdida de vitalidad en 3 puntos, +250 fuerza de combate al héroe";
            $item = "91";
            $effect = "3";
		}elseif($type==92){
        	$name = "Armadura articulada";
			$title = "Reduce la pérdida de vitalidad en 4 puntos, +500 fuerza de combate al héroe";
            $item = "92";
            $effect = "4";
		}elseif($type==93){
        	$name = "Armadura articulada pesada";
			$title = "Reduce la pérdida de vitalidad en 5 puntos, +750 fuerza de combate al héroe";
            $item = "93";
            $effect = "5";
		}
        
	}elseif($btype==3){
    
		if($type==61){
        	$name = "Mapa pequeño";
        	$title = "30% de regreso más rápido";
            $item = "61";
            $effect = "30";
		}elseif($type==62){
        	$name = "Mapa";
        	$title = "40% de regreso más rápido";
            $item = "62";
            $effect = "40";
		}elseif($type==63){
        	$name = "Mapa grande";
        	$title = "50% de regreso más rápido";
            $item = "63";
            $effect = "50";
		}
        if($type==64){
        	$name = "Estandarte pequeño";
        	$title = "30% más rápido entre aldeas propias";
            $item = "64";
            $effect = "30";
		}elseif($type==65){
        	$name = "Estandarte";
        	$title = "40% más rápido entre aldeas propias";
            $item = "65";
            $effect = "40";
		}elseif($type==66){
        	$name = "Gran estandarte";
        	$title = "50% más rápido entre aldeas propias";
            $item = "66";
            $effect = "50";
		}
        if($type==67){
        	$name = "Bandera pequeña";
			$title = "15% más rápido entre miembros de la alianza";
            $item = "67";
            $effect = "15";
		}elseif($type==68){
        	$name = "Bandera";
			$title = "20% más rápido entre miembros de la alianza";
            $item = "68";
            $effect = "20";
		}elseif($type==69){
        	$name = "Gran bandera";
			$title = "25% más rápido entre miembros de la alianza";
            $item = "69";
            $effect = "25";
		}
        if($type==73){
        	$name = "Bolsa del ladrón";
			$title = "+10% de bonificación de saqueo";
            $item = "73";
            $effect = "10";
		}elseif($type==74){
        	$name = "Saco del ladrón";
			$title = "+15% de bonificación de saqueo";
            $item = "74";
            $effect = "15";
		}elseif($type==75){
        	$name = "Costal del ladrón";
			$title = "+20% de bonificación de saqueo";
            $item = "75";
            $effect = "20";
		}
        if($type==76){
        	$name = "Escudo pequeño";
        	$title = "+500 fuerza del héroe";
            $item = "76";
            $effect = "500";
        }elseif($type==77){
        	$name = "Escudo";
        	$title = "+1000 fuerza del héroe";
            $item = "77";
            $effect = "1000";
        }elseif($type==78){
        	$name = "Escudo grande";
        	$title = "+1500 fuerza del héroe";
            $item = "78";
            $effect = "1500";
        }
        if($type==79){
        	$name = "Cuerno pequeño del natariano";
			$title = "+20% fuerza de lucha contra los Natares";
            $item = "79";
            $effect = "20";
		}elseif($type==80){
        	$name = "Cuerno del natariano";
			$title = "+25% fuerza de lucha contra los Natares";
            $item = "80";
            $effect = "25";
		}elseif($type==81){
        	$name = "Cuerno grande del natariano";
			$title = "+30% fuerza de lucha contra los Natares";
            $item = "81";
            $effect = "30";
		}
        
	}elseif($btype==4){
    
		if($type==16){
        	$name = "Espada corta del legionario";
        	$title = "+500 a la fuerza del héroe. Por cada legionario: +3 ataque y +3 defensa";
            $item = "16";
            $effect = "500";
		}elseif($type==17){
        	$name = "Espada del legionario";
        	$title = "+1000 a la fuerza del héroe. Por cada legionario: +4 ataque y +4 defensa";
            $item = "17";
            $effect = "1000";
		}elseif($type==18){
        	$name = "Espada larga del legionario";
        	$title = "+1500 a la fuerza del héroe. Por cada legionario: +5 ataque y +5 defensa";
            $item = "18";
            $effect = "1500";
		}
        if($type==19){
        	$name = "Espada corta del pretoriano";
        	$title = "+500 a la fuerza del héroe. Por cada pretoriano: +3 ataque y +3 defensa";
            $item = "19";
            $effect = "500";
        }elseif($type==20){
        	$name = "Espada del pretoriano";
        	$title = "+1000 a la fuerza del héroe. Por cada pretoriano: +4 ataque y +4 defensa";
            $item = "20";
            $effect = "1000";
        }elseif($type==21){
        	$name = "Espada larga del pretoriano";
        	$title = "+1500 a la fuerza del héroe. Por cada pretoriano: +5 ataque y +5 defensa";
            $item = "21";
            $effect = "1500";
        }
        if($type==22){
        	$name = "Espada corta del imperiano";
			$title = "+500 a la fuerza del héroe. Por cada imperiano: +3 ataque y +3 defensa";
            $item = "22";
            $effect = "500";
		}elseif($type==23){
        	$name = "Espada del imperiano";
			$title = "+1000 a la fuerza del héroe. Por cada imperiano: +4 ataque y +4 defensa";
            $item = "23";
            $effect = "1000";
		}elseif($type==24){
        	$name = "Espada larga del imperiano";
			$title = "+1500 a la fuerza del héroe. Por cada imperiano: +5 ataque y +5 defensa";
            $item = "24";
            $effect = "1500";
		}
        if($type==25){
        	$name = "Espada corta del Equites Imperatoris";
			$title = "+500 a la fuerza del héroe. Por cada Equites Imperatoris: +9 ataque y +9 defensa";
            $item = "25";
            $effect = "500";
		}elseif($type==26){
        	$name = "Espada del Equites Imperatoris";
			$title = "+1000 a la fuerza del héroe. Por cada Equites Imperatoris: +12 ataque y +12 defensa";
            $item = "26";
            $effect = "1000";
		}elseif($type==27){
        	$name = "Espada larga del Equites Imperatoris";
			$title = "+1500 a la fuerza del héroe. Por cada Equites Imperatoris: +15 ataque y +15 defensa";
            $item = "27";
            $effect = "1500";
		}
        if($type==28){
        	$name = "Lanza ligera del Equites Caesaris";
        	$title = "+500 a la fuerza del héroe. Por cada Equites Caesaris: +12 ataque y +12 defensa";
            $item = "28";
            $effect = "500";
        }elseif($type==29){
        	$name = "Lanza del Equites Caesaris";
        	$title = "+1000 a la fuerza del héroe. Por cada Equites Caesaris: +16 ataque y +16 defensa";
            $item = "29";
            $effect = "1000";
        }elseif($type==30){
        	$name = "Lanza pesada del Equites Caesaris";
        	$title = "+1500 a la fuerza del héroe. Por cada Equites Caesaris: +20 ataque y +20 defensa";
            $item = "30";
            $effect = "1500";
        }
        if($type==31){
        	$name = "Lanza de la falange";
			$title = "+500 a la fuerza del héroe. Por cada falange: +3 ataque y +3 defensa";
            $item = "31";
            $effect = "500";
		}elseif($type==32){
        	$name = "Pica de la falange";
			$title = "+1000 a la fuerza del héroe. Por cada falange: +4 ataque y +4 defensa";
            $item = "32";
            $effect = "1000";
		}elseif($type==33){
        	$name = "Lanza de la falange";
			$title = "+1500 a la fuerza del héroe. Por cada falange: +5 ataque y +5 defensa";
            $item = "33";
            $effect = "1500";
		}
        if($type==34){
        	$name = "Espada corta del espadachín";
        	$title = "+500 a la fuerza del héroe. Por cada espadachín: +3 ataque y +3 defensa";
            $item = "34";
            $effect = "500";
        }elseif($type==35){
        	$name = "Espada del espadachín";
        	$title = "+1000 a la fuerza del héroe. Por cada espadachín: +4 ataque y +4 defensa";
            $item = "35";
            $effect = "1000";
        }elseif($type==36){
        	$name = "Espada larga del espadachín";
        	$title = "+1500 a la fuerza del héroe. Por cada espadachín: +5 ataque y +5 defensa";
            $item = "36";
            $effect = "1500";
        }
        if($type==37){
        	$name = "Arco corto del trueno de Teutates";
			$title = "+500 a la fuerza del héroe. Por cada trueno de Teutates: +6 ataque y +6 defensa";
            $item = "37";
            $effect = "500";
		}elseif($type==38){
        	$name = "Arco del trueno de Teutates";
			$title = "+1000 a la fuerza del héroe. Por cada trueno de Teutates: +8 ataque y +8 defensa";
            $item = "38";
            $effect = "1000";
		}elseif($type==39){
        	$name = "Arco largo del trueno de Teutates";
			$title = "+1500 a la fuerza del héroe. Por cada trueno de Teutates: +10 ataque y +10 defensa";
            $item = "39";
            $effect = "1500";
		}
        if($type==40){
        	$name = "Bastón del druida";
			$title = "+500 a la fuerza del héroe. Por cada druida: +6 ataque y +6 defensa";
            $item = "40";
            $effect = "500";
		}elseif($type==41){
        	$name = "Gran bastón del druida";
			$title = "+1000 a la fuerza del héroe. Por cada druida: +8 ataque y +8 defensa";
            $item = "41";
            $effect = "1000";
		}elseif($type==42){
        	$name = "Bastón de combate del druida";
			$title = "+1500 a la fuerza del héroe. Por cada druida: +10 ataque y +10 defensa";
            $item = "42";
            $effect = "1500";
		}
        if($type==43){
        	$name = "Lanza ligera del haeduano";
        	$title = "+500 a la fuerza del héroe. Por cada haeduano: +9 ataque y +9 defensa";
            $item = "43";
            $effect = "500";
        }elseif($type==44){
        	$name = "Lanza del haeduano";
        	$title = "+1000 a la fuerza del héroe. Por cada haeduano: +12 ataque y +12 defensa";
            $item = "44";
            $effect = "1000";
        }elseif($type==45){
        	$name = "Lanza pesada del haeduano";
        	$title = "+1500 a la fuerza del héroe. Por cada haeduano: +15 ataque y +15 defensa";
            $item = "45";
            $effect = "1500";
        }
        if($type==46){
        	$name = "Garrote del luchador de porra";
			$title = "+500 a la fuerza del héroe. Por cada luchador de porra: +3 ataque y +3 defensa";
            $item = "46";
            $effect = "500";
		}elseif($type==47){
        	$name = "Maza del luchador de porra";
			$title = "+1000 a la fuerza del héroe. Por cada luchador de porra: +4 ataque y +4 defensa";
            $item = "47";
            $effect = "1000";
		}elseif($type==48){
        	$name = "Mangual del luchador de porra";
			$title = "+1500 a la fuerza del héroe. Por cada luchador de porra: +5 ataque y +5 defensa";
            $item = "48";
            $effect = "1500";
		}
        if($type==49){
        	$name = "Lanza del lancero";
        	$title = "+500 a la fuerza del héroe. Por cada lancero: +3 ataque y +3 defensa";
            $item = "49";
            $effect = "500";
        }elseif($type==50){
        	$name = "Pica del lancero";
        	$title = "+1000 a la fuerza del héroe. Por cada lancero: +4 ataque y +4 defensa";
            $item = "50";
            $effect = "1000";
        }elseif($type==51){
        	$name = "Lanza pesada del lancero";
        	$title = "+1500 a la fuerza del héroe. Por cada lancero: +5 ataque y +5 defensa";
            $item = "51";
            $effect = "1500";
        }
        if($type==52){
        	$name = "Hacha pequeña del hachero";
			$title = "+500 a la fuerza del héroe. Por cada hachero: +3 ataque y +3 defensa";
            $item = "52";
            $effect = "500";
		}elseif($type==53){
        	$name = "Hacha del hachero";
			$title = "+1000 a la fuerza del héroe. Por cada hachero: +4 ataque y +4 defensa";
            $item = "53";
            $effect = "1000";
		}elseif($type==54){
        	$name = "Hacha de batalla del hachero";
			$title = "+1500 a la fuerza del héroe. Por cada hachero: +5 ataque y +5 defensa";
            $item = "54";
            $effect = "1500";
		}
        if($type==55){
        	$name = "Martillo ligero del paladín";
			$title = "+500 a la fuerza del héroe. Por cada paladín: +6 ataque y +6 defensa";
            $item = "55";
		}elseif($type==56){
        	$name = "Martillo del paladín";
			$title = "+1000 a la fuerza del héroe. Por cada paladín: +8 ataque y +8 defensa";
            $item = "56";
		}elseif($type==57){
        	$name = "Martillo pesado del paladín";
			$title = "+1500 a la fuerza del héroe. Por cada paladín: +10 ataque y +10 defensa";
            $item = "57";
		}
        if($type==58){
			$name = "Espada corta del caballero germano";
			$title = "+500 a la fuerza del héroe. Por cada caballero germano: +9 ataque y +9 defensa";
            $item = "58";
            $effect = "500";
        }elseif($type==59){
			$name = "Espada del caballero germano";
			$title = "+1000 a la fuerza del héroe. Por cada caballero germano: +12 ataque y +12 defensa";
            $item = "59";
            $effect = "1000";
        }elseif($type==60){
			$name = "Espada larga del caballero germano";
			$title = "+1500 a la fuerza del héroe. Por cada caballero germano: +15 ataque y +15 defensa";
            $item = "60";
            $effect = "1500";
        }
        
	}elseif($btype==5){
    
		if($type==94){
        	$name = "Botas de la Regeneración";
        	$title = "+10 puntos de salud/día";
            $item = "94";
            $effect = "10";
		}elseif($type==95){
        	$name = "Botas de la Salud";
        	$title = "+15 puntos de salud/día";
            $item = "95";
            $effect = "15";
		}elseif($type==96){
        	$name = "Botas de la Curación";
        	$title = "+20 puntos de salud/día";
            $item = "96";
            $effect = "20";
		}
        if($type==97){
        	$name = "Botas del Mercenario";
        	$title = "+25% de velocidad del ejército en distancias > 20 casillas";
            $item = "97";
            $effect = "25";
        }elseif($type==98){
        	$name = "Botas del Guerrero";
        	$title = "+50% de velocidad del ejército en distancias > 20 casillas";
            $item = "98";
            $effect = "30";
        }elseif($type==99){
        	$name = "Botas del Arconte";
        	$title = "+75% de velocidad del ejército en distancias > 20 casillas";
            $item = "99";
            $effect = "35";
        }
        if($type==100){
        	$name = "Espuelas pequeñas";
			$title = "velocidad del héroe +3";
            $item = "100";
            $effect = "3";
		}elseif($type==101){
        	$name = "Espuelas";
			$title = "velocidad del héroe +4";
            $item = "101";
            $effect = "4";
		}elseif($type==102){
        	$name = "Espuelas afiladas";
			$title = "velocidad del héroe +5";
            $item = "102";
            $effect = "5";
		}
        
	}elseif($btype==6){
    	if($type==103){
        	$name = "Castrado";
			$title = "La velocidad del héroe es 14";
            $item = "103";
            $effect = "14";
		}elseif($type==104){
        	$name = "Pura sangre";
			$title = "La velocidad del héroe es 17";
            $item = "104";
            $effect = "17";
		}elseif($type==105){
        	$name = "Corcel de guerra";
			$title = "La velocidad del héroe es 20";
            $item = "105";
            $effect = "20";
		}
        
	}elseif($btype==7){
    	$name = " Venda pequeña";
		$title = "Cura 1 unidad cada una, máx 25%. Apilable";
		$item = "112";
	}elseif($btype==8){
    	$name = "Venda";
		$title = "Cura 1 unidad cada una, máx 33%. Apilable";
		$item = "113";
	}elseif($btype==9){
    	$name = "Jaula";
		$title = "Se puede capturar 1 animal en el oasis. Apilable";
		$item = "114";
	}elseif($btype==10){
    	$name = "Pergamino";
		$title = "Otorga 10 de experiencia al héroe. Apilable";
		$item = "107";
	}elseif($btype==11){
    	$name = "Ungüento";
		$title = "Cura al héroe un 1% al instante. Apilable";
		$item = "106";
	}elseif($btype==12){
    	$name = "Balde de agua";
		$title = "Resucita a tu héroe al instante";
        $item = "108";
	}elseif($btype==13){
    	$name = "Libro de la Sabiduría";
		$title = "Redistribuye las habilidades del héroe";
        $item = "110";
	}elseif($btype==14){
    	$name = "Tabla de la Ley";
		$title = "+1% de lealtad en la aldea, máx 125%. Apilable";
		$item = "109";
	}elseif($btype==15){
		$name = "Obra de arte";
		$title = "Otorga al instante tantos puntos de cultura como la producción diaria, hasta un máximo de 5000. Se puede apilar.";
        $item = "111";
	}

?>
