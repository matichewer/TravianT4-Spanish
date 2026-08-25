<?php
/**
 * El encabezado del Tesoro, compartido por las tres pestañas.
 *
 * build.php incluye `27_2.tpl` y `27_3.tpl` directamente cuando llega `?t=`, así que
 * cada pestaña necesita su propio encabezado; antes cada una traía su copia y las tres
 * se habían ido desincronizando (una decía "24 horas" fijo, otra no cargaba la ayuda de
 * niveles). El texto del retardo sale de la constante del mundo, no de un literal.
 */
$treasuryLevel = isset($village->resarray['f'.$id]) ? (int)$village->resarray['f'.$id] : 0;
$treasuryDelayHours = round(artefactActivationDelay(SPEED) / 3600);
?>
<h1 class="titleInHeader">Tesoro <span class="level">Nivel <?php echo $treasuryLevel; ?></span></h1>

    <div id="build" class="gid27">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(27,4);" class="build_logo">
        <img class="building big white g27" src="img/x.gif" alt="Tesoro" title="Tesoro"></a>
        En el tesoro se guardan las riquezas de tu imperio. A partir del nivel 10, cada tesoro tiene espacio para un artefacto.
        Después de capturarlo, el artefacto tarda <?php echo $treasuryDelayHours; ?> horas en hacer efecto.</div>
        <?php
        $buildingHelpType = 'treasury';
        $buildingHelpLevel = $treasuryLevel;
        include('build_level_help.tpl');
        include("upgrade.tpl");
        include("27_menu.tpl");
        ?>
