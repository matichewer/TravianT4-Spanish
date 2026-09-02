<?php
$mainbuilding = $building->getTypeLevel(15);
$cranny = $building->getTypeLevel(23);
$granary = $building->getTypeLevel(11);
$warehouse = $building->getTypeLevel(10);
$embassy = $building->getTypeLevel(18);
$wall = $village->resarray['f40'];
$rallypoint = $building->getTypeLevel(16);
$hero = $building->getTypeLevel(37);
$market = $building->getTypeLevel(17);
$barrack = $building->getTypeLevel(19);
$cropland = $building->getTypeLevel(4);
$grainmill = $building->getTypeLevel(8);
$residence = $building->getTypeLevel(25);
$academy = $building->getTypeLevel(22);
$woodcutter = $building->getTypeLevel(1);
$palace = $building->getTypeLevel(26);
$claypit = $building->getTypeLevel(2);
$ironmine = $building->getTypeLevel(3);
$blacksmith = $building->getTypeLevel(12);
$stable = $building->getTypeLevel(20);
$trapper = $building->getTypeLevel(36);
$treasury = $building->getTypeLevel(27);
$sawmill = $building->getTypeLevel(5);
$brickyard = $building->getTypeLevel(6);
$ironfoundry = $building->getTypeLevel(7);
$workshop = $building->getTypeLevel(21);
$stonemasonslodge = $building->getTypeLevel(34);
$townhall = $building->getTypeLevel(24);
$tournamentsquare = $building->getTypeLevel(14);
$bakery = $building->getTypeLevel(9);
$tradeoffice = $building->getTypeLevel(28);
$greatbarracks = $building->getTypeLevel(29);
$greatstable = $building->getTypeLevel(30);
$brewery = $building->getTypeLevel(35);
$horsedrinkingtrough = $building->getTypeLevel(41);
$herosmansion = $building->getTypeLevel(37);
$greatwarehouse = $building->getTypeLevel(38);
$greatgranary = $building->getTypeLevel(39);  
$greatworkshop = $building->getTypeLevel(42);

