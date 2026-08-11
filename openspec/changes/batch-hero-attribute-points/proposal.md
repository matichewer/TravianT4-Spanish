## Why

Asignar puntos del héroe uno por uno provoca una recarga completa por cada clic y hace lenta una operación que normalmente requiere distribuir varios puntos a la vez. La distribución debe poder prepararse en la interfaz y confirmarse en una sola acción.

## What Changes

- Permitir acumular localmente varios puntos en los cuatro atributos del héroe sin recargar la página.
- Mostrar debajo de los atributos y en todo momento los puntos restantes, junto con los valores previstos de cada atributo.
- Incorporar acciones «Aplicar» y «Cancelar» siempre visibles para confirmar toda la distribución de forma atómica o descartarla; los botones se muestran grises cuando no hay cambios y verdes cuando existe una distribución pendiente.
- Mostrar el saldo de puntos en verde mientras quede al menos uno disponible y en gris cuando llegue a cero.
- Mostrar el tooltip informativo solamente al colocar el puntero sobre el nombre de cada atributo.
- Validar en el servidor el total disponible y el límite de cada atributo antes de guardar.

## Capabilities

### New Capabilities

### Modified Capabilities

- `hero-attributes`: La asignación de puntos pasa de incrementos individuales inmediatos a una distribución por lote confirmada por el jugador.

## Impact

- Página y plantilla de atributos del héroe (`hero_inventory.php`, `Templates/hero.tpl`).
- Capa de base de datos del héroe y comprobador de regresión de atributos.
- Estilos del paquete gráfico para los controles de confirmación.
