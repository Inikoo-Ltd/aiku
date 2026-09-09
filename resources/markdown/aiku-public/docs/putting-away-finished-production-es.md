---
title: Guardar la producción terminada
summary: La guía del almacén - dónde aparecen las órdenes de trabajo terminadas, cómo calcula aiku si van a la bahía de un socio o a stock normal, y cómo registrarlas con un solo código.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Para el almacén. Cuando los artesanos terminan una orden de trabajo, no se convierte en stock por sí sola - alguien tiene que llevarla a algún sitio y decir dónde. Esa lista es <b>Dispatching → From production</b> (Desde producción). Cada fila te dice para quién son los productos y sugiere la ubicación: la bahía de reunión de un socio cuando todo el trabajo es para un solo socio, o si no, una ubicación de stock que escribes tú. Pulsa <b>Put away</b> (Guardar) y los productos quedan registrados en esa ubicación con un código de lote.
</aside>

## De dónde salen las filas

Una orden de trabajo aparece aquí en cuanto todas sus tareas se marcan DONE en la planta, y se queda hasta que se guarda. Nadie tiene que enviártela.

Las órdenes de trabajo que aún se están haciendo no están aquí. Si necesitas ver lo que se acerca, el tablero <b>To produce</b> (Por producir) de la fábrica tiene una columna <b>Done</b> (Terminado) con las mismas órdenes de trabajo - ver [Trabajar la lista To produce](/docs/fulfilling-partner-orders-es).

## Leer una fila

| Columna | Qué te dice |
| --- | --- |
| Job order | la referencia, JOxxx-0001 |
| Artisan | quién lo fabricó |
| Made | cuántos de qué - 20 × SKO-01, una línea por producto |
| For | el código de la organización socia, o *Stock* |
| To location | la ubicación a la que debe ir |

**For** se calcula a partir de las peticiones detrás de la orden de trabajo. Si todas las líneas las pidió la misma organización socia, los productos son suyos y la fila lo indica, con la bahía de reunión de ese socio ya rellenada en <b>To location</b>. Es la misma bahía que usa la [lista de pre-pick](/docs/gathering-a-partners-goods-es): lo que hay en ella está reservado y deja de contar como disponible para cualquier otro.

Si la orden de trabajo se hizo para stock, para un cliente propio, o para más de un socio, la fila dice *Stock* y la casilla de ubicación queda vacía. Escribe el código de la ubicación donde lo estás guardando.

## Guardarlo

1. Lleva los productos a la ubicación indicada, o a la que hayas elegido.
2. Comprueba el código en <b>To location</b>. Cámbialo si lo pusiste en otro sitio.
3. Pulsa <b>Put away</b> (Guardar).

Eso registra los productos en la ubicación, les da un código de lote hecho con la referencia de la orden de trabajo y el código del producto, descuenta las materias primas que la receta dice que se usaron, y marca la orden de trabajo como recibida. La fila desaparece, y en el tablero de la fábrica la línea sale de la columna <b>Done</b>.

La cantidad registrada es lo que los artesanos realmente hicieron, no lo que se pidió. Una orden de trabajo que pedía 25 y consiguió 19 registra 19.

## Qué pasa después

- **Los productos de un socio** quedan en su bahía hasta que alguien en la página <b>To produce</b> envía el pedido recogido al almacén. Recogerlo es entonces un paseo hasta una sola bahía. El socio ve la línea como *Staged for you* (Reunido para ti) en su lista de la compra.
- **Los productos de un cliente propio** pasan a stock normal y el albarán en espera se libera para recogida, ya que la falta de stock que lo retenía ha desaparecido.
- **El stock** simplemente queda disponible.

## Cosas que conviene saber

- **La pestaña solo aparece** en las organizaciones que tienen una fábrica.
- **Una orden de trabajo, una ubicación.** Si una orden de trabajo realmente hay que repartirla entre dos sitios, guárdala en stock y deja que la lista de pre-pick mueva la parte del socio.
- **Una ubicación equivocada se corrige como cualquier otro error de stock** - mueve el stock entre ubicaciones. La orden de trabajo en sí no se reabre.
- **Nada aquí queda reservado hasta que está en una bahía.** Los productos guardados en stock normal se pueden recoger para cualquiera.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>La lista:</b> tu organización → <b>Warehouse</b> → <b>Dispatching</b> → pestaña <b>From production</b>.</li>
<li><b>Registrarlo:</b> comprueba <b>To location</b> → <b>Put away</b>.</li>
<li><b>Ver qué hay en una bahía:</b> <b>Warehouse</b> → <b>Locations</b> → la ubicación → pestaña <b>SKOs</b>.</li>
<li><b>Lo que la fábrica todavía debe:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la lista y guardar: un puesto de dispatching para el almacén, o supervisor de la organización.</li>
</ul>
</aside>
