---
title: Hablar con compañeros en el chat interno
summary: La barra de mensajes a la derecha de cada pantalla de aiku - escribe a un compañero, pregunta a CRM o al almacén sobre un pedido o albarán concreto con un solo toque, y decide quién recibe esas preguntas.
date: 2026-09-02
source_date: 2026-09-02
tags: crm, dispatch, hr, chat, messaging
category: crm
---

<aside class="tldr">
El chat interno es para que la gente que trabaja en aiku hable entre sí. Está completamente separado del chat de clientes de tu web: los clientes nunca lo ven y nunca les llega. El personal de almacén lo usa desde tablets para llegar a atención al cliente sin salir de la mesa de empaquetado, y atención al cliente lo usa para responderles. Si te ha llegado un mensaje titulado <b>CRM · </b><i>referencia del pedido</i> que no esperabas, salta a <a href="#who-receives">Quién recibe "Ask CRM"</a>: es un ajuste que controla tu tienda.
</aside>

## Dónde está

Cada pantalla de aiku tiene una **barra de mensajes** estrecha a la derecha. Haz clic para desplegarla. Muestra quién está **en línea ahora**, tu **equipo** y tus **mensajes** abiertos, con un contador rojo para lo no leído. En un móvil o tablet la conversación se abre como una hoja a pantalla completa con botones grandes; en un ordenador se abre como una ventana pequeña al pie de la página, y **Open full view** te lleva a una versión a página completa con la lista de conversaciones a la izquierda.

La barra también tiene abajo la entrada **Customer chats** para quienes están configurados como agentes de chat. Ese es el chat de la web descrito en [Hablar con clientes por Chat](/docs/customer-chat-es), una cosa distinta.

## Escribir a un compañero

Abre la barra, busca a la persona en **Everyone online** o **Find a coworker…** y haz clic en su nombre. Un compañero es cualquiera que trabaje en una organización a la que tú también tienes acceso. Escribe y pulsa Intro. Está todo lo que esperas de un mensajero: pegar una captura de pantalla o adjuntar una imagen, responder a un mensaje concreto, reaccionar con un emoji, enviar un GIF y mencionar a alguien con **@** para que el mensaje le aparezca con una marca de color.

Dos cosas son propias de aiku:

- **Respuestas rápidas.** Encima del cuadro de escritura hay chips como **Done**, **Help!**, **Call me**, **OK**, **Thanks**. Un toque envía la palabra. Existen porque un empaquetador con guantes y una tablet no debería tener que teclear. Tu grupo puede cambiar la lista.
- **Traducción automática.** Cada mensaje se traduce al idioma que cada participante usa en aiku. Tú lo lees en el tuyo, ellos en el suyo, y cualquiera puede tocar **original** para ver lo que se escribió de verdad. Nadie necesita escribir en inglés.

Tu lista **My team** son las pocas personas con las que más hablas. Añádelas con **Add to my team** y quedan arriba de la barra con su punto de presencia. En línea significa que la persona tiene la aplicación abierta y ha hecho algo en el último cuarto de hora; ámbar significa inactiva.

## Preguntar a CRM o al almacén sobre un pedido

Esto es la mayor parte del tráfico del chat interno. Algunas pantallas tienen un botón que abre una conversación sobre ese documento exacto, con las personas correctas ya dentro:

- Página del **albarán** (delivery note): **Ask CRM**.
- Página del **pedido**: **Ask warehouse** y **Ask CRM**.
- Página de la **sesión de picking**: **Ask CRM**.
- Cuando un transportista se niega a generar la etiqueta de envío, el cuadro del envío muestra el error del propio transportista y un botón **Ask CRM about this**. Al tocarlo se publica el transportista y el error en la conversación de CRM del albarán por ti, sin teclear nada.

La conversación lleva el nombre de su documento, por ejemplo **CRM · AFR26782**, para que todos vean de qué trata antes de abrirla. Pulsar el botón otra vez más tarde vuelve a la misma conversación en lugar de crear una nueva.

Nadie más que tú ve una conversación recién creada hasta que se envía el primer mensaje, así que abrir una por error no molesta a nadie. Si no hay nadie al otro lado (una lista de destinatarios vacía y nadie con el rol), aiku te lo dice en lugar de mandar el mensaje a una sala vacía.

