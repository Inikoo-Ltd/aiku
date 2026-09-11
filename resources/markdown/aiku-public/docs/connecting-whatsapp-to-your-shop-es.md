---
title: Conectar WhatsApp a tu tienda
summary: Qué recoger de Meta, en cuál de las dos páginas de ajustes va cada valor, y cómo comprobar que la conexión funciona antes de confiar en ella.
date: 2026-09-10
source_date: 2026-09-10
tags: marketing, whatsapp, shop
category: marketing
series: WhatsApp
order: 1
---

<aside class="tldr">
WhatsApp necesita ajustes en <b>dos sitios</b>, y esto es lo que pilla a casi todo el mundo. La app de Meta — access key, app id, app secret — va en la página de ajustes de la <b>organización</b>. El número de teléfono y la cuenta de negocio van en la página de ajustes de la <b>tienda</b>. Una tienda con solo su mitad rellena parece conectada en todo aiku y luego falla justo cuando intenta enviar. Rellena las dos, y luego comprueba las tres cosas al final de esta página.
</aside>

## Qué estás conectando

WhatsApp no es una sola cuenta. Es una **Meta app**, que pertenece a la organización, más una **WhatsApp Business Account** — todo el mundo la llama WABA — y un **número de teléfono** bajo ella, que pertenecen a la tienda.

Recoge estos cinco valores del panel de Meta antes de empezar. Todo lo que sigue es rellenar dos formularios.

| Valor | Dónde está en Meta |
| --- | --- |
| App ID | Panel de la app, arriba |
| App secret | Panel de la app → Settings → Basic |
| Access token | Business settings → System users → Generate token |
| WABA ID | WhatsApp → API Setup |
| Phone number ID | WhatsApp → API Setup, bajo el número |

**El access token tiene que ser permanente**, generado para un system user. Meta ofrece primero un token temporal que caduca a las 24 horas — al día siguiente todo deja de funcionar sin ningún aviso y sin nada obvio que señalar. Es, con diferencia, el error de configuración más común.

## Decirle a Meta a dónde enviar los mensajes

En el panel de la app de Meta, bajo **WhatsApp → Configuration**, pon como dirección de callback tu dominio de aiku seguido de `/webhooks/whatsapp`, y un verify token. El verify token tiene que coincidir con un ajuste del lado de aiku, así que **pídeselo antes a tu desarrollador** y pega lo que te dé — es un cambio de ajustes para ellos, no un cambio de código.

Pulsa **Verify and save**. Meta llama a aiku inmediatamente, así que si se queja, o la dirección o el token están mal.

Luego abre **Manage** junto a los campos del webhook y suscríbete exactamente a dos:

- **messages** — todo lo que te envía un cliente, más los acuses de entrega y lectura.
- **message_template_status_update** — el veredicto de Meta cuando termina de revisar una plantilla.

Suscribirse a más no sirve de nada. Suscribirse a menos rompe algo concreto: sin el primero no recibes ningún mensaje, y sin el segundo tus plantillas se quedan diciendo "pending" para siempre incluso después de que Meta las haya aprobado.

## La mitad de la organización

Abre los **ajustes de tu organización** y busca **Meta-configuration**.

- **Access Key** — el access token. Firma todo lo que envía aiku. Sin él no sale nada.
- **App ID** — se usa al subir la imagen de muestra contra la que se revisa una plantilla.
- **App Secret** — demuestra que un mensaje entrante viene realmente de Meta y no de alguien haciéndose pasar por ella.

Cada organización usa su propia app de Meta, así que no hay nada a lo que recurrir. En blanco aquí significa en blanco: aiku no toma prestados los ajustes de otra organización.

## La mitad de la tienda

Abre los **ajustes de la tienda** y ve a **Chat**. Activa **Enable WhatsApp Channel**, que revela tres campos más.

- **Phone Number ID** — desde qué número envías, y cómo sabe aiku que un mensaje entrante pertenece a esta tienda.
- **WABA ID** — necesario para todo lo relacionado con plantillas.
- **Phone Number** — el número en sí, escrito tal como lo vería un cliente. Este es solo para mostrar; aparece en la vista previa de la campaña.

## Comprobar que funciona

Tres comprobaciones, en este orden. Cada una depende de la anterior, así que un fallo te dice dónde mirar.

1. **Meta aceptó el webhook.** Lo dijo al pulsar Verify and save.
2. **Llegan los mensajes.** Envía un mensaje de WhatsApp al número desde tu propio teléfono. En unos segundos la conversación debería aparecer en la bandeja de chat de la tienda. Si no aparece nada, los mensajes entrantes no están llegando a aiku — ver [cuando WhatsApp no funciona](/docs/when-whatsapp-is-not-working-es).
3. **Sincronizan las plantillas.** Abre **Chat → WhatsApp templates** y pulsa **Sync**. Debería aparecer todo lo ya aprobado en Meta. Si el botón da error, el WABA ID o el access token están mal.

Cuando las tres pasan, la tienda puede mantener conversaciones y enviar campañas.

## Antes de enviar nada

**Las plantillas no son opcionales.** WhatsApp no permite que un negocio abra una conversación con texto libre. Toda campaña, y toda respuesta enviada más de 24 horas después de que el cliente te escribiera por última vez, tiene que usar una plantilla aprobada previamente por Meta. Escríbelas y envíalas desde **Chat → WhatsApp templates**, y cuenta con que la revisión tarde entre unos minutos y un día.

**Una plantilla para campañas debe estar en la categoría Marketing.** Las plantillas de las categorías Utility o Authentication funcionan perfectamente en conversaciones pero nunca aparecerán en el selector de campañas. Eso lo decide Meta, no aiku, y la categoría no se puede cambiar después de crear la plantilla.

**Tener un número de teléfono no es permiso para hacerle marketing a alguien.** Los clientes se suscriben al boletín de WhatsApp al registrarse y en el checkout, y por defecto las campañas van solo a quienes lo hicieron. [Enviar una campaña de WhatsApp](/docs/sending-a-whatsapp-campaign-es) cubre esa elección.

## Vale la pena anotar en algún sitio

- A qué system user de Meta pertenece el access token. Cuando alguien elimine ese usuario, WhatsApp deja de funcionar y nadie recuerda por qué.
- Quién en el negocio aprueba el texto de una plantilla antes de enviarla a Meta. Una plantilla rechazada es un ida y vuelta lento.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>La mitad de la organización:</b> tu organización → <b>Settings</b> → <b>Meta-configuration</b> → Access Key, App ID, App Secret.</li>
<li><b>La mitad de la tienda:</b> tu tienda → <b>Settings</b> → <b>Chat</b> → activa <b>Enable WhatsApp Channel</b>, luego Phone Number ID, WABA ID y Phone Number.</li>
<li><b>Comprobar que llegan los mensajes:</b> tu tienda → <b>Chat → Inbox</b>, tras mandar un mensaje al número desde tu teléfono.</li>
<li><b>Comprobar las plantillas:</b> tu tienda → <b>Chat → WhatsApp templates</b> → <b>Sync</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los ajustes de organización y los de tienda son ambos pantallas de administrador. Si no ves <b>Meta-configuration</b> en la organización, no tienes los permisos para completar esto y necesitarás a alguien que sí los tenga.</li>
</ul>
</aside>
