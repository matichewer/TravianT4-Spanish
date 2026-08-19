## MODIFIED Requirements

### Requirement: Resumen de las cuatro clasificaciones
El anuncio SHALL contener una sección de Jugadores y otra de Alianzas. Cada sección SHALL enumerar exactamente un ganador para Ataque, Defensa, Crecimiento y Saqueo, tomando el puesto 1 de cada clasificación regular y excluyendo medallas especiales o de racha. Los nombres visibles de las cuatro categorías SHALL ser idénticos en ambas secciones, sin agregar el sufijo “de alianza”.

#### Scenario: Medalla personal de clasificación
- **WHEN** el anuncio incluye una medalla personal de clasificación
- **THEN** la sección Jugadores contiene exactamente cuatro entradas y cada una muestra el nombre y enlace del jugador, una de las etiquetas Ataque, Defensa, Crecimiento o Saqueo, su puesto y sus puntos separados por guiones simples

#### Scenario: Medalla de alianza de clasificación
- **WHEN** el anuncio incluye una medalla de alianza
- **THEN** la sección Alianzas contiene exactamente cuatro entradas y cada una muestra el nombre y la etiqueta enlazada de la alianza, una de las etiquetas Ataque, Defensa, Crecimiento o Saqueo, su puesto y sus puntos separados por guiones simples

#### Scenario: Medallas adicionales
- **WHEN** un jugador obtiene una medalla especial, de racha o una posición inferior al primer puesto
- **THEN** esa medalla permanece disponible en su perfil pero no agrega una entrada al anuncio global

