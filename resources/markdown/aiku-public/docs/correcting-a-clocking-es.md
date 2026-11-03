---
title: Corregir un fichaje
summary: Cuando una entrada o una salida está mal o falta, el empleado pide la corrección desde su propio parte de horas y RR. HH. la aprueba o la rechaza. Una corrección aprobada cambia el propio parte de horas.
date: 2026-11-03
source_date: 2026-11-03
tags: hr, clocking, attendance
category: hr
---

<aside class="tldr">
¿Se te olvidó fichar la salida, o fichaste tarde porque la máquina estaba ocupada? El empleado pide un <b>clocking adjustment</b> (ajuste de fichaje) desde su propia página <b>Employee Clocking</b>, indicando las horas correctas y un motivo. RR. HH. lo ve en <b>HR → Clocking adjustment</b> y lo aprueba o lo rechaza. Al aprobarlo, el parte de horas de ese día pasa a las horas pedidas; hasta entonces no cambia nada.
</aside>

## Pedir una corrección (empleado)

Todo está en tu página **Employee Clocking**. La pestaña que usas depende del día.

### Un día que ya tiene fichajes

1. Abre la pestaña **Timesheets** y busca el día.
2. Pulsa **Request correction** al final de la fila.
3. El cuadro **Request timesheet correction** muestra lo que quedó registrado (**Recorded**) y empieza con esas horas ya puestas. Cambia **Check-in time** (hora de entrada), **Check-out time** (hora de salida), o las dos.
4. Escribe el motivo en **Reason**. Es obligatorio.
5. Si tienes un justificante, añádelo en **Supporting Document (Optional)**: PDF, JPG o PNG, hasta 3 archivos de 5 MB cada uno.
6. Pulsa **Submit Request**.

La fila muestra ahora **Correction pending** en lugar del botón, hasta que RR. HH. decida.

### Un día sin ningún fichaje

Abre la pestaña **Clocking adjustment** y pulsa **Clocking adjustment**. Elige la fecha en **Date**, rellena la hora de entrada, la de salida o las dos, indica el motivo y envía.

### Qué puedes pedir y qué no

- Las solicitudes llegan como mucho **un mes** atrás, y nunca para una fecha futura.
- La hora de salida tiene que ser posterior a la de entrada.
- Una solicitud pendiente por día. Espera la decisión, o cancélala, antes de volver a pedir.
- No para un día cubierto por una ausencia aprobada.

### Seguir tu solicitud

La pestaña **Clocking adjustment** lista tus solicitudes con las horas originales, las horas pedidas y el estado. Mientras una solicitud está pendiente puedes retirarla con **Cancel request**. Si se rechaza, el motivo de RR. HH. aparece en rojo debajo del tuyo.

## Decidir una solicitud (RR. HH.)

Abre **HR → Clocking adjustment**. Cada solicitud es una tarjeta: el empleado, el día, las horas registradas (**Recorded**, o **No clockings** si no hay fichajes) junto a las horas pedidas (**Requested**), el motivo y los adjuntos. Las horas se muestran en la zona horaria de tu organización, que la tarjeta indica.

Encima de las tarjetas puedes buscar por empleado o motivo, elegir un estado (**All**, **Pending**, **Approved**, **Rejected**) y poner una fecha **From** (desde) y **To** (hasta). **Clear filters** los borra.

- **Approve** te pide confirmación y luego cambia el parte de horas de ese día: la primera entrada y la última salida pasan a las horas pedidas y las horas trabajadas se vuelven a calcular. Las pausas de en medio se quedan como estaban. En un día sin fichajes, se añaden la entrada y la salida que faltaban.
- **Reject** pide un motivo en **Reason for rejection**, que el empleado ve.

Una solicitud decidida muestra **Processed**, con quién la decidió y cuándo, y no se puede volver a decidir.

### Semanas de artesanos ya pagadas

Los artesanos de la planta de fábrica cobran por las horas que fichan, y el contable paga cada semana (de lunes a domingo) el martes siguiente. Por eso, en cuanto empieza ese martes, la semana queda **Locked for payroll**: RR. HH. ya no puede aprobar una corrección de esa semana, ni añadir, cambiar o borrar sus fichajes en el parte de horas. Solo pueden hacerlo el contable y el responsable de producción, y Aiku les pide el motivo. El cambio queda registrado, en la lista de **Factory** → **Artisans** → **Payroll changes**, y la diferencia de pago se paga o se descuenta en la siguiente ejecución de nómina. La semana en curso, y la semana pasada hasta el lunes a las 23:59, se pueden corregir como de costumbre. Los fichajes de los empleados que no trabajan en una planta de fábrica no se ven afectados.

### El panel

La pestaña **Dashboard** de la misma página es el mes de un vistazo: una fila por empleado, una columna por día, con una marca en cada día que tiene una solicitud, del color de su estado. Filtra por empleado o estado, avanza o retrocede por meses y pulsa una marca para ver los **Adjustment Details**.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Corregir un día en el que fichaste:</b> <b>Employee Clocking</b> → <b>Timesheets</b> → <b>Request correction</b>.</li>
<li><b>Añadir un día en el que no fichaste:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Clocking adjustment</b>.</li>
<li><b>Retirar una solicitud:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Cancel request</b>.</li>
<li><b>Aprobar o rechazar:</b> tu organización → <b>HR → Clocking adjustment</b>.</li>
<li><b>Ver el mes:</b> <b>HR → Clocking adjustment</b> → <b>Dashboard</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Pedir una corrección requiere una ficha de empleado vinculada a tu login de aiku. Solo puedes corregir tu propio parte de horas.</li>
<li>Ver las solicitudes y el panel requiere permisos de <b>HR view</b> en la organización.</li>
<li>Aprobar y rechazar requieren permisos de <b>HR edit</b>.</li>
</ul>
</aside>

Relacionado: <a href="/docs/reading-the-hr-dashboard-es">Cómo leer el panel de HR</a> para la asistencia del día, y <a href="/docs/setting-up-a-clocking-machine-es">Configurar una máquina de fichar</a> para saber de dónde salen los fichajes.
