## Context

La autorización de informes está repartida entre `Message`, consultas directas de plantillas, BBCode y `a2b.php`. La vista principal admite informes por alianza o por una referencia recibida en un mensaje, mientras que algunas rutas consultan `ndata` sin comprobar al jugador. La generación de batalla guarda un payload alternativo cuando mueren todos los atacantes, pero conserva banderas que revelan las tribus defensoras.

## Goals / Non-Goals

**Goals:**

- Tener una única decisión de autorización reutilizable por todas las rutas.
- Mantener la visibilidad de alianza para eventos militares, incluida exploración y refuerzos atacados.
- Mantener privados los informes de refuerzo llegado, aventura y comercio.
- Evitar que mensajes, parámetros manipulados o acciones masivas amplíen permisos.
- No guardar ni renderizar inteligencia defensiva en informes de atacante sin supervivientes.

**Non-Goals:**

- Cambiar el contenido que recibe el defensor en su propio informe.
- Rediseñar la interfaz de informes o el historial militar de alianza.
- Crear ACL históricas por miembro de alianza.

## Decisions

- La capa MySQLi expondrá `getAuthorizedNotice(uid, alliance, reportId)`. El propietario siempre obtiene su informe; los miembros de la alianza solo obtienen tipos militares `0-7` y `15-21`. Centralizar aquí evita que cada consumidor reconstruya reglas distintas.
- Una cita BBCode solo se convierte en enlace si el lector actual ya está autorizado. Se elimina la autorización derivada de mensajes: el contenido de un mensaje no es una capacidad de acceso.
- `a2b.php` cargará una única fila autorizada y sus plantillas reutilizarán ese payload. Los identificadores se convertirán a enteros y los campos seleccionables se limitarán a una lista fija.
- Las operaciones masivas recibirán también el `uid` de sesión y actualizarán únicamente filas del propietario.
- El payload de derrota total llevará un marcador explícito y ceros en todas las banderas defensivas. Las plantillas de derrota total y espionaje fallido mostrarán un aviso neutro en lugar de tablas defensoras. También se reconocerán informes antiguos por su tipo/forma para no seguir filtrando datos históricos.

## Risks / Trade-offs

- [Un miembro que entra luego a una alianza puede abrir informes militares históricos cuyo `ally` coincide] → Se conserva el comportamiento actual del historial de alianza; resolver membresía histórica requeriría un modelo ACL y migración fuera de alcance.
- [Código legado podría llamar consultas antiguas] → Se mantienen métodos compatibles, pero endurecidos, y se migran todas las rutas encontradas a la carga autorizada.
- [Payloads históricos no contienen el marcador nuevo] → Las plantillas usan además el tipo de informe y el indicador de fallo legado.

## Migration Plan

Los cambios son de PHP y entran en vigor al desplegar, sin migración de base de datos. El rollback consiste en revertir el cambio de código.

## Open Questions

Ninguna.
