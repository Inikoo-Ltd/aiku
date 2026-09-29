---
title: Revisar tus pedidos
summary: Encuentra los pedidos de cada canal de ventas, lee su estado, comprueba qué se envió y qué pagaste, y entiende por qué un pedido está sin pagar, cancelado o no aparece.
date: 2026-09-25
source_date: 2026-09-25
tags: pedidos, estado del pedido, no pagado, cancelado, lista de pedidos
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Los pedidos se guardan por canal de ventas. En el menú de la izquierda abre tu canal y pulsa <b>Pedidos</b>. Cada pedido muestra su estado, y una etiqueta roja <b>No pagado</b> cuando no pudimos cobrar todavía. Un pedido sin pagar espera y no se envía al almacén hasta que se paga. Pulsa la referencia del pedido para ver los productos, la dirección de entrega, el número de seguimiento y la factura.
</aside>

## Dónde están tus pedidos

Cada canal que conectaste (Shopify, eBay, TikTok, WooCommerce y los demás, y tu canal manual) tiene su propia lista de pedidos.

1. En el menú de la izquierda, busca tu canal en <b>Canales</b>.
2. Pulsa <b>Pedidos</b> debajo.

La lista tiene estas columnas: <b>Estado</b>, <b>Referencia</b>, <b>Cliente</b>, <b>Fecha</b>, <b>Elementos</b> y <b>Total</b>. Los pedidos más recientes están arriba. Usa el cuadro de búsqueda para encontrar un pedido por su referencia.

La <b>Referencia</b> es nuestro número de pedido. No es el número de pedido de tu tienda ni un número de seguimiento. Para encontrar el número de seguimiento, consulta [Encontrar el número de seguimiento de un pedido](/docs/tracking-numbers).

En un canal Manual/API, los pedidos que aún estás preparando no están en esta lista. Están en <b>Cestas</b>, dentro del mismo canal, hasta que los realices.

Para descargar la lista, usa el botón de exportar arriba de la página y elige <b>Excel</b> o <b>CSV</b>.

<!-- captura de pantalla: la lista de Orders de un canal, con la columna Status, una referencia con la etiqueta roja Unpaid y el botón de exportar -->

## Qué significa cada estado

- <b>Submitted</b>: tenemos el pedido. Si no está pagado, se queda aquí hasta que se pague.
- <b>In Warehouse</b>: el pedido está pagado y a la espera de preparación.
- <b>Preparación de pedidos</b>: el almacén está preparando los productos.
- <b>Espera</b>: la preparación está en pausa, por ejemplo mientras el almacén revisa un producto.
- <b>Preparado</b>, <b>Embalaje</b>, <b>Empacado</b>: el paquete se está preparando.
- <b>Finalized</b>: el pedido está facturado y listo para salir.
- <b>Dispatched</b>: el paquete ha salido de nuestro almacén. El número de seguimiento está en el pedido.
- <b>Cancelled</b>: el pedido no se enviará.

Junto a la referencia también puedes ver pequeños iconos para <b>Despacho premium</b>, <b>Embalaje extra</b> y <b>Seguro</b> cuando los elegiste para ese pedido.

## Pedidos sin pagar

Una etiqueta roja <b>No pagado</b> significa que no pudimos cobrar el importe completo todavía. El pedido se queda <b>Submitted</b> y no se envía al almacén.

Cuando llega un pedido de tu tienda, lo pagamos así:

1. Primero con tu saldo.
2. Si el saldo no es suficiente, con tus tarjetas guardadas, empezando por la predeterminada.

Si ninguna funciona, el pedido espera y te enviamos un correo diciendo que está en espera. La mayoría de las veces esto pasa porque no hay ninguna tarjeta guardada para el canal. Para que no vuelva a pasar, guarda una tarjeta: consulta [Cómo se pagan tus pedidos](/docs/topping-up-and-paying-with-balance).

Para pagar un pedido que está en espera:

1. Recarga tu saldo con al menos el importe pendiente. Consulta [Cómo se pagan tus pedidos](/docs/topping-up-and-paying-with-balance).
2. Abre el pedido de nuevo. Un aviso amarillo dice <b>Order ... is not paid yet</b> y muestra <b>Tu saldo</b>.
3. Pulsa el botón <b>Pay ... with balance</b>. Muestra el importe pendiente.

El pedido pasa entonces al almacén. El botón solo aparece cuando tu saldo cubre todo el importe pendiente, mientras el pedido está <b>Submitted</b> o <b>Preparación de pedidos</b>.

<b>Nota:</b> no volvemos a intentar tu tarjeta nosotros. Un pedido en espera se queda en espera hasta que lo pagas.

## Dentro de un pedido

Pulsa la referencia del pedido para abrirlo. Verás:

