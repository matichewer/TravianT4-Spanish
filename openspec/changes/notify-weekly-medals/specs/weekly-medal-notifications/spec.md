## Purpose

Informar a todos los jugadores del servidor sobre el resultado completo de las medallas semanales de jugadores y alianzas mediante un anuncio interno claro y sin duplicados.

## ADDED Requirements

### Requirement: Notificación posterior al reparto semanal
El sistema SHALL generar las notificaciones de medallas después de que las medallas personales y de alianzas de la semana hayan sido asignadas.

#### Scenario: Anuncio global
- **WHEN** finaliza el reparto semanal
- **THEN** cada jugador real del servidor recibe un mensaje interno del Multihunter que identifica la semana y contiene el mismo resumen completo de medallas

#### Scenario: Jugador sin medallas
- **WHEN** un jugador no obtuvo medallas personales y no pertenece a una alianza premiada
- **THEN** recibe igualmente el anuncio con todos los ganadores semanales

### Requirement: Un mensaje por destinatario y semana
El sistema SHALL generar como máximo un mensaje de anuncio de medallas por jugador durante cada ejecución semanal completada.

#### Scenario: Reparto con varios ganadores
- **WHEN** el reparto contiene varias medallas personales y de alianza
- **THEN** todas se agrupan en un único mensaje dirigido a cada jugador del servidor

### Requirement: Resumen de las cuatro clasificaciones
El anuncio SHALL contener una sección de Jugadores y otra de Alianzas. Cada sección SHALL enumerar exactamente un ganador para Ataque, Defensa, Crecimiento y Saqueo, tomando el puesto 1 de cada clasificación regular y excluyendo medallas especiales o de racha.

#### Scenario: Medalla personal de clasificación
- **WHEN** el anuncio incluye una medalla personal de clasificación
- **THEN** la sección Jugadores contiene exactamente cuatro entradas y cada una muestra el nombre y enlace del jugador, la categoría en español, su puesto y sus puntos separados por guiones simples

#### Scenario: Medalla de alianza de clasificación
- **WHEN** el anuncio incluye una medalla de alianza
- **THEN** la sección Alianzas contiene exactamente cuatro entradas y cada una muestra el nombre y la etiqueta enlazada de la alianza, la categoría en español, su puesto y sus puntos separados por guiones simples

#### Scenario: Medallas adicionales
- **WHEN** un jugador obtiene una medalla especial, de racha o una posición inferior al primer puesto
- **THEN** esa medalla permanece disponible en su perfil pero no agrega una entrada al anuncio global
