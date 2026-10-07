---
title: Enviar un reemplazo
summary: Vuelve a enviar artículos de un pedido expedido, da a cada uno un motivo que registra quién tuvo la culpa, y deja una nota para el almacén.
date: 2026-10-07
source_date: 2026-10-07
tags: dispatch, replacements, customers
category: dispatch
help_routes: grp.org.shops.show.ordering.orders.show.replacement.create, grp.org.shops.show.crm.customers.show.replacements.index
---

<aside class="tldr">
Cuando algo de un pedido expedido llega roto, se pierde o es incorrecto, envías al cliente un <em>reemplazo</em>: una nueva nota de entrega solo con los artículos que se vuelven a enviar. Cada artículo de un reemplazo necesita un motivo, y cada motivo queda registrado contra quien causó el problema — el transportista, el almacén, el proveedor o el cliente — de modo que hay un registro de productos defectuosos y de errores del almacén.
</aside>

## Iniciar un reemplazo

Abre el pedido. Cuando está **Dispatched**, aparece un botón **Replacement** en el encabezado de la página. Púlsalo para abrir la pantalla de reemplazo, que enumera todos los artículos que salieron en la nota de entrega del pedido.

## Elegir qué volver a enviar

Cada línea muestra la **Quantity Dispatched** y una casilla **Quantity Resend**. Escribe cuántas unidades de ese artículo se envían de nuevo — no puede ser más de lo que se expidió. **Replace All** rellena cada línea con la cantidad expedida completa cuando hay que volver a enviar el pedido entero.

Las líneas que quedan en cero no forman parte del reemplazo.

## Cada artículo se compensa una sola vez

Un artículo que llegó roto, que falta o que es incorrecto se compensa una sola vez: se envía de nuevo, se añade gratis al siguiente pedido del cliente o se reembolsa, o una mezcla de ellos, pero nunca más que la propia línea del pedido. Los reembolsos parciales cuentan por la parte del precio reembolsada: tras reembolsar el 20% de una línea, queda el 80% por reclamar.

Si pides más de lo que queda, **Save** se rechaza con un mensaje que nombra los artículos ya reemplazados, reembolsados o en espera en el siguiente pedido. Una vez que un reemplazo ha salido del almacén, sus artículos se pueden volver a reclamar, por si también llegaron rotos.

Las reclamaciones se aceptan durante 60 días después de que se expidió el pedido. Pasado ese plazo, el reemplazo se rechaza.

## Dar un motivo para cada artículo

Cada línea con cantidad a reenviar necesita un **Reason**. **Save** permanece desactivado hasta que todas esas líneas tengan uno.

| Motivo | Registrado contra |
|---|---|
| Damaged by courier | Transportista |
| Lost by courier | Transportista |
| Wrong item sent | Almacén |
| Missing from parcel | Almacén |
| Broken, poor packaging | Almacén |
| Faulty product | Proveedor |
| Customer error | Cliente |
| Other | No se sabe |

Elige el motivo que corresponde a lo que pasó de verdad: así se cuentan los fallos más adelante, no es solo una nota para este pedido.

## Dejar una nota para el almacén

Encima de los artículos hay una casilla **Note to warehouse**. Úsala para lo que los preparadores y empaquetadores deban hacer distinto esta vez — revisar el artículo antes de empaquetarlo, añadir embalaje extra, incluir algo que faltaba. La nota se guarda en el nuevo reemplazo. Déjala vacía para conservar la nota de almacén del propio pedido.

## Después de guardar

Al pulsar **Save** se crea la nota de entrega del reemplazo, que llega al almacén como cualquier otra nota (consulta [Preparar y empaquetar una nota de entrega](/docs/picking-and-packing-a-delivery-note-es)). El motivo de cada artículo se muestra junto a su nombre en la página de la nota de entrega del reemplazo y en la pantalla de preparación, así el almacén sabe por qué sale de nuevo.

Los reemplazos de un cliente se listan en su página dentro del **CRM** de la tienda. Los reemplazos creados antes de que existieran los motivos no muestran ninguno.

## Reclamaciones desde el chat

Cuando un cliente escribe por un artículo dañado o que falta, el panel del cliente junto a la conversación muestra una casilla **Claim** con las líneas de su pedido. Si dio un número de pedido, se usa ese pedido; si no, se usa su último pedido expedido. Cuando se refiere a un pedido anterior, elígelo en la lista **Order** de la parte superior de la casilla, que muestra sus pedidos expedidos en los últimos 60 días.

Marca las líneas y envíalas de nuevo con **Create replacement**, ponlas gratis en su siguiente pedido con **Add to next order**, o reembólsalas a su saldo con **Refund to balance**. Una línea ya compensada en parte muestra cuánto **left** queda, y una línea sin nada pendiente no se puede marcar.
