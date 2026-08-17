# Backlog

Trabajo identificado y todavía no propuesto como cambio. Cada entrada tiene lo suficiente
para retomarla en frío. Cuando una arranca, se convierte en un cambio bajo `changes/` y se
borra de acá.

Los cambios activos **no** viven en este archivo: `openspec list` es la fuente de verdad.

---

## Aldeas natar independientes

**Qué falta.** El T4 oficial tiene tres clases de aldea natar; este servidor implementa dos
(la capital y las 13 Aldeas de la Maravilla). Faltan las *independientes*: aparecen de a
poco durante la partida, funcionan como aldeas normales —producen, suben campos,
construyen y entrenan tropas de a poco en cuartel y establo—, se pueden saquear y
conquistar, no reciben refuerzos y desaparecen si las bajan a población 0 con catapultas.
En el oficial son las que sostienen el saqueo en el servidor tardío.

**La tensión de fondo.** El motor es *pull*: no hay cron ni tick, `Automation` corre en el
constructor en cada request. Una aldea que crece sola necesita que algo la empuje, y a las
4 de la mañana con el servidor vacío no hay nadie. Antes de escribir código hay que decidir
si crecen en tiempo real (hace falta un planificador, o colgarse del barrido existente) o
en tiempo lógico (se calcula su estado al vuelo la primera vez que alguien las mira, como
ya hace `lastupdate` con los recursos). Lo segundo es mucho más barato y encaja con el
motor que ya existe.

**Preguntas abiertas.** ¿Entrenan de verdad en `tdata` o se simula el resultado? ¿Qué
impide que una limpieza las convierta en un cajero automático permanente? ¿Se pueden
conquistar, con todo lo que eso arrastra de lealtad, senadores y cupos de expansión?

**Tamaño.** Grande. Es el que más cambia el juego.

### Fase 2: conquistarlas

En el oficial las aldeas natar independientes se pueden conquistar con senadores, no sólo
saquear y destruir. Queda explícitamente para después de la primera entrega: arrastra
lealtad, cupos de expansión y el camino de conquista entero, y saquear más destruir con
catapultas ya da la mayor parte del valor. **Matias lo quiere hecho en algún momento.**

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
