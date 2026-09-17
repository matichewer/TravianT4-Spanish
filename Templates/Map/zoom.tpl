<div class="mapZoomControls" role="group" aria-label="Zoom del mapa">
<?php foreach (array(1 => array('−', 'Alejar'), -1 => array('+', 'Acercar')) as $step => $control) {
    $nextZoom = $mapZoom + $step;
    if ($nextZoom >= 0 && $nextZoom <= 3) { ?>
    <a href="<?php echo $mapRoute; ?>?x=<?php echo (int)$x; ?>&amp;y=<?php echo (int)$y; ?>&amp;zoom=<?php echo $nextZoom; ?>" aria-label="<?php echo $control[1]; ?>" title="<?php echo $control[1]; ?>"><?php echo $control[0]; ?></a>
<?php } else { ?>
    <span aria-disabled="true" aria-label="<?php echo $control[1]; ?>"><?php echo $control[0]; ?></span>
<?php } } ?>
    <span class="mapZoomLabel">Zoom <?php echo array('100%', '67%', '50%', '25%')[$mapZoom]; ?></span>
</div>
<style>
.mapZoomControls{display:flex;gap:6px;align-items:center;margin:8px 0;}
.mapZoomControls a,.mapZoomControls span:not(.mapZoomLabel){display:inline-block;box-sizing:border-box;width:30px;height:28px;line-height:26px;text-align:center;border:1px solid #687c43;border-radius:4px;background:#f5f4df;color:#344723;font-size:21px;text-decoration:none;}
.mapZoomControls a:hover{background:#dce7ba;}
.mapZoomControls span[aria-disabled]{opacity:.4;}
.mapZoomLabel{font-size:12px;}
#mapContainer.lowRes .ruler.x{width:<?php echo $VIEW_WIDTH; ?>px;overflow:hidden;}
#mapContainer.lowRes .ruler.x .rulerContainer{position:relative;left:-<?php echo $OFFSET_X; ?>px;width:<?php echo $VCOLS*$TILE; ?>px;background-size:<?php echo $TILE; ?>px 30px;}
#mapContainer.lowRes .ruler.x .coordinate{box-sizing:border-box;width:<?php echo $TILE; ?>px;}
#mapContainer.lowRes .ruler.y{overflow:hidden;}
#mapContainer.lowRes .ruler.y .rulerContainer{position:relative;top:-<?php echo $OFFSET_Y; ?>px;background-size:30px <?php echo $TILE; ?>px;}
#mapContainer.lowRes .ruler.y .coordinate.zoom1{box-sizing:border-box;height:<?php echo $TILE; ?>px;padding-top:0;line-height:<?php echo $TILE; ?>px;}
</style>
