---
title: V·Desks privados y V·Ds, canales privados y mensajes directos
summary: V·Desks privados y mensajes directos en Verba - empezar uno, escribir, traducción, respuestas rápidas, ticks, traer tus conversaciones de Slack, la barra de mensajes y Ask CRM / Ask warehouse.
date: 2026-11-28
source_date: 2026-11-28
tags: chat, messaging, verba, crm, dispatch
category: help-desk
series: Verba
order: 4
---

<aside class="tldr">
Un <b>V·D</b> es un mensaje directo, a un compañero o a unos pocos. Un <b>V·Desk privado</b> es un canal privado con nombre, que muestra un candado en lugar de #. Solo las personas de una conversación pueden verla. Si te ha llegado un chat llamado <b>CRM · </b><i>referencia del pedido</i> que no esperabas, salta a <a href="#who-receives">Quién recibe Ask CRM</a>.
</aside>

<h2 id="v-ds">V·Ds: mensajes directos</h2>

Empieza uno con **New Verb** en la pestaña **News** o **V·Ds**, o con **Add coworkers** al final de la lista de V·Ds, y elige uno o más nombres. Si escribes a una persona, es un mensaje directo; si añades más, es un chat de grupo. También puedes hacer clic en la foto de cualquiera y pulsar **Message**.

Escribe en **Write a message…** y pulsa **Enter**. Puedes adjuntar una imagen, enviar un GIF o un emoji, responder a un mensaje concreto (**Reply**) y reaccionar con un emoji rápido. El menú **More actions** de un mensaje también tiene **Create a task from this message**.

Cosas que ayudan en el almacén:

- **Respuestas rápidas.** Fichas como **Done**, **Help!**, **Call me**, **OK**, **Thanks** envían la palabra con un solo toque. Los administradores del grupo pueden cambiarlas en **Settings → Verba → Quick replies** del grupo.
- **Traducción automática.** Cada mensaje se muestra en el idioma que usas en aiku. Toca **original** para ver lo que se escribió de verdad.

**Ticks** bajo tus mensajes: un tick gris es **Sent** (enviado), dos ticks grises **Received** (recibido), dos ticks azules **Read** (leído). En un grupo, haz clic en ellos para ver quién lo ha leído.

La **X** de arriba (**Done talking to…** o **Leave this conversation for now**) oculta el chat solo para ti. Vuelve solo cuando alguien escribe otra vez. No se borra nada.

Haz clic en la estrella junto a un chat para dejarlo en **Starred**, arriba de la lista.

<h2 id="verbations">V·Desks privados: canales privados</h2>

Pulsa **New private V·Desk** en la pestaña **V·Desks**, ponle un **Name** (por ejemplo "buying-team"), añade **People** y pulsa **Create**. Se crea como un canal privado de Slack, creado a tu nombre, así que solo lo ven las personas que invitas, en Slack y en Verba. Para crear uno necesitas tu cuenta de Slack conectada.

Los canales privados de Slack son por ahora de solo lectura en Verba: dicen **Private channel, read only** y respondes en Slack.

Para ver solo tus V·Desks privados, usa el filtro **All · Public · Private** de arriba en la barra lateral de V·Desks. Los enlaces antiguos que terminan en /vx/groups siguen funcionando.

Un miembro puede activar **Show this channel's name in V·Desks, so others can ask to join**. Sus mensajes siguen siendo solo para los miembros. Los canales mostrados así aparecen para el resto en **Other private channels**, con un botón **Ask to join**.

<h2 id="slack">Traer tus conversaciones de Slack</h2>

Tus mensajes directos, chats de grupo y canales privados de Slack no llegan solos a Verba. Tú eliges:

- **Tus mensajes directos y chats de grupo:** en la pestaña **V·Ds** o **V·Desks**, pulsa **Read and say yes** en la tarjeta **Bring your Slack conversations**, o activa **Private conversations** en tu **Personal settings** bajo **Slack**. Un cuadro explica exactamente qué guarda Verba y quién puede verlo; léelo, marca **I understand** y pulsa **Bring my messages**. En mensajes directos y chats de grupo solo vienen tus propias palabras; los canales privados en los que estás vienen enteros. Cuando un compañero todavía no ha dicho que sí, ves **Not brought to Verba yet** en lugar de su mensaje.
- **Un canal privado, entero, para todos los que están en él:** abre el canal en Slack y escribe `/invite @aiku`. Aparece en V·Desks en un momento, con una línea que dice quién lo trajo.

