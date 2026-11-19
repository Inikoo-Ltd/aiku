---
title: Notificaciones de WhatsApp a clientes
summary: Envía avisos de pedidos y recordatorios por WhatsApp, junto a los emails: qué envía cada outbox de WhatsApp, cómo activarlo y por qué un mensaje puede no salir.
date: 2026-11-18
source_date: 2026-11-18
tags: whatsapp, notifications, outboxes, orders
category: help-desk
series: WhatsApp
order: 4
help_routes: grp.org.shops.show.dashboard.comms.customer_notification_outboxes, grp.org.shops.show.dashboard.comms.whatsapp_outboxes
---

<aside class="tldr">
Un <b>outbox de WhatsApp</b> envía un tipo de notificación al cliente, por ejemplo Order dispatched (Pedido enviado), por WhatsApp. Funciona por su cuenta, junto al email del mismo nombre. Para activarlo: asígnale una <b>plantilla Utility aprobada</b> y ponlo en <b>Active</b> (Activo). Solo llega a los clientes que <b>aceptaron recibir avisos por WhatsApp</b> y tienen un número de teléfono al que podamos enviar.
</aside>

## Dónde encontrarlos

En tu tienda, ve a **Comms → Customer Notifications** (Notificaciones a clientes) y abre la pestaña **WhatsApp**. Cada fila es un outbox:

| Columna | Qué te dice |
| --- | --- |
| Template | La plantilla de WhatsApp que envía, o *No template* (Sin plantilla) |
| Status | **Active**, **Not sending** u **Off** (ver más abajo) |
| Sent 1w / Failed 1w | Mensajes enviados y fallidos en los últimos 7 días |

Haz clic en una fila para abrir el outbox.

## Qué envía cada outbox

| Outbox | Cuándo envía |
| --- | --- |
| Order confirmation | Cuando se hace un pedido |
| Order dispatched | Cuando se envía un pedido, con el seguimiento de cada paquete |
| Order payment reminder | Cuando un pedido por transferencia bancaria sigue sin pagar |
| Out of stock in order notification | Cada pocas horas, sobre productos de un pedido que se agotaron |
| Out of stock notification | Una vez al día, cuando vuelve a haber stock de un producto por el que preguntó el cliente |
| Pre-order update | Cuando cambia una reserva: saldo pendiente, nueva fecha de envío, cancelada |
| Credit balance notification for customer | Cuando cambia el saldo a favor del cliente |
| Chat notification to customer | Cuando el personal responde en el chat y el cliente ya salió de la web |
| Review reminder | Una vez al día, un número fijo de días después del envío |
| Gold reward reminder | Una vez al día, un número fijo de días después de la última factura del cliente |

**WhatsApp y email van por separado.** Desactivar el email no detiene el mensaje de WhatsApp, ni al revés. Si los dos están activos, el cliente recibe los dos.

## Activar un outbox

Todos los outbox empiezan en **Off** (Apagado). Para activar uno:

1. **Consigue una plantilla aprobada.** Escríbela en **Chat → WhatsApp templates** (Plantillas de WhatsApp) en la categoría **Utility** y envíala. Meta suele aprobarla en minutos.
2. **Abre el outbox** y elige la plantilla en **WhatsApp template**. Solo se pueden elegir plantillas aprobadas. La **Preview** (Vista previa) muestra lo que leerá el cliente.
3. **Rellena los tiempos**, en los outbox que los necesitan:
   - *Out of stock in order notification:* **Hours between runs** (Horas entre ejecuciones).
   - *Review reminder:* **Days after dispatch** (Días después del envío).
   - *Gold reward reminder:* **Days after the last invoice** (Días después de la última factura).
4. **Activa Active.**

aiku no te deja activar un outbox sin una plantilla Utility aprobada y sus tiempos. Al guardar te dice qué falta.

### Escribir una plantilla que siga aprobada

