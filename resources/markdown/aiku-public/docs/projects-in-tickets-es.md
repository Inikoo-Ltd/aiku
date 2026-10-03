---
title: Proyectos en los tickets
summary: Agrupa los tickets de un trabajo grande en un proyecto, mira cuánto ha avanzado y publica las novedades en un solo sitio en vez de en mensajes de chat.
date: 2026-10-03
source_date: 2026-10-03
tags: tickets, projects, help desk
category: help-desk
---

<aside class="tldr">
Un trabajo grande - por ejemplo un traslado de almacén - son muchos tickets y tareas. Un <b>proyecto</b> (project) los mantiene juntos con un objetivo, un equipo, hitos y una barra de progreso. Cualquiera puede ver cómo va, y el equipo publica <b>novedades</b> (progress updates) con una marca de salud en la página del proyecto en lugar de enviar mensajes de chat.
</aside>

## Encontrar los proyectos

En el menú izquierdo abre <b>Tickets</b> (Tickets) y luego <b>Projects</b> (Proyectos) en el menú superior. Cada proyecto es una tarjeta con su salud, estado, barra de progreso, responsable y equipo. Haz clic en uno para abrirlo.

## Empezar un proyecto

Pulsa <b>New project</b> (Nuevo proyecto) y rellena <b>Name</b> (Nombre), <b>Goal</b> (Objetivo: cómo se ve el trabajo terminado), <b>Start</b> (Inicio), <b>Target</b> (Meta: la fecha en que quieres terminarlo), <b>Owner</b> (Responsable) y <b>Team</b> (Equipo). Pulsa <b>Create</b> (Crear). Cualquiera puede empezar un proyecto.

## La página del proyecto

Arriba, una franja muestra la <b>Health</b> (Salud: la marca de la última novedad que tiene una), el <b>Status</b> (Estado), el <b>Owner and team</b> (Responsable y equipo), las barras de progreso y los contadores <b>Open</b> (Abiertos), <b>Done</b> (Hechos) y <b>Days left</b> (Días restantes). Los botones <b>New ticket</b> (Nuevo ticket) y <b>New task</b> (Nueva tarea) crean trabajo que ya está dentro del proyecto. <b>Edit</b> (Editar) cambia los datos del proyecto (solo para quien puede cambiarlo, ver más abajo).

La página tiene cinco pestañas: <b>Overview</b> (Resumen), <b>Work</b> (Trabajo), <b>Timeline</b> (Cronología), <b>Commits</b> (Commits) y <b>Activity</b> (Actividad).

## Meter trabajo

Los tickets y las tareas cuentan juntos. En la pestaña <b>Work</b>:

- <b>Pegar referencias:</b> escribe en el cuadro las referencias, por ejemplo HELP-123, TASK-45 - varias a la vez, tickets y tareas mezclados, está bien. Si quieres, elige un hito y pulsa <b>Add</b> (Añadir).
- <b>New ticket</b> o <b>New task:</b> arriba en la página.
- <b>Desde un ticket o una tarea:</b> el panel lateral tiene una etiqueta <b>Project</b> (Proyecto) y otra de hito. Haz clic para elegir un proyecto o un hito, o elige <b>No project</b> (Sin proyecto) o <b>No milestone</b> (Sin hito) para quitarlo.

Para sacar un elemento desde la página del proyecto, haz clic en la <b>x</b> a su lado.

## Pestaña Work

Busca y filtra por <b>Tickets and tasks</b> (Tickets y tareas), hito, persona (<b>Everyone</b> (Todos) o <b>Unassigned</b> (Sin asignar)), y marca <b>Show closed</b> (Mostrar cerrados) para incluir el trabajo terminado. Cambia entre <b>List</b> (Lista) y <b>Board</b> (Tablero; columnas <b>Todo</b> (Por hacer), <b>In progress</b> (En curso), <b>Done</b> (Hecho) y <b>Cancelled</b> (Cancelado) cuando se muestra lo cerrado). En la lista el trabajo se agrupa por hito y cada fila tiene su propio selector de hito.

