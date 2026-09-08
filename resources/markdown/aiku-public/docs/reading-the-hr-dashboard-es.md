---
title: Cómo leer el panel de HR
summary: La primera pantalla de HR — quién está hoy, quién llegó tarde, quién está de baja y por qué, más las ausencias de la semana, los tipos de ausencia del mes y los cumpleaños. Cada número es un enlace a las personas que hay detrás.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, attendance, leave, clocking
category: hr
---

<aside class="tldr">
Abre <b>HR</b> y aterrizas aquí. La franja superior cuenta lo que contiene tu módulo de HR. Las cinco tarjetas de debajo responden a la pregunta diaria — <em>quién está, quién llegó tarde, quién está de baja, quién falta</em> — y al pulsar cualquier tarjeta aparecen los nombres. La tabla bajo las tarjetas es la asistencia del día, y puedes retroceder a cualquier día pasado. La fila inferior trata de ausencias y cumpleaños. Si solo gestionas a las personas, lee las secciones de asistencia. Si además llevas las máquinas de fichaje, mira <a href="/docs/setting-up-a-clocking-machine-es">Configurar una máquina de fichaje</a> para saber de dónde salen las cifras de "presente" y "tarde".
</aside>

Ábrelo en **tu organización → HR**. El panel es la página de inicio de HR, así que también es adonde te lleva el enlace HR de la barra lateral.

## La franja superior: qué contiene el módulo

Seis contadores pequeños, cada uno un enlace a su propia lista:

- **Employees** — personas actualmente *trabajando* (no las que se han ido o aún no han empezado). Abre la lista de empleados ya filtrada por personal en activo.
- **Working places** — los centros donde la gente ficha.
- **Responsibilities** — las posiciones de trabajo que las personas pueden ocupar.
- **Clocking machines** — los quioscos y puntos QR que registran las llegadas.
- **Timesheets** — cada jornada registrada.
- **Staff chat** — mensajes de los últimos treinta días; abre la analítica del chat.

Esto es inventario, no estado. Nada de aquí cambia durante el día.

## Las cinco tarjetas: quién está dónde hoy

Esta fila es la parte que hay que leer cada mañana. El título de cada tarjeta dice **today** mientras miras el día de hoy, y pierde la palabra cuando retrocedes a una fecha anterior.

- **Present** — empleados con un timesheet del día, es decir, que ficharon al menos una vez. Quien llegó y ya se fue sigue contando como presente.
- **Annual leave** — empleados con una ausencia *aprobada* cuyo tipo pertenece a la categoría anual y que cubre ese día.
- **Sick leave** — lo mismo, para los tipos de ausencia de la categoría médica.
- **Late** — empleados presentes cuyo *primer* fichaje del día fue tarde. El retraso se decide en el momento de fichar: más tarde que la hora de inicio programada más quince minutos de cortesía. El personal a tiempo parcial y los días marcados como no laborables en el horario nunca cuentan como retraso.
- **Absent** — empleados en activo que ni han fichado ni tienen una ausencia aprobada que cubra el día. Esta es la tarjeta que importa: es la lista de personas a las que quizá tengas que llamar.

**Pulsa cualquier tarjeta** para ver los nombres. La tarjeta se marca con un borde resaltado y la tabla de abajo cambia a ese grupo:

- Present y Late muestran la tabla de asistencia (Late muestra solo las filas de retraso).
- Annual leave y Sick leave muestran a cada persona con su tipo de ausencia y sus fechas.
- Absent muestra a cada persona y su puesto. Pulsa un nombre para abrir al empleado.

Un enlace **Show all** junto al título de la tabla limpia la selección. La selección vive en la dirección de la página, así que puedes enviar a un compañero el enlace de "ausentes hoy".

Dos cosas sobre la aritmética. Un empleado de ausencia que ficha igualmente aparece en Present *y* en su tarjeta de ausencia. Y Present cuenta a cualquiera que haya fichado, incluida una persona cuyo estado de empleo no sea "working" — así que en un día con visitas o bajas las tarjetas no tienen por qué cuadrar exactamente con el contador Employees.

## La tabla de asistencia

