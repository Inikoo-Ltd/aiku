---
title: Enviar un reemplazo
summary: Vuelve a enviar artículos de un pedido expedido, da a cada uno un motivo que registra quién tuvo la culpa, y deja una nota para el almacén.
date: 2026-09-16
source_date: 2026-09-16
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