// Map by building id: procResType() is now translated, so variable names
// can no longer be derived from the English building name
$UnderConstructionVars = array(1=>'woodcutter',2=>'claypit',3=>'ironmine',4=>'cropland',5=>'sawmill',6=>'brickyard',7=>'ironfoundry',8=>'grainmill',9=>'bakery',10=>'warehouse',11=>'granary',12=>'blacksmith',14=>'tournamentsquare',15=>'mainbuilding',16=>'rallypoint',17=>'marketplace',18=>'embassy',19=>'barracks',20=>'stable',21=>'workshop',22=>'academy',23=>'cranny',24=>'townhall',25=>'residence',26=>'palace',27=>'treasury',28=>'tradeoffice',29=>'greatbarracks',30=>'greatstable',31=>'citywall',32=>'earthwall',33=>'palisade',34=>'stonemasonslodge',35=>'brewery',36=>'trapper',37=>'herosmansion',38=>'greatwarehouse',39=>'greatgranary',40=>'wonderoftheworld',41=>'horsedrinkingtrough',42=>'greatworkshop');
foreach ($database->getJobs($_SESSION['wid']) as $bdata) {
    if(!isset($UnderConstructionVars[$bdata['type']])) continue;
    $UnderConstruction = $UnderConstructionVars[$bdata['type']];
    $$UnderConstruction = ($$UnderConstruction == 0 ? -1 : $$UnderConstruction);
}
?>
<h1 class="titleInHeader">Construcción de nuevo edificio</h1>
<div id="build" class="gid0">
<?php
if($building->meetRequirement(15) && $id != 39 && $id != 40) {
    include("avaliable/mainbuilding.tpl");
}
if($building->meetRequirement(23) && $id != 39 && $id != 40) {
    include("avaliable/cranny.tpl");
}
if($building->meetRequirement(11) && $id != 39 && $id != 40) {
    include("avaliable/granary.tpl");
}
if($wall == 0 && !$database->getBuildList(31) && !$database->getBuildList(32) && !$database->getBuildList(33)) {
    if($session->tribe == 1 && $id != 39) {
    include("avaliable/citywall.tpl");
    }
    if($session->tribe == 2 && $id != 39) {
    include("avaliable/earthwall.tpl");
    }
    if($session->tribe == 3 && $id != 39) {
    include("avaliable/palisade.tpl");
    }
    if($session->tribe == 4 && $id != 39) {
    include("avaliable/earthwall.tpl");
    }
     if($session->tribe == 5 && $id != 39) {
    include("avaliable/citywall.tpl");
    }
}
if($building->meetRequirement(10) && $id != 39 && $id != 40) {
include("avaliable/warehouse.tpl");
}
$storageArtefact = $building->hasStorageArtefact();
if($building->meetRequirement(38)) {
    include("avaliable/greatwarehouse.tpl");
}
if($building->meetRequirement(39)) {
    include("avaliable/greatgranary.tpl");
}
if($building->meetRequirement(36) && $id != 39 && $id != 40) {
include("avaliable/trapper.tpl");
}
if($rallypoint == 0 && !$database->getBuildList(16) && $id != 40) {
include("avaliable/rallypoint.tpl");
}
if($building->meetRequirement(18) && $id != 39 && $id != 40) {
include("avaliable/embassy.tpl");
}
if($building->meetRequirement(37) && $id != 39 && $id != 40) {
include("avaliable/hero.tpl");
}
if($building->meetRequirement(19) && $id != 39 && $id != 40) {
include("avaliable/barracks.tpl");
}
if($building->meetRequirement(8) && $id != 39 && $id != 40) {
include("avaliable/grainmill.tpl");
}
if($building->meetRequirement(17) && $id != 39 && $id != 40) {
include("avaliable/marketplace.tpl");
}
if($building->meetRequirement(25) && $id != 39 && $id != 40) {
include("avaliable/residence.tpl");
}
if($building->meetRequirement(22) && $id != 39 && $id != 40) {
include("avaliable/academy.tpl");
}
if($building->meetRequirement(26) && $id != 39 && $id != 40) {
    include("avaliable/palace.tpl");
}
if($building->meetRequirement(12) && $id != 39 && $id != 40) {
include("avaliable/blacksmith.tpl");
}
if($building->meetRequirement(34) && $id != 39 && $id != 40) {
include("avaliable/stonemason.tpl");
}
if($building->meetRequirement(20) && $id != 39 && $id != 40) {
include("avaliable/stable.tpl");
}
if($building->meetRequirement(27) && $id != 39 && $id != 40) {
include("avaliable/treasury.tpl");
}
if($building->meetRequirement(6) && $id != 39 && $id != 40) {
include("avaliable/brickyard.tpl");
}
if($building->meetRequirement(5) && $id != 39 && $id != 40) {
   include("avaliable/sawmill.tpl");
  }
