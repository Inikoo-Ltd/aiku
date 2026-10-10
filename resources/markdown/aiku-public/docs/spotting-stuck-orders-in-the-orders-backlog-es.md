---
title: Detectar pedidos atascados en el backlog de pedidos
summary: El backlog de pedidos indica ahora qué pedidos llevan demasiado tiempo en la misma fase, cuáles tienen líneas en las que el preparador espera stock y quién tiene cada pedido, sin depender de que el almacén avise.
date: 2026-10-10
source_date: 2026-10-10
tags: orders, backlog, picking, out of stock, customer service, stuck orders
category: crm
help_routes: grp.org.shops.show.ordering.backlog
---

<aside class="tldr">
Abre <b>Orders → Backlog</b>. Las cuatro cajas de arriba son los pedidos que necesitan a una persona: <b>Stuck</b> (más de 2 días laborables en la misma fase), <b>Waiting for stock</b> (el preparador no encontró una línea y no se avisó a atención al cliente), <b>With customer service</b> (líneas que te han enviado para decidir) y <b>Waiting longest</b>. Haz clic en el nombre de una fase dentro de una caja para abrir justo esos pedidos. En cada lista, <b>In this stage</b> muestra cuánto lleva el pedido en la fase, los más antiguos primero y en rojo si está atascado, <b>Progress</b> muestra cuánto se ha preparado o empaquetado, y <b>Picker</b> / <b>Packer</b> muestran quién tiene el pedido.
</aside>

Se abre en **tu tienda → Orders → Backlog**. No necesitas acceso a las pantallas de Goods out del almacén: todo lo siguiente está en el backlog de la tienda.

## Las cajas de arriba

- **Stuck** — pedidos que llevan más de 2 días laborables en la misma fase. Debajo del total hay una etiqueta por fase con los atascados; pasa el ratón para ver la antigüedad del más viejo y haz clic para abrir la lista.
- **Waiting for stock** — pedidos con al menos una línea que el preparador no encontró y dejó a la espera de que el almacén reponga. Estas líneas **no** se envían a atención al cliente, así que hasta ahora nadie fuera del almacén las veía. Si una línea lleva mucho aquí, pregunta al almacén si el stock llega o si deben enviártela.
- **With customer service** — pedidos con líneas que el preparador te ha enviado. **Handle the waiting lines** abre la lista donde eliges Don't pick, Replace o Send back.
- **Waiting longest** — los cinco pedidos que llevan más tiempo atascados, con su fase. Haz clic en la referencia para abrir el pedido.

Cuando no hay nada atascado ni líneas en espera, una sola línea verde lo indica.

Los pedidos enviados pero sin pagar esperan al cliente, por eso no cuentan como atascados.

## En cada lista

Haz clic en un número de las cajas de fases para abrir esa fase. Solo se resalta la fase elegida, y una línea encima de la lista explica qué significa, por ejemplo *Ready to be picked — Sent to the warehouse, picking has not started*.

Las columnas de la izquierda describen el pedido; las de la derecha, cómo avanza por el almacén.

- **Reference, Customer, Net, Submitted** — el pedido. El icono pequeño delante del importe neto es el estado del pago; pasa el ratón para ver el detalle.
- **In this stage** — cuánto lleva el pedido en su fase actual. Las listas se abren con **los más antiguos primero**. Una etiqueta roja significa atascado. Pasa el ratón para ver la fecha exacta.
- **Progress** — una barra, un porcentaje y el número de líneas entre paréntesis. Hasta Picked es el avance de la preparación; en Packing, cuántas de las líneas preparadas están empaquetadas. Una etiqueta ámbar al lado indica líneas que esperan stock o están con atención al cliente. Packed y Waiting for dispatch no tienen barra, porque el trabajo está hecho.
- **Picker / Packer** — quién tiene el pedido. En *Ready to be picked* la columna Picker está vacía: todavía no se ha asignado a nadie.
- **Delivery** — desde Packed: el transportista y, debajo en letra pequeña, el número de seguimiento completo.

Un pedido en Picking sin nada en Progress ni en Picker no tiene albarán abierto: nadie está trabajando en él. Abre el pedido y consúltalo con el almacén.

## Filtrar

Junto a Destination y Channel están ahora **Payment** (Paid, Unpaid) y **Needs attention** (Stuck, Waiting for stock, With customer service). Cada uno muestra su número para la fase en la que estás. Haz clic para ver solo esos pedidos y otra vez para quitar el filtro.
