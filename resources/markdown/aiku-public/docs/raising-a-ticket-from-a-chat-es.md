---
title: Abrir un ticket desde un chat
summary: Convierte una conversación con un cliente en un ticket sin volver a escribirla, mantén el chat abierto hasta que el trabajo esté hecho, y deja que quien lo arregle avise al cliente y cierre la conversación.
date: 2026-09-21
source_date: 2026-09-21
tags: chat, tickets, help desk
category: crm
---

<aside class="tldr">
Cuando una conversación necesita un trabajo que dura más que ella - un fallo, un pedido que falta, algo que hay que dejar por escrito - abre un <b>ticket</b> (ticket) desde el propio chat. El ticket lleva consigo el cliente, la tienda y la conversación, así que nadie tiene que ir a buscar la historia más tarde. Marca <b>Mark as blocked</b> (Marcar como bloqueado) y el chat no se puede cerrar hasta que ese ticket esté en <b>Done</b> (Hecho) o <b>Cancelled</b> (Cancelado), que es cómo una promesa a un cliente deja de olvidarse al final del turno.
</aside>

## Abrirlo

Abre la conversación en <b>Chat</b> (Chat), luego el menú <b>&#8942;</b> arriba a la derecha del hilo, junto a <b>Ignore</b> (Ignorar) y el botón del cliente. Elige <b>Create Ticket</b> (Crear ticket).

El chat pertenece a quien lo lleva, y esto también. Si la conversación está asignada a un compañero, la opción aparece en gris y te dice quién la lleva: hazte cargo del chat primero, o pídeselo a un supervisor. Los supervisores y administradores de organización conservan la opción en cualquier conversación, así que un chat que alguien dejó al irse a casa se puede seguir atendiendo.

## Rellenarlo

<b>Subject</b> (Asunto) es una línea que dice qué falla. Es lo que todo el mundo ve en las listas de tickets, así que escribe el problema en vez del saludo: "Falta el seguimiento del pedido 57316", no "Pregunta del cliente".

<b>Details</b> (Detalles) es lo que el cliente contó, con sus propias palabras. Funciona Markdown, así que <b>**negrita**</b>, listas y enlaces salen como cabría esperar. Pega una captura de pantalla directamente en el cuadro o suelta archivos sobre él - hasta cinco imágenes o documentos.

<b>Priority</b> (Prioridad) se queda en Normal salvo que el cliente esté bloqueado o haya dinero en juego. <b>Kind</b> (Tipo) es Bug (Fallo), Documentation (Documentación) o Data integrity (Integridad de datos); déjalo sin marcar si no estás seguro, un ingeniero lo puede poner después. Nada de este formulario es definitivo - todo se puede cambiar después en el ticket.

La línea de abajo te dice que el ticket se abrirá como ticket de <b>Customer support</b> (Atención al cliente), lo que significa que lleva una referencia CUS y se queda con atención al cliente.

## Mark as blocked (Marcar como bloqueado)

Marcado, esta conversación no se puede cerrar hasta que el ticket esté resuelto o cancelado. Se rechaza finalizar el chat, y el rechazo nombra el ticket que lo está reteniendo.

Solo nos retiene a nosotros. El cliente puede seguir cerrando la conversación desde su lado, y un chat que se queda en silencio se sigue cerrando automáticamente - el objetivo es evitar que <em>nosotros</em> archivemos una conversación cuyo trabajo todavía está pendiente.

Elegir el tipo <b>Bug</b> (Fallo) marca la casilla por ti, porque un fallo suele ser el caso en el que el cliente se queda esperando algo de nosotros. Es una sugerencia, no una regla: desmárcala si este fallo es una nota para más adelante y no algo que ese cliente está esperando.

<b>Cuándo marcarlo.</b> Márcalo cuando al cliente se le debe una respuesta que depende del ticket. Déjalo sin marcar cuando el ticket es tarea nuestra - una nota de documentación, un arreglo interno - y al cliente ya se le ha atendido. Un chat retenido por tarea interna es un chat que nadie cierra nunca.

## Dejar que lo cierre el desarrollador

