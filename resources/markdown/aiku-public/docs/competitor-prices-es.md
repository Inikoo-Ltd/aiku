---
title: Precios de la competencia
summary: Cómo lee aiku los precios de las webs de tus competidores, empareja sus productos con tus productos maestros y muestra dónde eres más barato o más caro, con lo que conviene revisar en el panel de Competitors.
date: 2026-11-06
source_date: 2026-11-06
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
En <b>Masters › Competitors</b> le dices a aiku qué webs de la competencia vigilar. aiku lee por su cuenta sus productos y precios, los empareja con tus productos maestros y muestra <b>dónde eres más barato, parecido o más caro</b>, por unidad, en su moneda frente a tu propio precio en ella. Los marketplaces que bloquean las visitas automáticas se <b>revisan a mano</b> una vez al mes. La IA confirma los emparejamientos de los que está segura y te pide <b>revisar el resto</b> con un tick o una cruz. Los precios confirmados también aparecen en las <a href="/docs/price-tips-es">sugerencias de precio</a>.
</aside>

## Añadir un competidor

Ve a **Masters › Competitors** y pulsa **Add competitor** (añadir competidor). Necesitas:

- la **tienda maestra** con cuyos productos compite;
- un **nombre** y la **web**;
- a quién **vende**: a compradores finales (sus precios se comparan con tu RRP) o a minoristas (se comparan con tu precio);
- la **moneda** de sus precios.

El **idioma de su web** (**Language of their website**) se detecta solo al leer su dossier y se puede cambiar en **Edit**. Sus productos se emparejan, y su web se busca, con los nombres de tus productos en ese idioma, tomados de tu tienda en ese idioma, así que un competidor español se compara con tus nombres en español.

Con eso basta para una tienda Shopify o WooCommerce que muestra sus precios: aiku encuentra por sí mismo su catálogo público y lo lee a la mañana siguiente a las 04:00 UTC, con cada producto y cada tamaño de pack con su precio. La página del competidor muestra entonces **Shopify catalogue** o **WooCommerce catalogue** en *Prices read from* (precios leídos de).

Para cualquier otra web, rellena uno de los campos de **Where to read prices** (dónde leer los precios):

- **Product feed link** (enlace al fichero de productos): el fichero CSV o de texto con todos sus productos y precios que dan a sus clientes profesionales. Es la mejor opción; se lee cada mañana.
- **Search link** (enlace de búsqueda): busca cualquier palabra en su web, copia el enlace de la página de resultados y pon `{query}` donde estaba la palabra. aiku buscará entonces en su web cada semana los productos maestros que tengan una sugerencia de precio abierta. Si el enlace se deja vacío en una web con acceso de cliente, o el introducido lleva a una página sin las palabras buscadas, aiku usa el enlace de búsqueda que la propia web publica, si lo tiene; si no, el competidor muestra **The search link does not search**.
- **Login page** (página de acceso), **login email** y **password**: solo cuando muestran precios a clientes profesionales con sesión iniciada. La contraseña se guarda cifrada y no se vuelve a mostrar; déjala vacía al editar para conservarla. aiku inicia sesión de nuevo cada vez que busca.

## Marketplaces revisados a mano

Los grandes marketplaces que venden directamente al consumidor bloquean las visitas automáticas con controles de seguridad e inicios de sesión, así que aiku no puede leerlos. Añádelos como competidor que vende a **consumidores**, en la moneda que ves en su web, y activa **Prices checked by hand**. aiku no abre nunca su web: ni catálogo, ni búsqueda, ni dossier.

Una vez al mes, abre su web como comprador en la UE, busca nuestros productos expuestos (empieza por los fabricados en China, donde estos vendedores son más fuertes) y, en la página del competidor, pulsa **Add price check** por cada anuncio que sea el mismo artículo que el nuestro:

- **Our product code**, el código del producto maestro;
- el **enlace a su anuncio**, para que cualquiera lo abra y vea el precio con sus propios ojos;
- **su nombre de producto**, el **precio del pack** (IVA incluido, con el envío si lo cobran), las **unidades del pack** y los **días de entrega** que prometen;
- una **captura de pantalla** de su anuncio con el precio a la vista: elige el archivo o pégala directamente desde el portapapeles con Ctrl+V / Cmd+V. Es la prueba del precio y se conserva aunque el anuncio cambie o desaparezca.

El anuncio pasa a ser un emparejamiento confirmado, así que cuenta en el panel, en la comparación de precios **Confirmed** y en las sugerencias de precio como cualquier otro. Además se guarda cada revisión: la pestaña **Price checks** las lista de la más reciente a la más antigua, con su precio por unidad, el tuyo, la diferencia, el cambio desde la revisión anterior del mismo anuncio, los días de entrega, quién revisó y la captura (púlsala para verla a tamaño completo). Añadir el mismo enlace el mes siguiente añade una fila nueva, así el historial muestra si se están abaratando.

## Qué muestra el panel

**Masters › Competitors** se abre en el panel:

- **Competitors**: cuántos se leyeron bien, cuántos fallan y por qué (no se pudo abrir la web, nos bloqueó, falló el acceso o el enlace de búsqueda no busca), con el último error en palabras sencillas, y cuándo fue la última lectura.
- **Matches to review** (emparejamientos por revisar): cuántos emparejamientos no tenía claros la IA. Púlsalo para revisarlos.
- **Reviewed by staff this week** y **Confirmed this week**, cada uno frente a la semana pasada.
- Una tarjeta por tienda maestra: en cuántos productos confirmados eres **más barato**, **parecido** (dentro del 5%) o **más caro**, la diferencia mediana, y el porcentaje de productos maestros activos que tienen al menos un precio confirmado de la competencia.
- **Confirmed matches per week**: los emparejamientos confirmados por semana en las últimas 12 semanas.
- **Where they are cheapest** (dónde son más baratos) y **Where we are cheapest** (dónde somos más baratos): los diez emparejamientos confirmados con mayor diferencia en cada sentido, con enlace al producto maestro.
- Los competidores ordenados por emparejamientos confirmados, con su diferencia mediana, productos leídos, última lectura y estado.

Hasta que se añade el primer competidor, el panel explica qué hacer.

## Revisar emparejamientos

Después de cada lectura, la IA compara cada uno de sus productos con los tuyos. Un emparejamiento del que está segura se **confirma solo** (marcado *auto*); los demás esperan en **Prices to review**. Cada fila muestra tu producto, tu precio por unidad, su producto con un enlace, su precio por unidad y la diferencia: en rojo cuando ellos son más baratos, en verde cuando son más caros. Ambos precios van en su moneda, el tuyo tomado de tu precio en esa moneda (un precio de AW class en euros para un competidor en euros); solo cuando no tienes precio en su moneda se convierten los dos a la moneda del grupo.

Pulsa el **tick** cuando el emparejamiento es correcto y la **cruz** cuando no lo es. Los emparejamientos rechazados no vuelven a mostrarse. Los grupos de arriba alternan entre **To review**, **Confirmed**, **Rejected** y **All**; el grupo **Confirmed** sirve también como comparación de precios, ordenable por diferencia.

Un emparejamiento marcado como *Similar, not the same item* (similar, no es el mismo artículo) se conserva como pista y no cuenta en la comparación de precios hasta que pulses el **tick**: el tick dice que es el mismo artículo y, desde entonces, cuenta.

## La página del competidor

Pulsa el nombre de un competidor en la lista o en el panel. La cabecera muestra su logo, web, la plataforma en la que funciona su tienda (Shopify, WooCommerce, etc.), tienda maestra, a quién vende, la moneda, cómo se leen sus precios, cuándo se leyeron los precios por última vez y cuándo se actualizó el dossier por última vez. Después hay cuatro pestañas:

