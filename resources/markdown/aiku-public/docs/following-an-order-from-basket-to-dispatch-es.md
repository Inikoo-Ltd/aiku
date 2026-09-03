---
title: Seguir un pedido desde la cesta hasta el envío
summary: El recorrido completo de un pedido de venta en aiku, desde la cesta del cliente hasta el picking, el embalaje, la facturación y el envío, y dónde comprobarlo en cada paso.
date: 2026-09-02
source_date: 2026-09-02
tags: orders, orders lifecycle
category: orders
---

<aside class="tldr">
Todo pedido de venta pasa por una serie fija de estados, desde <b>En cesta</b> hasta <b>Enviado</b>. Puedes seguir el progreso de cualquier pedido desde la pantalla <b>Pedidos</b> de la tienda: abre el pedido y verás en qué estado está, sus líneas, sus albaranes y, una vez facturado, su factura. Este artículo recorre ese camino en orden.
</aside>

## Dónde empieza un pedido: En cesta

El cliente construye un pedido añadiendo productos; en aiku cada línea de producto se llama transacción. Mientras sigue añadiendo y cambiando cosas, el pedido está en el estado **En cesta**. Todavía no se ha enviado nada a ningún sitio y el cliente puede seguir editándolo libremente.

## Enviado al sistema (Submitted)

Cuando el cliente finaliza la compra, el pedido pasa a **Submitted**. Un pedido solo se puede confirmar una vez: intentar confirmarlo de nuevo está bloqueado, igual que confirmar un pedido sin ninguna línea.

Si el pedido ya está pagado, o es contra reembolso, aiku lo manda al almacén en el mismo momento. Si el pago todavía está pendiente, el pedido espera en **Submitted** hasta que llegue el pago.

## En almacén, y después el picking

Cuando un pedido se manda al almacén, aiku crea un **albarán** (delivery note) para él y el pedido pasa a **En almacén**. Es su puesto en la cola: está esperando a que un preparador empiece.

A partir de ahí el pedido sigue al albarán por el almacén:

- **Handling** — un preparador está recogiendo el pedido.
- **Waiting** (internamente, handling blocked) — el picking se ha parado, por ejemplo porque no se encuentra stock.
- **Picked** — todas las líneas están recogidas.
- **Packing**, y después **Packed** — el pedido está embalado y listo para salir.

## Finalizado: aquí se crea la factura

Cuando se finaliza el albarán, aiku pasa el pedido a **Finalizado** y, en el mismo paso, genera su factura. Un pedido no se puede finalizar dos veces: si ya tiene factura, volver a finalizarlo está bloqueado. Este es el momento en que la venta se convierte en una factura real en Contabilidad, y la verás aparecer en la pestaña **Facturas** del pedido.

## Enviado

Cuando el albarán sale de verdad del almacén, el estado del pedido cambia a **Enviado**. Ahí termina el recorrido normal: la mercancía ha salido y la venta está facturada.

## Cancelado

Un pedido puede cancelarse en lugar de seguir los estados anteriores, por ejemplo si una tienda cierra antes de que el pedido se procese. Un pedido cancelado deja de avanzar.

## Cambiar una dirección después de confirmar el pedido

Cuando se confirma un pedido, aiku hace una copia de las direcciones de facturación y de entrega del cliente y las guarda con el pedido. Los impuestos y el envío se calcularon con esas direcciones, así que el pedido no cambia solo. Editar la dirección en la ficha del cliente actualiza las cestas que sigan abiertas, pero nunca toca un pedido ya confirmado.

Para cambiar la dirección de un pedido confirmado, hazlo en el propio pedido. Abre el pedido y, junto al bloque de direcciones, pulsa **Editar** para cambiar la dirección de entrega o **Editar dirección de facturación** para cambiar la de facturación. Si el pedido muestra una sola dirección combinada, los dos enlaces están debajo. El cambio se aplica al pedido y a su albarán, y los totales se recalculan si cambia el tratamiento fiscal. Esto es posible hasta que el pedido se envía.

Una vez enviado, las direcciones del pedido quedan fijas. Si el cliente necesita entonces otra dirección en su factura, corrígela en la factura: abre la factura desde la pestaña **Facturas** del pedido y usa el lápiz junto a la dirección.

## Notas de un pedido, y qué se imprime en la etiqueta de envío

Abre cualquier pedido y verás una fila de cajas de notas de colores en la parte superior. Cada una va a un sitio distinto, así que importa en cuál escribas. Haz doble clic en una caja para editarla.

