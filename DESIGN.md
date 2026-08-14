---
name: Travian T4 en español
description: Clon de Travian T4 clásico — aldea pintada, carteles de madera y pergamino, con una capa propia discreta encima.
colors:
  moss-accent: "#99c01a"
  moss-accent-hover: "#00bc00"
  moss-accent-deep: "#568b0b"
  moss-accent-deep-hover: "#4f7500"
  link-active: "#ff8000"
  alarm-red: "#de0000"
  alarm-red-soft: "#c0392b"
  amber-warning: "#f88c1f"
  amber-warning-deep: "#f3a000"
  ink: "#252525"
  ink-parchment: "#3c2a14"
  timber-border: "#5d421a"
  timber-border-soft: "#69502c"
  weathered-oak: "#bba36e"
  parchment-cream: "#fff8df"
  parchment-cream-soft: "#fff3d5"
  surface-white: "#ffffff"
  surface-cool-white: "#f5fefd"
  stone-border: "#c8c8c8"
  stone-border-soft: "#d7d7d7"
typography:
  body:
    fontFamily: "Arial, Helvetica, Verdana, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: "16px"
  title:
    fontFamily: "Arial, Helvetica, Verdana, sans-serif"
    fontSize: "18px"
    fontWeight: 700
    lineHeight: "normal"
  label:
    fontFamily: "Verdana, Arial, Helvetica, sans-serif"
    fontSize: "11px"
    fontWeight: 400
    lineHeight: "normal"
  micro:
    fontFamily: "Verdana, Arial, Helvetica, sans-serif"
    fontSize: "10px"
    fontWeight: 700
    lineHeight: "normal"
rounded:
  xs: "2px"
  sm: "3px"
  md: "4px"
  lg: "5px"
  xl: "9px"
  pill: "50%"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "18px"
  xl: "24px"
components:
  adventure-count-badge:
    backgroundColor: "{colors.amber-warning-deep}"
    textColor: "{colors.ink-parchment}"
    typography: "{typography.micro}"
    rounded: "{rounded.xl}"
    padding: "0 2px"
  building-level-help-next:
    backgroundColor: "{colors.amber-warning-deep}"
    textColor: "{colors.surface-white}"
    typography: "{typography.micro}"
    rounded: "{rounded.sm}"
    padding: "1px 4px"
---

# Design System: Travian T4 en español

## Overview

**Creative North Star: "El Pergamino y la Madera"**

La interfaz no se presenta como una app: se presenta como objetos físicos apoyados sobre una aldea pintada. El fondo de cada pantalla es una ilustración de paisaje (`bgVillage-rtl.jpg` / `bgV.jpg`); encima de esa escena flotan carteles de madera tallada y rollos de pergamino resueltos como arte en sprite (`#villageName`, `#villageList`, el marco del héroe), no como tarjetas de UI genéricas. Ese arte del gpack (`Travian 4.0_41`) es la autoridad visual del proyecto y es intocable: ninguna decisión de esta capa lo reemplaza ni lo reinterpreta.

Encima de ese mundo heredado, el proyecto agrega una capa propia y deliberadamente discreta (`img/travian_basics.css`): badges de estado, un panel de progreso de cultura que se construye como un cartel de pergamino de tres piezas, tooltips, y utilidades para páginas nuevas (troop stats, building stats, ayuda contextual de nivel de edificio). Esta capa nunca compite con el arte pintado — toma prestada su paleta (verde musgo, madera, pergamino) y su vocabulario de profundidad es suave y funcional, nunca una tarjeta "elevada" al estilo de un dashboard moderno.

**Key Characteristics:**
- Ancho fijo de 990px, centrado sobre un fondo pintado que ocupa todo el viewport.
- Los componentes de estado del juego (carteles, marcos) son arte en sprite, no CSS; los componentes que agrega el proyecto sí son CSS real, pero minimalista.
- Paleta heredada del Travian clásico: verde musgo como acento primario, madera y pergamino como superficie secundaria, rojo/ámbar solo para alarma y advertencia.
- Cero border-radius ni sombra en el juego original; la capa propia introduce ambos con moderación (2–9px, sombras suaves en tonos madera/verde).

