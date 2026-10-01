---
title: Sugerencias de precio
summary: Cómo sugiere aiku cada noche una rebaja o una subida de precio para los productos maestros, qué muestra cada sugerencia y cómo aplicarla o descartarla.
date: 2026-09-30
source_date: 2026-09-30
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
Cada noche aiku revisa los productos maestros que tienen <b>demasiado stock en todos los almacenes</b>, o que se <b>están agotando en todos los almacenes</b>, y puede sugerir un cambio de precio. Una sugerencia es solo eso: <b>ningún precio cambia hasta que alguien la aplica y guarda</b>. Las sugerencias aparecen en la pestaña <b>Pricing</b> (precios), con las más seguras primero.
</aside>

## Qué productos reciben una sugerencia

El precio de un producto maestro es el mismo en todas las tiendas que lo venden, así que solo se da una sugerencia cuando todas las organizaciones coinciden:

- **Rebaja (precio más bajo):** el producto tiene stock para **120 días o más** en todas las organizaciones que lo tienen en stock.
- **Subida (precio más alto):** el producto **se agotará en menos de 45 días** en todas las organizaciones.

Si un almacén está lleno y otro se está quedando sin stock, no se da ninguna sugerencia.

Algunos productos nunca reciben sugerencia:

- productos que no vendieron nada en esos mismos meses hace un año, así que las líneas nuevas nunca se rebajan;
- la tienda maestra Aroma.

## Cómo se calcula la sugerencia

Para cada producto que cumple las condiciones, un modelo de IA revisa:

- las ventas mes a mes de los últimos dos años;
- el stock y los días de stock en cada organización, y el stock en camino de proveedores y socios;
- los días que el producto estuvo sin stock, porque quedarse sin stock baja las ventas sin bajar la demanda;
- el margen sobre el coste;
- el precio habitual de los demás productos de la misma familia;
- las ofertas activas en el producto o en su familia;
- los cambios de precio anteriores, y cómo se movieron las ventas en los tres meses siguientes a cada uno.

Elige una de estas opciones: bajar el precio un 15%, 10% o 5%, mantenerlo, o subirlo un 5% o 10%. También juzga si una caída de las ventas es **temporal** (falta de stock, la temporada, un pedido grande puntual el año pasado).

Antes de mostrar nada, aiku aplica reglas fijas:

- el cambio debe ir en la dirección correcta: una rebaja solo cuando sobra stock, una subida solo cuando el stock se está agotando;
- la IA debe estar al menos un 50% segura de su elección;
- no hay rebaja cuando la caída de las ventas parece temporal;
- una rebaja nunca lleva el precio por debajo de **coste + 25%**. Si lo hiciera, la rebaja se reduce, y el motivo lo indica.

Cuando la respuesta de la IA es "mantener el precio", no se muestra ninguna sugerencia.

## Qué muestra una sugerencia

En la pestaña **Pricing**, la columna **Price tip** (sugerencia de precio) muestra:

- el cambio, por ejemplo **−10%** en ámbar para una rebaja o **+5%** en verde para una subida;
- lo segura que está la IA, por ejemplo **72% sure**;
- un enlace **Dismiss** (descartar).

Pasa el ratón por encima del cambio para leer el motivo, por ejemplo: *Stock para 400 días en todas las organizaciones, ventas un 20% por debajo del año pasado, 20 más en camino, 80% de margen, el precio −10% el 2025-03-10 movió las ventas +25%*. El motivo se construye con las cifras anteriores, así que puedes comprobar cada una.

Los productos con sugerencia aparecen primero, con los más seguros arriba. Pulsa la cabecera de la columna para ordenar de otra manera.

Las sugerencias se calculan de nuevo cada noche. Si cambian el stock o las ventas, la sugerencia cambia o desaparece.

## Aplicar una sugerencia

1. Pulsa el cambio (por ejemplo **−10%**).
2. Se abre el editor de precios con todas las monedas ya movidas por ese porcentaje.
3. Comprueba los precios y ajusta los que quieras.
4. Guarda.

El nuevo precio llega a todas las tiendas mediante la actualización de precios normal, igual que cuando editas un precio a mano. El cambio queda registrado con tu nombre en el historial del producto.

Unas **8 semanas** después de aplicar una sugerencia, aiku compara las ventas del producto en las 8 semanas posteriores al cambio con las 8 semanas anteriores, y con las mismas semanas de un año antes. Ese producto no recibe una nueva sugerencia hasta que se haya medido.

## Descartar una sugerencia

Si una sugerencia es incorrecta, pulsa **Dismiss** y escribe el motivo, por ejemplo "Stock de Navidad, se vende en diciembre" o "precio acordado con un cliente importante". El motivo se guarda, para poder mejorar las reglas.

Un producto descartado no recibe una nueva sugerencia durante **30 días**.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver las sugerencias:</b> <b>Masters</b> → abre la tienda maestra → <b>Families</b> → abre la familia → pestaña <b>Pricing</b> → columna <b>Price tip</b>.</li>
<li><b>Aplicar:</b> pulsa el porcentaje, comprueba los precios, guarda.</li>
<li><b>Descartar:</b> el enlace <b>Dismiss</b> bajo el porcentaje.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Las tiendas maestras están a nivel de grupo. Necesitas acceso de nivel de grupo a masters para ver las sugerencias, y acceso de edición a masters para aplicarlas o descartarlas.</p>
</aside>
