---
title: Pre-pick
summary: La guía de la fábrica para Factory → Pre-pick - qué líneas de socios llegan ahí, qué significa cada estado, y qué hacer cuando el stock está, cuando solo hay una parte, cuando viene más en camino y cuando no va a llegar nunca.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Las líneas que hay que fabricar van a <b>To produce</b> (Por producir); las que ya tienes en la estantería llegan a <b>Pre-pick</b>. Para cada línea de Pre-pick:
<ul>
<li><b>Tienes todo el stock:</b> no hagas nada. aiku la envía sola al almacén en diez minutos. Si el stock la cubre justo, sin que sobre nada, espera: pulsa <b>Pre-pick</b>.</li>
<li><b>Tienes una parte, y viene más en camino de un proveedor:</b> espera. Cuando la entrega se dé de alta en stock, aiku envía la línea entera sola. O pulsa <b>Pre-pick</b> para enviar ahora lo que tienes; el resto va a <b>To produce</b>.</li>
<li><b>Tienes una parte, y es algo que fabricas:</b> esperar no sirve de nada, porque nadie recibe el encargo de fabricar el resto. Pulsa <b>Pre-pick</b> para enviar lo que tienes; el resto va a <b>To produce</b> para fabricarse.</li>
<li><b>No va a llegar (descatalogado, no disponible):</b> pulsa <b>Close short</b> (Cerrar corta) para enviar lo que hay y cancelar el resto; el socio recibe un aviso. Si no hay nada en stock, la línea está en <b>To produce</b>: mira el Caso 3 más abajo.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Dibujo a acuarela: una línea de la lista de la compra de un socio llega a un poste con dos flechas, una hacia To produce, un banco de trabajo donde se fabrican las cosas, y otra hacia Pre-pick, una estantería con stock. De la estantería, lo que está todo en stock va solo a la bahía del socio en diez minutos, lo que está en stock en parte va a la bahía mientras el resto espera a un camión de entrega, y lo que no va a llegar recibe un sello rojo de Close short con una nota que vuelve volando al socio." width="1200" height="750" loading="eager"><figcaption>En la estantería: Pre-pick. No en la estantería: To produce.</figcaption></figure>

## Por qué una línea llega aquí y no a To produce

El pedido de un socio llega como líneas de una lista de la compra. Para cada línea, aiku mira tu **free stock** (stock libre): lo que hay en la estantería menos lo que ya está prometido a otras líneas de socios.

- **El stock libre no cubre nada** - la línea va a **To produce**, para fabricarse. Consulta [Trabajar la lista To produce](/docs/fulfilling-partner-orders-es).
- **El stock libre cubre una parte o todo** - la línea llega a **Factory → Pre-pick**.

Así que un mismo pedido puede repartirse entre las dos páginas: el té que mezclas va a To produce, las botellas, bolsas y cajas que ya tienes llegan aquí. Una línea cuyo stock se acaba más tarde pasa sola a To produce.

Las líneas se atienden en cola, por producto: primero **urgent** (urgente), luego **high** (alta), **normal** y **low** (baja); con la misma prioridad, la que se necesita antes, y después la más antigua. Una línea solo cuenta con el stock que queda después de todas las líneas que van por delante.

## Cómo leer la lista

| Columna | Qué te dice |
| --- | --- |
| For | qué organización socia lo pidió |
| Artefact | el código y el nombre del producto |
| To | la bahía de reunión de ese socio, adonde irán los productos |
| Asked | cuánto pidió el socio |
| In stock | lo que hay ahora en tus estanterías |
| Can pick | lo que puedes dar ahora a esta línea, después de las líneas que van por delante en la cola |
| Shortfall | lo que falta para cubrir la línea entera |
| Status | lo que aiku va a hacer con ella, ver abajo |
| Priority, Added | el lugar de la línea en la cola |

Los filtros de categoría, solicitante y urgencia acotan la lista; los números de los filtros son los recuentos reales.

## Qué significa cada estado

| Estado | Qué significa | Qué haces |
| --- | --- | --- |
| **Going to the warehouse** (Va al almacén) | el stock cubre toda la línea y sobra algo | nada - en diez minutos aiku la pre-recoge sola y sale de la lista |
| **Held, one more needed on the shelf** (Retenida, falta uno más en la estantería) | el stock cubre la línea justo, sin que sobre nada | nada, o pulsa **Pre-pick** si debe salir ya; aiku solo envía una línea por sí misma cuando al menos una unidad se queda en la estantería |
| **Awaiting full stock, production manager to decide** (Esperando stock completo, decide el responsable de producción) | solo hay una parte de la línea en stock | decide, ver los casos de abajo |
| **In To produce** (En To produce) | hay stock, pero las líneas que van por delante de esta lo han tomado | nada aquí; la línea espera en la página To produce |

Pre-recoger no es enviar. Promete el stock a ese socio y pone un recorrido en la lista del almacén: sacan los productos de la estantería y los ponen en la bahía del socio. Los productos salen cuando sale el siguiente envío del socio, según un horario o cuando alguien pulsa **Send to warehouse** (Enviar al almacén). Consulta [Reunir los productos de un socio](/docs/gathering-a-partners-goods-es).

## Caso 1: tienes todo el stock

No hagas nada. La línea muestra **Going to the warehouse** y aiku la pre-recoge en diez minutos. Si muestra **Held, one more needed on the shelf** y no debe esperar, pulsa **Pre-pick** en la fila.

