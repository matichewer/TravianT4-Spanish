<div id="auction">
<span class="error"><?php echo htmlspecialchars((string)$bidError,ENT_QUOTES,'UTF-8'); ?></span>
<div class="silverAmount">
<div id="filter">
	<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">		<div class="wrapper">
			<div class="silver">
				<img title="Plata" class="silver" src="img/x.gif"> <?php $now = $database->getAuctionSilver($session->uid); echo ($session->silver - $now['silver']); ?> / <?php echo $session->silver; ?> </div>

						<div class="clear"></div>
		</div>
		</div>
				</div></div></div>
<div class="clear"></div>
<?php

$prefix = "".TB_PREFIX."auction";

$sql = mysql_query("SELECT * FROM $prefix WHERE finish = 0 AND owner = $session->uid ORDER BY time ASC");
$query = mysql_num_rows($sql); // Get the number of items already in auction

$typeArray = array("","helmet","body","leftHand","rightHand","shoes","horse","bandage25","bandage33","cage","scroll","ointment","bucketOfWater","bookOfWisdom","lawTables","artWork");

$outputList = '';
$timer = 1;
if($query == 0) {
    $outputList .= "<td colspan=\"6\" class=\"none\"><center>Sin objetos</center></td>";
}else{
	while($row = mysql_fetch_array($sql)){
$id = $row["id"];$owner = $row["owner"];$btype = $row["btype"];$type = $row["type"];$num = $row["num"];$uid = $row["uid"];$bids = $row["bids"];$silver = $row["silver"];$time = $row["time"];
include "Templates/Auction/alt.tpl";
    if($bids!=0){
    $outputList .= "<tr><td class=\"delete\"><img class=\"del inactive\" src=\"img/x.gif\" title=\"Cancelar\"></td><td class=\"icon\"><img class=\"itemCategory itemCategory_".$typeArray[$btype]."\" src=\"img/x.gif\" title=\"".$title."\"></td>";
    }else{
    $outputList .= "<tr><td class=\"delete\"><a href=\"?action=sell&amp;abort=".$id."&amp;c=".rawurlencode((string)$session->mchecker)."\"><img class=\"del\" src=\"img/x.gif\" title=\"Cancelar\"></a></td><td class=\"icon\"><img class=\"itemCategory itemCategory_".$typeArray[$btype]."\" src=\"img/x.gif\" title=\"".$title."\"></td>";
    }

	$outputList .= "<td class=\"name\">".$num." x ".$name."</td>";
	$outputList .= "<td class=\"bids\">";
    if($bids==0){ $outputList .= "<span class=\"none\">".$bids."</span>"; }else{ $outputList .= $bids; }
    $outputList .= "</td>";
	$outputList .= "<td class=\"silver\" title=\"".round($silver/$num, 2)." por cada unidad\">".$silver."</td>";
	$outputList .= "<td class=\"time\"><span id=\"timer".$timer."\">".$generator->getTimeFormat($time-time())."</span></td>";
	$outputList .= "";
   	$outputList .= "</tr>";

    $timer++;
	}
}
echo 'Actualmente tienes ' . $query . ' objetos en venta en la subasta (el máximo permitido a la vez es 5)<br><br>';
$maxReached = ($query == 5 ? true : false);
?>
<table class="sellings" cellspacing="1" cellpadding="1">
	<thead>
		<tr>
			<th class="name" colspan="3">Descripción</th>
			<th class="bids"><img title="Ofertas" class="bids" src="img/x.gif"></th>
			<th class="silver"><img title="Plata" class="silver" src="img/x.gif"></th>
			<th class="time"><img title="Tiempo" class="clock" src="img/x.gif"></th>
		</tr>
	</thead>

    <tbody>
		<?php echo $outputList; ?>
	</tbody>
</table>


<?php
$prefix = "".TB_PREFIX."heroitems";

$sql2 = mysql_query("SELECT * FROM $prefix WHERE proc = 0 AND uid = $session->uid");
$query2 = mysql_num_rows($sql2);

