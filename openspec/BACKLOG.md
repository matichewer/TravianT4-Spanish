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

---

## La capital natar crece sola

**Qué pasa.** Las oleadas contra la Maravilla salen de la nada: `startNatarAttack()` llama
a `addAttack()`, que sólo inserta una fila y nunca descuenta de `units`. Pero al volver,
`returnunitsComplete()` acredita los sobrevivientes con `modifyUnit(..., 1)` sobre la aldea
de origen. Resultado: cada Maravilla que alguien construye engorda la guarnición de la
capital, para siempre.

**La pregunta de diseño detrás.** La capital cumple hoy dos roles que no tienen relación:
es un depósito defensivo de 3,4 M de tropas *y* es el remitente nominal de unas oleadas
cuya composición está hardcodeada por nivel de Maravilla. Si las oleadas son escenario, el
arreglo correcto probablemente no sea "descontarlas al salir" sino "que no vuelvan".

**Tamaño.** Una tarde.

---

## Nadie valida que una tasa de producción sea sensata

**Qué pasa.** La acreditación de recursos tiene techo hacia arriba
(`LEAST(maxstore, ...)`) pero no tiene piso hacia abajo: el cereal puede irse a menos
infinito. Para jugadores está bien resuelto —`starvation()` mata en función de la *tasa* y
después pone `crop = 0`, así que descarta la deuda y nadie recibe un castigo retroactivo
por haber estado ausente—. El bug natar fue letal no por el reloj perezoso sino porque la
*tasa* era absurda (-45.000/h en una Maravilla, -5.200.000/h en la capital) y nada lo
notó.

**Qué faltaría.** Algo que detecte una tasa fuera de rango y avise, en vez de aplicarla en
silencio. Puede ser tan chico como un checker que barra el mundo y falle si alguna aldea
tiene un balance imposible de sostener.

**Tamaño.** Chico, pero hay que definir qué es "imposible".

---

## `addWW.php` y `natarend.php` crean Maravillas de la Naturaleza

**Qué pasa.** `GameEngine/Admin/Mods/addWW.php:41` y `GameEngine/Admin/Mods/natarend.php:39`
insertan la aldea con `owner = 3`, que en las instalaciones actuales es **Nature**, no
Natars (uid 2). Si se agregan Maravillas desde el panel de administración salen como
aldeas de la naturaleza: tribu equivocada en los informes, y quedan fuera de todo lo que
busca aldeas natar por cuenta.

Los dos archivos siguen además escribiendo la aldea con SQL a mano en vez de pasar por
`natarRestockGarrison()` + `natarProvisionVillage()`, así que nacen sin la economía que
`GameEngine/NatarVillage.php` les arma.

**Tamaño.** Chico.

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
