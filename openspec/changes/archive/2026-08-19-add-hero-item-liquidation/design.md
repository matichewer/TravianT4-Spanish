## Context

La venta en subasta retira primero el objeto de `heroitems` y `Automation::auctionComplete()` lo devuelve al propietario cuando `uid` permanece en cero. El inventario representa como disponibles las filas `heroitems` con `proc = 0`, mientras que el saldo de plata vive en la cuenta del usuario. Véanse `proposal.md` y `specs/hero-item-disposal/spec.md` para el comportamiento requerido.

El código es PHP procedural sobre una capa MySQLi compartida, sin framework ni autoloader. `heroitems` y `users` usan MyISAM, por lo que no admiten transacciones ni bloqueos de fila reales; la operación necesita exclusión mutua y compensación explícita.

## Goals / Non-Goals

**Goals:**

- Centralizar la validación, el cálculo de recompensa y la mutación en una única operación de base de datos.
- Conservar las reglas existentes que distinguen objetos apilables y no apilables.
- Hacer que la interfaz muestre exactamente el resultado que el servidor volverá a calcular.
- Impedir que dividir un lote en muchas operaciones convierta el mínimo de 1 plata en una fuente de beneficios artificiales.

**Non-Goals:**

- Modificar la duración, el precio inicial o la adjudicación de subastas.
- Comprar automáticamente objetos que terminen sin pujas.
- Crear un historial recuperable, una papelera o una recompra posterior.
- Introducir monedas fraccionarias, nuevos objetos o una tabla de precios de mercado.

## Decisions

### Operación serializada y compensada en la capa de base de datos

Se añadirá una operación dedicada que adquiera un named lock de MariaDB compartido con la creación de subastas y las demás disposiciones, valide `uid`, `proc = 0`, tipo y cantidad, retire la fila completa o reduzca `num` y acredite plata cuando corresponda. Si el crédito falla, restaurará inmediatamente la fila o cantidad exacta antes de liberar el bloqueo. El controlador solamente interpretará un estado de resultado y no ejecutará SQL parcial.

Esto se prefiere a migrar dos tablas centrales del juego a InnoDB dentro de una mejora de interfaz. El bloqueo evita carreras entre solicitudes normales; la compensación cubre errores SQL observables, aunque MyISAM no puede garantizar rollback ante una caída del proceso ocurrida exactamente entre el retiro y el crédito.

### Precio derivado de la regla de subasta existente

La función de cálculo compartirá la misma definición conceptual del precio inicial vigente: para tipos apilables es la cantidad del lote y para objetos no apilables es 100 de plata. La recompensa será `floor(precioInicial * 0.10)`.

Los no apilables producen 10 de plata. Para apilables, el servidor rechazará cantidades menores que 10 porque producirían 0; así, toda liquidación aceptada paga al menos 1. No se aplicará `max(1, ...)` indiscriminadamente: permitirlo haría rentable dividir un lote en operaciones unitarias y transformaría una liquidación del 10 % en hasta un 100 % del precio inicial.

Esto se prefiere a una tabla nueva de valores por objeto porque mantiene una regla transparente y no requiere calibrar cada variante. Si la economía necesita valores diferenciados en el futuro, podrá reemplazarse la función sin cambiar el flujo transaccional.

### Acciones disponibles desde la zona de venta del inventario

Los controles se ubicarán junto a los objetos disponibles en la pestaña de venta de subastas, donde ya se eligen objetos y cantidades para vender. Cada acción usará POST con el token `mchecker`; no se realizarán mutaciones mediante enlaces GET.

Los objetos apilables mostrarán selector de cantidad. En liquidación, la interfaz indicará la cantidad mínima liquidable y actualizará la recompensa prevista; el servidor siempre recalculará ambos valores. Los no apilables ocultarán o fijarán la cantidad al lote completo. Una confirmación explícita precederá el envío definitivo.

Se prefiere esta ubicación a añadir controles sobre el héroe equipado, porque `proc = 0` define una frontera clara de disponibilidad y evita presentar acciones destructivas sobre equipamiento en uso.

### Resultados explícitos y registro económico

La operación devolverá estados distinguibles —éxito, token inválido, objeto no disponible, cantidad inválida, bloqueo ocupado y error de persistencia— para mostrar mensajes neutrales en español sin exponer SQL. Las liquidaciones y descartes exitosos escribirán usuario, objeto, cantidad, acción y plata en el log del servidor con una línea estructurada.

El registro se prefiere aunque no sea visible al jugador porque la liquidación crea moneda y debe poder auditarse ante anomalías.

## Risks / Trade-offs

- [La liquidación incrementa la plata total del mundo] → Mantener el pago en 10 %, exigir lotes apilables que produzcan al menos 1 y registrar cada operación.
- [La definición de tipos apilables puede volver a divergir entre subasta e inventario] → Extraer o reutilizar una única función de clasificación y cubrir todos los tipos actuales con el comprobador.
- [JavaScript manipulado puede mostrar un importe distinto] → Tratar el cálculo del navegador como informativo y recalcular siempre en el servidor.
- [Una confirmación basada solo en JavaScript puede omitirse] → Considerar la confirmación una protección de UX; las garantías de seguridad permanecen en token, propiedad, disponibilidad, bloqueo y transacción.
- [Acciones destructivas accidentales] → Usar etiquetas distintas, describir irreversibilidad y requerir confirmación con objeto y cantidad.
- [Una caída abrupta entre dos escrituras MyISAM no puede revertirse] → Mantener mínima la ventana, acreditar inmediatamente después del retiro, compensar errores detectables y registrar éxitos; una garantía estricta requeriría migrar las tablas a InnoDB fuera de este cambio.

## Migration Plan

1. Desplegar la operación serializada y la interfaz en la misma versión; no se requiere migración de esquema ni cambio de motor.
2. Verificar liquidación, descarte, concurrencia, token inválido y convivencia con subastas mediante un comprobador independiente y pruebas de página en Docker.
3. Supervisar logs y emisión de plata después del despliegue.
4. Para rollback, retirar los controles y la operación nueva; los objetos ya liquidados o descartados no son recuperables y la plata ya acreditada se conserva.
