---
title: Reunir los productos de un socio
summary: La guía del almacén - qué significa pre-pick, por qué el stock en la bahía de reunión de un socio deja de contar como disponible, y cómo trabajar la lista Pre-pick en Dispatching.
date: 2026-09-09
source_date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Para el almacén. Parte de lo que pide una organización socia no se fabrica aquí en absoluto - botellas, bolsas, cajas, palos. Nada que producir: alguien solo tiene que sacarlo de la estantería y ponerlo en la bahía de ese socio. Ese recorrido se llama <b>pre-pick</b>, la bahía es una <b>goods out gathering location</b> (ubicación de reunión de salida de mercancía), y en cuanto el stock está en ella, deja de contar como disponible para cualquier otro. Tu lista de recorridos pendientes es <b>Dispatching → Pre-pick</b>.
</aside>

## Por qué existe el pre-pick

La petición de una organización socia no se convierte en pedido de inmediato. Queda en una lista hasta que alguien de este lado actúa sobre ella, y solo se convierte en pedido, albarán y entrada de stock cuando se envía al almacén.

Eso deja un hueco. Un socio pide hoy 490 juegos de botella y tapón, no se enviarán hasta dentro de una semana, y mientras tanto nada impide que esas botellas se vendan o se usen en otro sitio. Nada en aiku retiene stock por sí solo - ni una petición, ni un pedido, ni siquiera un albarán. Lo único que reserva stock de verdad es moverlo a un sitio de donde no se pueda coger.

Para eso está una **goods out gathering location**: una ubicación normal del almacén, marcada como punto de reunión para un socio. Lo que hay en ella es suyo.

## Qué significan las dos palabras

- **Pre-pick** - sacar los productos de la estantería antes de tiempo y ponerlos en la bahía de ese socio, antes de que el pedido vaya a ninguna parte. Del lado de fábrica también significa "esto no lo vamos a fabricar, cógelo de stock".
- **Goods out gathering location** - una ubicación marcada de modo que lo que hay en ella deja de contar como disponible. El stock sigue siendo nuestro, se sigue contando, se sigue valorando, se sigue auditando. Simplemente ya tiene dueño.

## La lista de recorridos pendientes

**Warehouse → Dispatching → Pre-pick.**

Cada fila es un recorrido:

| Columna | Qué te dice |
| --- | --- |
| For | para qué organización socia son los productos |
| SKO | el código y el nombre de lo que hay que ir a buscar |
| From | la ubicación que sugiere el sistema, la que tiene más cantidad |
| To | la bahía de reunión de ese socio |
| Staged | cuánto hay ya en la bahía |
| To move | cuánto queda todavía por llevar |

Ve a buscar los productos, ponlos en la bahía y pulsa **Moved**. Eso registra el movimiento en aiku, la fila desaparece sola, y la cantidad sale de la disponibilidad.

La lista se recalcula cada vez que la abres - no es un conjunto de tareas que alguien tenga que marcar o poner en orden. Si el stock ya está en la bahía, la fila directamente no aparece. Si alguien añade más cantidad a la petición, vuelve a aparecer una fila.

La pestaña solo aparece para las organizaciones que tienen una bahía de reunión configurada para un socio. Si no la ves, es que todavía no se ha hecho.

## La mitad del mismo trabajo que le toca a la fábrica

Los recorridos salen de algún sitio: alguien en la fábrica tiene que decidir que una línea se coge de stock en vez de fabricarse. Esa decisión tiene su propia página, **Factory → Pre-pick**, y es la gemela de la lista de arriba.

Lista cada línea abierta de socio que tiene stock detrás, la fabrique o no esta fábrica, y nunca muestra una línea ya pre-recogida. Cada fila lleva el solicitante, el artefacto, lo **asked** (pedido), lo **in stock** (en stock) y lo **can pick** (se puede recoger) - los dos limitados entre sí, para que una línea nunca prometa más de lo que existe. Los filtros de categoría, solicitante y urgencia acotan la lista, y los recuentos de los filtros son los recuentos reales, no solo lo que cabe en la página.

