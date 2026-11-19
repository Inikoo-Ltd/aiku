---
title: Responder a clientes con tu asistente de IA
summary: Conecta a aiku el asistente de IA que ya usas y pídele ayuda con un chat. Lee la conversación con todo lo que aiku sabe (el cliente, sus pedidos, los productos mencionados, las normas de la tienda y el idioma en el que responder) y guarda en el chat un borrador de respuesta para que lo revises y lo envíes desde aiku.
date: 2026-10-15
source_date: 2026-10-15
tags: crm, chat, ai
category: help-desk
series: Chat
order: 6
---

<aside class="tldr">
Para atención al cliente. En cuanto un administrador te lo active, el asistente de IA que conectes a aiku puede enumerar los chats que te esperan y leer uno con todo lo que aiku sabe de él. Dile qué quieres que diga la respuesta y la redacta en el idioma del cliente y la guarda en el chat como <b>borrador</b>. Tú lees el borrador en aiku, cambias lo que quieras y pulsas enviar tú mismo. Nada llega al cliente hasta que lo haces.
</aside>

## Para qué sirve

Las sugerencias que aiku escribe por su cuenta solo tienen un intento, con los datos elegidos de antemano. Tu asistente puede ir más lejos: lee toda la conversación, los pedidos del cliente, los productos que nombra y las normas de la tienda, y puede preguntarte qué quieres decir. Tú sigues al mando: pides ayuda solo cuando quieres, y decides qué sale.

> *"Abre el chat más antiguo que me está esperando. El cliente dice que le han llegado rotas dos tazas. Escribe una respuesta que pida disculpas y pida una foto de cada taza y de la caja."*

Funciona con cualquier asistente que pueda conectarse a aiku. Háblale en tu idioma; él responde al cliente en el suyo.

## Antes de empezar

**1. Pide a un administrador que lo active.** En tu cuenta de usuario, en <b>Access</b> (Acceso), activa <b>Can connect AI assistant</b> (Puede conectar un asistente de IA) y después <b>Can serve customers</b> (Puede atender a clientes).

**2. Necesitas tu permiso habitual de chat en la tienda.** El asistente solo ve los chats de las tiendas en las que trabajas el chat, y solo guarda borradores en chats que son tuyos o que nadie ha tomado.

**3. Conecta tu asistente a aiku.** Añade aiku como conector en tu asistente con la dirección `https://app.aiku.io/mcp/aiku` e inicia sesión con tu cuenta de aiku cuando te lo pida. Solo lo haces una vez.

## Cómo es una conversación

1. **Pregunta qué está esperando.** *"¿Qué chats me están esperando?"* El asistente enumera los chats de tus tiendas en los que el último mensaje del cliente no tiene respuesta, llevados por ti o por nadie, empezando por el que más lleva esperando. Cada uno tiene su Chat ID, la tienda, quién escribió y hace cuánto.
2. **Abre uno.** *"Abre el primero."* También puedes darle un Chat ID copiado del panel <b>Overview</b> (Resumen) de un chat.
3. **Dile qué debe decir la respuesta.** El asistente la redacta con los datos que le dio aiku.
4. **Guarda el borrador en el chat.** Abre el chat en aiku: el borrador está en el cuadro de IA encima del campo de mensaje, marcado con tu nombre y el asistente que usaste. Pulsa <b>Use</b> (Usar) para ponerlo en el campo de mensaje, cambia lo que quieras y envíalo. O pulsa <b>Discard</b> (Descartar).

El asistente siempre escribe una respuesta completa. Donde decidirías tú, por ejemplo un abono o una reposición, escribe lo que suele decidir el equipo de tu tienda, y la lista bajo el borrador marca esa pregunta como "nuestra decisión habitual, revísala". Cámbiala si este caso es distinto. Aprende las respuestas habituales de tu tienda de las que tu equipo ya ha enviado.

<aside class="tip">
Pídele que te explique antes de escribir: <i>"¿Qué sabemos de este pedido?"</i> Leer nunca cambia nada.
</aside>

## Lo que recibe el asistente con cada chat

| Qué | Para qué |
|---|---|
| **La conversación** | Los últimos 30 mensajes. Las palabras del cliente se tratan como palabras del cliente, nunca como instrucciones para el asistente. |
| **El idioma en el que responder** | aiku lo decide por lo que escribió el cliente. Un borrador en otro idioma se rechaza. |
| **El cliente** | Nombre, referencia, saldo y últimos pedidos, cuando el chat está vinculado a un cliente. |
| **Lo que aiku buscó** | Los pedidos y productos que el cliente menciona, por código o con palabras, y las respuestas a preguntas habituales como los gastos de envío o las formas de pago. Pedidos solo de un cliente que aiku ya conoce. |
| **Las normas de la tienda** | La política de chat de la tienda, sus notas y guías completas, las páginas de política que responden a lo que pregunta el cliente, y los títulos del resto. |
| **Conversaciones anteriores** | Tema y resumen de las últimas conversaciones del cliente. |

## Lo que puede y no puede hacer

**Puede** enumerar tus chats en espera, leer un chat y guardar en él un borrador de respuesta.

Con el mismo interruptor también puede actuar sobre un chat por ti, pero en cada caso te muestra siempre exactamente lo que va a hacer y solo sigue adelante después de que digas que sí con tus propias palabras: enviar una respuesta, tomar, cerrar o poner en espera un chat, vincular al cliente, resolver una reclamación y cambiar un pedido que aún no está empaquetado. Consulta [Hablar con clientes en Chat](/docs/customer-chat-es).

**No puede** ver chats de tiendas en las que no trabajas el chat, guardar un borrador en el chat de un compañero ni enviar nada sin tu sí.

## Conviene saber

- **El borrador sustituye a cualquier borrador que ya esté esperando en ese chat**, incluido uno que aiku escribió por su cuenta.
- **Tu responsable ve cómo se usan los borradores.** Chat › AI muestra cuántos borradores de los asistentes del personal se enviaron tal cual, se cambiaron o se descartaron, por asistente.
- **Lo que compartes va al proveedor de tu asistente.** La conversación y los datos del cliente se envían a la empresa que está detrás del asistente que conectes. Que lo tengas activado significa que tu administrador ha aceptado eso por ti.