$outputList = '';
$disposalItems = array();
if($query2==0){
	$outputList .= "<span class='none'>Subastas finalizadas.</span>";
}else{
while($row = mysql_fetch_array($sql2)){
$id = $row["id"];$uid = $row["uid"];$btype = $row["btype"];$type = $row["type"];$num = $row["num"];$proc = $row["proc"];

include "Templates/Auction/alt.tpl";
$disposalItems[] = array(
	'id'=>(int)$id,
	'name'=>(string)$name,
	'num'=>(int)$num,
	'stackable'=>heroItemIsAuctionStackable($btype)
);

   	$outputList .= "<div class=\"\" title=\"".$name."||".$title."\" id=\"item_".$id."\">";
	$outputList .= "<div class=\"itemInInventory item item_".$item." inventory\">";
	$outputList .= "<div class=\"amount\">".$num."</div>";
	$outputList .= "</div></div>";

}
}
?>

<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">

<div class="hero_inventory">

<div id="itemsToSale">
<?php echo $outputList; ?>
		<div class="clear"></div>
</div>
</div>

	</div>
				</div><div class="clear"></div>
<?php if(!empty($disposalItems)){ ?>
<div class="boxes boxesColor gray"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents cf">
	<h4>Gestionar objetos no deseados</h4>
	<p>Elige qué hacer con los objetos que ya no necesitas. Liquidar paga el 10 % del precio inicial; descartar los elimina sin entregar plata.</p>
	<form id="disposeHeroItemForm" method="post" action="hero_auction.php?action=sell">
		<input type="hidden" name="a" value="disposeHeroItem">
		<input type="hidden" name="c" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
		<input type="hidden" name="disposalAction" value="">
		<table class="transparent" style="margin-top:10px">
			<tbody>
				<tr>
					<th style="width:90px"><label for="disposeHeroItemId">Objeto</label></th>
					<td><select id="disposeHeroItemId" name="id" style="width:280px" onchange="updateHeroItemDisposal(true)">
						<?php foreach($disposalItems as $disposalItem){ ?>
						<option value="<?php echo $disposalItem['id']; ?>" data-name="<?php echo htmlspecialchars($disposalItem['name'],ENT_QUOTES,'UTF-8'); ?>" data-amount="<?php echo $disposalItem['num']; ?>" data-stackable="<?php echo $disposalItem['stackable'] ? '1' : '0'; ?>"><?php echo $disposalItem['num'].' × '.htmlspecialchars($disposalItem['name'],ENT_QUOTES,'UTF-8'); ?></option>
						<?php } ?>
					</select></td>
				</tr>
				<tr>
					<th><label for="disposeHeroItemAmount">Cantidad</label></th>
					<td><input class="text" id="disposeHeroItemAmount" name="amount" type="number" min="1" value="1" style="width:55px" onchange="updateHeroItemDisposal(false)" onkeyup="updateHeroItemDisposal(false)"> <span id="disposeHeroItemValue"></span></td>
				</tr>
			</tbody>
		</table>
		<p id="disposeHeroItemWarning" style="margin:8px 0 0"><b>Estas acciones son definitivas.</b> Revisa el objeto y la cantidad antes de confirmar.</p>
		<div style="margin-top:12px;text-align:right">
			<button type="button" onclick="submitHeroItemDisposal('liquidate')"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents" id="disposeLiquidateLabel">Liquidar</div></div></button>
			<button type="button" style="margin-left:8px" onclick="submitHeroItemDisposal('discard')"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Descartar sin plata</div></div></button>
		</div>
		<div class="clear"></div>
	</form>
</div></div>
<script type="text/javascript">
function selectedHeroDisposalItem(){
	var select = document.getElementById('disposeHeroItemId');
	return select.options[select.selectedIndex];
}
function updateHeroItemDisposal(resetAmount){
	var option = selectedHeroDisposalItem();
	var input = document.getElementById('disposeHeroItemAmount');
	var stackable = option.getAttribute('data-stackable') === '1';
	var maximum = parseInt(option.getAttribute('data-amount'),10);
	input.max = maximum;
	input.readOnly = !stackable;
	if(resetAmount || !stackable || parseInt(input.value,10)>maximum || parseInt(input.value,10)<1){
		input.value = maximum;
	}
	var amount = parseInt(input.value,10) || 0;
	var reward = stackable ? Math.floor(amount/10) : 10;
	document.getElementById('disposeHeroItemValue').innerHTML = stackable && amount<10
		? '<span class="error">Mínimo para liquidar: 10 unidades.</span>'
		: 'Recibirás <b>'+reward+' de plata</b>.';
	document.getElementById('disposeLiquidateLabel').innerHTML = reward>0
		? 'Liquidar por '+reward+' de plata'
		: 'Liquidar';
}
function submitHeroItemDisposal(action){
	var option = selectedHeroDisposalItem();
	var input = document.getElementById('disposeHeroItemAmount');
	var stackable = option.getAttribute('data-stackable') === '1';
	var maximum = parseInt(option.getAttribute('data-amount'),10);
	var amount = stackable ? parseInt(input.value,10) : maximum;
	if(!amount || amount<1 || amount>maximum){
		alert('Elige una cantidad válida.');
		return;
	}
	if(action==='liquidate' && stackable && amount<10){
		alert('Debes liquidar al menos 10 unidades para recibir 1 de plata.');
		return;
	}
	var name = option.getAttribute('data-name');
	var reward = stackable ? Math.floor(amount/10) : 10;
	var message = action==='liquidate'
		? '¿Liquidar definitivamente '+amount+' × '+name+' por '+reward+' de plata?'
		: '¿Descartar definitivamente '+amount+' × '+name+' sin recibir plata?';
	if(confirm(message)){
		document.getElementById('disposeHeroItemForm').disposalAction.value = action;
		document.getElementById('disposeHeroItemForm').submit();
	}
}
updateHeroItemDisposal(true);
</script>
<?php } ?>
<?php
$prefix = "".TB_PREFIX."auction";

