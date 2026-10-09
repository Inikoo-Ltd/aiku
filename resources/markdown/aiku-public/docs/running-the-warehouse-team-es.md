---
title: Gestionar el equipo del almacén
summary: La página Equipo del almacén — un panel para quien dirige el centro (quién está dentro ahora, el trabajo pendiente, hoy frente a un día habitual, los últimos 30 días y, por persona, artículos por hora trabajada, short picks y retrasos) con una pestaña Clockings para ver los fichajes de un día por persona, cerrar salidas olvidadas y añadir o corregir un fichaje sin pasar por HR.
date: 2026-10-07
source_date: 2026-10-07
tags: warehouse, clocking, picking, packing, performance
category: dispatch
---

<aside class="tldr">
Abre <b>tu almacén → Equipo</b>. La pestaña <b>Panel Control</b> muestra el centro ahora mismo (en el centro, fichó la salida, aún no ha llegado, no fichó la entrada, de baja, día libre), el trabajo pendiente de picking y packing, las cifras del equipo de hoy, ayer, 7 o 30 días frente al periodo anterior, gráficos de hoy por horas frente a un día habitual y de los últimos 30 días, y una tabla de Personas ordenada por artículos por hora trabajada con short picks y entradas tarde. La pestaña <b>Marcaciones de tiempo</b> muestra los fichajes de un día por persona, marca las entradas que nunca se cerraron y es donde se añade, cambia o borra un fichaje. Solo ven esta página los supervisores de almacén y quienes pueden editar HR.
</aside>

Ábrela en **tu almacén → Equipo**, el último elemento de la sección de almacén de la barra lateral.

## Quién la ve

La página es para quienes dirigen el trabajo del almacén. Ves **Equipo** si eres supervisor de almacén, supervisor de dispatching o supervisor de goods in de ese almacén. También la ves si puedes editar HR en la organización. Los pickers y packers no la ven.

## Quién forma parte del equipo

Todas las personas que siguen trabajando, o que están de salida, y que tienen una posición de trabajo en el departamento de almacén de este almacén: pickers, packers, exception pickers, controladores de stock y supervisores. Una posición que no está ligada a ningún almacén cuenta para todos. Quien trabaja solo en otro almacén no aparece. Para añadir o quitar a una persona, cambia sus posiciones de trabajo en HR.

El picking y el packing se asignan a una persona a través de su cuenta de usuario. Quien no tiene cuenta de usuario en aiku aparece con horas pero sin picking ni packing, y la tabla de Personas muestra "no user" bajo su nombre.

## La pestaña Panel Control

Las horas siguen la zona horaria de la organización.

### El centro ahora

Seis contadores, uno por estado, y debajo la lista de personas. Pulsa un contador para ver solo a las personas en ese estado; púlsalo de nuevo para ver a todas.

- **On site** — han fichado la entrada en este momento, con la hora de entrada y hace cuánto.
- **Clocked out** — han estado hoy y ya han fichado la salida, con la hora y las horas trabajadas.
- **Not in yet** — les toca hoy según su horario de trabajo (el suyo, o el de la organización si no tienen) y la hora de inicio fue hace menos de 30 minutos.
- **Did not clock in** — les toca hoy, han pasado más de 30 minutos de su hora de inicio y no hay ningún fichaje.
- **On leave** — una ausencia aprobada cubre el día de hoy.
- **Day off** — hoy no es día laborable en su horario.

El **+** al final de una fila añade un fichaje para esa persona.

Una barra amarilla sobre los contadores lista las **entradas de días anteriores que nunca se cerraron** (los últimos 14 días). Quien olvidó fichar la salida se queda "en el centro" ese día para siempre y sus horas de ese día son erróneas. Pulsa el nombre para añadir la salida que falta; la ventana se abre en ese día.

### Trabajo pendiente del equipo

Albaranes de este almacén que esperan ahora mismo al equipo, con su número de artículos: **To pick** (sin asignar, en cola o en picking), **Blocked**, **To pack** (pickeados o en packing) y **Packed, waiting dispatch**. **Open goods out** te lleva al listado completo.

### Rendimiento

Elige **Hoy**, **Ayer**, **7 days** o **30 days**. Las tarjetas y la tabla de Personas siguen esa elección; el estado del centro y el trabajo pendiente siempre hablan de este momento. Cada tarjeta muestra la cifra del periodo, la misma cifra del periodo anterior (ayer, el día anterior, los 7 o 30 días previos) y la variación en porcentaje, en verde cuando se movió en el sentido bueno y en rojo cuando no.

