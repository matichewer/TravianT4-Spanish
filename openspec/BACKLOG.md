# Backlog

Trabajo identificado y todavía no propuesto como cambio. Cada entrada tiene lo suficiente
para retomarla en frío. Cuando una arranca, se convierte en un cambio bajo `changes/` y se
borra de acá.

Los cambios activos **no** viven en este archivo: `openspec list` es la fuente de verdad.

---







## Las Maravillas tienen residencia y en el T4 oficial no

**Qué pasa.** `install/include/multihunter.php` le pone a cada Aldea de la Maravilla una
residencia nivel 10 (`f28t = 25`). En el T4 oficial las Maravillas **no tienen ni
residencia ni palacio** —tampoco muralla, eso sí lo cumplimos—.

**Por qué importa.** `getConquestEligibility()` se niega a conquistar una aldea mientras le
quede residencia o palacio en pie. O sea que acá hay que derribarle la residencia con
catapultas antes de poder mandar los jefes, mientras que en el oficial se limpia la
guarnición y se chiefea directo. Nuestras Maravillas son bastante más difíciles de tomar
que las del juego original.

**Qué haría falta.** Sacar la residencia del instalador, y una migración para las 13 que ya
existen. Ojo que eso las vuelve notablemente más tomables de un día para el otro: conviene
avisarles a los jugadores antes de aplicarlo.

**Tamaño.** Chico el código, pero es un cambio de dificultad del endgame.

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
