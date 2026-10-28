---
title: Responder correos en Chat
summary: El correo al buzón de la tienda llega como una conversación junto al chat de la web y WhatsApp, con todo el hilo, y tu respuesta sale desde la misma dirección con tu firma y tus adjuntos. Los clientes reales que Gmail pone en spam también llegan, con sus archivos retenidos por seguridad.
date: 2026-10-01
source_date: 2026-10-01
tags: crm, chat, email
category: help-desk
series: Chat
order: 3
---

<aside class="tldr">
Una vez conectado el buzón de la tienda, todo correo que le llega se convierte en una conversación en <b>Customer Inbox</b> (Bandeja del cliente), junto al chat de la web y WhatsApp, y se responde de la misma forma. Ves el hilo completo, no solo el último correo, y tu respuesta sale desde la dirección de la tienda en el mismo hilo, con tu firma y tus adjuntos.
</aside>

## Antes de que funcione

El buzón de la tienda tiene que conectarse una vez - consulta <a href="/docs/connecting-an-email-mailbox-to-your-shop-es">Conectar un buzón de correo a tu tienda</a>. Hasta entonces el canal de email se queda vacío.

## Leer y responder

Un correo se suma a <b>Waiting</b> (Esperando) como cualquier conversación, y <b>Assign to me</b> (Asignarme) la hace tuya - consulta <a href="/docs/customer-chat-es">Hablar con clientes en Chat</a> para el resto. Todo el hilo llega con él. Tu respuesta se envía desde el buzón de la tienda en el mismo hilo, y cuando el cliente responde, vuelve a la misma conversación, no a una nueva.

## Adjuntar archivos

Haz clic en el clip, o suelta los archivos sobre la conversación. Puedes adjuntar imágenes (JPG, PNG, WebP, AVIF), PDF, Word, Excel, CSV, texto, PowerPoint y archivos zip, hasta 30 a la vez y de 100 MB cada uno. Lo mismo vale para <b>New email</b>. Los archivos van adjuntos al correo mientras quepan en unos 18 MB entre todos; el resto va en el correo como enlaces de descarga, porque los servicios de correo rechazan un correo de más de unos 25 MB.

Una imagen pegada o soltada en el texto se queda donde la pusiste, dentro del correo, como en Gmail. Borrarla del texto la saca del correo. Cualquier otro archivo aparece encima del cuadro del mensaje antes de enviar, cada uno con una <b>x</b> para quitarlo.

Algunas imágenes no se pueden recoger, y el cuadro lo dice en rojo debajo en lugar de perderlas:

- Una imagen copiada junto con texto, de otro correo, de Word o de una página web, entra después del texto cuando la copia la lleva. Si no la lleva, copia la imagen sola, o guárdala y adjúntala con el clip.
- Una imagen arrastrada directamente desde una página web: guárdala primero y después arrastra el archivo.
- Una imagen pegada solo vive en la página abierta. Si la página se recarga antes de enviar, el texto de tu respuesta vuelve pero la imagen no, y el cuadro te pide que la pegues de nuevo.

## Saber que tu respuesta salió

Debajo de tu respuesta ves dónde está:

- <b>Sending by email…</b> (Enviando por correo) mientras se entrega a Gmail, normalmente unos segundos.
- Una marca cuando Gmail la ha aceptado. Pasa el ratón por encima para leer <b>Sent: Gmail took it</b> (Enviado: Gmail lo aceptó).
- <b>Not sent</b> (No enviado) en rojo con el motivo, y una nota roja en la conversación, cuando Gmail la rechazó en cada intento. Arregla lo que dice el motivo, a menudo un buzón que hay que volver a conectar en los ajustes de la tienda, y haz clic en <b>Send again</b> (Enviar de nuevo).
- <b>Not confirmed by Gmail yet</b> (Gmail aún no lo ha confirmado) en ámbar cuando lleva más de 15 minutos esperando. Mira la carpeta <b>Enviados</b> del Gmail de la tienda antes de enviarla de nuevo.

Que Gmail acepte el correo es hasta donde aiku puede ver. Si después el servidor de correo del cliente lo rechaza, unos minutos más tarde llega a la conversación una nota roja <b>Email not delivered to ...</b> (Correo no entregado a ...).

## Todas las imágenes y archivos de una conversación

