---
title: Tus pedidos de Shopify y su estado
summary: Cómo nos llegan los pedidos de tu tienda de Shopify, cómo se pagan, qué significa cada estado, por qué un pedido puede quedarse en Submitted o no llegar, y qué enviamos de vuelta a Shopify.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, pedidos, estado, pago, submitted, no pagado, solicitud de fulfilment
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Cuando un cliente compra un producto conectado en tu tienda de Shopify, Shopify nos envía una solicitud de fulfilment y el pedido aparece en <b>Pedidos</b> de tu canal. Lo pagamos con tu saldo, y luego con tu tarjeta guardada. Un pedido pagado va a nuestro almacén por sí solo. Un pedido que no pudimos pagar se queda <b>Submitted</b> y <b>No pagado</b> hasta que lo pagas. Cuando lo enviamos, lo marcamos como completado en Shopify con el número de seguimiento.
</aside>

## Cómo nos llega un pedido

1. Un cliente compra uno de tus productos conectados en Shopify.
2. Shopify envía una solicitud de fulfilment de esos artículos a la ubicación <b>aiku-</b>.
3. Aceptamos la solicitud y creamos el pedido en tu canal. Lo encuentras en tu canal, <b>Pedidos</b>.

Solo pueden llegarnos los productos conectados en <b>Mis productos</b>. Si un pedido tiene algunos de nuestros productos y algunos tuyos propios, aceptamos los nuestros y tú envías el resto tú mismo.

## Cómo se paga el pedido

Intentamos pagar cada pedido nuevo enseguida:

1. Primero con tu saldo.
2. Si el saldo no es suficiente, con las tarjetas guardadas en <b>Tarjetas guardadas</b>, por tu orden de prioridad.

Si el pago funciona, el pedido va a nuestro almacén por sí solo. Si no, el pedido espera y te enviamos un correo diciendo que está en espera. La mayoría de las veces esto pasa porque no hay ninguna tarjeta guardada. Para que no vuelva a pasar, guarda una tarjeta: consulta [Tarjetas y opciones de pago](/docs/payment-cards-and-options).

## Paga un pedido que está en espera

Un pedido que no pudimos pagar muestra <b>No pagado</b> junto a su número y se queda <b>Submitted</b>.

1. Recarga tu saldo con al menos el importe pendiente, desde <b>Recargar Saldo</b> en el menú.
2. Abre tu canal, <b>Pedidos</b>, y abre el pedido.
3. Pulsa <b>Pay ... with balance</b>. El botón solo aparece cuando tu saldo cubre el importe pendiente.

El pedido pasa entonces a nuestro almacén por sí solo.

<!-- captura de pantalla: un pedido sin pagar con la etiqueta Unpaid y el botón Pay with balance -->

## Qué significa cada estado

- <b>Submitted</b>: tenemos el pedido. Si también muestra <b>No pagado</b>, espera tu pago.
- <b>In Warehouse</b>: pagado y a la espera de preparación.
- <b>Manipulación</b>: se está preparando.
- <b>Espera</b>: el almacén tuvo que detener el pedido un momento antes de poder continuar.
- <b>Preparado</b>, <b>Embalaje</b>, <b>Empacado</b>: el paquete se está preparando.
- <b>Finalized</b>: facturado y listo para salir.
- <b>Dispatched</b>: enviado. Si no se pudieron enviar algunos artículos, verás <b>Modified</b> y el dinero de esos artículos se reembolsa automáticamente.
- <b>Cancelled</b>: el pedido no se enviará. El motivo se muestra arriba del pedido.

Para más sobre la página del pedido, consulta [Revisar tus pedidos](/docs/reviewing-orders).

## Qué enviamos de vuelta a Shopify

- Cuando el pedido se expide, lo marcamos como completado en Shopify con el número de seguimiento y el enlace. Shopify avisa a tu cliente.
- Cuando se cancela un pedido, cerramos la solicitud en Shopify. En un pedido cancelado puedes pulsar el botón de sincronización (<b>Sync order state</b>) para enviar la cancelación a Shopify de nuevo. Si Shopify ya está actualizado, verás <b>The order state on Shopify is up-to-date</b>.

## Un pedido no está en mis Orders

Abre el canal y pulsa <b>Fetch orders</b>. <b>Checks Shopify for orders that have not reached us yet</b>: revisa los pedidos recientes sin completar y trae los de nuestra ubicación. Si no hay nada nuevo, verás <b>No new orders</b>. Puedes volver a pulsarlo pasados un par de minutos.

Si el pedido sigue sin llegar, comprueba esto:

- Los productos están conectados (apretón de manos verde) en <b>Mis productos</b>.
- Los artículos tienen stock en la ubicación <b>aiku-</b> en Shopify, y la ubicación está en tu perfil de envío. Consulta [La ubicación de envío de AW en Shopify](/docs/shopify-fulfilment-location).
- El pedido no se completó ya en Shopify, ni se envió a otra ubicación.

## Cuando algo falla

**Un pedido cancelado dice "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** Ninguno de los productos del pedido está conectado en <b>Mis productos</b>. Añádelos y conéctalos, y luego solicita el fulfilment de nuevo en Shopify.

**Un pedido cancelado dice "Fulfilment request declined: Order don't have shipping information".** El pedido en Shopify no tiene dirección de entrega. Añade la dirección en Shopify y solicita el fulfilment de nuevo.

**La solicitud de fulfilment se aceptó en Shopify, pero no veo el pedido para pagarlo.** Abre <b>Pedidos</b> en el canal: ahí es donde aparecen los pedidos de Shopify. Busca el pedido con <b>No pagado</b>, o pulsa <b>Fetch orders</b>.

**Mi pedido de prueba en Shopify no llegó.** Un pedido de prueba solo nos llega si contiene productos conectados con stock en la ubicación <b>aiku-</b>. Ten cuidado: un pedido que sí nos llega es un pedido real. Lo pagamos y lo enviamos. Si hiciste uno por error, contacta con atención al cliente cuanto antes para cancelarlo.

**El pedido se queda en Submitted y Unpaid.** No había saldo suficiente y ninguna tarjeta funcionó. Págalo como se muestra en <b>Paga un pedido que está en espera</b>, y guarda una tarjeta para los siguientes.

**El pedido dice "We cannot deliver to ...".** No enviamos a ese país desde esta web. El pedido no se paga ni se envía. Actualiza la dirección de entrega, o contacta con atención al cliente.

**El pedido está pagado pero lleva mucho tiempo sin moverse.** Contacta con atención al cliente con el número de pedido. El número de pedido es la <b>Referencia</b> en <b>Pedidos</b>.

<aside class="wayfinder"><strong>Dónde pulsar</strong>
<ul>
<li><b>Ver tus pedidos de Shopify:</b> <b>Canales</b> → tu tienda de Shopify → <b>Pedidos</b>.</li>
<li><b>Pagar un pedido en espera:</b> abre el pedido → <b>Pay ... with balance</b>.</li>
<li><b>Traer un pedido que falta:</b> abre el canal → <b>Fetch orders</b>.</li>
<li><b>Guardar una tarjeta:</b> <b>Tarjetas guardadas</b> en el menú.</li>
</ul>
</aside>