Al marcar <b>Mark as blocked</b> aparece una segunda opción: <b>Let the developer close this chat when the ticket is settled</b> (Permitir que el desarrollador cierre este chat cuando el ticket se resuelva).

Sin marcar, el chat te espera a ti: el ticket se resuelve, el bloqueo se levanta, y tú avisas al cliente y cierras la conversación.

Marcado, lo hace por ti quien resuelva el ticket. Al ponerlo en <b>Done</b> (Hecho) o al hacer <b>Cancel</b> (Cancelar) escribe una nota de cierre, esa nota se envía a este cliente en esta conversación, y la conversación se cierra. Sale por el canal desde el que escribió - el chat de la web, el correo o WhatsApp - firmada como desarrollador y no con un nombre con el que el cliente nunca ha hablado. Ambos diálogos de cierre avisan a quien escribe de que el cliente lo va a leer.

<b>WhatsApp tiene un límite que no ponemos nosotros.</b> Meta solo admite una respuesta escrita dentro del día siguiente al último mensaje del cliente. Un ticket resuelto más tarde no se puede contestar allí, así que la conversación se cierra sin mensaje y el ticket deja constancia de que no se pudo avisar al cliente. Lo que pasó - avisado y cerrado, cerrado en silencio, o ninguna de las dos - queda escrito en el ticket, porque "se avisó al cliente" y "lo intentamos" no son lo mismo.

<b>Para qué es.</b> Un fallo que el cliente está esperando: se le debe la noticia el día en que se arregla, no cuando un agente vuelva a leer el ticket. Para qué no es: nada cuya respuesta necesite tus palabras y no las de un ingeniero.

## Seguirlo después

La cabecera del hilo cuenta lo que sigue abierto en la conversación. Haz clic en ese contador y el panel lateral se abre en <b>Tickets</b> (Tickets), con todos los tickets abiertos desde este chat, su referencia, estado, tipo y fecha. Haz clic en una fila para leer el ticket sin salir de la conversación.

Cuando uno de ellos está bloqueando, el botón de la cabecera se pone ámbar y lleva un candado, igual que la fila de ese ticket en la lista. Al pasar el ratón por el candado se explica por qué. El botón desaparece en cuanto todo lo abierto desde la conversación está resuelto.

Para quitar el bloqueo, termina el trabajo y pon el ticket en <b>Done</b> (Hecho), o <b>Cancel</b> (Cancelar) si resultó no ser nada. El bloqueo se levanta al momento; no hay nada más que deshacer. Si hay varios tickets bloqueando, hay que resolverlos todos.

## Qué llega al ticket

El cliente y la tienda pasan automáticamente, y quedas registrado como quien lo reportó. Si el fallo necesita a un ingeniero con urgencia, menciónalo por su nombre en un comentario - el ticket dice a quién mencionar y cómo.

El ticket se queda con la conversación misma, debajo de la descripción: quién es el cliente, con un enlace a su ficha, quién llevaba el chat y enlaces de vuelta a él. Abre allí <b>Conversation</b> (Conversación) y se lee el intercambio entero en el ticket, en orden, sin ir a ninguna parte - solo lectura, porque un ticket no es sitio desde el que responder a un cliente.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Abrirlo:</b> <b>Chat</b> &rarr; abre la conversación &rarr; <b>&#8942;</b> &rarr; <b>Create Ticket</b>.</li>
<li><b>Mantener el chat abierto:</b> marca <b>Mark as blocked</b> antes de pulsar <b>Create</b>.</li>
<li><b>Ver qué queda pendiente:</b> el botón contador en la cabecera del hilo &rarr; <b>Tickets</b> en el panel lateral.</li>
<li><b>Levantar el bloqueo:</b> abre el ticket &rarr; <b>Done</b>, o <b>Cancel</b>.</li>
<li><b>Que el desarrollador avise al cliente:</b> marca también <b>Let the developer close this chat</b> antes de pulsar <b>Create</b>.</li>
<li><b>Leer la conversación en el ticket:</b> abre el ticket &rarr; <b>Conversation</b>, debajo de la descripción.</li>
</ul>
</aside>
