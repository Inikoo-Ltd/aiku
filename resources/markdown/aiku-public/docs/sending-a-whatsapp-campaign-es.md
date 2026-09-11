---
title: Enviar una campaña de WhatsApp
summary: Elige una plantilla aprobada, decide quién la recibe, previsualízala tal como la verá el cliente, y envíala ahora o prográmala - además de qué te dicen y qué no te dicen las cifras de entrega.
date: 2026-09-10
source_date: 2026-09-10
tags: marketing, whatsapp, campaigns
category: marketing
series: WhatsApp
order: 2
---

<aside class="tldr">
Una campaña de WhatsApp envía un mensaje a muchas personas desde el propio número de tu tienda. A diferencia de un email, no puedes simplemente escribirlo: WhatsApp solo permite que un negocio inicie una conversación usando una <b>plantilla aprobada previamente por Meta</b>, y para campañas esa plantilla debe estar en la categoría <b>Marketing</b>. Creas la campaña, eliges la plantilla, eliges la audiencia, la previsualizas, y luego la envías ahora o la programas. Las respuestas vuelven a la bandeja de chat, así que ten a alguien pendiente después de un envío.
</aside>

## Dónde viven las campañas

Dentro de una tienda, **Marketing → Whatsapp Campaigns** contiene la lista. Cada fila muestra el estado de la campaña con un icono y un color: In process, Ready, Scheduled, Sending, Sent, Cancelled o Stopped. La pestaña **Subscribers** en la misma página muestra quién se ha suscrito para recibirlas.

## Por qué no puedes simplemente escribir un mensaje

WhatsApp no deja que un negocio abra una conversación con texto libre. Todo lo que envía una campaña tiene que ser una plantilla que Meta revisó y aprobó de antemano. Escribes la plantilla, la envías, y Meta suele responder en minutos — a veces tarda hasta el día siguiente.

Dos reglas pillan a la gente:

- La plantilla debe estar en la categoría **Marketing** para aparecer en el selector de campañas. Las plantillas Utility y Authentication funcionan bien en conversaciones pero no aparecerán aquí, y la categoría no se puede cambiar después de crear la plantilla.
- Una vez aprobado, el texto queda fijo. Cambiar el mensaje significa una plantilla nueva y otra revisión.

Las plantillas se escriben en **Chat → WhatsApp templates**.

## Crear una campaña

Pulsa **Campaign** arriba de la lista. aiku la crea inmediatamente con un nombre por defecto que puedes cambiar, y te lleva al **workshop**. Una campaña nueva empieza **In process**.

El workshop son dos decisiones en una pantalla: qué plantilla, y quién la recibe. La barra de progreso arriba muestra dónde estás — **Compose**, y luego **Preview & send**.

## Elegir quién la recibe

Tres grupos, que puedes combinar:

| Grupo | A quién se refiere |
| --- | --- |
| **Subscribers** *(activo por defecto)* | Clientes que se suscribieron al boletín de WhatsApp |
| **Contacted** | Cualquiera que haya escrito alguna vez a la tienda por WhatsApp, incluidas personas que no son clientes |
| **Customers** | Clientes con un número de teléfono utilizable, se hayan suscrito o no |

Encima de los grupos puedes añadir los filtros de cliente habituales, y el número de destinatarios se actualiza al cambiarlos.

**Piénsatelo bien antes de usar Customers.** Tener el número de teléfono de alguien no es lo mismo que su permiso para hacerle marketing. Subscribers es el valor por defecto por una razón: en la mayoría de sitios escribir a gente que nunca se suscribió es a la vez un problema legal y la forma más rápida de que reporten tu número, y con suficientes reportes Meta lo limita.

### Por qué la audiencia parece tan pequeña

Dos filtros se aplican los pidas o no.

**Opt-in.** Con el grupo por defecto, solo cuentan los clientes que marcaron la casilla del boletín de WhatsApp. Al principio será una fracción pequeña de tu lista de clientes y crece a medida que la gente se registra y hace checkout.

**Números entregables.** Un número al que WhatsApp no puede enrutar se descarta antes de intentarlo siquiera: sin prefijo de país, un email escrito en un campo de teléfono, un fijo registrado como móvil. Esos no se intentan ni se cuentan.

Así que el número de destinatarios queda bastante por debajo del número de clientes. Eso es aiku siendo honesto sobre a quién puede llegar de verdad, no un fallo.

## Personalizar el mensaje

Una plantilla puede llevar huecos — el nombre de pila del cliente, por ejemplo — rellenados por persona. Asocias cada hueco a un campo de cliente al componer, y la vista previa muestra valores reales para que veas lo que llegará de verdad.

**Si a alguien le falta un valor, se le salta en vez de enviarle un mensaje roto.** WhatsApp rechaza un mensaje con un hueco vacío, y un "Hola ," sería peor que nada. Esos contactos aparecen después como fallidos, indicando qué valor faltaba — normalmente una señal de que una ficha de cliente está incompleta y no de que algo vaya mal con la campaña.

## Vista previa, y luego enviar

