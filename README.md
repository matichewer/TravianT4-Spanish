# Travian T4 para PHP 7

Servidor privado y clon no oficial de **Travian T4**, el juego de estrategia multijugador para navegador. El proyecto está basado en TravianX/ZravianX y contiene cambios propios en la interfaz, la jugabilidad, la estabilidad y la traducción al español; no es una copia exacta del juego oficial.

## Versión

- Juego: Travian T4.
- Paquete gráfico: `Travian 4.0_41`.
- Base del proyecto: TravianX/ZravianX.
- Versión indicada por el instalador: `BETA 4`.
- Runtime incluido: PHP `7.4` con Apache y MariaDB `10.6`.

El repositorio no publica por el momento una versión semántica ni releases etiquetados.

> [!WARNING]
> Es una aplicación legacy. PHP 7.4 ya no recibe soporte de seguridad y el código no debe considerarse listo para producción sin una revisión de seguridad. No se recomienda exponer esta instalación directamente a Internet.

## Requisitos

Para desplegarlo con la configuración incluida se necesita:

- Git.
- Docker Engine con el plugin Docker Compose v2, o Docker Desktop.
- Los puertos `8080` y `3308` libres en el host.
- Espacio y memoria suficientes para construir la imagen y generar el mapa. Los mapas grandes tardan más en crearse.

No es necesario instalar PHP, Apache ni MariaDB en el host: Docker los proporciona.

El contenedor web ejecuta Apache con el UID `1000` para poder escribir sobre el código montado. En Linux, si el usuario propietario del repositorio tiene otro UID, puede ser necesario adaptar las instrucciones `usermod` y `groupmod` de `docker/Dockerfile` o corregir los permisos de `config/` e `install/installconfig/`.

## Instalación rápida

### 1. Descargar el proyecto

```bash
git clone https://github.com/matichewer/TravianT4-Spanish.git
cd TravianT4-Spanish
```

Si el repositorio ya está descargado, solo hay que entrar en su directorio.

### 2. Crear el archivo de entorno

```bash
cp .env.example .env
```

Editar `.env` y reemplazar ambos valores de ejemplo:

```dotenv
MARIADB_PASSWORD=una-clave-segura
MARIADB_ROOT_PASSWORD=otra-clave-segura
```

`MARIADB_PASSWORD` será la contraseña que se utilizará luego en el instalador web. Por compatibilidad con el instalador legacy, conviene usar contraseñas largas compuestas por letras, números, guiones y guiones bajos, sin comillas.

El archivo `.env` contiene secretos, está ignorado por Git y no debe subirse al repositorio.

### 3. Construir e iniciar los contenedores

```bash
docker compose up --build --detach
```

Comprobar que los servicios `web` y `db` estén funcionando y que la base figure como `healthy`:

```bash
docker compose ps
```

La aplicación quedará disponible en:

- Juego: <http://localhost:8080/>
- Instalador: <http://localhost:8080/install/>
- MariaDB desde el host: `127.0.0.1:3308`

La aplicación se conecta a MariaDB por la red interna de Docker; para ello utiliza el host `db` y el puerto interno `3306`.

## Crear el servidor por primera vez

Abrir <http://localhost:8080/install/> y completar el asistente en este orden.

### 1. Configuración del juego

Elegir el nombre del servidor y revisar velocidad del juego, velocidad de tropas, tamaño del mundo, idioma, protección de principiantes, duración de Plus, quests y las demás opciones.

Para una primera prueba se recomienda conservar los valores predeterminados y usar un mundo `100x100`. En **Home Page** usar la URL desde la cual se abrirá el juego, por ejemplo `http://localhost:8080/` en local o la URL pública con HTTPS en producción.

En **Database Connection Settings** hay que utilizar exactamente:

| Campo | Valor |
| --- | --- |
| Hostname | `db` |
| Username | `travian` |
| Password | El valor de `MARIADB_PASSWORD` en `.env` |
| DB name | `travian_t4` |
| Prefix | `s1_` |
| Type | `MYSQLi` |

Los valores `localhost` y `travian` que aparecen inicialmente en algunos campos del instalador no corresponden al despliegue con Docker.

Completar también **Admin Name** y **Admin Email**. El usuario técnico con acceso al panel se crea más adelante con el nombre `Multihunter`.

### 2. Crear la estructura SQL

Pulsar **Create** en la pantalla **Create SQL Structure**. Esto crea todas las tablas necesarias en `travian_t4`.

### 3. Generar el mundo

Pulsar **Create** en **Create World Data** y esperar sin recargar ni cerrar la página. La generación puede tardar, especialmente con un mapa grande.

### 4. Crear el Multihunter

Elegir una contraseña segura para el usuario `Multihunter` y guardarla en un gestor de contraseñas. Esta cuenta tendrá acceso administrativo al servidor.

### 5. Poblar los oasis

