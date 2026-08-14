<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       Protection.php                                              ##
##  Developed by:  SlimShady                                                   ##
##  Edited by:     Dzoki & Dixie                                               ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

// array_map() no baja a los valores anidados: un campo de formulario tipo array
// (name="x[]") volvia string vacio en silencio en vez de sanearse, porque
// mysql_real_escape_string()/htmlspecialchars() reciben un array y devuelven null.
// Ningun formulario del juego habia necesitado un campo array hasta las rutas
// comerciales con horarios multiples; de ahi que este bug quedara latente.
function sanitizeInputRecursive($value, $suppressErrors) {
	if(is_array($value)) {
		$result = array();
		foreach($value as $key => $item) {
			$result[$key] = sanitizeInputRecursive($item, $suppressErrors);
		}
		return $result;
	}
	$escaped = $suppressErrors ? @mysql_real_escape_string($value) : mysql_real_escape_string($value);
	return htmlspecialchars($escaped);
}

//heef npc uitzondering omdat die met speciaal $_post werken
if(isset($_POST)){
	if(!isset($_POST['ft'])){
	$_POST = sanitizeInputRecursive($_POST, true);
	}
}
$_GET = sanitizeInputRecursive($_GET, false);
$_COOKIE = sanitizeInputRecursive($_COOKIE, false);
?>