<h1 class="titleInHeader">Empalizada <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid33">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(33,4);" class="build_logo">
        <img class="building big white g33" src="img/x.gif" alt="Empalizada" title="Empalizada"></a>
       La empalizada protege tu aldea de los ataques. Cuanto mayor sea el nivel, más fácil será para tus defensores repeler con éxito a las hordas saqueadoras de tus enemigos. </div>

<?php
$buildingHelpType = 'palisade';
$buildingHelpLevel = $village->resarray['f'.$id];
include('build_level_help.tpl');
?>

<table cellpadding="1" cellspacing="1" id="build_value">
        <tr>
            <th>
Bono de defensa actual:</th>
            <td><b><?php echo $bid33[$village->resarray['f'.$id]]['attri']; ?>%</b></td>
        </tr><tr>
        <?php 
        if(!$building->isMax($village->resarray['f'.$id.'t'],$id)) {
        ?>
            <th>Bono de defensa en el nivel (<?php echo $village->resarray['f'.$id]+1; ?>) </th>

            <td><b><?php echo $bid33[$village->resarray['f'.$id]+1]['attri']; ?>%</b></td>
            <?php
            }
            ?>
        </tr></table>
<?php 
include("upgrade.tpl");
?>
        </p>
         </div>
