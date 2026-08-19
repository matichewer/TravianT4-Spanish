## Why

La sección Alianzas ya aporta el contexto necesario, por lo que repetir “de alianza” en cada categoría recarga innecesariamente el anuncio semanal y lo hace inconsistente con la sección Jugadores.

## What Changes

- Mostrar `Ataque`, `Defensa`, `Crecimiento` y `Saqueo` como etiquetas de categoría en ambas secciones.
- Conservar sin cambios los ganadores, puntos, puestos y enlaces de alianzas.
- Actualizar la vista previa local y el comprobador de regresión.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `weekly-medal-notifications`: Unificar los nombres visibles de las categorías entre las secciones Jugadores y Alianzas.

## Impact

- `GameEngine/Automation.php`: etiquetas del resumen semanal.
- `tools/check_weekly_medal_notifications.php`: expectativa del texto de alianzas.
- Mensaje de demostración en la base local.
