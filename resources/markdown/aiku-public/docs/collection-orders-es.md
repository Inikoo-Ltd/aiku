---
title: Pedidos para recoger
summary: Qué cambia cuando un cliente recoge el pedido en lugar de recibirlo por transporte, qué aparece en la factura y dónde corregir una dirección que falta o es incorrecta.
date: 2026-09-10
source_date: 2026-09-10
tags: orders, invoices, collection, accounting, crm
category: orders
help_routes: grp.org.accounting.invoices
---

<aside class="tldr">
Un <b>pedido para recoger</b> es el que el cliente recoge en persona. No lleva gastos de transporte ni dirección de entrega; en su lugar lleva la dirección donde se recoge y, si la tienda cobra por la recogida, un cargo de recogida. En la factura esa dirección ocupa el lugar de la dirección de entrega, bajo el título <b>Collection address</b>. Si el cliente no tiene ninguna dirección en su cuenta, el pedido se detiene antes del almacén hasta que alguien la añada.
</aside>

## Qué hace que un pedido sea para recoger

Un pedido normal se envía: tiene dirección de entrega, se cobra con gastos de transporte y el albarán sale con un transportista. Un pedido para recoger no tiene nada de eso. La mercancía se sigue preparando y empaquetando igual, pero el cliente viene a nosotros, así que no hay línea de transporte en el pedido ni dirección de entrega que imprimir.

Lo que el pedido lleva en su lugar es una **dirección de recogida** — el sitio donde el cliente recoge. Viene de la tienda, así que todos los pedidos para recoger de esa tienda apuntan al mismo sitio salvo que se haya indicado otra cosa.

Si la tienda tiene activo un cargo de tipo **Collection** en sus billables, ese cargo se añade al pedido en lugar del transporte. Aparece como una línea propia, sigue el importe configurado en el cargo y se retira solo si el pedido deja de ser para recoger. Una tienda sin ese cargo simplemente no lleva nada ahí.

## Qué muestra la factura

Una factura de recogida tiene los mismos dos recuadros que cualquier otra factura, pero el de la derecha cambia:

- **Billing address** — viene de la cuenta del cliente. Es la dirección a la que se factura y la que cuenta a efectos de impuestos.
- **Collection address** — la dirección desde la que se recogió la mercancía. Sustituye al recuadro de la dirección de entrega.

Las líneas vacías nunca se imprimen, así que una dirección incompleta muestra solo lo que tiene.

Una factura es un documento fijo. La dirección de recogida se guarda en ella en el momento de emitirla, así que cambiar después la dirección de recogida de la tienda **no** reescribe las facturas ya existentes: siguen mostrando la dirección que se aplicaba cuando se recogió la mercancía. Las facturas emitidas antes de que esto se guardara no tienen nada que mostrar, así que su recuadro pone simplemente **Collection**.

## Cuando el cliente no tiene dirección

Una cuenta puede quedarse sin ninguna dirección. Cuando pasa eso, el pedido **se detiene antes de llegar al almacén**: no se crea albarán, el pedido se queda en la lista de enviados y se añade un aviso a su nota de almacén pidiendo la dirección. No se prepara nada ni se factura nada hasta que se resuelve.

La solución es poner la dirección en el **cliente**, no en el pedido. Abra el cliente, añada su dirección y el pedido continúa al almacén por sí solo. Añadirla al cliente además hace que su próximo pedido salga bien sin que nadie tenga que acordarse.

Quien crea un pedido desde la oficina no puede enviarlo sin dirección de facturación. Un cliente que pide desde la web sí puede llegar a la pasarela de pago sin ella: su pago se procesa con normalidad y es la retención antes del almacén la que lo detecta, de modo que a nadie se le cobra y después se le rechaza el pedido.

## Corregir una dirección

Son dos tareas distintas y se hacen en dos pantallas distintas.

**Para todas las facturas futuras** — cambie la dirección de recogida de la tienda. Abra la tienda, vaya a **Settings** y edite el campo **Collection address**. A partir de ahí, las facturas de recogida de esa tienda se emiten con la nueva dirección. Las facturas existentes no se tocan, que es de lo que se trata.

**Para una factura ya emitida** — abra la factura y haga clic en el lápiz de su recuadro de dirección. Eso edita solo la dirección de **facturación**, la que viene de la cuenta del cliente. La dirección de recogida no se escribe a mano; viene de la tienda.

<aside class="wayfinder">

### Dónde hacer clic en aiku

- **Ver si un pedido es para recoger** — abra el pedido; un pedido para recoger muestra una dirección de recogida en lugar de la de entrega y no lleva línea de transporte.
- **Definir cuánto cuesta la recogida** — abra la tienda, luego **Billables → Charges**, y use el cargo de tipo **Collection**. Mientras esté activo, todos los pedidos para recoger de esa tienda lo aplican.
- **Encontrar un pedido detenido** — la lista de pedidos de la organización, grupo **Submitted**. El motivo está en la nota de almacén del pedido.
- **Añadir una dirección a un cliente** — abra el cliente desde **CRM → Customers** y edite su dirección.
- **Cambiar desde dónde recogen los clientes** — abra la tienda, luego **Settings**, y edite **Collection address**.
- **Corregir la dirección de facturación de una factura** — abra la factura, haga clic en el lápiz junto a la dirección, edite y **Save**.

### Permisos que necesita

- Editar los ajustes de una tienda, incluida la dirección de recogida, requiere **organisation admin** o **shop admin**.
- El lápiz junto a la dirección de una factura solo aparece para los **accounting supervisors** de esa organización.
- Añadir una dirección a un cliente forma parte del trabajo normal de atención al cliente en esa tienda.

</aside>