if($building->meetRequirement(7) && $id != 39 && $id != 40) {
   include("avaliable/ironfoundry.tpl");
}
if($building->meetRequirement(21) && $id != 39 && $id != 40) {
   include("avaliable/workshop.tpl");
}
if($building->meetRequirement(14) && $id != 39 && $id != 40) {
    include("avaliable/tsquare.tpl");
}
if($building->meetRequirement(9) && $id != 39 && $id != 40) {
    include("avaliable/bakery.tpl");
}
if($building->meetRequirement(24) && $id != 39 && $id != 40) {
    include("avaliable/townhall.tpl");
}
if($building->meetRequirement(28) && $id != 39 && $id != 40) {
    include("avaliable/tradeoffice.tpl");
}
if($building->meetRequirement(41) && $id != 39 && $id != 40) {
    include("avaliable/horsedrinking.tpl");
}
if($building->meetRequirement(35) && $id != 39 && $id != 40) {
    include("avaliable/brewery.tpl");
}
if($building->meetRequirement(29) && $id != 39 && $id != 40) {
    include("avaliable/greatbarracks.tpl");
}
if($building->meetRequirement(30) && $id != 39 && $id != 40) {
    include("avaliable/greatstable.tpl");
}
if($building->meetRequirement(42) && $id != 39 && $id != 40 && GREAT_WKS) {
    include("avaliable/greatworkshop.tpl");
}
if($id != 39 && $id != 40) {
?>
<div class="switch"><a id="soon_link" class="openedClosedSwitch switchClosed" href="javascript:show_build_list('soon');">Ver construcciones disponibles próximamente</a></div>
<div id="build_list_soon" class="hide">
<?php
if($rallypoint == 0 && $session->tribe == 3) {
include("soon/trapper.tpl");
}
if($hero == 0 && !$building->meetsLevelRequirements(37)){
    include("soon/hero.tpl");
}
if($barrack == 0 && !$building->meetsLevelRequirements(19)) {
    include("soon/barracks.tpl");
}
if($grainmill == 0 && !$building->meetsLevelRequirements(8)) {
   include("soon/grainmill.tpl");
}
// El nivel del Mercado se guarda en $market. La condición miraba una variable con otro
// nombre, que no existe: siempre daba cierta, así que la aldea veía el Mercado anunciado
// como "próximamente" aunque ya lo tuviera construido (y con un notice por variable
// indefinida en cada carga de la lista).
if($market == 0 && !$building->meetsLevelRequirements(17)) {
   include("soon/marketplace.tpl");
}
if($residence == 0 && $palace == 0 && !$building->meetsLevelRequirements(25)) {
   include("soon/residence.tpl");
}
if($academy == 0 && $barrack != 0 && !$building->meetsLevelRequirements(22)) {
   include("soon/academy.tpl");
}
if($palace == 0 && $residence == 0 && $mainbuilding >= 2 && !$building->meetRequirement(26)) {
    include("soon/palace.tpl");
}
if($blacksmith == 0 && $barrack != 0 && !$building->meetsLevelRequirements(12)) {
   include("soon/blacksmith.tpl");
}
if($stonemasonslodge == 0 && $village->capital == 1 && $palace != 0 && !$building->meetsLevelRequirements(34)) {
   include("soon/stonemason.tpl");
}
if($stable == 0 && !$database->getBuildList(20) && !$building->meetsLevelRequirements(20)) {
   include("soon/stable.tpl");
}
if($treasury == 0 && $mainbuilding >= 5 && !$building->meetsLevelRequirements(27)) {
   include("soon/treasury.tpl");
}
if($brickyard == 0 && $claypit >= 5 && $mainbuilding >= 2 && !$building->meetsLevelRequirements(6)) {
   include("soon/brickyard.tpl");
}
if($sawmill == 0 && $woodcutter >= 5 && $mainbuilding >= 2 && !$building->meetsLevelRequirements(5)) {
   include("soon/sawmill.tpl");
}
if($ironfoundry == 0 && $ironmine >= 5 && $mainbuilding >= 2 && !$building->meetsLevelRequirements(7)) {
   include("soon/ironfoundry.tpl");
}
if($workshop == 0 && $academy >= 5 && $mainbuilding >= 2 && !$building->meetsLevelRequirements(21)) {
   include("soon/workshop.tpl");
}
if($tournamentsquare == 0 && $rallypoint >= 7 && !$building->meetsLevelRequirements(14)) {
    include("soon/tsquare.tpl");
}
if($bakery == 0 && $grainmill != 0 && !$building->meetsLevelRequirements(9)) {
    include("soon/bakery.tpl");
}
if($townhall == 0 && $mainbuilding >= 5 && $academy >= 5 && !$building->meetsLevelRequirements(24)) {
    include("soon/townhall.tpl");
}
if($tradeoffice == 0 && $stable >= 5 && $market >= 10 && !$building->meetsLevelRequirements(28)) {
    include("soon/tradeoffice.tpl");
}
if($session->tribe == 1 && $horsedrinkingtrough == 0 && $rallypoint >= 5 && $stable >= 10 && !$building->meetsLevelRequirements(41)) {
    include("soon/horsedrinking.tpl");
    }
if($brewery == 0 && $village->capital == 1 && $session->tribe == 2 && $rallypoint >= 5 && $granary >= 10 && !$building->meetsLevelRequirements(35)) {
    include("soon/brewery.tpl");
}
if($village->capital == 0 && !($mainbuilding >= 10 && $storageArtefact)) {
    include("soon/greatwarehouse.tpl");
}
if($village->capital == 0 && !($mainbuilding >= 10 && $storageArtefact)) {
    include("soon/greatgranary.tpl");
} 
if($greatbarracks == 0 && $barrack >= 15 && $barrack < 20 && $village->capital == 0) {
    include("soon/greatbarracks.tpl");
}
if($greatstable == 0 && $stable >= 15 && $stable < 20 && $village->capital == 0) {
    include("soon/greatstable.tpl");
}
if($greatworkshop == 0 && $workshop >= 15 && $workshop < 20 && $village->capital == 0 && GREAT_WKS) {
    include("soon/greatworkshop.tpl");
}
   ?>
    </div>
<div class="switch"><a id="all_link" class="openedClosedSwitch switchClosed hide" href="#">Más</a></div>
    
    <div id="build_list_all" class="hide">
    <?php
    if($academy == 0 && $barrack == 0) {
    include("soon/academy.tpl");
    }
    // El Palacio es único por cuenta: eso lo sabe hasPalaceInAnotherVillage(), no un
    // escaneo a mano de fdata con in_array(26, $fila), que daba positivo con cualquier
    // columna que valiera 26 (el propio wref, sin ir más lejos).
    if($palace == 0 && $residence == 0 && $mainbuilding < 2 && !$building->meetRequirement(26)) {
        include("soon/palace.tpl");
    }
    if($blacksmith == 0 && $barrack == 0) {
    include("soon/blacksmith.tpl");
    }
    if($stonemasonslodge == 0 && $village->capital == 1 && $residence == 0 && ($palace == 0 || $mainbuilding < 2)) {
    include("soon/stonemason.tpl");
    }
    if($treasury == 0 && $mainbuilding < 5) {
    include("soon/treasury.tpl");
    }
    if($brickyard == 0 && ($claypit < 5 || $mainbuilding < 2)) {
    include("soon/brickyard.tpl");
    }
    if($sawmill == 0 && ($woodcutter < 5 || $mainbuilding < 2)) {
    include("soon/sawmill.tpl");
    }
    if($ironfoundry == 0 && ($ironmine < 5 || $mainbuilding < 2)) {
    include("soon/ironfoundry.tpl");
    }
    if($workshop == 0 && ($academy < 5 || $mainbuilding < 2)) {
    include("soon/workshop.tpl");
    }
    if($tournamentsquare == 0 && $rallypoint < 7) {
    include("soon/tsquare.tpl");
    }
    if($bakery == 0 && $grainmill == 0) {
    include("soon/bakery.tpl");
    }
    if($townhall == 0 && ($mainbuilding < 5 || $academy < 5) && !$building->meetsLevelRequirements(24)) {
    include("soon/townhall.tpl");
    }
    if($tradeoffice == 0 && ($market < 10 || $stable < 5)) {
    include("soon/tradeoffice.tpl");
    }
    if($session->tribe == 1 && $horsedrinkingtrough == 0 && ($rallypoint < 5 || $stable < 10)) {
    include("soon/horsedrinking.tpl");
    }
    if($brewery == 0 && $village->capital == 1 && ($rallypoint < 5 || $granary < 10) && $session->tribe == 2) {
    include("soon/brewery.tpl");
    }
    if($greatbarracks == 0 && $barrack >= 10 && $barrack < 15 && $village->capital == 0) {
        include("soon/greatbarracks.tpl");
    }
    if($greatstable == 0 && $stable >= 10 && $stable < 15 && $village->capital == 0) {
        include("soon/greatstable.tpl");
    }
    if($greatworkshop == 0 && $workshop >= 10 && $workshop < 15 && $village->capital == 0 && GREAT_WKS) {
        include("soon/greatworkshop.tpl");
    }
    ?>
    <script language="JavaScript" type="text/javascript">
window.addEvent('domready', function()
{
	$each(
	{
		'all_link': 'all',
		'soon_link': 'soon'
	}, function(list, element)
	{
		if ($(element))
		{
			$(element).addEvent('click', function(e)
			{
				e.stop();
				// aktuelle liste, aktueller link
				var build_list = $('build_list_' + list);
				var link = $(list + '_link');

				var all_link = $('all_link');
				var soon_link = $('soon_link');

				var build_list_all = $('build_list_all');
				var build_list_soon = $('build_list_soon');

				Travian.toggleSwitch(build_list, link);
				if (!build_list.hasClass('hide'))
				{
					if (link == soon_link)
					{
						link.innerHTML = 'Ocultar construcciones disponibles próximamente';
						if (all_link !== null)
						{
							all_link.removeClass('hide');
						}
					}
					else
					{
						link.innerHTML = 'Menos';
					}
				}
				else
				{
					if (link == soon_link)
					{
						link.innerHTML = 'Ver construcciones disponibles próximamente';
						if (all_link !== null)
						{
							all_link.innerHTML = 'Más';
							all_link.addClass('hide');
							build_list_all.addClass('hide');
						}
					}
					else
					{
						link.innerHTML = 'Más';
					}
				}
			});
		}
	});
});
</script>
<?php 
}
?>
</div>
</div>
