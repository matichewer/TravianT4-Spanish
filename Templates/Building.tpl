<div class="boxes buildingList">
<div class="boxes-tl"></div>
<div class="boxes-tr"></div>
<div class="boxes-tc"></div>
<div class="boxes-ml"></div>
<div class="boxes-mr"></div>
<div class="boxes-mc"></div>
<div class="boxes-bl"></div>
<div class="boxes-br"></div>
<div class="boxes-bc"></div>
<div class="boxes-contents">
			<table cellpadding="1" cellspacing="1" id="building_contract">
				<thead>
					<tr>
						<th colspan="4"><?php echo BUILDING_UPGRADING;?>
			<?php if($database->getUserField($session->uid, 'gold', 0) >= 2 && $building->canFinishAll()) { ?>
		<div class="finishNow">
		  <a class="arrow" href="?cmd=buildingFinish&amp;c=<?php echo $session->checker; ?>" onclick="return confirm('¿Completar todo al instante por 2 de oro?')">Finalizar todo</a>
		  <b> (precio: <img src="img/x.gif" class="gold" alt="Oro"> 2)</b>
		</div>
			<?php
            }
            ?></th>
		</tr></thead>
		<tbody>
        <?php 
		if(!isset($timer)) {
        $timer = 1;
        }
        foreach($building->buildArray as $jobs) {
		if($jobs['master'] == 0){
        	echo "<tr><td class=\"ico\"><a href=\"?d=".$jobs['id']."&a=0&c=$session->checker\">";
            echo "<img src=\"img/x.gif\" class=\"del\" title=\"cancel\" alt=\"cancel\" /></a></td><td>";
			echo $building->procResType($jobs['type'])." <span class=\"lvl\"> Level ".$jobs['level']."</span>";
			if($jobs['loopcon'] == 0) { $BuildFirst = $jobs['field']; }
            if($jobs['loopcon'] == 1) {
            	echo " (waiting loop)";
            }
            echo "</td><td colspan=\"2\" class=\"buildingTime\"><span id=\"timer".$timer."\">";
            echo $generator->getTimeFormat($jobs['timestamp']-time());
            echo "</span> hrs. ";
            echo "Time ".date('H:i', $jobs['timestamp'])."</td></tr>";
            $timer +=1;
      	}else{
        	echo "<tr><td class=\"ico\"><a href=\"?d=".$jobs['id']."&a=0&c=$session->checker\">";
            echo "<img src=\"img/x.gif\" class=\"del\" title=\"cancel\" alt=\"cancel\" /></a></td><td>";
			echo "<span class=\"none\">".$building->procResType($jobs['type'])."</span> <span class=\"lvl\"> Level ".$jobs['level']."</span>";
		}
		}
        ?>
            </tbody>
	</table>
        </div> 
				</div>

	<script type="text/javascript">var bld=[{"stufe":1,"gid":"1","aid":"3"}]</script>
