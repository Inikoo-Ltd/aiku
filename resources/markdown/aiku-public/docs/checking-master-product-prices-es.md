---
title: Comprobar los precios de los productos maestros
summary: De dónde saca aiku el precio sugerido al crear un producto maestro, los avisos en rojo sobre precios que parecen incorrectos, y qué hacer cuando cambian las unidades comerciales de un producto.
date: 2026-09-28
source_date: 2026-09-28
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
El precio de un producto maestro llega a todas las tiendas que lo venden, así que un solo error llega a todas las tiendas a la vez. aiku ahora marca en rojo dos tipos de precio sospechoso: un precio por unidad <b>muy alejado del resto de su familia</b>, y un precio que <b>no se revisó después de que cambiaran las unidades comerciales</b>. Si creas productos maestros o te ocupas de los precios, lee esta guía.
</aside>

## De dónde sale el precio sugerido

Cuando creas un producto maestro a partir de unidades comerciales, aiku te rellena un precio. Suma el coste de **cada unidad comercial que seleccionaste** y luego aplica el margen habitual de la tienda maestra. El RRP se calcula a partir de ese precio de la misma manera.

Esa sugerencia solo es tan buena como las unidades comerciales que elegiste:

- Selecciona **una** unidad comercial y la sugerencia es el precio de un artículo.
- Selecciona **varias** unidades comerciales y aiku trata el producto como un **bundle** de todas ellas. El precio sugerido es el precio del bundle completo.

Así que si querías crear una sola talla de una prenda y seleccionaste todas las tallas de la lista, el producto se tarifica como un pack de todas las tallas. Ese precio es muchas veces el que los clientes esperan por un solo artículo.

<aside class="tip">Comprueba siempre la lista de unidades comerciales y el campo <b>Unit</b> antes de guardar. Si el campo dice <b>bundle</b> y querías un solo artículo, has seleccionado demasiadas unidades comerciales.</aside>

## Aviso 1: el precio está alejado de la familia

aiku compara el **precio por unidad** de cada producto con el precio por unidad habitual de los **demás productos de la misma familia**. Si un producto cuesta **tres veces el precio habitual o más**, o **un tercio o menos**, aiku muestra un aviso.

- **Al crear un producto:** aparece un recuadro rojo debajo de los precios con el precio habitual de la familia. Al guardar, aiku te pide que confirmes.
- **En la pestaña Pricing:** el precio se muestra en rojo con un triángulo de aviso. Pasa el ratón por encima del triángulo para ver cuánto se aleja.

El aviso no bloquea nada. Algunas familias mezclan tamaños muy distintos, y una botella grande puede costar honestamente cinco veces más que una pequeña. Trátalo como una pregunta que responder: ¿es este realmente el precio correcto? Si lo es, déjalo. Si no lo es, corrígelo.

La comprobación necesita al menos otros tres productos en la familia, así que se mantiene en silencio en familias muy pequeñas.

## Aviso 2: las unidades comerciales cambiaron después de fijar el precio

Cambiar las unidades comerciales de un producto (su composición) **no cambia su precio**. Si un producto se creó como un bundle de 17 unidades comerciales y luego se corrigió a una sola, conserva el precio de 17 unidades hasta que alguien lo edite.

A partir de ahora, cuando las unidades comerciales de un producto maestro cambian y los precios no se guardan en la misma edición, el producto queda marcado para revisión. En la pestaña Pricing su precio se muestra en rojo con un triángulo de aviso, y el tooltip indica que la composición cambió después de fijar el precio.

La marca se borra en cuanto alguien **guarda los precios** de ese producto, ya sea de uno en uno o con una edición masiva de precios. Guardar el mismo precio de nuevo también la borra, así que puedes confirmar que un precio sigue siendo correcto.

## Qué comprobar

1. Abre la familia y ve a la pestaña **Pricing**.
2. Busca precios en rojo. Pasa el ratón por encima del triángulo para leer el motivo.
3. Para cada uno, comprueba la etiqueta de unidades comerciales junto al nombre. Muestra todas las unidades comerciales y su cantidad.
4. Si las unidades comerciales están mal, corrígelas primero en la página de edición del producto, y luego fija el precio.
5. Edita el precio con el lápiz y guárdalo. La marca roja desaparece.

No olvides tampoco el RRP: se calculó a partir del mismo precio equivocado, así que compruébalo en la columna RRP.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Precios de una familia:</b> <b>Masters</b> → abre la tienda maestra → <b>Families</b> → abre la familia → pestaña <b>Pricing</b>.</li>
<li><b>Precios de una variante:</b> abre la familia → la variante → pestaña <b>Pricing</b>.</li>
<li><b>Editar un precio:</b> el lápiz junto al precio en la pestaña Pricing.</li>
<li><b>Editar varios precios:</b> marca los productos en la pestaña Pricing y usa la edición masiva de precios.</li>
<li><b>Corregir unidades comerciales:</b> abre el producto maestro → edit → composition.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Las tiendas maestras están a nivel de grupo. Necesitas acceso de nivel de grupo a masters para ver la pestaña Pricing, y acceso de edición para cambiar precios o unidades comerciales.</p>
</aside>
