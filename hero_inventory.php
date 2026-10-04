<?php
include("GameEngine/Village.php");
include("GameEngine/Inventory.php");
$start = $generator->pageLoadTimeStart();
$artworkFeedback = isset($_SESSION['artwork_feedback']) && is_array($_SESSION['artwork_feedback'])
	? $_SESSION['artwork_feedback']
	: (isset($artworkFeedback) && is_array($artworkFeedback) ? $artworkFeedback : null);
unset($_SESSION['artwork_feedback']);

if(isset($_GET['newdid'])){
	$newVillageId = (int)$_GET['newdid'];
	if(in_array($newVillageId,array_map('intval',$session->villages),true)){
		$_SESSION['wid'] = $newVillageId;
	}
	header("Location: hero_inventory.php");
	exit;
}

if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_POST['a']) && $_POST['a'] === 'heroHiding'){
	$tokenIsValid = isset($_POST['c']) && is_scalar($_POST['c'])
		&& hash_equals((string)$session->mchecker, (string)$_POST['c']);
	if($tokenIsValid){
		$hideHero = isset($_POST['hide']) && (int)$_POST['hide'] === 1 ? 1 : 0;
		$database->modifyHero2('hide', $hideHero, (int)$session->uid, 0);
	}
	header("Location: hero_inventory.php");
	exit;
}

if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_POST['a']) && $_POST['a'] === 'allocateHeroAttributes'){
	$tokenIsValid = isset($_POST['c']) && is_scalar($_POST['c'])
		&& hash_equals((string)$session->mchecker, (string)$_POST['c']);
	if($tokenIsValid){
		$increments = array(
			'power' => isset($_POST['power']) ? $_POST['power'] : 0,
			'offBonus' => isset($_POST['offBonus']) ? $_POST['offBonus'] : 0,
			'defBonus' => isset($_POST['defBonus']) ? $_POST['defBonus'] : 0,
			'product' => isset($_POST['product']) ? $_POST['product'] : 0
		);
		$database->allocateHeroAttributePoints((int)$session->uid,$increments,heroAttributeLimit());
	}
	header("Location: hero_inventory.php");
	exit;
}

// La liquidación reutiliza la operación del servidor y vuelve al inventario.
if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']==='POST'
    && isset($_POST['a']) && $_POST['a']==='inventoryLiquidate'){
    $message = 'La solicitud expiró. Vuelve a intentarlo.';
    if(isset($_POST['c']) && is_scalar($_POST['c'])
        && hash_equals((string)$session->mchecker,(string)$_POST['c'])){
        $itemId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
        $result = $database->disposeHeroItem((int)$session->uid,$itemId,$amount,'liquidate');
        $message = $result['status']==='success'
            ? 'Objeto liquidado. Recibiste '.(int)$result['silver'].' de plata.'
            : 'No se pudo liquidar. Revisa la cantidad y la disponibilidad (mínimo 10 unidades para objetos apilables).';
    }
    $_SESSION['inventoryItemFeedback'] = $message;
    header('Location: hero_inventory.php');
    exit;
}

include "Templates/html.tpl";

if(isset($_GET['inventory'])){
	$uid = (int)$session->uid;
	$heroData = $database->getHeroData($uid);
	if(is_array($heroData) && (int)$heroData['dead']===0){
		$equipmentRequests = array(
			'helmet' => 1,
			'body' => 2,
			'leftHand' => 3,
			'rightHand' => 4,
			'shoes' => 5,
			'horse' => 6
		);

		foreach($equipmentRequests as $requestField => $btype){
			if(isset($_GET[$requestField])){
				unequipHeroItem($database, $uid, $btype, (int)$_GET[$requestField]);
				break;
			}
		}

		if(isset($_GET['bag'])){
			unequipHeroBagItem($database, $uid, (int)$_GET['bag']);
		}
	}
}
?>
<body class="v35 webkit chrome hero_inventory">
	<div id="wrapper"> 
		<img id="staticElements" src="img/x.gif" alt="" /> 
		<div id="logoutContainer"> 
			<a id="logout" href="logout.php" title="<?php echo LOGOUT; ?>">&nbsp;</a> 
		</div> 
		<div class="bodyWrapper"> 
			<div id="header"> 
				<div id="mtop">
					<a id="logo" href="<?php echo HOMEPAGE; ?>" target="_blank" title="<?php echo SERVER_NAME ?>"></a>
					<?php
						include("Templates/navigation.tpl");
					?>
