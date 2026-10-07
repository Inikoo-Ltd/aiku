---
title: ¿Qué tan rápida es mi página?
summary: El panel PageSpeed Insights de la pestaña Performance de una página web puntúa la página en vivo sobre 100 en escritorio y móvil, muestra lo que vivieron los visitantes reales en el último mes, y guarda un historial para que veas si un cambio hizo la página más rápida.
date: 2026-09-16
source_date: 2026-09-16
tags: website, performance, seo, shop
category: marketing
---

<aside class="tldr">
Abre una página web en aiku, pulsa <b>Performance</b> y baja hasta <b>PageSpeed Insights</b>. Cuatro esferas puntúan la página en vivo sobre 100 para <b>Performance</b> (rendimiento), <b>Accessibility</b> (accesibilidad), <b>Best practices</b> (buenas prácticas) y <b>SEO</b>, por separado para escritorio y móvil. Verde es 90-100, ámbar 50-89, rojo por debajo de 50. Debajo, <b>Core Web Vitals</b> es lo que vivieron realmente los visitantes en los últimos 28 días, <b>Lab metrics</b> (métricas de laboratorio) es de dónde salió la puntuación, y <b>Score history</b> (historial de puntuación) dibuja las puntuaciones en el periodo elegido arriba en la pestaña Performance. La medición es de Google, no nuestra, y siempre mide la página publicada, nunca tu borrador sin guardar.
</aside>

## Qué se mide

Google mide la página en sus propios servidores, lo que significa que consulta la **dirección pública y publicada** de la página. De ahí se derivan dos cosas:

- Una página que no está publicada, o que aún no tiene dirección pública, no se puede medir. El panel lo dice en lugar de mostrar puntuaciones.
- Lo que estás viendo es la página tal y como la recibe un visitante hoy, no el borrador que estás editando en el taller. Publica primero, mide después.

Cada página se mide dos veces, una como teléfono y otra como ordenador de escritorio, y los dos resultados se guardan por separado. Usa los botones <b>Desktop</b> / <b>Mobile</b> arriba del panel para cambiar entre ellos. Las puntuaciones de móvil casi siempre son más bajas que las de escritorio: Google simula un teléfono de gama media con una conexión lenta, y esa es la prueba más dura y honesta, porque la mayoría de los compradores llegan desde el móvil.

## Las cuatro puntuaciones

Cada esfera es una nota sobre 100:

- **Performance** (rendimiento) — con qué rapidez carga la página y se hace usable.
- **Accessibility** (accesibilidad) — qué tan bien funciona la página para alguien que usa un lector de pantalla u otra tecnología de asistencia.
- **Best practices** (buenas prácticas) — comprobaciones de seguridad y de web moderna, como HTTPS y errores en el navegador.
- **SEO** — las comprobaciones básicas que permiten a los buscadores encontrar, rastrear y entender la página.

Los colores son las propias franjas de Google: **0-49 deficiente**, **50-89 mejorable**, **90-100 correcto**. Al pasar el ratón o enfocar una esfera aparece una tarjeta que explica esa puntuación en una línea, pone las cifras de escritorio y móvil una junto a otra para que veas cuál dispositivo va peor, y repite las franjas.

Trata el número como una dirección, no como un objetivo. La misma página medida dos veces con una hora de diferencia puede moverse unos puntos, porque la red simulada de Google no es idéntica en cada pasada. Una bajada de 88 a 84 es ruido; una bajada de 88 a 40 es algo que cambiaste.

## Core Web Vitals: tus visitantes reales

El bloque <b>Core Web Vitals</b> solo aparece cuando Google tiene suficiente tráfico real a esta página. No es una simulación: es lo que vivió la gente que realmente la visitó en los <b>últimos 28 días</b>, recogido desde Chrome.

Se muestran cinco medidas, cada una con una barra que divide tus visitas entre buenas, mejorables y deficientes:

- **Largest Contentful Paint** — cuánto tarda en aparecer en pantalla lo principal de la página, normalmente la imagen grande o el titular.
- **Interaction to Next Paint** — cuánto tarda la página en responder a un toque o un clic.
- **Cumulative Layout Shift** — cuánto salta la página mientras carga. Es la que los compradores describen como "se movió y pulsé donde no era".
- **First Contentful Paint** — cuánto tarda en aparecer algo, lo que sea.
- **Time to First Byte** — cuánto tardó nuestro servidor en empezar a responder.

Las barras importan más que la cifra destacada. Una página puede tener una media aceptable mientras una quinta parte de las visitas son deficientes, y esa quinta parte suele ser un país, un teléfono o una imagen lenta.

Una página con poco tráfico — una familia nueva, una página de contenido poco visitada — no tendrá este bloque en absoluto. No es un fallo; Google simplemente no informa sobre un puñado de visitas.

## Lab metrics: de dónde salió la puntuación

Las <b>Lab metrics</b> (métricas de laboratorio) son los tiempos de la única pasada simulada de Google, la que produjo la esfera de Performance: First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift y Speed Index. Son el desglose detrás de la nota.