## Colors

Paleta heredada de la aldea pintada de Travian (verdes musgo, maderas, pergamino), extendida con un puñado de tonos funcionales para la capa propia.

### Primary
- **Verde Musgo** (`#99c01a`): acento de enlaces y estados interactivos en toda la UI heredada del gpack; es *el* color de identidad de Travian.
- **Verde Musgo Hover** (`#00bc00`): estado hover/focus sobre el acento primario (enlaces, `.a:hover`).

### Secondary
- **Musgo Profundo** (`#568b0b` / hover `#4f7500`): variante más oscura y saturada, usada en la capa propia para texto de encabezado interactivo (`summary` de ayuda de edificio, título de tooltip) y para valores positivos destacados (aldeas listas para asentar, ingreso diario de cultura). Se distingue del acento primario porque marca *confirmación*, no solo interactividad.
- **Naranja Activo** (`#ff8000`): estado `:active` de enlaces en todo el juego heredado.

### Tertiary
- **Rojo de Alarma** (`#de0000` / suave `#c0392b`): errores, depósito lleno, valores negativos de recursos. Reservado exclusivamente para estados que requieren atención inmediata.
- **Ámbar de Advertencia** (`#f88c1f` / profundo `#f3a000`): estados intermedios de aviso — recurso drenándose, insignia de "próximo nivel", nota de advertencia en la ayuda de edificios. Un escalón por debajo del rojo de alarma.

### Neutral
- **Tinta** (`#252525`): color de texto base en todo el juego.
- **Tinta sobre Pergamino** (`#3c2a14`): texto sobre superficies de pergamino/madera de la capa propia (tooltips, panel de cultura, intro de Plus).
- **Blanco Pergamino** (`#ffffff`): fondo de tarjetas de contenido y componentes propios sobre fondo claro.
- **Blanco Frío** (`#f5fefd`): fondo del tablero de recursos (`#res`), elegido para fundirse con el blanco ya pintado en el arte de fondo en ese punto exacto de la pantalla.
- **Borde Piedra** (`#c8c8c8` / suave `#d7d7d7`): bordes neutros de cajas de ayuda y separadores en la capa propia.

### Named Rules
**La Regla del Pergamino Intacto.** Ningún contenedor de la capa propia que se apoye en arte de cartel (`#cultureProgress`, `#villageName`, `#villageList`) lleva `background` o `box-shadow` propios: el rollo de pergamino ya es transparente fuera de su silueta, y cualquier fondo del contenedor se ve como una banda pálida por encima y por debajo del cartel.

## Typography

**Body Font:** Arial, Helvetica, Verdana, sans-serif
**Label/Mono Font:** Verdana, Arial, Helvetica, sans-serif (mismo stack, orden invertido — se usa en badges y etiquetas pequeñas)

**Character:** Una única familia sans-serif de sistema en todo el juego, sin webfonts ni pares tipográficos: la tipografía no es protagonista, es infraestructura legible sobre un fondo ilustrado.

### Hierarchy
- **Title** (bold, 18px): `<h1>` de cada página de contenido, dentro de la tarjeta de pergamino (`.contentTitle`).
- **Body** (normal, 13px, line-height 16px): texto de página por defecto.
- **Label** (normal, 11px): controles de formulario, texto secundario en paneles (footer de tooltips, notas de ayuda).
- **Micro** (bold, 10px): badges de conteo (aventuras del héroe, "próximo nivel"), siempre centrado y sobre una insignia de color sólido, nunca como texto de párrafo.

### Named Rules
**La Regla de la Familia Única.** No se introducen webfonts ni familias secundarias; toda jerarquía se logra con tamaño y peso dentro del mismo stack de sistema.

## Layout

El juego vive en un wrapper de **990px** centrado sobre un fondo pintado a todo el ancho del viewport (`background-image` en `body`, `min-width:990px`). Dentro de ese wrapper, dos modelos de layout conviven:

- **Vista de aldea** (`dorf1`/`dorf2`): capas absolutamente posicionadas sobre la ilustración de fondo — el tablero de recursos (`#res`, fijo en `top:80px;left:193px;width:596px`), la barra lateral de carteles (`#side_info`, `float:left;width:196px`), y los carteles de madera (`#villageName`, `#cultureProgress`) anclados con coordenadas exactas calculadas para no pisar el arte pintado ni los badges de recursos.
- **Vista de contenido** (informes, mercado, mensajes, etc.): una tarjeta de "pergamino" de tres piezas (`.contentTitle` / `.contentContainer` / `.contentFooter`, cada una un recorte de PNG) con `padding:23px` y `min-height:400px`.

Los carteles de madera cuyo ancho es independiente del arte (`#villageList`, `#villageName`, `#cultureProgress`) usan `background-size:100% <alto>` para que el PNG se estire al ancho real del contenido, con `width:max-content` acotado por `min-width`/`max-width` dentro de un ±25% del ancho nativo del arte — pasar ese rango deforma visiblemente los clavos y bordes tallados.

Un breakpoint en **1119px** (990 + 2×overhang + 20px de margen para el scrollbar) reancla los carteles que asoman fuera del wrapper cuando la ventana es más angosta que ese umbral; es el caso típico de un teléfono sin `<meta name="viewport">`.

### Named Rules
**La Regla de los 990px.** El wrapper del juego nunca es fluido de verdad: es un lienzo de ancho fijo con un breakpoint puntual (1119px) para el overhang de los carteles, no un sistema responsive general.

## Elevation & Depth

Sistema híbrido. El juego original (arte del gpack) resuelve toda su profundidad *dentro* del sprite — biseles, texturas de madera, sombreado de pergamino — sin una sola sombra CSS; `border-radius` y `box-shadow` no existen en `compact1.css`. La capa propia (`travian_basics.css`) sí usa sombras CSS reales, pero deliberadamente discretas y funcionales, nunca una elevación de "tarjeta moderna": según lo confirmado, esta capa nunca debe competir visualmente con el arte pintado.

### Shadow Vocabulary
- **Badge duro** (`box-shadow: 0 1px 2px rgba(0,0,0,0.7)`): contorno de contraste fuerte para badges pequeños de conteo sobre fondo ilustrado (aventuras del héroe).
- **Realce interior sutil** (`box-shadow: inset 0 1px 0 rgba(255,255,255,0.35)` / `0.55`): brillo superior de 1px sobre superficies con gradiente (level badges, botón de troop stats), simulando una arista pulida sin dibujar una sombra completa.
- **Panel flotante** (`box-shadow: 0 3px 9px rgba(43,29,11,0.45)`): único uso de sombra "elevada" del proyecto, reservado al tooltip del panel de cultura — un tono cálido (marrón), nunca gris/negro neutro.
- **Surco grabado** (`box-shadow: inset 0 1px 2px rgba(73,48,14,0.42)` sobre fondo `#c8ad72`): barra de progreso de cultura, se lee como una ranura tallada en madera, no como un input plano.
- **Sombra de tarjeta blanca** (`box-shadow: 0 2px 3px -2px rgba(45,70,60,0.5)`): el tablero de recursos, la única superficie blanca "flotante" sobre el fondo pintado — sombra corta y verde-grisácea para no romper la ilusión de que el blanco viene pintado en el fondo.

### Named Rules
**La Regla del Tono Cálido.** Ninguna sombra de la capa propia usa negro puro; siempre lleva un matiz marrón (`rgba(43,29,11,…)`) o verde-musgo (`rgba(45,70,60,…)`) para mantenerse dentro de la paleta de la aldea pintada.

## Shapes

El juego original no tiene lenguaje de forma en CSS: toda silueta (rollo de pergamino, marco de héroe, botón tallado) viene recortada en el PNG. La capa propia introduce un vocabulario de radios pequeño y utilitario, nunca decorativo: **2–5px** para cajas de contenido (ayuda de edificio, intro de Plus, tooltips, fichas de horario removibles), **8–9px** para insignias tipo etiqueta (tag de característica de Plus, badge circular de aventura), y círculo completo (`50%`) para badges de nivel/conteo y botones de quitar que se apoyan sobre una superficie blanca.