<div class="clear"></div> 
</div> 
</div>
					<div id="mid">
<a id="ingameManual" href="help.php"><img class="question" alt="Ayuda" src="img/x.gif"></a>
												<div class="clear"></div> 
						<div id="contentOuterContainer"> 
							<div class="contentTitle">&nbsp;</div> 

<div class="contentContainer">
								<div id="content" class="hero_inventory"><h1 class="titleInHeader">Héroe</h1>
<div class="contentNavi subNavi">
				<div class="container active">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_inventory.php"><span class="tabItem">Atributos</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero.php"><span class="tabItem">Apariencia</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_adventure.php"><span class="tabItem">Aventuras</span></a></div>
				</div>
				<div class="container normal">
					<div class="background-start">&nbsp;</div>
					<div class="background-end">&nbsp;</div>
					<div class="content"><a href="hero_auction.php"><span class="tabItem">Subastas</span></a></div>
				</div><div class="clear"></div>
		</div>
		<script type="text/javascript">
					window.addEvent('domready', function()
					{
						$$('.subNavi').each(function(element)
						{
							new Travian.Game.Menu(element);
						});
					});
</script>
<div class="clear"></div>
<?php if(is_array($artworkFeedback)) { ?>
	<div class="boxes boxesColor <?php echo !empty($artworkFeedback['ok']) ? 'green' : 'red'; ?>">
		<div class="boxes-contents">
		<?php if(!empty($artworkFeedback['ok'])) { ?>
			Obra de arte utilizada: obtuviste <?php echo number_format((int)$artworkFeedback['points'],0,',','.'); ?> puntos de cultura.
		<?php } elseif(isset($artworkFeedback['status']) && $artworkFeedback['status']==='cooldown') { ?>
			Solo puedes usar una obra de arte cada 24 horas. Falta <?php echo $generator->getTimeFormat((int)$artworkFeedback['remaining']); ?>.
		<?php } elseif(isset($artworkFeedback['status']) && $artworkFeedback['status']==='busy') { ?>
			La obra de arte se está procesando. Inténtalo nuevamente en unos segundos.
		<?php } else { ?>
			No se pudo utilizar la obra de arte. El objeto no fue consumido.
		<?php } ?>
		</div>
	</div>
<?php } ?>
<?php
if(isset($_SESSION['inventoryItemFeedback'])){
    echo '<p class="boxes boxesColor gray">'.htmlspecialchars($_SESSION['inventoryItemFeedback'],ENT_QUOTES,'UTF-8').'</p>';
    unset($_SESSION['inventoryItemFeedback']);
}
include("Templates/hero.tpl");
?>

<div id="bodyOptions">
	<div id="hero_body_container">
		<div id="hero_body">
			<img class="heroBody" src="<?php echo htmlspecialchars(heroBodyUrl($session->uid, 'inventory'), ENT_QUOTES); ?>">
			<div class="clear"></div>
		</div>
		<div id="hero_body_content">
			<div class="content">

<?php
$gi = $database->getHeroInventory($session->uid);
$equipmentSlots = array(
	'helmet' => 1,
	'leftHand' => 3,
	'rightHand' => 4,
	'body' => 2,
	'horse' => 6,
	'shoes' => 5,
	'bag' => 0
);
$disabledClass = $hero['dead']==1 ? ' disabled' : '';