Bajo las tarjetas, la asistencia del día, **primero las llegadas más tempranas**. Cada fila es el día de un empleado:

- **Name** y puesto. El nombre abre el timesheet. La imagen de al lado es la foto del registro del empleado o, si no la hay, el avatar que la persona haya puesto en su propio perfil de aiku; sin ninguna de las dos, son sus iniciales sobre un círculo liso. aiku nunca inventa una cara para nadie.
- **Start at** — primer fichaje de entrada, en rojo cuando fue tarde.
- **End at** — último fichaje de salida, o *Still working* si su última acción fue una entrada.
- **Status** — *Late*, *Working* (aún en el centro) u *On time*.
- **Notes** — lo que la persona escribió en la máquina de fichaje en su primera entrada, si escribió algo. A quien llega tarde se le suele pedir un motivo; aterriza aquí.
- **Working** y **Breaks** — horas y minutos acumulados, según el timesheet.
- **Clock in** y **Clock out** — cuántos de cada uno. Una diferencia de uno significa que sigue dentro.

### Mirar otro día

Las flechas y el selector de fecha junto al título mueven toda la vista — tarjetas y tabla — a ese día. No puedes pasar de hoy. La píldora verde **N present** y los contadores de las tarjetas siguen la fecha elegida, así que "Absent" del martes pasado es exactamente quién faltó el martes pasado. **Today** te devuelve.

## Ausencias y cumpleaños

La fila inferior no depende de la fecha elegida; siempre describe el ahora.

- **Leave overview** — una barra por día laborable, de lunes a viernes de la semana actual, con cuántos empleados tienen ausencia aprobada ese día. La barra de hoy es verde.
- **Employee leaves** — las próximas veinte ausencias aprobadas que aún no han terminado, las más cercanas primero, con el tipo y las fechas. Esta es tu lista de "quién se va pronto".
- **Leave types** — un donut de las ausencias aprobadas de este mes, una porción por tipo, contando empleados y no días. El centro muestra el total de empleados con alguna ausencia este mes.
- **Birthdays this month** — empleados en activo que cumplen años este mes, por fecha, con una tarta en el de hoy.

Las ausencias aún *pendientes* de aprobación no aparecen en ninguna parte de esta página. Apruébalas antes en **Leave requests**.

## Acciones rápidas

El panel de la derecha son las cuatro cosas que HR hace más a menudo:

- **Create employee** — el formulario descrito en <a href="/docs/setting-up-a-new-employee-es">Configurar un nuevo empleado</a>.
- **Record leave** — registrar una ausencia en nombre de alguien; queda aprobada al guardarla, así que cuenta en el panel de inmediato.
- **Leave requests** — la cola de lo que el personal ha pedido.
- **Leave calendar** — la vista mensual de quién está de baja.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>El panel:</b> tu organización → <b>HR</b>.</li>
<li><b>Quién está ausente:</b> pulsa la tarjeta <b>Absent</b>; pulsa un nombre para abrir al empleado.</li>
<li><b>Por qué alguien llegó tarde:</b> pulsa la tarjeta <b>Late</b> y lee la columna <b>Notes</b>, o abre el timesheet desde su nombre.</li>
<li><b>Una foto en lugar de iniciales:</b> abre el empleado → <b>Edit</b> → <b>Photo</b> (con permisos de edición de HR). Quien tenga login de aiku también puede poner su propio avatar en <b>Profile</b>.</li>
<li><b>Otro día:</b> las flechas o el selector de fecha sobre la tabla; <b>Today</b> para volver.</li>
<li><b>La hora de inicio contra la que se mide el retraso:</b> <b>HR → Shift Schedules</b>. Los quince minutos de cortesía son fijos; pregunta al soporte de aiku si tu organización necesita otro margen.</li>
<li><b>Llevar una ausencia pendiente al panel:</b> <b>HR → Leave requests</b> → aprobar.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Ver el panel requiere permisos de <b>HR view</b> en la organización, o el rol de supervisor de HR.</li>
<li>Las acciones rápidas (crear empleados, registrar ausencias) requieren permisos de <b>HR edit</b>.</li>
</ul>
</aside>
