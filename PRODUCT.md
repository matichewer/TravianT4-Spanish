# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Un grupo cerrado de amigos/conocidos que ya se conocen entre sí, jugando por nostalgia de Travian clásico. No es un servidor público que capta jugadores desconocidos por su cuenta; el crecimiento, si lo hay, pasa por invitación dentro de ese círculo.

## Product Purpose

Servidor privado de **Travian T4** en español (clon no oficial, basado en TravianX/ZravianX) para que ese grupo de amigos pueda jugar el juego de estrategia por navegador tal como lo recuerdan, con interfaz y textos en español. Es un proyecto de pasión/nostalgia, sin fines de lucro: el éxito se mide en diversión y vida de comunidad dentro del grupo, no en ingresos ni en cantidad de jugadores nuevos.

## Positioning

Réplica jugable del Travian T4 clásico (paquete gráfico `Travian 4.0_41`) con traducción completa al español neutro/latam y ajustes propios de interfaz/jugabilidad/estabilidad sobre la base legacy de TravianX/ZravianX — algo que un servidor genérico o el juego oficial actual (que ya no es T4) no ofrece.

## Operating Context

- Se juega en el navegador, en `travian.chewer.net` (producción, Raspberry Pi 4B) o en `localhost:8080` (desarrollo con Docker).
- Ciclo de vida típico de Travian: un "mundo"/servidor se crea con el instalador (`/install/`), corre una ronda, y eventualmente se reinicia desde cero (`reset_server.sh`) para arrancar otra ronda con el mismo grupo.
- Hay un panel de administración (`/Admin/`) operado por el usuario técnico `Multihunter`, usado por el operador (Matias) para gestionar el servidor durante la ronda.
- Páginas de juego típicas: vista de aldea (`dorf1.php`, `dorf2.php`, `dorf3.php`), construcción (`build.php`), héroe (`hero*.php`), mapa (`karte.php`), informes (`berichte.php`), mensajes (`nachrichten.php`), alianzas (`allianz.php`), perfil (`spieler.php`), rankings (`statistiken.php`), reglas (`rules.php`).

## Capabilities and Constraints

- Base de código legacy: PHP 7.4 procedural con una capa fina de clases, sin framework ni autoloader, SQL concatenado a mano, singletons globales (`$database`, `$session`, `$generator`, `$logging`).
- Sin suite de tests ni linter; validación vía scripts `tools/check_*.php` y prueba manual en el navegador.
- Layout de la barra lateral (`#side_info`) y de los carteles de madera (`#villageList`, `#villageName`, `#cultureProgress`) es frágil por diseño: ancho acoplado al arte gráfico del gpack, con clamps y anclajes específicos documentados en AGENTS.md.
- Producción de recursos, radio de anexión de oasis, y otras mecánicas centrales tienen una única implementación canónica que no debe duplicarse (ver AGENTS.md).
- Cloudflare cachea assets estáticos en producción por 4 horas; cambios de CSS/JS/imágenes requieren versión en la URL para no quedar servidos en stale.
- Zona horaria fija en `America/Argentina/Buenos_Aires`.

## Brand Commitments

- Nombre del proyecto: **"Travian T4 en español"**, servido en el dominio **travian.chewer.net**. Ambos son parte fija de la identidad y no deben cambiar.
- Se conserva el paquete gráfico original de Travian (`gpack` 4.0_41: pergaminos, iconos, arte de aldea, etc.) tal como está; el arte del juego no se reemplaza ni se rediseña.
- Traducción al español activa (`Lang/es.php`) en español neutro/latam, no peninsular. Las páginas orientadas al jugador ya están traducidas; el panel de Admin es el área principal que queda en inglés.

## Evidence on Hand

- `README.md` (instrucciones de despliegue e instalación, en español) y `AGENTS.md` (guía técnica para agentes de IA) documentan arquitectura, convenciones y despliegue con detalle.
- No hay landing page de marketing, testimonios, casos de estudio ni métricas de negocio — no corresponde inventarlos: este no es un producto que se vende, es un servidor para un grupo cerrado.

## Product Principles

1. **El grupo cerrado es la audiencia real.** Ninguna decisión de producto debe optimizar por captar jugadores anónimos o nuevos; prioriza la experiencia de gente que ya se conoce y ya conoce Travian.
2. **Fidelidad al Travian T4 clásico.** El valor central es la nostalgia; los cambios de jugabilidad/interfaz son ajustes puntuales sobre esa base, no una reinvención del juego.
3. **El arte del gpack es intocable.** Cualquier trabajo visual respeta el paquete gráfico original como restricción dura, no como punto de partida a reemplazar.
4. **Sin fines de lucro, sin métricas de crecimiento.** No se diseñan flujos de conversión, upsell agresivo de Gold/Plus, ni onboarding para desconocidos: es un proyecto de pasión.
5. **Estabilidad legacy por encima de modernización cosmética.** Sobre una base PHP 7.4 sin tests, cualquier cambio de UI debe ser conservador con el layout frágil existente (sidebar, carteles) y verificarse manualmente en el navegador.
