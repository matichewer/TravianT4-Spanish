<?php
/**
 * Pestaña "Artefactos grandes": los de alcance cuenta —grandes y únicos—, que piden
 * Tesoro 20. Misma lista que la de los pequeños con otro filtro; ver 27_list.tpl.
 */
include("27_head.tpl");
$treasuryListSizes = array(ARTEFACT_SIZE_LARGE, ARTEFACT_SIZE_UNIQUE);
$treasuryListTitle = 'Artefactos grandes y únicos';
$treasuryListEmpty = 'Todavía no hay artefactos grandes ni únicos en el servidor';
include("27_list.tpl");
?>
</div>
