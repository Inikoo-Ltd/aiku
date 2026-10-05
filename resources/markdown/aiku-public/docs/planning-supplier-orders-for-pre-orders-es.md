---
title: Planificar pedidos a proveedores para pedidos anticipados
summary: Cómo ve el equipo de compras todos los pedidos anticipados que siguen esperando mercancía, agrupados por proveedor, los compara con el pedido mínimo y la fecha límite de pedido del proveedor, y marca el pedido al proveedor como realizado o cancela los pedidos anticipados.
date: 2026-09-28
source_date: 2026-09-28
tags: procurement, orders
category: procurement
series: Pre-orders
---

<aside class="tldr">
<b>Procurement › Pre-orders</b> lista todos los pedidos anticipados de clientes que siguen esperando mercancía, agrupados por el proveedor de lo que esperan. Para cada proveedor ves cuántos pedidos anticipados hay, la cantidad, su valor de venta y su coste aproximado en el proveedor, junto al <b>pedido mínimo</b> y la <b>fecha límite de pedido</b> del proveedor. Cuando haces el pedido al proveedor, márcalo con <b>Mark supplier ordered</b>. Si no se puede alcanzar el mínimo a tiempo, decides si pedir de todos modos o cancelarlos con <b>Cancel all, full refund</b>.
</aside>

## Qué muestra la página

Los clientes pueden hacer un pedido anticipado de productos marcados como **back-order** o **made-to-order** (ver [Vender pedidos anticipados](/docs/selling-pre-orders-es)). A algunos proveedores se les pide en cada pedido de cliente; a otros solo cuando se reúnen suficientes pedidos anticipados para completar un envío. Esta página sirve para agruparlos.

Cada proveedor es una fila, con los proveedores cuya fecha límite de pedido llega antes arriba del todo:

- **Pre-orders** — cuántos pedidos de clientes esperan a este proveedor.
- **Quantity** (cantidad) — cuánto de sus SKO está esperando, en unidades de SKO.
- **Sales value** (valor de venta) — por cuánto se venden los artículos que esperan, en la moneda de la organización.
- **At supplier cost (approx.)** (coste aproximado en el proveedor) — la cantidad en espera al precio actual del proveedor, en la moneda del proveedor. Al lado está el **minimum** (mínimo) del proveedor, en verde cuando se alcanza y en rojo cuando no.
- **Order by** (pedir antes de) — la fecha en la que debería hacerse el pedido al proveedor.

Haz clic en un proveedor para ver cada línea en espera: el pedido, la tienda, el cliente, el producto y el SKO, la cantidad, cuándo se pidió, cuándo se hizo el pedido al proveedor y la fecha límite de envío.

Un pedido anticipado espera al **preferred supplier** (proveedor preferido) de cada uno de sus SKO. Cuando un pedido espera a dos proveedores, aparece bajo los dos.

## Fijar el mínimo y la fecha límite de pedido

Los dos están en el proveedor: **Minimum order** (pedido mínimo) en sus ajustes de compra, y **Order by date** y el **pre-order lead time** en **Pre-orders**. El plazo de entrega es lo que se le dice al cliente que espere, así que mantenlo realista: es el tiempo desde que el cliente pide hasta que nosotros enviamos.

## Hacer el pedido al proveedor

Cuando ya has pedido al proveedor, pulsa **Mark supplier ordered** en la fila de ese proveedor. Marca todos los pedidos anticipados del grupo que todavía no estén marcados.

Esto le importa al cliente: un cliente trade puede cancelar un artículo made-to-order gratis **hasta que se hace el pedido al proveedor**. A partir de ahí, cancelar retiene su depósito. Márcalo el día en que pides, no antes.

## Cuando no se alcanza el mínimo

Si los pedidos anticipados no alcanzan el mínimo del proveedor antes de la fecha límite de pedido, el equipo de compras decide:

- **Order anyway** (pedir de todos modos) — hacer el pedido al proveedor y marcarlo como se ha explicado.
- **Cancel them** (cancelarlos) — pulsa **Cancel all, full refund**. Se cancelan todos los pedidos anticipados del grupo y a cada cliente se le devuelve todo, depósito incluido, a su saldo de cuenta. Se les envía un correo.

También puedes cancelar un solo pedido anticipado desde su página de pedido.

## Cuando llega la mercancía

Aquí no tienes que hacer nada: cuando se registra la entrada del stock, los pedidos anticipados que lo esperan lo toman, empezando por el más antiguo, y salen de esta página. A los clientes se les pide su saldo pendiente, y cada pedido va al almacén en cuanto se paga.

<aside class="wayfinder">

### Dónde hacer clic en aiku

- **Abrir la página** — **Procurement**, y luego la tarjeta **Pre-orders** del panel.
- **Ver las líneas de un proveedor** — haz clic en la fila del proveedor.
- **Marcar el pedido al proveedor como realizado** — **Mark supplier ordered** en la fila del proveedor.
- **Cancelar los pedidos anticipados de un proveedor** — **Cancel all, full refund** en la fila del proveedor.
- **Fijar el mínimo, la fecha límite de pedido y el plazo de entrega** — abre el proveedor, **Edit**: **Minimum order**, y luego **Pre-orders**.

### Permisos que necesitas

- Ver la página requiere permiso para ver Procurement en la organización.
- **Mark supplier ordered** y **Cancel all, full refund** requieren permiso para editar Procurement.

</aside>
</content>