- **Usa la categoría Utility.** Aquí no se puede usar una plantilla Marketing.
- **Mantén un texto objetivo**, como un recibo. Las palabras promocionales ("oferta especial", "no te lo pierdas") pueden hacer que Meta pase la plantilla a Marketing. Cuando eso ocurre, el outbox deja de enviar y muestra **Not sending** (No envía).
- **Usa las etiquetas** entre corchetes, como `[Customer First Name]`, `[Order Number]`, `[Order Total]`, `[Delivery Address]` o `[Tracking Parcels]`. aiku las rellena para cada cliente.
- **Una plantilla aprobada no se puede editar.** Para cambiar el texto, escribe una plantilla nueva, haz que la aprueben y elígela en el outbox.

## Quién recibe los mensajes

Un cliente recibe una notificación de WhatsApp solo cuando se cumple **todo** esto:

- La tienda tiene un **número de WhatsApp conectado**. Si no, la página lo dice arriba y no se envía nada. Ver [conectar WhatsApp a tu tienda](/docs/connecting-whatsapp-to-your-shop-es).
- El outbox está **Active**, con una plantilla Utility aprobada.
- El cliente **aceptó recibir avisos por WhatsApp**. Marca la casilla en el checkout o en el panel de su cuenta.
- El cliente tiene un **número de teléfono que WhatsApp puede usar**: su número de WhatsApp si lo dio, o si no su teléfono principal.

Los clientes que no aceptaron se saltan sin aviso. Es lo esperado, no un error.

## Qué significa el estado

| Estado | Qué significa |
| --- | --- |
| Active | Activado y enviando |
| Not sending | Activado, pero la plantilla ya no es una plantilla Utility aprobada. Elige otra. |
| Off | Apagado. No se envía nada. |

## Comprobar qué se envió

Abre un outbox para ver tres pestañas:

- **Overview** (Resumen): los ajustes, la vista previa de la plantilla y las cifras. **Sent** significa entregado a WhatsApp, **Delivered** que llegó al teléfono y **Read** que el cliente lo abrió. Los clientes pueden desactivar las confirmaciones de lectura, así que Read es un mínimo.
- **Sent messages** (Mensajes enviados): cada mensaje, con su cliente, pedido y resultado.
- **Bulk runs** (Ejecuciones masivas): para los outbox que envían según un horario (sin stock, reseñas, gold reward), una línea por ejecución.

Cada mensaje aparece también en el **hilo de chat de WhatsApp** del cliente. Un mensaje fallido también queda ahí, con el motivo, para que veas qué se perdió el cliente.

## Cuando un mensaje no salió

| Lo que ves | Por qué, y qué hacer |
| --- | --- |
| Nada en absoluto para un cliente | No aceptó, o no tiene un número de teléfono utilizable. Revisa su ficha de cliente. |
| Failed: the template has more variables than are set up | Algunas etiquetas de la plantilla no están asignadas. Abre la plantilla y asigna todas las variables. |
| Un guion (-) en el mensaje | aiku no tenía valor para esa etiqueta, por ejemplo un pedido todavía sin seguimiento. El mensaje sale igualmente. |
| El estado dice Not sending | Meta pasó la plantilla a Marketing, o ya no está aprobada. Elige una plantilla Utility aprobada. |
| Failed con un error de WhatsApp | WhatsApp lo rechazó. Ver [cuando WhatsApp no funciona](/docs/when-whatsapp-is-not-working-es). |

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Ver todos los outbox de WhatsApp:</b> tu tienda → <b>Comms → Customer Notifications</b> → pestaña <b>WhatsApp</b>.</li>
<li><b>Activar uno:</b> abre el outbox → elige la <b>WhatsApp template</b> → rellena los tiempos si los pide → activa <b>Active</b>.</li>
<li><b>Escribir o cambiar una plantilla:</b> tu tienda → <b>Chat → WhatsApp templates</b>. Usa la categoría Utility.</li>
<li><b>Ver qué se envió:</b> abre el outbox → <b>Sent messages</b>.</li>
</ul>
</aside>