- **Shipping Label (From Customer)** (etiqueta de envío, del cliente) — el texto que se imprime en la etiqueta del transportista bajo la dirección. Solo caben los primeros 34 caracteres, así que limítate a lo que necesita el repartidor: "Llamar al timbre de la puerta lateral", "Abierto de 9 a 5, lunes a viernes". Normalmente lo rellena el cliente como *instrucciones de entrega* en su cesta, pero el personal también puede editarlo.
- **Customer Instructions** (instrucciones del cliente) — lo que el cliente escribió al hacer el pedido. Solo lectura.
- **Public Note** (nota pública) — visible tanto para el cliente como para el personal.
- **CRMs Note (Private)** (nota de CRM, privada) — solo para el personal, no se muestra en el albarán.
- **Warehouse Note (Private)** (nota de almacén, privada) — solo para el personal, y se imprime en el albarán para quienes hacen el picking y el embalaje.

Algunos clientes necesitan el mismo texto de etiqueta en todos sus paquetes, normalmente su horario de apertura. No hace falta volver a escribirlo en cada pedido. Abre la ficha del cliente y rellena **Shipping Label (Permanent)** (etiqueta de envío, permanente) en la fila de notas de arriba. A partir de ese momento, cada cesta nueva que abra ese cliente empieza con ese texto ya puesto en sus instrucciones de entrega, así que el cliente ve exactamente lo que se va a imprimir y puede cambiarlo para ese pedido concreto si lo necesita. Si lo deja tal cual, eso es lo que va en la etiqueta. Los pedidos que ya estaban en la cesta antes de configurarla lo recogen al confirmar, siempre que nadie haya escrito ya una nota de etiqueta en ellos.

El texto del propio cliente siempre tiene prioridad sobre la nota permanente. Es intencionado: quien recibe el paquete es quien mejor sabe qué debe leer el repartidor ese día.

La misma fila en la ficha del cliente tiene una **Warehouse Note (Permanent)** (nota de almacén, permanente), que funciona igual para la nota de almacén, y una **Warehouse Note (Temporary)** (nota de almacén, temporal), que se usa una sola vez, solo en el siguiente pedido.

## Encontrar un pedido y comprobar su progreso

Abre una tienda y ve a **Pedidos → Pedidos** para ver todos los pedidos de esa tienda. Cada fila muestra el estado como icono, la referencia, cuándo se confirmó o envió, el cliente, el estado del pago, la información de entrega y el importe neto. Puedes filtrar la lista por estado y buscar por referencia o número de seguimiento.

Abre cualquier pedido para ver su ficha completa. La página muestra la referencia como título, con el estado actual al lado, y una fila de pestañas:

- **Transacciones** — las líneas de producto del pedido.
- **Marketing** — de dónde vino el pedido.
- **Pagos** — pagos recibidos para el pedido.
- **Facturas** — la factura generada al finalizar el pedido.
- **Albaranes** — el papeleo de almacén que lleva el pedido por picking, embalaje y envío.
- **Devoluciones** — devoluciones vinculadas al pedido.
- **Adjuntos**.
- **Correos enviados** — los correos que aiku ha mandado sobre este pedido.
- **Historial** — registro de todo lo que ha pasado con el pedido.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver todos los pedidos:</b> tu tienda → <b>Pedidos</b> (menú superior) → pestaña <b>Pedidos</b>. Filtra por estado o busca por referencia.</li>
<li><b>Comprobar el progreso de un pedido:</b> ábrelo desde la lista; el estado aparece junto a la referencia, y las pestañas <b>Albaranes</b> y <b>Facturas</b> muestran lo que ha pasado después.</li>
<li><b>Ver qué está pendiente de trabajar:</b> tu tienda → <b>Pedidos</b> (menú superior) → pestaña <b>Backlog</b>.</li>
<li><b>Cambiar una dirección en un pedido confirmado:</b> abre el pedido → <b>Editar</b> o <b>Editar dirección de facturación</b> bajo el bloque de direcciones, hasta que se envíe.</li>
<li><b>Corregir la dirección en una factura ya emitida:</b> abre el pedido → pestaña <b>Facturas</b> → abre la factura → lápiz junto a la dirección.</li>
<li><b>Cambiar lo que se imprime en la etiqueta del transportista de un pedido:</b> abre el pedido → doble clic en <b>Shipping Label (From Customer)</b> en la fila de notas → guardar. Solo se imprimen los primeros 34 caracteres.</li>
<li><b>Fijar una nota de etiqueta permanente para un cliente:</b> tu tienda → <b>Customers</b> (clientes) → abre el cliente → <b>Shipping Label (Permanent)</b> en la fila de notas de arriba. Rellena por adelantado las instrucciones de entrega de cada cesta nueva.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Necesitas acceso de lectura a Pedidos en esa tienda, o acceso de lectura a Contabilidad en la organización, para ver esta lista y sus pedidos.
</aside>
