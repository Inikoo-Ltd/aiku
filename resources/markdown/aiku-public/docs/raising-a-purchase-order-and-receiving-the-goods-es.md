---
title: Cursar una orden de compra y recibir la mercancía
summary: Compra a un proveedor ordinario - cursa la orden de compra, consigue que se confirme, y luego convierte la entrega en stock que puedas vender.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, purchase orders, stock deliveries, suppliers
category: procurement
---

<aside class="tldr">
Cuando compras a un proveedor ordinario - no a una organización socia, que tiene su propia guía - el trabajo se hace en dos etapas. Primero cursas una <b>orden de compra</b> (purchase order) y consigues que el proveedor la confirme. Luego, cuando llega la mercancía, registras una <b>entrega de stock</b> (stock delivery) contra esa orden y la vas comprobando hasta que el stock queda colocado en tus estanterías. Este artículo cubre ambas partes, además de lo que hace realmente cada botón de estado por el camino.
</aside>

## Proveedores y agentes

Cada proveedor al que tu organización compra directamente vive en **Procurement → Suppliers**. La página de cada proveedor te da un botón **Purchase Order** para iniciar una orden nueva, más un menú lateral con **Products**, **Purchase Orders** y **Stock Deliveries** hasta la fecha.

Algunos proveedores solo son accesibles a través de un **agente** - una persona o empresa que compra en tu nombre en lugar de enviarte directamente. Los agentes tienen su propia lista en **Procurement → Agents**, y funcionan de la misma manera: las órdenes de compra y entregas contra un agente se registran en la página del agente en lugar de en la del proveedor.

## Cursar una orden de compra

Desde la página del proveedor, pulsa **Purchase Order**. Esto crea una orden nueva en el estado **In process** - existe, pero todavía no se ha enviado nada al proveedor.

Mientras está en proceso:

- Usa **Add Product** para añadir una línea por cada producto que quieras, uno a uno.
- Cada línea puede ajustarse mientras la orden siga en proceso.
- **Delete** elimina la orden entera, siempre que todavía no se haya enviado nada al proveedor.

Cuando hayas añadido todo lo que quieres, pulsa **Submit**. Esto envía la orden y la pasa a **Submitted**.

## Qué significan los estados

Una orden de compra pasa por una cadena corta y deliberada:

- **In process** - todavía estás construyendo la orden. Añade productos, envíala o elimínala.
- **Submitted** - la orden ha ido al proveedor. Puedes **Confirm**arla en cuanto el proveedor la haya aceptado, **Undo Submit** para devolverla a In process si algo necesita cambiarse, o **Cancel**arla del todo.
- **Confirmed** - el proveedor ha aceptado la orden. Puedes fijar o cambiar la **Delivery date** (la llegada estimada), y pulsar **New Delivery** para crear la entrega de stock que recibirá la mercancía. Mientras no exista una entrega para ella, también puedes hacer **Undo Confirm** para devolverla a Submitted.

A partir de aquí la orden se asienta sola según avanzan sus entregas de stock - no queda nada más que pulsar en la propia orden de compra. Termina **Settled** cuando todo ha llegado, o **Not Received**/**Cancelled** si la cosa no salió bien.

## La entrega de stock: registrar lo que llegó

Pulsar **New Delivery** en una orden de compra confirmada crea la entrega de stock por ti, ya vinculada a las líneas de esa orden. También puedes empezar una desde cero en **Procurement → Stock Deliveries**, que solo pide un **número** y una **fecha** de entrega.

La página de una entrega de stock tiene pestañas para sus **Items**, los **Pending Items** todavía por resolver, **Done Items**, **Attachments** e **History**.

La entrega pasa después por sus propios estados:

- **In process / Confirmed / Ready to ship** - mientras todavía está en camino, puedes pulsar **Mark as Dispatched** en cuanto el proveedor la haya enviado, **Mark as Received** si ya ha llegado, o **Delete** si se creó por error.
- **Dispatched** - el paquete está en la carretera. **Mark as Received** en cuanto llegue a tu almacén, o **Unmark as Dispatched** para deshacerlo si en realidad todavía no ha salido.
- **Received** - la mercancía está físicamente en el almacén. Desde aquí comprueba cada artículo frente a lo que se pidió; la entrega pasa a **Checked** cuando eso está hecho, o puedes hacer **Unmark as Received** o **Cancel** de la entrega entera.
- **Checked** - si todavía no se ha colocado nada en stock, aquí todavía puedes **Cancel**ar.
- **Booking in / Booked in** - las cantidades comprobadas se están dando de alta en el stock del almacén.
- **Booked in** - pulsa **Place** para colocar el stock recibido. Este es el estado final de trabajo de la entrega.

Comprobar un artículo significa confirmar cuánto de cada línea llegó realmente - no todos los pedidos llegan completos, y las cantidades de menos o de más aparecen en la pestaña **Under/Over delivered items**, así que nada se pierde en la diferencia entre lo que pediste y lo que llegó.

## Poniéndolo todo junto

En resumen: cursa la orden contra el proveedor, envíala, espera a que el proveedor la confirme, y luego crea la entrega desde la orden confirmada. Marca la entrega como enviada cuando el proveedor la despache, como recibida cuando llegue, ve comprobando cada artículo y, por último, colócala - momento en el que el stock ya está en el almacén y listo para vender.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Encontrar un proveedor o agente:</b> tu organización → <b>Procurement → Suppliers</b> (o <b>Agents</b> para proveedores gestionados por un agente).</li>
<li><b>Iniciar una orden de compra:</b> en la página del proveedor, pulsa <b>Purchase Order</b>; añade líneas con <b>Add Product</b>, y luego <b>Submit</b> cuando esté lista.</li>
<li><b>Avanzarla:</b> en la página de la orden, usa <b>Confirm</b>, <b>Undo Submit</b> o <b>Cancel</b> mientras está enviada; una vez confirmada, fija la <b>Delivery date</b> y pulsa <b>New Delivery</b>.</li>
<li><b>Recibir la mercancía:</b> en la página de la entrega de stock, avanza por <b>Mark as Dispatched → Mark as Received</b>, comprueba la pestaña <b>Items</b>, y luego <b>Place</b> en cuanto esté dada de alta.</li>
<li>También puedes iniciar una entrega desde cero en <b>Procurement → Stock Deliveries</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Necesitas permiso para ver procurement en la organización para consultar órdenes de compra y entregas de stock, y permiso para editar procurement para cursarlas, enviarlas, confirmarlas o modificarlas de cualquier otro modo.
</aside>
