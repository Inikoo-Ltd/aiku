---
title: Picking y packing de un albarán
summary: Sigue un albarán desde que llega al almacén hasta el picking, el packing y la expedición, y descubre qué hace realmente cada botón del albarán.
date: 2026-09-01
source_date: 2026-09-01
tags: dispatch, picking, packing
category: dispatch
---

<aside class="tldr">
Un <em>albarán</em> (delivery note) es la copia del almacén de un pedido: la lista de lo que tiene que salir, y el registro de lo que realmente salió. Pasa por una serie fija de etapas — to do, picking, picked, packing, packed, finalised, dispatched — y cada cambio de etapa es un botón que pulsas cuando el trabajo detrás de él ya está hecho. Este artículo sigue un albarán durante todo el recorrido y muestra dónde encontrarlo en cada etapa.
</aside>

## Dónde viven los albaranes

Dentro de tu almacén, **Dispatching → Delivery notes** lista todos los albaranes, con una pestaña **Stats** y una **History** junto a la lista principal. La lista en sí se divide en pestañas de etapa a un lado: **Dispatched**, **All**, **To do**, **Queued**, **Handling**, **Waiting**, **Picked**, **Packing**, **Packed** y **Finalised**. Cada pestaña muestra solo los albaranes que están en esa etapa, con un contador junto a su nombre, así que de un vistazo ves cuánto hay en cada sitio.

La etapa de un albarán se llama su estado. En orden, un albarán pasa por:

- **To do** — todavía no ha empezado nada.
- **Queued** — está dentro de una sesión de picking, esperando a que esa sesión arranque.
- **Handling** — se está haciendo el picking.
- **Waiting** — el picking se ha detenido porque algo del albarán necesita una decisión.
- **Picked** — todas las líneas están pickeadas.
- **Packing** — se está empaquetando.
- **Packed** — todas las líneas están empaquetadas.
- **Finalised** — listo para salir, con los datos de envío ya fijados.
- **Dispatched** — expedido.
- **Cancelled** — el albarán se ha cancelado.

## Picking

Un albarán se vuelve pickeable por sí solo, desde la pestaña **To do**, o como parte de una **sesión de picking** — un lote de albaranes que se pickean juntos. Las sesiones de picking tienen su propia pantalla, en **Dispatching → Picking sessions**, con el mismo tipo de pestañas de etapa: **In Process**, **Picking**, **Waiting**, **Picked**, **Packed** y **All**.

Al pulsar **Start picking** en un albarán, este pasa a **Handling** y se registra quién lo está pickeando. Si el albarán estaba en cola dentro de una sesión, al arrancar el picking de la sesión todos sus albaranes pasan a **Handling** de la misma manera, y quien inició la sesión se convierte en el picker de cada uno. Un albarán ya asignado a otra persona muestra un candado cerrado en lugar del botón de picking — pulsa **Unlock to pick** para hacerte cargo de él.

Mientras se pickea un albarán, una línea puede resultar necesitar una decisión que el picker no puede tomar en el puesto — por ejemplo un reemplazo o una liberación desde el almacén. Cuando eso ocurre, todo el albarán pasa a **Waiting** en lugar de dejar que el picking continúe alrededor del problema. En cuanto ya no queda nada realmente pendiente, aparece un botón **Auto Finish Waiting**, y al pulsarlo se revisa el albarán y, si de verdad todas las líneas están resueltas, lo lleva a **Picked**.

## De picked a packing

Una vez pickeadas todas las líneas de un albarán, este queda en **Picked** con un botón **Start packing**. En la mayoría de tiendas este es un paso aparte: al pulsarlo, el albarán pasa a **Packing**, se registra quién lo empaqueta, y se libera cualquier puesto de picking que lo estuviera reteniendo. Para tiendas de dropshipping se salta el packing — desde **Picked** el botón dice **Set as packed** en su lugar, y lleva el albarán directamente a **Packed** en un solo paso.

Durante el packing, el albarán no puede marcarse como **Set as packed** si todavía tiene líneas pendientes de una decisión de reemplazo o de una liberación de almacén — ese bloqueo hay que resolverlo antes.

Al pulsar **Set as packed** se registra quién empaquetó el albarán, se incorporan las líneas que no se confirmaron una a una en el puesto, y se fija un paquete por defecto si todavía no se había registrado ninguno.

Si un albarán necesita retroceder un paso, los albaranes editables llevan botones de deshacer: **Undo set as picked** devuelve un albarán **Picked** a picking, **Undo packing** devuelve un albarán **Packing** a picked, y **Unpack** devuelve un albarán **Packed** o **Finalised** a **Packing**.

## Finalizar y expedir con un transportista

Una vez que un albarán **Packed** tiene sus paquetes registrados, lleva un único botón **Finalise and Dispatch** (la etiqueta cambia a **Dispatch** o a **Finalise and set as Collected** para un albarán de reemplazo o uno que se recoge en persona en lugar de ir con un transportista). Al pulsarlo se finaliza el albarán — lo cual se rechaza si no hay ningún envío registrado — y se expide en el mismo paso: se marca el albarán como expedido, se registra la hora de expedición en cada línea y, para albaranes ligados a un pedido de cliente, se lleva también el propio pedido a expedido.

Un albarán expedido puede revertirse con **Undispatch**, que lo devuelve a packed.

## Cancelar un albarán

Un albarán puede cancelarse desde cualquier etapa antes de finalizarse o expedirse — cancelar un albarán finalizado o expedido se rechaza. Cancelar libera de vuelta al stock todo lo que ya se hubiera pickeado o empaquetado, marca todas las líneas del albarán como canceladas, y lo desvincula de cualquier carro o puesto de picking que estuviera usando. Cuando el albarán pertenece a un pedido de cliente, el propio pedido también se revierte, salvo que ya esté cancelado, finalizado o expedido.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver albaranes por etapa:</b> tu almacén → <b>Dispatching → Delivery notes</b>, luego elige una pestaña de etapa — <b>To do</b>, <b>Queued</b>, <b>Handling</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>, <b>Finalised</b>, <b>Dispatched</b> o <b>All</b>.</li>
<li><b>Trabajar sesiones de picking:</b> tu almacén → <b>Dispatching → Picking sessions</b> → pestañas de etapa <b>In Process</b>, <b>Picking</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packed</b>.</li>
<li><b>Avanzar un albarán:</b> abre el albarán y usa su botón de etapa — <b>Start picking</b>, <b>Auto Finish Waiting</b>, <b>Start packing</b> / <b>Set as packed</b>, <b>Finalise and Dispatch</b>, <b>Dispatch</b>. Los botones de deshacer (<b>Undo set as picked</b>, <b>Undo packing</b>, <b>Unpack</b>, <b>Undispatch</b>) lo devuelven atrás.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permisos que necesitas</strong>
Para ver los albaranes de un almacén necesitas acceso de visualización de dispatching o de fulfilment para ese almacén. Cancelar un albarán requiere además ser supervisor de dispatching, administrador de la organización, o tener acceso de edición a los pedidos o al CRM de la tienda.
</aside>