Pulsar **Create** en **Populate Oasis** y esperar a que finalice.

### 6. Finalizar

Al terminar, el instalador genera:

- `config/connection.php`, con la conexión a MariaDB.
- `config/config.php`, con la configuración del servidor.
- `config/installed`, que impide volver a ejecutar el asistente accidentalmente.

Después de la instalación se puede acceder a:

- Página principal: <http://localhost:8080/>
- Panel de administración: <http://localhost:8080/Admin/>

Para entrar al panel usar el usuario `Multihunter` y la contraseña elegida durante el asistente.

## Comandos habituales

Iniciar o reconstruir el servidor:

```bash
docker compose up --build --detach
```

Ver el estado:

```bash
docker compose ps
```

Ver los logs:

```bash
docker compose logs --follow
```

Detener los contenedores sin borrar la base de datos:

```bash
docker compose down
```

Volver a iniciarlos:

```bash
docker compose up --detach
```

El volumen Docker `dbdata` conserva la base de datos aunque se detengan o se vuelvan a crear los contenedores.

## Actualizar el servidor

Antes de actualizar, hacer una copia de seguridad de la base de datos, de `.env` y de los archivos generados dentro de `config/`.

```bash
git pull
docker compose up --build --detach
docker compose ps
```

También existe `deploy.sh`, que ejecuta `git pull` y luego reconstruye el stack en segundo plano:

```bash
./deploy.sh
```

Revisar siempre los cambios antes de actualizar: `config/config.php` puede contener configuración generada localmente y una actualización podría entrar en conflicto con ella.

## Reiniciar todo desde cero

> [!CAUTION]
> Los siguientes comandos eliminan definitivamente todos los jugadores, aldeas, configuraciones y datos guardados en MariaDB. Hacer una copia de seguridad antes de continuar.

```bash
docker compose down --volumes
rm -f config/connection.php config/installed
rm -rf install/installconfig
docker compose up --build --detach
```

Después, volver a abrir <http://localhost:8080/install/> y repetir el asistente. No es necesario borrar `.env`; conservarlo permite reutilizar las mismas contraseñas.

Si solo se cambia una contraseña en `.env` después de crear `dbdata`, MariaDB no la aplicará automáticamente. Las variables de inicialización se procesan únicamente al crear el volumen por primera vez. Para conservar los datos hay que cambiar la contraseña dentro de MariaDB; para una instalación vacía se puede reiniciar el volumen con el procedimiento anterior.

## Despliegue público

Para publicar el juego en un dominio hacen falta, como mínimo, estas medidas adicionales:

1. Usar contraseñas únicas y seguras en `.env` y para `Multihunter`.
2. Colocar un reverse proxy, como Nginx o Caddy, delante del puerto `8080` y habilitar HTTPS.
3. Configurar firewall. MariaDB ya se publica únicamente en `127.0.0.1:3308` y no debe abrirse a Internet.
4. Bloquear completamente el acceso HTTP a `/install/` después de terminar. Ese directorio contiene utilidades de instalación y desinstalación que no deben quedar públicas.
5. Mantener copias de seguridad periódicas del volumen `dbdata`, `.env` y `config/`.
6. Revisar permisos, logs, correo saliente si se habilita la activación por email y la configuración de zona horaria.
7. Auditar y modernizar el código antes de aceptar tráfico no confiable, especialmente por el uso de PHP 7.4 y componentes legacy.

El `docker-compose.yml` incluido es apropiado para desarrollo y pruebas. No configura dominio, certificados TLS, correo, backups ni monitoreo.

## Solución de problemas

### El instalador muestra `Already installed`

Existe `config/installed`. Si se quiere conservar el servidor, no hay que borrarlo. Si se desea eliminar todo y comenzar otra vez, seguir la sección **Reiniciar todo desde cero**.

### Error de conexión a la base de datos

Comprobar que:

- `docker compose ps` muestre `db` como `healthy`.
- El host configurado sea `db`, no `localhost`.
- La base sea `travian_t4`.
- El usuario sea `travian`.
- La contraseña coincida con `MARIADB_PASSWORD` del `.env` usado cuando se creó el volumen.

Consultar los logs con:

```bash
docker compose logs --tail=100 web db
```

### El instalador no puede escribir archivos

El proceso web necesita escribir en `config/` e `install/installconfig/`. Comprobar los permisos del repositorio y la observación sobre UID `1000` de la sección **Requisitos**.

### Un puerto ya está ocupado

Cambiar el lado izquierdo del mapeo correspondiente en `docker-compose.yml`. Por ejemplo, `8081:80` publicará el juego en <http://localhost:8081/>. Si se cambia la URL, actualizar también **Home Page** en la configuración del servidor.

## Nota legal

Este es un proyecto comunitario, no oficial y no afiliado con Travian Games. Quien lo despliegue es responsable de revisar las licencias, marcas, recursos gráficos y requisitos legales aplicables a su uso.