<h2 id="who-receives">Quién recibe "Ask CRM" y "Ask warehouse"</h2>

Los destinatarios son una lista que tú cuidas, no todo el que alguna vez haya sido agente de atención al cliente. Cada tienda tiene sus propias listas, porque a los clientes de una tienda los atiende el equipo de esa tienda:

1. Abre los **Settings** de la tienda y busca la sección **Staff chat**.
2. **Ask CRM goes to** es la lista principal. **Ask CRM backup** se usa cuando nadie de la lista principal está activo en ese momento.
3. **Ask warehouse goes to** y **Ask warehouse backup** funcionan igual para las preguntas en la otra dirección.

Los **Settings** de la organización también tienen una sección **Staff chat**, pero solo para las listas de almacén: son el valor por defecto cuando una tienda no tiene las suyas. Ask CRM es siempre por tienda.

Las reglas que sigue aiku al pulsar el botón:

- Si alguien de la lista principal está activo, solo se añaden los activos.
- Si no, las personas activas de la lista de respaldo.
- Si nadie de ninguna lista está activo, se añade a todos los de ambas listas, y la pregunta espera al primero que vuelva.
- Si la tienda no tiene listas, la pregunta va a todos los que tengan los roles de atención al cliente de esa tienda (o los roles de almacén de ese almacén).

Así que si una pregunta de almacén le llega a alguien que no debería recibirla, el arreglo está en los Settings de la tienda, no en el chat. Quítalo de la lista, o dale a la tienda una lista si no la tenía.

## Cerrar y mantener orden

Cerrar una conversación con **X** la archiva solo para ti; vuelve por sí sola cuando alguien escribe de nuevo en ella. También puedes usar **Leave this conversation for now** en una conversación de grupo. No se borra nada.

Los chats internos son conversaciones de trabajo, no privadas. RR. HH. y los administradores de la organización pueden leerlos desde **Human Resources → Staff chat**, que lista las conversaciones con sus mensajes. La página de sysadmin muestra recuentos y quién habla con quién, nunca el texto.

## Hacerlo tuyo

En **My profile** puedes poner un **Chat nickname**, que sustituye a tu nombre en la barra, y elegir un tema de colores para el chat. Los administradores del grupo pueden editar las respuestas rápidas en **Settings → Staff chat** del grupo, una por línea.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Escribir a alguien:</b> barra de mensajes a la derecha → <b>Everyone online</b> o <b>Find a coworker…</b> → su nombre → escribe → Intro.</li>
<li><b>Preguntar sobre un documento:</b> abre el albarán, pedido o sesión de picking → <b>Ask CRM</b> / <b>Ask warehouse</b>.</li>
<li><b>Elegir quién recibe esas preguntas:</b> tu organización → tu tienda → <b>Settings → Staff chat</b> → <b>Ask CRM goes to</b>, <b>Ask CRM backup</b>, <b>Ask warehouse goes to</b>, <b>Ask warehouse backup</b>. Organización → <b>Settings → Staff chat</b> guarda el valor por defecto de almacén.</li>
<li><b>Cambiar las respuestas rápidas:</b> grupo <b>Settings → Staff chat → Quick replies</b>.</li>
<li><b>Apodo y colores:</b> tu avatar → <b>My profile</b> → <b>Chat nickname</b>; <b>Settings</b> → tema del chat.</li>
<li><b>Leer conversaciones internas como RR. HH.:</b> organización → <b>Human Resources → Staff chat</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li><b>Enviar mensajes:</b> cualquier usuario de aiku. Puedes llegar a cualquiera que trabaje en una organización a la que tú también tengas acceso.</li>
<li><b>Botones Ask CRM / Ask warehouse:</b> quien pueda abrir el albarán, pedido o sesión de picking.</li>
<li><b>Editar las listas de destinatarios:</b> quien pueda editar los ajustes de la tienda o de la organización.</li>
<li><b>Editar respuestas rápidas:</b> administrador del grupo.</li>
<li><b>Leer los chats internos de otros:</b> RR. HH. o administrador de la organización.</li>
</ul>
</aside>
