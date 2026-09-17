<?php
// URL/form input is bounded before it controls the number of rendered tiles.
function mapZoomLevel($value) {
    return is_scalar($value) && in_array((string)$value, array('0', '1', '2', '3'), true)
        ? (int)$value : 0;
}

function mapZoomTileSize($level) {
    return array(60, 40, 30, 15)[mapZoomLevel($level)];
}