- Arriba, una línea de tiempo con los pasos que ha pasado el pedido, y una etiqueta <b>Pagado</b> o <b>No pagado</b>.
- Tu cliente: nombre, correo, teléfono y dirección de entrega.
- <b>Peso</b>: el peso estimado de todos los productos.
- <b>Notas de entrega</b>: los paquetes, su estado, y en <b>Envíos</b> el transportista y el número de seguimiento. El icono de PDF (<b>Download Picking List</b>) descarga la lista de productos del paquete.
- <b>Facturas</b>: nuestra factura del pedido, para abrir o descargar en PDF. Consulta [Tus facturas](/docs/invoices).
- El resumen de precio: <b>Elementos</b>, cargos, <b>Neto</b>, impuestos y <b>Total</b>.

Estos son los cargos adicionales de esta web:

{order_charges}

- La pestaña <b>Transactions</b>: cada producto con su <b>Cantidad</b>. Cuando se enviaron menos de los pedidos, la cantidad enviada aparece en rojo encima de la cantidad pedida, que se muestra tachada.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> y <b>Other Instructions</b>. Las instrucciones de entrega se imprimen en la etiqueta de envío.

<!-- captura de pantalla: una página de pedido mostrando la línea de tiempo, el cuadro Delivery Notes con un número de seguimiento y el cuadro Invoices -->

## Cuando no se envió todo

A veces no podemos enviar todos los productos, por ejemplo cuando uno se agota mientras preparamos el pedido. La página del pedido muestra entonces <b>Dispatched | Modified</b>, y en la lista aparece un icono de aviso amarillo junto al estado. La pestaña <b>Transactions</b> muestra qué productos no se enviaron. El dinero de los productos que no enviamos vuelve a tu saldo automáticamente cuando se factura el pedido.

## Pedidos cancelados

Un pedido cancelado muestra su estado <b>Cancelled</b> arriba, y un aviso rojo <b>Order cancelled</b> cuando se registró un motivo. El dinero que ya pagaste por él vuelve a tu saldo.

Para un pedido de Shopify también hay un botón de sincronización (tooltip <b>Sync order state</b>) arriba. Púlsalo para avisar a Shopify de que el pedido se canceló. Si ves <b>The order state on Shopify is up-to-date</b>, Shopify ya lo sabe.

No puedes cancelar un pedido tú mismo una vez realizado. Si necesitas detener uno, contacta con atención al cliente cuanto antes con la referencia del pedido. Una vez empaquetado el pedido puede ser demasiado tarde.

## Dejar una valoración

En algunas de nuestras webs, un tiempo después de expedirse un pedido, aparece un botón <b>Review</b> arriba del pedido. Úsalo para valorar el pedido y los productos.

## Cuando algo falla

**Un pedido de mi tienda no está en la lista.** Comprueba esto, en este orden:

- Solo llegan los productos que están en <b>Mis productos</b> de ese canal. Si ninguno de los productos del pedido está en <b>Mis productos</b>, el pedido no aparece en <b>Pedidos</b>, porque no hay nada que podamos enviar.
- Comprueba que estás mirando el canal correcto. Cada canal tiene su propia lista.
- El canal debe seguir conectado. Si la página del canal dice que no está conectado, reconéctalo primero.
- **Shopify**: solo llegan los pedidos que Shopify envía a nuestra ubicación de envío, y llegan como una solicitud de fulfilment. Si un producto del pedido no está en <b>Mis productos</b>, esa parte de la solicitud se rechaza en Shopify y el resto llega. Cuando se rechaza toda la solicitud (ninguno de los productos está en <b>Mis productos</b>, o el pedido no tiene dirección de envío), el pedido aparece en <b>Pedidos</b> como <b>Cancelled</b>, con el motivo en <b>Notes from Staff</b>. Corrige el pedido en Shopify y solicita el fulfilment de nuevo. Un pedido que se envía desde tu propia tienda, ya está enviado, o cuya solicitud se canceló en Shopify no llegará. Este suele ser el motivo por el que un pedido de prueba no llega: comprueba en Shopify que sus productos tienen stock en nuestra ubicación.

**Mi solicitud de fulfilment de Shopify se aceptó pero no veo el pedido para pagarlo.** Busca en <b>Pedidos</b> del canal de Shopify su estado y una etiqueta roja <b>No pagado</b>. Si no está ahí, contacta con atención al cliente con el número de pedido de Shopify.

**El pedido lleva mucho tiempo en Submitted.** Casi siempre es porque está sin pagar. Sigue los pasos de "Pedidos sin pagar" arriba.

**El pedido dice "We cannot deliver to ...".** No enviamos a ese país desde esta web. Consulta [Países a los que no podemos entregar](/docs/delivery-restrictions).

**Llegó un producto roto, o mi comprador quiere devolver algo.** Contacta con atención al cliente con la referencia del pedido y fotos. No nos envíes nada de vuelta antes de que atención al cliente te diga cómo hacerlo.