El trabajo que no puedes ver no aparece; la página indica cuántos elementos confidenciales están ocultos.

## Pestaña Overview

- <b>Goal</b> (Objetivo) del proyecto.
- <b>Progress updates</b> (Novedades): escribe qué ha avanzado, qué está bloqueado y qué viene después, marca si quieres <b>On track</b> (En camino), <b>At risk</b> (En riesgo) u <b>Off track</b> (Fuera de camino) y pulsa <b>Post update</b> (Publicar novedad). Las novedades quedan en orden, la más reciente primero. El responsable y el resto del equipo reciben aviso al momento, en la campana de Aiku y por correo; quien la publica, no.
- <b>Milestones</b> (Hitos): una lista con fechas de inicio y límite y una barrita de progreso por hito (elementos hechos de elementos totales). Márcalo para terminarlo; cambia el nombre, el orden o elimínalo con los controles pequeños. Con la fecha vencida y sin marcar aparece como <b>Overdue</b> (Vencido).
- <b>Workload</b> (Carga de trabajo): por persona, cuántos elementos hay por hacer, en curso y hechos.

## Timeline, Commits y Activity

- <b>Timeline:</b> los hitos sobre un calendario con una línea <b>Today</b> (Hoy), y un gráfico <b>Burn-up</b> que compara todo el trabajo (<b>Scope</b>) con lo <b>Done</b> semana a semana y una línea <b>Ideal</b>.
- <b>Commits:</b> los cambios de código que resolvieron los tickets del proyecto, con <b>Version</b> (Versión), <b>Deployed</b> (Desplegado), <b>Commit</b>, <b>Subject</b> (Asunto) y el <b>Ticket</b>. Aparecen solos cuando se despliega la solución de un ticket.
- <b>Activity:</b> lo que ha pasado, lo más reciente primero: trabajo añadido o terminado, novedades, despliegues. Se construye automáticamente.

## Leer el progreso

Las barras de progreso muestran cuánto del trabajo está hecho y cuánto hemos avanzado entre el inicio y la meta. La barra de trabajo se pone roja cuando va más de 10 puntos por detrás del tiempo. Pasada la fecha meta, cuenta los días de retraso. El trabajo cancelado no entra en los totales.

## Quién puede cambiar qué

El responsable, los miembros del equipo y los ingenieros que pueden asignar tickets pueden editar el proyecto (<b>Edit</b>, añadir o sacar trabajo, publicar novedades, cambiar hitos). Los demás solo pueden leerlo.

Meter o sacar un ticket o una tarea de un proyecto desde su propio panel lateral también está abierto a quienes trabajan en ello: en un ticket, quienes pueden actualizarlo y sus colaboradores; en una tarea, la persona asignada, los colaboradores y quien la pidió. Cualquier otra persona solo puede hacerlo si puede cambiar tanto el proyecto en que está como aquel al que va.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Ver proyectos:</b> <b>Tickets</b> &rarr; <b>Projects</b>.</li>
<li><b>Empezar uno:</b> <b>New project</b> &rarr; rellena el formulario &rarr; <b>Create</b>.</li>
<li><b>Añadir trabajo existente:</b> abre el proyecto &rarr; <b>Work</b> &rarr; escribe las referencias &rarr; <b>Add</b>.</li>
<li><b>Añadir trabajo nuevo:</b> abre el proyecto &rarr; <b>New ticket</b> o <b>New task</b>.</li>
<li><b>Meter o sacar trabajo:</b> abre el ticket o la tarea &rarr; etiqueta <b>Project</b> en el panel lateral.</li>
<li><b>Informar del progreso:</b> abre el proyecto &rarr; <b>Overview</b> &rarr; escribe en el cuadro &rarr; <b>Post update</b>.</li>
</ul>
</aside>
