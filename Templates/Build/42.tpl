<h1 class="titleInHeader">Műhely <span class="level">Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid42">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(42,4);" class="build_logo">
        <img class="building big white g42" src="img/x.gif" alt="Taller" title="Taller"></a>
        A műhelyben városromboló eszközöket lehet építeni, katapultot, faltörő kost. Magasabb szintű épület esetén az építési idő csökken. Az építhető harci egységek technológiáját ki kell fejleszteni előbb az akadémiában</div>

<?php if ($building->getTypeLevel(42) > 0) { ?>
<form method="POST" name="snd" action="build.php">
                <input type="hidden" name="id" value="<?php echo $id; ?>" />
                <input type="hidden" name="ft" value="t3" />
                <table cellpadding="1" cellspacing="1" class="build_details">
                <thead><tr>
                    <td>Nombre</td>
                    <td>Cantidad</td>
                    <td>Máximo</td>
                </tr></thead><tbody>
                <?php
                    include("42_train.tpl");
                ?></table>
    <p><input type="image" id="btn_train" class="dynamic_img" value="ok" name="s1" src="img/x.gif" alt="train" /></form></p>
    <?php
    } else {
        echo "<b>El entrenamiento puede comenzar cuando el gran taller esté terminado.</b><br>\n";
    }
    $trainlist = $technology->getTrainingList(7);
    if(count($trainlist) > 0) {
        echo "
    <table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\">
        <thead><tr>
			<td>En preparación</td>
			<td>Tiempo</td>
			<td>Finalización</td>
        </tr></thead>
        <tbody>";
        $TrainCount = 0;
        foreach($trainlist as $train) {
            $TrainCount++;
            echo "<tr><td class=\"desc\">";
            echo "<img class=\"unit u".$train['unit']."\" src=\"img/x.gif\" alt=\"".$train['name']."\" title=\"".$train['name']."\" />";
            echo $train['amt']." ".$train['name']."</td><td class=\"dur\">";
            if ($TrainCount == 1 ) {
                $NextFinished = $generator->getTimeFormat(($train['commence']+$train['eachtime'])-time());
                echo "<span id=timer1>".$generator->getTimeFormat(($train['commence']+($train['eachtime']*$train['amt']))-time())."</span>";
            } else {
                echo $generator->getTimeFormat($train['eachtime']*$train['amt']);
            }
            echo "</td><td class=\"fin\">";
            $time = $generator->procMTime($train['commence']+($train['eachtime']*$train['amt']));
			echo " ".$time[1]." horas";
        } ?>
		</tr><tr class="next"><td colspan="3">La siguiente unidad estará lista en <span id="timer2"><?php echo $NextFinished; ?></span> minutos</td></tr>
        </tbody></table>
    <?php }
include("upgrade.tpl");
?>
</p></div>
