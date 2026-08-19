# hero-item-disposal Specification

## Purpose
Permitir que los jugadores retiren definitivamente objetos no deseados del inventario del héroe mediante una liquidación de bajo valor o un descarte sin recompensa, sin habilitar duplicaciones ni abuso económico.
## Requirements
### Requirement: Liquidación manual de objetos
El sistema SHALL permitir que el propietario liquide un objeto disponible del inventario del héroe y SHALL retirar definitivamente la cantidad liquidada a cambio de plata.

#### Scenario: Liquidar un objeto no apilable
- **WHEN** el jugador confirma la liquidación de un objeto no apilable disponible que le pertenece
- **THEN** el sistema retira el objeto completo y acredita el 10 % de su precio inicial de subasta, redondeado hacia abajo, con una recompensa de al menos 1 de plata

#### Scenario: Liquidar parte de un objeto apilable
- **WHEN** el jugador confirma una cantidad válida de un objeto apilable disponible que le pertenece y esa cantidad produce al menos 1 de plata
- **THEN** el sistema retira solamente la cantidad elegida y acredita el 10 % de su precio inicial de subasta, redondeado hacia abajo

#### Scenario: Cantidad apilable demasiado pequeña para liquidar
- **WHEN** el jugador intenta liquidar una cantidad apilable cuyo 10 % redondeado hacia abajo equivale a 0 de plata
- **THEN** el sistema rechaza la liquidación, no modifica el objeto ni la plata e informa la cantidad mínima necesaria para obtener 1 de plata

### Requirement: Descarte manual de objetos
El sistema SHALL permitir que el propietario descarte definitivamente objetos disponibles del inventario del héroe sin recibir plata.

#### Scenario: Descartar un objeto no apilable
- **WHEN** el jugador confirma el descarte de un objeto no apilable disponible que le pertenece
- **THEN** el sistema retira el objeto completo y no modifica su plata

#### Scenario: Descartar parte de un objeto apilable
- **WHEN** el jugador confirma una cantidad válida de un objeto apilable disponible que le pertenece
- **THEN** el sistema retira solamente la cantidad elegida y no modifica su plata

### Requirement: Confirmación informada
El sistema MUST solicitar confirmación antes de liquidar o descartar y SHALL mostrar el objeto y la cantidad que se retirarán; para una liquidación también SHALL mostrar la plata exacta que se acreditará.

#### Scenario: Cancelar la confirmación
- **WHEN** el jugador cancela una confirmación de liquidación o descarte
- **THEN** el sistema conserva el objeto y la plata sin cambios

### Requirement: Validación segura y mutación serializada
El sistema MUST validar en el servidor la sesión, el token de solicitud, la propiedad, el estado disponible y la cantidad del objeto. El sistema MUST serializar las mutaciones de liquidación, descarte y creación de subastas mediante un bloqueo compartido y MUST compensar inmediatamente el retiro del objeto si falla el crédito de plata.

#### Scenario: Objeto ajeno, equipado o no disponible
- **WHEN** una solicitud apunta a un objeto ajeno, equipado, en subasta o que ya no está disponible
- **THEN** el sistema rechaza la solicitud sin modificar ningún objeto ni saldo

#### Scenario: Cantidad inválida o desactualizada
- **WHEN** la cantidad solicitada es menor que uno o supera la cantidad disponible al procesarse
- **THEN** el sistema rechaza la solicitud sin retirar objetos ni acreditar plata

#### Scenario: Solicitud repetida
- **WHEN** se vuelve a enviar una solicitud ya completada y la cantidad original ya no está disponible
- **THEN** el sistema no concede una segunda recompensa y comunica que el objeto o la cantidad dejaron de estar disponibles

#### Scenario: Fallo al acreditar plata
- **WHEN** el crédito de plata falla después de iniciarse una liquidación
- **THEN** el sistema restaura inmediatamente la cantidad retirada, no concede una recompensa y comunica que la operación falló

#### Scenario: Operación concurrente
- **WHEN** dos solicitudes intentan retirar la misma cantidad disponible al mismo tiempo
- **THEN** el sistema procesa las solicitudes de forma serializada y como máximo una puede consumir esa cantidad y conceder su recompensa

### Requirement: Convivencia con las subastas
El sistema SHALL conservar el comportamiento vigente de las subastas sin pujas y SHALL ofrecer liquidación o descarte solamente cuando el objeto se encuentre nuevamente disponible en el inventario.

#### Scenario: Subasta finalizada sin pujas
- **WHEN** una subasta termina sin ofertas
- **THEN** el objeto vuelve al inventario del vendedor sin plata automática y puede posteriormente subastarse, liquidarse o descartarse

#### Scenario: Objeto en una subasta activa
- **WHEN** un objeto se encuentra comprometido en una subasta activa
- **THEN** el sistema no permite liquidarlo ni descartarlo

