---
title: Vender pedidos anticipados
summary: Cómo se puede pedir un producto por encima de lo que hay en stock, ya sea porque está agotado temporalmente o porque se fabrica bajo pedido, qué ve y paga el cliente, cómo espera el pedido a que llegue la mercancía, y cómo se gestionan el saldo pendiente y las cancelaciones.
date: 2026-09-28
source_date: 2026-09-28
tags: orders, shop, catalogue, procurement, payments
category: orders
series: Pre-orders
---

<aside class="tldr">
Un <b>pedido anticipado</b> permite a un cliente comprar más de lo que tenemos en el almacén. Un <b>back-order</b> (pedido pendiente de stock) es un artículo de catálogo que está agotado temporalmente: se paga por completo y se envía cuando llega la siguiente entrega. Un <b>made-to-order</b> (hecho por encargo) no se guarda en stock en absoluto: lo pedimos al proveedor cuando un cliente lo compra, y los clientes trade pagan solo un <b>depósito</b> al pagar. En la web no cambia nada hasta que el interruptor <b>Enable pre-orders</b> de esa tienda está activado y un producto está marcado para ello. El pedido anticipado espera fuera del almacén hasta que llega su mercancía, y entonces el cliente paga lo que queda y se envía.
</aside>

## Back-order y made-to-order

Un producto se puede marcar de dos maneras, y las dos están desactivadas hasta que alguien las activa:

- **Back-order** (pedido pendiente de stock) — un producto que normalmente tenemos en stock. Mientras está agotado, los clientes pueden seguir pidiéndolo, y se envía cuando llega la siguiente entrega. La fecha estimada de envío sale de la llegada prevista del pedido de compra abierto.
- **Made-to-order** (hecho por encargo) — un producto que no tenemos en stock, como muebles o estatuas de nuestros proveedores en el extranjero. Lo pedimos al proveedor cuando un cliente lo compra. La fecha estimada de envío sale del plazo de entrega.

Si las dos están activadas, el producto se trata como made-to-order.

Los productos que no están marcados se comportan exactamente igual que antes: cuando están agotados, no se pueden comprar.

## Activar los pedidos anticipados para una tienda

Cada condición que el cliente acepta es un ajuste de la tienda, así que cada web puede tener las suyas. Están todas juntas en **Pre-orders** dentro de los ajustes de la tienda:

- **Enable pre-orders** (activar pedidos anticipados) — el interruptor de toda la tienda. Mientras está desactivado, los productos marcados se comportan como cualquier otro producto.
- **Default lead time** (plazo de entrega por defecto) y **Dispatch estimate range** (margen de la estimación de envío) — la web muestra un margen en semanas, no una fecha. Un plazo de 12 semanas con un margen de 2 muestra "estimated dispatch 12–14 weeks".
- **Made-to-order deposit** (depósito de hecho por encargo) — la parte que se paga al confirmar el pedido en los artículos made-to-order, el 30% salvo que se cambie.
- **Pay in full below** (pagar completo por debajo de) — los pedidos made-to-order de menos de este importe se pagan por completo, sin depósito.
- **Balance due within** (saldo pendiente antes de), los dos días de **balance reminder** (recordatorio de saldo) y **Cancel unpaid balance after** (cancelar saldo impagado tras) — qué pasa después de pedir el saldo pendiente.
- **Free cancellation of made-to-order** (cancelación gratuita de hecho por encargo) — los días laborables en que un cliente puede cancelar sin coste, hasta que hacemos el pedido al proveedor.
- **Full refund when late by** (reembolso completo si el retraso es de) — cuánto podemos retrasarnos antes de que el cliente pueda cancelar y recuperarlo todo.
- Los límites de **Pallet delivery** (entrega en palé), la **pallet quote tolerance** (tolerancia del presupuesto de palé) y una **pallet rate per country** (tarifa de palé por país) — ver más abajo.

