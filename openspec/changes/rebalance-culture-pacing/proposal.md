## Why

La curva cultural x1 usada actualmente en este mundo x3 apunta a unos 15 días por nueva aldea, un ritmo que los jugadores perciben como excesivamente lento y que dificulta poblar un servidor con pocos participantes. La curva oficial x3, cercana a 5 días, sería demasiado rápida; se necesita un punto intermedio explícito de aproximadamente 10 días.

## What Changes

- Sustituir la selección binaria entre las curvas oficiales x1 y x3 por una curva intermedia documentada, derivada de dos tercios de los requisitos x1 y con redondeo estable.
- Usar esa curva como única fuente de requisitos para fundación, conquista y presentación del progreso cultural.
- Convertir los PC acumulados de cada jugador desde la curva x1 actual a la nueva curva, conservando sus cupos de aldea y su avance proporcional hacia el siguiente cupo.
- Mantener sin cambios la producción pasiva, las celebraciones, los cascos de cultura y las obras de arte; este cambio ajusta el ritmo mediante los requisitos, no mediante las fuentes de PC.
- Proporcionar una migración con vista previa predeterminada, aplicación explícita y ejecución repetible, adecuada tanto para el mundo existente como para instalaciones nuevas.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `culture-balance-migration`: Cambiar el valor predeterminado permanente de la curva lenta x1 a una curva intermedia de 10 días y exigir una conversión segura de los saldos existentes.
- `settler-expansion`: Calcular la capacidad cultural de fundación y conquista con la nueva curva intermedia autoritativa en lugar de la curva lenta x1.

## Impact

- Afecta `GameEngine/Data/cp.php`, `config/config.php`, el instalador que genera la configuración y `tools/rescale_culture_points.php` o su reemplazo compatible.
- Afecta todas las comprobaciones y vistas que ya consumen `travianCultureExpansionEligibility()` y las funciones autoritativas de requisitos culturales, sin introducir cálculos locales nuevos.
- Requiere actualizar `tools/check_culture_balance.php`, los chequeos de expansión/conquista relacionados y las especificaciones principales al archivar el cambio.
- La puesta en producción exige desplegar la configuración y aplicar la conversión de saldos como una misma operación para evitar una ventana temporal con curva y saldos incompatibles.