El menú de arriba de la conversación &rarr; <b>Pictures and files</b> (Imágenes y archivos) muestra todo lo que el cliente y el equipo enviaron en ella, lo más nuevo primero: las imágenes en una cuadrícula que puedes abrir en grande y recorrer con las flechas del teclado, y los demás archivos en una lista con <b>Download</b> (Descargar).

## Visitantes de la web en la lista de correo

Algunas conversaciones de la lista de correo empezaron en la web. Cuando una tienda tiene activado <b>Answer offline messages by email</b> (Responder mensajes fuera de horario por correo), un mensaje dejado en el widget fuera del horario de trabajo se traslada a correo para que la respuesta llegue al cliente. Al abrirla, el mensaje del cliente muestra una etiqueta <b>Offline message</b> (Mensaje fuera de horario); a partir de ahí se responde como cualquier otro correo. Consulta <a href="/docs/live-chat-on-the-website-es">Chat en directo en la web</a>.

## Correo que Gmail puso en spam

Gmail a veces pone el correo real de un cliente en su carpeta de spam. aiku también revisa esa carpeta, para que tú no tengas que hacerlo:

- El correo de un cliente de esta tienda que nos ha comprado, o una respuesta en una conversación que ya tienes, siempre llega.
- El correo de cualquier otra persona se lee de forma automática y llega solo cuando parece un cliente preguntando por un pedido o un cliente nuevo que quiere comprar. Los anuncios, los proveedores que nos venden a nosotros, las estafas y el correo automático se quedan en el spam de Gmail, y Gmail los borra a los 30 días.

Alguien que se registró en la web pero nunca ha comprado se trata como cualquier otra persona, porque cualquiera puede registrarse.

Un correo que salió del spam tiene una nota ámbar en la parte superior. Empieza con lo que aiku pensó que era el correo, como <b>New customer enquiry</b> (Nueva consulta de cliente) o <b>Customer request</b> (Solicitud de cliente), así que léelo teniendo eso en cuenta. Cuando el correo parece una estafa habitual - una copia de un pago para descargar, una factura o un pedido detrás de un enlace, un aviso de contraseña o de cuenta - se añade una etiqueta roja <b>Possible scam</b> (Posible estafa), aunque venga de un cliente. Trata esos casos con especial cuidado: el buzón de un cliente puede estar comprometido.

Sus adjuntos se quedan en Gmail por tu seguridad: los archivos en spam pueden llevar virus o facturas falsas. El correo muestra <b>archivo(s) guardado(s) en Gmail por seguridad</b>, y solo se incorporan a la conversación cuando haces clic en <b>Show attachments</b> (Mostrar adjuntos). Responder no los trae. Ábrelos solo si los esperabas. Si el correo resulta ser basura, <b>Report spam</b> (Denunciar spam) bloquea al remitente.

## Correo que nunca llega

aiku lee la bandeja de entrada del buzón y su carpeta de spam, y archiva el correo que no es trabajo de clientes en carpetas propias en vez de en la lista de chat. Cuando parezca que falta un correo, consulta las carpetas de Gmail en <a href="/docs/chat-inbox-labels-es">Qué significan las etiquetas de la bandeja de chat</a>. Un correo genuino que se quedó en el spam de Gmail todavía se puede traer: ábrelo en Gmail y haz clic en <b>Not spam</b> (No es spam), y llega en unos minutos.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Conectar el buzón:</b> <b>Settings</b> (Ajustes) de la tienda &rarr; <b>Customer mailbox</b>.</li>
<li><b>Responder un correo:</b> <b>Chat &rarr; Customer Inbox</b> &rarr; enciende el cuadro de email de tu tienda &rarr; una conversación en <b>Waiting</b> &rarr; <b>Assign to me</b>.</li>
<li><b>Adjuntar archivos a una respuesta:</b> el clip bajo el cuadro del mensaje, o suelta los archivos sobre la conversación.</li>
<li><b>Enviar de nuevo una respuesta que Gmail rechazó:</b> debajo de la respuesta &rarr; <b>Send again</b>.</li>
<li><b>Ver todas las imágenes y archivos de una conversación:</b> el menú de arriba de la conversación &rarr; <b>Pictures and files</b>.</li>
<li><b>Abrir los archivos de un correo que salió del spam:</b> el correo en la conversación &rarr; <b>Show attachments</b>.</li>
<li><b>Encontrar un correo que nunca llegó a la bandeja:</b> el Gmail de la tienda &rarr; <b>aiku/filtered</b>, y después el <b>Spam</b> de Gmail.</li>
</ul>
</aside>
