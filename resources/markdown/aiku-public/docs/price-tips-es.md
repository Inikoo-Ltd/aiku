---
title: Sugerencias de precio
summary: Cómo señala aiku cada noche los productos maestros cuyo precio merece una revisión, qué muestra cada sugerencia, cómo descartarla y cómo revisar y guardar precios con tu asistente de IA.
date: 2026-10-10
source_date: 2026-10-10
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
Cada noche aiku señala los productos maestros cuyo <b>stock y ventas indican que el precio merece una revisión</b>. Una sugerencia <b>nunca propone un precio ni cambia nada</b>: muestra los datos y deja la decisión en tus manos. Las ves en la pestaña <b>Pricing</b>. Para decidir, revisa la familia por tu cuenta o con tu asistente de IA, que también puede guardar los precios que acordéis.
</aside>

## Qué productos reciben una sugerencia

El precio de un producto maestro es el mismo en todas las tiendas que lo venden, así que aiku mira los días de stock de cada organización y hace la media según **cuánto vendió cada organización en el último año**. La organización que vende la mayor parte del producto es la que más cuenta; la que vende poco apenas cuenta.

Un producto se señala por uno de estos tres motivos:

- **Más de 18 meses de stock y ventas en descenso** (*Over 18 months of stock, sales falling*): 540 días de stock o más, y ventas un 40% o más por debajo del año anterior.
- **Se agota y se vende más rápido** (*Running out, selling faster*): menos de 30 días de stock, ventas un 25% o más por encima del año anterior, y nada en camino de proveedores o socios.
- **Se vende peor que el resto de su familia** (*Selling worse than its family*): la familia aguanta (baja como mucho un 10%) mientras este producto va 50 puntos o más por detrás, con al menos 120 días de stock.

Solo se señalan productos con ventas reales: al menos 500 en la moneda del grupo en doce meses. En cualquier momento, unos 500 de los 20.000 productos maestros tienen una sugerencia.

Algunos productos nunca reciben una sugerencia:

- productos que no vendieron nada en los dos últimos años;
- líneas nuevas, que no tienen año anterior con el que comparar;
- productos cuyo precio alguien cambió a mano en los últimos **30 días**;
- productos cuya sugerencia se descartó en los últimos **30 días**;
- exceso de stock cuando el problema es la web (páginas fuera de línea, sin imágenes, o la página de la familia perdiendo visitas): la columna indica entonces qué falla en la web;
- la tienda maestra Aroma.

Cuando el stock y las ventas son normales, la última columna queda vacía. Cuando un producto se deja fuera por otro motivo, la columna lo explica en gris, por ejemplo *No tip: price changed by hand on 7 Oct, no new tip for 30 days after that* (precio cambiado a mano el 7 de octubre, sin sugerencias durante los 30 días siguientes), *No tip yet: new, on sale for 30 days* (nuevo, a la venta desde hace 30 días) o *No tip: the cost on record is above the price, check the cost* (el coste registrado es superior al precio, revise el coste). Este último significa que el coste del producto es erróneo y hay que corregirlo.

## Qué muestra una sugerencia

En la pestaña **Pricing**, la última columna muestra el motivo en ámbar. Pulsa sobre él para leer los datos, uno por línea:

- días de stock y stock en camino;
- ventas frente al año anterior;
- días que el producto estuvo sin stock;
- el margen sobre el coste y el precio habitual en su familia;
- ofertas activas;
- su posición en la familia (puesto, parte de las ventas de la familia, tendencia de la familia frente a la suya);
- cómo va la web;
- el último cambio de precio y cómo se movieron las ventas en los tres meses siguientes.

No hay porcentaje ni botón Apply. Una sugerencia es un aviso, no un precio.

Las sugerencias se recalculan cada noche. Si el stock o las ventas cambian, la sugerencia cambia o desaparece.

## Decidir el precio

Los precios suelen fijarse para toda una familia a la vez, así que mira la familia, no solo el producto señalado. Puedes:

- editar los precios en la misma pestaña **Pricing**, un producto o muchos a la vez, como siempre;
- o revisar la familia con tu asistente de IA. Pídele, por ejemplo, *«revisa los precios de la familia ABC en la tienda maestra aw»*. Lee el stock, las ventas, los márgenes y los cambios de precio anteriores de toda la familia y los comenta contigo.

Cuando hayas decidido, el asistente puede **guardar los precios por ti**. Primero muestra el precio anterior y el nuevo de cada producto y solo guarda cuando lo confirmas. Das los precios en las monedas principales; las demás siguen por tipo de cambio, salvo las fijadas a mano, que no se tocan. Cada guardado queda en el registro de cambios de IA con tu nombre y tu petición exacta, y puede revertirse allí.

Guardar precios a través del asistente requiere el interruptor **Can change master prices through their AI assistant**, que un administrador activa en la página de edición del usuario, además del acceso de edición a masters.

## Descartar una sugerencia

Si una sugerencia no es relevante, pulsa sobre ella, luego **Not relevant**, y escribe por qué, por ejemplo «stock de Navidad, se vende en diciembre» o «liquidando esta línea». El motivo se guarda con el producto y se muestra a quien revise la familia después, incluido tu asistente de IA.

Un producto cuya sugerencia se descartó no recibe otra durante **30 días**.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver las sugerencias de una familia:</b> <b>Masters</b> → abre la tienda maestra → <b>Families</b> → abre la familia → pestaña <b>Pricing</b> → última columna.</li>
<li><b>Ver todas las sugerencias de una tienda maestra:</b> <b>Masters</b> → abre la tienda maestra → <b>Products</b> → pestaña <b>Price tips</b>.</li>
<li><b>Descartar una:</b> pulsa la sugerencia → <b>Not relevant</b> → escribe por qué.</li>
<li><b>Permitir que alguien guarde precios con su asistente:</b> <b>Sysadmin</b> → <b>Users</b> → edita el usuario → <b>Can change master prices through their AI assistant</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Las tiendas maestras están a nivel de grupo. Necesitas acceso de nivel de grupo a masters para ver las sugerencias, y acceso de edición a masters para descartarlas o cambiar precios. Guardar precios a través de un asistente de IA requiere además el interruptor indicado arriba.</p>
</aside>