## Components

### Badges de estado
- **Badge de aventura del héroe** (`.adventures .adventureCount`): círculo de 16px, borde marrón oscuro, fondo ámbar sólido, texto micro en negrita; variante "no disponible" cambia a grises (`#aaa`/`#666`/`#333`) sin tocar la forma.
- **Level badge** (`.levelBadge`): círculo blanco sobre un fondo de gradiente que codifica estado — dorado por defecto, verde si se puede mejorar, azul si llegó al nivel máximo, naranja si está en construcción. El color es el único portador de significado; la forma no cambia.

### Panel de cultura (`#cultureProgress`)
Cartel de pergamino de tres piezas construido igual que `#villageList` (`.cultureProgressSignTop/Body/Foot`), contenedor sin fondo propio. Internamente: encabezado con título y tooltip on-hover, barra de progreso "grabada" (surco marrón con relleno en gradiente verde), y una línea de tasa diaria en verde musgo profundo cuando corresponde. Reposiciona su `top` según qué otros carteles estén presentes (protección de novato, quest master) y se angosta a 152px bajo el breakpoint de 1119px.

### Tooltips (`.cultureProgressTooltip`)
Caja de pergamino cálido (`#fff8df`) con borde marrón (`#5d421a`), esquina de 5px, flecha triangular apuntando al elemento ancla, transición de opacidad/transform de 120ms. Único componente del proyecto con sombra "flotante" completa.

### Cajas de ayuda / info (`details.buildingLevelHelp`, `.plusProductsIntro`)
Bloques colapsables o estáticos con borde neutro o cálido, encabezado en musgo profundo, divisores punteados en madera clara (`#bba36e`), y una insignia "próximo nivel" en ámbar. El patrón se repite en la intro de beneficios Plus con tags tipo píldora.

### Tablero de recursos (`#res`)
Tarjeta blanca fría, pixel-perfect contra el arte de fondo (posición y ancho exactos calculados para fundirse con el blanco ya pintado). Cada recurso es una columna con separador "grabado" (doble línea clara/oscura), una barra de progreso con tres estados de color (normal verde, drenando ámbar, lleno rojo) y un reloj centrado sobre la barra.

### Tablas de estadísticas (troop/building stats)
Zebra striping plano (`#fafafa` / `#f1f1f1`), sin bordes decorativos; navegación de índice como píldoras grises que se iluminan en verde musgo claro al hover.

## Do's and Don'ts

### Do:
- **Do** mantener toda superficie apoyada en arte de cartel (`#villageList`, `#villageName`, `#cultureProgress`) sin `background`/`box-shadow` propio en el contenedor — el pergamino ya resuelve ese fondo.
- **Do** usar tonos cálidos (marrón/verde) en cualquier sombra nueva de la capa propia, nunca negro neutro puro.
- **Do** versionar la URL (`?v=N`) de cualquier asset estático (CSS, JS, imágenes referenciadas desde CSS) que cambie, porque Cloudflare lo cachea 4 horas en producción.
- **Do** mantener los clamps de ancho de los carteles width-independent dentro de ±25% del ancho nativo del arte.

### Don't:
- **Don't** ensanchar `#side_info` en `compact1.css` — es un float de 196px dentro del wrapper de 990px; ensancharlo rompe el layout de toda la vista de aldea.
- **Don't** reemplazar ni reestilizar el arte del gpack (`gpack/travian_Travian_4.0_41`) — es un compromiso de marca fijo, no un punto de partida.
- **Don't** introducir sombras "de tarjeta moderna" (grandes, neutras, muy elevadas) en componentes propios — la única sombra flotante autorizada es la del tooltip del panel de cultura.
- **Don't** agregar webfonts o una segunda familia tipográfica — todo el juego usa un único stack de sistema.
