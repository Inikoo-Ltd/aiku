---
title: Por qué aiku pide una dirección completa
summary: Qué cuenta como dirección completa, por qué un pedido sin ella espera antes del almacén, cómo liberarlo y qué hacer cuando no se puede contactar con el cliente.
date: 2026-09-11
source_date: 2026-09-11
tags: orders, crm, invoices, addresses, accounting
category: orders
help_routes: grp.org.shops.show.crm.customers, grp.org.accounting.invoices
---

<aside class="tldr">
En todos los sitios donde una persona escribe una dirección, aiku pide ahora las partes que ese país usa realmente. Si un cliente no tiene dirección en su cuenta, su pedido se cobra con normalidad pero <b>espera antes del almacén</b> en lugar de prepararse: no sale nada y no se emite ninguna factura con la dirección en blanco. Ponga la dirección que falta y el pedido continúa solo. Si no se puede contactar con el cliente, hay un botón para enviarlo igualmente.
</aside>

## Qué cuenta como dirección completa

Una dirección no es lo mismo en todos los países, así que aiku pide lo que ese país usa y nada más:

- **Reino Unido** — calle, ciudad y código postal.
- **Irlanda** — calle y ciudad. No se pide código postal, las direcciones irlandesas no lo usan en este formato.
- **España e Italia** — calle, ciudad, provincia y código postal.
- **Emiratos Árabes Unidos** — calle y emirato. Sin ciudad.

Sale de la misma lista de países que decide qué casillas aparecen en el formulario, así que las casillas que ve son las que hay que rellenar. Las casillas aparecen además en el orden en que ese país escribe una dirección — para el Reino Unido, primero la calle, luego la ciudad y luego el código postal. Un simple **0** no cuenta como dirección; antes se aceptaba y es lo que se imprimía como ceros en la documentación.

Se le pedirá una dirección completa allí donde la escriba una persona: al registrarse en la web de una tienda, cuando un cliente edita sus datos, al añadir una dirección de entrega, al editar un cliente, un pedido o una factura en la oficina, y en las pantallas de tienda, proveedor, agente, almacén y empresa.

Los pedidos que llegan solos desde un canal de venta — Shopify, eBay, Amazon, TikTok y los demás — **nunca** se rechazan por una dirección incompleta. Su cobro ya se ha hecho en otro sitio, así que rechazarlos perdería el pedido. Lo mismo vale para las importaciones nocturnas de datos.

## Qué pasa cuando un cliente no tiene dirección

Algunas cuentas se crearon antes de que esto se pidiera, así que no tienen ninguna. Cuando una de ellas hace un pedido:

1. El cliente paga con normalidad. El pago nunca se rechaza por falta de dirección.
2. El pedido se envía con normalidad.
3. **Se detiene antes del almacén.** No se crea albarán, así que no hay nada que preparar ni empaquetar.
4. Se queda en la lista de **enviados** con un aviso en su nota de almacén que indica qué dirección falta — casi siempre la de **facturación**.

Un pedido detenido puede mostrar una dirección de **entrega** perfectamente correcta. En la mayoría de tiendas son dos direcciones distintas: la de entrega es adonde va el paquete, la de facturación pertenece a la cuenta del cliente. Si el aviso dice que falta la de facturación, esa es la que hay que arreglar, muestre lo que muestre la de entrega.

No se pierde nada y a nadie se le cobra algo que no va a recibir: el pedido simplemente espera a que alguien resuelva la dirección.

## Liberar un pedido detenido

**Cuando falta la dirección de facturación: póngala en el cliente.** Abra el cliente, añada su dirección, y cada pedido suyo detenido por falta de dirección de facturación la recoge y continúa al almacén por sí solo. Es lo preferible, porque además arregla todos los pedidos futuros de esa cuenta.

**Cuando falta la dirección de entrega, o para un solo pedido: póngala en el pedido.** Abra el pedido y edite la dirección que indica el aviso. En un pedido de dropshipping la dirección de entrega es la del cliente final, así que solo se puede arreglar en el pedido. En cuanto el pedido tiene lo que necesita, va al almacén solo.

Si pulsa **Send to warehouse** mientras falta una dirección, aiku se lo dice en lugar de no hacer nada.

## Cuando no se puede contactar con el cliente

A veces no hay respuesta y la mercancía tiene que salir. Un pedido detenido tiene un botón **Send anyway, no address**. Hace lo que dice: el pedido va al almacén y se prepara, empaqueta y expide con normalidad, y se añade una línea a su nota de almacén dejando constancia de que se envió deliberadamente sin dirección.

Úselo como último recurso, por lo que viene después: la factura de ese pedido mostrará solo el país en el recuadro de la dirección. Ese documento ya no se puede cambiar una vez emitido, así que merece la pena un intento más de localizar al cliente.

## Por qué importa

Antes, una cuenta sin dirección producía una factura con ceros donde debía ir la dirección, y pasaba varias veces al día, todos los días. El cliente recibe un documento que parece roto y nadie se entera salvo que lo mire. La dirección es además a la que se factura, así que una vacía es un hueco real en la documentación, no un detalle estético.

<aside class="wayfinder">

### Dónde hacer clic en aiku

- **Ver pedidos que esperan** — la lista de pedidos de la organización, **Submitted**. Uno detenido lleva un aviso en su nota de almacén.
- **Añadir una dirección a un cliente** — **CRM → Customers**, abra el cliente, edite la dirección. Eso libera sus pedidos detenidos por falta de dirección de facturación.
- **Arreglar un pedido, o una dirección de entrega que falta** — abra el pedido y edite la dirección que indica el aviso.
- **Enviarlo sin dirección** — abra el pedido detenido y use **Send anyway, no address**.
- **Corregir la dirección de una factura** — abra la factura y haga clic en el lápiz junto a la dirección.

### Permisos que necesita

- Editar clientes y pedidos forma parte del trabajo normal de atención al cliente en esa tienda.
- El lápiz de la dirección de una factura es para los **accounting supervisors** de esa organización.
- **Send anyway, no address** necesita el mismo permiso que enviar cualquier pedido al almacén.

</aside>