foreach($equipmentSlots as $slot => $expectedBtype){
	$itemId = isset($gi[$slot]) ? (int)$gi[$slot] : 0;
	$itemMarkup = '';
	if($itemId>0){
		$data = $database->getItemData($itemId);
		$isExpectedItem = isOwnedHeroItem($data, $session->uid)
			&& ($slot==='bag'
				? ((int)$data['btype']>=7 && (int)$data['btype']<=9)
				: (int)$data['btype']===$expectedBtype);
		if($isExpectedItem){
			$btype = (int)$data['btype'];
			$type = (int)$data['type'];
			include "Templates/Auction/alt.tpl";

			$itemClass = $slot==='bag' ? $btype+105 : $type;
			$amount = $slot==='bag' ? (int)$data['type'] : (int)$data['num'];
			$tooltipAmount = $amount>1 ? '('.$amount.') ' : '';
			$tooltip = htmlspecialchars($tooltipAmount.$name.'||'.$title, ENT_QUOTES, 'UTF-8');
			$item = '<div id="item_'.$itemId.'" title="'.$tooltip.'" class="item item_'.$itemClass.' onHero'.$disabledClass.'" style="position: relative; left: 0px; top: 0px;"><div class="amount">'.$amount.'</div></div>';
			$itemMarkup = $hero['dead']==1 ? $item : '<a href="?inventory&amp;'.$slot.'='.$itemId.'">'.$item.'</a>';
		}
	}
	echo '<div id="'.$slot.'" class="draggable'.$disabledClass.'">'.$itemMarkup.'</div>';
}
?>
			</div>
		</div>
	</div>
	<div class="heroHidden">
		<form method="post" action="hero_inventory.php">
			<input type="hidden" name="a" value="heroHiding">
			<input type="hidden" name="c" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
			<input type="hidden" name="hide" value="<?php echo (int)$hero['hide'] === 1 ? 0 : 1; ?>">
			<label for="heroHideShow">
				<input
					type="checkbox"
					class="check"
					id="heroHideShow"
					onchange="this.form.submit();"
					<?php if((int)$hero['hide'] === 1){ echo 'checked="checked"'; } ?>
				>
				Evasión del héroe: ocultarlo durante los ataques para que no defienda.
			</label>
			<div class="description">
				Esta opción es independiente de la evasión de tropas del Club de Oro.
			</div>
		</form>
	</div>
</div>
<div id="hero_inventory">
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">
    <div id="itemsToSale"><?php
$database->consolidateHeroConsumables((int)$session->uid);
$prefix = "".TB_PREFIX."heroitems";

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."heroitems WHERE (proc = 0 OR ((btype = 7 OR btype = 8 OR btype = 9) && num != 0)) AND uid = $session->uid ORDER BY FIELD(btype,1,2,4,3,5,6,7,8,9,10,11,12,13,14,15), type, id");
$query = mysql_num_rows($sql);

$outputList = '';
$inventoryItemDetails = array();