Para enviar varias líneas a la vez, márcalas y pulsa **Pre-pick selected** (Pre-pick de las marcadas), o **Pre-pick all** (Pre-pick de todas) para todo lo que muestren los filtros actuales.

## Caso 2: tienes una parte del stock, o viene más en camino

La línea muestra **Awaiting full stock, production manager to decide**. Tienes tres opciones:

- **Esperar al resto.** No hagas nada. Cuando llegan los productos que has pedido a un proveedor y se dan de alta en stock, el stock libre crece; en cuanto cubre la línea entera y al menos una unidad se queda en la estantería, aiku la pre-recoge sola. Elige esto cuando la entrega está cerca y el socio prefiere recibirlo todo de una vez. Esperar solo funciona cuando de verdad viene stock en camino: si es un producto que fabricas, nadie recibe el encargo de fabricar el resto mientras la línea espera aquí, así que esperaría para siempre.
- **Enviar ahora lo que tienes, y el resto después.** Pulsa **Pre-pick** (la cantidad aparece como *partial pick*, recogida parcial). La parte que hay en stock va a la bahía; el resto queda en la lista del socio como una línea propia, todavía abierta, y pasa a **To produce**. Si el producto es de los que fabricas, aparece ahí en el tablero, para fabricarse; cualquier otro aparece en la pestaña **All** (Todas), y vuelve aquí cuando llegue stock nuevo.
- **Enviar lo que tienes y dejarlo ahí.** Pulsa **Close short**. aiku te pide confirmar, envía lo que hay en stock y cancela el resto. La lista del socio baja a la cantidad enviada, y a quien añadió la línea y al equipo de compras del socio se les avisa de que se cerró corta, para que puedan comprarlo en otro sitio.

## Caso 3: el stock no va a llegar

El producto está descatalogado, el proveedor ya no lo tiene, o no estará disponible durante mucho tiempo.

- **Hay una parte en stock** - pulsa **Close short**: el socio recibe lo que hay y se le avisa de que el resto no llegará.
- **No hay nada en stock, y lo fabricas** - la línea es una tarjeta en el tablero de **To produce**. Arrástrala fuera de **Backlog** (Pendientes) (a **Preparing** (En preparación), o a **Producing** (En producción) si tu fábrica usa el tablero sencillo) y, en la ventana que se abre, pulsa **Can't be done** (No se puede hacer) y da el motivo. La línea sale del tablero, y a quien la añadió y al equipo de compras del socio se les avisa, con tu motivo, para que puedan comprarlo en otro sitio.
- **No hay nada en stock, y no lo fabricas** - la línea está en **To produce → All**, pero allí todavía no hay botón **Can't be done**. Pide al socio que borre la línea de su lista de la compra, o abre un ticket.

No dejes esas líneas esperando sin avisar: el socio las ve como **Requested** (Solicitado) en su lista y sigue contando con ellas.

## Qué ve el socio

En su propia lista de la compra cada línea dice en qué punto va: **Requested** (Solicitado), **Being made** (En fabricación), **Pre-picked** (Pre-recogido), **Staged for you** (Reunido para ti), **Being picked** (En recogida), **On its way** (En camino).

Cuando una línea se cierra corta o no se puede hacer, se avisa a las personas del lado del socio, con el motivo.

## Cosas que conviene saber

- **Nada reserva stock hasta que se pre-recoge.** Una línea que dice *Awaiting full stock* no ha apartado nada: su stock todavía puede venderse o darse a otra línea. Si no debe tocarse, pre-recoge lo que hay.
- **Si la estantería resulta tener menos** - una auditoría encuentra menos, o se vendió en otro sitio - aiku retira la promesa empezando por los pre-picks más recientes, y esas líneas vuelven a la lista o a To produce.
- **La lista se calcula de nuevo cada vez que la abres.** Las líneas salen solas una vez pre-recogidas; no hay nada que ordenar.
- **El número junto a Pre-pick** en la barra lateral de la fábrica es cuántas líneas están esperando, y se actualiza solo.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>La lista:</b> tu organización → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Enviar una línea ya:</b> <b>Pre-pick</b> en la fila, o marca filas y pulsa <b>Pre-pick selected</b>, o <b>Pre-pick all</b>.</li>
<li><b>Enviar lo que hay y cancelar el resto:</b> <b>Close short</b> en la fila.</li>
<li><b>Una línea que fabricas y no se puede hacer:</b> <b>Factory</b> → <b>To produce</b> → arrastra la tarjeta fuera de <b>Backlog</b> → <b>Can't be done</b>.</li>
<li><b>Líneas sin stock que no fabricas:</b> <b>Factory</b> → <b>To produce</b> → pestaña <b>All</b>.</li>
<li><b>Recorridos a la bahía:</b> <b>Warehouse</b> → <b>Dispatching</b> → pestaña <b>Pre-pick</b>, consulta <a href="/docs/gathering-a-partners-goods-es">Reunir los productos de un socio</a>.</li>
<li><b>Enviar lo reunido:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, o deja que lo haga el horario de envíos del socio.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la lista: cualquier puesto de fábrica que pueda ver producción, o supervisor de la organización.</li>
<li>Pre-recoger y cerrar corta: un puesto de fábrica que planifique o prepare la producción, un editor de compras, o supervisor de la organización.</li>
<li><b>Can't be done</b>: un puesto de fábrica que planifique o prepare la producción, o supervisor de la organización. Consulta <a href="/docs/factory-positions-es">Puestos de la fábrica</a>.</li>
</ul>
</aside>
