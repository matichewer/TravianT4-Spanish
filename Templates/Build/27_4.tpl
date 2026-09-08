<?php
/**
 * Pestaña "Planos de construcción": el artefacto que habilita la Maravilla del Mundo.
 *
 * Va aparte de las otras dos porque el plano no es un tamaño. Se guarda como "pequeño"
 * —es el tamaño que pide Tesoro 10, que es justo lo que el oficial pide para un plano—
 * pero no tiene versión grande ni única, no ocupa uno de los tres huecos de artefacto
 * activo y su efecto no es de aldea ni de cuenta sino de alianza. Filtrar por tipo en vez
 * de por tamaño es lo que evita que aparezca mezclado entre los pequeños.
 */
include("27_head.tpl");
$treasuryListTypes = array(ARTEFACT_PLAN);
$treasuryListSizes = array(ARTEFACT_SIZE_SMALL, ARTEFACT_SIZE_LARGE, ARTEFACT_SIZE_UNIQUE);
$treasuryListTitle = 'Planos de construcción';
$treasuryListEmpty = 'Todavía no hay planos de construcción en el servidor';
include("27_list.tpl");
?>
<p class="none">Con <b>un</b> plano en tu alianza la Maravilla del Mundo llega al nivel
<?php echo (int)WONDER_PLAN_SOLO_MAX_LEVEL; ?>. Del
<?php echo (int)WONDER_PLAN_SOLO_MAX_LEVEL + 1; ?> al 100 hacen falta <b>dos</b>: uno tuyo y
otro de otro jugador de tu alianza. El plano pide un Tesoro de nivel
<?php echo (int)artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL, ARTEFACT_PLAN); ?> y
<b>no ocupa</b> ninguno de los <?php echo (int)ARTEFACT_MAX_ACTIVE; ?> huecos de artefacto
activo, así que tenerlo no te cuesta ningún efecto.</p>
</div>