$sql = mysql_query("SELECT * FROM $prefix WHERE finish = 1 and owner = $session->uid ORDER BY time ASC");
$query = mysql_num_rows($sql); // Obtener el número de consultas de la base de datos

if (isset($_GET['page'])) { // Obtener el número de página
    $page = preg_replace('#[^0-9]#i', '', $_GET['page']); // Filtrar todo excepto los números
} else {
    $page = 1;
}

$itemsPerPage = 10; //Número de elementos mostrados por página
$lastPage = ceil($query / $itemsPerPage); // Obtener el número de la última página

if ($page < 1) {
    $page = 1;
} else if ($page > $lastPage) {
    $page = $lastPage;
}

$centerPages = "";
$sub1 = $page - 1;
$sub2 = $page - 2;
$sub3 = $page - 3;
$add1 = $page + 1;
$add2 = $page + 2;
$add3 = $page + 3;

if ($page <= 1 && $lastPage <= 1) {
    $centerPages .= '<span class="number currentPage">1</span>';

}elseif ($page == 1 && $lastPage == 2) {
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=2">2</a>';

}elseif ($page == 1 && $lastPage == 3) {
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=2">2</a> ';
    $centerPages .= '<a class="number" href="?action=sell&page=3">3</a>';

}elseif ($page == 1) {
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $add1 . '">' . $add1 . '</a> ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $add2 . '">' . $add2 . '</a> ... ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $lastPage . '">' . $lastPage . '</a>';

} else if ($page == $lastPage && $lastPage == 2) {
	$centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span>';

} else if ($page == $lastPage && $lastPage == 3) {
	$centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ';
    $centerPages .= '<a class="number" href="?action=sell&page=2">2</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span>';

} else if ($page == $lastPage) {
	$centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ... ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $sub2 . '">' . $sub2 . '</a> ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $sub1 . '">' . $sub1 . '</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span>';

} else if ($page == ($lastPage - 1) && $lastPage == 3) {
    $centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $lastPage . '">' . $lastPage . '</a>';

} else if ($page > 2 && $page < ($lastPage - 1)) {
    $centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ... ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $sub1 . '">' . $sub1 . '</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $add1 . '">' . $add1 . '</a> ... ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $lastPage . '">' . $lastPage . '</a>';

}else if ($page == ($lastPage - 1)) {
    $centerPages .= '<a class="number" href="?action=sell&page=1">1</a> ... ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $sub1 . '">' . $sub1 . '</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $lastPage . '">' . $lastPage . '</a>';

} else if ($page > 1 && $page < $lastPage && $lastPage == 3) {
    $centerPages .= '<a class="number" href="?action=sell&page=' . $sub1 . '">' . $sub1 . '</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $add1 . '">' . $add1 . '</a>';

} else if ($page > 1 && $page < $lastPage) {
    $centerPages .= '<a class="number" href="?action=sell&page=' . $sub1 . '">' . $sub1 . '</a> ';
    $centerPages .= '<span class="number currentPage">' . $page . '</span> ';
    $centerPages .= '<a class="number" href="?action=sell&page=' . $add1 . '">' . $add1 . '</a> ... ';
	$centerPages .= '<a class="number" href="?action=sell&page=' . $lastPage . '">' . $lastPage . '</a>';
}