**Pre-pick** en una fila, **Pre-pick selected** para lo que hayas marcado, o **Pre-pick all** para todo lo que muestren los filtros actuales. Pre-pick promete el stock a ese socio y pone el recorrido en la lista del almacén; cuando solo hay disponible parte de lo pedido, la línea se divide, la parte prometida se va y el resto queda abierto. No se vende nada y no se crea ningún pedido - el stock simplemente deja de estar disponible para cualquier otro.

El número junto a **Pre-pick** en la barra lateral de la fábrica es cuántas líneas están esperando esa decisión, y se actualiza solo.

## Qué ve el socio

No hay que avisarles a mano de nada. En su propia lista de la compra cada línea lleva el punto en el que va: **Requested** (Solicitado), **Being made** (En fabricación), **Pre-picked** (Pre-recogido), **Staged for you** (Reunido para ti), **Being picked** (En recogida), **On its way** (En camino) - con la orden de trabajo, el pedido o la referencia del albarán al lado.

**Staged for you** significa exactamente lo que hiciste: sus productos están en su bahía, esperando el siguiente envío.

## Cuándo se envía de verdad

Reunir no es enviar. Los productos salen cuando alguien manda el pedido recogido al almacén, en la página **To produce**, que convierte las peticiones reunidas en un pedido, un albarán y una entrada de stock en el lado del socio. Consulta [Trabajar la lista To produce](/docs/fulfilling-partner-orders-es).

Como los productos ya están en una sola bahía, la recogida en ese momento es un paseo hasta una única ubicación en vez de una vuelta por todo el almacén.

## Cosas que conviene saber

- **Una bahía de reunión no es almacenaje.** Lo que se deja en ella es invisible para todos los demás - no se ofrecerá a ningún recogedor y no aparecerá como disponible para vender. Pon stock ahí solo cuando vaya a ir a ese socio.
- **Marcar una bahía que ya tiene stock cambia los números al momento.** Si una ubicación ya tenía stock cuando se marca como punto de reunión, ese stock sale de la disponibilidad de inmediato. Comprueba qué hay en una ubicación antes de marcarla.
- **Nada más reserva.** Dos personas pueden pensar que las mismas unidades están libres hasta que una de ellas las lleva a una bahía. Si algo no se puede vender por debajo de un socio, muévelo.
- **Sacarlo de la bahía lo libera de nuevo.** Saca el stock de la bahía, o quítale la marca de reunión a la ubicación, y la cantidad vuelve a estar disponible.
- **Una bahía por socio** es lo habitual, con el nombre del socio para que un recogedor la reconozca de un vistazo.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Decidir que una línea se coge de stock:</b> tu organización → <b>Factory</b> → <b>Pre-pick</b> → <b>Pre-pick</b> en la fila, o marca y usa <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>La lista de recorridos:</b> tu organización → <b>Warehouse</b> → <b>Dispatching</b> → pestaña <b>Pre-pick</b>.</li>
<li><b>Registrar un recorrido:</b> pulsa <b>Moved</b> en la fila una vez que los productos están físicamente en la bahía.</li>
<li><b>Comprobar qué hay en una bahía:</b> <b>Warehouse</b> → <b>Locations</b> → la ubicación → pestaña <b>SKOs</b>.</li>
<li><b>Marcar una ubicación como punto de reunión:</b> <b>Warehouse</b> → <b>Locations</b> → la ubicación → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Indicarle a un socio dónde está su bahía:</b> no es una pantalla - pídeselo a un administrador, se fija a propósito desde la consola para que no se cambie por accidente.</li>
<li><b>Enviar lo reunido:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la lista y registrar un movimiento: un puesto de dispatching para el almacén, o supervisor de la organización.</li>
<li>Marcar una ubicación como punto de reunión: un puesto de almacén que pueda editar ubicaciones.</li>
<li>Indicarle a un socio dónde está su bahía: administrador, desde la consola.</li>
</ul>
</aside>