Los importes están en la moneda propia de la tienda, así que una tienda de la UE fija su propio equivalente de los importes del Reino Unido.

## Marcar un producto

Los pedidos anticipados se configuran en el **master product** (producto maestro) una sola vez, y se copian a ese producto en todas las tiendas. El producto propio de una tienda tiene los mismos campos, pero un cambio en el maestro los sustituye.

En **Pre-order** dentro del producto se elige **Back-order** o **Made-to-order**, y opcionalmente:

- un **Lead time** (plazo de entrega) propio, en días, en lugar del del proveedor;
- su propio **Made-to-order deposit**, en lugar del de la tienda;
- una **Maximum quantity per order** (cantidad máxima por pedido). Déjalo vacío para que no haya límite.

El plazo de entrega sale, por este orden: el plazo propio del producto, luego el **pre-order lead time** (plazo de entrega del pedido anticipado) de su proveedor preferido, y luego el valor por defecto de la tienda. El plazo de un proveedor se puede escribir en días o en semanas. Para un back-order, una fecha de llegada escrita en un pedido de compra abierto o una entrega de stock tiene prioridad.

## Qué ve y paga el cliente

En la página del producto y en los listados de productos el cliente ve el tipo y la estimación, por ejemplo "Made to order · Estimated dispatch 9–11 weeks", junto con las condiciones del pedido anticipado. La cesta marca cada línea de pedido anticipado y vuelve a listar las condiciones.

Al pagar, el cliente debe **tick to accept the pre-order terms** (marcar la casilla para aceptar las condiciones del pedido anticipado) antes de que se muestre ningún pago. Las condiciones son las que le corresponden, por ejemplo:

- el plazo estimado de envío, y que es solo una estimación;
- las condiciones de depósito o de pago y las condiciones de cancelación;
- que los artículos artesanales varían en tamaño, color, veta y acabado, y que las medidas son aproximadas;
- que la entrega en palé es solo hasta la acera.

Qué se paga al confirmar el pedido:

- **Trade, back-order** — se paga por completo, como es normal.
- **Trade, made-to-order** — solo el depósito, o todo cuando el pedido vale menos que el umbral de la tienda.
- **Dropshipping** — todo, siempre.

Pastpay y el contra reembolso no se ofrecen en una cesta con pedidos anticipados, y la transferencia bancaria no se ofrece cuando hay un depósito pendiente, porque ninguno de los tres puede cobrar un depósito ahora y el resto después.

Las mismas condiciones se repiten en el correo de confirmación del pedido y en la factura.

## Cuando una cesta mezcla stock y pedidos anticipados

Cuando se realiza el pedido, los artículos en stock y los artículos de pedido anticipado se convierten en **dos pedidos**. El pedido en stock va al almacén ahora mismo, con su entrega normal. Los artículos de pedido anticipado forman un pedido propio, con sus propios gastos de envío, que espera hasta que llega la mercancía. Cada pedido muestra una nota que remite al otro.

El cliente puede en su lugar marcar **Hold my order and send everything together** (retener mi pedido y enviarlo todo junto): entonces no se divide nada y todo el pedido espera a la mercancía del pedido anticipado.

El cliente paga una sola vez al confirmar el pedido. La parte de ese pago que corresponde al pedido anticipado se traslada a él a través del saldo de la cuenta del cliente, de modo que el saldo en sí no cambia.

## Esperar a la mercancía

Un pedido anticipado nunca va al almacén por sí solo mientras espera. En el pedido verás un panel **Pre-order** con su estado, las fechas estimadas de envío, lo que se ha pagado y lo que queda pendiente.

Cuando llega el stock de todo lo que hay en el pedido anticipado, los pedidos anticipados que lo esperan lo toman, empezando por el más antiguo. A partir de ese momento la mercancía **se reserva para ellos** y deja de ofrecerse en la web. Entonces:

- si no queda nada por pagar, el pedido va al almacén de inmediato;
- si no, se envía al cliente un correo con un enlace para pagar el **saldo pendiente**, antes de los días que marca la tienda. En cuanto se paga, el pedido va al almacén por sí solo.

Si no se paga el saldo, el cliente recibe recordatorios en los días que marca la tienda. Pasado el límite de la tienda, el pedido se cancela, se retiene el depósito, y la mercancía vuelve a estar a la venta.

Si el pedido de compra de un back-order llega ahora más tarde de la fecha de envío que prometimos, se envía automáticamente al cliente un correo con las nuevas fechas y sus opciones de cancelación. Cuando cambian las fechas de cualquier otro pedido anticipado, usa **Change dispatch dates** (cambiar fechas de envío) en su panel y se envía al cliente el mismo correo.

## Entrega en palé

Los productos más pesados o más largos que los límites de palé de la tienda se marcan como **pallet delivery**. La página del producto y la cesta muestran una estimación aproximada para el país del cliente, por ejemplo "Estimated pallet delivery to Germany: approx. €150", tomada de las tarifas de palé de la tienda.

- **Trade** — cuando llega la mercancía, el pedido espera a que el personal escriba el coste real con **Pallet quote** (presupuesto de palé). El presupuesto se envía junto con la solicitud del saldo. Si supera la tolerancia de la tienda por encima de la estimación, el cliente puede cancelar y recuperar su depósito.
- **Dropshipping** — la estimación se cobra con el pedido. Cualquier diferencia con el coste final se factura o se reembolsa después de la entrega.

## Cancelar

Un cliente puede cancelar desde la página de su pedido, y ve cuánto se le devuelve antes de confirmar. El personal cancela desde el panel del pedido anticipado y elige el motivo. El reembolso va al saldo de la cuenta del cliente.

- **Our failure to deliver** (fallo nuestro en la entrega) — nos retrasamos más del límite de la tienda respecto a la fecha estimada de envío, el proveedor no puede suministrar, no se alcanzó el pedido mínimo del proveedor, o el presupuesto de palé supera la estimación: **se reembolsa todo**, depósito incluido, tanto para trade como para dropshipping.
- **Trade, back-order** — se puede cancelar en cualquier momento antes del envío con reembolso completo.
- **Trade, made-to-order** — gratis hasta que hacemos el pedido al proveedor, dentro de los días laborables de la tienda. Después de eso se **retiene el depósito** del made-to-order y se reembolsa el resto.
- **Dropshipping** — una vez realizado el pedido, el pago no se reembolsa.

<aside class="wayfinder">

### Dónde hacer clic en aiku

- **Activar los pedidos anticipados para una tienda** — abre la tienda, **Settings**, y luego **Pre-orders** › **Enable pre-orders**. Las demás condiciones están en la misma sección.
- **Marcar un producto** — **Masters**, abre el producto maestro, **Edit**, y luego **Pre-order**. El producto propio de una tienda tiene la misma sección en **Edit**.
- **Fijar el plazo de entrega de un proveedor** — abre el proveedor, **Edit**, y luego **Pre-orders**: **Pre-order lead time**, **Lead time in** (days or weeks) y **Order by date**.
- **Seguir un pedido anticipado** — abre el pedido. El panel **Pre-order** de arriba tiene **Supplier ordered**, **Goods arrived**, **Pallet quote**, **Change dispatch dates**, **Send to warehouse** y **Cancel pre-order**.
- **Ver todos los pedidos anticipados abiertos por proveedor** — ver [Planificar pedidos a proveedores para pedidos anticipados](/docs/planning-supplier-orders-for-pre-orders-es).

### Permisos que necesitas

- Cambiar los ajustes de una tienda requiere **organisation admin** o **shop admin**.
- Marcar productos requiere permiso para editar el catálogo, en los productos maestros o en esa tienda.
- Los botones del panel del pedido anticipado requieren permiso para editar pedidos en esa tienda.

</aside>
</content>