$paginationDisplay = "";
$nextPage = $_GET['page'] + 1;
$previous = $_GET['page'] - 1;

if ($page == "1" && $lastPage == "1"){
$paginationDisplay .=  '<img alt="Primera página" src="img/x.gif" class="first disabled"> ';
$paginationDisplay .=  '<img alt="Página anterior" src="img/x.gif" class="previous disabled">';
$paginationDisplay .= $centerPages;
$paginationDisplay .=  '<img alt="Página siguiente" src="img/x.gif" class="next disabled"> ';
$paginationDisplay .=  '<img alt="Última página" src="img/x.gif" class="last disabled">';

}elseif ($lastPage == 0){
$paginationDisplay .=  '<img alt="Primera página" src="img/x.gif" class="first disabled"> ';
$paginationDisplay .=  '<img alt="Página anterior" src="img/x.gif" class="previous disabled">';
$paginationDisplay .= $centerPages;
$paginationDisplay .=  '<img alt="Página siguiente" src="img/x.gif" class="next disabled"> ';
$paginationDisplay .=  '<img alt="Última página" src="img/x.gif" class="last disabled">';

}elseif ($page == "1" && $lastPage != "1"){
$paginationDisplay .=  '<img alt="Primera página" src="img/x.gif" class="first disabled"> ';
$paginationDisplay .=  '<img alt="Página anterior" src="img/x.gif" class="previous disabled">';
$paginationDisplay .= $centerPages;
$paginationDisplay .=  '<a class="next" href="?action=sell&page=' . $nextPage . '"><img alt="Página siguiente" src="img/x.gif"></a> ';
$paginationDisplay .=  '<a class="last" href="?action=sell&page=' . $lastPage . '"><img alt="Última página" src="img/x.gif"></a>';

}elseif ($page != "1" && $page != $lastPage){
$paginationDisplay .=  '<a class="first" href="?action=sell&page=1"><img alt="Primera página" src="img/x.gif"></a> ';
$paginationDisplay .=  '<a class="previous" href="?action=sell&page=' . $previous . '"><img alt="Página anterior" src="img/x.gif"></a>';
$paginationDisplay .= $centerPages;
$paginationDisplay .=  '<a class="next" href="?action=sell&page=' . $nextPage . '"><img alt="Página siguiente" src="img/x.gif"></a> ';
$paginationDisplay .=  '<a class="last" href="?action=sell&page=' . $lastPage . '"><img alt="Última página" src="img/x.gif"></a>';

}elseif ($page == $lastPage){
$paginationDisplay .=  '<a class="first" href="?action=sell&page=1"><img alt="Primera página" src="img/x.gif"></a> ';
$paginationDisplay .=  '<a class="previous" href="?action=sell&page=' . $previous . '"><img alt="Página anterior" src="img/x.gif"></a>';
$paginationDisplay .= $centerPages;
$paginationDisplay .=  '<img alt="Página siguiente" src="img/x.gif" class="next disabled"> ';
$paginationDisplay .=  '<img alt="Última página" src="img/x.gif" class="last disabled">';
}

$limit = 'LIMIT ' .($page - 1) * $itemsPerPage .',' .$itemsPerPage;
$sql2 = mysql_query("SELECT * FROM $prefix WHERE finish = 1 and owner = $session->uid ORDER BY time DESC $limit");

$typeArray = array("","helmet","body","leftHand","rightHand","shoes","horse","bandage25","bandage33","cage","scroll","ointment","bucketOfWater","bookOfWisdom","lawTables","artWork");