- **Dossier**, la pestaña por defecto: el informe de inteligencia sobre el competidor, creado cada semana con sus páginas públicas (ver abajo).
- **Matches**: sus emparejamientos, con la misma revisión de tick y cruz.
- **Products read**: todo lo leído de él, con el precio, el precio antes del descuento cuando la tienda lo muestra, cuánto se descuenta, unidades y enlace. El filtro **On sale now** deja solo los productos con descuento.
- **Policies**: el texto de cada página de envíos, devoluciones y condiciones leída para el dossier, cada una con su enlace, para poder comprobar un dato del dossier con la página de la que salió.

**Edit** abre el mismo formulario que al añadir, sin la tienda maestra.

### El dossier

Cada semana aiku abre en un navegador la página de inicio del competidor y sus páginas de envíos, devoluciones y condiciones, exactamente como lo haría un visitante, sin iniciar sesión. Hace una captura de pantalla de la página de inicio, lee lo que la empresa publica sobre sí misma, y la IA extrae los datos, cada uno con la página de la que salió, y escribe un resumen corto. El dossier también se crea al añadir un competidor y cada vez que pulsas **Refresh dossier** (tarda unos minutos; vuelve a la página más tarde).

La pestaña muestra, de arriba abajo:

- **Briefing**: de cuatro a seis frases sobre quiénes son, cómo fijan precios frente a nosotros, cuánto dependen de las ofertas, y sus envíos y condiciones, con una frase de posicionamiento.
- **What changed**: lo que cambia respecto a la actualización anterior, con fechas: el mínimo para envío gratis, los costes o plazos de envío, el pedido mínimo, el plazo de devolución, una oferta o cupón que apareció o terminó, métodos de pago, teléfono, email o dirección, plataforma, un número de productos que cambió más del 10%, o un porcentaje en oferta que cambió más de 10 puntos. Los cambios anteriores siguen como una cronología corta. Un primer dossier dice que aún no hay nada con qué comparar.
- **Screenshot** de su página de inicio; púlsala para ampliarla.
- **Identity**: nombre de la empresa, teléfonos, emails, dirección, país y redes sociales, tal como los publica su web.
- **Key numbers**: productos listados, rango de precios, porcentaje en oferta ahora, descuento medio, emparejamientos confirmados y la diferencia mediana frente a nosotros.
- **Site-wide offers**: banners, códigos de cupón, descuentos por volumen o por tramos y descuentos por suscribirse al boletín, cada uno con su página de origen, más los anuncios de su página de inicio.
- **Share of products on sale, last 90 days**: una línea con las lecturas diarias del catálogo, así una tienda con precio de lista alto que siempre está en oferta se ve como tal.
- **Shipping and terms**: envío gratis desde, costes de envío, adónde envían, plazos de entrega, pedido mínimo, devoluciones y pagos, cada uno con un enlace a la página que lo dice. *Not found on their pages* significa que las páginas leídas no lo dicen.
- **Most discounted right now**: sus productos con mayor descuento en la última lectura del catálogo.

Un dato sale solo de las páginas leídas: a la IA se le indica que diga *not found* en lugar de adivinar. Cuando una web bloquea el navegador o no responde, el dossier conserva su último contenido y la cabecera muestra el error.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Panel:</b> <b>Masters</b> → <b>Competitors</b>.</li>
<li><b>Añadir uno:</b> <b>Masters</b> → <b>Competitors</b> → <b>Add competitor</b>.</li>
<li><b>Revisar emparejamientos:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → tick o cruz en cada fila.</li>
<li><b>Comparación de precios:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → <b>Confirmed</b>.</li>
<li><b>Un competidor:</b> <b>Masters</b> → <b>Competitors</b> → <b>Competitors</b> → pulsa su nombre.</li>
<li><b>Añadir un precio revisado a mano:</b> abre el competidor → <b>Add price check</b>; el historial está en la pestaña <b>Price checks</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Los competidores están a nivel de grupo, junto con las tiendas maestras. Necesitas acceso de nivel de grupo a masters para ver el panel, las listas y las páginas de los competidores, y acceso de edición a masters para añadir o editar un competidor y para revisar emparejamientos.</p>
</aside>
