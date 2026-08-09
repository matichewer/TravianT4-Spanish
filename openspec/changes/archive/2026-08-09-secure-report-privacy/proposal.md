## Why

Los informes exponen datos sensibles de combate mediante rutas secundarias y reglas de compartición demasiado amplias. Además, un ataque o espionaje sin supervivientes puede revelar composición defensiva que el jugador no debería conocer.

## What Changes

- Centralizar la autorización de lectura de informes para el propietario y, únicamente en informes militares, miembros de la alianza asociada.
- Impedir que una cita de informe en un mensaje conceda acceso a un destinatario no autorizado.
- Aplicar la misma autorización a la vista directa, previsualización BBCode y repetición de ataques.
- Restringir archivado, desarchivado y borrado masivo al propietario del informe.
- Endurecer las consultas de informes contra identificadores y campos manipulados.
- Ocultar por completo los datos defensivos cuando no sobreviva ninguna tropa atacante o ningún espía.

## Capabilities

### New Capabilities

- `report-privacy`: Define quién puede leer, citar y operar sobre informes, y qué información defensiva se revela según el resultado.

### Modified Capabilities

Ninguna.

## Impact

Afecta la capa de mensajes e informes, consultas de base de datos, renderizado BBCode, repetición de ataques, generación de informes de batalla y plantillas de informes. No requiere dependencias nuevas ni cambios de esquema.
