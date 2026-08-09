## Why

Las medallas semanales se asignan automáticamente, pero los jugadores sólo las descubren al revisar perfiles y clasificaciones. Una notificación inmediata del Multihunter hace visibles tanto los logros personales como los de la alianza.

## What Changes

- Enviar una notificación interna del Multihunter después de completar el reparto semanal de medallas.
- Enviar el anuncio a todos los jugadores reales del servidor, hayan ganado o no una medalla.
- Incluir en cada mensaje exactamente los ocho ganadores principales: un jugador y una alianza para Ataque, Defensa, Crecimiento y Saqueo.
- Identificar para cada resultado al ganador, la categoría, el puesto y el valor con el que fue obtenida, usando guiones simples como separadores.
- Enlazar los nombres de jugadores y las etiquetas de alianzas a sus perfiles reales.
- Evitar más de un anuncio por jugador durante una misma ejecución semanal.

## Capabilities

### New Capabilities

- `weekly-medal-notifications`: Anuncio interno global con los ganadores de las cuatro clasificaciones semanales de jugadores y alianzas.

### Modified Capabilities

Ninguna.

## Impact

- `GameEngine/Automation.php`: integración con el reparto semanal y composición de notificaciones.
- Tabla de mensajes `mdata`: nuevos mensajes internos generados por Multihunter.
- `tools/check_weekly_medal_notifications.php`: comprobador de regresión independiente.
