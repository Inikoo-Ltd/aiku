---
title: Reempaquetar un albarán finalizado
summary: Qué ocurre cuando un albarán finalizado se desempaqueta y se vuelve a empaquetar, por qué vuelve directo a finalizado, y qué hacer cuando aiku se niega porque lo preparado ya no coincide con la factura.
date: 2026-09-15
source_date: 2026-09-15
tags: dispatch, packing, invoices, accounting
category: dispatch
---

<aside class="tldr">
<b>Almacén:</b> aún puede <b>Unpack</b> (desempaquetar) un albarán finalizado para corregir algo. Al volver a empaquetarlo vuelve directo a <b>Finalised</b>, y también su pedido. Después pulse <b>Dispatch</b> como siempre.<br>
<b>Si aiku se niega a empaquetar:</b> las cantidades preparadas ya no coinciden con las de la factura. Vuelva a dejarlas como en la factura, o pida a contabilidad que reembolse la diferencia, y empaquete de nuevo.
</aside>

## Por qué un albarán finalizado es especial

Finalizar un albarán hace dos cosas a la vez: marca el albarán como listo para salir y crea la **factura** del pedido a partir de lo que se preparó. Desde ese momento la factura es el registro de lo que el cliente está pagando.

Un albarán finalizado se puede volver atrás con **Unpack**, por ejemplo para abrir una caja y revisar o corregir su contenido. El albarán y su pedido vuelven a **Packing**. La factura queda exactamente igual.

## Volver a empaquetarlo

Cuando el albarán se empaqueta de nuevo, con **Set as packed** o escaneando el último artículo, no se detiene en **Packed**. Como su pedido ya tiene factura, el albarán y el pedido vuelven directo a **Finalised**. La factura, los importes del pedido y cualquier envío ya registrado quedan sin tocar.

A partir de ahí el albarán muestra su botón **Dispatch**, y se continúa como con cualquier albarán finalizado.

## Cuando aiku se niega a empaquetar

Antes de devolver el albarán a finalizado, aiku revisa cada línea: la cantidad preparada ahora debe coincidir con la de la factura. Si una línea se preparó de forma distinta mientras el albarán estaba desempaquetado, el empaquetado se rechaza con un mensaje que indica la factura y enlaza a esta página. El albarán se queda en **Packing**; nada más cambia.

Volver a finalizado en ese caso enviaría al cliente algo distinto de lo facturado, así que antes debe pasar una de estas cosas:

- **Lo preparado fue un error.** Retroceda el albarán (**Undo packing**, luego **Undo set as picked**), vuelva a preparar las líneas con las cantidades facturadas, y empaquete.
- **Realmente sale menos mercancía.** Pida a contabilidad que emita un **reembolso** (refund) en la factura por lo que no se va a enviar. Una vez aplicado el reembolso, empaquete el albarán otra vez. Vea [Facturas, pagos y reembolsos](/docs/invoices-payments-and-refunds-es).
- **Realmente sale más mercancía.** No lo añada a este albarán. Prepare aquí las cantidades facturadas y pida a atención al cliente que ponga la mercancía adicional en un pedido nuevo.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Encontrar el albarán:</b> su almacén → <b>Dispatching → Delivery notes</b> → pestaña <b>Packing</b> (tras desempaquetar) o pestaña <b>Finalised</b> (tras volver a empaquetar).</li>
<li><b>Desempaquetar y volver a empaquetar:</b> abra el albarán → <b>Unpack</b>, y luego <b>Set as packed</b> cuando la caja esté lista. Después <b>Dispatch</b>.</li>
<li><b>Retroceder para volver a preparar:</b> en un albarán en <b>Packing</b>, <b>Undo packing</b>; en uno en <b>Picked</b>, <b>Undo set as picked</b>.</li>
<li><b>Revisar la factura:</b> su organización → <b>Accounting → Invoices</b> → abra la factura indicada en el mensaje → pestañas <b>Transactions</b> y <b>Refunds</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permisos que necesita</strong>
Desempaquetar y empaquetar requieren acceso de dispatching para el almacén. El reembolso lo emite el personal de contabilidad con acceso a las facturas de la organización.
</aside>