- **Delivery notes picked** y **Delivery notes packed** — albaranes terminados en este almacén por personas del equipo.
- **Items handled** — artículos pickeados más artículos empaquetados.
- **Hours worked** — el tiempo fichado del equipo según sus timesheets, sin contar los descansos. Pasa el ratón para ver cuántas personas fichó.
- **Items per worked hour** — artículos gestionados divididos entre las horas trabajadas. Queda en blanco hasta que el equipo haya trabajado diez minutos.
- **Short picks** — líneas de picking que el picker no pudo completar, con el porcentaje que suponen sobre todas las líneas. Cuanto menos, mejor.
- **Late clock-ins** — fichajes marcados como tarde respecto al horario de trabajo. Cuanto menos, mejor.

Cuando todavía no se ha pickeado ni empaquetado nada hoy, una nota lo indica y da la última vez que se hizo.

### Los gráficos

- **Today by hour** — albaranes terminados por el equipo cada hora de hoy (barras), frente a la media del mismo día de la semana en las últimas cuatro semanas (líneas discontinuas). Te dice de un vistazo si el día va por delante o por detrás de uno normal.
- **Last 30 days** — albaranes pickeados y empaquetados por día.
- **Items per worked hour** — la productividad del equipo por día en los últimos 30 días, con las horas trabajadas cada día debajo, de modo que un día bajo con pocas horas se lee distinto de un día bajo con todo el equipo presente.

### Personas

Una fila por persona, ordenada por artículos por hora trabajada; pulsa la cabecera de una columna para ordenar de otro modo. Las personas sin nada registrado en el periodo están ocultas; el interruptor del encabezado las muestra.

- **En** y **Afuera** (un solo día) son el primer fichaje de entrada y el último de salida. "…" significa que sigue en el centro. Si el periodo abarca varios días, la tabla muestra en su lugar los **Días** trabajados.
- **Worked** es su tiempo fichado, sin contar los descansos.
- **Preparado** y **Empacado** son artículos. Pasa el ratón por encima del número para ver a cuántos albaranes corresponde.
- **Items/h** es la suma de artículos pickeados y empaquetados por hora trabajada, con una barra relativa al mejor del equipo. A un packer que también hace picking se le mide por ambos.
- **Short picks** — líneas que no pudo pickear. Pasa el ratón para ver el porcentaje sobre sus líneas de picking. Un 0 verde significa que pickeó sin ningún short.
- **Late** — cuántos de sus fichajes fueron tarde.

## La pestaña Marcaciones de tiempo

Un día cada vez. Usa las flechas, el selector de fecha o **Hoy** para moverte. La línea superior indica cuántas personas fichó ese día, las horas trabajadas, cuántos fichajes fueron tarde y cuántos se añadieron a mano. Solo se listan las personas con fichajes; el interruptor muestra a todas.

Cada persona tiene una fila con su tiempo trabajado y de descanso, y luego sus fichajes en orden: una flecha verde para una entrada, una gris para una salida. Un fichaje amarillo es uno tarde. Pasa el ratón por una hora para ver de dónde vino (la máquina de fichaje, o quién lo añadió a mano) y su nota. "No clock-out" en rojo bajo el nombre significa que su última entrada de ese día nunca se cerró.

El recuadro amarillo **Missing clock-outs from earlier days** lista cada entrada de los últimos 14 días que nunca se cerró. Pulsa una para añadir la salida en ese día.

### Añadir, cambiar o borrar un fichaje

- **Añadir** — pulsa **+** al final de los fichajes de una persona, **+** en una fila del Panel Control, o **Add clocking** arriba y elige a la persona. Comprueba la fecha y la hora (empieza en este momento, o en el día que estás viendo, y no puede estar en el futuro), añade una nota con el motivo, p. ej. "olvidó fichar la entrada", y pulsa **Add clocking**. Cada fichaje cambia a la persona entre fichada dentro y fichada fuera; la ventana te indica cuál será este.
- **Cambiar** — pulsa el lápiz de un fichaje y fija la nueva hora. El fichaje se queda en su día; solo cambia la hora, y se recalcula el tiempo de trabajo que abría o cerraba.
- **Borrar** — pulsa la papelera de un fichaje y confirma. Se recalcula el tiempo de trabajo a su alrededor. No se puede deshacer.

Un fichaje añadido o cambiado aquí equivale a uno hecho en HR: va al timesheet de ese día y queda registrado como hecho por ti, con tu nombre visible al pasar el ratón por encima.
