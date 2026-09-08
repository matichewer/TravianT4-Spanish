<?php 
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       renameVillage.tpl                                           ##
##  Developed by:  Dzoki & Advocatie                                           ##
##  License:       TravianX Project                                            ##
##  Thanks to:     Dzoki & itay2277(Edit troops)                               ## 
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
#################################################################################

if($_SESSION['access'] < ADMIN) die("Acceso denegado: esta pantalla es sólo para administradores.");


$id = $_GET['did'];

if(isset($id))
{
$village = $database->getVillage($id);  
$user = $database->getUserArray($village['owner'],1);  
$coor = $database->getCoor($village['wref']); 
$varray = $database->getProfileVillages($village['owner']); 
$type = $database->getVillageType($village['wref']);
$fdata = $database->getResourceLevel($village['wref']);
$units = $database->getUnit($village['wref']);
?>
<table id="member">
    <thead>
		<tr>
			<th colspan="2">Cambiar el nombre de la aldea</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><?php echo $village['name']; ?></td>
		</td>
	</tbody>
</table>
<?php
}
else
{
	// El `if(isset($id))` de arriba nunca se cerraba: el archivo terminaba en `</table>` y
	// la pantalla era un error de sintaxis, o sea una página en blanco. Se ve sólo si
	// alguien entra a `?p=renameVillage` sin `did`, que es justo el camino que faltaba.
	echo '<p>Falta el ID de la aldea. Se llega a esta pantalla desde la ficha de una aldea.</p>';
}
?>
