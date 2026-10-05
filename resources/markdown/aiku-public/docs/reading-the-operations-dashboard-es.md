---
title: Cómo leer el panel de Operations
summary: La vista de almacén del panel — qué necesita atención ahora, dónde está cada albarán, a qué velocidad salen los pedidos, quién está preparando y empaquetando, qué está llegando y qué liberará. Se actualiza solo cada minuto.
date: 2026-10-05
source_date: 2026-10-05
tags: warehouse, dispatch, goods in, picking, packing, dashboard
category: warehouse
---

<aside class="tldr">
Abre <b>Dashboard</b> y elige la pestaña <b>Operations (In/Out)</b>. El personal de entrada de mercancías, salida de mercancías y fulfilment aterriza directamente en ella. Lee primero la franja de colores de arriba: cada tarjeta cuenta algo que necesita a una persona ahora, y está en gris cuando no hay nada que hacer. Debajo, el pipeline muestra dónde está cada albarán, y después la velocidad de despacho, el equipo de picking y packing, la entrada de mercancías, el stock y las devoluciones, y una pequeña tarjeta de ventas al final. Cada número es un enlace a la lista que hay detrás.
</aside>

Ábrelo en **Dashboard → Operations (In/Out)**. Si tu puesto es de entrada de mercancías, salida de mercancías o fulfilment, el panel se abre en esta pestaña. La última pestaña que elijas es la que verás la próxima vez, así que puedes volver a **Sales** o **Stock** de forma permanente si lo prefieres.

## Filtros y hora

Arriba hay dos filtros:

- **Warehouse** (almacén) — un almacén (con su organización) o todos. Solo ves los almacenes en los que trabajas.
- **Channel** (canal) — Trade, Retail, Dropshipping, Marketplace o Fulfilment client.

Tu elección se recuerda. A la derecha, cada almacén muestra su **hora local**. "Hoy", "ayer" y "mismo día" siempre se refieren al calendario del propio almacén, no a UTC. La página obtiene cifras nuevas cada minuto mientras está abierta. **Live** las obtiene ahora mismo.

Con **todos los almacenes** seleccionados, cada tarjeta muestra un código corto por almacén bajo el total (por ejemplo *ED 7 · PAR 20*). Pulsa un código para abrir la lista de ese almacén. Con un solo almacén seleccionado, el número grande es el propio enlace.

## Needs attention now

Ocho tarjetas. Una tarjeta está **gris a cero**, **ámbar** cuando hay que mirarla y **roja** solo cuando se supera un límite. El ? de cada tarjeta da la definición exacta.

- **Urgent queue not picked** (cola urgente sin preparar) — albaranes de despacho premium que nadie ha empezado a preparar. Roja cuando el más antiguo lleva esperando más de una hora.
- **At risk of missing collection** (en riesgo de perder la recogida) — necesita los horarios de recogida de cada transportista por almacén. Todavía no están configurados, así que la tarjeta muestra un guion.
- **Blocked orders** (pedidos bloqueados) — albaranes cuyo picking se ha detenido, divididos por motivo: **Stock** (un artículo está esperando al almacén) o **CS** (un artículo está esperando a atención al cliente). Ámbar por encima de 10, roja cuando uno lleva bloqueado más de 24 horas.
- **CS waiting for decision** (CS esperando decisión) — albaranes en picking o bloqueados con un artículo esperando a atención al cliente, con la antigüedad del más viejo. Roja pasadas 24 horas. Abre la lista de artículos esperando a atención al cliente.
- **Out of stock on open orders** (sin stock en pedidos abiertos) — SKO sin stock en ninguna ubicación que aún hay que preparar en un albarán abierto, y cuántos albaranes retienen.
- **Replenishment needed** (reposición necesaria) — ubicaciones de picking por debajo de su mínimo mientras otra ubicación aún tiene stock.
- **Overdue deliveries** (entregas atrasadas) — entregas de stock entrantes aún no llegadas cuya fecha prevista ya ha pasado. La fecha prevista es la propia fecha de la entrega, o la del pedido de compra si la entrega no tiene.
- **Stock errors** (errores de stock) — ubicaciones con stock negativo.

## Order pipeline

Cada albarán abierto, por etapa:

- **To assign** — en el almacén, sin asignar a un preparador.
- **Queued** — asignado a un preparador, sin empezar.
- **Picking** — en preparación.
- **Blocked** — picking detenido (ver arriba).
- **Packing** — preparado, o en proceso de empaquetado.
- **Packed** — empaquetado, aún sin facturar ni finalizar.
- **Waiting for dispatch** — facturado y listo, esperando al transportista.
- **Dispatched today**.

