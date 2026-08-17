# Backlog

Trabajo identificado y todavía no propuesto como cambio. Cada entrada tiene lo suficiente
para retomarla en frío. Cuando una arranca, se convierte en un cambio bajo `changes/` y se
borra de acá.

Los cambios activos **no** viven en este archivo: `openspec list` es la fuente de verdad.

---







## `canClaimArtifact()` no comprueba nada

**Qué pasa.** `GameEngine/Database/db_MYSQLi.php:6160` lee `$AttackerFields` en el primer
bucle **antes** de asignarla —queda indefinida, así que `$defcanclaim` termina siempre en
`TRUE`— y después consulta `getResourceLevel($vref)` una segunda vez sobre la *misma*
aldea. O sea que nunca mira la tesorería del atacante, que es el requisito real del T4.
Funciona por accidente contra las Maravillas, porque su tesorería nivel 10 satisface la
condición equivocada.

**Cuándo importa.** Sólo si se usan artefactos. Hoy no hay ninguno colocado: aparecen
únicamente si se agregan desde el panel de administración.

**Tamaño.** Chico, pero conviene arreglarlo *antes* de colocar el primer artefacto.
