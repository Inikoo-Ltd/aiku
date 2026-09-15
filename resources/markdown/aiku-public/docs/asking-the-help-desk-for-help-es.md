---
title: Pedir ayuda al help desk
summary: Informa de un error o pide una función desde aiku o Slack, sigue lo que pasa con ella, responde a tiempo a las preguntas de los ingenieros y valora la solución cuando esté lista.
date: 2026-09-15
source_date: 2026-09-15
tags: help desk, tickets, bugs
category: help-desk
help_routes: grp.tickets
---

<aside class="tldr">
Cuando algo en aiku no funciona, o necesitas algo que todavía no hace, abre un <b>ticket</b>. Entra en la cola del help desk, un ingeniero lo recoge y tú lo sigues en la página <b>Tickets</b> hasta que queda en <b>Done</b>. Un buen ticket explica qué pasa, enlaza la página donde pasa y lleva una captura de pantalla. Todo lo que se escribe en un ticket lo ven las personas que trabajan en él, así que escríbelo como se lo dirías a ellas.
</aside>

## Dónde están los tickets

Abre <b>Tickets</b> en el menú de la izquierda. El <b>Dashboard</b> muestra <b>Mine</b>, los tickets que has abierto y siguen abiertos, y los cerrados hace poco. <b>List</b> muestra todos los tickets que puedes ver, con filtros por los que abriste tú, su estado y su tipo.

<b>Consejo:</b> el contador de tickets de la derecha de la pantalla muestra tus tickets que están en <b>To do</b>, <b>In progress</b> o <b>Waiting for my reply</b>. Haz clic en él para abrirlos. En <b>Recent</b> ves los últimos cambios en tus tickets, como un estado nuevo o un comentario nuevo. Un punto marca los que todavía no has abierto, y desaparece cuando abres el ticket.

## Abrir un ticket en aiku

Lo más rápido es el botón rojo <b>Bug</b> que hay en todas las páginas, o <b>Alt+Shift+B</b>. Rellena por ti la página en la que estás. También puedes pulsar <b>New ticket</b> en las páginas de Tickets.

- <b>Subject</b>: una línea que diga qué falla o qué necesitas. Es el único campo obligatorio.
- <b>Details</b>: qué hiciste, qué esperabas y qué pasó en su lugar. Para una función, qué necesitas y por qué.
- <b>Page where it happens</b>: el enlace a la página.
- <b>Kind</b>: <b>Bug</b> si algo está roto, <b>Feature request</b> si necesitas algo nuevo.
- <b>Module</b> y <b>Priority</b>: elígelos si los sabes; deja la prioridad en normal salvo que el trabajo esté parado.
- Capturas y archivos: pégalos, arrástralos al cuadro de detalles o pulsa <b>Attach</b>. Puedes añadir hasta cinco a la vez: imágenes y archivos PDF, Word, Excel, CSV, ZIP, RAR y 7z de hasta 10 MB cada uno, y vídeos cortos de hasta 50 MB. Una grabación corta de la pantalla que muestre el problema ayuda mucho.

## Abrir un ticket desde Slack

En Slack puedes usar el atajo <b>Raise ticket</b> sobre cualquier mensaje, o escribir <b>/ticket</b>. Los dos abren un formulario corto con los mismos campos. <b>/ticket</b> seguido de texto crea el ticket al momento: la primera línea es el asunto y el resto los detalles. También puedes reaccionar a un mensaje con el emoji de ticket para convertirlo en ticket.

Si el ticket no tiene enlace ni captura, el help desk responde en el hilo pidiéndolos. Sin ellos, un ticket es mucho más difícil de arreglar.

Cada ticket tiene un hilo en Slack. Las respuestas en ese hilo se añaden al ticket como comentarios, y los comentarios escritos en aiku aparecen en el hilo.

## Seguir tu ticket

Un ticket pasa por estos estados:

- <b>Todo</b>: esperando a un ingeniero.
- <b>Assigned</b>: un ingeniero lo tiene en su lista.
- <b>In progress</b>: alguien está trabajando en él.
- <b>Waiting</b>: el ingeniero necesita una respuesta tuya.
- <b>Reporter replied</b>: has respondido y vuelve a ser el turno del ingeniero.
- <b>Waiting for deployment</b>: la solución está terminada y llegará a aiku con la próxima actualización. Entonces el ticket se cierra solo.
- <b>Done</b>: arreglado o entregado.
- <b>Cancelled</b>: cerrado sin solución.

Se te avisa cuando un ingeniero te pregunta algo, cuando tu ticket está hecho y cuando alguien comenta. Los comentarios aparecen en el indicador del ticket y, según tu configuración, por email, por Slack o como notificación del navegador en tu ordenador o tu móvil. Consulta [Getting notifications on your computer and phone](/docs/getting-notifications-on-your-computer-and-phone).

## Cuando el ingeniero te pregunta algo

Si el ingeniero necesita más información, el ticket pasa a <b>Waiting</b> y recibes su pregunta. Responde con un comentario en el ticket o en su hilo de Slack. Por defecto tienes <b>72 horas</b>; si nadie responde a tiempo, el ticket se cancela con la nota "No reply for … days".

Responder a un ticket en espera o cancelado se lo devuelve al ingeniero, así que una respuesta tardía nunca se pierde.

## Archivos de tu ticket

Todos los archivos de tu ticket aparecen juntos en <b>Attachments</b>. Haz clic en uno para verlo sin descargarlo. Al hacer clic en un archivo ZIP, RAR o 7z ves qué archivos hay dentro, con un botón para descargarlo. También puedes elegir ver los comentarios de más nuevos a más antiguos o al revés, y aiku recuerda tu elección.

## Cerrar y valorar

Si el problema desapareció o ya no necesitas la función, puedes cancelar tu propio ticket. Antes aiku te pide una nota corta, para que el ingeniero sepa por qué ya no hace falta. Cuando un ingeniero cierra tu ticket, también deja una nota contándote qué ha hecho. Cuando un ticket se cierra se te pregunta <b>How did we do?</b>: ponle estrellas y, si quieres, un comentario, y pulsa <b>Send rating</b>. Cada ticket se puede valorar una vez.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Informar de un error en la página en la que estás:</b> el botón rojo <b>Bug</b>, o <b>Alt+Shift+B</b>.</li>
<li><b>Abrir un ticket desde el menú:</b> <b>Tickets</b> → <b>New ticket</b>.</li>
<li><b>Ver tus tickets abiertos:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Mine</b>.</li>
<li><b>Responder a la pregunta de un ingeniero:</b> abre el ticket → escribe un comentario, o responde en su hilo de Slack.</li>
<li><b>Valorar un ticket cerrado:</b> abre el ticket → <b>How did we do?</b> → <b>Send rating</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Cualquiera que pueda entrar en aiku puede abrir tickets, comentarlos y seguirlos. El help desk es quien hace avanzar tu ticket: tú no puedes cambiar su estado, prioridad, módulo, etiquetas ni la persona asignada, pero puedes seguir cada paso y responder en los comentarios. A veces trabaja en tu ticket más de un ingeniero: el ingeniero asignado lo dirige y puede añadir compañeros como colaboradores para que le ayuden. Los tickets confidenciales solo los ven quien los abrió, el ingeniero que trabaja en ellos, sus colaboradores y los lead engineers.
</aside>
