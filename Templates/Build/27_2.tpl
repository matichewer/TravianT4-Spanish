<?php
/**
 * Pestaña "Artefactos pequeños": los de alcance aldea, que piden Tesoro 10.
 *
 * Es la misma lista que la pestaña de los grandes con otro filtro, así que las dos
 * incluyen `27_list.tpl` en vez de repetir el bloque una vez por tipo de artefacto. La
 * versión anterior lo repetía nueve veces por pestaña, se le había perdido el tipo 1 y
 * listaba el 9 dos veces.
 */
include("27_head.tpl");
$treasuryListSizes = array(ARTEFACT_SIZE_SMALL);
$treasuryListTitle = 'Artefactos pequeños';
$treasuryListEmpty = 'Todavía no hay artefactos pequeños en el servidor';
include("27_list.tpl");
?>
</div>