Bajo cada cifra ves cuánto tiempo lleva el **más antiguo** en esa etapa. Si puedes ver las cifras de ventas, también ves el valor del pedido. Las reposiciones (replacements) también se cuentan y se muestran por separado, porque son trabajo de almacén sin valor de pedido.

Esta pestaña cuenta **albaranes**, que es lo que trabaja el almacén. La pestaña Sales cuenta **pedidos**, así que difieren por las reposiciones y por los pedidos con más de un albarán. La pestaña **Stock** muestra solo los niveles de stock; el trabajo del almacén está aquí.

**Why the delivery notes are waiting** (por qué esperan los albaranes) divide los que están por asignar o en cola según el stock que hay ahora mismo en las estanterías: **pickable now** (está todo), **partly pickable** (parcialmente preparable) o **awaiting stock** (no hay nada). Otros pedidos que piden el mismo stock no se descuentan, así que interpreta "pickable" como "merece la pena mandar a un preparador".

## Next collections y dispatched today

La cuenta atrás del transportista necesita horarios de recogida por transportista y almacén. Hasta que se configuren, esta tarjeta lo indica.

**Dispatched today, by this time** compara hoy con ayer y con el mismo día de la semana pasada, cada uno contado solo hasta la hora actual. Muestra albaranes, bultos y líneas.

## Time to dispatch

El tiempo desde que un albarán llega al almacén hasta su despacho, solo para pedidos (no reposiciones). Elige **Today**, **Last 7 days** o **Last 30 days**.

- **Median** (mediana) y **90th percentile** (percentil 90). Con todos los almacenes seleccionados, el percentil 90 es el del almacén más lento.
- **Same day** — el porcentaje despachado el mismo día natural en que llegó.
- **Within SLA** — necesita el nivel de servicio por canal y cliente de fulfilment, que aún no está definido.

**Open delivery notes by age** muestra todo lo que sigue abierto en cuatro tramos: menos de 4 horas, de 4 a 24 horas, de 1 a 2 días y más de 2 días.

## Pickers and packers

Solo cifras de equipo. Las cifras por persona esperan la aprobación de RR. HH. en Eslovaquia y España.

- **Pickers** (preparadores) — cada línea preparada queda registrada con su preparador y la hora, así que la tarjeta cuenta las líneas preparadas hoy, **por persona y hora** (las horas de cada persona van desde su primer hasta su último picking), la última hora y los **short picks** (líneas marcadas como no preparadas).
- **Packers** (empaquetadores) — albaranes empaquetados hoy, por persona y hora, y la última hora.

**Active now** significa que alguien registró trabajo en los últimos 15 minutos. **Idle** significa que alguien trabajó en la última hora pero no en los últimos 15 minutos.

## Goods in

Recuentos de entregas **en camino**, **atrasadas**, **por registrar** (llegadas o revisadas) y **en registro**. Después viene **dock to stock** (del muelle al stock), la mediana y el percentil 90 del tiempo desde la llegada hasta el registro en los últimos 90 días, y cuántas entregas abiertas tienen **no ETA** (sin fecha prevista) o **no purchase order** (sin pedido de compra).

La tabla lista las entregas abiertas con las atrasadas primero. **Releases** es el número de albaranes abiertos que esperan stock y que la entrega trae. Ubica primero la entrega que libera más pedidos. Pulsa un proveedor para abrir la entrega.

## Stock and locations

**Empty locations** (ubicaciones vacías) sobre el total de ubicaciones, ubicaciones con stock **no contado en 90 días**, **negative stock** (stock negativo) y **replenishments due** (reposiciones pendientes). Debajo, **Out of stock with open orders** lista los SKO que retienen más albaranes, con la fecha prevista de la entrega entrante si hay una en camino.

La capacidad de las ubicaciones (cuán llena está una zona) necesita capacidades en las ubicaciones, que aún no están registradas.

## Returns

**To process** — devoluciones recibidas en el almacén que aún no se han procesado, con la más antigua. **Customer returns received** y esperadas. Después las unidades procesadas este mes (repuestas en stock, dañadas, no devueltas) y los motivos de devolución más comunes.

## Sales by organisation

Una tarjeta plegable al final con una fila por organización, como señal de carga de trabajo:

- **Orders in today** con los últimos siete días como una pequeña línea de barras.
- **vs same weekday LY** — frente al mismo día de la semana del año pasado, hasta la misma hora.
- El número se pone **rojo** cuando hoy está más de un 25% por encima de la media de los últimos cuatro mismos días de la semana: señal de que hacen falta más manos.

Si puedes ver las cifras de ventas, también ves **value in today**, **in warehouse pipeline**, **month to date** (facturado) y **% of month target**, cada organización en su propia moneda, con una fila de grupo en libras.
