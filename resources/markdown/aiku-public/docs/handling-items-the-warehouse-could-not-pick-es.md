---
title: Gestionar artículos que el almacén no pudo preparar
summary: Cuando un preparador no encuentra un artículo, el pedido espera a atención al cliente. Decide cada línea en Waiting for CRM, y consulta qué volverá al saldo del cliente.
date: 2026-09-29
source_date: 2026-09-29
tags: orders, picking, out of stock, balance, customer service
category: crm
help_routes: grp.org.shops.show.ordering.backlog.waiting_items
---

<aside class="tldr">
Cuando un preparador no encuentra un artículo, no lo marca como agotado él mismo: envía la línea a atención al cliente con una nota, y el pedido pasa a mostrarse como <b>Waiting</b> (esperando). Abre <b>Orders backlog</b>, pulsa el número rojo en la caja <b>Waiting</b>, y decide cada línea en <b>Waiting for CRM</b>: <b>Don't pick</b> (no preparar), <b>Replace</b> (sustituir) o <b>Send back to waiting warehouse</b> (devolver al almacén en espera). Solo cuando una línea se marca <b>Don't pick</b>, la página del pedido muestra su valor bajo <b>Marked out of stock so far</b>, y lo que se espera que vuelva al saldo del cliente.
</aside>

## Qué pasa en el almacén

El preparador va a la ubicación y el artículo no está, o no hay suficiente. En vez de adivinar, envía la línea a atención al cliente y normalmente deja una nota, por ejemplo "Out of stock" o "Product on this location?".

A partir de ese momento:

- La nota de entrega, y el pedido, se muestran como <b>Waiting</b> (esperando; también puedes verlo llamado handling blocked, bloqueado en preparación).
- En la página del pedido, la línea corta muestra cuántas unidades se prepararon frente a cuántas se pidieron, por ejemplo <b>2</b> tachado y <b>0</b>.
- La línea **no** se marca como agotada todavía. No se ha decidido nada, así que no se muestra ningún importe como pendiente de volver al cliente.

El producto puede seguir diciendo, por ejemplo, <b>Stock: 16 available</b>. Esa es la cifra en los libros. El preparador estaba delante de la estantería, así que su informe es lo que cuenta; los libros se corrigen cuando se revisa la ubicación.

## Dónde encontrar las líneas a decidir

1. Ve a tu tienda → <b>Orders</b> (Pedidos) → <b>Backlog</b>.
2. En la caja <b>Waiting</b>, pulsa el número rojo pequeño. Cuenta los pedidos con artículos esperando tu decisión.
3. La página <b>Waiting for CRM</b> lista una fila por cada nota de entrega, con un enlace a su pedido.

Cada línea muestra el SKO, cuántas unidades esperan, el producto con su precio neto y con IVA, y la nota del preparador. Una etiqueta <b>Still on picking</b> (aún en preparación) significa que el preparador sigue trabajando en el resto de esa nota de entrega.

## Decidir cada línea

<b>Don't pick</b> (botón rojo con una calavera; no preparar). El artículo no se enviará y no se cobrará al cliente por él. Úsalo cuando el artículo de verdad no está. Cuando no queda nada más esperando en la nota de entrega, el almacén puede seguir con el pedido de inmediato.

<b>Replace</b> (sustituir). Envía otro producto en su lugar, por ejemplo el mismo artículo en otro color. Puedes sustituir parte de un pack con el interruptor de cristal roto bajo <b>Quantity to replace</b> (cantidad a sustituir); consulta [Cambiar parte de un SKO en un pedido](/docs/changing-part-of-an-sko-on-an-order-es). Lo que no sustituyas sigue esperando a CRM.

<b>Send back to waiting warehouse</b> (devolver al almacén en espera). Devuelve la línea al almacén, con una nota, cuando crees que debería estar allí: otra ubicación, una entrega recién llegada, o que el preparador vuelva a mirar.

Si el cliente debería saberlo, contacta con él antes de decidir, para que la decisión coincida con lo que quiere.

### Cuando la línea es parte de un set

Un producto formado por varias piezas, como una lámpara de sal con su bombilla y su cable, se reembolsa por el valor de la pieza que falta: si falta la lámpara, se reembolsa la lámpara, no un tercio del producto.

Si el producto tiene activado **Sold only as a complete set** (se vende solo como set completo) en la página **Composition** (composición) de su maestro, o en la suya propia si no sigue las piezas del maestro, las demás piezas nunca se envían solas. Tras marcar <b>Don't pick</b> en una pieza, el pedido se queda en <b>Waiting</b> hasta que el almacén devuelve las demás piezas y pulsa <b>Parts put back</b> en el albarán. Entonces se reembolsa al cliente el producto completo.

## Qué muestra la página del pedido

En la página del pedido, la caja de pago muestra dos líneas adicionales en cuanto al menos una línea se ha marcado <b>Don't pick</b> mientras el pedido sigue en el almacén:

- <b>Marked out of stock so far</b>: el valor, con IVA, de todo lo marcado como no preparado en ese pedido.
- <b>Expected back to balance when picking finishes</b>: cuánto de lo que el cliente ya pagó volverá a su saldo.

Estas líneas **no** aparecen mientras una línea siga en <b>Waiting for CRM</b>. Si estás mirando un pedido que está <b>Waiting</b> y no hay ningún importe, revisa primero <b>Waiting for CRM</b>: la línea está esperando tu decisión.

El total del pedido se mantiene en el importe enviado hasta que termina la preparación. Después, la factura se hace por lo que realmente se envió y, en un pedido ya pagado, lo que se pagó por los artículos no enviados vuelve al saldo del cliente automáticamente.

## Antes de abrir un ticket

Si un importe parece que falta o está mal en un pedido en espera, comprueba primero si la línea sigue en <b>Waiting for CRM</b>. Pregunta a un compañero o a tu responsable, o busca en estas guías. Abre un ticket cuando de verdad esté roto.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Encontrar líneas que esperan tu decisión:</b> tu tienda → <b>Orders</b> → <b>Backlog</b> → número rojo en la caja <b>Waiting</b> → <b>Waiting for CRM</b>.</li>
<li><b>El artículo de verdad no está:</b> <b>Don't pick</b> en la línea.</li>
<li><b>Enviar otra cosa:</b> <b>Replace</b> en la línea → elige el producto y la cantidad → <b>Save</b>.</li>
<li><b>El almacén debe volver a mirar:</b> <b>Send back to waiting warehouse</b> → añade una nota → <b>Confirm</b>.</li>
<li><b>Ver el valor agotado:</b> abre el pedido → caja de pago → <b>Marked out of stock so far</b>.</li>
</ul>
</aside>