$inv = 1;
while($row = mysql_fetch_array($sql)){ 

$id = $row["id"];
$uid = $row["uid"];
$btype = $row["btype"];
$type = $row["type"];
$num = $row["num"];
$proc = $row["proc"];

include "Templates/Auction/alt.tpl";
$inventoryItemDetails[(int)$id] = array('name'=>$name,'description'=>$title,'icon'=>(int)$item,
    'sellable'=>(int)$proc===0,'stackable'=>heroItemIsAuctionStackable($btype),
    'liquidationPerUnit'=>in_array((int)$btype,array(12,13),true) ? 10 : 0);
	if($btype<=10 or $btype==11 or $btype==13){
		if($hero['dead']==1){
			$dis = ' disabled';
			$deadTitle = "
			<span class='itemNotMoveable'>No puedes usar este objeto mientras tu héroe está muerto.</span><br>";
		}else{
			$dis = '';
			$deadTitle = '';
		}
	}else{
		$dis = '';
		$deadTitle = '';
	}
	if($btype >= 7 && $btype <= 9){
		$var = $num - $type;
		if ($var > 0) {
	$amount = '('.$var.') ';
	$outputList .= "<div id=\"inventory_".$inv."\" class=\"inventory draggable\">";
	// El id lleva prefijo propio: cuando el objeto está cargado en la bolsa, el slot ya
	// dibuja otro div con id "item_<id>" y $() devolvería ese en lugar de este.
	$outputList .= "<div id=\"inv_item_".$id."\" title=\"".$amount."".$name."||".$deadTitle."".$title."\" class=\"item item_".($btype+105)."".$dis."\" style=\"position:relative;left:0px;top:0px;\">";
	$outputList .= "<div class=\"amount\">".$var."</div>";
	$outputList .= "</div>";
	$outputList .= '</div>';
	$inv++;
		}
	}else{
	if($num==1){$amount = '';}else{$amount = '('.$num.') ';}
	$outputList .= "<div id=\"inventory_".$inv."\" class=\"inventory draggable\">";
	$outputList .= "<div id=\"item_".$id."\" title=\"".$amount."".$name."||".$deadTitle."".$title."\" class=\"item item_".$item."".$dis."\" style=\"position:relative;left:0px;top:0px;\">";
	$outputList .= "<div class=\"amount\">".$num."</div>";
	$outputList .= "</div>";
	$outputList .= '</div>';
	$inv++;	
	}
}
	echo $outputList;
	
for($i=$inv;$i<=12;$i++){
	echo '<div id="inventory_'.$i.'" class="inventory draggable"></div>';
}
?>
			<div class="clear"></div>
		</div>
	</div>
</div>
</div>
<div class="clear"></div>
<div id="placeHolder"></div>
<form id="HeroInventory" method="post" action="hero_inventory.php">
	<input type="hidden" name="a" value="inventory">
	<input type="hidden" name="c" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
	<input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? (int)$_POST['id'] : ''; ?>">
	<input type="hidden" name="amount" value="<?php echo isset($_POST['amount']) ? (int)$_POST['amount'] : ''; ?>">
    <input type="hidden" name="btype" value="<?php echo isset($_POST['btype']) ? (int)$_POST['btype'] : ''; ?>">
    <input type="hidden" name="type" value="<?php echo isset($_POST['type']) ? (int)$_POST['type'] : ''; ?>">
</form>
<?php
// Los diálogos de confirmación tienen que anunciar lo mismo que después acredita
// Inventory.php. Los dos consumibles dependen del casco puesto: el rollo, del bono de
// experiencia, y la obra de arte, de la producción diaria (que incluye el casco de
// cultura) recortada por el tope que promete el objeto (artworkCulturePointsCap()).
$currentCulturePoints = (int)$database->getUserField($session->uid, 'cp', 0);
$cultureDailyProduction = artworkCulturePoints($database, (int)$session->uid);
$artworkLastUsed = (int)$database->getUserField($session->uid,'artwork_last_used',0);
$artworkCooldownRemaining = artworkCooldownRemaining($artworkLastUsed);
$equippedHelmet = heroEquippedItem($database, (int)$session->uid, 1);
$helmetExperienceBonus = is_array($equippedHelmet)
	? getHeroHelmetBonuses((int)$equippedHelmet['type'])['experience']
	: 0;
