## Context

El formateador recibe un indicador de medalla de alianza y actualmente mantiene dos mapas de etiquetas, aunque las cuatro categorías regulares son semánticamente iguales y ya están contextualizadas por el encabezado de sección.

## Goals / Non-Goals

**Goals:**

- Compartir las mismas cuatro etiquetas visibles entre jugadores y alianzas.
- Conservar el enlace de alianza que envuelve su nombre y etiqueta.

**Non-Goals:**

- Cambiar categorías, ganadores, puntuaciones, puestos o destinatarios.

## Decisions

Se usará un único mapa de categorías para ambos tipos de medalla. El parámetro que diferencia jugadores de alianzas seguirá utilizándose exclusivamente para construir el enlace del ganador. Esto evita que las etiquetas vuelvan a divergir.

## Risks / Trade-offs

- [El texto pierde contexto si se lee aislado] → Los resultados siempre aparecen bajo los encabezados Jugadores o Alianzas y el enlace identifica el tipo de ganador.

