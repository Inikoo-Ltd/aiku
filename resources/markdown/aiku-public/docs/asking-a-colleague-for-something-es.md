---
title: Pedir algo a un compañero o a un departamento
summary: Levanta una tarea en lugar de un mensaje de Slack cuando necesitas que alguien de otro equipo haga algo - tiene un responsable, un hilo y un final - y gestiona las tareas que otros te han enviado.
date: 2026-09-16
source_date: 2026-09-16
tags: tasks, chat, messaging, help desk
category: help-desk
---

<aside class="tldr">
Una <b>task</b> (tarea) es una petición de un miembro del personal a otro, o a todo un departamento: actualiza este banner, comprueba este producto contra su foto, envía la factura que falta. No es un ticket, los tickets son para cosas que están rotas en aiku y van a los ingenieros. Una tarea tiene un responsable, un hilo de chat, y termina en <b>Done</b> (hecho) o <b>Can't be done</b> (no se puede hacer). Hacen falta dos cosas para levantar una: qué hay que hacer y a quién se lo pides.
</aside>

## Por qué tareas y no Slack

Una petición escrita en Slack no tiene responsable ni final. Se pierde en el desplazamiento, nadie sabe si se ha visto, y una semana después alguien vuelve a preguntar. Una tarea es la misma frase con un responsable y un final, para que ambas partes puedan dejar de preocuparse por ella. La conversación sigue pasando en el chat: cada tarea abre una conversación de chat interno con su mismo nombre, con la misma traducción, respuestas, fotos y respuestas rápidas descritas en [Hablar con compañeros en el chat interno](/docs/staff-chat-es).

## Levantar una tarea

Abre <b>Tasks</b> (tareas) en el menú de la izquierda y pulsa <b>New task</b> (nueva tarea). Escribe qué hay que hacer, y elige una <b>persona</b> o un <b>departamento</b>. Con eso basta. Los detalles, una fecha límite y una urgencia están ahí si los necesitas, pero la mayoría de tareas no los necesitan.

Dos atajos ahorran el viaje a la página de Tasks:

- <b>Desde la página a la que se refiere.</b> Las páginas de producto, cliente, pedido y albarán tienen un botón <b>Task</b> en la cabecera. Una tarea levantada ahí queda enlazada a ese registro, así que quien la recoja llega al producto o pedido correcto con un solo clic, y la página muestra una etiqueta por cada tarea abierta sobre ella.
- <b>Desde un mensaje de chat.</b> Pasa el ratón por encima de cualquier mensaje del chat interno y pulsa <b>task</b>. El mensaje se convierte en el asunto, y una respuesta en la conversación original dice qué tarea se ha levantado, para que quien lo pidió pueda seguirla.

## Persona o departamento

Pide a una <b>persona</b> cuando sabes quién debe hacerlo. Le llega la tarea directamente y aparece en <b>Assigned to me</b> (asignadas a mí) en su página de Tasks.

Pide a un <b>departamento</b> cuando no lo sabes, por ejemplo al almacén o a marketing. La tarea se queda en la cola de ese departamento, visible para todos en él bajo <b>My department</b> (mi departamento). Quien la recoja pulsa <b>I will do it</b> (me encargo yo) y se convierte en el responsable. Los departamentos vienen de los puestos de trabajo, no hay nada que configurar.

A los ingenieros y a QA no se les puede asignar una tarea. Cualquier cosa para ellos es un ticket, ver [Pedir ayuda a atención al cliente técnica](/docs/asking-the-help-desk-for-help-es).

## Gestionar las tareas que te envían

La página de Tasks tiene tres vistas:

- <b>Assigned to me</b> (asignadas a mí): tareas que son tuyas.
- <b>My department</b> (mi departamento): la cola de tu departamento, esperando que alguien las recoja.
- <b>I asked for</b> (las que yo pedí): tareas que has levantado, para que veas cómo van.

Cada tarea tiene cuatro botones: <b>I will do it</b> (me encargo yo) para reclamarla, <b>Working on it</b> (trabajando en ello), <b>Done</b> (hecho), y <b>Can't be done</b> (no se puede hacer), que te pide un motivo breve. Cada cambio se publica en el hilo de la tarea, así que quien la pidió lo ve sin que nadie le avise. El icono del hilo abre la conversación para hacer una pregunta, adjuntar una foto o decir que está terminada.

<b>Show closed</b> (mostrar cerradas) lista lo que se terminó o se abandonó.

## Cuando no pasa nada

Una tarea que lleva dos días en silencio recibe un aviso: se recuerda al responsable, o si aún no la ha recogido nadie, se avisa a los supervisores del departamento de que está esperando. El aviso se repite cada dos días hasta que la tarea avanza. No hay nada que configurar ni otro plazo; una fecha límite en una tarea es información para quien la hace, no una regla.

## El tablero y los informes

<b>Board</b> (tablero) muestra las mismas tareas como columnas, Todo (pendiente), Working on it (trabajando en ello), Done (hecho), Can't be done (no se puede hacer), para el periodo elegido arriba, con filtros por departamento, urgencia y responsable. Los supervisores pueden arrastrar una tarjeta de una columna a otra para cambiar su estado; el resto solo puede ver el tablero.

<b>Reports</b> (informes) muestra, para el periodo elegido, cuántas tareas se han levantado y terminado, cuánto tardan, cuántas llevan abiertas más de una semana, y los mismos números por departamento, por responsable y por quien las pidió. <b>All</b> (todas) es la lista simple de todas las tareas con filtros y búsqueda.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Levantar una tarea:</b> <b>Tasks</b> → <b>New task</b>, o el botón <b>Task</b> en una página de producto, cliente, pedido o albarán, o pasa el ratón por un mensaje de chat → <b>task</b>.</li>
<li><b>Ver lo que es tuyo:</b> <b>Tasks</b> → <b>Assigned to me</b>.</li>
<li><b>Recoger una tarea de tu departamento:</b> <b>Tasks</b> → <b>My department</b> → <b>I will do it</b>.</li>
<li><b>Terminar una:</b> <b>Done</b>, o <b>Can't be done</b> explicando por qué.</li>
<li><b>Hablar de ella:</b> el icono del hilo en la tarea, o la conversación con su mismo nombre en la barra de mensajes.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Cualquiera que pueda entrar en aiku puede levantar tareas, recibir tareas y ver la página de Tasks, el tablero y los informes. Los ingenieros y QA ven todo pero no se les puede asignar una tarea. Los supervisores, cualquiera con un puesto de trabajo de supervisor, pueden mover tarjetas en el tablero; para el resto el tablero solo se puede ver. Los departamentos para los que puedes recoger tareas dependen de tus puestos de trabajo.
</aside>