$scrollExperience = heroExperienceWithHelmet($database, (int)$session->uid, 10);
?>
<script type="text/javascript">
	Travian.Game.Hero.Inventory = new (new Class(
	{
		b10: '<p><div style="color:#F90">Experiencia actual del héroe: <?php echo $hero['experience']; ?><br>Experiencia obtenida: <?php echo $scrollExperience; ?><br>Experiencia después de usarlo: <?php echo ($hero['experience']+$scrollExperience); ?><br></div>',

	b15: '<table id="heroInventoryDataDialog" class="transparent" cellspacing="0" cellpadding="0"><tbody><tr class="rowBeforeUse"><th>Puntos de cultura actuales:</th><td><?php echo $currentCulturePoints; ?></td></tr><tr class="rowUseValue"><th>PC obtenidos (producción diaria de la cuenta, máximo <?php echo number_format(artworkCulturePointsCap(),0,',','.'); ?>):</th><td class="displayUseValue"><?php echo $cultureDailyProduction; ?></td></tr><tr class="rowAfterUse"><th>Puntos de cultura después de usar la obra de arte:</th><td class="displayAfterUse"><?php echo ($currentCulturePoints+$cultureDailyProduction); ?></td></tr><tr><th>Límite:</th><td>Una obra de arte cada 24 horas<?php if($artworkCooldownRemaining>0){ echo ' (disponible en '.$generator->getTimeFormat($artworkCooldownRemaining).')'; } ?></td></tr></tbody></table>',
		
		alreadyOpen: false,
		lastTouchActivation: 0,
		textSingle: '¿Realmente quieres usar este objeto?',
		textMulti: 'Cantidad de objetos que se usarán: &lt;input class=\"text\" id=\"amount\" type=\"text\" value=\"\" /&gt;'.unescapeHtml(),
		initialize: function() {
			var $this = this;
			
<?php
// Los objetos de bolsa cargados (proc = 1) siguen mostrando el resto en la grilla, así
// que también necesitan handler para poder cargar más. Debe coincidir con lo que dibuja
// el bucle de arriba: prefijo "inv_item_" y la cantidad que queda sin cargar.
$sql2 = mysql_query("SELECT * FROM ".TB_PREFIX."heroitems WHERE (proc = 0 OR (btype >= 7 AND btype <= 9 AND num > type)) AND uid = $session->uid");

while($row2 = mysql_fetch_array($sql2)){
$id = $row2["id"];$num = $row2["num"];$btype = $row2["btype"];$type = $row2["type"];
	if($btype >= 7 && $btype <= 9){
		$element = "inv_item_".$id;
		$bindAmount = $num - $type;
		if($bindAmount <= 0){
			continue;
		}
	}else{
		$element = "item_".$id;
		$bindAmount = $num;
	}
?>
$this.bindItem($('<?php echo $element; ?>'), <?php echo $id; ?>, <?php echo $bindAmount; ?>, <?php echo $btype; ?>, <?php echo $type; ?>, false);
<?php
}
?>
								},
		bindItem: function(element, id, amount, btype, type, useImmediately){
			var $this = this;
			var touchStart = null;
			var touchMoved = false;
			var activate = function(){
                $this.openItemDetails(id, amount, btype, type);
			};

			if(!element){
				return;
			}

			element.set({role: 'button', tabindex: '0'});
			element.addEvent('click', function(event){
				if((new Date()).getTime() - $this.lastTouchActivation < 700){
					if(event){ event.stop(); }
					return;
				}
				activate();
			});
			element.addEvent('touchstart', function(event){
				var originalEvent = event.event || event;
				var touch = originalEvent.touches && originalEvent.touches[0];
				touchStart = touch ? {x: touch.pageX, y: touch.pageY} : null;
				touchMoved = false;
			});
			element.addEvent('touchmove', function(event){
				var originalEvent = event.event || event;
				var touch = originalEvent.touches && originalEvent.touches[0];
				if(touchStart && touch && (Math.abs(touch.pageX - touchStart.x) > 10 || Math.abs(touch.pageY - touchStart.y) > 10)){
					touchMoved = true;
				}
			});
			element.addEvent('touchend', function(event){
				if(touchMoved){
					return;
				}
				$this.lastTouchActivation = (new Date()).getTime();
				if(event){ event.stop(); }
				activate();
			});
			element.addEvent('keydown', function(event){
				if(event.code == 13 || event.code == 32){
					event.stop();
					activate();
				}
			});
		},
        itemDetails: <?php echo json_encode($inventoryItemDetails, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>,
        heroDead: <?php echo (int)$hero['dead']; ?>,
        openItemDetails: function(id, amount, btype, type){
            if(this.alreadyOpen){ return; }
            var self = this, info = this.itemDetails[id];
            if(!info){ return; }
            this.alreadyOpen = true;
            var escape = function(value){ return new Element('span', {text: value}).get('html'); };
            var html = '<div class="heroItemDetails"><div class="item item_'+info.icon+'" style="position:relative;float:left;margin:0 16px 12px 0"></div>'+
                '<p>'+info.description+'</p><div class="clear"></div><p>Disponibles: '+amount+'</p>'+
                (amount===1 ? '<input id="itemDetailsAmount" type="hidden" value="1">' :
                '<label>Cantidad: <input id="itemDetailsAmount" class="text" type="number" min="1" max="'+amount+'" value="'+(btype===12 || btype===13 || btype===15 ? 1 : amount)+'" style="width:65px"></label>')+
                '<p id="itemDetailsReward"></p><p id="itemDetailsError" class="error" role="alert"></p>'+
                '<div id="itemDetailsActions"></div></div>';
            html.dialog({relativeTo: $('content'), title: escape(info.name), buttonOk: false,
                elementFocus: amount===1 ? null : 'itemDetailsAmount',
                onOpen: function(dialog){
                    var input = $('itemDetailsAmount');
                    var reward = function(){
                        return info.liquidationPerUnit ? info.liquidationPerUnit*Number(input.value) : (info.stackable ? Math.floor(Number(input.value)/10) : 10);
                    };
                    var update = function(){
                        $('itemDetailsReward').set('text', 'Liquidar: '+reward()+' de plata. La liquidación es definitiva.');
                    };
                    input.addEventListener('input', update);
                    input.addEventListener('change', update);
                    update();
                    ['Equipar','Subastar','Liquidar'].each(function(label, index){
                        var button = dialog.buttonOk.clone().removeProperty('id');
                        button.removeClass('ok').setProperty('type','button').setStyle('display','inline-block');
                        button.getElement('.text').set('text', index===0 && btype>9 ? 'Usar' : label);
                        button.setStyle('margin','6px');
                        button.inject($('itemDetailsActions'));
                        var blocked = index===0 ? (self.heroDead && (btype<=11 || btype===13)) : !info.sellable;
                        button.disabled = !!blocked;
                        if(blocked){
                            button.addClass('disabled');
                            button.title = index===0 ? 'Tu héroe está muerto.' : 'Primero retira este objeto de la bolsa del héroe.';
                        }
                        button.addEvent('click', function(event){
                            event.stop();
                            var quantity = Number(input.value);
                            if(!isFinite(quantity) || quantity<1 || quantity>amount || Math.floor(quantity)!==quantity){
                                $('itemDetailsError').set('text','Elige una cantidad válida.'); return;
                            }
                            if(index===0){
                                if(btype===12 || btype===13 || btype===15){ quantity=1; }
                                dialog.close();
                                self.showItem(id, quantity, btype, type);
                                return;
                            }
                            if(!info.stackable && quantity!==amount){
                                $('itemDetailsError').set('text','Este objeto debe venderse completo.'); return;
                            }
                            if(index===2 && reward()<1){
                                $('itemDetailsError').set('text','Debes liquidar al menos 10 unidades.'); return;
                            }
                            if(index===2){
                                var confirmation = '¿Liquidar '+quantity+' × '+info.name+' por '+reward()+' de plata? Esta acción es definitiva.';
                                if(!window.confirm(confirmation)){ return; }
                            }
                            var form = $('HeroInventory');
                            form.setAttribute('action', index===1 ? 'hero_auction.php?action=sell' : 'hero_inventory.php');
                            form.elements.a.value = index===1 ? 'e45' : 'inventoryLiquidate';
                            form.elements.id.value = id;
                            form.elements.amount.value = quantity;
                            button.disabled = true;
                            form.submit();
                        });
                    });
                },
                onClose: function(){ self.alreadyOpen=false; }
            });
        },
		showItem: function (id, amount, btype, type){
			var $this = this;
			$('HeroInventory').elements.id.value = id;
			$('HeroInventory').elements.amount.value = amount;
			$('HeroInventory').elements.btype.value = btype;
			$('HeroInventory').elements.type.value = type;
			$('HeroInventory').submit();
		},
		sellItem: function (id, amount, btype, type){
			var html = '';
			var $this = this;
			if (this.alreadyOpen){
				return;
			}
			this.alreadyOpen = true;
			if(btype == 15){ amount = 1; }
			$('HeroInventory').elements.id.value = id;
			$('HeroInventory').elements.amount.value = amount;
			$('HeroInventory').elements.btype.value = btype;
			$('HeroInventory').elements.type.value = type;
			if (amount == 1){
				if(btype == 10){
					html = $this.textSingle;
					html += this.b10;
				}else
				if(btype == 15){
					html = $this.textSingle;
					html += this.b15;
				}else{
					html = $this.textSingle;
				}
			}else{
				if(btype == 10){
					exp_a = '<?php echo $hero['experience']; ?>';
					// El bono del casco se aplica sobre el total, no por rollo: hacerlo
					// por unidad trunca de más (3 rollos con +15% son 34, no 3x11).
					exp_b = Math.floor(amount*10*(100+<?php echo (int)$helmetExperienceBonus; ?>)/100);
					exp_total = <?php echo $hero['experience']; ?>+exp_b;
					html = $this.textMulti;
					html += '<table id="heroInventoryDataDialog" class="transparent" cellspacing="0" cellpadding="0"><tbody><tr class="rowBeforeUse"><th>Experiencia actual del héroe:</th><td>'+exp_a+'</td></tr><tr class="rowUseValue"><th>Experiencia obtenida al usar los pergaminos:</th><td class="displayUseValue">'+exp_b+'</td></tr><tr class="rowAfterUse"><th>Experiencia del héroe después de usarlos:</th><td class="displayAfterUse">'+exp_total+'</td></tr></tbody></table>';

				}else
				if(btype == 15){
					// Cada obra de arte concede un día de producción, no los PC que el
					// jugador ya tiene acumulados, que es lo que se mostraba acá.
					cp = <?php echo $currentCulturePoints; ?>;
					cp_b = <?php echo $cultureDailyProduction; ?>*amount;
					cp_total = cp+cp_b;
					html = $this.textMulti;
					html += '<table id="heroInventoryDataDialog" class="transparent" cellspacing="0" cellpadding="0"><tbody><tr class="rowBeforeUse"><th>Puntos de cultura actuales:</th><td>'+cp+'</td></tr><tr class="rowUseValue"><th>Puntos de cultura obtenidos al usar las obras de arte:</th><td class="displayUseValue">'+cp_b+'</td></tr><tr class="rowAfterUse"><th>Puntos de cultura después de usarlas:</th><td class="displayAfterUse">'+cp_total+'</td></tr></tbody></table>';
					
				}else{
					html = $this.textMulti;
				}
			}
			html.dialog({
				relativeTo:			$('content'),
				elementFoucs:		'inventoryAmount',
				buttonTextOk:		'Aceptar',
				buttonTextCancel:	'Cancelar',
				title:				'Uso del objeto',
				onOpen: function(dialog, contentElement){
					if ($('amount')){
						$('amount').value = amount;
						$('amount').addEvent('change', function(){
							$('HeroInventory').elements.amount.value = $('amount').value;
						});
					}
				},
				onOkay: function(dialog, contentElement){
					if ($('amount')){
						$('HeroInventory').elements.amount.value = $('amount').value;
					}
					$('HeroInventory').submit();
				},
				onClose: function(dialog, contentElement){
					$this.alreadyOpen = false;
				}
			});
		}
	}));
</script>
<div class="clear">&nbsp;</div>
</div>
<div class="clear"></div>
</div>

                        <div class="contentFooter">&nbsp;</div>

					</div>
<?php
include("Templates/sideinfo.tpl"); 
include("Templates/footer.tpl");
include("Templates/header.tpl");
include("Templates/res.tpl");
include("Templates/vname.tpl");
include("Templates/quest.tpl");
?>

</div>
<div id="ce"></div>
</div>
</body>
</html>