$outputList = '';
$timer = 1;
if($query == 0) {
    $outputList .= "<td colspan=\"6\" class=\"none\"><center>No se encontraron ventas.</center></td>";
}else{
	while($row = mysql_fetch_array($sql2)){
$id = $row["id"];$owner = $row["owner"];$btype = $row["btype"];$type = $row["type"];$num = $row["num"];$uid = $row["uid"];$bids = $row["bids"];$silver = $row["silver"];$time = $row["time"];
include "Templates/Auction/alt.tpl";
    if($bids!=0){ $inac=" inactive"; }
    $outputList .= "<tr><td class=\"icon\"><img class=\"itemCategory itemCategory_".$typeArray[$btype]."\" src=\"img/x.gif\" title=\"".$name."||".$title."\"></td>";

	$outputList .= "<td class=\"name\">".$num." x ".$name."</td>";
	$outputList .= "<td class=\"bids\">";
    if($bids==0){ $outputList .= "<span class=\"none\">".$bids."</span>"; }else{ $outputList .= $bids; }
    $outputList .= "</td>";
	$outputList .= "<td class=\"silver\" title=\"".round($silver/$num, 2)." por unidad\">".$silver."</td>";
	$outputList .= "<td class=\"time\">".date('y/m/d',$time)." ".date('H:i',$time)."</td>";
	$outputList .= "";
   	$outputList .= "</tr>";

    $timer++;
	}
 }


?>
<h4 class="auctionEnded spacer">Subastas finalizadas</h4>
<table cellspacing="1" cellpadding="1">
	<thead>
		<tr>
			<th class="name" colspan="2">Descripción</th>
			<th class="bids"><img title="Ofertas" class="bids" src="img/x.gif"></th>
			<th class="silver"><img title="Plata" class="silver" src="img/x.gif"></th>
			<th class="time"><img title="Tiempo" class="clock" src="img/x.gif"></th>
		</tr>
	</thead>
	<tbody>
    <?php echo $outputList; ?>
	</tbody>
</table>

<div class="footer">
	<div class="paginator">
    <?php echo $paginationDisplay; ?>
    </div>
    <div class="clear"></div>
</div>
<form id="sellForm" method="post" action="hero_auction.php?action=sell">
	<input type="hidden" name="a" value="e45">
	<input type="hidden" name="c" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
	<input type="hidden" name="id" value="<?php echo isset($_POST['id']) ? (int)$_POST['id'] : ''; ?>">
	<input type="hidden" name="amount" value="<?php echo isset($_POST['amount']) ? (int)$_POST['amount'] : ''; ?>">
</form>
<script type="text/javascript">
	Travian.Game.HeroAuction = new (new Class(
	{
		alreadyOpen: false,
		textSingle: '¿Realmente quieres vender este objeto?',
		textMulti: 'Vender &lt;input class=\"text\" id=\"sellAmount\" style=\"width:30px\" type=\"text\" value=\"0\" /&gt; unidades'.unescapeHtml(),
		initialize: function() {
			var $this = this;
<?php
$prefix = "".TB_PREFIX."heroitems";

$sql2 = mysql_query("SELECT * FROM $prefix WHERE proc = 0 AND uid = $session->uid");

while($row = mysql_fetch_array($sql2)){
$id = $row["id"];$num = $row["num"];
?>
				$('item_<?php echo $id; ?>').addEvent('click', function() { $this.sellItem(<?php echo $id; ?>,<?php echo $num; ?>); });
<?php } ?>

							},
		sellItem: function (id, amount)
        {
            var maxReached = "<?php echo $maxReached; ?>";
            if (maxReached)
            {
                return;
            }
            var html = '';
			var $this = this;
			if (this.alreadyOpen)
			{
				return;
			}
			this.alreadyOpen = true;
			$('sellForm').id.value = id;
			$('sellForm').amount.value = amount;
			if (amount == 1)
			{
				html = $this.textSingle;
			}
			else
			{
				html = $this.textMulti;
			}
			html.dialog(
			{
				relativeTo:			$('content'),
				elementFoucs:		'sellAmount',
				buttonTextOk:		'OK',
				buttonTextCancel:	'CANCELAR',
				title:				'Confirmar venta:',
				onOpen: function(dialog, contentElement)
				{
					if ($('sellAmount'))
					{
						$('sellAmount').value = amount;
						$('sellAmount').addEvent('change', function()
						{
							$('sellForm').amount.value = $('sellAmount').value;
						});
					}
				},
				onOkay: function(dialog, contentElement)
				{
					if ($('sellAmount'))
					{
						$('sellForm').amount.value = $('sellAmount').value;
					}
					$('sellForm').submit();
				},
				onClose: function(dialog, contentElement)
				{
					$this.alreadyOpen = false;
				}
			});
		}
	}));
</script>
</div>
