<?php
/**
 * El Tesoro. El encabezado y las pestañas viven en 27_head.tpl porque build.php incluye
 * 27_2.tpl y 27_3.tpl directamente cuando llega `?t=`, sin pasar por acá.
 */
include("27_head.tpl");
if(isset($_GET['show'])) {
	include("27_show.tpl");
} else {
	include("27_1.tpl");
}
?>
    </div>