**Preview & send** muestra el mensaje tal como lo verá el cliente, con valores reales rellenados, junto al número de destinatarios. Es la última pantalla antes de que salga nada.

Desde aquí hay dos caminos:

- **Send now** la empieza de inmediato. La campaña pasa a **Sending** y aiku va procesando los destinatarios en segundo plano en lotes de 50, en vez de todos a la vez. Una audiencia grande tarda un rato, y la página se actualiza según avanza.
- **Schedule** abre un selector de fecha y hora. La campaña pasa a **Scheduled**, y aiku comprueba cada minuto si hay campañas cuya hora ha llegado.

Puedes editar una campaña libremente hasta que se programa o se envía. Una vez programada, el contenido queda congelado — pulsa **Cancel Schedule** para recuperarla y volver a editarla. Es intencionado: la audiencia que elegiste es la audiencia a la que va.

Una campaña programada se vuelve a comprobar en el momento en que se dispara, no solo cuando la programaste. Si algo cambió entre medias — se eliminó la conexión de WhatsApp de la tienda, se borró la plantilla — la campaña **se detiene** en vez de enviarse, y su página dice por qué. No se queda ahí pareciendo que está a punto de salir.

### Qué significan los estados

| Estado | Qué significa para ti |
| --- | --- |
| In process | Se está escribiendo. No ha salido nada todavía. |
| Ready | Tiene plantilla y audiencia. Lista para enviar o programar. |
| Scheduled | Esperando su hora. Cancela la programación para volver a editarla. |
| Sending | Saliendo ahora. |
| Sent | Han salido todos los lotes. |
| Cancelled | La programación se canceló antes de dispararse. |
| Stopped | Terminó sin enviarse. La página de la campaña dice por qué. |

## Leer las cifras

Cuatro números, que vienen del propio WhatsApp a medida que entrega:

- **Sent** — aiku le pasó el mensaje a WhatsApp.
- **Delivered** — llegó al teléfono del cliente.
- **Read** — lo abrieron. Los tics azules. Los clientes pueden desactivar los acuses de lectura, así que trátalo como un mínimo y no como la verdad.
- **Failed** — no salió, o porque WhatsApp lo rechazó o porque faltaba un valor de personalización y se saltó a esa persona.

**Clicked siempre es 0.** El seguimiento de clics a través de WhatsApp todavía no existe en aiku. La casilla está ahí y se quedará en cero — eso no es un fallo, ni prueba de que nadie hizo clic.

Los informes de entrega llegan a lo largo de minutos y a veces más, así que las cifras siguen moviéndose después de que una campaña diga Sent. Vuelve a mirarla en vez de juzgar una campaña en el momento en que termina.

## Lo que una campaña no te puede decir

- **Si alguien hizo clic en un enlace** — ver arriba.
- **Si vendió algo.** Las campañas de WhatsApp todavía no están unidas a la atribución de marketing, así que los ingresos no se les acreditan como sí ocurre con un boletín.
- **Por qué falló un número**, más allá de la razón que da el propio WhatsApp, que a veces es solo "invalid".
- **Nada sobre la gente a la que nunca llegaste.** Los contactos filtrados por no tener opt-in o por tener un número inutilizable simplemente están ausentes de las cifras.

## Reglas prácticas

- Envía a Subscribers a menos que tengas una razón concreta que estés cómodo defendiendo.
- Mantén pequeña la primera campaña desde un número nuevo. Meta vigila de cerca los números nuevos, y una ráfaga de bloqueos o reportes al principio puede hacer que lo limiten.
- Espera respuestas. Una campaña llega a la misma conversación que usaría el cliente para responder, así que alguien debería vigilar la bandeja las horas siguientes.
- Delivered alto con Read bajo es normal. Failed alto vale la pena investigarlo — empieza por [cuando WhatsApp no funciona](/docs/when-whatsapp-is-not-working-es).
- No compares Read aquí con Opened en una campaña de email. Miden cosas distintas por medios distintos.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver o empezar una campaña:</b> tu tienda → <b>Marketing → Whatsapp Campaigns</b> → pulsa <b>Campaign</b> para crear una.</li>
<li><b>Elegir la plantilla y la audiencia:</b> la pantalla de workshop a la que llegas — elige una plantilla, luego ajusta los grupos y filtros y observa el número de destinatarios.</li>
<li><b>Enviarla:</b> <b>Preview &amp; send</b> → <b>Send now</b>, o <b>Schedule</b> para una fecha y hora. Mientras está programada, <b>Cancel Schedule</b> la devuelve a editable.</li>
<li><b>Escribir una plantilla:</b> tu tienda → <b>Chat → WhatsApp templates</b>. Debe ser de categoría Marketing y estar aprobada antes de que una campaña pueda usarla.</li>
<li><b>Ver quién se ha suscrito:</b> la pestaña <b>Subscribers</b> en la página de campañas.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Crear y enviar campañas necesita permisos de edición de marketing en la tienda. Con permisos de solo ver puedes leer una campaña y sus cifras pero los botones de enviar y programar no estarán ahí.</li>
</ul>
</aside>