Los mensajes directos de Slack en Verba son por ahora de solo lectura: respondes en Slack.

Para dejar de traerlos, desactiva **Private conversations** en tu **Personal settings** bajo **Slack**. El cuadro te dice qué pasa: los mensajes directos que Verba trajo se borran de Verba, los canales privados se quedan porque pertenecen a todos sus miembros, y en Slack no cambia nada.

<h2 id="bar">La barra de mensajes</h2>

Todas las pantallas de aiku tienen una barra fina a la derecha. Haz clic para abrirla: **Everyone online**, **Online in my organisation**, **My team** y **Messages**, con un contador rojo para lo no leído. Haz clic en un nombre para escribirle. Haz clic en la estrella junto a alguien (**Add to my team**) para ponerlo en **My team**; tu equipo también sale en el [botón de llamada rápida](/docs/verba-calls-es#quick-call).

<h2 id="ask-crm">Ask CRM y Ask warehouse</h2>

Algunas pantallas tienen un botón que abre un chat sobre ese documento concreto, con las personas adecuadas ya dentro:

- Página de **Delivery note**: **Ask CRM**.
- Página de **Order**: **Ask warehouse** y **Ask CRM**.
- Página de **Picking session**: **Ask CRM**.

El chat lleva el nombre de su documento, por ejemplo **CRM · AFR26782**. Si vuelves a pulsar el botón más tarde, abre el mismo chat. Estos chats aparecen en **Legacy chats**, al final de la lista de **V·Ds**.

<h2 id="who-receives">Quién recibe Ask CRM y Ask warehouse</h2>

Las personas son una lista que mantiene cada tienda:

1. Abre **Settings** de la tienda y busca la sección **Verba**.
2. **Ask CRM goes to** es la lista principal. **Ask CRM backup** se usa cuando nadie de la lista principal está activo ahora mismo.
3. **Ask warehouse goes to** y **Ask warehouse backup** funcionan igual.

**Settings → Verba** de la organización guarda las listas de almacén que se usan cuando una tienda no tiene las suyas.

Cuando se pulsa el botón: si hay alguien activo en la lista principal, solo se añaden los activos; si no, los activos de la lista de respaldo; si no hay nadie activo en ninguna de las dos, se añade a todos los de ambas listas. Si la tienda no tiene ninguna lista, la pregunta va a todas las personas con los puestos de atención al cliente de esa tienda (o los puestos de almacén de ese almacén). Así que si una pregunta llega a la persona equivocada, corrige la lista en los ajustes de la tienda.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Mensaje directo:</b> <b>Verba → V·Ds</b> → <b>New Verb</b> o <b>Add coworkers</b> → nombre.</li>
<li><b>V·Desk privado:</b> <b>Verba → V·Desks</b> → <b>New private V·Desk</b>.</li>
<li><b>Traer tus conversaciones de Slack:</b> <b>V·Ds</b> o <b>V·Desks</b> → <b>Read and say yes</b>; o tu avatar → <b>Personal settings</b> → <b>Slack</b> → <b>Private conversations</b>.</li>
<li><b>Preguntar sobre un documento:</b> delivery note, order o picking session → <b>Ask CRM</b> / <b>Ask warehouse</b>.</li>
<li><b>Quién recibe esas preguntas:</b> tienda → <b>Settings → Verba</b>; organización → <b>Settings → Verba</b> para el valor por defecto del almacén.</li>
<li><b>Respuestas rápidas:</b> <b>Settings → Verba → Quick replies</b> del grupo.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li><b>V·Ds:</b> cualquier acceso a aiku.</li>
<li><b>New private V·Desk:</b> tu cuenta de Slack conectada.</li>
<li><b>Ask CRM / Ask warehouse:</b> quien pueda abrir la delivery note, el order o la picking session.</li>
<li><b>Editar las listas de Ask:</b> quien pueda editar los ajustes de la tienda o de la organización.</li>
<li><b>Editar las respuestas rápidas:</b> administrador del grupo.</li>
</ul>
</aside>
