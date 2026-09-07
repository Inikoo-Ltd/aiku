---
title: Facturas, pagos y reembolsos
summary: Encuentra cualquier factura, comprueba si está pagada, registra un pago contra ella y entiende cómo se genera un reembolso y a dónde va el dinero.
date: 2026-09-01
source_date: 2026-09-01
tags: accounting, invoices, payments, refunds
category: accounting
---

<aside class="tldr">
Cada venta acaba en una <b>factura</b>. La pantalla <b>Accounting → Invoices</b> de la organización las lista todas, muestra si cada una está pagada, y te deja abrir una para ver sus líneas, sus pagos y cualquier reembolso contra ella. El dinero que entra se registra como un <b>payment</b> contra una <b>payment account</b>; el dinero que sale se registra como un <b>refund</b>, que es en sí mismo un tipo especial de factura.
</aside>

## La lista de facturas

Abre tu organización y ve a **Accounting → Invoices**. Cada fila muestra la **Reference** de la factura, el **Customer** al que pertenece, la **Date**, su estado de **Payment**, y los importes **Net** y **Total**. Puedes buscar, ordenar por cualquiera de estas columnas y filtrar entre fechas.

Una tienda tiene su propia vista de la misma información: dentro del dashboard de una tienda, en **Invoices**, encuentras las facturas de la tienda, con listas separadas para facturas **paid** y **unpaid**, más una para facturas que se han borrado desde entonces.

La lista también tiene una pestaña **Invoices** y una pestaña **Refunds**, así que puedes pasar directamente al lado de los reembolsos sin salir de la pantalla.

## Abrir una factura

Pulsa una referencia para abrir la factura. En la parte superior encuentras sus pestañas:

- **Transactions** — las líneas que componen la factura: la mercancía, los cargos, el envío, etc.
- **Payments** — todos los pagos que se han tomado contra esta factura.
- **Refunds** — cualquier reembolso generado desde esta factura.
- **Email** — los correos que aiku ha enviado sobre esta factura.
- **History** — un historial de qué ha pasado con la factura y cuándo.
- **Attachments** — cualquier archivo adjunto a ella.

Desde aquí también puedes descargar la factura como **PDF**, y, si tu organización activa esta opción, descargarla en el formato **Omega** que se usa para algunas exportaciones contables.

## Tipos de factura

Una factura siempre es de uno de dos tipos: un **Invoice** normal, o un **Refund**. Un reembolso no es un tipo de registro aparte: es una factura cuyo tipo está fijado a Refund, enlazada a la factura original que corrige. Abrir un reembolso desde la lista de facturas te lleva directamente a su propia página de reembolso, en lugar de a una página de factura normal.

## Estado de pago

Cada factura lleva un estado de pago que puedes ver de un vistazo en la columna **Payment**:

- **Unpaid** — no se ha pagado nada, o no lo suficiente, todavía.
- **Paid** — la factura está saldada.
- **Unknown payment status** — se usa solo para facturas muy antiguas (de más de tres años) que no tienen ningún historial de pagos, así que aiku de verdad no puede saberlo con certeza.

## Registrar un pago

Los pagos viven en su propia área de **Payments** dentro de **Accounting**, y también se pueden iniciar desde la **payment account** de un cliente. Crear un pago (**New payment**) pide una referencia, el cliente y los detalles del pago, y siempre se hace contra una payment account concreta.

Al guardar un pago, aiku averigua cómo se pagó: si el pago llegó con detalles de tarjeta, wallet o scheme, aiku registra el wallet o el tipo de pago como el **method** y el scheme de la tarjeta como el **sub method**; si no, recurre al tipo de la propia payment account. Un pago correcto queda enlazado a la factura a través de la pestaña **Payments** de la propia factura, y una lista de pagos —ya estés mirando toda la organización, una tienda, una payment account o una sola factura— siempre muestra el **Status**, la **Reference**, la **Payment Account**, el **Type**, el **Method**, el **Amount** y la **Date** del pago.

## Payment accounts y payment service providers

Una **payment account** es de donde realmente se toma o a donde entra un pago: pertenece a un **payment service provider**, la empresa que procesa el pago (por ejemplo una pasarela de tarjetas). Cada payment service provider que tu organización ha conectado tiene su propia página que lista sus payment accounts, y abrir una cuenta lista los pagos y las tiendas que la usan.

## Reembolsos

Un reembolso se genera desde la factura que corrige y comparte su referencia con un sufijo `-refund-` (a menos que los ajustes de tu tienda activen una secuencia de numeración de reembolsos separada). Cuando se crea un reembolso, empieza en cero y queda marcado como **in process** mientras se va construyendo; sus importes solo se dan por definitivos cuando el reembolso se completa.

Un reembolso puede devolver el dinero de formas distintas, ofrecidas como dos opciones al enviarlo:

- **Refund money to customer's credit balance** — el importe se añade al saldo a favor del cliente en lugar de devolverse a una tarjeta o cuenta.
- **Refund money to payment method of the invoice** — el importe se reembolsa contra un pago original concreto, a través de la payment account de la que se tomó originalmente.

Cuando un reembolso se procesa online a través de tu proveedor de pago con tarjeta, aiku espera a que el proveedor confirme que el reembolso realmente se ha completado antes de actualizar el **total refund** del pago original y marcarlo como reembolsado; si el proveedor no confirma el éxito, el reembolso no se acepta.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver todas las facturas:</b> tu organización → <b>Accounting → Invoices</b>. Cambia entre las pestañas <b>Invoices</b> y <b>Refunds</b> en la parte superior.</li>
<li><b>Ver las facturas de una tienda:</b> el dashboard de la tienda → <b>Invoices</b> → facturas paid, unpaid o deleted.</li>
<li><b>Abrir una factura:</b> pulsa su referencia para ver sus pestañas Transactions, Payments, Refunds, Email, History y Attachments.</li>
<li><b>Registrar un pago:</b> tu organización → <b>Accounting → Payments</b> → <b>New payment</b> (o desde la pestaña Payments de una payment account).</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Necesitas permiso para ver la contabilidad de la organización para que la sección <b>Accounting</b> aparezca en tu navegación. Crear o editar un pago o una payment account necesita permiso para editar la contabilidad de la organización.
</aside>
