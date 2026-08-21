<?php
/**
 * Verifica que cambiar de aldea desde una pestaña con gid= no pierda esa pestaña.
 *
 * El selector de aldeas (Templates/multivillage.tpl) arma su link con &gid=N en vez de
 * &id=N cuando la pagina actual es build.php y el slot activo es una de las
 * construcciones especiales (19-40, ej. Mercado): el slot cambia de aldea en aldea, asi
 * que hay que pedir el tipo de edificio (gid) y dejar que build.php resuelva el slot
 * correcto alla. Pero la pestaña (t=, ej. Rutas comerciales del Mercado) es la misma
 * edificio a edificio, y esa rama no la llevaba puesta: cambiar de aldea desde Rutas
 * comerciales volvia siempre a la pestaña por defecto del Mercado.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_village_switch_tab.php
 */

$fails = 0;

function check($condition, $message) {
	global $fails;
	if($condition) {
		echo "OK: ".$message."\n";
	} else {
		echo "FAIL: ".$message."\n";
		$fails++;
	}
}

$multivillageSource = file_get_contents(dirname(__DIR__).'/Templates/multivillage.tpl');
$buildSource = file_get_contents(dirname(__DIR__).'/build.php');

check(preg_match('/if\(\$buildingGid > 0\)\{\s*\$vill = "&gid="\.\$buildingGid;\s*[^}]*if\(isset\(\$_GET\[\'t\'\]\)/s', $multivillageSource) === 1,
	'la rama de &gid= tambien preserva la pestaña (t=) activa');

check(preg_match('/isset\(\$_GET\[\'t\'\]\).*?\$vill \.= "&t="\.\$_GET\[\'t\'\];/s', $multivillageSource) === 1,
	'la pestaña se agrega al link de cambio de aldea cuando esta presente');

check(strpos($buildSource, '$newdidLocation .= (strpos($newdidLocation') !== false
	&& strpos($buildSource, "\$newdidLocation .= (strpos(\$newdidLocation,'?') !== false ? '&' : '?').'t='.(int)\$_GET['t'];") !== false,
	'build.php reenvia t= al redirigir tras newdid, ademas de id/gid');

echo "\n";
if($fails) {
	echo "$fails comprobacion(es) fallaron\n";
	exit(1);
}
echo "todo ok\n";
exit(0);