Úsalas para saber *qué tipo* de lentitud tiene una página. Un mal Largest Contentful Paint suele significar una imagen de cabecera pesada. Un mal Total Blocking Time significa scripts. Un mal Cumulative Layout Shift suele significar una imagen o un banner sin espacio reservado. Si las cifras de laboratorio se ven bien y las barras de visitantes reales se ven mal, el problema no es la página en sí sino quién la visita y desde dónde.

## Score history

Al final, <b>Score history</b> dibuja una puntuación a la vez en el periodo fijado por las fechas <b>From</b> y <b>To</b> arriba en la pestaña Performance. Elige cuál con el desplegable. La línea continua es escritorio, la discontinua es móvil.

Los puntos son diarios cuando el rango es de hasta unos tres meses, y una media semanal más allá de eso, para que una mirada larga hacia atrás siga siendo legible. Solo existe un punto para un día en que la página fue realmente medida, así que hay que esperar huecos en vez de una línea continua.

Esta es la parte que hay que mirar después de un rediseño, después de añadir un vídeo, o después de cambiar las imágenes de una página de departamento. Pon el periodo alrededor del cambio y ve si la línea dio un salto.

## Medir y volver a medir

Junto al título del panel está la hora en que se midió el resultado actual. Un resultado se guarda un día: la primera vez que alguien abre la pestaña se mide la página, y durante las siguientes 25 horas todos ven esa misma medición en lugar de disparar una nueva. Esto es deliberado — Google limita cuántas veces podemos preguntar.

Cuando quieras una cifra nueva al momento, pulsa <b>Re-measure</b> (volver a medir). Las esferas se ponen grises, el panel dice que está midiendo, y los resultados suelen llegar en menos de un minuto; el panel se refresca solo, así que no hace falta recargar. Si Google va lento, el panel sigue comprobando durante unos tres minutos, y luego te pide que pulses <b>Re-measure</b> otra vez.

Cada medición completada, tanto si viene de que abriste la pestaña como de una ejecución en lote, se guarda en el historial de puntuación. Así que cuanto más se mire la página, más densa será su línea de historial.

## Cuando no hay puntuaciones

- **"This webpage has no publicly reachable URL to analyse"** — la página no está en vivo, o el sitio web aún no tiene un dominio público. Publica la página, o pide a quien configuró el sitio que termine de apuntar el dominio.
- **"Google could not measure this page"** con un mensaje debajo — Google llegó a la página y se negó o falló. Las causas habituales son una página que devuelve un error a un visitante que no ha iniciado sesión, un bucle de redirecciones, o una página demasiado lenta para terminar de cargar. Abre la página en una ventana privada primero; si no funciona ahí, tampoco funciona para Google.
- **Sin bloque de Core Web Vitals** — no hay suficientes visitantes reales en los últimos 28 días. Todo lo demás del panel sigue aplicando.

## Qué hacer con una puntuación mala

La mayor parte de lo que mueve estas cifras no está en el texto de la página:

- **Las imágenes** son la culpable habitual. Una foto de producto enorme reducida en el navegador le cuesta al visitante el archivo original completo.
- **Cualquier cosa incrustada** — un vídeo, un mapa, un chat o un widget de reseñas — es código de otro corriendo en tu página, y se cuenta en tu contra.
- Las puntuaciones de <b>Accessibility</b> y <b>SEO</b> a menudo pierden puntos por cosas rápidas de arreglar desde el taller: descripciones de imagen que faltan, un título o descripción de página que falta, encabezados usados por su tamaño en vez de por su significado, o texto demasiado pálido sobre su fondo.

Los problemas de rendimiento que son iguales en todas las páginas de un sitio web son nuestros, no tuyos; levanta un ticket con la dirección de la página y una captura del panel. Los problemas en una sola página suelen ser del propio contenido de esa página.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Abrir el panel:</b> tu organización → tu tienda → <b>Website → Webpages</b> → la página → <b>Performance</b>, y baja hasta <b>PageSpeed Insights</b>.</li>
<li><b>Teléfono u ordenador:</b> los botones <b>Desktop</b> / <b>Mobile</b> en la cabecera del panel.</li>
<li><b>Explicar una puntuación:</b> pasa el ratón por una de las cuatro esferas.</li>
<li><b>Medición nueva:</b> el botón <b>Re-measure</b> a la derecha de la cabecera del panel.</li>
<li><b>Cambiar el periodo del historial:</b> las fechas <b>From</b> y <b>To</b> arriba en la pestaña Performance.</li>
<li><b>Elegir la línea del historial:</b> el desplegable junto a <b>Score history</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Bueno saberlo</strong>
<ul>
<li><b>Las puntuaciones son de Google.</b> aiku consulta a PageSpeed Insights y muestra la respuesta; no calculamos las notas.</li>
<li><b>Solo se mide la página en vivo</b>, nunca un borrador, y nunca una página que requiere iniciar sesión.</li>
<li><b>Una medición al día por página</b>, por dispositivo, salvo que pulses <b>Re-measure</b>.</li>
<li><b>El tráfico y las ventas de la misma página</b> están en el gráfico encima de este panel — ver <a href="/docs/did-my-webpage-change-work-es">¿Funcionó mi cambio en la página web?</a>.</li>
</ul>
</aside>
</content>
</invoke>
