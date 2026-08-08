## Context

El reparto ya se ejecuta desde `Automation::weeklyMedals()` bajo un bloqueo de archivo y sólo marca la semana como completada después de llamar a `giveOutMedals()`. Las medallas personales y de alianza se persisten antes de reiniciar los contadores semanales. Los mensajes internos se guardan mediante la capa de base de datos y el usuario con id 4 actúa como Multihunter en avisos automáticos existentes.

## Goals / Non-Goals

**Goals:**

- Derivar las notificaciones de las medallas efectivamente persistidas para la semana repartida.
- Componer una sola vez el resumen de los cuatro ganadores principales de jugadores y de alianzas.
- Distribuir el mismo resumen a todos los usuarios reales del servidor.
- Mantener el envío dentro del bloqueo semanal existente para evitar carreras entre solicitudes.
- Permitir comprobar la composición y selección de destinatarios sin depender de un lunes real.

**Non-Goals:**

- Notificar por correo electrónico o por canales externos.
- Cambiar las reglas, puestos, puntuaciones o imágenes de las medallas.

## Decisions

### Consultar las medallas recién persistidas

Después de insertar ambas clases de medallas, se consultarán por número de semana, puesto 1 y las cuatro categorías regulares: 1, 2, 10 y 4 para jugadores; 1, 2, 3 y 4 para alianzas. Las medallas especiales y puestos restantes se conservan en perfiles, pero no forman parte del anuncio.

### Componer un resumen global

Las consultas de medallas incorporarán los nombres de jugadores y los nombres y etiquetas de alianzas. Se generarán dos secciones de cuatro filas ordenadas Ataque, Defensa, Crecimiento y Saqueo. Cada fila incluirá un enlace real al perfil correspondiente, la categoría, el puesto y los puntos con `-` como separador. Componer una sola vez evita generar cuerpos distintos o repetir el formateo para cada destinatario.

### Difundir a todos los jugadores reales

Una consulta separada obtendrá todos los usuarios con id mayor a 3, coherente con el criterio usado por las clasificaciones para excluir cuentas del sistema. Se insertará el mismo mensaje una vez por destinatario. La alternativa de limitarlo a ganadores contradice el carácter de anuncio global solicitado.

### Usar Multihunter como remitente automático

Los mensajes se enviarán con owner 4, igual que otros avisos automáticos del juego. El asunto identificará la semana y el cuerpo usará el marcado de mensajes existente.

### Mantener la idempotencia en el límite semanal

El envío forma parte de `giveOutMedals()` y ocurre antes de que `weeklyMedals()` escriba su marcador de finalización. El bloqueo existente impide ejecuciones simultáneas. No se incorpora una tabla nueva de entregas: una falla parcial de base de datos conserva el comportamiento heredado del reparto, que tampoco es transaccional.

## Risks / Trade-offs

- [Una falla después de algunos envíos puede dejar una entrega parcial] → El comprobador valida la ruta normal; una idempotencia transaccional completa exigiría migrar tablas MyISAM o agregar un registro persistente fuera del alcance.
- [El anuncio global genera una inserción por cuenta cada semana] → El cuerpo se compone una sola vez y la frecuencia semanal mantiene la carga acotada.
- [Una categoría sin ganador produciría menos de cuatro filas] → El reparto regular crea un puesto 1 por categoría cuando existen jugadores o alianzas; el comprobador valida el contrato normal de cuatro y cuatro.

## Migration Plan

No requiere cambios de esquema. El despliegue de `Automation.php` activa la función en el siguiente reparto semanal. Para revertir, se elimina la llamada y los métodos auxiliares; los mensajes ya enviados permanecen como historial normal.
