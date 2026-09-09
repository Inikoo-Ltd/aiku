---
title: Hornadas, packs y hornadas parciales
summary: La fábrica fabrica unidades en hornadas completas, el almacén cuenta packs. Qué hacen packed_in y el tamaño de hornada a una orden de trabajo, a lo que llega al estante y a lo que debería pedir un socio.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
Dos recuentos se encuentran en cada artefacto y no son el mismo recuento. La planta trabaja en <b>units</b> - una bomba de baño, una pastilla de jabón - y solo puede fabricar razonablemente una <b>batch</b> (hornada) completa de ellas, porque eso es lo que cabe en la mezcladora. El almacén y la tienda trabajan en <b>SKOs</b>: el pack en el que se vende y se almacena la mercancía, diez por caja. <b>Packed in</b> es el único puente entre los dos, y ahora aiku lo cruza en los dos extremos en vez de fingir que los números son intercambiables.
</aside>

## Los tres números

- **Batch size** — cuántas unidades fabrica la fábrica de una vez. Pertenece al artefacto, se fija en su página o en bloque desde la lista de artefactos, ver [Cambiar varios artefactos a la vez](/docs/changing-many-artefacts-at-once-es).
- **Packed in** — cuántas unidades van en un SKO. Pertenece al SKO en el almacén.
- **Units y SKOs** — a la planta se le pide en unidades, todo lo demás se cuenta en SKOs.

## Qué pasa en cada extremo

**Levantar un trabajo.** Lo que se pidió — una línea de socio, una falta de un cliente propio, una línea de reposición — es una cantidad en SKOs. aiku la multiplica por *packed in* para obtener unidades, y luego redondea **hacia arriba** a la siguiente hornada completa. Pide 1 SKO de un pack de diez fabricado en hornadas de 16 y al artesano se le pide 16 unidades, ni 1 ni 10.

**Recibir el trabajo terminado.** Lo que ha fabricado el artesano son unidades, y se divide por *packed in* camino del estante. Esas 16 unidades de un pack de diez llegan como 1,6 SKOs: una caja sellada y seis sueltas. La cifra del estante es honesta sobre el resto en lugar de redondearlo y hacerlo desaparecer.

## Cuando una hornada no llena packs completos

Una hornada de 16 y una caja de 10 nunca salen exactas. Eso no es un error y aiku no lo trata como tal: la fábrica dimensiona una hornada para la mezcladora, la tienda vende en packs, y las dos cosas son correctas. Simplemente conviene saber dónde pasa, así que se mide:

- una columna **Batch in SKOs** y un filtro **Batch not whole SKOs** en la lista de artefactos;
- una línea en la página del artefacto que muestra la hornada en SKOs y el tamaño de hornada más cercano que saldría exacto;
- una estadística en el panel de crafts que cuenta los artefactos donde esto pasa.

Nada te obliga a cambiar un tamaño de hornada. Si la sugerencia es fácil de aceptar, acéptala y la aritmética deja de dejar restos. Si es la mezcladora la que decide la hornada, déjala como está.

## Qué debería pedir un socio

La misma aritmética decide la cantidad de pedido más limpia, a la que aiku llama el **order step**: el número más pequeño de SKOs que llenan hornadas completas exactamente. Para una hornada de 16 unidades en packs de 10 son 8 SKOs — ochenta unidades, cinco hornadas, sin resto.

Del lado del socio, en la [lista de la compra](/docs/buying-from-a-partner-es) y en la lista de stock:

- la línea dice *made in batches of N units* y, cuando difieren, *full batches every N SKO*;
- un pequeño botón redondea la cantidad hacia arriba hasta el siguiente step;
- las cantidades **suggested** y todo lo que propone Auto-fill ya están en el step;
- un pedido fuera del step se sigue aceptando, con una nota de que se fabrica una hornada completa de todos modos, así que el pedido puede retrasarse o la cantidad ajustarse.

Un pedido por debajo de un step completo no se puede fabricar solo, de ninguna manera. En el tablero [To produce](/docs/fulfilling-partner-orders-es) espera detrás de *lines too small for a batch are waiting for company* hasta que se junte suficiente demanda, un pedido de cliente propio hace que el trabajo se ejecute de todos modos, o el planificador decide fabricarlo igualmente.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Fijar un tamaño de hornada:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → abre el artefacto, o marca varios y fíjalo desde la barra de selección.</li>
<li><b>Encontrar los incómodos:</b> la lista de artefactos → filtro <b>Batch not whole SKOs</b>, o la columna <b>Batch in SKOs</b>.</li>
<li><b>Ver la sugerencia:</b> la página del artefacto, bajo el tamaño de hornada.</li>
<li><b>Fijar packed in:</b> <b>Warehouse → Inventory</b> → abre el SKO → <b>Edit SKO</b>.</li>
<li><b>Pedir en el step:</b> lista de la compra del socio → el botón junto a <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Tamaño de hornada y vida útil en artefactos: el derecho de <b>research and development</b> de la fábrica, o supervisor de la organización.</li>
<li>Packed in en un SKO: un puesto de almacén que pueda editar inventario.</li>
</ul>
</aside>
