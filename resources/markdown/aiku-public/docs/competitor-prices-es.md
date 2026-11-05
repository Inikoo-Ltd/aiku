---
title: Precios de la competencia
summary: Cómo lee aiku los precios de las webs de tus competidores, empareja sus productos con tus productos maestros y muestra dónde eres más barato o más caro, con lo que conviene revisar en el panel de Competitors.
date: 2026-11-06
source_date: 2026-11-06
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
En <b>Masters › Competitors</b> le dices a aiku qué webs de la competencia vigilar. aiku lee por su cuenta sus productos y precios, los empareja con tus productos maestros y muestra <b>dónde eres más barato, parecido o más caro</b>, por unidad y en la moneda del grupo. La IA confirma los emparejamientos de los que está segura y te pide <b>revisar el resto</b> con un tick o una cruz. Los precios confirmados también aparecen en las <a href="/docs/price-tips-es">sugerencias de precio</a>.
</aside>

## Añadir un competidor

Ve a **Masters › Competitors** y pulsa **Add competitor** (añadir competidor). Necesitas:

- la **tienda maestra** con cuyos productos compite;
- un **nombre** y la **web**;
- a quién **vende**: a compradores finales (sus precios se comparan con tu RRP) o a minoristas (se comparan con tu precio);
- la **moneda** de sus precios.

Con eso basta para una tienda Shopify o WooCommerce que muestra sus precios: aiku encuentra por sí mismo su catálogo público y lo lee a la mañana siguiente a las 04:00 UTC, con cada producto y cada tamaño de pack con su precio. La página del competidor muestra entonces **Shopify catalogue** o **WooCommerce catalogue** en *Prices read from* (precios leídos de).

Para cualquier otra web, rellena uno de los campos de **Where to read prices** (dónde leer los precios):

- **Product feed link** (enlace al fichero de productos): el fichero CSV o de texto con todos sus productos y precios que dan a sus clientes profesionales. Es la mejor opción; se lee cada mañana.
- **Search link** (enlace de búsqueda): busca cualquier palabra en su web, copia el enlace de la página de resultados y pon `{query}` donde estaba la palabra. aiku buscará entonces en su web cada semana los productos maestros que tengan una sugerencia de precio abierta.
- **Login page** (página de acceso), **login email** y **password**: solo cuando muestran precios a clientes profesionales con sesión iniciada. La contraseña se guarda cifrada y no se vuelve a mostrar; déjala vacía al editar para conservarla.

## Qué muestra el panel

**Masters › Competitors** se abre en el panel:

- **Competitors**: cuántos se leyeron bien, cuántos fallan y por qué (no se pudo abrir la web, nos bloqueó o falló el acceso), con el último error en palabras sencillas, y cuándo fue la última lectura.
- **Matches to review** (emparejamientos por revisar): cuántos emparejamientos no tenía claros la IA. Púlsalo para revisarlos.
- **Reviewed by staff this week** y **Confirmed this week**, cada uno frente a la semana pasada.
- Una tarjeta por tienda maestra: en cuántos productos confirmados eres **más barato**, **parecido** (dentro del 5%) o **más caro**, la diferencia mediana, y el porcentaje de productos maestros activos que tienen al menos un precio confirmado de la competencia.
- **Confirmed matches per week**: los emparejamientos confirmados por semana en las últimas 12 semanas.
- **Where they are cheapest** (dónde son más baratos) y **Where we are cheapest** (dónde somos más baratos): los diez emparejamientos confirmados con mayor diferencia en cada sentido, con enlace al producto maestro.
- Los competidores ordenados por emparejamientos confirmados, con su diferencia mediana, productos leídos, última lectura y estado.

Hasta que se añade el primer competidor, el panel explica qué hacer.

## Revisar emparejamientos

Después de cada lectura, la IA compara cada uno de sus productos con los tuyos. Un emparejamiento del que está segura se **confirma solo** (marcado *auto*); los demás esperan en **Prices to review**. Cada fila muestra tu producto, tu precio por unidad, su producto con un enlace, su precio por unidad en la moneda del grupo y la diferencia: en rojo cuando ellos son más baratos, en verde cuando son más caros.

Pulsa el **tick** cuando el emparejamiento es correcto y la **cruz** cuando no lo es. Los emparejamientos rechazados no vuelven a mostrarse. Los grupos de arriba alternan entre **To review**, **Confirmed**, **Rejected** y **All**; el grupo **Confirmed** sirve también como comparación de precios, ordenable por diferencia.

Un emparejamiento marcado como *Similar, not the same item* (similar, no es el mismo artículo) se conserva como pista, pero nunca cuenta en la comparación de precios.

## La página del competidor

Pulsa el nombre de un competidor en la lista o en el panel para ver su tienda maestra, web, a quién vende, la moneda, cómo se leen sus precios, si aiku inicia sesión, su estado y último error, y dos pestañas:

- **Matches**: sus emparejamientos, con la misma revisión de tick y cruz;
- **Products read**: todo lo leído de él, con precio, unidades y enlace.

**Edit** abre el mismo formulario que al añadir, sin la tienda maestra.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Panel:</b> <b>Masters</b> → <b>Competitors</b>.</li>
<li><b>Añadir uno:</b> <b>Masters</b> → <b>Competitors</b> → <b>Add competitor</b>.</li>
<li><b>Revisar emparejamientos:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → tick o cruz en cada fila.</li>
<li><b>Comparación de precios:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → <b>Confirmed</b>.</li>
<li><b>Un competidor:</b> <b>Masters</b> → <b>Competitors</b> → <b>Competitors</b> → pulsa su nombre.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Los competidores están a nivel de grupo, junto con las tiendas maestras. Necesitas acceso de nivel de grupo a masters para ver el panel, las listas y las páginas de los competidores, y acceso de edición a masters para añadir o editar un competidor y para revisar emparejamientos.</p>
</aside>
