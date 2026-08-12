## 1. Reglas compartidas de objetos y precio

- [x] 1.1 Centralizar la clasificación de objetos apilables usada por subastas, liquidación y descarte, conservando los tipos actualmente admitidos.
- [x] 1.2 Implementar el cálculo del precio inicial y de la recompensa del 10 %, incluidos los 10 de plata para no apilables y el rechazo de lotes apilables menores que 10.

## 2. Mutación segura de inventario

- [x] 2.1 Añadir una operación de base de datos que adquiera un named lock compartido y valide un objeto disponible por propietario, estado y cantidad.
- [x] 2.2 Implementar bajo ese bloqueo el retiro total o parcial y, para liquidación, el crédito inmediato de plata con restauración compensatoria ante fallos detectables.
- [x] 2.3 Devolver estados de resultado diferenciados y registrar liquidaciones y descartes exitosos con usuario, objeto, cantidad y recompensa en el log del servidor.

## 3. Flujo HTTP e interfaz

- [x] 3.1 Procesar liquidación y descarte exclusivamente mediante POST autenticado y token `mchecker`, traduciendo cada resultado a un mensaje neutral en español.
- [x] 3.2 Añadir controles de liquidar y descartar a los objetos disponibles de la zona de venta, con selección parcial solo para objetos apilables.
- [x] 3.3 Mostrar la cantidad mínima liquidable, calcular la recompensa prevista en la interfaz y solicitar una confirmación irreversible con objeto, cantidad y plata exactos.
- [x] 3.4 Mantener ocultas o inhabilitadas ambas acciones para objetos equipados, comprometidos en subastas o no disponibles.
- [x] 3.5 Si se modifica CSS o JavaScript estático, actualizar sus versiones de caché —y las de imágenes CSS afectadas— conforme a las reglas de despliegue.

## 4. Verificación

- [x] 4.1 Crear un comprobador `tools/check_*.php` que cubra precios, liquidación total y parcial, descarte, propiedad, estado, cantidades inválidas y repetición de solicitudes.
- [x] 4.2 Añadir una prueba de concurrencia o simulación de exclusión mutua que demuestre que dos solicitudes no pueden retirar ni recompensar dos veces la misma cantidad.
- [x] 4.3 Ejecutar validación de sintaxis sobre todos los PHP modificados y ejecutar todos los comprobadores `tools/check_*.php` dentro del contenedor web.
- [x] 4.4 Verificar en el navegador las confirmaciones, mensajes, saldo e inventario para objetos apilables y no apilables, incluida la devolución de una subasta sin pujas.
