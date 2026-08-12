## Why

Los objetos del héroe que no reciben pujas regresan al inventario y pueden quedar allí indefinidamente, obligando al jugador a repetir subastas sin demanda o conservar objetos que ya no desea. Hace falta una salida definitiva y deliberada que mantenga la subasta como la opción más rentable sin saturar el inventario.

## What Changes

- Añadir una acción manual para liquidar objetos del inventario del héroe a cambio de una cantidad reducida y garantizada de plata.
- Calcular la liquidación como el 10 % del precio inicial de subasta del lote, redondeado hacia abajo y con un mínimo de 1 de plata por operación.
- Añadir una acción manual para descartar objetos sin recibir plata.
- Permitir elegir la cantidad al liquidar o descartar objetos apilables; los objetos no apilables se procesan completos.
- Exigir una confirmación que muestre la acción, la cantidad afectada y, al liquidar, la plata que se recibirá.
- Mantener sin cambios el cierre de subastas sin pujas: el objeto vuelve al vendedor y luego puede liquidarse, descartarse o volver a subastarse.
- Serializar ambas acciones con un bloqueo global, validar en el servidor la propiedad, disponibilidad y cantidad, y compensar inmediatamente el retiro si falla el crédito de plata para impedir duplicaciones o recompensas repetidas dentro de las limitaciones de MyISAM.

## Capabilities

### New Capabilities

- `hero-item-disposal`: Liquidación y descarte manual y seguro de objetos disponibles en el inventario del héroe, incluida la selección de cantidades apilables y el cálculo de la recompensa.

### Modified Capabilities

Ninguna.

## Impact

- Interfaz del inventario y/o venta de objetos del héroe (`hero_inventory.php`, `hero_auction.php` y plantillas relacionadas).
- Operaciones de inventario y saldo de plata en `GameEngine/Database/db_MYSQLi.php`.
- Registro de liquidaciones y descartes en el log del servidor para facilitar auditorías económicas sin añadir tablas.
- Nuevo comprobador de regresión independiente en `tools/check_*.php`; no se requieren dependencias externas ni cambios incompatibles de esquema previstos.
