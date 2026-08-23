<?php
/**
 * Regresión del acceso desde el mapa a la lista de granjas.
 *
 *   docker compose exec -T web php /var/www/html/tools/check_map_farm_list.php
 *
 * El flujo cruza cuatro plantillas y la capa de base de datos. Este checker fija
 * las uniones importantes: disponibilidad en el mapa, coordenadas precargadas,
 * continuación después de crear la primera lista y defensas de propiedad/duplicado.
 */

error_reporting(E_ALL);

$fails = array();
function mapFarmListAssert($condition, $message) {
	global $fails;
	if(!$condition) {
		$fails[] = $message;
		echo "  FAIL  ".$message."\n";
	}
}

$root = dirname(__DIR__);
$map = file_get_contents($root.'/Templates/Map/vilview.tpl');
$addList = file_get_contents($root.'/Templates/goldClub/farmlist_add.tpl');
$lists = file_get_contents($root.'/Templates/goldClub/farmlist.tpl');
$addSlot = file_get_contents($root.'/Templates/goldClub/farmlist_addraid.tpl');
$database = file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php');

foreach(array($map, $addList, $lists, $addSlot, $database) as $source) {
	mapFarmListAssert($source !== false, 'No se pudieron leer todos los archivos del flujo.');
}

// El mapa ofrece la acción sólo para objetivos ajenos y comunica los dos bloqueos.
mapFarmListAssert(strpos($map, '$farmListEligibleVillage') !== false, 'El mapa dejó de evaluar aldeas elegibles.');
mapFarmListAssert(strpos($map, '$farmListEligibleOasis') !== false, 'El mapa dejó de evaluar oasis elegibles.');
mapFarmListAssert(strpos($map, "(int)\$basearray['wref'] !== (int)\$_SESSION['wid']") !== false, 'La aldea actual volvió a ofrecerse como granja.');
mapFarmListAssert(strpos($map, "(int)\$basearray['owner'] !== (int)\$session->uid") !== false, 'Los oasis propios volvieron a ofrecerse como granja.');
mapFarmListAssert(strpos($map, 'Requiere Club de Oro') !== false, 'Falta el estado deshabilitado sin Club de Oro.');
mapFarmListAssert(strpos($map, 'Construye una Plaza de Reuniones') !== false, 'Falta el estado deshabilitado sin Plaza de Reuniones.');
mapFarmListAssert(substr_count($map, 'Agregar a lista de granjas') >= 3, 'La acción del mapa o sus estados deshabilitados desaparecieron.');

// Con listas va al slot; sin listas va a creación. Ambos caminos llevan X/Y.
mapFarmListAssert(strpos($map, 'getFirstOwnedFarmListId($session->uid)') !== false, 'El mapa dejó de resolver una lista propia.');
mapFarmListAssert(strpos($map, 'action=showSlot&amp;lid=') !== false, 'El mapa dejó de enlazar al formulario de objetivo.');
mapFarmListAssert(strpos($map, 'action=addList&amp;x=') !== false, 'El mapa dejó de enlazar a la creación cuando no hay listas.');
mapFarmListAssert(strpos($addSlot, "isset(\$_GET['x']) ? \$_GET['x']") !== false, 'El formulario dejó de precargar X desde el mapa.');
mapFarmListAssert(strpos($addSlot, "isset(\$_GET['y']) ? \$_GET['y']") !== false, 'El formulario dejó de precargar Y desde el mapa.');

// Crear la primera lista conserva el objetivo y usa el id realmente insertado.
mapFarmListAssert(strpos($addList, 'name="map_x"') !== false && strpos($addList, 'name="map_y"') !== false, 'La creación dejó de conservar las coordenadas.');
mapFarmListAssert(strpos($lists, '$newFarmListId = $database->createFarmList') !== false, 'La continuación dejó de usar la lista recién creada.');
mapFarmListAssert(strpos($lists, 'action=showSlot&lid='.(string)'') !== false, 'La creación dejó de redirigir al formulario del objetivo.');
mapFarmListAssert(preg_match('/function createFarmList\(.*?mysqli_insert_id\(\$this->connection\)/s', $database) === 1, 'createFarmList() dejó de devolver el id insertado.');

// Toda lectura/escritura relevante cruza el dueño, y el INSERT bloquea duplicados.
mapFarmListAssert(preg_match('/function getFirstOwnedFarmListId\(.*?WHERE owner = \$owner/s', $database) === 1, 'La búsqueda de listas dejó de filtrar por dueño.');
mapFarmListAssert(preg_match('/function farmListTargetExists\(.*?f\.owner = \$owner/s', $database) === 1, 'La comprobación de duplicados dejó de validar el dueño.');
mapFarmListAssert(preg_match('/function addSlotFarm\(.*?f\.owner = \$owner.*?NOT EXISTS.*?r\.towref = \$towref/s', $database) === 1, 'El alta dejó de bloquear atómicamente un objetivo duplicado en una lista propia.');
mapFarmListAssert(strpos($addSlot, 'farmListTargetExists((int)$_POST[\'lid\'], $session->uid, $Wref)') !== false, 'El formulario dejó de mostrar el caso de objetivo duplicado.');
mapFarmListAssert(strpos($addSlot, 'Este objetivo ya está agregado a la lista seleccionada.') !== false, 'Falta el mensaje de objetivo duplicado.');

if($fails) {
	fwrite(STDERR, "\nFallaron ".count($fails)." comprobaciones del acceso a listas desde el mapa.\n");
	exit(1);
}

echo "OK: el mapa agrega objetivos a listas propias sin perder coordenadas ni duplicarlos\n";
