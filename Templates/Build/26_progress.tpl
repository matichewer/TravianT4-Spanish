 <?php
    $trainlist = $technology->getTrainingList(4);
    if(count($trainlist) > 0) {
    	echo "
    <br /><table cellpadding=\"1\" cellspacing=\"1\" class=\"under_progress\">
		<thead><tr>
			<td>Unidad</td>
			<td>Hora</td>
			<td>Finaliza</td>
		</tr></thead>
		<tbody>";
		$TrainCount = 0;
        foreach($trainlist as $train) {
			$TrainCount++;
	        echo "<tr><td class=\"desc\">";
			echo "<img class=\"unit u".$train['unit']."\" src=\"img/x.gif\" alt=\"".$train['name']."\" title=\"".$train['name']."\" />";
			echo $train['amt']." ".$train['name']."</td><td class=\"dur\">";
			if ($TrainCount == 1 ) {
				$NextFinished = $generator->getTimeFormat($train['timestamp2']-time());
				echo "<span id=timer1>".$generator->getTimeFormat($train['timestamp']-time())."</span>";
			} else {
				echo $generator->getTimeFormat($train['eachtime']*$train['amt']);
			}
			echo "</td><td class=\"fin\">";
			$time = $generator->procMTime($train['timestamp']);
			echo " ".$time[1];
		} ?>
		</tr><tr class="next"><td colspan="3">La próxima unidad estará lista en <span id="timer2"><?php echo $NextFinished; ?></span></td></tr>
		</tbody></table>
    <?php }
?>
