---
title: Cuando WhatsApp no funciona
summary: Los síntomas con los que la gente se topa de verdad - nada llega a la bandeja, plantillas atascadas en pending, una campaña que se detuvo o no termina - con la causa y qué cambiar.
date: 2026-09-10
source_date: 2026-09-10
tags: marketing, whatsapp, campaigns
category: marketing
series: WhatsApp
order: 3
---

<aside class="tldr">
Dos páginas de ajustes sostienen todo lo que hace WhatsApp — la app de Meta de la <b>organización</b> y el número de teléfono de la <b>tienda</b> — y la mayoría de los fallos son un campo en blanco en una de las dos. Empieza por ahí. Si todo se paró a la vez y nadie cambió nada, casi siempre es un access token caducado. Cómo configurarlos se explica en <a href="/docs/connecting-whatsapp-to-your-shop-es">conectar WhatsApp a tu tienda</a>.
</aside>

## No llega nada a la bandeja cuando escribo al número

Los mensajes entrantes o no están llegando a aiku, o llegan y son rechazados. Repasa esto en orden — están ordenados por con qué frecuencia resulta ser cada uno el culpable.

1. **¿Sigue verificado el webhook en Meta?** WhatsApp → Configuration. Si muestra un error ahí, la dirección de callback o el verify token están mal.
2. **¿Está suscrito el campo `messages`?** Verificar el webhook y suscribirse a sus campos son dos pasos separados en Meta, y es fácil hacer el primero y olvidar el segundo.
3. **¿Tiene la tienda el Phone Number ID correcto?** Un mensaje entrante se asocia a una tienda por ese número. Uno erróneo o en blanco hace que el mensaje llegue y no vaya a ninguna parte.
4. **¿Tiene la organización un App Secret?** Sin él, todo mensaje entrante se rechaza antes de leerse.

Si las cuatro parecen correctas, pide a tu desarrollador que revise los logs. Ellos pueden distinguir las causas 3 y 4, algo que no es posible desde fuera.

## Las plantillas se quedan en "pending" aunque Meta las aprobó

El veredicto de Meta no está llegando a aiku, o no se puede asociar a una tienda. Dos cosas que comprobar.

Primero, que **message_template_status_update** esté suscrito en Meta. Es una casilla separada de `messages` y suele ser la que falta.

Segundo, que el **WABA ID** de la tienda esté rellenado. Los veredictos de plantillas se asocian a una tienda por cuenta de negocio y no por número de teléfono, así que una tienda puede estar recibiendo mensajes perfectamente mientras pierde todos los veredictos. Eso es lo que hace tan confuso este síntoma: todo lo demás funciona.

Mientras tanto, **Refresh** en la plantilla le pide a Meta su estado actual directamente y la desatasca.

## "WhatsApp is not configured for this shop"

La tienda no tiene **Phone Number ID**. Shop settings → Chat → rellena los campos de WhatsApp Connection.

Esto aparece al intentar enviar y no mientras compones, por eso una campaña puede parecer completamente lista y luego negarse en el último paso. Es intencionado: los ajustes de la tienda no forman parte de si una campaña está lista, o cambiar un ajuste de la tienda dejaría de golpe "no lista" a toda campaña de la tienda.

## Una campaña se queda atascada diciendo "Sending"

Una campaña sale en lotes de 50 y solo dice Sent cuando todos los lotes han terminado, así que un lote nunca acabó.

Primero dale tiempo — una audiencia grande tarda un rato, y los informes de entrega siguen llegando después de que salga el último mensaje. Si no se ha movido en una hora, pregunta a tu desarrollador: hay comandos para empujar los lotes restantes y cerrar la campaña, y normalmente significa que los workers en segundo plano de aiku se han parado y no que algo vaya mal con la campaña.

## Una campaña pasó directamente a "Stopped"

Cuando llegó su hora programada no se pudo enviar, así que terminó en vez de quedarse ahí aparentando que estaba a punto de salir.

La página de la campaña dice por qué. Será uno de estos: no se eligió plantilla, no hay destinatarios, o falta la conexión de WhatsApp de la tienda. Arréglalo y envía una campaña nueva — una detenida no se reanuda.

## La audiencia es mucho más pequeña que mi lista de clientes

Normalmente no es ningún fallo.

Por defecto una campaña va solo a los clientes que se **suscribieron** al boletín de WhatsApp, que ya de por sí es una fracción pequeña de la lista y crece con el tiempo. Además, los números a los que WhatsApp no puede entregar — sin prefijo de país, un email escrito en un campo de teléfono — se descartan sin contarse.

Si de verdad quieres una audiencia más amplia, añade los grupos Contacted o Customers al componer, y lee antes el aviso en [enviar una campaña de WhatsApp](/docs/sending-a-whatsapp-campaign-es).

## Muchos mensajes fallaron con algo tipo "Missing"

La plantilla tiene un hueco personalizado y a esos clientes les falta el valor para rellenarlo, así que se les saltó en vez de enviarles un mensaje con un agujero.

O bien rellena el campo que falta en esas fichas de cliente, o usa una plantilla cuyos huecos pueda responder cualquier destinatario. No hay forma de enviar una plantilla con un hueco vacío — WhatsApp la rechaza directamente.

## Una plantilla no aparece en el selector de campañas

Todavía no está aprobada, o no está en la categoría **Marketing**. Ambas cosas son obligatorias. La categoría se elige al crear la plantilla y no se puede cambiar después, así que una plantilla Utility hay que reescribirla como Marketing y volver a enviarla.

## Todo se paró a la vez y nadie cambió nada

Nueve de cada diez veces, un access token caducado. Meta entrega por defecto un token temporal de 24 horas y es fácil guardar uno por error durante la configuración. Genera uno permanente para un system user y pégalo en los ajustes de la organización, bajo **Meta-configuration → Access Key**.

Los demás candidatos, por orden de probabilidad: se eliminó el system user de Meta al que pertenecía el token, el número fue limitado por Meta tras reportes o bloqueos, o la app volvió a modo desarrollo.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>La mitad de la organización:</b> tu organización → <b>Settings</b> → <b>Meta-configuration</b>.</li>
<li><b>La mitad de la tienda:</b> tu tienda → <b>Settings</b> → <b>Chat</b> → los campos de WhatsApp Connection.</li>
<li><b>Desatascar una plantilla:</b> tu tienda → <b>Chat → WhatsApp templates</b> → ábrela → <b>Refresh</b>.</li>
<li><b>Ver por qué se detuvo una campaña:</b> abre la campaña desde <b>Marketing → Whatsapp Campaigns</b>; la razón está en su página.</li>
</ul>
</aside>
